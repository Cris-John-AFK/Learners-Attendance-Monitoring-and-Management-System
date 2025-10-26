<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class NCS_Teachers_LammsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "========================================\n";
        echo "NCS TEACHERS SEEDER\n";
        echo "========================================\n\n";

        DB::beginTransaction();

        try {
            // Create curriculum if not exists
            $curriculum = DB::table('curricula')->where('name', 'MATATAG Curriculum')->first();
            if (!$curriculum) {
                $curriculumId = DB::table('curricula')->insertGetId([
                    'name' => 'MATATAG Curriculum',
                    'description' => 'DepEd MATATAG Curriculum',
                    'school_year' => '2025-2026',
                    'start_year' => 2025,
                    'end_year' => 2026,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $curriculumId = $curriculum->id;
            }

            // Create grades
            $grades = [
                ['code' => 'K', 'name' => 'Kindergarten', 'level' => 0, 'display_order' => 1],
                ['code' => 'G1', 'name' => 'Grade 1', 'level' => 1, 'display_order' => 2],
                ['code' => 'G2', 'name' => 'Grade 2', 'level' => 2, 'display_order' => 3],
                ['code' => 'G3', 'name' => 'Grade 3', 'level' => 3, 'display_order' => 4],
                ['code' => 'G4', 'name' => 'Grade 4', 'level' => 4, 'display_order' => 5],
                ['code' => 'G5', 'name' => 'Grade 5', 'level' => 5, 'display_order' => 6],
                ['code' => 'G6', 'name' => 'Grade 6', 'level' => 6, 'display_order' => 7],
            ];

            $gradeIds = [];
            foreach ($grades as $grade) {
                $existing = DB::table('grades')->where('code', $grade['code'])->first();
                if (!$existing) {
                    $gradeIds[$grade['code']] = DB::table('grades')->insertGetId($grade);
                } else {
                    $gradeIds[$grade['code']] = $existing->id;
                }

                // Create curriculum_grade
                $cgExists = DB::table('curriculum_grade')->where([
                    'curriculum_id' => $curriculumId,
                    'grade_id' => $gradeIds[$grade['code']]
                ])->first();

                if (!$cgExists) {
                    DB::table('curriculum_grade')->insert([
                        'curriculum_id' => $curriculumId,
                        'grade_id' => $gradeIds[$grade['code']]
                    ]);
                }
            }

            // Teachers data with sections and subjects
            $teachersData = [
                // KINDERGARTEN
                ['first_name' => 'Liza', 'last_name' => 'Santos', 'grade' => 'K', 'section' => 'Generous AM', 'subjects' => ['Oral Communication', 'Reading Readiness', 'Writing', 'Mathematics', 'Science and Health', 'Music/Arts/Movement', 'GMRC', 'Mother Tongue']],
                ['first_name' => 'Grace', 'last_name' => 'Dela Cruz', 'grade' => 'K', 'section' => 'Generous PM', 'subjects' => ['Oral Communication', 'Reading Readiness', 'Writing', 'Mathematics', 'Science and Health', 'Music/Arts/Movement', 'GMRC', 'Mother Tongue']],
                ['first_name' => 'Arlene', 'last_name' => 'Villanueva', 'grade' => 'K', 'section' => 'Good AM', 'subjects' => ['Oral Communication', 'Reading Readiness', 'Writing', 'Mathematics', 'Science and Health', 'Music/Arts/Movement', 'GMRC', 'Mother Tongue']],
                ['first_name' => 'Mark', 'last_name' => 'Gutierrez', 'grade' => 'K', 'section' => 'Good PM', 'subjects' => ['Oral Communication', 'Reading Readiness', 'Writing', 'Mathematics', 'Science and Health', 'Music/Arts/Movement', 'GMRC', 'Mother Tongue']],
                ['first_name' => 'Jenny', 'last_name' => 'Ramos', 'grade' => 'K', 'section' => 'Great AM', 'subjects' => ['Oral Communication', 'Reading Readiness', 'Writing', 'Mathematics', 'Science and Health', 'Music/Arts/Movement', 'GMRC', 'Mother Tongue']],
                ['first_name' => 'Samuel', 'last_name' => 'Garcia', 'grade' => 'K', 'section' => 'Great PM', 'subjects' => ['Oral Communication', 'Reading Readiness', 'Writing', 'Mathematics', 'Science and Health', 'Music/Arts/Movement', 'GMRC', 'Mother Tongue']],

                // GRADE 1
                ['first_name' => 'Ana', 'last_name' => 'Lopez', 'grade' => 'G1', 'section' => 'Admirable', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],
                ['first_name' => 'Ruby', 'last_name' => 'Dizon', 'grade' => 'G1', 'section' => 'Adorable', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],
                ['first_name' => 'Carlo', 'last_name' => 'Lim', 'grade' => 'G1', 'section' => 'Affectionate', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],
                ['first_name' => 'Melinda', 'last_name' => 'Cruz', 'grade' => 'G1', 'section' => 'Alert', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],
                ['first_name' => 'Joy', 'last_name' => 'Gonzales', 'grade' => 'G1', 'section' => 'Amazing', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],
                ['first_name' => 'Trisha', 'last_name' => 'Serrano', 'grade' => 'G1', 'section' => 'SNED (GRADED)', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],

                // GRADE 2
                ['first_name' => 'Ramil', 'last_name' => 'Bautista', 'grade' => 'G2', 'section' => 'Beloved', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],
                ['first_name' => 'Tess', 'last_name' => 'Morales', 'grade' => 'G2', 'section' => 'Beneficient', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],
                ['first_name' => 'Irene', 'last_name' => 'Torres', 'grade' => 'G2', 'section' => 'Benevolent', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],
                ['first_name' => 'Albert', 'last_name' => 'Reyes', 'grade' => 'G2', 'section' => 'Blessed', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],
                ['first_name' => 'Fe', 'last_name' => 'Santos', 'grade' => 'G2', 'section' => 'Blissful', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],
                ['first_name' => 'Vincent', 'last_name' => 'Mercado', 'grade' => 'G2', 'section' => 'Blossom', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],
                ['first_name' => 'Ursula', 'last_name' => 'Perez', 'grade' => 'G2', 'section' => 'SNED-GRADE 2 (DHH)', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH', 'Mother Tongue']],

                // GRADE 3
                ['first_name' => 'Lorna', 'last_name' => 'Diaz', 'grade' => 'G3', 'section' => 'Calm', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH']],
                ['first_name' => 'Jeric', 'last_name' => 'Mendoza', 'grade' => 'G3', 'section' => 'Candor', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH']],
                ['first_name' => 'Rosemarie', 'last_name' => 'Tan', 'grade' => 'G3', 'section' => 'Charitable', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH']],
                ['first_name' => 'Jessa', 'last_name' => 'Cruz', 'grade' => 'G3', 'section' => 'Cheerful', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH']],
                ['first_name' => 'Marvin', 'last_name' => 'Torres', 'grade' => 'G3', 'section' => 'Clever', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH']],
                ['first_name' => 'Kyle', 'last_name' => 'Dizon', 'grade' => 'G3', 'section' => 'Curious', 'subjects' => ['English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan', 'EsP', 'MAPEH']],

                // GRADE 4
                ['first_name' => 'Claire', 'last_name' => 'Ramos', 'grade' => 'G4', 'section' => 'Dainty', 'subjects' => ['English 5', 'MAPEH 4', 'EsP 6', 'Araling Panlipunan 4', 'EPP 5', 'Science 4', 'Mathematics 6', 'Filipino 4']],
                ['first_name' => 'Joseph', 'last_name' => 'Lim', 'grade' => 'G4', 'section' => 'Dedicated', 'subjects' => ['English 6', 'MAPEH 5', 'EsP 4', 'Araling Panlipunan 6', 'EPP 4', 'Science 5', 'Mathematics 4', 'Filipino 5']],
                ['first_name' => 'Anne', 'last_name' => 'Soriano', 'grade' => 'G4', 'section' => 'Demure', 'subjects' => ['English 4', 'MAPEH 6', 'EsP 5', 'Araling Panlipunan 5', 'EPP 6', 'Science 6', 'Mathematics 5', 'Filipino 6']],
                ['first_name' => 'Joy', 'last_name' => 'Castillo', 'grade' => 'G4', 'section' => 'Devoted', 'subjects' => ['English 5', 'MAPEH 4', 'EsP 6', 'Araling Panlipunan 5', 'EPP 4', 'Science 4', 'Mathematics 6', 'Filipino 4']],
                ['first_name' => 'Alvin', 'last_name' => 'Cortez', 'grade' => 'G4', 'section' => 'Dynamic', 'subjects' => ['English 6', 'MAPEH 5', 'EsP 5', 'Araling Panlipunan 6', 'EPP 6', 'Science 5', 'Mathematics 4', 'Filipino 6']],
                ['first_name' => 'Tristan', 'last_name' => 'Santos', 'grade' => 'G4', 'section' => 'Diligent', 'subjects' => ['English 6', 'MAPEH 5', 'EsP 5', 'Araling Panlipunan 6', 'EPP 6', 'Science 5', 'Mathematics 4', 'Filipino 6']],
                ['first_name' => 'William', 'last_name' => 'Perez', 'grade' => 'G4', 'section' => 'SNED (GRADED)', 'subjects' => ['English 6', 'MAPEH 5', 'EsP 5', 'Araling Panlipunan 6', 'EPP 6', 'Science 5', 'Mathematics 4', 'Filipino 6']],

                // GRADE 5
                ['first_name' => 'Lara', 'last_name' => 'Gutierrez', 'grade' => 'G5', 'section' => 'Effective', 'subjects' => ['English 6', 'MAPEH 5', 'EsP 5', 'Araling Panlipunan 6', 'EPP 6', 'Science 5', 'Mathematics 6', 'Filipino 5']],
                ['first_name' => 'Marvin', 'last_name' => 'Cruz', 'grade' => 'G5', 'section' => 'Efficient', 'subjects' => ['English 5', 'MAPEH 6', 'EsP 4', 'Araling Panlipunan 5', 'EPP 4', 'Science 4', 'Mathematics 5', 'Filipino 6']],
                ['first_name' => 'Cherry', 'last_name' => 'Lopez', 'grade' => 'G5', 'section' => 'Endurance', 'subjects' => ['English 4', 'MAPEH 4', 'EsP 6', 'Araling Panlipunan 4', 'EPP 5', 'Science 6', 'Mathematics 4', 'Filipino 4']],
                ['first_name' => 'Fely', 'last_name' => 'Rivera', 'grade' => 'G5', 'section' => 'Energetic', 'subjects' => ['English 6', 'MAPEH 5', 'EsP 5', 'Araling Panlipunan 5', 'EPP 6', 'Science 5', 'Mathematics 6', 'Filipino 6']],
                ['first_name' => 'Nico', 'last_name' => 'Fernandez', 'grade' => 'G5', 'section' => 'Everlasting', 'subjects' => ['English 5', 'MAPEH 6', 'EsP 4', 'Araling Panlipunan 6', 'EPP 4', 'Science 4', 'Mathematics 5', 'Filipino 5']],

                // GRADE 6
                ['first_name' => 'Joy', 'last_name' => 'Manalansan', 'grade' => 'G6', 'section' => 'Fair', 'subjects' => ['English 6', 'MAPEH 6', 'EsP 6', 'Araling Panlipunan 6', 'EPP 6', 'Science 6', 'Mathematics 6', 'Filipino 6']],
                ['first_name' => 'Kenneth', 'last_name' => 'Tan', 'grade' => 'G6', 'section' => 'Faithful', 'subjects' => ['English 5', 'MAPEH 5', 'EsP 5', 'Araling Panlipunan 5', 'EPP 5', 'Science 5', 'Mathematics 5', 'Filipino 5']],
                ['first_name' => 'Lea', 'last_name' => 'Ramos', 'grade' => 'G6', 'section' => 'Flexible', 'subjects' => ['English 4', 'MAPEH 4', 'EsP 4', 'Araling Panlipunan 4', 'EPP 4', 'Science 4', 'Mathematics 4', 'Filipino 4']],
                ['first_name' => 'Divine', 'last_name' => 'Garcia', 'grade' => 'G6', 'section' => 'Forebearance', 'subjects' => ['English 6', 'MAPEH 5', 'EsP 5', 'Araling Panlipunan 6', 'EPP 6', 'Science 6', 'Mathematics 6', 'Filipino 6']],
                ['first_name' => 'Patrick', 'last_name' => 'Lopez', 'grade' => 'G6', 'section' => 'Fortitude', 'subjects' => ['English 5', 'MAPEH 4', 'EsP 6', 'Araling Panlipunan 5', 'EPP 5', 'Science 5', 'Mathematics 5', 'Filipino 5']],
                ['first_name' => 'Valerie', 'last_name' => 'Aquino', 'grade' => 'G6', 'section' => 'Friendly', 'subjects' => ['English 5', 'MAPEH 4', 'EsP 6', 'Araling Panlipunan 5', 'EPP 5', 'Science 5', 'Mathematics 5', 'Filipino 5']],
                ['first_name' => 'Wendy', 'last_name' => 'Ramos', 'grade' => 'G6', 'section' => 'Fearless', 'subjects' => ['English 5', 'MAPEH 4', 'EsP 6', 'Araling Panlipunan 5', 'EPP 5', 'Science 5', 'Mathematics 5', 'Filipino 5']],
            ];

            $teacherCount = 0;
            $sectionCount = 0;
            $subjectCount = 0;

            foreach ($teachersData as $teacherData) {
                $username = strtolower($teacherData['first_name'] . '.' . $teacherData['last_name']);
                $email = $username . '@msunaawan.edu.ph';

                // Check if user exists
                $existingUser = DB::table('users')->where('username', $username)->first();
                
                if (!$existingUser) {
                    // Create user
                    $userId = DB::table('users')->insertGetId([
                        'username' => $username,
                        'email' => $email,
                        'password' => Hash::make('password123'),
                        'role' => 'teacher',
                        'is_active' => true,
                        'email_verified_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Create teacher
                    $teacherId = DB::table('teachers')->insertGetId([
                        'user_id' => $userId,
                        'first_name' => $teacherData['first_name'],
                        'last_name' => $teacherData['last_name'],
                        'phone_number' => '09' . rand(100000000, 999999999),
                        'address' => 'Naawan, Misamis Oriental, Philippines',
                        'gender' => in_array($teacherData['first_name'], ['Mark', 'Samuel', 'Carlo', 'Ramil', 'Albert', 'Vincent', 'Jeric', 'Marvin', 'Kyle', 'Joseph', 'Alvin', 'Tristan', 'William', 'Kenneth', 'Patrick', 'Nico']) ? 'male' : 'female',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $teacherCount++;
                    echo "✅ Created teacher: {$teacherData['first_name']} {$teacherData['last_name']} (Username: {$username})\n";
                } else {
                    $teacher = DB::table('teachers')->where('user_id', $existingUser->id)->first();
                    $teacherId = $teacher->id;
                    echo "ℹ️  Teacher exists: {$teacherData['first_name']} {$teacherData['last_name']}\n";
                }

                // Get curriculum_grade_id
                $curriculumGrade = DB::table('curriculum_grade')
                    ->where('curriculum_id', $curriculumId)
                    ->where('grade_id', $gradeIds[$teacherData['grade']])
                    ->first();

                // Create section
                $existingSection = DB::table('sections')
                    ->where('name', $teacherData['section'])
                    ->where('curriculum_grade_id', $curriculumGrade->id)
                    ->first();

                if (!$existingSection) {
                    $sectionId = DB::table('sections')->insertGetId([
                        'name' => $teacherData['section'],
                        'curriculum_grade_id' => $curriculumGrade->id,
                        'homeroom_teacher_id' => $teacherId,
                    ]);
                    $sectionCount++;
                } else {
                    $sectionId = $existingSection->id;
                    // Update homeroom teacher
                    DB::table('sections')->where('id', $sectionId)->update([
                        'homeroom_teacher_id' => $teacherId
                    ]);
                }

                // Create subjects and assignments
                foreach ($teacherData['subjects'] as $subjectName) {
                    $subjectCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $subjectName), 0, 10)) . rand(100, 999);
                    
                    $existingSubject = DB::table('subjects')->where('name', $subjectName)->first();
                    
                    if (!$existingSubject) {
                        $subjectId = DB::table('subjects')->insertGetId([
                            'name' => $subjectName,
                            'code' => $subjectCode,
                            'description' => $subjectName,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $subjectCount++;
                    } else {
                        $subjectId = $existingSubject->id;
                    }

                    // Create teacher-section-subject assignment
                    $existingAssignment = DB::table('teacher_section_subject')
                        ->where('teacher_id', $teacherId)
                        ->where('section_id', $sectionId)
                        ->where('subject_id', $subjectId)
                        ->first();

                    if (!$existingAssignment) {
                        DB::table('teacher_section_subject')->insert([
                            'teacher_id' => $teacherId,
                            'section_id' => $sectionId,
                            'subject_id' => $subjectId,
                            'role' => 'homeroom',
                            'is_primary' => true,
                            'is_active' => true,
                        ]);
                    }
                }
            }

            DB::commit();

            echo "\n========================================\n";
            echo "✅ NCS TEACHERS SEEDER COMPLETE!\n";
            echo "========================================\n\n";
            echo "📊 Summary:\n";
            echo "   Teachers created: {$teacherCount}\n";
            echo "   Sections created: {$sectionCount}\n";
            echo "   Subjects created: {$subjectCount}\n\n";
            echo "🔐 Login Credentials:\n";
            echo "   Username format: firstname.lastname\n";
            echo "   Password: password123\n";
            echo "   Example: liza.santos / password123\n\n";

        } catch (\Exception $e) {
            DB::rollBack();
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            throw $e;
        }
    }
}
