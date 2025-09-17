<?php
require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

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
    echo "=== SETTING UP MARIA SANTOS PASSWORD ===\n\n";

    // Set a known password for testing
    $newPassword = 'teacher123';
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $updated = DB::table('users')
        ->where('username', 'maria.santos')
        ->update(['password' => $hashedPassword]);
        
    if ($updated) {
        echo "✅ Password updated successfully\n";
        echo "Username: maria.santos\n";
        echo "Password: $newPassword\n\n";
        
        // Verify the user exists and is active
        $user = DB::table('users')->where('username', 'maria.santos')->first();
        echo "User details:\n";
        echo "- ID: {$user->id}\n";
        echo "- Email: {$user->email}\n";
        echo "- Role: {$user->role}\n";
        echo "- Active: " . ($user->is_active ? 'Yes' : 'No') . "\n";
    } else {
        echo "❌ Failed to update password\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
