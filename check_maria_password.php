<?php
require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Facades\Hash;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable('lamms-backend');
$dotenv->load();

// Setup database connection
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'pgsql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? '5432',
    'database' => $_ENV['DB_DATABASE'] ?? 'lamms_db',
    'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    echo "=== CHECKING MARIA SANTOS USER DATA ===\n\n";

    // Get Maria's user data
    $user = DB::table('users')->where('username', 'maria.santos')->first();
    
    if ($user) {
        echo "✅ User found:\n";
        echo "ID: {$user->id}\n";
        echo "Username: {$user->username}\n";
        echo "Email: {$user->email}\n";
        echo "Role: {$user->role}\n";
        echo "Active: " . ($user->is_active ? 'Yes' : 'No') . "\n";
        echo "Password hash: " . substr($user->password, 0, 20) . "...\n\n";
        
        // Test common passwords
        $testPasswords = ['password', 'password123', '123456', 'maria123', 'santos123'];
        
        echo "Testing common passwords:\n";
        foreach ($testPasswords as $testPassword) {
            if (Hash::check($testPassword, $user->password)) {
                echo "✅ Password '$testPassword' matches!\n";
                break;
            } else {
                echo "❌ Password '$testPassword' does not match\n";
            }
        }
        
        // Set a known password for testing
        echo "\n=== SETTING TEST PASSWORD ===\n";
        $newPassword = 'teacher123';
        $hashedPassword = Hash::make($newPassword);
        
        DB::table('users')
            ->where('id', $user->id)
            ->update(['password' => $hashedPassword]);
            
        echo "✅ Password updated to: $newPassword\n";
        
    } else {
        echo "❌ User 'maria.santos' not found\n";
        
        // Check all users
        echo "\nAll users:\n";
        $users = DB::table('users')->get();
        foreach ($users as $u) {
            echo "- {$u->username} ({$u->email}) - Role: {$u->role}\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
