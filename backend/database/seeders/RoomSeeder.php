<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $hotels = Hotel::all();
        if ($hotels->isEmpty()) {
            return;
        }

        $roomTemplates = [
            ['name' => 'Standard Double', 'capacity' => 2, 'base_price' => 650000, 'total_rooms' => 10, 'type_slug' => 'standard', 'size' => 28, 'bed_type' => 'double', 'view_type' => 'city', 'description' => 'Phòng tiêu chuẩn rộng rãi với giường đôi, cửa sổ nhìn ra thành phố.'],
            ['name' => 'Deluxe King', 'capacity' => 2, 'base_price' => 1200000, 'total_rooms' => 6, 'type_slug' => 'deluxe', 'size' => 35, 'bed_type' => 'king', 'view_type' => 'sea', 'description' => 'Phòng Deluxe với giường King cỡ lớn, tầm nhìn ra biển và nội thất cao cấp.'],
            ['name' => 'Suite', 'capacity' => 4, 'base_price' => 3500000, 'total_rooms' => 4, 'type_slug' => 'suite', 'size' => 55, 'bed_type' => 'king', 'view_type' => 'mountain', 'description' => 'Suite sang trọng với phòng khách riêng, giường King và tầm nhìn núi tuyệt đẹp.'],
            ['name' => 'Family Room', 'capacity' => 5, 'base_price' => 1800000, 'total_rooms' => 5, 'type_slug' => 'family', 'size' => 42, 'bed_type' => 'twin', 'view_type' => 'garden', 'description' => 'Phòng gia đình rộng rãi với 2 giường đơn lớn, phù hợp cho 4-5 người.'],
            ['name' => 'Single', 'capacity' => 1, 'base_price' => 550000, 'total_rooms' => 8, 'type_slug' => 'standard', 'size' => 18, 'bed_type' => 'single', 'view_type' => 'courtyard', 'description' => 'Phòng đơn gọn gàng, đầy đủ tiện nghi cho khách công tác.'],
        ];

        $roomAmenitySlugs = ['wifi', 'air-conditioning', 'minibar', 'balcony', 'room-service'];
        $roomAmenityIds = Amenity::whereIn('slug', $roomAmenitySlugs)->pluck('id')->toArray();

        foreach ($hotels as $hotel) {
            $roomTypes = RoomType::where('hotel_id', $hotel->id)->get()->keyBy('slug');
            foreach ($roomTemplates as $template) {
                $room = Room::firstOrCreate(
                    [
                        'hotel_id' => $hotel->id,
                        'name' => $template['name'],
                    ],
                    [
                        'room_type_id' => $roomTypes[$template['type_slug']]?->id,
                        'capacity' => $template['capacity'],
                        'base_price' => $template['base_price'],
                        'total_rooms' => $template['total_rooms'],
                        'size' => $template['size'],
                        'bed_type' => $template['bed_type'],
                        'view_type' => $template['view_type'],
                        'description' => $template['description'],
                    ]
                );
                if (! empty($roomAmenityIds)) {
                    $count = rand(2, min(5, count($roomAmenityIds)));
                    $shuffled = $roomAmenityIds;
                    shuffle($shuffled);
                    $ids = array_slice($shuffled, 0, $count);
                    $room->amenities()->sync($ids);
                }
            }
        }
    }
}
