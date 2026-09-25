<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('product_media')) {
            return;
        }

        DB::table('products')
            ->where(function ($query): void {
                $query->whereNull('image')->orWhere('image', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($products): void {
                foreach ($products as $product) {
                    $path = DB::table('product_media')
                        ->where('product_id', $product->id)
                        ->whereIn('type', ['image', 'gallery'])
                        ->where('active', true)
                        ->where('approval_status', 'approved')
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->value('path');

                    if (is_string($path) && trim($path) !== '') {
                        DB::table('products')
                            ->where('id', $product->id)
                            ->update(['image' => $path]);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        // Data backfill only. Do not clear valid product image assignments on rollback.
    }
};
