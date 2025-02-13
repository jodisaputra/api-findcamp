<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\API\RegionController;
use App\Http\Controllers\API\CountryController;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

Route::apiResource('regions', RegionController::class);
Route::apiResource('countries', CountryController::class);

// Protected routes
Route::middleware(['firebase.auth'])->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/user/update', [AuthController::class, 'updateProfile']);
    Route::post('/user/update-image', [AuthController::class, 'updateProfileImage']);
});
