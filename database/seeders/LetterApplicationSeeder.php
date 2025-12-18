<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class LetterApplicationSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // Ambil ID dari tabel yang benar
        $residentIds = DB::table('residents')->pluck('id')->toArray();
        $letterTypeIds = DB::table('letter_types')->pluck('id')->toArray();
        $userIds = DB::table('users')->pluck('id')->toArray(); // Jika submitted_by merujuk ke users

        // Jika tidak ada data users, buat dummy users terlebih dahulu
        if (empty($userIds)) {
            $this->createDummyUsers();
            $userIds = DB::table('users')->pluck('id')->toArray();
        }

        // Pastikan ada data untuk dijadikan approved_by
        $adminUserIds = array_slice($userIds, 0, min(5, count($userIds)));

        $statuses = ['new', 'on_progress', 'rejected', 'approved'];
        $rejectionReasons = [
            'Data tidak lengkap',
            'Dokumen pendukung tidak valid',
            'Syarat tidak terpenuhi',
            'Pengajuan diluar jam kerja',
            'Tanda tangan tidak sesuai',
            null
        ];

        // Generate letter applications
        for ($i = 0; $i < 100; $i++) {
            $submissionDate = $faker->dateTimeBetween('-6 months', 'now');
            $status = $faker->randomElement($statuses);

            // Tentukan approval date berdasarkan status
            $approvalDate = null;
            if ($status === 'approved' || $status === 'rejected') {
                $approvalDate = $faker->dateTimeBetween($submissionDate, 'now');
            }

            // Tentukan description berdasarkan status
            $description = null;
            $rejectionReason = null;

            if ($status === 'rejected') {
                $rejectionReason = $faker->randomElement($rejectionReasons);
                $description = "Pengajuan ditolak. Alasan: " . $rejectionReason;
            } elseif ($status === 'approved') {
                $description = "Pengajuan telah disetujui dan siap diambil.";
            } elseif ($status === 'on_progress') {
                $description = "Sedang dalam proses verifikasi oleh petugas.";
            } else {
                $description = "Menunggu diproses oleh petugas.";
            }

            // Generate letter number format: SK/001/VI/2024
            $letterNumber = $this->generateLetterNumber($faker);

            // Escape single quotes di letter number
            $letterNumber = str_replace("'", "''", $letterNumber);

            DB::table('letter_applications')->insert([
                'resident_id' => $faker->randomElement($residentIds),
                'approved_by' => ($status === 'approved' || $status === 'rejected')
                    ? $faker->randomElement($adminUserIds)
                    : null,
                'submitted_by' => $faker->randomElement($userIds),
                'letter_type_id' => $faker->randomElement($letterTypeIds),
                'letter_number' => $letterNumber,
                'submission_date' => $submissionDate->format('Y-m-d H:i:s'),
                'approval_date' => $approvalDate ? $approvalDate->format('Y-m-d H:i:s') : null,
                'status' => $status,
                'description' => $description,
                'created_at' => $submissionDate,
                'updated_at' => $approvalDate ?: $submissionDate,
            ]);
        }

        // Update beberapa data agar lebih realistis
        $this->updateForConsistency($userIds);
    }

    /**
     * Create dummy users if users table is empty
     */
    private function createDummyUsers(): void
    {
        $faker = Faker::create('id_ID');

        for ($i = 0; $i < 10; $i++) {
            DB::table('users')->insert([
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'email_verified_at' => now(),
                'password' => bcrypt('password123'),
                'role' => $faker->randomElement(['admin', 'staff', 'user']),
                'remember_token' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Generate realistic letter number
     */
    private function generateLetterNumber($faker): string
    {
        $letterCodes = ['SK', 'ST', 'SU', 'SP'];
        $code = $faker->randomElement($letterCodes);
        $sequence = str_pad($faker->numberBetween(1, 999), 3, '0', STR_PAD_LEFT);
        $monthRoman = $this->getRomanNumber($faker->numberBetween(1, 12));
        $year = $faker->numberBetween(2022, 2024);

        return "{$code}/{$sequence}/{$monthRoman}/{$year}";
    }

    /**
     * Convert number to Roman numeral
     */
    private function getRomanNumber($number): string
    {
        $map = [
            'I',
            'II',
            'III',
            'IV',
            'V',
            'VI',
            'VII',
            'VIII',
            'IX',
            'X',
            'XI',
            'XII'
        ];

        return $map[$number - 1] ?? 'I';
    }

    /**
     * Update data untuk konsistensi
     */
    private function updateForConsistency(array $userIds): void
    {
        // Update beberapa data agar submitted_by konsisten dengan user yang ada
        DB::table('letter_applications')
            ->whereNotIn('submitted_by', $userIds)
            ->update([
                'submitted_by' => DB::raw("(SELECT id FROM users ORDER BY RAND() LIMIT 1)")
            ]);

        // Update beberapa data agar approved_by konsisten
        DB::table('letter_applications')
            ->whereNotNull('approved_by')
            ->whereNotIn('approved_by', $userIds)
            ->update([
                'approved_by' => DB::raw("(SELECT id FROM users WHERE role = 'admin' ORDER BY RAND() LIMIT 1)")
            ]);

        // Pastikan approved_by hanya untuk status approved/rejected
        DB::table('letter_applications')
            ->whereIn('status', ['new', 'on_progress'])
            ->whereNotNull('approved_by')
            ->update(['approved_by' => null]);

        // Pastikan approval_date hanya untuk status approved/rejected
        DB::table('letter_applications')
            ->whereIn('status', ['new', 'on_progress'])
            ->whereNotNull('approval_date')
            ->update(['approval_date' => null]);

        // Update timeline untuk status on_progress
        DB::statement("
            UPDATE letter_applications 
            SET updated_at = DATE_ADD(submission_date, INTERVAL FLOOR(RAND() * 7) DAY)
            WHERE status = 'on_progress'
        ");

        // Update timeline untuk status approved/rejected
        DB::statement("
            UPDATE letter_applications 
            SET updated_at = DATE_ADD(approval_date, INTERVAL 1 HOUR)
            WHERE status IN ('approved', 'rejected')
        ");
    }
}
