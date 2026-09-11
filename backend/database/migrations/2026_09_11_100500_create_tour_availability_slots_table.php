<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_availability_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained('tour_products')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            // available, held, booked, blocked
            $table->string('status', 20)->default('available');
            $table->timestamp('held_until')->nullable();
            // No FK to tour_bookings here (circular). FK added after tour_bookings exists.
            $table->unsignedBigInteger('tour_booking_id')->nullable();
            $table->decimal('price_override', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['tour_id', 'date', 'start_time']);
            $table->index(['tour_id', 'date', 'status']);
            $table->index('tour_booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_availability_slots');
    }
};
