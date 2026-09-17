<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendLanguages();
        $this->extendCurrencies();
        $this->extendCommerceSnapshots();
        $this->createStorefrontTranslations();
        $this->seedLanguageCatalogue();
        $this->seedCurrencyCatalogue();
        $this->seedInitialCompanyContext();
        $this->seedCoreInterfaceTranslations();
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_translations');

        $this->dropColumns('payment_transactions', ['base_currency', 'exchange_rate', 'base_amount']);
        $this->dropColumns('order_items', ['base_unit_price', 'base_total']);
        $this->dropColumns('orders', ['base_currency_code', 'base_subtotal', 'base_shipping', 'base_discount', 'base_total']);
        $this->dropColumns('company_languages', ['enabled_storefront']);
        $this->dropColumns('languages', ['direction', 'fallback_locale', 'sort_order']);
        $this->dropColumns('currencies', ['symbol_position', 'decimal_separator', 'thousands_separator', 'sort_order']);
    }

    private function extendLanguages(): void
    {
        if (Schema::hasTable('languages')) {
            Schema::table('languages', function (Blueprint $table): void {
                if (! Schema::hasColumn('languages', 'direction')) $table->string('direction', 3)->default('ltr');
                if (! Schema::hasColumn('languages', 'fallback_locale')) $table->string('fallback_locale', 10)->nullable();
                if (! Schema::hasColumn('languages', 'sort_order')) $table->unsignedSmallInteger('sort_order')->default(0);
            });
        }

        if (Schema::hasTable('company_languages') && ! Schema::hasColumn('company_languages', 'enabled_storefront')) {
            Schema::table('company_languages', function (Blueprint $table): void {
                $table->boolean('enabled_storefront')->default(true);
            });
        }
    }

    private function extendCurrencies(): void
    {
        if (! Schema::hasTable('currencies')) return;

        Schema::table('currencies', function (Blueprint $table): void {
            if (! Schema::hasColumn('currencies', 'symbol_position')) $table->string('symbol_position', 8)->default('before');
            if (! Schema::hasColumn('currencies', 'decimal_separator')) $table->string('decimal_separator', 1)->default('.');
            if (! Schema::hasColumn('currencies', 'thousands_separator')) $table->string('thousands_separator', 1)->default(',');
            if (! Schema::hasColumn('currencies', 'sort_order')) $table->unsignedSmallInteger('sort_order')->default(0);
        });
    }

    private function extendCommerceSnapshots(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('orders', 'base_currency_code')) $table->string('base_currency_code', 3)->nullable();
                if (! Schema::hasColumn('orders', 'base_subtotal')) $table->decimal('base_subtotal', 12, 2)->nullable();
                if (! Schema::hasColumn('orders', 'base_shipping')) $table->decimal('base_shipping', 12, 2)->nullable();
                if (! Schema::hasColumn('orders', 'base_discount')) $table->decimal('base_discount', 12, 2)->nullable();
                if (! Schema::hasColumn('orders', 'base_total')) $table->decimal('base_total', 12, 2)->nullable();
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('order_items', 'base_unit_price')) $table->decimal('base_unit_price', 12, 2)->nullable();
                if (! Schema::hasColumn('order_items', 'base_total')) $table->decimal('base_total', 12, 2)->nullable();
            });
        }

        if (Schema::hasTable('payment_transactions')) {
            Schema::table('payment_transactions', function (Blueprint $table): void {
                if (! Schema::hasColumn('payment_transactions', 'base_currency')) $table->string('base_currency', 3)->nullable();
                if (! Schema::hasColumn('payment_transactions', 'exchange_rate')) $table->decimal('exchange_rate', 18, 8)->nullable();
                if (! Schema::hasColumn('payment_transactions', 'base_amount')) $table->decimal('base_amount', 12, 2)->nullable();
            });
        }
    }

    private function createStorefrontTranslations(): void
    {
        if (Schema::hasTable('storefront_translations')) return;

        Schema::create('storefront_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('translation_key', 180);
            $table->string('namespace', 50)->default('ui');
            $table->text('source_text');
            $table->char('source_hash', 40);
            $table->text('translation');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'locale', 'translation_key'], 'storefront_translations_company_locale_key_unique');
            $table->index(['locale', 'source_hash']);
        });
    }

    private function seedLanguageCatalogue(): void
    {
        if (! Schema::hasTable('languages')) return;

        $languages = [
            ['en', 'English', 'English', 'ltr', null, 10],
            ['ga', 'Irish', 'Gaeilge', 'ltr', 'en', 20],
            ['fr', 'French', 'Français', 'ltr', 'en', 30],
            ['de', 'German', 'Deutsch', 'ltr', 'en', 40],
            ['es', 'Spanish', 'Español', 'ltr', 'en', 50],
            ['it', 'Italian', 'Italiano', 'ltr', 'en', 60],
            ['pt', 'Portuguese', 'Português', 'ltr', 'en', 70],
            ['nl', 'Dutch', 'Nederlands', 'ltr', 'en', 80],
            ['pl', 'Polish', 'Polski', 'ltr', 'en', 90],
            ['ar', 'Arabic', 'العربية', 'rtl', 'en', 100],
            ['zh-CN', 'Chinese (Simplified)', '简体中文', 'ltr', 'en', 110],
            ['ja', 'Japanese', '日本語', 'ltr', 'en', 120],
            ['ko', 'Korean', '한국어', 'ltr', 'en', 130],
            ['hi', 'Hindi', 'हिन्दी', 'ltr', 'en', 140],
            ['tr', 'Turkish', 'Türkçe', 'ltr', 'en', 150],
            ['uk', 'Ukrainian', 'Українська', 'ltr', 'en', 160],
            ['sv', 'Swedish', 'Svenska', 'ltr', 'en', 170],
            ['da', 'Danish', 'Dansk', 'ltr', 'en', 180],
            ['no', 'Norwegian', 'Norsk', 'ltr', 'en', 190],
            ['fi', 'Finnish', 'Suomi', 'ltr', 'en', 200],
        ];

        foreach ($languages as [$locale, $name, $native, $direction, $fallback, $sort]) {
            DB::table('languages')->updateOrInsert(['locale' => $locale], [
                'name' => $name,
                'native_name' => $native,
                'direction' => $direction,
                'fallback_locale' => $fallback,
                'sort_order' => $sort,
                'active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    private function seedCurrencyCatalogue(): void
    {
        if (! Schema::hasTable('currencies')) return;

        $currencies = [
            ['EUR','Euro','€',2,'before',10], ['GBP','Pound Sterling','£',2,'before',20], ['USD','US Dollar','$',2,'before',30],
            ['CAD','Canadian Dollar','C$',2,'before',40], ['AUD','Australian Dollar','A$',2,'before',50], ['JPY','Japanese Yen','¥',0,'before',60],
            ['CHF','Swiss Franc','CHF',2,'before',70], ['SEK','Swedish Krona','kr',2,'after',80], ['NOK','Norwegian Krone','kr',2,'after',90],
            ['DKK','Danish Krone','kr',2,'after',100], ['PLN','Polish Zloty','zł',2,'after',110], ['CZK','Czech Koruna','Kč',2,'after',120],
            ['HUF','Hungarian Forint','Ft',2,'after',130], ['RON','Romanian Leu','lei',2,'after',140], ['CNY','Chinese Yuan','¥',2,'before',150],
            ['HKD','Hong Kong Dollar','HK$',2,'before',160], ['SGD','Singapore Dollar','S$',2,'before',170], ['NZD','New Zealand Dollar','NZ$',2,'before',180],
            ['KRW','South Korean Won','₩',0,'before',190], ['INR','Indian Rupee','₹',2,'before',200], ['AED','UAE Dirham','د.إ',2,'after',210],
            ['SAR','Saudi Riyal','ر.س',2,'after',220], ['TRY','Turkish Lira','₺',2,'before',230], ['ZAR','South African Rand','R',2,'before',240],
            ['BRL','Brazilian Real','R$',2,'before',250], ['MXN','Mexican Peso','MX$',2,'before',260], ['THB','Thai Baht','฿',2,'before',270],
            ['MYR','Malaysian Ringgit','RM',2,'before',280], ['IDR','Indonesian Rupiah','Rp',2,'before',290], ['PHP','Philippine Peso','₱',2,'before',300],
        ];

        foreach ($currencies as [$code, $name, $symbol, $decimals, $position, $sort]) {
            DB::table('currencies')->updateOrInsert(['code' => $code], [
                'name' => $name,
                'symbol' => $symbol,
                'decimals' => $decimals,
                'symbol_position' => $position,
                'decimal_separator' => '.',
                'thousands_separator' => ',',
                'sort_order' => $sort,
                'active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    private function seedInitialCompanyContext(): void
    {
        if (! Schema::hasTable('companies')) return;
        $company = DB::table('companies')->where('code', 'ERL')->first()
            ?? DB::table('companies')->where('active', true)->orderBy('id')->first();
        if (! $company) return;

        if (Schema::hasTable('company_languages')) {
            foreach (['en','ga','fr','de','es','it','pt','nl','pl','ar'] as $locale) {
                DB::table('company_languages')->updateOrInsert(
                    ['company_id' => $company->id, 'locale' => $locale],
                    ['is_default' => $locale === ($company->default_locale ?: 'en'), 'enabled_storefront' => true, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        if (Schema::hasTable('company_currencies')) {
            foreach (['EUR','GBP','USD','CAD','AUD','JPY','SEK','NOK','DKK','PLN'] as $code) {
                DB::table('company_currencies')->updateOrInsert(
                    ['company_id' => $company->id, 'currency_code' => $code],
                    ['is_base' => $code === ($company->base_currency ?: 'EUR'), 'enabled_storefront' => true, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        if (Schema::hasTable('exchange_rates') && strtoupper((string) ($company->base_currency ?: 'EUR')) === 'EUR') {
            $rates = ['GBP'=>0.85915,'USD'=>1.14775,'CAD'=>1.60548,'AUD'=>1.61430,'JPY'=>179.019,'SEK'=>11.2676,'NOK'=>10.8049,'DKK'=>7.47585,'PLN'=>4.35768];
            foreach ($rates as $quote => $rate) {
                DB::table('exchange_rates')->updateOrInsert(
                    ['base_currency'=>'EUR','quote_currency'=>$quote,'rate_date'=>'2026-09-17'],
                    ['rate'=>$rate,'source'=>'bootstrap-market-snapshot-2026-09-17','updated_at'=>now(),'created_at'=>now()]
                );
            }
        }
    }

    private function seedCoreInterfaceTranslations(): void
    {
        if (! Schema::hasTable('storefront_translations') || ! Schema::hasTable('companies')) return;
        $company = DB::table('companies')->where('code', 'ERL')->first()
            ?? DB::table('companies')->where('active', true)->orderBy('id')->first();
        if (! $company) return;

        $dictionary = [
            'HOME' => ['ga'=>'BAILE','fr'=>'ACCUEIL','de'=>'STARTSEITE','es'=>'INICIO','it'=>'HOME','pt'=>'INÍCIO','nl'=>'HOME','pl'=>'STRONA GŁÓWNA','ar'=>'الرئيسية'],
            'SHOP' => ['ga'=>'SIOPA','fr'=>'BOUTIQUE','de'=>'SHOP','es'=>'TIENDA','it'=>'NEGOZIO','pt'=>'LOJA','nl'=>'WINKEL','pl'=>'SKLEP','ar'=>'المتجر'],
            'SHOP ALL' => ['ga'=>'SIOPA UILE','fr'=>'TOUT ACHETER','de'=>'ALLES EINKAUFEN','es'=>'COMPRAR TODO','it'=>'ACQUISTA TUTTO','pt'=>'COMPRAR TUDO','nl'=>'ALLES BEKIJKEN','pl'=>'ZOBACZ WSZYSTKO','ar'=>'تسوق الكل'],
            'COLLECTIONS' => ['ga'=>'BAILIÚCHÁIN','fr'=>'COLLECTIONS','de'=>'KOLLEKTIONEN','es'=>'COLECCIONES','it'=>'COLLEZIONI','pt'=>'COLEÇÕES','nl'=>'COLLECTIES','pl'=>'KOLEKCJE','ar'=>'المجموعات'],
            'CATALOGUE' => ['ga'=>'CATALÓG','fr'=>'CATALOGUE','de'=>'KATALOG','es'=>'CATÁLOGO','it'=>'CATALOGO','pt'=>'CATÁLOGO','nl'=>'CATALOGUS','pl'=>'KATALOG','ar'=>'الكتالوج'],
            'NEW ARRIVALS' => ['ga'=>'NUA-THAGTHA','fr'=>'NOUVEAUTÉS','de'=>'NEUHEITEN','es'=>'NOVEDADES','it'=>'NUOVI ARRIVI','pt'=>'NOVIDADES','nl'=>'NIEUW BINNEN','pl'=>'NOWOŚCI','ar'=>'وصل حديثاً'],
            'CORPORATE ORDER' => ['ga'=>'ORDÚ CORPARÁIDEACH','fr'=>'COMMANDE ENTREPRISE','de'=>'FIRMENBESTELLUNG','es'=>'PEDIDO CORPORATIVO','it'=>'ORDINE AZIENDALE','pt'=>'PEDIDO CORPORATIVO','nl'=>'BEDRIJFSBESTELLING','pl'=>'ZAMÓWIENIE FIRMOWE','ar'=>'طلب شركات'],
            'BULK ORDER' => ['ga'=>'MÓRORDÚ','fr'=>'COMMANDE EN GROS','de'=>'GROSSBESTELLUNG','es'=>'PEDIDO AL POR MAYOR','it'=>'ORDINE ALL’INGROSSO','pt'=>'PEDIDO EM MASSA','nl'=>'GROTE BESTELLING','pl'=>'ZAMÓWIENIE HURTOWE','ar'=>'طلب بالجملة'],
            'FRANCHISE APPLY' => ['ga'=>'IARRATAS SAINCHEADÚNAIS','fr'=>'CANDIDATURE FRANCHISE','de'=>'FRANCHISE BEWERBEN','es'=>'SOLICITAR FRANQUICIA','it'=>'CANDIDATURA FRANCHISING','pt'=>'CANDIDATAR-SE A FRANQUIA','nl'=>'FRANCHISE AANVRAGEN','pl'=>'ZGŁOŚ FRANCZYZĘ','ar'=>'طلب امتياز'],
            'HIRING APPLY' => ['ga'=>'IARRATAS POIST','fr'=>'POSTULER','de'=>'BEWERBEN','es'=>'SOLICITAR EMPLEO','it'=>'CANDIDATI','pt'=>'CANDIDATAR-SE','nl'=>'SOLLICITEREN','pl'=>'APLIKUJ','ar'=>'التقديم للوظائف'],
            'CONTACT US' => ['ga'=>'DÉAN TEAGMHÁIL','fr'=>'CONTACTEZ-NOUS','de'=>'KONTAKT','es'=>'CONTÁCTANOS','it'=>'CONTATTACI','pt'=>'CONTACTE-NOS','nl'=>'NEEM CONTACT OP','pl'=>'KONTAKT','ar'=>'اتصل بنا'],
            'LANGUAGE' => ['ga'=>'TEANGA','fr'=>'LANGUE','de'=>'SPRACHE','es'=>'IDIOMA','it'=>'LINGUA','pt'=>'IDIOMA','nl'=>'TAAL','pl'=>'JĘZYK','ar'=>'اللغة'],
            'CURRENCY' => ['ga'=>'AIRGEAD REATHA','fr'=>'DEVISE','de'=>'WÄHRUNG','es'=>'MONEDA','it'=>'VALUTA','pt'=>'MOEDA','nl'=>'VALUTA','pl'=>'WALUTA','ar'=>'العملة'],
            'SEARCH' => ['ga'=>'CUARDAIGH','fr'=>'RECHERCHER','de'=>'SUCHEN','es'=>'BUSCAR','it'=>'CERCA','pt'=>'PESQUISAR','nl'=>'ZOEKEN','pl'=>'SZUKAJ','ar'=>'بحث'],
            'FILTERS' => ['ga'=>'SCAGAIRÍ','fr'=>'FILTRES','de'=>'FILTER','es'=>'FILTROS','it'=>'FILTRI','pt'=>'FILTROS','nl'=>'FILTERS','pl'=>'FILTRY','ar'=>'التصفية'],
            'RESET ALL' => ['ga'=>'ATHSHOCRIGH UILE','fr'=>'TOUT RÉINITIALISER','de'=>'ALLES ZURÜCKSETZEN','es'=>'RESTABLECER TODO','it'=>'REIMPOSTA TUTTO','pt'=>'REPOR TUDO','nl'=>'ALLES RESETTEN','pl'=>'RESETUJ WSZYSTKO','ar'=>'إعادة ضبط الكل'],
            'COLOUR' => ['ga'=>'DATH','fr'=>'COULEUR','de'=>'FARBE','es'=>'COLOR','it'=>'COLORE','pt'=>'COR','nl'=>'KLEUR','pl'=>'KOLOR','ar'=>'اللون'],
            'MATERIAL' => ['ga'=>'ÁBHAR','fr'=>'MATIÈRE','de'=>'MATERIAL','es'=>'MATERIAL','it'=>'MATERIALE','pt'=>'MATERIAL','nl'=>'MATERIAAL','pl'=>'MATERIAŁ','ar'=>'الخامة'],
            'SIZE' => ['ga'=>'MÉID','fr'=>'TAILLE','de'=>'GRÖSSE','es'=>'TALLA','it'=>'TAGLIA','pt'=>'TAMANHO','nl'=>'MAAT','pl'=>'ROZMIAR','ar'=>'المقاس'],
            'PRICE' => ['ga'=>'PRAGHAS','fr'=>'PRIX','de'=>'PREIS','es'=>'PRECIO','it'=>'PREZZO','pt'=>'PREÇO','nl'=>'PRIJS','pl'=>'CENA','ar'=>'السعر'],
            'AVAILABILITY' => ['ga'=>'INFHAIGHTEACHT','fr'=>'DISPONIBILITÉ','de'=>'VERFÜGBARKEIT','es'=>'DISPONIBILIDAD','it'=>'DISPONIBILITÀ','pt'=>'DISPONIBILIDADE','nl'=>'BESCHIKBAARHEID','pl'=>'DOSTĘPNOŚĆ','ar'=>'التوفر'],
            'APPLY FILTERS' => ['ga'=>'CUIR SCAGAIRÍ I BHFEIDHM','fr'=>'APPLIQUER LES FILTRES','de'=>'FILTER ANWENDEN','es'=>'APLICAR FILTROS','it'=>'APPLICA FILTRI','pt'=>'APLICAR FILTROS','nl'=>'FILTERS TOEPASSEN','pl'=>'ZASTOSUJ FILTRY','ar'=>'تطبيق التصفية'],
            'ADD TO CART' => ['ga'=>'CUIR SA CHISEÁN','fr'=>'AJOUTER AU PANIER','de'=>'IN DEN WARENKORB','es'=>'AÑADIR AL CARRITO','it'=>'AGGIUNGI AL CARRELLO','pt'=>'ADICIONAR AO CARRINHO','nl'=>'IN WINKELWAGEN','pl'=>'DODAJ DO KOSZYKA','ar'=>'أضف إلى السلة'],
            'VIEW DETAILS' => ['ga'=>'FÉACH SONRAÍ','fr'=>'VOIR LES DÉTAILS','de'=>'DETAILS ANSEHEN','es'=>'VER DETALLES','it'=>'VEDI DETTAGLI','pt'=>'VER DETALHES','nl'=>'BEKIJK DETAILS','pl'=>'ZOBACZ SZCZEGÓŁY','ar'=>'عرض التفاصيل'],
            'IN STOCK' => ['ga'=>'I STOC','fr'=>'EN STOCK','de'=>'AUF LAGER','es'=>'EN STOCK','it'=>'DISPONIBILE','pt'=>'EM STOCK','nl'=>'OP VOORRAAD','pl'=>'W MAGAZYNIE','ar'=>'متوفر'],
            'OUT OF STOCK' => ['ga'=>'AS STOC','fr'=>'RUPTURE DE STOCK','de'=>'NICHT AUF LAGER','es'=>'AGOTADO','it'=>'ESAURITO','pt'=>'ESGOTADO','nl'=>'UITVERKOCHT','pl'=>'BRAK W MAGAZYNIE','ar'=>'غير متوفر'],
            'CART' => ['ga'=>'CISEÁN','fr'=>'PANIER','de'=>'WARENKORB','es'=>'CARRITO','it'=>'CARRELLO','pt'=>'CARRINHO','nl'=>'WINKELWAGEN','pl'=>'KOSZYK','ar'=>'السلة'],
            'CHECKOUT' => ['ga'=>'SEICEÁIL AMACH','fr'=>'PAIEMENT','de'=>'KASSE','es'=>'PAGO','it'=>'CHECKOUT','pt'=>'FINALIZAR COMPRA','nl'=>'AFREKENEN','pl'=>'KASA','ar'=>'إتمام الشراء'],
            'SUBTOTAL' => ['ga'=>'FO-IOMLÁN','fr'=>'SOUS-TOTAL','de'=>'ZWISCHENSUMME','es'=>'SUBTOTAL','it'=>'SUBTOTALE','pt'=>'SUBTOTAL','nl'=>'SUBTOTAAL','pl'=>'SUMA CZĘŚCIOWA','ar'=>'المجموع الفرعي'],
            'SHIPPING' => ['ga'=>'LOINGEAS','fr'=>'LIVRAISON','de'=>'VERSAND','es'=>'ENVÍO','it'=>'SPEDIZIONE','pt'=>'ENVIO','nl'=>'VERZENDING','pl'=>'WYSYŁKA','ar'=>'الشحن'],
            'TOTAL' => ['ga'=>'IOMLÁN','fr'=>'TOTAL','de'=>'GESAMT','es'=>'TOTAL','it'=>'TOTALE','pt'=>'TOTAL','nl'=>'TOTAAL','pl'=>'SUMA','ar'=>'الإجمالي'],
        ];

        foreach ($dictionary as $source => $translations) {
            foreach ($translations as $locale => $translation) {
                DB::table('storefront_translations')->updateOrInsert(
                    ['company_id'=>$company->id,'locale'=>$locale,'translation_key'=>'ui.'.Str::slug($source,'_')],
                    ['namespace'=>'ui','source_text'=>$source,'source_hash'=>sha1($this->normalize($source)),'translation'=>$translation,'active'=>true,'updated_at'=>now(),'created_at'=>now()]
                );
            }
        }
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value) ?? $value));
    }

    private function dropColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) return;
        $existing = array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
        if ($existing === []) return;
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($existing));
    }
};
