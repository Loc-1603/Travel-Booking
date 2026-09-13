<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::updateOrCreate(
            ['code' => 'SAVE10'],
            [
                'type' => Coupon::TYPE_PERCENTAGE,
                'value' => 10,
                'min_nights' => 1,
                'min_amount' => 500000,
                'valid_from' => now()->subDay(),
                'valid_to' => now()->addMonths(3),
                'usage_limit_total' => 100,
                'usage_limit_per_user' => 1,
                'hotel_ids' => null,
                'room_ids' => null,
                'name' => 'Giảm 10%',
                'description' => 'Giảm 10% cho đơn từ 500.000đ',
                'is_active' => true,
            ]
        );

        Coupon::updateOrCreate(
            ['code' => 'FLAT200K'],
            [
                'type' => Coupon::TYPE_FIXED,
                'value' => 200000,
                'min_nights' => 2,
                'min_amount' => 1000000,
                'valid_from' => now()->subDay(),
                'valid_to' => null,
                'usage_limit_total' => null,
                'usage_limit_per_user' => 2,
                'hotel_ids' => null,
                'room_ids' => null,
                'name' => 'Giảm 200.000đ',
                'description' => 'Giảm 200.000đ cho kỳ nghỉ từ 2 đêm, tối thiểu 1.000.000đ',
                'is_active' => true,
            ]
        );
    }
}
