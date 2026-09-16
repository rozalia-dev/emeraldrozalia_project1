<?php

use App\Support\CatalogCountries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_countries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('code', 3)->unique();
            $table->string('name', 160);
            $table->boolean('is_eu')->default(false)->index();
            $table->boolean('is_uefa')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('catalog_clubs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('catalog_country_id')->constrained('catalog_countries')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('governing_body', 24)->index();
            $table->string('name', 180);
            $table->string('slug', 220);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['governing_body', 'catalog_country_id', 'slug'], 'catalog_club_scope_unique');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->string('taxonomy_type', 24)->nullable()->index();
            $table->foreignId('catalog_country_id')->nullable()->constrained('catalog_countries')->nullOnDelete();
            $table->foreignId('catalog_club_id')->nullable()->constrained('catalog_clubs')->nullOnDelete();
            $table->string('product_type', 32)->nullable()->index();
        });

        $now = now();
        $rows = array_map(static function (array $row) use ($now): array {
            return [
                ...$row,
                'public_uuid' => (string) Str::uuid(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, CatalogCountries::seedRows());

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('catalog_countries')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropForeign(['catalog_club_id']);
            $table->dropForeign(['catalog_country_id']);
            $table->dropColumn(['taxonomy_type', 'catalog_country_id', 'catalog_club_id', 'product_type']);
        });

        Schema::dropIfExists('catalog_clubs');
        Schema::dropIfExists('catalog_countries');
    }
};
