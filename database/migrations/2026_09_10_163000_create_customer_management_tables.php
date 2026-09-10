<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('retail')->index();
            $table->text('description')->nullable();
            $table->string('pricing_rule')->nullable();
            $table->string('discount_note')->nullable();
            $table->string('country', 2)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_vip')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('custom')->index();
            $table->text('description')->nullable();
            $table->json('rule_definition')->nullable();
            $table->string('country', 2)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('primary_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $table->string('account_status')->default('active')->index();
            $table->string('preferred_language', 10)->default('en');
            $table->string('country', 2)->nullable()->index();
            $table->string('timezone')->default('Europe/Dublin');
            $table->boolean('marketing_consent')->default(false);
            $table->timestampTz('consent_at')->nullable();
            $table->timestampTz('phone_verified_at')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_vip')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('customer_group_user', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['customer_group_id', 'user_id']);
        });

        Schema::create('customer_segment_user', function (Blueprint $table) {
            $table->foreignId('customer_segment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['customer_segment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_segment_user');
        Schema::dropIfExists('customer_group_user');
        Schema::dropIfExists('customer_profiles');
        Schema::dropIfExists('customer_segments');
        Schema::dropIfExists('customer_groups');
    }
};
