<?php

use App\Http\Controllers\Api\LetterApplicationController;
use App\Http\Controllers\Api\LetterTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/letter-types', [LetterTypeController::class, 'index']);
    Route::post('/letter-types', [LetterTypeController::class, 'store']);
    Route::get('/letter-types/{id}', [LetterTypeController::class, 'show']);
    Route::put('/letter-types/{id}', [LetterTypeController::class, 'update']);
    Route::delete('/letter-types/{id}', [LetterTypeController::class, 'destroy']);

    Route::get('/letter-applications', [LetterApplicationController::class, 'index']);
    Route::post('/letter-applications', [LetterApplicationController::class, 'store']);
    Route::get('/letter-applications/{id}', [LetterApplicationController::class, 'show']);
    Route::put('/letter-applications/{id}', [LetterApplicationController::class, 'update']);
    Route::delete('/letter-applications/{id}', [LetterApplicationController::class, 'destroy']);
    Route::put('/letter-applications/{id}/approve', [LetterApplicationController::class, 'approve']);
    Route::put('/letter-applications/{id}/reject', [LetterApplicationController::class, 'reject']);
});
Route::get('/users/{id}/letter-applications', [LetterApplicationController::class, 'getLetterApplicationsByUserId']);
