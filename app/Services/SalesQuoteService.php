<?php

namespace App\Services;

use App\Events\SalesQuoteConverted;
use App\Models\{Conversation, FranchiseApplication, Inquiry, InventoryMovement, Order, OrderItem, PaymentTransaction, Product, ProductVariant, SalesQuote};
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SalesQuoteService
{
    private const ORDER_TYPES = ['corporate', 'bulk', 'franchise'];

    public function orderTypeForInquiryType(string $inquiryType): ?string
    {
        return match ($inquiryType) {
            'corporate-orders' => 'corporate',
            'bulk-orders' => 'bulk',
            'franchise' => 'franchise',
            default => null,
        };
    }

    public function visibleQuery(): Builder
    {
        $query = SalesQuote::withoutGlobalScopes();
        $companyId = session('company_id');

        if (! $companyId) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query->where(function (Builder $visible) use ($table, $companyId): void {
            $visible->where($table.'.company_id', (int) $companyId);
            if (auth()->user()?->is_admin) {
                $visible->orWhereNull($table.'.company_id');
            }
        });
    }

    public function createFromInquiry(
        Inquiry $inquiry,
        Conversation $conversation,
        ?FranchiseApplication $application = null,
    ): SalesQuote {
        $existing = SalesQuote::withoutGlobalScopes()
            ->where('inquiry_id', $inquiry->getKey())
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        $orderType = $this->orderTypeForInquiryType((string) $inquiry->type);
        if ($orderType === null) {
            throw ValidationException::withMessages([
                'type' => 'This enquiry type cannot become a sales quote.',
            ]);
        }

        $currency = strtoupper((string) (
            $inquiry->company_id
                ? DB::table('companies')->whereKey($inquiry->company_id)->value('base_currency')
                : null
        ) ?: 'EUR');

        $quote = SalesQuote::create([
            'company_id' => $inquiry->company_id,
            'inquiry_id' => $inquiry->getKey(),
            'franchise_application_id' => $application?->getKey(),
            'conversation_id' => $conversation->getKey(),
            'customer_id' => $inquiry->getAttribute('customer_id'),
            'created_by' => auth()->id(),
            'order_type' => $orderType,
            'status' => 'submitted',
            'currency_code' => $currency,
            'exchange_rate' => '1.00000000',
            'subtotal' => '0.00',
            'shipping' => '0.00',
            'discount' => '0.00',
            'total' => '0.00',
            'line_items' => [],
            'notes' => $inquiry->message,
            'correlation_id' => $inquiry->correlation_id,
            'idempotency_key' => $inquiry->idempotency_key,
            'submitted_at' => now(),
            'version' => 1,
        ]);

        AuditTrail::record('sales_quote.submitted', $quote, null, [
            'quote_uuid' => (string) $quote->uuid,
            'order_type' => $quote->order_type,
            'inquiry_id' => $quote->inquiry_id,
            'conversation_id' => $quote->conversation_id,
            'correlation_id' => $quote->correlation_id,
        ]);

        return $quote;
    }

    public function updatePricing(SalesQuote $quote, array $changes): SalesQuote
    {
        return DB::transaction(function () use ($quote, $changes): SalesQuote {
            $locked = $this->lockVisible($quote);
            $this->assertVersion($locked, $changes['expected_version'] ?? null);

            $rawItems = array_key_exists('line_items', $changes)
                ? (array) $changes['line_items']
                : (array) $locked->line_items;
            $lineItems = [];
            $subtotal = '0.00';

            foreach ($rawItems as $item) {
                if (! is_array($item)) {
                    throw ValidationException::withMessages([
                        'line_items' => 'Each quote line must be an object.',
                    ]);
                }

                $productId = (int) ($item['product_id'] ?? 0);
                $variantId = (int) ($item['variant_id'] ?? $item['product_variant_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);
                if ($productId < 1 || $quantity < 1) {
                    throw ValidationException::withMessages([
                        'line_items' => 'Each quote line needs a product and a positive quantity.',
                    ]);
                }

                $product = $this->productForQuote($locked, $productId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'line_items' => 'A quote line references a product outside the selected company or an unpublished product.',
                    ]);
                }

                $variant = null;
                if ($variantId > 0) {
                    $variant = $this->variantForQuote($locked, $product, $variantId);
                    if (! $variant) {
                        throw ValidationException::withMessages([
                            'line_items' => 'A quote line references an invalid product variant.',
                        ]);
                    }
                }

                $unitPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== null && $item['unit_price'] !== ''
                    ? $item['unit_price']
                    : ($variant?->price ?? $product->price);
                $unitPrice = Money::round($unitPrice);
                if (Money::compare($unitPrice, '0.00') < 0) {
                    throw ValidationException::withMessages([
                        'line_items' => 'A negotiated unit price cannot be negative.',
                    ]);
                }

                $lineTotal = Money::multiply($unitPrice, $quantity);
                $subtotal = Money::add($subtotal, $lineTotal);
                $lineItems[] = [
                    'product_id' => $product->getKey(),
                    'variant_id' => $variant?->getKey(),
                    'name' => (string) $product->name,
                    'sku' => (string) ($variant?->sku ?: $product->sku),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $lineTotal,
                    'options' => is_array($item['options'] ?? null) ? $item['options'] : [],
                ];
            }

            $shipping = Money::round($changes['shipping'] ?? $locked->shipping ?? '0.00');
            $discount = Money::round($changes['discount'] ?? $locked->discount ?? '0.00');
            if (Money::compare($shipping, '0.00') < 0 || Money::compare($discount, '0.00') < 0) {
                throw ValidationException::withMessages([
                    'pricing' => 'Shipping and discount values cannot be negative.',
                ]);
            }

            $gross = Money::add($subtotal, $shipping);
            if (Money::compare($discount, $gross) > 0) {
                throw ValidationException::withMessages([
                    'discount' => 'The discount cannot exceed the quote value.',
                ]);
            }

            $currency = strtoupper(trim((string) ($changes['currency_code'] ?? $locked->currency_code ?? 'EUR')));
            if (! preg_match('/^[A-Z]{3}$/', $currency)) {
                throw ValidationException::withMessages([
                    'currency_code' => 'Currency must be a three-letter ISO code.',
                ]);
            }

            $exchangeRate = (string) ($changes['exchange_rate'] ?? $locked->exchange_rate ?? '1');
            if (! is_numeric($exchangeRate) || (float) $exchangeRate <= 0) {
                throw ValidationException::withMessages([
                    'exchange_rate' => 'Exchange rate must be greater than zero.',
                ]);
            }

            $total = Money::subtract($gross, $discount);
            $before = $locked->toArray();
            $locked->update([
                'line_items' => $lineItems,
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'discount' => $discount,
                'total' => $total,
                'currency_code' => $currency,
                'exchange_rate' => number_format((float) $exchangeRate, 8, '.', ''),
                'customer_id' => array_key_exists('customer_id', $changes) ? $changes['customer_id'] : $locked->customer_id,
                'notes' => array_key_exists('notes', $changes) ? $changes['notes'] : $locked->notes,
                'version' => (int) $locked->version + 1,
            ]);
            $after = $locked->fresh()->toArray();
            AuditTrail::record('sales_quote.priced', $locked, $before, $after);

            return $locked->fresh();
        });
    }

    public function transition(SalesQuote $quote, string $nextStatus, array $changes = []): SalesQuote
    {
        return DB::transaction(function () use ($quote, $nextStatus, $changes): SalesQuote {
            $locked = $this->lockVisible($quote);
            $this->assertVersion($locked, $changes['expected_version'] ?? null);
            $current = (string) $locked->status;

            $allowed = match ($nextStatus) {
                'approved' => ['submitted'],
                'rejected' => ['submitted'],
                'cancelled' => ['submitted', 'approved'],
                default => [],
            };

            if (! in_array($current, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => 'That quote status transition is not allowed.',
                ]);
            }

            if ($nextStatus === 'approved' && count((array) $locked->line_items) < 1) {
                throw ValidationException::withMessages([
                    'line_items' => 'A quote needs at least one priced line before approval.',
                ]);
            }

            $before = $locked->toArray();
            $note = trim((string) ($changes['note'] ?? ''));
            $updates = [
                'status' => $nextStatus,
                'version' => (int) $locked->version + 1,
            ];
            if ($nextStatus === 'approved') {
                $updates['approved_by'] = auth()->id();
                $updates['approved_at'] = now();
            } elseif ($nextStatus === 'rejected') {
                $updates['rejected_at'] = now();
            } elseif ($nextStatus === 'cancelled') {
                $updates['cancelled_at'] = now();
            }
            if ($note !== '') {
                $updates['notes'] = trim((string) $locked->notes)."\n\nDecision note: ".$note;
            }

            $locked->update($updates);
            AuditTrail::record('sales_quote.'.$nextStatus, $locked, $before, $locked->fresh()->toArray());

            return $locked->fresh();
        });
    }

    public function convert(SalesQuote $quote, string $conversionKey, ?int $expectedVersion = null): Order
    {
        return DB::transaction(function () use ($quote, $conversionKey, $expectedVersion): Order {
            $locked = $this->lockVisible($quote);

            if ((string) $locked->status === 'converted') {
                if ((string) $locked->conversion_key !== $conversionKey) {
                    abort(409, 'This quote has already been converted with another idempotency key.');
                }

                return Order::withoutGlobalScopes()->whereKey($locked->order_id)->firstOrFail();
            }

            $this->assertVersion($locked, $expectedVersion);
            if ((string) $locked->status !== 'approved') {
                throw ValidationException::withMessages([
                    'status' => 'Only an approved quote can become an order.',
                ]);
            }

            $lineItems = is_array($locked->line_items) ? $locked->line_items : [];
            if ($lineItems === []) {
                throw ValidationException::withMessages([
                    'line_items' => 'A quote needs at least one priced line before conversion.',
                ]);
            }

            $orderIdempotencyKey = 'quote-conversion-'.hash('sha256', $locked->uuid.'|'.$conversionKey);
            $existingOrder = Order::withoutGlobalScopes()
                ->where('idempotency_key', $orderIdempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existingOrder) {
                if ((int) $existingOrder->quote_id !== (int) $locked->getKey()) {
                    abort(409, 'The conversion idempotency key belongs to another order.');
                }

                return $existingOrder;
            }

            $preparedItems = [];
            $subtotal = '0.00';
            foreach ($lineItems as $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $variantId = (int) ($line['variant_id'] ?? 0);
                $quantity = (int) ($line['quantity'] ?? 0);
                if ($productId < 1 || $quantity < 1) {
                    throw ValidationException::withMessages([
                        'line_items' => 'A stored quote line is invalid and cannot be converted.',
                    ]);
                }

                $product = $this->productForQuote($locked, $productId, true);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'line_items' => 'A stored quote product is no longer available in this company.',
                    ]);
                }

                $variant = $variantId > 0 ? $this->variantForQuote($locked, $product, $variantId, true) : null;
                if ($variantId > 0 && ! $variant) {
                    throw ValidationException::withMessages([
                        'line_items' => 'A stored quote variant is no longer available.',
                    ]);
                }

                $stockable = $variant ?: $product;
                $tracksInventory = ! array_key_exists('track_inventory', $stockable->getAttributes())
                    || (bool) $stockable->getAttribute('track_inventory');
                $backorder = (bool) ($stockable->getAttribute('backorder') ?? false);
                if ($tracksInventory && (int) $stockable->stock < $quantity && ! $backorder) {
                    throw ValidationException::withMessages([
                        'inventory' => 'There is not enough inventory to convert this quote.',
                    ]);
                }

                $unitPrice = Money::round($line['unit_price'] ?? ($variant?->price ?? $product->price));
                $lineTotal = Money::multiply($unitPrice, $quantity);
                $subtotal = Money::add($subtotal, $lineTotal);
                if ($tracksInventory) {
                    $stockable->decrement('stock', $quantity);
                }

                $preparedItems[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $lineTotal,
                    'options' => is_array($line['options'] ?? null) ? $line['options'] : [],
                ];
            }

            $shipping = Money::round($locked->shipping ?? '0.00');
            $discount = Money::round($locked->discount ?? '0.00');
            $gross = Money::add($subtotal, $shipping);
            if (Money::compare($discount, $gross) > 0) {
                throw ValidationException::withMessages([
                    'discount' => 'The stored quote discount exceeds the converted value.',
                ]);
            }
            $total = Money::subtract($gross, $discount);
            if (Money::compare($total, $locked->total ?? '0.00') !== 0) {
                throw ValidationException::withMessages([
                    'pricing' => 'The stored quote total is inconsistent and must be repriced before conversion.',
                ]);
            }

            $inquiry = $locked->inquiry_id
                ? Inquiry::withoutGlobalScopes()->whereKey($locked->inquiry_id)->lockForUpdate()->first()
                : null;
            $inquiryMeta = is_array($inquiry?->meta) ? $inquiry->meta : [];
            $shippingAddress = data_get($inquiryMeta, 'shipping_address');
            if (! is_array($shippingAddress)) {
                $shippingAddress = array_filter([
                    'name' => $inquiry?->name,
                    'company' => $inquiry?->company,
                    'email' => $inquiry?->email,
                    'phone' => $inquiry?->phone,
                    'country' => data_get($inquiryMeta, 'country'),
                ], static fn ($value): bool => filled($value));
            }

            $correlationId = (string) ($locked->correlation_id ?: $inquiry?->correlation_id ?: Str::uuid());
            $order = Order::create([
                'company_id' => $locked->company_id,
                'user_id' => $locked->customer_id,
                'quote_id' => $locked->getKey(),
                'inquiry_id' => $locked->inquiry_id,
                'number' => $this->newOrderNumber(),
                'order_type' => $locked->order_type,
                'status' => 'approved',
                'payment_status' => 'pending',
                'fulfillment_status' => 'ready_to_ship',
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'discount' => $discount,
                'total' => $total,
                'currency' => $locked->currency_code,
                'currency_code' => $locked->currency_code,
                'exchange_rate' => $locked->exchange_rate,
                'shipping_method' => 'quote',
                'shipping_method_code' => 'quote',
                'payment_method' => 'manual',
                'email' => $inquiry?->email,
                'phone' => $inquiry?->phone,
                'shipping_address' => $shippingAddress,
                'notes' => $locked->notes,
                'idempotency_key' => $orderIdempotencyKey,
                'request_hash' => hash('sha256', json_encode([
                    'quote_uuid' => $locked->uuid,
                    'conversion_key' => $conversionKey,
                    'line_items' => $lineItems,
                    'total' => $total,
                ], JSON_UNESCAPED_SLASHES)),
                'correlation_id' => $correlationId,
                'version' => 1,
            ]);

            foreach ($preparedItems as $prepared) {
                /** @var Product $product */
                $product = $prepared['product'];
                /** @var ProductVariant|null $variant */
                $variant = $prepared['variant'];
                $orderItem = OrderItem::create([
                    'company_id' => $order->company_id,
                    'order_id' => $order->getKey(),
                    'product_id' => $product->getKey(),
                    'product_variant_id' => $variant?->getKey(),
                    'name' => $product->name,
                    'sku' => $variant?->sku ?: $product->sku,
                    'quantity' => $prepared['quantity'],
                    'unit_price' => $prepared['unit_price'],
                    'total' => $prepared['total'],
                    'options' => $prepared['options'],
                ]);

                $stockable = $variant ?: $product;
                $tracksInventory = ! array_key_exists('track_inventory', $stockable->getAttributes())
                    || (bool) $stockable->getAttribute('track_inventory');
                if ($tracksInventory) {
                    InventoryMovement::create([
                        'company_id' => $order->company_id,
                        'order_id' => $order->getKey(),
                        'product_id' => $product->getKey(),
                        'product_variant_id' => $variant?->getKey(),
                        'quantity' => -((int) $prepared['quantity']),
                        'type' => 'sale',
                        'reference' => $order->number,
                        'note' => 'Sales quote conversion',
                    ]);
                }
            }

            PaymentTransaction::create([
                'company_id' => $order->company_id,
                'order_id' => $order->getKey(),
                'provider' => 'manual',
                'amount' => $total,
                'currency' => $locked->currency_code,
                'status' => 'awaiting_payment',
                'payload' => [
                    'source' => 'sales_quote_conversion',
                    'quote_uuid' => $locked->uuid,
                    'order_type' => $locked->order_type,
                    'correlation_id' => $correlationId,
                ],
            ]);

            $before = $locked->toArray();
            $locked->update([
                'status' => 'converted',
                'order_id' => $order->getKey(),
                'conversion_key' => $conversionKey,
                'converted_at' => now(),
                'version' => (int) $locked->version + 1,
            ]);

            if ($inquiry) {
                $inquiryMeta['converted_quote_uuid'] = (string) $locked->uuid;
                $inquiryMeta['converted_order_number'] = (string) $order->number;
                $inquiry->update([
                    'status' => 'converted',
                    'meta' => $inquiryMeta,
                ]);
            }

            $conversation = $locked->conversation_id
                ? Conversation::withoutGlobalScopes()->whereKey($locked->conversation_id)->lockForUpdate()->first()
                : null;
            if ($conversation) {
                $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
                $metadata['quote_uuid'] = (string) $locked->uuid;
                $metadata['order_number'] = (string) $order->number;
                $conversation->update([
                    'order_id' => $order->getKey(),
                    'status' => 'open',
                    'metadata' => $metadata,
                ]);
            }

            AuditTrail::record('sales_quote.converted', $locked, $before, $locked->fresh()->toArray());
            AuditTrail::record('sales_order.created_from_quote', $order, null, [
                'order_number' => $order->number,
                'order_type' => $order->order_type,
                'quote_uuid' => $locked->uuid,
                'correlation_id' => $correlationId,
            ]);

            SalesQuoteConverted::dispatch($locked->fresh(), $order->fresh(['items', 'payments']), $correlationId);

            return $order->fresh(['items', 'payments']);
        });
    }

    private function lockVisible(SalesQuote $quote): SalesQuote
    {
        return $this->visibleQuery()
            ->whereKey($quote->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertVersion(SalesQuote $quote, mixed $expectedVersion): void
    {
        if ($expectedVersion !== null && (int) $quote->version !== (int) $expectedVersion) {
            abort(409, 'This quote changed while you were editing it. Refresh and try again.');
        }
    }

    private function productForQuote(SalesQuote $quote, int $productId, bool $lock = false): ?Product
    {
        $query = Product::withoutGlobalScopes()
            ->whereKey($productId)
            ->where('is_active', true)
            ->whereIn('status', Product::PUBLIC_STATUSES);
        $this->applyCompanyFilter($query, $quote->company_id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function variantForQuote(
        SalesQuote $quote,
        Product $product,
        int $variantId,
        bool $lock = false,
    ): ?ProductVariant {
        $query = ProductVariant::withoutGlobalScopes()
            ->whereKey($variantId)
            ->where('product_id', $product->getKey())
            ->where('is_active', true)
            ->whereIn('status', ['active', 'published']);
        $this->applyCompanyFilter($query, $quote->company_id);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function applyCompanyFilter(Builder $query, ?int $companyId): void
    {
        $companyId = $companyId ?: session('company_id');
        $table = $query->getModel()->getTable();
        if ($companyId) {
            $query->where($table.'.company_id', (int) $companyId);
        } else {
            $query->whereNull($table.'.company_id');
        }
    }

    private function newOrderNumber(): string
    {
        do {
            $number = 'ER-Q-'.strtoupper(Str::random(14));
        } while (Order::withoutGlobalScopes()->where('number', $number)->exists());

        return $number;
    }
}
