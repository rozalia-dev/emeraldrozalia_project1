<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        // This migration publishes owner-approved catalog content on real releases.
        // Feature tests intentionally build their own isolated fixtures and assert
        // exact row counts, so do not inject production catalog rows while testing.
        if (app()->environment('testing')) {
            return;
        }

        $now = now();

        $companyId = DB::table('companies')->where('code', 'ERL')->value('id');
        if (! $companyId) {
            $companyId = DB::table('companies')->insertGetId([
                'name' => 'Emerald Rozalia',
                'legal_name' => 'Emerald Rozalia Limited',
                'code' => 'ERL',
                'country_code' => 'IE',
                'base_currency' => 'EUR',
                'default_locale' => 'en',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $categoryId = DB::table('categories')->where('slug', 'bucket-hats')->value('id');
        if (! $categoryId) {
            $categoryId = DB::table('categories')->insertGetId([
                'company_id' => $companyId,
                'public_uuid' => (string) Str::uuid(),
                'name' => 'Bucket Hats',
                'slug' => 'bucket-hats',
                'description' => 'Premium Emerald Rozalia bucket hats made in Limerick, Ireland.',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $sku = 'ER-IHBH-001';
        $slug = 'irish-heritage-bucket-hat-emerald-green';
        $image = '/assets/products/irish-heritage-bucket-hat/front.jpg';

        $productId = DB::table('products')->where('sku', $sku)->value('id');
        $productData = [
            'company_id' => $companyId,
            'category_id' => $categoryId,
            'name' => 'Irish Heritage Bucket Hat – Emerald Green',
            'slug' => $slug,
            'sku' => $sku,
            'description' => 'Emerald green Irish heritage bucket hat with Emerald Rozalia crest and Irish tricolour detailing.',
            'price' => 44.99,
            'stock' => 100,
            'colours' => json_encode(['Emerald Green']),
            'sizes' => json_encode(['S / M', 'M / L', 'L / XL']),
            'image' => $image,
            'spin_images' => null,
            'is_new' => true,
            'is_active' => true,
            'status' => 'active',
            'brand' => 'Emerald Rozalia',
            'updated_at' => $now,
        ];

        if ($productId) {
            DB::table('products')->where('id', $productId)->update($productData);
        } else {
            $productId = DB::table('products')->insertGetId($productData + [
                'public_uuid' => (string) Str::uuid(),
                'created_at' => $now,
            ]);
        }

        foreach ([
            ['STD-SM', 'S / M'],
            ['STD-ML', 'M / L'],
            ['STD-LXL', 'L / XL'],
        ] as [$suffix, $size]) {
            $variantSku = $sku.'-'.$suffix;
            $variantId = DB::table('product_variants')->where('sku', $variantSku)->value('id');
            $variantData = [
                'product_id' => $productId,
                'colour' => 'Emerald Green',
                'size' => $size,
                'price' => 44.99,
                'stock' => 100,
                'is_active' => true,
                'updated_at' => $now,
            ];
            if ($variantId) {
                DB::table('product_variants')->where('id', $variantId)->update($variantData);
            } else {
                DB::table('product_variants')->insert($variantData + [
                    'public_uuid' => (string) Str::uuid(),
                    'sku' => $variantSku,
                    'created_at' => $now,
                ]);
            }
        }

        $mediaId = DB::table('product_media')
            ->where('product_id', $productId)
            ->where('path', $image)
            ->value('id');

        $mediaData = [
            'product_id' => $productId,
            'type' => 'image',
            'disk' => 'public',
            'path' => $image,
            'alt_text' => 'Emerald Rozalia Irish Heritage Bucket Hat in emerald green',
            'sort_order' => 0,
            'metadata' => json_encode(['source' => 'owner_supplied_original', 'view' => 'front']),
            'active' => true,
            'updated_at' => $now,
        ];

        if ($mediaId) {
            DB::table('product_media')->where('id', $mediaId)->update($mediaData);
        } else {
            DB::table('product_media')->insert($mediaData + [
                'uuid' => (string) Str::uuid(),
                'created_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $productId = DB::table('products')->where('sku', 'ER-IHBH-001')->value('id');
        if (! $productId) {
            return;
        }

        DB::table('product_media')->where('product_id', $productId)->delete();
        DB::table('product_variants')->where('product_id', $productId)->delete();
        DB::table('products')->where('id', $productId)->delete();
    }
};
