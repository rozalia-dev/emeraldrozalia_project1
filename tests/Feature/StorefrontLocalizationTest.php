<?php

namespace Tests\Feature;

use App\Models\{Category, Company, Currency, ExchangeRate, Language};
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): Company
    {
        $company = Company::updateOrCreate(['code'=>'ERL'],['name'=>'Emerald Rozalia','country_code'=>'IE','base_currency'=>'EUR','default_locale'=>'en','active'=>true]);
        foreach ([['en','English','English'],['ga','Irish','Gaeilge']] as [$locale,$name,$native]) Language::updateOrCreate(['locale'=>$locale],['name'=>$name,'native_name'=>$native,'active'=>true]);
        foreach ([['EUR','Euro','€'],['GBP','Pound Sterling','£']] as [$code,$name,$symbol]) Currency::updateOrCreate(['code'=>$code],['name'=>$name,'symbol'=>$symbol,'decimals'=>2,'active'=>true]);
        $company->languages()->sync(['en'=>['is_default'=>true],'ga'=>['is_default'=>false]]);
        $company->currencies()->sync(['EUR'=>['is_base'=>true,'enabled_storefront'=>true],'GBP'=>['is_base'=>false,'enabled_storefront'=>true]]);
        ExchangeRate::updateOrCreate(['base_currency'=>'EUR','quote_currency'=>'GBP','rate_date'=>today()],['rate'=>0.86,'source'=>'test']);
        return $company;
    }

    public function test_language_and_currency_contexts_are_switchable(): void
    {
        $company = $this->configure();
        $this->withSession(['company_id'=>$company->id])->post('/context/language',['locale'=>'ga'])->assertRedirect()->assertSessionHas('locale','ga');
        $this->post('/context/currency',['currency'=>'GBP'])->assertRedirect()->assertSessionHas('currency','GBP');
    }

    public function test_money_uses_selected_storefront_currency(): void
    {
        $company = $this->configure();
        session(['company_id'=>$company->id,'currency'=>'GBP']);
        $this->assertSame('£8.60', app(TenantContext::class)->formatMoney('10.00'));
    }

    public function test_category_uses_irish_translation_when_locale_is_irish(): void
    {
        $this->configure();
        app()->setLocale('ga');
        $category = new Category(['name'=>'Traditional','translations'=>['ga'=>['name'=>'Traidisiúnta']]]);
        $this->assertSame('Traidisiúnta',$category->name);
    }
}
