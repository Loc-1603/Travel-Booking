<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VendorProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vendors = User::where('role', 'vendor')->get();

        foreach ($vendors as $vendor) {
            VendorProfile::firstOrCreate(
                ['user_id' => $vendor->id],
                [
                    'status' => VendorProfile::STATUS_APPROVED,
                    'approved_at' => now(),
                ]
            );
        }
    }
}
