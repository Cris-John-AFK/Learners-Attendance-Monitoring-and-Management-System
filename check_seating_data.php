<?php

require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CURRENT SEATING ARRANGEMENTS ===\n\n";

try {
    $arrangements = DB::table('seating_arrangements')->get();
    
    echo "Total records: " . $arrangements->count() . "\n\n";
    
    foreach ($arrangements as $arrangement) {
        echo "ID: {$arrangement->id}\n";
        echo "Section ID: {$arrangement->section_id}\n";
        echo "Subject ID: {$arrangement->subject_id}\n";
        echo "Teacher ID: {$arrangement->teacher_id}\n";
        echo "Layout Preview: " . substr($arrangement->layout, 0, 100) . "...\n";
        echo "Updated: {$arrangement->updated_at}\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}