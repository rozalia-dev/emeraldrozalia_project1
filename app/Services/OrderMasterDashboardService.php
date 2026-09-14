<?php

namespace App\Services;

use App\Models\{Order, OrderItem, Product, ReturnRequest};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Builds the shared, type-aware dashboards used by each Online Sales order master.
 *
 * Every value returned by this service is derived from the current tenant's order
 * records. An empty installation is represented by zeroes and empty collections;
 * it must never look like it contains operational history that does not exist.
 */
class OrderMasterDashboardService
{
    private const ORDER_STATUSES = ['pending', 'approved', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];
    private const PAYMENT_STATUSES = ['unpaid', 'pending', 'paid', 'failed', 'refunded', 'pay_on_delivery'];
    private const FULFILLMENT_STATUSES = ['pending', 'on_hold', 'picking', 'packed', 'ready_to_ship', 'shipped', 'delivered', 'closed'];

    private const META = [
        'online' => ['label' => 'Online Orders', 'singular' => 'Online Order', 'tone' => 'blue', 'icon' => 'globe', 'subtitle' => 'Manage orders placed through your website and online store.', 'entity_header' => 'CUSTOMER', 'selector_label' => 'All Sites / Channels', 'selector_key' => 'channel', 'entity_mode' => 'channel', 'entity_title' => 'Channel Performance', 'performance_title' => 'Fulfillment Performance', 'pending_label' => 'Pending Approval'],
        'corporate' => ['label' => 'Corporate Orders', 'singular' => 'Corporate Order', 'tone' => 'purple', 'icon' => 'briefcase', 'subtitle' => 'Manage orders placed by corporate accounts and business customers.', 'entity_header' => 'COMPANY', 'selector_label' => 'All Companies', 'selector_key' => 'company', 'entity_mode' => 'buyer', 'entity_title' => 'Top Corporate Accounts', 'performance_title' => 'Fulfillment Performance', 'pending_label' => 'Pending Approval'],
        'bulk' => ['label' => 'Bulk Orders', 'singular' => 'Bulk Order', 'tone' => 'orange', 'icon' => 'package', 'subtitle' => 'Manage large quantity orders from wholesalers, distributors and bulk buyers.', 'entity_header' => 'BUYER / COMPANY', 'selector_label' => 'All Buyers', 'selector_key' => 'buyer', 'entity_mode' => 'buyer', 'entity_title' => 'Top Buyers (Bulk Orders)', 'performance_title' => 'Channel / Fulfillment Performance', 'pending_label' => 'Pending Approval'],
        'franchise' => ['label' => 'Franchise Orders', 'singular' => 'Franchise Order', 'tone' => 'green', 'icon' => 'users', 'subtitle' => 'Manage orders placed by franchisees for their business operations and distribution.', 'entity_header' => 'FRANCHISEE / STORE', 'selector_label' => 'All Franchisees', 'selector_key' => 'franchise', 'entity_mode' => 'franchise', 'entity_title' => 'Top Performing Franchises (By Order Value)', 'performance_title' => 'Store Performance (Fulfillment)', 'pending_label' => 'Pending Approval'],
        'franchise_retail' => ['label' => 'Franchise Retail Orders', 'singular' => 'Franchise Retail Order', 'tone' => 'teal', 'icon' => 'shopping-bag', 'subtitle' => 'Manage orders placed by franchise retail stores for walk-in and local sales.', 'entity_header' => 'STORE / FRANCHISE', 'selector_label' => 'All Stores', 'selector_key' => 'store', 'entity_mode' => 'store', 'entity_title' => 'Top Performing Stores (By Order Value)', 'performance_title' => 'Store Performance (Fulfillment)', 'pending_label' => 'Pending Approval'],
        'buyer' => ['label' => 'Buyer Orders', 'singular' => 'Buyer Order', 'tone' => 'red', 'icon' => 'user', 'subtitle' => 'Manage orders placed by individual buyers and repeat customers.', 'entity_header' => 'BUYER', 'selector_label' => 'All Buyers', 'selector_key' => 'buyer', 'entity_mode' => 'buyer', 'entity_title' => 'Top Buyers (By Order Value)', 'performance_title' => 'Fulfillment Performance', 'pending_label' => 'Pending Payment'],
    ];

