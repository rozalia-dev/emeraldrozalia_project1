<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ContentPage extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'navigation_visible' => 'boolean',
        'is_reserved' => 'boolean',
        'validation_errors' => 'array',
        'scheduled_for' => 'datetime',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($page) => $page->uuid ??= Str::uuid()->toString());
    }

    public function sections()
    {
        return $this->hasMany(PageSection::class)->orderBy('sort_order');
    }

    public function revisions()
    {
        return $this->hasMany(PageRevision::class)->latest('version');
    }

    public function settings(): array
    {
        $settings = data_get($this->meta, 'settings', []);

        return is_array($settings) ? $settings : [];
    }

    public function isPublic(): bool
    {
        return ($this->settings()['visibility'] ?? 'public') === 'public';
    }

    public function requiresLogin(): bool
    {
        return (bool) ($this->settings()['login_required'] ?? false);
    }

    public function shouldShowInFooter(): bool
    {
        return (bool) ($this->settings()['show_in_footer'] ?? false);
    }

    public function routePath(): string
    {
        return (string) ($this->route_path ?: '/'.$this->slug);
    }

    public function isHomepage(): bool
    {
        return $this->is_reserved && $this->page_kind === 'home' && $this->routePath() === '/';
    }
}
