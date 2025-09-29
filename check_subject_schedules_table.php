<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Load Laravel configuration
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Check if table exists
    if (Schema::hasTable('subject_schedules')) {
        echo "✅ subject_schedules table exists\n";
        
        // Get column information
        $columns = Schema::getColumnListing('subject_schedules');
        echo "📋 Columns: " . implode(', ', $columns) . "\n";
        
        // Check for specific columns we need
        $requiredColumns = ['teacher_id', 'section_id', 'subject_id', 'day_of_week', 'start_time', 'end_time'];
        $missingColumns = [];
        
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $columns)) {
                $missingColumns[] = $column;
            }
        }
        
        if (empty($missingColumns)) {
            echo "✅ All required columns exist\n";
        } else {
            echo "❌ Missing columns: " . implode(', ', $missingColumns) . "\n";
        }
        
        // Check current data
        $count = DB::table('subject_schedules')->count();
        echo "📊 Current records: {$count}\n";
        
    } else {
        echo "❌ subject_schedules table does not exist\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
