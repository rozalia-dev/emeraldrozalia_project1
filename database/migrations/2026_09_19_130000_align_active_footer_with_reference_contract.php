<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('site_layout_versions')) {
            return;
        }

        DB::table('site_layout_versions')
            ->select(['id', 'regions'])
            ->where('scope', 'public')
            ->where('environment', 'production')
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(100, function ($layouts): void {
                foreach ($layouts as $layout) {
                    $regions = $this->decodeRegions($layout->regions);
                    if (! is_array($regions)) {
                        continue;
                    }

                    $footer = is_array($regions['footer'] ?? null) ? $regions['footer'] : [];
                    $changed = false;

                    if (! array_key_exists('copyright_text', $footer)) {
                        $footer['copyright_text'] = null;
                        $changed = true;
                    }

                    if (! array_key_exists('manufacturing_text', $footer)) {
                        $footer['manufacturing_text'] = 'Designed & Manufactured in Limerick, Ireland';
                        $changed = true;
                    }

                    $newsletter = is_array($footer['newsletter'] ?? null) ? $footer['newsletter'] : [];
                    $legacyDefaultNewsletter =
                        trim((string) ($newsletter['title'] ?? 'NEWSLETTER')) === 'NEWSLETTER'
                        && trim((string) ($newsletter['description'] ?? 'Stay updated with new arrivals and offers.')) === 'Stay updated with new arrivals and offers.'
                        && trim((string) ($newsletter['href'] ?? '/contact')) === '/contact'
                        && in_array(trim((string) ($newsletter['cta_label'] ?? 'Contact our team')), ['', 'Contact our team', 'Submit email'], true);

                    if (! array_key_exists('placeholder', $newsletter)) {
                        $newsletter['placeholder'] = 'Your email address';
                        $changed = true;
                    }

                    if ($legacyDefaultNewsletter) {
                        if (! filter_var($newsletter['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                            $newsletter['enabled'] = true;
                            $changed = true;
                        }
                        if (($newsletter['cta_label'] ?? null) !== 'Submit email') {
                            $newsletter['cta_label'] = 'Submit email';
                            $changed = true;
                        }
                    }

                    if (! $changed) {
                        continue;
                    }

                    $footer['newsletter'] = $newsletter;
                    $regions['footer'] = $footer;

                    DB::table('site_layout_versions')
                        ->where('id', $layout->id)
                        ->update([
                            'regions' => json_encode(
                                $regions,
                                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                            ),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally non-destructive. These fields become owner-managed after
        // deployment and rollback must not erase later cPanel footer edits.
    }

    private function decodeRegions(mixed $regions): ?array
    {
        if (is_array($regions)) {
            return $regions;
        }

        if (! is_string($regions) || trim($regions) === '') {
            return null;
        }

        $decoded = json_decode($regions, true);

        return is_array($decoded) ? $decoded : null;
    }
};