    public function build(Request $request, string $type): array
    {
        if (! isset(self::META[$type])) {
            throw new InvalidArgumentException("Unsupported order master type [{$type}].");
        }

        $meta = self::META[$type];
        $allOrders = Order::query()
            ->where('order_type', $type)
            ->with(['user', 'items', 'payments'])
            ->withCount('returns')
            ->latest()
            ->get();
        $filtered = $this->filterOrders($allOrders, $request, $meta)->values();
        $perPage = in_array((int) $request->query('per_page', 8), [8, 16, 25, 50], true) ? (int) $request->query('per_page', 8) : 8;
        $page = max(1, (int) $request->query('page', 1));
        $orders = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
        $hasLiveData = $allOrders->isNotEmpty();
        $stats = $this->actualStats($allOrders, $type);

        return [
            'orders' => $orders,
            'hasLiveData' => $hasLiveData,
            'dataState' => $hasLiveData ? 'live' : 'empty',
            'emptyStateMessage' => $hasLiveData ? null : 'No live orders have been recorded for this order master yet.',
            'type' => $type,
            'label' => $meta['label'],
            'meta' => $meta,
            'metrics' => $this->metricCards($stats, $type, $hasLiveData),
            'summary' => [...$stats, 'average' => $stats['total'] ? $stats['value'] / $stats['total'] : 0, 'pending_label' => $meta['pending_label']],
            'statusRows' => $this->statusRows($allOrders, $type, $stats),
            'productRows' => $this->productRows($allOrders),
            'entityRows' => $this->entityRows($allOrders, $type),
            'performanceRows' => $this->performanceRows($allOrders),
            'selectorOptions' => $this->selectorOptions($allOrders, $meta),
            'paymentMethods' => $this->paymentMethods($allOrders),
            'orderStatuses' => self::ORDER_STATUSES,
            'paymentStatuses' => self::PAYMENT_STATUSES,
            'fulfillmentStatuses' => self::FULFILLMENT_STATUSES,
            'dateRange' => $this->dateRange($request),
            'tabs' => $this->tabs($meta),
            'activeTab' => (string) $request->query('tab', 'all'),
            'notifications' => $this->actualNotifications($allOrders, $type, $stats),
        ];
    }

    private function filterOrders(Collection $orders, Request $request, array $meta): Collection
    {
        $search = Str::lower(trim((string) $request->query('q', '')));
        $status = (string) $request->query('status', '');
        $paymentStatus = (string) $request->query('payment_status', '');
        $paymentMethod = (string) $request->query('payment_method', '');
        $fulfillment = (string) $request->query('fulfillment_status', '');
        $selector = trim((string) $request->query($meta['selector_key'], ''));
        $tab = (string) $request->query('tab', 'all');
        $from = $this->parseDate($request->query('date_from'))?->startOfDay();
        $to = $this->parseDate($request->query('date_to'))?->endOfDay();

        return $orders->filter(function (Order $order) use ($search, $status, $paymentStatus, $paymentMethod, $fulfillment, $selector, $meta, $tab, $from, $to): bool {
            $address = (array) ($order->shipping_address ?: []);
            $name = (string) (data_get($address, 'name') ?: $order->user?->name ?: 'Guest Customer');
            $haystack = Str::lower(implode(' ', [$order->number, $order->email, $order->phone, $name, data_get($address, 'company', ''), data_get($address, 'store', ''), data_get($address, 'franchise', '')]));
            $pendingTab = $meta['pending_label'] === 'Pending Payment'
                ? in_array($order->payment_status, ['unpaid', 'pending', 'failed'], true)
                : $order->status === 'pending';
            $tabMatch = match ($tab) {
                'new' => $order->status === 'pending',
                'processing' => $order->status === 'processing',
                'pending' => $pendingTab,
                'approved' => $order->status === 'approved',
                'shipped' => $order->status === 'shipped' || $order->fulfillment_status === 'shipped',
                'delivered' => $order->status === 'completed' || $order->fulfillment_status === 'delivered',
                'cancelled' => $order->status === 'cancelled',
                'returns' => $order->status === 'refunded' || (int) ($order->returns_count ?? 0) > 0,
                default => true,
            };

            return ($search === '' || Str::contains($haystack, $search))
                && ($status === '' || $order->status === $status)
                && ($paymentStatus === '' || $order->payment_status === $paymentStatus)
                && ($paymentMethod === '' || Str::lower((string) $order->payment_method) === Str::lower($paymentMethod))
                && ($fulfillment === '' || $order->fulfillment_status === $fulfillment)
                && ($selector === '' || $selector === $this->selectorValue($order, $meta))
                && $tabMatch
                && ($from === null || $order->created_at?->gte($from))
                && ($to === null || $order->created_at?->lte($to));
        });
    }

