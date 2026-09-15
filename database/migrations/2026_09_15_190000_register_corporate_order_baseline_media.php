<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const LEGACY_PATH = 'assets/products/irish-heritage-bucket-hat/front.jpg';
    private const ASSET_KEY = 'legacy:'.self::LEGACY_PATH;
    private const SOURCE = 'repository-corporate-order-baseline';

    public function up(): void
    {
        if (! Schema::hasTable('media_assets')) {
            return;
        }

        $existing = DB::table('media_assets')
            ->where('asset_key', self::ASSET_KEY)
            ->first();

        if ($existing) {
            return;
        }

        $sourcePath = public_path(self::LEGACY_PATH);
        if (! is_file($sourcePath)) {
            return;
        }

        $contents = @file_get_contents($sourcePath);
        if ($contents === false) {
            return;
        }

        $hash = hash_file('sha256', $sourcePath) ?: Str::random(20);
        $target = 'media-managed/baseline-products/corporate-orders/'.substr($hash, 0, 24).'-front.jpg';
        if (! Storage::disk('public')->exists($target)) {
            Storage::disk('public')->put($target, $contents);
        }

        $dimensions = @getimagesize($sourcePath);
        $now = now();

        DB::table('media_assets')->insert([
            'uuid' => (string) Str::uuid(),
            'asset_key' => self::ASSET_KEY,
            'company_id' => null,
            'name' => 'Irish Heritage Bucket Hat Corporate Baseline',
            'disk' => 'public',
            'path' => $target,
            'mime_type' => function_exists('mime_content_type') ? (@mime_content_type($sourcePath) ?: 'image/jpeg') : 'image/jpeg',
            'bytes' => (int) (@filesize($sourcePath) ?: 0) ?: null,
            'width' => (int) ($dimensions[0] ?? 0) ?: null,
            'height' => (int) ($dimensions[1] ?? 0) ?: null,
            'alt_text' => 'Emerald Rozalia Irish heritage bucket hat',
            'approval_status' => 'approved',
            'approved_at' => $now,
            'active' => true,
            'metadata' => json_encode([
                'source' => self::SOURCE,
                'legacy_path' => self::LEGACY_PATH,
                'original_name' => basename($sourcePath),
                'purpose' => 'corporate-orders-safe-fallback',
            ], JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('media_assets')) {
            return;
        }

        $asset = DB::table('media_assets')
            ->where('asset_key', self::ASSET_KEY)
            ->first(['id', 'disk', 'path', 'metadata']);

        if (! $asset) {
            return;
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
