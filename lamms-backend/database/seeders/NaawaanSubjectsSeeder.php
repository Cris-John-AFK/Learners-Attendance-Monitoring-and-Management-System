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
            ],

            // GRADE 7-10 SUBJECTS (Junior High School)
            [
                'code' => 'G7-10-FIL',
                'name' => 'Filipino',
                'description' => 'Advanced Filipino language, literature, and communication',
                'credits' => 3,
                'grade_levels' => ['G7', 'G8', 'G9', 'G10'],
                'is_active' => true
            ],
            [
                'code' => 'G7-10-ENG',
                'name' => 'English',
                'description' => 'Advanced English language, literature, and communication',
                'credits' => 3,
                'grade_levels' => ['G7', 'G8', 'G9', 'G10'],
                'is_active' => true
            ],
            [
                'code' => 'G7-10-MATH',
                'name' => 'Mathematics',
                'description' => 'Algebra, geometry, and advanced mathematical concepts',
                'credits' => 3,
                'grade_levels' => ['G7', 'G8', 'G9', 'G10'],
                'is_active' => true
            ],
            [
                'code' => 'G7-10-SCI',
                'name' => 'Science',
                'description' => 'Integrated science covering biology, chemistry, physics',
                'credits' => 3,
                'grade_levels' => ['G7', 'G8', 'G9', 'G10'],
                'is_active' => true
            ],
            [
                'code' => 'G7-10-AP',
                'name' => 'Araling Panlipunan',
                'description' => 'Philippine and world history, geography, economics',
                'credits' => 3,
                'grade_levels' => ['G7', 'G8', 'G9', 'G10'],
                'is_active' => true
            ],
            [
                'code' => 'G7-10-MAPEH',
                'name' => 'MAPEH',
                'description' => 'Music, Arts, Physical Education, and Health',
                'credits' => 2,
                'grade_levels' => ['G7', 'G8', 'G9', 'G10'],
                'is_active' => true
            ],
            [
                'code' => 'G7-10-ESP',
                'name' => 'Edukasyon sa Pagpapakatao',
                'description' => 'Values education and character development',
                'credits' => 1,
                'grade_levels' => ['G7', 'G8', 'G9', 'G10'],
                'is_active' => true
            ],
            [
                'code' => 'G7-10-TLE',
                'name' => 'Technology and Livelihood Education',
                'description' => 'Practical skills and career exploration',
                'credits' => 2,
                'grade_levels' => ['G7', 'G8', 'G9', 'G10'],
                'is_active' => true
            ],

            // GRADE 11-12 SUBJECTS (Senior High School - Core)
            [
                'code' => 'G11-12-ORAL',
                'name' => 'Oral Communication',
                'description' => 'Speaking and listening skills development',
                'credits' => 3,
                'grade_levels' => ['G11', 'G12'],
                'is_active' => true
            ],
            [
                'code' => 'G11-12-READ',
                'name' => 'Reading and Writing',
                'description' => 'Advanced literacy and composition skills',
                'credits' => 3,
                'grade_levels' => ['G11', 'G12'],
                'is_active' => true
            ],
            [
                'code' => 'G11-12-MATH',
                'name' => 'General Mathematics',
                'description' => 'Applied mathematics for daily life',
                'credits' => 3,
                'grade_levels' => ['G11', 'G12'],
                'is_active' => true
            ],
            [
                'code' => 'G11-12-EARTH',
                'name' => 'Earth and Life Science',
                'description' => 'Environmental science and biology',
                'credits' => 3,
                'grade_levels' => ['G11', 'G12'],
                'is_active' => true
            ],
            [
                'code' => 'G11-12-PHYS',
                'name' => 'Physical Science',
                'description' => 'Chemistry and physics fundamentals',
                'credits' => 3,
                'grade_levels' => ['G11', 'G12'],
                'is_active' => true
            ],
            [
                'code' => 'G11-12-PHIL',
                'name' => 'Introduction to Philosophy',
                'description' => 'Critical thinking and philosophical concepts',
                'credits' => 3,
                'grade_levels' => ['G11', 'G12'],
                'is_active' => true
            ],
            [
                'code' => 'G11-12-PE',
                'name' => 'Physical Education and Health',
                'description' => 'Advanced physical fitness and health education',
                'credits' => 2,
                'grade_levels' => ['G11', 'G12'],
                'is_active' => true
            ],

            // SPECIALIZED SUBJECTS FOR DIFFERENT TRACKS
            [
                'code' => 'G11-12-RESEARCH',
                'name' => 'Research',
                'description' => 'Research methodology and project development',
                'credits' => 3,
                'grade_levels' => ['G11', 'G12'],
                'is_active' => true
            ],
            [
                'code' => 'G11-12-ENTRE',
                'name' => 'Entrepreneurship',
                'description' => 'Business skills and entrepreneurial mindset',
                'credits' => 3,
                'grade_levels' => ['G11', 'G12'],
                'is_active' => true
            ],
            [
                'code' => 'G11-12-WORK',
                'name' => 'Work Immersion',
                'description' => 'Practical work experience and career preparation',
                'credits' => 4,
                'grade_levels' => ['G12'],
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
