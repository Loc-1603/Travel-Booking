<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Storefront shows business_name as-is, so drop the seeded "Guide " /
     * "Guide bản địa " prefixes — guides should display as just their name.
     * Only strips a leading prefix and skips rows that would become empty.
     * Uses LENGTH/SUBSTRING/TRIM which work on both MySQL and SQLite.
     */
    public function up(): void
    {
        foreach (['Guide bản địa ', 'Guide '] as $prefix) {
            $len = mb_strlen($prefix);
            DB::table('tour_providers')
                ->where('business_name', 'like', $prefix.'%')
                ->whereRaw("LENGTH(TRIM(SUBSTRING(business_name, {$len} + 1))) > 0")
                ->update([
                    'business_name' => DB::raw("TRIM(SUBSTRING(business_name, {$len} + 1))"),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Not reversible: the original prefix wording is lost.
    }
};
