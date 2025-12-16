<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetLoanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('assets')->group(function () {
        Route::get('/report', [AssetController::class, 'report']);
        Route::get('/', [AssetController::class, 'index']);
        Route::get('/{id}', [AssetController::class, 'show']);

        Route::middleware('role:admin,pegawai')->group(function () {
            Route::post('/', [AssetController::class, 'store']);
            Route::put('/{id}', [AssetController::class, 'update']);
            Route::delete('/{id}', [AssetController::class, 'destroy']);
        });
    });

    Route::prefix('asset-loans')->group(function () {
        Route::get('/report', [AssetLoanController::class, 'report']);
        Route::get('/', [AssetLoanController::class, 'index']);
        Route::get('/{id}', [AssetLoanController::class, 'show']);
        Route::post('/', [AssetLoanController::class, 'store']);

        Route::middleware('role:admin,pegawai')->group(function () {
            Route::post('/{id}/approve', [AssetLoanController::class, 'approve']);
            Route::post('/{id}/return', [AssetLoanController::class, 'returnAsset']);
            Route::post('/{id}/reject', [AssetLoanController::class, 'reject']);
        });
    });
});
