<?php
require_once 'lamms-backend/vendor/autoload.php';

try {
    $app = require_once 'lamms-backend/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    echo "=== STUDENTS ANALYSIS ===\n\n";
    
    $students = DB::table('students')->limit(10)->get();
    echo "Sample students:\n";
    foreach($students as $s) {
        echo "- {$s->name}, Section: {$s->section}, Grade: {$s->gradeLevel}\n";
    }
    
    echo "\nGrade level distribution:\n";
    $gradeCounts = DB::table('students')
        ->select('gradeLevel', DB::raw('COUNT(*) as count'))
        ->groupBy('gradeLevel')
        ->get();
    foreach($gradeCounts as $grade) {
        echo "- {$grade->gradeLevel}: {$grade->count} students\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
