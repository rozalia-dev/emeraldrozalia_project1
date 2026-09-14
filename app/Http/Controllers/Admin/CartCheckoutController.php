<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Models\Conversation;
use App\Models\Order;
use App\Services\AuditTrail;
use App\Services\CommunicationCenter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CartCheckoutController extends Controller
{
    private const STATUSES = [
        'active',
        'checkout_started',
        'payment_pending',
        'abandoned',
        'saved',
        'completed',
        'cancelled',
        'refunded',
        'unknown',
    ];

    private const TABS = [
        'overview' => 'Cart Overview',
        'funnel' => 'Checkout Steps Funnel',
        'abandoned' => 'Abandoned Carts',
        'saved' => 'Saved Carts',
        'sessions' => 'Checkout Sessions',
        'shipping' => 'Shipping & Delivery',
        'tax' => 'Tax',
        'analytics' => 'Analytics',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->string('tab')->toString(), self::TABS)
            ? $request->string('tab')->toString()
            : 'overview';

        $records = $this->cartRecords();
        $orders = $this->onlineOrders();
        $allRows = $this->liveRows($records, $orders);
        $periodRows = $this->periodRows($allRows);
        $rows = $this->filterRows($allRows, $request, $tab);
        $metrics = $this->metrics($periodRows);
        $sources = $this->breakdown($periodRows, 'source');
        $devices = $this->breakdown($periodRows, 'device', false);
        $paymentPreferences = $this->breakdown($periodRows, 'payment_method', false);

        return view('admin.cart-checkout.index', [
            'metrics' => $metrics,
            'summary' => $this->summary($periodRows, $metrics),
            'rows' => $this->paginate($rows, $request),
            'tabs' => self::TABS,
            'tab' => $tab,
            'status' => $request->string('status')->toString(),
            'source' => $request->string('source')->toString(),
            'search' => $request->string('q')->toString(),
            'hasLiveData' => $allRows->isNotEmpty(),
            'dataState' => $allRows->isNotEmpty() ? 'live' : 'empty',
            'emptyStateMessage' => $allRows->isNotEmpty()
                ? 'No checkout records match the current filters.'
                : 'Checkout tracking has not recorded any live carts or online orders yet.',
            'periodHasData' => $periodRows->isNotEmpty(),
            'periodLabel' => now()->format('F Y'),
            'funnel' => $this->funnel($periodRows),
            'sources' => $sources,
            'sourceDonutStyle' => $this->donutStyle($sources, ['#147841', '#2573c6', '#ee8b20', '#724aaf', '#159c9d']),
            'insights' => $this->insights($periodRows),
            'abandonedReasons' => $this->abandonedReasons($periodRows),
            'devices' => $devices,
            'deviceDonutStyle' => $this->donutStyle($devices, ['#e05252', '#2878cc', '#ef8a1f', '#724aaf', '#159c9d']),
            'paymentPreferences' => $paymentPreferences,
            'statusOptions' => $this->statusOptions($allRows),
            'sourceOptions' => $this->sourceOptions($allRows),
            'recentActivity' => $this->recentActivity($allRows),
        ]);
    }

    public function action(Request $request, AdminRecord $record): RedirectResponse
    {
        abort_unless($record->module === 'cart-checkout', 404);

        $data = $request->validate([
            'action' => ['required', 'in:mark_abandoned,restore,complete,send_recovery'],
        ]);

        $action = $data['action'];
        $recordId = $record->getKey();

        DB::transaction(function () use ($action, $recordId): void {
            $locked = AdminRecord::query()->lockForUpdate()->find($recordId);
            if (! $locked) {
                throw (new ModelNotFoundException())->setModel(AdminRecord::class, [$recordId]);
            }

            $allowed = match ($action) {
                'mark_abandoned' => ['active', 'checkout_started', 'payment_pending', 'saved'],
                'restore' => ['abandoned', 'saved'],
                'complete' => ['active', 'checkout_started', 'payment_pending'],
                'send_recovery' => ['abandoned', 'saved'],
            };

            if (! in_array($locked->status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'action' => 'This checkout record cannot receive that transition from its current state.',
                ]);
            }

            $before = $locked->toArray();
            $payload = is_array($locked->data) ? $locked->data : [];

            if ($action === 'send_recovery') {
                $email = $this->payloadString($payload, ['email', 'customer_email']);
                if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::withMessages([
                        'action' => 'A valid customer email must be recorded before a recovery message can be requested.',
                    ]);
                }

                $conversation = Conversation::query()->firstOrCreate(
                    [
                        'contact' => $email,
                        'subject' => 'Cart recovery: '.($locked->reference ?: 'record-'.$locked->id),
                    ],
                    [
                        'company_id' => $locked->company_id,
                        'channel' => 'email',
                        'status' => 'open',
                        'metadata' => [
                            'source' => 'cart_checkout_recovery',
                            'cart_record_id' => $locked->id,
                            'cart_reference' => $locked->reference,
                        ],
                    ],
                );

                $message = app(CommunicationCenter::class)->sendReply(
                    $conversation,
                    'Hello, we saved your Emerald Rozalia cart ('.($locked->reference ?: 'checkout record').'). '
                        .'You can return to the website to continue checkout. If you need help, reply to this email.',
                    'cart-recovery-'.($locked->public_uuid ?: $locked->id),
                );

                $payload['recovery_requested_at'] = now()->toIso8601String();
                $payload['recovery_message_uuid'] = $message->uuid;
                $payload['recovery_delivery_status'] = $message->delivery_status;
                $locked->update(['data' => $payload]);
            } else {
                $locked->update([
                    'status' => match ($action) {
                        'mark_abandoned' => 'abandoned',
                        'restore' => 'active',
                        'complete' => 'completed',
                    },
                ]);
            }

            AuditTrail::record(
                'admin.cart_checkout.'.$action,
                $locked->fresh(),
                $before,
                $locked->fresh()->toArray(),
            );
        });

        return back()->with('success', match ($action) {
            'mark_abandoned' => 'Cart marked as abandoned.',
            'restore' => 'Cart restored to active follow-up.',
            'complete' => 'Checkout marked as completed.',
            'send_recovery' => 'Recovery message request recorded and queued for delivery.',
        });
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filterRows(
            $this->liveRows($this->cartRecords(), $this->onlineOrders()),
            $request,
            $request->string('tab')->toString() ?: 'overview',
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Cart / Checkout ID', 'Public UUID', 'Customer', 'Email', 'Source', 'Status', 'Currency', 'Value', 'Progress', 'Last activity']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->reference,
                    $row->public_uuid,
                    $row->customer,
                    $row->email,
                    $row->source,
                    $row->status_label,
                    $row->currency,
                    $row->amount !== null ? number_format($row->amount, 2, '.', '') : '',
                    $row->progress_label,
                    $row->last_activity,
                ]);
            }
            fclose($handle);
        }, 'cart-checkout-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function cartRecords(): Collection
    {
        return AdminRecord::query()
            ->where('module', 'cart-checkout')
            ->latest('created_at')
            ->get();
    }

    private function onlineOrders(): Collection
    {
        return Order::query()
            ->where('order_type', 'online')
            ->with('user')
            ->latest('created_at')
            ->get();
    }

    private function liveRows(Collection $records, Collection $orders): Collection
    {
        $recordOrderIds = $records
            ->map(fn (AdminRecord $record): mixed => data_get($record->data, 'order_id'))
            ->filter(fn (mixed $id): bool => is_numeric($id))
            ->map(fn (mixed $id): string => (string) $id)
            ->all();
        $recordOrderReferences = $records
            ->map(fn (AdminRecord $record): mixed => data_get($record->data, 'order_number'))
            ->filter(fn (mixed $reference): bool => is_scalar($reference) && trim((string) $reference) !== '')
            ->map(fn (mixed $reference): string => trim((string) $reference))
            ->all();

        $recordRows = $records->map(fn (AdminRecord $record): object => $this->recordRow($record));
        $orderRows = $orders
            ->reject(fn (Order $order): bool => in_array((string) $order->id, $recordOrderIds, true)
                || in_array((string) $order->number, $recordOrderReferences, true))
            ->map(fn (Order $order): object => $this->orderRow($order));

        return $recordRows
            ->concat($orderRows)
            ->sortByDesc('sort_timestamp')
            ->values();
    }

    private function filterRows(Collection $rows, Request $request, string $tab): Collection
    {
        $requestedStatus = mb_strtolower(trim($request->string('status')->toString()));
        $requestedSource = mb_strtolower(trim($request->string('source')->toString()));
        $query = mb_strtolower(trim($request->string('q')->toString()));
        $tabStatuses = match ($tab) {
            'abandoned' => ['abandoned'],
            'saved' => ['saved'],
            'sessions' => ['checkout_started', 'payment_pending'],
            default => [],
        };

        return $rows
            ->filter(function (object $row) use ($requestedStatus, $requestedSource, $query, $tabStatuses): bool {
                if ($tabStatuses && ! in_array($row->status, $tabStatuses, true)) {
                    return false;
                }
                if ($requestedStatus !== '' && mb_strtolower($row->status) !== $requestedStatus) {
                    return false;
                }
                if ($requestedSource !== '' && mb_strtolower($row->source) !== $requestedSource) {
                    return false;
                }

                $searchable = implode(' ', [
                    $row->reference,
                    $row->public_uuid,
                    $row->customer,
                    $row->email,
                    $row->phone,
                    $row->source,
                ]);

                return $query === '' || str_contains(mb_strtolower($searchable), $query);
            })
            ->sortByDesc('sort_timestamp')
            ->values();
    }

    private function recordRow(AdminRecord $record): object
    {
        $data = is_array($record->data) ? $record->data : [];
        $rawStatus = mb_strtolower(trim((string) $record->status));
        $status = in_array($rawStatus, self::STATUSES, true) ? $rawStatus : 'unknown';
        $createdTimestamp = $this->timestamp($record->created_at) ?? $this->timestamp($record->record_date);
        $updatedTimestamp = $this->timestamp($record->updated_at) ?? $createdTimestamp;
        $amount = $this->amount($record->amount, $data);
        $progress = $this->progress($status, $data);
        $paymentStatus = $this->normaliseLabel($this->payloadValue($data, ['payment_status', 'payment_state'])) ?: 'Not recorded';

        return (object) [
            'record_id' => $record->id,
            'order_id' => null,
            'reference' => $record->reference ?: 'Cart record #'.$record->id,
            'public_uuid' => (string) ($record->public_uuid ?: 'Not assigned'),
            'customer' => $this->payloadString($data, ['customer_name', 'customer', 'name']) ?: ($record->title ?: 'Not recorded'),
            'email' => $this->payloadString($data, ['email', 'customer_email']) ?: 'Not recorded',
            'phone' => $this->payloadString($data, ['phone', 'customer_phone']) ?: 'Not recorded',
            'source' => $this->normaliseLabel($this->payloadValue($data, ['source', 'channel', 'utm_source'])) ?: 'Not recorded',
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'amount' => $amount,
            'amount_known' => $amount !== null,
            'currency' => strtoupper($this->payloadString($data, ['currency', 'currency_code']) ?: 'EUR'),
            'progress' => $progress,
            'progress_label' => $progress === null ? 'Not recorded' : $progress.' / 5',
            'progress_percent' => $progress === null ? 0 : min(100, max(0, $progress * 20)),
            'last_activity' => $this->dateLabel($this->payloadValue($data, ['last_activity_at', 'last_activity']) ?? $updatedTimestamp),
            'sort_timestamp' => $updatedTimestamp ?? 0,
            'created_timestamp' => $createdTimestamp,
            'completed' => $status === 'completed',
            'started' => in_array($status, ['checkout_started', 'payment_pending', 'completed'], true),
            'revenue_eligible' => $status === 'completed'
                && ($paymentStatus === 'Not recorded' || in_array(mb_strtolower($paymentStatus), ['paid', 'captured', 'completed', 'pay on delivery'], true)),
            'shipping_method' => $this->normaliseLabel($this->payloadValue($data, ['shipping_method', 'shipping_method_code'])),
            'payment_method' => $this->normaliseLabel($this->payloadValue($data, ['payment_method', 'payment_provider'])),
            'device' => $this->normaliseLabel($this->payloadValue($data, ['device', 'device_type'])),
            'abandonment_step' => $this->normaliseLabel($this->payloadValue($data, ['abandonment_step', 'checkout_step'])),
            'abandonment_reason' => $this->normaliseLabel($this->payloadValue($data, ['abandonment_reason', 'reason'])),
            'recovery_requested' => $this->payloadValue($data, ['recovery_requested_at', 'recovery_sent_at']) !== null,
            'recovered' => $this->truthy($this->payloadValue($data, ['recovered', 'recovered_at'])),
            'actionable' => true,
        ];
    }

    private function orderRow(Order $order): object
    {
        $rawStatus = mb_strtolower(trim((string) $order->status));
        $status = match ($rawStatus) {
            'completed', 'delivered', 'shipped' => 'completed',
            'processing', 'approved', 'ready_to_ship', 'picking' => 'checkout_started',
            'pending', 'awaiting_payment' => 'payment_pending',
            default => in_array($rawStatus, self::STATUSES, true) ? $rawStatus : 'unknown',
        };
        $address = is_array($order->shipping_address) ? $order->shipping_address : [];
        $createdTimestamp = $this->timestamp($order->created_at);
        $updatedTimestamp = $this->timestamp($order->updated_at) ?? $createdTimestamp;
        $currency = strtoupper((string) ($order->currency_code ?: $order->currency ?: 'EUR'));
        $paymentStatus = mb_strtolower(trim((string) ($order->payment_status ?: '')));

        return (object) [
            'record_id' => null,
            'order_id' => $order->id,
            'reference' => (string) $order->number,
            'public_uuid' => (string) ($order->public_uuid ?: 'Not assigned'),
            'customer' => $order->user?->name ?: ($order->email ?: 'Guest customer'),
            'email' => $order->email ?: $order->user?->email ?: 'Not recorded',
            'phone' => $order->phone ?: 'Not recorded',
            'source' => $this->normaliseLabel($address['source'] ?? $address['channel'] ?? null) ?: 'Online checkout',
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'amount' => $order->total !== null ? (float) $order->total : null,
            'amount_known' => $order->total !== null,
            'currency' => $currency,
            'progress' => in_array($status, ['completed', 'refunded'], true) ? 5 : (in_array($status, ['checkout_started', 'payment_pending'], true) ? 4 : null),
            'progress_label' => in_array($status, ['completed', 'refunded'], true) ? '5 / 5' : (in_array($status, ['checkout_started', 'payment_pending'], true) ? '4 / 5' : 'Not recorded'),
            'progress_percent' => in_array($status, ['completed', 'refunded'], true) ? 100 : (in_array($status, ['checkout_started', 'payment_pending'], true) ? 80 : 0),
            'last_activity' => $this->dateLabel($updatedTimestamp),
            'sort_timestamp' => $updatedTimestamp ?? 0,
            'created_timestamp' => $createdTimestamp,
            'completed' => $status === 'completed',
            'started' => in_array($status, ['checkout_started', 'payment_pending', 'completed'], true),
            'revenue_eligible' => $status === 'completed' && in_array($paymentStatus, ['paid', 'captured', 'completed', 'pay_on_delivery'], true),
            'shipping_method' => $this->normaliseLabel($order->shipping_method),
            'payment_method' => $this->normaliseLabel($order->payment_method),
            'device' => $this->normaliseLabel($address['device'] ?? $address['device_type'] ?? null),
            'abandonment_step' => null,
            'abandonment_reason' => null,
            'recovery_requested' => false,
            'recovered' => false,
            'actionable' => false,
        ];
    }

    private function metrics(Collection $rows): array
    {
        $hasData = $rows->isNotEmpty();
        $active = $rows->whereIn('status', ['active', 'checkout_started', 'payment_pending'])->count();
        $abandoned = $rows->where('status', 'abandoned')->count();
        $started = $rows->where('started', true)->count();
        $completed = $rows->where('completed', true)->count();
        $conversion = $started > 0 ? round(($completed / $started) * 100, 2) : 0;
        $revenueRows = $rows->filter(fn (object $row): bool => $row->revenue_eligible && $row->amount_known);
        $revenue = (float) $revenueRows->sum('amount');
        $currencies = $revenueRows->pluck('currency')->filter()->unique()->values();
        $revenueLabel = $currencies->count() > 1
            ? 'Multiple currencies'
            : $this->moneyLabel($revenue, (string) ($currencies->first() ?: 'EUR'));
        $source = $hasData ? 'Live database value' : 'No current-month checkout data';

        return [
            ['label' => 'Active Carts', 'value' => number_format($active), 'icon' => 'shopping-bag', 'tone' => 'green', 'source' => $source],
            ['label' => 'Abandoned Carts', 'value' => number_format($abandoned), 'icon' => 'shopping-bag', 'tone' => 'orange', 'source' => $source],
            ['label' => 'Checkouts Started', 'value' => number_format($started), 'icon' => 'arrow-right', 'tone' => 'blue', 'source' => $source],
            ['label' => 'Completed Orders', 'value' => number_format($completed), 'icon' => 'check', 'tone' => 'purple', 'source' => $source],
            ['label' => 'Conversion Rate', 'value' => number_format($conversion, 2).'%', 'icon' => 'percent', 'tone' => 'teal', 'source' => $source],
            ['label' => 'Revenue from Checkout', 'value' => $revenueLabel, 'icon' => 'credit-card', 'tone' => 'red', 'source' => $source],
        ];
    }

    private function summary(Collection $rows, array $metrics): array
    {
        return [
            'total_carts' => number_format($rows->count()),
            'active_carts' => $metrics[0]['value'],
            'abandoned_carts' => $metrics[1]['value'],
            'checkout_started' => $metrics[2]['value'],
            'completed_orders' => $metrics[3]['value'],
            'conversion_rate' => $metrics[4]['value'],
            'revenue' => $metrics[5]['value'],
        ];
    }

    private function funnel(Collection $rows): array
    {
        $cartCreated = $rows->count();
        $steps = [
            ['label' => 'Cart Created', 'count' => $cartCreated, 'tone' => 'green'],
            ['label' => 'Checkout Started', 'count' => $rows->where('started', true)->count(), 'tone' => 'blue'],
            ['label' => 'Shipping Method', 'count' => $rows->filter(fn (object $row): bool => $row->started && $this->recorded($row->shipping_method))->count(), 'tone' => 'purple'],
            ['label' => 'Payment Method', 'count' => $rows->filter(fn (object $row): bool => $row->started && $this->recorded($row->payment_method))->count(), 'tone' => 'orange'],
            ['label' => 'Order Completed', 'count' => $rows->where('completed', true)->count(), 'tone' => 'green'],
        ];

        return collect($steps)->map(function (array $step) use ($cartCreated): array {
            return [
                'label' => $step['label'],
                'value' => number_format($step['count']),
                'percent' => $cartCreated > 0 ? round(($step['count'] / $cartCreated) * 100, 1) : 0,
                'tone' => $step['tone'],
            ];
        })->all();
    }

    private function breakdown(Collection $rows, string $field, bool $includeUnrecorded = true): array
    {
        $values = $rows
            ->map(fn (object $row): string => (string) ($row->{$field} ?? ''))
            ->map(fn (string $value): string => trim($value))
            ->filter(fn (string $value): bool => $value !== '' && ($includeUnrecorded || $value !== 'Not recorded'));
        $total = $values->count();

        if ($total === 0) {
            return [];
        }

        return $values->countBy()->sortDesc()->map(function (int $count, string $label) use ($total): array {
            return [
                'label' => $label,
                'count' => $count,
                'percent' => round(($count / $total) * 100, 1),
            ];
        })->values()->all();
    }

    private function insights(Collection $rows): array
    {
        $abandoned = $rows->where('status', 'abandoned');
        $amountRows = $abandoned->filter(fn (object $row): bool => $row->amount_known);
        $rate = $rows->count() > 0 ? round(($abandoned->count() / $rows->count()) * 100, 2) : null;
        $steps = $abandoned->pluck('abandonment_step')->filter(fn (mixed $step): bool => $this->recorded($step));
        $recoveryRows = $rows->filter(fn (object $row): bool => $row->recovery_requested);
        $recoveredRows = $rows->filter(fn (object $row): bool => $row->recovered);

        return [
            'abandonment_rate' => [
                'value' => $rate === null ? '—' : number_format($rate, 2).'%',
                'source' => $rate === null ? 'No current-month checkout data' : 'Live database value',
            ],
            'most_abandoned_step' => [
                'value' => $steps->isEmpty() ? 'Not recorded' : (string) $steps->countBy()->sortDesc()->keys()->first(),
                'source' => $steps->isEmpty() ? 'No step data recorded' : 'Live database value',
            ],
            'average_abandoned_value' => [
                'value' => $amountRows->isEmpty() ? '—' : $this->moneyLabel((float) $amountRows->avg('amount'), $this->singleCurrency($amountRows)),
                'source' => $amountRows->isEmpty() ? 'No cart value recorded' : 'Live database value',
            ],
            'recovery_requested' => [
                'value' => number_format($recoveryRows->count()),
                'source' => $recoveryRows->isEmpty() ? 'No recovery requests recorded' : 'Live database value',
            ],
            'recovered_carts' => [
                'value' => number_format($recoveredRows->count()),
                'source' => $recoveredRows->isEmpty() ? 'No recovery outcomes recorded' : 'Live database value',
            ],
        ];
    }

    private function abandonedReasons(Collection $rows): array
    {
        $abandoned = $rows
            ->where('status', 'abandoned')
            ->filter(fn (object $row): bool => $this->recorded($row->abandonment_reason));
        $total = $abandoned->count();

        if ($total === 0) {
            return [];
        }

        $counts = $abandoned->countBy('abandonment_reason')->sortDesc();
        $max = max(1, (int) $counts->max());

        return $counts->map(fn (int $count, string $label): array => [
            'label' => $label,
            'count' => $count,
            'percent' => round(($count / $total) * 100, 1),
            'bar_percent' => round(($count / $max) * 100, 1),
        ])->values()->all();
    }

    private function recentActivity(Collection $rows): array
    {
        return $rows->take(5)->map(function (object $row): array {
            $verb = match ($row->status) {
                'completed' => 'completed',
                'abandoned' => 'marked abandoned',
                'checkout_started', 'payment_pending' => 'is in checkout',
                default => 'was recorded',
            };

            return [
                'label' => $row->reference.' '.$verb,
                'time' => $row->sort_timestamp > 0
                    ? now()->setTimestamp($row->sort_timestamp)->diffForHumans()
                    : 'Time not recorded',
                'tone' => match ($row->status) {
                    'completed' => 'green',
                    'abandoned' => 'orange',
                    'checkout_started', 'payment_pending' => 'blue',
                    default => 'purple',
                },
            ];
        })->values()->all();
    }

    private function periodRows(Collection $rows): Collection
    {
        $start = now()->startOfMonth()->timestamp;
        $end = now()->endOfMonth()->timestamp;

        return $rows->filter(fn (object $row): bool => $row->sort_timestamp >= $start && $row->sort_timestamp <= $end)->values();
    }

    private function statusOptions(Collection $rows): array
    {
        $present = $rows->pluck('status')->filter()->unique()->all();
        $ordered = array_values(array_unique(array_merge(array_slice(self::STATUSES, 0, -1), $present)));

        return collect($ordered)->mapWithKeys(fn (string $status): array => [$status => $this->statusLabel($status)])->all();
    }

    private function sourceOptions(Collection $rows): array
    {
        return $rows->pluck('source')->filter()->unique()->sort()->values()->all();
    }

    private function donutStyle(array $breakdown, array $colors): string
    {
        if ($breakdown === []) {
            return 'conic-gradient(#e8eeea 0 100%)';
        }

        $segments = [];
        $cursor = 0.0;
        $last = count($breakdown) - 1;
        foreach ($breakdown as $index => $row) {
            $end = $index === $last ? 100.0 : min(100.0, $cursor + (float) $row['percent']);
            $color = $colors[$index % count($colors)];
            $segments[] = $color.' '.$cursor.'% '.$end.'%';
            $cursor = $end;
        }

        return 'conic-gradient('.implode(',', $segments).')';
    }

    private function progress(string $status, array $data): ?int
    {
        $provided = $data['progress'] ?? null;
        if (is_numeric($provided)) {
            return min(5, max(1, (int) $provided));
        }

        return match ($status) {
            'completed', 'refunded' => 5,
            'payment_pending' => 4,
            'checkout_started' => 3,
            'active', 'saved', 'abandoned' => 2,
            default => null,
        };
    }

    private function amount(mixed $recordAmount, array $data): ?float
    {
        if (is_numeric($recordAmount)) {
            return (float) $recordAmount;
        }

        $payloadAmount = $this->payloadValue($data, ['amount', 'cart_value', 'total']);
        return is_numeric($payloadAmount) ? (float) $payloadAmount : null;
    }

    private function payloadValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        return null;
    }

    private function payloadString(array $payload, array $keys): ?string
    {
        $value = $this->payloadValue($payload, $keys);
        return is_scalar($value) ? trim((string) $value) : null;
    }

    private function normaliseLabel(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $label = trim((string) $value);
        return $label === '' ? null : ucwords(str_replace(['_', '-'], ' ', $label));
    }

    private function statusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    private function recorded(mixed $value): bool
    {
        return is_scalar($value) && trim((string) $value) !== '' && trim((string) $value) !== 'Not recorded';
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(mb_strtolower(trim($value)), ['1', 'true', 'yes', 'y'], true);
        }

        return is_numeric($value) && (int) $value === 1;
    }

    private function timestamp(mixed $value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return now()->parse($value)->timestamp;
        } catch (\Throwable) {
            return null;
        }
    }

    private function dateLabel(mixed $value): string
    {
        $timestamp = $this->timestamp($value);
        if ($timestamp !== null) {
            return now()->setTimestamp($timestamp)->format('d M Y, H:i');
        }

        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : 'Not recorded';
    }

    private function singleCurrency(Collection $rows): string
    {
        $currencies = $rows->pluck('currency')->filter()->unique()->values();
        return $currencies->count() === 1 ? (string) $currencies->first() : 'EUR';
    }

    private function moneyLabel(float $amount, string $currency): string
    {
        $symbol = match (strtoupper($currency)) {
            'EUR' => '€',
            'GBP' => '£',
            'USD' => '$',
            'CAD' => 'CA$',
            'AUD' => 'A$',
            default => strtoupper($currency).' ',
        };

        return $symbol.number_format($amount, 2);
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
