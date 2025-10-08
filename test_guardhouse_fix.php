<?php

require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

// Load Laravel configuration
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Testing guardhouse foreign key fix...\n";
    
    // Test inserting a record with student_id 3240
    DB::table('guardhouse_attendance')->insert([
        'student_id' => 3240,
        'qr_code_data' => 'TEST_RECORD',
        'record_type' => 'check-in',
        'timestamp' => now(),
        'date' => today(),
        'guard_name' => 'Test Guard',
        'guard_id' => 'T001',
        'is_manual' => false,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "✅ SUCCESS: Record inserted successfully!\n";
    echo "✅ Foreign key constraint is now working correctly!\n";
    
    // Clean up test record
    DB::table('guardhouse_attendance')->where('qr_code_data', 'TEST_RECORD')->delete();
    echo "✅ Test record cleaned up.\n";
    
    echo "\n🎉 The guardhouse scanner should now work properly!\n";
    echo "Try scanning the QR code again.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
