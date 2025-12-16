<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'email' => 'admin@kelurahan.local',
                'password' => 'admin123',
                'role' => User::ROLE_ADMIN,
            ],
            [
                'name' => 'Staff Kelurahan',
                'username' => 'staff',
                'email' => 'staff@kelurahan.local',
                'password' => 'staff123',
                'role' => User::ROLE_PEGAWAI
            ],
            [
                'name' => 'Operator Pendaftaran',
                'username' => 'operator',
                'email' => 'operator@kelurahan.local',
                'password' => 'operator123',
                'role' => User::ROLE_USER
            ],
            [
                'name' => 'Warga Contoh',
                'username' => 'warga',
                'email' => 'warga@kelurahan.local',
                'password' => 'warga123',
                'role' => User::ROLE_USER
            ],
            [
                'name' => 'Kepala Kelurahan',
                'username' => 'lurah',
                'email' => 'lurah@kelurahan.local',
                'password' => 'lurah123',
                'role' => User::ROLE_ADMIN
            ],
        ];

        foreach ($users as $userData) {
            // Cek apakah user sudah ada
            $existingUser = User::where('username', $userData['username'])
                ->orWhere('email', $userData['email'])
                ->first();

            if (!$existingUser) {
                User::create([
                    'name' => $userData['name'],
                    'username' => $userData['username'],
                    'email' => $userData['email'],
                    'password' => Hash::make($userData['password']),
                    'role' => $userData['role'] ?? User::ROLE_USER
                ]);

                $this->command->info("User {$userData['name']} created successfully.");
            } else {
                $this->command->warn("User {$userData['name']} already exists.");
            }
        }

        // Buat beberapa user dummy untuk testing
        if (app()->environment('local')) {
            $this->createDummyUsers();
        }
    }

    private function createDummyUsers(): void
    {
        $faker = \Faker\Factory::create('id_ID');

        for ($i = 1; $i <= 10; $i++) {
            $name = $faker->name;
            $username = strtolower(str_replace(' ', '_', $name)) . $i;
            $email = $username . '@kelurahan.test';

            User::create([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => Hash::make('password123'),
                'role' => $faker->randomElement([User::ROLE_USER, User::ROLE_PEGAWAI, User::ROLE_ADMIN]),
            ]);
        }

        $this->command->info('10 dummy users created.');
    }
}
