<?php
require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Database configuration
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'pgsql',
    'host' => 'localhost',
    'database' => 'lamms_db',
    'username' => 'postgres',
    'password' => 'password',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    echo "Creating sample data for LAMMS system...\n";

    // 1. Create Grade 3 if not exists
    $grade3 = DB::table('grades')->where('name', 'Grade 3')->first();
    if (!$grade3) {
        $grade3Id = DB::table('grades')->insertGetId([
            'name' => 'Grade 3',
            'level' => 3,
            'description' => 'Third Grade Elementary',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    } else {
        $grade3Id = $grade3->id;
    }
    echo "✓ Grade 3 ready (ID: $grade3Id)\n";

    // 2. Create Malikhain section if not exists
    $malikhainSection = DB::table('sections')->where('name', 'Malikhain')->first();
    if (!$malikhainSection) {
        $malikhainId = DB::table('sections')->insertGetId([
            'name' => 'Malikhain',
            'curriculum_grade_id' => 1, // Assuming curriculum grade exists
            'capacity' => 40,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    } else {
        $malikhainId = $malikhainSection->id;
    }
    echo "✓ Malikhain section ready (ID: $malikhainId)\n";

    // 3. Create Mathematics subject if not exists
    $mathSubject = DB::table('subjects')->where('name', 'Mathematics')->first();
    if (!$mathSubject) {
        $mathId = DB::table('subjects')->insertGetId([
            'name' => 'Mathematics',
            'code' => 'MATH',
            'description' => 'Elementary Mathematics',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    } else {
        $mathId = $mathSubject->id;
    }
    echo "✓ Mathematics subject ready (ID: $mathId)\n";

    // 4. Create sample students
    $students = [
        ['first_name' => 'avdavd', 'last_name' => 'avd', 'lrn' => 'ENR20254026'],
        ['first_name' => 'avdavd', 'last_name' => 'avdavd', 'lrn' => 'ENR20254231'],
        ['first_name' => 'ar', 'last_name' => 'g2', 'lrn' => 'ENR20254104'],
        ['first_name' => 'New Test', 'last_name' => 'New', 'lrn' => 'ENR20250231'],
        ['first_name' => 'avdaw', 'last_name' => 'G/3', 'lrn' => 'ENR20258201'],
        ['first_name' => 'david', 'last_name' => 'ki', 'lrn' => 'ENR20254202'],
        ['first_name' => 'avdavd', 'last_name' => 'avdad', 'lrn' => 'ENR20254794']
    ];

    $studentIds = [];
    foreach ($students as $student) {
        $existingStudent = DB::table('student_details')->where('lrn', $student['lrn'])->first();
        if (!$existingStudent) {
            $studentId = DB::table('student_details')->insertGetId([
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'lrn' => $student['lrn'],
                'gender' => 'Male',
                'age' => 8,
                'status' => 'Active',
                'qr_code' => 'QR_' . $student['lrn'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $studentId = $existingStudent->id;
        }
        $studentIds[] = $studentId;
        
        // Assign student to Malikhain section
        $existingAssignment = DB::table('student_section')
            ->where('student_id', $studentId)
            ->where('section_id', $malikhainId)
            ->first();
            
        if (!$existingAssignment) {
            DB::table('student_section')->insert([
                'student_id' => $studentId,
                'section_id' => $malikhainId,
                'school_year' => '2025-2026',
                'is_active' => true,
                'status' => 'enrolled',
                'enrollment_date' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    echo "✓ Created/updated " . count($students) . " students in Malikhain section\n";

    // 5. Create teacher if not exists
    $teacher = DB::table('teachers')->where('email', 'maria.santos@naawan.edu.ph')->first();
    if (!$teacher) {
        $teacherId = DB::table('teachers')->insertGetId([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria.santos@naawan.edu.ph',
            'employee_id' => 'TCH001',
            'phone' => '09123456789',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    } else {
        $teacherId = $teacher->id;
    }
    echo "✓ Teacher Maria Santos ready (ID: $teacherId)\n";

    // 6. Assign Mathematics to Malikhain section
    $sectionSubject = DB::table('section_subject')
        ->where('section_id', $malikhainId)
        ->where('subject_id', $mathId)
        ->first();
        
    if (!$sectionSubject) {
        DB::table('section_subject')->insert([
            'section_id' => $malikhainId,
            'subject_id' => $mathId,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    echo "✓ Mathematics assigned to Malikhain section\n";

    // 7. Assign teacher to section-subject
    $teacherAssignment = DB::table('teacher_section_subject')
        ->where('teacher_id', $teacherId)
        ->where('section_id', $malikhainId)
        ->where('subject_id', $mathId)
        ->first();
        
    if (!$teacherAssignment) {
        DB::table('teacher_section_subject')->insert([
            'teacher_id' => $teacherId,
            'section_id' => $malikhainId,
            'subject_id' => $mathId,
            'is_primary' => true,
            'is_active' => true,
            'role' => 'teacher'
        ]);
    }
    echo "✓ Maria Santos assigned to teach Mathematics in Malikhain\n";

    echo "\n🎉 Sample data creation completed successfully!\n";
    echo "📊 Summary:\n";
    echo "   - Grade 3 section 'Malikhain' with 7 students\n";
    echo "   - Teacher Maria Santos assigned to Mathematics\n";
    echo "   - Ready for attendance tracking\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
