<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Load Laravel configuration
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "🔍 Checking day format in subject_schedules table\n";
    echo "================================================\n\n";

    // Check existing data
    $existing = DB::table('subject_schedules')->select('day')->distinct()->get();
    
    echo "Existing day values:\n";
    foreach ($existing as $row) {
        echo "   - '{$row->day}'\n";
    }
    
    // Check table constraints
    $constraints = DB::select("
        SELECT 
            tc.constraint_name, 
            cc.check_clause 
        FROM information_schema.table_constraints tc
        JOIN information_schema.check_constraints cc ON tc.constraint_name = cc.constraint_name
        WHERE tc.table_name = 'subject_schedules' AND tc.constraint_type = 'CHECK'
    ");
    
    echo "\nTable constraints:\n";
    foreach ($constraints as $constraint) {
        echo "   - {$constraint->constraint_name}: {$constraint->check_clause}\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
