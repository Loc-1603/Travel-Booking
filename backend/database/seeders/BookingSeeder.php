<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Booking;
use App\Models\BookingRoom;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', 'customer')->get();
        if ($customers->isEmpty()) {
            return;
        }

        $hotels = Hotel::with('rooms')->get();
        if ($hotels->isEmpty()) {
            return;
        }

        // Create multiple customers for more realistic bookings
        $additionalCustomers = [
            ['email' => 'customer1@test.com', 'name' => 'customer1'],
            ['email' => 'customer2@test.com', 'name' => 'customer2'],
            ['email' => 'customer3@test.com', 'name' => 'customer3'],
            ['email' => 'customer4@test.com', 'name' => 'customer4'],
        ];

        foreach ($additionalCustomers as $customerData) {
            $customer = User::firstOrCreate(
                ['email' => $customerData['email']],
                [
                    'name' => $customerData['name'],
                    'password' => bcrypt('12345678'),
                    'role' => Role::CUSTOMER,
                    'status' => 'active',
                ]
            );
            if (!$customer->hasRole('customer')) {
                $customer->assignRole('customer');
            }
        }

        $allCustomers = User::where('role', 'customer')->get();

        // Booking window: 20/08/2026 - 10/10/2026. check_out may overflow past 10/10.
        // Today reference 09/09/2026: past dates -> completed/cancelled, future dates -> confirmed/pending.
        $pastStart = Carbon::create(2026, 8, 20)->startOfDay();
        $futureStart = Carbon::create(2026, 9, 10)->startOfDay();
        $futureEnd = Carbon::create(2026, 10, 10)->startOfDay();
        $futureDays = $futureStart->diffInDays($futureEnd); // 30

        // Past/completed bookings (45 bookings, check_out < 09/09/2026)
        for ($i = 0; $i < 45; $i++) {
            $hotel = $hotels->random();
            $room = $hotel->rooms->random();
            $customer = $allCustomers->random();

            if (!$room) {
                continue;
            }

            // check_in: 20/08 - 01/09 => +1-7 nights => check_out <= 08/09
            $checkIn = $pastStart->copy()->addDays(rand(0, 12));
            $checkOut = $checkIn->copy()->addDays(rand(1, 7));
            $nights = $checkIn->diffInDays($checkOut);
            $totalPrice = round($room->base_price * $nights, 2);

            $booking = Booking::create([
                'customer_id' => $customer->id,
                'hotel_id' => $hotel->id,
                'status' => 'completed',
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'total_price' => $totalPrice,
                'currency' => 'VND',
            ]);

            BookingRoom::create([
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'quantity' => rand(1, 2),
                'unit_price' => $room->base_price,
            ]);

            Payment::create([
                'booking_id' => $booking->id,
                'amount' => $totalPrice,
                'currency' => 'VND',
                'provider' => ['vnpay', 'cash', 'bank_transfer'][array_rand(['vnpay', 'cash', 'bank_transfer'])],
                'external_id' => 'pi_'.uniqid(),
                'status' => 'succeeded',
                'payload' => ['payment_method' => 'card'],
            ]);

            // Add reviews for most completed bookings
            if (rand(1, 10) <= 7) {
                Review::create([
                    'booking_id' => $booking->id,
                    'rating' => (int) rand(3, 5),
                    'comment' => [
                        'Excellent stay, highly recommended!',
                        'Great location and service.',
                        'Comfortable rooms and friendly staff.',
                        'Would definitely stay again.',
                        'Good value for money.',
                    ][array_rand([
                        'Excellent stay, highly recommended!',
                        'Great location and service.',
                        'Comfortable rooms and friendly staff.',
                        'Would definitely stay again.',
                        'Good value for money.',
                    ])],
                    'approved' => (bool) rand(0, 1),
                ]);
            }
        }

        // Upcoming confirmed bookings (30 bookings, check_in 10/09 - 10/10)
        for ($i = 0; $i < 30; $i++) {
            $hotel = $hotels->random();
            $room = $hotel->rooms->random();
            $customer = $allCustomers->random();

            if (!$room) {
                continue;
            }

            $checkIn = $futureStart->copy()->addDays(rand(0, $futureDays));
            $checkOut = $checkIn->copy()->addDays(rand(1, 5));
            $nights = $checkIn->diffInDays($checkOut);
            $totalPrice = round($room->base_price * $nights, 2);

            $booking = Booking::create([
                'customer_id' => $customer->id,
                'hotel_id' => $hotel->id,
                'status' => 'confirmed',
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'total_price' => $totalPrice,
                'currency' => 'VND',
            ]);

            BookingRoom::create([
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'quantity' => rand(1, 2),
                'unit_price' => $room->base_price,
            ]);

            Payment::create([
                'booking_id' => $booking->id,
                'amount' => $totalPrice,
                'currency' => 'VND',
                'provider' => ['vnpay', 'bank_transfer'][array_rand(['vnpay', 'bank_transfer'])],
                'external_id' => 'pi_'.uniqid(),
                'status' => 'succeeded',
                'payload' => null,
            ]);
        }

        // Pending bookings (15 bookings, check_in 10/09 - 10/10)
        for ($i = 0; $i < 15; $i++) {
            $hotel = $hotels->random();
            $room = $hotel->rooms->random();
            $customer = $allCustomers->random();

            if (!$room) {
                continue;
            }

            $checkIn = $futureStart->copy()->addDays(rand(0, $futureDays));
            $checkOut = $checkIn->copy()->addDays(rand(1, 4));
            $nights = $checkIn->diffInDays($checkOut);
            $totalPrice = round($room->base_price * $nights, 2);

            $booking = Booking::create([
                'customer_id' => $customer->id,
                'hotel_id' => $hotel->id,
                'status' => 'pending',
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'total_price' => $totalPrice,
                'currency' => 'VND',
            ]);

            BookingRoom::create([
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'quantity' => 1,
                'unit_price' => $room->base_price,
            ]);
        }

        // Cancelled bookings (10 bookings, check_in 20/08 - 01/09)
        for ($i = 0; $i < 10; $i++) {
            $hotel = $hotels->random();
            $room = $hotel->rooms->random();
            $customer = $allCustomers->random();

            if (!$room) {
                continue;
            }

            $checkIn = $pastStart->copy()->addDays(rand(0, 12));
            $checkOut = $checkIn->copy()->addDays(rand(1, 3));
            $nights = $checkIn->diffInDays($checkOut);
            $totalPrice = round($room->base_price * $nights, 2);

            $booking = Booking::create([
                'customer_id' => $customer->id,
                'hotel_id' => $hotel->id,
                'status' => 'cancelled',
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'total_price' => $totalPrice,
                'currency' => 'VND',
            ]);

            BookingRoom::create([
                'booking_id' => $booking->id,
                'room_id' => $room->id,
                'quantity' => 1,
                'unit_price' => $room->base_price,
            ]);
        }
    }
}
