<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tour_providers', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->after('vendor_id')->constrained('tour_provinces')->nullOnDelete();
            $table->decimal('price_daily', 15, 2)->nullable()->after('languages');
            $table->index('province_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tour_providers', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->dropIndex(['province_id']);
            $table->dropColumn(['province_id', 'price_daily']);
        });
    }
};
