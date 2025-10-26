<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NCS_Students_LammsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "========================================\n";
        echo "NCS STUDENTS SEEDER\n";
        echo "========================================\n\n";

        DB::beginTransaction();

        try {
            // Students data organized by grade and section
            $studentsData = [
                // GRADE 1 - A (Admirable) - Ana Lopez
                [
                    'grade' => 'Grade 1',
                    'section' => 'Admirable',
                    'students' => [
                        // Male
                        ['first_name' => 'Adrian', 'last_name' => 'Dela Cruz', 'gender' => 'Male'],
                        ['first_name' => 'Aaron', 'last_name' => 'Villanueva', 'gender' => 'Male'],
                        ['first_name' => 'Benedict', 'last_name' => 'Santos', 'gender' => 'Male'],
                        ['first_name' => 'Carlo', 'last_name' => 'Ramirez', 'gender' => 'Male'],
                        ['first_name' => 'Daniel', 'last_name' => 'Gutierrez', 'gender' => 'Male'],
                        ['first_name' => 'Ethan', 'last_name' => 'Cruz', 'gender' => 'Male'],
                        ['first_name' => 'Francis', 'last_name' => 'Mendoza', 'gender' => 'Male'],
                        ['first_name' => 'Gabriel', 'last_name' => 'Lopez', 'gender' => 'Male'],
                        // Female
                        ['first_name' => 'Abigail', 'last_name' => 'Santos', 'gender' => 'Female'],
                        ['first_name' => 'Alexa', 'last_name' => 'Dela Cruz', 'gender' => 'Female'],
                        ['first_name' => 'Angelica', 'last_name' => 'Villanueva', 'gender' => 'Female'],
                        ['first_name' => 'Bianca', 'last_name' => 'Ramos', 'gender' => 'Female'],
                        ['first_name' => 'Camille', 'last_name' => 'Gutierrez', 'gender' => 'Female'],
                        ['first_name' => 'Danica', 'last_name' => 'Lopez', 'gender' => 'Female'],
                        ['first_name' => 'Erika', 'last_name' => 'Mendoza', 'gender' => 'Female'],
                        ['first_name' => 'Faith', 'last_name' => 'Morales', 'gender' => 'Female'],
                    ]
                ],

                // GRADE 4 - D (Devoted) - Joy Castillo
                [
                    'grade' => 'Grade 4',
                    'section' => 'Devoted',
                    'students' => [
                        // Male
                        ['first_name' => 'Henry', 'last_name' => 'Bautista', 'gender' => 'Male'],
                        ['first_name' => 'Ivan', 'last_name' => 'Torres', 'gender' => 'Male'],
                        ['first_name' => 'James', 'last_name' => 'Navarro', 'gender' => 'Male'],
                        ['first_name' => 'Kevin', 'last_name' => 'Ramos', 'gender' => 'Male'],
                        ['first_name' => 'Lance', 'last_name' => 'Flores', 'gender' => 'Male'],
                        ['first_name' => 'Mark', 'last_name' => 'Castillo', 'gender' => 'Male'],
                        ['first_name' => 'Nathan', 'last_name' => 'Diaz', 'gender' => 'Male'],
                        ['first_name' => 'Oliver', 'last_name' => 'Hernandez', 'gender' => 'Male'],
                        ['first_name' => 'Patrick', 'last_name' => 'Morales', 'gender' => 'Male'],
                        ['first_name' => 'Quinn', 'last_name' => 'Salazar', 'gender' => 'Male'],
                        ['first_name' => 'Ryan', 'last_name' => 'Dominguez', 'gender' => 'Male'],
                        // Female
                        ['first_name' => 'Gabrielle', 'last_name' => 'Castillo', 'gender' => 'Female'],
                        ['first_name' => 'Hazel', 'last_name' => 'Tan', 'gender' => 'Female'],
                        ['first_name' => 'Isabelle', 'last_name' => 'Reyes', 'gender' => 'Female'],
                        ['first_name' => 'Jasmine', 'last_name' => 'Gonzales', 'gender' => 'Female'],
                        ['first_name' => 'Katrina', 'last_name' => 'Bautista', 'gender' => 'Female'],
                        ['first_name' => 'Lara', 'last_name' => 'Dominguez', 'gender' => 'Female'],
                        ['first_name' => 'Monica', 'last_name' => 'Cabrera', 'gender' => 'Female'],
                        ['first_name' => 'Nicole', 'last_name' => 'Torres', 'gender' => 'Female'],
                        ['first_name' => 'Olivia', 'last_name' => 'Navarro', 'gender' => 'Female'],
                        ['first_name' => 'Pauline', 'last_name' => 'Rivera', 'gender' => 'Female'],
                        ['first_name' => 'Queenie', 'last_name' => 'Flores', 'gender' => 'Female'],
                        ['first_name' => 'Rose', 'last_name' => 'Hernandez', 'gender' => 'Female'],
                        ['first_name' => 'Sofia', 'last_name' => 'Lim', 'gender' => 'Female'],
                    ]
                ],
            ];

            $studentCount = 0;
            $lrnCounter = 100000000000; // Starting LRN

            foreach ($studentsData as $classData) {
                // Find the section
                $section = DB::table('sections as s')
                    ->join('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
                    ->join('grades as g', 'cg.grade_id', '=', 'g.id')
                    ->where('s.name', $classData['section'])
                    ->where('g.name', $classData['grade'])
                    ->select('s.id as section_id', 's.name as section_name', 'g.name as grade_name')
                    ->first();

                if (!$section) {
                    echo "⚠️  Section not found: {$classData['grade']} - {$classData['section']}\n";
                    continue;
                }

                echo "📚 Processing {$section->grade_name} - {$section->section_name}...\n";

                foreach ($classData['students'] as $studentData) {
                    $lrn = (string)($lrnCounter++);
                    
                    // Check if student already exists
                    $existingStudent = DB::table('student_details')
                        ->where('firstName', $studentData['first_name'])
                        ->where('lastName', $studentData['last_name'])
                        ->first();

                    if ($existingStudent) {
                        echo "   ℹ️  Student exists: {$studentData['first_name']} {$studentData['last_name']}\n";
                        $studentId = $existingStudent->id;
                        
                        // Get the students table ID
                        $studentsTableEntry = DB::table('students')->where('studentId', $lrn)->first();
                        $studentsTableId = $studentsTableEntry ? $studentsTableEntry->id : null;
                    } else {
                        // Create student in student_details
                        $studentId = DB::table('student_details')->insertGetId([
                            'lrn' => $lrn,
                            'studentId' => $lrn,
                            'student_id' => $lrn,
                            'firstName' => $studentData['first_name'],
                            'lastName' => $studentData['last_name'],
                            'middleName' => '',
                            'name' => $studentData['first_name'] . ' ' . $studentData['last_name'],
                            'gender' => $studentData['gender'],
                            'sex' => $studentData['gender'],
                            'birthdate' => Carbon::now()->subYears(rand(6, 12))->format('Y-m-d'),
                            'birthplace' => 'Naawan, Misamis Oriental',
                            'age' => rand(6, 12),
                            'email' => strtolower($studentData['first_name'] . '.' . $studentData['last_name']) . '@student.msunaawan.edu.ph',
                            'contactInfo' => '09' . rand(100000000, 999999999),
                            'currentAddress' => json_encode(['street' => '', 'barangay' => 'Poblacion', 'city' => 'Naawan', 'province' => 'Misamis Oriental', 'zipCode' => '9023']),
                            'permanentAddress' => json_encode(['street' => '', 'barangay' => 'Poblacion', 'city' => 'Naawan', 'province' => 'Misamis Oriental', 'zipCode' => '9023']),
                            'address' => 'Naawan, Misamis Oriental',
                            'father' => json_encode(['firstName' => 'Juan', 'lastName' => $studentData['last_name'], 'occupation' => '', 'contact' => '09' . rand(100000000, 999999999)]),
                            'mother' => json_encode(['firstName' => 'Maria', 'lastName' => $studentData['last_name'], 'occupation' => '', 'contact' => '09' . rand(100000000, 999999999)]),
                            'parentName' => 'Juan ' . $studentData['last_name'],
                            'parentContact' => '09' . rand(100000000, 999999999),
                            'gradeLevel' => $classData['grade'],
                            'section' => $classData['section'],
                            'admissionDate' => Carbon::now()->subMonths(2)->format('Y-m-d'),
                            'enrollmentDate' => Carbon::now()->subMonths(1)->format('Y-m-d'),
                            'status' => 'active',
                            'isActive' => true,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $studentCount++;
                        echo "   ✅ Created: {$studentData['first_name']} {$studentData['last_name']} (LRN: {$lrn})\n";
                        
                        // Also create in students table for foreign key
                        $studentsTableId = DB::table('students')->insertGetId([
                            'studentId' => $lrn,
                            'name' => $studentData['first_name'] . ' ' . $studentData['last_name'],
                            'firstName' => $studentData['first_name'],
                            'lastName' => $studentData['last_name'],
                            'middleName' => '',
                            'email' => strtolower($studentData['first_name'] . '.' . $studentData['last_name']) . '@student.msunaawan.edu.ph',
                            'gradeLevel' => $classData['grade'],
                            'section' => $classData['section'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    // Link student to section via pivot table
                    if ($studentsTableId) {
                        $existingLink = DB::table('student_section')
                            ->where('student_id', $studentsTableId)
                            ->where('section_id', $section->section_id)
                            ->where('school_year', '2025-2026')
                            ->first();

                        if (!$existingLink) {
                            DB::table('student_section')->insert([
                                'student_id' => $studentsTableId,
                                'section_id' => $section->section_id,
                                'school_year' => '2025-2026',
                                'is_active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }

                echo "   ✅ Completed {$section->grade_name} - {$section->section_name}\n\n";
            }

            DB::commit();

            echo "========================================\n";
            echo "✅ NCS STUDENTS SEEDER COMPLETE!\n";
            echo "========================================\n\n";
            echo "📊 Summary:\n";
            echo "   Students created: {$studentCount}\n";
            echo "   Grade 1 - Admirable: 16 students\n";
            echo "   Grade 4 - Devoted: 24 students\n";
            echo "   Total: 40 students\n\n";
            echo "📝 Note: Students are linked to their sections\n";
            echo "   and ready for attendance tracking.\n\n";

        } catch (\Exception $e) {
            DB::rollBack();
            echo "❌ Error: " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            throw $e;
        }
    }
}
