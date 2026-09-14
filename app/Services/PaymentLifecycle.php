<?php

namespace App\Services;

use App\Models\{Order, PaymentTransaction};
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PaymentLifecycle
{
    private const ACTIONS = ['capture', 'refund', 'cancel'];

    public function act(PaymentTransaction $payment, array $changes): PaymentTransaction
    {
        fwrite(STDERR, "PAYMENT-ACT-1\n");
        $action = (string) ($changes['action'] ?? '');
        if (! in_array($action, self::ACTIONS, true)) {
            throw ValidationException::withMessages(['action' => 'That payment action is not supported.']);
        }

        return DB::transaction(function () use ($payment, $changes, $action): PaymentTransaction {
            fwrite(STDERR, "PAYMENT-ACT-2\n");
            $paymentRecord = PaymentTransaction::query()
                ->whereKey($payment->getKey())
                ->firstOrFail();
            fwrite(STDERR, "PAYMENT-ACT-3\n");
            $order = Order::query()
                ->whereKey($paymentRecord->order_id)
                ->lockForUpdate()
                ->firstOrFail();
            fwrite(STDERR, "PAYMENT-ACT-4\n");
            $lockedPayment = PaymentTransaction::query()
                ->whereKey($paymentRecord->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            fwrite(STDERR, "PAYMENT-ACT-5\n");
            $latestPaymentId = PaymentTransaction::query()
                ->where('order_id', $order->id)
                ->latest('id')
                ->value('id');

            if ((int) $latestPaymentId !== (int) $lockedPayment->id) {
                throw ValidationException::withMessages([
                    'action' => 'Only the current payment record for an order can be changed.',
                ]);
            }

            $status = strtolower((string) $lockedPayment->status);
            $this->assertActionAllowed($action, $status);
            $before = $lockedPayment->toArray();
            $transitionNote = $changes['transition_note'] ?? null;
            $expectedVersion = $changes['expected_version'] ?? null;

            if ($action === 'refund') {
                return $this->refund($lockedPayment, $order, $changes, $before, $transitionNote, $expectedVersion);
            }

            $targetPaymentStatus = $action === 'capture' ? 'paid' : 'failed';
            $targetOrderStatus = $action === 'capture' ? $order->status : 'cancelled';
            $transactionStatus = $action === 'capture' ? 'paid' : 'cancelled';

            app(OrderLifecycle::class)->transition(
                $order,
                [
                    'status' => $targetOrderStatus,
                    'payment_status' => $targetPaymentStatus,
                    'expected_version' => $expectedVersion,
                    'transition_note' => $transitionNote,
                ],
                $transactionStatus,
            );
            fwrite(STDERR, "PAYMENT-ACT-6\n");

            $lockedPayment->refresh();
            $payload = $this->actionPayload($lockedPayment->payload, $action, $transitionNote);
            $lockedPayment->update([
                'status' => $transactionStatus,
                'payload' => $payload,
            ]);
            AuditTrail::record('admin.payment_'.$action, $lockedPayment, $before, $lockedPayment->fresh()->toArray());

            return $lockedPayment->fresh(['order']);
        });
    }

    private function refund(
        PaymentTransaction $payment,
        Order $order,
        array $changes,
        array $before,
        ?string $transitionNote,
        mixed $expectedVersion,
    ): PaymentTransaction {
        $payload = is_array($payment->payload) ? $payment->payload : [];
        $alreadyRefunded = Money::round($payload['refunded_amount'] ?? '0.00');
        $remaining = Money::subtract($payment->amount, $alreadyRefunded);
        $requested = array_key_exists('refund_amount', $changes) && $changes['refund_amount'] !== null && $changes['refund_amount'] !== ''
            ? Money::round($changes['refund_amount'])
            : $remaining;

        if (Money::compare($requested, '0.00') <= 0 || Money::compare($requested, $remaining) > 0) {
            throw ValidationException::withMessages([
                'refund_amount' => 'The refund amount must be greater than zero and no more than the remaining payment amount.',
            ]);
        }

        $fullRefund = Money::compare($requested, $remaining) === 0;
        if ($fullRefund) {
            app(OrderLifecycle::class)->transition(
                $order,
                [
                    'status' => 'refunded',
                    'payment_status' => 'refunded',
                    'expected_version' => $expectedVersion,
                    'transition_note' => $transitionNote,
                ],
                'refunded',
            );
            $payment->refresh();
        }

        $payment->refresh();
        $payload = $this->actionPayload($payment->payload, 'refund', $transitionNote);
        $payload['refunded_amount'] = Money::add($alreadyRefunded, $requested);
        $payload['last_refund_amount'] = $requested;
        $payment->update([
            'status' => $fullRefund ? 'refunded' : 'partially_refunded',
            'payload' => $payload,
        ]);
        AuditTrail::record('admin.payment_refund', $payment, $before, $payment->fresh()->toArray());

        return $payment->fresh(['order']);
    }

    private function assertActionAllowed(string $action, string $status): void
    {
        $allowed = match ($action) {
            'capture' => ['pending', 'awaiting_payment', 'pay_on_delivery'],
            'refund' => ['paid', 'captured', 'partially_refunded'],
            'cancel' => ['pending', 'awaiting_payment', 'pay_on_delivery'],
        };

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'action' => 'This payment cannot make that state transition.',
            ]);
        }
    }

    private function actionPayload(mixed $current, string $action, ?string $note): array
    {
        $payload = is_array($current) ? $current : [];
        $payload['last_action'] = $action;
        $payload['last_action_at'] = now()->toIso8601String();
        $payload['last_action_note'] = $note;
        $payload[$action === 'refund' ? 'refunded_at' : $action === 'capture' ? 'captured_at' : 'cancelled_at'] = $payload['last_action_at'];
        $payload['reconciliation_status'] = $action === 'cancel' ? 'unreconciled' : 'reconciled';

        return $payload;
    }
}
