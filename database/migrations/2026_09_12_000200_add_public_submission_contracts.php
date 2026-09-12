<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->uuid('correlation_id')->nullable()->index();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->string('request_hash', 64)->nullable();
        });

        Schema::table('franchise_applications', function (Blueprint $table): void {
            $table->uuid('correlation_id')->nullable()->index();
            $table->foreignId('inquiry_id')->nullable()->constrained('inquiries')->nullOnDelete();
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->uuid('correlation_id')->nullable()->index();
            $table->foreignId('inquiry_id')->nullable()->constrained('inquiries')->nullOnDelete();
            $table->foreignId('franchise_application_id')->nullable()->constrained('franchise_applications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('franchise_application_id');
            $table->dropConstrainedForeignId('inquiry_id');
            $table->dropColumn('correlation_id');
        });

        Schema::table('franchise_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inquiry_id');
            $table->dropColumn('correlation_id');
        });

        Schema::table('inquiries', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['correlation_id', 'idempotency_key', 'request_hash']);
        });
    }
};
