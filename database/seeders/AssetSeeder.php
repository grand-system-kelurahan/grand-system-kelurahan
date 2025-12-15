<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $assets = [
            [
                'asset_code' => 'AST-LAP-001',
                'asset_name' => 'Laptop',
                'description' => 'Laptop operasional kelurahan',
                'asset_type' => Asset::TYPE_ITEM,
                'total_stock' => 5,
                'available_stock' => 5,
                'location' => 'Ruang Administrasi',
            ],
            [
                'asset_code' => 'AST-PC-001',
                'asset_name' => 'PC Desktop',
                'description' => 'Komputer pelayanan administrasi',
                'asset_type' => Asset::TYPE_ITEM,
                'total_stock' => 3,
                'available_stock' => 3,
                'location' => 'Ruang Pelayanan',
            ],
            [
                'asset_code' => 'AST-PRN-001',
                'asset_name' => 'Printer',
                'description' => 'Printer dokumen pelayanan',
                'asset_type' => Asset::TYPE_ITEM,
                'total_stock' => 2,
                'available_stock' => 2,
                'location' => 'Ruang Pelayanan',
            ],
            [
                'asset_code' => 'AST-SCN-001',
                'asset_name' => 'Scanner',
                'description' => 'Scanner arsip dokumen',
                'asset_type' => Asset::TYPE_ITEM,
                'total_stock' => 1,
                'available_stock' => 1,
                'location' => 'Ruang Arsip',
            ],
            [
                'asset_code' => 'AST-PRO-001',
                'asset_name' => 'Proyektor',
                'description' => 'Proyektor rapat kelurahan',
                'asset_type' => Asset::TYPE_ITEM,
                'total_stock' => 1,
                'available_stock' => 1,
                'location' => 'Ruang Rapat',
            ],
            [
                'asset_code' => 'AST-SND-001',
                'asset_name' => 'Sound System',
                'description' => 'Sound system kegiatan dan rapat',
                'asset_type' => Asset::TYPE_ITEM,
                'total_stock' => 1,
                'available_stock' => 1,
                'location' => 'Gudang',
            ],
            [
                'asset_code' => 'AST-KRP-001',
                'asset_name' => 'Kursi Plastik',
                'description' => 'Kursi plastik kegiatan warga',
                'asset_type' => Asset::TYPE_ITEM,
                'total_stock' => 50,
                'available_stock' => 50,
                'location' => 'Gudang',
            ],
            [
                'asset_code' => 'AST-TND-001',
                'asset_name' => 'Tenda Kegiatan',
                'description' => 'Tenda kegiatan kelurahan',
                'asset_type' => Asset::TYPE_ITEM,
                'total_stock' => 3,
                'available_stock' => 3,
                'location' => 'Gudang',
            ],
            [
                'asset_code' => 'AST-AUL-001',
                'asset_name' => 'Aula Kelurahan',
                'description' => 'Aula kelurahan untuk rapat dan acara resmi',
                'asset_type' => Asset::TYPE_ROOM,
                'total_stock' => 1,
                'available_stock' => 1,
                'location' => 'Kantor Kelurahan',
            ],
            [
                'asset_code' => 'AST-BNJ-001',
                'asset_name' => 'Balai Banjar',
                'description' => 'Balai banjar untuk kegiatan masyarakat',
                'asset_type' => Asset::TYPE_ROOM,
                'total_stock' => 1,
                'available_stock' => 1,
                'location' => 'Wilayah Banjar',
            ],
            [
                'asset_code' => 'AST-LPG-001',
                'asset_name' => 'Lapangan',
                'description' => 'Lapangan serbaguna kegiatan warga',
                'asset_type' => Asset::TYPE_ROOM,
                'total_stock' => 1,
                'available_stock' => 1,
                'location' => 'Area Kelurahan',
            ],
        ];

        foreach ($assets as $asset) {
            Asset::create([
                ...$asset,
                'asset_status' => Asset::STATUS_ACTIVE,
            ]);
        }
    }
}
