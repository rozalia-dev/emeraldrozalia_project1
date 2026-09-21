<?php

namespace App\Support;

final class CatalogGaaCountyClubs
{
    public static function ireland(): array
    {
        return array_values(array_map(
            static fn (array $county): array => [
                'county_code' => strtoupper((string) ($county['code'] ?? '')),
                'name' => trim((string) ($county['name'] ?? '')).' GAA',
            ],
            array_filter(
                CatalogCounties::forCountry('IE'),
                static fn (array $county): bool => filled($county['code'] ?? null) && filled($county['name'] ?? null)
            )
        ));
    }
}
