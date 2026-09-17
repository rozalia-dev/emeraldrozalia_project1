<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('languages') || ! Schema::hasTable('currencies')) return;
        $now = now();

        foreach ([
            ['locale'=>'en','name'=>'English','native_name'=>'English'],
            ['locale'=>'ga','name'=>'Irish','native_name'=>'Gaeilge'],
        ] as $language) {
            DB::table('languages')->updateOrInsert(['locale'=>$language['locale']], [...$language,'active'=>true,'updated_at'=>$now,'created_at'=>$now]);
        }
        foreach ([
            ['code'=>'EUR','name'=>'Euro','symbol'=>'€','decimals'=>2],
            ['code'=>'GBP','name'=>'Pound Sterling','symbol'=>'£','decimals'=>2],
            ['code'=>'USD','name'=>'US Dollar','symbol'=>'$','decimals'=>2],
        ] as $currency) {
            DB::table('currencies')->updateOrInsert(['code'=>$currency['code']], [...$currency,'active'=>true,'updated_at'=>$now,'created_at'=>$now]);
        }

        $company = Schema::hasTable('companies') ? DB::table('companies')->where('code','ERL')->first() : null;
        if (! $company && Schema::hasTable('companies')) $company = DB::table('companies')->where('active',true)->orderBy('id')->first();
        if ($company) {
            foreach (['en'=>true,'ga'=>false] as $locale=>$isDefault) {
                DB::table('company_languages')->updateOrInsert(
                    ['company_id'=>$company->id,'locale'=>$locale],
                    ['is_default'=>$isDefault,'updated_at'=>$now,'created_at'=>$now]
                );
            }
            foreach (['EUR'=>true,'GBP'=>false,'USD'=>false] as $code=>$isBase) {
                DB::table('company_currencies')->updateOrInsert(
                    ['company_id'=>$company->id,'currency_code'=>$code],
                    ['is_base'=>$isBase,'enabled_storefront'=>true,'updated_at'=>$now,'created_at'=>$now]
                );
            }
        }

        if (Schema::hasTable('exchange_rates')) {
            foreach ([['GBP',0.86],['USD',1.18]] as [$quote,$rate]) {
                DB::table('exchange_rates')->updateOrInsert(
                    ['base_currency'=>'EUR','quote_currency'=>$quote,'rate_date'=>$now->toDateString()],
                    ['rate'=>$rate,'source'=>'project1-bootstrap-reference','updated_at'=>$now,'created_at'=>$now]
                );
            }
        }

        $this->seedIrishCategoryTranslations();
        $this->seedIrishProductTranslations();
    }

    public function down(): void
    {
        if (Schema::hasTable('exchange_rates')) DB::table('exchange_rates')->where('source','project1-bootstrap-reference')->delete();
        $company = Schema::hasTable('companies') ? DB::table('companies')->where('code','ERL')->first() : null;
        if ($company && Schema::hasTable('company_languages')) DB::table('company_languages')->where('company_id',$company->id)->where('locale','ga')->delete();
        if ($company && Schema::hasTable('company_currencies')) DB::table('company_currencies')->where('company_id',$company->id)->whereIn('currency_code',['GBP','USD'])->delete();
    }

    private function seedIrishCategoryTranslations(): void
    {
        if (! Schema::hasTable('categories') || ! Schema::hasColumn('categories','translations')) return;
        $map = [
            'traditional'=>'Traidisiúnta','irish-traditional-flat-caps'=>'Caipíní Réidhe Traidisiúnta Éireannacha',
            'heritage'=>'Oidhreacht','irish-heritage-hats'=>'Hataí Oidhreachta Éireannacha','classic'=>'Clasaiceach',
            'outdoor'=>'Lasmuigh','winter'=>'Geimhreadh','sports'=>'Spórt','workwear'=>'Éadaí Oibre','kids'=>'Páistí','costume'=>'Feisteas',
        ];
        foreach (DB::table('categories')->whereIn('slug',array_keys($map))->get(['id','slug','translations']) as $category) {
            $translations = json_decode((string) $category->translations,true) ?: [];
            $translations['ga']['name'] = $map[$category->slug];
            $translations['ga']['description'] ??= 'Ceannbheart ardchaighdeáin Emerald Rozalia, déanta in Éirinn.';
            DB::table('categories')->where('id',$category->id)->update(['translations'=>json_encode($translations,JSON_UNESCAPED_UNICODE)]);
        }
    }

    private function seedIrishProductTranslations(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products','translations')) return;
        $names = [
            'Classic Emerald Cap'=>'Caipín Emerald Clasaiceach','Emerald Signature Cap'=>'Caipín Sínithe Emerald',
            'Emerald Flat Cap'=>'Caipín Réidh Emerald','Emerald Beanie'=>'Beanie Emerald','Emerald Trucker Cap'=>'Caipín Trucker Emerald',
        ];
        foreach (DB::table('products')->whereIn('name',array_keys($names))->get(['id','name','translations']) as $product) {
            $translations = json_decode((string) $product->translations,true) ?: [];
            $translations['ga']['name'] = $names[$product->name];
            $translations['ga']['description'] ??= 'Ceannbheart Emerald Rozalia ar ardchaighdeán, déanta i Luimneach, Éire.';
            DB::table('products')->where('id',$product->id)->update(['translations'=>json_encode($translations,JSON_UNESCAPED_UNICODE)]);
        }
    }
};
