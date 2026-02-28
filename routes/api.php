<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\LogController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no auth needed)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Test route - used to verify server is live
Route::get('/ping', function() {
    return response()->json(['success' => true, 'message' => 'pong']);
});

// Protected routes (require Bearer token)
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);

    // Products & Inventory
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    // Admin-only product management
    Route::middleware('role:admin')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::post('/products/{product}/restock', [ProductController::class, 'adjustStock']);
    });

    // Sales
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales', [SaleController::class, 'store']);
    Route::get('/sales/summary', [SaleController::class, 'summary']);
    Route::get('/sales/{sale}', [SaleController::class, 'show']);

    // Void sale - admin only
    Route::middleware('role:admin')->group(function () {
        Route::post('/sales/{sale}/void', [SaleController::class, 'void']);
    });

    // System logs - admin only
    Route::middleware('role:admin')->group(function () {
        Route::get('/logs', [LogController::class, 'index']);
        Route::get('/logs/export', [LogController::class, 'export']);
    });
});