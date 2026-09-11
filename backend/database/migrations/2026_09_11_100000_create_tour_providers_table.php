<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_providers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vendor_id')->constrained('users')->cascadeOnDelete();
            $table->string('business_name');
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->json('languages')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamps();
            $table->softDeletes();

            $table->index('vendor_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_providers');
    }
};
