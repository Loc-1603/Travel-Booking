<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Review;
use App\Models\TourAvailabilitySlot;
use App\Models\TourBooking;
use App\Models\TourImage;
use App\Models\TourProduct;
use App\Models\TourProvider;
use App\Models\TourProvince;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Demo data for Hà Giang 1vs1 tours: 10 guides, each with tours,
 * 14 days of free slots and at least 2 visible reviews.
 * Idempotent: safe to re-run (top-ups only, no duplicates).
 */
class TourHaGiangDemoSeeder extends Seeder
{
    private const IMG_MA_PI_LENG = 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/dc/%C4%90%C3%A8o_M%C3%A3_P%C3%AD_L%C3%A8ng_2022.jpg/1280px-%C4%90%C3%A8o_M%C3%A3_P%C3%AD_L%C3%A8ng_2022.jpg';

    private const IMG_LUNG_CU = 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/20/C%E1%BB%99t_c%E1%BB%9D_L%C5%A9ng_C%C3%BA.JPG/1280px-C%E1%BB%99t_c%E1%BB%9D_L%C5%A9ng_C%C3%BA.JPG';

    private const IMG_NHO_QUE = 'https://upload.wikimedia.org/wikipedia/commons/thumb/c/c3/S%C3%B4ng_Nho_Qu%E1%BA%BF_2022_-_NKS.jpg/1280px-S%C3%B4ng_Nho_Qu%E1%BA%BF_2022_-_NKS.jpg';

    private const IMG_DONG_VAN = 'https://upload.wikimedia.org/wikipedia/commons/thumb/2/2b/Tt._%C4%90%E1%BB%93ng_V%C4%83n%2C_%C4%90%E1%BB%93ng_V%C4%83n%2C_H%C3%A0_Giang%2C_Vietnam_-_panoramio.jpg/1280px-Tt._%C4%90%E1%BB%93ng_V%C4%83n%2C_%C4%90%E1%BB%93ng_V%C4%83n%2C_H%C3%A0_Giang%2C_Vietnam_-_panoramio.jpg';

