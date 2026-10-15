<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Trang người dùng hiển thị price_from = MIN(base_price_daily) của tour,
     * trong khi vendor chỉ chỉnh giá ở guide-profile settings (price_daily).
     * Các tour tạo tự động từ trước đó vẫn giữ giá của lần tạo đầu tiên —
     * đồng bộ lại theo giá hiện tại của guide.
     */
    public function up(): void
    {
        DB::table('tour_providers')
            ->whereNotNull('price_daily')
            ->chunkById(100, function ($providers): void {
                foreach ($providers as $provider) {
                    DB::table('tour_products')
                        ->where('provider_id', $provider->id)
                        ->where('base_price_daily', '!=', $provider->price_daily)
                        ->update(['base_price_daily' => $provider->price_daily]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to roll back — the backfill is not reversible without data loss.
    }
};
