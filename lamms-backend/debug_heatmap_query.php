<?php

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🔍 Debugging Geographic Heatmap Query\n";
echo "=" . str_repeat("=", 80) . "\n\n";

$teacherId = 1;
$startDate = '2025-10-01';
$endDate = '2025-10-05';
$attendanceStatus = 'absent';

echo "🎯 Query Parameters:\n";
echo "   • Teacher ID: {$teacherId}\n";
echo "   • Date Range: {$startDate} to {$endDate}\n";
echo "   • Status: {$attendanceStatus}\n\n";

// Step 1: Check teacher assignments
echo "📚 Step 1: Teacher Assignments\n";
$assignments = DB::table('teacher_section_subject as tss')
    ->where('tss.teacher_id', $teacherId)
    ->where('tss.is_active', true)
    ->get();

echo "   Found {$assignments->count()} assignments\n";
foreach ($assignments as $assignment) {
    echo "   • Section {$assignment->section_id}, Subject {$assignment->subject_id}\n";
}

// Step 2: Check students in those sections
echo "\n👥 Step 2: Students in Teacher's Sections\n";
$students = DB::table('teacher_section_subject as tss')
    ->join('student_section as ss', 'tss.section_id', '=', 'ss.section_id')
    ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
    ->where('tss.teacher_id', $teacherId)
    ->where('tss.is_active', true)
    ->where('ss.is_active', true)
    ->whereNotNull('sd.currentAddress')
    ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.currentAddress')
    ->distinct()
    ->get();

echo "   Found {$students->count()} students with addresses\n";
if ($students->count() > 0) {
    echo "   Sample students:\n";
    foreach ($students->take(5) as $student) {
        $address = json_decode($student->currentAddress, true);
        $location = $address ? "{$address['barangay']}, {$address['city']}" : "No address";
        echo "      • {$student->firstName} {$student->lastName} - {$location}\n";
    }
}

// Step 3: Check attendance records for these students
echo "\n📊 Step 3: Attendance Records\n";
$records = DB::table('attendance_records as ar')
    ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->join('student_details as sd', 'ar.student_id', '=', 'sd.id')
    ->where('ases.teacher_id', $teacherId)
    ->whereBetween('ases.session_date', [$startDate, $endDate])
    ->select('ar.*', 'ases.session_date', 'ast.code', 'ast.name as status_name', 'sd.firstName', 'sd.lastName')
    ->get();

echo "   Found {$records->count()} attendance records in date range\n";

if ($records->count() > 0) {
    $statusCounts = $records->groupBy('code');
    echo "   Records by status:\n";
    foreach ($statusCounts as $code => $statusRecords) {
        echo "      • {$code}: {$statusRecords->count()} records\n";
    }
    
    echo "\n   Sample records:\n";
    foreach ($records->take(10) as $record) {
        echo "      • {$record->firstName} {$record->lastName} - {$record->status_name} - {$record->session_date}\n";
    }
}

// Step 4: Test the full heatmap query
echo "\n🗺️ Step 4: Full Heatmap Query Test\n";

$query = DB::table('teacher_section_subject as tss')
    ->join('student_section as ss', 'tss.section_id', '=', 'ss.section_id')
    ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
    ->leftJoin('attendance_records as ar', function($join) use ($startDate, $endDate) {
        $join->on('sd.id', '=', 'ar.student_id');
    })
    ->leftJoin('attendance_sessions as ases', function($join) use ($startDate, $endDate) {
        $join->on('ar.attendance_session_id', '=', 'ases.id')
             ->whereBetween('ases.session_date', [$startDate, $endDate]);
    })
    ->leftJoin('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->where('tss.teacher_id', $teacherId)
    ->where('tss.is_active', true)
    ->where('ss.is_active', true)
    ->whereNotNull('sd.currentAddress');

// Filter by attendance status if specified
if ($attendanceStatus) {
    $statusCodes = [
        'absent' => 'A',
        'late' => 'L',
        'excused' => 'E',
        'present' => 'P'
    ];
    
    if (isset($statusCodes[$attendanceStatus])) {
        $query->where('ast.code', $statusCodes[$attendanceStatus]);
    }
}

$results = $query->select([
    'sd.id as student_id',
    'sd.firstName',
    'sd.lastName',
    'sd.currentAddress',
    'ast.code as attendance_status',
    'ases.session_date',
    DB::raw('COUNT(ar.id) as total_records')
])
->groupBy('sd.id', 'sd.firstName', 'sd.lastName', 'ast.code', 'ases.session_date')
->get();

echo "   Heatmap query returned {$results->count()} results\n";

if ($results->count() > 0) {
    echo "   Sample results:\n";
    foreach ($results->take(10) as $result) {
        $address = json_decode($result->currentAddress, true);
        $location = $address ? "{$address['barangay']}, {$address['city']}" : "No address";
        echo "      • {$result->firstName} {$result->lastName} - {$result->attendance_status} - {$location} - {$result->total_records} records\n";
    }
} else {
    echo "   ❌ No results found!\n";
    echo "\n🔍 Debugging why no results:\n";
    
    // Check if the issue is with the date range
    echo "   Testing without date filter...\n";
    $testQuery = DB::table('teacher_section_subject as tss')
        ->join('student_section as ss', 'tss.section_id', '=', 'ss.section_id')
        ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
        ->leftJoin('attendance_records as ar', 'sd.id', '=', 'ar.student_id')
        ->leftJoin('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
        ->leftJoin('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
        ->where('tss.teacher_id', $teacherId)
        ->where('tss.is_active', true)
        ->where('ss.is_active', true)
        ->whereNotNull('sd.currentAddress')
        ->where('ast.code', 'A')
        ->select('sd.firstName', 'sd.lastName', 'ast.code', 'ases.session_date')
        ->get();
    
    echo "   Without date filter: {$testQuery->count()} results\n";
    
    if ($testQuery->count() > 0) {
        echo "   Issue is with date range filtering!\n";
        foreach ($testQuery->take(5) as $test) {
            echo "      • {$test->firstName} {$test->lastName} - {$test->code} - {$test->session_date}\n";
        }
    }
}

echo "\n" . "=" . str_repeat("=", 80) . "\n";
echo "🎯 DIAGNOSIS:\n";

if ($results->count() > 0) {
    echo "✅ Heatmap query is working correctly!\n";
    echo "🗺️ Geographic data should display on the map\n";
} else {
    echo "❌ Heatmap query is not returning results\n";
    echo "🔧 Need to fix the query logic or date filtering\n";
}

?>
