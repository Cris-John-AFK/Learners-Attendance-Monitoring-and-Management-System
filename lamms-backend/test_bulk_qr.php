<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StudentQRCode;
use Illuminate\Support\Facades\DB;

echo "=== TESTING BULK QR CODE ENDPOINT ===\n";

// Test with a few student IDs from section 226
$studentIds = [3404, 3411, 3413, 3415];

echo "Testing with student IDs: " . implode(', ', $studentIds) . "\n\n";

// Check if QR codes exist for these students
echo "=== CHECKING QR CODES IN DATABASE ===\n";
$qrCodes = StudentQRCode::whereIn('student_id', $studentIds)
    ->where('is_active', true)
    ->with('student')
    ->get();

foreach ($qrCodes as $qrCode) {
    echo "Student ID: {$qrCode->student_id}\n";
    echo "QR Code ID: {$qrCode->id}\n";
    echo "Is Active: " . ($qrCode->is_active ? 'Yes' : 'No') . "\n";
    echo "QR Data Length: " . strlen($qrCode->qr_code_data) . " characters\n";
    echo "QR Data Preview: " . substr($qrCode->qr_code_data, 0, 100) . "...\n";
    echo "---\n";
}

echo "\nFound " . $qrCodes->count() . " QR codes out of " . count($studentIds) . " requested\n";

// Test the bulk endpoint logic
echo "\n=== TESTING BULK ENDPOINT LOGIC ===\n";
$qrCodesKeyed = $qrCodes->keyBy('student_id');
$result = [];

foreach ($studentIds as $studentId) {
    $qrCode = $qrCodesKeyed->get($studentId);
    if ($qrCode && $qrCode->qr_code_data) {
        $result[$studentId] = $qrCode->qr_code_data;
        echo "✅ Student {$studentId}: QR code found (" . strlen($qrCode->qr_code_data) . " chars)\n";
    } else {
        echo "❌ Student {$studentId}: No QR code found\n";
    }
}

echo "\nResult array keys: " . implode(', ', array_keys($result)) . "\n";
echo "Result count: " . count($result) . "\n";
