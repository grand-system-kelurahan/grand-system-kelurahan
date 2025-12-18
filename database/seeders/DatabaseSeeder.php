<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            RegionSeeder::class,
            ResidentSeeder::class,
            UserSeeder::class,
            AssetLoanSeeder::class,
            AssetSeeder::class,
            FamilyCardSeeder::class,
            FamilyMemberSeeder::class,
            LetterTypeSeeder::class,
            LetterApplicationSeeder::class,
        ]);
    }
}
