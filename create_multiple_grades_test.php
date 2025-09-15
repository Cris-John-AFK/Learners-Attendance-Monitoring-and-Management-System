<?php
require_once 'lamms-backend/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as DB;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable('lamms-backend');
$dotenv->load();

// Setup database connection
$capsule = new DB;
$capsule->addConnection([
    'driver' => 'pgsql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? '5432',
    'database' => $_ENV['DB_DATABASE'] ?? 'lamms_db',
    'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8',
    'prefix' => '',
    'schema' => 'public',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

try {
    echo "🔧 Creating test data for multiple grades...\n\n";

    // Get curriculum ID
    $curriculum = DB::table('curriculums')->first();
    if (!$curriculum) {
        throw new Exception('No curriculum found');
    }

    // Create additional grades if they don't exist
    $grades = [
        ['name' => 'Grade 1', 'code' => 'G1', 'level' => 1],
        ['name' => 'Grade 2', 'code' => 'G2', 'level' => 2],
        ['name' => 'Grade 4', 'code' => 'G4', 'level' => 4],
        ['name' => 'Grade 5', 'code' => 'G5', 'level' => 5],
    ];

    foreach ($grades as $gradeData) {
        // Check if grade exists
        $existingGrade = DB::table('grades')->where('name', $gradeData['name'])->first();
        
        if (!$existingGrade) {
            // Create grade
            $gradeId = DB::table('grades')->insertGetId([
                'name' => $gradeData['name'],
                'code' => $gradeData['code'],
                'level' => $gradeData['level'],
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "✅ Created grade: {$gradeData['name']} (ID: $gradeId)\n";
        } else {
            $gradeId = $existingGrade->id;
            echo "ℹ️ Grade already exists: {$gradeData['name']} (ID: $gradeId)\n";
        }

        // Add to curriculum if not already added
        $curriculumGrade = DB::table('curriculum_grade')
            ->where('curriculum_id', $curriculum->id)
            ->where('grade_id', $gradeId)
            ->first();

        if (!$curriculumGrade) {
            DB::table('curriculum_grade')->insert([
                'curriculum_id' => $curriculum->id,
                'grade_id' => $gradeId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "✅ Added {$gradeData['name']} to curriculum\n";
        }

        // Create a section for this grade
        $sectionName = "{$gradeData['name']} - Section A";
        $existingSection = DB::table('sections')->where('name', $sectionName)->first();
        
        if (!$existingSection) {
            $curriculumGradeId = DB::table('curriculum_grade')
                ->where('curriculum_id', $curriculum->id)
                ->where('grade_id', $gradeId)
                ->value('id');

            $sectionId = DB::table('sections')->insertGetId([
                'name' => $sectionName,
                'curriculum_grade_id' => $curriculumGradeId,
                'capacity' => 30,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "✅ Created section: $sectionName (ID: $sectionId)\n";
        } else {
            $sectionId = $existingSection->id;
            echo "ℹ️ Section already exists: $sectionName (ID: $sectionId)\n";
        }

        // Create some students for this grade
        for ($i = 1; $i <= 6; $i++) {
            $studentName = "Test Student {$gradeData['code']}-$i";
            $existingStudent = DB::table('students')->where('name', $studentName)->first();
            
            if (!$existingStudent) {
                $studentId = DB::table('students')->insertGetId([
                    'name' => $studentName,
                    'student_id' => "{$gradeData['code']}-" . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'email' => strtolower(str_replace(' ', '.', $studentName)) . '@test.com',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Assign student to section
                DB::table('student_section')->insert([
                    'student_id' => $studentId,
                    'section_id' => $sectionId,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                echo "✅ Created student: $studentName and assigned to section\n";
            }
        }

        // Create some attendance records for this grade's students
        $studentIds = DB::table('student_section')
            ->join('sections', 'student_section.section_id', '=', 'sections.id')
            ->join('curriculum_grade', 'sections.curriculum_grade_id', '=', 'curriculum_grade.id')
            ->where('curriculum_grade.grade_id', $gradeId)
            ->where('student_section.is_active', true)
            ->pluck('student_section.student_id');

        if ($studentIds->count() > 0) {
            // Create attendance sessions for the past few days
            $dates = [
                now()->subDays(1)->format('Y-m-d'),
                now()->subDays(2)->format('Y-m-d'),
                now()->subDays(3)->format('Y-m-d'),
            ];

            foreach ($dates as $date) {
                // Check if session exists
                $existingSession = DB::table('attendance_sessions')
                    ->where('session_date', $date)
                    ->where('section_id', $sectionId)
                    ->first();

                if (!$existingSession) {
                    $sessionId = DB::table('attendance_sessions')->insertGetId([
                        'session_date' => $date,
                        'section_id' => $sectionId,
                        'subject_id' => null, // Homeroom
                        'teacher_id' => 1, // Assuming teacher ID 1 exists
                        'session_type' => 'homeroom',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // Create attendance records for each student
                    foreach ($studentIds as $studentId) {
                        // Random attendance status (mostly present, some absent/late)
                        $rand = rand(1, 10);
                        if ($rand <= 7) {
                            $statusId = 1; // Present
                        } elseif ($rand <= 8) {
                            $statusId = 2; // Absent  
                        } elseif ($rand <= 9) {
                            $statusId = 3; // Late
                        } else {
                            $statusId = 4; // Excused
                        }

                        DB::table('attendance_records')->insert([
                            'attendance_session_id' => $sessionId,
                            'student_id' => $studentId,
                            'attendance_status_id' => $statusId,
                            'marked_at' => "$date 08:00:00",
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                    echo "✅ Created attendance session for {$gradeData['name']} on $date\n";
                }
            }
        }

        echo "\n";
    }

    echo "🎉 Test data creation completed!\n\n";

    // Test the API with multiple grades
    echo "🧪 Testing API with multiple grades...\n";
    $url = 'http://localhost:8000/api/admin/attendance/analytics?date_range=current_year';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        echo "✅ API working with " . count($data['data']['grades']) . " grades:\n";
        foreach ($data['data']['grades'] as $grade) {
            echo "  - {$grade['grade_name']}: {$grade['student_count']} students, {$grade['attendance_rate']}% attendance\n";
        }
    } else {
        echo "❌ API failed (HTTP $httpCode)\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
