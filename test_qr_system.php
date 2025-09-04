<?php
require_once 'lamms-backend/vendor/autoload.php';
$app = require_once 'lamms-backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Student;
use App\Models\StudentQRCode;

echo "Testing QR Code System for Grade 3 Students\n";
echo "==========================================\n\n";

// Get all Grade 3 students
$students = Student::where('gradeLevel', 3)->get();
echo "Found " . $students->count() . " Grade 3 students:\n";

foreach ($students as $student) {
    echo "\n--- Student: " . $student->name . " (ID: " . $student->id . ") ---\n";
    
    // Check if student already has a QR code
    $existingQR = StudentQRCode::getActiveQRForStudent($student->id);
    
    if ($existingQR) {
        echo "✓ Already has QR code: " . $existingQR->qr_code_data . "\n";
        echo "  Generated at: " . $existingQR->generated_at . "\n";
        if ($existingQR->last_used_at) {
            echo "  Last used: " . $existingQR->last_used_at . "\n";
        }
    } else {
        echo "⚠ No QR code found, generating new one...\n";
        
        try {
            $newQR = StudentQRCode::generateForStudent($student->id);
            echo "✓ Generated QR code: " . $newQR->qr_code_data . "\n";
            echo "  QR ID: " . $newQR->id . "\n";
            echo "  Generated at: " . $newQR->generated_at . "\n";
        } catch (Exception $e) {
            echo "✗ Error generating QR code: " . $e->getMessage() . "\n";
        }
    }
}

echo "\n\nQR Code System Test Summary:\n";
echo "============================\n";

$totalQRCodes = StudentQRCode::where('is_active', true)->count();
echo "Total active QR codes in system: " . $totalQRCodes . "\n";

$grade3QRCodes = StudentQRCode::whereHas('student', function($query) {
    $query->where('gradeLevel', 3);
})->where('is_active', true)->count();

echo "QR codes for Grade 3 students: " . $grade3QRCodes . "\n";

echo "\nAPI Endpoints available:\n";
echo "- POST /api/qr-codes/generate/{studentId} - Generate QR code\n";
echo "- GET /api/qr-codes/image/{studentId} - Get QR code image\n";
echo "- POST /api/qr-codes/validate - Validate QR code\n";
echo "- GET /api/qr-codes/student/{studentId} - Get student QR code info\n";

echo "\nQR Code System is ready for testing!\n";
