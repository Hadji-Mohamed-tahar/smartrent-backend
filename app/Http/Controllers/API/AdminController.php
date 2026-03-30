<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;

use App\Models\Admin;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware("auth:api");
    //     // Add middleware for admin role/permissions here
    //     // Example: $this->middleware("role:super_admin|moderator");
    // }

    // Admins Management
    public function indexAdmins()
    {
        $admins = Admin::with("user")->get();
        return response()->json(["admins" => $admins]);
    }

    public function storeAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "user_id" => "required|exists:users,id|unique:admins,user_id",
            "role" => "required|string|max:50",
            "permissions" => "nullable|array",
        ]);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        $admin = Admin::create($request->all());
        return response()->json(["message" => "Admin added successfully.", "admin" => $admin], 201);
    }

    public function updateAdmin(Request $request, $admin_id)
    {
        $admin = Admin::find($admin_id);
        if (!$admin) {
            return response()->json(["message" => "Admin not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            "role" => "sometimes|required|string|max:50",
            "permissions" => "nullable|array",
        ]);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        $admin->update($request->all());
        return response()->json(["message" => "Admin updated successfully.", "admin" => $admin]);
    }

    public function destroyAdmin($admin_id)
    {
        $admin = Admin::find($admin_id);
        if (!$admin) {
            return response()->json(["message" => "Admin not found."], 404);
        }

        $admin->delete();
        return response()->json(null, 204);
    }

    // Packages Management
    public function indexPackages()
    {
        $packages = Package::all();
        return response()->json(["packages" => $packages]);
    }

    public function storePackage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "name" => "required|string|max:255",
            "description" => "nullable|string",
            "price" => "required|numeric|min:0",
            "duration_in_days" => "required|integer|min:1",
            "features" => "nullable|array",
            "is_active" => "boolean",
        ]);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        $package = Package::create($request->all());
        return response()->json(["message" => "Package created successfully.", "package" => $package], 201);
    }

    public function updatePackage(Request $request, $package_id)
    {
        $package = Package::find($package_id);
        if (!$package) {
            return response()->json(["message" => "Package not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            "name" => "sometimes|required|string|max:255",
            "description" => "nullable|string",
            "price" => "sometimes|required|numeric|min:0",
            "duration_in_days" => "sometimes|required|integer|min:1",
            "features" => "nullable|array",
            "is_active" => "boolean",
        ]);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        $package->update($request->all());
        return response()->json(["message" => "Package updated successfully.", "package" => $package]);
    }

    public function destroyPackage($package_id)
    {
        $package = Package::find($package_id);
        if (!$package) {
            return response()->json(["message" => "Package not found."], 404);
        }

        // TODO: Implement logic to handle existing subscriptions before deleting package
        $package->delete();
        return response()->json(null, 204);
    }

    // Payments Management
    public function indexPendingPayments()
    {
        $payments = Payment::where("status", "pending_verification")->with("user", "package")->get();
        return response()->json(["pending_payments" => $payments]);
    }

    public function showPayment($payment_id)
    {
        $payment = Payment::with("user", "package")->find($payment_id);
        if (!$payment) {
            return response()->json(["message" => "Payment not found."], 404);
        }
        return response()->json(["payment" => $payment]);
    }

    public function approvePayment(Request $request, $payment_id)
    {
        $payment = Payment::find($payment_id);
        if (!$payment) {
            return response()->json(["message" => "Payment not found."], 404);
        }

        if ($payment->status !== "pending_verification") {
            return response()->json(["message" => "Payment is not pending verification."], 400);
        }

        $package = $payment->package;
        if (!$package) {
            return response()->json(["message" => "Associated package not found."], 404);
        }

        $payment->status = "approved";
        $payment->admin_notes = $request->input("admin_notes");
        $payment->save();

        // Create subscription
        $startDate = now();
        $endDate = $startDate->copy()->addDays($package->duration_in_days);

        $subscription = Subscription::create([
            "user_id" => $payment->user_id,
            "package_id" => $payment->package_id,
            "start_date" => $startDate,
            "end_date" => $endDate,
            "is_active" => true,
        ]);

        return response()->json([
            "message" => "Payment approved and subscription created successfully.",
            "payment" => $payment,
            "subscription" => $subscription,
        ]);
    }

    public function rejectPayment(Request $request, $payment_id)
    {
        $payment = Payment::find($payment_id);
        if (!$payment) {
            return response()->json(["message" => "Payment not found."], 404);
        }

        if ($payment->status !== "pending_verification") {
            return response()->json(["message" => "Payment is not pending verification."], 400);
        }

        $validator = Validator::make($request->all(), [
            "admin_notes" => "required|string",
        ]);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        $payment->status = "rejected";
        $payment->admin_notes = $request->input("admin_notes");
        $payment->save();

        return response()->json(["message" => "Payment rejected successfully.", "payment" => $payment]);
    }

    // Subscriptions Management
    public function indexSubscriptions()
    {
        $subscriptions = Subscription::with("user", "package")->get();
        return response()->json(["subscriptions" => $subscriptions]);
    }

    public function updateSubscriptionStatus(Request $request, $subscription_id)
    {
        $subscription = Subscription::find($subscription_id);
        if (!$subscription) {
            return response()->json(["message" => "Subscription not found."], 404);
        }

        $validator = Validator::make($request->all(), [
            "is_active" => "required|boolean",
        ]);

        if ($validator->fails()) {
            return response()->json(["errors" => $validator->errors()], 422);
        }

        $subscription->is_active = $request->input("is_active");
        $subscription->save();

        return response()->json(["message" => "Subscription status updated successfully.", "subscription" => $subscription]);
    }
}
