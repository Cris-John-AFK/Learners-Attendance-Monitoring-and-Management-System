<?php

require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING ALL SEATING ARRANGEMENTS IN DATABASE ===\n\n";

try {
    $arrangements = DB::table('seating_arrangements')->get();
    
    echo "Total records found: " . $arrangements->count() . "\n\n";
    
    if ($arrangements->count() > 0) {
        echo "Current records:\n";
        foreach ($arrangements as $arrangement) {
            echo "ID: {$arrangement->id} | Section: {$arrangement->section_id} | Subject: {$arrangement->subject_id} | Teacher: {$arrangement->teacher_id}\n";
            echo "Layout preview: " . substr($arrangement->layout, 0, 100) . "...\n";
            echo "Created: {$arrangement->created_at} | Updated: {$arrangement->updated_at}\n";
            echo "---\n";
        }
        
        echo "\n🗑️  CLEARING ALL SEATING ARRANGEMENT DATA...\n";
        
        // Delete ALL seating arrangements
        $deleted = DB::table('seating_arrangements')->delete();
        echo "✅ Deleted {$deleted} records\n";
        
        // Verify it's completely empty
        $remaining = DB::table('seating_arrangements')->count();
        echo "📊 Remaining records: {$remaining}\n";
        
        if ($remaining == 0) {
            echo "🎉 Database is now completely clean!\n";
        } else {
            echo "⚠️  Warning: {$remaining} records still remain!\n";
        }
    } else {
        echo "✅ Database is already clean - no seating arrangements found\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}