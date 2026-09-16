<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ảnh minh hoạ cho đánh giá tour (tối đa 5 ảnh/review).
     */
    public function up(): void
    {
        Schema::create('tour_review_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_review_id')->constrained('tour_reviews')->cascadeOnDelete();
            $table->string('path', 500);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_review_images');
    }
};
