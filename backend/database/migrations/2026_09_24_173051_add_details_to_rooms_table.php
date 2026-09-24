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
        Schema::table('rooms', function (Blueprint $table) {
            $table->text('description')->nullable()->after('total_rooms');
            $table->decimal('size', 6, 2)->nullable()->after('description');
            $table->string('bed_type')->nullable()->after('size');
            $table->string('view_type')->nullable()->after('bed_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['description', 'size', 'bed_type', 'view_type']);
        });
    }
};
