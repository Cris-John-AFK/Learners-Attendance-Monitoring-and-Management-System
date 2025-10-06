<?php

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🗺️ Simple Heatmap Test - Find Absent Students with Addresses\n";
echo "=" . str_repeat("=", 80) . "\n\n";

// Direct query to find absent students with addresses
$absentStudents = DB::table('attendance_records as ar')
    ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->join('student_details as sd', 'ar.student_id', '=', 'sd.id')
    ->where('ast.code', 'A') // Absent
    ->where('ases.teacher_id', 1) // Maria Santos
    ->whereNotNull('sd.currentAddress')
    ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.currentAddress', 'ases.session_date', 'ast.name as status')
    ->get();

echo "🔍 Found {$absentStudents->count()} absent students with addresses:\n\n";

if ($absentStudents->count() > 0) {
    $locationGroups = [];
    
    foreach ($absentStudents as $student) {
        $address = json_decode($student->currentAddress, true);
        if (!$address) continue;
        
        $barangay = $address['barangay'] ?? 'Unknown';
        $city = $address['city'] ?? 'Unknown';
        $locationKey = "{$city}_{$barangay}";
        
        if (!isset($locationGroups[$locationKey])) {
            $locationGroups[$locationKey] = [
                'location' => $address,
                'students' => [],
                'total_absences' => 0
            ];
        }
        
        $locationGroups[$locationKey]['students'][] = [
            'name' => "{$student->firstName} {$student->lastName}",
            'date' => $student->session_date
        ];
        $locationGroups[$locationKey]['total_absences']++;
        
        echo "👤 {$student->firstName} {$student->lastName} - {$barangay}, {$city} - {$student->session_date}\n";
    }
    
    echo "\n🗺️ GEOGRAPHIC GROUPING:\n";
    foreach ($locationGroups as $locationKey => $data) {
        $location = $data['location'];
        $barangay = $location['barangay'];
        $city = $location['city'];
        $count = $data['total_absences'];
        
        echo "📍 {$barangay}, {$city}: {$count} absences\n";
        foreach ($data['students'] as $student) {
            echo "   • {$student['name']} ({$student['date']})\n";
        }
        echo "\n";
    }
    
    // Create simple heatmap data structure
    $heatmapData = [];
    foreach ($locationGroups as $locationKey => $data) {
        $location = $data['location'];
        
        // Generate coordinates for Naawan area
        $baseLatitude = 8.4304;
        $baseLongitude = 124.2897;
        $barangay = strtolower($location['barangay'] ?? 'naawan');
        $latOffset = (crc32($barangay) % 1000) / 100000;
        $lngOffset = (crc32($barangay) % 1000) / 100000;
        
        $heatmapData[] = [
            'location' => $location,
            'coordinates' => [
                'latitude' => $baseLatitude + $latOffset,
                'longitude' => $baseLongitude + $lngOffset
            ],
            'students' => $data['students'],
            'total_incidents' => $data['total_absences'],
            'incident_type' => 'absent',
            'intensity' => min(1.0, $data['total_absences'] / 5) // Scale intensity
        ];
    }
    
    echo "🎯 HEATMAP DATA STRUCTURE:\n";
    echo json_encode($heatmapData, JSON_PRETTY_PRINT);
    
    echo "\n✅ SUCCESS! The heatmap has data to display:\n";
    echo "   • {$absentStudents->count()} absent records\n";
    echo "   • " . count($locationGroups) . " geographic locations\n";
    echo "   • Ready for map visualization!\n";
    
} else {
    echo "❌ No absent students found with addresses\n";
    echo "🔧 Need to create some absent attendance records first\n";
    
    // Check what attendance data exists
    echo "\n📊 Available attendance data:\n";
    $allRecords = DB::table('attendance_records as ar')
        ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
        ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
        ->where('ases.teacher_id', 1)
        ->select('ast.code', 'ast.name', DB::raw('COUNT(*) as count'))
        ->groupBy('ast.code', 'ast.name')
        ->get();
    
    foreach ($allRecords as $record) {
        echo "   • {$record->name} ({$record->code}): {$record->count} records\n";
    }
}

?>
