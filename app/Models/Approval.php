<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Approval extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    public const STATUSES = ['pending', 'in-progress', 'approved', 'rejected', 'escalated', 'cancelled'];

    protected $table = 'approvals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'due_at' => 'datetime',
            'record_date' => 'date',
            'decided_at' => 'datetime',
            'correlation_id' => 'string',
            'version' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $approval): void {
            $approval->uuid ??= Str::uuid()->toString();
            $approval->version ??= 1;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForCurrentCompany(Builder $query): Builder
    {
        $companyId = session('company_id');

        return $companyId
            ? $query->where($query->getModel()->getTable().'.company_id', (int) $companyId)
            : $query;
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $this->scopeForCurrentCompany(
            parent::resolveRouteBindingQuery($query, $value, $field),
        );
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function getTitleAttribute(): string
    {
        return trim((string) ($this->attributes['title'] ?? '')) ?: 'Approval request';
    }

    public function getReferenceAttribute(): string
    {
        $reference = trim((string) ($this->attributes['reference'] ?? ''));
        if ($reference !== '') {
            return $reference;
        }

        $compactUuid = str_replace('-', '', (string) ($this->attributes['uuid'] ?? ''));

        return $compactUuid !== ''
            ? 'APR-'.Str::upper(Str::substr($compactUuid, 0, 10))
            : 'APR-'.$this->getKey();
    }

    public function getRecordDateAttribute(): ?Carbon
    {
        $raw = $this->attributes['record_date'] ?? null;

        return $raw ? Carbon::parse($raw) : $this->created_at?->toDate();
    }

    public function getAmountAttribute(): ?int
    {
        return null;
    }

    public function getDataAttribute(): array
    {
        $requestedBy = $this->relationLoaded('requestedBy') ? $this->getRelation('requestedBy') : null;
        $approver = $this->relationLoaded('approver') ? $this->getRelation('approver') : null;
        $data = is_array($this->metadata) ? $this->metadata : [];

        return array_merge($data, array_filter([
            'type' => $this->request_type,
            'description' => $this->description,
            'priority' => $this->priority,
            'requested_by' => $this->requester_name ?: $requestedBy?->name,
            'approver' => $this->approver_name ?: $approver?->name,
            'entity' => $this->entity,
            'entity_type' => $this->entity_type,
            'entity_uuid' => $this->entity_uuid,
            'source' => $this->source,
            'due_at' => $this->due_at?->toIso8601String(),
        ], static fn ($value): bool => $value !== null && $value !== ''));
    }

    public function getPublicUuidAttribute(): ?string
    {
        return $this->uuid;
    }
}
