<?php

namespace App\Services;

use App\Models\{InventoryMovement, Order, OrderItem, PaymentTransaction, Product, ProductVariant};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderLifecycle
{
    public const ORDER_STATUSES = [
        'pending',
        'approved',
        'processing',
        'shipped',
        'completed',
        'cancelled',
        'refunded',
    ];

    public const PAYMENT_STATUSES = [
        'unpaid',
        'pending',
        'pay_on_delivery',
        'paid',
        'failed',
        'refunded',
    ];

    public const FULFILLMENT_STATUSES = [
        'pending',
        'on_hold',
        'picking',
        'packed',
        'ready_to_ship',
        'shipped',
        'delivered',
        'closed',
    ];

    private const ORDER_TRANSITIONS = [
        'pending' => ['approved', 'processing', 'cancelled', 'refunded'],
        'approved' => ['processing', 'cancelled', 'refunded'],
        'processing' => ['shipped', 'cancelled', 'refunded'],
        'shipped' => ['completed', 'refunded'],
        'completed' => ['refunded'],
        'cancelled' => [],
        'refunded' => [],
    ];

    private const PAYMENT_TRANSITIONS = [
        'unpaid' => ['pending', 'pay_on_delivery', 'paid', 'failed'],
        'pending' => ['pay_on_delivery', 'paid', 'failed'],
        'pay_on_delivery' => ['paid', 'failed'],
        'paid' => ['refunded'],
        'failed' => ['pending', 'paid'],
        'refunded' => [],
    ];

    private const FULFILLMENT_DEFAULTS = [
        'pending' => 'on_hold',
        'approved' => 'ready_to_ship',
        'processing' => 'picking',
        'shipped' => 'shipped',
        'completed' => 'delivered',
        'cancelled' => 'closed',
        'refunded' => 'closed',
    ];

    private const FULFILLMENT_TRANSITIONS = [
        'pending' => ['on_hold', 'picking', 'packed', 'ready_to_ship', 'shipped', 'delivered', 'closed'],
        'on_hold' => ['pending', 'picking', 'packed', 'ready_to_ship', 'shipped', 'delivered', 'closed'],
        'picking' => ['on_hold', 'packed', 'ready_to_ship', 'shipped', 'delivered', 'closed'],
        'packed' => ['on_hold', 'ready_to_ship', 'shipped', 'delivered', 'closed'],
        'ready_to_ship' => ['on_hold', 'shipped', 'delivered', 'closed'],
        'shipped' => ['delivered', 'closed'],
        'delivered' => ['closed'],
        'closed' => [],
    ];

    public function transition(Order $order, array $changes, ?string $transactionStatus = null): Order
    {
        return DB::transaction(function () use ($order, $changes, $transactionStatus): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $expectedVersion = $changes['expected_version'] ?? null;

            if ($expectedVersion !== null && (int) $locked->version !== (int) $expectedVersion) {
                abort(409, 'This order changed while you were editing it. Refresh and try again.');
            }

            $nextStatus = (string) $changes['status'];
            $nextPayment = (string) $changes['payment_status'];
            $currentStatus = (string) $locked->status;
            $currentPayment = (string) $locked->payment_status;
            $currentFulfillment = (string) ($locked->fulfillment_status ?: 'pending');

            $this->assertTransition(
                $currentStatus,
                $nextStatus,
                self::ORDER_TRANSITIONS,
                'status',
                'That order status transition is not allowed.',
            );
            $this->assertTransition(
                $currentPayment,
                $nextPayment,
                self::PAYMENT_TRANSITIONS,
                'payment_status',
                'That payment status transition is not allowed.',
            );

            $requestedFulfillment = array_key_exists('fulfillment_status', $changes)
                && $changes['fulfillment_status'] !== null
                && $changes['fulfillment_status'] !== ''
                ? (string) $changes['fulfillment_status']
                : null;
            $fulfillment = $nextStatus !== $currentStatus && ($requestedFulfillment === null || $requestedFulfillment === $currentFulfillment)
                    ? (self::FULFILLMENT_DEFAULTS[$nextStatus] ?? $locked->fulfillment_status)
                    : ($requestedFulfillment ?: $currentFulfillment);

            if ($fulfillment !== null && ! in_array($fulfillment, self::FULFILLMENT_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'fulfillment_status' => 'That fulfillment status is not supported.',
                ]);
            }
            $this->assertTransition(
                $currentFulfillment,
                (string) $fulfillment,
                self::FULFILLMENT_TRANSITIONS,
                'fulfillment_status',
                'That fulfillment status transition is not allowed.',
            );

            $before = $locked->toArray();
            $changed = $nextStatus !== $currentStatus
                || $nextPayment !== $currentPayment
                || $fulfillment !== $currentFulfillment;
            $nextVersion = (int) $locked->version + ($changed ? 1 : 0);

            $locked->update([
                'status' => $nextStatus,
                'payment_status' => $nextPayment,
                'fulfillment_status' => $fulfillment,
                'version' => $nextVersion,
            ]);

            if ($nextPayment !== $currentPayment) {
                $this->recordPaymentState(
                    $locked,
                    $currentPayment,
                    $nextPayment,
                    $transactionStatus ?: $nextPayment,
                    $changes,
                    $nextVersion,
                );
            }

            if ($this->shouldReleaseInventory($currentStatus, $nextStatus)) {
                $this->releaseInventory($locked, $nextStatus === 'refunded' ? 'refund' : 'cancellation');
            }

            $after = $locked->fresh()->toArray();
            $after['_transition'] = [
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'from_payment_status' => $currentPayment,
                'to_payment_status' => $nextPayment,
                'version' => $nextVersion,
                'note' => $changes['transition_note'] ?? null,
            ];
            AuditTrail::record('admin.order_updated', $locked, $before, $after);

            return $locked->fresh(['items', 'payments']);
        });
    }

    private function recordPaymentState(
        Order $order,
        string $previousStatus,
        string $orderStatus,
        string $transactionStatus,
        array $changes,
        int $orderVersion,
    ): void {
        $transaction = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->latest('id')
            ->lockForUpdate()
            ->first();
        $payload = is_array($transaction?->payload) ? $transaction->payload : [];
        $history = is_array($payload['status_history'] ?? null) ? $payload['status_history'] : [];
        $history[] = [
            'from_order_status' => $previousStatus,
            'to_order_status' => $orderStatus,
            'transaction_status' => $transactionStatus,
            'note' => $changes['transition_note'] ?? null,
            'order_version' => $orderVersion,
            'recorded_at' => now()->toIso8601String(),
            'source' => 'order_lifecycle',
        ];
        $payload['status_history'] = $history;
        $payload['last_order_status'] = $orderStatus;
        $payload['last_transition_note'] = $changes['transition_note'] ?? null;

        if ($transaction) {
            $transaction->update([
                'status' => $transactionStatus,
                'payload' => $payload,
            ]);

            return;
        }

        PaymentTransaction::create([
            'order_id' => $order->id,
            'provider' => $order->payment_method ?: 'manual',
            'amount' => $order->total,
            'currency' => $order->currency ?: 'EUR',
            'status' => $transactionStatus,
            'payload' => [
                ...$payload,
                'source' => 'order_lifecycle',
                'order_type' => $order->order_type,
                'previous_order_payment_status' => $previousStatus,
            ],
        ]);
    }

    private function shouldReleaseInventory(string $currentStatus, string $nextStatus): bool
    {
        return in_array($nextStatus, ['cancelled', 'refunded'], true)
            && ! in_array($currentStatus, ['cancelled', 'refunded'], true);
    }

    private function releaseInventory(Order $order, string $reason): void
    {
        if ($order->inventory_released_at !== null) {
            return;
        }

        $items = OrderItem::query()
            ->where('order_id', $order->id)
            ->orderByRaw('COALESCE(product_variant_id, product_id), id')
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            $stockable = $item->product_variant_id
                ? ProductVariant::query()->whereKey($item->product_variant_id)->lockForUpdate()->first()
                : Product::query()->whereKey($item->product_id)->lockForUpdate()->first();

            if (! $stockable) {
                throw ValidationException::withMessages([
                    'inventory' => 'Inventory could not be released because an ordered stock item is missing.',
                ]);
            }

            $quantity = (int) $item->quantity;
            if ($quantity < 1) {
                continue;
            }

            $stockable->increment('stock', $quantity);
            InventoryMovement::create([
                'company_id' => $order->company_id,
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $quantity,
                'type' => 'restock',
                'reference' => $order->number,
                'note' => ucfirst($reason).' inventory release',
            ]);
        }

        $order->update([
            'inventory_released_at' => now(),
            'inventory_release_reason' => $reason,
        ]);
    }

    private function assertTransition(
        string $current,
        string $next,
        array $transitions,
        string $field,
        string $message,
    ): void {
        if ($current === $next) {
            return;
        }

        if (! in_array($next, $transitions[$current] ?? [], true)) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
