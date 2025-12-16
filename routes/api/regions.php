<?php

use App\Http\Controllers\RegionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('regions', RegionController::class);
