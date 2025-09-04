<?php

require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== CHECKING DATABASE TABLES ===\n\n";

try {
    // Get all tables
    $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
    
    echo "All tables in database:\n";
    foreach ($tables as $table) {
        echo "- {$table->tablename}\n";
    }
    
    echo "\n=== TEACHER-RELATED TABLES ===\n";
    foreach ($tables as $table) {
        if (strpos($table->tablename, 'teacher') !== false) {
            echo "✅ {$table->tablename}\n";
            
            // Get columns for teacher-related tables
            $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = ? ORDER BY ordinal_position", [$table->tablename]);
            foreach ($columns as $column) {
                echo "   - {$column->column_name} ({$column->data_type})\n";
            }
            echo "\n";
        }
    }
    
    echo "\n=== ASSIGNMENT-RELATED TABLES ===\n";
    foreach ($tables as $table) {
        if (strpos($table->tablename, 'assignment') !== false || strpos($table->tablename, 'section') !== false) {
            echo "✅ {$table->tablename}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}