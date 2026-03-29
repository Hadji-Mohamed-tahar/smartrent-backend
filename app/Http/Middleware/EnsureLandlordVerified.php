<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureLandlordVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // تحقق من نوع المستخدم
        if (!$user || $user->type !== 'landlord') {
            return response()->json([
                'status' => 'error',
                'message' => 'فقط الملاك يمكنهم القيام بهذه العملية'
            ], 403);
        }

        // تحقق من وجود وثيقة تحقق تمت الموافقة عليها
        $verified = $user->verificationDocuments()->where('status', 'approved')->exists();

        if (!$verified) {
            return response()->json([
                'status' => 'error',
                'message' => 'لا يمكنك إضافة أو تعديل شقة قبل التحقق من وثيقتك'
            ], 403);
        }

        return $next($request);
    }
}