<?php
require_once 'lamms-backend/vendor/autoload.php';

try {
    $app = require_once 'lamms-backend/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    echo "=== STUDENT_DETAILS TABLE STRUCTURE ===\n\n";
    
    $columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'student_details' ORDER BY ordinal_position");
    foreach($columns as $col) {
        echo $col->column_name . ': ' . $col->data_type . "\n";
    }
    
    echo "\n=== SAMPLE STUDENT DATA ===\n\n";
    $students = DB::table('student_details')->limit(3)->get();
    foreach($students as $student) {
        echo "Student: {$student->name}\n";
        echo "Status: {$student->status}\n";
        if (isset($student->archive_reason)) {
            echo "Archive Reason: {$student->archive_reason}\n";
        }
        if (isset($student->archive_notes)) {
            echo "Archive Notes: {$student->archive_notes}\n";
        }
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
