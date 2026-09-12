<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->string('subtitle', 255)->nullable();
            $table->string('type', 30)->default('banner')->index();
            $table->string('position', 120)->default('Home - Main Slider')->index();
            $table->string('target_url', 500)->nullable();
            $table->string('target_type', 20)->default('internal');
            $table->string('status', 30)->default('draft')->index();
            $table->timestampTz('starts_at')->nullable()->index();
            $table->timestampTz('ends_at')->nullable()->index();
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedInteger('priority')->default(1);
            $table->string('image_disk', 30)->default('public');
            $table->string('image_path', 500)->nullable();
            $table->jsonb('device_visibility')->nullable();
            $table->jsonb('specific_pages')->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->string('title_text', 180)->nullable();
            $table->string('aria_label', 180)->nullable();
            $table->string('animation', 30)->default('fade');
            $table->boolean('autoplay')->default(true);
            $table->unsignedInteger('autoplay_speed')->default(5);
            $table->boolean('show_arrows')->default(true);
            $table->boolean('show_dots')->default(true);
            $table->boolean('pause_on_hover')->default(true);
            $table->jsonb('settings')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->softDeletesTz();
            $table->index(['company_id', 'status', 'type']);
            $table->index(['company_id', 'starts_at', 'ends_at']);
        });

        Schema::create('banner_revisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('banner_id')->constrained('banners')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('snapshot');
            $table->string('reason', 180)->nullable();
            $table->timestampsTz();
            $table->unique(['banner_id', 'version']);
            $table->index(['banner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_revisions');
        Schema::dropIfExists('banners');
    }
};
