<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Discount, Order};
use App\Services\AuditTrail;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscountController extends Controller
{
    private const TABS = [
        'all' => 'All Coupons',
        'rules' => 'Discount Rules',
        'campaigns' => 'Campaigns',
        'auto' => 'Auto Discounts',
        'usage' => 'Usage History',
        'approval' => 'Approval Center',
        'settings' => 'Settings',
    ];

    private const TYPES = [
        'percent' => 'Percentage',
        'fixed' => 'Fixed Amount',
        'free_shipping' => 'Free Shipping',
        'buy_x_get_y' => 'Buy X Get Y',
    ];

    private const ORDER_CATEGORIES = [
        'online' => 'Online Orders',
        'corporate' => 'Corporate Orders',
        'bulk' => 'Bulk Orders',
        'franchise' => 'Franchise Orders',
        'franchise_retail' => 'Franchise Retail Orders',
        'buyer' => 'Buyer Orders',
    ];

    public function index(Request $request): View
    {
        $tab = $request->string('tab')->toString();
        $tab = array_key_exists($tab, self::TABS) ? $tab : 'all';
        $discounts = Discount::query()->latest('created_at')->get();
        $orders = Order::query()->whereNotNull('discount_code')->latest('created_at')->get();
        $usageByCode = $this->usageByCode($orders);
        $rows = $this->filterRows(
            $discounts->map(fn (Discount $discount): object => $this->discountRow($discount, $usageByCode)),
            $request,
            $tab,
        );

        $metrics = $this->metrics($discounts, $orders);
        $types = $this->typeBreakdown($discounts);
        $statusDistribution = $this->statusDistribution($discounts);

        return view('admin.discounts-coupons.index', [
            'rows' => $this->paginate($rows, $request),
            'tabs' => self::TABS,
            'tab' => $tab,
            'search' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'type' => $request->string('type')->toString(),
            'category' => $request->string('category')->toString(),
            'customerGroup' => $request->string('customer_group')->toString(),
            'hasLiveData' => $discounts->isNotEmpty() || $orders->isNotEmpty(),
            'emptyStateMessage' => $discounts->isNotEmpty()
                ? 'No coupons match these filters.'
                : 'No live discount records have been created yet.',
            'metrics' => $metrics,
            'topPerforming' => $this->topPerforming($discounts, $usageByCode),
            'approval' => $this->approval($discounts),
            'types' => $types,
            'typeDonutStyle' => $this->donutStyle($types, ['#2573c6', '#f18a15', '#087943', '#7141a5', '#6a737b']),
            'usageTrend' => $this->usageTrend($orders),
            'categoryUsage' => $this->categoryUsage($orders),
            'impactBreakdown' => $this->impactBreakdown($discounts, $usageByCode),
            'statusDistribution' => $statusDistribution,
            'statusDonutStyle' => $this->donutStyle($statusDistribution, ['#087943', '#2573c6', '#f18a15', '#df3e52', '#6a737b']),
            'statusOptions' => ['active' => 'Active', 'scheduled' => 'Scheduled', 'expired' => 'Expired', 'inactive' => 'Inactive'],
            'typeOptions' => self::TYPES,
            'categoryOptions' => self::ORDER_CATEGORIES,
            'customerGroupOptions' => $this->customerGroupOptions($discounts),
        ]);
    }

    public function create(): View
    {
        return view('admin.discounts-coupons.form', ['discount' => null, 'types' => self::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $discount = Discount::create($this->normalise($data));
        AuditTrail::record('admin.discount.created', $discount, null, $discount->toArray());

        return redirect()->route('admin.discounts-coupons')->with('success', 'Discount / coupon created successfully.');
    }

    public function edit(Discount $discount): View
    {
        return view('admin.discounts-coupons.form', ['discount' => $discount, 'types' => self::TYPES]);
    }

    public function update(Request $request, Discount $discount): RedirectResponse
    {
        $data = $this->validated($request, $discount);
        $before = $discount->toArray();
        $discount->update($this->normalise($data, is_array($discount->metadata) ? $discount->metadata : []));
        AuditTrail::record('admin.discount.updated', $discount, $before, $discount->fresh()->toArray());

        return redirect()->route('admin.discounts-coupons')->with('success', 'Discount / coupon updated successfully.');
    }

    public function action(Request $request, Discount $discount): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:activate,pause,expire,duplicate,delete']]);
        $action = $data['action'];
        $discountId = $discount->getKey();
        $duplicate = null;

        DB::transaction(function () use ($action, $discountId, &$duplicate): void {
            $locked = Discount::query()->lockForUpdate()->find($discountId);
            if (! $locked) {
                throw (new ModelNotFoundException())->setModel(Discount::class, [$discountId]);
            }

            $before = $locked->toArray();
            if ($action === 'activate' && $locked->ends_at?->isPast()) {
                throw ValidationException::withMessages(['action' => 'An expired coupon must be edited with a new end date before it can be activated.']);
            }

            if ($action === 'duplicate') {
                $duplicate = $this->duplicate($locked);
                AuditTrail::record('admin.discount.duplicated', $duplicate, null, $duplicate->toArray());
            } else {
                match ($action) {
                    'activate' => $locked->update(['is_active' => true]),
                    'pause' => $locked->update(['is_active' => false]),
                    'expire' => $locked->update(['is_active' => false, 'ends_at' => now()]),
                    'delete' => $locked->delete(),
                };
                AuditTrail::record(
                    'admin.discount_'.$action,
                    $locked,
                    $before,
                    $action === 'delete' ? null : $locked->fresh()->toArray(),
                );
            }
        });

        return back()->with('success', match ($action) {
            'activate' => 'Coupon activated.',
            'pause' => 'Coupon paused.',
            'expire' => 'Coupon expired.',
            'duplicate' => 'Coupon duplicated with a new code.',
            'delete' => 'Coupon archived.',
        });
    }

    public function export(Request $request): StreamedResponse
    {
        $discounts = Discount::query()->latest('created_at')->get();
        $orders = Order::query()->whereNotNull('discount_code')->latest('created_at')->get();
        $usageByCode = $this->usageByCode($orders);
        $tab = $request->string('tab')->toString() ?: 'all';
        $rows = $this->filterRows(
            $discounts->map(fn (Discount $discount): object => $this->discountRow($discount, $usageByCode)),
            $request,
            array_key_exists($tab, self::TABS) ? $tab : 'all',
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Coupon / Code', 'UUID', 'Type', 'Applies to', 'Order categories', 'Customer group', 'Usage', 'Validity', 'Status', 'Discount value', 'Revenue impact']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->code,
                    $row->uuid,
                    $row->type_label,
                    $row->applies_to,
                    implode(', ', $row->order_categories),
                    $row->customer_group,
                    $row->usage,
                    $row->validity,
                    $row->status_label,
                    $row->value_label,
                    $row->impact,
                ]);
            }
            fclose($handle);
        }, 'discounts-coupons-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function validated(Request $request, ?Discount $discount = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('discounts', 'code')->ignore($discount?->id)->where(fn ($query) => $query->whereNull('deleted_at'))],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'value' => ['required_unless:type,buy_x_get_y', 'nullable', 'numeric', 'min:0'],
            'minimum_order' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'applies_to' => ['nullable', 'string', 'max:100'],
            'customer_group' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', 'string', 'size:3'],
            'order_categories' => ['nullable', 'array'],
            'order_categories.*' => [Rule::in(array_keys(self::ORDER_CATEGORIES))],
            'buy_quantity' => ['required_if:type,buy_x_get_y', 'nullable', 'integer', 'min:1', 'max:1000'],
            'get_quantity' => ['required_if:type,buy_x_get_y', 'nullable', 'integer', 'min:1', 'max:1000'],
            'product_skus' => ['nullable', 'string', 'max:4000'],
            'category_slugs' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($data['type'] === 'percent' && (float) $data['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'Percentage discounts cannot exceed 100%.']);
        }
        return $data;
    }

    private function normalise(array $data, array $existingMetadata = []): array
    {
        $metadata = $existingMetadata;
        foreach (['applies_to', 'customer_group', 'currency'] as $key) {
            if (array_key_exists($key, $data)) {
                if (filled($data[$key])) {
                    $metadata[$key] = $key === 'currency' ? strtoupper((string) $data[$key]) : trim((string) $data[$key]);
                } else {
                    unset($metadata[$key]);
                }
            }
        }
        if (array_key_exists('order_categories', $data)) {
            $metadata['order_categories'] = array_values(array_filter($data['order_categories'] ?: [], fn (mixed $key): bool => array_key_exists((string) $key, self::ORDER_CATEGORIES)));
        }
        foreach (['buy_quantity', 'get_quantity'] as $key) {
            if (($data['type'] ?? null) === 'buy_x_get_y' && array_key_exists($key, $data)) {
                $metadata[$key] = max(1, (int) $data[$key]);
            } else {
                unset($metadata[$key]);
            }
        }
        foreach ([
            'product_skus' => 'product_skus',
            'category_slugs' => 'category_slugs',
        ] as $input => $metadataKey) {
            if (($data['type'] ?? null) === 'buy_x_get_y' && array_key_exists($input, $data)) {
                $metadata[$metadataKey] = collect(preg_split('/[\s,]+/', (string) ($data[$input] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
                    ->map(fn (string $value): string => $metadataKey === 'product_skus' ? strtoupper(trim($value)) : strtolower(trim($value)))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
            } else {
                unset($metadata[$metadataKey]);
            }
        }

        return [
            'code' => strtoupper(trim($data['code'])),
            'type' => $data['type'],
            'value' => (float) ($data['value'] ?? 0),
            'minimum_order' => (float) ($data['minimum_order'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'metadata' => $metadata ?: null,
        ];
    }

    private function duplicate(Discount $discount): Discount
    {
        $code = substr((string) $discount->code, 0, 47).'-'.strtoupper(Str::random(6));

        return Discount::create([
            'code' => $code,
            'type' => $discount->type,
            'value' => $discount->value,
            'minimum_order' => $discount->minimum_order,
            'starts_at' => $discount->starts_at,
            'ends_at' => $discount->ends_at,
            'usage_limit' => $discount->usage_limit,
            'is_active' => false,
            'metadata' => $discount->metadata,
        ]);
    }

    private function usageByCode(Collection $orders): Collection
    {
        return $orders
            ->filter(fn (Order $order): bool => filled($order->discount_code))
            ->groupBy(fn (Order $order): string => strtoupper((string) $order->discount_code));
    }

    private function filterRows(Collection $rows, Request $request, string $tab): Collection
    {
        $query = mb_strtolower(trim($request->string('q')->toString()));
        $status = mb_strtolower($request->string('status')->toString());
        $type = mb_strtolower($request->string('type')->toString());
        $category = mb_strtolower($request->string('category')->toString());
        $customerGroup = mb_strtolower($request->string('customer_group')->toString());

        return $rows->filter(function (object $row) use ($query, $status, $type, $category, $customerGroup, $tab): bool {
            if (! $this->tabMatches($row, $tab)) {
                return false;
            }
            if ($status && $row->status !== $status) {
                return false;
            }
            if ($type && $row->type !== $type) {
                return false;
            }
            if ($category && ! in_array($category, $row->category_keys, true)) {
                return false;
            }
            if ($customerGroup && mb_strtolower($row->customer_group_key) !== $customerGroup) {
                return false;
            }

            return $query === '' || str_contains(mb_strtolower(implode(' ', [
                $row->code,
                $row->uuid,
                $row->type_label,
                $row->applies_to,
                $row->customer_group,
            ])), $query);
        })->sortByDesc('sort_timestamp')->values();
    }

    private function tabMatches(object $row, string $tab): bool
    {
        return match ($tab) {
            'rules' => in_array($row->type, ['percent', 'fixed'], true),
            'auto' => $row->applies_to === 'Cart Subtotal',
            'usage' => $row->used > 0,
            'approval' => in_array($row->status, ['scheduled', 'inactive', 'expired'], true),
            'campaigns', 'settings', 'all' => true,
            default => true,
        };
    }

    private function discountRow(Discount $discount, Collection $usageByCode): object
    {
        $status = $this->status($discount);
        $code = strtoupper((string) $discount->code);
        $orders = $usageByCode->get($code, collect());
        $metadata = is_array($discount->metadata) ? $discount->metadata : [];
        $used = (int) $discount->used;
        $appliedUsage = $orders->count();
        $categoryKeys = $this->categoryKeys($metadata, $orders);
        $customerGroup = $this->metadataLabel($metadata, 'customer_group');
        $appliesTo = $this->metadataLabel($metadata, 'applies_to')
            ?: match ($discount->type) {
                'free_shipping' => 'Shipping Method',
                'percent', 'fixed' => 'Cart Subtotal',
                default => 'Not recorded',
            };
        $currency = strtoupper((string) ($metadata['currency'] ?? 'EUR'));
        $impact = (float) $orders->sum('discount');
        $created = $this->timestamp($discount->created_at);

        return (object) [
            'discount_id' => $discount->id,
            'code' => $code,
            'uuid' => $discount->public_uuid ?: 'Not assigned',
            'type' => (string) $discount->type,
            'type_label' => self::TYPES[$discount->type] ?? Str::headline((string) $discount->type),
            'applies_to' => $appliesTo,
            'category_keys' => $categoryKeys,
            'order_categories' => array_map(fn (string $key): string => self::ORDER_CATEGORIES[$key] ?? Str::headline($key), $categoryKeys),
            'customer_group_key' => $customerGroup ? mb_strtolower($customerGroup) : 'not recorded',
            'customer_group' => $customerGroup ?: 'Not recorded',
            'usage' => $discount->usage_limit !== null
                ? number_format($discount->usage_limit).' / '.number_format($used)
                : 'Unlimited / '.number_format($used),
            'used' => $used,
            'applied_usage' => $appliedUsage,
            'usage_source' => $appliedUsage > 0 ? 'Applied order records' : 'Stored coupon counter',
            'usage_limit' => $discount->usage_limit,
            'validity' => ($discount->starts_at?->format('d M Y') ?: 'Any time').' — '.($discount->ends_at?->format('d M Y') ?: 'No expiry'),
            'status' => $status,
            'status_label' => Str::headline($status),
            'value_label' => $this->valueLabel($discount, $metadata),
            'impact' => $this->moneyLabel($impact, $this->singleCurrency($orders, $currency)),
            'sort_timestamp' => $created ?? 0,
            'actionable' => true,
        ];
    }

    private function status(Discount $discount): string
    {
        if (! $discount->is_active) {
            return 'inactive';
        }
        if ($discount->starts_at?->isFuture()) {
            return 'scheduled';
        }
        if ($discount->ends_at?->isPast()) {
            return 'expired';
        }

        return 'active';
    }

    private function valueLabel(Discount $discount, array $metadata): string
    {
        $currency = strtoupper((string) ($metadata['currency'] ?? 'EUR'));
        $symbol = $this->currencySymbol($currency);

        return match ($discount->type) {
            'percent' => number_format((float) $discount->value, 0).'% OFF',
            'fixed' => $symbol.number_format((float) $discount->value, 2).' OFF',
            'free_shipping' => 'Free Shipping',
            'buy_x_get_y' => 'Buy X Get Y',
            default => number_format((float) $discount->value, 2),
        };
    }

    private function categoryKeys(array $metadata, Collection $orders): array
    {
        $metadataKeys = is_array($metadata['order_categories'] ?? null) ? $metadata['order_categories'] : [];
        $usedKeys = $orders->pluck('order_type')->filter(fn (mixed $key): bool => array_key_exists((string) $key, self::ORDER_CATEGORIES))->all();

        return array_values(array_unique(array_merge(
            array_filter(array_map('strval', $metadataKeys), fn (string $key): bool => array_key_exists($key, self::ORDER_CATEGORIES)),
            array_map('strval', $usedKeys),
        )));
    }

    private function metadataLabel(array $metadata, string $key): ?string
    {
        $value = $metadata[$key] ?? null;
        return is_scalar($value) && trim((string) $value) !== '' ? Str::headline(trim((string) $value)) : null;
    }

    private function metrics(Collection $discounts, Collection $orders): array
    {
        $active = $discounts->filter(fn (Discount $discount): bool => $this->status($discount) === 'active')->count();
        $usage = (int) $discounts->sum('used');
        $discountValue = (float) $orders->sum('discount');
        $average = (float) ($discounts->where('type', 'percent')->avg('value') ?: 0);
        $revenue = (float) $orders->sum('total');
        $source = $discounts->isNotEmpty() || $orders->isNotEmpty() ? 'Live database value' : 'No discount data recorded';
        $discountCurrency = $this->singleCurrency($orders, 'EUR');

        return [
            ['label' => 'Total Coupons', 'value' => number_format($discounts->count()), 'icon' => 'tag', 'tone' => 'green', 'source' => $source],
            ['label' => 'Active Coupons', 'value' => number_format($active), 'icon' => 'clipboard', 'tone' => 'purple', 'source' => $source],
            ['label' => 'Total Usage', 'value' => number_format($usage), 'icon' => 'users', 'tone' => 'orange', 'source' => $source],
            ['label' => 'Discount Value', 'value' => $this->moneyLabel($discountValue, $discountCurrency), 'icon' => 'credit-card', 'tone' => 'teal', 'source' => $source],
            ['label' => 'Avg. Discount', 'value' => number_format($average, 1).'%', 'icon' => 'percent', 'tone' => 'red', 'source' => $source],
            ['label' => 'Revenue Impact', 'value' => $this->moneyLabel($revenue, $this->singleCurrency($orders, 'EUR')), 'icon' => 'shopping-bag', 'tone' => 'blue', 'source' => $source],
        ];
    }

    private function topPerforming(Collection $discounts, Collection $usageByCode): array
    {
        $rows = $discounts->map(function (Discount $discount) use ($usageByCode): array {
            $orders = $usageByCode->get(strtoupper((string) $discount->code), collect());
            $impact = (float) $orders->sum('discount');

            return [
                'code' => (string) $discount->code,
                'impact_amount' => $impact,
                'impact' => $this->moneyLabel($impact, $this->singleCurrency($orders, 'EUR')),
            ];
        })->filter(fn (array $item): bool => $item['impact_amount'] > 0)->sortByDesc('impact_amount')->values();
        $total = (float) $rows->sum('impact_amount');

        return $rows->take(5)->map(function (array $item) use ($total): array {
            $item['percent'] = $total > 0 ? number_format(($item['impact_amount'] / $total) * 100, 1).'%' : '0%';
            return $item;
        })->all();
    }

    private function approval(Collection $discounts): array
    {
        $total = $discounts->count();
        $percent = fn (int $count): string => $total > 0 ? number_format(($count / $total) * 100, 1).'%' : '0%';
        $active = $discounts->filter(fn (Discount $discount): bool => $this->status($discount) === 'active')->count();
        $scheduled = $discounts->filter(fn (Discount $discount): bool => $this->status($discount) === 'scheduled')->count();
        $inactive = $discounts->filter(fn (Discount $discount): bool => $this->status($discount) === 'inactive')->count();
        $expired = $discounts->filter(fn (Discount $discount): bool => $this->status($discount) === 'expired')->count();

        return [
            'total' => number_format($total),
            'approved' => number_format($active).' ('.$percent($active).')',
            'pending' => number_format($scheduled).' ('.$percent($scheduled).')',
            'rejected' => 'Not recorded',
            'draft' => number_format($inactive).' ('.$percent($inactive).')',
            'expired' => number_format($expired).' ('.$percent($expired).')',
            'source' => $total > 0 ? 'Live coupon status' : 'No discount data recorded',
        ];
    }

    private function typeBreakdown(Collection $discounts): array
    {
        $total = $discounts->count();
        if ($total === 0) {
            return [];
        }

        return $discounts->countBy('type')->sortDesc()->map(function (int $count, string $type) use ($total): array {
            return [
                'label' => self::TYPES[$type] ?? Str::headline($type),
                'count' => $count,
                'percent' => round(($count / $total) * 100, 1),
                'value' => number_format($count).' ('.number_format(($count / $total) * 100, 1).'%)',
            ];
        })->values()->all();
    }

    private function statusDistribution(Collection $discounts): array
    {
        $counts = $discounts->map(fn (Discount $discount): string => $this->status($discount))->countBy()->sortDesc();
        $total = $discounts->count();
        if ($total === 0) {
            return [];
        }

        return $counts->map(fn (int $count, string $status): array => [
            'label' => Str::headline($status),
            'status' => $status,
            'count' => $count,
            'percent' => round(($count / $total) * 100, 1),
        ])->values()->all();
    }

    private function usageTrend(Collection $orders): array
    {
        $months = collect(range(5, 0))->map(fn (int $offset): Carbon => now()->copy()->subMonthsNoOverflow($offset)->startOfMonth());
        $counts = $orders->filter(fn (Order $order): bool => $order->created_at !== null)
            ->groupBy(fn (Order $order): string => $order->created_at->format('Y-m'));
        $values = $months->map(fn (Carbon $month): int => $counts->get($month->format('Y-m'), collect())->count());
        $max = max(1, (int) $values->max());

        if ($values->sum() === 0) {
            return [];
        }

        return $months->map(function (Carbon $month, int $index) use ($values, $max): array {
            $count = $values[$index];
            return [
                'label' => $month->format('M'),
                'count' => $count,
                'percent' => round(($count / $max) * 100, 1),
            ];
        })->all();
    }

    private function categoryUsage(Collection $orders): array
    {
        $values = $orders->pluck('order_type')->filter(fn (mixed $type): bool => array_key_exists((string) $type, self::ORDER_CATEGORIES));
        $total = $values->count();
        if ($total === 0) {
            return [];
        }
        $counts = $values->countBy()->sortDesc();
        $max = max(1, (int) $counts->max());

        return $counts->map(fn (int $count, string $type): array => [
            'label' => self::ORDER_CATEGORIES[$type] ?? Str::headline($type),
            'count' => $count,
            'percent' => round(($count / $total) * 100, 1),
            'bar_percent' => round(($count / $max) * 100, 1),
        ])->values()->all();
    }

    private function impactBreakdown(Collection $discounts, Collection $usageByCode): array
    {
        $rows = $discounts->map(function (Discount $discount) use ($usageByCode): array {
            $orders = $usageByCode->get(strtoupper((string) $discount->code), collect());
            return [
                'label' => self::TYPES[$discount->type] ?? Str::headline((string) $discount->type),
                'type' => (string) $discount->type,
                'amount' => (float) $orders->sum('discount'),
                'currency' => $this->singleCurrency($orders, 'EUR'),
            ];
        })->filter(fn (array $row): bool => $row['amount'] > 0);
        $grouped = $rows->groupBy('type')->map(function (Collection $matches): array {
            $currencies = $matches->pluck('currency')->filter()->unique()->values();
            return [
                'label' => $matches->first()['label'],
                'amount' => (float) $matches->sum('amount'),
                'currency' => $currencies->count() > 1 ? 'MIXED' : (string) ($currencies->first() ?: 'EUR'),
            ];
        })->sortByDesc('amount')->values();
        $total = (float) $grouped->sum('amount');

        return $grouped->map(function (array $row) use ($total): array {
            $row['amount_label'] = $this->moneyLabel($row['amount'], $row['currency']);
            $row['percent'] = $total > 0 ? round(($row['amount'] / $total) * 100, 1) : 0;
            return $row;
        })->all();
    }

    private function customerGroupOptions(Collection $discounts): array
    {
        return $discounts
            ->map(fn (Discount $discount): ?string => $this->metadataLabel(is_array($discount->metadata) ? $discount->metadata : [], 'customer_group'))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $label): array => [mb_strtolower($label) => $label])
            ->all();
    }

    private function donutStyle(array $breakdown, array $colors): string
    {
        if ($breakdown === []) {
            return 'conic-gradient(#e7ece9 0 100%)';
        }

        $segments = [];
        $cursor = 0.0;
        $last = count($breakdown) - 1;
        foreach ($breakdown as $index => $row) {
            $end = $index === $last ? 100.0 : min(100.0, $cursor + (float) $row['percent']);
            $segments[] = $colors[$index % count($colors)].' '.$cursor.'% '.$end.'%';
            $cursor = $end;
        }

        return 'conic-gradient('.implode(',', $segments).')';
    }

    private function singleCurrency(Collection $orders, string $fallback): string
    {
        $currencies = $orders->map(fn (Order $order): string => strtoupper((string) ($order->currency_code ?: $order->currency ?: $fallback)))->unique()->values();
        return $currencies->count() === 1 ? (string) $currencies->first() : ($currencies->count() > 1 ? 'MIXED' : $fallback);
    }

    private function moneyLabel(float $amount, string $currency): string
    {
        if ($currency === 'MIXED') {
            return 'Multiple currencies';
        }

        return $this->currencySymbol($currency).number_format($amount, 2);
    }

    private function currencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'EUR' => '€',
            'GBP' => '£',
            'USD' => '$',
            'CAD' => 'CA$',
            'AUD' => 'A$',
            default => strtoupper($currency).' ',
        };
    }

    private function timestamp(mixed $value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        return null;
    }

    private function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 8)));
        $page = max(1, (int) $request->query('page', 1));

        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }
}