    private function actualStats(Collection $orders, string $type): array
    {
        $total = $orders->count();
        $value = round((float) $orders->sum(fn (Order $order): float => (float) $order->total), 2);
        $returns = $orders->isEmpty()
            ? 0
            : ReturnRequest::query()->whereIn('order_id', $orders->pluck('id')->all())->count();

        return [
            'total' => $total,
            'value' => $value,
            'in_progress' => $orders->whereIn('status', ['approved', 'processing', 'shipped'])->count(),
            'pending' => self::META[$type]['pending_label'] === 'Pending Payment'
                ? $orders->whereIn('payment_status', ['unpaid', 'pending', 'failed'])->count()
                : $orders->where('status', 'pending')->count(),
            'approved' => $orders->where('status', 'approved')->count(),
            'shipped' => $orders->filter(fn (Order $order): bool => $order->status === 'shipped' || $order->fulfillment_status === 'shipped')->count(),
            'delivered' => $orders->filter(fn (Order $order): bool => $order->status === 'completed' || $order->fulfillment_status === 'delivered')->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
            'returns' => $returns,
            'conversion' => $total ? round(($orders->whereIn('status', ['completed', 'shipped'])->count() / $total) * 100, 2) : 0,
        ];
    }

    private function metricCards(array $stats, string $type, bool $hasLiveData): array
    {
        $meta = self::META[$type];
        $sourceLabel = $hasLiveData ? 'Live database value' : 'Awaiting live order data';

        return [
            ['label' => 'Total '.$meta['label'], 'value' => $stats['total'], 'kind' => 'number', 'icon' => 'shopping-bag', 'tone' => $meta['tone'], 'source_label' => $sourceLabel],
            ['label' => 'Order Value', 'value' => $stats['value'], 'kind' => 'money', 'icon' => 'chart', 'tone' => 'blue', 'source_label' => $sourceLabel],
            ['label' => 'Orders in Progress', 'value' => $stats['in_progress'], 'kind' => 'number', 'icon' => 'package', 'tone' => 'orange', 'source_label' => $sourceLabel],
            ['label' => $meta['pending_label'], 'value' => $stats['pending'], 'kind' => 'number', 'icon' => 'clock', 'tone' => 'purple', 'source_label' => $sourceLabel],
            ['label' => 'Shipped Orders', 'value' => $stats['shipped'], 'kind' => 'number', 'icon' => 'truck', 'tone' => 'teal', 'source_label' => $sourceLabel],
            ['label' => 'Delivered Orders', 'value' => $stats['delivered'], 'kind' => 'number', 'icon' => 'check', 'tone' => 'green', 'source_label' => $sourceLabel],
            ['label' => 'Cancelled Orders', 'value' => $stats['cancelled'], 'kind' => 'number', 'icon' => 'close', 'tone' => 'red', 'source_label' => $sourceLabel],
        ];
    }

