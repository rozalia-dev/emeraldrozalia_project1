<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automation_rules')) {
            $this->addColumnIfMissing('automation_rules', 'last_run_at', static fn (Blueprint $table): mixed => $table->timestampTz('last_run_at')->nullable());
            $this->addColumnIfMissing('automation_rules', 'last_run_status', static fn (Blueprint $table): mixed => $table->string('last_run_status', 30)->nullable());
        }

        if (! Schema::hasTable('automation_runs')) {
            Schema::create('automation_runs', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
                $table->foreignId('automation_rule_id')->nullable()->index()->constrained('automation_rules')->nullOnDelete();
                $table->string('event', 120)->index();
                $table->string('event_key', 180);
                $table->string('idempotency_key', 64)->unique();
                $table->string('status', 30)->index();
                $table->jsonb('payload')->nullable();
                $table->jsonb('result')->nullable();
                $table->text('error')->nullable();
                $table->timestampTz('started_at')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->timestampsTz();
                $table->unique(['automation_rule_id', 'event_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_runs');

        foreach (['last_run_status', 'last_run_at'] as $column) {
            if (! Schema::hasTable('automation_rules') || ! Schema::hasColumn('automation_rules', $column)) {
                continue;
            }

            Schema::table('automation_rules', static function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    /** @param callable(Blueprint): mixed $definition */
    private function addColumnIfMissing(string $tableName, string $column, callable $definition): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            Schema::table($tableName, static function (Blueprint $table) use ($definition): void {
                $definition($table);
            });
        }
    }
};
