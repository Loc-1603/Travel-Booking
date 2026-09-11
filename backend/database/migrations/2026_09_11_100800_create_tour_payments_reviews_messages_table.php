<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Separate payments table (do NOT touch payments) — VNPay adapter writes here.
        Schema::create('tour_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_booking_id')->constrained('tour_bookings')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('VND');
            $table->string('provider')->default('vnpay');
            $table->string('external_id')->nullable();
            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index('tour_booking_id');
            $table->index('external_id');
            $table->index('status');
        });

        Schema::create('tour_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_booking_id')->constrained('tour_bookings')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('approved')->default(false);
            $table->boolean('hidden')->default(false);
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('tour_booking_id');
            $table->index('approved');
        });

        Schema::create('tour_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_booking_id')->constrained('tour_bookings')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->json('attachments')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tour_booking_id', 'created_at']);
        });

        Schema::create('saved_tours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained('tour_products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'tour_id']);
            $table->index('user_id');
        });

        Schema::create('tour_disputes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_booking_id')->constrained('tour_bookings')->cascadeOnDelete();
            $table->string('status')->default('open'); // open, in_review, resolved, closed
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('tour_booking_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_disputes');
        Schema::dropIfExists('saved_tours');
        Schema::dropIfExists('tour_messages');
        Schema::dropIfExists('tour_reviews');
        Schema::dropIfExists('tour_payments');
    }
};
