<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            AdminSeeder::class,
            VendorProfileSeeder::class,
            PlatformSettingSeeder::class,
            CountrySeeder::class,
            VietnamCitiesSeeder::class,
            AmenitySeeder::class,
            HotelSeeder::class,
            RoomTypeSeeder::class,
            RoomSeeder::class,
            HotelImageSeeder::class,
            RoomAvailabilitySeeder::class,
            BookingSeeder::class,
            CouponSeeder::class,
            TourProvinceSeeder::class,
            TourProductSeeder::class,
            TourHaGiangDemoSeeder::class,
        ]);
    }
}
