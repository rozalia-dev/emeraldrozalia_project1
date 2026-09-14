<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CommunicationAction extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $table = 'communication_actions';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'record_date' => 'date',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'deleted_at' => 'datetime',
            'amount' => 'decimal:2',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $record): string => $record->uuid ??= Str::uuid()->toString());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForCurrentCompany(Builder $query): Builder
    {
        $companyId = session('company_id');
        if (! $companyId) {
            return $query;
        }

        $table = $query->getModel()->getTable();

        return $query->withoutGlobalScope('tenant')->where(function (Builder $visible) use ($table, $companyId): void {
            $visible->where($table.'.company_id', (int) $companyId);
            if (auth()->user()?->is_admin) {
                $visible->orWhereNull($table.'.company_id');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
