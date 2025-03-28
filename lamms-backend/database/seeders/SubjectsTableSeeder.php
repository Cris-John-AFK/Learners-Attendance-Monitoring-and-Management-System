<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Subject;
use App\Models\Grade;

class SubjectsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        Subject::truncate();

        $subjects = [
            [
                'code' => 'MATH101',
                'name' => 'Mathematics',
                'description' => 'Basic mathematics',
                'is_active' => true,
                'grades' => ['1']
            ],
            [
                'code' => 'MATH102',
                'name' => 'Mathematics',
                'description' => 'Advanced mathematics',
                'is_active' => true,
                'grades' => ['2']
            ],
            [
                'code' => 'MATH103',
                'name' => 'Mathematics',
                'description' => 'Advanced mathematics',
                'is_active' => true,
                'grades' => ['3']
            ],
            [
                'code' => 'ENG101',
                'name' => 'English',
                'description' => 'Basic English',
                'is_active' => true,
                'grades' => ['1']
            ],
            [
                'code' => 'ENG102',
                'name' => 'English',
                'description' => 'Advanced English',
                'is_active' => true,
                'grades' => ['2']
            ],
            [
                'code' => 'ENG103',
                'name' => 'English',
                'description' => 'Advanced English',
                'is_active' => true,
                'grades' => ['3']
            ],
            [
                'code' => 'SCI101',
                'name' => 'Science',
                'description' => 'Basic Science',
                'is_active' => true,
                'grades' => ['1']
            ],
            [
                'code' => 'SCI102',
                'name' => 'Science',
                'description' => 'Advanced Science',
                'is_active' => true,
                'grades' => ['2']
            ],
            [
                'code' => 'SCI103',
                'name' => 'Science',
                'description' => 'Advanced Science',
                'is_active' => true,
                'grades' => ['3']
            ],
        ];

        foreach ($subjects as $subjectData) {
            $grades = $subjectData['grades'];
            unset($subjectData['grades']);

            $subject = Subject::create($subjectData);

            // Attach grades to the subject
            foreach ($grades as $gradeCode) {
                $grade = Grade::where('code', $gradeCode)->first();
                if ($grade) {
                    $subject->grades()->attach($grade->id);
                }
            }
        }
    }
}
