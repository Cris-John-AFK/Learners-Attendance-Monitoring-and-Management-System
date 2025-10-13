<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\API\QRCodeController;
use Illuminate\Http\Request;

echo "=== TESTING BULK QR API DIRECTLY ===\n";

// Create a mock request
$request = new Request();
$request->merge([
    'student_ids' => [3404, 3411, 3413, 3415]
]);

// Create controller instance
$controller = new QRCodeController();

try {
    // Call the bulk method directly
    $response = $controller->getBulkQRCodes($request);
    
    // Get the response data
    $responseData = $response->getData(true);
    
    echo "Response success: " . ($responseData['success'] ? 'Yes' : 'No') . "\n";
    echo "Found count: " . $responseData['found_count'] . "\n";
    echo "Requested count: " . $responseData['requested_count'] . "\n";
    
    if (isset($responseData['qr_codes'])) {
        echo "\nQR Codes returned:\n";
        foreach ($responseData['qr_codes'] as $studentId => $qrData) {
            echo "Student {$studentId}: " . gettype($qrData) . "\n";
            if (is_array($qrData)) {
                echo "Array contents: " . print_r($qrData, true) . "\n";
            } else {
                echo "Data: " . substr($qrData, 0, 100) . "...\n";
            }
            echo "---\n";
        }
    } else {
        echo "No QR codes in response!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
