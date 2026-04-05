#!/bin/sh

# 1. تفعيل روابط التخزين (Storage Link)
# لضمان وصول الجمهور لصور الشقق في مجلد storage/app/public
php artisan storage:link --force

# 2. إعادة إنشاء قاعدة البيانات من الصفر (لمرة واحدة فقط)
# سيقوم بحذف الجداول القديمة وإنشائها مجدداً
php artisan migrate:refresh --force

# 3. تشغيل الـ Seeders المحددة بالاسم
# تأكد من كتابة اسم الكلاس (Class Name) كما هو موجود داخل الملفات
php artisan db:seed --class=AdminSeeder --force
php artisan db:seed --class=PackageSeeder --force

# 4. تشغيل خادم FrankenPHP (الأمر الأساسي لبقاء الموقع حياً)
# ملاحظة: هذا المسار افتراضي لـ Railpack لضمان استقرار الحاوية
exec /usr/bin/frankenphp run --config /etc/caddy/Caddyfile