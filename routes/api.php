<?php

use App\Http\Controllers\ResidentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RegionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [LoginController::class, 'me']);
    Route::post('/logout', [LoginController::class, 'logout']);
});

Route::post('/register', [LoginController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);


Route::apiResource('residents', ResidentController::class);

Route::apiResource('regions', RegionController::class);

// Route::get('/residents/statistics', [ResidentController::class, 'statistics']);
// Route::get('/residents/area', [ResidentController::class, 'getByArea']);
// Route::get('/residents/search', [ResidentController::class, 'search']);
// Route::post('/residents/bulk-delete', [ResidentController::class, 'bulkDelete']);
