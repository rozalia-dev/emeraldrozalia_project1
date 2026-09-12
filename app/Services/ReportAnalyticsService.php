<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Server-first data provider for the reference Reports dashboards.
 *
 * The UI preserves the reference layout on an empty installation, while every
 * filter and headline is derived from PostgreSQL domain records.
 */
class ReportAnalyticsService
{
    public const REPORTS = ['order', 'communication', 'customer'];

    private const ORDER_TYPES = [
        'online' => ['label' => 'Online Orders', 'color' => '#2676cc'],
        'corporate' => ['label' => 'Corporate Orders', 'color' => '#f08a14'],
        'bulk' => ['label' => 'Bulk Orders', 'color' => '#f2b20d'],
        'franchise' => ['label' => 'Franchise Orders', 'color' => '#a33c98'],
        'franchise_retail' => ['label' => 'Franchise Retail Orders', 'color' => '#087b72'],
        'buyer' => ['label' => 'Buyer Orders', 'color' => '#1b7065'],
    ];

    private const TABS = [
        'order' => [
            'overview' => 'Overview', 'by-order-type' => 'By Order Type', 'by-channel' => 'By Channel',
            'sales-analytics' => 'Sales Analytics', 'returns-refunds' => 'Returns & Refunds',
            'geographic-analysis' => 'Geographic Analysis', 'customer-analysis' => 'Customer Analysis',
            'products' => 'Products', 'delivery-fulfillment' => 'Delivery & Fulfillment',
            'comparative-analysis' => 'Comparative Analysis',
        ],
        'communication' => [
            'overview' => 'Overview', 'channels' => 'Channels', 'agents' => 'Agents', 'customers' => 'Customers',
            'franchise-stores' => 'Franchise & Stores', 'orders' => 'Orders', 'approvals' => 'Approvals',
            'follow-ups' => 'Follow-ups', 'sla-performance' => 'SLA & Performance', 'trends' => 'Trends', 'audit' => 'Audit',
        ],
        'customer' => [
            'overview' => 'Overview', 'acquisition' => 'Acquisition', 'customer-profile' => 'Customer Profile',
            'purchase-behavior' => 'Purchase Behavior', 'rfm-analysis' => 'RFM Analysis', 'lifetime-value' => 'Lifetime Value',
            'retention-churn' => 'Retention & Churn', 'customer-engagement' => 'Customer Engagement',
            'geographic-analysis' => 'Geographic Analysis', 'cohort-analysis' => 'Cohort Analysis',
            'comparative-analysis' => 'Comparative Analysis',
        ],
    ];

    private const META = [
        'order' => [
            'title' => 'Order Reports',
            'subtitle' => 'Comprehensive insights across all order types and channels.',
            'source' => 'Reports', 'icon' => 'shopping-bag',
        ],
        'communication' => [
            'title' => 'Communication Reports',
            'subtitle' => 'Track performance and effectiveness of all communication channels and activities.',
            'source' => 'Communication Center', 'icon' => 'message',
        ],
        'customer' => [
            'title' => 'Customer Reports',
            'subtitle' => 'Comprehensive insights into customer acquisition, behavior, value, retention and engagement.',
            'source' => 'Reports', 'icon' => 'users',
        ],
    ];

    public function build(string $report, Request $request): array
    {
        abort_unless(in_array($report, self::REPORTS, true), 404);

        $filters = $this->filters($report, $request);
        $tabs = self::TABS[$report];
        $activeTab = (string) $request->query('tab', 'overview');
        if (!array_key_exists($activeTab, $tabs)) $activeTab = 'overview';

        $payload = match ($report) {
            'order' => $this->orderData($filters),
            'communication' => $this->communicationData($filters),
            'customer' => $this->customerData($filters),
        };

        return array_merge([
            'report' => $report,
            'title' => self::META[$report]['title'],
            'subtitle' => self::META[$report]['subtitle'],
            'source' => self::META[$report]['source'],
            'icon' => self::META[$report]['icon'],
            'tabs' => $tabs,
            'activeTab' => $activeTab,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($report),
        ], $payload);
    }

