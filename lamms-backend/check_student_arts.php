<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking Arts attendance for Student ID 16 in September 2025:\n\n";

$records = DB::table('attendance_records as ar')
    ->join('attendance_sessions as ase', 'ar.attendance_session_id', '=', 'ase.id')
    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->join('subjects as s', 'ase.subject_id', '=', 's.id')
    ->where('ar.student_id', 16)
    ->where('ase.subject_id', 5) // Arts
    ->where('ase.session_date', '>=', '2025-09-01')
    ->where('ase.session_date', '<=', '2025-09-30')
    ->select('ase.session_date', 's.name as subject', 'ast.code', 'ast.name as status')
    ->orderBy('ase.session_date')
    ->get();

if ($records->isEmpty()) {
    echo "NO RECORDS FOUND for Arts subject!\n";
} else {
    echo "Found " . $records->count() . " records:\n";
    echo str_repeat('-', 60) . "\n";
    foreach ($records as $record) {
        echo sprintf("%s | %s | %s (%s)\n", 
            $record->session_date, 
            $record->subject, 
            $record->status,
            $record->code
        );
    }
}

echo "\n\nNow checking ALL subjects for this student in September:\n\n";

$allRecords = DB::table('attendance_records as ar')
    ->join('attendance_sessions as ase', 'ar.attendance_session_id', '=', 'ase.id')
    ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
    ->join('subjects as s', 'ase.subject_id', '=', 's.id')
    ->where('ar.student_id', 16)
    ->where('ase.session_date', '>=', '2025-09-01')
    ->where('ase.session_date', '<=', '2025-09-30')
    ->select('ase.session_date', 's.id as subject_id', 's.name as subject', 'ast.code', 'ast.name as status')
    ->orderBy('ase.session_date')
    ->get();

echo "Total records: " . $allRecords->count() . "\n";
echo str_repeat('-', 60) . "\n";

$bySubject = [];
foreach ($allRecords as $record) {
    $subjectName = $record->subject_id . ':' . $record->subject;
    if (!isset($bySubject[$subjectName])) {
        $bySubject[$subjectName] = ['P' => 0, 'A' => 0, 'L' => 0];
    }
    $bySubject[$subjectName][$record->code]++;
    
    echo sprintf("%s | ID:%d %s | %s (%s)\n", 
        $record->session_date,
        $record->subject_id,
        $record->subject, 
        $record->status,
        $record->code
    );
}

echo "\n\nSummary by Subject:\n";
echo str_repeat('-', 60) . "\n";
foreach ($bySubject as $subject => $counts) {
    echo sprintf("%s: P=%d, A=%d, L=%d\n", $subject, $counts['P'], $counts['A'], $counts['L']);
}
