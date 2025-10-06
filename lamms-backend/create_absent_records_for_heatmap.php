<?php

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "🗺️ Creating Absent Records for Geographic Heatmap Testing\n";
echo "=" . str_repeat("=", 80) . "\n\n";

// Get Maria Santos' students with addresses
$students = DB::table('teacher_section_subject as tss')
    ->join('student_section as ss', 'tss.section_id', '=', 'ss.section_id')
    ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
    ->where('tss.teacher_id', 1)
    ->where('tss.is_active', true)
    ->where('ss.is_active', true)
    ->whereNotNull('sd.currentAddress')
    ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.currentAddress', 'tss.section_id', 'tss.subject_id')
    ->distinct()
    ->get();

echo "👥 Found {$students->count()} students with addresses\n";

// Get attendance statuses
$absentId = DB::table('attendance_statuses')->where('code', 'A')->value('id');
$lateId = DB::table('attendance_statuses')->where('code', 'L')->value('id');

if (!$absentId || !$lateId) {
    echo "❌ Attendance statuses not found!\n";
    exit;
}

echo "✅ Attendance status IDs: Absent={$absentId}, Late={$lateId}\n";

// Create attendance sessions for the last few days
$dates = [
    Carbon::now()->subDays(3)->format('Y-m-d'),
    Carbon::now()->subDays(2)->format('Y-m-d'),
    Carbon::now()->subDays(1)->format('Y-m-d'),
    Carbon::now()->format('Y-m-d')
];

$sessionsCreated = 0;
$recordsCreated = 0;

foreach ($dates as $date) {
    echo "\n📅 Creating sessions for {$date}:\n";
    
    // Create session for English (subject_id = 2)
    $sessionId = DB::table('attendance_sessions')->insertGetId([
        'teacher_id' => 1,
        'section_id' => 288, // Sampaguita section
        'subject_id' => 2,   // English
        'session_date' => $date,
        'status' => 'completed',
        'created_at' => Carbon::parse($date)->setTime(8, 0, 0),
        'updated_at' => Carbon::parse($date)->setTime(8, 0, 0)
    ]);
    
    $sessionsCreated++;
    echo "   ✅ Created session ID: {$sessionId}\n";
    
    // Create attendance records with geographic patterns
    $sessionRecords = 0;
    
    foreach ($students as $student) {
        $address = json_decode($student->currentAddress, true);
        $barangay = $address['barangay'] ?? 'Unknown';
        $city = $address['city'] ?? 'Unknown';
        
        // Determine attendance pattern based on location
        $attendanceStatusId = 1; // Default to present (ID: 1)
        $shouldCreateRecord = false;
        
        // Mountain areas (higher absenteeism) - CREATE ABSENT RECORDS
        if (in_array($barangay, ['Upper Naawan', 'Kapatagan'])) {
            if (rand(1, 100) <= 30) { // 30% chance of absence
                $attendanceStatusId = $absentId;
                $shouldCreateRecord = true;
            } elseif (rand(1, 100) <= 20) { // 20% chance of late
                $attendanceStatusId = $lateId;
                $shouldCreateRecord = true;
            }
        } 
        // Neighboring towns (distance issues) - CREATE SOME ABSENT RECORDS
        elseif (in_array($city, ['Linangkayan', 'Manticao', 'Tagoloan'])) {
            if (rand(1, 100) <= 20) { // 20% chance of absence
                $attendanceStatusId = $absentId;
                $shouldCreateRecord = true;
            } elseif (rand(1, 100) <= 25) { // 25% chance of late
                $attendanceStatusId = $lateId;
                $shouldCreateRecord = true;
            }
        }
        // Coastal areas (weather dependent) - CREATE SOME ABSENT RECORDS
        elseif (in_array($barangay, ['Baybay', 'Malubog', 'Talisay'])) {
            if (rand(1, 100) <= 15) { // 15% chance of absence
                $attendanceStatusId = $absentId;
                $shouldCreateRecord = true;
            } elseif (rand(1, 100) <= 20) { // 20% chance of late
                $attendanceStatusId = $lateId;
                $shouldCreateRecord = true;
            }
        }
        // Rural areas - CREATE SOME ABSENT RECORDS
        elseif (in_array($barangay, ['Libertad', 'Mapulog', 'Camaman-an', 'Tubajon'])) {
            if (rand(1, 100) <= 12) { // 12% chance of absence
                $attendanceStatusId = $absentId;
                $shouldCreateRecord = true;
            } elseif (rand(1, 100) <= 15) { // 15% chance of late
                $attendanceStatusId = $lateId;
                $shouldCreateRecord = true;
            }
        }
        // Town center (good attendance) - MINIMAL ABSENT RECORDS
        elseif ($barangay === 'Poblacion') {
            if (rand(1, 100) <= 5) { // 5% chance of absence
                $attendanceStatusId = $absentId;
                $shouldCreateRecord = true;
            } elseif (rand(1, 100) <= 8) { // 8% chance of late
                $attendanceStatusId = $lateId;
                $shouldCreateRecord = true;
            }
        }
        
        // Only create records for absent/late students (not present)
        if ($shouldCreateRecord) {
            DB::table('attendance_records')->insert([
                'student_id' => $student->id,
                'attendance_session_id' => $sessionId,
                'attendance_status_id' => $attendanceStatusId,
                'recorded_at' => Carbon::parse($date)->setTime(8, rand(0, 30), 0),
                'created_at' => Carbon::parse($date)->setTime(8, 0, 0),
                'updated_at' => Carbon::parse($date)->setTime(8, 0, 0)
            ]);
            
            $recordsCreated++;
            $sessionRecords++;
            
            $statusName = $attendanceStatusId == $absentId ? 'ABSENT' : 'LATE';
            echo "      📍 {$student->firstName} {$student->lastName} - {$statusName} - {$barangay}, {$city}\n";
        }
    }
    
    echo "   📊 Created {$sessionRecords} attendance records for this session\n";
}

