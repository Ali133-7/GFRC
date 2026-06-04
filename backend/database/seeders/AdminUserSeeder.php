<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'مدير النظام',
                'email' => 'admin@gfrc.local',
                'password' => 'Admin@12345',
                'is_active' => true,
            ]
        );

        $user->assignRole('super_admin');
    }
}
