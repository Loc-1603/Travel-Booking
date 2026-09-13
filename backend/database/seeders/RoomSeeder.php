<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\Hotel;
use App\Models\Room;
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
            ['name' => 'Standard Double', 'capacity' => 2, 'base_price' => 650000, 'total_rooms' => 10],
            ['name' => 'Deluxe King', 'capacity' => 2, 'base_price' => 1200000, 'total_rooms' => 6],
            ['name' => 'Suite', 'capacity' => 4, 'base_price' => 3500000, 'total_rooms' => 4],
            ['name' => 'Family Room', 'capacity' => 5, 'base_price' => 1800000, 'total_rooms' => 5],
            ['name' => 'Single', 'capacity' => 1, 'base_price' => 550000, 'total_rooms' => 8],
        ];

        $roomAmenitySlugs = ['wifi', 'air-conditioning', 'minibar', 'balcony', 'room-service'];
        $roomAmenityIds = Amenity::whereIn('slug', $roomAmenitySlugs)->pluck('id')->toArray();

        foreach ($hotels as $hotel) {
            foreach ($roomTemplates as $template) {
                $room = Room::firstOrCreate(
                    [
                        'hotel_id' => $hotel->id,
                        'name' => $template['name'],
                    ],
                    [
                        'room_type_id' => null,
                        'capacity' => $template['capacity'],
                        'base_price' => $template['base_price'],
                        'total_rooms' => $template['total_rooms'],
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
