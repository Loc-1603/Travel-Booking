<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use Illuminate\Database\Seeder;

class VietnamCitiesSeeder extends Seeder
{
    /**
     * 15 Vietnamese cities with image filenames.
     * Images can be fetched via: php artisan cities:download-images --country=VN
     */
    public static array $cities = [
        'Hà Nội' => 'locations/cities/ha-noi.jpg',
        'TP.HCM' => 'locations/cities/tp-hcm.jpg',
        'Đà Nẵng' => 'locations/cities/da-nang.jpg',
        'Nha Trang' => 'locations/cities/nha-trang.jpg',
        'Huế' => 'locations/cities/hue.jpg',
        'Hội An' => 'locations/cities/hoi-an.jpg',
        'Đà Lạt' => 'locations/cities/da-lat.jpg',
        'Phú Quốc' => 'locations/cities/phu-quoc.jpg',
        'Hạ Long' => 'locations/cities/ha-long.jpg',
        'Sa Pa' => 'locations/cities/sa-pa.jpg',
        'Cần Thơ' => 'locations/cities/can-tho.jpg',
        'Vũng Tàu' => 'locations/cities/vung-tau.jpg',
        'Quy Nhơn' => 'locations/cities/quy-nhon.jpg',
        'Ninh Bình' => 'locations/cities/ninh-binh.jpg',
        'Hà Giang' => 'locations/cities/ha-giang.jpg',
    ];

    public function run(): void
    {
        $vietnam = Country::where('code', 'VN')->first();
        if (! $vietnam) {
            return;
        }

        foreach (self::$cities as $name => $image) {
            City::firstOrCreate(
                ['country_id' => $vietnam->id, 'name' => $name],
                ['country_id' => $vietnam->id, 'name' => $name, 'image' => $image]
            );
        }
    }
}
