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
use App\Models\Package;
use App\Models\Subscription;

class AuthController extends Controller
{
    /**
     * تسجيل الدخول والحصول على بيانات المستخدم والباقة
     */
    /**
     * تسجيل الدخول مع إصلاح خطأ الـ Guard وتوحيد هيكل الباقة
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->only("email", "password");

        try {
            // استخدام JWTAuth مباشرة لمحاولة تسجيل الدخول وتوليد التوكن
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    "status" => "error",
                    "message" => "بيانات تسجيل الدخول غير صحيحة"
                ], 401);
            }
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json([
                "status" => "error",
                "message" => "تعذر إنشاء التوكن، يرجى المحاولة لاحقاً"
            ], 500);
        }

    // جلب المستخدم الحالي
        /** @var \App\Models\User $user */
        $user = auth('api')->user();

        // إعداد الهيكل الافتراضي (تخطيطنا الموحد)
        $currentPackage = [
            "package_id"    => null,
            "package_name"  => "بدون اشتراك",
            "package_price" => "0.00",
            "status"        => "none",
            "end_date"      => null,
            "message"       => "يرجى الاشتراك في باقة لتتمكن من النشر"
        ];

        // البحث عن الاشتراك النشط
        $activeSubscription = $user->subscriptions()
            ->where('is_active', true)
            ->where('end_date', '>', now())
            ->with('package')
            ->latest('end_date')
            ->first();

        if ($activeSubscription) {
            $currentPackage = [
                "package_id"    => $activeSubscription->package->id,
                "package_name"  => $activeSubscription->package->name,
                "package_price" => number_format($activeSubscription->package->price, 2),
                "status"        => "active",
                "end_date"      => $activeSubscription->end_date->format('Y-m-d'),
            ];
        } else {
            // البحث عن دفع معلق
            $pendingPayment = $user->payments()
                ->where('status', 'pending_verification')
                ->with('package')
                ->latest('created_at')
                ->first();

            if ($pendingPayment) {
                $currentPackage = [
                    "package_id"    => $pendingPayment->package->id,
                    "package_name"  => $pendingPayment->package->name,
                    "package_price" => number_format($pendingPayment->package->price, 2),
                    "status"        => "pending_verification",
                    "payment_id"    => $pendingPayment->id,
                    "end_date"      => null,
                ];
            }
        }

        return response()->json([
            "status" => "success",
            "message" => "تم تسجيل الدخول بنجاح",
            "data" => [
                "user" => [
                    "id"                  => $user->id,
                    "name"                => $user->name,
                    "email"               => $user->email,
                    "phone"               => $user->phone,
                    "type"                => $user->type,
                    "verification_status" => $user->verification_status,
                    "current_package"     => $currentPackage,
                ],
                "token"      => $token,
                "token_type" => "bearer",
                "expires_in" => config('jwt.ttl') * 60
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

        // البحث عن باقة مجانية افتراضية (السعر = 0)
        $freePackage = Package::where("price", 0)->where("is_active", true)->first();

        if ($freePackage) {
            // إنشاء اشتراك تلقائي للباقة المجانية للمستخدم الجديد
            $startDate = now();
            $endDate = $startDate->copy()->addDays($freePackage->duration_in_days);

            Subscription::create([
                "user_id" => $user->id,
                "package_id" => $freePackage->id,
                "start_date" => $startDate,
                "end_date" => $endDate,
                "is_active" => true,
            ]);
        }

        return response()->json([
            "status" => "success",
            "message" => "تم تسجيل الحساب بنجاح",
            "data" => [
                "user" => [
                    "id" => $user->id,
                    "name" => $user->name,
                    "type" => $user->type,
                    "verification_status" => $user->verification_status,
                ]
            ]
        ]);
    }

    /**
     * جلب بيانات الملف الشخصي مع حالة التوثيق الحالية
     */
    /**
     * جلب بيانات الملف الشخصي مع حالة التوثيق الحالية
     */
    /**
     * جلب بيانات الملف الشخصي مع حالة الباقة الموحدة
     */
    public function profile()
    {
        // الحصول على ID المستخدم الحالي من التوكن
        $userId = auth('api')->id();

    // جلب كائن المستخدم من الموديل لضمان تحميل العلاقات
        /** @var \App\Models\User $user */
        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "المستخدم غير موجود أو انتهت صلاحية الجلسة"
            ], 401);
        }

