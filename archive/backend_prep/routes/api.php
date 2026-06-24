<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\AdminCustomerController;
use App\Http\Controllers\Api\Admin\AdminReportController;

/*
|----------------------------------------------------------------------
| Public Routes — No authentication required
|----------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
});

// Products (public read)
Route::get('products',             [ProductController::class, 'index']);
Route::get('products/{id}',        [ProductController::class, 'show']);
Route::get('products/{id}/reviews',[ReviewController::class, 'index']);
Route::get('categories',           [CategoryController::class, 'index']);

/*
|----------------------------------------------------------------------
| Authenticated Routes — Requires Sanctum token
|----------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',      [AuthController::class, 'me']);

    // Cart
    Route::get   ('cart',                    [CartController::class, 'index']);
    Route::post  ('cart/items',              [CartController::class, 'addItem']);
    Route::put   ('cart/items/{variantId}',  [CartController::class, 'updateItem']);
    Route::delete('cart/items/{variantId}',  [CartController::class, 'removeItem']);
    Route::delete('cart',                    [CartController::class, 'clear']);

    // Orders
    Route::get ('orders',     [OrderController::class, 'index']);
    Route::get ('orders/{id}',[OrderController::class, 'show']);
    Route::post('orders',     [OrderController::class, 'store']);

    // Wishlist
    Route::get   ('wishlist',           [WishlistController::class, 'index']);
    Route::post  ('wishlist/{id}',      [WishlistController::class, 'toggle']);

    // Reviews (post requires auth)
    Route::post('reviews', [ReviewController::class, 'store']);

    /*
    |------------------------------------------------------------------
    | Admin Routes — Requires admin role
    |------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Products
        Route::get   ('products',                [AdminProductController::class, 'index']);
        Route::post  ('products',                [AdminProductController::class, 'store']);
        Route::put   ('products/{id}',           [AdminProductController::class, 'update']);
        Route::delete('products/{id}',           [AdminProductController::class, 'destroy']);
        Route::put   ('variants/{id}',           [AdminProductController::class, 'updateVariant']);

        // Orders
        Route::get('orders',                     [AdminOrderController::class, 'index']);
        Route::put('orders/{id}/status',         [AdminOrderController::class, 'updateStatus']);

        // Customers
        Route::get('customers',                  [AdminCustomerController::class, 'index']);
        Route::put('customers/{id}/status',      [AdminCustomerController::class, 'toggleStatus']);

        // Reports
        Route::get('reports/summary',            [AdminReportController::class, 'summary']);
        Route::get('reports/revenue',            [AdminReportController::class, 'revenue']);
        Route::get('reports/inventory',          [AdminReportController::class, 'inventory']);
    });
});
