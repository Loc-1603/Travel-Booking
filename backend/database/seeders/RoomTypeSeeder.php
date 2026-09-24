<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultRoomTypes = [
            ['name' => 'Standard', 'slug' => 'standard', 'description' => 'Phòng tiêu chuẩn cơ bản', 'sort_order' => 1],
            ['name' => 'Deluxe', 'slug' => 'deluxe', 'description' => 'Phòng cao cấp hơn Standard', 'sort_order' => 2],
            ['name' => 'Suite', 'slug' => 'suite', 'description' => 'Phòng rộng rãi, có khu vực khách riêng', 'sort_order' => 3],
            ['name' => 'Family', 'slug' => 'family', 'description' => 'Phòng gia đình, diện tích lớn', 'sort_order' => 4],
            ['name' => 'Executive', 'slug' => 'executive', 'description' => 'Phòng phục vụ khách doanh nhân', 'sort_order' => 5],
            ['name' => 'Presidential', 'slug' => 'presidential', 'description' => 'Phòng hạng sang nhất', 'sort_order' => 6],
        ];

        $hotels = Hotel::all();

        foreach ($hotels as $hotel) {
            foreach ($defaultRoomTypes as $index => $type) {
                RoomType::firstOrCreate(
                    ['hotel_id' => $hotel->id, 'slug' => $type['slug']],
                    array_merge($type, ['hotel_id' => $hotel->id])
                );
            }
        }
    }
}
