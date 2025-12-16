<?php

use App\Http\Controllers\FamilyCardController;
use App\Http\Controllers\FamilyMemberController;
use App\Http\Controllers\ResidentController;
use Illuminate\Support\Facades\Route;

Route::apiResource('residents', ResidentController::class);
Route::apiResource('family-cards', FamilyCardController::class);

Route::post('/family-cards/{familyCard}/members', [FamilyMemberController::class, 'store']);
Route::delete('/family-cards/{familyCard}/members/{familyMember}', [FamilyMemberController::class, 'destroy']);
Route::put('/family-cards/{familyCard}/members/{familyMember}', [FamilyMemberController::class, 'update']);
