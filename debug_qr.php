<?php
require_once 'lamms-backend/vendor/autoload.php';
$app = require_once 'lamms-backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StudentQRCode;

echo "Testing QR Code Generation\n";
echo "========================\n\n";

try {
    // Test if QR code class exists
    if (class_exists('SimpleSoftwareIO\QrCode\Facades\QrCode')) {
        echo "✓ QrCode facade is available\n";
        
        // Test basic QR code generation
        $testData = "LAMMS_TEST_" . time();
        echo "Testing with data: " . $testData . "\n";
        
        $qrCodeImage = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                             ->size(300)
                             ->margin(2)
                             ->generate($testData);
        
        echo "✓ QR code generated successfully\n";
        echo "Image size: " . strlen($qrCodeImage) . " bytes\n";
        
        // Test with student QR code
        $studentQR = StudentQRCode::where('student_id', 4)->where('is_active', true)->first();
        if ($studentQR) {
            echo "\nTesting with student QR data: " . $studentQR->qr_code_data . "\n";
            
            $studentQrImage = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('png')
                                   ->size(300)
                                   ->margin(2)
                                   ->generate($studentQR->qr_code_data);
            
            echo "✓ Student QR code generated successfully\n";
            echo "Student QR Image size: " . strlen($studentQrImage) . " bytes\n";
        } else {
            echo "✗ No QR code found for student ID 4\n";
        }
        
    } else {
        echo "✗ QrCode facade not available\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\nDone.\n";