        // 1. إعداد الهيكل الافتراضي (Default Schema) - نفس الموجود في Login
        $currentPackage = [
            "package_id"    => null,
            "package_name"  => "بدون اشتراك",
            "package_price" => "0.00",
            "status"        => "none",
            "end_date"      => null,
            "message"       => "يرجى الاشتراك في باقة لتتمكن من النشر"
        ];

        // 2. البحث عن الاشتراك النشط الحالي
        $activeSubscription = $user->subscriptions()
            ->where('is_active', true)
            ->where('end_date', '>', now())
            ->with('package')
            ->latest('end_date')
            ->first();

        if ($activeSubscription) {
            $currentPackage = [
                "package_id"    => $activeSubscription->package->id,
                "package_name"  => $activeSubscription->package->name,
                "package_price" => number_format($activeSubscription->package->price, 2),
                "status"        => "active",
                "end_date"      => $activeSubscription->end_date->format('Y-m-d'),
            ];
        } else {
            // 3. البحث عن دفع معلق بانتظار التوثيق
            $pendingPayment = $user->payments()
                ->where('status', 'pending_verification')
                ->with('package')
                ->latest('created_at')
                ->first();

            if ($pendingPayment) {
                $currentPackage = [
                    "package_id"    => $pendingPayment->package->id,
                    "package_name"  => $pendingPayment->package->name,
                    "package_price" => number_format($pendingPayment->package->price, 2),
                    "status"        => "pending_verification",
                    "payment_id"    => $pendingPayment->id,
                    "end_date"      => null,
                ];
            }
        }

        // 4. الرد النهائي بالهيكل الموحد
        return response()->json([
            "status" => "success",
            "data" => [
                "user" => [
                    "id"                  => $user->id,
                    "name"                => $user->name,
                    "email"               => $user->email,
                    "phone"               => $user->phone,
                    "type"                => $user->type,
                    "verification_status" => $user->verification_status,
                    "current_package"     => $currentPackage, // الهيكل ثابت لن يعود null أبداً
                ]
            ]
        ]);
    }

    /**
     * تحديث بيانات الملف الشخصي
     */
    
    public function updateProfile(UpdateProfileRequest $request)
    {
        $userId = auth('api')->id();

        /** @var \App\Models\User $user */
        $user = \App\Models\User::find($userId);

        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "المستخدم غير موجود في قاعدة البيانات"
            ], 404);
        }

        // 1. تحديث البيانات بناءً على المدخلات الموثقة
        $user->update($request->validated());
        $user->refresh(); // لضمان قراءة البيانات المحدثة

        // 2. إعداد الهيكل الافتراضي للباقة (نفس المنطق الموحد)
        $currentPackage = [
            "package_id"    => null,
            "package_name"  => "بدون اشتراك",
            "package_price" => "0.00",
            "status"        => "none",
            "end_date"      => null,
            "message"       => "يرجى الاشتراك في باقة لتتمكن من النشر"
        ];

        // 3. جلب حالة الاشتراك الحالية لضمان دقة الرد
        $activeSubscription = $user->subscriptions()
            ->where('is_active', true)
            ->where('end_date', '>', now())
            ->with('package')
            ->latest('end_date')
            ->first();

        if ($activeSubscription) {
            $currentPackage = [
                "package_id"    => $activeSubscription->package->id,
                "package_name"  => $activeSubscription->package->name,
                "package_price" => number_format($activeSubscription->package->price, 2),
                "status"        => "active",
                "end_date"      => $activeSubscription->end_date->format('Y-m-d'),
            ];
        } else {
            $pendingPayment = $user->payments()
                ->where('status', 'pending_verification')
                ->with('package')
                ->latest('created_at')
                ->first();

            if ($pendingPayment) {
                $currentPackage = [
                    "package_id"    => $pendingPayment->package->id,
                    "package_name"  => $pendingPayment->package->name,
                    "package_price" => number_format($pendingPayment->package->price, 2),
                    "status"        => "pending_verification",
                    "payment_id"    => $pendingPayment->id,
                    "end_date"      => null,
                ];
            }
        }

        // 4. الرد النهائي بالهيكل الموحد (Consistency)
        return response()->json([
            "status" => "success",
            "message" => "تم تحديث الملف الشخصي بنجاح",
            "data" => [
                "user" => [
                    "id"                  => $user->id,
                    "name"                => $user->name,
                    "email"               => $user->email,
                    "phone"               => $user->phone,
                    "type"                => $user->type,
                    "verification_status" => $user->verification_status,
                    "current_package"     => $currentPackage, // يرسل الحالة الحالية للباقة بعد التحديث
                ]
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
