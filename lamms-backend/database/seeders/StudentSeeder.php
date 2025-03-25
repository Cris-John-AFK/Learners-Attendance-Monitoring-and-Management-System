<?php

namespace Database\Seeders;

use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run()
    {
        // Filipino first names
        $firstNames = [
            'Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Antonio', 'Nena'
        ];

        // Filipino last names
        $lastNames = [
            'Santos', 'Reyes', 'Cruz', 'Bautista', 'Mendoza', 'Garcia'
        ];

        // Sections per grade level
        $sections = [
            0 => ['Mabini', 'Rizal'], // Kinder
            1 => ['Sampaguita', 'Rosal'], // Grade 1
            2 => ['Bonifacio', 'Mabini'], // Grade 2
            3 => ['Aguinaldo', 'Quezon'], // Grade 3
            4 => ['Del Pilar', 'Luna'], // Grade 4
            5 => ['Orchid', 'Jasmine'], // Grade 5
            6 => ['Emerald', 'Ruby'], // Grade 6
        ];

        // Generate students
        $studentId = 1000;

        // Generate 10 students for each grade level (distributed across sections)
        for ($gradeLevel = 0; $gradeLevel <= 6; $gradeLevel++) {
            $gradeSections = $sections[$gradeLevel];

            for ($i = 0; $i < 10; $i++) {
                $studentId++;
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $name = $firstName . ' ' . $lastName;
                $section = $gradeSections[array_rand($gradeSections)];
                $gender = rand(0, 1) ? 'Male' : 'Female';

                Student::create([
                    'name' => $name,
                    'gradeLevel' => $gradeLevel,
                    'section' => $section,
                    'studentId' => 'S' . $studentId,
                    'gender' => $gender,
                    'contactInfo' => '09' . rand(100000000, 999999999),
                    'parentName' => 'Mr/Mrs. ' . $lastName,
                    'parentContact' => '09' . rand(100000000, 999999999),
                ]);
            }
        }
    }
}
