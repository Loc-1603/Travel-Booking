<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained('tour_products')->cascadeOnDelete();
            $table->string('url');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_banner')->default(false);
            $table->timestamps();

            $table->index(['tour_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_images');
    }
};
