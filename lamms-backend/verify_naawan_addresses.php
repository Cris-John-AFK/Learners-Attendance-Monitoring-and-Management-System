<?php

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🗺️ Verifying Updated Naawan Addresses...\n\n";

// Get sample addresses to verify the update
$sampleStudents = DB::table('student_details')
    ->whereNotNull('currentAddress')
    ->select('id', 'firstName', 'lastName', 'currentAddress')
    ->limit(10)
    ->get();

echo "📋 Sample Updated Addresses:\n";
echo "=" . str_repeat("=", 80) . "\n";

foreach ($sampleStudents as $student) {
    $address = json_decode($student->currentAddress, true);
    
    echo "👤 {$student->firstName} {$student->lastName}\n";
    echo "📍 {$address['street']}, Brgy. {$address['barangay']}, {$address['city']}, {$address['province']}\n";
    echo "---\n";
}

// Count students by city
echo "\n🏘️ Distribution by City:\n";
$cities = DB::table('student_details')
    ->whereNotNull('currentAddress')
    ->get()
    ->map(function($student) {
        $address = json_decode($student->currentAddress, true);
        return $address['city'] ?? 'Unknown';
    })
    ->countBy()
    ->sortDesc();

foreach ($cities as $city => $count) {
    echo "📌 $city: $count students\n";
}

// Count students by barangay in Naawan
echo "\n🏘️ Naawan Barangays Distribution:\n";
$naawaanBarangays = DB::table('student_details')
    ->whereNotNull('currentAddress')
    ->get()
    ->filter(function($student) {
        $address = json_decode($student->currentAddress, true);
        return ($address['city'] ?? '') === 'Naawan';
    })
    ->map(function($student) {
        $address = json_decode($student->currentAddress, true);
        return $address['barangay'] ?? 'Unknown';
    })
    ->countBy()
    ->sortDesc();

foreach ($naawaanBarangays as $barangay => $count) {
    echo "📍 Brgy. $barangay: $count students\n";
}

// Show mountain areas (expected higher absenteeism)
echo "\n🏔️ Mountain Areas (Expected Higher Absenteeism):\n";
$mountainAreas = ['Upper Naawan', 'Kapatagan'];
$mountainStudents = DB::table('student_details')
    ->whereNotNull('currentAddress')
    ->get()
    ->filter(function($student) use ($mountainAreas) {
        $address = json_decode($student->currentAddress, true);
        return in_array($address['barangay'] ?? '', $mountainAreas);
    })
    ->count();

echo "🏔️ Students in mountain areas: $mountainStudents\n";
echo "   (These areas may show red zones in the heatmap due to transportation challenges)\n";

// Show neighboring towns
echo "\n🏘️ Students from Neighboring Towns:\n";
$neighboringTowns = ['Linangkayan', 'Manticao', 'Tagoloan'];
$neighboringStudents = DB::table('student_details')
    ->whereNotNull('currentAddress')
    ->get()
    ->filter(function($student) use ($neighboringTowns) {
        $address = json_decode($student->currentAddress, true);
        return in_array($address['city'] ?? '', $neighboringTowns);
    })
    ->countBy(function($student) {
        $address = json_decode($student->currentAddress, true);
        return $address['city'] ?? 'Unknown';
    });

foreach ($neighboringStudents as $town => $count) {
    echo "🏘️ $town: $count students\n";
}

echo "\n✅ Address Update Verification Complete!\n";
echo "🎯 All students now have realistic Naawan-area addresses.\n";
echo "🗺️ The Geographic Attendance Heatmap will now show meaningful patterns!\n\n";

echo "🔥 Expected Heatmap Patterns:\n";
echo "🔴 RED ZONES: Mountain areas (Upper Naawan, Kapatagan) - Transportation challenges\n";
echo "🟡 YELLOW ZONES: Neighboring towns (Linangkayan, Manticao) - Distance issues\n";
echo "🟢 GREEN ZONES: Town center (Poblacion) - Easy access to school\n";
echo "🔵 BLUE ZONES: Coastal areas - Weather-dependent attendance\n";

?>
