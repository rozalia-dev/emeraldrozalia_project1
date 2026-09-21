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

        foreach (CatalogEngland::CLUBS as $index => $row) {
            $name = (string) $row['name'];
            $slug = Str::slug($name);

            CatalogClub::query()->updateOrCreate(
                [
                    'governing_body' => 'english',
                    'catalog_country_id' => $england->id,
                    'slug' => $slug,
                ],
                [
                    'catalog_county_code' => strtoupper((string) $row['county_code']),
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                ]
            );
        }
    }

    public function down(): void
    {
        // Seeded catalogue clubs become editable business data after deployment.
        // Do not automatically delete them during a release rollback.
    }
};
