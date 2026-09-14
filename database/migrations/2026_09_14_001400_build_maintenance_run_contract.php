<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maintenance_runs')) {
            return;
        }

        Schema::create('maintenance_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->nullable()->index()->constrained('companies')->nullOnDelete();
            $table->foreignId('triggered_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->index();
            $table->jsonb('checks')->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_runs');
    }
};
