<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_bookings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // Tour requires login: no guest fields (unlike bookings.guest_email).
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained('tour_products')->cascadeOnDelete();
            $table->foreignId('slot_id')->unique()->constrained('tour_availability_slots')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('tour_providers')->cascadeOnDelete();
            $table->foreignId('province_id')->constrained('tour_provinces')->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            // hour, day
            $table->string('pricing_mode', 10)->default('hour');
            $table->unsignedInteger('duration_value')->default(1);
            // Price snapshot: total = base_fixed + unit_price * duration + transport - discount + tax
            $table->decimal('base_fixed', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('transport_fee', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2);
            $table->string('currency', 3)->default('VND');
            $table->string('status')->default('pending_payment');
            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('meeting_point')->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_id');
            $table->index('provider_id');
            $table->index('tour_id');
            $table->index('status');
            $table->index('start_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_bookings');
    }
};
