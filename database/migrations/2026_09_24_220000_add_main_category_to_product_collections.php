<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_collections', function (Blueprint $table): void {
            $table->foreignId('main_category_id')
                ->nullable()
                ->after('company_id')
                ->constrained('categories')
                ->nullOnDelete()
                ->index();
        });

        $heritageCategoryId = DB::table('categories')
            ->whereNull('parent_id')
            ->whereRaw('LOWER(name) = ?', ['heritage'])
            ->value('id');

        if ($heritageCategoryId) {
            DB::table('product_collections')
                ->where(function ($query): void {
                    $query->whereRaw('LOWER(name) LIKE ?', ['%heritage%'])
                        ->orWhereRaw('LOWER(slug) LIKE ?', ['%heritage%']);
                })
                ->update(['main_category_id' => $heritageCategoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('product_collections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('main_category_id');
        });
    }
};
