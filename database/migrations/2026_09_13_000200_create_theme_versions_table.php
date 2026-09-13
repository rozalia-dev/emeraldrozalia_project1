<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('theme_versions')) {
            return;
        }

        Schema::create('theme_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name', 180);
            $table->string('scope', 40)->default('public');
            $table->string('environment', 32)->default('production');
            $table->string('locale', 12)->default('en');
            $table->unsignedInteger('version');
            $table->string('status', 32)->default('draft');
            $table->jsonb('token_payload');
            $table->jsonb('asset_references')->nullable();
            $table->jsonb('validation_errors')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('source_version_uuid')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('validated_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->timestampTz('rolled_back_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['company_id', 'scope', 'environment', 'locale', 'version'],
                'theme_versions_context_version_unique',
            );
            $table->index(
                ['company_id', 'scope', 'environment', 'locale', 'status'],
                'theme_versions_context_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('theme_versions');
    }
};
