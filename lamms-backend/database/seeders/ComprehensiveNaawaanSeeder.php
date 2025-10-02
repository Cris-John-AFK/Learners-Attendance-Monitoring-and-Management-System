<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Schedule;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ComprehensiveNaawaanSeeder extends Seeder
{
    private $grades = [];
    private $curriculumGrades = []; // Maps grade_id => curriculum_grade_id
    private $sections = [];
    private $subjects = [];
    private $students = [];
    private $teachers; // Will hold Collection of Teacher models
    private $schedules = [];
    
    // Realistic Filipino names pool
    private $firstNamesMale = [
        'Juan', 'Jose', 'Miguel', 'Gabriel', 'Rafael', 'Carlos', 'Luis', 'Antonio', 
        'Fernando', 'Ricardo', 'Andres', 'Manuel', 'Pedro', 'Diego', 'Daniel',
        'Marco', 'Paolo', 'Angelo', 'Christian', 'Joshua', 'Nathan', 'Elijah',
        'Isaiah', 'Caleb', 'Ethan', 'Noah', 'Liam', 'Lucas', 'Mason', 'Oliver'
    ];
    
    private $firstNamesFemale = [
        'Maria', 'Ana', 'Sofia', 'Isabella', 'Gabriela', 'Valentina', 'Camila',
        'Victoria', 'Elena', 'Carmen', 'Rosa', 'Lucia', 'Paula', 'Andrea',
        'Patricia', 'Angela', 'Michelle', 'Nicole', 'Stephanie', 'Jasmine',
        'Crystal', 'Angel', 'Faith', 'Hope', 'Grace', 'Joy', 'Pearl', 'Ruby'
    ];
    
    private $lastNames = [
        'Dela Cruz', 'Garcia', 'Reyes', 'Santos', 'Ramos', 'Fernandez', 'Mendoza',
        'Torres', 'Gonzales', 'Rodriguez', 'Villanueva', 'Cruz', 'Bautista',
        'Aquino', 'Rivera', 'Santiago', 'Flores', 'Morales', 'Castro', 'Herrera',
        'Pascual', 'Valdez', 'Navarro', 'Diaz', 'Gomez', 'Perez', 'Sanchez',
        'Martinez', 'Lopez', 'Hernandez', 'Castillo', 'Romero', 'Gutierrez',
        'Alvarez', 'Jimenez', 'Vargas', 'Chavez', 'Velasquez', 'Aguilar', 'Ortiz'
    ];

    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            $this->command->info('🏫 Starting Comprehensive Naawan Central School Seeding...');
            
            // Step 1: Seed Grades
            $this->seedGrades();
            
            // Step 2: Create Curriculum and link to grades
            $this->seedCurriculum();
            
            // Step 3: Seed Sections (unique names per grade)
            $this->seedSections();
            
            // Step 3: Seed Subjects (DepEd K-12 curriculum)
            $this->seedSubjects();
            
            // Step 4: Link Subjects to Grades
            $this->seedCurriculumGradeSubjects();
            
            // Step 5: Seed Students (realistic names, diverse per section)
            $this->seedStudents();
            
            // Step 6: Get existing teachers
            $this->teachers = Teacher::all();
            
            // Step 7: Assign teachers to sections and subjects
            $this->seedTeacherAssignments();
            
            // Step 8: Create class schedules (realistic elementary/JHS/SHS schedules)
            $this->seedSchedules();
            
            // Step 9: Seed attendance sessions from June 2 to October 1, 2025
            $this->seedAttendanceSessions();
            
            DB::commit();
            
            $this->command->info('');
            $this->command->info('✅ ===== SEEDING COMPLETE =====');
            $this->command->info('📊 Summary:');
            $this->command->info("   Grades: " . count($this->grades));
            $this->command->info("   Sections: " . count($this->sections));
            $this->command->info("   Subjects: " . count($this->subjects));
            $this->command->info("   Students: " . count($this->students));
            $this->command->info("   Teachers: " . count($this->teachers));
            $this->command->info("   Date Range: June 2, 2025 - October 1, 2025");
            $this->command->info('🎓 Naawan Central School is ready!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function seedGrades(): void
    {
        $this->command->info('📚 Seeding Grades...');
        
        // ELEMENTARY SCHOOL ONLY: Kindergarten to Grade 6
        $gradeData = [
            ['code' => 'K', 'name' => 'Kindergarten', 'level' => 'Kinder', 'description' => 'Kindergarten'],
            ['code' => 'G1', 'name' => 'Grade 1', 'level' => 'Grade 1', 'description' => 'Grade 1 - Basic Education'],
            ['code' => 'G2', 'name' => 'Grade 2', 'level' => 'Grade 2', 'description' => 'Grade 2 - Primary Education'],
            ['code' => 'G3', 'name' => 'Grade 3', 'level' => 'Grade 3', 'description' => 'Grade 3 - Primary Education'],
            ['code' => 'G4', 'name' => 'Grade 4', 'level' => 'Grade 4', 'description' => 'Grade 4 - Intermediate'],
            ['code' => 'G5', 'name' => 'Grade 5', 'level' => 'Grade 5', 'description' => 'Grade 5 - Intermediate'],
            ['code' => 'G6', 'name' => 'Grade 6', 'level' => 'Grade 6', 'description' => 'Grade 6 - Intermediate'],
        ];
        
        foreach ($gradeData as $grade) {
            // Use updateOrCreate to avoid duplicates
            $this->grades[] = Grade::updateOrCreate(
                ['code' => $grade['code']], // Match by code
                $grade // Update or create with this data
            );
        }
        
        $this->command->info('   ✓ Created/Updated ' . count($this->grades) . ' grade levels');
    }

    private function seedCurriculum(): void
    {
        $this->command->info('📋 Setting up Curriculum...');
        
        // Create or get main curriculum (ELEMENTARY SCHOOL ONLY)
        $curriculum = DB::table('curricula')->updateOrInsert(
            ['name' => 'DepEd Elementary Curriculum'],
            [
                'name' => 'DepEd Elementary Curriculum',
                'description' => 'Department of Education Elementary Curriculum (Kindergarten to Grade 6)',
                'start_year' => 2024,
                'end_year' => 2025,
                'is_active' => true,
                'status' => 'Active', // Must be: Draft, Active, or Archived
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
        
        $curriculumId = DB::table('curricula')->where('name', 'DepEd Elementary Curriculum')->value('id');
        
        // Link all grades to this curriculum
        $curriculumGrades = [];
        foreach ($this->grades as $grade) {
            // Check if curriculum_grade already exists
            $existing = DB::table('curriculum_grade')
                ->where('curriculum_id', $curriculumId)
                ->where('grade_id', $grade->id)
                ->first();
            
            if (!$existing) {
                $id = DB::table('curriculum_grade')->insertGetId([
                    'curriculum_id' => $curriculumId,
                    'grade_id' => $grade->id
                ]);
                $curriculumGrades[$grade->id] = $id;
            } else {
                $curriculumGrades[$grade->id] = $existing->id;
            }
        }
        
        // Store curriculum_grade IDs for later use
        $this->curriculumGrades = $curriculumGrades;
        
        $this->command->info("   ✓ Created curriculum with " . count($curriculumGrades) . " grade levels");
    }

    private function seedSections(): void
    {
        $this->command->info('🏛️  Seeding Sections...');
        
        // ELEMENTARY SCHOOL ONLY: Unique section names (Filipino-inspired)
        $sectionNamesByLevel = [
            'Kinder' => ['Sampaguita', 'Gumamela'],
            'Grade 1' => ['Mabini', 'Bonifacio'],
            'Grade 2' => ['Rizal', 'Luna'],
            'Grade 3' => ['Aguinaldo', 'Jacinto'],
            'Grade 4' => ['Silang', 'Dagohoy'],
            'Grade 5' => ['Tandang Sora', 'Gabriela'],
            'Grade 6' => ['Lapu-Lapu', 'Magat Salamat'],
        ];
        
        foreach ($this->grades as $grade) {
            $sectionNames = $sectionNamesByLevel[$grade->level];
            $curriculumGradeId = $this->curriculumGrades[$grade->id] ?? null;
            
            if (!$curriculumGradeId) continue;
            
            foreach ($sectionNames as $sectionName) {
                $this->sections[] = Section::create([
                    'curriculum_grade_id' => $curriculumGradeId,
                    'name' => $sectionName,
                    'description' => "$sectionName - {$grade->name}",
                    'capacity' => rand(25, 35)
                ]);
            }
        }
        
        $this->command->info('   ✓ Created ' . count($this->sections) . ' unique sections');
    }

    private function seedSubjects(): void
    {
        $this->command->info('📖 Seeding Subjects (DepEd K-12 Curriculum)...');
        
        // ELEMENTARY SCHOOL ONLY: Subjects for Kinder to Grade 6
        $subjectsByLevel = [
            'Kinder' => [
                'English', 'Filipino', 'Mathematics', 'Arts', 'Music', 
                'Physical Education', 'Good Manners and Right Conduct'
            ],
            'Grade 1-3' => [
                'English', 'Filipino', 'Mathematics', 'Araling Panlipunan', 
                'Science', 'MAPEH', 'Edukasyon sa Pagpapakatao'
            ],
            'Grade 4-6' => [
                'English', 'Filipino', 'Mathematics', 'Araling Panlipunan', 
                'Science', 'MAPEH', 'Edukasyon sa Pagpapakatao', 'Technology and Livelihood Education'
            ],
        ];
        
        foreach ($subjectsByLevel as $level => $subjects) {
            foreach ($subjects as $subjectName) {
                // Avoid duplicates
                $existing = collect($this->subjects)->firstWhere('name', $subjectName);
                if (!$existing) {
                    $this->subjects[] = Subject::create([
                        'name' => $subjectName,
                        'code' => strtoupper(substr(str_replace(' ', '', $subjectName), 0, 6)) . rand(100, 999),
                        'description' => $subjectName . ' - ' . $level
                    ]);
                }
            }
        }
        
        $this->command->info('   ✓ Created ' . count($this->subjects) . ' subjects');
    }

    private function seedCurriculumGradeSubjects(): void
    {
        $this->command->info('🔗 Linking Subjects to Grades...');
        
        // Get curriculum ID
        $curriculumId = DB::table('curricula')->where('name', 'DepEd K-12 Curriculum')->value('id');
        
        if (!$curriculumId) {
            $this->command->error('   ✗ Curriculum not found!');
            return;
        }
        
        // Map subjects to appropriate grades
        $mappings = [
            'Kinder' => ['English', 'Filipino', 'Mathematics', 'Arts', 'Music', 'Physical Education', 'Good Manners and Right Conduct'],
            'Grade 1' => ['English', 'Filipino', 'Mathematics', 'Araling Panlipunan', 'Science', 'MAPEH', 'Edukasyon sa Pagpapakatao'],
            'Grade 2' => ['English', 'Filipino', 'Mathematics', 'Araling Panlipunan', 'Science', 'MAPEH', 'Edukasyon sa Pagpapakatao'],
            'Grade 3' => ['English', 'Filipino', 'Mathematics', 'Araling Panlipunan', 'Science', 'MAPEH', 'Edukasyon sa Pagpapakatao'],
            'Grade 4' => ['English', 'Filipino', 'Mathematics', 'Araling Panlipunan', 'Science', 'MAPEH', 'Edukasyon sa Pagpapakatao', 'Technology and Livelihood Education'],
            'Grade 5' => ['English', 'Filipino', 'Mathematics', 'Araling Panlipunan', 'Science', 'MAPEH', 'Edukasyon sa Pagpapakatao', 'Technology and Livelihood Education'],
            'Grade 6' => ['English', 'Filipino', 'Mathematics', 'Araling Panlipunan', 'Science', 'MAPEH', 'Edukasyon sa Pagpapakatao', 'Technology and Livelihood Education'],
        ];
        
        $count = 0;
        foreach ($mappings as $gradeLevel => $subjectNames) {
            $grade = collect($this->grades)->firstWhere('level', $gradeLevel);
            if ($grade) {
                $curriculumGradeId = $this->curriculumGrades[$grade->id] ?? null;
                if (!$curriculumGradeId) continue;
                
                // Get max existing sequence for this curriculum/grade combo
                $maxSequence = DB::table('curriculum_grade_subject')
                    ->where('curriculum_id', $curriculumId)
                    ->where('grade_id', $grade->id)
                    ->max('sequence_number') ?: 0;
                
                $sequence = $maxSequence + 1; // Start after existing sequences
                
                foreach ($subjectNames as $subjectName) {
                    $subject = collect($this->subjects)->firstWhere('name', $subjectName);
                    if ($subject) {
                        // Check if already exists
                        $exists = DB::table('curriculum_grade_subject')
                            ->where('curriculum_id', $curriculumId)
                            ->where('grade_id', $grade->id)
                            ->where('subject_id', $subject->id)
                            ->exists();
                        
                        if (!$exists) {
                            DB::table('curriculum_grade_subject')->insert([
                                'curriculum_id' => $curriculumId,
                                'grade_id' => $grade->id,
                                'subject_id' => $subject->id,
                                'sequence_number' => $sequence
                            ]);
                            $sequence++;
                            $count++;
                        }
                    }
                }
            }
        }
        
        $this->command->info("   ✓ Created $count curriculum mappings");
    }

    private function seedStudents(): void
    {
        $this->command->info('👨‍🎓 Seeding Students with unique Filipino names...');
        
        $studentCount = 0;
        
        foreach ($this->sections as $section) {
            // Find grade by matching curriculum_grade_id
            $grade = null;
            foreach ($this->grades as $g) {
                if (isset($this->curriculumGrades[$g->id]) && $this->curriculumGrades[$g->id] == $section->curriculum_grade_id) {
                    $grade = $g;
                    break;
                }
            }
            
            if (!$grade) {
                $this->command->warn("   ⚠ No grade found for section {$section->name}");
                continue;
            }
            
            $numStudents = rand(20, 30); // Realistic class sizes
            
            for ($i = 0; $i < $numStudents; $i++) {
                $gender = rand(0, 1) ? 'Male' : 'Female';
                $sex = $gender;
                $firstName = $gender === 'Male' 
                    ? $this->firstNamesMale[array_rand($this->firstNamesMale)]
                    : $this->firstNamesFemale[array_rand($this->firstNamesFemale)];
                $lastName = $this->lastNames[array_rand($this->lastNames)];
                
                // Add middle initial for uniqueness
                $middleInitial = chr(rand(65, 90));
                $fullName = "$firstName $middleInitial. $lastName";
                
                // Generate unique LRN (Learner Reference Number)
                $lrn = '1' . rand(10000000000, 99999999999);
                
                $birthdate = now()->subYears(rand(5, 18));
                $age = now()->year - $birthdate->year;
                
                $studentIdStr = 'NCS-' . now()->year . '-' . str_pad($studentCount + 1, 5, '0', STR_PAD_LEFT);
                
                $student = DB::table('student_details')->insertGetId([
                    'student_id' => $studentIdStr,
                    'studentId' => $studentIdStr,
                    'name' => $fullName,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'middleName' => $middleInitial,
                    'lrn' => $lrn,
                    'gradeLevel' => $grade->level,
                    'section' => $section->name,
                    'gender' => $gender,
                    'sex' => $sex,
                    'birthdate' => $birthdate->format('Y-m-d'),
                    'birthplace' => 'Naawan, Misamis Oriental',
                    'age' => $age,
                    'motherTongue' => 'Cebuano',
                    'currentAddress' => json_encode([
                        'street' => 'Purok ' . rand(1, 7),
                        'barangay' => 'Naawan',
                        'city' => 'Naawan',
                        'province' => 'Misamis Oriental',
                        'zipCode' => '9023'
                    ]),
                    'permanentAddress' => json_encode([
                        'street' => 'Purok ' . rand(1, 7),
                        'barangay' => 'Naawan',
                        'city' => 'Naawan',
                        'province' => 'Misamis Oriental',
                        'zipCode' => '9023'
                    ]),
                    'parentName' => $this->generateGuardianName($lastName),
                    'parentContact' => '+63 9' . rand(100000000, 999999999),
                    'schoolYearStart' => 2024,
                    'schoolYearEnd' => 2025,
                    'status' => 'Enrolled',
                    'enrollment_status' => 'active',
                    'current_status' => 'active',
                    'enrollmentDate' => '2024-06-01',
                    'admissionDate' => '2024-06-01',
                    'isIndigenous' => rand(0, 10) === 0, // 10% indigenous
                    'is4PsBeneficiary' => rand(0, 3) === 0, // 25% 4Ps beneficiaries
                    'hasDisability' => rand(0, 20) === 0, // 5% with disabilities
                    'isActive' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                // Link student to section via student_section table
                DB::table('student_section')->insert([
                    'student_id' => $student,
                    'section_id' => $section->id,
                    'is_active' => true
                ]);
                
                $this->students[] = (object)['id' => $student, 'name' => $fullName, 'section_name' => $section->name];
                $studentCount++;
            }
        }
        
        $this->command->info("   ✓ Created $studentCount unique students");
    }

    private function generateGuardianName($lastName): string
    {
        $guardianFirstNames = ['Roberto', 'Maricel', 'Eduardo', 'Lorna', 'Benjamin', 'Alma', 'Rodrigo', 'Cynthia'];
        return $guardianFirstNames[array_rand($guardianFirstNames)] . ' ' . $lastName;
    }

    private function seedTeacherAssignments(): void
    {
        $this->command->info('👩‍🏫 Assigning Teachers to Sections and Subjects...');
        
        if (!$this->teachers || $this->teachers->isEmpty()) {
            $this->command->error('   ✗ No teachers found! Please run NaawaanTeachersSeeder first.');
            return;
        }
        
        $teacherIndex = 0;
        $teacherCount = $this->teachers->count();
        $assignments = 0;
        
        foreach ($this->sections as $section) {
            $grade = collect($this->grades)->firstWhere('id', $section->curriculum_grade_id);
            
            if (!$grade) continue;
            
            // Get subjects for this grade level
            $gradeSubjects = DB::table('curriculum_grade_subject')
                ->where('grade_id', $grade->id)
                ->pluck('subject_id')
                ->toArray();
            
            if (empty($gradeSubjects)) continue;
            
            // Assign 2-4 subjects per teacher for this section
            $numSubjects = rand(2, min(4, count($gradeSubjects)));
            $selectedSubjects = array_slice($gradeSubjects, 0, $numSubjects);
            
            foreach ($selectedSubjects as $subjectId) {
                // Get teacher by rotating through the collection
                $teacher = $this->teachers->values()->get($teacherIndex % $teacherCount);
                
                DB::table('teacher_section_subject')->insert([
                    'teacher_id' => $teacher->id,
                    'section_id' => $section->id,
                    'subject_id' => $subjectId,
                    'is_active' => true
                ]);
                
                $assignments++;
                $teacherIndex++;
            }
        }
        
        $this->command->info("   ✓ Created $assignments teacher assignments");
    }

    private function seedSchedules(): void
    {
        $this->command->info('📅 Creating Class Schedules...');
        
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $scheduleCount = 0;
        
        // Get all teacher assignments
        $assignments = DB::table('teacher_section_subject')
            ->where('is_active', true)
            ->get();
        
        foreach ($assignments as $assignment) {
            // Create 3-5 schedule slots per subject per week
            $numSlots = rand(3, 5);
            
            for ($i = 0; $i < $numSlots; $i++) {
                $day = $days[$i % count($days)];
                
                // Elementary: 7:30 AM - 4:00 PM with age-appropriate times
                $startHour = rand(7, 14);
                $startMinute = rand(0, 1) * 30; // 00 or 30
                $startTime = sprintf('%02d:%02d:00', $startHour, $startMinute);
                $endTime = sprintf('%02d:%02d:00', $startHour + 1, $startMinute);
                
                DB::table('subject_schedules')->insert([
                    'teacher_id' => $assignment->teacher_id,
                    'section_id' => $assignment->section_id,
                    'subject_id' => $assignment->subject_id,
                    'day' => $day,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $scheduleCount++;
            }
        }
        
        $this->command->info("   ✓ Created $scheduleCount schedule entries");
    }

    private function seedAttendanceSessions(): void
    {
        $this->command->info('📊 Seeding Attendance Sessions (June 2 - October 1, 2025)...');
        $this->command->info('   This will take a few minutes for realistic data...');
        
        // June 2, 2025 is a Monday (first day of school year)
        $startDate = Carbon::create(2025, 6, 2);
        $endDate = Carbon::create(2025, 10, 1);
        
        // Create attendance sessions and records
        $currentDate = $startDate->copy();
        $sessionCount = 0;
        $recordCount = 0;
        
        // Student attendance patterns (for realistic variation)
        $studentPatterns = [];
        
        // One tragic story: randomly pick a student ID mid-year (rare but realistic)
        $allStudentIds = DB::table('student_details')->pluck('id')->toArray();
        if (count($allStudentIds) > 50) {
            $tragicStudent = $allStudentIds[array_rand($allStudentIds)];
            $tragicDate = $startDate->copy()->addDays(rand(20, 60));
        }
        
        while ($currentDate <= $endDate) {
            // Skip weekends
            if ($currentDate->isWeekday()) {
                // Get all active assignments for today
                $assignments = DB::table('teacher_section_subject')
                    ->where('is_active', true)
                    ->get();
                
                foreach ($assignments as $assignment) {
                    // Check if there's a schedule for today
                    $hasSchedule = DB::table('subject_schedules')
                        ->where('teacher_id', $assignment->teacher_id)
                        ->where('section_id', $assignment->section_id)
                        ->where('subject_id', $assignment->subject_id)
                        ->where('day', $currentDate->format('l'))
                        ->exists();
                    
                    if ($hasSchedule) {
                        // Create attendance session
                        $session = AttendanceSession::create([
                            'teacher_id' => $assignment->teacher_id,
                            'section_id' => $assignment->section_id,
                            'subject_id' => $assignment->subject_id,
                            'session_date' => $currentDate->toDateString(),
                            'session_start_time' => $currentDate->format('H:i:s'),
                            'session_end_time' => $currentDate->copy()->addHours(1)->format('H:i:s'),
                            'session_type' => ['regular', 'makeup', 'special'][rand(0, 2)],
                            'status' => 'completed',
                            'completed_at' => $currentDate->copy()->addHours(1)
                        ]);
                        
                        $sessionCount++;
                        
                        // Get students for this section via student_section table
                        $sectionStudentIds = DB::table('student_section')
                            ->where('section_id', $assignment->section_id)
                            ->where('is_active', true)
                            ->pluck('student_id')
                            ->toArray();
                        
                        foreach ($sectionStudentIds as $studentId) {
                            if (!isset($studentPatterns[$studentId])) {
                                // Initialize pattern for new student
                                $studentPatterns[$studentId] = [
                                    'attendance_rate' => rand(75, 100) / 100,
                                    'consecutive_absences' => 0
                                ];
                            }
                            
                            $status = 'present';
                            $statusId = 1; // Present
                            
                            // Check if student died (tragic but realistic)
                            if (isset($tragicStudent) && $tragicStudent == $studentId && $currentDate >= $tragicDate) {
                                continue; // No more attendance records after passing
                            }
                            
                            // Realistic attendance patterns
                            $pattern = $studentPatterns[$studentId];
                            $random = rand(1, 100) / 100;
                            
                            if ($random > $pattern['attendance_rate']) {
                                $status = 'absent';
                                $statusId = 2; // Absent
                                $pattern['consecutive_absences']++;
                            } else {
                                $pattern['consecutive_absences'] = 0;
                                
                                // Occasional late arrivals
                                if (rand(1, 20) === 1) {
                                    $status = 'late';
                                    $statusId = 3; // Late
                                }
                                
                                // Occasional excused (doctor visits, family emergencies)
                                if (rand(1, 30) === 1) {
                                    $status = 'excused';
                                    $statusId = 4; // Excused
                                }
                            }
                            
                            DB::table('attendance_records')->insert([
                                'attendance_session_id' => $session->id,
                                'student_id' => $studentId,
                                'attendance_status_id' => $statusId,
                                'marked_by_teacher_id' => $assignment->teacher_id,
                                'marked_at' => $currentDate->copy()->addMinutes(rand(5, 30)),
                                'marking_method' => ['manual', 'qr_scan', 'seat_plan'][rand(0, 2)],
                                'remarks' => $status === 'excused' ? 'Medical appointment' : null,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            
                            $recordCount++;
                            $studentPatterns[$studentId] = $pattern;
                        }
                    }
                }
            }
            
            $currentDate->addDay();
            
            // Progress indicator
            if ($sessionCount % 100 === 0) {
                $this->command->info("      Processing... $sessionCount sessions, $recordCount records");
            }
        }
        
        $this->command->info("   ✓ Created $sessionCount attendance sessions");
        $this->command->info("   ✓ Created $recordCount attendance records");
        $this->command->info("   ✓ Date range: June 2 - October 1, 2025 (weekdays only)");
    }
}
