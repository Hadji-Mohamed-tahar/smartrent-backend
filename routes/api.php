<?php

use App\Http\Controllers\API\ApartmentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\FavoriteController;

// مسارات Auth العامة (لا تتطلب تسجيل دخول)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// مسارات المستخدم المحمية (JWT)
Route::middleware('auth:api')->group(function () {
    Route::get('user/profile', [AuthController::class, 'profile']); // GET {{base_url}}/api/user/profile
    Route::post('auth/logout', [AuthController::class, 'logout']);   // POST {{base_url}}/api/auth/logout
    Route::put('user/profile', [AuthController::class, 'updateProfile']);
    Route::put('user/password', [AuthController::class, 'changePassword']);
});

//مسارات الشقق 
// تصفح الشقق متاح للجميع (السياح والمستأجرين بدون تسجيل)
Route::get('apartments', [ApartmentController::class, 'index']);
// جلب بيانات المالك وتزيد عداد المشاهدات.
Route::get('apartments/{id}', [ApartmentController::class, 'show']);
Route::middleware('auth:api')->group(function () {
    // apartement
    Route::post('apartments', [ApartmentController::class, 'store']);
    Route::post('apartments/{id}', [ApartmentController::class, 'update']);
    Route::delete('apartments/{id}', [ApartmentController::class, 'destroy']);
    Route::post('apartments/{id}/phone-click', [ApartmentController::class, 'recordPhoneClick']);
    // favorite
    Route::get('/favorites', [FavoriteController::class, 'index']);
    // Toggle Favorite
    Route::post('/favorites/{apartment_id}', [FavoriteController::class, 'toggle']);
});
