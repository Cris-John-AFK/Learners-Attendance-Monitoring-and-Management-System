<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subject;

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
                'id' => 'MATH101',
                'name' => 'Mathematics',
                'grade' => 'Grade 1',
                'description' => 'Basic mathematics',
                'credits' => 3,
            ],
            [
                'id' => 'MATH102',
                'name' => 'Mathematics',
                'grade' => 'Grade 2',
                'description' => 'Advanced mathematics',
                'credits' => 3,
            ],
            [
                'id' => 'MATH103',
                'name' => 'Mathematics',
                'grade' => 'Grade 3',
                'description' => 'Advanced mathematics',
                'credits' => 3,
            ],
            [
                'id' => 'ENG101',
                'name' => 'English',
                'grade' => 'Grade 1',
                'description' => 'Basic English',
                'credits' => 3,
            ],
            [
                'id' => 'ENG102',
                'name' => 'English',
                'grade' => 'Grade 2',
                'description' => 'Advanced English',
                'credits' => 3,
            ],
            [
                'id' => 'ENG103',
                'name' => 'English',
                'grade' => 'Grade 3',
                'description' => 'Advanced English',
                'credits' => 3,
            ],
            [
                'id' => 'SCI101',
                'name' => 'Science',
                'grade' => 'Grade 1',
                'description' => 'Basic Science',
                'credits' => 3,
            ],
            [
                'id' => 'SCI102',
                'name' => 'Science',
                'grade' => 'Grade 2',
                'description' => 'Advanced Science',
                'credits' => 3,
            ],
            [
                'id' => 'SCI103',
                'name' => 'Science',
                'grade' => 'Grade 3',
                'description' => 'Advanced Science',
                'credits' => 3,
            ],
        ];

        foreach ($subjects as $subject) {
            Subject::create($subject);
        }
    }
}
