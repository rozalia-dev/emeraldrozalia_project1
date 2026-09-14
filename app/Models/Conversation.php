<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'follow_up_at' => 'datetime',
            'consent_captured_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $row): string => $row->uuid ??= Str::uuid()->toString());
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class);
    }

    public function franchiseApplication(): BelongsTo
    {
        return $this->belongsTo(FranchiseApplication::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeForCurrentCompany(Builder $query): Builder
    {
        $companyId = session('company_id');
        if (! $companyId) {
            return $query;
        }

        $table = $query->getModel()->getTable();
        $query->withoutGlobalScope('tenant')->where(function (Builder $visible) use ($table, $companyId): void {
            $visible->where($table.'.company_id', (int) $companyId);

            // Global administrators may still resolve legacy conversations whose
            // tenant was not recoverable during the ownership backfill. New
            // records are always assigned by BelongsToTenant.
            if (auth()->user()?->is_admin) {
                $visible->orWhereNull($table.'.company_id');
            }
        });

        return $query;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        // Implicit binding can run before the appended web middleware has
        // established the selected company. Start without the tenant scope,
        // then apply the same explicit visibility rule used by admin actions.
        $query = static::query()
            ->withoutGlobalScope('tenant')
            ->where($field, $value);

        return $this->scopeForCurrentCompany($query)->first();
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $this->scopeForCurrentCompany(
            parent::resolveRouteBindingQuery($query, $value, $field),
        );
    }
}
