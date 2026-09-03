<?php

namespace App\Http\Controllers;

use App\Models\{
    Address,
    Discount,
    InventoryMovement,
    Order,
    OrderItem,
    PaymentTransaction,
    Product,
    ProductVariant,
    RewardTransaction,
    ShippingMethod
};
use App\Services\CartService;
use App\Services\AuditTrail;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(CartService $cart): View|RedirectResponse
    {
        if (!$cart->count()) {
            return redirect()->route('cart')->withErrors(['cart' => 'Your cart is empty.']);
        }

        return view('site.checkout', [
            'items' => $cart->items(),
            'subtotal' => $cart->subtotal(),
            'addresses' => auth()->user()->addresses()->latest()->get(),
            'shippingMethods' => ShippingMethod::query()
                ->where('is_active', true)
                ->orderBy('price')
                ->get(),
        ]);
    }

    public function store(Request $request, CartService $cart): RedirectResponse
    {
        if (!$cart->count()) {
            return redirect()->route('cart')->withErrors(['cart' => 'Your cart is empty.']);
        }

        $data = $request->validate([
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'name' => ['nullable', 'string', 'max:120', 'required_without:address_id'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'line1' => ['nullable', 'string', 'max:180', 'required_without:address_id'],
            'line2' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:120', 'required_without:address_id'],
            'county' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'size:2', 'required_without:address_id'],
            'shipping_method' => ['nullable', 'string', 'max:80'],
            'payment_method' => ['required', 'in:cod,bank_transfer,manual'],
            'discount_code' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $address = null;
        if (!empty($data['address_id'])) {
            $address = auth()->user()->addresses()->find($data['address_id']);
            if (!$address) {
                throw ValidationException::withMessages([
                    'address_id' => 'That saved address is not available on your account.',
                ]);
            }
        }

        $shippingAddress = $address
            ? $this->addressPayload($address)
            : [
                'name' => $data['name'],
                'line1' => $data['line1'],
                'line2' => $data['line2'] ?? null,
                'city' => $data['city'],
                'county' => $data['county'] ?? null,
                'postcode' => $data['postcode'] ?? null,
                'country' => strtoupper($data['country']),
            ];

        $shippingMethod = null;
        if (!empty($data['shipping_method'])) {
            $shippingMethod = ShippingMethod::query()
                ->where('code', $data['shipping_method'])
                ->where('is_active', true)
                ->first();

            if (!$shippingMethod) {
                throw ValidationException::withMessages([
                    'shipping_method' => 'Please choose an available shipping method.',
                ]);
            }
        }

        $subtotal = Money::round($cart->subtotal());
        $shipping = $shippingMethod
            ? (($shippingMethod->free_over !== null && $subtotal >= (float) $shippingMethod->free_over)
                ? 0.0
                : Money::round($shippingMethod->price))
            : 0.0;
        $discountCode = filled($data['discount_code'] ?? null)
            ? strtoupper(trim($data['discount_code']))
            : null;

        $order = DB::transaction(function () use (
            $cart,
            $data,
            $shippingAddress,
            $shippingMethod,
            $subtotal,
            $shipping,
            $discountCode
        ): Order {
            $discount = 0.0;
            $coupon = null;

            if ($discountCode) {
                $coupon = Discount::query()
                    ->where('code', $discountCode)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (
                    !$coupon
                    || ($coupon->starts_at && $coupon->starts_at->isFuture())
                    || ($coupon->ends_at && $coupon->ends_at->isPast())
                    || $subtotal < (float) $coupon->minimum_order
                    || ($coupon->usage_limit !== null && $coupon->used >= $coupon->usage_limit)
                ) {
                    throw ValidationException::withMessages([
                        'discount_code' => 'That discount code is unavailable for this order.',
                    ]);
                }

                $discount = $coupon->type === 'percent'
                    ? Money::round($subtotal * min(100, (float) $coupon->value) / 100)
                    : Money::round(min($subtotal, (float) $coupon->value));
            }

            $total = Money::round(max(0, $subtotal + $shipping - $discount));
            $order = Order::create([
                'user_id' => auth()->id(),
                'number' => 'ER-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                'status' => 'processing',
                'payment_status' => $data['payment_method'] === 'cod' ? 'pay_on_delivery' : 'pending',
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'discount' => $discount,
                'total' => $total,
                'currency' => 'EUR',
                'currency_code' => 'EUR',
                'exchange_rate' => 1,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'shipping_method' => $shippingMethod?->name,
                'payment_method' => $data['payment_method'],
                'discount_code' => $coupon?->code,
                'notes' => $data['notes'] ?? null,
                'shipping_address' => $shippingAddress,
            ]);
            AuditTrail::record('order.created', $order, null, $order->toArray());

            foreach ($cart->items() as $item) {
                $product = Product::query()->whereKey($item['product_id'])->lockForUpdate()->first();
                $variant = $item['variant_id']
                    ? $product?->variants()->whereKey($item['variant_id'])->lockForUpdate()->first()
                    : null;
                $available = $variant?->stock ?? $product?->stock;

                if (!$product?->is_active || ($item['variant_id'] && !$variant?->is_active) || $available < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'cart' => $item['name'].' is no longer available in the requested quantity.',
                    ]);
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'name' => $item['name'],
                    'sku' => $item['sku'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'total' => Money::multiply($item['price'], $item['quantity']),
                    'options' => $item['options'],
                ]);

                ($variant ?: $product)->decrement('stock', $item['quantity']);
                InventoryMovement::create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => -$item['quantity'],
                    'type' => 'sale',
                    'reference' => $order->number,
                    'note' => 'Checkout stock allocation',
                ]);
            }

            if ($coupon) {
                $coupon->increment('used');
            }

            PaymentTransaction::create([
                'order_id' => $order->id,
                'provider' => $data['payment_method'],
                'amount' => $order->total,
                'currency' => 'EUR',
                'status' => $data['payment_method'] === 'cod' ? 'pending' : 'awaiting_payment',
            ]);

            RewardTransaction::create([
                'user_id' => auth()->id(),
                'points' => (int) floor($order->total),
                'type' => 'earn',
                'reference' => $order->number,
                'description' => 'Points earned from order',
            ]);

            $cart->clear();

            return $order;
        });

        return redirect()
            ->route('order.success', $order)
            ->with('success', 'Order placed successfully.');
    }

    public function success(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);

        return view('site.order-success', compact('order'));
    }

    private function addressPayload(Address $address): array
    {
        return [
            'name' => $address->name,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'county' => $address->county,
            'postcode' => $address->postcode,
            'country' => strtoupper($address->country),
        ];
    }
}
