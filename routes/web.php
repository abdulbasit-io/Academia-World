<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Email verification route
Route::get('/email/verify/{token}', function ($token) {
    return redirect(config('app.frontend_url', 'http://localhost:3000') . '/verify-email?token=' . $token);
})->name('email.verify');
