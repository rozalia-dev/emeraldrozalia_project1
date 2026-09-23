<?php

use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Support\CatalogEngland;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        if (! Schema::hasTable('catalog_countries') || ! Schema::hasTable('catalog_clubs')) {
            return;
        }

        $england = CatalogCountry::query()->where('code', 'ENG')->first();
        if (! $england) {
            return;
        }

        $uefaClubs = CatalogClub::query()
            ->where('governing_body', 'uefa')
            ->where('catalog_country_id', $england->id)
            ->get();

        $byNormalizedName = [];
        foreach ($uefaClubs as $club) {
            $normalized = $this->normalizeClubName((string) $club->name);
            if ($normalized !== '' && ! isset($byNormalizedName[$normalized])) {
                $byNormalizedName[$normalized] = $club;
            }
        }

        $nextSort = max(10, ((int) $uefaClubs->max('sort_order')) + 10);

        foreach (CatalogEngland::CLUBS as $row) {
            $countyCode = strtoupper(trim((string) ($row['county_code'] ?? '')));
            $name = trim((string) ($row['name'] ?? ''));
            if ($countyCode === '' || $name === '') {
                continue;
            }

            $normalized = $this->normalizeClubName($name);
            /** @var CatalogClub|null $club */
            $club = $byNormalizedName[$normalized] ?? null;

            if ($club) {
                $club->catalog_county_code = $countyCode;
                $club->is_active = true;
                $club->save();
                continue;
            }

            $club = CatalogClub::query()->firstOrNew([
                'governing_body' => 'uefa',
                'catalog_country_id' => $england->id,
                'slug' => Str::limit(Str::slug($name), 220, ''),
            ]);

            $club->name = $name;
            $club->catalog_county_code = $countyCode;
            $club->is_active = true;
            $club->sort_order = $club->sort_order ?: $nextSort;
            $club->save();

            $nextSort += 10;
            $byNormalizedName[$normalized] = $club;
        }
    }

    public function down(): void
    {
        // UEFA club mappings become editable catalogue data after deployment.
        // Do not remove or unmap them automatically during release rollback.
    }

    private function normalizeClubName(string $value): string
    {
        $value = trim(Str::ascii($value));
        $value = preg_replace('/\s+(?:A\.?F\.?C\.?|F\.?C\.?|Football Club)$/i', '', $value) ?? $value;

        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $value));
    }
};
