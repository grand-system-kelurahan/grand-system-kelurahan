<?php

use App\Http\Controllers\PointOfInterestController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ResidentHouseController;
use Illuminate\Support\Facades\Route;

Route::apiResource('regions', RegionController::class);
Route::apiResource('points-of-interest', PointOfInterestController::class);
Route::apiResource('resident-houses', ResidentHouseController::class);
