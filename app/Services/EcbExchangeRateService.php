<?php

namespace App\Services;

use App\Models\{Company, Currency, ExchangeRate};
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EcbExchangeRateService
{
    public const SOURCE_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    /** @return array<string, float> */
    public function sync(?Company $company = null): array
    {
        $company ??= app(TenantContext::class)->company();
        $base = strtoupper((string) ($company?->base_currency ?: 'EUR'));

        $response = Http::timeout(12)->accept('application/xml')->get(self::SOURCE_URL);
        if (! $response->successful()) {
            throw new RuntimeException('ECB exchange-rate feed is unavailable.');
        }

        $eurRates = $this->parse((string) $response->body());
        $eurRates['EUR'] = 1.0;
        if (! isset($eurRates[$base]) || $eurRates[$base] <= 0) {
            throw new RuntimeException("ECB does not publish a usable {$base} reference rate.");
        }

        $date = today()->toDateString();
        $saved = [];
        $known = Currency::query()->where('active', true)->pluck('code')->map(fn ($code) => strtoupper((string) $code))->all();

        foreach ($eurRates as $quote => $eurQuote) {
            if ($quote === $base || ! in_array($quote, $known, true) || $eurQuote <= 0) continue;
            $rate = $eurQuote / $eurRates[$base];
            ExchangeRate::query()->updateOrCreate(
                ['base_currency'=>$base, 'quote_currency'=>$quote, 'rate_date'=>$date],
                ['rate'=>$rate, 'source'=>'ecb']
            );
            $saved[$quote] = $rate;
        }

        return $saved;
    }

    /** @return array<string, float> */
    public function parse(string $xml): array
    {
        preg_match_all('/currency=["\']([A-Z]{3})["\']\s+rate=["\']([0-9]+(?:\.[0-9]+)?)["\']/i', $xml, $matches, PREG_SET_ORDER);
        $rates = [];
        foreach ($matches as $match) {
            $rate = (float) $match[2];
            if ($rate > 0) $rates[strtoupper($match[1])] = $rate;
        }

        if ($rates === []) throw new RuntimeException('ECB exchange-rate feed did not contain any rates.');

        return $rates;
    }
}
