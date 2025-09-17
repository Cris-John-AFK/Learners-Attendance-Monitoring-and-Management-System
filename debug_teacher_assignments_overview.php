<?php
require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    // Database connection
    $pdo = new PDO(
        "pgsql:host=" . $_ENV['DB_HOST'] . ";port=" . $_ENV['DB_PORT'] . ";dbname=" . $_ENV['DB_DATABASE'],
        $_ENV['DB_USERNAME'],
        $_ENV['DB_PASSWORD']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== COMPREHENSIVE TEACHER ASSIGNMENT OVERVIEW ===\n\n";

    // Get complete teacher assignment overview
    $stmt = $pdo->prepare("
        SELECT 
            t.id as teacher_id,
            t.first_name,
            t.last_name,
            u.username,
            
            -- Homeroom assignment
            homeroom_tss.id as homeroom_assignment_id,
            homeroom_section.name as homeroom_section,
            homeroom_grade.name as homeroom_grade,
            
            -- Subject assignments
            subject_tss.id as subject_assignment_id,
            subjects.name as subject_name,
            subject_section.name as subject_section,
            subject_grade.name as subject_grade,
            subject_tss.role as assignment_role,
            subject_tss.is_primary,
            subject_tss.is_active
            
        FROM teachers t
        JOIN users u ON t.user_id = u.id
        
        -- Homeroom assignments (role = 'homeroom')
        LEFT JOIN teacher_section_subject homeroom_tss ON t.id = homeroom_tss.teacher_id 
            AND homeroom_tss.role = 'homeroom' 
            AND homeroom_tss.is_active = true 
            AND homeroom_tss.deleted_at IS NULL
        LEFT JOIN sections homeroom_section ON homeroom_tss.section_id = homeroom_section.id
        LEFT JOIN curriculum_grade homeroom_cg ON homeroom_section.curriculum_grade_id = homeroom_cg.id
        LEFT JOIN grades homeroom_grade ON homeroom_cg.grade_id = homeroom_grade.id
        
        -- Subject assignments (role = 'subject_teacher')
        LEFT JOIN teacher_section_subject subject_tss ON t.id = subject_tss.teacher_id 
            AND subject_tss.role = 'subject_teacher' 
            AND subject_tss.is_active = true 
            AND subject_tss.deleted_at IS NULL
        LEFT JOIN subjects ON subject_tss.subject_id = subjects.id
        LEFT JOIN sections subject_section ON subject_tss.section_id = subject_section.id
        LEFT JOIN curriculum_grade subject_cg ON subject_section.curriculum_grade_id = subject_cg.id
        LEFT JOIN grades subject_grade ON subject_cg.grade_id = subject_grade.id
        
        WHERE u.role = 'teacher' AND u.is_active = true
        ORDER BY t.first_name, t.last_name, subjects.name
    ");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group results by teacher
    $teacherData = [];
    foreach ($results as $row) {
        $teacherId = $row['teacher_id'];
        
        if (!isset($teacherData[$teacherId])) {
            $teacherData[$teacherId] = [
                'teacher_id' => $row['teacher_id'],
                'name' => $row['first_name'] . ' ' . $row['last_name'],
                'username' => $row['username'],
                'homeroom' => null,
                'subjects' => []
            ];
        }
        
        // Set homeroom if exists
        if ($row['homeroom_assignment_id'] && !$teacherData[$teacherId]['homeroom']) {
            $teacherData[$teacherId]['homeroom'] = [
                'section' => $row['homeroom_section'],
                'grade' => $row['homeroom_grade']
            ];
        }
        
        // Add subject if exists and not already added
        if ($row['subject_assignment_id']) {
            $subjectKey = $row['subject_assignment_id'];
            if (!isset($teacherData[$teacherId]['subjects'][$subjectKey])) {
                $teacherData[$teacherId]['subjects'][$subjectKey] = [
                    'subject' => $row['subject_name'],
                    'section' => $row['subject_section'],
                    'grade' => $row['subject_grade'],
                    'role' => $row['assignment_role'],
                    'is_primary' => $row['is_primary'],
                    'is_active' => $row['is_active']
                ];
            }
        }
    }

    echo "📊 TEACHER ASSIGNMENT SUMMARY\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    foreach ($teacherData as $teacher) {
        echo "👤 {$teacher['name']} (Username: {$teacher['username']})\n";
        echo "   Teacher ID: {$teacher['teacher_id']}\n";
        
        // Homeroom
        if ($teacher['homeroom']) {
            echo "   🏠 HOMEROOM: {$teacher['homeroom']['section']} ({$teacher['homeroom']['grade']})\n";
        } else {
            echo "   🏠 HOMEROOM: ❌ Not assigned\n";
        }
        
        // Subjects
        if (!empty($teacher['subjects'])) {
            echo "   📚 SUBJECTS:\n";
            foreach ($teacher['subjects'] as $subject) {
                $primary = $subject['is_primary'] ? ' (PRIMARY)' : '';
                $active = $subject['is_active'] ? '✅' : '❌';
                echo "      {$active} {$subject['subject']} - {$subject['section']} ({$subject['grade']}){$primary}\n";
            }
        } else {
            echo "   📚 SUBJECTS: ❌ None assigned\n";
        }
        
        echo "   ───────────────────────────────────────────────────────────\n";
    }

    // Summary statistics
    echo "\n📈 ASSIGNMENT STATISTICS\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    
    $totalTeachers = count($teacherData);
    $teachersWithHomeroom = 0;
    $teachersWithSubjects = 0;
    $totalSubjectAssignments = 0;
    
    foreach ($teacherData as $teacher) {
        if ($teacher['homeroom']) $teachersWithHomeroom++;
        if (!empty($teacher['subjects'])) {
            $teachersWithSubjects++;
            $totalSubjectAssignments += count($teacher['subjects']);
        }
    }
    
    echo "Total Teachers: {$totalTeachers}\n";
    echo "Teachers with Homeroom: {$teachersWithHomeroom} (" . round(($teachersWithHomeroom/$totalTeachers)*100, 1) . "%)\n";
    echo "Teachers with Subjects: {$teachersWithSubjects} (" . round(($teachersWithSubjects/$totalTeachers)*100, 1) . "%)\n";
    echo "Total Subject Assignments: {$totalSubjectAssignments}\n";
    echo "Average Subjects per Teacher: " . round($totalSubjectAssignments/$totalTeachers, 1) . "\n";

    // Grade distribution
    echo "\n📊 GRADE LEVEL DISTRIBUTION\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    
    $gradeDistribution = [];
    foreach ($teacherData as $teacher) {
        if ($teacher['homeroom']) {
            $grade = $teacher['homeroom']['grade'];
            $gradeDistribution[$grade] = ($gradeDistribution[$grade] ?? 0) + 1;
        }
    }
    
    foreach ($gradeDistribution as $grade => $count) {
        echo "{$grade}: {$count} homeroom teacher(s)\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
