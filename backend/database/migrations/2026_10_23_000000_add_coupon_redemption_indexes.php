<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->index(['coupon_id', 'status', 'customer_id'], 'bookings_coupon_status_customer_idx');
        });

        Schema::table('tour_bookings', function (Blueprint $table): void {
            $table->index(['coupon_id', 'status', 'customer_id'], 'tour_bookings_coupon_status_customer_idx');
        });
    }

    public function down(): void
    {
        $bookingNeedsCouponIndex = ! Schema::hasIndex('bookings', 'bookings_coupon_id_foreign')
            && ! Schema::hasIndex('bookings', 'bookings_coupon_id_index');
        $tourNeedsCouponIndex = ! Schema::hasIndex('tour_bookings', 'tour_bookings_coupon_id_foreign')
            && ! Schema::hasIndex('tour_bookings', 'tour_bookings_coupon_id_index');

        if ($bookingNeedsCouponIndex) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->index('coupon_id', 'bookings_coupon_id_index');
            });
        }

        if ($tourNeedsCouponIndex) {
            Schema::table('tour_bookings', function (Blueprint $table): void {
                $table->index('coupon_id', 'tour_bookings_coupon_id_index');
            });
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_coupon_status_customer_idx');
        });

        Schema::table('tour_bookings', function (Blueprint $table): void {
            $table->dropIndex('tour_bookings_coupon_status_customer_idx');
        });
    }
};
