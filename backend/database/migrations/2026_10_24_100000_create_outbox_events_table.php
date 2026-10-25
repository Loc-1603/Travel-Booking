<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->unsignedTinyInteger('event_version')->default(1);
            $table->json('payload')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['published_at', 'available_at', 'lease_expires_at'], 'outbox_events_claim_idx');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
