<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'product_metadata') || ! Schema::hasColumn('products', 'published_at')) {
            Schema::table('products', function (Blueprint $table): void {
                if (! Schema::hasColumn('products', 'product_metadata')) {
                    $table->json('product_metadata')->nullable();
                }

                if (! Schema::hasColumn('products', 'published_at')) {
                    $table->timestamp('published_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'product_metadata') || Schema::hasColumn('products', 'published_at')) {
            Schema::table('products', function (Blueprint $table): void {
                $columns = [];

                if (Schema::hasColumn('products', 'product_metadata')) {
                    $columns[] = 'product_metadata';
                }

                if (Schema::hasColumn('products', 'published_at')) {
                    $columns[] = 'published_at';
                }

                $table->dropColumn($columns);
            });
        }
    }
};
