<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AutomationRule extends Model
{
    use BelongsToTenant;

    public const EVENTS = [
        'order.created',
        'order.status.changed',
        'payment.updated',
        'quote.converted',
        'communication.conversation.created',
        'communication.conversation.updated',
        'communication.template.updated',
        'communication.message.failed',
        'approval.changed',
        'franchise.application.created',
        'franchise.store.status.changed',
    ];

    protected $guarded = [];

    protected $casts = [
        'conditions' => 'array',
        'actions' => 'array',
        'enabled' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $rule): string => $rule->uuid ??= Str::uuid()->toString());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
