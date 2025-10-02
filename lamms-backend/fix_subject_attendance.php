<?php

echo "🔧 Fixing Subject-Specific Attendance Data\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    // Connect to database using Laravel's config
    require_once 'vendor/autoload.php';
    
    $app = require_once 'bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
    
    use Illuminate\Support\Facades\DB;
    
    echo "1. CHECKING CURRENT ATTENDANCE SESSIONS:\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $sessions = DB::table('attendance_sessions')
        ->where('teacher_id', 1)
        ->get();
    
    foreach ($sessions as $session) {
        echo "📅 Session {$session->id}: Date={$session->session_date}, Subject={$session->subject_id}, Section={$session->section_id}\n";
    }
    
    echo "\n2. UPDATING SESSIONS TO ENGLISH SUBJECT:\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    // Update attendance sessions to assign them to English (subject_id = 2)
    $updated = DB::table('attendance_sessions')
        ->where('teacher_id', 1)
        ->whereNull('subject_id')
        ->update([
            'subject_id' => 2, // English subject
            'updated_at' => now()
        ]);
    
    echo "✅ Updated {$updated} attendance sessions to English subject\n";
    
    echo "\n3. VERIFYING UPDATED SESSIONS:\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $updatedSessions = DB::table('attendance_sessions')
        ->where('teacher_id', 1)
        ->get();
    
    foreach ($updatedSessions as $session) {
        echo "📅 Session {$session->id}: Date={$session->session_date}, Subject={$session->subject_id}, Section={$session->section_id}\n";
    }
    
    echo "\n4. CHECKING ATTENDANCE RECORDS:\n";
    echo "-" . str_repeat("-", 40) . "\n";
    
    $records = DB::table('attendance_records as ar')
        ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
        ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
        ->where('ases.teacher_id', 1)
        ->select('ar.*', 'ases.session_date', 'ases.subject_id', 'ast.code as status_code')
        ->get();
    
    echo "📊 Found " . count($records) . " attendance records:\n";
    
    $statusCounts = [
        'P' => 0, // Present
        'A' => 0, // Absent  
        'L' => 0, // Late
        'E' => 0  // Excused
    ];
    
    foreach ($records as $record) {
        if (isset($statusCounts[$record->status_code])) {
            $statusCounts[$record->status_code]++;
        }
        
        // Show first few records as examples
        if ($statusCounts['P'] + $statusCounts['A'] + $statusCounts['L'] + $statusCounts['E'] <= 5) {
            echo "   📋 Student {$record->student_id}: {$record->status_code} on {$record->session_date} (Subject: {$record->subject_id})\n";
        }
    }
    
    echo "\n📈 ATTENDANCE SUMMARY:\n";
    echo "   Present: {$statusCounts['P']}\n";
    echo "   Absent: {$statusCounts['A']}\n";
    echo "   Late: {$statusCounts['L']}\n";
    echo "   Excused: {$statusCounts['E']}\n";
    echo "   Total: " . array_sum($statusCounts) . "\n";
    
    echo "\n🎉 SUCCESS! Attendance data is now associated with English subject.\n";
    echo "💡 Refresh your dashboard to see the English subject data!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 Line: " . $e->getLine() . "\n";
    echo "📁 File: " . $e->getFile() . "\n";
}
