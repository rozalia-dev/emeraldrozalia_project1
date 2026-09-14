<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Order, PaymentTransaction};
use App\Services\AuditTrail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    private const TABS = [
        'all' => 'All Payments',
        'paid' => 'Paid',
        'pending' => 'Pending',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
        'partially_refunded' => 'Partially Refunded',
        'chargebacks' => 'Chargebacks',
    ];

    private const CATEGORY_LABELS = [
        'online' => 'Online Orders',
        'corporate' => 'Corporate Orders',
        'bulk' => 'Bulk Orders',
        'franchise' => 'Franchise Orders',
        'franchise_retail' => 'Franchise Retail Orders',
        'buyer' => 'Buyer Orders',
    ];

    public function index(Request $request): View
    {
        $requestedTab = $request->string('tab')->toString();
        $tab = array_key_exists($requestedTab, self::TABS) ? $requestedTab : 'all';

        $transactions = PaymentTransaction::query()
            ->with(['order.user'])
            ->latest('created_at')
            ->get();
        $orders = Order::query()
            ->with('user')
            ->latest('created_at')
            ->get();
        $liveRows = $this->liveRows($transactions, $orders);
        $periodRows = $this->currentPeriodRows($liveRows);
        $hasLiveData = $liveRows->isNotEmpty();
        $filteredRows = $this->filterRows($liveRows, $request, $tab);
        $metrics = $this->metrics($periodRows);
        $categoryBreakdown = $this->breakdown($periodRows, 'category_key');
        $statusBreakdown = $this->statusBreakdown($periodRows);
        $currencyBreakdown = $this->breakdown($periodRows, 'currency');

        return view('admin.payments.index', [
            'rows' => $this->paginate($filteredRows, $request),
            'tabs' => self::TABS,
            'tab' => $tab,
            'search' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'category' => $request->string('category')->toString(),
            'method' => $request->string('method')->toString(),
            'gateway' => $request->string('gateway')->toString(),
            'hasLiveData' => $hasLiveData,
            'dataState' => $hasLiveData ? 'live' : 'empty',
            'emptyStateMessage' => $hasLiveData
                ? 'No payments match the selected filters.'
                : 'No live payment or order payment records have been recorded yet.',
            'periodHasData' => $periodRows->isNotEmpty(),
            'periodLabel' => now()->format('F Y'),
            'metrics' => $metrics,
            'netReceived' => $this->formatAmount($this->netReceived($periodRows)),
            'reconciliation' => $this->reconciliation($liveRows),
            'methods' => $this->breakdown($periodRows, 'method_key'),
            'categoryBreakdown' => $categoryBreakdown,
            'categoryDonutStyle' => $this->donutStyle($categoryBreakdown),
            'statusBreakdown' => $statusBreakdown,
            'statusDonutStyle' => $this->donutStyle($statusBreakdown),
            'gatewayBreakdown' => $this->breakdown($periodRows, 'gateway_key'),
            'currencyBreakdown' => $currencyBreakdown,
            'currencyDonutStyle' => $this->donutStyle($currencyBreakdown),
            'trend' => $this->trend($liveRows),
            'recentActivity' => $this->recentActivity($liveRows),
            'methodOptions' => $this->filterOptions($liveRows, 'method_key', 'method'),
            'gatewayOptions' => $this->filterOptions($liveRows, 'gateway_key', 'gateway'),
        ]);
    }

    public function action(Request $request, PaymentTransaction $payment): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:capture,refund,cancel'],
        ]);
        $payment->load('order');

        $allowedStatuses = match ($data['action']) {
            'capture' => ['pending', 'awaiting_payment', 'pay_on_delivery'],
            'refund' => ['paid', 'captured', 'partially_refunded'],
            'cancel' => ['pending', 'awaiting_payment', 'pay_on_delivery'],
        };
        abort_unless(in_array(strtolower((string) $payment->status), $allowedStatuses, true), 422, 'This payment cannot make that state transition.');

        DB::transaction(function () use ($payment, $data): void {
            $before = $payment->toArray();
            $payload = $payment->payload ?: [];

            match ($data['action']) {
                'capture' => $this->capture($payment, $payload),
                'refund' => $this->refund($payment, $payload),
                'cancel' => $this->cancel($payment, $payload),
            };

            AuditTrail::record('admin.payment_'.$data['action'], $payment, $before, $payment->fresh()->toArray());
        });

        return back()->with('success', match ($data['action']) {
            'capture' => 'Payment captured and reconciled.',
            'refund' => 'Payment refund recorded and order updated.',
            'cancel' => 'Payment cancelled and order marked accordingly.',
        });
    }

    public function export(Request $request): StreamedResponse
    {
        $transactions = PaymentTransaction::query()->with(['order.user'])->latest('created_at')->get();
        $orders = Order::query()->with('user')->latest('created_at')->get();
        $rows = $this->filterRows(
            $this->liveRows($transactions, $orders),
            $request,
            $request->string('tab')->toString() ?: 'all',
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Transaction ID', 'UUID', 'Order ID', 'Category', 'Customer', 'Payment method', 'Gateway', 'Amount', 'Currency', 'Status', 'Payment date', 'Reference / Gateway ID', 'Reconciliation']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row->reference, $row->uuid, $row->order_reference, $row->category, $row->customer, $row->method, $row->gateway, number_format($row->amount, 2, '.', ''), $row->currency, $row->status_label, $row->payment_date, $row->gateway_reference, $row->reconciliation]);
            }
            fclose($handle);
        }, 'payments-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function capture(PaymentTransaction $payment, array $payload): void
    {
        $payment->update([
            'status' => 'paid',
            'payload' => array_merge($payload, [
                'captured_at' => now()->toIso8601String(),
                'reconciliation_status' => 'reconciled',
            ]),
        ]);
        $payment->order?->update(['payment_status' => 'paid']);
    }

    private function refund(PaymentTransaction $payment, array $payload): void
    {
        $payment->update([
            'status' => 'refunded',
            'payload' => array_merge($payload, [
                'refunded_at' => now()->toIso8601String(),
                'reconciliation_status' => 'reconciled',
            ]),
        ]);
        $payment->order?->update(['payment_status' => 'refunded', 'status' => 'refunded']);
    }

    private function cancel(PaymentTransaction $payment, array $payload): void
    {
        $payment->update([
            'status' => 'cancelled',
            'payload' => array_merge($payload, [
                'cancelled_at' => now()->toIso8601String(),
                'reconciliation_status' => 'unreconciled',
            ]),
        ]);
        $payment->order?->update(['payment_status' => 'failed', 'status' => 'cancelled']);
    }

    private function liveRows(Collection $transactions, Collection $orders): Collection
    {
        $transactionRows = $transactions
            ->map(fn (PaymentTransaction $payment): object => $this->transactionRow($payment));
        $representedOrderIds = $transactions
            ->pluck('order_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();
        $orderRows = $orders
            ->reject(fn (Order $order): bool => in_array((int) $order->id, $representedOrderIds, true))
            ->map(fn (Order $order): object => $this->orderRow($order));

        return $transactionRows
            ->concat($orderRows)
            ->sortByDesc('sort_timestamp')
            ->values();
    }

    private function currentPeriodRows(Collection $rows): Collection
    {
        $start = now()->startOfMonth()->timestamp;

        return $rows->filter(fn (object $row): bool => $row->sort_timestamp >= $start)->values();
    }

    private function filterRows(Collection $rows, Request $request, string $tab): Collection
    {
        $query = mb_strtolower(trim($request->string('q')->toString()));
        $status = mb_strtolower($request->string('status')->toString());
        $category = mb_strtolower($request->string('category')->toString());
        $method = mb_strtolower($request->string('method')->toString());
        $gateway = mb_strtolower($request->string('gateway')->toString());

        return $rows->filter(function (object $row) use ($query, $status, $category, $method, $gateway, $tab): bool {
            if ($tab !== 'all' && ! $this->tabMatches($row->status, $tab)) {
                return false;
            }
            if ($status && $row->status !== $status) {
                return false;
            }
            if ($category && mb_strtolower($row->category_key) !== $category) {
                return false;
            }
            if ($method && $row->method_key !== $method) {
                return false;
            }
            if ($gateway && $row->gateway_key !== $gateway) {
                return false;
            }

            return $query === '' || str_contains(mb_strtolower(implode(' ', [
                $row->reference,
                $row->uuid,
                $row->order_reference,
                $row->customer,
                $row->method,
                $row->gateway,
            ])), $query);
        })->sortByDesc('sort_timestamp')->values();
    }

    private function tabMatches(string $status, string $tab): bool
    {
        return match ($tab) {
            'paid' => in_array($status, ['paid', 'captured'], true),
            'pending' => in_array($status, ['pending', 'awaiting_payment', 'pay_on_delivery'], true),
            'failed' => in_array($status, ['failed', 'cancelled', 'canceled'], true),
            'refunded' => $status === 'refunded',
            'partially_refunded' => $status === 'partially_refunded',
            'chargebacks' => $status === 'chargeback',
            default => true,
        };
    }

    private function transactionRow(PaymentTransaction $payment): object
    {
        $order = $payment->order;
        $payload = $payment->payload ?: [];
        $status = strtolower((string) $payment->status);
        $methodKey = $this->normaliseKey($payload['payment_method'] ?? $order?->payment_method);
        $gatewayKey = $this->normaliseKey($payload['gateway'] ?? $payment->provider);
        $categoryKey = $order?->order_type ?: 'online';
        $created = $payment->created_at ?: $order?->created_at;
        $reconciliationKey = $this->reconciliationKey($status, $payload['reconciliation_status'] ?? null);

        return (object) [
            'payment_id' => $payment->id,
            'order_id' => $order?->id,
            'reference' => $payment->transaction_id ?: 'Payment transaction #'.$payment->id,
            'uuid' => $payment->public_uuid ?: 'Not assigned',
            'order_reference' => $order?->number ?: 'Not linked to an order',
            'category_key' => $categoryKey,
            'category' => self::CATEGORY_LABELS[$categoryKey] ?? Str::headline(str_replace('_', ' ', $categoryKey)),
            'customer' => $order?->user?->name ?: ($order?->email ?: 'Guest customer'),
            'customer_type' => $payload['customer_type'] ?? ($order?->order_type === 'corporate' ? 'Corporate Account' : 'Retail Customer'),
            'method_key' => $methodKey,
            'method' => $this->displayKey($methodKey),
            'gateway_key' => $gatewayKey,
            'gateway' => $this->displayKey($gatewayKey),
            'amount' => (float) $payment->amount,
            'currency' => strtoupper((string) ($payment->currency ?: $order?->currency_code ?: $order?->currency ?: 'EUR')),
            'status' => $status ?: 'unknown',
            'status_group' => $this->statusGroup($status),
            'status_label' => $status ? Str::headline(str_replace('_', ' ', $status)) : 'Not recorded',
            'payment_date' => $created?->format('d M Y, H:i') ?: 'Not recorded',
            'gateway_reference' => $payment->transaction_id ?: ($payload['gateway_reference'] ?? 'Not recorded'),
            'reconciliation_key' => $reconciliationKey,
            'reconciliation' => Str::headline($reconciliationKey),
            'sort_timestamp' => $created?->timestamp ?: 0,
            'actionable' => true,
        ];
    }

    private function orderRow(Order $order): object
    {
        $status = match ($order->payment_status) {
            'paid' => 'paid',
            'refunded' => 'refunded',
            'failed' => 'failed',
            'pay_on_delivery' => 'pay_on_delivery',
            default => 'pending',
        };
        $created = $order->created_at;
        $categoryKey = $order->order_type ?: 'online';
        $methodKey = $this->normaliseKey($order->payment_method);
        $reconciliationKey = $this->reconciliationKey($status);

        return (object) [
            'payment_id' => null,
            'order_id' => $order->id,
            'reference' => $order->number ?: 'Order payment',
            'uuid' => $order->public_uuid ?: 'Not assigned',
            'order_reference' => $order->number ?: 'Not recorded',
            'category_key' => $categoryKey,
            'category' => self::CATEGORY_LABELS[$categoryKey] ?? Str::headline(str_replace('_', ' ', $categoryKey)),
            'customer' => $order->user?->name ?: ($order->email ?: 'Guest customer'),
            'customer_type' => $categoryKey === 'corporate' ? 'Corporate Account' : 'Retail Customer',
            'method_key' => $methodKey,
            'method' => $this->displayKey($methodKey),
            'gateway_key' => 'not_recorded',
            'gateway' => 'Not recorded',
            'amount' => (float) $order->total,
            'currency' => strtoupper((string) ($order->currency_code ?: $order->currency ?: 'EUR')),
            'status' => $status,
            'status_group' => $this->statusGroup($status),
            'status_label' => Str::headline(str_replace('_', ' ', $status)),
            'payment_date' => $created?->format('d M Y, H:i') ?: 'Not recorded',
            'gateway_reference' => 'Not recorded',
            'reconciliation_key' => $reconciliationKey,
            'reconciliation' => Str::headline($reconciliationKey),
            'sort_timestamp' => $created?->timestamp ?: 0,
            'actionable' => false,
        ];
    }

    private function metrics(Collection $rows): array
    {
        $sourceLabel = $rows->isNotEmpty() ? 'Live database value' : 'No current-month payment data';
        $received = $this->amountForStatuses($rows, ['paid', 'captured']);
        $pending = $this->amountForStatuses($rows, ['pending', 'awaiting_payment', 'pay_on_delivery']);
        $failed = $this->amountForStatuses($rows, ['failed', 'cancelled', 'canceled']);
        $refunds = $this->amountForStatuses($rows, ['refunded', 'partially_refunded']);
        $chargebacks = $this->amountForStatuses($rows, ['chargeback']);

        return [
            ['label' => 'Total Transactions (This Month)', 'value' => number_format($rows->count()), 'icon' => 'credit-card', 'tone' => 'green', 'change' => null, 'direction' => 'up', 'source_label' => $sourceLabel],
            ['label' => 'Total Amount Received', 'value' => $this->formatAmount($received), 'icon' => 'credit-card', 'tone' => 'blue', 'change' => null, 'direction' => 'up', 'source_label' => $sourceLabel],
            ['label' => 'Pending Payments', 'value' => $this->formatAmount($pending), 'icon' => 'clock', 'tone' => 'purple', 'change' => null, 'direction' => 'down', 'source_label' => $sourceLabel],
            ['label' => 'Failed Payments', 'value' => $this->formatAmount($failed), 'icon' => 'alert', 'tone' => 'orange', 'change' => null, 'direction' => 'down', 'source_label' => $sourceLabel],
            ['label' => 'Refunds Processed', 'value' => $this->formatAmount($refunds), 'icon' => 'rotate-ccw', 'tone' => 'teal', 'change' => null, 'direction' => 'up', 'source_label' => $sourceLabel],
            ['label' => 'Chargebacks', 'value' => $this->formatAmount($chargebacks), 'icon' => 'alert', 'tone' => 'red', 'change' => null, 'direction' => 'down', 'source_label' => $sourceLabel],
        ];
    }

    private function reconciliation(Collection $rows): array
    {
        $counts = $rows->countBy('reconciliation_key');
        $total = $rows->count();
        $percent = static fn (int $value): float => $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
        $segments = collect(['reconciled', 'pending', 'unreconciled'])
            ->filter(fn (string $key): bool => (int) ($counts[$key] ?? 0) > 0)
            ->map(fn (string $key): array => [
                'label' => Str::headline($key),
                'count' => (int) ($counts[$key] ?? 0),
                'value' => number_format((int) ($counts[$key] ?? 0)).' ('.$percent((int) ($counts[$key] ?? 0)).'%)',
                'tone' => ['reconciled' => 'green', 'pending' => 'orange', 'unreconciled' => 'red'][$key],
                'color' => $this->toneColor(['reconciled' => 'green', 'pending' => 'orange', 'unreconciled' => 'red'][$key]),
            ])->values()->all();

        return [
            'total' => number_format($total),
            'reconciled' => number_format((int) ($counts['reconciled'] ?? 0)).' ('.$percent((int) ($counts['reconciled'] ?? 0)).'%)',
            'pending' => number_format((int) ($counts['pending'] ?? 0)).' ('.$percent((int) ($counts['pending'] ?? 0)).'%)',
            'unreconciled' => number_format((int) ($counts['unreconciled'] ?? 0)).' ('.$percent((int) ($counts['unreconciled'] ?? 0)).'%)',
            'segments' => $segments,
            'donut_style' => $this->donutStyle($segments),
        ];
    }

    private function statusBreakdown(Collection $rows): array
    {
        return collect($this->breakdown($rows, 'status_group'))
            ->map(function (array $row): array {
                $row['label'] = match ($row['label']) {
                    'Paid' => 'Paid',
                    'Pending' => 'Pending',
                    'Failed' => 'Failed',
                    'Refunded' => 'Refunded',
                    'Chargeback' => 'Chargebacks',
                    default => $row['label'],
                };
                return $row;
            })->values()->all();
    }

    private function breakdown(Collection $rows, string $field): array
    {
        $counts = $rows->countBy($field)->sortDesc();
        $total = $rows->count();
        $tones = ['green', 'blue', 'orange', 'purple', 'teal', 'red', 'gray'];

        return $counts->map(function (int $count, string $key) use ($total, $tones, $field): array {
            $paletteKey = $field === 'category_key' ? array_search($key, array_keys(self::CATEGORY_LABELS), true) : false;
            $tone = $tones[$paletteKey === false ? 0 : $paletteKey % count($tones)];
            $percent = $total > 0 ? round(($count / $total) * 100, 1) : 0.0;
            $label = match ($field) {
                'category_key' => self::CATEGORY_LABELS[$key] ?? Str::headline(str_replace('_', ' ', $key)),
                'status_group' => Str::headline(str_replace('_', ' ', $key)),
                'method_key', 'gateway_key' => $key === 'not_recorded' ? 'Not recorded' : Str::headline($key),
                default => Str::upper($key),
            };

            return [
                'label' => $label,
                'value' => number_format($count).' ('.$percent.'%)',
                'count' => $count,
                'percent' => $percent,
                'tone' => $tone,
                'color' => $this->toneColor($tone),
                'key' => $key,
            ];
        })->values()->all();
    }

    private function filterOptions(Collection $rows, string $valueField, string $labelField): array
    {
        return $rows
            ->map(fn (object $row): array => ['value' => $row->{$valueField}, 'label' => $row->{$labelField}])
            ->filter(fn (array $option): bool => $option['value'] !== '')
            ->unique('value')
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function trend(Collection $rows): array
    {
        $firstMonth = now()->startOfMonth()->subMonths(5);
        $months = collect(range(0, 5))->map(function (int $offset) use ($rows, $firstMonth): array {
            $month = $firstMonth->copy()->addMonths($offset);
            $nextMonth = $month->copy()->addMonth();
            $items = $rows->filter(fn (object $row): bool => $row->sort_timestamp >= $month->timestamp && $row->sort_timestamp < $nextMonth->timestamp);

            return [
                'label' => $month->format('M'),
                'period' => $month->format('M Y'),
                'count' => $items->count(),
                'amount' => round((float) $items->sum('amount'), 2),
            ];
        });
        $max = max(0.0, (float) $months->max('amount'));

        if (! $months->contains(fn (array $month): bool => $month['count'] > 0)) {
            return [];
        }

        return $months->map(function (array $month) use ($max): array {
            $month['height'] = $max > 0 && $month['amount'] > 0
                ? max(4, min(100, (int) round(($month['amount'] / $max) * 100)))
                : 0;
            return $month;
        })->all();
    }

    private function recentActivity(Collection $rows): array
    {
        return $rows->take(5)->map(function (object $row): array {
            $subject = $row->order_reference !== 'Not linked to an order' ? $row->order_reference : $row->reference;
            $label = $row->payment_id
                ? 'Payment '.$row->status_label.' for '.$subject
                : 'Order '.$subject.' payment '.$row->status_label;

            return [
                'label' => $label,
                'time' => $row->sort_timestamp > 0 ? now()->setTimestamp($row->sort_timestamp)->diffForHumans() : 'Not recorded',
                'tone' => match ($row->status_group) {
                    'paid' => 'green',
                    'pending' => 'blue',
                    'refunded' => 'purple',
                    default => 'orange',
                },
            ];
        })->values()->all();
    }

    private function amountForStatuses(Collection $rows, array $statuses): float
    {
        return round((float) $rows->filter(fn (object $row): bool => in_array($row->status, $statuses, true))->sum('amount'), 2);
    }

    private function netReceived(Collection $rows): float
    {
        return round($this->amountForStatuses($rows, ['paid', 'captured']) - $this->amountForStatuses($rows, ['refunded', 'partially_refunded']), 2);
    }

    private function reconciliationKey(string $status, ?string $requested = null): string
    {
        return match (strtolower((string) $requested)) {
            'reconciled' => 'reconciled',
            'pending' => 'pending',
            'unreconciled' => 'unreconciled',
            default => match ($status) {
                'paid', 'captured', 'refunded', 'partially_refunded' => 'reconciled',
                'pending', 'awaiting_payment', 'pay_on_delivery' => 'pending',
                default => 'unreconciled',
            },
        };
    }

    private function statusGroup(string $status): string
    {
        return match ($status) {
            'paid', 'captured' => 'paid',
            'pending', 'awaiting_payment', 'pay_on_delivery' => 'pending',
            'failed', 'cancelled', 'canceled' => 'failed',
            'refunded', 'partially_refunded' => 'refunded',
            'chargeback' => 'chargeback',
            default => 'other',
        };
    }

    private function normaliseKey(mixed $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        return $value !== '' ? str_replace('_', ' ', $value) : 'not_recorded';
    }

    private function displayKey(string $key): string
    {
        return $key === 'not_recorded' ? 'Not recorded' : Str::headline($key);
    }

    private function formatAmount(float $amount): string
    {
        return '€'.number_format($amount, 2);
    }

    private function toneColor(string $tone): string
    {
        return match ($tone) {
            'green' => '#147841',
            'blue' => '#2573c6',
            'orange' => '#ef8b20',
            'purple' => '#724aaf',
            'teal' => '#159c9d',
            'red' => '#df4352',
            default => '#777',
        };
    }

    private function donutStyle(array $segments): string
    {
        $segments = array_values(array_filter($segments, fn (array $segment): bool => (int) ($segment['count'] ?? 0) > 0));
        $total = array_sum(array_map(fn (array $segment): int => (int) $segment['count'], $segments));
        if ($total === 0) {
            return 'background: conic-gradient(#e7efeb 0 100%);';
        }

        $offset = 0.0;
        $stops = [];
        foreach ($segments as $segment) {
            $next = $offset + (((int) $segment['count'] / $total) * 100);
            $stops[] = ($segment['color'] ?? $this->toneColor($segment['tone'] ?? 'gray')).' '.$offset.'% '.$next.'%';
            $offset = $next;
        }

        return 'background: conic-gradient('.implode(',', $stops).');';
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
