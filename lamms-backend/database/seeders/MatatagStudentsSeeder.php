<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MatatagStudentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        
        // Create 24 students for Matatag section
        for ($i = 1; $i <= 24; $i++) {
            $studentId = DB::table('students')->insertGetId([
                'studentId' => 'STU' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'lrn' => '1234567890' . str_pad($i, 2, '0', STR_PAD_LEFT),
                'firstName' => 'Student',
                'lastName' => 'Number ' . $i,
                'middleName' => '',
                'name' => 'Student Number ' . $i,
                'gender' => $i % 2 == 0 ? 'Female' : 'Male',
                'sex' => $i % 2 == 0 ? 'Female' : 'Male',
                'birthdate' => '2018-01-01',
                'age' => 7,
                'gradeLevel' => 'Kinder',
                'section' => 'Matatag',
                'schoolYearStart' => 2025,
                'schoolYearEnd' => 2026,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now
            ]);
            
            // Enroll in Matatag section (section_id = 1)
            DB::table('student_section')->insert([
                'student_id' => $studentId,
                'section_id' => 1,
                'enrollment_date' => $now,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }
        
        echo "Created 24 students and enrolled them in Matatag section\n";
    }
}
