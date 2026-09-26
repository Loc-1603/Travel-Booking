<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSuperAdmin();
        $this->seedAdmin();
        $this->seedVendor();
        $this->seedAdditionalVendors();
        $this->seedCustomer();
    }

    protected function seedSuperAdmin(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Super Admin',
                'password' => bcrypt('12345678'),
                'role' => Role::SUPER_ADMIN,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        if (!$admin->hasRole('super-admin')) {
            $admin->assignRole('super-admin');
        }
        $admin->update(['role' => Role::SUPER_ADMIN, 'status' => 'active']);
    }

    protected function seedAdmin(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin2@test.com'],
            [
                'name' => 'Admin User',
                'password' => bcrypt('12345678'),
                'role' => Role::ADMIN,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        if (!$user->hasRole('admin')) {
            $user->assignRole('admin');
        }
        $user->update(['role' => Role::ADMIN, 'status' => 'active']);
    }

    protected function seedVendor(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'vendor@test.com'],
            [
                'name' => 'Vendor User',
                'password' => bcrypt('12345678'),
                'role' => Role::VENDOR,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        if (!$user->hasRole('vendor')) {
            $user->assignRole('vendor');
        }
        $user->update(['role' => Role::VENDOR, 'status' => 'active']);
    }

    protected function seedAdditionalVendors(): void
    {
        $additionalVendors = [
            [
                'email' => 'vendor2@test.com',
                'name' => 'vendor2',
            ],
            [
                'email' => 'vendor3@test.com',
                'name' => 'vendor3',
            ],
            [
                'email' => 'vendor4@test.com',
                'name' => 'vendor4',
            ],
            [
                'email' => 'vendor5@test.com',
                'name' => 'vendor5',
            ],
            [
                'email' => 'vendor6@test.com',
                'name' => 'vendor6',
            ],
        ];

        foreach ($additionalVendors as $vendorData) {
            $user = User::firstOrCreate(
                ['email' => $vendorData['email']],
                [
                    'name' => $vendorData['name'],
                    'password' => bcrypt('12345678'),
                    'role' => Role::VENDOR,
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );
            if (!$user->hasRole('vendor')) {
                $user->assignRole('vendor');
            }
            $user->update(['role' => Role::VENDOR, 'status' => 'active']);
        }
    }

    protected function seedCustomer(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'customer@test.com'],
            [
                'name' => 'Customer User',
                'password' => bcrypt('12345678'),
                'role' => Role::CUSTOMER,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );
        if (!$user->hasRole('customer')) {
            $user->assignRole('customer');
        }
        $user->update(['role' => Role::CUSTOMER, 'status' => 'active']);
    }
}
