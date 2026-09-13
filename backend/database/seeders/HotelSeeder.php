<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\City;
use App\Models\Country;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    /** Vietnamese city coordinates (lat, lng) for realistic placement */
    private array $cityCoordinates = [
        'Hà Nội' => [21.0285, 105.8542],
        'TP.HCM' => [10.8231, 106.6297],
        'Đà Nẵng' => [16.0544, 108.2022],
        'Nha Trang' => [12.2388, 109.1967],
        'Huế' => [16.4637, 107.5909],
        'Hội An' => [15.8801, 108.3380],
        'Đà Lạt' => [11.9404, 108.4583],
        'Phú Quốc' => [10.2899, 103.9840],
        'Hạ Long' => [20.9101, 107.1839],
        'Sa Pa' => [22.3364, 103.8438],
        'Cần Thơ' => [10.0452, 105.7469],
        'Vũng Tàu' => [10.3459, 107.0843],
        'Quy Nhơn' => [13.7765, 109.2235],
        'Ninh Bình' => [20.2506, 105.9745],
        'Hà Giang' => [22.8266, 104.9788],
    ];

    private array $hotelNames = [
        'Khách sạn Phố Cổ',
        'Boutique Tràng Tiền',
        'Resort Biển Mỹ Khê',
        'Palace Sài Gòn',
        'Villa Đà Lạt',
        'Homestay Sa Pa',
        'Seaside Resort Trần Phú',
        'Royal Palace Huế',
        'Riverside Hội An',
        'Pearl Island Phú Quốc',
        'Bay View Hạ Long',
        'Tây Đô Riverside Cần Thơ',
        'Beachfront Vũng Tàu',
        'Quy Nhơn Bay Resort',
        'Tràng An Ecolodge Ninh Bình',
        'Cao Nguyên Đá Hà Giang',
        'Lotus Boutique Hotel',
        'Golden Lotus Resort',
        'Sông Hương Hotel',
        'Lighthouse Beach Resort',
        'Cloud Villa',
        'Old Quarter Suites',
        'Mekong Delta Resort',
        'Coral Bay Hotel',
        'Heritage Inn',
        'Sunrise Beach Hotel',
        'Mountain Retreat',
        'City Central Hotel',
        'Garden Palace',
        'Luxury Sky Suites',
    ];

    private array $descriptions = [
        'Khách sạn hiện đại ngay trung tâm thành phố, thuận tiện di chuyển tới các điểm tham quan.',
        'Khu nghỉ dưỡng yên tĩnh với hồ bơi, spa và nhà hàng phục vụ ẩm thực địa phương.',
        'Vị trí đắc địa gần biển, phòng ốc thoáng mát với view hướng ra đại dương.',
        'Thiết kế boutique độc đáo, kết hợp hài hòa giữa nét truyền thống và tiện nghi hiện đại.',
        'Lựa chọn lý tưởng cho gia đình với không gian rộng rãi và nhiều hoạt động giải trí.',
        'Nơi nghỉ dưỡng lãng mạn dành cho các cặp đôi, ngắm trọn cảnh núi non hùng vĩ.',
        'Cách chợ đêm và phố ẩm thực chỉ vài phút đi bộ, thuận tiện khám phá văn hóa địa phương.',
        'Không gian xanh mát giữa lòng thành phố, bữa sáng phong phú với đặc sản vùng miền.',
        'Kiến trúc Pháp cổ được trùng tu, giữ nguyên nét đẹp hoài cổ cùng dịch vụ tận tâm.',
        'Resort cao cấp với bãi biển riêng, thể thao dưới nước và hải sản tươi sống mỗi ngày.',
        'Homestay ấm cúng giữa thung lũng, buổi tối đốt lửa trại và ngắm sao trời.',
        'Tòa nhà hiện đại với hồ bơi vô cực trên tầng thượng ngắm toàn cảnh thành phố.',
        'Khu sinh thái gần gũi thiên nhiên, thích hợp cho du khách yêu thích trải nghiệm xanh.',
        'Phòng suite sang trọng, ban công riêng nhìn ra vịnh biển tuyệt đẹp.',
        'Nhân viên thân thiện, hỗ trợ đặt tour và xe đưa đón tận nơi với giá hợp lý.',
    ];

    private array $addressParts = [
        'Đường Trần Phú', 'Phố Hàng Bạc', 'Đại lộ Võ Nguyên Giáp', 'Đường Bùi Viện',
        'Đường Nguyễn Huệ', 'Phố Cổ Bao Vinh', 'Đường Trần Hưng Đạo', 'Đường Phạm Văn Đồng',
        'Đường Lê Lợi', 'Phố Tạ Hiện', 'Đường Võ Thị Sáu', 'Đại lộ Hòa Bình',
        'Đường Hai Bà Trưng', 'Phố Nguyễn Thái Học', 'Đường Quang Trung',
    ];

    public function run(): void
    {
        $vietnam = Country::where('code', 'VN')->first();
        if (! $vietnam) {
            return;
        }

        $cities = City::where('country_id', $vietnam->id)->get();
        if ($cities->isEmpty()) {
            return;
        }

        $vendors = User::where('role', 'vendor')->get();
        if ($vendors->isEmpty()) {
            return;
        }

        $amenityIds = Amenity::pluck('id')->toArray();
        if (empty($amenityIds)) {
            return;
        }

        $target = 30;
        $vendorIds = $vendors->pluck('id')->toArray();

        for ($i = 0; $i < $target; $i++) {
            $city = $cities->get($i % $cities->count());
            $vendorId = $vendorIds[$i % count($vendorIds)];
            $coords = $this->cityCoordinates[$city->name] ?? [16.05, 108.2];

            $baseName = $this->hotelNames[$i % count($this->hotelNames)];
            $name = $baseName . ' ' . $city->name . ' ' . ($i + 1);
            $address = $this->addressParts[$i % count($this->addressParts)] . ' ' . (rand(1, 99) + $i);

            $hotel = Hotel::firstOrCreate(
                ['name' => $name],
                [
                    'vendor_id' => $vendorId,
                    'name' => $name,
                    'description' => $this->descriptions[$i % count($this->descriptions)],
                    'address' => $address,
                    'country_id' => $vietnam->id,
                    'city_id' => $city->id,
                    'city' => $city->name,
                    'country' => 'Vietnam',
                    'latitude' => $coords[0] + (rand(-100, 100) / 10000),
                    'longitude' => $coords[1] + (rand(-100, 100) / 10000),
                    'check_in' => ['14:00:00', '15:00:00', '16:00:00'][$i % 3],
                    'check_out' => ['10:00:00', '11:00:00', '12:00:00'][$i % 3],
                    'status' => 'active',
                    'tax_rate' => 0.08,
                    'tax_name' => 'VAT',
                    'tax_inclusive' => false,
                ]
            );

            $count = rand(4, min(10, count($amenityIds)));
            $shuffled = $amenityIds;
            shuffle($shuffled);
            $hotel->amenities()->sync(array_slice($shuffled, 0, $count));
        }
    }
}
