<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductSpin extends Model
{
    protected $guarded = [];
    protected $casts = ['frames'=>'array','settings'=>'array','seo'=>'array','hotspots'=>'array'];
    public const STATUSES = ['published'=>'Published','in_progress'=>'In Progress','draft'=>'Drafts','needs_attention'=>'Needs Attention','archived'=>'Archived'];
    public const CATEGORIES = ['product'=>'Product','lifestyle'=>'Lifestyle','promotional'=>'Promotional','other'=>'Other'];
    public const DEFAULTS = ['auto_rotate'=>false,'zoom'=>true,'fullscreen'=>true,'hotspots'=>true,'lazy_load'=>true,'mobile'=>true];

    protected static function booted(): void
    {
        static::addGlobalScope('product_tenant',fn ($q) => $q->whereHas('product',fn($p)=>$p->when(session('company_id'),fn($p,$id)=>$p->where('products.company_id',(int)$id))));
        static::creating(fn ($spin) => $spin->uuid ??= (string) Str::uuid());
    }
    public function product() { return $this->belongsTo(Product::class); }
    public function visits() { return $this->hasMany(SpinVisit::class); }
    public function isPublic(): bool
    {
        return $this->status === 'published' && $this->visibility === 'public' && count($this->frames ?? []) >= 2 && (bool) $this->product?->is_active;
    }
    public function viewerData(): array
    {
        return ['uuid'=>$this->uuid,'title'=>$this->title,'frames'=>array_map(fn ($i)=>route('spins.frame',[$this->uuid,$i]),array_keys($this->frames ?? [])),
            'settings'=>array_replace(self::DEFAULTS,$this->settings ?? []),'hotspots'=>$this->hotspots ?? [],
            'alt'=>$this->seo['alt'] ?? $this->title,'aria'=>$this->seo['aria'] ?? $this->title,
            'metric'=>route('spins.visit',$this->uuid)];
    }
}
