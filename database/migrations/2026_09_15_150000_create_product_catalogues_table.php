<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_catalogues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('Emerald Rozalia Product Catalogue');
            $table->string('version', 80)->nullable();
            $table->text('description')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_original_name')->nullable();
            $table->unsignedBigInteger('pdf_size')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('cover_original_name')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('download_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_catalogues');
    }
};
