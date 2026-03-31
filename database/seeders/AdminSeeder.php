<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Admin;

class AdminSeeder extends Seeder
{
    public function run()
    {
        // 1. الأدمن الأساسي (تم تشغيله مسبقاً - سيتم تخطيه)
        $admin1 = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Lakhdar',
                'password' => bcrypt('000000'),
                'phone' => '0000000000',
                'type' => 'renter',
                'verification_status' => 'verified'
            ]
        );

        Admin::firstOrCreate(
            ['user_id' => $admin1->id],
            [
                'role' => 'super_admin',
                'permissions' => json_encode(['manage_users', 'manage_packages', 'manage_payments', 'manage_apartments'])
            ]
        );

        // 2. إضافة أدمن جديد: مدير العقارات (Moderator)
        $admin2 = User::firstOrCreate(
            ['email' => 'moderator@smartrent.dz'],
            [
                'name' => 'Ahmed Moderator',
                'password' => bcrypt('123456'),
                'phone' => '0555112233',
                'type' => 'renter',
                'verification_status' => 'verified'
            ]
        );

        Admin::firstOrCreate(
            ['user_id' => $admin2->id],
            [
                'role' => 'moderator',
                'permissions' => json_encode(['manage_apartments', 'manage_users'])
            ]
        );

        // 3. إضافة أدمن جديد: المدير المالي (Finance)
        $admin3 = User::firstOrCreate(
            ['email' => 'finance@smartrent.dz'],
            [
                'name' => 'Sara Finance',
                'password' => bcrypt('123456'),
                'phone' => '0555445566',
                'type' => 'renter',
                'verification_status' => 'verified'
            ]
        );

        Admin::firstOrCreate(
            ['user_id' => $admin3->id],
            [
                'role' => 'finance_admin',
                'permissions' => json_encode(['manage_payments', 'manage_packages'])
            ]
        );
    }
}