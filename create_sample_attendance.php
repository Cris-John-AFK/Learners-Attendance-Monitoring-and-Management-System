<?php

require_once 'lamms-backend/vendor/autoload.php';

$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CREATING SAMPLE ATTENDANCE DATA ===\n";

// Get students for Teacher 1
$students = [14, 16, 20]; // One Kind, Male Kind One, der Kin
$teacherId = 1;
$sectionId = 1;
$subjectId = 2; // English

// Create attendance for last 20 school days
$startDate = now()->subDays(30);
$endDate = now();
$schoolDays = [];

// Generate school days (Monday to Friday)
$current = $startDate->copy();
while ($current <= $endDate) {
    if ($current->isWeekday()) {
        $schoolDays[] = $current->format('Y-m-d');
    }
    $current->addDay();
}

$schoolDays = array_slice($schoolDays, -20); // Last 20 school days

echo "Creating attendance for " . count($schoolDays) . " school days\n";
echo "Students: " . implode(', ', $students) . "\n";

$recordsCreated = 0;

foreach ($students as $studentId) {
    foreach ($schoolDays as $date) {
        // Create realistic attendance patterns
        $rand = rand(1, 100);
        
        if ($studentId == 14) { // One Kind - good attendance
            $status = $rand <= 90 ? 'present' : ($rand <= 95 ? 'late' : 'absent');
        } elseif ($studentId == 16) { // Male Kind One - some issues
            $status = $rand <= 80 ? 'present' : ($rand <= 90 ? 'late' : 'absent');
        } else { // der Kin - attendance issues
            $status = $rand <= 70 ? 'present' : ($rand <= 85 ? 'late' : 'absent');
        }

        // Check if record already exists
        $exists = \DB::table('attendances')
            ->where('student_id', $studentId)
            ->where('teacher_id', $teacherId)
            ->where('date', $date)
            ->exists();

        if (!$exists) {
            \DB::table('attendances')->insert([
                'student_id' => $studentId,
                'teacher_id' => $teacherId,
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'date' => $date,
                'status' => $status,
                'marked_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $recordsCreated++;
        }
    }
}

echo "\n✅ Created {$recordsCreated} attendance records\n";

// Show summary
echo "\n=== ATTENDANCE SUMMARY ===\n";
foreach ($students as $studentId) {
    $student = \DB::table('student_details')->where('id', $studentId)->first();
    $present = \DB::table('attendances')->where('student_id', $studentId)->where('status', 'present')->count();
    $absent = \DB::table('attendances')->where('student_id', $studentId)->where('status', 'absent')->count();
    $late = \DB::table('attendances')->where('student_id', $studentId)->where('status', 'late')->count();
    $total = $present + $absent + $late;
    $rate = $total > 0 ? round(($present / $total) * 100, 1) : 0;
    
    echo "- {$student->name}: {$present}P, {$absent}A, {$late}L (Rate: {$rate}%)\n";
}

echo "\n=== SAMPLE DATA CREATION COMPLETE ===\n";

?>
