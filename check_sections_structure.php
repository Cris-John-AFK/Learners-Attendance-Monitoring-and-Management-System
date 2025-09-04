<?php

require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING SECTIONS TABLE STRUCTURE ===\n\n";

try {
    // Get columns for sections table
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'sections' ORDER BY ordinal_position");
    
    echo "Sections table columns:\n";
    foreach ($columns as $column) {
        echo "- {$column->column_name} ({$column->data_type})\n";
    }
    
    echo "\n=== SAMPLE SECTIONS DATA ===\n";
    $sections = DB::table('sections')->limit(3)->get();
    foreach ($sections as $section) {
        echo "Section: " . json_encode($section, JSON_PRETTY_PRINT) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}