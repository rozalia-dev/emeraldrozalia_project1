<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderTransitionRequest;
use App\Models\Order;
use App\Models\SalesQuote;
use App\Services\OrderLifecycle;
use App\Services\OrderMasterDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OrderMasterController extends Controller
{
    private const TYPES = ['online', 'corporate', 'bulk', 'franchise', 'franchise_retail', 'buyer'];

    private const LABELS = [
        'online' => 'Online Orders',
        'corporate' => 'Corporate Orders',
        'bulk' => 'Bulk Orders',
        'franchise' => 'Franchise Orders',
        'franchise_retail' => 'Franchise Retail Orders',
        'buyer' => 'Buyer Orders',
    ];

    public function index(Request $request, string $type): View
    {
        $this->ensureType($type);
        Gate::authorize('viewAny', Order::class);

        $data = app(OrderMasterDashboardService::class)->build($request, $type);

        if (in_array($type, ['corporate', 'bulk'], true)) {
            $quoteBase = SalesQuote::query()->where('order_type', $type);
            $openQuoteCount = (clone $quoteBase)
                ->whereIn('status', ['submitted', 'approved'])
                ->count();
            $convertedQuoteCount = (clone $quoteBase)
                ->where('status', 'converted')
                ->count();

            array_unshift($data['metrics'], [
                'label' => 'Quote Requests',
                'value' => $openQuoteCount,
                'kind' => 'number',
                'icon' => 'file-text',
                'tone' => 'purple',
                'source_label' => 'Pre-order intake · Sales Quotes & Conversions',
            ]);

            $data['meta']['subtitle'] = $data['meta']['subtitle'].' Public website requests first enter Sales Quotes & Conversions; only approved conversions are counted as orders here.';
            $data['quoteIntake'] = [
                'open' => $openQuoteCount,
                'converted' => $convertedQuoteCount,
                'route' => route('admin.quotes.index', ['order_type' => $type]),
            ];

            if (! $data['hasLiveData'] && $openQuoteCount > 0) {
                $data['dataState'] = 'quote-intake';
                $data['emptyStateMessage'] = number_format($openQuoteCount).' '.$type.' quote request'.($openQuoteCount === 1 ? ' is' : 's are').' waiting in Sales Quotes & Conversions. They will appear in this Order Master after approval and conversion.';
            }
        } elseif ($type === 'franchise') {
            $data['meta']['subtitle'] = 'Manage product/supply orders placed by approved franchise partners. Public Franchise Apply submissions belong to Franchise Management → Applications & Leads and do not create sales orders.';
        }

        return view('admin.orders.index', $data);
    }

    public function show(string $type, Order $order): View
    {
        $this->ensureOrder($type, $order);
        Gate::authorize('view', $order);
        $order->load(['user', 'items.product', 'items.variant', 'payments', 'returns']);

        return view('admin.orders.show', [
            'order' => $order,
            'type' => $type,
            'label' => self::LABELS[$type],
            'orderStatuses' => OrderLifecycle::ORDER_STATUSES,
            'paymentStatuses' => OrderLifecycle::PAYMENT_STATUSES,
            'fulfillmentStatuses' => OrderLifecycle::FULFILLMENT_STATUSES,
        ]);
    }

    public function invoice(string $type, Order $order): View
    {
        $this->ensureOrder($type, $order);
        Gate::authorize('invoice', $order);
        $order->load(['user', 'items', 'payments']);

        return view('account.invoice', [
            'order' => $order,
            'adminContext' => ['type' => $type],
        ]);
    }

    public function update(OrderTransitionRequest $request, string $type, Order $order, OrderLifecycle $lifecycle): RedirectResponse
    {
        $this->ensureOrder($type, $order);
        Gate::authorize('update', $order);
        $lifecycle->transition($order, $request->validated());

        return back()->with('success', 'Order status updated and recorded.');
    }

    private function ensureType(string $type): void
    {
        abort_unless(in_array($type, self::TYPES, true), 404);
    }

    private function ensureOrder(string $type, Order $order): void
    {
        $this->ensureType($type);
        abort_unless($order->order_type === $type, 404);
    }
}
