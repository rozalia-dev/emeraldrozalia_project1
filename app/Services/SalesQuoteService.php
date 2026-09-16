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
        // Public franchise applications are a Franchise Management aggregate,
        // not sales quotes. Franchise supply/product orders can still exist as a
        // distinct order type for approved partners, but must be created through
        // the franchise/order workflow rather than Franchise Apply.
        return match ($inquiryType) {
            'corporate-orders' => 'corporate',
            'bulk-orders' => 'bulk',
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
                ? DB::table('companies')->where('id', $inquiry->company_id)->value('base_currency')
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
                    continue;
                }

                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $unitPrice = Money::decimal($item['unit_price'] ?? '0');
                $lineTotal = Money::multiply($unitPrice, $quantity);
                $subtotal = Money::add($subtotal, $lineTotal);
                $lineItems[] = [
                    'product_id' => ! empty($item['product_id']) ? (int) $item['product_id'] : null,
                    'variant_id' => ! empty($item['variant_id']) ? (int) $item['variant_id'] : null,
                    'name' => trim((string) ($item['name'] ?? 'Custom item')) ?: 'Custom item',
                    'sku' => trim((string) ($item['sku'] ?? '')) ?: null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $shipping = Money::decimal($changes['shipping'] ?? $locked->shipping);
            $discount = Money::decimal($changes['discount'] ?? $locked->discount);
            $total = Money::subtract(Money::add($subtotal, $shipping), $discount);
            if (Money::compare($total, '0.00') < 0) {
                throw ValidationException::withMessages([
                    'discount' => 'Discount cannot make the quote total negative.',
                ]);
            }

            $before = $locked->toArray();
            $locked->update([
                'line_items' => $lineItems,
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'discount' => $discount,
                'total' => $total,
                'notes' => array_key_exists('notes', $changes) ? $changes['notes'] : $locked->notes,
                'expires_at' => array_key_exists('expires_at', $changes) ? $changes['expires_at'] : $locked->expires_at,
                'version' => $locked->version + 1,
            ]);

            AuditTrail::record('sales_quote.pricing_updated', $locked, $before, $locked->fresh()->toArray());

            return $locked->fresh();
        });
    }

    public function approve(SalesQuote $quote): SalesQuote
    {
        return DB::transaction(function () use ($quote): SalesQuote {
            $locked = $this->lockVisible($quote);
            abort_unless($locked->status === 'submitted', 422, 'Only submitted quotes can be approved.');
            abort_if(empty($locked->line_items), 422, 'Add at least one quoted item before approval.');
            abort_if(Money::compare($locked->total, '0.00') <= 0, 422, 'Quote total must be greater than zero.');

            $before = $locked->toArray();
            $locked->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'version' => $locked->version + 1,
            ]);

            AuditTrail::record('sales_quote.approved', $locked, $before, $locked->fresh()->toArray());

            return $locked->fresh();
        });
    }

    public function reject(SalesQuote $quote, ?string $reason = null): SalesQuote
    {
        return DB::transaction(function () use ($quote, $reason): SalesQuote {
            $locked = $this->lockVisible($quote);
            abort_unless(in_array($locked->status, ['submitted', 'approved'], true), 422, 'This quote cannot be rejected.');

            $before = $locked->toArray();
            $locked->update([
                'status' => 'rejected',
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'notes' => $reason ? trim(($locked->notes ? $locked->notes."\n\n" : '').'Rejection: '.$reason) : $locked->notes,
                'version' => $locked->version + 1,
            ]);

            AuditTrail::record('sales_quote.rejected', $locked, $before, $locked->fresh()->toArray());

            return $locked->fresh();
        });
    }

    public function cancel(SalesQuote $quote, ?string $reason = null): SalesQuote
    {
        return DB::transaction(function () use ($quote, $reason): SalesQuote {
            $locked = $this->lockVisible($quote);
            abort_unless(in_array($locked->status, ['submitted', 'approved'], true), 422, 'This quote cannot be cancelled.');

            $before = $locked->toArray();
            $locked->update([
                'status' => 'cancelled',
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'notes' => $reason ? trim(($locked->notes ? $locked->notes."\n\n" : '').'Cancellation: '.$reason) : $locked->notes,
                'version' => $locked->version + 1,
            ]);

            AuditTrail::record('sales_quote.cancelled', $locked, $before, $locked->fresh()->toArray());

            return $locked->fresh();
        });
    }

    public function convert(SalesQuote $quote): Order
    {
        return DB::transaction(function () use ($quote): Order {
            $locked = $this->lockVisible($quote);
            $locked->loadMissing(['inquiry', 'conversation', 'franchiseApplication', 'order']);

            if ($locked->order) {
                return $locked->order;
            }

            abort_unless($locked->status === 'approved', 422, 'Only approved quotes can be converted.');
            abort_if($locked->expires_at && $locked->expires_at->isPast(), 422, 'This quote has expired.');
            abort_unless(in_array($locked->order_type, self::ORDER_TYPES, true), 422, 'Unsupported order type.');
            abort_if(empty($locked->line_items), 422, 'A quote needs line items before conversion.');

            $inquiry = $locked->inquiry;
            abort_unless($inquiry, 422, 'The quote is missing its source enquiry.');

            $email = strtolower(trim((string) $inquiry->email));
            abort_if($email === '', 422, 'The source enquiry requires an email address.');

            $number = $this->nextOrderNumber();
            $order = Order::create([
                'user_id' => $locked->customer_id,
                'order_type' => $locked->order_type,
                'number' => $number,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'fulfillment_status' => 'unfulfilled',
                'subtotal' => $locked->subtotal,
                'shipping' => $locked->shipping,
                'discount' => $locked->discount,
                'total' => $locked->total,
                'currency_code' => $locked->currency_code,
                'exchange_rate' => $locked->exchange_rate,
                'email' => $email,
                'company_id' => $locked->company_id,
                'source' => 'sales_quote',
                'source_reference' => (string) $locked->uuid,
                'correlation_id' => $locked->correlation_id,
            ]);

            foreach ((array) $locked->line_items as $item) {
                $product = ! empty($item['product_id']) ? Product::find($item['product_id']) : null;
                $variant = ! empty($item['variant_id']) ? ProductVariant::find($item['variant_id']) : null;
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                $unitPrice = Money::decimal($item['unit_price'] ?? '0');

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant?->id,
                    'name' => trim((string) ($item['name'] ?? $product?->name ?? 'Custom item')) ?: 'Custom item',
                    'sku' => trim((string) ($item['sku'] ?? $variant?->sku ?? $product?->sku ?? '')) ?: null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => Money::multiply($unitPrice, $quantity),
                ]);

                if ($product && $product->track_inventory) {
                    $available = (int) ($variant?->stock ?? $product->stock);
                    abort_if($available < $quantity, 422, 'Insufficient stock for '.$product->name.'.');

                    $movement = InventoryMovement::create([
                        'product_id' => $product->id,
                        'product_variant_id' => $variant?->id,
                        'type' => 'out',
                        'quantity' => $quantity,
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                        'notes' => 'Sales quote conversion '.$locked->uuid,
                    ]);

                    $variant
                        ? $variant->decrement('stock', $quantity)
                        : $product->decrement('stock', $quantity);

                    AuditTrail::record('inventory.quote_conversion_out', $movement, null, $movement->toArray());
                }
            }

            $locked->paymentTransactions()
                ->whereNull('order_id')
                ->lockForUpdate()
                ->get()
                ->each(function (PaymentTransaction $payment) use ($order): void {
                    $payment->update(['order_id' => $order->id]);
                });

            $before = $locked->toArray();
            $locked->update([
                'status' => 'converted',
                'converted_by' => auth()->id(),
                'converted_at' => now(),
                'order_id' => $order->id,
                'version' => $locked->version + 1,
            ]);

            if ($locked->conversation) {
                $metadata = is_array($locked->conversation->metadata) ? $locked->conversation->metadata : [];
                $metadata['quote_uuid'] = (string) $locked->uuid;
                $metadata['quote_status'] = 'converted';
                $metadata['order_uuid'] = (string) $order->uuid;
                $metadata['order_number'] = $order->number;
                $locked->conversation->update([
                    'order_id' => $order->id,
                    'metadata' => $metadata,
                ]);
            }

            if ($locked->franchiseApplication) {
                $application = $locked->franchiseApplication;
                $applicationData = is_array($application->data) ? $application->data : [];
                $applicationData['order_uuid'] = (string) $order->uuid;
                $applicationData['order_number'] = $order->number;
                $application->update(['data' => $applicationData]);
            }

            AuditTrail::record('sales_quote.converted', $locked, $before, [
                'quote_uuid' => (string) $locked->uuid,
                'order_uuid' => (string) $order->uuid,
                'order_number' => $order->number,
                'order_type' => $order->order_type,
                'correlation_id' => $locked->correlation_id,
            ]);

            event(new SalesQuoteConverted($locked->fresh(), $order));

            return $order;
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
        if ($expectedVersion === null || $expectedVersion === '') {
            return;
        }

        if ((int) $expectedVersion !== (int) $quote->version) {
            throw ValidationException::withMessages([
                'expected_version' => 'This quote was updated by another user. Refresh and try again.',
            ]);
        }
    }

    private function nextOrderNumber(): string
    {
        $prefix = now()->format('Ymd');
        $last = Order::query()
            ->where('number', 'like', 'ER-'.$prefix.'-%')
            ->orderByDesc('number')
            ->lockForUpdate()
            ->value('number');
        $sequence = $last ? ((int) str($last)->afterLast('-')->toString()) + 1 : 1;

        return 'ER-'.$prefix.'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
