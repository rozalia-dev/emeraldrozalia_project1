<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discounts') || ! Schema::hasColumn('discounts', 'deleted_at')) {
            return;
        }

        Schema::table('discounts', function (Blueprint $table): void {
            $table->dropUnique('discounts_code_unique');
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX discounts_code_unique ON discounts (code) WHERE deleted_at IS NULL');
        } else {
            Schema::table('discounts', function (Blueprint $table): void {
                $table->unique(['code', 'deleted_at'], 'discounts_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('discounts')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS discounts_code_unique');
        } else {
            Schema::table('discounts', function (Blueprint $table): void {
                $table->dropUnique('discounts_code_unique');
            });
        }

        Schema::table('discounts', function (Blueprint $table): void {
            $table->unique('code', 'discounts_code_unique');
        });
    }
};
