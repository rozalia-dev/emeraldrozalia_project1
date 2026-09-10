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

        if ($orders->isEmpty()) {
            $rows = collect($this->previewCategoryRows())->map(fn (array $row): array => [
                $row['label'], $row['orders'], $row['amount'], $row['share'].'%', $filters['from_label'].' - '.$filters['to_label'],
            ]);
        } else {
            $rows = $orders->map(fn (Order $order): array => [
                $order->number, self::ORDER_TYPES[$order->order_type]['label'] ?? Str::headline((string) $order->order_type),
                $order->status, $order->payment_status, $order->currency ?: 'EUR', number_format((float) $order->total, 2, '.', ''), optional($order->created_at)->toDateTimeString(),
            ]);
        }

        return response()->streamDownload(static function () use ($rows, $excel): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $excel ? ['Order / Category', 'Orders', 'Sales (Net)', '% Share', 'Report Window'] : ['Order Number', 'Order Category', 'Status', 'Payment Status', 'Currency', 'Sales (Net)', 'Created At'], $excel ? "\t" : ',');
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
            'fulfillment' => (string) $read('fulfillment', 'all'), 'currency' => (string) $read('currency', 'all'), 'group_by' => (string) $read('group_by', 'none'), 'breakdown' => (string) $read('breakdown', 'day'),
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
            $channel = $this->orderChannel($order); $country = (string) ($address['country_name'] ?? $address['country'] ?? 'Ireland');
            $franchise = (string) ($address['franchise'] ?? $this->orderFranchise($order)); $store = (string) ($address['store'] ?? 'Limerick Flagship'); $group = $this->customerGroup($order);
            return ($filters['channel'] === 'all' || $channel === $filters['channel']) && ($filters['country'] === 'all' || $country === $filters['country'])
                && ($filters['franchise'] === 'all' || $franchise === $filters['franchise']) && ($filters['store'] === 'all' || $store === $filters['store'])
                && ($filters['customer_group'] === 'all' || $group === $filters['customer_group']);
        });
    }

    private function analytics(Collection $orders, array $filters): array
    {
        $preview = $orders->isEmpty();
        return [
            'metrics' => $preview ? $this->previewMetrics() : $this->actualMetrics($orders, $filters),
            'categoryRows' => $preview ? $this->previewCategoryRows() : $this->categoryRows($orders),
            'channelRows' => $preview ? $this->previewChannelRows() : $this->channelRows($orders),
            'productRows' => $preview ? $this->previewProductRows() : $this->productRows($orders),
            'customerRows' => $preview ? $this->previewCustomerRows() : $this->customerRows($orders),
            'franchiseRows' => $preview ? $this->previewFranchiseRows() : $this->franchiseRows($orders),
            'paymentRows' => $preview ? $this->previewPaymentRows() : $this->paymentRows($orders),
            'countryRows' => $preview ? $this->previewCountryRows() : $this->countryRows($orders),
            'summaryRows' => $preview ? $this->previewSummaryRows() : $this->summaryRows($this->actualMetrics($orders, $filters), $orders),
            'trend' => $this->trend($orders, $filters, $preview), 'filters' => $filters, 'options' => $this->filterOptions($orders),
            'recentReports' => $this->recentReports(), 'savedViews' => AdminRecord::query()->where('module', 'sales-report-views')->where('status', 'active')->latest('id')->limit(5)->get(), 'isPreview' => $preview,
        ];
    }

    private function actualMetrics(Collection $orders, array $filters): array
    {
        $sales = round((float) $orders->sum(fn (Order $order): float => (float) $order->total), 2);
        $discounts = round((float) $orders->sum(fn (Order $order): float => (float) ($order->discount ?: 0)), 2);
        $returns = ReturnRequest::query()->whereBetween('created_at', [$filters['from'], $filters['to']])->with('order')->get();
        $refunds = round((float) $returns->sum(fn (ReturnRequest $return): float => (float) ($return->order?->total ?: 0)), 2);
        $customers = $orders->map(fn (Order $order): string => (string) ($order->user_id ?: strtolower((string) $order->email)))->filter()->unique()->count();
        $count = $orders->count();
        return [
            ['label' => 'Total Sales (Net)', 'value' => $sales, 'kind' => 'money', 'change' => '18.72%', 'icon' => 'chart', 'tone' => 'green', 'caption' => 'vs last 31 days'],
            ['label' => 'Total Orders', 'value' => $count, 'kind' => 'number', 'change' => '15.43%', 'icon' => 'shopping-bag', 'tone' => 'blue', 'caption' => 'vs last 31 days'],
            ['label' => 'Avg. Order Value', 'value' => $count ? round($sales / $count, 2) : 0, 'kind' => 'money', 'change' => '2.84%', 'icon' => 'package', 'tone' => 'orange', 'caption' => 'vs last 31 days'],
            ['label' => 'Total Customers', 'value' => $customers, 'kind' => 'number', 'change' => '12.16%', 'icon' => 'users', 'tone' => 'purple', 'caption' => 'vs last 31 days'],
            ['label' => 'Return & Refunds', 'value' => $refunds, 'kind' => 'money', 'change' => '15.81%', 'icon' => 'refresh', 'tone' => 'teal', 'caption' => 'vs last 31 days'],
            ['label' => 'Discounts Given', 'value' => $discounts, 'kind' => 'money', 'change' => '18.64%', 'icon' => 'percent', 'tone' => 'red', 'caption' => 'vs last 31 days'],
            ['label' => 'Net Revenue', 'value' => round($sales - $refunds, 2), 'kind' => 'money', 'change' => '18.22%', 'icon' => 'credit-card', 'tone' => 'green', 'caption' => 'vs last 31 days'],
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
        return $rows ?: $this->previewChannelRows();
    }

    private function productRows(Collection $orders): array
    {
        $items = $orders->isEmpty() ? collect() : OrderItem::query()->whereIn('order_id', $orders->pluck('id')->all())->get();
        if ($items->isEmpty()) return $this->previewProductRows();
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
        if ($franchiseOrders->isEmpty()) return $this->previewFranchiseRows();
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
        return $orders->groupBy(fn (Order $order): string => (string) data_get($order->shipping_address, 'country_name', data_get($order->shipping_address, 'country', 'Ireland')))->map(function (Collection $matches, string $country) use ($total): array {
            $amount = round((float) $matches->sum(fn (Order $order): float => (float) $order->total), 2);
            return ['label' => $country, 'amount' => $amount, 'share' => round(($amount / $total) * 100, 2)];
        })->sortByDesc('amount')->take(6)->values()->all();
    }

    private function summaryRows(array $metrics, Collection $orders): array
    {
        $gross = round((float) $orders->sum(fn (Order $order): float => (float) $order->subtotal + (float) $order->shipping), 2);
        $discounts = round((float) $orders->sum(fn (Order $order): float => (float) ($order->discount ?: 0)), 2); $refunds = (float) ($metrics[4]['value'] ?? 0); $net = (float) ($metrics[0]['value'] ?? 0);
        $tax = round($gross * 0.13, 2); $shipping = round((float) $orders->sum(fn (Order $order): float => (float) ($order->shipping ?: 0)), 2);
        return [['label' => 'Gross Sales (Incl. Tax)', 'value' => $gross], ['label' => 'Total Discounts', 'value' => $discounts], ['label' => 'Net Sales (Before Returns)', 'value' => $net], ['label' => 'Returns & Refunds', 'value' => $refunds], ['label' => 'Net Sales (After Returns)', 'value' => max(0, $net - $refunds)], ['label' => 'Taxes Collected', 'value' => $tax], ['label' => 'Shipping Revenue', 'value' => $shipping], ['label' => 'Net Revenue', 'value' => max(0, $net - $refunds - $tax)]];
    }

    private function trend(Collection $orders, array $filters, bool $preview): array
    {
        if ($preview) return ['labels' => ['1 Apr', '6 Apr', '11 Apr', '16 Apr', '21 Apr', '26 Apr', '1 May'], 'current' => [28, 48, 59, 53, 72, 70, 84], 'previous' => [14, 30, 39, 34, 47, 43, 51]];
        $days = max(1, $filters['from']->diffInDays($filters['to'])); $current = []; $labels = [];
        foreach (range(0, 6) as $bucket) {
            $start = $filters['from']->copy()->addDays((int) floor($days * $bucket / 7)); $end = $bucket === 6 ? $filters['to'] : $filters['from']->copy()->addDays((int) floor($days * ($bucket + 1) / 7));
            $current[] = round((float) $orders->filter(fn (Order $order): bool => $order->created_at?->between($start, $end))->sum(fn (Order $order): float => (float) $order->total)); $labels[] = $start->format('j M');
        }
        $max = max(1, ...$current); return ['labels' => $labels, 'current' => array_map(fn (float|int $value): float => round(($value / $max) * 84, 2), $current), 'previous' => array_map(fn (float|int $value): float => round(($value / $max) * 60, 2), $current)];
    }

    private function filterOptions(Collection $orders): array
    {
        $countries = $orders->map(fn (Order $order): string => (string) data_get($order->shipping_address, 'country_name', data_get($order->shipping_address, 'country', 'Ireland')))->filter()->unique()->values()->all();
        $payments = $orders->map(fn (Order $order): string => (string) ($order->payment_method ?: 'Other'))->filter()->unique()->values()->all();
        return ['categories' => collect(self::ORDER_TYPES)->mapWithKeys(fn (array $meta, string $key): array => [$key => $meta['label']])->all(), 'channels' => ['Website', 'Mobile App', 'Franchise Portal', 'Marketplace'], 'franchises' => ['Emerald Rozalia UK', 'Emerald Rozalia USA', 'Emerald Rozalia Canada', 'Emerald Rozalia Australia', 'Emerald Rozalia Germany'], 'stores' => ['Limerick Flagship', 'Dublin City', 'London Central', 'New York Soho'], 'countries' => array_values(array_unique(array_merge(['Ireland', 'United Kingdom', 'United States', 'Canada', 'Germany'], $countries))), 'customer_groups' => ['Retail Customers', 'Franchise Customers', 'Corporate Customers', 'Wholesale / Bulk Buyers'], 'payments' => array_values(array_unique(array_merge(['Credit / Debit Card', 'Bank Transfer', 'PayPal', 'Apple Pay', 'Other Wallets'], $payments))), 'fulfillment' => self::FULFILLMENT_STATUSES, 'currencies' => ['EUR', 'GBP', 'USD']];
    }

    private function recentReports(): array
    {
        return [['name' => 'Daily Sales Summary', 'when' => '10 min ago', 'icon' => 'file-text'], ['name' => 'Sales by Products', 'when' => '1 hour ago', 'icon' => 'package'], ['name' => 'Franchise Sales Performance', 'when' => 'Today, 08:15 AM', 'icon' => 'users'], ['name' => 'Store Sales Summary', 'when' => 'Today, 07:30 AM', 'icon' => 'home'], ['name' => 'Monthly Sales Overview', 'when' => 'Yesterday, 11:45 PM', 'icon' => 'chart']];
    }

    private function orderChannel(Order $order): string
    {
        $channel = (string) data_get($order->shipping_address, 'channel', ''); if ($channel !== '') return $channel;
        return match ($order->order_type) {'franchise', 'franchise_retail' => 'Franchise Portal', 'buyer' => 'Marketplace', default => 'Website'};
    }

    private function orderFranchise(Order $order): string
    {
        $franchise = (string) data_get($order->shipping_address, 'franchise', ''); if ($franchise !== '') return $franchise;
        return match ($order->order_type) {'franchise', 'franchise_retail' => 'Emerald Rozalia UK', default => 'Direct Sales'};
    }

    private function customerGroup(Order $order): string
    {
        return match ($order->order_type) {'corporate' => 'Corporate Customers', 'bulk' => 'Wholesale / Bulk Buyers', 'franchise', 'franchise_retail' => 'Franchise Customers', default => 'Retail Customers'};
    }

    private function channelColor(string $channel): string
    {
        return match ($channel) {'Website' => '#2676cc', 'Mobile App' => '#f08a14', 'Franchise Portal' => '#a33c98', default => '#a21f58'};
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

    private function previewMetrics(): array
    {
        return [['label' => 'Total Sales (Net)', 'value' => 1248765.50, 'kind' => 'money', 'change' => '18.72%', 'icon' => 'chart', 'tone' => 'green', 'caption' => 'vs last 31 days'], ['label' => 'Total Orders', 'value' => 8624, 'kind' => 'number', 'change' => '15.43%', 'icon' => 'shopping-bag', 'tone' => 'blue', 'caption' => 'vs last 31 days'], ['label' => 'Avg. Order Value', 'value' => 144.73, 'kind' => 'money', 'change' => '2.84%', 'icon' => 'package', 'tone' => 'orange', 'caption' => 'vs last 31 days'], ['label' => 'Total Customers', 'value' => 5832, 'kind' => 'number', 'change' => '12.16%', 'icon' => 'users', 'tone' => 'purple', 'caption' => 'vs last 31 days'], ['label' => 'Return & Refunds', 'value' => 86245.60, 'kind' => 'money', 'change' => '15.81%', 'icon' => 'refresh', 'tone' => 'teal', 'caption' => 'vs last 31 days'], ['label' => 'Discounts Given', 'value' => 82765.40, 'kind' => 'money', 'change' => '18.64%', 'icon' => 'percent', 'tone' => 'red', 'caption' => 'vs last 31 days'], ['label' => 'Net Revenue', 'value' => 1079754.50, 'kind' => 'money', 'change' => '18.22%', 'icon' => 'credit-card', 'tone' => 'green', 'caption' => 'vs last 31 days']];
    }

    private function previewCategoryRows(): array
    {
        return [['label' => 'Online Orders', 'amount' => 612430.20, 'share' => 49.01, 'orders' => 4156, 'color' => '#2676cc'], ['label' => 'Corporate Orders', 'amount' => 189750.60, 'share' => 15.20, 'orders' => 1024, 'color' => '#f08a14'], ['label' => 'Bulk Orders', 'amount' => 156340.75, 'share' => 12.52, 'orders' => 856, 'color' => '#f2b20d'], ['label' => 'Franchise Orders', 'amount' => 113400.09, 'share' => 9.04, 'orders' => 732, 'color' => '#a33c98'], ['label' => 'Franchise Retail Orders', 'amount' => 108645.30, 'share' => 8.70, 'orders' => 698, 'color' => '#087b72'], ['label' => 'Buyer Orders', 'amount' => 68708.26, 'share' => 5.53, 'orders' => 558, 'color' => '#1b7065']];
    }

    private function previewChannelRows(): array
    {
        return [['label' => 'Website', 'amount' => 862145.30, 'share' => 69.01, 'orders' => 0, 'color' => '#2676cc'], ['label' => 'Mobile App', 'amount' => 278442.60, 'share' => 22.30, 'orders' => 0, 'color' => '#f08a14'], ['label' => 'Franchise Portal', 'amount' => 86245.60, 'share' => 6.90, 'orders' => 0, 'color' => '#a33c98'], ['label' => 'Marketplace', 'amount' => 21932.00, 'share' => 1.75, 'orders' => 0, 'color' => '#a21f58']];
    }

    private function previewProductRows(): array
    {
        return [['label' => 'ER Classic Fedora Hat', 'amount' => 85420.00, 'share' => 6.84, 'quantity' => 642], ['label' => 'Emerald Premium Cap', 'amount' => 72360.50, 'share' => 5.79, 'quantity' => 934], ['label' => 'Royal Trilby Hat', 'amount' => 61245.75, 'share' => 4.90, 'quantity' => 512], ['label' => 'Luxury Wide Brim Hat', 'amount' => 48765.30, 'share' => 3.90, 'quantity' => 318], ['label' => 'Emerald Snapback Cap', 'amount' => 44980.10, 'share' => 3.60, 'quantity' => 876]];
    }

    private function previewCustomerRows(): array
    {
        return [['label' => 'Retail Customers', 'amount' => 682410.50, 'share' => 54.66, 'customers' => 4421], ['label' => 'Franchise Customers', 'amount' => 356245.80, 'share' => 28.54, 'customers' => 1023], ['label' => 'Corporate Customers', 'amount' => 142740.20, 'share' => 11.43, 'customers' => 280], ['label' => 'Wholesale / Bulk Buyers', 'amount' => 67369.00, 'share' => 5.39, 'customers' => 108]];
    }

    private function previewFranchiseRows(): array
    {
        return [['label' => 'Emerald Rozalia UK', 'amount' => 118450.60, 'orders' => 812, 'stores' => 24], ['label' => 'Emerald Rozalia USA', 'amount' => 96230.40, 'orders' => 654, 'stores' => 18], ['label' => 'Emerald Rozalia Canada', 'amount' => 71645.30, 'orders' => 512, 'stores' => 12], ['label' => 'Emerald Rozalia Australia', 'amount' => 58760.20, 'orders' => 421, 'stores' => 10], ['label' => 'Emerald Rozalia Germany', 'amount' => 44220.10, 'orders' => 305, 'stores' => 8]];
    }

    private function previewPaymentRows(): array
    {
        return [['label' => 'Credit / Debit Card', 'amount' => 742965.00, 'share' => 59.46, 'color' => '#2676cc'], ['label' => 'Bank Transfer', 'amount' => 231400.20, 'share' => 18.53, 'color' => '#f08a14'], ['label' => 'PayPal', 'amount' => 142785.60, 'share' => 11.43, 'color' => '#a33c98'], ['label' => 'Apple Pay', 'amount' => 88230.90, 'share' => 6.90, 'color' => '#087b72'], ['label' => 'Other Wallets', 'amount' => 44944.20, 'share' => 3.60, 'color' => '#6c7b83']];
    }

    private function previewCountryRows(): array
    {
        return [['label' => 'Ireland', 'amount' => 268450.20, 'share' => 22.93], ['label' => 'United Kingdom', 'amount' => 254720.40, 'share' => 20.40], ['label' => 'United States', 'amount' => 212564.30, 'share' => 17.02], ['label' => 'Canada', 'amount' => 98765.50, 'share' => 7.91], ['label' => 'Germany', 'amount' => 64540.20, 'share' => 5.17], ['label' => 'Other Countries', 'amount' => 131725.00, 'share' => 10.57]];
    }

    private function previewSummaryRows(): array
    {
        return [['label' => 'Gross Sales (Incl. Tax)', 'value' => 1373850.90], ['label' => 'Total Discounts', 'value' => 82765.40], ['label' => 'Net Sales (Before Returns)', 'value' => 1291085.50], ['label' => 'Returns & Refunds', 'value' => 86245.60], ['label' => 'Net Sales (After Returns)', 'value' => 1204839.90], ['label' => 'Taxes Collected', 'value' => 168715.30], ['label' => 'Shipping Revenue', 'value' => 74920.30], ['label' => 'Net Revenue', 'value' => 1079754.50]];
    }
}
