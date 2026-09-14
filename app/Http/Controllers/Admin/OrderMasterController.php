<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderTransitionRequest;
use App\Models\Order;
use App\Services\OrderLifecycle;
use Illuminate\Support\Facades\Gate;
use App\Services\OrderMasterDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        return view('admin.orders.index', app(OrderMasterDashboardService::class)->build($request, $type));
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