echo "\n" . "=" . str_repeat("=", 80) . "\n";
echo "🎉 Absent Records Created Successfully!\n";
echo "✅ Sessions created: {$sessionsCreated}\n";
echo "✅ Attendance records: {$recordsCreated}\n";

// Verify the data
echo "\n🔍 Verification - Checking absent students:\n";
$absentStudents = DB::table('attendance_records as ar')
    ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->join('student_details as sd', 'ar.student_id', '=', 'sd.id')
    ->where('ast.code', 'A')
    ->where('ases.teacher_id', 1)
    ->whereNotNull('sd.currentAddress')
    ->select('sd.firstName', 'sd.lastName', 'sd.currentAddress', 'ases.session_date')
    ->get();

echo "📊 Found {$absentStudents->count()} absent records with addresses\n";

if ($absentStudents->count() > 0) {
    // Group by location
    $locationGroups = [];
    foreach ($absentStudents as $student) {
        $address = json_decode($student->currentAddress, true);
        $barangay = $address['barangay'] ?? 'Unknown';
        $city = $address['city'] ?? 'Unknown';
        $locationKey = "{$city}_{$barangay}";
        
        if (!isset($locationGroups[$locationKey])) {
            $locationGroups[$locationKey] = 0;
        }
        $locationGroups[$locationKey]++;
    }
    
    echo "\n🗺️ EXPECTED HEATMAP PATTERNS:\n";
    foreach ($locationGroups as $location => $count) {
        list($city, $barangay) = explode('_', $location);
        echo "   📍 {$barangay}, {$city}: {$count} absences\n";
    }
    
    echo "\n🎯 NOW YOU CAN TEST THE HEATMAP:\n";
    echo "1. API Test: GET /api/geographic-attendance/heatmap-data?teacher_id=1&attendance_status=absent\n";
    echo "2. The heatmap should show red zones in mountain areas\n";
    echo "3. Yellow zones in neighboring towns\n";
    echo "4. Green zones in town center\n";
    
} else {
    echo "⚠️ No absent records created - try running again\n";
}

?>
