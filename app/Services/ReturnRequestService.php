<?php

namespace App\Services;

use App\Models\{Order, ReturnRequest};
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReturnRequestService
{
    private const OPEN_STATUSES = ['requested', 'approved', 'received', 'inspecting'];

    public function submit(Order $order, array $data): ReturnRequest
    {
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if (preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $idempotencyKey) !== 1) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'A valid idempotency key is required for a return request.',
            ]);
        }

        $requestHash = hash('sha256', (string) json_encode([
            'user_id' => auth()->id(),
            'order_id' => $order->getKey(),
            'type' => $data['type'],
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $return = null;

        try {
            DB::transaction(function () use (&$return, $order, $data, $idempotencyKey, $requestHash): void {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());
                if ((int) $lockedOrder->user_id !== (int) auth()->id()) {
                    abort(403);
                }

                $existing = ReturnRequest::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    if ((int) $existing->user_id !== (int) auth()->id() || (int) $existing->order_id !== (int) $lockedOrder->id) {
                        abort(409, 'The idempotency key is already used for another return request.');
                    }
                    if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                        abort(409, 'The idempotency key was already used for different return details.');
                    }
                    $return = $existing;
                    return;
                }

                if (! in_array($lockedOrder->status, ['shipped', 'completed'], true)) {
                    throw ValidationException::withMessages([
                        'order' => 'Returns and exchanges can be requested after an order has shipped.',
                    ]);
                }

                if (ReturnRequest::query()
                    ->where('user_id', auth()->id())
                    ->where('order_id', $lockedOrder->id)
                    ->whereIn('status', self::OPEN_STATUSES)
                    ->lockForUpdate()
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'order' => 'An open return or exchange already exists for this order.',
                    ]);
                }

                $return = $lockedOrder->returns()->create([
                    'user_id' => auth()->id(),
                    'number' => 'RET-'.Str::upper(Str::substr(str_replace('-', '', (string) Str::uuid()), 0, 12)),
                    'type' => $data['type'],
                    'reason' => $data['reason'],
                    'details' => $data['details'] ?? null,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'correlation_id' => $this->correlationId(),
                    'version' => 1,
                ]);
                AuditTrail::record('customer.return_requested', $return, null, $return->toArray());
            });
        } catch (QueryException $exception) {
            $existing = ReturnRequest::query()->where('idempotency_key', $idempotencyKey)->first();
            if (! $existing) {
                throw $exception;
            }
            if ((int) $existing->user_id !== (int) auth()->id() || (int) $existing->order_id !== (int) $order->id) {
                abort(409, 'The idempotency key is already used for another return request.');
            }
            if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                abort(409, 'The idempotency key was already used for different return details.');
            }
            $return = $existing;
        }

        return $return->fresh(['order']);
    }

    private function correlationId(): string
    {
        $candidate = app()->bound('request') ? request()->attributes->get('correlation_id') : null;

        return is_string($candidate) && Str::isUuid($candidate) ? $candidate : (string) Str::uuid();
    }
}
