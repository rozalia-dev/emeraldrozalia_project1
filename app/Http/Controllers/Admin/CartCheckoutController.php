<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Models\Order;
use App\Services\AuditTrail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CartCheckoutController extends Controller
{
    private const STATUSES = ['active', 'checkout_started', 'payment_pending', 'abandoned', 'saved', 'completed'];

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
        $records = AdminRecord::query()
            ->where('module', 'cart-checkout')
            ->latest('created_at')
            ->get();
        $orders = Order::query()
            ->where('order_type', 'online')
            ->with('user')
            ->latest('created_at')
            ->limit(250)
            ->get();
        $hasLiveData = $records->isNotEmpty() || $orders->isNotEmpty();

        $rows = $this->rows($records, $orders, $request, $tab);
        $preview = !$hasLiveData;
        if ($preview) {
            $rows = $this->demoRows();
        }

        $paginator = $this->paginate($rows, $request);
        $metrics = $this->metrics($records, $orders, $preview);

        return view('admin.cart-checkout.index', [
            'metrics' => $metrics,
            'rows' => $paginator,
            'tabs' => self::TABS,
            'tab' => $tab,
            'status' => $request->string('status')->toString(),
            'source' => $request->string('source')->toString(),
            'search' => $request->string('q')->toString(),
            'preview' => $preview,
            'funnel' => $this->funnel($metrics),
            'sources' => $this->sources($rows),
            'recentActivity' => $this->recentActivity($records, $orders, $preview),
        ]);
    }

    public function action(Request $request, AdminRecord $record): RedirectResponse
    {
        abort_unless($record->module === 'cart-checkout', 404);
        $data = $request->validate([
            'action' => ['required', 'in:mark_abandoned,restore,complete,send_recovery'],
        ]);
        $before = $record->toArray();
        $payload = $record->data ?: [];

        match ($data['action']) {
            'mark_abandoned' => $record->update(['status' => 'abandoned']),
            'restore' => $record->update(['status' => 'active']),
            'complete' => $record->update(['status' => 'completed']),
            'send_recovery' => $record->update(['data' => array_merge($payload, [
                'recovery_sent_at' => now()->toIso8601String(),
            ])]),
        };

        AuditTrail::record('admin.cart_checkout.'. $data['action'], $record, $before, $record->fresh()->toArray());

        return back()->with('success', match ($data['action']) {
            'mark_abandoned' => 'Cart marked as abandoned.',
            'restore' => 'Cart restored to active follow-up.',
            'complete' => 'Checkout marked as completed.',
            'send_recovery' => 'Recovery email queued for this cart.',
        });
    }

    public function export(Request $request): StreamedResponse
    {
        $records = AdminRecord::query()->where('module', 'cart-checkout')->latest('created_at')->get();
        $orders = Order::query()->where('order_type', 'online')->with('user')->latest('created_at')->limit(250)->get();
        $rows = $this->rows($records, $orders, $request, $request->string('tab')->toString() ?: 'overview');

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Cart / Checkout ID', 'Customer', 'Email', 'Source', 'Status', 'Value', 'Progress', 'Last activity']);
            foreach ($rows as $row) {
                fputcsv($handle, [$row->reference, $row->customer, $row->email, $row->source, $row->status_label, number_format($row->amount, 2, '.', ''), $row->progress_label, $row->last_activity]);
            }
            fclose($handle);
        }, 'cart-checkout-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function rows(Collection $records, Collection $orders, Request $request, string $tab): Collection
    {
        $requestedStatus = $request->string('status')->toString();
        $requestedSource = $request->string('source')->toString();
        $query = mb_strtolower(trim($request->string('q')->toString()));
        $tabStatuses = match ($tab) {
            'abandoned' => ['abandoned'],
            'saved' => ['saved'],
            'sessions' => ['checkout_started', 'payment_pending'],
            default => [],
        };

        $cartRows = $records->map(fn (AdminRecord $record) => $this->recordRow($record))
            ->filter(function (object $row) use ($requestedStatus, $requestedSource, $query, $tabStatuses): bool {
                if ($tabStatuses && !in_array($row->status, $tabStatuses, true)) {
                    return false;
                }
                if ($requestedStatus && $row->status !== $requestedStatus) {
                    return false;
                }
                if ($requestedSource && mb_strtolower($row->source) !== mb_strtolower($requestedSource)) {
                    return false;
                }
                return $query === '' || str_contains(mb_strtolower(implode(' ', [$row->reference, $row->customer, $row->email, $row->source])), $query);
            });

        if ($tab !== 'abandoned' && $tab !== 'saved' && $tab !== 'sessions') {
            $cartRows = $cartRows->concat($orders->map(fn (Order $order) => $this->orderRow($order)));
        }

        return $cartRows
            ->filter(function (object $row) use ($requestedStatus, $requestedSource, $query): bool {
                if ($requestedStatus && $row->status !== $requestedStatus) {
                    return false;
                }
                if ($requestedSource && mb_strtolower($row->source) !== mb_strtolower($requestedSource)) {
                    return false;
                }
                return $query === '' || str_contains(mb_strtolower(implode(' ', [$row->reference, $row->customer, $row->email, $row->source])), $query);
            })
            ->sortByDesc('sort_timestamp')
            ->values();
    }

    private function recordRow(AdminRecord $record): object
    {
        $data = $record->data ?: [];
        $status = in_array($record->status, self::STATUSES, true) ? $record->status : 'active';
        $progress = (int) ($data['progress'] ?? match ($status) {
            'completed' => 5,
            'payment_pending' => 4,
            'checkout_started' => 3,
            default => 2,
        });
        $created = $record->created_at ?: now();

        return (object) [
            'record_id' => $record->id,
            'reference' => $record->reference ?: 'CART-'.$record->id,
            'customer' => $data['customer'] ?? $record->title,
            'email' => $data['email'] ?? '—',
            'source' => $data['source'] ?? 'Website',
            'status' => $status,
            'status_label' => str_replace('_', ' ', ucfirst($status)),
            'amount' => (float) ($record->amount ?? $data['amount'] ?? 0),
            'progress' => min(5, max(1, $progress)),
            'progress_label' => min(5, max(1, $progress)).' / 5',
            'progress_percent' => min(100, max(20, $progress * 20)),
            'last_activity' => $data['last_activity'] ?? $created->format('d M Y, H:i'),
            'sort_timestamp' => $created->timestamp,
            'order_id' => null,
            'actionable' => true,
        ];
    }

    private function orderRow(Order $order): object
    {
        $status = $order->status === 'completed' ? 'completed' : ($order->status === 'processing' ? 'checkout_started' : 'payment_pending');
        $created = $order->created_at ?: now();
        return (object) [
            'record_id' => null,
            'reference' => $order->number,
            'customer' => $order->user?->name ?: ($order->email ?: 'Guest customer'),
            'email' => $order->email ?: $order->user?->email ?: '—',
            'source' => $order->payment_method ? ucfirst(str_replace('_', ' ', $order->payment_method)) : 'Website',
            'status' => $status,
            'status_label' => $order->status === 'completed' ? 'Completed' : ucfirst(str_replace('_', ' ', $status)),
            'amount' => (float) $order->total,
            'progress' => $order->status === 'completed' ? 5 : 4,
            'progress_label' => ($order->status === 'completed' ? 5 : 4).' / 5',
            'progress_percent' => $order->status === 'completed' ? 100 : 80,
            'last_activity' => $created->format('d M Y, H:i'),
            'sort_timestamp' => $created->timestamp,
            'order_id' => $order->id,
            'actionable' => false,
        ];
    }

    private function metrics(Collection $records, Collection $orders, bool $preview): array
    {
        if ($preview) {
            return [
                ['label' => 'Active Carts', 'value' => '1,856', 'icon' => 'shopping-bag', 'tone' => 'green', 'change' => '12.5%', 'direction' => 'up'],
                ['label' => 'Abandoned Carts', 'value' => '1,243', 'icon' => 'shopping-bag', 'tone' => 'orange', 'change' => '8.7%', 'direction' => 'up'],
                ['label' => 'Checkouts Started', 'value' => '3,521', 'icon' => 'arrow-right', 'tone' => 'blue', 'change' => '14.3%', 'direction' => 'up'],
                ['label' => 'Completed Orders', 'value' => '2,278', 'icon' => 'check', 'tone' => 'purple', 'change' => '16.8%', 'direction' => 'up'],
                ['label' => 'Conversion Rate', 'value' => '64.72%', 'icon' => 'percent', 'tone' => 'teal', 'change' => '2.9%', 'direction' => 'up'],
                ['label' => 'Revenue from Checkout', 'value' => '€428,765.75', 'icon' => 'credit-card', 'tone' => 'red', 'change' => '18.6%', 'direction' => 'up'],
            ];
        }
        $active = $records->whereIn('status', ['active', 'checkout_started', 'payment_pending'])->count();
        $abandoned = $records->where('status', 'abandoned')->count();
        $completed = $orders->where('status', 'completed')->count() + $records->where('status', 'completed')->count();
        $started = $records->count() + $orders->count();
        $revenue = (float) $orders->whereIn('payment_status', ['paid', 'pay_on_delivery'])->sum('total');
        $conversion = $started > 0 ? round(($completed / $started) * 100, 2) : 0;

        return [
            ['label' => 'Active Carts', 'value' => number_format($active), 'icon' => 'shopping-bag', 'tone' => 'green', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Abandoned Carts', 'value' => number_format($abandoned), 'icon' => 'shopping-bag', 'tone' => 'orange', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Checkouts Started', 'value' => number_format($started), 'icon' => 'arrow-right', 'tone' => 'blue', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Completed Orders', 'value' => number_format($completed), 'icon' => 'check', 'tone' => 'purple', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Conversion Rate', 'value' => number_format($conversion, 2).'%', 'icon' => 'percent', 'tone' => 'teal', 'change' => '—', 'direction' => 'up'],
            ['label' => 'Revenue from Checkout', 'value' => '€'.number_format($revenue, 2), 'icon' => 'credit-card', 'tone' => 'red', 'change' => '—', 'direction' => 'up'],
        ];
    }

    private function funnel(array $metrics): array
    {
        return [
            ['label' => 'Cart Created', 'value' => $metrics[0]['value'], 'percent' => 100, 'tone' => 'green'],
            ['label' => 'Checkout Started', 'value' => $metrics[2]['value'], 'percent' => 78, 'tone' => 'blue'],
            ['label' => 'Shipping Method', 'value' => '2,846', 'percent' => 68, 'tone' => 'purple'],
            ['label' => 'Payment Method', 'value' => '2,512', 'percent' => 57, 'tone' => 'orange'],
            ['label' => 'Order Completed', 'value' => $metrics[3]['value'], 'percent' => 48, 'tone' => 'green'],
        ];
    }

    private function sources(Collection $rows): array
    {
        $counts = $rows->countBy('source');
        $total = max(1, $rows->count());
        return $counts->map(fn ($count, $source) => ['label' => $source, 'count' => $count, 'percent' => round(($count / $total) * 100, 1)])->values()->all();
    }

    private function recentActivity(Collection $records, Collection $orders, bool $preview): array
    {
        if ($preview) {
            return [
                ['label' => 'Order ORD-250501-00678 completed', 'time' => '9 min ago', 'tone' => 'green'],
                ['label' => 'Payment received for CHK-250501-00098', 'time' => '18 min ago', 'tone' => 'green'],
                ['label' => 'Abandoned cart recovery email sent', 'time' => '25 min ago', 'tone' => 'orange'],
                ['label' => 'New cart created by Emma Walsh', 'time' => '32 min ago', 'tone' => 'blue'],
                ['label' => 'Discount code applied: ER100OFF', 'time' => '45 min ago', 'tone' => 'purple'],
            ];
        }
        return $orders->take(5)->map(fn (Order $order) => [
            'label' => 'Order '.$order->number.' '.$order->status,
            'time' => $order->created_at?->diffForHumans() ?: 'Recently',
            'tone' => $order->status === 'completed' ? 'green' : 'blue',
        ])->values()->all();
    }

    private function demoRows(): Collection
    {
        return collect([
            ['reference' => 'CART-250501-00123', 'customer' => 'Emma Walsh', 'email' => 'emma.walsh@email.com', 'source' => 'Website', 'status' => 'active', 'status_label' => 'Active', 'amount' => 168.80, 'progress' => 3, 'progress_label' => '3 / 5', 'progress_percent' => 60, 'last_activity' => '01 May 2025, 11:40', 'sort_timestamp' => 8, 'record_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'CART-250501-00124', 'customer' => 'John Smith', 'email' => '+353 87 654 3210', 'source' => 'Mobile App', 'status' => 'abandoned', 'status_label' => 'Abandoned', 'amount' => 89.50, 'progress' => 2, 'progress_label' => '2 / 5', 'progress_percent' => 40, 'last_activity' => '01 May 2025, 10:22', 'sort_timestamp' => 7, 'record_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'CHK-250501-00098', 'customer' => 'Aoife Byrne', 'email' => 'aoife.byrne@email.com', 'source' => 'Website', 'status' => 'checkout_started', 'status_label' => 'In Progress', 'amount' => 213.40, 'progress' => 4, 'progress_label' => '4 / 5', 'progress_percent' => 80, 'last_activity' => '01 May 2025, 11:30', 'sort_timestamp' => 6, 'record_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'ORD-250501-00678', 'customer' => 'Michael O’Connor', 'email' => 'michael.oconnor@email.com', 'source' => 'Website', 'status' => 'completed', 'status_label' => 'Completed', 'amount' => 124.95, 'progress' => 5, 'progress_label' => '5 / 5', 'progress_percent' => 100, 'last_activity' => '01 May 2025, 09:58', 'sort_timestamp' => 5, 'record_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'CART-250430-00987', 'customer' => 'Sarah Kelly', 'email' => '+353 87 890 1234', 'source' => 'Instagram', 'status' => 'active', 'status_label' => 'Active', 'amount' => 344.60, 'progress' => 3, 'progress_label' => '3 / 5', 'progress_percent' => 60, 'last_activity' => '30 Apr 2025, 19:15', 'sort_timestamp' => 4, 'record_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'CART-250430-00988', 'customer' => 'Patrick Doyle', 'email' => 'patrick.doyle@email.com', 'source' => 'Facebook', 'status' => 'abandoned', 'status_label' => 'Abandoned', 'amount' => 67.30, 'progress' => 1, 'progress_label' => '1 / 5', 'progress_percent' => 20, 'last_activity' => '30 Apr 2025, 16:44', 'sort_timestamp' => 3, 'record_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'CHK-250430-00976', 'customer' => 'Lisa Campbell', 'email' => 'lisa.campbell@email.com', 'source' => 'Website', 'status' => 'payment_pending', 'status_label' => 'Payment Pending', 'amount' => 198.75, 'progress' => 4, 'progress_label' => '4 / 5', 'progress_percent' => 80, 'last_activity' => '30 Apr 2025, 16:31', 'sort_timestamp' => 2, 'record_id' => null, 'order_id' => null, 'actionable' => false],
            ['reference' => 'ORD-250430-00612', 'customer' => 'Global Wholesale Inc.', 'email' => 'orders@globalwholesale.ie', 'source' => 'B2B Portal', 'status' => 'completed', 'status_label' => 'Completed', 'amount' => 2430.00, 'progress' => 5, 'progress_label' => '5 / 5', 'progress_percent' => 100, 'last_activity' => '30 Apr 2025, 15:12', 'sort_timestamp' => 1, 'record_id' => null, 'order_id' => null, 'actionable' => false],
        ])->map(fn (array $row) => (object) $row);
    }

    private function paginate(Collection $rows, Request $request): LengthAwarePaginator
    {
        $perPage = min(50, max(1, (int) $request->query('per_page', 8)));
        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
        return $paginator;
    }
}
