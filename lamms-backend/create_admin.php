<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "========================================\n";
echo "CREATING ADMIN USER\n";
echo "========================================\n\n";

try {
    // Check if admin already exists
    $existingAdmin = DB::table('users')->where('username', 'admin')->first();
    
    if ($existingAdmin) {
        echo "ℹ️  Admin user already exists!\n";
        echo "   Username: admin\n";
        echo "   Password: admin\n\n";
    } else {
        // Create admin user
        $adminUserId = DB::table('users')->insertGetId([
            'username' => 'admin',
            'email' => 'admin@school.edu',
            'password' => Hash::make('admin'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "✅ Admin user created successfully!\n";
        echo "   ID: {$adminUserId}\n";
        echo "   Username: admin\n";
        echo "   Password: admin\n";
        echo "   Email: admin@school.edu\n\n";

        // Create admin profile
        DB::table('admins')->insert([
            'user_id' => $adminUserId,
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'phone_number' => '09123456789',
            'gender' => 'male',
            'position' => 'School Administrator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        echo "✅ Admin profile created!\n\n";
    }

    echo "========================================\n";
    echo "✅ SETUP COMPLETE!\n";
    echo "========================================\n\n";
    
    echo "You can now login at:\n";
    echo "🔐 Admin Login: http://localhost:5173/admin-login\n";
    echo "   Username: admin\n";
    echo "   Password: admin\n\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
