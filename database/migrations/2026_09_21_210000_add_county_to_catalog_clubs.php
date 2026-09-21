<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('catalog_clubs', 'catalog_county_code')) {
            Schema::table('catalog_clubs', function (Blueprint $table): void {
                $table->string('catalog_county_code', 16)->nullable()->after('catalog_country_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('catalog_clubs', 'catalog_county_code')) {
            Schema::table('catalog_clubs', function (Blueprint $table): void {
                $table->dropColumn('catalog_county_code');
            });
        }
    }
};
