<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TryOnAsset extends Model
{
    protected $guarded = [];
    protected $casts = ['files'=>'array','settings'=>'array','seo'=>'array'];

    public const STATUSES = [
        'published' => 'Published',
        'in_review' => 'In Review',
        'draft' => 'Drafts',
        'needs_attention' => 'Needs Attention',
        'archived' => 'Archived',
    ];

    public const TYPES = [
        'ar_ai' => 'AR / AI',
        'ai_only' => 'AI Only',
    ];

    public const TARGETS = [
        'male' => 'Male',
        'female' => 'Female',
        'unisex' => 'Unisex',
        'kids' => 'Kids',
    ];

    public const DEFAULTS = [
        'auto_fit' => true,
        'face_detection' => true,
        'realistic_lighting' => true,
        'shadow_rendering' => true,
        'occlusion' => true,
        'high_quality' => false,
        'mobile' => true,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('product_tenant', fn ($query) => $query->whereHas('product', fn ($product) => $product->when(session('company_id'), fn ($product, $companyId) => $product->where('products.company_id', (int) $companyId))));
        static::creating(fn ($asset) => $asset->uuid ??= (string) Str::uuid());
    }

    public function product() { return $this->belongsTo(Product::class); }
    public function visits() { return $this->hasMany(TryOnVisit::class); }

    public function previewPath(): ?string
    {
        $path = data_get($this->files, 'preview');
        return is_string($path) && $path !== '' ? $path : null;
    }

    public function modelPath(): ?string
    {
        $path = data_get($this->files, 'model');
        return is_string($path) && $path !== '' ? $path : null;
    }

    public function isPublic(): bool
    {
        return $this->status === 'published'
            && $this->visibility === 'public'
            && $this->previewPath() !== null
            && (bool) $this->product?->is_active;
    }

    public function previewUrl(): ?string
    {
        return $this->previewPath() ? route('tryons.asset', [$this->uuid, 'preview']) : null;
    }

    public function viewerData(): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'preview' => $this->previewUrl(),
            'model' => $this->modelPath() ? route('tryons.asset', [$this->uuid, 'model']) : null,
            'type' => $this->type,
            'target' => $this->target,
            'age_range' => $this->age_range,
            'settings' => array_replace(self::DEFAULTS, $this->settings ?? []),
            'seo' => $this->seo ?? [],
            'visit' => route('tryons.visit', $this->uuid),
        ];
    }
}
