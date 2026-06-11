<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'Kenya',  'code' => 'KE', 'phone_prefix' => '254', 'is_active' => true],
            ['name' => 'Uganda', 'code' => 'UG', 'phone_prefix' => '256', 'is_active' => true],
        ];

        foreach ($countries as $country) {
            Country::firstOrCreate(['code' => $country['code']], $country);
        }
    }
}
