<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PointOfInterestController;
use App\Http\Controllers\FamilyCardController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ResidentController;
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

// Sementara di luar middleware auth:sanctum biar gampang testingnya
Route::apiResource('residents', ResidentController::class);
Route::apiResource('regions', RegionController::class);
Route::apiResource('residents', ResidentController::class);
Route::apiResource('family-cards', FamilyCardController::class);
Route::post('/family-cards/{familyCard}/members', [FamilyMemberController::class, 'store']);
Route::delete('/family-cards/{familyCard}/members/{familyMember}', [FamilyMemberController::class, 'destroy']);
Route::put('/family-cards/{familyCard}/members/{familyMember}', [FamilyMemberController::class, 'update']);

// Route::get('/residents/statistics', [ResidentController::class, 'statistics']);
// Route::get('/residents/area', [ResidentController::class, 'getByArea']);
// Route::get('/residents/search', [ResidentController::class, 'search']);
// Route::post('/residents/bulk-delete', [ResidentController::class, 'bulkDelete']);

// GIS Resources
Route::apiResource('regions', RegionController::class);
Route::apiResource('points-of-interest', PointOfInterestController::class);
Route::apiResource('resident-houses', ResidentHouseController::class);
// Route::post('/residents/bulk-delete', [ResidentController::class, 'bulkDelete']);

require __DIR__ . '/api/asset_loan.php';
