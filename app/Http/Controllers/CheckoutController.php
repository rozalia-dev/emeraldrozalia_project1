<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Address;
use App\Models\Discount;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RewardTransaction;
use App\Models\ShippingMethod;
use App\Services\AuditTrail;
use App\Services\CartService;
use App\Services\DiscountCalculator;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(CartService $cart): View|RedirectResponse
    {
        if (! $cart->count()) {
            return redirect()->route('cart')->withErrors(['cart' => 'Your cart is empty.']);
        }

        return view('site.checkout', [
            'items' => $cart->items(),
            'subtotal' => $cart->subtotal(),
            'idempotencyKey' => $this->checkoutKey(),
            'addresses' => auth()->user()->addresses()->latest()->get(),
            'shippingMethods' => ShippingMethod::query()
                ->where('is_active', true)
                ->orderBy('price')
                ->get(),
        ]);
    }

    public function store(CheckoutRequest $request, CartService $cart): RedirectResponse
    {
        $data = $request->validated();
        $idempotencyKey = $request->idempotencyKey();

        $address = null;
        if (! empty($data['address_id'])) {
            $address = auth()->user()->addresses()->find($data['address_id']);
            if (! $address) {
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
                'country' => strtoupper((string) $data['country']),
            ];

        $cartItems = $cart->items();
        if ($cartItems === []) {
            $existing = Order::withoutGlobalScopes()
                ->where('idempotency_key', $idempotencyKey)
                ->where('user_id', auth()->id())
                ->first();

            if ($existing) {
                $requestHash = $this->checkoutHash(
                    $data,
                    $this->orderItemsForHash($existing),
                    $existing->shipping_method_code,
                    $existing->shipping_address ?: [],
                );
                $this->assertIdempotentReplay($existing, $requestHash);
                session()->forget('checkout_idempotency_key');

                return redirect()->route('order.success', $existing);
            }

            return redirect()->route('cart')->withErrors(['cart' => 'Your cart is empty.']);
        }

        $shippingMethodCode = filled($data['shipping_method'] ?? null)
            ? (string) $data['shipping_method']
            : null;
        $requestHash = $this->checkoutHash($data, $cartItems, $shippingMethodCode, $shippingAddress);
        $discountCode = filled($data['discount_code'] ?? null)
            ? strtoupper(trim($data['discount_code']))
            : null;

        try {
            $order = DB::transaction(function () use (
                $cartItems,
                $data,
                $shippingAddress,
                $shippingMethodCode,
                $discountCode,
                $idempotencyKey,
                $requestHash,
            ): Order {
                $existing = Order::withoutGlobalScopes()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $this->assertIdempotentReplay($existing, $requestHash);

                    return $existing;
                }

                $shippingMethod = null;
                if ($shippingMethodCode !== null) {
                    $shippingMethod = ShippingMethod::query()
                        ->where('code', $shippingMethodCode)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (! $shippingMethod) {
                        throw ValidationException::withMessages([
                            'shipping_method' => 'Please choose an available shipping method.',
                        ]);
                    }
                }

                $items = $this->lockCartItems($cartItems);
                $subtotal = $this->subtotalForItems($items);
                $shipping = $shippingMethod
                    ? (($shippingMethod->free_over !== null && Money::compare($subtotal, $shippingMethod->free_over) >= 0)
                        ? '0.00'
                        : Money::round($shippingMethod->price))
                    : '0.00';
                $discount = '0.00';
                $orderShipping = $shipping;
                $coupon = null;

                if ($discountCode) {
                    $coupon = Discount::query()
                        ->where('code', $discountCode)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (! $coupon) {
                        throw ValidationException::withMessages([
                            'discount_code' => 'That discount code is unavailable for this order.',
                        ]);
                    }

                    $calculation = app(DiscountCalculator::class)->calculate(
                        $coupon,
                        $items,
                        $subtotal,
                        $shipping,
                        auth()->user(),
                        'online',
                        'EUR',
                    );
                    if (! $calculation['valid']) {
                        throw ValidationException::withMessages([
                            'discount_code' => $calculation['error'] ?: 'That discount code is unavailable for this order.',
                        ]);
                    }

                    $discount = $calculation['discount'];
                    $orderShipping = $calculation['shipping'];
                }

                $total = Money::subtract(Money::add($subtotal, $orderShipping), $discount);
                if (Money::compare($total, '0.00') < 0) {
                    $total = '0.00';
                }

                $order = Order::create([
                    'user_id' => auth()->id(),
                    'number' => 'ER-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                    'status' => 'processing',
                    'payment_status' => $data['payment_method'] === 'cod' ? 'pay_on_delivery' : 'pending',
                    'subtotal' => $subtotal,
                    'shipping' => $orderShipping,
                    'discount' => $discount,
                    'total' => $total,
                    'currency' => 'EUR',
                    'currency_code' => 'EUR',
                    'exchange_rate' => 1,
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'shipping_method' => $shippingMethod?->name,
                    'shipping_method_code' => $shippingMethodCode,
                    'payment_method' => $data['payment_method'],
                    'discount_code' => $coupon?->code,
                    'notes' => $data['notes'] ?? null,
                    'shipping_address' => $shippingAddress,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'correlation_id' => $this->correlationId(),
                ]);
                AuditTrail::record('order.created', $order, null, $order->toArray());

                foreach ($items as $item) {
                    /** @var Product $product */
                    $product = $item['_product'];
                    /** @var ProductVariant|null $variant */
                    $variant = $item['_variant'];

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'name' => $item['name'],
                        'sku' => $item['sku'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'total' => Money::multiply($item['price'], $item['quantity']),
                        'options' => $item['options'] ?? [],
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
                    'points' => intdiv(max(0, Money::toMinor($order->total)), 100),
                    'type' => 'earn',
                    'reference' => $order->number,
                    'description' => 'Points earned from order',
                ]);

                return $order;
            });
        } catch (QueryException $exception) {
            $existing = Order::withoutGlobalScopes()
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if (! $existing) {
                throw $exception;
            }

            $this->assertIdempotentReplay($existing, $requestHash);
            $order = $existing;
        }

        $cart->clear();
        session()->forget('checkout_idempotency_key');

        return redirect()
            ->route('order.success', $order)
            ->with('success', 'Order placed successfully.');
    }

    public function success(Order $order): View
    {
        Gate::authorize('view', $order);

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

    private function checkoutKey(): string
    {
        $key = trim((string) session('checkout_idempotency_key', ''));
        if ($key === '' || preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $key) !== 1) {
            $key = (string) Str::uuid();
            session(['checkout_idempotency_key' => $key]);
        }

        return $key;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function checkoutHash(array $data, array $items, ?string $shippingMethodCode, array $shippingAddress): string
    {
        $normalizedItems = collect($items)
            ->map(function (array $item): array {
                $variantId = $item['variant_id'] ?? $item['product_variant_id'] ?? null;
                $options = is_array($item['options'] ?? null) ? $item['options'] : [];
                ksort($options);

                return [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'variant_id' => $variantId === null ? null : (int) $variantId,
                    'quantity' => (int) ($item['quantity'] ?? 0),
                    'options' => $options,
                ];
            })
            ->sortBy(fn (array $item): string => $item['product_id'].':'.($item['variant_id'] ?? 0).':'.(string) json_encode($item['options']))
            ->values()
            ->all();

        ksort($shippingAddress);

        return hash('sha256', (string) json_encode([
            'user_id' => auth()->id(),
            'email' => strtolower(trim((string) ($data['email'] ?? ''))),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'shipping_address' => $shippingAddress,
            'shipping_method_code' => $shippingMethodCode,
            'payment_method' => (string) ($data['payment_method'] ?? ''),
            'discount_code' => strtoupper(trim((string) ($data['discount_code'] ?? ''))),
            'notes' => (string) ($data['notes'] ?? ''),
            'items' => $normalizedItems,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<int, array<string, mixed>> $items */
    private function lockCartItems(array $items): array
    {
        $lockedItems = [];

        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $product = Product::query()
                ->whereKey((int) ($item['product_id'] ?? 0))
                ->lockForUpdate()
                ->first();
            $variant = null;
            if ($product && ! empty($item['variant_id'])) {
                $variant = ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->whereKey((int) $item['variant_id'])
                    ->lockForUpdate()
                    ->first();
            }

            $available = $variant?->stock ?? $product?->stock;
            if ($quantity < 1 || $quantity > 50 || ! $product?->is_active
                || (! empty($item['variant_id']) && ! $variant?->is_active)
                || $available === null || (int) $available < $quantity) {
                throw ValidationException::withMessages([
                    'cart' => ($item['name'] ?? 'This item').' is no longer available in the requested quantity.',
                ]);
            }

            $lockedItems[] = array_merge($item, [
                'name' => $product->name,
                'sku' => $variant?->sku ?? $product->sku,
                'price' => Money::round($variant?->price ?? $product->price),
                '_product' => $product,
                '_variant' => $variant,
            ]);
        }

        return $lockedItems;
    }

    /** @param array<int, array<string, mixed>> $items */
    private function subtotalForItems(array $items): string
    {
        $subtotal = '0.00';
        foreach ($items as $item) {
            $subtotal = Money::add($subtotal, Money::multiply($item['price'], (int) $item['quantity']));
        }

        return $subtotal;
    }

    /** @return array<int, array<string, mixed>> */
    private function orderItemsForHash(Order $order): array
    {
        return $order->items()->get()->map(fn (OrderItem $item): array => [
            'product_id' => $item->product_id,
            'variant_id' => $item->product_variant_id,
            'quantity' => $item->quantity,
            'options' => $item->options ?: [],
        ])->all();
    }

    private function assertIdempotentReplay(Order $order, string $requestHash): void
    {
        if ((int) $order->user_id !== (int) auth()->id()
            || (session('company_id') && (int) $order->company_id !== (int) session('company_id'))
            || ! hash_equals((string) $order->request_hash, $requestHash)) {
            abort(409, 'The Idempotency-Key was already used for a different checkout.');
        }
    }

    private function correlationId(): string
    {
        $candidate = request()->attributes->get('correlation_id');

        return is_string($candidate) && Str::isUuid($candidate) ? $candidate : (string) Str::uuid();
    }
}
