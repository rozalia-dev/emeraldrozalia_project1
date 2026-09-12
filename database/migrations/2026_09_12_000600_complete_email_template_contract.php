<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TEMPLATE_STATUSES = ['draft', 'pending_approval', 'active', 'archived'];

    public function up(): void
    {
        $this->addTemplateColumns();
        $this->addAuditColumns();
        $this->addTemplateStatusConstraint();
    }

    public function down(): void
    {
        $this->dropTemplateStatusConstraint();

        foreach (['communication_templates', 'audit_logs'] as $table) {
            foreach ($this->columnsFor($table) as $column) {
                $this->dropColumnIfPresent($table, $column);
            }
        }
    }

    private function addTemplateColumns(): void
    {
        if (! Schema::hasTable('communication_templates')) {
            return;
        }

        Schema::table('communication_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('communication_templates', 'company_id')) {
                $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
            }
            if (! Schema::hasColumn('communication_templates', 'created_by')) {
                $table->foreignId('created_by')->nullable()->index()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('communication_templates', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->index()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('communication_templates', 'idempotency_key')) {
                $table->string('idempotency_key', 100)->nullable()->unique('communication_templates_idempotency_key_unique');
            }
            if (! Schema::hasColumn('communication_templates', 'request_hash')) {
                $table->string('request_hash', 64)->nullable();
            }
            if (! Schema::hasColumn('communication_templates', 'correlation_id')) {
                $table->uuid('correlation_id')->nullable()->index();
            }
            if (! Schema::hasColumn('communication_templates', 'version')) {
                $table->unsignedInteger('version')->default(1);
            }
            if (! Schema::hasColumn('communication_templates', 'published_at')) {
                $table->timestampTz('published_at')->nullable();
            }
            if (! Schema::hasColumn('communication_templates', 'archived_at')) {
                $table->timestampTz('archived_at')->nullable();
            }
            if (! Schema::hasColumn('communication_templates', 'deleted_at')) {
                $table->softDeletesTz();
            }
        });

        if (! Schema::hasIndex('communication_templates', 'communication_templates_channel_status_index') || ! Schema::hasIndex('communication_templates', 'communication_templates_company_status_index')) {
            Schema::table('communication_templates', function (Blueprint $table): void {
                if (! Schema::hasIndex('communication_templates', 'communication_templates_channel_status_index')) {
                    $table->index(['channel', 'status'], 'communication_templates_channel_status_index');
                }
                if (! Schema::hasIndex('communication_templates', 'communication_templates_company_status_index')) {
                    $table->index(['company_id', 'status'], 'communication_templates_company_status_index');
                }
            });
        }

        DB::table('communication_templates')
            ->whereNotIn('status', self::TEMPLATE_STATUSES)
            ->update(['status' => 'draft']);
    }

    private function addAuditColumns(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('audit_logs', 'actor_uuid')) {
                $table->uuid('actor_uuid')->nullable()->index();
            }
            if (! Schema::hasColumn('audit_logs', 'subject_uuid')) {
                $table->uuid('subject_uuid')->nullable()->index();
            }
        });
    }

    private function addTemplateStatusConstraint(): void
    {
        if (! Schema::hasTable('communication_templates') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        $exists = DB::table('pg_constraint')->where('conname', 'communication_templates_status_check')->exists();
        if (! $exists) {
            DB::statement("ALTER TABLE communication_templates ADD CONSTRAINT communication_templates_status_check CHECK (status IN ('draft', 'pending_approval', 'active', 'archived'))");
        }
    }

    private function dropTemplateStatusConstraint(): void
    {
        if (! Schema::hasTable('communication_templates') || DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE communication_templates DROP CONSTRAINT IF EXISTS communication_templates_status_check');
    }

    private function columnsFor(string $table): array
    {
        return $table === 'communication_templates'
            ? ['company_id', 'created_by', 'updated_by', 'idempotency_key', 'request_hash', 'correlation_id', 'version', 'published_at', 'archived_at', 'deleted_at']
            : ['actor_uuid', 'subject_uuid'];
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
