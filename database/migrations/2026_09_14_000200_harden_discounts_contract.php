<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discounts')) {
            return;
        }

        Schema::table('discounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('discounts', 'metadata')) {
                $table->json('metadata')->nullable();
            }
            if (! Schema::hasColumn('discounts', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('discounts')) {
            return;
        }

        Schema::table('discounts', function (Blueprint $table): void {
            if (Schema::hasColumn('discounts', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
            if (Schema::hasColumn('discounts', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};
