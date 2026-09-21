<?php

use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Support\CatalogCounties;
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

        $countries = CatalogCountry::query()->active()->get()->keyBy(fn (CatalogCountry $country) => strtoupper($country->code));
        $countyMaps = [];
        foreach ($countries as $code => $country) {
            $map = [];
            foreach (CatalogCounties::forCountry($code) as $county) {
                $name = (string) ($county['name'] ?? '');
                $countyCode = strtoupper((string) ($county['code'] ?? ''));
                if ($name === '' || $countyCode === '') {
                    continue;
                }
                $map[$this->normalize($name)] = $countyCode;
            }
            $countyMaps[$code] = $map;
        }

        $sortByCountry = [];

        foreach (glob(database_path('data/openfootball-fifa-clubs-*.csv')) ?: [] as $file) {
            $handle = fopen($file, 'rb');
            if (! is_resource($handle)) {
                continue;
            }

            $header = fgetcsv($handle);
            if (! is_array($header)) {
                fclose($handle);
                continue;
            }

            while (($row = fgetcsv($handle)) !== false) {
                $record = array_combine($header, array_pad($row, count($header), ''));
                if (! is_array($record)) {
                    continue;
                }

                $countryCode = strtoupper(trim((string) ($record['country_code'] ?? '')));
                $name = trim((string) ($record['club_name'] ?? ''));
                if ($countryCode === '' || $name === '' || ! $countries->has($countryCode)) {
                    continue;
                }

                /** @var CatalogCountry $country */
                $country = $countries->get($countryCode);
                $region = trim((string) ($record['region_name'] ?? ''));
                $countyCode = $region !== ''
                    ? ($countyMaps[$countryCode][$this->normalize($region)] ?? null)
                    : null;

                $slug = Str::slug($name);
                if ($slug === '') {
                    continue;
                }

                $club = CatalogClub::query()->firstOrNew([
                    'governing_body' => 'fifa',
                    'catalog_country_id' => $country->id,
                    'slug' => $slug,
                ]);

                $club->name = $name;
                $club->is_active = true;
                $sortByCountry[$countryCode] = ($sortByCountry[$countryCode] ?? 0) + 10;
                $club->sort_order = $club->sort_order ?: $sortByCountry[$countryCode];

                // Keep any manually curated county mapping. Otherwise use a
                // region/state match from the bundled club snapshot when one
                // can be resolved safely.
                if (! filled($club->catalog_county_code) && $countyCode) {
                    $club->catalog_county_code = $countyCode;
                }

                $club->save();
            }

            fclose($handle);
        }
    }

    public function down(): void
    {
        // Imported club rows become editable business data after deployment.
        // Do not remove them automatically during a release rollback.
    }

    private function normalize(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/', '', Str::ascii(trim($value))));
    }
};
