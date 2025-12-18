<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class FamilyCardSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        $regionIds = DB::table('regions')->pluck('id')->toArray();

        for ($i = 0; $i < 30; $i++) {
            DB::table('family_cards')->insert([
                'family_card_number' => $faker->unique()->numerify('##########'),
                'head_of_family_name' => $faker->name,
                'address' => $faker->address,
                'publication_date' => $faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
                'region_id' => $faker->randomElement($regionIds),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
