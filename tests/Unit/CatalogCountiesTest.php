<?php

namespace Tests\Unit;

use App\Support\CatalogCounties;
use App\Support\CatalogCountries;
use PHPUnit\Framework\TestCase;

class CatalogCountiesTest extends TestCase
{
    public function test_every_eu_country_has_a_complete_non_empty_subdivision_list(): void
    {
        $expectedCountries = CatalogCountries::EU_CODES;
        $actualCountries = CatalogCounties::countryCodes();
        sort($expectedCountries);
        sort($actualCountries);

        $this->assertSame($expectedCountries, $actualCountries);
        $this->assertSame(1237, CatalogCounties::count(), 'The checked-in ISO subdivision payload appears to be cut off or changed unexpectedly.');

        foreach (CatalogCountries::EU_CODES as $countryCode) {
            $rows = CatalogCounties::forCountry($countryCode);
            $this->assertNotEmpty($rows, "{$countryCode} must have subdivisions.");

            $codes = array_column($rows, 'code');
            $this->assertSame(count($codes), count(array_unique($codes)), "{$countryCode} contains duplicate subdivision codes.");

            foreach ($rows as $row) {
                $this->assertNotSame('', trim((string) ($row['name'] ?? '')));
                $this->assertStringStartsWith($countryCode.'-', (string) ($row['code'] ?? ''));
                $this->assertNotSame('', trim((string) ($row['type'] ?? '')));
            }
        }
    }

    public function test_county_lookup_is_country_scoped(): void
    {
        $limerick = CatalogCounties::find('IE', 'IE-LK');

        $this->assertNotNull($limerick);
        $this->assertSame('Limerick', $limerick['name']);
        $this->assertTrue(CatalogCounties::isValid('IE', 'IE-LK'));
        $this->assertFalse(CatalogCounties::isValid('FR', 'IE-LK'));
    }
}
