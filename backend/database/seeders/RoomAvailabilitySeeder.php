<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomAvailability;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RoomAvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        $rooms = Room::all();
        if ($rooms->isEmpty()) {
            return;
        }

        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays(180);

        foreach ($rooms as $room) {
            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                // Create realistic availability patterns
                $dayOfWeek = $date->dayOfWeek;
                $isWeekend = ($dayOfWeek === Carbon::SATURDAY || $dayOfWeek === Carbon::SUNDAY);
                $isHoliday = $this->isHoliday($date);
                
                // Base availability with weekend variations
                if ($isWeekend) {
                    $availableRooms = max(1, $room->total_rooms - rand(0, 2)); // Higher demand on weekends
                } elseif ($isHoliday) {
                    $availableRooms = max(1, $room->total_rooms - rand(1, 3)); // Even higher demand on holidays
                } else {
                    $availableRooms = max($room->total_rooms - rand(0, 1), $room->total_rooms - 2); // Normal weekday availability
                }

                // Price variations based on demand (VND, rounded to thousands)
                $priceOverride = null;
                if ($isWeekend) {
                    $priceOverride = $room->base_price + rand(100, 300) * 1000; // Weekend premium
                } elseif ($isHoliday) {
                    $priceOverride = $room->base_price + rand(200, 500) * 1000; // Holiday premium
                } elseif (rand(1, 20) === 1) {
                    $priceOverride = $room->base_price + rand(-50, 100) * 1000; // Random price fluctuations
                }

                // Seasonal pricing (higher in summer months)
                $month = $date->month;
                if (in_array($month, [6, 7, 8])) { // Summer months
                    $priceOverride = ($priceOverride ?? $room->base_price) + rand(100, 300) * 1000;
                } elseif (in_array($month, [12, 1, 2])) { // Winter months
                    $priceOverride = ($priceOverride ?? $room->base_price) - rand(50, 150) * 1000;
                }

                RoomAvailability::firstOrCreate(
                    [
                        'room_id' => $room->id,
                        'date' => $date->toDateString(),
                    ],
                    [
                        'available_rooms' => $availableRooms,
                        'price_override' => $priceOverride,
                    ]
                );
            }
        }
    }

    private function isHoliday(Carbon $date): bool
    {
        // Vietnamese public holidays (fixed-date ones; Tet varies by lunar calendar)
        $holidays = [
            '01-01', // Tết Dương lịch
            '04-30', // Ngày Giải phóng miền Nam
            '05-01', // Quốc tế Lao động
            '09-02', // Quốc khánh
        ];

        return in_array($date->format('m-d'), $holidays);
    }
}
