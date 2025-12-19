<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class ResidentVerificationSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // Ambil data yang diperlukan
        $residentIds = DB::table('residents')->pluck('id')->toArray();
        $userIds = DB::table('users')->pluck('id')->toArray();

        // Jika tidak ada users, buat dummy user terlebih dahulu
        if (empty($userIds)) {
            $this->createDummyUsers();
            $userIds = DB::table('users')->pluck('id')->toArray();
        }

        $statuses = ['pending', 'verified', 'rejected'];

        // Untuk setiap resident, buat verifikasi dengan status random
        foreach ($residentIds as $residentId) {
            $status = $faker->randomElement($statuses);
            $verifiedBy = null;
            $verifiedAt = null;
            $notes = null;
            $verifiedData = null;

            // Jika status verified atau rejected, isi verified_by dan verified_at
            if ($status === 'verified' || $status === 'rejected') {
                $verifiedBy = $faker->randomElement($userIds);
                $verifiedAt = $faker->dateTimeBetween('-6 months', 'now');

                // Ambil data resident untuk disimpan di verified_data
                $resident = DB::table('residents')->where('id', $residentId)->first();
                if ($resident) {
                    $verifiedData = json_encode([
                        'national_number_id' => $resident->national_number_id,
                        'name' => $resident->name,
                        'gender' => $resident->gender,
                        'place_of_birth' => $resident->place_of_birth,
                        'date_of_birth' => $resident->date_of_birth,
                        'religion' => $resident->religion,
                        'rt' => $resident->rt,
                        'rw' => $resident->rw,
                        'education' => $resident->education,
                        'occupation' => $resident->occupation,
                        'marital_status' => $resident->marital_status,
                        'verified_at' => $verifiedAt->format('Y-m-d H:i:s'),
                    ]);
                }

                // Buat notes berdasarkan status
                if ($status === 'verified') {
                    $notes = $faker->randomElement([
                        'Data sudah diverifikasi dan valid',
                        'Semua dokumen lengkap dan sesuai',
                        'Verifikasi selesai, data sesuai dengan dokumen',
                        'Tidak ada masalah pada data yang diajukan',
                        'Verifikasi berhasil, data sudah benar',
                    ]);
                } else { // rejected
                    $rejectionReasons = [
                        'Data tidak lengkap',
                        'Dokumen pendukung tidak valid',
                        'Foto tidak jelas',
                        'NIK sudah terdaftar',
                        'Alamat tidak sesuai dengan KK',
                        'Data tidak up-to-date',
                        'Tanda tangan tidak sesuai',
                    ];
                    $notes = "Ditolak: " . $faker->randomElement($rejectionReasons);
                }
            } else { // pending
                $notes = $faker->randomElement([
                    'Menunggu verifikasi petugas',
                    'Sedang dalam proses pengecekan',
                    'Dokumen sedang diperiksa',
                    'Menunggu konfirmasi dari kepala lingkungan',
                    null,
                ]);
            }

            // Tentukan created_at (harus sebelum verified_at jika ada)
            $createdAt = $faker->dateTimeBetween('-1 year', $verifiedAt ?: 'now');

            DB::table('resident_verifications')->insert([
                'resident_id' => $residentId,
                'verified_by' => $verifiedBy,
                'status' => $status,
                'notes' => $notes,
                'verified_data' => $verifiedData,
                'verified_at' => $verifiedAt ? $verifiedAt->format('Y-m-d H:i:s') : null,
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'updated_at' => $verifiedAt ? $verifiedAt->format('Y-m-d H:i:s') : $createdAt->format('Y-m-d H:i:s'),
            ]);
        }

        // Tambahkan beberapa verifikasi pending tambahan untuk beberapa resident
        $this->createAdditionalPendingVerifications($residentIds, $userIds);

        // Update beberapa data agar lebih realistis
        $this->updateForConsistency($userIds);
    }

    /**
     * Create dummy users if users table is empty
     */
    private function createDummyUsers(): void
    {
        $faker = Faker::create('id_ID');

        $users = [];
        for ($i = 0; $i < 10; $i++) {
            $users[] = [
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'email_verified_at' => now(),
                'password' => bcrypt('password123'),
                'role' => $faker->randomElement(['admin', 'staff', 'user']),
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('users')->insert($users);
    }

    /**
     * Create additional pending verifications for some residents
     */
    private function createAdditionalPendingVerifications(array $residentIds, array $userIds): void
    {
        $faker = Faker::create('id_ID');

        // Ambil 20% resident untuk memiliki multiple verifikasi (history)
        $selectedResidentIds = $faker->randomElements($residentIds, intval(count($residentIds) * 0.2));

        foreach ($selectedResidentIds as $residentId) {
            // Buat 2-3 verifikasi tambahan dengan status completed
            $count = $faker->numberBetween(2, 3);

            for ($i = 0; $i < $count; $i++) {
                $status = $faker->randomElement(['verified', 'rejected']);
                $verifiedBy = $faker->randomElement($userIds);
                $verifiedAt = $faker->dateTimeBetween('-2 years', '-7 months');
                $createdAt = $faker->dateTimeBetween($verifiedAt->format('Y-m-d H:i:s'), '-1 month');

                // Ambil data resident
                $resident = DB::table('residents')->where('id', $residentId)->first();
                $verifiedData = null;

                if ($resident) {
                    $verifiedData = json_encode([
                        'national_number_id' => $resident->national_number_id,
                        'name' => $resident->name,
                        'gender' => $resident->gender,
                        'place_of_birth' => $resident->place_of_birth,
                        'date_of_birth' => $resident->date_of_birth,
                        'religion' => $resident->religion,
                        'rt' => $resident->rt,
                        'rw' => $resident->rw,
                        'education' => $resident->education,
                        'verified_at' => $verifiedAt->format('Y-m-d H:i:s'),
                    ]);
                }

                $notes = $status === 'verified'
                    ? $faker->randomElement(['Verifikasi tahunan', 'Update data', 'Perubahan alamat'])
                    : "Ditolak: " . $faker->randomElement(['Dokumen expired', 'Foto tidak jelas', 'Data sudah kadaluarsa']);

                DB::table('resident_verifications')->insert([
                    'resident_id' => $residentId,
                    'verified_by' => $verifiedBy,
                    'status' => $status,
                    'notes' => $notes,
                    'verified_data' => $verifiedData,
                    'verified_at' => $verifiedAt->format('Y-m-d H:i:s'),
                    'created_at' => $createdAt->format('Y-m-d H:i:s'),
                    'updated_at' => $verifiedAt->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /**
     * Update data untuk konsistensi
     */
    private function updateForConsistency(array $userIds): void
    {
        // Pastikan verified_by hanya untuk status verified/rejected
        DB::table('resident_verifications')
            ->where('status', 'pending')
            ->whereNotNull('verified_by')
            ->update(['verified_by' => null]);

        // Pastikan verified_at hanya untuk status verified/rejected
        DB::table('resident_verifications')
            ->where('status', 'pending')
            ->whereNotNull('verified_at')
            ->update([
                'verified_at' => null,
                'verified_data' => null,
            ]);

        // Update timeline agar verified_at > created_at
        DB::statement("
            UPDATE resident_verifications 
            SET verified_at = DATE_ADD(created_at, INTERVAL FLOOR(RAND() * 30) DAY)
            WHERE status IN ('verified', 'rejected') 
            AND verified_at IS NOT NULL
            AND verified_at <= created_at
        ");

        // Update updated_at agar sesuai dengan timeline
        DB::table('resident_verifications')
            ->whereIn('status', ['verified', 'rejected'])
            ->whereNotNull('verified_at')
            ->update([
                'updated_at' => DB::raw('verified_at')
            ]);

        // Untuk pending, updated_at = created_at + random days
        DB::statement("
            UPDATE resident_verifications 
            SET updated_at = DATE_ADD(created_at, INTERVAL FLOOR(RAND() * 60) DAY)
            WHERE status = 'pending'
            AND updated_at <= created_at
        ");

        // Validasi foreign key verified_by
        DB::table('resident_verifications')
            ->whereNotNull('verified_by')
            ->whereNotIn('verified_by', $userIds)
            ->update([
                'verified_by' => DB::raw("(SELECT id FROM users ORDER BY RAND() LIMIT 1)")
            ]);
    }
}
