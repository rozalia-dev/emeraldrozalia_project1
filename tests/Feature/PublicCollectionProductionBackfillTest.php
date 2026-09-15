<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicCollectionProductionBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_public_collection_rows_can_be_backfilled_without_overwriting_existing_admin_collection(): void
    {
        $company = Company::query()->firstOrCreate(
            ['code' => 'ERL'],
            [
                'name' => 'Emerald Rozalia',
                'legal_name' => 'Emerald Rozalia Limited',
                'country_code' => 'IE',
                'base_currency' => 'EUR',
                'default_locale' => 'en',
                'active' => true,
            ]
        );

        DB::table('collection_product')->delete();
        DB::table('product_collections')->delete();

        $custom = ProductCollection::create([
            'company_id' => $company->id,
            'name' => 'Premium Collection — Admin Edited',
            'slug' => 'premium-collection',
            'type' => 'curated',
            'season' => 'Admin Season',
            'description' => 'Keep this administrator-managed content.',
            'status' => 'active',
            'visibility' => 'visible',
            'is_featured' => true,
            'show_on_homepage' => true,
            'allow_in_filters' => true,
            'sort_order' => 50,
        ]);

        Product::query()->create([
            'company_id' => $company->id,
            'name' => 'Backfill Product',
            'slug' => 'backfill-product',
            'sku' => 'ER-BACKFILL-001',
            'price' => 39.99,
            'stock' => 10,
            'status' => 'active',
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_09_15_180000_backfill_public_product_collections.php');
        $migration->up();

        $this->assertDatabaseHas('product_collections', [
            'slug' => 'best-sellers',
            'status' => 'active',
            'visibility' => 'visible',
        ]);
        $this->assertDatabaseHas('product_collections', [
            'slug' => 'limited-edition',
            'status' => 'active',
            'visibility' => 'visible',
        ]);
        $this->assertDatabaseHas('product_collections', [
            'id' => $custom->id,
            'slug' => 'premium-collection',
            'name' => 'Premium Collection — Admin Edited',
            'description' => 'Keep this administrator-managed content.',
            'sort_order' => 50,
        ]);

        $bestSellersId = ProductCollection::query()->where('slug', 'best-sellers')->value('id');
        $this->assertNotNull($bestSellersId);
        $this->assertDatabaseHas('collection_product', [
            'collection_id' => $bestSellersId,
        ]);
    }
}
