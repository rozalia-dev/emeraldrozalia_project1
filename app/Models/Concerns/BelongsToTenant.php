<?php
namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $companyId = app()->bound('session') ? session('company_id') : null;
            if ($companyId) {
                $builder->where($builder->getModel()->getTable().'.company_id', (int) $companyId);
            }
        });

        static::creating(function (Model $model): void {
            $contextCompanyId = app()->bound('session') && session()->has('company_id')
                ? (int) session('company_id')
                : null;
            $parentCompanyId = static::resolveParentCompanyId($model);

            if ($model->company_id && $contextCompanyId && (int) $model->company_id !== $contextCompanyId) {
                throw new \Illuminate\Auth\Access\AuthorizationException('The record belongs to another company context.');
            }

            if ($model->company_id && $parentCompanyId && (int) $model->company_id !== $parentCompanyId) {
                throw new \Illuminate\Auth\Access\AuthorizationException('The record does not belong to the referenced company records.');
            }

            if ($model->company_id) {
                return;
            }

            if ($parentCompanyId && $contextCompanyId && $parentCompanyId !== $contextCompanyId) {
                throw new \Illuminate\Auth\Access\AuthorizationException('The record belongs to another company context.');
            }

            $model->company_id = $parentCompanyId ?: $contextCompanyId;
        });
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($query->getModel()->getTable().'.company_id', $companyId);
    }

    private static function resolveParentCompanyId(Model $model): ?int
    {
        $parentCompanyIds = [];

        foreach ([
            'product_id' => 'product',
            'product_variant_id' => 'variant',
            'order_id' => 'order',
            'content_page_id' => 'page',
            'banner_id' => 'banner',
            'media_asset_id' => 'asset',
            'product_media_id' => 'media',
            'primary_group_id' => 'primaryGroup',
            'franchise_application_id' => 'application',
            'conversation_id' => 'conversation',
            'product_spin_id' => 'spin',
            'try_on_asset_id' => 'asset',
        ] as $foreignKey => $relationName) {
            if (! $model->getAttribute($foreignKey) || ! method_exists($model, $relationName)) {
                continue;
            }

            $parent = $model->{$relationName}()->withoutGlobalScopes()->first();
            if ($parent?->company_id) {
                $parentCompanyIds[] = (int) $parent->company_id;
            }
        }

        $parentCompanyIds = array_values(array_unique($parentCompanyIds));
        if (count($parentCompanyIds) > 1) {
            throw new \Illuminate\Auth\Access\AuthorizationException('The record references records from multiple companies.');
        }
        if ($parentCompanyIds !== []) {
            return $parentCompanyIds[0];
        }

        if ($userId = $model->getAttribute('user_id')) {
            $companyId = DB::table('company_user')
                ->where('user_id', $userId)
                ->orderByDesc('is_default')
                ->orderBy('company_id')
                ->value('company_id');

            if ($companyId) {
                return (int) $companyId;
            }
        }

        if ($creatorId = $model->getAttribute('created_by')) {
            $companyId = DB::table('company_user')
                ->where('user_id', $creatorId)
                ->orderByDesc('is_default')
                ->orderBy('company_id')
                ->value('company_id');

            if ($companyId) {
                return (int) $companyId;
            }
        }

        return null;
    }
}
