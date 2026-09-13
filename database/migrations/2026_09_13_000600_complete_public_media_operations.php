<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('media_asset_versions')) {
            return;
        }

        Schema::create('media_asset_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('disk', 30);
            $table->string('path', 500);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->jsonb('focal_point')->nullable();
            $table->jsonb('crop')->nullable();
            $table->jsonb('responsive_variants')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['media_asset_id', 'version']);
            $table->index(['media_asset_id', 'created_at']);
        });

        $this->seedInitialVersions();
    }

    public function down(): void
    {
        Schema::dropIfExists('media_asset_versions');
    }

    private function seedInitialVersions(): void
    {
        if (! Schema::hasTable('media_assets')) {
            return;
        }

        $now = now();
        DB::table('media_assets')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->each(function (object $asset) use ($now): void {
                $metadata = is_array($asset->metadata ?? null)
                    ? $asset->metadata
                    : (json_decode((string) ($asset->metadata ?? ''), true) ?: []);
                $metadata['current_version'] = max(1, (int) ($metadata['current_version'] ?? 1));
                $variants = is_array($asset->responsive_variants ?? null)
                    ? $asset->responsive_variants
                    : (json_decode((string) ($asset->responsive_variants ?? ''), true) ?: null);

                DB::table('media_asset_versions')->insert([
                    'uuid' => (string) Str::uuid(),
                    'media_asset_id' => $asset->id,
                    'version' => 1,
                    'disk' => $asset->disk,
                    'path' => $asset->path,
                    'mime_type' => $asset->mime_type,
                    'bytes' => $asset->bytes,
                    'width' => $asset->width,
                    'height' => $asset->height,
                    'alt_text' => $asset->alt_text,
                    'focal_point' => $asset->focal_point,
                    'crop' => $asset->crop,
                    'responsive_variants' => $variants ? json_encode($variants, JSON_UNESCAPED_SLASHES) : null,
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
                    'created_by' => $asset->created_by,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('media_assets')->where('id', $asset->id)->update([
                    'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
                    'updated_at' => $now,
                ]);
            });
    }
};
