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
        $this->addContentPageColumns();
        $this->addPageSectionColumns();
        $this->createSharedLayoutTable();
        $this->reserveHomepage();
    }

    public function down(): void
    {
        Schema::dropIfExists('site_layout_versions');

        foreach ([
            'content_pages' => ['route_path', 'page_kind', 'is_reserved', 'validation_errors'],
            'page_sections' => ['region', 'locale', 'media_uuid', 'focal_point', 'devices', 'variant', 'animation', 'analytics_key', 'validation_errors'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn($table, $column)));
            if ($existing !== []) {
                Schema::table($table, fn (Blueprint $blueprint): mixed => $blueprint->dropColumn($existing));
            }
        }
    }

    private function addContentPageColumns(): void
    {
        if (! Schema::hasTable('content_pages')) {
            return;
        }

        $missing = [];
        if (! Schema::hasColumn('content_pages', 'route_path')) {
            $missing['route_path'] = fn (Blueprint $table): mixed => $table->string('route_path', 220)->nullable()->index();
        }
        if (! Schema::hasColumn('content_pages', 'page_kind')) {
            $missing['page_kind'] = fn (Blueprint $table): mixed => $table->string('page_kind', 32)->default('standard')->index();
        }
        if (! Schema::hasColumn('content_pages', 'is_reserved')) {
            $missing['is_reserved'] = fn (Blueprint $table): mixed => $table->boolean('is_reserved')->default(false)->index();
        }
        if (! Schema::hasColumn('content_pages', 'validation_errors')) {
            $missing['validation_errors'] = fn (Blueprint $table): mixed => $table->json('validation_errors')->nullable();
        }

        if ($missing !== []) {
            Schema::table('content_pages', function (Blueprint $table) use ($missing): void {
                foreach ($missing as $definition) {
                    $definition($table);
                }
            });
        }

        DB::table('content_pages')->whereNull('route_path')->get(['id', 'slug'])->each(function (object $page): void {
            DB::table('content_pages')->where('id', $page->id)->update([
                'route_path' => $page->slug === 'home' ? '/' : '/'.$page->slug,
            ]);
        });
        DB::table('content_pages')->whereNull('page_kind')->update(['page_kind' => 'standard']);
        DB::table('content_pages')->where('slug', 'home')->update([
            'route_path' => '/',
            'page_kind' => 'home',
            'is_reserved' => true,
        ]);
    }

    private function addPageSectionColumns(): void
    {
        if (! Schema::hasTable('page_sections')) {
            return;
        }

        $missing = [];
        if (! Schema::hasColumn('page_sections', 'region')) {
            $missing['region'] = fn (Blueprint $table): mixed => $table->string('region', 40)->default('main')->index();
        }
        if (! Schema::hasColumn('page_sections', 'locale')) {
            $missing['locale'] = fn (Blueprint $table): mixed => $table->string('locale', 12)->nullable()->index();
        }
        if (! Schema::hasColumn('page_sections', 'media_uuid')) {
            $missing['media_uuid'] = fn (Blueprint $table): mixed => $table->uuid('media_uuid')->nullable()->index();
        }
        if (! Schema::hasColumn('page_sections', 'focal_point')) {
            $missing['focal_point'] = fn (Blueprint $table): mixed => $table->json('focal_point')->nullable();
        }
        if (! Schema::hasColumn('page_sections', 'devices')) {
            $missing['devices'] = fn (Blueprint $table): mixed => $table->json('devices')->nullable();
        }
        if (! Schema::hasColumn('page_sections', 'variant')) {
            $missing['variant'] = fn (Blueprint $table): mixed => $table->string('variant', 40)->nullable();
        }
        if (! Schema::hasColumn('page_sections', 'animation')) {
            $missing['animation'] = fn (Blueprint $table): mixed => $table->string('animation', 40)->default('none');
        }
        if (! Schema::hasColumn('page_sections', 'analytics_key')) {
            $missing['analytics_key'] = fn (Blueprint $table): mixed => $table->string('analytics_key', 100)->nullable();
        }
        if (! Schema::hasColumn('page_sections', 'validation_errors')) {
            $missing['validation_errors'] = fn (Blueprint $table): mixed => $table->json('validation_errors')->nullable();
        }

        if ($missing !== []) {
            Schema::table('page_sections', function (Blueprint $table) use ($missing): void {
                foreach ($missing as $definition) {
                    $definition($table);
                }
            });
        }
    }

    private function createSharedLayoutTable(): void
    {
        if (Schema::hasTable('site_layout_versions')) {
            return;
        }

        Schema::create('site_layout_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name', 180);
            $table->string('scope', 40)->default('public');
            $table->string('environment', 32)->default('production');
            $table->string('locale', 12)->default('en');
            $table->unsignedInteger('version');
            $table->string('status', 32)->default('draft');
            $table->json('regions');
            $table->json('validation_errors')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('source_version_uuid')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('validated_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampTz('rolled_back_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['company_id', 'scope', 'environment', 'locale', 'version'],
                'site_layout_versions_context_version_unique',
            );
            $table->index(
                ['company_id', 'scope', 'environment', 'locale', 'status'],
                'site_layout_versions_context_status_index',
            );
        });
    }

    private function reserveHomepage(): void
    {
        if (! Schema::hasTable('content_pages')) {
            return;
        }

        $now = now();
        $page = DB::table('content_pages')->where('slug', 'home')->first();
        if ($page) {
            DB::table('content_pages')->where('id', $page->id)->update([
                'route_path' => '/',
                'page_kind' => 'home',
                'is_reserved' => true,
                'status' => $page->status ?: 'published',
                'updated_at' => $now,
            ]);
            return;
        }

        DB::table('content_pages')->insert([
            'uuid' => (string) Str::uuid(),
            'title' => 'Homepage',
            'slug' => 'home',
            'route_path' => '/',
            'page_kind' => 'home',
            'is_reserved' => true,
            'intro' => 'Emerald Rozalia public homepage composition.',
            'body' => null,
            'status' => 'published',
            'locale' => 'en',
            'template' => 'home',
            'navigation_visible' => false,
            'meta' => json_encode([
                'title' => 'Emerald Rozalia — Irish Made Hats & Caps',
                'description' => 'Irish-made hats and caps, crafted in Limerick and worn everywhere.',
                'settings' => [
                    'visibility' => 'public',
                    'indexable' => true,
                    'devices' => ['desktop', 'tablet', 'mobile'],
                ],
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
