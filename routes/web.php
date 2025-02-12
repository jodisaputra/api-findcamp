<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\FirebaseController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/firebase', [FirebaseController::class, 'showLoginPage']);
