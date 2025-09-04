<?php

require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING STUDENT_DETAILS TABLE STRUCTURE ===\n\n";

try {
    // Get columns for student_details table
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'student_details' ORDER BY ordinal_position");
    
    echo "Student_details table columns:\n";
    foreach ($columns as $column) {
        echo "- {$column->column_name} ({$column->data_type})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}