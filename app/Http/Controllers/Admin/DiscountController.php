<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Discount, Order};
use App\Services\AuditTrail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Pagination\LengthAwarePaginator;
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
        $discounts = Discount::query()->latest('created_at')->limit(500)->get();
        $orders = Order::query()->whereNotNull('discount_code')->latest('created_at')->limit(1000)->get();
        $usageByCode = $orders->filter(fn (Order $order) => filled($order->discount_code))
            ->groupBy(fn (Order $order) => strtoupper((string) $order->discount_code));
        $preview = $discounts->isEmpty();
        $rows = $preview
            ? $this->filterRows($this->demoRows(), $request, $tab)
            : $this->filterRows($discounts->map(fn (Discount $discount) => $this->discountRow($discount, $usageByCode)), $request, $tab);

        return view('admin.discounts-coupons.index', [
            'rows' => $this->paginate($rows, $request),
            'tabs' => self::TABS,
            'tab' => $tab,
            'search' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'type' => $request->string('type')->toString(),
            'category' => $request->string('category')->toString(),
            'customerGroup' => $request->string('customer_group')->toString(),
            'preview' => $preview,
            'metrics' => $this->metrics($discounts, $orders, $preview),
            'topPerforming' => $this->topPerforming($discounts, $usageByCode, $preview),
            'approval' => $this->approval($discounts, $preview),
            'types' => $this->typeBreakdown($discounts, $preview),
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
        $discount->update($this->normalise($data));
        AuditTrail::record('admin.discount.updated', $discount, $before, $discount->fresh()->toArray());

        return redirect()->route('admin.discounts-coupons')->with('success', 'Discount / coupon updated successfully.');
    }

    public function action(Request $request, Discount $discount): RedirectResponse
    {
        $data = $request->validate(['action' => ['required', 'in:activate,pause,expire,duplicate,delete']]);
        $before = $discount->toArray();

        DB::transaction(function () use ($discount, $data, $before): void {
            match ($data['action']) {
                'activate' => $discount->update(['is_active' => true]),
                'pause' => $discount->update(['is_active' => false]),
                'expire' => $discount->update(['is_active' => false, 'ends_at' => now()]),
                'duplicate' => $this->duplicate($discount),
                'delete' => $discount->delete(),
            };
            AuditTrail::record('admin.discount_'.$data['action'], $discount, $before, $data['action'] === 'delete' ? null : $discount->fresh()->toArray());
        });

        $messages = [
            'activate' => 'Coupon activated.',
            'pause' => 'Coupon paused.',
            'expire' => 'Coupon expired.',
            'duplicate' => 'Coupon duplicated with a new code.',
            'delete' => 'Coupon deleted.',
        ];

        return back()->with('success', $messages[$data['action']]);
    }

    public function export(Request $request): StreamedResponse
    {
        $discounts = Discount::query()->latest('created_at')->limit(1000)->get();
        $orders = Order::query()->whereNotNull('discount_code')->latest('created_at')->limit(2000)->get();
        $usageByCode = $orders->filter(fn (Order $order) => filled($order->discount_code))
            ->groupBy(fn (Order $order) => strtoupper((string) $order->discount_code));
        $tab = $request->string('tab')->toString() ?: 'all';
        $rows = $this->filterRows($discounts->map(fn (Discount $discount) => $this->discountRow($discount, $usageByCode)), $request, array_key_exists($tab, self::TABS) ? $tab : 'all');

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Coupon / Code', 'UUID', 'Type', 'Applies to', 'Order categories', 'Customer group', 'Usage', 'Validity', 'Status', 'Discount value', 'Revenue impact']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row->code, $row->uuid, $row->type_label, $row->applies_to, implode(', ', $row->order_categories), $row->customer_group, $row->usage, $row->validity, $row->status_label, $row->value_label, $row->impact]);
            }
            fclose($handle);
        }, 'discounts-coupons-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function validated(Request $request, ?Discount $discount = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('discounts', 'code')->ignore($discount?->id)],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'value' => ['required', 'numeric', 'min:0'],
            'minimum_order' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($data['type'] === 'percent' && (float) $data['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'Percentage discounts cannot exceed 100%.']);
        }

        return $data;
    }

    private function normalise(array $data): array
    {
        return [
            'code' => strtoupper(trim($data['code'])),
            'type' => $data['type'],
            'value' => (float) $data['value'],
            'minimum_order' => (float) ($data['minimum_order'] ?? 0),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ];
    }

    private function duplicate(Discount $discount): Discount
    {
        $code = substr($discount->code, 0, 47).'-'.strtoupper(Str::random(6));
        return Discount::create([
            'code' => $code,
            'type' => $discount->type,
            'value' => $discount->value,
            'minimum_order' => $discount->minimum_order,
            'starts_at' => $discount->starts_at,
            'ends_at' => $discount->ends_at,
            'usage_limit' => $discount->usage_limit,
            'is_active' => false,
        ]);
    }

    private function filterRows(Collection $rows, Request $request, string $tab): Collection
    {
        $query = mb_strtolower(trim($request->string('q')->toString()));
        $status = mb_strtolower($request->string('status')->toString());
        $type = mb_strtolower($request->string('type')->toString());
        $category = mb_strtolower($request->string('category')->toString());
        $customerGroup = mb_strtolower($request->string('customer_group')->toString());

        return $rows->filter(function (object $row) use ($query, $status, $type, $category, $customerGroup, $tab): bool {
            if (!$this->tabMatches($row, $tab)) {
                return false;
            }
            if ($status && $row->status !== $status) {
                return false;
            }
            if ($type && $row->type !== $type) {
                return false;
            }
            if ($category && !in_array($category, $row->category_keys, true)) {
                return false;
            }
            if ($customerGroup && mb_strtolower($row->customer_group_key) !== $customerGroup) {
                return false;
            }

            return $query === '' || str_contains(mb_strtolower(implode(' ', [$row->code, $row->uuid, $row->type_label, $row->applies_to, $row->customer_group])), $query);
        })->sortByDesc('sort_timestamp')->values();
    }

    private function tabMatches(object $row, string $tab): bool
    {
        return match ($tab) {
            'rules' => in_array($row->type, ['percent', 'fixed'], true),
            'auto' => $row->applies_to === 'Cart Subtotal',
            'usage' => $row->used > 0,
            'approval' => $row->status !== 'active',
            'campaigns' => true,
            'settings', 'all' => true,
            default => true,
        };
    }

    private function discountRow(Discount $discount, Collection $usageByCode): object
    {
        $status = $this->status($discount);
        $code = strtoupper((string) $discount->code);
        $orders = $usageByCode->get($code, collect());
        $used = (int) $discount->used;
        $categoryKey = $this->categoryKey($code);
        $categoryKeys = array_keys(self::ORDER_CATEGORIES);
        $created = $discount->created_at ?: now();

        return (object) [
            'discount_id' => $discount->id,
            'code' => $code,
            'uuid' => $discount->public_uuid ?: 'CP-'.$discount->id,
            'type' => (string) $discount->type,
            'type_label' => self::TYPES[$discount->type] ?? Str::headline((string) $discount->type),
            'applies_to' => $discount->type === 'free_shipping' ? 'Shipping Method' : (str_contains($code, 'WELCOME') ? 'Cart Subtotal' : 'Products'),
            'category_keys' => $categoryKeys,
            'order_categories' => array_values(self::ORDER_CATEGORIES),
            'customer_group_key' => $categoryKey === 'franchise' ? 'franchisees' : ($categoryKey === 'buyer' ? 'buyers' : 'all'),
            'customer_group' => $categoryKey === 'franchise' ? 'Franchisees' : ($categoryKey === 'buyer' ? 'Buyers' : (str_contains($code, 'WELCOME') ? 'New Customers' : 'All Customers')),
            'usage' => $discount->usage_limit ? number_format($discount->usage_limit).' / '.number_format($used) : 'Unlimited'.($used ? ', '.number_format($used) : ''),
            'used' => $used,
            'usage_limit' => $discount->usage_limit,
            'validity' => ($discount->starts_at?->format('d M Y') ?: 'Any time').' — '.($discount->ends_at?->format('d M Y') ?: 'No expiry'),
            'status' => $status,
            'status_label' => Str::headline($status),
            'value_label' => $this->valueLabel($discount),
            'impact' => '€'.number_format((float) $orders->sum('discount'), 2),
            'sort_timestamp' => $created->timestamp,
            'actionable' => true,
        ];
    }

    private function status(Discount $discount): string
    {
        if (!$discount->is_active) {
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

    private function valueLabel(Discount $discount): string
    {
        return match ($discount->type) {
            'percent' => number_format((float) $discount->value, 0).'% OFF',
            'fixed' => '€'.number_format((float) $discount->value, 2).' OFF',
            'free_shipping' => 'Free Shipping',
            'buy_x_get_y' => 'Buy X Get Y',
            default => number_format((float) $discount->value, 2),
        };
    }

    private function categoryKey(string $code): string
    {
        return match (true) {
            str_contains($code, 'FRANCHISE') || str_contains($code, 'RETAIL') => 'franchise',
            str_contains($code, 'BUYER') => 'buyer',
            str_contains($code, 'BULK') => 'bulk',
            str_contains($code, 'CORP') => 'corporate',
            default => 'online',
        };
    }

    private function metrics(Collection $discounts, Collection $orders, bool $preview): array
    {
        if ($preview) {
            return [
                ['label' => 'Total Coupons', 'value' => '156', 'icon' => 'tag', 'tone' => 'green', 'change' => '12.1%', 'direction' => 'up'],
                ['label' => 'Active Coupons', 'value' => '98', 'icon' => 'clipboard', 'tone' => 'purple', 'change' => '8.2%', 'direction' => 'up'],
                ['label' => 'Total Usage', 'value' => '24,875', 'icon' => 'users', 'tone' => 'orange', 'change' => '15.3%', 'direction' => 'up'],
                ['label' => 'Discount Value', 'value' => '€82,765.40', 'icon' => 'credit-card', 'tone' => 'teal', 'change' => '18.6%', 'direction' => 'up'],
                ['label' => 'Avg. Discount', 'value' => '19.6%', 'icon' => 'percent', 'tone' => 'red', 'change' => '2.4%', 'direction' => 'up'],
                ['label' => 'Revenue Impact', 'value' => '€428,765.75', 'icon' => 'shopping-bag', 'tone' => 'blue', 'change' => '17.9%', 'direction' => 'up'],
            ];
        }

        $active = $discounts->filter(fn (Discount $discount) => $this->status($discount) === 'active')->count();
        $usage = (int) $discounts->sum('used');
        $discountValue = (float) $orders->sum('discount');
        $average = (float) ($discounts->where('type', 'percent')->avg('value') ?: 0);
        $revenue = (float) $orders->sum('total');

        return [
            ['label' => 'Total Coupons', 'value' => number_format($discounts->count()), 'icon' => 'tag', 'tone' => 'green', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Active Coupons', 'value' => number_format($active), 'icon' => 'clipboard', 'tone' => 'purple', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Total Usage', 'value' => number_format($usage), 'icon' => 'users', 'tone' => 'orange', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Discount Value', 'value' => '€'.number_format($discountValue, 2), 'icon' => 'credit-card', 'tone' => 'teal', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Avg. Discount', 'value' => number_format($average, 1).'%', 'icon' => 'percent', 'tone' => 'red', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Revenue Impact', 'value' => '€'.number_format($revenue, 2), 'icon' => 'shopping-bag', 'tone' => 'blue', 'change' => '—', 'direction' => 'up'],
        ];
    }

    private function topPerforming(Collection $discounts, Collection $usageByCode, bool $preview): array
    {
        if ($preview) {
            return [['code' => 'ER100OFF', 'impact' => '€12,540.30', 'percent' => '15.2%'], ['code' => 'FRANCHISE7', 'impact' => '€7,865.10', 'percent' => '9.5%'], ['code' => 'FREESHIP', 'impact' => '€6,230.80', 'percent' => '7.5%'], ['code' => 'RETAILSTORES8', 'impact' => '€6,420.70', 'percent' => '7.8%'], ['code' => 'WELCOME15', 'impact' => '€9,875.60', 'percent' => '11.9%']];
        }
        return $discounts->map(function (Discount $discount) use ($usageByCode): array {
            $orders = $usageByCode->get(strtoupper((string) $discount->code), collect());
            return ['code' => $discount->code, 'impact' => '€'.number_format((float) $orders->sum('discount'), 2), 'percent' => $discount->used ? number_format(($discount->used / max(1, $discount->usage_limit ?: $discount->used)) * 100, 1).'%' : '0%'];
        })->sortByDesc(fn (array $item) => (float) str_replace([',', '€'], '', $item['impact']))->take(5)->values()->all();
    }

    private function approval(Collection $discounts, bool $preview): array
    {
        if ($preview) {
            return ['total' => '156', 'approved' => '112 (71.8%)', 'pending' => '18 (11.5%)', 'rejected' => '7 (4.5%)', 'draft' => '19 (12.2%)'];
        }
        $total = max(1, $discounts->count());
        $approved = $discounts->filter(fn (Discount $discount) => $this->status($discount) === 'active')->count();
        $pending = $discounts->filter(fn (Discount $discount) => $this->status($discount) === 'scheduled')->count();
        $draft = $discounts->where('is_active', false)->count();
        $percent = fn (int $count) => number_format(($count / $total) * 100, 1).'%';
        return ['total' => number_format($discounts->count()), 'approved' => number_format($approved).' ('.$percent($approved).')', 'pending' => number_format($pending).' ('.$percent($pending).')', 'rejected' => '0 (0%)', 'draft' => number_format($draft).' ('.$percent($draft).')'];
    }

    private function typeBreakdown(Collection $discounts, bool $preview): array
    {
        if ($preview) {
            return [['label' => 'Percentage', 'value' => '98 (62.8%)', 'tone' => 'blue'], ['label' => 'Fixed Amount', 'value' => '32 (20.5%)', 'tone' => 'orange'], ['label' => 'Free Shipping', 'value' => '14 (9.0%)', 'tone' => 'green'], ['label' => 'Buy X Get Y', 'value' => '8 (5.1%)', 'tone' => 'purple'], ['label' => 'Other', 'value' => '4 (2.6%)', 'tone' => 'dark']];
        }
        $total = max(1, $discounts->count());
        return $discounts->countBy('type')->sortDesc()->map(fn ($count, $type) => ['label' => self::TYPES[$type] ?? Str::headline($type), 'value' => number_format($count).' ('.number_format(($count / $total) * 100, 1).'%)', 'tone' => 'blue'])->values()->all();
    }

    private function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 8)));
        $page = max(1, (int) $request->query('page', 1));
        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);
    }

    private function demoRows(): Collection
    {
        $categories = array_keys(self::ORDER_CATEGORIES);
        return collect([
            ['code' => 'ER100OFF', 'uuid' => 'CP-ER10-00001', 'type' => 'percent', 'type_label' => 'Percentage', 'applies_to' => 'Products', 'customer_group' => 'All Customers', 'customer_group_key' => 'all', 'usage' => 'Unlimited, 5,245', 'used' => 5245, 'category_keys' => $categories, 'order_categories' => array_values(self::ORDER_CATEGORIES), 'validity' => '01 Apr 2025 — 31 May 2025', 'status' => 'active', 'status_label' => 'Active', 'value_label' => '10% OFF', 'impact' => '€12,540.30', 'sort_timestamp' => 8, 'discount_id' => null, 'actionable' => false],
            ['code' => 'WELCOME15', 'uuid' => 'CP-WELC-00002', 'type' => 'percent', 'type_label' => 'Percentage', 'applies_to' => 'Cart Subtotal', 'customer_group' => 'New Customers', 'customer_group_key' => 'new customers', 'usage' => '1,000 / 756', 'used' => 756, 'category_keys' => $categories, 'order_categories' => array_values(self::ORDER_CATEGORIES), 'validity' => '15 Apr 2025 — 15 Jun 2025', 'status' => 'active', 'status_label' => 'Active', 'value_label' => '15% OFF', 'impact' => '€9,875.60', 'sort_timestamp' => 7, 'discount_id' => null, 'actionable' => false],
            ['code' => 'FREESHIP', 'uuid' => 'CP-FS-00003', 'type' => 'free_shipping', 'type_label' => 'Free Shipping', 'applies_to' => 'Shipping Method', 'customer_group' => 'All Customers', 'customer_group_key' => 'all', 'usage' => 'Unlimited, 3,652', 'used' => 3652, 'category_keys' => $categories, 'order_categories' => array_values(self::ORDER_CATEGORIES), 'validity' => '01 Apr 2025 — 31 May 2025', 'status' => 'active', 'status_label' => 'Active', 'value_label' => 'Free Shipping', 'impact' => '€6,230.80', 'sort_timestamp' => 6, 'discount_id' => null, 'actionable' => false],
            ['code' => 'BULK5OFF', 'uuid' => 'CP-BULK-00004', 'type' => 'percent', 'type_label' => 'Percentage', 'applies_to' => 'Order Subtotal', 'customer_group' => 'Bulk Buyers', 'customer_group_key' => 'bulk buyers', 'usage' => '500 / 210', 'used' => 210, 'category_keys' => ['bulk'], 'order_categories' => ['Bulk Orders'], 'validity' => '10 Apr 2025 — 10 Jun 2025', 'status' => 'active', 'status_label' => 'Active', 'value_label' => '5% OFF', 'impact' => '€4,205.25', 'sort_timestamp' => 5, 'discount_id' => null, 'actionable' => false],
            ['code' => 'FRANCHISE7', 'uuid' => 'CP-FRAN-00005', 'type' => 'percent', 'type_label' => 'Percentage', 'applies_to' => 'Order Subtotal', 'customer_group' => 'Franchisees', 'customer_group_key' => 'franchisees', 'usage' => 'Unlimited, 1,845', 'used' => 1845, 'category_keys' => ['franchise'], 'order_categories' => ['Franchise Orders'], 'validity' => '01 Apr 2025 — 30 Jun 2025', 'status' => 'active', 'status_label' => 'Active', 'value_label' => '7% OFF', 'impact' => '€7,865.10', 'sort_timestamp' => 4, 'discount_id' => null, 'actionable' => false],
            ['code' => 'RETAILSTORES8', 'uuid' => 'CP-RETL-00006', 'type' => 'percent', 'type_label' => 'Percentage', 'applies_to' => 'Products', 'customer_group' => 'Retail Store Customers', 'customer_group_key' => 'retail store customers', 'usage' => 'Unlimited, 2,145', 'used' => 2145, 'category_keys' => ['franchise_retail'], 'order_categories' => ['Franchise Retail Orders'], 'validity' => '01 Apr 2025 — 30 Jun 2025', 'status' => 'active', 'status_label' => 'Active', 'value_label' => '8% OFF', 'impact' => '€6,420.70', 'sort_timestamp' => 3, 'discount_id' => null, 'actionable' => false],
            ['code' => 'BUYER12', 'uuid' => 'CP-BUYR-00007', 'type' => 'percent', 'type_label' => 'Percentage', 'applies_to' => 'Cart Subtotal', 'customer_group' => 'Buyers', 'customer_group_key' => 'buyers', 'usage' => '300 / 98', 'used' => 98, 'category_keys' => ['buyer'], 'order_categories' => ['Buyer Orders'], 'validity' => '15 Apr 2025 — 15 May 2025', 'status' => 'scheduled', 'status_label' => 'Scheduled', 'value_label' => '12% OFF', 'impact' => '€2,140.00', 'sort_timestamp' => 2, 'discount_id' => null, 'actionable' => false],
            ['code' => 'EXPIRED20', 'uuid' => 'CP-EXPO-00008', 'type' => 'percent', 'type_label' => 'Percentage', 'applies_to' => 'Products', 'customer_group' => 'All Customers', 'customer_group_key' => 'all', 'usage' => '800 / 800', 'used' => 800, 'category_keys' => $categories, 'order_categories' => array_values(self::ORDER_CATEGORIES), 'validity' => '01 Mar 2025 — 30 Apr 2025', 'status' => 'expired', 'status_label' => 'Expired', 'value_label' => '20% OFF', 'impact' => '€1,230.75', 'sort_timestamp' => 1, 'discount_id' => null, 'actionable' => false],
        ])->map(fn (array $row) => (object) $row);
    }
}
