<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourImage;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\TourReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Demo data for Hà Giang 1vs1 tours: 10 guides, each with tours,
 * 14 days of free slots and at least 2 approved reviews.
 * Idempotent: safe to re-run (top-ups only, no duplicates).
 */
class TourHaGiangDemoSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, email: string, bio: string, languages: array<int, string>, ratings: array<int, int>, fixed: int, hourly: int, daily: int, transport: int}>
     */
    protected array $guides = [
        [
            'name' => 'Giàng A Páo', 'email' => 'guide-hg-01@test.local',
            'bio' => 'Người Mông bản địa Đồng Văn, 8 năm chở khách loop Hà Giang bằng xe máy. Nhận chở tới: Đèo Mã Pí Lèng, Sông Nho Quế, Cột cờ Lũng Cú.',
            'languages' => ['vi', 'en'], 'ratings' => [5, 5, 5],
            'fixed' => 400000, 'hourly' => 180000, 'daily' => 1400000, 'transport' => 250000,
        ],
        [
            'name' => 'Lò Thị Mai', 'email' => 'guide-hg-02@test.local',
            'bio' => 'Guide nữ người Tày, chuyên tour nhẹ nhàng, chụp ảnh đẹp. Nhận chở tới: Phố cổ Đồng Văn, Dinh Vua Mèo, Cao nguyên đá.',
            'languages' => ['vi', 'en'], 'ratings' => [5, 5, 4],
            'fixed' => 350000, 'hourly' => 150000, 'daily' => 1200000, 'transport' => 200000,
        ],
        [
            'name' => 'Sùng Mí Dỉ', 'email' => 'guide-hg-03@test.local',
            'bio' => 'Xế cứng cung đường đèo, am hiểu văn hóa chợ phiên. Nhận chở tới: Chợ Mèo Vạc, Đèo Mã Pí Lèng, Làng H’Mông Pả Vi.',
            'languages' => ['vi'], 'ratings' => [5, 4],
            'fixed' => 300000, 'hourly' => 150000, 'daily' => 1100000, 'transport' => 200000,
        ],
        [
            'name' => 'Nguyễn Văn Thắng', 'email' => 'guide-hg-04@test.local',
            'bio' => 'Cựu phượt thủ, nay làm guide full-time, xe mới, đồ bảo hộ đầy đủ. Nhận chở tới: toàn tuyến loop 3N2Đ + Sông Nho Quế.',
            'languages' => ['vi', 'en', 'fr'], 'ratings' => [5, 5, 4, 4],
            'fixed' => 500000, 'hourly' => 200000, 'daily' => 1500000, 'transport' => 300000,
        ],
        [
            'name' => 'Trần Thu Hà', 'email' => 'guide-hg-05@test.local',
            'bio' => 'Guide nữ, tour chậm rãi ngắm cảnh, cà phê phố cổ. Nhận chở tới: Phố cổ Đồng Văn, Cột cờ Lũng Cú, Nhà của Pao.',
            'languages' => ['vi', 'en'], 'ratings' => [4, 4],
            'fixed' => 250000, 'hourly' => 120000, 'daily' => 900000, 'transport' => 150000,
        ],
        [
            'name' => 'Hoàng Văn Sơn', 'email' => 'guide-hg-06@test.local',
            'bio' => 'Người Dao đỏ, chuyên trekking + tắm lá thuốc. Nhận chở tới: Ruộng bậc thang Hoàng Su Phì, Chợ Quản Bạ, Núi đôi Cô Tiên.',
            'languages' => ['vi'], 'ratings' => [5, 4, 5],
            'fixed' => 300000, 'hourly' => 140000, 'daily' => 1000000, 'transport' => 180000,
        ],
        [
            'name' => 'Vừ Thị Súa', 'email' => 'guide-hg-07@test.local',
            'bio' => 'Guide trẻ nói tiếng Anh tốt, chuyên khách quốc tế đi 1 mình. Nhận chở tới: Sông Nho Quế, Đèo Mã Pí Lèng, Mèo Vạc.',
            'languages' => ['vi', 'en'], 'ratings' => [5, 5],
            'fixed' => 350000, 'hourly' => 160000, 'daily' => 1250000, 'transport' => 220000,
        ],
        [
            'name' => 'Đặng Quốc Bảo', 'email' => 'guide-hg-08@test.local',
            'bio' => 'Lái xe ô tô 4 chỗ cho khách không đi được xe máy. Nhận chở tới: toàn tỉnh theo yêu cầu, đón tại TP. Hà Giang.',
            'languages' => ['vi'], 'ratings' => [4, 5, 3],
            'fixed' => 450000, 'hourly' => 180000, 'daily' => 1300000, 'transport' => 300000,
        ],
        [
            'name' => 'Lý A Chính', 'email' => 'guide-hg-09@test.local',
            'bio' => 'Thợ ảnh tự do + guide, bao ảnh đẹp mang về. Nhận chở tới: Dốc Thẩm Mã, Nhà của Pao, Sủng Là, Lũng Cú.',
            'languages' => ['vi', 'en'], 'ratings' => [5, 4, 4, 5],
            'fixed' => 400000, 'hourly' => 170000, 'daily' => 1350000, 'transport' => 250000,
        ],
        [
            'name' => 'Phạm Minh Đức', 'email' => 'guide-hg-10@test.local',
            'bio' => 'Guide mới, giá mềm, nhiệt tình. Nhận chở tới: TP. Hà Giang – Quản Bạ – Yên Minh trong ngày.',
            'languages' => ['vi'], 'ratings' => [3, 4],
            'fixed' => 200000, 'hourly' => 100000, 'daily' => 800000, 'transport' => 150000,
        ],
    ];

    protected array $reviewComments = [
        5 => ['Đi riêng thoải mái, guide chở an toàn, ảnh đẹp.', 'Tuyệt vời! Đúng giờ, nhiệt tình, hiểu đường.', 'Chuyến đi đáng nhớ nhất của mình ở Hà Giang.', 'Guide chu đáo, xe tốt, lịch trình linh hoạt.'],
        4 => ['Khá tốt, chỉ hơi vội đoạn đèo.', 'Guide thân thiện, giá hợp lý.', 'Ổn, lần sau sẽ đặt tour dài ngày hơn.', 'Tốt, trừ lúc chờ thuyền hơi lâu.'],
        3 => ['Tạm được, xe hơi cũ.', 'Guide nhiệt tình nhưng tiếng Anh còn hạn chế.'],
    ];

    public function run(): void
    {
        $province = TourProvince::where('slug', 'ha-giang')->first()
            ?? TourProvince::where('name', 'Hà Giang')->first();
        if (! $province) {
            $this->command?->warn('TourHaGiangDemoSeeder: Hà Giang province not found, run TourProvinceSeeder first.');

            return;
        }

        $customers = $this->seedCustomers();

        foreach ($this->guides as $i => $g) {
            $user = User::firstOrCreate(
                ['email' => $g['email']],
                ['name' => $g['name'], 'password' => bcrypt('12345678'), 'role' => Role::VENDOR, 'status' => 'active']
            );
            if (! $user->hasRole('vendor')) {
                $user->assignRole('vendor');
            }

            $provider = TourProvider::firstOrCreate(
                ['vendor_id' => $user->id],
                [
                    'business_name' => $g['name'],
                    'bio' => $g['bio'],
                    'languages' => $g['languages'],
                    'status' => 'approved',
                ]
            );

            $tour = TourProduct::firstOrCreate(
                ['provider_id' => $provider->id, 'title' => 'Hà Giang 1vs1 cùng '.$g['name']],
                [
                    'provider_id' => $provider->id,
                    'province_id' => $province->id,
                    'title' => 'Hà Giang 1vs1 cùng '.$g['name'],
                    'description' => $g['bio'].' Địa điểm do bạn quyết, guide đề xuất – dẫn – chở riêng 1 kèm 1.',
                    'base_fixed' => $g['fixed'],
                    'base_price_hourly' => $g['hourly'],
                    'base_price_daily' => $g['daily'],
                    'transport_fee' => $g['transport'],
                    'transport_desc' => 'Xe máy + xăng xe guide chở bạn.',
                    'meeting_point' => 'Khách sạn của bạn tại Hà Giang.',
                    'max_group_size' => 1,
                    'duration_unit' => 'hour',
                    'status' => 'published',
                ]
            );

            TourImage::firstOrCreate(
                ['tour_id' => $tour->id, 'url' => $province->image ?? 'locations/tours/default.jpg'],
                ['tour_id' => $tour->id, 'url' => $province->image ?? 'locations/tours/default.jpg', 'is_banner' => true, 'sort_order' => 0]
            );

            // 14 ngày slot trống (sáng + chiều) để test lọc multi-day.
            for ($d = 1; $d <= 14; $d++) {
                $date = Carbon::tomorrow()->addDays($d - 1)->toDateString();
                foreach ([['08:00:00', '12:00:00'], ['13:30:00', '17:30:00']] as [$start, $end]) {
                    TourAvailabilitySlot::firstOrCreate(
                        ['tour_id' => $tour->id, 'date' => $date, 'start_time' => $start],
                        ['tour_id' => $tour->id, 'date' => $date, 'start_time' => $start, 'end_time' => $end, 'status' => 'available']
                    );
                }
            }

            $this->seedReviews($provider, $tour, $province->id, $customers, $g['ratings'], $i);
        }
    }

    /**
     * @return array<int, User>
     */
    protected function seedCustomers(): array
    {
        $customers = [];
        for ($i = 1; $i <= 4; $i++) {
            $user = User::firstOrCreate(
                ['email' => "customer-hg-0{$i}@test.local"],
                ['name' => "Khách HG 0{$i}", 'password' => bcrypt('12345678'), 'role' => Role::CUSTOMER, 'status' => 'active']
            );
            if (! $user->hasRole('customer')) {
                $user->assignRole('customer');
            }
            $customers[] = $user;
        }

        return $customers;
    }

    /**
     * @param  array<int, User>  $customers
     * @param  array<int, int>  $ratings
     */
    protected function seedReviews(TourProvider $provider, TourProduct $tour, int $provinceId, array $customers, array $ratings, int $seedIndex): void
    {
        $approvedCount = TourReview::where('approved', true)->where('hidden', false)
            ->whereHas('booking', fn ($q) => $q->where('provider_id', $provider->id))
            ->count();
        if ($approvedCount >= count($ratings)) {
            return;
        }

        foreach ($ratings as $k => $rating) {
            // Slot quá khứ riêng cho booking đánh giá (không ảnh hưởng lịch trống).
            $date = Carbon::today()->subDays(20 + $seedIndex * 4 + $k)->toDateString();
            $slot = TourAvailabilitySlot::firstOrCreate(
                ['tour_id' => $tour->id, 'date' => $date, 'start_time' => '09:00:00'],
                ['tour_id' => $tour->id, 'date' => $date, 'start_time' => '09:00:00', 'end_time' => '12:00:00', 'status' => 'booked']
            );

            $booking = TourBooking::firstOrCreate(
                ['slot_id' => $slot->id],
                [
                    'customer_id' => $customers[($seedIndex + $k) % count($customers)]->id,
                    'tour_id' => $tour->id,
                    'slot_id' => $slot->id,
                    'provider_id' => $provider->id,
                    'province_id' => $provinceId,
                    'start_at' => $date.' 09:00:00',
                    'end_at' => $date.' 12:00:00',
                    'pricing_mode' => 'hour',
                    'duration_value' => 4,
                    'base_fixed' => $tour->base_fixed,
                    'unit_price' => $tour->base_price_hourly,
                    'subtotal' => $tour->base_fixed + $tour->base_price_hourly * 4,
                    'transport_fee' => $tour->transport_fee ?? 0,
                    'total_price' => $tour->base_fixed + $tour->base_price_hourly * 4 + ($tour->transport_fee ?? 0),
                    'currency' => 'VND',
                    'status' => 'completed',
                ]
            );

            $comments = $this->reviewComments[$rating] ?? ['Ổn.'];
            TourReview::firstOrCreate(
                ['tour_booking_id' => $booking->id],
                [
                    'tour_booking_id' => $booking->id,
                    'rating' => $rating,
                    'comment' => $comments[($seedIndex + $k) % count($comments)],
                    'approved' => true,
                    'hidden' => false,
                ]
            );
        }
    }
}
