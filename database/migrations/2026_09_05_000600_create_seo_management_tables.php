<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'seo')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->json('seo')->nullable();
            });
        }

        if (! Schema::hasColumn('categories', 'meta_title')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->string('meta_title')->nullable();
            });
        }

        if (! Schema::hasColumn('categories', 'meta_description')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->text('meta_description')->nullable();
            });
        }

        if (! Schema::hasColumn('categories', 'seo')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->json('seo')->nullable();
            });
        }

        if (! Schema::hasTable('seo_settings')) {
            Schema::create('seo_settings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete()->index();
                $table->string('key', 100);
                $table->json('value')->nullable();
                $table->timestampsTz();
                $table->unique(['company_id', 'key']);
            });
        }

        if (! Schema::hasTable('seo_audits')) {
            Schema::create('seo_audits', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete()->index();
                $table->unsignedTinyInteger('score')->default(0);
                $table->json('summary')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampTz('started_at')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->timestampsTz();
            });
        }

        if (! Schema::hasTable('seo_issues')) {
            Schema::create('seo_issues', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete()->index();
                $table->string('source_type', 40);
                $table->unsignedBigInteger('source_id');
                $table->string('issue_type', 80);
                $table->string('path', 255);
                $table->string('title', 180);
                $table->string('severity', 20)->default('medium');
                $table->string('status', 20)->default('open')->index();
                $table->text('details')->nullable();
                $table->timestampTz('last_seen_at')->nullable();
                $table->timestampTz('resolved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampsTz();
                $table->unique(['company_id', 'source_type', 'source_id', 'issue_type']);
                $table->index(['status', 'severity']);
            });
        }

        if (! Schema::hasTable('seo_redirects')) {
            Schema::create('seo_redirects', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete()->index();
                $table->string('from_path', 255);
                $table->string('to_path', 255);
                $table->unsignedSmallInteger('status_code')->default(301);
                $table->boolean('active')->default(true);
                $table->timestampsTz();
                $table->unique(['company_id', 'from_path']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('seo_issues');
        Schema::dropIfExists('seo_audits');
        Schema::dropIfExists('seo_settings');

        if (Schema::hasColumn('categories', 'meta_title')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->dropColumn('meta_title');
            });
        }

        if (Schema::hasColumn('categories', 'meta_description')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->dropColumn('meta_description');
            });
        }

        if (Schema::hasColumn('categories', 'seo')) {
            Schema::table('categories', function (Blueprint $table): void {
                $table->dropColumn('seo');
            });
        }

        if (Schema::hasColumn('products', 'seo')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('seo');
            });
        }
    }
};
