<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('payment_type', 16);
            $table->unsignedBigInteger('payment_id');
            $table->string('provider', 32)->default('vnpay');
            $table->string('request_id', 32)->unique();
            $table->string('idempotency_key', 191);
            $table->string('txn_ref', 100);
            $table->string('transaction_no', 15)->nullable();
            $table->string('transaction_date', 14)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('VND');
            $table->string('type', 2);
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('vnp_response_code', 2)->nullable();
            $table->string('vnp_transaction_status', 2)->nullable();
            $table->string('vnp_refund_transaction_no', 15)->nullable();
            $table->string('vnp_pay_date', 14)->nullable();
            $table->json('provider_response')->nullable();
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->timestamps();

            $table->index(['payment_type', 'payment_id', 'status'], 'payment_refunds_payment_status_idx');
            $table->index(['status', 'next_retry_at'], 'payment_refunds_claim_idx');
            $table->unique(['payment_type', 'payment_id', 'idempotency_key'], 'payment_refunds_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
