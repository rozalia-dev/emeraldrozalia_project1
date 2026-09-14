<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('returns')) {
            return;
        }

        Schema::table('returns', function (Blueprint $table): void {
            if (! Schema::hasColumn('returns', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable();
            }
            if (! Schema::hasColumn('returns', 'request_hash')) {
                $table->string('request_hash', 64)->nullable();
            }
            if (! Schema::hasColumn('returns', 'correlation_id')) {
                $table->uuid('correlation_id')->nullable()->index();
            }
            if (! Schema::hasColumn('returns', 'version')) {
                $table->unsignedInteger('version')->default(1)->index();
            }
        });

        if (! Schema::hasIndex('returns', 'returns_idempotency_key_unique')) {
            Schema::table('returns', function (Blueprint $table): void {
                $table->unique('idempotency_key', 'returns_idempotency_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('returns')) {
            return;
        }

        if (Schema::hasIndex('returns', 'returns_idempotency_key_unique')) {
            Schema::table('returns', function (Blueprint $table): void {
                $table->dropUnique('returns_idempotency_key_unique');
            });
        }

        Schema::table('returns', function (Blueprint $table): void {
            foreach (['idempotency_key', 'request_hash', 'correlation_id', 'version'] as $column) {
                if (Schema::hasColumn('returns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
