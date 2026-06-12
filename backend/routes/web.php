<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Login route for auth redirects (returns 401 for API requests)
Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'message' => 'Unauthorized - Please login through the application',
        'error_code' => 'UNAUTHORIZED',
    ], 401);
})->name('login');
