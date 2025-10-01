<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Admin;
use App\Models\GuardhouseUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CreateDefaultUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            // Create Admin User
            echo "Creating admin user...\n";
            
            // Check if admin already exists
            $existingAdmin = User::where('username', 'admin')->orWhere('email', 'admin@school.edu')->first();
            
            if (!$existingAdmin) {
                $adminUser = User::create([
                    'username' => 'admin',
                    'email' => 'admin@school.edu',
                    'password' => Hash::make('admin'),
                    'role' => 'admin',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                Admin::create([
                    'user_id' => $adminUser->id,
                    'first_name' => 'System',
                    'last_name' => 'Administrator',
                    'phone_number' => '09123456789',
                    'gender' => 'male',
                    'position' => 'School Administrator',
                ]);

                echo "✅ Admin created successfully!\n";
                echo "   Username: admin\n";
                echo "   Password: admin\n";
                echo "   Email: admin@school.edu\n\n";
            } else {
                echo "ℹ️  Admin user already exists\n\n";
            }

            // Create Guardhouse User
            echo "Creating guardhouse user...\n";
            
            // Check if guardhouse user already exists
            $existingGuard = User::where('username', 'guard')->orWhere('email', 'guard@school.edu')->first();
            
            if (!$existingGuard) {
                $guardUser = User::create([
                    'username' => 'guard',
                    'email' => 'guard@school.edu',
                    'password' => Hash::make('guard'),
                    'role' => 'guardhouse',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                GuardhouseUser::create([
                    'user_id' => $guardUser->id,
                    'first_name' => 'Security',
                    'last_name' => 'Guard',
                    'phone_number' => '09123456788',
                    'shift' => 'morning',
                ]);

                echo "✅ Guardhouse user created successfully!\n";
                echo "   Username: guard\n";
                echo "   Password: guard\n";
                echo "   Email: guard@school.edu\n\n";
            } else {
                echo "ℹ️  Guardhouse user already exists\n\n";
            }

            DB::commit();
            
            echo "========================================\n";
            echo "✅ DEFAULT USERS CREATED SUCCESSFULLY!\n";
            echo "========================================\n";
            echo "\nYou can now login with:\n";
            echo "1. Admin    - Username: admin | Password: admin\n";
            echo "2. Guard    - Username: guard | Password: guard\n";
            echo "3. Teacher  - Username: maria.santos | Password: password123\n";
            
        } catch (\Exception $e) {
            DB::rollBack();
            echo "❌ Error creating users: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}
