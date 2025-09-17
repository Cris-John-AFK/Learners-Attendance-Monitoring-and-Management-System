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
    echo "Creating teacher_sessions table...\n";

    // Check if table exists
    if (DB::getSchemaBuilder()->hasTable('teacher_sessions')) {
        echo "❌ teacher_sessions table already exists\n";
        exit;
    }

    // Create teacher_sessions table
    DB::getSchemaBuilder()->create('teacher_sessions', function ($table) {
        $table->id();
        $table->unsignedBigInteger('teacher_id');
        $table->unsignedBigInteger('user_id');
        $table->string('token', 255)->unique();
        $table->string('ip_address', 45)->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
        $table->timestamp('expires_at')->nullable();
        
        // Foreign keys
        $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        
        // Indexes
        $table->index(['teacher_id', 'user_id']);
        $table->index('token');
    });

    echo "✅ teacher_sessions table created successfully\n";

} catch (Exception $e) {
    echo "❌ Error creating table: " . $e->getMessage() . "\n";
}
?>
