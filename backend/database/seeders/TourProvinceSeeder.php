<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\TourAttraction;
use App\Models\TourProvince;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TourProvinceSeeder extends Seeder
{
    /**
     * @var array<string, array{image: string, description: string, attractions: array<int, array{name: string, description: string, image: string}>}>
     */
    public static array $provinces = [
        'Hà Giang' => [
            'image' => 'locations/cities/ha-giang.jpg',
            'description' => 'Tour 1vs1 cùng guide bản địa: đèo Mã Pí Lèng, sông Nho Quế, phố cổ Đồng Văn theo giờ giấc của riêng bạn.',
            'attractions' => [
                ['name' => 'Đèo Mã Pí Lèng', 'description' => 'Một trong tứ đại đỉnh đèo, ngắm vực Tu Sản.', 'image' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/d/dc/%C4%90%C3%A8o_M%C3%A3_P%C3%AD_L%C3%A8ng_2022.jpg/1280px-%C4%90%C3%A8o_M%C3%A3_P%C3%AD_L%C3%A8ng_2022.jpg'],
                ['name' => 'Sông Nho Quế', 'description' => 'Đi thuyền hẻm Tu Sản, nước xanh ngọc.', 'image' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/c/c3/S%C3%B4ng_Nho_Qu%E1%BA%BF_2022_-_NKS.jpg/1280px-S%C3%B4ng_Nho_Qu%E1%BA%BF_2022_-_NKS.jpg'],
                ['name' => 'Cột cờ Lũng Cú', 'description' => 'Điểm cực Bắc, check-in cùng guide.', 'image' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/2/20/C%E1%BB%99t_c%E1%BB%9D_L%C5%A9ng_C%C3%BA.JPG/1280px-C%E1%BB%99t_c%E1%BB%9D_L%C5%A9ng_C%C3%BA.JPG'],
                ['name' => 'Phố cổ Đồng Văn', 'description' => 'Cà phê phố cổ, chợ phiên sáng.', 'image' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/3/36/Ph%E1%BB%97_C%E1%BB%95.jpg/1280px-Ph%E1%BB%97_C%E1%BB%95.jpg'],
            ],
        ],
        'Sa Pa' => [
            'image' => 'locations/cities/sa-pa.jpg',
            'description' => 'Guide 1vs1 dẫn trekking bản Cát Cát, Tả Van theo sức của bạn, chở xe máy an toàn.',
            'attractions' => [
                ['name' => 'Fansipan', 'description' => 'Nóc nhà Đông Dương, cáp treo + leo bộ cùng guide.', 'image' => 'https://upload.wikimedia.org/wikipedia/commons/d/de/C%C3%A1p-treo-fansipan-17.jpg'],
                ['name' => 'Bản Cát Cát', 'description' => 'Bản H’Mông gần trung tâm, thác và cọn nước.', 'image' => 'https://upload.wikimedia.org/wikipedia/commons/6/66/B%E1%BA%A3n_C%C3%A1t_C%C3%A1t.jpg'],
                ['name' => 'Thung lũng Mường Hoa', 'description' => 'Ruộng bậc thang, bãi đá cổ.', 'image' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/b/b6/An_ancient_engraved_rock_of_Sapa.JPG/1280px-An_ancient_engraved_rock_of_Sapa.JPG'],
            ],
        ],
        'Hội An' => [
            'image' => 'locations/cities/hoi-an.jpg',
            'description' => 'Dạo phố đèn lồng, làng rau Trà Quế, biển An Bàng với guide riêng theo giờ.',
            'attractions' => [
                ['name' => 'Phố cổ Hội An', 'description' => 'Chùa Cầu, đèn lồng, thả hoa đăng.', 'image' => 'https://upload.wikimedia.org/wikipedia/commons/f/f3/PhoCoHoiAn.jpg'],
                ['name' => 'Làng rau Trà Quế', 'description' => 'Trải nghiệm làm nông, ăn đặc sản.', 'image' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/2/2f/Tra_Que_Village%2C_Hoi_An_%2845491719075%29.jpg/1280px-Tra_Que_Village%2C_Hoi_An_%2845491719075%29.jpg'],
                ['name' => 'Biển An Bàng', 'description' => 'Tắm biển, hải sản cùng guide địa phương.', 'image' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/b/b0/2024-11-23_An_Bang_Beach_in_Hoi_An_in_November.jpg/1280px-2024-11-23_An_Bang_Beach_in_Hoi_An_in_November.jpg'],
            ],
        ],
    ];

    public function run(): void
    {
        $vietnam = Country::where('code', 'VN')->first();
        if (! $vietnam) {
            return;
        }

        foreach (self::$provinces as $name => $data) {
            $city = City::where('country_id', $vietnam->id)->where('name', $name)->first();
            $slug = Str::slug($name);

            $province = TourProvince::firstOrCreate(
                ['slug' => $slug],
                [
                    'country_id' => $vietnam->id,
                    'city_id' => $city?->id,
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $data['description'],
                    'image' => $data['image'],
                    'is_featured' => true,
                ]
            );

            foreach ($data['attractions'] as $attr) {
                TourAttraction::firstOrCreate(
                    ['province_id' => $province->id, 'name' => $attr['name']],
                    [
                        'province_id' => $province->id,
                        'name' => $attr['name'],
                        'description' => $attr['description'],
                        'image' => $attr['image'] ?? null,
                        'is_famous' => true,
                    ]
                );
            }
        }
    }
}
