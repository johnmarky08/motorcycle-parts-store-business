<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/login', function () {
    return Inertia::render('Login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
});

Route::get('/user-management', function () {
    return Inertia::render('UserManagement');
});

Route::get('/inventory', function () {
    return Inertia::render('InventoryManagement');
});

Route::get('/sales', function () {
    return Inertia::render('SalesManagement');
});

Route::get('/knowledge-analysis', function () {
    return Inertia::render('KnowledgeAnalysis');
});

Route::get('/prediction', function () {
    return Inertia::render('Prediction');
});
