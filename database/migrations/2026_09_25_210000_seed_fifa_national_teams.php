<?php

use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalog_countries') || ! Schema::hasTable('catalog_clubs')) {
            return;
        }

        CatalogCountry::query()
            ->active()
            ->orderBy('id')
            ->chunkById(200, function ($countries): void {
                foreach ($countries as $country) {
                    $name = trim((string) $country->name).' National Team';
                    $slug = Str::slug($name);

                    $team = CatalogClub::query()->firstOrNew([
                        'catalog_country_id' => $country->id,
                        'slug' => $slug,
                    ]);

                    if (! $team->exists) {
                        $team->governing_body = 'fifa';
                        $team->sort_order = 0;
                    }

                    $team->catalog_county_code = null;
                    $team->name = $name;
                    $team->is_active = true;
                    $team->save();

                    $organizations = $team->organizations()
                        ->pluck('taxonomy_type')
                        ->push($team->governing_body)
                        ->push('fifa')
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    $team->syncOrganizations($organizations);
                }
            });
    }

    public function down(): void
    {
        // National-team rows become editable catalogue data after deployment.
        // Do not remove them automatically during rollback.
    }
};
