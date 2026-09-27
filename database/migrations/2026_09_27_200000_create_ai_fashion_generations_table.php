<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_fashion_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('fashn');
            $table->string('prediction_id')->unique();
            $table->string('status', 30)->default('starting')->index();
            $table->string('model', 30);
            $table->string('pose', 40);
            $table->string('scene', 40);
            $table->text('prompt')->nullable();
            $table->string('source_path', 500);
            $table->string('result_disk', 30)->nullable();
            $table->string('result_path', 500)->nullable();
            $table->text('provider_error')->nullable();
            $table->jsonb('provider_payload')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('product_media_id')->nullable()->constrained('product_media')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['product_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_fashion_generations');
    }
};
