<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB; // <--- هذا السطر سيحل مشكلة Undefined DB نهائياً

class UserController extends Controller
{
    // Packages
    public function indexPackages()
    {
        $packages = Package::where("is_active", true)->get();
        return response()->json(["packages" => $packages]);
    }

    public function showPackage($package_id)
    {
        $package = Package::where("is_active", true)->find($package_id);
        if (!$package) {
            return response()->json(["message" => "Package not found or not active."], 404);
        }
        return response()->json(["package" => $package]);
    }

    public function subscribeToPackage(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            "package_id" => "required|exists:packages,id",
            "payment_method" => "sometimes|required|string|in:manual_bank_transfer",
        ]);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        $package = Package::find($request->package_id);

        if (!$package || !$package->is_active) {
            return response()->json(["message" => "Package not found or not active."], 404);
        }

        // 1. التحقق من وجود أي اشتراك نشط حالياً (لأي باقة)
        $activeSubscription = $user->subscriptions()
            ->where('is_active', true)
            ->where('end_date', '>', now())
            ->first();

        if ($activeSubscription) {
            return response()->json([
                "message" => "You already have an active subscription. You cannot subscribe to a new one until it expires.",
                "current_subscription" => $activeSubscription,
            ], 400);
        }

        // 2. معالجة الباقة المجانية باستخدام DB Transaction المستورد في الأعلى
        if ($package->price == 0) {
            return DB::transaction(function () use ($user, $package) {
                // إلغاء تفعيل أي اشتراكات سابقة لضمان النظافة
                $user->subscriptions()->update(['is_active' => false]);

                $startDate = now();
                $endDate = $startDate->copy()->addDays($package->duration_in_days);

                $subscription = Subscription::create([
                    "user_id" => $user->id,
                    "package_id" => $package->id,
                    "start_date" => $startDate,
                    "end_date" => $endDate,
                    "is_active" => true,
                ]);

                return response()->json([
                    "message" => "Free package subscribed and activated successfully.",
                    "subscription" => $subscription,
                ], 201);
            });
        } 
        
        // 3. معالجة الباقة المدفوعة
        else {
            if (!$request->has('payment_method')) {
                return response()->json(["message" => "Payment method is required for paid packages."], 422);
            }

            $payment = Payment::create([
                "user_id" => $user->id,
                "package_id" => $package->id,
                "amount" => $package->price,
                "payment_method" => $request->payment_method,
                "status" => "pending_verification",
            ]);

            return response()->json([
                "message" => "Subscription process initiated. Please upload your payment receipt.",
                "payment" => $payment,
            ], 201);
        }
    }

    public function uploadPaymentReceipt(Request $request, $payment_id)
    {
        /** @var User $user */
        $user = Auth::user();

        $payment = Payment::where("user_id", $user->id)->find($payment_id);
        if (!$payment) {
            return response()->json(["message" => "Payment not found."], 404);
        }

        if ($payment->status !== "pending_verification") {
            return response()->json(["message" => "Payment is not in pending verification status."], 400);
        }

        $validator = Validator::make($request->all(), [
            "receipt_image" => "required|image|mimes:jpeg,png,jpg,gif,svg|max:2048",
        ]);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        if ($request->hasFile("receipt_image")) {
            $imagePath = $request->file("receipt_image")->store("receipts", "public");
            $payment->receipt_image_path = $imagePath;
            $payment->save();

            return response()->json([
                "message" => "Payment receipt uploaded successfully. Awaiting admin verification.",
                "payment" => $payment,
            ]);
        }

        return response()->json(["message" => "Failed to upload receipt image."], 500);
    }

    public function getSubscriptionStatus()
    {
        /** @var User $user */
        $user = Auth::user();
        
        $subscription = $user->subscriptions()
            ->where("is_active", true)
            ->latest("end_date")
            ->first();

        if (!$subscription) {
            return response()->json(["message" => "No active subscription found.", "subscription" => null]);
        }

        return response()->json([
            "subscription" => [
                "id" => $subscription->id,
                "package_name" => $subscription->package->name,
                "start_date" => $subscription->start_date->format("Y-m-d"),
                "end_date" => $subscription->end_date->format("Y-m-d"),
                "is_active" => $subscription->is_active,
                "status" => ($subscription->end_date->isFuture() && $subscription->is_active) ? "active" : "expired",
            ],
        ]);
    }

    public function indexMySubscriptions()
    {
        /** @var User $user */
        $user = Auth::user();
        $subscriptions = $user->subscriptions()->with("package")->latest("created_at")->get();

        $formattedSubscriptions = $subscriptions->map(function ($subscription) {
            return [
                "id" => $subscription->id,
                "package_name" => $subscription->package->name,
                "start_date" => $subscription->start_date->format("Y-m-d"),
                "end_date" => $subscription->end_date->format("Y-m-d"),
                "is_active" => $subscription->is_active,
            ];
        });

        return response()->json(["subscriptions" => $formattedSubscriptions]);
    }

    public function indexMyPayments()
    {
        /** @var User $user */
        $user = Auth::user();
        $payments = $user->payments()->with("package")->latest("created_at")->get();

        $formattedPayments = $payments->map(function ($payment) {
            return [
                "id" => $payment->id,
                "package_name" => $payment->package->name,
                "amount" => $payment->amount,
                "payment_method" => $payment->payment_method,
                "status" => $payment->status,
                "created_at" => $payment->created_at->format("Y-m-d H:i:s"),
            ];
        });

        return response()->json(["payments" => $formattedPayments]);
    }

    public function showMyPayment($payment_id)
    {
        /** @var User $user */
        $user = Auth::user();
        $payment = $user->payments()->with("package")->find($payment_id);

        if (!$payment) {
            return response()->json(["message" => "Payment not found."], 404);
        }

        return response()->json([
            "payment" => [
                "id" => $payment->id,
                "package_name" => $payment->package->name,
                "amount" => $payment->amount,
                "payment_method" => $payment->payment_method,
                "status" => $payment->status,
                "receipt_image_path" => $payment->receipt_image_path ? Storage::url($payment->receipt_image_path) : null,
                "created_at" => $payment->created_at->format("Y-m-d H:i:s"),
            ],
        ]);
    }
}