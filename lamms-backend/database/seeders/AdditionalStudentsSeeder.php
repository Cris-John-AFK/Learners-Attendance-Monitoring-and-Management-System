<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;

class AdditionalStudentsSeeder extends Seeder
{
    public function run()
    {
        // Male students (25)
        $maleNames = [
            'Bali Male 1', 'Bali Male 2', 'Bali Male 3', 'Bali Male 4', 'Bali Male 5',
            'Bali Male 6', 'Bali Male 7', 'Bali Male 8', 'Bali Male 9', 'Bali Male 10',
            'Bali Male 11', 'Bali Male 12', 'Bali Male 13', 'Bali Male 14', 'Bali Male 15',
            'Bali Male 16', 'Bali Male 17', 'Bali Male 18', 'Bali Male 19', 'Bali Male 20',
            'Bali Male 21', 'Bali Male 22', 'Bali Male 23', 'Bali Male 24', 'Bali Male 25'
        ];

        // Female students (25)
        $femaleNames = [
            'Bali Female 1', 'Bali Female 2', 'Bali Female 3', 'Bali Female 4', 'Bali Female 5',
            'Bali Female 6', 'Bali Female 7', 'Bali Female 8', 'Bali Female 9', 'Bali Female 10',
            'Bali Female 11', 'Bali Female 12', 'Bali Female 13', 'Bali Female 14', 'Bali Female 15',
            'Bali Female 16', 'Bali Female 17', 'Bali Female 18', 'Bali Female 19', 'Bali Female 20',
            'Bali Female 21', 'Bali Female 22', 'Bali Female 23', 'Bali Female 24', 'Bali Female 25'
        ];

        // Sections for distribution
        $sections = ['Matatag', 'Sampaguita', 'Rizal', 'Mabini', 'Bonifacio'];
        $gradeLevels = [1, 2, 3, 4, 5, 6];

        $studentId = 2000; // Starting ID to avoid conflicts

        // Add male students
        foreach ($maleNames as $index => $name) {
            $studentId++;
            $section = $sections[array_rand($sections)];
            $gradeLevel = $gradeLevels[array_rand($gradeLevels)];

            Student::create([
                'name' => $name,
                'gradeLevel' => $gradeLevel,
                'section' => $section,
                'studentId' => 'S' . $studentId,
                'student_id' => 'S' . $studentId,
                'gender' => 'Male',
                'contactInfo' => '09' . rand(100000000, 999999999),
                'parentName' => 'Mr/Mrs. Parent ' . $studentId,
                'parentContact' => '09' . rand(100000000, 999999999),
            ]);
        }

        // Add female students
        foreach ($femaleNames as $index => $name) {
            $studentId++;
            $section = $sections[array_rand($sections)];
            $gradeLevel = $gradeLevels[array_rand($gradeLevels)];

            Student::create([
                'name' => $name,
                'gradeLevel' => $gradeLevel,
                'section' => $section,
                'studentId' => 'S' . $studentId,
                'student_id' => 'S' . $studentId,
                'gender' => 'Female',
                'contactInfo' => '09' . rand(100000000, 999999999),
                'parentName' => 'Mr/Mrs. Parent ' . $studentId,
                'parentContact' => '09' . rand(100000000, 999999999),
            ]);
        }

        $this->command->info('Added 50 additional students: 25 male and 25 female');
    }
}
