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
use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ReturnRequestController;
use App\Http\Controllers\Api\Admin\AdminPromotionController;
use App\Http\Controllers\Api\Admin\AdminReviewController;
use App\Http\Controllers\Api\Admin\AdminAnnouncementController;
use App\Http\Controllers\Api\Admin\AdminAttributeController;
use App\Http\Controllers\Api\Admin\AdminInventoryController;
use App\Http\Controllers\Api\Admin\AdminReturnRequestController;
use App\Http\Controllers\Api\Admin\AdminStaffController;
use App\Http\Controllers\Api\ShippingPaymentController;
use App\Http\Controllers\Api\Admin\AdminPaymentShippingSettingController;
use App\Support\UserRole;

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
Route::get('announcements',        [AnnouncementController::class, 'index']);

/*
|----------------------------------------------------------------------
| Authenticated Routes — Requires Sanctum token
|----------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me',      [AuthController::class, 'me']);
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);

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
    Route::put ('orders/{id}/bank-transfer-paid', [OrderController::class, 'markBankTransferPaid']);
    Route::post('shipping/calculate', [ShippingPaymentController::class, 'calculate']);
    Route::get ('payment/bank-info', [ShippingPaymentController::class, 'bankInfo']);
    Route::get ('address/provinces', [ShippingPaymentController::class, 'provinces']);
    Route::get ('address/districts', [ShippingPaymentController::class, 'districts']);
    Route::get ('address/wards', [ShippingPaymentController::class, 'wards']);

    // Wishlist
    Route::get   ('wishlist',           [WishlistController::class, 'index']);
    Route::post  ('wishlist/{id}',      [WishlistController::class, 'toggle']);
    Route::get   ('wishlist/{id}/status',[WishlistController::class, 'status']);
    Route::post  ('promotions/validate',[PromotionController::class, 'validateCode']);

    // Reviews (post requires auth)
    Route::post('reviews', [ReviewController::class, 'store']);
    Route::put('reviews/{id}', [ReviewController::class, 'update']);
    Route::get('reviews/mine', [ReviewController::class, 'mine']);
    Route::get('reviews/eligible', [ReviewController::class, 'eligible']);
    Route::post('reviews/images', [ReviewController::class, 'uploadImages']);

    Route::get('returns', [ReturnRequestController::class, 'index']);
    Route::post('returns', [ReturnRequestController::class, 'store']);
    Route::post('returns/images', [ReturnRequestController::class, 'uploadImages']);
    Route::get('returns/{id}', [ReturnRequestController::class, 'show']);
    Route::put('returns/{id}/cancel', [ReturnRequestController::class, 'cancel']);

    /*
    |------------------------------------------------------------------
    | Admin Routes — Requires admin role
    |------------------------------------------------------------------
    */
    Route::middleware('role:'.UserRole::STAFF.','.UserRole::ADMIN)->prefix('admin')->group(function () {
        // Products
        Route::get   ('products',                [AdminProductController::class, 'index']);
        Route::get   ('products/options',        [AdminProductController::class, 'options']);
        Route::get   ('products/{id}',           [AdminProductController::class, 'show']);
        Route::put   ('products/{id}/hide',      [AdminProductController::class, 'hide']);
        Route::put   ('variants/{id}',           [AdminProductController::class, 'updateVariant']);

        Route::get('reviews', [AdminReviewController::class, 'index']);
        Route::put('reviews/{id}/status', [AdminReviewController::class, 'moderate']);

        Route::get('returns', [AdminReturnRequestController::class, 'index']);
        Route::get('returns/{id}', [AdminReturnRequestController::class, 'show']);
        Route::put('returns/{id}/status', [AdminReturnRequestController::class, 'updateStatus']);
        Route::put('returns/{id}/refund', [AdminReturnRequestController::class, 'updateRefund']);

        Route::get ('inventory/variants',  [AdminInventoryController::class, 'variants']);
        Route::get ('inventory/receipts',  [AdminInventoryController::class, 'receipts']);
        Route::post('inventory/receipts',  [AdminInventoryController::class, 'storeReceipt']);
        Route::get ('inventory/receipts/{id}', [AdminInventoryController::class, 'showReceipt']);
        Route::get ('inventory/movements', [AdminInventoryController::class, 'movements']);
        Route::post('inventory/adjust',    [AdminInventoryController::class, 'adjust']);
        Route::get ('inventory/alerts',    [AdminInventoryController::class, 'alerts']);

        // Orders
        Route::get('orders',                     [AdminOrderController::class, 'index']);
        Route::put('orders/{id}/status',         [AdminOrderController::class, 'updateStatus']);
        Route::put('orders/{id}/payment-status', [AdminOrderController::class, 'updatePaymentStatus']);
        Route::middleware('role:'.UserRole::ADMIN)->group(function () {
            Route::post  ('products/images',         [AdminProductController::class, 'uploadImages']);
            Route::post  ('products',                [AdminProductController::class, 'store']);
            Route::put   ('products/{id}',           [AdminProductController::class, 'update']);
            Route::delete('products/{id}',           [AdminProductController::class, 'destroy']);

            Route::get   ('categories',              [AdminCategoryController::class, 'index']);
            Route::post  ('categories',              [AdminCategoryController::class, 'store']);
            Route::put   ('categories/{id}',         [AdminCategoryController::class, 'update']);
            Route::delete('categories/{id}',         [AdminCategoryController::class, 'destroy']);

            Route::get   ('attributes',                  [AdminAttributeController::class, 'index']);
            Route::post  ('attributes',                  [AdminAttributeController::class, 'store']);
            Route::get   ('attributes/{id}',             [AdminAttributeController::class, 'show']);
            Route::put   ('attributes/{id}',             [AdminAttributeController::class, 'update']);
            Route::delete('attributes/{id}',             [AdminAttributeController::class, 'destroy']);
            Route::post  ('attributes/{id}/values',      [AdminAttributeController::class, 'storeValue']);
            Route::put   ('attributes/{id}/values/{valueId}', [AdminAttributeController::class, 'updateValue']);
            Route::delete('attributes/{id}/values/{valueId}', [AdminAttributeController::class, 'destroyValue']);

            Route::put('inventory/receipts/{id}/approve', [AdminInventoryController::class, 'approveReceipt']);
            Route::put('inventory/receipts/{id}/reject', [AdminInventoryController::class, 'rejectReceipt']);

            Route::apiResource('promotions', AdminPromotionController::class)->except(['show']);
            Route::put('reviews/{id}/reply', [AdminReviewController::class, 'reply']);
            Route::delete('reviews/{id}/reply', [AdminReviewController::class, 'deleteReply']);
            Route::delete('reviews/{id}', [AdminReviewController::class, 'destroy']);
            Route::apiResource('announcements', AdminAnnouncementController::class)->except(['show']);
            Route::post('announcements/images', [AdminAnnouncementController::class, 'uploadImages']);
            Route::delete('announcements/images/uploaded', [AdminAnnouncementController::class, 'deleteUploadedImage']);

            Route::get('payment-shipping-settings',  [AdminPaymentShippingSettingController::class, 'show']);
            Route::put('payment-shipping-settings',  [AdminPaymentShippingSettingController::class, 'update']);

            Route::get('customers',                  [AdminCustomerController::class, 'index']);
            Route::put('customers/{id}/status',      [AdminCustomerController::class, 'toggleStatus']);

            Route::get('staff',                      [AdminStaffController::class, 'index']);
            Route::post('staff',                     [AdminStaffController::class, 'store']);
            Route::put('staff/{id}',                 [AdminStaffController::class, 'update']);
            Route::put('staff/{id}/status',          [AdminStaffController::class, 'toggleStatus']);

            Route::get('reports/summary',            [AdminReportController::class, 'summary']);
            Route::get('reports/revenue',            [AdminReportController::class, 'revenue']);
            Route::get('reports/inventory',          [AdminReportController::class, 'inventory']);
        });
    });
});
