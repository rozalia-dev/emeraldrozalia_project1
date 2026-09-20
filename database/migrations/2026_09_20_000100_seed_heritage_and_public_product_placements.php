<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')
            || ! Schema::hasTable('product_collections')
            || ! Schema::hasTable('collection_product')) {
            return;
        }

        $companyId = DB::table('companies')->where('code', 'ERL')->value('id');
        if ($companyId === null) {
            return;
        }

        $now = now();
        $definitions = [
            [
                'name' => 'Best Sellers',
                'slug' => 'best-sellers',
                'type' => 'curated',
                'description' => 'Customer favourites selected by the Emerald Rozalia team.',
                'is_featured' => true,
                'show_on_homepage' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Irish Heritage',
                'slug' => 'irish-heritage',
                'type' => 'curated',
                'description' => 'A curated collection of Irish heritage headwear.',
                'is_featured' => false,
                'show_on_homepage' => false,
                'sort_order' => 11,
            ],
        ];

        foreach ($definitions as $definition) {
            $exists = DB::table('product_collections')->where('slug', $definition['slug'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('product_collections')->insert([
                'public_uuid' => (string) Str::uuid(),
                'company_id' => $companyId,
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'type' => $definition['type'],
                'season' => 'All Season',
                'description' => $definition['description'],
                'status' => 'active',
                'visibility' => 'visible',
                'is_featured' => $definition['is_featured'],
                'show_on_homepage' => $definition['show_on_homepage'],
                'allow_in_filters' => true,
                'sort_order' => $definition['sort_order'],
                'meta_title' => $definition['name'].' — Emerald Rozalia',
                'meta_description' => $definition['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! Schema::hasTable('categories') || ! Schema::hasTable('products')) {
            return;
        }

        $heritageCollectionId = DB::table('product_collections')
            ->where('slug', 'irish-heritage')
            ->value('id');
        $categoryQuery = DB::table('categories')->whereIn('slug', [
            'irish-heritage-hats',
            'irish-heritage',
            'heritage',
        ]);
        if (Schema::hasColumn('categories', 'company_id')) {
            $categoryQuery->where('company_id', $companyId);
        }
        $categoryIds = $categoryQuery->pluck('id');
        if ($categoryIds->isEmpty()) {
            return;
        }

        $productsQuery = DB::table('products')->whereIn('category_id', $categoryIds);
        if (Schema::hasColumn('products', 'company_id')) {
            $productsQuery->where('company_id', $companyId);
        }

        foreach ($productsQuery->pluck('id') as $productId) {
            DB::table('collection_product')->insertOrIgnore([
                'collection_id' => $heritageCollectionId,
                'product_id' => $productId,
                'sort_order' => (int) $productId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep placement data because collections can be edited after this migration.
    }
};
