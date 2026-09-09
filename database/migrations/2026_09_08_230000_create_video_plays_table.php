<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_plays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_media_id')->constrained('product_media')->cascadeOnDelete();
            $table->string('session_hash', 64);
            $table->date('day');
            $table->timestampTz('started_at');
            $table->unsignedInteger('seconds')->default(0);
            $table->timestampsTz();
            $table->unique(['product_media_id', 'session_hash', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_plays');
    }
};
