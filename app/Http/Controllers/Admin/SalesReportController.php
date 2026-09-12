<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminRecord;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalesReportController extends Controller
{
    private const ORDER_TYPES = [
        'online' => ['label' => 'Online Orders', 'color' => '#2676cc'],
        'corporate' => ['label' => 'Corporate Orders', 'color' => '#f08a14'],
        'bulk' => ['label' => 'Bulk Orders', 'color' => '#f2b20d'],
        'franchise' => ['label' => 'Franchise Orders', 'color' => '#a33c98'],
        'franchise_retail' => ['label' => 'Franchise Retail Orders', 'color' => '#087b72'],
        'buyer' => ['label' => 'Buyer Orders', 'color' => '#1b7065'],
    ];

    private const TABS = [
        'overview' => 'Overview', 'orders-by-category' => 'Orders by Category', 'sales-by-products' => 'Sales by Products',
        'sales-by-customers' => 'Sales by Customers', 'sales-by-franchises' => 'Sales by Franchises',
        'sales-by-retail-stores' => 'Sales by Retail Stores', 'channels' => 'Channels', 'payments' => 'Payments',
        'discounts' => 'Discounts', 'returns-refunds' => 'Returns & Refunds', 'exports' => 'Exports',
    ];

    private const FULFILLMENT_STATUSES = ['pending', 'on_hold', 'picking', 'packed', 'ready_to_ship', 'shipped', 'delivered', 'closed'];

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $orders = $this->orders($filters);
        $payload = $this->analytics($orders, $filters);

        return view('admin.sales-reports.index', [
            'filters' => $filters, 'tabs' => self::TABS,
            'activeTab' => (string) $request->input('tab', $request->query('tab', 'overview')),
            ...$payload,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $orders = $this->orders($filters);
        $format = strtolower((string) $request->query('format', $request->input('format', 'csv')));
        $excel = $format === 'excel';
        $filename = 'emerald-rozalia-sales-report-'.now()->format('Ymd-His').'.'.($excel ? 'xls' : 'csv');

        $rows = $orders->map(fn (Order $order): array => [
            $order->number, self::ORDER_TYPES[$order->order_type]['label'] ?? Str::headline((string) $order->order_type),
            $order->status, $order->payment_status, $order->currency ?: 'EUR', number_format((float) $order->total, 2, '.', ''), optional($order->created_at)->toDateTimeString(),
        ]);

        return response()->streamDownload(static function () use ($rows, $excel): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order Number', 'Order Category', 'Status', 'Payment Status', 'Currency', 'Sales (Net)', 'Created At'], $excel ? "\t" : ',');
            foreach ($rows as $row) fputcsv($out, $row, $excel ? "\t" : ',');
            fclose($out);
        }, $filename, ['Content-Type' => $excel ? 'application/vnd.ms-excel' : 'text/csv']);
    }

    public function saveView(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $filters = $this->filters($request);
        $record = AdminRecord::create([
            'module' => 'sales-report-views', 'reference' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
            'title' => $data['name'], 'status' => 'active', 'record_date' => now(), 'user_id' => auth()->id(),
            'data' => ['filters' => Arr::only($filters, ['category', 'channel', 'franchise', 'store', 'country', 'customer_group', 'payment', 'fulfillment', 'currency', 'group_by', 'breakdown', 'include_tax', 'from_value', 'to_value', 'compare'])],
        ]);
        AuditTrail::record('sales-reports.view.created', $record, null, $record->toArray());

        return redirect()->route('admin.sales-reports.dashboard', $this->queryParams($filters))->with('success', 'Sales report view saved with an auditable UUID.');
    }

    private function filters(Request $request): array
    {
        $read = static fn (string $key, mixed $default = null): mixed => $request->input($key, $request->query($key, $default));
        $from = Carbon::parse($read('from', '2025-04-01'))->startOfDay();
        $to = Carbon::parse($read('to', '2025-05-01'))->endOfDay();
        if ($from->gt($to)) [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];

        return [
            'q' => trim((string) $read('q', '')), 'category' => $this->allowed((string) $read('category', 'all'), array_merge(['all'], array_keys(self::ORDER_TYPES)), 'all'),
            'channel' => (string) $read('channel', 'all'), 'franchise' => (string) $read('franchise', 'all'), 'store' => (string) $read('store', 'all'),
            'country' => (string) $read('country', 'all'), 'customer_group' => (string) $read('customer_group', 'all'), 'payment' => (string) $read('payment', 'all'),
            'fulfillment' => $this->allowed((string) $read('fulfillment', 'all'), array_merge(['all'], self::FULFILLMENT_STATUSES), 'all'),
            'currency' => (string) $read('currency', 'all'), 'group_by' => $this->allowed((string) $read('group_by', 'none'), ['none', 'category', 'channel', 'customer_group', 'country'], 'none'), 'breakdown' => $this->allowed((string) $read('breakdown', 'day'), ['day', 'week', 'month'], 'day'),
            'include_tax' => $request->boolean('include_tax'), 'compare' => $request->boolean('compare'),
            'from' => $from, 'to' => $to, 'from_value' => $from->toDateString(), 'to_value' => $to->toDateString(),
            'from_label' => $from->format('d M Y'), 'to_label' => $to->format('d M Y'),
        ];
    }

    private function orders(array $filters): Collection
    {
        $orders = Order::query()->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['category'] !== 'all', fn ($query) => $query->where('order_type', $filters['category']))
            ->when($filters['payment'] !== 'all', fn ($query) => $query->where('payment_method', $filters['payment']))
            ->when($filters['fulfillment'] !== 'all', fn ($query) => $query->where('fulfillment_status', $filters['fulfillment']))
            ->when($filters['currency'] !== 'all', fn ($query) => $query->where('currency', $filters['currency']))
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('number', 'like', '%'.$filters['q'].'%')->orWhere('email', 'like', '%'.$filters['q'].'%')))
            ->latest()->get();

        return $orders->filter(function (Order $order) use ($filters): bool {
            $address = (array) ($order->shipping_address ?: []);
            $channel = $this->orderChannel($order); $country = (string) ($address['country_name'] ?? $address['country'] ?? 'Unknown');
            $franchise = (string) ($address['franchise'] ?? $this->orderFranchise($order)); $store = (string) ($address['store'] ?? 'Unknown'); $group = $this->customerGroup($order);
            return ($filters['channel'] === 'all' || $channel === $filters['channel']) && ($filters['country'] === 'all' || $country === $filters['country'])
                && ($filters['franchise'] === 'all' || $franchise === $filters['franchise']) && ($filters['store'] === 'all' || $store === $filters['store'])
                && ($filters['customer_group'] === 'all' || $group === $filters['customer_group']);
        });
    }

    private function analytics(Collection $orders, array $filters): array
    {
        $metrics = $this->actualMetrics($orders, $filters);
        $trend = $this->trend($orders, $filters);
        return [
            'metrics' => $metrics,
            'categoryRows' => $this->categoryRows($orders),
            'channelRows' => $this->channelRows($orders),
            'productRows' => $this->productRows($orders),
            'customerRows' => $this->customerRows($orders),
            'franchiseRows' => $this->franchiseRows($orders),
            'paymentRows' => $this->paymentRows($orders),
            'countryRows' => $this->countryRows($orders),
            'summaryRows' => $this->summaryRows($metrics, $orders),
            'trend' => $trend, 'filters' => $filters, 'options' => $this->filterOptions($orders),
            'recentReports' => $this->recentReports(), 'savedViews' => AdminRecord::query()->where('module', 'sales-report-views')->where('status', 'active')->latest('id')->limit(5)->get(),
            'isEmpty' => $orders->isEmpty(), 'dataNote' => $orders->isEmpty() ? 'No orders found for the selected filters.' : 'Live PostgreSQL data · '.$orders->count().' orders in this report window',
            'insights' => $this->impactMetrics($orders, $filters),
        ];
    }

    private function actualMetrics(Collection $orders, array $filters): array
    {
        $sales = round((float) $orders->sum(fn (Order $order): float => (float) $order->total), 2);
        $discounts = round((float) $orders->sum(fn (Order $order): float => (float) ($order->discount ?: 0)), 2);
        $returns = ReturnRequest::query()->whereIn('order_id', $orders->pluck('id'))->whereBetween('created_at', [$filters['from'], $filters['to']])->with('order')->get();
        $refunds = round((float) $returns->sum(fn (ReturnRequest $return): float => (float) ($return->order?->total ?: 0)), 2);
        $customers = $orders->map(fn (Order $order): string => (string) ($order->user_id ?: strtolower((string) $order->email)))->filter()->unique()->count();
        $count = $orders->count();
        return [
            ['label' => 'Total Sales (Net)', 'value' => $sales, 'kind' => 'money', 'change' => '—', 'icon' => 'chart', 'tone' => 'green', 'caption' => 'No comparison loaded'],
            ['label' => 'Total Orders', 'value' => $count, 'kind' => 'number', 'change' => '—', 'icon' => 'shopping-bag', 'tone' => 'blue', 'caption' => 'No comparison loaded'],
            ['label' => 'Avg. Order Value', 'value' => $count ? round($sales / $count, 2) : 0, 'kind' => 'money', 'change' => '—', 'icon' => 'package', 'tone' => 'orange', 'caption' => 'No comparison loaded'],
            ['label' => 'Total Customers', 'value' => $customers, 'kind' => 'number', 'change' => '—', 'icon' => 'users', 'tone' => 'purple', 'caption' => 'No comparison loaded'],
            ['label' => 'Return & Refunds', 'value' => $refunds, 'kind' => 'money', 'change' => '—', 'icon' => 'refresh', 'tone' => 'teal', 'caption' => 'No comparison loaded'],
            ['label' => 'Discounts Given', 'value' => $discounts, 'kind' => 'money', 'change' => '—', 'icon' => 'percent', 'tone' => 'red', 'caption' => 'No comparison loaded'],
            ['label' => 'Net Revenue', 'value' => round($sales - $refunds, 2), 'kind' => 'money', 'change' => '—', 'icon' => 'credit-card', 'tone' => 'green', 'caption' => 'No comparison loaded'],
        ];
    }

    private function impactMetrics(Collection $orders, array $filters): array
    {
        $sales = round((float) $orders->sum(fn (Order $order): float => (float) $order->total), 2);
        $discounted = $orders->filter(fn (Order $order): bool => (float) ($order->discount ?: 0) > 0);
        $discounts = round((float) $discounted->sum(fn (Order $order): float => (float) ($order->discount ?: 0)), 2);
        $returns = ReturnRequest::query()->whereIn('order_id', $orders->pluck('id'))->whereBetween('created_at', [$filters['from'], $filters['to']])->with('order')->get();
        $refunds = round((float) $returns->sum(fn (ReturnRequest $return): float => (float) ($return->order?->total ?: 0)), 2);
        $count = $orders->count();

        return [
            'discounts' => $discounts,
            'discountedOrders' => $discounted->count(),
            'discountRate' => $count ? number_format(($discounted->count() / $count) * 100, 2).'%' : '—',
            'averageDiscount' => $discounted->count() ? $this->money($discounts / $discounted->count()) : '—',
            'returnRate' => $count ? number_format(($returns->count() / $count) * 100, 2).'%' : '—',
            'refundedOrders' => $returns->where('status', 'refunded')->count(),
            'refundImpact' => $sales ? number_format(($refunds / $sales) * 100, 2).'%' : '—',
        ];
    }

    private function categoryRows(Collection $orders): array
    {
        $total = max(0.01, (float) $orders->sum(fn (Order $order): float => (float) $order->total));
        return collect(self::ORDER_TYPES)->map(function (array $meta, string $type) use ($orders, $total): array {
            $matches = $orders->where('order_type', $type); $amount = round((float) $matches->sum(fn (Order $order): float => (float) $order->total), 2);
            return ['label' => $meta['label'], 'amount' => $amount, 'orders' => $matches->count(), 'share' => round(($amount / $total) * 100, 2), 'color' => $meta['color']];
        })->values()->all();
    }

    private function channelRows(Collection $orders): array
    {
        $total = max(0.01, (float) $orders->sum(fn (Order $order): float => (float) $order->total));
        $rows = $orders->groupBy(fn (Order $order): string => $this->orderChannel($order))->map(function (Collection $matches, string $channel) use ($total): array {
            $amount = round((float) $matches->sum(fn (Order $order): float => (float) $order->total), 2);
            return ['label' => $channel, 'amount' => $amount, 'orders' => $matches->count(), 'share' => round(($amount / $total) * 100, 2), 'color' => $this->channelColor($channel)];
        })->sortByDesc('amount')->values()->all();
        return $rows;
    }

    private function productRows(Collection $orders): array
    {
        $items = $orders->isEmpty() ? collect() : OrderItem::query()->whereIn('order_id', $orders->pluck('id')->all())->get();
        if ($items->isEmpty()) return [];
        $total = max(0.01, (float) $items->sum(fn (OrderItem $item): float => (float) $item->total));
        return $items->groupBy('name')->map(function (Collection $matches, string $name) use ($total): array {
            $amount = round((float) $matches->sum(fn (OrderItem $item): float => (float) $item->total), 2);
            return ['label' => $name, 'amount' => $amount, 'share' => round(($amount / $total) * 100, 2), 'quantity' => (int) $matches->sum('quantity')];
        })->sortByDesc('amount')->take(5)->values()->all();
    }

    private function customerRows(Collection $orders): array
    {
        $total = max(0.01, (float) $orders->sum(fn (Order $order): float => (float) $order->total));
        return $orders->groupBy(fn (Order $order): string => $this->customerGroup($order))->map(function (Collection $matches, string $group) use ($total): array {
            $amount = round((float) $matches->sum(fn (Order $order): float => (float) $order->total), 2);
            return ['label' => $group, 'amount' => $amount, 'share' => round(($amount / $total) * 100, 2), 'customers' => $matches->map(fn (Order $order): string => (string) ($order->user_id ?: $order->email))->unique()->count()];
        })->sortByDesc('amount')->values()->all();
    }

    private function franchiseRows(Collection $orders): array
    {
        $franchiseOrders = $orders->filter(fn (Order $order): bool => in_array($order->order_type, ['franchise', 'franchise_retail'], true));
        if ($franchiseOrders->isEmpty()) return [];
        return $franchiseOrders->groupBy(fn (Order $order): string => $this->orderFranchise($order))->map(fn (Collection $matches, string $name): array => ['label' => $name, 'amount' => round((float) $matches->sum(fn (Order $order): float => (float) $order->total), 2), 'orders' => $matches->count(), 'stores' => $matches->map(fn (Order $order): string => (string) data_get($order->shipping_address, 'store', $name))->unique()->count()])->sortByDesc('amount')->take(5)->values()->all();
    }

    private function paymentRows(Collection $orders): array
    {
        $total = max(0.01, (float) $orders->sum(fn (Order $order): float => (float) $order->total));
        return $orders->groupBy(fn (Order $order): string => Str::headline((string) ($order->payment_method ?: 'Other')))->map(function (Collection $matches, string $name) use ($total): array {
            $amount = round((float) $matches->sum(fn (Order $order): float => (float) $order->total), 2);
            return ['label' => $name, 'amount' => $amount, 'share' => round(($amount / $total) * 100, 2), 'color' => $this->paymentColor($name)];
        })->sortByDesc('amount')->values()->all();
    }

    private function countryRows(Collection $orders): array
    {
        $total = max(0.01, (float) $orders->sum(fn (Order $order): float => (float) $order->total));
        return $orders->groupBy(fn (Order $order): string => (string) data_get($order->shipping_address, 'country_name', data_get($order->shipping_address, 'country', 'Unknown')))->map(function (Collection $matches, string $country) use ($total): array {
            $amount = round((float) $matches->sum(fn (Order $order): float => (float) $order->total), 2);
            return ['label' => $country, 'amount' => $amount, 'share' => round(($amount / $total) * 100, 2)];
        })->sortByDesc('amount')->take(6)->values()->all();
    }

    private function summaryRows(array $metrics, Collection $orders): array
    {
        $gross = round((float) $orders->sum(fn (Order $order): float => (float) $order->subtotal + (float) $order->shipping + (float) ($order->discount ?: 0)), 2);
        $discounts = round((float) $orders->sum(fn (Order $order): float => (float) ($order->discount ?: 0)), 2); $refunds = (float) ($metrics[4]['value'] ?? 0); $net = (float) ($metrics[0]['value'] ?? 0);
        $shipping = round((float) $orders->sum(fn (Order $order): float => (float) ($order->shipping ?: 0)), 2);
        return [['label' => 'Gross Sales (Recorded)', 'value' => $gross], ['label' => 'Total Discounts', 'value' => $discounts], ['label' => 'Net Sales (Before Returns)', 'value' => $net], ['label' => 'Returns & Refunds', 'value' => $refunds], ['label' => 'Net Sales (After Returns)', 'value' => max(0, $net - $refunds)], ['label' => 'Taxes Collected (not recorded)', 'value' => 0], ['label' => 'Shipping Revenue', 'value' => $shipping], ['label' => 'Net Revenue', 'value' => max(0, $net - $refunds)]];
    }

    private function trend(Collection $orders, array $filters): array
    {
        $days = max(1, $filters['from']->diffInDays($filters['to'])); $current = []; $labels = [];
        $previousFrom = $filters['from']->copy()->subDays($days + 1); $previousTo = $filters['from']->copy()->subSecond();
        $previousFilters = $filters; $previousFilters['from'] = $previousFrom; $previousFilters['to'] = $previousTo;
        $previousOrders = $this->orders($previousFilters);
        $previous = [];
        foreach (range(0, 6) as $bucket) {
            $start = $filters['from']->copy()->addDays((int) floor($days * $bucket / 7)); $end = $bucket === 6 ? $filters['to'] : $filters['from']->copy()->addDays((int) floor($days * ($bucket + 1) / 7));
            $previousStart = $previousFrom->copy()->addDays((int) floor($days * $bucket / 7)); $previousEnd = $bucket === 6 ? $previousTo : $previousFrom->copy()->addDays((int) floor($days * ($bucket + 1) / 7));
            $current[] = round((float) $orders->filter(fn (Order $order): bool => $order->created_at?->between($start, $end))->sum(fn (Order $order): float => (float) $order->total));
            $previous[] = round((float) $previousOrders->filter(fn (Order $order): bool => $order->created_at?->between($previousStart, $previousEnd))->sum(fn (Order $order): float => (float) $order->total)); $labels[] = $start->format('j M');
        }
        $max = max(1, ...$current, ...$previous); $currentTotal = array_sum($current); $previousTotal = array_sum($previous);
        return ['labels' => $labels, 'current' => array_map(fn (float|int $value): float => round(($value / $max) * 84, 2), $current), 'previous' => array_map(fn (float|int $value): float => round(($value / $max) * 84, 2), $previous), 'currentTotal' => $currentTotal, 'previousTotal' => $previousTotal, 'change' => $previousTotal > 0 ? number_format((($currentTotal - $previousTotal) / $previousTotal) * 100, 2).'%' : '—'];
    }

    private function filterOptions(Collection $orders): array
    {
        $countries = $orders->map(fn (Order $order): string => (string) data_get($order->shipping_address, 'country_name', data_get($order->shipping_address, 'country', 'Unknown')))->filter()->unique()->values()->all();
        $payments = $orders->map(fn (Order $order): string => (string) ($order->payment_method ?: 'Other'))->filter()->unique()->values()->all();
        return ['categories' => collect(self::ORDER_TYPES)->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label']])->all(), 'channels' => $orders->map(fn (Order $order): string => $this->orderChannel($order))->unique()->values()->all(), 'franchises' => $orders->map(fn (Order $order): string => $this->orderFranchise($order))->unique()->values()->all(), 'stores' => $orders->map(fn (Order $order): string => (string) data_get($order->shipping_address, 'store', 'Unknown'))->unique()->values()->all(), 'countries' => $countries, 'customer_groups' => $orders->map(fn (Order $order): string => $this->customerGroup($order))->unique()->values()->all(), 'payments' => $payments, 'fulfillment' => $orders->pluck('fulfillment_status')->filter()->unique()->values()->all(), 'currencies' => $orders->pluck('currency')->filter()->unique()->values()->all()];
    }

    private function recentReports(): array
    {
        return AdminRecord::query()->whereIn('module', ['report-runs', 'sales-report-views'])->where('status', '!=', 'deleted')->latest('record_date')->latest('id')->limit(5)->get()->map(fn (AdminRecord $record): array => [
            'name' => $record->title,
            'when' => optional($record->record_date)->diffForHumans() ?: 'Date unavailable',
            'icon' => str_contains((string) $record->module, 'sales') ? 'chart' : 'file-text',
        ])->all();
    }

    private function orderChannel(Order $order): string
    {
        $channel = strtolower((string) data_get($order->shipping_address, 'channel', ''));
        if (str_contains($channel, 'mobile')) return 'Mobile App';
        if (str_contains($channel, 'portal')) return 'Franchise Portal';
        if (str_contains($channel, 'market')) return 'Marketplace';
        if (in_array($channel, ['website', 'web'], true)) return 'Website';
        if ($channel !== '') return 'Other Integrations';
        return 'Unattributed';
    }

    private function orderFranchise(Order $order): string
    {
        $franchise = (string) data_get($order->shipping_address, 'franchise', ''); if ($franchise !== '') return $franchise;
        return 'Unassigned';
    }

    private function money(float $amount): string
    {
        return '€'.number_format($amount, 2);
    }

    private function customerGroup(Order $order): string
    {
        return match ($order->order_type) {'corporate' => 'Corporate Customers', 'bulk' => 'Wholesale / Bulk Buyers', 'franchise', 'franchise_retail' => 'Franchise Customers', default => 'Retail Customers'};
    }

    private function channelColor(string $channel): string
    {
        return match ($channel) {'Website' => '#2676cc', 'Mobile App' => '#f08a14', 'Franchise Portal' => '#a33c98', 'Marketplace' => '#087b72', 'Unattributed' => '#7b8790', default => '#a21f58'};
    }

    private function paymentColor(string $payment): string
    {
        return match ($payment) {'Credit / Debit Card' => '#2676cc', 'Bank Transfer' => '#f08a14', 'PayPal' => '#a33c98', 'Apple Pay' => '#087b72', default => '#6c7b83'};
    }

    private function allowed(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function queryParams(array $filters): array
    {
        return [...Arr::only($filters, ['category', 'channel', 'franchise', 'store', 'country', 'customer_group', 'payment', 'fulfillment', 'currency', 'group_by', 'breakdown', 'include_tax', 'compare']), 'from' => $filters['from_value'], 'to' => $filters['to_value']];
    }

}
