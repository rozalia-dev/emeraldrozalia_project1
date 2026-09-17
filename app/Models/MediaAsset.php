<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MediaAsset extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'focal_point' => 'array',
            'crop' => 'array',
            'responsive_variants' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $asset): void {
            $asset->uuid ??= (string) Str::uuid();

            if (data_get($asset->metadata, 'source') === 'banner-upload') {
                $asset->approval_status = 'approved';
                $asset->approved_at ??= now();
                $asset->approved_by ??= auth()->id();
                $asset->active = true;
            }
        });
    }

    public function scopeApprovedPublic(Builder $query): Builder
    {
        return $query
            ->visibleToCurrentCompany()
            ->where('approval_status', 'approved')
            ->where('active', true);
    }

    public function scopeVisibleToCurrentCompany(Builder $query): Builder
    {
        $companyId = session('company_id');

        return $query
            ->withoutGlobalScope('tenant')
            ->where(function (Builder $visible) use ($companyId): void {
                if ($companyId) {
                    $visible->whereNull('company_id')->orWhere('company_id', (int) $companyId);
                } else {
                    $visible->whereNull('company_id');
                }
            })
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END');
    }

    public function isApprovedPublic(): bool
    {
        return $this->approval_status === 'approved'
            && (bool) $this->active
            && ! $this->trashed()
            && $this->isVisibleToCurrentCompany();
    }

    public function isVisibleToCurrentCompany(): bool
    {
        $companyId = session('company_id');

        return $companyId
            ? $this->company_id === null || (int) $this->company_id === (int) $companyId
            : $this->company_id === null;
    }

    /**
     * Global baseline assets are public to every tenant, while uploaded assets
     * remain limited to the current company. Route binding must apply the same
     * visibility rule as the public resolver instead of the tenant scope alone.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $query
            ->visibleToCurrentCompany()
            ->where($field ?: $this->getRouteKeyName(), $value);
    }
}
