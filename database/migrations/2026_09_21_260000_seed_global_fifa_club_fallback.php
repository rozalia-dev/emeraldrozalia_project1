<?php

use App\Models\CatalogCountry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
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

        $path = database_path('data/reep-global-football-clubs.csv');
        if (! is_file($path)) {
            return;
        }

        $aliases = [
            'czechrepublic' => 'CZ',
            'russia' => 'RU',
            'turkey' => 'TR',
            'iran' => 'IR',
            'peoplesrepublicofchina' => 'CN',
            'venezuela' => 'VE',
            'southkorea' => 'KR',
            'capeverde' => 'CV',
            'moldova' => 'MD',
            'bolivia' => 'BO',
            'democraticrepublicofthecongo' => 'CD',
            'tanzania' => 'TZ',
            'ivorycoast' => 'CI',
            'palestine' => 'PS',
            'syria' => 'SY',
            'republicofthecongo' => 'CG',
            'taiwan' => 'TW',
            'thegambia' => 'GM',
            'laos' => 'LA',
            'northkorea' => 'KP',
            'brunei' => 'BN',
            'thebahamas' => 'BS',
            'britishvirginislands' => 'VG',
            'macau' => 'MO',
            'federatedstatesofmicronesia' => 'FM',
            'sintmaarten' => 'SX',
            'vaticancity' => 'VA',
            'unitedstatesvirginislands' => 'VI',
        ];

        $countries = CatalogCountry::query()->active()->get();
        $countryByNormalizedName = $countries->mapWithKeys(
            fn (CatalogCountry $country): array => [$this->normalize($country->name) => $country]
        );
        $countryByCode = $countries->keyBy(fn (CatalogCountry $country): string => strtoupper($country->code));

        $handle = fopen($path, 'rb');
        if (! is_resource($handle)) {
            return;
        }

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            return;
        }

        $now = now();
        $batch = [];
        $sortByCountry = [];
        $seen = [];

        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($header, array_pad($row, count($header), ''));
            if (! is_array($record)) {
                continue;
            }

            $countryName = trim((string) ($record['country_name'] ?? ''));
            $clubName = trim((string) ($record['club_name'] ?? ''));
            if ($countryName === '' || $clubName === '') {
                continue;
            }

            $normalizedCountry = $this->normalize($countryName);
            $country = $countryByNormalizedName->get($normalizedCountry);
            if (! $country && isset($aliases[$normalizedCountry])) {
                $country = $countryByCode->get($aliases[$normalizedCountry]);
            }
            if (! $country) {
                continue;
            }

            $clubName = Str::limit($clubName, 180, '');
            $slug = Str::limit(Str::slug($clubName), 220, '');
            if ($slug === '') {
                continue;
            }

            $dedupeKey = $country->id.'|'.$slug;
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $countryCode = strtoupper($country->code);
            $sortByCountry[$countryCode] = ($sortByCountry[$countryCode] ?? 0) + 10;

            $batch[] = [
                'public_uuid' => (string) Str::uuid(),
                'catalog_country_id' => $country->id,
                'catalog_county_code' => null,
                'governing_body' => 'fifa',
                'name' => $clubName,
                'slug' => $slug,
                'is_active' => true,
                'sort_order' => $sortByCountry[$countryCode],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 500) {
                $this->upsert($batch);
                $batch = [];
            }
        }

        fclose($handle);

        if ($batch !== []) {
            $this->upsert($batch);
        }
    }

    public function down(): void
    {
        // Imported FIFA clubs become editable catalogue data after deployment.
        // Rollbacks must not remove records that may have been curated by admins.
    }

    private function upsert(array $rows): void
    {
        DB::table('catalog_clubs')->upsert(
            $rows,
            ['governing_body', 'catalog_country_id', 'slug'],
            ['name', 'is_active', 'updated_at']
        );
    }

    private function normalize(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/', '', Str::ascii(trim($value))));
    }
};
