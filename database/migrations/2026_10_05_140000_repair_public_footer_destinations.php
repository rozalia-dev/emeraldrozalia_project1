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

        $footerDestinations = [
            'Size Guide' => '/page/size-guide',
            'Shipping & Delivery' => '/page/shipping-delivery',
            'Returns & Refunds' => '/returns-refunds',
            'Our Story' => '/our-story',
        ];

        $legalDestinations = [
            'Privacy Policy' => '/page/privacy-policy',
            'Terms & Conditions' => '/page/terms-conditions',
        ];

        DB::table('site_layout_versions')
            ->select(['id', 'regions'])
            ->where('scope', 'public')
            ->where('environment', 'production')
            ->whereIn('status', ['active', 'draft'])
            ->orderBy('id')
            ->chunkById(100, function ($layouts) use ($footerDestinations, $legalDestinations): void {
                foreach ($layouts as $layout) {
                    $regions = $this->decodeRegions($layout->regions);
                    if (! is_array($regions)) {
                        continue;
                    }

                    $footer = is_array($regions['footer'] ?? null) ? $regions['footer'] : [];
                    $columns = array_values((array) ($footer['columns'] ?? []));
                    $legalLinks = array_values((array) ($footer['legal_links'] ?? []));
                    $changed = false;

                    foreach ($columns as $columnIndex => $column) {
                        $links = array_values((array) ($column['links'] ?? []));

                        foreach ($links as $linkIndex => $link) {
                            $label = trim((string) ($link['label'] ?? ''));
                            $href = trim((string) ($link['href'] ?? ''));

                            if ($href === '/factory' && isset($footerDestinations[$label])) {
                                $links[$linkIndex]['href'] = $footerDestinations[$label];
                                $changed = true;
                            }
                        }

                        $columns[$columnIndex]['links'] = $links;
                    }

                    foreach ($legalLinks as $linkIndex => $link) {
                        $label = trim((string) ($link['label'] ?? ''));
                        $href = trim((string) ($link['href'] ?? ''));

                        if ($href === '/factory' && isset($legalDestinations[$label])) {
                            $legalLinks[$linkIndex]['href'] = $legalDestinations[$label];
                            $changed = true;
                        }
                    }

                    if (! $changed) {
                        continue;
                    }

                    $footer['columns'] = $columns;
                    $footer['legal_links'] = $legalLinks;
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
        // Intentionally non-destructive. Public layout links are owner-managed
        // after deployment and rollback must not overwrite later cPanel edits.
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
