<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\KnowledgeAnalysisController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? '/dashboard' : '/login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:forgot-password')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Admin: users and registration approvals
    Route::middleware('role:admin')->group(function () {
        Route::get('/user-management', [UserManagementController::class, 'index']);
        Route::post('/user-management', [UserManagementController::class, 'store']);
        Route::put('/user-management/{user}', [UserManagementController::class, 'update']);
        Route::delete('/user-management/{user}', [UserManagementController::class, 'destroy']);
        Route::patch('/user-management/{user}/status', [UserManagementController::class, 'toggleStatus']);
        Route::patch('/user-management/{user}/approve', [UserManagementController::class, 'approve']);
        Route::patch('/user-management/{user}/reject', [UserManagementController::class, 'reject']);
    });

    // Admin + instructor: prediction inputs and outputs
    Route::middleware('role:admin,instructor')->prefix('prediction')->group(function () {
        Route::get('/', [PredictionController::class, 'index']);
        Route::post('/run', [PredictionController::class, 'run'])->middleware('throttle:predict');
    });

    // Instructor: datasets, training and evaluation metrics
    Route::middleware('role:instructor')->prefix('knowledge-analysis')->group(function () {
        Route::get('/', [KnowledgeAnalysisController::class, 'index']);
        Route::post('/train', [KnowledgeAnalysisController::class, 'train'])->middleware('throttle:ml-train');
        Route::post('/datasets', [KnowledgeAnalysisController::class, 'upload'])->middleware('throttle:upload');
        Route::delete('/datasets/{dataset}', [KnowledgeAnalysisController::class, 'destroyDataset']);
        Route::post('/models/{mlModel}/activate', [KnowledgeAnalysisController::class, 'activate']);
    });

    // Inventory clerk
    Route::middleware('role:inventory_clerk')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::post('/inventory', [InventoryController::class, 'store']);
        Route::post('/inventory/categories', [InventoryController::class, 'storeCategory']);
        Route::put('/inventory/{product}', [InventoryController::class, 'update']);
        Route::delete('/inventory/{product}', [InventoryController::class, 'destroy']);
        Route::post('/inventory/{product}/stock', [InventoryController::class, 'adjustStock']);
        Route::get('/inventory/{product}/movements', [InventoryController::class, 'movements']);
    });

    // Sales personnel
    Route::middleware('role:sales_personnel')->group(function () {
        Route::get('/sales', [SalesController::class, 'index']);
        Route::get('/sales/{sale}', [SalesController::class, 'show']);
        Route::post('/sales', [SalesController::class, 'store']);
    });
});
