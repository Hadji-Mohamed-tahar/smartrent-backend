<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash; // السطر الذي حل المشكلة
class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->only("email", "password");

        // نحدد الـ guard يدوياً لضمان استخدام الـ JWT driver
        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return response()->json([
                "status" => "error",
                "message" => "بيانات الدخول غير صحيحة"
            ], 401);
        }

        $user = Auth::guard('api')->user();

        return response()->json([
            "status" => "success",
            "message" => "تم تسجيل الدخول بنجاح",
            "data" => [
                "user" => [
                    "id" => $user->id,
                    "name" => $user->name,
                    "type" => $user->type,
                ],
                "token" => $token,
                "token_type" => "bearer",
                // الحصول على وقت انتهاء التوكن بالدقائق وتحويله لثواني
                "expires_in" => JWTAuth::factory()->getTTL() * 60
            ]
        ]);
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->only("name", "email", "password", "phone", "type");

        // تشفير كلمة المرور
        $data["password"] = bcrypt($data["password"]);

        $user = \App\Models\User::create($data);

        return response()->json([
            "status" => "success",
            "message" => "User registered successfully",
            "data" => [
                "user" => [
                    "id" => $user->id,
                    "name" => $user->name,
                    "type" => $user->type,
                ]
            ]
        ]);
    }

    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken()); // إبطال التوكن الحالي

            return response()->json([
                "status" => "success",
                "message" => "Successfully logged out"
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                "status" => "error",
                "message" => "Token already expired"
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                "status" => "error",
                "message" => "Token is invalid"
            ], 401);
        }
    }


    public function profile()
    {
        // استخدام auth('api')->user() يضمن جلب المستخدم المرتبط بالتوكن الحالي
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "المستخدم غير موجود أو التوكن غير صالح"
            ], 401);
        }

        return response()->json([
            "status" => "success",
            "data" => [
                "id"    => $user->id,
                "name"  => $user->name,
                "email" => $user->email,
                "phone" => $user->phone,
                "type"  => $user->type,
            ]
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        // 1. الحصول على الـ ID الخاص بالمستخدم الحالي من التوكن
        $userId = auth('api')->id();

        // 2. جلب الموديل مباشرة من قاعدة البيانات لضمان أنه Eloquent Instance
        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "المستخدم غير موجود في قاعدة البيانات"
            ], 404);
        }

        // 3. تحديث البيانات التي تم التحقق منها فقط
        $user->update($request->validated());

        return response()->json([
            "status" => "success",
            "message" => "تم تحديث الملف الشخصي بنجاح",
            "data" => [
                "id"    => $user->id,
                "name"  => $user->name,
                "email" => $user->email,
                "phone" => $user->phone,
                "type"  => $user->type,
            ]
        ]);
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        // 1. جلب المستخدم الحالي كموديل Eloquent لضمان توفر دالة save()
        $user = \App\Models\User::find(auth('api')->id());

        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "غير مصرح لك بالقيام بهذا الإجراء"
            ], 401);
        }

        // 2. التحقق من كلمة المرور الحالية باستخدام الـ Facade الصحيح
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                "status" => "error",
                "message" => "كلمة المرور الحالية غير صحيحة"
            ], 403);
        }

        // 3. تحديث كلمة المرور الجديدة
        // ملاحظة: بما أنك وضعت 'password' => 'hashed' في الـ $casts في موديل User،
        // فإن Laravel سيقوم بتشفيرها تلقائياً عند الإسناد.
        $user->password = $request->new_password;
        $user->save();

        return response()->json([
            "status" => "success",
            "message" => "تم تحديث كلمة المرور بنجاح"
        ]);
    }
}
