<?php

use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Support\CatalogGaaCountyClubs;
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

        $ireland = CatalogCountry::query()->where('code', 'IE')->first();
        if (! $ireland) {
            return;
        }

        foreach (CatalogGaaCountyClubs::ireland() as $index => $row) {
            $countyCode = strtoupper((string) $row['county_code']);
            $name = (string) $row['name'];
            $slug = Str::slug($name);

            CatalogClub::query()->updateOrCreate(
                [
                    'governing_body' => 'gaa',
                    'catalog_country_id' => $ireland->id,
                    'slug' => $slug,
                ],
                [
                    'catalog_county_code' => $countyCode,
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                ]
            );
        }
    }

    public function down(): void
    {
        // Default county associations become normal catalogue data after deployment.
        // Do not remove them automatically during a release rollback.
    }
};
