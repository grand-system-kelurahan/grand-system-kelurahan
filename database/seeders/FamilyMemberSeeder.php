<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class FamilyMemberSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        $familyCardIds = DB::table('family_cards')->pluck('id')->toArray();
        $residentIds = DB::table('residents')->pluck('id')->toArray();

        // Track resident yang sudah dipakai untuk menghindari duplikasi
        $usedResidentIds = [];
        $familyMemberCount = 0;
        $targetMembers = 100; // Sama dengan jumlah total resident

        // Untuk setiap family card, tambahkan beberapa anggota
        foreach ($familyCardIds as $familyCardId) {
            // Tentukan jumlah anggota keluarga (1-5 orang)
            $membersInFamily = $faker->numberBetween(1, 5);

            // Untuk kepala keluarga (anggota pertama)
            if (count($usedResidentIds) < count($residentIds)) {
                $availableResidents = array_diff($residentIds, $usedResidentIds);
                $headResidentId = $faker->randomElement($availableResidents);

                DB::table('family_members')->insert([
                    'family_card_id' => $familyCardId,
                    'resident_id' => $headResidentId,
                    'relationship' => 'Kepala Keluarga',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $usedResidentIds[] = $headResidentId;
                $familyMemberCount++;
            }

            // Tambahkan anggota keluarga lainnya (istri/anak)
            for ($j = 1; $j < $membersInFamily; $j++) {
                if ($familyMemberCount >= $targetMembers || count($usedResidentIds) >= count($residentIds)) {
                    break 2; // Keluar dari kedua loop jika sudah mencapai target
                }

                $availableResidents = array_diff($residentIds, $usedResidentIds);
                if (empty($availableResidents)) {
                    break 2; // Tidak ada resident tersisa
                }

                $residentId = $faker->randomElement($availableResidents);

                DB::table('family_members')->insert([
                    'family_card_id' => $familyCardId,
                    'resident_id' => $residentId,
                    'relationship' => $faker->randomElement([
                        'Istri',
                        'Anak',
                        'Anak',
                        'Anak', // Lebih banyak kemungkinan anak
                        'Menantu',
                        'Cucu',
                        'Orang Tua',
                        'Saudara',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $usedResidentIds[] = $residentId;
                $familyMemberCount++;
            }
        }

        // Jika masih ada resident yang belum masuk ke family member (misalnya lajang)
        $remainingResidents = array_diff($residentIds, $usedResidentIds);
        if (!empty($remainingResidents)) {
            // Buat family card khusus untuk lajang
            foreach (array_chunk($remainingResidents, 1) as $chunk) { // 1 resident per family card untuk lajang
                if (empty($familyCardIds)) {
                    // Buat family card baru jika perlu
                    $regionIds = DB::table('regions')->pluck('id')->toArray();
                    $newFamilyCardId = DB::table('family_cards')->insertGetId([
                        'head_of_family_name' => DB::table('residents')->where('id', $chunk[0])->value('name'),
                        'address' => $faker->address,
                        'publication_date' => $faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
                        'region_id' => $faker->randomElement($regionIds),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $familyCardIds[] = $newFamilyCardId;
                } else {
                    $familyCardId = $faker->randomElement($familyCardIds);
                }

                DB::table('family_members')->insert([
                    'family_card_id' => $familyCardId,
                    'resident_id' => $chunk[0],
                    'relationship' => 'Kepala Keluarga',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
