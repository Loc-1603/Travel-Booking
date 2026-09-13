<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Database\Seeder;

class HotelImageSeeder extends Seeder
{
    /** Curated Unsplash hotel photos (stable IDs) */
    private array $hotelPhotos = [
        '1566073771259-6a8506099945',
        '1551882547-ff40c63fe5fa',
        '1542314831-068cd1dbfeeb',
        '1520250497591-112f2f40a3f4',
        '1571896349842-33c89424de2d',
        '1584132967334-10e028bd69f7',
        '1445019980597-93fa8acb246c',
        '1566665797739-1674de7a421a',
        '1582719508461-905c673771fd',
        '1590490360182-c33d57733427',
        '1611892440504-42a792e24d32',
        '1591088398332-8a7791972843',
    ];

    /** Curated Unsplash room photos (stable IDs) */
    private array $roomPhotos = [
        '1590490360182-c33d57733427',
        '1611892440504-42a792e24d32',
        '1578683010236-d716f9a3f461',
        '1591088398332-8a7791972843',
        '1582719508461-905c673771fd',
        '1566665797739-1674de7a421a',
        '1598928506315-c55ded91a20c',
        '1505693416388-ac5ce068fe85',
    ];

    private function url(string $photoId): string
    {
        return "https://images.unsplash.com/photo-{$photoId}?w=1200&q=80&auto=format&fit=crop";
    }

    public function run(): void
    {
        $hotels = Hotel::all();
        foreach ($hotels as $index => $hotel) {
            if ($hotel->images()->exists()) {
                continue;
            }
            for ($i = 0; $i < 3; $i++) {
                $photo = $this->hotelPhotos[($index * 3 + $i) % count($this->hotelPhotos)];
                HotelImage::create([
                    'hotel_id' => $hotel->id,
                    'image_path' => $this->url($photo),
                    'alt_text' => $hotel->name,
                    'is_banner' => $i === 0,
                    'sort_order' => $i,
                ]);
            }
        }

        $rooms = Room::all();
        foreach ($rooms as $index => $room) {
            if ($room->images()->exists()) {
                continue;
            }
            $photo = $this->roomPhotos[$index % count($this->roomPhotos)];
            RoomImage::create([
                'room_id' => $room->id,
                'image_path' => $this->url($photo),
                'alt_text' => $room->name,
                'is_banner' => true,
                'sort_order' => 0,
            ]);
        }
    }
}
