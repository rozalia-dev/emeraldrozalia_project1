<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUSES = ['pending', 'in-progress', 'approved', 'rejected', 'escalated', 'cancelled'];

    private const ADDED_COLUMNS = [
        'company_id', 'approver_id', 'request_type', 'title', 'description', 'requester_name',
        'approver_name', 'reference', 'entity', 'entity_type', 'entity_uuid', 'source', 'priority',
        'due_at', 'record_date', 'metadata', 'correlation_id', 'request_hash', 'idempotency_key',
        'version', 'deleted_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('approvals')) {
            return;
        }

        Schema::table('approvals', function (Blueprint $table): void {
            if (! Schema::hasColumn('approvals', 'company_id')) {
                $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
            }
            if (! Schema::hasColumn('approvals', 'approver_id')) {
                $table->foreignId('approver_id')->nullable()->index()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('approvals', 'request_type')) {
                $table->string('request_type', 120)->nullable()->index();
            }
            if (! Schema::hasColumn('approvals', 'title')) {
                $table->string('title', 180)->nullable();
            }
            if (! Schema::hasColumn('approvals', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('approvals', 'requester_name')) {
                $table->string('requester_name', 180)->nullable();
            }
            if (! Schema::hasColumn('approvals', 'approver_name')) {
                $table->string('approver_name', 180)->nullable();
            }
            if (! Schema::hasColumn('approvals', 'reference')) {
                $table->string('reference', 100)->nullable();
            }
            if (! Schema::hasColumn('approvals', 'entity')) {
                $table->string('entity', 180)->nullable();
            }
            if (! Schema::hasColumn('approvals', 'entity_type')) {
                $table->string('entity_type', 120)->nullable();
            }
            if (! Schema::hasColumn('approvals', 'entity_uuid')) {
                $table->uuid('entity_uuid')->nullable()->index();
            }
            if (! Schema::hasColumn('approvals', 'source')) {
                $table->string('source', 120)->nullable();
            }
            if (! Schema::hasColumn('approvals', 'priority')) {
                $table->string('priority', 20)->default('normal')->index();
            }
            if (! Schema::hasColumn('approvals', 'due_at')) {
                $table->timestampTz('due_at')->nullable()->index();
            }
            if (! Schema::hasColumn('approvals', 'record_date')) {
                $table->date('record_date')->nullable()->index();
            }
            if (! Schema::hasColumn('approvals', 'metadata')) {
                $table->jsonb('metadata')->nullable();
            }
            if (! Schema::hasColumn('approvals', 'correlation_id')) {
                $table->uuid('correlation_id')->nullable()->index();
            }
            if (! Schema::hasColumn('approvals', 'request_hash')) {
                $table->string('request_hash', 64)->nullable();
            }
            if (! Schema::hasColumn('approvals', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable();
            }
            if (! Schema::hasColumn('approvals', 'version')) {
                $table->unsignedInteger('version')->default(1);
            }
            if (! Schema::hasColumn('approvals', 'deleted_at')) {
                $table->softDeletesTz();
            }
        });

        if (! Schema::hasIndex('approvals', 'approvals_reference_unique')) {
            Schema::table('approvals', function (Blueprint $table): void {
                $table->unique('reference', 'approvals_reference_unique');
            });
        }
        if (! Schema::hasIndex('approvals', 'approvals_idempotency_key_unique')) {
            Schema::table('approvals', function (Blueprint $table): void {
                $table->unique('idempotency_key', 'approvals_idempotency_key_unique');
            });
        }
        if (! Schema::hasIndex('approvals', 'approvals_company_status_index')) {
            Schema::table('approvals', function (Blueprint $table): void {
                $table->index(['company_id', 'status'], 'approvals_company_status_index');
            });
        }
        if (! Schema::hasIndex('approvals', 'approvals_company_priority_index')) {
            Schema::table('approvals', function (Blueprint $table): void {
                $table->index(['company_id', 'priority'], 'approvals_company_priority_index');
            });
        }
        if (! Schema::hasIndex('approvals', 'approvals_company_due_at_index')) {
            Schema::table('approvals', function (Blueprint $table): void {
                $table->index(['company_id', 'due_at'], 'approvals_company_due_at_index');
            });
        }

        DB::table('approvals')
            ->whereNotIn('status', self::STATUSES)
            ->update(['status' => 'pending']);

        if (DB::getDriverName() === 'pgsql') {
            $exists = DB::table('pg_constraint')->where('conname', 'approvals_status_check')->exists();
            if (! $exists) {
                DB::statement("ALTER TABLE approvals ADD CONSTRAINT approvals_status_check CHECK (status IN ('pending', 'in-progress', 'approved', 'rejected', 'escalated', 'cancelled'))");
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('approvals')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE approvals DROP CONSTRAINT IF EXISTS approvals_status_check');
        }

        foreach (['approvals_reference_unique', 'approvals_idempotency_key_unique'] as $index) {
            if (Schema::hasIndex('approvals', $index)) {
                Schema::table('approvals', function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index);
                });
            }
        }

        foreach ([
            'approvals_company_status_index',
            'approvals_company_priority_index',
            'approvals_company_due_at_index',
        ] as $index) {
            if (Schema::hasIndex('approvals', $index)) {
                Schema::table('approvals', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index);
                });
            }
        }

        foreach (self::ADDED_COLUMNS as $column) {
            $this->dropColumnIfPresent('approvals', $column);
        }
    }

    private function dropColumnIfPresent(string $tableName, string $column): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $column)) {
            return;
        }

        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (($foreignKey['columns'] ?? []) !== [$column]) {
                continue;
            }

            $name = $foreignKey['name'] ?? null;
            if ($name) {
                Schema::table($tableName, function (Blueprint $table) use ($name): void {
                    $table->dropForeign($name);
                });
            }
        }

        Schema::table($tableName, function (Blueprint $table) use ($column): void {
            $table->dropColumn($column);
        });
    }
};
