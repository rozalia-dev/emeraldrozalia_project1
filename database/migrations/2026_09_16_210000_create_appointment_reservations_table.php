<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reservations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inquiry_id')->unique()->constrained()->cascadeOnDelete();
            $table->date('appointment_date');
            $table->string('appointment_time', 5);
            $table->string('meeting_type', 32)->nullable();
            $table->timestamps();

            $table->unique(
                ['company_id', 'appointment_date', 'appointment_time'],
                'appointment_reservations_slot_unique'
            );
            $table->index(['company_id', 'appointment_date'], 'appointment_reservations_day_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reservations');
    }
};
