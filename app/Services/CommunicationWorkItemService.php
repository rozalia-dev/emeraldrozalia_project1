<?php

namespace App\Services;

use App\Models\CommunicationAction;
use App\Models\CommunicationAlert;
use App\Models\Conversation;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CommunicationWorkItemService
{
    public function query(string $section): Builder
    {
        $model = $this->modelClass($section);
        $query = $model::query()->withoutGlobalScope('tenant');
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

    public function find(string $section, string $identifier): Model
    {
        return $this->query($section)
            ->where(function (Builder $builder) use ($identifier): void {
                $builder->where('uuid', $identifier);
                if (ctype_digit($identifier)) {
                    $builder->orWhereKey((int) $identifier);
                }
            })
            ->firstOrFail();
    }

    public function create(string $section, array $data): Model
    {
        $model = $this->modelClass($section);

        return DB::transaction(function () use ($section, $model, $data): Model {
            $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
            $requestHash = $this->requestHash($section, $data);

            if ($idempotencyKey !== '') {
                $existing = $model::withoutGlobalScopes()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if (! $this->query($section)->whereKey($existing->getKey())->exists()) {
                        abort(409, 'This idempotency key belongs to another company context.');
                    }
                    if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                        abort(409, 'This idempotency key was already used for a different work item.');
                    }

                    return $existing;
                }
            }

            $alert = $section === 'alerts-notifications';
            $metadata = $this->metadata($data);
            $relations = $this->resolveRelations($data);
            $attributes = [
                'reference' => filled($data['reference'] ?? null) ? $data['reference'] : null,
                'title' => $data['title'],
                'description' => $data['description'] ?? ($data['notes'] ?? null),
                'category' => $data['category'] ?? null,
                'source' => $data['source'] ?? null,
                'entity' => $data['entity'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'status' => $data['status'],
                'company_id' => $data['company_id'] ?? session('company_id'),
                'record_date' => $data['record_date'] ?? now()->toDateString(),
                'due_at' => $data['due_at'] ?? null,
                'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                'request_hash' => $requestHash,
                'data' => $metadata,
                'created_by' => auth()->id(),
                'version' => 1,
                ...$relations,
            ];
            if ($alert) {
                $attributes['type'] = $data['type'] ?? null;
                $attributes['severity'] = $data['severity'] ?? 'low';
            } else {
                $attributes['amount'] = $data['amount'] ?? null;
            }
            $record = $model::create($attributes);

            if (blank($record->reference)) {
                $record->update(['reference' => $this->referenceFor($section, $record->getKey())]);
            }

            AuditTrail::record('communication.'.$section.'.created', $record, null, [
                'uuid' => (string) $record->uuid,
                'reference' => (string) $record->reference,
                'status' => (string) $record->status,
                'correlation_id' => data_get($metadata, 'correlation_id'),
            ]);

            return $record->fresh();
        });
    }

    public function update(string $section, string $identifier, array $data): Model
    {
        return DB::transaction(function () use ($section, $identifier, $data): Model {
            $record = $this->find($section, $identifier);
            $record = $this->query($section)
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertVersion($record, $data['expected_version'] ?? null);

            if (array_key_exists('status', $data) && (string) $data['status'] !== (string) $record->status) {
                throw ValidationException::withMessages([
                    'status' => 'Use the explicit action endpoint to change a work-item status.',
                ]);
            }

            $metadata = array_merge((array) $record->data, $this->metadata($data));
            $relations = $this->resolveRelations($data, $record);
            $isAlert = $section === 'alerts-notifications';
            $attributes = [
                'reference' => array_key_exists('reference', $data) ? ($data['reference'] ?: null) : $record->reference,
                'title' => $data['title'],
                'description' => $data['description'] ?? ($data['notes'] ?? $record->description),
                'category' => $data['category'] ?? $record->category,
                'source' => $data['source'] ?? $record->source,
                'entity' => $data['entity'] ?? $record->entity,
                'priority' => $data['priority'] ?? $record->priority,
                'status' => $record->status,
                'record_date' => $data['record_date'] ?? $record->record_date,
                'due_at' => array_key_exists('due_at', $data) ? ($data['due_at'] ?: null) : $record->due_at,
                'data' => $metadata,
                'version' => (int) $record->version + 1,
                ...$relations,
            ];
            if ($isAlert) {
                $attributes['type'] = $data['type'] ?? $record->type;
                $attributes['severity'] = $data['severity'] ?? $record->severity;
            } else {
                $attributes['amount'] = $data['amount'] ?? $record->amount;
            }
            $record->update($attributes);
            AuditTrail::record('communication.'.$section.'.updated', $record, null, [
                'uuid' => (string) $record->uuid,
                'version' => (int) $record->version,
            ]);

            return $record->fresh();
        });
    }

    public function transition(string $section, string $identifier, string $action, array $data): Model
    {
        return DB::transaction(function () use ($section, $identifier, $action, $data): Model {
            $record = $this->find($section, $identifier);
            $record = $this->query($section)
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $metadata = (array) $record->data;
            $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
            $lastKey = (string) data_get($metadata, 'last_action_idempotency_key', '');
            $lastAction = (string) data_get($metadata, 'last_action', '');

            if ($idempotencyKey !== '' && hash_equals($lastKey, $idempotencyKey) && $lastAction === $action) {
                return $record->fresh();
            }

            $this->assertVersion($record, $data['expected_version'] ?? null);
            $alert = $section === 'alerts-notifications';
            $allowed = $alert
                ? match ($action) {
                    'acknowledge' => ['unread', 'in-progress', 'escalated'],
                    'resolve' => ['unread', 'in-progress', 'acknowledged', 'escalated'],
                    default => [],
                }
                : match ($action) {
                    'complete' => ['pending', 'in-progress', 'overdue'],
                    'reopen' => ['completed', 'cancelled'],
                    default => [],
                };
            if (! in_array((string) $record->status, $allowed, true)) {
                if ($lastKey !== '' && $idempotencyKey !== '' && ! hash_equals($lastKey, $idempotencyKey)) {
                    abort(409, 'This work item has already processed another action key.');
                }

                throw ValidationException::withMessages([
                    'status' => 'That work-item transition is not allowed from its current status.',
                ]);
            }

            $nextStatus = $alert
                ? ['acknowledge' => 'acknowledged', 'resolve' => 'resolved'][$action]
                : ['complete' => 'completed', 'reopen' => 'pending'][$action];
            $now = now();
            $metadata['last_action'] = $action;
            $metadata['last_action_idempotency_key'] = $idempotencyKey;
            $metadata['last_action_at'] = $now->toISOString();
            $updates = [
                'status' => $nextStatus,
                'data' => $metadata,
                'version' => (int) $record->version + 1,
            ];

            if ($action === 'complete') {
                $updates['completed_at'] = $now;
                if ($record->created_at) {
                    $metadata['completion_seconds'] = $record->created_at->diffInSeconds($now);
                    $updates['data'] = $metadata;
                }
            } elseif ($action === 'reopen') {
                $updates['completed_at'] = null;
            } elseif ($action === 'acknowledge') {
                $updates['acknowledged_at'] = $now;
                if ($record->created_at) {
                    $metadata['response_seconds'] = $record->created_at->diffInSeconds($now);
                    $updates['data'] = $metadata;
                }
            } elseif ($action === 'resolve') {
                $updates['resolved_at'] = $now;
            }

            $before = $record->toArray();
            $record->update($updates);
            AuditTrail::record('communication.'.$section.'.'.$action, $record, $before, $record->fresh()->toArray());

            return $record->fresh();
        });
    }

    public function destroy(string $section, string $identifier): void
    {
        DB::transaction(function () use ($section, $identifier): void {
            $record = $this->find($section, $identifier);
            $record = $this->query($section)
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $before = $record->toArray();
            $record->delete();
            AuditTrail::record('communication.'.$section.'.trashed', $record, $before, [
                'uuid' => (string) $record->uuid,
                'deleted_at' => $record->deleted_at?->toISOString(),
            ]);
        });
    }

    private function modelClass(string $section): string
    {
        return match ($section) {
            'action-follow-ups' => CommunicationAction::class,
            'alerts-notifications' => CommunicationAlert::class,
            default => throw new \InvalidArgumentException('Unsupported communication work-item section.'),
        };
    }

    private function metadata(array $data): array
    {
        $keys = [
            'description', 'notes', 'category', 'type', 'priority', 'assigned_to_name',
            'entity', 'source', 'due_at', 'severity', 'correlation_id',
            'conversation_uuid', 'order_uuid',
        ];

        $metadata = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $metadata[$key] = $data[$key];
            }
        }

        return $metadata;
    }

    private function resolveRelations(array $data, ?Model $record = null): array
    {
        $relations = [];

        $conversation = $this->relatedModel(
            Conversation::class,
            $data['conversation_id'] ?? null,
            $data['conversation_uuid'] ?? null,
        );
        if ($conversation) {
            $this->assertRelatedCompany($conversation->company_id, $data['company_id'] ?? $record?->company_id);
            $relations['conversation_id'] = $conversation->getKey();
        } elseif ($record && array_key_exists('conversation_id', $data)) {
            $relations['conversation_id'] = null;
        }

        $order = $this->relatedModel(
            Order::class,
            $data['order_id'] ?? null,
            $data['order_uuid'] ?? null,
        );
        if ($order) {
            $this->assertRelatedCompany($order->company_id, $data['company_id'] ?? $record?->company_id);
            $relations['order_id'] = $order->getKey();
        } elseif ($record && array_key_exists('order_id', $data)) {
            $relations['order_id'] = null;
        }

        return $relations;
    }

    private function relatedModel(string $modelClass, mixed $id, mixed $uuid): ?Model
    {
        if (! filled($id) && ! filled($uuid)) {
            return null;
        }

        $query = $modelClass::query();
        $identifierColumn = $modelClass === Order::class ? 'public_uuid' : 'uuid';
        $model = filled($id)
            ? $query->whereKey((int) $id)->firstOrFail()
            : $query->where($identifierColumn, (string) $uuid)->firstOrFail();

        if (filled($id) && filled($uuid) && (string) $model->{$identifierColumn} !== (string) $uuid) {
            abort(422, 'The related record identifiers do not match.');
        }

        return $model;
    }

    private function assertRelatedCompany(mixed $relatedCompanyId, mixed $companyId): void
    {
        if (filled($relatedCompanyId) && filled($companyId) && (int) $relatedCompanyId !== (int) $companyId) {
            abort(409, 'The work item relation belongs to another company context.');
        }
    }

    private function requestHash(string $section, array $data): string
    {
        unset($data['idempotency_key'], $data['expected_version']);

        return hash('sha256', $section.'|'.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function assertVersion(Model $record, mixed $expectedVersion): void
    {
        if ($expectedVersion !== null && (int) $record->version !== (int) $expectedVersion) {
            abort(409, 'This work item changed while you were editing it. Refresh and try again.');
        }
    }

    private function referenceFor(string $section, int $id): string
    {
        return ($section === 'alerts-notifications' ? 'ALT' : 'ACT')
            .'-'.now()->format('ymd').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }
}
