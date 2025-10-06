<?php

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

echo "🗺️ Creating Test Attendance Data for Geographic Heatmap\n";
echo "=" . str_repeat("=", 80) . "\n\n";

// Get teacher Maria Santos (ID: 1)
$teacher = DB::table('teachers')->where('id', 1)->first();
if (!$teacher) {
    echo "❌ Teacher ID 1 not found!\n";
    exit;
}

echo "👩‍🏫 Creating attendance for teacher: {$teacher->first_name} {$teacher->last_name}\n";

// Get teacher's assignments
$assignments = DB::table('teacher_section_subject as tss')
    ->join('sections as s', 'tss.section_id', '=', 's.id')
    ->join('subjects as subj', 'tss.subject_id', '=', 'subj.id')
    ->where('tss.teacher_id', 1)
    ->where('tss.is_active', true)
    ->select('tss.*', 's.name as section_name', 'subj.name as subject_name')
    ->get();

if ($assignments->isEmpty()) {
    echo "❌ No assignments found for teacher ID 1!\n";
    exit;
}

echo "📚 Found " . $assignments->count() . " subject assignments\n";

// Get attendance statuses
$statuses = DB::table('attendance_statuses')->get()->keyBy('code');
$presentId = $statuses['P']->id ?? null;
$absentId = $statuses['A']->id ?? null;
$lateId = $statuses['L']->id ?? null;

