<?php

namespace Database\Seeders;

use App\Models\TourAvailabilitySlot;
use App\Models\TourImage;
use App\Models\TourProduct;
use App\Models\TourProvince;
use App\Models\TourProvider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TourProductSeeder extends Seeder
{
    public function run(): void
    {
        $vendor = User::where('role', 'vendor')->first()
            ?? User::where('email', 'like', '%vendor%')->first();
        if (! $vendor) {
            return;
        }

        $provider = TourProvider::firstOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'business_name' => $vendor->name,
                'bio' => 'Guide 1vs1: đề xuất lịch trình, dẫn và chở riêng theo giờ của bạn.',
                'languages' => ['vi', 'en'],
                'status' => 'approved',
            ]
        );

        $demos = [
            [
                'province' => 'Hà Giang',
                'title' => 'Hà Giang 1vs1: Mã Pí Lèng – Nho Quế trong ngày',
                'base_fixed' => 300000,
                'hourly' => 150000,
                'daily' => 1200000,
                'transport' => 200000,
            ],
            [
                'province' => 'Sa Pa',
                'title' => 'Sa Pa trekking 1vs1: Cát Cát – Mường Hoa nửa ngày',
                'base_fixed' => 200000,
                'hourly' => 120000,
                'daily' => 900000,
                'transport' => 150000,
            ],
        ];

        foreach ($demos as $demo) {
            $province = TourProvince::where('name', $demo['province'])->first();
            if (! $province) {
                continue;
            }

            $tour = TourProduct::firstOrCreate(
                ['provider_id' => $provider->id, 'title' => $demo['title']],
                [
                    'provider_id' => $provider->id,
                    'province_id' => $province->id,
                    'title' => $demo['title'],
                    'description' => 'Địa điểm do bạn quyết, guide đề xuất – dẫn – chở riêng. Giá = phí cố định + theo giờ/ngày.',
                    'base_fixed' => $demo['base_fixed'],
                    'base_price_hourly' => $demo['hourly'],
                    'base_price_daily' => $demo['daily'],
                    'transport_fee' => $demo['transport'],
                    'transport_desc' => 'Xe máy + xăng xe guide chở bạn.',
                    'meeting_point' => 'Khách sạn của bạn trong trung tâm.',
                    'max_group_size' => 1,
                    'duration_unit' => 'hour',
                    'status' => 'published',
                ]
            );

            TourImage::firstOrCreate(
                ['tour_id' => $tour->id, 'url' => $province->image ?? 'locations/tours/default.jpg'],
                ['tour_id' => $tour->id, 'url' => $province->image ?? 'locations/tours/default.jpg', 'is_banner' => true, 'sort_order' => 0]
            );

            // 7 ngày slot mẫu 8:00-12:00 để test availability
            for ($i = 1; $i <= 7; $i++) {
                $date = Carbon::tomorrow()->addDays($i - 1)->toDateString();
                TourAvailabilitySlot::firstOrCreate(
                    ['tour_id' => $tour->id, 'date' => $date, 'start_time' => '08:00:00'],
                    ['tour_id' => $tour->id, 'date' => $date, 'start_time' => '08:00:00', 'end_time' => '12:00:00', 'status' => 'available']
                );
            }
        }
    }
}
