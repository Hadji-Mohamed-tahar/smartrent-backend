<?php

use App\Http\Controllers\API\ApartmentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\FavoriteController;
use App\Http\Controllers\API\LandlordController;

// 1. مسارات Auth العامة
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// 2. مسارات الشقق العامة (للزوار)
Route::get('apartments', [ApartmentController::class, 'index']);
Route::get('apartments/{id}', [ApartmentController::class, 'show']);

// 3. المسارات المحمية (تتطلب تسجيل دخول JWT)
Route::middleware('auth:api')->group(function () {

    // ملف المستخدم الشخصي
    Route::get('user/profile', [AuthController::class, 'profile']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::put('user/profile', [AuthController::class, 'updateProfile']);
    Route::put('user/password', [AuthController::class, 'changePassword']);

    // مسارات المالك (Landlord)
    Route::prefix('landlord')->group(function () {
        Route::get('apartments', [LandlordController::class, 'landlordApartments']);
        Route::post('verify', [LandlordController::class, 'uploadVerificationDocument']); // رفع الوثائق
        // إضافة إحصائيات المالك
        Route::get('stats', [LandlordController::class, 'stats']);
    });

    // عمليات الشقق (Apartments Ops) للمالكين الموثقين
    Route::middleware('verified.landlord')->group(function () {
        Route::post('apartments', [ApartmentController::class, 'store']);
        Route::post('apartments/{id}', [ApartmentController::class, 'update']);
        Route::delete('apartments/{id}', [ApartmentController::class, 'destroy']);
    });

    // عمليات الشقق الأخرى (يمكن الوصول لها من الزوار أو الملاك)
    Route::post('apartments/{id}/phone-click', [ApartmentController::class, 'recordPhoneClick']);

    // المفضلة (Favorite)
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{apartment_id}', [FavoriteController::class, 'toggle']);
});
