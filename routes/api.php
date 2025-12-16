<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PointOfInterestController;
use App\Http\Controllers\ResidentHouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [LoginController::class, 'me']);
    Route::post('/logout', [LoginController::class, 'logout']);
});

Route::post('/register', [LoginController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Masukin route ke sini kalau udah ditest
});


Route::apiResource('points-of-interest', PointOfInterestController::class);
Route::apiResource('resident-houses', ResidentHouseController::class);

require __DIR__ . '/api/regions.php';
require __DIR__ . '/api/asset_loan.php';
require __DIR__ . '/api/residents.php';
require __DIR__ . '/api/family_cards.php';
