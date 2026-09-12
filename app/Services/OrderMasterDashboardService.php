<?php

namespace App\Services;

use App\Models\{Order, OrderItem, Product, ReturnRequest};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Builds the shared, type-aware dashboards used by each Online Sales order master.
 * Preview values keep an empty installation useful while every filter and table
 * switches to the tenant's PostgreSQL order data as soon as it exists.
 */
class OrderMasterDashboardService
{
    private const ORDER_STATUSES = ['pending', 'approved', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'];
    private const PAYMENT_STATUSES = ['unpaid', 'pending', 'paid', 'failed', 'refunded', 'pay_on_delivery'];
    private const FULFILLMENT_STATUSES = ['pending', 'on_hold', 'picking', 'packed', 'ready_to_ship', 'shipped', 'delivered', 'closed'];

    private const META = [
        'online' => ['label' => 'Online Orders', 'singular' => 'Online Order', 'prefix' => 'ONL', 'tone' => 'blue', 'icon' => 'globe', 'subtitle' => 'Manage orders placed through your website and online store.', 'entity_header' => 'CUSTOMER', 'selector_label' => 'All Sites / Channels', 'selector_key' => 'channel', 'entity_mode' => 'channel', 'entity_title' => 'Channel Performance', 'performance_title' => 'Fulfillment Performance', 'selector_options' => ['Website', 'Mobile App', 'WhatsApp', 'Social Media', 'Other'], 'pending_label' => 'Pending Approval'],
        'corporate' => ['label' => 'Corporate Orders', 'singular' => 'Corporate Order', 'prefix' => 'CORP', 'tone' => 'purple', 'icon' => 'briefcase', 'subtitle' => 'Manage orders placed by corporate accounts and business customers.', 'entity_header' => 'COMPANY', 'selector_label' => 'All Companies', 'selector_key' => 'company', 'entity_mode' => 'buyer', 'entity_title' => 'Top Corporate Accounts', 'performance_title' => 'Fulfillment Performance', 'selector_options' => ['Emerald Retail Group', 'Style Hub Ltd.', 'Fashion Roots', 'Elite Retail Group'], 'pending_label' => 'Pending Approval'],
        'bulk' => ['label' => 'Bulk Orders', 'singular' => 'Bulk Order', 'prefix' => 'BULK', 'tone' => 'orange', 'icon' => 'package', 'subtitle' => 'Manage large quantity orders from wholesalers, distributors and bulk buyers.', 'entity_header' => 'BUYER / COMPANY', 'selector_label' => 'All Buyers', 'selector_key' => 'buyer', 'entity_mode' => 'buyer', 'entity_title' => 'Top Buyers (Bulk Orders)', 'performance_title' => 'Channel / Fulfillment Performance', 'selector_options' => ['Emerald Wholesale Ltd.', 'Hat & Co. Distributors', 'Irish Retail Supply', 'Northstar Outfitters'], 'pending_label' => 'Pending Approval'],
        'franchise' => ['label' => 'Franchise Orders', 'singular' => 'Franchise Order', 'prefix' => 'FRAN', 'tone' => 'green', 'icon' => 'users', 'subtitle' => 'Manage orders placed by franchisees for their business operations and distribution.', 'entity_header' => 'FRANCHISEE / STORE', 'selector_label' => 'All Franchisees', 'selector_key' => 'franchise', 'entity_mode' => 'franchise', 'entity_title' => 'Top Performing Franchises (By Order Value)', 'performance_title' => 'Store Performance (Fulfillment)', 'selector_options' => ['Emerald Rozalia UK', 'Emerald Rozalia USA', 'Emerald Rozalia Canada', 'Emerald Rozalia Australia', 'Emerald Rozalia Germany'], 'pending_label' => 'Pending Approval'],
        'franchise_retail' => ['label' => 'Franchise Retail Orders', 'singular' => 'Franchise Retail Order', 'prefix' => 'FRRET', 'tone' => 'teal', 'icon' => 'shopping-bag', 'subtitle' => 'Manage orders placed by franchise retail stores for walk-in and local sales.', 'entity_header' => 'STORE / FRANCHISE', 'selector_label' => 'All Stores', 'selector_key' => 'store', 'entity_mode' => 'store', 'entity_title' => 'Top Performing Stores (By Order Value)', 'performance_title' => 'Store Performance (Fulfillment)', 'selector_options' => ['Emerald Store - Limerick', 'Emerald Store - Cork', 'Emerald Store - Galway', 'Emerald Store - Belfast', 'Emerald Store - Waterford'], 'pending_label' => 'Pending Approval'],
        'buyer' => ['label' => 'Buyer Orders', 'singular' => 'Buyer Order', 'prefix' => 'BUYR', 'tone' => 'red', 'icon' => 'user', 'subtitle' => 'Manage orders placed by individual buyers and repeat customers.', 'entity_header' => 'BUYER', 'selector_label' => 'All Buyers', 'selector_key' => 'buyer', 'entity_mode' => 'buyer', 'entity_title' => 'Top Buyers (By Order Value)', 'performance_title' => 'Fulfillment Performance', 'selector_options' => ['Emma Walsh', 'John Smith', 'Aoife Byrne', 'Michael O\'Connor', 'Sarah Kelly'], 'pending_label' => 'Pending Payment'],
    ];

    public function build(Request $request, string $type): array
    {
        $meta = self::META[$type];
        $allOrders = Order::query()->where('order_type', $type)->with(['user', 'items', 'payments'])->withCount('returns')->latest()->get();
        $filtered = $this->filterOrders($allOrders, $request, $meta)->values();
        $perPage = in_array((int) $request->query('per_page', 8), [8, 16, 25, 50], true) ? (int) $request->query('per_page', 8) : 8;
        $page = max(1, (int) $request->query('page', 1));
        $orders = new LengthAwarePaginator($filtered->forPage($page, $perPage)->values(), $filtered->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $preview = $allOrders->isEmpty();
        $stats = $preview ? $this->previewStats($type) : $this->actualStats($allOrders, $type);

        return [
            'orders' => $orders, 'previewRows' => $preview ? $this->previewRows($type) : [], 'preview' => $preview,
            'type' => $type, 'label' => $meta['label'], 'meta' => $meta, 'metrics' => $this->metricCards($stats, $type),
            'summary' => [...$stats, 'average' => $stats['total'] ? $stats['value'] / $stats['total'] : 0, 'pending_label' => $meta['pending_label']],
            'statusRows' => $preview ? $this->previewStatusRows($type, $stats) : $this->statusRows($allOrders, $type, $stats),
            'productRows' => $this->productRows($allOrders, $type), 'entityRows' => $this->entityRows($allOrders, $type),
            'performanceRows' => $preview ? $this->previewPerformanceRows($type) : $this->performanceRows($allOrders, $type),
            'selectorOptions' => $this->selectorOptions($allOrders, $meta), 'paymentMethods' => $this->paymentMethods($allOrders),
            'orderStatuses' => self::ORDER_STATUSES, 'paymentStatuses' => self::PAYMENT_STATUSES, 'fulfillmentStatuses' => self::FULFILLMENT_STATUSES,
            'dateRange' => $this->dateRange($request), 'tabs' => $this->tabs($meta), 'activeTab' => (string) $request->query('tab', 'all'),
            'notifications' => $preview ? $this->previewNotifications($type, $stats) : $this->actualNotifications($allOrders, $type, $stats),
        ];
    }

    private function filterOrders(Collection $orders, Request $request, array $meta): Collection
    {
        $search = Str::lower(trim((string) $request->query('q', '')));
        $status = (string) $request->query('status', ''); $paymentStatus = (string) $request->query('payment_status', '');
        $paymentMethod = (string) $request->query('payment_method', ''); $fulfillment = (string) $request->query('fulfillment_status', '');
        $selector = trim((string) $request->query($meta['selector_key'], '')); $tab = (string) $request->query('tab', 'all');
        $from = $this->parseDate($request->query('date_from'))?->startOfDay(); $to = $this->parseDate($request->query('date_to'))?->endOfDay();

        return $orders->filter(function (Order $order) use ($search, $status, $paymentStatus, $paymentMethod, $fulfillment, $selector, $meta, $tab, $from, $to): bool {
            $address = (array) ($order->shipping_address ?: []);
            $name = (string) ($order->user?->name ?: data_get($address, 'name', 'Guest Customer'));
            $haystack = Str::lower(implode(' ', [$order->number, $order->email, $order->phone, $name, data_get($address, 'company', ''), data_get($address, 'store', ''), data_get($address, 'franchise', '')]));
            $pendingTab = $meta['pending_label'] === 'Pending Payment' ? in_array($order->payment_status, ['unpaid', 'pending', 'failed'], true) : $order->status === 'pending';
            $tabMatch = match ($tab) {
                'new' => $order->status === 'pending', 'processing' => $order->status === 'processing', 'pending' => $pendingTab,
                'approved' => $order->status === 'approved', 'shipped' => $order->status === 'shipped' || $order->fulfillment_status === 'shipped',
                'delivered' => $order->status === 'completed' || $order->fulfillment_status === 'delivered', 'cancelled' => $order->status === 'cancelled',
                'returns' => $order->status === 'refunded' || (int) ($order->returns_count ?? 0) > 0, default => true,
            };
            return ($search === '' || Str::contains($haystack, $search)) && ($status === '' || $order->status === $status)
                && ($paymentStatus === '' || $order->payment_status === $paymentStatus) && ($paymentMethod === '' || Str::lower((string) $order->payment_method) === Str::lower($paymentMethod))
                && ($fulfillment === '' || $order->fulfillment_status === $fulfillment) && ($selector === '' || $selector === $this->selectorValue($order, $meta))
                && $tabMatch && ($from === null || $order->created_at?->gte($from)) && ($to === null || $order->created_at?->lte($to));
        });
    }

    private function actualStats(Collection $orders, string $type): array
    {
        $total = $orders->count(); $value = round((float) $orders->sum(fn (Order $order): float => (float) $order->total), 2);
        $returns = ReturnRequest::query()->whereIn('order_id', $orders->pluck('id')->all())->count();
        return ['total' => $total, 'value' => $value, 'in_progress' => $orders->whereIn('status', ['approved', 'processing', 'shipped'])->count(), 'pending' => self::META[$type]['pending_label'] === 'Pending Payment' ? $orders->whereIn('payment_status', ['unpaid', 'pending', 'failed'])->count() : $orders->where('status', 'pending')->count(), 'approved' => $orders->where('status', 'approved')->count(), 'shipped' => $orders->filter(fn (Order $order): bool => $order->status === 'shipped' || $order->fulfillment_status === 'shipped')->count(), 'delivered' => $orders->filter(fn (Order $order): bool => $order->status === 'completed' || $order->fulfillment_status === 'delivered')->count(), 'cancelled' => $orders->where('status', 'cancelled')->count(), 'returns' => $returns, 'conversion' => $total ? round(($orders->whereIn('status', ['completed', 'shipped'])->count() / $total) * 100, 2) : 0];
    }

    private function previewStats(string $type): array
    {
        return [
            'online' => ['total' => 6248, 'value' => 2145780.25, 'in_progress' => 2356, 'pending' => 356, 'approved' => 845, 'shipped' => 1123, 'delivered' => 1045, 'cancelled' => 355, 'returns' => 78, 'conversion' => 3.82],
            'corporate' => ['total' => 1024, 'value' => 735420.10, 'in_progress' => 228, 'pending' => 84, 'approved' => 376, 'shipped' => 452, 'delivered' => 338, 'cancelled' => 41, 'returns' => 12, 'conversion' => 2.88],
            'bulk' => ['total' => 1156, 'value' => 1245980.00, 'in_progress' => 276, 'pending' => 142, 'approved' => 612, 'shipped' => 568, 'delivered' => 286, 'cancelled' => 58, 'returns' => 24, 'conversion' => 2.77],
            'franchise' => ['total' => 1842, 'value' => 482765.75, 'in_progress' => 412, 'pending' => 218, 'approved' => 796, 'shipped' => 846, 'delivered' => 614, 'cancelled' => 152, 'returns' => 26, 'conversion' => 3.21],
            'franchise_retail' => ['total' => 1356, 'value' => 152830.00, 'in_progress' => 286, 'pending' => 126, 'approved' => 542, 'shipped' => 654, 'delivered' => 512, 'cancelled' => 94, 'returns' => 18, 'conversion' => 2.96],
            'buyer' => ['total' => 2789, 'value' => 186540.20, 'in_progress' => 513, 'pending' => 247, 'approved' => 900, 'shipped' => 1284, 'delivered' => 1082, 'cancelled' => 176, 'returns' => 27, 'conversion' => 3.18],
        ][$type];
    }

    private function metricCards(array $stats, string $type): array
    {
        $changes = ['18.72%', '15.43%', '2.84%', '12.16%', '15.81%', '18.64%', '18.22%']; $meta = self::META[$type];
        return [
            ['label' => 'Total '.$meta['label'], 'value' => $stats['total'], 'kind' => 'number', 'icon' => 'shopping-bag', 'tone' => $meta['tone'], 'change' => $changes[0]],
            ['label' => 'Order Value', 'value' => $stats['value'], 'kind' => 'money', 'icon' => 'chart', 'tone' => 'blue', 'change' => $changes[1]],
            ['label' => 'Orders in Progress', 'value' => $stats['in_progress'], 'kind' => 'number', 'icon' => 'package', 'tone' => 'orange', 'change' => $changes[2]],
            ['label' => $meta['pending_label'], 'value' => $stats['pending'], 'kind' => 'number', 'icon' => 'clock', 'tone' => 'purple', 'change' => $changes[3]],
            ['label' => 'Shipped Orders', 'value' => $stats['shipped'], 'kind' => 'number', 'icon' => 'truck', 'tone' => 'teal', 'change' => $changes[4]],
            ['label' => 'Delivered Orders', 'value' => $stats['delivered'], 'kind' => 'number', 'icon' => 'check', 'tone' => 'green', 'change' => $changes[5]],
            ['label' => 'Cancelled Orders', 'value' => $stats['cancelled'], 'kind' => 'number', 'icon' => 'close', 'tone' => 'red', 'change' => $changes[6]],
        ];
    }

    private function statusRows(Collection $orders, string $type, array $stats): array
    {
        return $this->withShares([
            ['label' => 'New', 'count' => $orders->where('status', 'pending')->count(), 'color' => '#2676cc'], ['label' => 'Processing', 'count' => $orders->where('status', 'processing')->count(), 'color' => '#ef870d'],
            ['label' => self::META[$type]['pending_label'], 'count' => $stats['pending'], 'color' => '#f2b20d'], ['label' => 'Approved', 'count' => $stats['approved'], 'color' => '#6633a0'],
            ['label' => 'Shipped', 'count' => $stats['shipped'], 'color' => '#087b72'], ['label' => 'Delivered', 'count' => $stats['delivered'], 'color' => '#08753d'],
            ['label' => 'Cancelled', 'count' => $stats['cancelled'], 'color' => '#dc303e'], ['label' => 'Return / Refund', 'count' => $stats['returns'], 'color' => '#6c7b83'],
        ], 'count');
    }

    private function previewStatusRows(string $type, array $stats): array
    {
        $rows = [['label' => 'New', 'count' => $type === 'online' ? 1245 : max(1, (int) round($stats['total'] * .18)), 'color' => '#2676cc'], ['label' => 'Processing', 'count' => $stats['in_progress'], 'color' => '#ef870d'], ['label' => self::META[$type]['pending_label'], 'count' => $stats['pending'], 'color' => '#f2b20d'], ['label' => 'Approved', 'count' => $stats['approved'], 'color' => '#6633a0'], ['label' => 'Shipped', 'count' => $stats['shipped'], 'color' => '#087b72'], ['label' => 'Delivered', 'count' => $stats['delivered'], 'color' => '#08753d'], ['label' => 'Cancelled', 'count' => $stats['cancelled'], 'color' => '#dc303e'], ['label' => 'Return / Refund', 'count' => $stats['returns'], 'color' => '#6c7b83']];
        return $this->withShares($rows, 'count');
    }

    private function productRows(Collection $orders, string $type): array
    {
        $items = $orders->flatMap(fn (Order $order): Collection => $order->items);
        if ($items->isEmpty()) return $this->previewProducts($type);
        return $items->groupBy(fn (OrderItem $item): string => (string) ($item->name ?: 'Unnamed Product'))->map(fn (Collection $matches, string $name): array => ['label' => $name, 'quantity' => (int) $matches->sum('quantity'), 'amount' => round((float) $matches->sum(fn (OrderItem $item): float => (float) $item->total), 2)])->sortByDesc('amount')->take(5)->values()->all();
    }

    private function previewProducts(string $type): array
    {
        if ($type === 'bulk') return [['label' => 'Emerald Signature Cap', 'quantity' => 18450, 'amount' => 236540.00], ['label' => 'Luxury Baseball Cap', 'quantity' => 12320, 'amount' => 178640.00], ['label' => 'Premium Bucket Hat', 'quantity' => 9875, 'amount' => 139250.00], ['label' => 'Flex Fit Cap', 'quantity' => 8560, 'amount' => 114880.00], ['label' => 'Winter Beanie Hat', 'quantity' => 6420, 'amount' => 85730.00]];
        $amounts = $type === 'online' ? [37350.55, 29580.14, 18926.46, 16275.58, 8979.62] : [85420.00, 64380.00, 48618.00, 39420.00, 18901.00];
        $quantities = $type === 'online' ? [1245, 986, 654, 542, 438] : [2845, 2146, 1682, 1314, 922];
        return collect(['Emerald Signature Cap', 'Luxury Baseball Cap', 'Premium Bucket Hat', 'Flex Fit Cap', 'Winter Beanie Hat'])->map(fn (string $label, int $index): array => ['label' => $label, 'quantity' => $quantities[$index], 'amount' => $amounts[$index]])->all();
    }

    private function entityRows(Collection $orders, string $type): array
    {
        $meta = self::META[$type];
        if ($orders->isEmpty()) return $this->previewEntities($type);
        $rows = $orders->groupBy(fn (Order $order): string => $this->selectorValue($order, $meta))->map(function (Collection $matches, string $label) use ($meta, $orders): array {
            $secondary = $meta['entity_mode'] === 'franchise' ? $matches->map(fn (Order $order): string => (string) data_get($order->shipping_address, 'store', 'Store'))->unique()->count().' stores' : null;
            return ['label' => $label, 'amount' => round((float) $matches->sum(fn (Order $order): float => (float) $order->total), 2), 'orders' => $matches->count(), 'secondary' => $secondary, 'share' => round(($matches->count() / max(1, $orders->count())) * 100, 1)];
        })->sortByDesc('amount')->take(5)->values()->all();
        return $rows ?: $this->previewEntities($type);
    }

    private function previewEntities(string $type): array
    {
        return match ($type) {
            'online' => [['label' => 'Website', 'amount' => 0, 'orders' => 4562, 'secondary' => '75.0%', 'share' => 75.0], ['label' => 'Mobile App', 'amount' => 0, 'orders' => 1256, 'secondary' => '20.1%', 'share' => 20.1], ['label' => 'WhatsApp', 'amount' => 0, 'orders' => 258, 'secondary' => '4.1%', 'share' => 4.1], ['label' => 'Social Media', 'amount' => 0, 'orders' => 104, 'secondary' => '1.7%', 'share' => 1.7], ['label' => 'Other', 'amount' => 0, 'orders' => 68, 'secondary' => '1.1%', 'share' => 1.1]],
            'corporate' => [['label' => 'Emerald Retail Group', 'amount' => 218450.20, 'orders' => 186, 'secondary' => '42 stores'], ['label' => 'Style Hub Ltd.', 'amount' => 176820.45, 'orders' => 144, 'secondary' => '28 stores'], ['label' => 'Fashion Roots', 'amount' => 148640.30, 'orders' => 122, 'secondary' => '19 stores'], ['label' => 'Elite Retail Group', 'amount' => 105390.15, 'orders' => 91, 'secondary' => '16 stores'], ['label' => 'Trendsetters Co.', 'amount' => 86420.00, 'orders' => 74, 'secondary' => '12 stores']],
            'bulk' => [['label' => 'Emerald Wholesale Ltd.', 'amount' => 286540.00, 'orders' => 124, 'secondary' => '42 products'], ['label' => 'Hat & Co. Distributors', 'amount' => 248650.50, 'orders' => 98, 'secondary' => '36 products'], ['label' => 'Irish Retail Supply', 'amount' => 198420.25, 'orders' => 76, 'secondary' => '31 products'], ['label' => 'Northstar Outfitters', 'amount' => 164280.00, 'orders' => 62, 'secondary' => '24 products'], ['label' => 'Atlantic Wholesale', 'amount' => 129540.75, 'orders' => 49, 'secondary' => '18 products']],
            'franchise' => [['label' => 'Emerald Rozalia UK', 'amount' => 118450.60, 'orders' => 812, 'secondary' => '24 stores'], ['label' => 'Emerald Rozalia USA', 'amount' => 96230.40, 'orders' => 654, 'secondary' => '18 stores'], ['label' => 'Emerald Rozalia Canada', 'amount' => 71645.30, 'orders' => 512, 'secondary' => '12 stores'], ['label' => 'Emerald Rozalia Australia', 'amount' => 58760.20, 'orders' => 421, 'secondary' => '10 stores'], ['label' => 'Emerald Rozalia Germany', 'amount' => 44220.10, 'orders' => 305, 'secondary' => '8 stores']],
            'franchise_retail' => [['label' => 'Emerald Store - Limerick', 'amount' => 28640.00, 'orders' => 286, 'secondary' => 'Limerick'], ['label' => 'Emerald Store - Cork', 'amount' => 24850.20, 'orders' => 242, 'secondary' => 'Cork'], ['label' => 'Emerald Store - Galway', 'amount' => 22430.80, 'orders' => 198, 'secondary' => 'Galway'], ['label' => 'Emerald Store - Belfast', 'amount' => 19640.25, 'orders' => 176, 'secondary' => 'Belfast'], ['label' => 'Emerald Store - Waterford', 'amount' => 17860.40, 'orders' => 152, 'secondary' => 'Waterford']],
            default => [['label' => 'Emma Walsh', 'amount' => 18640.20, 'orders' => 84, 'secondary' => 'Gold'], ['label' => 'John Smith', 'amount' => 15420.50, 'orders' => 72, 'secondary' => 'Gold'], ['label' => 'Aoife Byrne', 'amount' => 12860.75, 'orders' => 61, 'secondary' => 'Silver'], ['label' => 'Michael O\'Connor', 'amount' => 11240.30, 'orders' => 54, 'secondary' => 'Silver'], ['label' => 'Sarah Kelly', 'amount' => 9840.10, 'orders' => 48, 'secondary' => 'Bronze']],
        };
    }

    private function performanceRows(Collection $orders, string $type): array
    {
        $total = max(1, $orders->count());
        return $orders->groupBy(fn (Order $order): string => Str::headline((string) ($order->fulfillment_status ?: 'pending')))->map(fn (Collection $matches, string $label): array => ['label' => $label, 'value' => $matches->count(), 'percent' => round(($matches->count() / $total) * 100, 1), 'color' => $this->statusColor($label)])->sortByDesc('value')->take(5)->values()->all() ?: $this->previewPerformanceRows($type);
    }

    private function previewPerformanceRows(string $type): array
    {
        return match ($type) {
            'online' => [['label' => 'Unfulfilled', 'value' => 1456, 'percent' => 23.3, 'color' => '#f2b20d'], ['label' => 'Picking', 'value' => 1224, 'percent' => 19.6, 'color' => '#2676cc'], ['label' => 'Packed', 'value' => 786, 'percent' => 12.6, 'color' => '#6633a0'], ['label' => 'Shipped', 'value' => 1123, 'percent' => 18.0, 'color' => '#087b72'], ['label' => 'Delivered', 'value' => 1045, 'percent' => 16.7, 'color' => '#08753d']],
            'bulk' => [['label' => 'Wholesale Portal', 'value' => 542, 'percent' => 46.9, 'color' => '#2676cc'], ['label' => 'Account Manager', 'value' => 326, 'percent' => 28.2, 'color' => '#ef870d'], ['label' => 'Email Orders', 'value' => 188, 'percent' => 16.3, 'color' => '#6633a0'], ['label' => 'Phone Orders', 'value' => 100, 'percent' => 8.7, 'color' => '#087b72']],
            'franchise', 'franchise_retail' => [['label' => 'Delivered', 'value' => $type === 'franchise' ? 614 : 512, 'percent' => 45.0, 'color' => '#08753d'], ['label' => 'Shipped', 'value' => $type === 'franchise' ? 846 : 654, 'percent' => 48.2, 'color' => '#087b72'], ['label' => 'Picking', 'value' => $type === 'franchise' ? 218 : 126, 'percent' => 15.1, 'color' => '#2676cc'], ['label' => 'On Hold', 'value' => $type === 'franchise' ? 82 : 64, 'percent' => 5.0, 'color' => '#f2b20d']],
            default => [['label' => 'Delivered', 'value' => $type === 'buyer' ? 1082 : 338, 'percent' => $type === 'buyer' ? 38.8 : 33.0, 'color' => '#08753d'], ['label' => 'Shipped', 'value' => $type === 'buyer' ? 1284 : 452, 'percent' => $type === 'buyer' ? 46.1 : 44.1, 'color' => '#087b72'], ['label' => 'Processing', 'value' => $type === 'buyer' ? 513 : 228, 'percent' => $type === 'buyer' ? 18.4 : 22.3, 'color' => '#ef870d'], ['label' => 'Pending', 'value' => $type === 'buyer' ? 247 : 84, 'percent' => $type === 'buyer' ? 8.9 : 8.2, 'color' => '#f2b20d']],
        };
    }

    private function previewRows(string $type): array
    {
        if ($type === 'online') return [
            ['number' => 'ONL-250501-0001', 'entity' => 'John Smith', 'contact' => 'john.smith@email.com · +353 87 123 4567', 'date' => '01 May 2025 · 11:20 AM', 'channel' => 'Website', 'payment' => 'Visa •••• 4242', 'payment_status' => 'Paid', 'amount' => 128.95, 'items' => '1 item', 'status' => 'New', 'status_key' => 'pending', 'fulfillment' => 'Unfulfilled', 'fulfillment_key' => 'pending'],
            ['number' => 'ONL-250501-0002', 'entity' => 'Sarah Kelly', 'contact' => 'sarah.kelly@email.com · +353 86 234 5678', 'date' => '01 May 2025 · 10:45 AM', 'channel' => 'Website', 'payment' => 'Mastercard •••• 5555', 'payment_status' => 'Paid', 'amount' => 2450.00, 'items' => '8 items', 'status' => 'Processing', 'status_key' => 'processing', 'fulfillment' => 'Picking', 'fulfillment_key' => 'picking'],
            ['number' => 'ONL-250501-0003', 'entity' => 'Michael O’Connor', 'contact' => 'michael.oconnor@email.com · +353 87 345 6789', 'date' => '01 May 2025 · 09:32 AM', 'channel' => 'Mobile App', 'payment' => 'PayPal', 'payment_status' => 'Paid', 'amount' => 5680.00, 'items' => '16 items', 'status' => 'Pending Approval', 'status_key' => 'pending', 'fulfillment' => 'On Hold', 'fulfillment_key' => 'on_hold'],
            ['number' => 'ONL-250501-0004', 'entity' => 'Emma Walsh', 'contact' => 'emma.walsh@email.com · +353 85 456 7890', 'date' => '01 May 2025 · 08:55 AM', 'channel' => 'Website', 'payment' => 'Visa •••• 1111', 'payment_status' => 'Paid', 'amount' => 980.50, 'items' => '4 items', 'status' => 'Approved', 'status_key' => 'approved', 'fulfillment' => 'Ready to Ship', 'fulfillment_key' => 'ready_to_ship'],
            ['number' => 'ONL-250501-0005', 'entity' => 'Liam Murphy', 'contact' => 'liam.murphy@email.com · +353 87 567 8901', 'date' => '30 Apr 2025 · 04:18 PM', 'channel' => 'Website', 'payment' => 'Mastercard •••• 3333', 'payment_status' => 'Paid', 'amount' => 256.75, 'items' => '2 items', 'status' => 'Shipped', 'status_key' => 'shipped', 'fulfillment' => 'Shipped', 'fulfillment_key' => 'shipped'],
            ['number' => 'ONL-250501-0006', 'entity' => 'Aoife Byrne', 'contact' => 'aoife.byrne@email.com · +353 86 678 9012', 'date' => '30 Apr 2025 · 02:04 PM', 'channel' => 'Website', 'payment' => 'Stripe', 'payment_status' => 'Paid', 'amount' => 89.99, 'items' => '1 item', 'status' => 'Delivered', 'status_key' => 'completed', 'fulfillment' => 'Delivered', 'fulfillment_key' => 'delivered'],
            ['number' => 'ONL-250501-0007', 'entity' => 'David Taylor', 'contact' => 'david.taylor@email.com · +353 85 789 0123', 'date' => '30 Apr 2025 · 11:47 AM', 'channel' => 'Mobile App', 'payment' => 'Visa •••• 8888', 'payment_status' => 'Paid', 'amount' => 199.00, 'items' => '2 items', 'status' => 'Cancelled', 'status_key' => 'cancelled', 'fulfillment' => 'Cancelled', 'fulfillment_key' => 'closed'],
            ['number' => 'ONL-250501-0008', 'entity' => 'Chloe Brown', 'contact' => 'chloe.brown@email.com · +353 89 890 1234', 'date' => '30 Apr 2025 · 10:16 AM', 'channel' => 'Website', 'payment' => 'Mastercard •••• 7777', 'payment_status' => 'Paid', 'amount' => 74.50, 'items' => '1 item', 'status' => 'Return Requested', 'status_key' => 'refunded', 'fulfillment' => 'Return Pending', 'fulfillment_key' => 'on_hold'],
        ];

        $names = match ($type) {
            'franchise' => ['Emerald Store - Dublin', 'Emerald Store - Cork', 'Emerald Store - Galway', 'Emerald Store - Belfast', 'Emerald Store - Waterford', 'Emerald Store - Sligo', 'Emerald Store - Kilkenny', 'Emerald Store - Limerick'],
            'franchise_retail' => ['Emerald Store - Limerick', 'Emerald Store - Cork', 'Emerald Store - Galway', 'Emerald Store - Belfast', 'Emerald Store - Waterford', 'Emerald Store - Sligo', 'Emerald Store - Kilkenny', 'Emerald Store - Dublin City'],
            'bulk' => ['Emerald Wholesale Ltd.', 'Hat & Co. Distributors', 'Irish Retail Supply', 'Northstar Outfitters', 'Atlantic Wholesale', 'Clover Distribution', 'Limerick Trade House', 'Celtic Hat Company'],
            'buyer' => ['Emma Walsh', 'John Smith', 'Aoife Byrne', 'Michael O\'Connor', 'Sarah Kelly', 'Liam Murphy', 'Chloe Brown', 'David Taylor'],
            default => ['Emerald Retail Group', 'Style Hub Ltd.', 'Fashion Roots', 'Elite Retail Group', 'Trendsetters Co.', 'Design District Store', 'Clover Brands', 'Irish Hat House'],
        };
        $amounts = match ($type) { 'franchise' => [4860.50, 3765.25, 2980.00, 2485.75, 1980.20, 1640.30, 1425.00, 1240.50], 'franchise_retail' => [1280.50, 1145.25, 980.00, 865.75, 740.20, 625.30, 520.00, 480.50], 'bulk' => [24560.50, 18640.25, 15420.00, 12850.75, 9860.20, 8450.00, 7320.30, 6240.50], 'buyer' => [420.50, 245.00, 198.75, 156.20, 128.00, 94.50, 79.99, 64.50], default => [12560.50, 9865.25, 8240.00, 6485.75, 5280.20, 4460.00, 3425.00, 2840.50] };
        $items = $type === 'bulk' ? [5420, 3250, 2150, 4800, 1850, 6750, 1320, 2640] : range(1, 8);
        $states = [['New', 'pending', 'Unfulfilled', 'pending'], ['Processing', 'processing', 'Picking', 'picking'], [$type === 'buyer' ? 'Pending Payment' : 'Pending Approval', 'pending', 'On Hold', 'on_hold'], ['Approved', 'approved', 'Ready to Ship', 'ready_to_ship'], ['Shipped', 'shipped', 'Shipped', 'shipped'], ['Delivered', 'completed', 'Delivered', 'delivered'], ['Cancelled', 'cancelled', 'Cancelled', 'closed'], ['Return Requested', 'refunded', 'Return Pending', 'on_hold']];
        return collect($names)->map(function (string $name, int $index) use ($type, $amounts, $items, $states): array {
            [$status, $statusKey, $fulfillment, $fulfillmentKey] = $states[$index];
            $contact = $type === 'buyer' ? (($index < 2 ? 'Gold' : ($index < 5 ? 'Silver' : 'Bronze')).' · '.Str::lower(str_replace(' ', '.', $name)).'@email.com · +353 87 123 45'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) : 'Account '.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT).' · orders@emeraldstore.ie · +353 61 555 '.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $payment = $type === 'bulk' ? ($index % 2 ? 'Bank Transfer' : 'Credit Account') : ($type === 'franchise' ? ($index % 2 ? 'Card •••• 4242' : 'Bank Transfer') : ($type === 'franchise_retail' ? ($index % 2 ? 'Cash on Delivery' : 'Card •••• 4242') : ['Visa •••• 4242', 'Mastercard •••• 5555', 'PayPal', 'Credit Card •••• 1111', 'Stripe', 'Visa •••• 3333', 'Mastercard •••• 8888', 'PayPal'][$index]));
            return ['number' => self::META[$type]['prefix'].'-250501-000'.($index + 1), 'entity' => $name, 'contact' => $contact, 'date' => '01 May 2025 · '.(11 - min($index, 5)).':'.str_pad((string) (20 + $index), 2, '0', STR_PAD_LEFT).' AM', 'payment' => $payment, 'payment_status' => $index === 2 ? 'Pending' : 'Paid', 'amount' => $amounts[$index], 'items' => $type === 'bulk' ? number_format($items[$index]).' units' : $items[$index].' items', 'status' => $status, 'status_key' => $statusKey, 'fulfillment' => $fulfillment, 'fulfillment_key' => $fulfillmentKey];
        })->all();
    }

    private function selectorOptions(Collection $orders, array $meta): array
    {
        return array_values(array_unique(array_merge($meta['selector_options'], $orders->map(fn (Order $order): string => $this->selectorValue($order, $meta))->filter()->unique()->values()->all())));
    }

    private function paymentMethods(Collection $orders): array
    {
        return array_values(array_unique(array_merge(['Visa', 'Mastercard', 'PayPal', 'Bank Transfer', 'Stripe', 'Cash on Delivery'], $orders->map(fn (Order $order): string => (string) $order->payment_method)->filter()->values()->all())));
    }

    private function selectorValue(Order $order, array $meta): string
    {
        $address = (array) ($order->shipping_address ?: []);
        return match ($meta['selector_key']) {
            'channel' => (string) data_get($address, 'channel', $order->order_type === 'buyer' ? 'Marketplace' : 'Website'),
            'franchise' => (string) data_get($address, 'franchise', 'Emerald Rozalia UK'),
            'store' => (string) data_get($address, 'store', data_get($address, 'name', 'Limerick Flagship')),
            'company', 'buyer' => (string) ($order->user?->name ?: data_get($address, 'company', data_get($address, 'name', 'Guest Customer'))),
            default => 'Other',
        };
    }

    private function dateRange(Request $request): array
    {
        $from = $this->parseDate($request->query('date_from')) ?: Carbon::create(2025, 4, 1); $to = $this->parseDate($request->query('date_to')) ?: Carbon::create(2025, 5, 1);
        if ($from->gt($to)) [$from, $to] = [$to->copy(), $from->copy()];
        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'from_label' => $from->format('d M Y'), 'to_label' => $to->format('d M Y')];
    }

