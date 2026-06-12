<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin user already exists
        if (User::where('username', 'admin')->exists()) {
            $this->command->info('Admin user already exists');
            return;
        }

        // Create admin user without role (minimal fields)
        User::create([
            'username' => 'admin',
            'name' => 'Administrator',
            'email' => 'admin@gov.krd',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->command->info('Admin user created:');
        $this->command->info('  Username: admin');
        $this->command->info('  Password: password');
    }
}
