<?php

use App\Http\Controllers\API\v1\AuthController;
use App\Http\Controllers\API\v1\AdminStatsController;
use App\Http\Controllers\API\v1\AdminUserController;
use App\Http\Controllers\API\v1\OwnerStatsController;
use App\Http\Controllers\API\v1\MallController;
use App\Http\Controllers\API\v1\ProductController;
use App\Http\Controllers\API\v1\OrderController;
use App\Http\Controllers\API\v1\CategoryController;
use App\Http\Controllers\API\v1\MallThemeController;
use App\Http\Controllers\API\v1\SearchController;
use App\Http\Controllers\API\v1\ProductImportController;
use App\Http\Controllers\API\v1\SubscriptionController;
use App\Http\Controllers\API\v1\SubscriptionPlanController;
use App\Http\Controllers\API\v1\ShelfController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/malls', [MallController::class, 'index']);
    Route::get('/malls/slug/{slug}', [MallController::class, 'showBySlug'])->middleware('mall.theme'); // distinct public QR view
    Route::get('/malls/{id}/products', [ProductController::class, 'getMallProducts'])->middleware('mall.theme'); // contextual viewing
    Route::get('/malls/{id}/shelves', [ShelfController::class, 'getMallShelves']);
    Route::get('/malls/{id}', [MallController::class, 'show'])->middleware('mall.theme');
    Route::get('/search', [SearchController::class, 'search'])->middleware('mall.theme');
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    // Making scanner route public so users can scan without login
    Route::post('/scanner', [ProductController::class, 'scanner']);
    // QR scan handler - handles both mall and product QR codes
    Route::post('/scan', [MallController::class, 'handleScan']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // Admin routes
        Route::middleware('role:super-admin|admin')->prefix('admin')->group(function () {
            Route::get('/stats', [AdminStatsController::class, 'index']);
            Route::get('/pending-malls', [AdminStatsController::class, 'pendingMalls']);
            Route::get('/malls', [MallController::class, 'adminIndex']);
            Route::post('/malls', [MallController::class, 'adminStore']);
            Route::get('/malls/{id}/theme', [MallThemeController::class, 'show'])->middleware('mall.theme');
            Route::post('/malls/{id}/theme', [MallThemeController::class, 'store'])->middleware('mall.theme');
            Route::put('/malls/{id}/theme', [MallThemeController::class, 'update'])->middleware('mall.theme');
            Route::post('/malls/{id}/generate-qr', [MallController::class, 'generateQR']);
            Route::apiResource('/users', AdminUserController::class);
            Route::get('/product-imports', [ProductImportController::class, 'index']);
            Route::get('/product-imports/template', [ProductImportController::class, 'template']);
            Route::post('/product-imports/preview', [ProductImportController::class, 'preview']);
            Route::get('/product-imports/{id}', [ProductImportController::class, 'show']);
            Route::get('/product-imports/{id}/report', [ProductImportController::class, 'downloadReport']);
            Route::post('/product-imports/{id}/start', [ProductImportController::class, 'start']);
            Route::delete('/product-imports/{id}', [ProductImportController::class, 'destroy']);

            // Subscription Management
            Route::apiResource('/subscription-plans', SubscriptionPlanController::class);
            Route::get('/mall-subscriptions', [SubscriptionController::class, 'adminIndex']);
            Route::post('/mall-subscriptions', [SubscriptionController::class, 'adminSubscribe']);
        });

        // Mall Owner routes
        Route::middleware('role:mall-owner')->prefix('owner')->group(function () {
            Route::get('/stats', [OwnerStatsController::class, 'index']);
            Route::get('/recent-products', [OwnerStatsController::class, 'recentProducts']);
            Route::get('/my-malls', [MallController::class, 'myMalls']);
            Route::put('/malls/{id}', [MallController::class, 'update']);
            Route::post('/malls/{id}/generate-qr', [MallController::class, 'generateQR']);
            Route::get('/categories', [CategoryController::class, 'ownerCategories']);
            Route::post('/malls', [MallController::class, 'store']);
            Route::get('/products', [ProductController::class, 'ownerProducts']);
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/delete-all', [ProductController::class, 'destroyAll']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);

            Route::get('/product-imports', [ProductImportController::class, 'index']);
            Route::get('/product-imports/template', [ProductImportController::class, 'template']);
            Route::post('/product-imports/preview', [ProductImportController::class, 'preview']);
            Route::get('/product-imports/{id}', [ProductImportController::class, 'show']);
            Route::get('/product-imports/{id}/report', [ProductImportController::class, 'downloadReport']);
            Route::post('/product-imports/{id}/start', [ProductImportController::class, 'start']);
            Route::delete('/product-imports/{id}', [ProductImportController::class, 'destroy']);

            // Subscription
            Route::get('/subscription-plans', [SubscriptionController::class, 'index']);
            Route::get('/subscription/current', [SubscriptionController::class, 'current']);
            Route::post('/subscribe', [SubscriptionController::class, 'subscribe']);

            // Shelves (Map)
            Route::get('/shelves', [ShelfController::class, 'index']);
            Route::post('/shelves', [ShelfController::class, 'store']);
            Route::put('/shelves/{id}', [ShelfController::class, 'update']);
            Route::delete('/shelves/{id}', [ShelfController::class, 'destroy']);
        });

        // Shared Protected routes
        // (Scanner moved to public above)
        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store']);
    });
});