    public function exportRows(string $report, Request $request): array
    {
        $payload = $this->build($report, $request);
        $rows = [['Metric', 'Value', 'Change']];
        foreach ($payload['metrics'] as $metric) {
            $rows[] = [$metric['label'], $metric['value'], $metric['change'] ?? ''];
        }

        if ($report === 'order') {
            $rows[] = [];
            $rows[] = ['Order Type', 'Orders', 'Share'];
            foreach ($payload['orderTypes'] as $row) $rows[] = [$row['label'], $row['value'], $row['share'].'%'];
        } elseif ($report === 'communication') {
            $rows[] = [];
            $rows[] = ['Channel', 'Conversations', 'Share'];
            foreach ($payload['channels'] as $row) $rows[] = [$row['label'], $row['value'], $row['share'].'%'];
        } else {
            $rows[] = [];
            $rows[] = ['Customer', 'Revenue', 'Orders'];
            foreach ($payload['topCustomers'] as $row) $rows[] = [$row['name'], $row['revenue'], $row['orders']];
        }

        return $rows;
    }

    private function filters(string $report, Request $request): array
    {
        $read = static fn (string $key, mixed $default = null): mixed => $request->input($key, $request->query($key, $default));
        $from = $this->date($read('from', '2025-04-01'), '2025-04-01')->startOfDay();
        $to = $this->date($read('to', '2025-05-01'), '2025-05-01')->endOfDay();
        if ($from->gt($to)) [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];

        return [
            'q' => trim((string) $read('q', '')),
            'from' => $from, 'to' => $to,
            'from_value' => $from->toDateString(), 'to_value' => $to->toDateString(),
            'from_label' => $from->format('d M Y'), 'to_label' => $to->format('d M Y'),
            'compare_label' => 'No comparison loaded',
            'order_type' => $this->allowed((string) $read('order_type', 'all'), array_merge(['all'], array_keys(self::ORDER_TYPES)), 'all'),
            'channel' => $this->allowed((string) $read('channel', 'all'), $report === 'order'
                ? ['all', 'website', 'mobile', 'portal', 'other', 'unattributed']
                : ($report === 'communication' ? ['all', 'web', 'chat', 'whatsapp', 'email', 'phone', 'system'] : ['all']), 'all'),
            'status' => $this->allowed((string) $read('status', 'all'), $report === 'order'
                ? ['all', 'pending', 'processing', 'shipped', 'delivered', 'cancelled']
                : ($report === 'communication' ? ['all', 'new', 'open', 'pending', 'closed'] : ['all']), 'all'),
            'segment' => $this->allowed((string) $read('segment', 'all'), ['all', 'new', 'repeat', 'vip', 'at-risk'], 'all'),
            'breakdown' => $this->allowed((string) $read('breakdown', 'day'), ['day', 'week', 'month'], 'day'),
            'customer_type' => $this->allowed((string) $read('customer_type', 'all'), ['all', 'individual', 'business', 'franchise'], 'all'),
        ];
    }

    private function date(mixed $value, string $fallback): Carbon
    {
        try {
            $value = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
            return Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return Carbon::createFromFormat('Y-m-d', $fallback);
        }
    }

    private function allowed(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function filterOptions(string $report): array
    {
        return match ($report) {
            'order' => [
                'order_type' => array_merge(['all' => 'All Order Types'], collect(self::ORDER_TYPES)->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label']])->all()),
                'channel' => ['all' => 'All Channels', 'website' => 'Website', 'mobile' => 'Mobile App', 'portal' => 'Customer Portal', 'other' => 'Other Integrations', 'unattributed' => 'Unattributed'],
                'status' => ['all' => 'All Statuses', 'pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'],
            ],
            'communication' => [
                'channel' => ['all' => 'All Channels', 'web' => 'Web Chat', 'chat' => 'Live Chat', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'phone' => 'Phone'],
                'status' => ['all' => 'All Statuses', 'new' => 'New', 'open' => 'Open', 'pending' => 'Pending', 'closed' => 'Closed'],
            ],
            'customer' => [
                'segment' => ['all' => 'All Segments', 'new' => 'New Customers', 'repeat' => 'Repeat Customers', 'vip' => 'VIP Customers', 'at-risk' => 'At Risk'],
                'customer_type' => ['all' => 'All Customer Types', 'individual' => 'Individual', 'business' => 'Business', 'franchise' => 'Franchise'],
            ],
        };
    }

    private function orderData(array $filters): array
    {
        $orders = Order::query()->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['order_type'] !== 'all', fn ($query) => $query->where('order_type', $filters['order_type']))
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('number', 'like', '%'.$filters['q'].'%')->orWhere('email', 'like', '%'.$filters['q'].'%')))
            ->latest()->get();
        $orders = $orders->filter(fn (Order $order): bool => $filters['channel'] === 'all' || $this->orderChannel($order) === $filters['channel']);

