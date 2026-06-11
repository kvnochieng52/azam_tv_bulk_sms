<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'kvnochieng52@gmail.com'],
            [
                'name'              => 'Admin',
                'email'             => 'kvnochieng52@gmail.com',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active'         => true,
            ]
        );

        // Ensure is_active is set even if user already existed
        if (!$user->is_active) {
            $user->update(['is_active' => true]);
        }

        $adminRole = Role::where('name', 'admin')->first();

        if ($adminRole && !$user->hasRole('admin')) {
            $user->assignRole($adminRole);
        }

        // Assign all active countries to admin
        $allCountryIds = Country::where('is_active', true)->pluck('id');
        $user->countries()->sync($allCountryIds);

        $this->command->info('Admin user seeded: kvnochieng52@gmail.com (countries: ' . $allCountryIds->count() . ')');
    }
}
