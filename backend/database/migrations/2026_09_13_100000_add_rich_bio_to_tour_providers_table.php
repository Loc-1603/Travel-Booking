<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mô tả rich của guide (TipTap): bio_json là TipTap doc,
     * bio_html là HTML đã sanitize phía server để render nhanh.
     * Giữ cột bio cũ làm fallback/excerpt.
     */
    public function up(): void
    {
        Schema::table('tour_providers', function (Blueprint $table) {
            $table->json('bio_json')->nullable()->after('bio');
            $table->mediumText('bio_html')->nullable()->after('bio_json');
        });
    }

    public function down(): void
    {
        Schema::table('tour_providers', function (Blueprint $table) {
            $table->dropColumn(['bio_json', 'bio_html']);
        });
    }
};
