<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('franchise_stores')) {
            Schema::table('franchise_stores', function (Blueprint $table): void {
                if (! Schema::hasColumn('franchise_stores', 'version')) {
                    $table->unsignedInteger('version')->default(1);
                }
                if (! Schema::hasColumn('franchise_stores', 'status_changed_at')) {
                    $table->timestampTz('status_changed_at')->nullable();
                }
                if (! Schema::hasColumn('franchise_stores', 'status_changed_by')) {
                    $table->foreignId('status_changed_by')->nullable()->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('franchise_stores') && ! Schema::hasTable('franchise_store_actions')) {
            Schema::create('franchise_store_actions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
                $table->foreignId('franchise_store_id')->constrained('franchise_stores')->cascadeOnDelete();
                $table->foreignId('performed_by')->nullable()->index()->constrained('users')->nullOnDelete();
                $table->string('action', 30);
                $table->string('idempotency_key', 100);
                $table->string('request_hash', 64);
                $table->unsignedInteger('expected_version')->nullable();
                $table->string('from_status', 30);
                $table->string('to_status', 30);
                $table->text('reason')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestampsTz();

                $table->unique(
                    ['franchise_store_id', 'idempotency_key'],
                    'franchise_store_actions_store_key_unique',
                );
                $table->index(['company_id', 'action']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('franchise_store_actions');

        if (Schema::hasTable('franchise_stores')) {
            Schema::table('franchise_stores', function (Blueprint $table): void {
                if (Schema::hasColumn('franchise_stores', 'status_changed_by')) {
                    $table->dropConstrainedForeignId('status_changed_by');
                }
                if (Schema::hasColumn('franchise_stores', 'status_changed_at')) {
                    $table->dropColumn('status_changed_at');
                }
                if (Schema::hasColumn('franchise_stores', 'version')) {
                    $table->dropColumn('version');
                }
            });
        }
    }
};
