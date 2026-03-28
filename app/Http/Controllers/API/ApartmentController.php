<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Apartment;
use App\Http\Requests\ListApartmentsRequest;
use App\Http\Requests\StoreApartmentRequest;
use App\Http\Requests\UpdateApartmentRequest;
use Illuminate\Support\Facades\Storage;

class ApartmentController extends Controller
{
    /**
     * تصفح قائمة الشقق مع دعم الفلاتر
     */
    public function index(ListApartmentsRequest $request)
    {
        $query = Apartment::query()->where('is_active', true)->where('status', 'approved');

        // تطبيق الفلاتر إذا وجدت
        if ($request->filled('wilaya')) {
            $query->where('wilaya', $request->wilaya);
        }

        if ($request->filled('municipality')) {
            $query->where('municipality', $request->municipality);
        }

        if ($request->filled('price_unit')) {
            $query->where('price_unit', $request->price_unit);
        }

        if ($request->filled('rooms')) {
            $query->where('rooms', $request->rooms);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // ترتيب النتائج
        switch ($request->get('sort_by', 'latest')) {
            case 'price_asc':
                $query->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc'); // latest
        }

        $apartments = $query->get([
            'id',
            'title',
            'wilaya',
            'price',
            'images',
            'amenities'
        ]);

        return response()->json([
            "status" => "success",
            "data" => $apartments
        ]);
    }

    public function show($id)
    {
        // جلب الشقة مع بيانات المالك
        $apartment = Apartment::with('landlord:id,name,phone')->find($id);

        if (!$apartment) {
            return response()->json([
                "status" => "error",
                "message" => "الشقة غير موجودة"
            ], 404);
        }

        // زيادة عداد المشاهدات
        $apartment->increment('views_count');

        return response()->json([
            "status" => "success",
            "data" => [
                "id" => $apartment->id,
                "title" => $apartment->title,
                "description" => $apartment->description,
                "wilaya" => $apartment->wilaya,
                "municipality" => $apartment->municipality,
                "price" => $apartment->price,
                "price_unit" => $apartment->price_unit,
                "rooms" => $apartment->rooms,
                "bathrooms" => $apartment->bathrooms,
                "area" => $apartment->area,
                "is_active" => $apartment->is_active,
                "is_featured" => $apartment->is_featured,
                "status" => $apartment->status,
                "views_count" => $apartment->views_count,
                "phone_clicks" => $apartment->phone_clicks,
                "landlord_id" => $apartment->landlord->id,
                "landlord_name" => $apartment->landlord->name,
                "landlord_phone" => $apartment->landlord->phone,
                "images" => $apartment->images,
                "amenities" => $apartment->amenities,
            ]
        ]);
    }

    public function store(StoreApartmentRequest $request)
    {
        // 1. استخدام البيانات التي تم التحقق منها (Validated Data) فقط
        $validated = $request->validated();

        $user = auth('api')->user();

        // 2. التحقق من الصلاحيات (يفضل لاحقاً نقلها إلى Middleware أو Policy)
        if (!$user || $user->type !== 'landlord') {
            return response()->json([
                "status" => "error",
                "message" => "فقط الملاك يمكنهم إضافة شقق"
            ], 403);
        }

        // 3. معالجة الصور
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                if ($image->isValid()) {
                    // تخزين الملف والحصول على المسار النسبي
                    $path = $image->store('apartments', 'public');
                    // نصيحة: خزن المسار النسبي فقط في القاعدة، وقم بتحويله لرابط كامل عند العرض (URL)
                    $imagePaths[] = $path;
                }
            }
        } else {
            return response()->json([
                "status" => "error",
                "message" => "يرجى رفع صور للشقة"
            ], 422); // كود 422 أنسب لأخطاء التحقق من البيانات
        }

        // 4. إنشاء الشقة باستخدام البيانات الموثوقة
        $apartment = \App\Models\Apartment::create([
            "landlord_id"  => $user->id,
            "title"        => $validated['title'],
            "description"  => $validated['description'],
            "wilaya"       => $validated['wilaya'],
            "municipality" => $validated['municipality'],
            "price"        => $validated['price'],
            "price_unit"   => $validated['price_unit'],
            "rooms"        => $validated['rooms'],
            "bathrooms"    => $validated['bathrooms'],
            "area"         => $validated['area'],
            "amenities"    => $request->amenities ?? [],
            "images"       => $imagePaths,
            "is_active"    => true,
            "status"       => "pending",
        ]);

