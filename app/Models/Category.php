<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'status',
        'is_active',
        'is_visible',
        'sort_order',
        'meta_title',
        'meta_description',
        'seo',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_visible' => 'boolean',
            'seo' => 'array',
            'translations' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('storefrontVisibility', function (Builder $builder): void {
            if (app()->runningInConsole() || request()->is('admin/*')) {
                return;
            }

            $builder->where($builder->getModel()->getTable().'.status', 'active')
                ->where($builder->getModel()->getTable().'.is_active', true)
                ->where($builder->getModel()->getTable().'.is_visible', true);
        });

        static::creating(function (Category $category): void {
            $category->public_uuid ??= (string) Str::uuid();
            $category->status = $category->status ?: ($category->is_active === false ? 'inactive' : 'active');
            $category->is_active = $category->status === 'active';
            $category->is_visible ??= $category->is_active;
        });

        static::saving(function (Category $category): void {
            if ($category->isDirty('status')) {
                $category->is_active = $category->status === 'active';
            } elseif ($category->isDirty('is_active')) {
                $category->status = $category->is_active ? 'active' : 'inactive';
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function childrenRecursive(): HasMany
    {
        return $this->children()->with(['childrenRecursive' => fn ($query) => $query->withCount('products')])->withCount('products');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeWebsiteVisible(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('is_active', true)->where('is_visible', true);
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (! request()->is('admin/*')) {
            $query->where('status', 'active')->where('is_active', true)->where('is_visible', true);
        }

        return $query;
    }
}
