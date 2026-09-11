<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot payouts <-> tour_bookings for UNION payouts.
     * Hotel pivot payout_booking is left untouched.
     */
    public function up(): void
    {
        Schema::create('payout_tour_booking', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payout_id')->constrained('payouts')->cascadeOnDelete();
            $table->foreignId('tour_booking_id')->constrained('tour_bookings')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('tour_booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_tour_booking');
    }
};
