<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_pages') || ! Schema::hasTable('page_sections')) {
            return;
        }

        $homepage = DB::table('content_pages')
            ->where('slug', 'home')
            ->where('route_path', '/')
            ->where('page_kind', 'home')
            ->first(['id']);

        if (! $homepage) {
            return;
        }

        foreach (DB::table('page_sections')->where('content_page_id', $homepage->id)->get(['id', 'type', 'analytics_key', 'settings']) as $section) {
            $settings = json_decode((string) $section->settings, true);
            if (! is_array($settings)) {
                continue;
            }

            $original = json_encode($settings, JSON_THROW_ON_ERROR);

            if ($section->type === 'hero' || $section->analytics_key === 'homepage.hero') {
                // Update only the scaffold values from the initial homepage
                // migration; editorially customised copy is left untouched.
                if (($settings['eyebrow'] ?? null) === 'CRAFTED IN LIMERICK.') {
                    $settings['eyebrow'] = 'IRISH MADE. LIMERICK BORN.';
                }
                if (($settings['content'] ?? null) === 'VIRTUAL TRY-ON lets you preview Emerald Rozalia hats on your photo.') {
                    $settings['content'] = 'We are a Limerick based Irish manufacturer of premium hats and caps. Quality craftsmanship, Irish roots. Global reach. VIRTUAL TRY-ON is available from the hero card.';
                }
                if (($settings['primary_label'] ?? null) === 'START VIRTUAL TRY-ON') {
                    $settings['primary_label'] = 'START TRY-ON';
                }
            }

            if ($section->type === 'heritage' || $section->analytics_key === 'homepage.heritage') {
                if (($settings['eyebrow'] ?? null) === '') {
                    $settings['eyebrow'] = 'THE IRISH HERITAGE COLLECTION';
                }
                if (($settings['title'] ?? null) === '') {
                    $settings['title'] = 'Tradition, Made in Limerick.';
                }
            }

            $updated = json_encode($settings, JSON_THROW_ON_ERROR);
            if ($updated !== $original) {
                DB::table('page_sections')->where('id', $section->id)->update([
                    'settings' => $updated,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Do not roll back editorial section settings.
    }
};
