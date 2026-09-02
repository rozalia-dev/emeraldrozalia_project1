<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void {
 Schema::create('companies',function(Blueprint $t){$t->id();$t->string('name');$t->string('legal_name')->nullable();$t->string('code')->unique();$t->string('country_code',2)->default('IE');$t->string('base_currency',3)->default('EUR');$t->string('default_locale',10)->default('en');$t->boolean('active')->default(true);$t->json('settings')->nullable();$t->timestamps();});
 Schema::create('currencies',function(Blueprint $t){$t->string('code',3)->primary();$t->string('name');$t->string('symbol',8);$t->unsignedTinyInteger('decimals')->default(2);$t->boolean('active')->default(true);$t->timestamps();});
 Schema::create('exchange_rates',function(Blueprint $t){$t->id();$t->string('base_currency',3);$t->string('quote_currency',3);$t->decimal('rate',18,8);$t->date('rate_date');$t->string('source')->default('manual');$t->timestamps();$t->unique(['base_currency','quote_currency','rate_date']);});
 Schema::create('languages',function(Blueprint $t){$t->string('locale',10)->primary();$t->string('name');$t->string('native_name');$t->boolean('active')->default(true);$t->timestamps();});
 Schema::create('company_user',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->string('role')->default('staff');$t->boolean('is_default')->default(false);$t->timestamps();$t->unique(['company_id','user_id']);});
 Schema::create('company_languages',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->string('locale',10);$t->boolean('is_default')->default(false);$t->timestamps();$t->unique(['company_id','locale']);});
 Schema::create('company_currencies',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->string('currency_code',3);$t->boolean('is_base')->default(false);$t->boolean('enabled_storefront')->default(true);$t->timestamps();$t->unique(['company_id','currency_code']);});
 foreach(['products','categories','orders','inquiries','stores','admin_records','discounts','shipping_methods'] as $table){ if(Schema::hasTable($table) && !Schema::hasColumn($table,'company_id')) Schema::table($table,function(Blueprint $t){$t->foreignId('company_id')->nullable()->index()->constrained()->nullOnDelete();}); }
 if(Schema::hasTable('orders')) Schema::table('orders',function(Blueprint $t){ if(!Schema::hasColumn('orders','currency_code'))$t->string('currency_code',3)->default('EUR'); if(!Schema::hasColumn('orders','exchange_rate'))$t->decimal('exchange_rate',18,8)->default(1);});
 if(Schema::hasTable('products')) Schema::table('products',function(Blueprint $t){ if(!Schema::hasColumn('products','translations'))$t->json('translations')->nullable();});
 if(Schema::hasTable('categories')) Schema::table('categories',function(Blueprint $t){ if(!Schema::hasColumn('categories','translations'))$t->json('translations')->nullable();});
 }
 public function down(): void { foreach(['company_currencies','company_languages','company_user','exchange_rates','languages','currencies','companies'] as $t) Schema::dropIfExists($t); }
};
