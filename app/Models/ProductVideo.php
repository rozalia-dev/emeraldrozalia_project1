<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductVideo extends ProductMedia
{
    protected $table = 'product_media';

    public const CATEGORIES = [
        'product' => 'Product Videos',
        'lifestyle' => 'Lifestyle Videos',
        'how-to' => 'How-To / Tutorial',
        'promotional' => 'Promotional',
        'interactive' => '360° / Interactive',
        'other' => 'Other',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('videos', fn (Builder $query) => $query->where('type', 'video')->whereHas('product'));
        static::creating(function (self $video): void {
            $video->type = 'video';
            $video->uuid ??= (string) Str::uuid();
        });
    }

    public function plays()
    {
        return $this->hasMany(VideoPlay::class, 'product_media_id');
    }

    public function getTitleAttribute(): string
    {
        return data_get($this->metadata, 'title') ?: $this->alt_text ?: $this->product?->name.' video';
    }

    public function getVideoStatusAttribute(): string
    {
        if (!$this->active) return 'draft';
        $publishAt = data_get($this->metadata, 'publish_at');
        return $publishAt && \Carbon\Carbon::parse($publishAt)->isFuture() ? 'scheduled' : 'published';
    }

    public function getPlatformAttribute(): string
    {
        return data_get($this->metadata, 'platform', 'Website');
    }

    public function getEmbedUrlAttribute(): ?string
    {
        if ($this->platform === 'Website') return null;
        $parts = parse_url($this->path);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        if (in_array($host, ['youtube.com','www.youtube.com','m.youtube.com','youtu.be'], true)) {
            parse_str($parts['query'] ?? '', $query);
            $id = $host === 'youtu.be' ? trim($path, '/') : ($query['v'] ?? (preg_match('~^/(?:shorts|embed)/([A-Za-z0-9_-]+)$~', $path, $m) ? $m[1] : ''));
            if (is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) return 'https://www.youtube-nocookie.com/embed/'.$id;
        }
        if (in_array($host, ['vimeo.com','www.vimeo.com','player.vimeo.com'], true) && preg_match('~^/(?:video/)?([0-9]+)$~', $path, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }
        return null;
    }

    public function isPubliclyPlayable(): bool
    {
        return $this->video_status === 'published'
            && data_get($this->metadata, 'visibility', $this->disk === 'public' ? 'public' : 'private') === 'public'
            && (bool) $this->product?->is_active;
    }

    public function details(): array
    {
        $meta = $this->metadata ?? [];
        return [
            'id' => $this->id, 'uuid' => $this->uuid, 'title' => $this->title,
            'product_id' => $this->product_id, 'product' => $this->product?->name,
            'sku' => $this->product?->sku, 'category' => $meta['category'] ?? 'product',
            'status' => $this->video_status, 'publish_at' => $meta['publish_at'] ?? '',
            'platform' => $this->platform, 'external_url' => $this->platform === 'Website' ? '' : $this->path,
            'description' => $meta['description'] ?? '', 'seo_title' => $meta['seo_title'] ?? $this->title,
            'tags' => $meta['tags'] ?? '', 'visibility' => $meta['visibility'] ?? ($this->disk === 'public' ? 'public' : 'private'),
            'gallery' => (bool) ($meta['gallery'] ?? true), 'allow_download' => (bool) ($meta['allow_download'] ?? false),
            'duration' => (float) ($meta['duration'] ?? 0), 'resolution' => $meta['resolution'] ?? '',
            'caption_language' => $meta['caption_language'] ?? 'en',
            'poster' => !empty($meta['poster']) ? route('videos.asset', [$this->uuid, 'poster']) : null,
            'captions' => !empty($meta['captions']) ? route('videos.asset', [$this->uuid, 'captions']) : null,
            'playback' => $this->platform === 'Website' ? route('videos.asset', [$this->uuid, 'video']) : null,
            'embed' => $this->embed_url,
            'update_url' => route('admin.videos.update', $this->id),
            'audit_url' => route('admin.videos.audit', $this->id),
            'views' => (int) ($this->plays_count ?? 0), 'seconds' => (int) ($this->plays_sum_seconds ?? 0),
        ];
    }
}
