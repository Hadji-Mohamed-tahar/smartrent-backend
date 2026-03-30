<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin; // تأكد من استيراد نموذج Admin

class AdminCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return response()->json(["message" => "Unauthenticated."], 401);
        }

        $user = Auth::user();

        // تحقق مما إذا كان المستخدم لديه سجل في جدول Admins
        // أو إذا كان لديه حقل 'type' في جدول users يشير إلى أنه مسؤول
        // يمكنك تعديل هذا المنطق ليناسب طريقة تعريف المسؤولين في مشروعك
        if (!$user->admin) { // يفترض وجود علاقة hasOne بين User و Admin
            return response()->json(["message" => "Unauthorized action. Admin access required."], 403);
        }

        // يمكنك إضافة منطق للتحقق من الأدوار أو الصلاحيات المحددة هنا
        // مثال: if ($user->admin->role !== 'super_admin') { ... }

        return $next($request);
    }
}