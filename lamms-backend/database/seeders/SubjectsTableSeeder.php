<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subject;

class SubjectsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create initial subjects data
        $subjects = [
            [
                'id' => 'MATH101',
                'name' => 'Mathematics',
                'grade' => 'Grade 1',
                'description' => 'Basic mathematics',
                'credits' => 3
            ],
            [
                'id' => 'ENG101',
                'name' => 'English',
                'grade' => 'Grade 1',
                'description' => 'English language fundamentals',
                'credits' => 3
            ],
            [
                'id' => 'SCI101',
                'name' => 'Science',
                'grade' => 'Grade 1',
                'description' => 'Basic science concepts',
                'credits' => 3
            ],
            [
                'id' => 'MATH201',
                'name' => 'Mathematics',
                'grade' => 'Grade 2',
                'description' => 'Grade 2 mathematics',
                'credits' => 3
            ],
            [
                'id' => 'ENG201',
                'name' => 'English',
                'grade' => 'Grade 2',
                'description' => 'Grade 2 English',
                'credits' => 3
            ],
            [
                'id' => 'SCI201',
                'name' => 'Science',
                'grade' => 'Grade 2',
                'description' => 'Grade 2 science',
                'credits' => 3
            ],
            [
                'id' => 'MATH301',
                'name' => 'Mathematics',
                'grade' => 'Grade 3',
                'description' => 'Grade 3 mathematics',
                'credits' => 3
            ],
            [
                'id' => 'ENG301',
                'name' => 'English',
                'grade' => 'Grade 3',
                'description' => 'Grade 3 English',
                'credits' => 3
            ]
        ];

        // Insert the subjects data
        foreach ($subjects as $subject) {
            Subject::create($subject);
        }
    }
}
