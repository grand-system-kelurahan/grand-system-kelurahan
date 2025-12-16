<?php

use App\Http\Controllers\ResidentController;
use Illuminate\Support\Facades\Route;

Route::apiResource('residents', ResidentController::class);
