<?php

use App\Http\Controllers\ResidentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\Api\LetterApplicationController;
use App\Http\Controllers\Api\LetterTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Data Master Letter Type
    Route::get('/letter-types', [LetterTypeController::class, 'index']);
    Route::post('/letter-types', [LetterTypeController::class, 'store']);
    Route::get('/letter-types/{id}', [LetterTypeController::class, 'show']);
    Route::put('/letter-types/{id}', [LetterTypeController::class, 'update']);
    Route::delete('/letter-types/{id}', [LetterTypeController::class, 'destroy']);

    // Letter Applications
    Route::get('/letter-applications', [LetterApplicationController::class, 'index']);
    Route::post('/letter-applications', [LetterApplicationController::class, 'store']);
    Route::get('/letter-applications/{id}', [LetterApplicationController::class, 'show']);
    Route::put('/letter-applications/{id}', [LetterApplicationController::class, 'update']);
    Route::delete('/letter-applications/{id}', [LetterApplicationController::class, 'destroy']);
    Route::post('/letter-applications/{id}/approve', [LetterApplicationController::class, 'approve']);
    Route::post('/letter-applications/{id}/reject', [LetterApplicationController::class, 'reject']);
});

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
