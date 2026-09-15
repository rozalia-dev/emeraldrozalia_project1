<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductCatalogue extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'download_count' => 'integer',
        'pdf_size' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProductCatalogue $catalogue): void {
            $catalogue->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function currentCompanyId(): ?int
    {
        $sessionCompanyId = app()->bound('session') ? session('company_id') : null;
        if ($sessionCompanyId) {
            return (int) $sessionCompanyId;
        }

        $companyId = Company::query()->where('active', true)->orderBy('id')->value('id');

        return $companyId ? (int) $companyId : null;
    }

    public static function publishedForCurrentCompany(): ?self
    {
        $companyId = static::currentCompanyId();
        if (! $companyId) {
            return null;
        }

        return static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_published', true)
            ->whereNotNull('pdf_path')
            ->first();
    }
}
