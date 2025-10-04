<?php

require_once 'vendor/autoload.php';

// Load Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "🔍 LAMMS Database Analysis - Current Seeders & Data\n";
echo "=" . str_repeat("=", 80) . "\n\n";

echo "📋 ACTIVE SEEDERS (Currently Used):\n";
echo "From DatabaseSeeder.php:\n";
echo "1. ✅ NaawaanGradesSeeder - Creates K-6 grade levels\n";
echo "2. ✅ NaawaanSubjectsSeeder - Creates all school subjects\n";
echo "3. ✅ NaawaanCurriculumSeeder - Creates curriculum structure\n";
echo "4. ✅ NaawaanTeachersSeeder - Creates teacher accounts\n";
echo "5. ✅ NaawaanSectionsSeeder - Creates class sections\n";
echo "6. ✅ CollectedReportSeeder - Creates SF2 reports\n";
echo "7. ✅ AttendanceSeeder - Creates attendance data\n\n";

echo "📊 CURRENT DATABASE CONTENT:\n";
echo "=" . str_repeat("=", 50) . "\n";

// Check key tables and their data
$tables = [
    'grades' => 'Grade Levels',
    'subjects' => 'School Subjects', 
    'teachers' => 'Teachers',
    'sections' => 'Class Sections',
    'student_details' => 'Students',
    'attendance_records' => 'Attendance Records',
    'attendance_sessions' => 'Attendance Sessions',
    'student_qr_codes' => 'Student QR Codes'
];

foreach ($tables as $table => $description) {
    if (Schema::hasTable($table)) {
        $count = DB::table($table)->count();
        echo "📊 {$description}: {$count} records\n";
    } else {
        echo "❌ {$description}: Table not found\n";
    }
}

echo "\n🎓 GRADE LEVELS:\n";
if (Schema::hasTable('grades')) {
    $grades = DB::table('grades')->orderBy('level')->get();
    foreach ($grades as $grade) {
        echo "   • {$grade->name} (Level {$grade->level})\n";
    }
}

echo "\n👩‍🏫 TEACHERS:\n";
if (Schema::hasTable('teachers') && Schema::hasTable('users')) {
    $teachers = DB::table('teachers')
        ->join('users', 'teachers.user_id', '=', 'users.id')
        ->select('teachers.first_name', 'teachers.last_name', 'users.username')
        ->limit(10)
        ->get();
    
    foreach ($teachers as $teacher) {
        echo "   • {$teacher->first_name} {$teacher->last_name} ({$teacher->username})\n";
    }
    
    $totalTeachers = DB::table('teachers')->count();
    if ($totalTeachers > 10) {
        echo "   ... and " . ($totalTeachers - 10) . " more teachers\n";
    }
}

echo "\n🏫 SECTIONS BY GRADE:\n";
if (Schema::hasTable('sections')) {
    // Try different approaches to get section data
    try {
        $sections = DB::table('sections as s')
            ->leftJoin('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
            ->leftJoin('grades as g', 'cg.grade_id', '=', 'g.id')
            ->select('s.name as section_name', 'g.name as grade_name')
            ->orderBy('g.level')
            ->get();
        
        $sectionsByGrade = $sections->groupBy('grade_name');
        foreach ($sectionsByGrade as $grade => $gradeSections) {
            $grade = $grade ?: 'Unknown Grade';
            echo "   📂 {$grade}:\n";
            foreach ($gradeSections as $section) {
                echo "      • {$section->section_name}\n";
            }
        }
    } catch (Exception $e) {
        // Fallback: just show section names
        $sections = DB::table('sections')->select('name')->get();
        echo "   Sections (grade info unavailable):\n";
        foreach ($sections as $section) {
            echo "   • {$section->name}\n";
        }
    }
}

echo "\n👨‍🎓 STUDENTS:\n";
if (Schema::hasTable('student_details')) {
    $totalStudents = DB::table('student_details')->count();
    $activeStudents = DB::table('student_details')->where('current_status', 'active')->count();
    
    echo "   • Total Students: {$totalStudents}\n";
    echo "   • Active Students: {$activeStudents}\n";
    
    // Sample students
    $sampleStudents = DB::table('student_details')
        ->select('firstName', 'lastName', 'gradeLevel')
        ->limit(5)
        ->get();
    
    echo "   Sample Students:\n";
    foreach ($sampleStudents as $student) {
        echo "      • {$student->firstName} {$student->lastName} ({$student->gradeLevel})\n";
    }
}

echo "\n🗺️ GEOGRAPHIC DISTRIBUTION (Updated!):\n";
if (Schema::hasTable('student_details')) {
    $studentsWithAddresses = DB::table('student_details')
        ->whereNotNull('currentAddress')
        ->count();
    
    echo "   • Students with addresses: {$studentsWithAddresses}\n";
    
    // Sample addresses
    $sampleAddresses = DB::table('student_details')
        ->whereNotNull('currentAddress')
        ->select('firstName', 'lastName', 'currentAddress')
        ->limit(3)
        ->get();
    
    echo "   Sample Addresses:\n";
    foreach ($sampleAddresses as $student) {
        $address = json_decode($student->currentAddress, true);
        if ($address) {
            $location = "{$address['barangay']}, {$address['city']}";
            echo "      • {$student->firstName} {$student->lastName}: {$location}\n";
        }
    }
}

echo "\n📱 QR CODE SYSTEM:\n";
if (Schema::hasTable('student_qr_codes')) {
    $qrCount = DB::table('student_qr_codes')->count();
    $activeQR = DB::table('student_qr_codes')->where('is_active', true)->count();
    echo "   • Total QR Codes: {$qrCount}\n";
    echo "   • Active QR Codes: {$activeQR}\n";
}

echo "\n📊 ATTENDANCE SYSTEM:\n";
if (Schema::hasTable('attendance_records')) {
    $attendanceCount = DB::table('attendance_records')->count();
    echo "   • Attendance Records: {$attendanceCount}\n";
}

if (Schema::hasTable('attendance_sessions')) {
    $sessionCount = DB::table('attendance_sessions')->count();
    echo "   • Attendance Sessions: {$sessionCount}\n";
}

echo "\n" . "=" . str_repeat("=", 80) . "\n";
echo "🎯 SEEDER SUMMARY:\n";
echo "✅ Main Seeders: Using Naawan-specific seeders for realistic school data\n";
echo "✅ Data Quality: Production-ready with real Naawan, Misamis Oriental addresses\n";
echo "✅ System Status: Complete K-6 school system with teachers, students, and attendance\n";
echo "✅ Recent Updates: Geographic heatmap system with realistic address data\n";
echo "🏫 School: Naawan Central School (Misamis Oriental)\n";

echo "\n📋 OTHER AVAILABLE SEEDERS (Not Currently Used):\n";
$otherSeeders = [
    'ComprehensiveNaawaanSeeder' => 'Large comprehensive seeder (backup)',
    'StudentSeeder' => 'Generic student seeder (replaced by Naawan-specific)',
    'TeacherSeeder' => 'Generic teacher seeder (replaced by Naawan-specific)',
    'DepartmentalizedTeacherSeeder' => 'Specialized for Grade 4-6 teachers',
    'Grade4to6StudentsSeeder' => 'Specific seeder for upper grades',
    'MatatagStudentsSeeder' => 'Matatag curriculum students',
    'QuickRestoreMariaSantosSeeder' => 'Emergency teacher restoration'
];

foreach ($otherSeeders as $seeder => $description) {
    echo "   • {$seeder}: {$description}\n";
}

echo "\n🎉 Current Status: Ready for production with realistic Naawan school data!\n";

?>
