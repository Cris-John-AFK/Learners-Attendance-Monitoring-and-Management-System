<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Faker\Factory as Faker;

class Grade4to6StudentsSeeder extends Seeder
{
    /**
     * Seed students for Grade 4-6 sections
     */
    public function run(): void
    {
        $faker = Faker::create('en_PH');
        
        Log::info('🎓 Starting Grade 4-6 Students Seeder');
        
        // Grade 4-6 section mapping
        $sections = [
            'Grade 4' => [
                ['id' => 166, 'name' => 'Silang'],
                ['id' => 167, 'name' => 'Dagohoy'],
            ],
            'Grade 5' => [
                ['id' => 168, 'name' => 'Tandang Sora'],
                ['id' => 169, 'name' => 'Gabriela'],
            ],
            'Grade 6' => [
                ['id' => 170, 'name' => 'Lapu-Lapu'],
                ['id' => 171, 'name' => 'Magat Salamat'],
            ],
        ];
        
        $studentsPerSection = 30; // 30 students per section
        $currentYear = date('Y');
        
        // Start counter from highest existing student ID + 1
        $maxStudentId = DB::table('student_details')
            ->where('studentId', 'like', "NCS-{$currentYear}-%")
            ->max('studentId');
        
        $studentCounter = 1;
        if ($maxStudentId) {
            // Extract number from NCS-2025-XXXXX format
            $lastNumber = (int)substr($maxStudentId, -5);
            $studentCounter = $lastNumber + 1;
        }
        
        Log::info("Starting student counter from: {$studentCounter}");
        
        DB::beginTransaction();
        
        try {
            foreach ($sections as $gradeLevel => $gradeSections) {
                foreach ($gradeSections as $section) {
                    Log::info("Creating students for {$gradeLevel} - {$section['name']}");
                    
                    for ($i = 1; $i <= $studentsPerSection; $i++) {
                        // Generate student data
                        $gender = $faker->randomElement(['male', 'female']);
                        $firstName = $gender === 'male' ? $faker->firstNameMale : $faker->firstNameFemale;
                        $lastName = $faker->lastName;
                        $middleName = $faker->lastName;
                        
                        // Create unique LRN
                        $lrn = sprintf('%012d', 100000000000 + ($studentCounter * 100) + $i);
                        
                        // Create student record (minimal fields to avoid JSON column issues)
                        $studentIdStr = "NCS-{$currentYear}-" . str_pad($studentCounter, 5, '0', STR_PAD_LEFT);
                        $studentId = DB::table('student_details')->insertGetId([
                            'studentId' => $studentIdStr,
                            'student_id' => $studentIdStr,  // Required column
                            'lrn' => $lrn,
                            'firstName' => $firstName,
                            'middleName' => $middleName,
                            'lastName' => $lastName,
                            'name' => "{$firstName} {$lastName}",
                            'birthdate' => $faker->dateTimeBetween('-12 years', '-10 years')->format('Y-m-d'),
                            'gender' => $gender,
                            'sex' => $gender,
                            'gradeLevel' => str_replace('Grade ', '', $gradeLevel),
                            'enrollment_status' => 'active',
                            'status' => 'active',
                            'current_status' => 'active',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        // Assign student to section
                        DB::table('student_section')->insert([
                            'student_id' => $studentId,
                            'section_id' => $section['id'],
                        ]);
                        
                        $studentCounter++;
                    }
                    
                    Log::info("Created {$studentsPerSection} students for {$gradeLevel} - {$section['name']}");
                }
            }
            
            DB::commit();
            
            $totalStudents = ($studentsPerSection * 6); // 6 sections
            
            Log::info("✅ Successfully created {$totalStudents} Grade 4-6 students");
            $this->command->info("✅ Created {$totalStudents} students across 6 Grade 4-6 sections");
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Error creating Grade 4-6 students: " . $e->getMessage());
            $this->command->error("Error: " . $e->getMessage());
            throw $e;
        }
    }
}
