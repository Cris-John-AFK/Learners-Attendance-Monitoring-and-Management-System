<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking Filipino subject (ID: 3) attendance records:\n\n";

// Get all students with Filipino attendance
$filipinoRecords = DB::table('attendance_records as ar')
    ->join('attendance_sessions as ase', 'ar.attendance_session_id', '=', 'ase.id')
    ->join('student_details as sd', 'ar.student_id', '=', 'sd.id')
    ->join('subjects as s', 'ase.subject_id', '=', 's.id')
    ->where('ase.subject_id', 3) // Filipino
    ->select('sd.id', 'sd.firstName', 'sd.lastName', DB::raw('COUNT(*) as record_count'))
    ->groupBy('sd.id', 'sd.firstName', 'sd.lastName')
    ->orderBy('sd.id')
    ->get();

echo "Students with Filipino attendance records:\n";
echo str_repeat('-', 60) . "\n";
foreach ($filipinoRecords as $record) {
    echo sprintf("Student ID: %d | Name: %s %s | Records: %d\n", 
        $record->id,
        $record->firstName,
        $record->lastName,
        $record->record_count
    );
}

echo "\n\nNow checking 'One Kind' (Student ID 14):\n\n";

// Get all subjects for student 14
$student14Subjects = DB::table('attendance_records as ar')
    ->join('attendance_sessions as ase', 'ar.attendance_session_id', '=', 'ase.id')
    ->join('subjects as s', 'ase.subject_id', '=', 's.id')
    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->where('ar.student_id', 14)
    ->select('s.id as subject_id', 's.name as subject', 'ase.session_date', 'ast.code', 'ast.name as status')
    ->orderBy('ase.session_date')
    ->get();

$subjectSummary = [];
foreach ($student14Subjects as $record) {
    $key = $record->subject_id . ':' . $record->subject;
    if (!isset($subjectSummary[$key])) {
        $subjectSummary[$key] = ['P' => 0, 'A' => 0, 'L' => 0, 'E' => 0, 'dates' => []];
    }
    if (!isset($subjectSummary[$key][$record->code])) {
        $subjectSummary[$key][$record->code] = 0;
    }
    $subjectSummary[$key][$record->code]++;
    $subjectSummary[$key]['dates'][] = $record->session_date;
}

echo "Subject Summary for 'One Kind' (ID: 14):\n";
echo str_repeat('-', 60) . "\n";
foreach ($subjectSummary as $subject => $data) {
    echo sprintf("%s: P=%d, A=%d, L=%d | Dates: %s\n", 
        $subject, 
        $data['P'], 
        $data['A'], 
        $data['L'],
        implode(', ', array_unique($data['dates']))
    );
}

echo "\n\nChecking Filipino sessions created:\n\n";

// Check all Filipino sessions
$filipinoSessions = DB::table('attendance_sessions')
    ->join('subjects as s', 'attendance_sessions.subject_id', '=', 's.id')
    ->join('sections as sec', 'attendance_sessions.section_id', '=', 'sec.id')
    ->where('attendance_sessions.subject_id', 3)
    ->select('attendance_sessions.id', 'attendance_sessions.session_date', 'sec.section_name', 
             DB::raw('(SELECT COUNT(*) FROM attendance_records WHERE attendance_session_id = attendance_sessions.id) as student_count'))
    ->orderBy('attendance_sessions.session_date')
    ->get();

echo "Filipino sessions:\n";
echo str_repeat('-', 60) . "\n";
foreach ($filipinoSessions as $session) {
    echo sprintf("Session ID: %d | Date: %s | Section: %s | Students: %d\n",
        $session->id,
        $session->session_date,
        $session->section_name,
        $session->student_count
    );
}
