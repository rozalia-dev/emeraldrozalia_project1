<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('categories', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique();
            }
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

        Category::withoutGlobalScopes()->whereNull('uuid')->orderBy('id')->chunkById(100, function ($categories): void {
            foreach ($categories as $category) {
                $category->forceFill(['uuid' => (string) Str::uuid()])->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            foreach (['updated_by', 'created_by'] as $column) {
                if (Schema::hasColumn('categories', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            if (Schema::hasColumn('categories', 'parent_id')) {
                $table->dropConstrainedForeignId('parent_id');
            }
            foreach (['is_visible', 'status', 'uuid'] as $column) {
                if (Schema::hasColumn('categories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
