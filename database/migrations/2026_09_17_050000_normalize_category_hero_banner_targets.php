<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('banners') || ! Schema::hasColumn('banners', 'specific_pages')) {
            return;
        }

        $aliases = [
            'category:traditional' => 'category:irish-traditional-flat-caps',
            'category:heritage' => 'category:irish-heritage-hats',
        ];

        DB::table('banners')
            ->where('position', 'Category Hero')
            ->orderBy('id')
            ->get(['id', 'specific_pages'])
            ->each(function (object $banner) use ($aliases): void {
                $pages = $banner->specific_pages;

                if (is_string($pages)) {
                    $decoded = json_decode($pages, true);
                    $pages = is_array($decoded) ? $decoded : [];
                }

                if (! is_array($pages) || $pages === []) {
                    return;
                }

                $normalized = array_values(array_unique(array_map(
                    static fn ($page) => $aliases[(string) $page] ?? (string) $page,
                    $pages,
                )));

                if ($normalized === $pages) {
                    return;
                }

                DB::table('banners')
                    ->where('id', $banner->id)
                    ->update([
                        'specific_pages' => json_encode($normalized, JSON_UNESCAPED_SLASHES),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Intentional no-op. Category hero targets are managed business data and
        // should keep the canonical category slugs after a release rollback.
    }
};
