<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// استيراد المتحكمات (Controllers)
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ApartmentController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\LandlordController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\UserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- 1. المسارات العامة (بدون تسجيل دخول) ---

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// مسارات الشقق للزوار
Route::get('apartments', [ApartmentController::class, 'index']);
Route::get('apartments/{id}', [ApartmentController::class, 'show']);


// --- 2. المسارات المحمية (تتطلب JWT Auth) ---

Route::middleware("auth:api")->group(function () {

    // أ) الملف الشخصي والتحكم بالحساب
    Route::get('user/profile', [AuthController::class, 'profile']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::put('user/profile', [AuthController::class, 'updateProfile']);
    Route::put('user/password', [AuthController::class, 'changePassword']);

    // ب) مسارات المستخدم (نظام الاشتراكات والمدفوعات)
    Route::prefix("user")->group(function () {
        Route::get("packages", [UserController::class, "indexPackages"]);
        Route::get("packages/{package_id}", [UserController::class, "showPackage"]);
        Route::post("subscribe", [UserController::class, "subscribeToPackage"]);
        Route::post("payments/{payment_id}/upload-receipt", [UserController::class, "uploadPaymentReceipt"]);
        Route::get("subscription/status", [UserController::class, "getSubscriptionStatus"]);
        Route::get("subscriptions", [UserController::class, "indexMySubscriptions"]);
        Route::get("payments", [UserController::class, "indexMyPayments"]);
        Route::get("payments/{payment_id}", [UserController::class, "showMyPayment"]);
    });

    // ج) مسارات المالك (Landlord) والشقق
    Route::prefix('landlord')->group(function () {
        Route::get('apartments', [LandlordController::class, 'landlordApartments']);
        Route::post('verify', [LandlordController::class, 'uploadVerificationDocument']);
        Route::get('stats', [LandlordController::class, 'stats']);
    });

    // عمليات الشقق للملاك الموثقين
    Route::middleware('verified.landlord')->group(function () {
        Route::post('apartments', [ApartmentController::class, 'store']);
        Route::post('apartments/{id}', [ApartmentController::class, 'update']);
        Route::delete('apartments/{id}', [ApartmentController::class, 'destroy']);
    });

    Route::post('apartments/{id}/phone-click', [ApartmentController::class, 'recordPhoneClick']);

    // د) المفضلة (Favorite)
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{apartment_id}', [FavoriteController::class, 'toggle']);

    // هـ) مسارات الأدمن (Admin APIs)
    Route::prefix("admin")->middleware("admin.check")->group(function () {
        
        // إدارة الأدمن
        Route::get("admins", [AdminController::class, "indexAdmins"]);
        Route::post("admins", [AdminController::class, "storeAdmin"]);
        Route::put("admins/{admin_id}", [AdminController::class, "updateAdmin"]);
        Route::delete("admins/{admin_id}", [AdminController::class, "destroyAdmin"]);

        // إدارة الباقات
        Route::get("packages", [AdminController::class, "indexPackages"]);
        Route::post("packages", [AdminController::class, "storePackage"]);
        Route::put("packages/{package_id}", [AdminController::class, "updatePackage"]);
        Route::delete("packages/{package_id}", [AdminController::class, "destroyPackage"]);

        // إدارة المدفوعات والاشتراكات
        Route::get("payments/pending", [AdminController::class, "indexPendingPayments"]);
        Route::get("payments/{payment_id}", [AdminController::class, "showPayment"]);
        Route::put("payments/{payment_id}/approve", [AdminController::class, "approvePayment"]);
        Route::put("payments/{payment_id}/reject", [AdminController::class, "rejectPayment"]);
        Route::get("subscriptions", [AdminController::class, "indexSubscriptions"]);
        Route::put("subscriptions/{subscription_id}/status", [AdminController::class, "updateSubscriptionStatus"]);
    });

});