    private function tabs(array $meta): array
    {
        return ['all' => 'All Orders', 'new' => 'New', 'processing' => 'Processing', 'pending' => $meta['pending_label'], 'approved' => 'Approved', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'returns' => 'Return / Refund'];
    }

    private function actualNotifications(Collection $orders, string $type, array $stats): array
    {
        return $this->notificationRows($stats, $orders->where('payment_status', 'failed')->count(), Product::query()->whereBetween('stock', [1, 10])->count(), $type);
    }

    private function previewNotifications(string $type, array $stats): array
    {
        return $this->notificationRows($stats, match ($type) { 'online' => 8, 'corporate' => 3, 'bulk' => 2, 'franchise' => 4, 'franchise_retail' => 1, default => 6 }, match ($type) { 'online', 'buyer' => 12, 'bulk' => 18, 'franchise' => 15, 'franchise_retail' => 10, default => 8 }, $type);
    }

    private function notificationRows(array $stats, int $failed, int $lowStock, string $type): array
    {
        $pending = Str::lower(self::META[$type]['pending_label']);
        return [['tone' => 'orange', 'text' => number_format($stats['pending']).' orders '.$pending, 'time' => '5 mins ago'], ['tone' => 'blue', 'text' => number_format($stats['in_progress']).' orders in progress', 'time' => '10 mins ago'], ['tone' => 'green', 'text' => number_format($stats['shipped']).' orders shipped today', 'time' => '15 mins ago'], ['tone' => 'gray', 'text' => number_format($stats['returns']).' return requests received', 'time' => '25 mins ago'], ['tone' => 'red', 'text' => number_format($stats['cancelled']).' orders cancelled', 'time' => '35 mins ago'], ['tone' => 'orange', 'text' => 'Low stock for '.number_format($lowStock).' products', 'time' => '45 mins ago'], ['tone' => 'red', 'text' => 'Payment failed for '.number_format($failed).' orders', 'time' => '1 hour ago'], ['tone' => 'red', 'text' => $stats['value'] >= 1000 ? 'High value order received' : 'Order review required', 'time' => '1 hour ago']];
    }

    private function withShares(array $rows, string $field): array
    {
        $total = max(1, (float) collect($rows)->sum($field));
        return array_map(fn (array $row): array => [...$row, 'share' => round(((float) ($row[$field] ?? 0) / $total) * 100, 1)], $rows);
    }

    private function statusColor(string $label): string
    {
        return match (Str::lower($label)) { 'delivered' => '#08753d', 'shipped' => '#087b72', 'picking', 'processing' => '#2676cc', 'packed', 'ready to ship' => '#6633a0', default => '#f2b20d' };
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') return null;
        try { return Carbon::parse($value); } catch (\Throwable) { return null; }
    }
}
