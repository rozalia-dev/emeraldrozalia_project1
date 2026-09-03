<?php
namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (app()->runningInConsole()) return;
            $companyId=session('company_id');
            if ($companyId) $builder->where($builder->getModel()->getTable().'.company_id',(int) $companyId);
        });

        static::creating(function ($model): void {
            if (!$model->company_id && session()->has('company_id')) $model->company_id=(int) session('company_id');
        });
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($this->getTable().'.company_id',$companyId);
    }
}
