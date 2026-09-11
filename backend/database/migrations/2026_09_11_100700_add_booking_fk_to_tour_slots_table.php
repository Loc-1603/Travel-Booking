<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Close the circular reference slots <-> bookings (slots created first without FK).
        Schema::table('tour_availability_slots', function (Blueprint $table): void {
            $table->foreign('tour_booking_id', 'slots_booking_fk')
                ->references('id')
                ->on('tour_bookings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tour_availability_slots', function (Blueprint $table): void {
            $table->dropForeign('slots_booking_fk');
        });
    }
};
