<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('integration_connections')) {
            return;
        }

        $this->addColumnIfMissing('health_message', static fn (Blueprint $table): mixed => $table->text('health_message')->nullable());
        $this->addColumnIfMissing('last_checked_at', static fn (Blueprint $table): mixed => $table->timestampTz('last_checked_at')->nullable()->index());

        $this->replaceGlobalServiceUniqueIndex();
    }

    public function down(): void
    {
        if (! Schema::hasTable('integration_connections')) {
            return;
        }

        foreach (Schema::getIndexes('integration_connections') as $index) {
            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === ['company_id', 'service']) {
                Schema::table('integration_connections', static function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index['name']);
                });
            }
        }

        foreach (['last_checked_at', 'health_message'] as $column) {
            if (! Schema::hasColumn('integration_connections', $column)) {
                continue;
            }

            Schema::table('integration_connections', static function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    /** @param callable(Blueprint): mixed $definition */
    private function addColumnIfMissing(string $column, callable $definition): void
    {
        if (Schema::hasColumn('integration_connections', $column)) {
            return;
        }

        Schema::table('integration_connections', static function (Blueprint $table) use ($definition): void {
            $definition($table);
        });
    }

    private function replaceGlobalServiceUniqueIndex(): void
    {
        $hasTenantUnique = false;

        foreach (Schema::getIndexes('integration_connections') as $index) {
            $columns = $index['columns'] ?? [];
            if (($index['unique'] ?? false) && $columns === ['service']) {
                Schema::table('integration_connections', static function (Blueprint $table) use ($index): void {
                    $table->dropUnique($index['name']);
                });
            }

            if (($index['unique'] ?? false) && $columns === ['company_id', 'service']) {
                $hasTenantUnique = true;
            }
        }

        if (! $hasTenantUnique) {
            Schema::table('integration_connections', static function (Blueprint $table): void {
                $table->unique(['company_id', 'service'], 'integration_connections_company_service_unique');
            });
        }
    }
};
