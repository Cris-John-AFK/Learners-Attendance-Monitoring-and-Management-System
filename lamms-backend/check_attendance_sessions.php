<?php

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🔍 Checking Attendance Sessions Database Status\n";
echo "=" . str_repeat("=", 80) . "\n\n";

// Check if attendance_sessions table exists and its structure
if (Schema::hasTable('attendance_sessions')) {
    echo "✅ attendance_sessions table EXISTS\n";
    
    $columns = Schema::getColumnListing('attendance_sessions');
    echo "📋 Table columns:\n";
    foreach ($columns as $column) {
        echo "   • {$column}\n";
    }
    
    // Check current data
    $sessionCount = DB::table('attendance_sessions')->count();
    echo "\n📊 Current attendance sessions: {$sessionCount}\n";
    
    if ($sessionCount > 0) {
        echo "\n📋 Sample attendance sessions:\n";
        $sessions = DB::table('attendance_sessions as ases')
            ->leftJoin('teachers as t', 'ases.teacher_id', '=', 't.id')
            ->leftJoin('sections as s', 'ases.section_id', '=', 's.id')
            ->leftJoin('subjects as subj', 'ases.subject_id', '=', 'subj.id')
            ->select('ases.*', 't.first_name', 't.last_name', 's.name as section_name', 'subj.name as subject_name')
            ->limit(10)
            ->get();
        
        foreach ($sessions as $session) {
            $teacher = "{$session->first_name} {$session->last_name}";
            echo "   📅 {$session->session_date} - {$teacher} - {$session->subject_name} - {$session->section_name}\n";
        }
    }
    
} else {
    echo "❌ attendance_sessions table DOES NOT EXIST!\n";
}

echo "\n" . str_repeat("-", 80) . "\n";

// Check attendance_records table
if (Schema::hasTable('attendance_records')) {
    echo "✅ attendance_records table EXISTS\n";
    
    $recordCount = DB::table('attendance_records')->count();
    echo "📊 Current attendance records: {$recordCount}\n";
    
    if ($recordCount > 0) {
        echo "\n📋 Sample attendance records:\n";
        $records = DB::table('attendance_records as ar')
            ->leftJoin('student_details as sd', 'ar.student_id', '=', 'sd.id')
            ->leftJoin('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
            ->leftJoin('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->select('ar.*', 'sd.firstName', 'sd.lastName', 'ast.name as status_name', 'ases.session_date')
            ->limit(10)
            ->get();
        
        foreach ($records as $record) {
            echo "   👤 {$record->firstName} {$record->lastName} - {$record->status_name} - {$record->session_date}\n";
        }
        
        // Check records by status
        echo "\n📊 Records by status:\n";
        $statusCounts = DB::table('attendance_records as ar')
            ->leftJoin('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
            ->select('ast.name', 'ast.code', DB::raw('COUNT(*) as count'))
            ->groupBy('ast.name', 'ast.code')
            ->get();
        
        foreach ($statusCounts as $status) {
            echo "   • {$status->name} ({$status->code}): {$status->count} records\n";
        }
    }
    
} else {
    echo "❌ attendance_records table DOES NOT EXIST!\n";
}

echo "\n" . str_repeat("-", 80) . "\n";

// Check attendance_statuses table
if (Schema::hasTable('attendance_statuses')) {
    echo "✅ attendance_statuses table EXISTS\n";
    
    $statuses = DB::table('attendance_statuses')->get();
    echo "📊 Available attendance statuses:\n";
    foreach ($statuses as $status) {
        echo "   • {$status->name} ({$status->code}) - ID: {$status->id}\n";
    }
    
} else {
    echo "❌ attendance_statuses table DOES NOT EXIST!\n";
}

echo "\n" . str_repeat("-", 80) . "\n";

// Check teacher assignments for Maria Santos (ID: 1)
echo "👩‍🏫 Checking Maria Santos (Teacher ID: 1) assignments:\n";
$assignments = DB::table('teacher_section_subject as tss')
    ->leftJoin('sections as s', 'tss.section_id', '=', 's.id')
    ->leftJoin('subjects as subj', 'tss.subject_id', '=', 'subj.id')
    ->where('tss.teacher_id', 1)
    ->where('tss.is_active', true)
    ->select('tss.*', 's.name as section_name', 'subj.name as subject_name')
    ->get();

if ($assignments->count() > 0) {
    echo "✅ Found {$assignments->count()} assignments:\n";
    foreach ($assignments as $assignment) {
        echo "   📚 {$assignment->subject_name} - {$assignment->section_name} (Section ID: {$assignment->section_id})\n";
        
        // Check students in this section
        $studentCount = DB::table('student_section as ss')
            ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
            ->where('ss.section_id', $assignment->section_id)
            ->where('ss.is_active', true)
            ->count();
        
        $studentsWithAddresses = DB::table('student_section as ss')
            ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
            ->where('ss.section_id', $assignment->section_id)
            ->where('ss.is_active', true)
            ->whereNotNull('sd.currentAddress')
            ->count();
        
        echo "      👥 Students: {$studentCount} total, {$studentsWithAddresses} with addresses\n";
    }
} else {
    echo "❌ No assignments found for Maria Santos!\n";
}

echo "\n" . "=" . str_repeat("=", 80) . "\n";
echo "🎯 SUMMARY:\n";

$totalSessions = DB::table('attendance_sessions')->count();
$totalRecords = DB::table('attendance_records')->count();

if ($totalSessions > 0 && $totalRecords > 0) {
    echo "✅ Database has attendance data: {$totalSessions} sessions, {$totalRecords} records\n";
    echo "✅ Geographic heatmap should work with existing data!\n";
    echo "🗺️ You can test the heatmap API now:\n";
    echo "   GET /api/geographic-attendance/heatmap-data?teacher_id=1\n";
} else {
    echo "❌ No attendance data found in database\n";
    echo "⚠️ Need to create attendance sessions and records first\n";
    echo "💡 The heatmap needs actual attendance data to display patterns\n";
}

?>