    private function statusRows(Collection $orders, string $type, array $stats): array
    {
        return $this->withShares([
            ['label' => 'New', 'count' => $orders->where('status', 'pending')->count(), 'color' => '#2676cc'],
            ['label' => 'Processing', 'count' => $orders->where('status', 'processing')->count(), 'color' => '#ef870d'],
            ['label' => self::META[$type]['pending_label'], 'count' => $stats['pending'], 'color' => '#f2b20d'],
            ['label' => 'Approved', 'count' => $stats['approved'], 'color' => '#6633a0'],
            ['label' => 'Shipped', 'count' => $stats['shipped'], 'color' => '#087b72'],
            ['label' => 'Delivered', 'count' => $stats['delivered'], 'color' => '#08753d'],
            ['label' => 'Cancelled', 'count' => $stats['cancelled'], 'color' => '#dc303e'],
            ['label' => 'Return / Refund', 'count' => $stats['returns'], 'color' => '#6c7b83'],
        ], 'count');
    }

    private function productRows(Collection $orders): array
    {
        $items = $orders->flatMap(fn (Order $order): Collection => $order->items);

        return $items
            ->groupBy(fn (OrderItem $item): string => (string) ($item->name ?: 'Unnamed Product'))
            ->map(fn (Collection $matches, string $name): array => [
                'label' => $name,
                'quantity' => (int) $matches->sum('quantity'),
                'amount' => round((float) $matches->sum(fn (OrderItem $item): float => (float) $item->total), 2),
            ])
            ->sortByDesc('amount')
            ->take(5)
            ->values()
            ->all();
    }

    private function entityRows(Collection $orders, string $type): array
    {
        $meta = self::META[$type];

        return $orders
            ->groupBy(fn (Order $order): string => $this->selectorValue($order, $meta))
            ->map(function (Collection $matches, string $label) use ($meta, $orders): array {
                $secondary = $meta['entity_mode'] === 'franchise'
                    ? $matches->map(fn (Order $order): string => (string) data_get($order->shipping_address, 'store', 'Unassigned store'))->unique()->count().' stores'
                    : null;

                return [
                    'label' => $label,
                    'amount' => round((float) $matches->sum(fn (Order $order): float => (float) $order->total), 2),
                    'orders' => $matches->count(),
                    'secondary' => $secondary,
                    'share' => round(($matches->count() / max(1, $orders->count())) * 100, 1),
                ];
            })
            ->sortByDesc('amount')
            ->take(5)
            ->values()
            ->all();
    }

    private function performanceRows(Collection $orders): array
    {
        $total = max(1, $orders->count());

        return $orders
            ->groupBy(fn (Order $order): string => Str::headline((string) ($order->fulfillment_status ?: 'Unspecified')))
            ->map(fn (Collection $matches, string $label): array => [
                'label' => $label,
                'value' => $matches->count(),
                'percent' => round(($matches->count() / $total) * 100, 1),
                'color' => $this->statusColor($label),
            ])
            ->sortByDesc('value')
            ->take(5)
            ->values()
            ->all();
    }

