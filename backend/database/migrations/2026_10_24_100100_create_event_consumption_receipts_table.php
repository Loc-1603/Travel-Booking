<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_consumption_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('consumer');
            $table->string('event_id');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['consumer', 'event_id'], 'event_consumption_receipts_consumer_event_unique');
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_consumption_receipts');
    }
};
