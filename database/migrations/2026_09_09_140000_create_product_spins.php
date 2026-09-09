<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_spins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title',160);
            $table->string('category',24)->default('product');
            $table->string('status',24)->default('draft');
            $table->string('visibility',12)->default('private');
            $table->json('frames');
            $table->json('settings');
            $table->json('seo');
            $table->json('hotspots');
            $table->unsignedBigInteger('bytes')->default(0);
            $table->string('resolution',40)->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->index(['product_id','status']);
        });
        Schema::create('spin_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_spin_id')->constrained('product_spins')->cascadeOnDelete();
            $table->string('visitor_hash',64);
            $table->date('day');
            $table->boolean('engaged')->default(false);
            $table->unsignedInteger('load_ms')->default(0);
            $table->unique(['product_spin_id','visitor_hash','day']);
            $table->index('day');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('spin_visits');
        Schema::dropIfExists('product_spins');
    }
};
