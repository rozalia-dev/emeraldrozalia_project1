<?php

use App\Services\PageSectionBlueprints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_pages') || ! Schema::hasTable('page_sections')) {
            return;
        }

        $blueprints = app(PageSectionBlueprints::class);
        $now = now();

        DB::table('content_pages')
            ->whereNull('deleted_at')
            ->where('slug', '!=', 'home')
            ->orderBy('id')
            ->get(['id', 'slug', 'title', 'template'])
            ->each(function (object $page) use ($blueprints, $now): void {
                if (DB::table('page_sections')->where('content_page_id', $page->id)->exists()) {
                    return;
                }

                foreach ($blueprints->forSlug((string) $page->slug, (string) ($page->title ?: 'Public page')) as $index => $section) {
                    DB::table('page_sections')->insert([
                        'uuid' => (string) Str::uuid(),
                        'content_page_id' => $page->id,
                        'type' => $section['type'],
                        'label' => $section['label'],
                        'sort_order' => $index,
                        'region' => $section['region'] ?? 'main',
                        'locale' => $section['locale'] ?? null,
                        'media_uuid' => $section['media_uuid'] ?? null,
                        'focal_point' => $section['focal_point'] ?? null,
                        'devices' => json_encode($section['devices'] ?? ['desktop', 'tablet', 'mobile'], JSON_THROW_ON_ERROR),
                        'variant' => $section['variant'] ?? null,
                        'animation' => $section['animation'] ?? 'none',
                        'analytics_key' => $section['analytics_key'] ?? null,
                        'validation_errors' => null,
                        'settings' => json_encode($section['settings'] ?? [], JSON_THROW_ON_ERROR),
                        'visible' => (bool) ($section['visible'] ?? true),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Starter sections are editorial records. Never remove them during a rollback.
    }
};
