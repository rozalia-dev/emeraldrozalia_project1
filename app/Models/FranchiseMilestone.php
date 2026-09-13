<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class FranchiseMilestone extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'franchise_milestones';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'due_on' => 'date',
            'completed_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $milestone): string => $milestone->uuid ??= Str::uuid()->toString());
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(FranchiseApplication::class, 'franchise_application_id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
