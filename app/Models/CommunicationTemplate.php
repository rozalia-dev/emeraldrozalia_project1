<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommunicationTemplate extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'correlation_id' => 'string',
            'version' => 'integer',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $template): void {
            $template->uuid ??= Str::uuid()->toString();
            $template->version ??= 1;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getTitleAttribute(): string
    {
        return (string) ($this->attributes['name'] ?? '');
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['name'] = $value;
    }

    public function getReferenceAttribute(): string
    {
        $compactUuid = str_replace('-', '', (string) ($this->attributes['uuid'] ?? ''));

        return 'TPL-'.Str::upper(Str::substr($compactUuid, 0, 10));
    }

    public function getRecordDateAttribute(): mixed
    {
        return $this->created_at?->toDate();
    }

    public function getAmountAttribute(): ?int
    {
        return null;
    }

    public function getDataAttribute(): array
    {
        $variables = is_array($this->variables) ? $this->variables : [];

        return array_merge([
            'subject' => $this->subject,
            'channel' => Str::headline((string) $this->channel),
            'body' => $this->body,
        ], $variables);
    }

    public function getPublicUuidAttribute(): ?string
    {
        return $this->uuid;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
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
}
