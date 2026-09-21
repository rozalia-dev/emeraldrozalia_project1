<?php

use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Support\CatalogBrazil;
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

        $brazil = CatalogCountry::query()->where('code', 'BR')->first();
        if (! $brazil) {
            return;
        }

        foreach (CatalogBrazil::CLUBS as $index => $row) {
            $name = (string) $row['name'];
            $slug = Str::slug($name);

            CatalogClub::query()->updateOrCreate(
                [
                    'governing_body' => 'fifa',
                    'catalog_country_id' => $brazil->id,
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
        // Seeded FIFA club rows become editable catalogue data after deployment.
        // Do not remove them automatically during release rollback.
    }
};
