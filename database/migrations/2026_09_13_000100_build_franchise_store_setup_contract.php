<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['franchise_applications', 'franchise_stores', 'franchise_milestones'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'company_id')) {
                    $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
                }

                if ($tableName === 'franchise_milestones' && ! Schema::hasColumn($tableName, 'deleted_at')) {
                    $table->softDeletesTz();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['franchise_applications', 'franchise_stores', 'franchise_milestones'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (Schema::hasColumn($tableName, 'company_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('company_id');
                });
            }

            if ($tableName === 'franchise_milestones' && Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('deleted_at');
                });
            }
        }
    }
};