        $sales = round((float) $orders->sum(fn (Order $order): float => (float) $order->total), 2);
        $returns = ReturnRequest::query()->whereIn('order_id', $orders->pluck('id'))->with('order')->get();
        $items = (int) OrderItem::query()->whereIn('order_id', $orders->pluck('id'))->sum('quantity');
        $count = $orders->count();
        $returnRate = $count ? round(($returns->count() / $count) * 100, 2) : 0;
        $refundValue = round((float) $returns->sum(fn (ReturnRequest $return): float => (float) ($return->order?->total ?: 0)), 2);

        return [
            'isEmpty' => $count === 0,
            'dataNote' => $count === 0 ? 'No orders found for the selected filters.' : 'Live PostgreSQL data · '.$count.' orders in this report window',
            'metrics' => [
                $this->metric('Total Orders', number_format($count), '—', 'green', 'shopping-bag'),
                $this->metric('Total Order Value (EUR)', $this->money($sales), '—', 'blue', 'credit-card'),
                $this->metric('Avg. Order Value (EUR)', $this->money($count ? $sales / $count : 0), '—', 'orange', 'package'),
                $this->metric('Items Ordered', number_format($items), '—', 'purple', 'tag'),
                $this->metric('Return Rate', $count ? number_format($returnRate, 2).'%' : '—', '—', 'teal', 'refresh'),
                $this->metric('Refund Rate', $count ? number_format(($returns->where('status', 'refunded')->count() / $count) * 100, 2).'%' : '—', '—', 'red', 'percent'),
                $this->metric('Customer Satisfaction', '—', '—', 'green', 'star'),
            ],
            'orderTypes' => $this->groupOrders($orders, 'order_type'),
            'channels' => $this->groupOrdersByChannel($orders),
            'statuses' => $this->groupSimple($orders->groupBy('status'), fn (Collection $group, string $key): array => ['label' => Str::headline($key), 'value' => $group->count(), 'share' => $this->share($group->count(), $count), 'color' => '#2676cc']),
            'orderSummary' => $this->orderSummary($orders),
            'categories' => $this->categoryRows($orders),
            'regions' => $this->regionRows($orders),
            'topCustomers' => $this->orderTopCustomers($orders),
            'timeSeries' => $this->timeSeries($orders, $filters['from'], $filters['to'], 'orders'),
            'alerts' => $this->orderAlerts($orders, $returns),
            'refundValue' => $this->money($refundValue),
            'returnsCount' => $returns->count(),
            'returnRateLabel' => $count ? number_format($returnRate, 2).'%' : '—',
            'avgReturnProcessing' => '—',
        ];
    }

    private function communicationData(array $filters): array
    {
        $conversations = Conversation::query()->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['channel'] !== 'all', fn ($query) => $query->where('channel', $filters['channel']))
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('contact', 'like', '%'.$filters['q'].'%')->orWhere('subject', 'like', '%'.$filters['q'].'%')))
            ->with('assignee')->latest()->get();

        $count = $conversations->count();
        $channels = $this->conversationChannels($conversations);
        $statuses = $this->conversationStatuses($conversations);
        $agents = $conversations->groupBy('assigned_to')->map(function (Collection $rows): array {
            $user = $rows->first()?->assignee;
            $csat = $this->csatSummary($rows);
            $sla = $this->slaSummary($rows);
            return ['name' => $user?->name ?: 'Unassigned', 'conversations' => $rows->count(), 'csat' => $csat['score'] ?? '—', 'sla' => $sla['rate']];
        })->sortByDesc('conversations')->values()->take(5)->all();
        $firstResponse = $this->formatDuration($conversations, 'first_response_seconds');
        $resolution = $this->formatDuration($conversations, 'resolution_seconds');
        $sla = $this->slaSummary($conversations);
        $csat = $this->csatSummary($conversations);

        return [
            'isEmpty' => $count === 0,
            'dataNote' => $count === 0 ? 'No conversations found for the selected filters.' : 'Live PostgreSQL data · '.$count.' conversations in this report window',
            'metrics' => [
                $this->metric('Total Conversations', number_format($count), '—', 'green', 'message'),
                $this->metric('New Conversations', number_format($conversations->where('status', 'new')->count()), '—', 'blue', 'plus'),
                $this->metric('Closed Conversations', number_format($conversations->where('status', 'closed')->count()), '—', 'orange', 'check'),
                $this->metric('Avg. First Response Time', $firstResponse, '—', 'purple', 'clock'),
                $this->metric('Avg. Resolution Time', $resolution, '—', 'teal', 'refresh'),
                $this->metric('SLA Compliance', $sla['rate'], '—', 'green', 'shield'),
                $this->metric('Customer Satisfaction', $csat['value'], '—', 'green', 'star'),
            ],
            'channels' => $channels, 'statuses' => $statuses, 'agents' => $agents,
            'categories' => $this->conversationCategories($conversations),
            'linkedRows' => $this->linkedConversationRows($conversations),
            'timeSeries' => $this->timeSeries($conversations, $filters['from'], $filters['to'], 'conversations'),
            'topics' => $conversations->take(5)->map(fn (Conversation $conversation): array => ['topic' => $conversation->subject ?: 'Customer enquiry', 'volume' => 1, 'change' => 'New'])->all(),
            'alerts' => [['label' => 'Unassigned conversations', 'value' => (string) $conversations->whereNull('assigned_to')->count(), 'tone' => 'orange'], ['label' => 'Follow-ups due', 'value' => (string) $conversations->whereNotNull('follow_up_at')->count(), 'tone' => 'red']],
            'slaRate' => $sla['rate'], 'slaWithin' => $sla['within'], 'slaBreached' => $sla['breached'],
            'avgCsat' => $csat['value'], 'csatScore' => $csat['score'], 'csatChange' => '—',
            'firstResponse' => $firstResponse, 'resolution' => $resolution,
            'hasResponseTiming' => $this->metadataNumbers($conversations, 'first_response_seconds')->isNotEmpty() || $this->metadataNumbers($conversations, 'resolution_seconds')->isNotEmpty(),
            'hasCsatData' => $csat['score'] !== '—',
        ];
    }

    private function customerData(array $filters): array
    {
        $customers = User::query()->where('is_admin', false)->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', '%'.$filters['q'].'%')->orWhere('email', 'like', '%'.$filters['q'].'%')))
            ->withCount(['orders as orders_count' => fn ($query) => $query->whereBetween('created_at', [$filters['from'], $filters['to']])])
            ->with('customerProfile.primaryGroup')->latest()->get();
        $customers = $customers->filter(function (User $customer) use ($filters): bool {
            $groupType = strtolower((string) ($customer->customerProfile?->primaryGroup?->type ?? 'individual'));
            $typeMatches = match ($filters['customer_type']) {
                'business' => str_contains($groupType, 'business') || str_contains($groupType, 'corporate') || str_contains($groupType, 'wholesale'),
                'franchise' => str_contains($groupType, 'franchise'),
                'individual' => !str_contains($groupType, 'business') && !str_contains($groupType, 'corporate') && !str_contains($groupType, 'wholesale') && !str_contains($groupType, 'franchise'),
                default => true,
            };
            if (! $typeMatches) return false;
            return match ($filters['segment']) {
                'new' => $customer->orders_count === 0,
                'repeat' => $customer->orders_count > 1,
                'vip' => (bool) ($customer->customerProfile?->is_vip) || $customer->orders_count >= 5,
                'at-risk' => ($customer->customerProfile?->account_status ?? $customer->status ?? 'active') !== 'active',
                default => true,
            };
        })->values();

        $customerIds = $customers->pluck('id');
        $orders = Order::query()->whereIn('user_id', $customerIds)->whereBetween('created_at', [$filters['from'], $filters['to']])->get();
        $revenue = round((float) $orders->sum(fn (Order $order): float => (float) $order->total), 2);
        $new = $customers->where('orders_count', 0)->count();
        $repeat = $customers->where('orders_count', '>', 1)->count();
        $vip = $customers->filter(fn (User $customer): bool => (bool) ($customer->customerProfile?->is_vip) || $customer->orders_count >= 5)->count();
        $active = $customers->filter(fn (User $customer): bool => ($customer->customerProfile?->account_status ?? $customer->status ?? 'active') === 'active')->count();
        $topCustomers = $customers->map(function (User $customer) use ($orders): array {
            $rows = $orders->where('user_id', $customer->id);
            return ['name' => $customer->name, 'email' => $customer->email, 'revenue' => $this->money((float) $rows->sum('total')), 'orders' => $rows->count(), 'segment' => $rows->count() > 1 ? 'Repeat' : 'New'];
        })->sortByDesc(fn (array $row): float => (float) str_replace([',', '€'], '', $row['revenue']))->values()->take(6)->all();

        return [
            'isEmpty' => $customers->count() === 0,
            'dataNote' => $customers->count() === 0 ? 'No customers found for the selected filters.' : 'Live PostgreSQL data · '.$customers->count().' customers in this report window',
            'metrics' => [
                $this->metric('Total Customers', number_format($customers->count()), '—', 'green', 'users'),
                $this->metric('New Customers', number_format($new), '—', 'blue', 'user'),
                $this->metric('Active Customers', number_format($active), '—', 'orange', 'eye'),
                $this->metric('Repeat Customers', number_format($repeat), '—', 'purple', 'refresh'),
                $this->metric('Total Revenue (EUR)', $this->money($revenue), '—', 'teal', 'credit-card'),
                $this->metric('Avg. Revenue per Customer', $this->money($customers->count() ? $revenue / $customers->count() : 0), '—', 'green', 'chart'),
                $this->metric('Retention Rate', $customers->count() ? number_format(($repeat / $customers->count()) * 100, 2).'%' : '—', '—', 'blue', 'shield'),
                $this->metric('Avg. Satisfaction', '—', '—', 'green', 'star'),
            ],
            'segments' => $this->customerSegments($customers),
            'channels' => $this->customerChannels($customers),
            'revenueSegments' => $this->customerRevenueSegments($orders),
            'topCustomers' => $topCustomers,
            'valueSummary' => [['label' => 'High Value Customers', 'value' => number_format($customers->map(fn (User $customer): float => (float) $orders->where('user_id', $customer->id)->sum('total'))->filter(fn (float $value): bool => $value >= 1000)->count()), 'detail' => '€1,000+ revenue'], ['label' => 'Average Order Value', 'value' => $this->money($orders->count() ? $revenue / $orders->count() : 0), 'detail' => 'Across customer orders'], ['label' => 'Purchase Frequency', 'value' => number_format($customers->count() ? $orders->count() / $customers->count() : 0, 1), 'detail' => 'Orders per customer'], ['label' => 'Churn Risk', 'value' => number_format(max(0, $customers->count() - $active)), 'detail' => 'Customers to re-engage']],
            'rfmRows' => $this->rfmRows($customers, $orders),
            'regions' => $this->customerRegions($customers),
            'timeSeries' => $this->timeSeries($customers, $filters['from'], $filters['to'], 'customers'),
            'alerts' => [['label' => 'Customers at risk', 'value' => number_format(max(0, $customers->count() - $active)), 'tone' => 'orange'], ['label' => 'New customers without an order', 'value' => number_format(max(0, $customers->count() - $orders->pluck('user_id')->unique()->count())), 'tone' => 'red']],
            'totalRevenueLabel' => $this->money($revenue), 'customerTotalLabel' => number_format($customers->count()),
            'retentionRate' => $customers->count() ? number_format(($repeat / $customers->count()) * 100, 2).'%' : '—',
            'satisfactionLabel' => '—', 'satisfactionChange' => '—', 'vipCount' => $vip,
        ];
    }

    private function metric(string $label, string $value, string $change, string $tone, string $icon): array
    {
        return compact('label', 'value', 'change', 'tone', 'icon');
    }

    private function money(float $amount): string
    {
        return '€'.number_format($amount, 2);
    }

    private function share(int|float $value, int|float $total): string
    {
        return number_format($total > 0 ? ($value / $total) * 100 : 0, 2);
    }

    private function groupOrders(Collection $orders, string $field): array
    {
        $total = $orders->count();
        return collect(self::ORDER_TYPES)->map(function (array $meta, string $type) use ($orders, $total): array {
            $value = $orders->where('order_type', $type)->count();
            return ['label' => $meta['label'], 'value' => $value, 'share' => $this->share($value, $total), 'color' => $meta['color']];
        })->values()->all();
    }

    private function groupOrdersByChannel(Collection $orders): array
    {
        $meta = [
            'website' => ['label' => 'Website', 'color' => '#2676cc'], 'mobile' => ['label' => 'Mobile App', 'color' => '#f08a14'],
            'portal' => ['label' => 'Customer Portal', 'color' => '#087943'], 'other' => ['label' => 'Other Integrations', 'color' => '#a33c98'],
            'unattributed' => ['label' => 'Unattributed', 'color' => '#7b8790'],
        ];
        $total = $orders->count();
        return collect($meta)->map(function (array $row, string $channel) use ($orders, $total): array {
            $value = $orders->filter(fn (Order $order): bool => $this->orderChannel($order) === $channel)->count();
            return $row + ['value' => $value, 'share' => $this->share($value, $total)];
        })->values()->all();
    }

    private function orderChannel(Order $order): string
    {
        $value = strtolower((string) data_get($order->shipping_address, 'channel', ''));
        if (str_contains($value, 'mobile')) return 'mobile';
        if (str_contains($value, 'portal')) return 'portal';
        if (in_array($value, ['website', 'web'], true)) return 'website';
        if (str_contains($value, 'pos') || str_contains($value, 'store')) return 'other';
        return $value !== '' ? 'other' : 'unattributed';
    }

    private function groupSimple(Collection $groups, callable $callback): array
    {
        return $groups->map($callback)->values()->all();
    }

    private function orderSummary(Collection $orders): array
    {
        return collect(self::ORDER_TYPES)->map(function (array $meta, string $type) use ($orders): array {
            $rows = $orders->where('order_type', $type);
            return ['type' => $meta['label'], 'orders' => $rows->count(), 'value' => $this->money((float) $rows->sum('total')), 'avg' => $this->money($rows->count() ? (float) $rows->sum('total') / $rows->count() : 0), 'status' => $rows->pluck('status')->filter()->countBy()->sortDesc()->keys()->first() ? Str::headline((string) $rows->pluck('status')->filter()->countBy()->sortDesc()->keys()->first()) : '—'];
        })->values()->all();
    }

    private function categoryRows(Collection $orders): array
    {
        $items = OrderItem::query()->whereIn('order_id', $orders->pluck('id'))->get();
        if ($items->isEmpty()) return [['label' => 'No product categories yet', 'value' => 0, 'percent' => '0%', 'color' => '#087943']];
        $rows = $items->groupBy(fn (OrderItem $item): string => $item->name ?: 'Uncategorised')->map(fn (Collection $group, string $name): array => ['label' => $name, 'value' => $group->sum('quantity'), 'percent' => $this->share($group->sum('quantity'), $items->sum('quantity')).'%', 'color' => '#2676cc']);
        return $rows->sortByDesc('value')->values()->take(6)->all();
    }

    private function regionRows(Collection $orders): array
    {
        $total = $orders->count();
        return $orders->groupBy(fn (Order $order): string => (string) (data_get($order->shipping_address, 'country_name') ?: data_get($order->shipping_address, 'country') ?: 'Unknown'))->map(fn (Collection $rows, string $country): array => ['label' => $country, 'orders' => $rows->count(), 'value' => $this->money((float) $rows->sum('total')), 'share' => $this->share($rows->count(), $total).'%' ])->sortByDesc('orders')->values()->take(6)->all();
    }

    private function orderTopCustomers(Collection $orders): array
    {
        return $orders->groupBy('user_id')->map(function (Collection $rows, $userId): array {
            $first = $rows->first();
            return ['name' => $first?->user?->name ?: ($first?->email ?: 'Guest customer'), 'revenue' => $this->money((float) $rows->sum('total')), 'orders' => $rows->count(), 'channel' => Str::headline($this->orderChannel($first))];
        })->sortByDesc(fn (array $row): float => (float) str_replace([',', '€'], '', $row['revenue']))->values()->take(6)->all();
    }

    private function timeSeries(Collection $rows, Carbon $from, Carbon $to, string $kind): array
    {
        $points = [];
        $span = max(1, $from->diffInDays($to));
        for ($index = 0; $index < 7; $index++) {
            $date = $from->copy()->addDays((int) round(($span * $index) / 6));
            $next = $from->copy()->addDays((int) round(($span * ($index + 1)) / 6));
            $value = $rows->filter(fn ($row): bool => optional($row->created_at)->between($date->startOfDay(), $next->endOfDay(), true))->count();
            $points[] = ['label' => $date->format('d M'), 'value' => $value];
        }
        return $points;
    }

    private function conversationChannels(Collection $conversations): array
    {
        $meta = ['web' => ['label' => 'Web Chat', 'color' => '#2676cc'], 'chat' => ['label' => 'Live Chat', 'color' => '#087943'], 'whatsapp' => ['label' => 'WhatsApp', 'color' => '#1b7065'], 'email' => ['label' => 'Email', 'color' => '#f08a14'], 'phone' => ['label' => 'Phone', 'color' => '#a33c98'], 'system' => ['label' => 'System', 'color' => '#7b8790']];
        $total = $conversations->count();
        return collect($meta)->map(function (array $row, string $channel) use ($conversations, $total): array { $value = $conversations->where('channel', $channel)->count(); return $row + ['value' => $value, 'share' => $this->share($value, $total)]; })->values()->all();
    }

    private function conversationStatuses(Collection $conversations): array
    {
        $colors = ['new' => '#2676cc', 'open' => '#087943', 'pending' => '#f08a14', 'closed' => '#7b8790'];
        $total = $conversations->count();
        return collect($colors)->map(fn (string $color, string $status): array => ['label' => Str::headline($status), 'value' => $conversations->where('status', $status)->count(), 'share' => $this->share($conversations->where('status', $status)->count(), $total), 'color' => $color])->values()->all();
    }

    private function conversationCategories(Collection $conversations): array
    {
        return $conversations->groupBy(fn (Conversation $conversation): string => (string) data_get($conversation->metadata, 'category', 'General Enquiries'))->map(fn (Collection $rows, string $label): array => ['label' => $label, 'value' => $rows->count(), 'percent' => $this->share($rows->count(), $conversations->count()).'%', 'color' => '#2676cc'])->sortByDesc('value')->values()->take(6)->all();
    }

    private function linkedConversationRows(Collection $conversations): array
    {
        return $conversations->take(6)->map(fn (Conversation $conversation): array => ['topic' => $conversation->subject ?: 'Customer conversation', 'contact' => data_get($conversation->metadata, 'name', $conversation->contact), 'channel' => Str::headline((string) $conversation->channel), 'status' => Str::headline((string) $conversation->status)])->all();
    }

    private function customerSegments(Collection $customers): array
    {
        $atRisk = $customers->filter(fn (User $customer): bool => ($customer->customerProfile?->account_status ?? $customer->status ?? 'active') !== 'active')->count();
        $vip = $customers->filter(fn (User $customer): bool => ($customer->customerProfile?->account_status ?? $customer->status ?? 'active') === 'active' && ((bool) ($customer->customerProfile?->is_vip) || $customer->orders_count >= 5))->count();
        $repeat = $customers->filter(fn (User $customer): bool => ($customer->customerProfile?->account_status ?? $customer->status ?? 'active') === 'active' && ! ((bool) ($customer->customerProfile?->is_vip) || $customer->orders_count >= 5) && $customer->orders_count > 1)->count();
        $new = max(0, $customers->count() - $atRisk - $vip - $repeat);
        return [
            ['label' => 'New Customers', 'value' => $new, 'share' => $this->share($new, $customers->count()), 'color' => '#2676cc'],
            ['label' => 'Repeat Customers', 'value' => $repeat, 'share' => $this->share($repeat, $customers->count()), 'color' => '#087943'],
            ['label' => 'VIP Customers', 'value' => $vip, 'share' => $this->share($vip, $customers->count()), 'color' => '#f08a14'],
            ['label' => 'At Risk', 'value' => $atRisk, 'share' => $this->share($atRisk, $customers->count()), 'color' => '#df3e52'],
        ];
    }

    private function customerChannels(Collection $customers): array
    {
        $meta = [
            'website' => ['label' => 'Website', 'color' => '#2676cc'],
            'mobile' => ['label' => 'Mobile App', 'color' => '#f08a14'],
            'referral' => ['label' => 'Referral', 'color' => '#087943'],
            'unattributed' => ['label' => 'Unattributed', 'color' => '#7b8790'],
        ];
        $total = $customers->count();
        $counts = array_fill_keys(array_keys($meta), 0);
        foreach ($customers as $customer) {
            $tags = (array) ($customer->customerProfile?->tags ?: []);
            $source = strtolower((string) ($tags['acquisition_channel'] ?? $tags['source'] ?? ''));
            $channel = str_contains($source, 'mobile') ? 'mobile' : (str_contains($source, 'referr') ? 'referral' : (in_array($source, ['website', 'web'], true) ? 'website' : 'unattributed'));
            $counts[$channel]++;
        }
        return collect($meta)->map(fn (array $row, string $channel): array => $row + ['value' => $counts[$channel], 'share' => $this->share($counts[$channel], $total)])->values()->all();
    }

    private function customerRevenueSegments(Collection $orders): array
    {
        $groups = $orders->groupBy(fn (Order $order): string => (string) ($order->user_id ?: 'guest:'.strtolower((string) $order->email)));
        $rows = $groups->map(function (Collection $matches): array {
            return ['segment' => $matches->count() > 1 ? 'Repeat Customers' : 'New Customers', 'amount' => (float) $matches->sum('total')];
        })->groupBy('segment')->map(fn (Collection $matches, string $segment): array => ['label' => $segment, 'amount' => (float) $matches->sum('amount')]);
        $total = (float) $orders->sum('total');
        $colors = ['Repeat Customers' => '#087943', 'New Customers' => '#2676cc'];
        return $rows->map(fn (array $row): array => ['label' => $row['label'], 'value' => $this->money($row['amount']), 'share' => $this->share($row['amount'], $total), 'color' => $colors[$row['label']] ?? '#7b8790'])->values()->all();
    }

    private function customerRegions(Collection $customers): array
    {
        return $customers->groupBy(fn (User $customer): string => (string) ($customer->customerProfile?->country ?: 'Unknown'))->map(fn (Collection $rows, string $country): array => ['label' => $country, 'customers' => $rows->count(), 'share' => $this->share($rows->count(), $customers->count()).'%'])->sortByDesc('customers')->values()->take(6)->all();
    }

    private function orderAlerts(Collection $orders, Collection $returns): array
    {
        $awaiting = $orders->filter(fn (Order $order): bool => in_array(strtolower((string) $order->status), ['pending', 'processing', 'shipped'], true))->count();
        $withoutTracking = $orders->filter(fn (Order $order): bool => blank(data_get($order->shipping_address, 'tracking_number')))->count();
        return [['label' => 'Orders awaiting fulfilment', 'value' => number_format($awaiting), 'tone' => 'orange'], ['label' => 'Returns needing review', 'value' => number_format($returns->whereIn('status', ['requested', 'pending'])->count()), 'tone' => 'red'], ['label' => 'Orders without tracking', 'value' => number_format($withoutTracking), 'tone' => 'blue']];
    }

    private function metadataNumbers(Collection $rows, string $key): Collection
    {
        return $rows->map(fn ($row): mixed => data_get($row->metadata, $key))
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): float => (float) $value)
            ->values();
    }

    private function formatDuration(Collection $rows, string $key): string
    {
        $values = $this->metadataNumbers($rows, $key);
        if ($values->isEmpty()) return '—';

        $seconds = (int) round((float) $values->avg());
        if ($seconds >= 3600) return intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m';
        if ($seconds >= 60) return intdiv($seconds, 60).'m '.($seconds % 60).'s';
        return $seconds.'s';
    }

    private function slaSummary(Collection $rows): array
    {
        $values = $rows->map(fn ($row): mixed => data_get($row->metadata, 'sla_met'))
            ->map(function (mixed $value): ?bool {
                if (is_bool($value)) return $value;
                if (in_array($value, [0, 1, '0', '1', 'false', 'true'], true)) return filter_var($value, FILTER_VALIDATE_BOOL);
                return null;
            })->filter(fn (?bool $value): bool => $value !== null)->values();
        if ($values->isEmpty()) return ['rate' => '—', 'within' => '—', 'breached' => '—'];

        $within = $values->filter()->count();
        return ['rate' => number_format(($within / $values->count()) * 100, 2).'%', 'within' => number_format($within), 'breached' => number_format($values->count() - $within)];
    }

    private function csatSummary(Collection $rows): array
    {
        $values = $this->metadataNumbers($rows, 'csat')->filter(fn (float $value): bool => $value >= 0 && $value <= 5)->values();
        if ($values->isEmpty()) return ['value' => '—', 'score' => '—'];

        $score = number_format((float) $values->avg(), 2);
        return ['value' => $score.' / 5', 'score' => $score];
    }

    private function rfmRows(Collection $customers, Collection $orders): array
    {
        $profiles = $customers->map(function (User $customer) use ($orders): array {
            $customerOrders = $orders->where('user_id', $customer->id);
            return ['customer' => $customer, 'orders' => $customerOrders->count(), 'revenue' => (float) $customerOrders->sum('total')];
        });
        $groups = [
            ['label' => 'Champions', 'color' => '#087943', 'rows' => $profiles->filter(fn (array $row): bool => ((bool) ($row['customer']->customerProfile?->is_vip) || $row['orders'] >= 5) && $row['revenue'] >= 1000)],
            ['label' => 'Loyal Customers', 'color' => '#2676cc', 'rows' => $profiles->filter(fn (array $row): bool => $row['orders'] >= 2 && $row['orders'] < 5)],
            ['label' => 'Potential Loyalists', 'color' => '#f08a14', 'rows' => $profiles->filter(fn (array $row): bool => $row['orders'] === 1)],
            ['label' => 'At Risk', 'color' => '#df3e52', 'rows' => $profiles->filter(fn (array $row): bool => ($row['customer']->customerProfile?->account_status ?? $row['customer']->status ?? 'active') !== 'active')],
        ];
        return collect($groups)->map(fn (array $group): array => ['label' => $group['label'], 'count' => $group['rows']->count(), 'color' => $group['color']])->all();
    }

}