        return response()->json([
            "status"  => "success",
            "message" => "تمت إضافة الشقة بنجاح، وهي قيد المراجعة الآن",
            "data"    => $apartment
        ], 201);
    }

    public function update(UpdateApartmentRequest $request, $id)
    {
        $user = auth('api')->user();

        // 1. جلب الشقة
        $apartment = Apartment::find($id);

        if (!$apartment) {
            return response()->json([
                "status" => "error",
                "message" => "الشقة غير موجودة"
            ], 404);
        }

        // 2. تحقق من الملكية
        if ($apartment->landlord_id !== $user->id) {
            return response()->json([
                "status" => "error",
                "message" => "ليس لديك صلاحية تعديل هذه الشقة"
            ], 403);
        }

        // 3. التحقق من البيانات الموثوقة
        $validated = $request->validated();

        // 4. معالجة الصور الجديدة (إذا تم رفعها)
        if ($request->hasFile('images')) {
            $imagePaths = [];
            foreach ($request->file('images') as $image) {
                if ($image->isValid()) {
                    $path = $image->store('apartments', 'public');
                    $imagePaths[] = $path;
                }
            }
            $validated['images'] = $imagePaths;
        }

        // 5. تحديث الشقة
        $apartment->update($validated);

        return response()->json([
            "status" => "success",
            "message" => "تم تعديل الشقة بنجاح",
            "data" => $apartment
        ]);
    }
    public function destroy($id)
    {
        $user = auth('api')->user();

        // 1️⃣ تحقق من تسجيل الدخول
        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "Unauthorized"
            ], 401);
        }

        // 2️⃣ جلب الشقة
        $apartment = Apartment::find($id);

        if (!$apartment) {
            return response()->json([
                "status" => "error",
                "message" => "الشقة غير موجودة"
            ], 404);
        }

        // 3️⃣ تحقق من ملكية المالك للشقة
        if ($apartment->landlord_id !== $user->id) {
            return response()->json([
                "status" => "error",
                "message" => "ليس لديك صلاحية حذف هذه الشقة"
            ], 403);
        }

        // 4️⃣ حذف الصور من التخزين
        if ($apartment->images && is_array($apartment->images)) {
            foreach ($apartment->images as $imagePath) {

                // تنظيف المسار: نحذف الرابط الكامل أو كلمة storage/ إذا وجدت في البداية
                // هذا يضمن أننا نحصل على "apartments/filename.jpg" فقط
                $relativePath = str_replace([asset('storage/'), 'storage/', asset('storage')], '', $imagePath);
                $relativePath = ltrim($relativePath, '/'); // حذف أي شرطة مائلة زائدة في البداية

                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($relativePath)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($relativePath);
                }
            }
        }

        // 5️⃣ حذف الشقة من قاعدة البيانات
        $apartment->delete();

        return response()->json([
            "status" => "success",
            "message" => "تم حذف الشقة بنجاح"
        ]);
    }

    public function recordPhoneClick($id)
    {
        $user = auth('api')->user();

        // 1️⃣ تحقق من تسجيل الدخول
        if (!$user) {
            return response()->json([
                "status" => "error",
                "message" => "Unauthorized"
            ], 401);
        }

        // 2️⃣ تحقق من نوع المستخدم (renter only)
        if ($user->type !== 'renter') {
            return response()->json([
                "status" => "error",
                "message" => "فقط المستأجرين يمكنهم تسجيل نقرة الهاتف"
            ], 403);
        }

        // 3️⃣ جلب الشقة
        $apartment = Apartment::find($id);

        if (!$apartment) {
            return response()->json([
                "status" => "error",
                "message" => "الشقة غير موجودة"
            ], 404);
        }

        // 4️⃣ زيادة عداد phone_clicks
        $apartment->increment('phone_clicks');

        return response()->json([
            "status" => "success",
            "message" => "تم تسجيل نقرة الهاتف بنجاح",
            "data" => [
                "apartment_id" => $apartment->id,
                "phone_clicks" => $apartment->phone_clicks
            ]
        ]);
    }
}
