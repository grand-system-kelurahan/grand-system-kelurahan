<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            'Desa Bunutin',
            'Desa Kayubihi',
            'Desa Landih',
            'Desa Pengotan',
            'Desa Taman Bali',
        ];

        foreach ($regions as $region) {
            DB::table('regions')->insert([
                'name' => $region,
                'encoded_geometry' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
