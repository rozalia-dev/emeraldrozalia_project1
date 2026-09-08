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
        $tab = array_key_exists($request->string('tab')->toString(), self::TABS)
            ? $request->string('tab')->toString()
            : 'all';
        $transactions = PaymentTransaction::query()
            ->with(['order.user'])
            ->latest('created_at')
            ->limit(500)
            ->get();
        $orders = Order::query()
            ->with('user')
            ->latest('created_at')
            ->limit(500)
            ->get();
        $hasLiveData = $transactions->isNotEmpty() || $orders->isNotEmpty();
        $rows = $this->rows($transactions, $orders, $request, $tab);
        $preview = !$hasLiveData;

        if ($preview) {
            $rows = $this->filterRows($this->demoRows(), $request, $tab);
        }

        return view('admin.payments.index', [
            'rows' => $this->paginate($rows, $request),
            'tabs' => self::TABS,
            'tab' => $tab,
            'search' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'category' => $request->string('category')->toString(),
            'method' => $request->string('method')->toString(),
            'gateway' => $request->string('gateway')->toString(),
            'preview' => $preview,
            'metrics' => $this->metrics($transactions, $orders, $preview),
            'reconciliation' => $this->reconciliation($transactions, $orders, $preview),
            'methods' => $this->methods($transactions, $orders, $preview),
            'categoryBreakdown' => $this->categoryBreakdown($transactions, $orders, $preview),
            'recentActivity' => $this->recentActivity($transactions, $orders, $preview),
        ]);
    }

    public function action(Request $request, PaymentTransaction $payment): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:capture,refund,cancel'],
        ]);
        $payment->load('order');

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
        $transactions = PaymentTransaction::query()->with(['order.user'])->latest('created_at')->limit(500)->get();
        $orders = Order::query()->with('user')->latest('created_at')->limit(500)->get();
        $rows = $this->rows($transactions, $orders, $request, $request->string('tab')->toString() ?: 'all');

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

    private function rows(Collection $transactions, Collection $orders, Request $request, string $tab): Collection
    {
        $rows = $transactions->map(fn (PaymentTransaction $payment) => $this->transactionRow($payment));
        if ($rows->isEmpty()) {
            $rows = $orders->map(fn (Order $order) => $this->orderRow($order));
        }

        return $this->filterRows($rows, $request, $tab);
    }

    private function filterRows(Collection $rows, Request $request, string $tab): Collection
    {
        $query = mb_strtolower(trim($request->string('q')->toString()));
        $status = $request->string('status')->toString();
        $category = mb_strtolower($request->string('category')->toString());
        $method = mb_strtolower($request->string('method')->toString());
        $gateway = mb_strtolower($request->string('gateway')->toString());

        return $rows->filter(function (object $row) use ($query, $status, $category, $method, $gateway, $tab): bool {
            if ($tab !== 'all' && !$this->tabMatches($row->status, $tab)) {
                return false;
            }
            if ($status && $row->status !== $status) {
                return false;
            }
            if ($category && mb_strtolower($row->category_key) !== $category) {
                return false;
            }
            if ($method && !str_contains(mb_strtolower($row->method), $method)) {
                return false;
            }
            if ($gateway && !str_contains(mb_strtolower($row->gateway), $gateway)) {
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
        $method = $payload['payment_method'] ?? $order?->payment_method ?? $payment->provider ?: 'manual';
        $gateway = $payload['gateway'] ?? $payment->provider ?: 'Manual';
        $categoryKey = $order?->order_type ?: 'online';
        $created = $payment->created_at ?: now();

        return (object) [
            'payment_id' => $payment->id,
            'order_id' => $order?->id,
            'reference' => $payment->transaction_id ?: 'PAY-'.now()->format('ymd').'-'.str_pad((string) $payment->id, 5, '0', STR_PAD_LEFT),
            'uuid' => $payment->public_uuid ?: 'PAY-'.$payment->id,
            'order_reference' => $order?->number ?: '—',
            'category_key' => $categoryKey,
            'category' => self::CATEGORY_LABELS[$categoryKey] ?? ucfirst(str_replace('_', ' ', $categoryKey)),
            'customer' => $order?->user?->name ?: ($order?->email ?: 'Guest customer'),
            'customer_type' => $payload['customer_type'] ?? ($order?->order_type === 'corporate' ? 'Corporate Account' : 'Retail Customer'),
            'method' => Str::headline(str_replace('_', ' ', $method)),
            'gateway' => Str::headline(str_replace('_', ' ', $gateway)),
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency ?: ($order?->currency ?: 'EUR'),
            'status' => $status,
            'status_label' => Str::headline(str_replace('_', ' ', $status)),
            'payment_date' => $created->format('d M Y, H:i'),
            'gateway_reference' => $payment->transaction_id ?: ($payload['gateway_reference'] ?? 'N/A'),
            'reconciliation' => $payload['reconciliation_status'] ?? ($status === 'paid' ? 'Reconciled' : 'Pending'),
            'sort_timestamp' => $created->timestamp,
            'actionable' => true,
        ];
    }

    private function orderRow(Order $order): object
    {
        $status = match ($order->payment_status) {
            'paid' => 'paid',
            'refunded' => 'refunded',
            'failed' => 'failed',
            default => 'pending',
        };
        $created = $order->created_at ?: now();
        $categoryKey = $order->order_type ?: 'online';
        return (object) [
            'payment_id' => null,
            'order_id' => $order->id,
            'reference' => 'PAY-'.$created->format('ymd').'-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
            'uuid' => $order->public_uuid ?: 'ORD-'.$order->id,
            'order_reference' => $order->number,
            'category_key' => $categoryKey,
            'category' => self::CATEGORY_LABELS[$categoryKey] ?? ucfirst(str_replace('_', ' ', $categoryKey)),
            'customer' => $order->user?->name ?: ($order->email ?: 'Guest customer'),
            'customer_type' => $categoryKey === 'corporate' ? 'Corporate Account' : 'Retail Customer',
            'method' => Str::headline(str_replace('_', ' ', $order->payment_method ?: 'manual')),
            'gateway' => 'Manual',
            'amount' => (float) $order->total,
            'currency' => $order->currency ?: 'EUR',
            'status' => $status,
            'status_label' => Str::headline($status),
            'payment_date' => $created->format('d M Y, H:i'),
            'gateway_reference' => 'N/A',
            'reconciliation' => $status === 'paid' ? 'Reconciled' : 'Pending',
            'sort_timestamp' => $created->timestamp,
            'actionable' => false,
        ];
    }

    private function metrics(Collection $transactions, Collection $orders, bool $preview): array
    {
        if ($preview) {
            return [
                ['label' => 'Total Transactions (This Month)', 'value' => '3,842', 'icon' => 'credit-card', 'tone' => 'green', 'change' => '16.4%', 'direction' => 'up'],
                ['label' => 'Total Amount Received', 'value' => '€482,765.75', 'icon' => 'credit-card', 'tone' => 'blue', 'change' => '18.7%', 'direction' => 'up'],
                ['label' => 'Pending Payments', 'value' => '€18,240.60', 'icon' => 'clock', 'tone' => 'purple', 'change' => '4.8%', 'direction' => 'down'],
                ['label' => 'Failed Payments', 'value' => '€2,450.30', 'icon' => 'alert', 'tone' => 'orange', 'change' => '8.3%', 'direction' => 'down'],
                ['label' => 'Refunds Processed', 'value' => '€26,870.40', 'icon' => 'rotate-ccw', 'tone' => 'teal', 'change' => '12.9%', 'direction' => 'up'],
                ['label' => 'Chargebacks', 'value' => '€1,125.20', 'icon' => 'alert', 'tone' => 'red', 'change' => '15.6%', 'direction' => 'down'],
            ];
        }
        $fallbackOrders = $transactions->isEmpty() ? $orders : collect();
        $count = $transactions->isNotEmpty() ? $transactions->count() : $fallbackOrders->count();
        $received = $transactions->isNotEmpty()
            ? (float) $transactions->whereIn('status', ['paid', 'captured'])->sum('amount')
            : (float) $fallbackOrders->where('payment_status', 'paid')->sum('total');
        $pending = $transactions->isNotEmpty()
            ? (float) $transactions->whereIn('status', ['pending', 'awaiting_payment', 'pay_on_delivery'])->sum('amount')
            : (float) $fallbackOrders->whereIn('payment_status', ['pending', 'pay_on_delivery'])->sum('total');
        $failed = $transactions->isNotEmpty()
            ? (float) $transactions->whereIn('status', ['failed', 'cancelled', 'canceled'])->sum('amount')
            : (float) $fallbackOrders->where('payment_status', 'failed')->sum('total');
        $refunds = $transactions->isNotEmpty()
            ? (float) $transactions->whereIn('status', ['refunded', 'partially_refunded'])->sum('amount')
            : (float) $fallbackOrders->where('payment_status', 'refunded')->sum('total');
        $chargebacks = (float) $transactions->where('status', 'chargeback')->sum('amount');

        return [
            ['label' => 'Total Transactions (This Month)', 'value' => number_format($count), 'icon' => 'credit-card', 'tone' => 'green', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Total Amount Received', 'value' => '€'.number_format($received, 2), 'icon' => 'credit-card', 'tone' => 'blue', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Pending Payments', 'value' => '€'.number_format($pending, 2), 'icon' => 'clock', 'tone' => 'purple', 'change' => '—', 'direction' => 'down'],
            ['label' => 'Failed Payments', 'value' => '€'.number_format($failed, 2), 'icon' => 'alert', 'tone' => 'orange', 'change' => '—', 'direction' => 'down'],
            ['label' => 'Refunds Processed', 'value' => '€'.number_format($refunds, 2), 'icon' => 'rotate-ccw', 'tone' => 'teal', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Chargebacks', 'value' => '€'.number_format($chargebacks, 2), 'icon' => 'alert', 'tone' => 'red', 'change' => '—', 'direction' => 'down'],
        ];
    }

    private function reconciliation(Collection $transactions, Collection $orders, bool $preview): array
    {
        if ($preview) {
            return ['total' => '3,842', 'reconciled' => '3,214 (83.7%)', 'pending' => '412 (10.7%)', 'unreconciled' => '216 (5.6%)'];
        }
        $total = $transactions->count() ?: $orders->count();
        $reconciled = $transactions->filter(fn (PaymentTransaction $payment) => ($payment->payload['reconciliation_status'] ?? null) === 'reconciled' || $payment->status === 'paid')->count();
        $pending = $transactions->filter(fn (PaymentTransaction $payment) => ($payment->payload['reconciliation_status'] ?? null) === 'pending' || in_array($payment->status, ['pending', 'awaiting_payment'], true))->count();
        $unreconciled = max(0, $total - $reconciled - $pending);
        $percent = fn (int $value) => $total > 0 ? round(($value / $total) * 100, 1) : 0;
        return ['total' => number_format($total), 'reconciled' => number_format($reconciled).' ('.$percent($reconciled).'%)', 'pending' => number_format($pending).' ('.$percent($pending).'%)', 'unreconciled' => number_format($unreconciled).' ('.$percent($unreconciled).'%)'];
    }

    private function methods(Collection $transactions, Collection $orders, bool $preview): array
    {
        if ($preview) {
            return [['label' => 'VISA / Mastercard', 'value' => '1,824 (47.5%)', 'tone' => 'purple'], ['label' => 'Bank Transfer', 'value' => '985 (25.6%)', 'tone' => 'dark'], ['label' => 'PayPal', 'value' => '562 (14.6%)', 'tone' => 'blue'], ['label' => 'Apple Pay / Google Pay', 'value' => '312 (8.1%)', 'tone' => 'orange'], ['label' => 'Other Wallets', 'value' => '159 (4.2%)', 'tone' => 'violet']];
        }
        $rows = $transactions->isNotEmpty() ? $transactions->map(fn (PaymentTransaction $payment) => Str::headline(str_replace('_', ' ', $payment->order?->payment_method ?: $payment->provider ?: 'manual'))) : $orders->map(fn (Order $order) => Str::headline(str_replace('_', ' ', $order->payment_method ?: 'manual')));
        $counts = $rows->countBy();
        $total = max(1, $rows->count());
        return $counts->sortDesc()->take(5)->map(fn ($count, $label) => ['label' => $label, 'value' => number_format($count).' ('.round(($count / $total) * 100, 1).'%)', 'tone' => 'dark'])->values()->all();
    }

    private function categoryBreakdown(Collection $transactions, Collection $orders, bool $preview): array
    {
        if ($preview) {
            return [['label' => 'Online Orders', 'value' => '1,482 (38.6%)', 'tone' => 'blue'], ['label' => 'Corporate Orders', 'value' => '882 (22.4%)', 'tone' => 'purple'], ['label' => 'Bulk Orders', 'value' => '512 (13.2%)', 'tone' => 'orange'], ['label' => 'Franchise Orders', 'value' => '398 (10.4%)', 'tone' => 'green'], ['label' => 'Franchise Retail Orders', 'value' => '324 (8.4%)', 'tone' => 'teal'], ['label' => 'Buyer Orders', 'value' => '262 (6.8%)', 'tone' => 'gray']];
        }
        $items = $transactions->isNotEmpty() ? $transactions->map(fn (PaymentTransaction $payment) => self::CATEGORY_LABELS[$payment->order?->order_type ?: 'online'] ?? 'Online Orders') : $orders->map(fn (Order $order) => self::CATEGORY_LABELS[$order->order_type ?: 'online'] ?? 'Online Orders');
        $counts = $items->countBy();
        $total = max(1, $items->count());
        return $counts->sortDesc()->map(fn ($count, $label) => ['label' => $label, 'value' => number_format($count).' ('.round(($count / $total) * 100, 1).'%)', 'tone' => 'green'])->values()->all();
    }

    private function recentActivity(Collection $transactions, Collection $orders, bool $preview): array
    {
        if ($preview) {
            return [['label' => 'Payment received for ORD-250501-00678', 'time' => '9 min ago', 'tone' => 'green'], ['label' => 'Payment received for CHK-250501-00098', 'time' => '18 min ago', 'tone' => 'green'], ['label' => 'Refund processed for ORD-250430-00612', 'time' => '25 min ago', 'tone' => 'orange'], ['label' => 'New card payment pending review', 'time' => '32 min ago', 'tone' => 'blue'], ['label' => 'Reconciliation completed for 48 transactions', 'time' => '45 min ago', 'tone' => 'purple']];
        }
        $items = $transactions->take(5)->map(fn (PaymentTransaction $payment) => ['label' => 'Payment '.$payment->status.' for '.($payment->order?->number ?: 'order'), 'time' => $payment->created_at?->diffForHumans() ?: 'Recently', 'tone' => $payment->status === 'paid' ? 'green' : 'orange']);
        return $items->isNotEmpty() ? $items->values()->all() : $orders->take(5)->map(fn (Order $order) => ['label' => 'Order '.$order->number.' payment '.$order->payment_status, 'time' => $order->created_at?->diffForHumans() ?: 'Recently', 'tone' => $order->payment_status === 'paid' ? 'green' : 'blue'])->values()->all();
    }

    private function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 8)));
        $page = max(1, (int) $request->query('page', 1));
        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);
    }

    private function demoRows(): Collection
    {
        return collect([
            ['reference' => 'PAY-250501-00098', 'uuid' => 'PAY-7A1F3C8E9D4B', 'order_reference' => 'ORD-250501-00678', 'category_key' => 'online', 'category' => 'Online Orders', 'customer' => 'Emma Walsh', 'customer_type' => 'Retail Customer', 'method' => 'Visa •••• 4242', 'gateway' => 'Stripe', 'amount' => 156.80, 'currency' => 'EUR', 'status' => 'paid', 'status_label' => 'Paid', 'payment_date' => '01 May 2025, 11:40', 'gateway_reference' => 'pi_3M988K1L2V', 'reconciliation' => 'Reconciled', 'sort_timestamp' => 8, 'payment_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'PAY-250501-00097', 'uuid' => 'PAY-78CD28AB1F56', 'order_reference' => 'ORD-250501-00679', 'category_key' => 'corporate', 'category' => 'Corporate Orders', 'customer' => 'Global Wholesale Inc.', 'customer_type' => 'Corporate Account', 'method' => 'Bank Transfer', 'gateway' => 'Manual', 'amount' => 2430.00, 'currency' => 'EUR', 'status' => 'paid', 'status_label' => 'Paid', 'payment_date' => '01 May 2025, 10:56', 'gateway_reference' => 'BT-250501-77891', 'reconciliation' => 'Reconciled', 'sort_timestamp' => 7, 'payment_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'PAY-250501-00096', 'uuid' => 'PAY-FA26E05A3B11', 'order_reference' => 'ORD-250501-00680', 'category_key' => 'bulk', 'category' => 'Bulk Orders', 'customer' => 'Patrick Doyle', 'customer_type' => 'Retail Customer', 'method' => 'PayPal', 'gateway' => 'PayPal', 'amount' => 198.75, 'currency' => 'EUR', 'status' => 'paid', 'status_label' => 'Paid', 'payment_date' => '01 May 2025, 09:30', 'gateway_reference' => '8YJ12345PV675432L', 'reconciliation' => 'Reconciled', 'sort_timestamp' => 6, 'payment_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'PAY-250501-00095', 'uuid' => 'PAY-FA26E05A9B55', 'order_reference' => 'ORD-250501-00681', 'category_key' => 'franchise', 'category' => 'Franchise Orders', 'customer' => 'Emerald Store - Galway', 'customer_type' => 'Franchisee', 'method' => 'Visa •••• 1111', 'gateway' => 'Stripe', 'amount' => 344.60, 'currency' => 'EUR', 'status' => 'pending', 'status_label' => 'Pending', 'payment_date' => '01 May 2025, 09:10', 'gateway_reference' => 'pi_3M9C9K2V3W', 'reconciliation' => 'Pending', 'sort_timestamp' => 5, 'payment_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'PAY-250501-00094', 'uuid' => 'PAY-F23A8CBE022', 'order_reference' => 'ORD-250501-00682', 'category_key' => 'franchise_retail', 'category' => 'Franchise Retail Orders', 'customer' => 'Sarah Kelly', 'customer_type' => 'Retail Customer', 'method' => 'Apple Pay', 'gateway' => 'Stripe', 'amount' => 124.95, 'currency' => 'EUR', 'status' => 'paid', 'status_label' => 'Paid', 'payment_date' => '01 May 2025, 08:45', 'gateway_reference' => 'pi_3M9C9L10KX', 'reconciliation' => 'Reconciled', 'sort_timestamp' => 4, 'payment_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'PAY-250501-00093', 'uuid' => 'PAY-3C281A9D7F66', 'order_reference' => 'ORD-250501-00683', 'category_key' => 'buyer', 'category' => 'Buyer Orders', 'customer' => 'Michael O’Connor', 'customer_type' => 'Buyer', 'method' => 'Mastercard •••• 8888', 'gateway' => 'Stripe', 'amount' => 89.50, 'currency' => 'EUR', 'status' => 'failed', 'status_label' => 'Failed', 'payment_date' => '01 May 2025, 08:30', 'gateway_reference' => 'pi_3M9B7JQH9Y', 'reconciliation' => 'N/A', 'sort_timestamp' => 3, 'payment_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'PAY-250430-00092', 'uuid' => 'PAY-6D7EF09A844', 'order_reference' => 'ORD-250430-00612', 'category_key' => 'online', 'category' => 'Online Orders', 'customer' => 'Aoife Byrne', 'customer_type' => 'Retail Customer', 'method' => 'Visa •••• 4242', 'gateway' => 'Stripe', 'amount' => 213.40, 'currency' => 'EUR', 'status' => 'refunded', 'status_label' => 'Refunded', 'payment_date' => '30 Apr 2025, 15:15', 'gateway_reference' => 're_3M8A6K8L3T', 'reconciliation' => 'Reconciled', 'sort_timestamp' => 2, 'payment_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'PAY-250430-00091', 'uuid' => 'PAY-F2E3D8C4B888', 'order_reference' => 'ORD-250430-00611', 'category_key' => 'corporate', 'category' => 'Corporate Orders', 'customer' => 'Acme Retail Ltd.', 'customer_type' => 'Corporate Account', 'method' => 'Bank Transfer', 'gateway' => 'Manual', 'amount' => 1560.00, 'currency' => 'EUR', 'status' => 'paid', 'status_label' => 'Paid', 'payment_date' => '30 Apr 2025, 16:20', 'gateway_reference' => 'BT-250430-55721', 'reconciliation' => 'Reconciled', 'sort_timestamp' => 1, 'payment_id' => null, 'order_id' => null, 'actionable' => false],
        ])->map(fn (array $row) => (object) $row);
    }
}
