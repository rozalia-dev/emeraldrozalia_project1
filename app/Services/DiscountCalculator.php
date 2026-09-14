<?php

namespace App\Services;

use App\Models\{Discount, Product, User};
use App\Support\Money;
use Illuminate\Support\Collection;

final class DiscountCalculator
{
    /**
     * Calculate one coupon against the immutable cart snapshot used by checkout.
     * The result is deliberately explicit so callers cannot silently apply an
     * unsupported rule or turn a non-eligible coupon into a free order.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{valid: bool, discount: string, shipping: string, error: string|null}
     */
    public function calculate(
        Discount $coupon,
        array $items,
        int|float|string $subtotal,
        int|float|string $shipping,
        ?User $user = null,
        string $orderCategory = 'online',
        string $currency = 'EUR',
    ): array {
        $subtotal = Money::round($subtotal);
        $shipping = Money::round($shipping);
        $metadata = is_array($coupon->metadata) ? $coupon->metadata : [];
        $invalid = fn (string $message): array => [
            'valid' => false,
            'discount' => '0.00',
            'shipping' => $shipping,
            'error' => $message,
        ];

        if (! $coupon->is_active) {
            return $invalid('That discount code is unavailable for this order.');
        }
        if ($coupon->starts_at?->isFuture() || $coupon->ends_at?->isPast()) {
            return $invalid('That discount code is unavailable for this order.');
        }
        if (Money::compare($subtotal, $coupon->minimum_order ?? 0) < 0) {
            return $invalid('That discount code is unavailable for this order.');
        }
        if ($coupon->usage_limit !== null && (int) $coupon->used >= (int) $coupon->usage_limit) {
            return $invalid('That discount code is unavailable for this order.');
        }

        $configuredCurrency = strtoupper(trim((string) data_get($metadata, 'currency', '')));
        if ($configuredCurrency !== '' && $configuredCurrency !== strtoupper($currency)) {
            return $invalid('That discount code is not available in this currency.');
        }

        $categories = collect((array) data_get($metadata, 'order_categories', []))
            ->map(fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->values();
        if ($categories->isNotEmpty() && ! $categories->contains(strtolower($orderCategory))) {
            return $invalid('That discount code is not available for this order type.');
        }

        if (! $this->customerIsEligible($metadata, $user)) {
            return $invalid('That discount code is not available for this customer.');
        }

        $calculated = match ($coupon->type) {
            'percent' => Money::percentage(
                $subtotal,
                Money::fromMinor(max(0, min(Money::toMinor(100), Money::toMinor($coupon->value ?? 0)))),
            ),
            'fixed' => Money::fromMinor(min(
                Money::toMinor($subtotal),
                max(0, Money::toMinor($coupon->value ?? 0)),
            )),
            // Shipping is represented by the returned shipping amount so the
            // order total cannot subtract the same benefit twice.
            'free_shipping' => '0.00',
            'buy_x_get_y' => $this->buyXGetY($items, $metadata),
            default => null,
        };

        if ($calculated === null) {
            return $invalid('That discount type is not supported by checkout.');
        }
        if ($coupon->type === 'buy_x_get_y' && $calculated === false) {
            return $invalid('That discount code does not apply to the items in this cart.');
        }

        $discount = Money::fromMinor(min(
            Money::toMinor(Money::add($subtotal, $shipping)),
            max(0, Money::toMinor($calculated)),
        ));

        return [
            'valid' => true,
            'discount' => $discount,
            'shipping' => $coupon->type === 'free_shipping' ? '0.00' : $shipping,
            'error' => null,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $metadata
     * @return string|false
     */
    private function buyXGetY(array $items, array $metadata): string|false
    {
        $buy = max(1, (int) data_get($metadata, 'buy_quantity', 0));
        $get = max(1, (int) data_get($metadata, 'get_quantity', 0));
        if ($buy < 1 || $get < 1) {
            return false;
        }

        $productIds = collect((array) data_get($metadata, 'product_ids', []))
            ->map(fn (mixed $value): int => (int) $value)
            ->filter()
            ->values();
        $skus = collect((array) data_get($metadata, 'product_skus', []))
            ->map(fn (mixed $value): string => strtoupper(trim((string) $value)))
            ->filter()
            ->values();
        $categorySlugs = collect((array) data_get($metadata, 'category_slugs', []))
            ->map(fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->values();

        $productIdsInCart = collect($items)->pluck('product_id')->map(fn (mixed $id): int => (int) $id)->filter()->unique();
        $products = $productIdsInCart->isNotEmpty()
            ? Product::query()->with('category')->whereIn('id', $productIdsInCart)->get()->keyBy('id')
            : collect();

        $units = collect($items)->flatMap(function (array $item) use ($productIds, $skus, $categorySlugs, $products): Collection {
            $productId = (int) ($item['product_id'] ?? 0);
            $sku = strtoupper(trim((string) ($item['sku'] ?? '')));
            $product = $products->get($productId);
            $restricted = $productIds->isNotEmpty() || $skus->isNotEmpty() || $categorySlugs->isNotEmpty();
            $eligible = ! $restricted
                || ($productIds->isNotEmpty() && $productIds->contains($productId))
                || ($skus->isNotEmpty() && $skus->contains($sku))
                || ($categorySlugs->isNotEmpty() && $categorySlugs->contains(strtolower((string) $product?->category?->slug)));

            if (! $eligible) {
                return collect();
            }

            $quantity = max(0, (int) ($item['quantity'] ?? 0));
            $price = max(0, Money::toMinor($item['price'] ?? 0));
            if ($quantity < 1) {
                return collect();
            }

            return collect(range(1, $quantity))->map(fn (): int => $price);
        })->sort()->values();

        $freeUnits = intdiv($units->count(), $buy + $get) * $get;
        if ($freeUnits < 1) {
            return false;
        }

        return Money::fromMinor((int) $units->take($freeUnits)->sum());
    }

    /** @param array<string, mixed> $metadata */
    private function customerIsEligible(array $metadata, ?User $user): bool
    {
        $configured = strtolower(trim((string) data_get($metadata, 'customer_group', '')));
        if ($configured === '' || in_array($configured, ['all', 'all_customers'], true)) {
            return true;
        }
        if (! $user) {
            return false;
        }
        if (in_array($configured, ['new_customer', 'new_customers'], true)) {
            return ! $user->orders()->exists();
        }
        if (in_array($configured, ['vip', 'vip_customers'], true)) {
            return (bool) $user->customerProfile?->is_vip || $user->customerGroups()->where('is_vip', true)->exists();
        }

        return $user->customerGroups()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereRaw('LOWER(slug) = ?', [$configured])->orWhereRaw('LOWER(name) = ?', [$configured]))
            ->exists();
    }
}
