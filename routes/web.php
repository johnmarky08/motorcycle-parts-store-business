<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
});

Route::get('/user-management', function () {
    return Inertia::render('UserManagement');
});

Route::get('/prediction', function () {
    return Inertia::render('Prediction');
});
