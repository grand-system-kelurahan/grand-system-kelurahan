<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetLoan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssetLoanSeeder extends Seeder
{
    public function run(): void
    {
        $assets = Asset::all();

        DB::transaction(function () use ($assets) {

            foreach ($assets as $asset) {

                // REQUESTED (belum mempengaruhi stok)
                AssetLoan::create([
                    'asset_id' => $asset->id,
                    'resident_id' => rand(1, 5),
                    'quantity' => 1,
                    'loan_date' => Carbon::now()->subDays(2)->toDateString(),
                    'planned_return_date' => Carbon::now()->addDays(3)->toDateString(),
                    'loan_status' => AssetLoan::STATUS_REQUESTED,
                    'loan_reason' => 'Pengajuan kegiatan warga',
                ]);

                // BORROWED (stok dikurangi)
                if ($asset->available_stock > 0) {
                    $borrowQty = min(1, $asset->available_stock);

                    AssetLoan::create([
                        'asset_id' => $asset->id,
                        'resident_id' => rand(1, 5),
                        'quantity' => $borrowQty,
                        'loan_date' => Carbon::now()->subDays(5)->toDateString(),
                        'planned_return_date' => Carbon::now()->addDays(2)->toDateString(),
                        'loan_status' => AssetLoan::STATUS_BORROWED,
                        'loan_reason' => 'Dipinjam untuk keperluan operasional',
                    ]);

                    $asset->decrement('available_stock', $borrowQty);
                }

                // RETURNED (stok dikembalikan)
                AssetLoan::create([
                    'asset_id' => $asset->id,
                    'resident_id' => rand(1, 5),
                    'quantity' => 1,
                    'loan_date' => Carbon::now()->subDays(10)->toDateString(),
                    'planned_return_date' => Carbon::now()->subDays(5)->toDateString(),
                    'actual_return_date' => Carbon::now()->subDays(4)->toDateString(),
                    'loan_status' => AssetLoan::STATUS_RETURNED,
                    'loan_reason' => 'Selesai digunakan',
                ]);

                // stok kembali
                $asset->increment('available_stock', 1);

                // REJECTED
                AssetLoan::create([
                    'asset_id' => $asset->id,
                    'resident_id' => rand(1, 5),
                    'quantity' => 1,
                    'loan_date' => Carbon::now()->subDays(1)->toDateString(),
                    'planned_return_date' => Carbon::now()->addDays(1)->toDateString(),
                    'loan_status' => AssetLoan::STATUS_REJECTED,
                    'loan_reason' => 'Pinjam untuk kegiatan warga',
                    'rejected_reason' => 'Waktu peminjaman bentrok dengan kegiatan lain',
                ]);

                // BORROWED TAMBAHAN (simulasi peminjaman aktif lebih dari satu)
                $additionalBorrowCount = rand(0, 2); // 0–2 peminjaman tambahan

                for ($i = 0; $i < $additionalBorrowCount; $i++) {
                    if ($asset->available_stock <= 0) {
                        break;
                    }

                    $borrowQty = rand(1, min(2, $asset->available_stock));

                    AssetLoan::create([
                        'asset_id' => $asset->id,
                        'resident_id' => rand(1, 5),
                        'quantity' => $borrowQty,
                        'loan_date' => Carbon::now()->subDays(rand(3, 7))->toDateString(),
                        'planned_return_date' => Carbon::now()->addDays(rand(1, 5))->toDateString(),
                        'loan_status' => AssetLoan::STATUS_BORROWED,
                        'loan_reason' => 'Dipinjam untuk kegiatan operasional tambahan',
                    ]);

                    // kurangi stok
                    $asset->decrement('available_stock', $borrowQty);
                }
            }
        });
    }
}
