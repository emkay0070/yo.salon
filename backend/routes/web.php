<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Yo Salon API']);
});

Route::get('/login', function () {
    return response()->json(['message' => 'Use POST /api/v1/auth/login']);
})->name('login');
