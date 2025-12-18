<?php

namespace Database\Seeders;

use App\Models\LetterType;
use Illuminate\Database\Seeder;

class LetterTypeSeeder extends Seeder
{
    public function run(): void
    {
        $letterTypes = [
            [
                'letter_code' => 'SKBM',
                'letter_name' => 'Surat Keterangan Belum Menikah',
                'description' => 'Surat keterangan yang menyatakan bahwa seseorang belum pernah menikah',
            ],
            [
                'letter_code' => 'SKTM',
                'letter_name' => 'Surat Keterangan Tidak Mampu',
                'description' => 'Surat keterangan untuk warga tidak mampu',
            ],
            [
                'letter_code' => 'SKK',
                'letter_name' => 'Surat Keterangan Kematian',
                'description' => 'Surat keterangan kematian seseorang',
            ],
            [
                'letter_code' => 'SKKL',
                'letter_name' => 'Surat Keterangan Kelahiran',
                'description' => 'Surat keterangan kelahiran bayi',
            ],
            [
                'letter_code' => 'SKU',
                'letter_name' => 'Surat Keterangan Usaha',
                'description' => 'Surat keterangan memiliki usaha',
            ],
            [
                'letter_code' => 'SKDP',
                'letter_name' => 'Surat Keterangan Domisili Perusahaan',
                'description' => 'Surat keterangan domisili untuk perusahaan',
            ],
            [
                'letter_code' => 'SKD',
                'letter_name' => 'Surat Keterangan Domisili',
                'description' => 'Surat keterangan tempat tinggal',
            ],
            [
                'letter_code' => 'SKKP',
                'letter_name' => 'Surat Keterangan Kepemilikan Properti',
                'description' => 'Surat keterangan kepemilikan tanah/rumah',
            ],
            [
                'letter_code' => 'SKPH',
                'letter_name' => 'Surat Keterangan Penghasilan',
                'description' => 'Surat keterangan jumlah penghasilan',
            ],
            [
                'letter_code' => 'SKB',
                'letter_name' => 'Surat Keterangan Berkelakuan Baik',
                'description' => 'Surat keterangan berkelakuan baik dari lingkungan',
            ],
            [
                'letter_code' => 'SKH',
                'letter_name' => 'Surat Keterangan Harga Tanah',
                'description' => 'Surat keterangan harga tanah untuk keperluan transaksi',
            ],
            [
                'letter_code' => 'SKGG',
                'letter_name' => 'Surat Keterangan Ganti Rugi',
                'description' => 'Surat keterangan penggantian rugi',
            ],
            [
                'letter_code' => 'SKJM',
                'letter_name' => 'Surat Keterangan Janda/Mati Suami',
                'description' => 'Surat keterangan status janda karena suami meninggal',
            ],
            [
                'letter_code' => 'SKDU',
                'letter_name' => 'Surat Keterangan Duda/Mati Istri',
                'description' => 'Surat keterangan status duda karena istri meninggal',
            ],
            [
                'letter_code' => 'SKP',
                'letter_name' => 'Surat Keterangan Pindah',
                'description' => 'Surat keterangan pindah domisili',
            ],
            [
                'letter_code' => 'SKC',
                'letter_name' => 'Surat Keterangan Cerai',
                'description' => 'Surat keterangan status perceraian',
            ],
            [
                'letter_code' => 'SKKJ',
                'letter_name' => 'Surat Keterangan Kehilangan',
                'description' => 'Surat keterangan kehilangan barang/dokumen',
            ],
            [
                'letter_code' => 'SKHB',
                'letter_name' => 'Surat Keterangan Hubungan Keluarga',
                'description' => 'Surat keterangan hubungan keluarga',
            ],
            [
                'letter_code' => 'SKPP',
                'letter_name' => 'Surat Keterangan Pernikahan/Pemberkatan',
                'description' => 'Surat keterangan untuk pernikahan/pemberkatan',
            ],
            [
                'letter_code' => 'SKM',
                'letter_name' => 'Surat Keterangan Miskin',
                'description' => 'Surat keterangan keluarga miskin',
            ],
        ];

        foreach ($letterTypes as $letterType) {
            LetterType::create([
                ...$letterType,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
