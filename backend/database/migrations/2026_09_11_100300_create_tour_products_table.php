<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_products', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('provider_id')->constrained('tour_providers')->cascadeOnDelete();
            $table->foreignId('province_id')->constrained('tour_provinces')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // Pricing: total = base_fixed + unit_price * duration + transport_fee
            $table->decimal('base_fixed', 12, 2)->default(0);
            $table->decimal('base_price_hourly', 12, 2)->default(0);
            $table->decimal('base_price_daily', 12, 2)->default(0);
            $table->decimal('transport_fee', 12, 2)->nullable();
            $table->string('transport_desc')->nullable();
            $table->string('meeting_point')->nullable();
            $table->unsignedTinyInteger('max_group_size')->default(1);
            $table->string('duration_unit')->default('hour'); // hour, day
            $table->string('status')->default('draft'); // draft, published, suspended
            $table->timestamps();
            $table->softDeletes();

            $table->index('provider_id');
            $table->index('province_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_products');
    }
};
