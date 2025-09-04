<?php

require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== CHECKING ATTENDANCE TABLE CONSTRAINTS ===\n\n";

try {
    // Get table constraints
    $constraints = DB::select("
        SELECT conname, contype, pg_get_constraintdef(c.oid) as definition
        FROM pg_constraint c
        JOIN pg_namespace n ON n.oid = c.connamespace
        JOIN pg_class cl ON cl.oid = c.conrelid
        WHERE cl.relname = 'attendances'
        AND n.nspname = 'public'
    ");
    
    echo "Attendance table constraints:\n";
    foreach ($constraints as $constraint) {
        echo "- {$constraint->conname} ({$constraint->contype}): {$constraint->definition}\n";
    }
    
    echo "\n=== ATTENDANCE STATUSES ===\n";
    $statuses = DB::table('attendance_statuses')->get();
    foreach ($statuses as $status) {
        echo "- ID: {$status->id}, Code: {$status->code}, Name: {$status->name}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}