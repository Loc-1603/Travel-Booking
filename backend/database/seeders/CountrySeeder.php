<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            [
                'name' => 'Vietnam',
                'code' => 'VN',
                'tax_rate' => 0.08,
                'tax_name' => 'VAT',
                'image' => 'locations/countries/vietnam.jpg',
            ],
        ];

        foreach ($countries as $data) {
            Country::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}
