<?php

namespace App\Services;

use App\Models\{Order, PaymentTransaction};
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
        'pending' => ['approved', 'processing', 'cancelled'],
        'approved' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
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

    public function transition(Order $order, array $changes): Order
    {
        return DB::transaction(function () use ($order, $changes): Order {
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
                PaymentTransaction::create([
                    'order_id' => $locked->id,
                    'provider' => $locked->payment_method ?: 'manual',
                    'amount' => $locked->total,
                    'currency' => $locked->currency ?: 'EUR',
                    'status' => $nextPayment,
                    'payload' => [
                        'source' => 'admin_order_lifecycle',
                        'order_type' => $locked->order_type,
                        'previous_status' => $currentPayment,
                        'transition_note' => $changes['transition_note'] ?? null,
                        'order_version' => $nextVersion,
                    ],
                ]);
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
