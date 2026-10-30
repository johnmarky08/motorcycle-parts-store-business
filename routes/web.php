<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect(auth()->check() ? '/dashboard' : '/login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:admin')->group(function () {
        Route::get('/user-management', [UserManagementController::class, 'index']);
        Route::get('/prediction', fn () => Inertia::render('Prediction'));
        Route::post('/user-management', [UserManagementController::class, 'store']);
        Route::put('/user-management/{user}', [UserManagementController::class, 'update']);
        Route::delete('/user-management/{user}', [UserManagementController::class, 'destroy']);
        Route::patch('/user-management/{user}/status', [UserManagementController::class, 'toggleStatus']);
        Route::patch('/user-management/{user}/approve', [UserManagementController::class, 'approve']);
        Route::patch('/user-management/{user}/reject', [UserManagementController::class, 'reject']);
    });

    Route::middleware('role:admin,inventory_clerk')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::post('/inventory', [InventoryController::class, 'store']);
        Route::post('/inventory/categories', [InventoryController::class, 'storeCategory']);
        Route::put('/inventory/{product}', [InventoryController::class, 'update']);
        Route::delete('/inventory/{product}', [InventoryController::class, 'destroy']);
        Route::post('/inventory/{product}/stock', [InventoryController::class, 'adjustStock']);
        Route::get('/inventory/{product}/movements', [InventoryController::class, 'movements']);
    });

    Route::middleware('role:admin,sales_personnel')->group(function () {
        Route::get('/sales', [SalesController::class, 'index']);
        Route::get('/sales/{sale}', [SalesController::class, 'show']);
        Route::post('/sales', [SalesController::class, 'store']);
    });

    Route::middleware('role:admin,instructor')->group(function () {
        Route::get('/analysis/datasets', [AnalysisController::class, 'datasets']);
        Route::get('/analysis/models', [AnalysisController::class, 'models']);
        Route::get('/analysis/business-features', [AnalysisController::class, 'businessFeatures']);
        Route::get('/analysis/insights', [AnalysisController::class, 'insights']);
        Route::post('/analysis/train', [AnalysisController::class, 'train']);
        Route::post('/analysis/predict', [AnalysisController::class, 'predict']);
        Route::get('/knowledge-analysis', fn () => Inertia::render('KnowledgeAnalysis'));
    });
});
