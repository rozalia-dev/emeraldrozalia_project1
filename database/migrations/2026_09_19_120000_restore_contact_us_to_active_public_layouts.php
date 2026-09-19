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

                    $regions['header'] = is_array($regions['header'] ?? null) ? $regions['header'] : [];
                    $menu = is_array($regions['header']['primary_menu'] ?? null)
                        ? array_values($regions['header']['primary_menu'])
                        : [];

                    $contactIndex = null;

                    foreach ($menu as $index => $item) {
                        if (! is_array($item)) {
                            continue;
                        }

                        $path = parse_url(trim((string) ($item['href'] ?? '')), PHP_URL_PATH);
                        $normalizedPath = '/'.trim((string) $path, '/');

                        if ($normalizedPath === '/contact') {
                            $contactIndex = $index;
                            break;
                        }
                    }

                    if ($contactIndex === null) {
                        $menu[] = [
                            'label' => 'CONTACT US',
                            'href' => '/contact',
                            'enabled' => true,
                        ];
                    } else {
                        $menu[$contactIndex]['label'] = filled($menu[$contactIndex]['label'] ?? null)
                            ? $menu[$contactIndex]['label']
                            : 'CONTACT US';
                        $menu[$contactIndex]['href'] = '/contact';
                        $menu[$contactIndex]['enabled'] = true;
                    }

                    $regions['header']['primary_menu'] = $menu;

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
        // Intentional no-op: this is a one-time production data repair.
        // Removing CONTACT US on rollback could erase a later owner-managed navigation choice.
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
