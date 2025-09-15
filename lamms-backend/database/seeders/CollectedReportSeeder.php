<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CollectedReport;

class CollectedReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CollectedReport::create([
            'grade_level' => 'Kindergarten',
            'section' => 'Kinder A',
            'school_id' => '123456',
            'school_year' => '2024-2025',
            'month' => 'September 2025',
            'total_students' => 25,
            'present_today' => 23,
            'absent_today' => 2,
            'attendance_rate' => 92.00,
            'teacher_name' => 'Ms. Lisa Chen',
        ]);

        CollectedReport::create([
            'grade_level' => 'Grade 1',
            'section' => 'Grade 1-A',
            'school_id' => '123456',
            'school_year' => '2024-2025',
            'month' => 'September 2025',
            'total_students' => 30,
            'present_today' => 28,
            'absent_today' => 2,
            'attendance_rate' => 93.33,
            'teacher_name' => 'Mrs. Maria Santos',
        ]);
    }
}
