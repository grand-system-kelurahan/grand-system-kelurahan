<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class ResidentSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        $regionIds = DB::table('regions')->pluck('id')->toArray();

        for ($i = 0; $i < 50; $i++) {
            DB::table('residents')->insert([
                'national_number_id' => $faker->unique()->numerify('################'),
                'name' => $faker->name,
                'gender' => $faker->randomElement(['male', 'female']),
                'place_of_birth' => $faker->city,
                'date_of_birth' => $faker->dateTimeBetween('-100 years', '-1 years')->format('Y-m-d'),
                'religion' => $faker->randomElement([
                    'Islam',
                    'Kristen',
                    'Katolik',
                    'Hindu',
                    'Buddha',
                    'Konghucu'
                ]),
                'rt' => str_pad($faker->numberBetween(1, 20), 3, '0', STR_PAD_LEFT),
                'rw' => str_pad($faker->numberBetween(1, 10), 3, '0', STR_PAD_LEFT),
                'education' => $faker->randomElement([
                    'SD',
                    'SMP',
                    'SMA',
                    'D3',
                    'S1',
                    'S2',
                    'S3'
                ]),
                'occupation' => $faker->jobTitle,
                'marital_status' => $faker->randomElement([
                    'Belum Kawin',
                    'Kawin',
                    'Cerai Hidup',
                    'Cerai Mati'
                ]),
                'citizenship' => 'WNI',
                'blood_type' => $faker->randomElement(['A', 'B', 'AB', 'O']),
                'disabilities' => $faker->randomElement([
                    'Tidak Ada',
                    'Tuna Netra',
                    'Tuna Rungu',
                    'Tuna Daksa'
                ]),
                'father_name' => $faker->name('male'),
                'mother_name' => $faker->name('female'),
                'region_id' => $faker->randomElement($regionIds),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
