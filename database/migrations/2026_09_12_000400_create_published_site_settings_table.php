<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PUBLIC_KEYS = [
        'general-configuration' => [
            'company_name', 'system_name', 'company_legal_name', 'website_url', 'country',
            'timezone', 'default_language', 'default_currency', 'primary_email', 'primary_phone',
            'support_email', 'support_phone',
        ],
        'company-branding' => [
            'legal_name', 'trading_name', 'address', 'city', 'county', 'postcode', 'country',
            'phone', 'email', 'website', 'description', 'logo_path', 'brand_primary',
            'brand_secondary', 'brand_accent', 'footer_text',
        ],
        'localization' => [
            'default_language', 'default_currency', 'country_code', 'region', 'timezone',
            'date_format', 'time_format', 'first_day', 'decimal_separator', 'thousands_separator',
            'measurement_system', 'rtl_support', 'fallback_language',
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('published_site_settings')) {
            Schema::create('published_site_settings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
                $table->string('section');
                $table->unsignedInteger('version');
                $table->jsonb('data');
                $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampTz('published_at');
                $table->timestampsTz();
                $table->unique(['company_id', 'section', 'version']);
                $table->index(['company_id', 'section', 'published_at']);
            });
        }

        $this->backfillExistingCompanySettings();
    }

    public function down(): void
    {
        Schema::dropIfExists('published_site_settings');
    }

    private function backfillExistingCompanySettings(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        foreach (DB::table('companies')->where('active', true)->get() as $company) {
            foreach (array_keys(self::PUBLIC_KEYS) as $section) {
                $exists = DB::table('published_site_settings')
                    ->where('company_id', $company->id)
                    ->where('section', $section)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $record = Schema::hasTable('admin_records')
                    ? DB::table('admin_records')
                        ->where('module', 'system-settings')
                        ->where('reference', $section)
                        ->where(function ($query) use ($company): void {
                            $query->where('company_id', $company->id)->orWhereNull('company_id');
                        })
                        ->latest('id')
                        ->first()
                    : null;
                $data = $this->onlyPublic($section, $record ? $this->decode($record->data) : []);
                $data = array_merge($this->defaults($section, $company), $data);
                $now = now();

                DB::table('published_site_settings')->insert([
                    'uuid' => (string) Str::uuid(),
                    'company_id' => $company->id,
                    'section' => $section,
                    'version' => 1,
                    'data' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'published_by' => null,
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function onlyPublic(string $section, array $data): array
    {
        $filtered = array_intersect_key($data, array_flip(self::PUBLIC_KEYS[$section] ?? []));

        if ($section === 'company-branding') {
            if (array_key_exists('logo_path', $filtered)) {
                $filtered['logo_path'] = in_array($filtered['logo_path'], [
                    '/assets/logo/logo_one_line.png',
                    '/assets/logo/logo_two_line.png',
                ], true) ? $filtered['logo_path'] : '/assets/logo/logo_two_line.png';
            }
            foreach (['brand_primary' => '#075b2f', 'brand_secondary' => '#0b1711', 'brand_accent' => '#7fbd42'] as $key => $fallback) {
                if (array_key_exists($key, $filtered)) {
                    $filtered[$key] = is_string($filtered[$key]) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $filtered[$key])
                        ? strtolower($filtered[$key])
                        : $fallback;
                }
            }
        }

        return $filtered;
    }

    private function defaults(string $section, object $company): array
    {
        return match ($section) {
            'general-configuration' => [
                'company_name' => $company->name,
                'company_legal_name' => $company->legal_name ?: 'Emerald Rozalia Limited',
                'country' => $company->country_code ?: 'IE',
                'timezone' => 'Europe/Dublin',
                'default_language' => $company->default_locale ?: 'en',
                'default_currency' => $company->base_currency ?: 'EUR',
            ],
            'company-branding' => [
                'legal_name' => $company->legal_name ?: 'Emerald Rozalia Limited',
                'trading_name' => $company->name,
                'country' => 'Ireland',
                'city' => 'Limerick',
                'email' => 'urmos@rozalia.ie',
                'phone' => '0899788187',
                'website' => 'https://emeraldrozalia.ie',
                'logo_path' => '/assets/logo/logo_two_line.png',
                'brand_primary' => '#075b2f',
                'brand_secondary' => '#0b1711',
                'brand_accent' => '#7fbd42',
                'description' => 'Proudly manufacturing hats and caps in Limerick, Ireland.',
            ],
            'localization' => [
                'default_language' => $company->default_locale ?: 'en',
                'default_currency' => $company->base_currency ?: 'EUR',
                'country_code' => $company->country_code ?: 'IE',
                'region' => 'Ireland',
                'timezone' => 'Europe/Dublin',
                'date_format' => 'DD/MM/YYYY',
                'time_format' => '24-hour',
                'first_day' => 'Monday',
                'measurement_system' => 'Metric',
            ],
            default => [],
        };
    }
};
