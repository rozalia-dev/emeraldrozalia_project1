<?php

namespace Tests\Feature;

use App\Models\{Category, Company, Currency, ExchangeRate, Language, Product, StorefrontTranslation};
use App\Services\{EcbExchangeRateService, StorefrontTranslator, TenantContext};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): Company
    {
        $company = Company::query()->updateOrCreate(
            ['code'=>'LOCALIZATION-TEST'],
            ['name'=>'Localization Test','country_code'=>'IE','base_currency'=>'EUR','default_locale'=>'en','active'=>true],
        );

        foreach ([
            ['en','English','English','ltr'],
            ['fr','French','Français','ltr'],
            ['ar','Arabic','العربية','rtl'],
        ] as [$locale,$name,$native,$direction]) {
            Language::query()->updateOrCreate(['locale'=>$locale],[
                'name'=>$name,'native_name'=>$native,'direction'=>$direction,'fallback_locale'=>$locale==='en'?null:'en','active'=>true,
            ]);
        }

        foreach ([
            ['EUR','Euro','€',2,'before'],
            ['CAD','Canadian Dollar','C$',2,'before'],
            ['JPY','Japanese Yen','¥',0,'before'],
        ] as [$code,$name,$symbol,$decimals,$position]) {
            Currency::query()->updateOrCreate(['code'=>$code],[
                'name'=>$name,'symbol'=>$symbol,'decimals'=>$decimals,'symbol_position'=>$position,'active'=>true,
            ]);
        }

        $company->languages()->sync([
            'en'=>['is_default'=>true,'enabled_storefront'=>true],
            'fr'=>['is_default'=>false,'enabled_storefront'=>true],
            'ar'=>['is_default'=>false,'enabled_storefront'=>true],
        ]);
        $company->currencies()->sync([
            'EUR'=>['is_base'=>true,'enabled_storefront'=>true],
            'CAD'=>['is_base'=>false,'enabled_storefront'=>true],
            'JPY'=>['is_base'=>false,'enabled_storefront'=>true],
        ]);

        ExchangeRate::query()->updateOrCreate(
            ['base_currency'=>'EUR','quote_currency'=>'CAD','rate_date'=>today()],
            ['rate'=>1.60,'source'=>'test'],
        );
        ExchangeRate::query()->updateOrCreate(
            ['base_currency'=>'EUR','quote_currency'=>'JPY','rate_date'=>today()],
            ['rate'=>180,'source'=>'test'],
        );

        session(['company_id'=>$company->id]);

        return $company;
    }

    public function test_arbitrary_storefront_languages_and_currencies_are_switchable(): void
    {
        $company = $this->configure();

        $this->withSession(['company_id'=>$company->id])
            ->post('/context/language',['locale'=>'fr'])
            ->assertRedirect()
            ->assertSessionHas('locale','fr');

        $this->post('/context/currency',['currency'=>'CAD'])
            ->assertRedirect()
            ->assertSessionHas('currency','CAD');
    }

    public function test_company_controls_which_languages_and_currencies_are_public(): void
    {
        $company = $this->configure();
        $company->languages()->updateExistingPivot('ar',['enabled_storefront'=>false]);
        $company->currencies()->updateExistingPivot('JPY',['enabled_storefront'=>false]);

        $context = app(TenantContext::class);

        $this->assertSame(['en','fr'], $context->availableLanguages()->pluck('locale')->sort()->values()->all());
        $this->assertSame(['CAD','EUR'], $context->availableCurrencies()->pluck('code')->sort()->values()->all());
    }

    public function test_money_conversion_is_currency_agnostic_and_respects_currency_decimals(): void
    {
        $company = $this->configure();

        session(['company_id'=>$company->id,'currency'=>'CAD']);
        $this->assertSame('C$16.00', app(TenantContext::class)->formatMoney('10.00'));

        session(['currency'=>'JPY']);
        $this->assertSame('¥1,800', app(TenantContext::class)->formatMoney('10.00'));
    }

    public function test_database_driven_interface_translation_works_for_non_irish_language(): void
    {
        $company = $this->configure();
        StorefrontTranslation::withoutGlobalScopes()->create([
            'company_id'=>$company->id,
            'locale'=>'fr',
            'translation_key'=>'ui.add_to_cart',
            'namespace'=>'ui',
            'source_text'=>'ADD TO CART',
            'source_hash'=>sha1(StorefrontTranslator::normalize('ADD TO CART')),
            'translation'=>'AJOUTER AU PANIER',
            'active'=>true,
        ]);

        session(['locale'=>'fr']);
        app()->setLocale('fr');

        $translator = app(StorefrontTranslator::class);
        $this->assertSame('AJOUTER AU PANIER', $translator->translate('ADD TO CART'));
        $this->assertSame('AJOUTER AU PANIER', $translator->runtimeMap('fr')['ADD TO CART']);
    }

    public function test_product_and_category_content_support_any_configured_locale(): void
    {
        $this->configure();
        session(['locale'=>'fr']);
        app()->setLocale('fr');

        $category = new Category([
            'name'=>'Traditional',
            'translations'=>['fr'=>['name'=>'Traditionnel','description'=>'Collection traditionnelle']],
        ]);
        $product = new Product([
            'name'=>'Classic Cap',
            'description'=>'Classic product',
            'translations'=>['fr'=>['name'=>'Casquette Classique','description'=>'Produit classique']],
        ]);

        $this->assertSame('Traditionnel', $category->name);
        $this->assertSame('Casquette Classique', $product->name);
        $this->assertSame('Produit classique', $product->description);
    }

    public function test_rtl_language_direction_is_part_of_storefront_context(): void
    {
        $company = $this->configure();
        session(['company_id'=>$company->id,'locale'=>'ar']);
        app()->setLocale('ar');

        $payload = app(TenantContext::class)->storefrontPayload();

        $this->assertSame('ar', $payload['locale']);
        $this->assertSame('rtl', $payload['direction']);
        $this->assertContains('fr', array_column($payload['languages'],'locale'));
        $this->assertContains('CAD', array_column($payload['currencies'],'code'));
    }

    public function test_exchange_rates_can_be_triangulated_through_euro(): void
    {
        $this->configure();
        Currency::query()->updateOrCreate(['code'=>'USD'],[
            'name'=>'US Dollar','symbol'=>'$','decimals'=>2,'symbol_position'=>'before','active'=>true,
        ]);
        ExchangeRate::query()->updateOrCreate(
            ['base_currency'=>'EUR','quote_currency'=>'USD','rate_date'=>today()],
            ['rate'=>1.20,'source'=>'test'],
        );

        $this->assertEqualsWithDelta(0.75, app(TenantContext::class)->exchangeRate('CAD','USD'), 0.000001);
    }

    public function test_ecb_parser_accepts_real_daily_feed_shape_without_network_access(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Envelope><Cube><Cube time="2026-09-17"><Cube currency="USD" rate="1.14775"/><Cube currency="GBP" rate="0.85915"/><Cube currency="JPY" rate="179.019"/></Cube></Cube></Envelope>
XML;

        $rates = app(EcbExchangeRateService::class)->parse($xml);

        $this->assertSame(1.14775, $rates['USD']);
        $this->assertSame(0.85915, $rates['GBP']);
        $this->assertSame(179.019, $rates['JPY']);
    }
}
