<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up():void
    {
        Schema::table('product_variants',function(Blueprint $table):void{
            if(!Schema::hasColumn('product_variants','barcode'))$table->string('barcode',120)->nullable()->unique();
            if(!Schema::hasColumn('product_variants','material'))$table->string('material',120)->nullable();
            if(!Schema::hasColumn('product_variants','style'))$table->string('style',120)->nullable();
            if(!Schema::hasColumn('product_variants','compare_price'))$table->decimal('compare_price',12,2)->nullable();
            if(!Schema::hasColumn('product_variants','cost_price'))$table->decimal('cost_price',12,2)->nullable();
            if(!Schema::hasColumn('product_variants','stock_total'))$table->integer('stock_total')->default(0);
            if(!Schema::hasColumn('product_variants','status'))$table->string('status',30)->default('active')->index();
            if(!Schema::hasColumn('product_variants','option_values'))$table->json('option_values')->nullable();
            if(!Schema::hasColumn('product_variants','low_stock_threshold'))$table->integer('low_stock_threshold')->default(10);
            if(!Schema::hasColumn('product_variants','track_inventory'))$table->boolean('track_inventory')->default(true);
            if(!Schema::hasColumn('product_variants','backorder'))$table->boolean('backorder')->default(false);
            if(!Schema::hasColumn('product_variants','sort_order'))$table->unsignedInteger('sort_order')->default(0);
            if(!Schema::hasColumn('product_variants','created_by'))$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            if(!Schema::hasColumn('product_variants','updated_by'))$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            if(!Schema::hasColumn('product_variants','disabled_at'))$table->timestampTz('disabled_at')->nullable();
        });
        DB::table('product_variants')->update(['stock_total'=>DB::raw('CASE WHEN stock_total < stock THEN stock ELSE stock_total END'),'status'=>DB::raw("CASE WHEN is_active = true THEN 'active' ELSE 'inactive' END")]);
        if(!Schema::hasTable('variant_media'))Schema::create('variant_media',function(Blueprint $table):void{$table->id();$table->uuid('uuid')->unique();$table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();$table->string('type',30);$table->string('disk',30)->default('public');$table->string('path');$table->string('alt_text')->nullable();$table->unsignedInteger('sort_order')->default(0);$table->json('metadata')->nullable();$table->boolean('active')->default(true);$table->timestampsTz();$table->index(['product_variant_id','type','sort_order']);});
        if(!Schema::hasTable('variant_settings'))Schema::create('variant_settings',function(Blueprint $table):void{$table->id();$table->foreignId('company_id')->nullable()->index()->constrained()->nullOnDelete();$table->boolean('auto_generate_sku')->default(true);$table->boolean('auto_manage_stock')->default(true);$table->boolean('sync_variant_stock')->default(true);$table->boolean('track_variant_inventory')->default(true);$table->unsignedTinyInteger('price_rounding')->default(2);$table->string('inventory_policy',20)->default('deny');$table->boolean('backorder')->default(false);$table->integer('low_stock_threshold')->default(10);$table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();$table->timestampsTz();});
    }
    public function down():void
    {
        Schema::dropIfExists('variant_settings');Schema::dropIfExists('variant_media');
        Schema::table('product_variants',function(Blueprint $table):void{
            foreach(['created_by','updated_by'] as $column)if(Schema::hasColumn('product_variants',$column))$table->dropConstrainedForeignId($column);
            foreach(['barcode','material','style','compare_price','cost_price','stock_total','status','option_values','low_stock_threshold','track_inventory','backorder','sort_order','disabled_at'] as $column)if(Schema::hasColumn('product_variants',$column))$table->dropColumn($column);
        });
    }
};