    /**
     * @var array<int, array{name: string, email: string, bio: string, bio_json?: array<string, mixed>, bio_html?: string, languages: array<int, string>, ratings: array<int, int>, fixed: int, hourly: int, daily: int, transport: int}>
     */
    protected array $guides = [
        [
            'name' => 'Giàng A Páo', 'email' => 'guide-hg-01@test.local',
            'bio' => 'Người Mông bản địa Đồng Văn, 8 năm chở khách loop Hà Giang bằng xe máy. Nhận chở tới: Đèo Mã Pí Lèng, Sông Nho Quế, Cột cờ Lũng Cú.',
            // Mẫu "Mô tả chi tiết (đẹp mắt)": doc TipTap + HTML cho trang hồ sơ.
            // Ảnh Wikimedia (đã kiểm tra HTTP 200) giúp phần demo trực quan, sinh động.
            'bio_json' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [
                        ['type' => 'text', 'text' => 'Giàng A Páo — guide người Mông ở Đồng Văn'],
                    ]],
                    ['type' => 'image', 'attrs' => [
                        'src' => self::IMG_MA_PI_LENG,
                        'alt' => 'Đèo Mã Pí Lèng — cung đường huyền thoại của Hà Giang',
                        'title' => 'Đèo Mã Pí Lèng',
                    ]],
                    ['type' => 'paragraph', 'content' => [
                        ['type' => 'text', 'text' => 'Chào bạn! Mình là '],
                        ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Giàng A Páo'],
                        ['type' => 'text', 'text' => ', người Mông bản địa ở '],
                        ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Đồng Văn'],
                        ['type' => 'text', 'text' => ', đã '],
                        ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => '8 năm'],
                        ['type' => 'text', 'text' => ' chở khách chinh phục loop Hà Giang bằng xe máy. Mình thuộc từng khúc đèo, từng điểm dừng chân và cả những quán ăn chỉ người địa phương mới biết.'],
                    ]],
                    ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [
                        ['type' => 'text', 'text' => '🏍️ Mình có thể đưa bạn đi đâu'],
                    ]],
                    ['type' => 'bulletList', 'content' => [
                        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => '🏔️ '],
                            ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Đèo Mã Pí Lèng'],
                            ['type' => 'text', 'text' => ' — ngắm vực Tu Sản và Sông Nho Quế từ trên mây'],
                        ]]]],
                        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => '💎 '],
                            ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Sông Nho Quế'],
                            ['type' => 'text', 'text' => ' — đi thuyền vào hẻm Tu Sản, nước xanh ngọc'],
                        ]]]],
                        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => '🇻🇳 '],
                            ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Cột cờ Lũng Cú'],
                            ['type' => 'text', 'text' => ' — chạm tay vào điểm cực Bắc Tổ quốc'],
                        ]]]],
                        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => '🏮 '],
                            ['type' => 'text', 'marks' => [['type' => 'bold']], 'text' => 'Phố cổ Đồng Văn'],
                            ['type' => 'text', 'text' => ' — cà phê sáng, chợ phiên và nhịp sống người Mông'],
                        ]]]],
                    ]],
                    ['type' => 'image', 'attrs' => [
                        'src' => self::IMG_LUNG_CU,
                        'alt' => 'Cột cờ Lũng Cú — điểm cực Bắc của Tổ quốc',
                        'title' => 'Cột cờ Lũng Cú',
                    ]],
                    ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [
                        ['type' => 'text', 'text' => '🗓️ Lịch trình gợi ý 1 ngày'],
                    ]],
                    ['type' => 'orderedList', 'content' => [
                        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => '🌅 08:00 — đón bạn tại khách sạn, kiểm tra xe và đồ bảo hộ'],
                        ]]]],
                        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => '🍜 12:00 — ăn trưa đặc sản (thắng cố, bánh đá) trên đường'],
                        ]]]],
                        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => '🌄 17:00 — trả bạn về khách sạn, khép lại một ngày đáng nhớ'],
                        ]]]],
                    ]],
                    ['type' => 'image', 'attrs' => [
                        'src' => self::IMG_NHO_QUE,
                        'alt' => 'Sông Nho Quế xanh ngọc giữa hẻm Tu Sản',
                        'title' => 'Sông Nho Quế',
                    ]],
                    ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [
                        ['type' => 'text', 'text' => '💡 Đi riêng 1 kèm 1, không ghép đoàn — lịch trình do bạn quyết, mình lo đường đi, an toàn và ảnh đẹp mang về.'],
                    ]]]],
                    ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [
                        ['type' => 'text', 'text' => '✅ Vì sao chọn mình'],
                    ]],
                    ['type' => 'bulletList', 'content' => [
                        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => '🛡️ Xe máy chính chủ, đồ bảo hộ đầy đủ, chạy đèo an toàn'],
                        ]]]],
                        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => '📸 Biết chỗ chụp ảnh đẹp nhất ở mỗi cung đường'],
                        ]]]],
                        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => '💬 Giao tiếp tiếng Anh cơ bản, thân thiện với khách quốc tế'],
                        ]]]],
                        ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [
                            ['type' => 'text', 'text' => '🕒 Giờ giấc linh hoạt, đón/trả tại khách sạn ở Hà Giang'],
                        ]]]],
                    ]],
                    ['type' => 'image', 'attrs' => [
                        'src' => self::IMG_DONG_VAN,
                        'alt' => 'Phố cổ Đồng Văn — nhịp sống bình yên của người Mông',
                        'title' => 'Phố cổ Đồng Văn',
                    ]],
                    ['type' => 'horizontalRule'],
                    ['type' => 'paragraph', 'content' => [
                        ['type' => 'text', 'marks' => [['type' => 'italic']], 'text' => '👉 Nhắn tin cho mình trước 1 ngày để kịp chuẩn bị xe và lịch trình nhé!'],
                    ]],
                ],
            ],
            'bio_html' => '<h2>Giàng A Páo — guide người Mông ở Đồng Văn</h2>'
                .'<img src="'.self::IMG_MA_PI_LENG.'" alt="Đèo Mã Pí Lèng — cung đường huyền thoại của Hà Giang" title="Đèo Mã Pí Lèng">'
                .'<p>Chào bạn! Mình là <strong>Giàng A Páo</strong>, người Mông bản địa ở <strong>Đồng Văn</strong>, đã <strong>8 năm</strong> chở khách chinh phục loop Hà Giang bằng xe máy. Mình thuộc từng khúc đèo, từng điểm dừng chân và cả những quán ăn chỉ người địa phương mới biết.</p>'
                .'<h3>🏍️ Mình có thể đưa bạn đi đâu</h3>'
                .'<ul><li>🏔️ <strong>Đèo Mã Pí Lèng</strong> — ngắm vực Tu Sản và Sông Nho Quế từ trên mây</li>'
                .'<li>💎 <strong>Sông Nho Quế</strong> — đi thuyền vào hẻm Tu Sản, nước xanh ngọc</li>'
                .'<li>🇻🇳 <strong>Cột cờ Lũng Cú</strong> — chạm tay vào điểm cực Bắc Tổ quốc</li>'
                .'<li>🏮 <strong>Phố cổ Đồng Văn</strong> — cà phê sáng, chợ phiên và nhịp sống người Mông</li></ul>'
                .'<img src="'.self::IMG_LUNG_CU.'" alt="Cột cờ Lũng Cú — điểm cực Bắc của Tổ quốc" title="Cột cờ Lũng Cú">'
                .'<h3>🗓️ Lịch trình gợi ý 1 ngày</h3>'
                .'<ol><li>🌅 08:00 — đón bạn tại khách sạn, kiểm tra xe và đồ bảo hộ</li>'
                .'<li>🍜 12:00 — ăn trưa đặc sản (thắng cố, bánh đá) trên đường</li>'
                .'<li>🌄 17:00 — trả bạn về khách sạn, khép lại một ngày đáng nhớ</li></ol>'
                .'<img src="'.self::IMG_NHO_QUE.'" alt="Sông Nho Quế xanh ngọc giữa hẻm Tu Sản" title="Sông Nho Quế">'
                .'<blockquote><p>💡 Đi riêng 1 kèm 1, không ghép đoàn — lịch trình do bạn quyết, mình lo đường đi, an toàn và ảnh đẹp mang về.</p></blockquote>'
                .'<h3>✅ Vì sao chọn mình</h3>'
                .'<ul><li>🛡️ Xe máy chính chủ, đồ bảo hộ đầy đủ, chạy đèo an toàn</li>'
                .'<li>📸 Biết chỗ chụp ảnh đẹp nhất ở mỗi cung đường</li>'
                .'<li>💬 Giao tiếp tiếng Anh cơ bản, thân thiện với khách quốc tế</li>'
                .'<li>🕒 Giờ giấc linh hoạt, đón/trả tại khách sạn ở Hà Giang</li></ul>'
                .'<img src="'.self::IMG_DONG_VAN.'" alt="Phố cổ Đồng Văn — nhịp sống bình yên của người Mông" title="Phố cổ Đồng Văn">'
                .'<hr>'
                .'<p><em>👉 Nhắn tin cho mình trước 1 ngày để kịp chuẩn bị xe và lịch trình nhé!</em></p>',
            'languages' => ['vi', 'en'], 'ratings' => [5, 5, 5],
            'fixed' => 0, 'hourly' => 180000, 'daily' => 400000, 'transport' => 200000,
        ],
        [
            'name' => 'Lò Thị Mai', 'email' => 'guide-hg-02@test.local',
            'bio' => 'Guide nữ người Tày, chuyên tour nhẹ nhàng, chụp ảnh đẹp. Nhận chở tới: Phố cổ Đồng Văn, Dinh Vua Mèo, Cao nguyên đá.',
            'languages' => ['vi', 'en'], 'ratings' => [5, 5, 4],
            'fixed' => 0, 'hourly' => 150000, 'daily' => 350000, 'transport' => 150000,
        ],
        [
            'name' => 'Sùng Mí Dỉ', 'email' => 'guide-hg-03@test.local',
            'bio' => 'Xế cứng cung đường đèo, am hiểu văn hóa chợ phiên. Nhận chở tới: Chợ Mèo Vạc, Đèo Mã Pí Lèng, Làng H’Mông Pả Vi.',
            'languages' => ['vi'], 'ratings' => [5, 4],
            'fixed' => 0, 'hourly' => 150000, 'daily' => 300000, 'transport' => 150000,
        ],
        [
            'name' => 'Nguyễn Văn Thắng', 'email' => 'guide-hg-04@test.local',
            'bio' => 'Cựu phượt thủ, nay làm guide full-time, xe mới, đồ bảo hộ đầy đủ. Nhận chở tới: toàn tuyến loop 3N2Đ + Sông Nho Quế.',
            'languages' => ['vi', 'en', 'fr'], 'ratings' => [5, 5, 4, 4],
            'fixed' => 0, 'hourly' => 200000, 'daily' => 400000, 'transport' => 200000,
        ],
        [
            'name' => 'Trần Thu Hà', 'email' => 'guide-hg-05@test.local',
            'bio' => 'Guide nữ, tour chậm rãi ngắm cảnh, cà phê phố cổ. Nhận chở tới: Phố cổ Đồng Văn, Cột cờ Lũng Cú, Nhà của Pao.',
            'languages' => ['vi', 'en'], 'ratings' => [4, 4],
            'fixed' => 0, 'hourly' => 120000, 'daily' => 300000, 'transport' => 100000,
        ],
        [
            'name' => 'Hoàng Văn Sơn', 'email' => 'guide-hg-06@test.local',
            'bio' => 'Người Dao đỏ, chuyên trekking + tắm lá thuốc. Nhận chở tới: Ruộng bậc thang Hoàng Su Phì, Chợ Quản Bạ, Núi đôi Cô Tiên.',
            'languages' => ['vi'], 'ratings' => [5, 4, 5],
            'fixed' => 0, 'hourly' => 140000, 'daily' => 350000, 'transport' => 150000,
        ],
        [
            'name' => 'Vừ Thị Súa', 'email' => 'guide-hg-07@test.local',
            'bio' => 'Guide trẻ nói tiếng Anh tốt, chuyên khách quốc tế đi 1 mình. Nhận chở tới: Sông Nho Quế, Đèo Mã Pí Lèng, Mèo Vạc.',
            'languages' => ['vi', 'en'], 'ratings' => [5, 5],
            'fixed' => 0, 'hourly' => 160000, 'daily' => 400000, 'transport' => 200000,
        ],
        [
            'name' => 'Đặng Quốc Bảo', 'email' => 'guide-hg-08@test.local',
            'bio' => 'Lái xe ô tô 4 chỗ cho khách không đi được xe máy. Nhận chở tới: toàn tỉnh theo yêu cầu, đón tại TP. Hà Giang.',
            'languages' => ['vi'], 'ratings' => [4, 5, 3],
            'fixed' => 0, 'hourly' => 180000, 'daily' => 400000, 'transport' => 200000,
        ],
        [
            'name' => 'Lý A Chính', 'email' => 'guide-hg-09@test.local',
            'bio' => 'Thợ ảnh tự do + guide, bao ảnh đẹp mang về. Nhận chở tới: Dốc Thẩm Mã, Nhà của Pao, Sủng Là, Lũng Cú.',
            'languages' => ['vi', 'en'], 'ratings' => [5, 4, 4, 5],
            'fixed' => 0, 'hourly' => 170000, 'daily' => 400000, 'transport' => 200000,
        ],
        [
            'name' => 'Phạm Minh Đức', 'email' => 'guide-hg-10@test.local',
            'bio' => 'Guide mới, giá mềm, nhiệt tình. Nhận chở tới: TP. Hà Giang – Quản Bạ – Yên Minh trong ngày.',
            'languages' => ['vi'], 'ratings' => [3, 4],
            'fixed' => 0, 'hourly' => 100000, 'daily' => 300000, 'transport' => 100000,
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
                ['name' => $g['name'], 'password' => bcrypt('12345678'), 'role' => Role::GUIDE, 'status' => 'active', 'email_verified_at' => now()]
            );
            $user->update(['role' => Role::GUIDE]);
            if (! $user->hasRole('guide')) {
                $user->assignRole('guide');
            }
            if ($user->hasRole('vendor')) {
                $user->removeRole('vendor');
            }

            $provider = TourProvider::firstOrCreate(
                ['vendor_id' => $user->id],
                [
                    'province_id' => $province->id,
                    'business_name' => $g['name'],
                    'bio' => $g['bio'],
                    'languages' => $g['languages'],
                    'status' => 'approved',
                ]
            );

            // Phần giới thiệu là dữ liệu mặc định của demo: chạy db:seed luôn
            // khôi phục về mặc định (chỉnh sửa trên web trước đó sẽ bị thay).
            $provider->forceFill([
                'bio' => $g['bio'],
                'bio_json' => $g['bio_json'] ?? null,
                'bio_html' => $g['bio_html'] ?? null,
            ])->save();

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

            // Giá demo luôn đồng bộ theo seeder (re-run db:seed cập nhật giá).
            $tour->forceFill([
                'base_fixed' => $g['fixed'],
                'base_price_hourly' => $g['hourly'],
                'base_price_daily' => $g['daily'],
                'transport_fee' => $g['transport'],
            ])->save();

            TourImage::firstOrCreate(
                ['tour_id' => $tour->id, 'url' => $province->image ?? 'locations/tours/default.jpg'],
                ['tour_id' => $tour->id, 'url' => $province->image ?? 'locations/tours/default.jpg', 'is_banner' => true, 'sort_order' => 0]
            );

            // 30 ngày slot trống theo ngày (08:00-18:00).
            for ($d = 1; $d <= 30; $d++) {
                $date = Carbon::tomorrow()->addDays($d - 1)->toDateString();
                $this->firstOrCreateSlot($tour, $date, '08:00:00', '18:00:00', 'available');
            }

            $this->seedReviews($provider, $tour, $province->id, $customers, $g['ratings'], $i);
        }
    }

    /**
     * firstOrCreate theo (tour_id, date, start_time) — cast 'date' lưu kèm
     * 00:00:00 nên where() thông thường không khớp khi chạy seeder lần 2
     * (vi phạm unique constraint trên sqlite). whereDate so sánh đúng ngày.
     */
    protected function firstOrCreateSlot(TourProduct $tour, string $date, string $start, string $end, string $status): TourAvailabilitySlot
    {
        return TourAvailabilitySlot::query()
            ->where('tour_id', $tour->id)
            ->whereDate('date', $date)
            ->where('start_time', $start)
            ->first()
            ?? TourAvailabilitySlot::create([
                'tour_id' => $tour->id,
                'date' => $date,
                'start_time' => $start,
                'end_time' => $end,
                'status' => $status,
            ]);
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
                ['name' => "Khách HG 0{$i}", 'password' => bcrypt('12345678'), 'role' => Role::CUSTOMER, 'status' => 'active', 'email_verified_at' => now()]
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
        $visibleCount = Review::where('hidden', false)
            ->whereHas('tourBooking', fn ($q) => $q->where('provider_id', $provider->id))
            ->count();
        if ($visibleCount >= count($ratings)) {
            return;
        }

        foreach ($ratings as $k => $rating) {
            // Slot quá khứ riêng cho booking đánh giá (không ảnh hưởng lịch trống).
            $date = Carbon::today()->subDays(20 + $seedIndex * 4 + $k)->toDateString();
            $slot = $this->firstOrCreateSlot($tour, $date, '09:00:00', '12:00:00', 'booked');

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
            Review::firstOrCreate(
                ['tour_booking_id' => $booking->id],
                [
                    'tour_booking_id' => $booking->id,
                    'rating' => $rating,
                    'comment' => $comments[($seedIndex + $k) % count($comments)],
                    'hidden' => false,
                ]
            );
        }
    }
}
