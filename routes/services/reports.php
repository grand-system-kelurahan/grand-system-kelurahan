<?php

use App\Http\Controllers\ReportWrapperController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/reports', [ReportWrapperController::class, 'getReport']);
});

Route::get('/reports/public', [ReportWrapperController::class, 'getPublicReport']);
