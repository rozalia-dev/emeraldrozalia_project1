<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Order, PaymentTransaction};
use App\Services\AuditTrail;
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
        $base = Order::query()->where('order_type', $type);
        $orders = (clone $base)
            ->with(['user', 'payments'])
            ->withCount('returns')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')->toString()))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('q'), fn ($query) => $query->where(function ($search) use ($request): void {
                $term = $request->string('q')->toString();
                $search->where('number', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%');
            }))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $metrics = [
            'total' => (clone $base)->count(),
            'open' => (clone $base)->whereIn('status', ['pending', 'approved', 'processing'])->count(),
            'paid' => (clone $base)->where('payment_status', 'paid')->count(),
            'revenue' => (float) (clone $base)->where('payment_status', 'paid')->sum('total'),
            'returns' => (clone $base)->whereHas('returns', fn ($query) => $query->whereIn('status', ['requested', 'approved', 'received', 'inspecting']))->count(),
        ];

        return view('admin.orders.index', [
            'orders' => $orders,
            'type' => $type,
            'label' => self::LABELS[$type],
            'metrics' => $metrics,
        ]);
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
