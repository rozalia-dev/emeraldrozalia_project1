<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('backup_runs')) {
            return;
        }

        $this->addColumnIfMissing('checksum', static fn (Blueprint $table): mixed => $table->string('checksum', 64)->nullable()->index());
        $this->addColumnIfMissing('verified_at', static fn (Blueprint $table): mixed => $table->timestampTz('verified_at')->nullable());
        $this->addColumnIfMissing('restore_status', static fn (Blueprint $table): mixed => $table->string('restore_status', 30)->nullable()->index());
        $this->addColumnIfMissing('restored_at', static fn (Blueprint $table): mixed => $table->timestampTz('restored_at')->nullable());
    }

    public function down(): void
    {
        foreach (['restored_at', 'restore_status', 'verified_at', 'checksum'] as $column) {
            if (! Schema::hasTable('backup_runs') || ! Schema::hasColumn('backup_runs', $column)) {
                continue;
            }

            Schema::table('backup_runs', static function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    /** @param callable(Blueprint): mixed $definition */
    private function addColumnIfMissing(string $column, callable $definition): void
    {
        if (Schema::hasColumn('backup_runs', $column)) {
            return;
        }

        Schema::table('backup_runs', static function (Blueprint $table) use ($definition): void {
            $definition($table);
        });
    }
};
