<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "DATABASE IMPORT SCRIPT\n";
echo "========================================\n\n";

try {
    // Test database connection
    echo "Testing database connection...\n";
    DB::connection()->getPdo();
    echo "✅ Database connected successfully!\n\n";

    // Import main database export
    echo "Importing main database export...\n";
    $mainSql = file_get_contents(__DIR__ . '/../LAMMS_DATABASE_EXPORT_2025-10-21_055045.sql');
    if ($mainSql) {
        DB::unprepared($mainSql);
        echo "✅ Main database imported successfully!\n\n";
    } else {
        echo "❌ Could not read main database file\n\n";
    }

    // Import attendance data
    echo "Importing attendance data...\n";
    $attendanceSql = file_get_contents(__DIR__ . '/../LAMMS_ATTENDANCE_DATA.sql');
    if ($attendanceSql) {
        DB::unprepared($attendanceSql);
        echo "✅ Attendance data imported successfully!\n\n";
    } else {
        echo "❌ Could not read attendance data file\n\n";
    }

    // Import groupmate attendance data
    echo "Importing groupmate attendance data...\n";
    $groupmateSql = file_get_contents(__DIR__ . '/../LAMMS_ATTENDANCE_DATA_GROUPMATE.sql');
    if ($groupmateSql) {
        DB::unprepared($groupmateSql);
        echo "✅ Groupmate attendance data imported successfully!\n\n";
    } else {
        echo "❌ Could not read groupmate attendance data file\n\n";
    }

    // Check for users
    echo "Checking imported users...\n";
    $users = DB::table('users')->select('username', 'role')->get();
    
    if ($users->count() > 0) {
        echo "✅ Found " . $users->count() . " users:\n";
        foreach ($users as $user) {
            echo "   - {$user->username} ({$user->role})\n";
        }
    } else {
        echo "⚠️  No users found. Creating default users...\n";
        
        // Create admin user
        $adminUserId = DB::table('users')->insertGetId([
            'username' => 'admin',
            'email' => 'admin@school.edu',
            'password' => bcrypt('admin'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        echo "✅ Admin user created!\n";
        echo "   Username: admin\n";
        echo "   Password: admin\n";
    }

    echo "\n========================================\n";
    echo "✅ DATABASE IMPORT COMPLETED!\n";
    echo "========================================\n\n";

    echo "You can now login with:\n";
    echo "1. Admin    - Username: admin | Password: admin\n";
    echo "2. Teachers - Check the users table for teacher credentials\n";
    echo "              (Usually: firstname.lastname | password123)\n\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