if (!$presentId || !$absentId || !$lateId) {
    echo "❌ Attendance statuses not found! Creating them...\n";
    
    // Create attendance statuses
    $presentId = DB::table('attendance_statuses')->insertGetId([
        'name' => 'Present',
        'code' => 'P',
        'description' => 'Student is present',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $absentId = DB::table('attendance_statuses')->insertGetId([
        'name' => 'Absent', 
        'code' => 'A',
        'description' => 'Student is absent',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    $lateId = DB::table('attendance_statuses')->insertGetId([
        'name' => 'Late',
        'code' => 'L', 
        'description' => 'Student is late',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now()
    ]);
    
    echo "✅ Created attendance statuses\n";
}

// Create attendance sessions for the last 30 days
$startDate = Carbon::now()->subDays(30);
$endDate = Carbon::now();
$sessionsCreated = 0;
$recordsCreated = 0;

echo "\n📅 Creating attendance sessions from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}\n";

foreach ($assignments as $assignment) {
    echo "\n📖 Processing {$assignment->subject_name} - {$assignment->section_name}\n";
    
    // Get students in this section with addresses
    $students = DB::table('student_details as sd')
        ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
        ->where('ss.section_id', $assignment->section_id)
        ->where('ss.is_active', true)
        ->whereNotNull('sd.currentAddress')
        ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.currentAddress')
        ->get();
    
    if ($students->isEmpty()) {
        echo "   ⚠️ No students with addresses found in this section\n";
        continue;
    }
    
    echo "   👥 Found {$students->count()} students with addresses\n";
    
    // Create attendance sessions (3 times per week for the last 30 days)
    $currentDate = $startDate->copy();
    $sessionCount = 0;
    
    while ($currentDate->lte($endDate)) {
        // Skip weekends
        if ($currentDate->isWeekend()) {
            $currentDate->addDay();
            continue;
        }
        
        // Create session 3 times per week (Mon, Wed, Fri)
        if (in_array($currentDate->dayOfWeek, [1, 3, 5])) { // Monday, Wednesday, Friday
            
            // Create attendance session
            $sessionId = DB::table('attendance_sessions')->insertGetId([
                'teacher_id' => $assignment->teacher_id,
                'section_id' => $assignment->section_id,
                'subject_id' => $assignment->subject_id,
                'session_date' => $currentDate->format('Y-m-d'),
                'session_time' => '08:00:00',
                'status' => 'completed',
                'created_at' => $currentDate->copy()->setTime(8, 0, 0),
                'updated_at' => $currentDate->copy()->setTime(8, 0, 0)
            ]);
            
            $sessionsCreated++;
            $sessionRecords = 0;
            
            // Create attendance records for each student with geographic patterns
            foreach ($students as $student) {
                $address = json_decode($student->currentAddress, true);
                $barangay = $address['barangay'] ?? 'Unknown';
                $city = $address['city'] ?? 'Unknown';
                
                // Determine attendance pattern based on location
                $attendanceStatusId = $presentId; // Default to present
                
                // Mountain areas (higher absenteeism)
                if (in_array($barangay, ['Upper Naawan', 'Kapatagan'])) {
                    $absentChance = 25; // 25% chance of absence
                    $lateChance = 15;   // 15% chance of being late
                } 
                // Neighboring towns (distance issues)
                elseif (in_array($city, ['Linangkayan', 'Manticao', 'Tagoloan'])) {
                    $absentChance = 15; // 15% chance of absence
                    $lateChance = 20;   // 20% chance of being late
                }
                // Coastal areas (weather dependent)
                elseif (in_array($barangay, ['Baybay', 'Malubog', 'Talisay'])) {
                    $absentChance = 10; // 10% chance of absence
                    $lateChance = 15;   // 15% chance of being late
                }
                // Town center (easy access)
                elseif ($barangay === 'Poblacion') {
                    $absentChance = 5;  // 5% chance of absence
                    $lateChance = 8;    // 8% chance of being late
                }
                // Rural areas
                else {
                    $absentChance = 12; // 12% chance of absence
                    $lateChance = 12;   // 12% chance of being late
                }
                
                // Determine status based on probabilities
                $rand = rand(1, 100);
                if ($rand <= $absentChance) {
                    $attendanceStatusId = $absentId;
                } elseif ($rand <= $absentChance + $lateChance) {
                    $attendanceStatusId = $lateId;
                }
                
                // Create attendance record
                DB::table('attendance_records')->insert([
                    'student_id' => $student->id,
                    'attendance_session_id' => $sessionId,
                    'attendance_status_id' => $attendanceStatusId,
                    'recorded_at' => $currentDate->copy()->setTime(8, rand(0, 30), 0),
                    'created_at' => $currentDate->copy()->setTime(8, 0, 0),
                    'updated_at' => $currentDate->copy()->setTime(8, 0, 0)
                ]);
                
                $recordsCreated++;
                $sessionRecords++;
            }
            
            if ($sessionCount % 5 === 0) {
                echo "   📅 Created session for {$currentDate->format('M d')} with {$sessionRecords} records\n";
            }
            $sessionCount++;
        }
        
        $currentDate->addDay();
    }
    
    echo "   ✅ Created {$sessionCount} sessions for {$assignment->subject_name}\n";
}

echo "\n" . "=" . str_repeat("=", 80) . "\n";
echo "🎉 Test Attendance Data Created!\n";
echo "✅ Sessions created: {$sessionsCreated}\n";
echo "✅ Attendance records: {$recordsCreated}\n";

echo "\n🗺️ EXPECTED HEATMAP PATTERNS:\n";
echo "🔴 RED ZONES (High Absenteeism):\n";
echo "   • Upper Naawan, Kapatagan (Mountain areas - 25% absence rate)\n";
echo "🟡 YELLOW ZONES (Moderate Issues):\n";
echo "   • Linangkayan, Manticao, Tagoloan (Distance - 15% absence, 20% late)\n";
echo "🔵 BLUE ZONES (Weather Dependent):\n";
echo "   • Baybay, Malubog, Talisay (Coastal - 10% absence, 15% late)\n";
echo "🟢 GREEN ZONES (Good Attendance):\n";
echo "   • Poblacion (Town center - 5% absence, 8% late)\n";

echo "\n🎯 NOW YOU CAN:\n";
echo "1. Refresh your teacher dashboard\n";
echo "2. Go to the Geographic Attendance Heatmap\n";
echo "3. See realistic attendance patterns by location!\n";
echo "4. Filter by 'Absent' to see red zones in mountain areas\n";
echo "5. Filter by 'Late' to see yellow zones in distant areas\n";

echo "\n📊 Test the API:\n";
echo "GET /api/geographic-attendance/heatmap-data?teacher_id=1&attendance_status=absent\n";

?>
