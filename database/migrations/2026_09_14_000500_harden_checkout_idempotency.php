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

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'shipping_method_code')) {
                $table->string('shipping_method_code', 80)->nullable()->after('shipping_method');
            }
            if (! Schema::hasColumn('orders', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable();
            }
            if (! Schema::hasColumn('orders', 'request_hash')) {
                $table->string('request_hash', 64)->nullable();
            }
            if (! Schema::hasColumn('orders', 'correlation_id')) {
                $table->uuid('correlation_id')->nullable()->index();
            }
        });

        if (! Schema::hasIndex('orders', 'orders_idempotency_key_unique')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unique('idempotency_key', 'orders_idempotency_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (Schema::hasIndex('orders', 'orders_idempotency_key_unique')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropUnique('orders_idempotency_key_unique');
            });
        }

        Schema::table('orders', function (Blueprint $table): void {
            foreach (['shipping_method_code', 'idempotency_key', 'request_hash', 'correlation_id'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
