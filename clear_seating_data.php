<?php

require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CLEARING SEATING ARRANGEMENTS ===\n\n";

try {
    // Clear all seating arrangements
    $deleted = DB::table('seating_arrangements')->delete();
    
    echo "✅ Cleared {$deleted} seating arrangement(s) from database\n";
    
    // Verify it's empty
    $remaining = DB::table('seating_arrangements')->count();
    echo "📊 Remaining records: {$remaining}\n";
    
    if ($remaining == 0) {
        echo "🎉 Database is now clean!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}