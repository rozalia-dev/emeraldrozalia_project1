<?php

use App\Models\CatalogClub;
use App\Models\CatalogCountry;
use App\Support\CatalogCounties;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalog_counties')) {
            Schema::create('catalog_counties', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_uuid')->unique();
                $table->foreignId('catalog_country_id')->constrained('catalog_countries')->cascadeOnUpdate()->restrictOnDelete();
                $table->string('code', 16);
                $table->string('name', 180);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['catalog_country_id', 'code'], 'catalog_county_country_code_unique');
                $table->index(['catalog_country_id', 'name']);
            });
        }

        $countries = CatalogCountry::query()->get()->keyBy(fn (CatalogCountry $country) => strtoupper($country->code));
        $now = now();

        foreach (CatalogCounties::all() as $countryCode => $rows) {
            $country = $countries->get(strtoupper((string) $countryCode));
            if (! $country) {
                continue;
            }

            foreach (array_values($rows) as $index => $row) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                $name = trim((string) ($row['name'] ?? ''));
                if ($code === '' || $name === '') {
                    continue;
                }

                DB::table('catalog_counties')->updateOrInsert(
                    ['catalog_country_id' => $country->id, 'code' => $code],
                    [
                        'public_uuid' => (string) Str::uuid(),
                        'name' => $name,
                        'is_active' => true,
                        'sort_order' => ($index + 1) * 10,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        if (app()->environment('testing')) {
            return;
        }

        // Extend County / Region Master from the bundled global club catalogue.
        // This catches regions that are present in club source data but were not
        // part of the older hard-coded subdivision snapshot.
        $countyLookup = [];
        foreach (DB::table('catalog_counties')->get(['catalog_country_id', 'code', 'name']) as $row) {
            $countyLookup[$row->catalog_country_id][$this->normalize($row->name)] = $row->code;
        }

        $sortByCountry = [];
        foreach (DB::table('catalog_counties')->selectRaw('catalog_country_id, MAX(sort_order) AS max_sort')->groupBy('catalog_country_id')->get() as $row) {
            $sortByCountry[(int) $row->catalog_country_id] = (int) $row->max_sort;
        }

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
                $regionName = trim((string) ($record['region_name'] ?? ''));
                $clubName = trim((string) ($record['club_name'] ?? ''));

                if ($countryCode === '' || $regionName === '' || $clubName === '' || preg_match('/^=+$/', $regionName)) {
                    continue;
                }

                /** @var CatalogCountry|null $country */
                $country = $countries->get($countryCode);
                if (! $country) {
                    continue;
                }

                $normalizedRegion = $this->normalize($regionName);
                if ($normalizedRegion === '') {
                    continue;
                }

                $countyCode = $countyLookup[$country->id][$normalizedRegion] ?? null;
                if (! $countyCode) {
                    $countyCode = $this->generatedCode($countryCode, $regionName);
                    $sortByCountry[$country->id] = ($sortByCountry[$country->id] ?? 0) + 10;

                    DB::table('catalog_counties')->updateOrInsert(
                        ['catalog_country_id' => $country->id, 'code' => $countyCode],
                        [
                            'public_uuid' => (string) Str::uuid(),
                            'name' => Str::limit($regionName, 180, ''),
                            'is_active' => true,
                            'sort_order' => $sortByCountry[$country->id],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                    $countyLookup[$country->id][$normalizedRegion] = $countyCode;
                }

                $slug = Str::limit(Str::slug($clubName), 220, '');
                if ($slug === '') {
                    continue;
                }

                CatalogClub::query()
                    ->where('catalog_country_id', $country->id)
                    ->where('slug', $slug)
                    ->whereNull('catalog_county_code')
                    ->update(['catalog_county_code' => $countyCode]);
            }

            fclose($handle);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_counties');
    }

    private function normalize(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/', '', Str::ascii(trim($value))));
    }

    private function generatedCode(string $countryCode, string $regionName): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Z0-9]/', '', Str::ascii($countryCode)), 0, 3));
        return substr($prefix.'-'.strtoupper(substr(sha1($countryCode.'|'.$regionName), 0, 10)), 0, 16);
    }
};
