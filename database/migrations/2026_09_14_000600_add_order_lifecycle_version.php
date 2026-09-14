<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'version')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unsignedInteger('version')->default(1)->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'version')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('version');
            });
        }
    }
};
