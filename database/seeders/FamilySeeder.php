<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class FamilySeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        $regionIds   = DB::table('regions')->pluck('id')->toArray();
        $residentIds = DB::table('residents')->pluck('id')->toArray();

        // Safety check
        if (empty($regionIds) || empty($residentIds)) {
            return;
        }

        shuffle($residentIds);

        $relationships = [
            'Istri',
            'Suami',
            'Anak',
            'Orang Tua',
            'Saudara',
        ];

        // Tentukan jumlah KK (misal 30 KK atau sesuai jumlah resident)
        $familyCount = min(30, intdiv(count($residentIds), 2));

        for ($i = 0; $i < $familyCount; $i++) {

            // INSERT FAMILY CARD
            $familyCardId = DB::table('family_cards')->insertGetId([
                'head_of_family_name' => $faker->name('male'),
                'address' => $faker->address,
                'publication_date' => $faker
                    ->dateTimeBetween('-20 years', 'now')
                    ->format('Y-m-d'),
                'region_id' => $faker->randomElement($regionIds),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // KEPALA KELUARGA
            $headResidentId = array_shift($residentIds);
            if (!$headResidentId) break;

            DB::table('family_members')->insert([
                'family_card_id' => $familyCardId,
                'resident_id' => $headResidentId,
                'relationship' => 'Kepala Keluarga',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // ANGGOTA KELUARGA
            $memberCount = rand(1, 4);

            for ($j = 0; $j < $memberCount; $j++) {
                $residentId = array_shift($residentIds);
                if (!$residentId) break;

                DB::table('family_members')->insert([
                    'family_card_id' => $familyCardId,
                    'resident_id' => $residentId,
                    'relationship' => $faker->randomElement($relationships),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
