<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_collections') || ! Schema::hasTable('collection_product')) {
            return;
        }

        $companyId = Schema::hasTable('companies')
            ? DB::table('companies')->where('code', 'ERL')->value('id')
            : null;

        $collectionSeeds = [
            ['Spring Summer 2025', 'spring-summer-2025', 'seasonal', 'Spring / Summer 2025', true, 'visible'],
            ['Best Sellers', 'best-sellers', 'curated', 'All Season', true, 'visible'],
            ['New Arrivals', 'new-arrivals', 'automated', 'All Season', false, 'visible'],
            ['Premium Collection', 'premium-collection', 'curated', 'All Season', true, 'visible'],
            ['Wedding Collection', 'wedding-collection', 'occasion', 'Weddings', false, 'visible'],
            ['Corporate Gifting', 'corporate-gifting', 'occasion', 'Corporate', false, 'visible'],
            ['Limited Edition', 'limited-edition', 'curated', 'All Season', true, 'visible'],
            ['Winter Essentials', 'winter-essentials', 'seasonal', 'Winter 2024–2025', false, 'hidden'],
            ['Back to College', 'back-to-college', 'occasion', 'Student', false, 'visible'],
            ['Gift for Her', 'gift-for-her', 'occasion', 'Gifting', false, 'visible'],
        ];

        $productIds = collect();
        if (Schema::hasTable('products')) {
            $products = DB::table('products')->select('id')->where('is_active', true);

            if (Schema::hasColumn('products', 'status')) {
                $products->whereIn('status', ['active', 'published']);
            }
            if (Schema::hasColumn('products', 'deleted_at')) {
                $products->whereNull('deleted_at');
            }
            if ($companyId !== null && Schema::hasColumn('products', 'company_id')) {
                $products->where('company_id', $companyId);
            }

            $productIds = $products->orderBy('id')->pluck('id')->values();
        }

        foreach ($collectionSeeds as $index => $seed) {
            [$name, $slug, $type, $season, $featured, $visibility] = $seed;

            if (DB::table('product_collections')->where('slug', $slug)->exists()) {
                continue;
            }

            $now = now();
            $collectionId = DB::table('product_collections')->insertGetId([
                'public_uuid' => (string) Str::uuid(),
                'company_id' => $companyId,
                'name' => $name,
                'slug' => $slug,
                'type' => $type,
                'season' => $season,
                'description' => 'A considered Emerald Rozalia edit curated for '.$season.'.',
                'status' => 'active',
                'visibility' => $visibility,
                'is_featured' => $featured,
                'show_on_homepage' => $featured,
                'allow_in_filters' => true,
                'sort_order' => $index + 1,
                'meta_title' => $name.' Collection — Emerald Rozalia',
                'meta_description' => 'Explore the '.$name.' collection from Emerald Rozalia Limited.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($productIds->filter(fn ($productId, $productIndex): bool => ($productIndex + $index) % 3 !== 0)->values() as $productIndex => $productId) {
                DB::table('collection_product')->insertOrIgnore([
                    'collection_id' => $collectionId,
                    'product_id' => $productId,
                    'sort_order' => $productIndex + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data backfill is intentionally non-destructive on rollback. Collections may
        // have been edited by administrators after this migration was applied.
    }
};
