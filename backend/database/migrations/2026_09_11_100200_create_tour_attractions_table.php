<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_attractions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id')->constrained('tour_provinces')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_famous')->default(false);
            $table->timestamps();

            $table->index('province_id');
            $table->index('is_famous');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_attractions');
    }
};
