<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_bookings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->date('meeting_date');
            $table->time('meeting_time');
            $table->string('meeting_mode', 32);
            $table->string('status', 24)->default('booked');
            $table->text('meeting_link')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'meeting_date', 'meeting_time'], 'appointment_company_date_time_unique');
            $table->index(['company_id', 'meeting_date', 'status'], 'appointment_company_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_bookings');
    }
};
