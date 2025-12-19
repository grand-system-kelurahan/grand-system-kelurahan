<?php

use App\Http\Controllers\FamilyCardController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\ResidentController;
use App\Http\Controllers\ResidentVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/residents/statistics', [ResidentController::class, 'statistics']);
Route::get('/family-cards/statistics', [FamilyCardController::class, 'statistics']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('residents', ResidentController::class);

    Route::apiResource('family-cards', FamilyCardController::class);

    Route::post('/family-cards/{familyCard}/members', [FamilyMemberController::class, 'store']);
    Route::delete('/family-cards/{familyCard}/members/{familyMember}', [FamilyMemberController::class, 'destroy']);
    Route::put('/family-cards/{familyCard}/members/{familyMember}', [FamilyMemberController::class, 'update']);

    Route::prefix('resident-verifications')->group(function () {
        Route::get('/', [ResidentVerificationController::class, 'index']);
        Route::get('/statistics', [ResidentVerificationController::class, 'statistics']);
        Route::get('/{id}', [ResidentVerificationController::class, 'show']);
        Route::post('/', [ResidentVerificationController::class, 'store']);
        Route::post('/bulk', [ResidentVerificationController::class, 'bulkCreate']);
        Route::put('/{id}/approve', [ResidentVerificationController::class, 'approve']);
        Route::put('/{id}/reject', [ResidentVerificationController::class, 'reject']);
    });
});
