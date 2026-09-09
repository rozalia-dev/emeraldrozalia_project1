<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('try_on_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('type', 32)->default('ar_ai');
            $table->string('target', 32)->default('unisex');
            $table->string('age_range', 40)->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('visibility', 16)->default('public');
            $table->json('files')->nullable();
            $table->json('settings')->nullable();
            $table->json('seo')->nullable();
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('updated_by', 120)->nullable();
            $table->timestamps();
            $table->index(['product_id', 'status', 'visibility']);
            $table->index(['status', 'updated_at']);
        });

        Schema::create('try_on_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('try_on_asset_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_hash', 64);
            $table->date('day');
            $table->string('device', 32)->default('desktop_web');
            $table->boolean('converted')->default(false);
            $table->unsignedInteger('session_seconds')->default(0);
            $table->timestamps();
            $table->unique(['try_on_asset_id', 'visitor_hash', 'day'], 'try_on_visit_daily_unique');
            $table->index(['day', 'device']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('try_on_visits');
        Schema::dropIfExists('try_on_assets');
    }
};
