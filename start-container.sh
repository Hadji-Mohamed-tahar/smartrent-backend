#!/bin/sh

# 1. تفعيل روابط التخزين
php artisan storage:link --force

# 2. مسح شامل وإعادة بناء (أقوى من refresh)
php artisan migrate:fresh --force

# 3. تشغيل الـ Seeders
php artisan db:seed --class=AdminSeeder --force
php artisan db:seed --class=PackageSeeder --force

# 4. التشغيل النهائي
exec /usr/bin/frankenphp run --config /etc/caddy/Caddyfile