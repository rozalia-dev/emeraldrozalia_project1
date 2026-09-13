<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->createMediaAssets();
        $this->registerBaselineAssets();
        $this->addProductMediaFields();
        $this->addVariantMediaFields();
        $this->addReferenceColumn('banners');
        $this->addReferenceColumn('product_collections');
        $this->migrateLegacyReferences();
    }

    public function down(): void
    {
        $this->removeBaselineAssets();
        $this->dropReferenceColumn('product_collections');
        $this->dropReferenceColumn('banners');
        $this->dropFields('variant_media', [
            'approval_status', 'approved_at', 'approved_by', 'mime_type', 'width', 'height',
            'bytes', 'focal_point', 'crop', 'responsive_variants',
        ]);
        $this->dropFields('product_media', [
            'approval_status', 'approved_at', 'approved_by', 'mime_type', 'width', 'height',
            'bytes', 'focal_point', 'crop', 'responsive_variants',
        ]);
        Schema::dropIfExists('media_assets');
    }

    private function createMediaAssets(): void
    {
        if (! Schema::hasTable('media_assets')) {
            Schema::create('media_assets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('asset_key', 180)->nullable()->index();
                $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
                $table->string('name', 180);
                $table->string('disk', 30)->default('local');
                $table->string('path', 500);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('bytes')->nullable();
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('alt_text', 255)->nullable();
                $table->jsonb('focal_point')->nullable();
                $table->jsonb('crop')->nullable();
                $table->jsonb('responsive_variants')->nullable();
                $table->string('approval_status', 30)->default('pending')->index();
                $table->timestampTz('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->boolean('active')->default(true)->index();
                $table->jsonb('metadata')->nullable();
                $table->timestampsTz();
                $table->softDeletesTz();
                $table->index(['company_id', 'approval_status', 'active']);
            });
        }

        if (! Schema::hasColumn('media_assets', 'asset_key')) {
            Schema::table('media_assets', function (Blueprint $table): void {
                $table->string('asset_key', 180)->nullable()->index();
            });
        }
    }

    private function registerBaselineAssets(): void
    {
        if (! Schema::hasTable('media_assets')) {
            return;
        }

        foreach ([public_path('assets/brand'), public_path('assets/logo')] as $directory) {
            foreach ((array) glob($directory.'/*') as $sourcePath) {
                if (! is_file($sourcePath)) {
                    continue;
                }

                $relative = str_replace('\\', '/', ltrim(Str::after($sourcePath, public_path()), '/\\'));
                $assetKey = 'legacy:'.$relative;
                if (DB::table('media_assets')->where('asset_key', $assetKey)->exists()) {
                    continue;
                }

                $contents = @file_get_contents($sourcePath);
                if ($contents === false) {
                    continue;
                }

                $target = 'media-managed/baseline/'.Str::uuid()->toString().'-'.basename($sourcePath);
                Storage::disk('public')->put($target, $contents);
                $dimensions = @getimagesize($sourcePath);
                $now = now();
                DB::table('media_assets')->insert([
                    'uuid' => (string) Str::uuid(),
                    'asset_key' => $assetKey,
                    'company_id' => null,
                    'name' => Str::headline(pathinfo($sourcePath, PATHINFO_FILENAME)),
                    'disk' => 'public',
                    'path' => $target,
                    'mime_type' => function_exists('mime_content_type') ? (@mime_content_type($sourcePath) ?: null) : null,
                    'bytes' => (int) (@filesize($sourcePath) ?: 0) ?: null,
                    'width' => (int) ($dimensions[0] ?? 0) ?: null,
                    'height' => (int) ($dimensions[1] ?? 0) ?: null,
                    'alt_text' => 'Emerald Rozalia '.Str::headline(pathinfo($sourcePath, PATHINFO_FILENAME)),
                    'approval_status' => 'approved',
                    'approved_at' => $now,
                    'active' => true,
                    'metadata' => json_encode([
                        'source' => 'repository-baseline',
                        'legacy_path' => $relative,
                        'original_name' => basename($sourcePath),
                    ], JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function removeBaselineAssets(): void
    {
        if (! Schema::hasTable('media_assets')) {
            return;
        }

        $assets = DB::table('media_assets')
            ->where('asset_key', 'like', 'legacy:%')
            ->get(['disk', 'path', 'metadata']);
        foreach ($assets as $asset) {
            $metadata = json_decode((string) $asset->metadata, true);
            if (data_get($metadata, 'source') === 'repository-baseline'
                && in_array($asset->disk, ['local', 'public'], true)
                && is_string($asset->path)) {
                Storage::disk($asset->disk)->delete($asset->path);
            }
        }
        DB::table('media_assets')->where('asset_key', 'like', 'legacy:%')->delete();
    }

    private function migrateLegacyReferences(): void
    {
        $this->migrateLegacyReferenceTable('banners', 'image_path', 'image_disk', 'media_uuid');
        $this->migrateLegacyReferenceTable('product_collections', 'image', null, 'media_uuid');
    }

    private function migrateLegacyReferenceTable(string $tableName, string $pathColumn, ?string $diskColumn, string $uuidColumn): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $pathColumn) || ! Schema::hasColumn($tableName, $uuidColumn)) {
            return;
        }

        $columns = ['id', 'company_id', $pathColumn];
        if ($diskColumn && Schema::hasColumn($tableName, $diskColumn)) {
            $columns[] = $diskColumn;
        }

        foreach (DB::table($tableName)->whereNull($uuidColumn)->whereNotNull($pathColumn)->get($columns) as $record) {
            $rawPath = trim((string) $record->{$pathColumn});
            $path = ltrim($rawPath, '/');
            if ($path === '' || str_contains($path, '..') || preg_match('/\A(?:https?:)?\/\//i', $rawPath)) {
                continue;
            }

            $baseline = DB::table('media_assets')
                ->where('asset_key', 'legacy:'.$path)
                ->whereNull('company_id')
                ->first();
            $assetKey = 'legacy:'.$tableName.':'.$record->id.':'.sha1($path);
            $asset = $baseline ?: DB::table('media_assets')->where('asset_key', $assetKey)->first();
            if (! $asset) {
                $disk = $diskColumn ? (string) ($record->{$diskColumn} ?: 'public') : 'public';
                if (! in_array($disk, ['local', 'public'], true)) {
                    continue;
                }

                $sourcePath = null;
                if (Storage::disk($disk)->exists($path)) {
                    $sourcePath = Storage::disk($disk)->path($path);
                } elseif (is_file(public_path($path))) {
                    $disk = 'public';
                    $target = 'media-managed/migrated/'.Str::uuid()->toString().'-'.basename($path);
                    $contents = @file_get_contents(public_path($path));
                    if ($contents === false || ! Storage::disk('public')->put($target, $contents)) {
                        continue;
                    }
                    $path = $target;
                    $sourcePath = Storage::disk('public')->path($path);
                }

                if (! $sourcePath || ! is_file($sourcePath)) {
                    continue;
                }

                $dimensions = @getimagesize($sourcePath);
                $now = now();
                $assetUuid = (string) Str::uuid();
                DB::table('media_assets')->insert([
                    'uuid' => $assetUuid,
                    'asset_key' => $assetKey,
                    'company_id' => $record->company_id,
                    'name' => Str::headline(pathinfo($rawPath, PATHINFO_FILENAME)),
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => function_exists('mime_content_type') ? (@mime_content_type($sourcePath) ?: null) : null,
                    'bytes' => (int) (@filesize($sourcePath) ?: 0) ?: null,
                    'width' => (int) ($dimensions[0] ?? 0) ?: null,
                    'height' => (int) ($dimensions[1] ?? 0) ?: null,
                    'alt_text' => Str::headline(pathinfo($rawPath, PATHINFO_FILENAME)),
                    'approval_status' => 'approved',
                    'approved_at' => $now,
                    'active' => true,
                    'metadata' => json_encode([
                        'source' => 'legacy-media-migration',
                        'legacy_path' => $rawPath,
                        'original_name' => basename($rawPath),
                    ], JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $asset = (object) ['uuid' => $assetUuid];
            }

            DB::table($tableName)->where('id', $record->id)->update([$uuidColumn => $asset->uuid]);
        }
    }

    private function addProductMediaFields(): void
    {
        $this->addFields('product_media');
    }

    private function addVariantMediaFields(): void
    {
        $this->addFields('variant_media');
    }

    private function addFields(string $tableName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $missing = [];
        if (! Schema::hasColumn($tableName, 'approval_status')) {
            $missing['approval_status'] = fn (Blueprint $table): mixed => $table->string('approval_status', 30)->default('approved')->index();
        }
        if (! Schema::hasColumn($tableName, 'approved_at')) {
            $missing['approved_at'] = fn (Blueprint $table): mixed => $table->timestampTz('approved_at')->nullable();
        }
        if (! Schema::hasColumn($tableName, 'approved_by')) {
            $missing['approved_by'] = fn (Blueprint $table): mixed => $table->unsignedBigInteger('approved_by')->nullable();
        }
        if (! Schema::hasColumn($tableName, 'mime_type')) {
            $missing['mime_type'] = fn (Blueprint $table): mixed => $table->string('mime_type', 120)->nullable();
        }
        if (! Schema::hasColumn($tableName, 'width')) {
            $missing['width'] = fn (Blueprint $table): mixed => $table->unsignedInteger('width')->nullable();
        }
        if (! Schema::hasColumn($tableName, 'height')) {
            $missing['height'] = fn (Blueprint $table): mixed => $table->unsignedInteger('height')->nullable();
        }
        if (! Schema::hasColumn($tableName, 'bytes')) {
            $missing['bytes'] = fn (Blueprint $table): mixed => $table->unsignedBigInteger('bytes')->nullable();
        }
        if (! Schema::hasColumn($tableName, 'focal_point')) {
            $missing['focal_point'] = fn (Blueprint $table): mixed => $table->jsonb('focal_point')->nullable();
        }
        if (! Schema::hasColumn($tableName, 'crop')) {
            $missing['crop'] = fn (Blueprint $table): mixed => $table->jsonb('crop')->nullable();
        }
        if (! Schema::hasColumn($tableName, 'responsive_variants')) {
            $missing['responsive_variants'] = fn (Blueprint $table): mixed => $table->jsonb('responsive_variants')->nullable();
        }

        if ($missing !== []) {
            Schema::table($tableName, function (Blueprint $table) use ($missing): void {
                foreach ($missing as $definition) {
                    $definition($table);
                }
            });
        }
    }

    private function addReferenceColumn(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'media_uuid')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->uuid('media_uuid')->nullable()->index();
        });
    }

    private function dropReferenceColumn(string $tableName): void
    {
        if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'media_uuid')) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('media_uuid');
            });
        }
    }

    /** @param list<string> $fields */
    private function dropFields(string $tableName, array $fields): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existing = array_values(array_filter($fields, fn (string $field): bool => Schema::hasColumn($tableName, $field)));
        if ($existing !== []) {
            Schema::table($tableName, fn (Blueprint $table): mixed => $table->dropColumn($existing));
        }
    }
};