    private function selectorOptions(Collection $orders, array $meta): array
    {
        return $orders
            ->map(fn (Order $order): string => $this->selectorValue($order, $meta))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function paymentMethods(Collection $orders): array
    {
        return $orders
            ->map(fn (Order $order): string => trim((string) $order->payment_method))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function selectorValue(Order $order, array $meta): string
    {
        $address = (array) ($order->shipping_address ?: []);

        return match ($meta['selector_key']) {
            'channel' => (string) (data_get($address, 'channel') ?: 'Unspecified channel'),
            'franchise' => (string) (data_get($address, 'franchise') ?: 'Unassigned franchise'),
            'store' => (string) (data_get($address, 'store') ?: 'Unassigned store'),
            'company', 'buyer' => (string) ($order->user?->name ?: data_get($address, 'company', data_get($address, 'name', 'Guest Customer'))),
            default => 'Unspecified',
        };
    }

    private function dateRange(Request $request): array
    {
        $from = $this->parseDate($request->query('date_from'));
        $to = $this->parseDate($request->query('date_to'));

        if ($from !== null && $to !== null && $from->gt($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        return [
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'from_label' => $from?->format('d M Y') ?: 'All time',
            'to_label' => $to?->format('d M Y') ?: ($from ? 'Present' : ''),
        ];
    }

    private function tabs(array $meta): array
    {
        return ['all' => 'All Orders', 'new' => 'New', 'processing' => 'Processing', 'pending' => $meta['pending_label'], 'approved' => 'Approved', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'returns' => 'Return / Refund'];
    }

    private function actualNotifications(Collection $orders, string $type, array $stats): array
    {
        $notifications = [];
        $pendingLabel = Str::lower(self::META[$type]['pending_label']);
        $todayOrders = $orders->filter(fn (Order $order): bool => $order->created_at?->isToday() === true);
        $updatedToday = $orders->filter(fn (Order $order): bool => $order->updated_at?->isToday() === true);
        $failed = $orders->where('payment_status', 'failed')->count();
        $lowStock = Product::query()->whereBetween('stock', [1, 10])->count();
        $highValueToday = $todayOrders->where('total', '>=', 1000)->count();
        $shippedToday = $updatedToday->filter(fn (Order $order): bool => $order->status === 'shipped' || $order->fulfillment_status === 'shipped')->count();
        $cancelledToday = $updatedToday->where('status', 'cancelled')->count();

        if ($stats['pending'] > 0) {
            $notifications[] = ['tone' => 'orange', 'text' => number_format($stats['pending']).' orders '.$pendingLabel, 'time' => 'current'];
        }
        if ($stats['in_progress'] > 0) {
            $notifications[] = ['tone' => 'blue', 'text' => number_format($stats['in_progress']).' orders in progress', 'time' => 'current'];
        }
        if ($shippedToday > 0) {
            $notifications[] = ['tone' => 'green', 'text' => number_format($shippedToday).' orders shipped today', 'time' => 'today'];
        }
        if ($stats['returns'] > 0) {
            $notifications[] = ['tone' => 'gray', 'text' => number_format($stats['returns']).' return requests received', 'time' => 'current'];
        }
        if ($cancelledToday > 0) {
            $notifications[] = ['tone' => 'red', 'text' => number_format($cancelledToday).' orders cancelled today', 'time' => 'today'];
        }
        if ($lowStock > 0) {
            $notifications[] = ['tone' => 'orange', 'text' => 'Low stock for '.number_format($lowStock).' products', 'time' => 'current'];
        }
        if ($failed > 0) {
            $notifications[] = ['tone' => 'red', 'text' => 'Payment failed for '.number_format($failed).' orders', 'time' => 'current'];
        }
        if ($highValueToday > 0) {
            $notifications[] = ['tone' => 'red', 'text' => number_format($highValueToday).' high value orders received', 'time' => 'today'];
        }

        return $notifications;
    }

    private function withShares(array $rows, string $field): array
    {
        $total = (float) collect($rows)->sum($field);

        return array_map(fn (array $row): array => [...$row, 'share' => $total > 0 ? round(((float) ($row[$field] ?? 0) / $total) * 100, 1) : 0], $rows);
    }

    private function statusColor(string $label): string
    {
        return match (Str::lower($label)) {
            'delivered' => '#08753d',
            'shipped' => '#087b72',
            'picking', 'processing' => '#2676cc',
            'packed', 'ready to ship' => '#6633a0',
            default => '#f2b20d',
        };
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
