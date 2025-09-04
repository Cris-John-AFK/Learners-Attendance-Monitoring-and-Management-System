<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

class NaawaanSubjectsSeeder extends Seeder
{
    /**
     * Seed subjects for Naawan Central School following Philippine K-12 curriculum
     * Organized by grade level with appropriate subjects for each level
     */
    public function run(): void
    {
        // Clear existing subjects to avoid duplicates (PostgreSQL compatible)
        DB::statement('TRUNCATE TABLE subjects RESTART IDENTITY CASCADE;');

        $subjects = [
            // KINDERGARTEN SUBJECTS (Age 5-6)
            [
                'code' => 'KINDER-MTB',
                'name' => 'Mother Tongue-Based Multilingual Education',
                'description' => 'Foundation literacy in native language (Cebuano/Filipino)',
                'credits' => 1,
                'grade_levels' => ['K1', 'K2'],
                'is_active' => true
            ],
            [
                'code' => 'KINDER-ENG',
                'name' => 'English',
                'description' => 'Basic English language introduction',
                'credits' => 1,
                'grade_levels' => ['K1', 'K2'],
                'is_active' => true
            ],
            [
                'code' => 'KINDER-FIL',
                'name' => 'Filipino',
                'description' => 'Basic Filipino language',
                'credits' => 1,
                'grade_levels' => ['K1', 'K2'],
                'is_active' => true
            ],
            [
                'code' => 'KINDER-MATH',
                'name' => 'Mathematics',
                'description' => 'Basic numeracy and counting',
                'credits' => 1,
                'grade_levels' => ['K1', 'K2'],
                'is_active' => true
            ],
            [
                'code' => 'KINDER-ARTS',
                'name' => 'Arts',
                'description' => 'Creative expression through drawing and crafts',
                'credits' => 1,
                'grade_levels' => ['K1', 'K2'],
                'is_active' => true
            ],
            [
                'code' => 'KINDER-MUSIC',
                'name' => 'Music',
                'description' => 'Basic music appreciation and singing',
                'credits' => 1,
                'grade_levels' => ['K1', 'K2'],
                'is_active' => true
            ],
            [
                'code' => 'KINDER-PE',
                'name' => 'Physical Education',
                'description' => 'Basic motor skills and physical activities',
                'credits' => 1,
                'grade_levels' => ['K1', 'K2'],
                'is_active' => true
            ],

            // GRADE 1-3 SUBJECTS (Primary Level)
            [
                'code' => 'G1-3-MTB',
                'name' => 'Mother Tongue',
                'description' => 'Native language literacy development',
                'credits' => 2,
                'grade_levels' => ['G1', 'G2', 'G3'],
                'is_active' => true
            ],
            [
                'code' => 'G1-3-FIL',
                'name' => 'Filipino',
                'description' => 'Filipino language and literature',
                'credits' => 2,
                'grade_levels' => ['G1', 'G2', 'G3'],
                'is_active' => true
            ],
            [
                'code' => 'G1-3-ENG',
                'name' => 'English',
                'description' => 'English language and literature',
                'credits' => 2,
                'grade_levels' => ['G1', 'G2', 'G3'],
                'is_active' => true
            ],
            [
                'code' => 'G1-3-MATH',
                'name' => 'Mathematics',
                'description' => 'Basic arithmetic and problem solving',
                'credits' => 2,
                'grade_levels' => ['G1', 'G2', 'G3'],
                'is_active' => true
            ],
            [
                'code' => 'G1-3-AP',
                'name' => 'Araling Panlipunan',
                'description' => 'Social studies and community awareness',
                'credits' => 1,
                'grade_levels' => ['G1', 'G2', 'G3'],
                'is_active' => true
            ],
            [
                'code' => 'G1-3-SCI',
                'name' => 'Science',
                'description' => 'Basic science concepts and nature study',
                'credits' => 1,
                'grade_levels' => ['G1', 'G2', 'G3'],
                'is_active' => true
            ],
            [
                'code' => 'G1-3-MAPEH',
                'name' => 'MAPEH',
                'description' => 'Music, Arts, Physical Education, and Health',
                'credits' => 2,
                'grade_levels' => ['G1', 'G2', 'G3'],
                'is_active' => true
            ],
            [
                'code' => 'G1-3-ESP',
                'name' => 'Edukasyon sa Pagpapakatao',
                'description' => 'Values education and character formation',
                'credits' => 1,
                'grade_levels' => ['G1', 'G2', 'G3'],
                'is_active' => true
            ],

            // GRADE 4-6 SUBJECTS (Intermediate Level)
            [
                'code' => 'G4-6-FIL',
                'name' => 'Filipino',
                'description' => 'Advanced Filipino language and literature',
                'credits' => 2,
                'grade_levels' => ['G4', 'G5', 'G6'],
                'is_active' => true
            ],
            [
                'code' => 'G4-6-ENG',
                'name' => 'English',
                'description' => 'Advanced English language and literature',
                'credits' => 2,
                'grade_levels' => ['G4', 'G5', 'G6'],
                'is_active' => true
            ],
            [
                'code' => 'G4-6-MATH',
                'name' => 'Mathematics',
                'description' => 'Intermediate mathematics concepts',
                'credits' => 2,
                'grade_levels' => ['G4', 'G5', 'G6'],
                'is_active' => true
            ],
            [
                'code' => 'G4-6-SCI',
                'name' => 'Science',
                'description' => 'Elementary science concepts and experiments',
                'credits' => 2,
                'grade_levels' => ['G4', 'G5', 'G6'],
                'is_active' => true
            ],
            [
                'code' => 'G4-6-AP',
                'name' => 'Araling Panlipunan',
                'description' => 'Philippine history, geography, and civics',
                'credits' => 2,
                'grade_levels' => ['G4', 'G5', 'G6'],
                'is_active' => true
            ],
            [
                'code' => 'G4-6-MAPEH',
                'name' => 'MAPEH',
                'description' => 'Music, Arts, Physical Education, and Health',
                'credits' => 2,
                'grade_levels' => ['G4', 'G5', 'G6'],
                'is_active' => true
            ],
            [
                'code' => 'G4-6-ESP',
                'name' => 'Edukasyon sa Pagpapakatao',
                'description' => 'Values education and moral development',
                'credits' => 1,
                'grade_levels' => ['G4', 'G5', 'G6'],
                'is_active' => true
            ],
            [
                'code' => 'G4-6-TLE',
                'name' => 'Technology and Livelihood Education',
                'description' => 'Basic life skills and technology awareness',
                'credits' => 1,
                'grade_levels' => ['G4', 'G5', 'G6'],
                'is_active' => true
            ]
        ];

        // Insert subjects into database
        foreach ($subjects as $subject) {
            Subject::create([
                'code' => $subject['code'],
                'name' => $subject['name'],
                'description' => $subject['description'],
                'credits' => $subject['credits'],
                'is_active' => $subject['is_active']
            ]);
        }

        $this->command->info('✅ Naawan Central School subjects seeded successfully!');
        $this->command->info('📚 Total subjects created: ' . count($subjects));
    }
}
