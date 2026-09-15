<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const LEGACY_PATH = 'assets/corporate/corporate-orders-hero.webp';
    private const ASSET_KEY = 'legacy:'.self::LEGACY_PATH;
    private const SOURCE = 'generated-corporate-orders-hero';

    public function up(): void
    {
        if (! Schema::hasTable('media_assets')) {
            return;
        }

        $asset = DB::table('media_assets')
            ->where('asset_key', self::ASSET_KEY)
            ->first();

        if (! $asset) {
            $sourcePath = public_path(self::LEGACY_PATH);
            if (! is_file($sourcePath)) {
                return;
            }

            $contents = @file_get_contents($sourcePath);
            if ($contents === false) {
                return;
            }

            $hash = hash_file('sha256', $sourcePath) ?: Str::random(20);
            $target = 'media-managed/corporate-orders/'.substr($hash, 0, 24).'-corporate-orders-hero.webp';
            if (! Storage::disk('public')->exists($target)) {
                Storage::disk('public')->put($target, $contents);
            }

            $dimensions = @getimagesize($sourcePath);
            $now = now();
            $uuid = (string) Str::uuid();

            DB::table('media_assets')->insert([
                'uuid' => $uuid,
                'asset_key' => self::ASSET_KEY,
                'company_id' => null,
                'name' => 'Emerald Rozalia Corporate Orders Hero',
                'disk' => 'public',
                'path' => $target,
                'mime_type' => 'image/webp',
                'bytes' => (int) (@filesize($sourcePath) ?: 0) ?: null,
                'width' => (int) ($dimensions[0] ?? 0) ?: 1242,
                'height' => (int) ($dimensions[1] ?? 0) ?: 821,
                'alt_text' => 'Emerald Rozalia premium corporate headwear and branded merchandise',
                'approval_status' => 'approved',
                'approved_at' => $now,
                'active' => true,
                'focal_point' => json_encode(['x' => 0.5, 'y' => 0.52]),
                'metadata' => json_encode([
                    'source' => self::SOURCE,
                    'legacy_path' => self::LEGACY_PATH,
                    'original_name' => basename($sourcePath),
                    'purpose' => 'corporate-orders-hero',
                    'composition' => 'section-cropped',
                ], JSON_UNESCAPED_SLASHES),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $asset = (object) ['uuid' => $uuid];
        }

        if (! Schema::hasTable('content_pages') || ! Schema::hasTable('page_sections')) {
            return;
        }

        $pageIds = DB::table('content_pages')
            ->where('slug', 'corporate-orders')
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($pageIds as $pageId) {
            DB::table('page_sections')
                ->where('content_page_id', $pageId)
                ->where('type', 'hero')
                ->whereNull('media_uuid')
                ->update([
                    'media_uuid' => $asset->uuid,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('media_assets')) {
            return;
        }

        $asset = DB::table('media_assets')
            ->where('asset_key', self::ASSET_KEY)
            ->first(['id', 'uuid', 'disk', 'path', 'metadata']);

        if (! $asset) {
            return;
        }

        if (Schema::hasTable('page_sections')) {
            DB::table('page_sections')
                ->where('media_uuid', $asset->uuid)
                ->update(['media_uuid' => null, 'updated_at' => now()]);
        }

        $metadata = json_decode((string) $asset->metadata, true);
        if (data_get($metadata, 'source') !== self::SOURCE) {
            return;
        }

        if (in_array($asset->disk, ['local', 'public'], true) && is_string($asset->path)) {
            Storage::disk($asset->disk)->delete($asset->path);
        }

        DB::table('media_assets')->where('id', $asset->id)->delete();
    }
};
