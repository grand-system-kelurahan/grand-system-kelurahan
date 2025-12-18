<?php

use App\Http\Controllers\FamilyCardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
