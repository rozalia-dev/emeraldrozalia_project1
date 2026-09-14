<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Support\Facades\Session;

final class CartService
{
    /** @return array<string, array<string, mixed>> */
    public function items(): array
    {
        $stored = Session::get('cart', []);
        if (! is_array($stored)) {
            return [];
        }

        $items = [];
        foreach ($stored as $key => $item) {
            if (! is_array($item)) {
                continue;
            }

            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            if ($quantity < 1) {
                continue;
            }

            $item['key'] = (string) ($item['key'] ?? $key);
            $item['price'] = Money::round($item['price'] ?? 0);
            $item['quantity'] = $quantity;
            $item['line_total'] = Money::multiply($item['price'], $quantity);
            $items[(string) $key] = $item;
        }

        return $items;
    }

    public function add(Product $product, int $quantity = 1, ?ProductVariant $variant = null, array $options = []): void
    {
        $key = $product->id.':'.($variant?->id ?? 0).':'.md5((string) json_encode($options));
        $cart = $this->items();
        $price = Money::round($variant?->price ?? $product->price);

        $newQuantity = ($cart[$key]['quantity'] ?? 0) + max(1, $quantity);
        $cart[$key] = [
            'key' => $key,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'name' => $product->name,
            'sku' => $variant?->sku ?? $product->sku,
            'price' => $price,
            'quantity' => $newQuantity,
            'line_total' => Money::multiply($price, $newQuantity),
            'image' => $variant?->image ?? $product->image,
            'options' => $options,
        ];

        Session::put('cart', $cart);
    }

    public function update(string $key, int $quantity): void
    {
        $cart = $this->items();
        if (! isset($cart[$key])) {
            return;
        }

        if ($quantity <= 0) {
            unset($cart[$key]);
        } else {
            $cart[$key]['quantity'] = $quantity;
            $cart[$key]['line_total'] = Money::multiply($cart[$key]['price'], $quantity);
        }

        Session::put('cart', $cart);
    }

    public function remove(string $key): void
    {
        $cart = $this->items();
        unset($cart[$key]);
        Session::put('cart', $cart);
    }

    public function clear(): void
    {
        Session::forget('cart');
    }

    public function subtotal(): string
    {
        $subtotal = '0.00';

        foreach ($this->items() as $item) {
            $subtotal = Money::add(
                $subtotal,
                Money::multiply($item['price'], (int) $item['quantity']),
            );
        }

        return $subtotal;
    }

    public function count(): int
    {
        return array_sum(array_map(
            static fn (array $item): int => (int) ($item['quantity'] ?? 0),
            $this->items(),
        ));
    }
}
