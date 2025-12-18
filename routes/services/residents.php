<?php

use App\Http\Controllers\FamilyCardController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\ResidentController;
use Illuminate\Support\Facades\Route;

Route::get('/residents/statistics', [ResidentController::class, 'statistics']);
Route::get('/family-cards/statistics', [FamilyCardController::class, 'statistics']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('residents', ResidentController::class);

    Route::apiResource('family-cards', FamilyCardController::class);

    Route::post('/family-cards/{familyCard}/members', [FamilyMemberController::class, 'store']);
    Route::delete('/family-cards/{familyCard}/members/{familyMember}', [FamilyMemberController::class, 'destroy']);
    Route::put('/family-cards/{familyCard}/members/{familyMember}', [FamilyMemberController::class, 'update']);
});
