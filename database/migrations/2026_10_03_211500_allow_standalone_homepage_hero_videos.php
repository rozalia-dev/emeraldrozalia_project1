<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_media') || ! Schema::hasColumn('product_media', 'product_id')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "product_media" ALTER COLUMN "product_id" DROP NOT NULL');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `product_media` MODIFY `product_id` BIGINT UNSIGNED NULL');
            return;
        }

        Schema::table('product_media', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_media') || ! Schema::hasColumn('product_media', 'product_id')) {
            return;
        }

        // Do not destroy standalone homepage hero videos during rollback. If any
        // exist, preserve the nullable schema rather than deleting customer media.
        if (DB::table('product_media')->whereNull('product_id')->exists()) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE "product_media" ALTER COLUMN "product_id" SET NOT NULL');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `product_media` MODIFY `product_id` BIGINT UNSIGNED NOT NULL');
            return;
        }

        Schema::table('product_media', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
        });
    }
};
