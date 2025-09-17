<?php
/**
 * Complete Database Seeder for LAMMS
 * Seeds teachers, students, sections, grade levels, and their relationships
 */

require_once 'lamms-backend/vendor/autoload.php';

// Database configuration
$host = 'localhost';
$dbname = 'lamms_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔗 Connected to database successfully!\n";
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Clear existing data (in correct order to avoid foreign key constraints)
    echo "\n🗑️  Clearing existing data...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DELETE FROM teacher_assignments");
    $pdo->exec("DELETE FROM attendance_records");
    $pdo->exec("DELETE FROM attendance_sessions");
    $pdo->exec("DELETE FROM seating_arrangements");
    $pdo->exec("DELETE FROM student_sections");
    $pdo->exec("DELETE FROM students");
    $pdo->exec("DELETE FROM teachers");
    $pdo->exec("DELETE FROM users WHERE role IN ('teacher', 'student')");
    $pdo->exec("DELETE FROM sections");
    $pdo->exec("DELETE FROM curriculum_grades");
    $pdo->exec("DELETE FROM subjects");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // 1. Seed Grade Levels
    echo "\n📚 Seeding grade levels...\n";
    $grades = [
        ['name' => 'Kindergarten', 'level' => 0, 'description' => 'Kindergarten level'],
        ['name' => 'Grade 1', 'level' => 1, 'description' => 'First grade level'],
        ['name' => 'Grade 2', 'level' => 2, 'description' => 'Second grade level'],
        ['name' => 'Grade 3', 'level' => 3, 'description' => 'Third grade level'],
        ['name' => 'Grade 4', 'level' => 4, 'description' => 'Fourth grade level'],
        ['name' => 'Grade 5', 'level' => 5, 'description' => 'Fifth grade level'],
        ['name' => 'Grade 6', 'level' => 6, 'description' => 'Sixth grade level']
    ];
    
    $gradeIds = [];
    foreach ($grades as $grade) {
        $stmt = $pdo->prepare("INSERT INTO curriculum_grades (name, level, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->execute([$grade['name'], $grade['level'], $grade['description']]);
        $gradeIds[$grade['name']] = $pdo->lastInsertId();
        echo "  ✅ Created {$grade['name']}\n";
    }
    
    // 2. Seed Subjects
    echo "\n📖 Seeding subjects...\n";
    $subjects = [
        ['name' => 'Mathematics', 'code' => 'MATH', 'description' => 'Mathematics subject'],
        ['name' => 'English', 'code' => 'ENG', 'description' => 'English Language subject'],
        ['name' => 'Science', 'code' => 'SCI', 'description' => 'Science subject'],
        ['name' => 'Filipino', 'code' => 'FIL', 'description' => 'Filipino Language subject'],
        ['name' => 'Araling Panlipunan', 'code' => 'AP', 'description' => 'Social Studies subject'],
        ['name' => 'MAPEH', 'code' => 'MAPEH', 'description' => 'Music, Arts, Physical Education, and Health'],
        ['name' => 'Homeroom', 'code' => 'HR', 'description' => 'Homeroom/Advisory class']
    ];
    
    $subjectIds = [];
    foreach ($subjects as $subject) {
        $stmt = $pdo->prepare("INSERT INTO subjects (name, code, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->execute([$subject['name'], $subject['code'], $subject['description']]);
        $subjectIds[$subject['name']] = $pdo->lastInsertId();
        echo "  ✅ Created {$subject['name']} ({$subject['code']})\n";
    }
    
    // 3. Seed Sections
    echo "\n🏫 Seeding sections...\n";
    $sections = [
        // Kindergarten
        ['name' => 'Kinder-A', 'grade' => 'Kindergarten', 'capacity' => 25],
        ['name' => 'Kinder-B', 'grade' => 'Kindergarten', 'capacity' => 25],
        
        // Grade 1
        ['name' => 'Grade 1-A', 'grade' => 'Grade 1', 'capacity' => 30],
        ['name' => 'Grade 1-B', 'grade' => 'Grade 1', 'capacity' => 30],
        
        // Grade 2
        ['name' => 'Grade 2-A', 'grade' => 'Grade 2', 'capacity' => 30],
        ['name' => 'Grade 2-B', 'grade' => 'Grade 2', 'capacity' => 30],
        
        // Grade 3
        ['name' => 'Grade 3-A', 'grade' => 'Grade 3', 'capacity' => 30],
        ['name' => 'Grade 3-B', 'grade' => 'Grade 3', 'capacity' => 30],
        
        // Grade 4
        ['name' => 'Grade 4-A', 'grade' => 'Grade 4', 'capacity' => 32],
        ['name' => 'Grade 4-B', 'grade' => 'Grade 4', 'capacity' => 32],
        
        // Grade 5
        ['name' => 'Grade 5-A', 'grade' => 'Grade 5', 'capacity' => 32],
        ['name' => 'Grade 5-B', 'grade' => 'Grade 5', 'capacity' => 32],
        
        // Grade 6
        ['name' => 'Grade 6-A', 'grade' => 'Grade 6', 'capacity' => 35],
        ['name' => 'Grade 6-B', 'grade' => 'Grade 6', 'capacity' => 35]
    ];
    
    $sectionIds = [];
    foreach ($sections as $section) {
        $gradeId = $gradeIds[$section['grade']];
        $stmt = $pdo->prepare("INSERT INTO sections (name, curriculum_grade_id, capacity, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->execute([$section['name'], $gradeId, $section['capacity']]);
        $sectionIds[$section['name']] = $pdo->lastInsertId();
        echo "  ✅ Created {$section['name']} (Capacity: {$section['capacity']})\n";
    }
    
    // 4. Seed Teachers with Users
    echo "\n👨‍🏫 Seeding teachers...\n";
    $teachers = [
        [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria.santos@ncs.edu',
            'username' => 'maria.santos',
            'password' => 'teacher123',
            'employee_id' => 'T001',
            'specialization' => 'Mathematics, Homeroom'
        ],
        [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan.delacruz@ncs.edu',
            'username' => 'juan.delacruz',
            'password' => 'teacher123',
            'employee_id' => 'T002',
            'specialization' => 'English, Science'
        ],
        [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana.reyes@ncs.edu',
            'username' => 'ana.reyes',
            'password' => 'teacher123',
            'employee_id' => 'T003',
            'specialization' => 'Filipino, Araling Panlipunan'
        ],
        [
            'first_name' => 'Carlos',
            'last_name' => 'Garcia',
            'email' => 'carlos.garcia@ncs.edu',
            'username' => 'carlos.garcia',
            'password' => 'teacher123',
            'employee_id' => 'T004',
            'specialization' => 'MAPEH, Science'
        ],
        [
            'first_name' => 'Lisa',
            'last_name' => 'Fernandez',
            'email' => 'lisa.fernandez@ncs.edu',
            'username' => 'lisa.fernandez',
            'password' => 'teacher123',
            'employee_id' => 'T005',
            'specialization' => 'Kindergarten, Homeroom'
        ]
    ];
    
    $teacherIds = [];
    foreach ($teachers as $teacher) {
        // Create user account
        $hashedPassword = password_hash($teacher['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at, updated_at) VALUES (?, ?, ?, 'teacher', NOW(), NOW())");
        $stmt->execute([$teacher['username'], $teacher['email'], $hashedPassword]);
        $userId = $pdo->lastInsertId();
        
        // Create teacher record
        $stmt = $pdo->prepare("INSERT INTO teachers (user_id, first_name, last_name, email, employee_id, specialization, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$userId, $teacher['first_name'], $teacher['last_name'], $teacher['email'], $teacher['employee_id'], $teacher['specialization']]);
        $teacherId = $pdo->lastInsertId();
        
        $teacherIds[$teacher['username']] = $teacherId;
        echo "  ✅ Created {$teacher['first_name']} {$teacher['last_name']} ({$teacher['username']})\n";
    }
    
    // 5. Seed Students
    echo "\n👨‍🎓 Seeding students...\n";
    $studentNames = [
        // Grade 3-A students (20 students)
        ['first_name' => 'Cris John', 'last_name' => 'Cabales', 'section' => 'Grade 3-A'],
        ['first_name' => 'Jessica', 'last_name' => 'Roque', 'section' => 'Grade 3-A'],
        ['first_name' => 'Angelo', 'last_name' => 'Larot', 'section' => 'Grade 3-A'],
        ['first_name' => 'Miguel', 'last_name' => 'Rodriguez', 'section' => 'Grade 3-A'],
        ['first_name' => 'Sofia', 'last_name' => 'Cruz', 'section' => 'Grade 3-A'],
        ['first_name' => 'Daniel', 'last_name' => 'Torres', 'section' => 'Grade 3-A'],
        ['first_name' => 'Isabella', 'last_name' => 'Morales', 'section' => 'Grade 3-A'],
        ['first_name' => 'Gabriel', 'last_name' => 'Hernandez', 'section' => 'Grade 3-A'],
        ['first_name' => 'Valentina', 'last_name' => 'Jimenez', 'section' => 'Grade 3-A'],
        ['first_name' => 'Sebastian', 'last_name' => 'Gutierrez', 'section' => 'Grade 3-A'],
        ['first_name' => 'Camila', 'last_name' => 'Vargas', 'section' => 'Grade 3-A'],
        ['first_name' => 'Mateo', 'last_name' => 'Castillo', 'section' => 'Grade 3-A'],
        ['first_name' => 'Lucia', 'last_name' => 'Mendoza', 'section' => 'Grade 3-A'],
        ['first_name' => 'Diego', 'last_name' => 'Ortega', 'section' => 'Grade 3-A'],
        ['first_name' => 'Valeria', 'last_name' => 'Silva', 'section' => 'Grade 3-A'],
        ['first_name' => 'Adrian', 'last_name' => 'Ramos', 'section' => 'Grade 3-A'],
        ['first_name' => 'Natalia', 'last_name' => 'Flores', 'section' => 'Grade 3-A'],
        ['first_name' => 'Emilio', 'last_name' => 'Aguilar', 'section' => 'Grade 3-A'],
        ['first_name' => 'Regina', 'last_name' => 'Vega', 'section' => 'Grade 3-A'],
        ['first_name' => 'Fernando', 'last_name' => 'Navarro', 'section' => 'Grade 3-A'],
        
        // Grade 3-B students (18 students)
        ['first_name' => 'Carmen', 'last_name' => 'Lopez', 'section' => 'Grade 3-B'],
        ['first_name' => 'Roberto', 'last_name' => 'Martinez', 'section' => 'Grade 3-B'],
        ['first_name' => 'Elena', 'last_name' => 'Gonzalez', 'section' => 'Grade 3-B'],
        ['first_name' => 'Pablo', 'last_name' => 'Sanchez', 'section' => 'Grade 3-B'],
        ['first_name' => 'Mariana', 'last_name' => 'Rivera', 'section' => 'Grade 3-B'],
        ['first_name' => 'Alejandro', 'last_name' => 'Moreno', 'section' => 'Grade 3-B'],
        ['first_name' => 'Catalina', 'last_name' => 'Ruiz', 'section' => 'Grade 3-B'],
        ['first_name' => 'Ricardo', 'last_name' => 'Perez', 'section' => 'Grade 3-B'],
        ['first_name' => 'Esperanza', 'last_name' => 'Diaz', 'section' => 'Grade 3-B'],
        ['first_name' => 'Joaquin', 'last_name' => 'Romero', 'section' => 'Grade 3-B'],
        ['first_name' => 'Beatriz', 'last_name' => 'Herrera', 'section' => 'Grade 3-B'],
        ['first_name' => 'Andres', 'last_name' => 'Medina', 'section' => 'Grade 3-B'],
        ['first_name' => 'Cristina', 'last_name' => 'Castro', 'section' => 'Grade 3-B'],
        ['first_name' => 'Manuel', 'last_name' => 'Ortiz', 'section' => 'Grade 3-B'],
        ['first_name' => 'Patricia', 'last_name' => 'Rubio', 'section' => 'Grade 3-B'],
        ['first_name' => 'Francisco', 'last_name' => 'Molina', 'section' => 'Grade 3-B'],
        ['first_name' => 'Gabriela', 'last_name' => 'Delgado', 'section' => 'Grade 3-B'],
        ['first_name' => 'Raul', 'last_name' => 'Campos', 'section' => 'Grade 3-B'],
        
        // Grade 4-A students (15 students)
        ['first_name' => 'Antonio', 'last_name' => 'Valdez', 'section' => 'Grade 4-A'],
        ['first_name' => 'Monica', 'last_name' => 'Guerrero', 'section' => 'Grade 4-A'],
        ['first_name' => 'Sergio', 'last_name' => 'Ibarra', 'section' => 'Grade 4-A'],
        ['first_name' => 'Adriana', 'last_name' => 'Cortez', 'section' => 'Grade 4-A'],
        ['first_name' => 'Hector', 'last_name' => 'Espinoza', 'section' => 'Grade 4-A'],
        ['first_name' => 'Lorena', 'last_name' => 'Fuentes', 'section' => 'Grade 4-A'],
        ['first_name' => 'Ignacio', 'last_name' => 'Sandoval', 'section' => 'Grade 4-A'],
        ['first_name' => 'Paola', 'last_name' => 'Carrillo', 'section' => 'Grade 4-A'],
        ['first_name' => 'Esteban', 'last_name' => 'Dominguez', 'section' => 'Grade 4-A'],
        ['first_name' => 'Claudia', 'last_name' => 'Vazquez', 'section' => 'Grade 4-A'],
        ['first_name' => 'Rodrigo', 'last_name' => 'Mendez', 'section' => 'Grade 4-A'],
        ['first_name' => 'Veronica', 'last_name' => 'Paredes', 'section' => 'Grade 4-A'],
        ['first_name' => 'Guillermo', 'last_name' => 'Contreras', 'section' => 'Grade 4-A'],
        ['first_name' => 'Silvia', 'last_name' => 'Rios', 'section' => 'Grade 4-A'],
        ['first_name' => 'Armando', 'last_name' => 'Cabrera', 'section' => 'Grade 4-A']
    ];
    
    $studentIds = [];
    foreach ($studentNames as $index => $student) {
        $sectionId = $sectionIds[$student['section']];
        $studentId = 'S' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);
        $email = strtolower($student['first_name'] . '.' . $student['last_name']) . '@student.ncs.edu';
        $email = str_replace(' ', '', $email);
        
        // Create user account for student
        $username = strtolower($student['first_name'] . '.' . $student['last_name']);
        $username = str_replace(' ', '', $username);
        $hashedPassword = password_hash('student123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at, updated_at) VALUES (?, ?, ?, 'student', NOW(), NOW())");
        $stmt->execute([$username, $email, $hashedPassword]);
        $userId = $pdo->lastInsertId();
        
        // Create student record
        $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, first_name, last_name, email, current_section_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$userId, $studentId, $student['first_name'], $student['last_name'], $email, $sectionId]);
        $dbStudentId = $pdo->lastInsertId();
        
        // Create student-section relationship
        $stmt = $pdo->prepare("INSERT INTO student_sections (student_id, section_id, academic_year, created_at, updated_at) VALUES (?, ?, '2024-2025', NOW(), NOW())");
        $stmt->execute([$dbStudentId, $sectionId]);
        
        $studentIds[] = $dbStudentId;
        echo "  ✅ Created {$student['first_name']} {$student['last_name']} ({$student['section']})\n";
    }
    
    // 6. Seed Teacher Assignments
    echo "\n📋 Seeding teacher assignments...\n";
    $assignments = [
        // Maria Santos - Grade 3-A Homeroom and Mathematics
        [
            'teacher' => 'maria.santos',
            'section' => 'Grade 3-A',
            'subjects' => ['Mathematics', 'Homeroom'],
            'roles' => ['subject_teacher', 'homeroom_teacher']
        ],
        // Juan Dela Cruz - Grade 3-B English and Science
        [
            'teacher' => 'juan.delacruz',
            'section' => 'Grade 3-B',
            'subjects' => ['English', 'Science'],
            'roles' => ['subject_teacher', 'subject_teacher']
        ],
        // Ana Reyes - Grade 4-A Filipino and AP
        [
            'teacher' => 'ana.reyes',
            'section' => 'Grade 4-A',
            'subjects' => ['Filipino', 'Araling Panlipunan'],
            'roles' => ['subject_teacher', 'subject_teacher']
        ],
        // Carlos Garcia - Grade 3-A and 3-B MAPEH
        [
            'teacher' => 'carlos.garcia',
            'section' => 'Grade 3-A',
            'subjects' => ['MAPEH'],
            'roles' => ['subject_teacher']
        ],
        [
            'teacher' => 'carlos.garcia',
            'section' => 'Grade 3-B',
            'subjects' => ['MAPEH'],
            'roles' => ['subject_teacher']
        ]
    ];
    
    foreach ($assignments as $assignment) {
        $teacherId = $teacherIds[$assignment['teacher']];
        $sectionId = $sectionIds[$assignment['section']];
        
        foreach ($assignment['subjects'] as $index => $subjectName) {
            $subjectId = $subjectIds[$subjectName];
            $role = $assignment['roles'][$index];
            
            $stmt = $pdo->prepare("INSERT INTO teacher_assignments (teacher_id, section_id, subject_id, role, academic_year, created_at, updated_at) VALUES (?, ?, ?, ?, '2024-2025', NOW(), NOW())");
            $stmt->execute([$teacherId, $sectionId, $subjectId, $role]);
            
            echo "  ✅ Assigned {$assignment['teacher']} to {$assignment['section']} - {$subjectName} ({$role})\n";
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n🎉 Database seeding completed successfully!\n";
    echo "\n📊 Summary:\n";
    echo "  • Grade Levels: " . count($grades) . "\n";
    echo "  • Subjects: " . count($subjects) . "\n";
    echo "  • Sections: " . count($sections) . "\n";
    echo "  • Teachers: " . count($teachers) . "\n";
    echo "  • Students: " . count($studentNames) . "\n";
    
    echo "\n🔑 Teacher Login Credentials:\n";
    foreach ($teachers as $teacher) {
        echo "  • {$teacher['first_name']} {$teacher['last_name']}: {$teacher['username']} / {$teacher['password']}\n";
    }
    
    echo "\n🎓 Student Login Credentials (sample):\n";
    echo "  • All students: [firstname.lastname] / student123\n";
    echo "  • Example: cris.john.cabales / student123\n";
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
