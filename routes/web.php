<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\KnowledgeAnalysisController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(Auth::user()?->homePath() ?? '/login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:forgot-password')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Instructor and admin: user management (any role can be assigned)
    Route::middleware('role:instructor,admin')->group(function () {
        Route::get('/user-management', [UserManagementController::class, 'index']);
        Route::post('/user-management', [UserManagementController::class, 'store']);
        Route::put('/user-management/{user}', [UserManagementController::class, 'update']);
        Route::delete('/user-management/{user}', [UserManagementController::class, 'destroy']);
        Route::patch('/user-management/{user}/status', [UserManagementController::class, 'toggleStatus']);
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
        Route::post('/datasets/refresh', [KnowledgeAnalysisController::class, 'refreshFromOrders'])->middleware('throttle:upload');
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

    // Sales personnel: read-only transaction history
    Route::middleware('role:sales_personnel')->group(function () {
        Route::get('/sales', [SalesController::class, 'index']);
    });

    // Customers: shop, cart, checkout and their own orders
    Route::middleware('role:user')->group(function () {
        Route::get('/shop', [ShopController::class, 'index']);
        Route::post('/cart', [ShopController::class, 'addToCart']);
        Route::patch('/cart/{product}', [ShopController::class, 'updateCart']);
        Route::delete('/cart/{product}', [ShopController::class, 'removeFromCart']);
        Route::delete('/cart', [ShopController::class, 'clearCart']);
        Route::post('/checkout', [ShopController::class, 'checkout'])->middleware('throttle:20,1');
        Route::get('/orders', [OrderController::class, 'index']);
    });
});
