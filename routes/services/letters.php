<?php

use App\Http\Controllers\Api\LetterApplicationController;
use App\Http\Controllers\Api\LetterTypeController;
use Illuminate\Support\Facades\Route;

Route::get('/letter-types', [LetterTypeController::class, 'index']);
Route::middleware('auth:sanctum')->group(function () {
  Route::post('/letter-types', [LetterTypeController::class, 'store']);
  Route::get('/letter-types/{id}', [LetterTypeController::class, 'show']);
  Route::put('/letter-types/{id}', [LetterTypeController::class, 'update']);
  Route::delete('/letter-types/{id}', [LetterTypeController::class, 'destroy']);

  Route::get('/letter-applications', [LetterApplicationController::class, 'index']);
  Route::post('/letter-applications', [LetterApplicationController::class, 'store']);
  Route::get('/letter-applications/{id}', [LetterApplicationController::class, 'show']);
  Route::put('/letter-applications/{id}', [LetterApplicationController::class, 'update']);
  Route::delete('/letter-applications/{id}', [LetterApplicationController::class, 'destroy']);
  Route::post('/letter-applications/{id}/approve', [LetterApplicationController::class, 'approve']);
  Route::post('/letter-applications/{id}/reject', [LetterApplicationController::class, 'reject']);
});
