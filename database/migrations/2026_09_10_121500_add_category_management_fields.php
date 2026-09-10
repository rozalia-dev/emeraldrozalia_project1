<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('categories', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('company_id')->constrained('categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('categories', 'status')) {
                $table->string('status', 20)->default('active')->index();
            }
            if (! Schema::hasColumn('categories', 'is_visible')) {
                $table->boolean('is_visible')->default(true)->index();
            }
            if (! Schema::hasColumn('categories', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('categories', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        DB::table('categories')->where('is_active', false)->update([
            'status' => 'inactive',
            'is_visible' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            if (Schema::hasColumn('categories', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }
            if (Schema::hasColumn('categories', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('categories', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
            if (Schema::hasColumn('categories', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('categories', 'is_visible')) {
                $table->dropColumn('is_visible');
            }
        });
    }
};
