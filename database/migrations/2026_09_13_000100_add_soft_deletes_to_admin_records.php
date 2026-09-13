<?php

use IlluminateDatabaseMigrationsMigration;
use IlluminateDatabaseSchemaBlueprint;
use IlluminateSupportFacadesSchema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_records') || Schema::hasColumn('admin_records', 'deleted_at')) {
            return;
        }

        Schema::table('admin_records', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_records') || ! Schema::hasColumn('admin_records', 'deleted_at')) {
            return;
        }

        Schema::table('admin_records', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
