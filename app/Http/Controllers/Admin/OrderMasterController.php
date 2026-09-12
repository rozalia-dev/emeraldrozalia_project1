<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Order, PaymentTransaction};
use App\Services\AuditTrail;
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
        return view('admin.orders.index', app(OrderMasterDashboardService::class)->build($request, $type));
    }

    public function show(string $type, Order $order): View
    {
        $this->ensureOrder($type, $order);
        $order->load(['user', 'items.product', 'items.variant', 'payments', 'returns']);

        return view('admin.orders.show', [
            'order' => $order,
            'type' => $type,
            'label' => self::LABELS[$type],
        ]);
    }

    public function invoice(string $type, Order $order): View
    {
        $this->ensureOrder($type, $order);
        $order->load(['user', 'items', 'payments']);

        return view('account.invoice', [
            'order' => $order,
            'adminContext' => ['type' => $type],
        ]);
    }

    public function update(Request $request, string $type, Order $order): RedirectResponse
    {
        $this->ensureOrder($type, $order);

        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,processing,shipped,completed,cancelled,refunded'],
            'payment_status' => ['required', 'in:unpaid,pending,pay_on_delivery,paid,failed,refunded'],
        ]);
        $before = $order->toArray();
        $order->update($data);

        if ($before['payment_status'] !== $order->payment_status) {
            PaymentTransaction::create([
                'order_id' => $order->id,
                'provider' => $order->payment_method ?: 'manual',
                'amount' => $order->total,
                'currency' => $order->currency ?: 'EUR',
                'status' => $order->payment_status,
                'payload' => ['source' => 'admin_order_lifecycle', 'order_type' => $type],
            ]);
        }

        AuditTrail::record('admin.order_updated', $order, $before, $order->fresh()->toArray());

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
