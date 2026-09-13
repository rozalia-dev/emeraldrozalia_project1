<?php

namespace App\Services;

use App\Models\AdminRecord;
use App\Models\Approval;
use App\Models\CommunicationTemplate;
use App\Models\Conversation;
use App\Models\FranchiseApplication;
use App\Models\FranchiseMilestone;
use App\Models\FranchiseStore;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminDashboardMetricsService
{
    private const ORDER_CATEGORY_META = [
        'online' => ['label' => 'Online Orders', 'icon' => 'shopping-bag', 'tone' => 'blue'],
        'corporate' => ['label' => 'Corporate Orders', 'icon' => 'briefcase', 'tone' => 'purple'],
        'bulk' => ['label' => 'Bulk Orders', 'icon' => 'package', 'tone' => 'orange'],
        'franchise' => ['label' => 'Franchise Orders', 'icon' => 'users', 'tone' => 'green'],
        'franchise_retail' => ['label' => 'Franchise Retail Orders', 'icon' => 'shopping-bag', 'tone' => 'red'],
        'buyer' => ['label' => 'Buyer Orders', 'icon' => 'user', 'tone' => 'gold'],
    ];

    private const PERFORMANCE_TONE_COLORS = [
        'blue' => '#2676cc',
        'purple' => '#6e4dc5',
        'orange' => '#f08312',
        'green' => '#08783d',
        'red' => '#e23e45',
        'gold' => '#d89c18',
        'teal' => '#087b72',
    ];

    public function build(Request $request): array
    {
        $period = $this->period((string) $request->query('period', 'this_month'));
        $periodOrders = fn (): Builder => Order::query()->whereBetween('created_at', [$period['start'], $period['end']]);
        $previousOrders = fn (): Builder => Order::query()->whereBetween('created_at', [$period['previous_start'], $period['previous_end']]);

        $orders = Order::query()->with('user')->latest('created_at')->limit(8)->get();
        $applications = FranchiseApplication::query();
        $stores = FranchiseStore::query()->orderBy('name')->get();
        $storeSetup = FranchiseMilestone::query()->where('type', 'store_setup');
        $activeFranchiseeQuery = (clone $applications)->whereIn('status', ['converted', 'active']);
        $activeStoreCount = $stores->whereIn('status', ['active', 'open'])->count();
        $franchiseApplicationCount = $applications->count();
        $activeFranchiseeCount = $activeFranchiseeQuery->count();

        $franchiseApplicationsInPeriod = (clone $applications)->whereBetween('created_at', [$period['start'], $period['end']])->count();
        $franchiseApplicationsPreviousPeriod = (clone $applications)->whereBetween('created_at', [$period['previous_start'], $period['previous_end']])->count();
        $activeFranchiseesInPeriod = (clone $activeFranchiseeQuery)->whereBetween('created_at', [$period['start'], $period['end']])->count();
        $activeFranchiseesPreviousPeriod = (clone $activeFranchiseeQuery)->whereBetween('created_at', [$period['previous_start'], $period['previous_end']])->count();
        $storesInPeriod = FranchiseStore::query()->whereBetween('created_at', [$period['start'], $period['end']])->count();
        $storesPreviousPeriod = FranchiseStore::query()->whereBetween('created_at', [$period['previous_start'], $period['previous_end']])->count();
        $franchiseOrders = $periodOrders()->where('order_type', 'franchise')->count();
        $franchiseOrdersPreviousPeriod = $previousOrders()->where('order_type', 'franchise')->count();

        $openConversationQuery = Conversation::query()->whereIn('status', ['new', 'open', 'pending']);
        $openConversations = $openConversationQuery->count();
        $openConversationsInPeriod = (clone $openConversationQuery)->whereBetween('created_at', [$period['start'], $period['end']])->count();
        $openConversationsPreviousPeriod = (clone $openConversationQuery)->whereBetween('created_at', [$period['previous_start'], $period['previous_end']])->count();
        $revenue = round((float) Order::query()->where('payment_status', 'paid')->sum('total'), 2);
        $periodRevenue = round((float) $periodOrders()->where('payment_status', 'paid')->sum('total'), 2);
        $previousPeriodRevenue = round((float) $previousOrders()->where('payment_status', 'paid')->sum('total'), 2);

        $dashboardKpis = [
            $this->kpi('Franchise Applications', $franchiseApplicationCount, 'users', 'green', $this->trend($franchiseApplicationsInPeriod, $franchiseApplicationsPreviousPeriod, $period['comparison']), route('admin.franchise.page', ['section' => 'franchise-applications'])),
            $this->kpi('Active Franchisees', $activeFranchiseeCount, 'users', 'purple', $this->trend($activeFranchiseesInPeriod, $activeFranchiseesPreviousPeriod, $period['comparison']), route('admin.franchise.page', ['section' => 'franchisees', 'tab' => 'active'])),
            $this->kpi('Franchise Retail Stores', $activeStoreCount, 'home', 'orange', $this->trend($storesInPeriod, $storesPreviousPeriod, $period['comparison']), route('admin.franchise.page', ['section' => 'franchise-retail-stores', 'tab' => 'active'])),
            $this->kpi('Franchise Orders', $franchiseOrders, 'shopping-bag', 'green', $this->trend($franchiseOrders, $franchiseOrdersPreviousPeriod, $period['comparison']), route('admin.order-master', ['type' => 'franchise', ...$period['query']]), $period['label']),
            $this->kpi('Total Sales', $revenue, 'credit-card', 'gold', $this->trend($periodRevenue, $previousPeriodRevenue, $period['comparison']), route('admin.sales-reports.dashboard'), 'All Orders', true),
            $this->kpi('Open Conversations', $openConversations, 'message', 'purple', $this->trend($openConversationsInPeriod, $openConversationsPreviousPeriod, $period['comparison']), route('admin.communication-center.page.communication-center')),
        ];

        $pipelineStages = $this->pipelineStages($applications);
        $conversionRate = $franchiseApplicationCount > 0
            ? round($activeFranchiseeCount / $franchiseApplicationCount * 100, 1)
            : 0.0;

        $storeMap = $this->storeMap($stores);
        $performance = $this->performance($periodOrders, $period['label']);
        $orderCategories = $this->orderCategories($periodOrders, $previousOrders, $period);
        $recentActivities = $this->recentActivities($orders, $applications, $stores);
        $communicationOverview = $this->communicationOverview($openConversations);
        $businessFlowSteps = $this->businessFlow($applications, $storeSetup, $stores, $period);

        $reports = [
            ['label' => 'Franchise Reports', 'description' => 'Applications, franchise, agreements, performance', 'icon' => 'file-text', 'href' => route('admin.franchise.page', ['section' => 'franchise-reports'])],
            ['label' => 'Franchise Retail Store Reports', 'description' => 'Store performance, sales, stock, targets', 'icon' => 'shopping-bag', 'href' => route('admin.franchise.page', ['section' => 'franchise-retail-stores'])],
            ['label' => 'Order Reports (All Categories)', 'description' => 'Online, corporate, bulk, franchise, retail, buyer orders', 'icon' => 'shopping-bag', 'href' => route('admin.reports.order', $period['query'])],
            ['label' => 'Product & Sales Reports', 'description' => 'Top products, categories, sales performance', 'icon' => 'chart', 'href' => route('admin.sales-reports.dashboard')],
            ['label' => 'Customer Reports', 'description' => 'Customers, segments, purchase behavior', 'icon' => 'users', 'href' => route('admin.reports.customer', $period['query'])],
            ['label' => 'Communication Reports', 'description' => 'Conversations, response time, team performance', 'icon' => 'message', 'href' => route('admin.reports.communication', $period['query'])],
            ['label' => 'Approval Reports', 'description' => 'Approvals, pending, rejected, approval performance', 'icon' => 'check', 'href' => route('admin.reports.approvals', $period['query'])],
            ['label' => 'Website Analytics', 'description' => 'Traffic, visitors, conversions, pages, behavior', 'icon' => 'globe', 'href' => route('admin.resource', 'website-products')],
            ['label' => 'Returns & Refund Reports', 'description' => 'Returns, refunds, reasons, product impact', 'icon' => 'refresh', 'href' => route('admin.reports.returns', $period['query'])],
            ['label' => 'Performance / KPI Reports', 'description' => 'Targets, KPI tracking, achievements', 'icon' => 'chart', 'href' => route('admin.franchise.page', ['section' => 'performance-targets'])],
            ['label' => 'Audit & Activity Reports', 'description' => 'System activities, changes, user logs', 'icon' => 'file-text', 'href' => route('admin.resource', 'audit-logs')],
        ];

        return [
            'orders' => $orders,
            'totalOrders' => Order::count(),
            'revenue' => $revenue,
            'customers' => User::query()->where('is_admin', false)->count(),
            'products' => Product::count(),
            'lowStock' => Product::query()->where('stock', '<', 5)->count(),
            'inquiries' => Inquiry::query()->where('status', 'new')->count(),
            'franchiseApplications' => $franchiseApplicationCount,
            'activeFranchisees' => $activeFranchiseeCount,
            'retailStores' => $activeStoreCount,
            'franchiseOrders' => $franchiseOrders,
            'openConversations' => $openConversations,
            'pipelineStages' => $pipelineStages,
            'conversionRate' => $conversionRate,
            'storeMapPins' => $storeMap['pins'],
            'mapRegions' => $storeMap['regions'],
            'hasGeocodedStores' => $storeMap['pins'] !== [],
            'performanceTotal' => $performance['total'],
            'performanceSegments' => $performance['segments'],
            'performanceDonutStyle' => $performance['donutStyle'],
            'performancePeriod' => $period['label'],
            'period' => $period['key'],
            'periodOptions' => $this->periodOptions(),
            'recentActivities' => $recentActivities,
            'orderCategories' => $orderCategories,
            'communicationOverview' => $communicationOverview,
            'businessFlowSteps' => $businessFlowSteps,
            'reports' => $reports,
            'dashboardKpis' => $dashboardKpis,
        ];
    }

    private function period(string $key): array
    {
        $key = in_array($key, ['this_month', 'last_month', 'this_year'], true) ? $key : 'this_month';
        $now = now();

        return match ($key) {
            'last_month' => [
                'key' => $key,
                'label' => 'Last Month',
                'comparison' => 'vs month before',
                'start' => $now->copy()->subMonthNoOverflow()->startOfMonth(),
                'end' => $now->copy()->subMonthNoOverflow()->endOfMonth(),
                'previous_start' => $now->copy()->subMonthsNoOverflow(2)->startOfMonth(),
                'previous_end' => $now->copy()->subMonthsNoOverflow(2)->endOfMonth(),
                'query' => [
                    'date_from' => $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                    'date_to' => $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
                ],
            ],
            'this_year' => [
                'key' => $key,
                'label' => 'This Year',
                'comparison' => 'vs previous year',
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
                'previous_start' => $now->copy()->subYear()->startOfYear(),
                'previous_end' => $now->copy()->subYear()->endOfYear(),
                'query' => [
                    'date_from' => $now->copy()->startOfYear()->toDateString(),
                    'date_to' => $now->copy()->endOfYear()->toDateString(),
                ],
            ],
            default => [
                'key' => 'this_month',
                'label' => 'This Month',
                'comparison' => 'vs previous month',
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
                'previous_start' => $now->copy()->subMonthNoOverflow()->startOfMonth(),
                'previous_end' => $now->copy()->subMonthNoOverflow()->endOfMonth(),
                'query' => [
                    'date_from' => $now->copy()->startOfMonth()->toDateString(),
                    'date_to' => $now->copy()->endOfMonth()->toDateString(),
                ],
            ],
        };
    }

    private function periodOptions(): array
    {
        return ['this_month' => 'This Month', 'last_month' => 'Last Month', 'this_year' => 'This Year'];
    }

    private function kpi(string $label, int|float $value, string $icon, string $tone, array $trend, string $href, ?string $context = null, bool $currency = false): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'icon' => $icon,
            'tone' => $tone,
            'change' => $trend['change'],
            'comparison' => $trend['comparison'],
            'trend' => $trend['trend'],
            'href' => $href,
            'context' => $context,
            'currency' => $currency,
        ];
    }

    private function trend(int|float $current, int|float $previous, string $comparison = 'vs previous period'): array
    {
        if ((float) $previous === 0.0) {
            return [
                'change' => (float) $current === 0.0 ? '0.0%' : 'New',
                'trend' => (float) $current === 0.0 ? 'flat' : 'up',
                'comparison' => $comparison,
            ];
        }

        $percentage = round((((float) $current - (float) $previous) / (float) $previous) * 100, 1);

        return [
            'change' => ($percentage > 0 ? '+' : '').number_format($percentage, 1).'%',
            'trend' => $percentage > 0 ? 'up' : ($percentage < 0 ? 'down' : 'flat'),
            'comparison' => $comparison,
        ];
    }

    private function pipelineStages(Builder $applications): array
    {
        $definitions = [
            ['label' => 'New Applications', 'statuses' => ['new'], 'tone' => 'blue', 'href' => ['section' => 'franchise-applications', 'tab' => 'new']],
            ['label' => 'Initial Screening', 'statuses' => ['pending', 'under-review', 'under_review', 'review'], 'tone' => 'purple', 'href' => ['section' => 'franchise-applications', 'tab' => 'under-review']],
            ['label' => 'Meeting Scheduled', 'statuses' => ['meeting-scheduled', 'meeting_scheduled'], 'tone' => 'violet', 'href' => ['section' => 'franchise-applications', 'status' => 'meeting-scheduled']],
            ['label' => 'Due Diligence', 'statuses' => ['due-diligence', 'due_diligence'], 'tone' => 'orange', 'href' => ['section' => 'franchise-applications', 'status' => 'due-diligence']],
            ['label' => 'Agreement Sent', 'statuses' => ['agreement-sent', 'agreement_sent'], 'tone' => 'amber', 'href' => ['section' => 'franchise-agreements', 'status' => 'under-review']],
            ['label' => 'Approved', 'statuses' => ['approved'], 'tone' => 'green', 'href' => ['section' => 'franchise-applications', 'tab' => 'approved']],
        ];
        $counts = collect($definitions)->mapWithKeys(fn (array $definition): array => [$definition['label'] => (clone $applications)->whereIn('status', $definition['statuses'])->count()]);
        $max = max(0, (int) $counts->max());

        return collect($definitions)->map(function (array $definition) use ($counts, $max): array {
            $value = (int) $counts->get($definition['label'], 0);

            return [
                'label' => $definition['label'],
                'value' => $value,
                'tone' => $definition['tone'],
                'width' => $max > 0 ? max(22, round($value / $max * 96, 1)) : 0,
                'href' => route('admin.franchise.page', $definition['href']),
            ];
        })->all();
    }

    private function storeMap(Collection $stores): array
    {
        $pins = $stores->map(function (FranchiseStore $store): ?array {
            $address = is_array($store->address) ? $store->address : [];
            $latitude = data_get($address, 'latitude', data_get($address, 'coordinates.latitude'));
            $longitude = data_get($address, 'longitude', data_get($address, 'coordinates.longitude', data_get($address, 'lng')));

            if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                return null;
            }

            $latitude = max(-90, min(90, (float) $latitude));
            $longitude = max(-180, min(180, (float) $longitude));

            return [
                'name' => $store->name,
                'x' => round(max(4, min(96, (($longitude + 180) / 360) * 100)), 2),
                'y' => round(max(5, min(95, ((90 - $latitude) / 180) * 100)), 2),
                'href' => route('admin.franchise.page', ['section' => 'franchise-retail-stores', 'q' => $store->code]),
            ];
        })->filter()->values()->all();

        $regions = $stores->map(function (FranchiseStore $store): ?string {
            $address = is_array($store->address) ? $store->address : [];
            $region = trim((string) data_get($address, 'region', data_get($address, 'country', $store->territory)));

            return $region !== '' ? $region : null;
        })->filter()->countBy()->sortDesc()->take(5)->map(function (int $value, string $label) use ($stores): array {
            $store = $stores->first(function (FranchiseStore $candidate) use ($label): bool {
                $address = is_array($candidate->address) ? $candidate->address : [];
                $region = trim((string) data_get($address, 'region', data_get($address, 'country', $candidate->territory)));

                return $region === $label;
            });

            return [
                'label' => $label,
                'value' => $value,
                'href' => $store ? route('admin.franchise.page', ['section' => 'franchise-retail-stores', 'q' => $store->territory]) : route('admin.franchise.page', ['section' => 'franchise-retail-stores']),
            ];
        })->values()->all();

        return ['pins' => $pins, 'regions' => $regions];
    }

    private function performance(callable $periodOrders, string $label): array
    {
        $rows = $periodOrders()
            ->where('payment_status', 'paid')
            ->select('order_type')
            ->selectRaw('SUM(total) as aggregate')
            ->groupBy('order_type')
            ->get();
        $meta = self::ORDER_CATEGORY_META;
        $total = round((float) $rows->sum(fn (Order $order): float => (float) $order->aggregate), 2);
        $segments = $rows->map(function (Order $order) use ($meta, $total): array {
            $type = (string) $order->order_type;
            $segment = $meta[$type] ?? ['label' => Str::headline($type), 'tone' => 'blue'];
            $value = round((float) $order->aggregate, 2);

            return [
                'label' => $segment['label'],
                'value' => $value,
                'share' => $total > 0 ? number_format($value / $total * 100, 1).'%' : '0.0%',
                'tone' => $segment['tone'],
            ];
        })->sortByDesc('value')->values()->all();

        $offset = 0.0;
        $stops = [];
        foreach ($segments as $segment) {
            $share = $total > 0 ? round($segment['value'] / $total * 100, 1) : 0.0;
            $next = min(100, $offset + $share);
            $color = self::PERFORMANCE_TONE_COLORS[$segment['tone']] ?? self::PERFORMANCE_TONE_COLORS['blue'];
            $stops[] = $color.' '.$offset.'% '.$next.'%';
            $offset = $next;
        }

        return [
            'total' => $total,
            'segments' => $segments,
            'donutStyle' => 'background: conic-gradient('.($stops ? implode(', ', $stops) : '#e5ece7 0% 100%').');',
            'label' => $label,
        ];
    }

    private function orderCategories(callable $periodOrders, callable $previousOrders, array $period): array
    {
        $counts = $periodOrders()->select('order_type')->selectRaw('COUNT(*) as aggregate')->groupBy('order_type')->pluck('aggregate', 'order_type');
        $previousCounts = $previousOrders()->select('order_type')->selectRaw('COUNT(*) as aggregate')->groupBy('order_type')->pluck('aggregate', 'order_type');

        return collect(self::ORDER_CATEGORY_META)->map(function (array $meta, string $type) use ($counts, $previousCounts, $period): array {
            $current = (int) $counts->get($type, 0);
            $previous = (int) $previousCounts->get($type, 0);
            $trend = $this->trend($current, $previous, $period['comparison']);

            return [
                'type' => $type,
                'label' => $meta['label'],
                'icon' => $meta['icon'],
                'tone' => $meta['tone'],
                'count' => $current,
                'change' => $trend['change'],
                'trend' => $trend['trend'],
                'comparison' => $period['comparison'],
                'href' => route('admin.order-master', ['type' => $type, ...$period['query']]),
            ];
        })->values()->all();
    }

    private function recentActivities(Collection $orders, Builder $applications, Collection $stores): array
    {
        $activities = collect();

        foreach ($orders as $order) {
            if (! $order->created_at) {
                continue;
            }

            $activities->push([
                'sort' => $order->created_at,
                'icon' => 'shopping-bag',
                'tone' => 'blue',
                'title' => 'Order '.$order->number.' placed',
                'meta' => $order->created_at->diffForHumans(),
                'href' => route('admin.order-master.show', ['type' => $order->order_type, 'order' => $order]),
            ]);
        }

        foreach ((clone $applications)->latest('created_at')->limit(6)->get() as $application) {
            if (! $application->created_at) {
                continue;
            }

            $activities->push([
                'sort' => $application->created_at,
                'icon' => 'file-text',
                'tone' => 'green',
                'title' => 'Franchise application received: '.$application->applicant_name,
                'meta' => $application->created_at->diffForHumans(),
                'href' => route('admin.franchise.page', ['section' => 'franchise-applications', 'q' => $application->applicant_name]),
            ]);
        }

        foreach ($stores->sortByDesc('created_at')->take(6) as $store) {
            if (! $store->created_at) {
                continue;
            }

            $activities->push([
                'sort' => $store->created_at,
                'icon' => 'home',
                'tone' => 'purple',
                'title' => 'Franchise store registered: '.$store->name,
                'meta' => $store->created_at->diffForHumans(),
                'href' => route('admin.franchise.page', ['section' => 'franchise-retail-stores', 'q' => $store->code]),
            ]);
        }

        return $activities->sortByDesc('sort')->take(6)->map(fn (array $activity): array => collect($activity)->except('sort')->all())->values()->all();
    }

    private function communicationOverview(int $openConversations): array
    {
        $conversationCount = Conversation::count();
        $approvalCount = Approval::query()->whereIn('status', ['pending', 'in-progress', 'escalated'])->count();
        $followUpCount = Conversation::query()->whereNotNull('follow_up_at')->where('status', '!=', 'closed')->count();
        $alertCount = AdminRecord::query()->where('module', 'alerts-notifications')->count();

        return [
            ['label' => 'Inbox (All)', 'count' => $conversationCount, 'icon' => 'mail', 'href' => route('admin.communication-center.page.inbox')],
            ['label' => 'Chat 24/7', 'count' => Conversation::where('channel', 'chat')->count(), 'icon' => 'message', 'href' => route('admin.communication-center.page.chat-24-7')],
            ['label' => 'WhatsApp', 'count' => Conversation::where('channel', 'whatsapp')->count(), 'icon' => 'message', 'href' => route('admin.communication-center.page.whatsapp')],
            ['label' => 'Email', 'count' => Conversation::where('channel', 'email')->count(), 'icon' => 'mail', 'href' => route('admin.communication-center.page.email')],
            ['label' => 'Email Templates', 'count' => CommunicationTemplate::count(), 'icon' => 'file-text', 'href' => route('admin.communication-center.page.email-templates')],
            ['label' => 'Approval Pending', 'count' => $approvalCount, 'icon' => 'check', 'tone' => 'red', 'href' => route('admin.communication-center.page.approval-center')],
            ['label' => 'Action / Follow-ups', 'count' => $followUpCount, 'icon' => 'clock', 'tone' => 'orange', 'href' => route('admin.communication-center.page.action-follow-ups')],
            ['label' => 'Alerts & Notifications', 'count' => $alertCount, 'icon' => 'bell', 'tone' => 'orange', 'href' => route('admin.communication-center.page.alerts-notifications')],
            ['label' => 'Open Conversations', 'count' => $openConversations, 'icon' => 'message', 'tone' => 'purple', 'href' => route('admin.communication-center.page.communication-center')],
            ['label' => 'Communication History', 'count' => $conversationCount, 'icon' => 'file-text', 'tone' => 'green', 'action' => 'View Log', 'href' => route('admin.communication-center.page.communication-history')],
        ];
    }

    private function businessFlow(Builder $applications, Builder $storeSetup, Collection $stores, array $period): array
    {
        $franchiseOrders = Order::query()->whereIn('order_type', ['franchise', 'franchise_retail'])->whereNotIn('status', ['cancelled', 'refunded'])->count();
        $activeStores = $stores->whereIn('status', ['active', 'open'])->count();
        $activeFranchisees = (clone $applications)->whereIn('status', ['converted', 'active'])->count();

        return [
            ['title' => 'Application Received', 'detail' => 'New application submitted', 'value' => $applications->count(), 'icon' => 'file-text', 'href' => route('admin.franchise.page', ['section' => 'franchise-applications'])],
            ['title' => 'Initial Screening', 'detail' => 'Application reviewed', 'value' => (clone $applications)->whereIn('status', ['pending', 'under-review', 'under_review', 'review'])->count(), 'icon' => 'users', 'href' => route('admin.franchise.page', ['section' => 'franchise-applications', 'tab' => 'under-review'])],
            ['title' => 'Meeting Scheduled', 'detail' => 'Meeting planned', 'value' => (clone $applications)->whereIn('status', ['meeting-scheduled', 'meeting_scheduled'])->count(), 'icon' => 'calendar', 'href' => route('admin.franchise.page', ['section' => 'franchise-applications', 'status' => 'meeting-scheduled'])],
            ['title' => 'Due Diligence', 'detail' => 'Verification & evaluation', 'value' => (clone $applications)->whereIn('status', ['due-diligence', 'due_diligence'])->count(), 'icon' => 'search', 'href' => route('admin.franchise.page', ['section' => 'franchise-applications', 'status' => 'due-diligence'])],
            ['title' => 'Agreement Sent', 'detail' => 'Agreement shared', 'value' => AdminRecord::where('module', 'franchise-agreements')->whereIn('status', ['sent', 'agreement-sent', 'under-review'])->count(), 'icon' => 'file-text', 'href' => route('admin.franchise.page', ['section' => 'franchise-agreements', 'status' => 'under-review'])],
            ['title' => 'Agreement Signed', 'detail' => 'Agreement signed', 'value' => AdminRecord::where('module', 'franchise-agreements')->whereIn('status', ['signed', 'completed', 'active'])->count(), 'icon' => 'check', 'href' => route('admin.franchise.page', ['section' => 'franchise-agreements', 'status' => 'completed'])],
            ['title' => 'Approved', 'detail' => 'Franchise approved', 'value' => (clone $applications)->where('status', 'approved')->count(), 'icon' => 'check', 'href' => route('admin.franchise.page', ['section' => 'franchise-applications', 'tab' => 'approved'])],
            ['title' => 'Store Setup', 'detail' => 'Store setup in progress', 'value' => (clone $storeSetup)->whereNotIn('status', ['complete', 'completed'])->count(), 'icon' => 'briefcase', 'href' => route('admin.franchise.store-setup', ['status' => 'pending'])],
            ['title' => 'Opening Order', 'detail' => 'Initial order placed', 'value' => $franchiseOrders, 'icon' => 'shopping-bag', 'href' => route('admin.order-master', ['type' => 'franchise', ...$period['query']])],
            ['title' => 'Store Opened', 'detail' => 'Store is live', 'value' => $activeStores, 'icon' => 'home', 'href' => route('admin.franchise.page', ['section' => 'franchise-retail-stores', 'tab' => 'active'])],
            ['title' => 'Active & Growing', 'detail' => 'Store active & growing', 'value' => $activeFranchisees, 'icon' => 'chart', 'href' => route('admin.franchise.page', ['section' => 'franchisees', 'tab' => 'active'])],
        ];
    }
}
