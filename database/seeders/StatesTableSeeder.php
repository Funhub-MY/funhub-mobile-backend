<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StatesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $malaysia = Country::where('code', 'MY')->first();
        if ($malaysia) {
            $states = [
                'JHR' => 'Johor',
                'KDH' => 'Kedah',
                'KTN' => 'Kelantan',
                'MLK' => 'Melaka',
                'NSN' => 'Negeri Sembilan',
                'PHG' => 'Pahang',
                'PRK' => 'Perak',
                'PLS' => 'Perlis',
                'PNG' => 'Pulau Pinang',
                'SBH' => 'Sabah',
                'SWK' => 'Sarawak',
                'SGR' => 'Selangor',
                'TRG' => 'Terengganu',
                'KUL' => 'W.P. Kuala Lumpur',
                'LBN' => 'W.P. Labuan',
                'PJY' => 'W.P. Putrajaya',
            ];

            foreach ($states as $code => $name) {
                \App\Models\State::create([
                    'country_id' => $malaysia->id,
                    'code' => $code,
                    'name' => $name,
                ]);
            }
        }
    }
}
