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
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * تسجيل الدخول وجلب التوكن وحالة التوثيق
     */
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

        /** @var \App\Models\User $user */
        $user = Auth::guard('api')->user();

        return response()->json([
            "status" => "success",
            "message" => "تم تسجيل الدخول بنجاح",
            "data" => [
                "user" => [
                    "id" => $user->id,
                    "name" => $user->name,
                    "type" => $user->type,
                    "verification_status" => $user->verification_status, // الحقل الجديد للفرونت إند
                ],
                "token" => $token,
                "token_type" => "bearer",
                "expires_in" => JWTAuth::factory()->getTTL() * 60
            ]
        ]);
    }

    /**
     * تسجيل مستخدم جديد (الحالة الافتراضية unverified)
     */
   public function register(RegisterRequest $request)
    {
        $data = $request->only("name", "email", "password", "phone", "type");

        // تشفير كلمة المرور
        $data["password"] = bcrypt($data["password"]);

        // إنشاء المستخدم في قاعدة البيانات
        $user = \App\Models\User::create($data);

        // تحديث كائن المستخدم من قاعدة البيانات لجلب القيم الافتراضية (مثل verification_status)
        $user->refresh();

        return response()->json([
            "status" => "success",
            "message" => "تم تسجيل الحساب بنجاح",
            "data" => [
                "user" => [
                    "id" => $user->id,
                    "name" => $user->name,
                    "type" => $user->type,
                    "verification_status" => $user->verification_status, // ستظهر الآن 'unverified' بدلاً من null
                ]
            ]
        ]);
    }

    /**
     * جلب بيانات الملف الشخصي مع حالة التوثيق الحالية
     */
    public function profile()
    {
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
                "verification_status" => $user->verification_status, // مهم جداً لتحديث حالة الفرونت إند
            ]
        ]);
    }

    /**
     * تحديث بيانات الملف الشخصي
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        $userId = auth('api')->id();
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "المستخدم غير موجود في قاعدة البيانات"
            ], 404);
        }

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
                "verification_status" => $user->verification_status,
            ]
        ]);
    }

    /**
     * تغيير كلمة المرور
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = User::find(auth('api')->id());

        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "غير مصرح لك بالقيام بهذا الإجراء"
            ], 401);
        }

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                "status" => "error",
                "message" => "كلمة المرور الحالية غير صحيحة"
            ], 403);
        }

        $user->password = $request->new_password;
        $user->save();

        return response()->json([
            "status" => "success",
            "message" => "تم تحديث كلمة المرور بنجاح"
        ]);
    }

    /**
     * تسجيل الخروج وإبطال التوكن
     */
    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                "status" => "success",
                "message" => "تم تسجيل الخروج بنجاح"
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                "status" => "error",
                "message" => "التوكن منتهي الصلاحية بالفعل"
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                "status" => "error",
                "message" => "التوكن غير صالح"
            ], 401);
        }
    }
}
