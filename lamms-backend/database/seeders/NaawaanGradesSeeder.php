<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Grade;
use Illuminate\Support\Facades\DB;

class NaawaanGradesSeeder extends Seeder
{
    /**
     * Seed grade levels for Naawan Central School following Philippine K-12 system
     * Complete grade structure from Kindergarten to Grade 12
     */
    public function run(): void
    {
        // Clear existing grades to avoid duplicates (PostgreSQL compatible)
        DB::statement('TRUNCATE TABLE grades RESTART IDENTITY CASCADE;');

        $grades = [
            // KINDERGARTEN LEVELS
            [
                'code' => 'K1',
                'name' => 'Kinder 1',
                'level' => '0',
                'description' => 'Kindergarten Level 1 (Age 5)',
                'display_order' => 1,
                'is_active' => true
            ],
            [
                'code' => 'K2',
                'name' => 'Kinder 2',
                'level' => '0',
                'description' => 'Kindergarten Level 2 (Age 6)',
                'display_order' => 2,
                'is_active' => true
            ],

            // PRIMARY EDUCATION (Grades 1-6)
            [
                'code' => 'G1',
                'name' => 'Grade 1',
                'level' => '1',
                'description' => 'First Grade - Primary Education',
                'display_order' => 3,
                'is_active' => true
            ],
            [
                'code' => 'G2',
                'name' => 'Grade 2',
                'level' => '2',
                'description' => 'Second Grade - Primary Education',
                'display_order' => 4,
                'is_active' => true
            ],
            [
                'code' => 'G3',
                'name' => 'Grade 3',
                'level' => '3',
                'description' => 'Third Grade - Primary Education',
                'display_order' => 5,
                'is_active' => true
            ],
            [
                'code' => 'G4',
                'name' => 'Grade 4',
                'level' => '4',
                'description' => 'Fourth Grade - Intermediate Education',
                'display_order' => 6,
                'is_active' => true
            ],
            [
                'code' => 'G5',
                'name' => 'Grade 5',
                'level' => '5',
                'description' => 'Fifth Grade - Intermediate Education',
                'display_order' => 7,
                'is_active' => true
            ],
            [
                'code' => 'G6',
                'name' => 'Grade 6',
                'level' => '6',
                'description' => 'Sixth Grade - Intermediate Education',
                'display_order' => 8,
                'is_active' => true
            ],

            // JUNIOR HIGH SCHOOL (Grades 7-10)
            [
                'code' => 'G7',
                'name' => 'Grade 7',
                'level' => '7',
                'description' => 'Seventh Grade - Junior High School',
                'display_order' => 9,
                'is_active' => true
            ],
            [
                'code' => 'G8',
                'name' => 'Grade 8',
                'level' => '8',
                'description' => 'Eighth Grade - Junior High School',
                'display_order' => 10,
                'is_active' => true
            ],
            [
                'code' => 'G9',
                'name' => 'Grade 9',
                'level' => '9',
                'description' => 'Ninth Grade - Junior High School',
                'display_order' => 11,
                'is_active' => true
            ],
            [
                'code' => 'G10',
                'name' => 'Grade 10',
                'level' => '10',
                'description' => 'Tenth Grade - Junior High School',
                'display_order' => 12,
                'is_active' => true
            ],

            // SENIOR HIGH SCHOOL (Grades 11-12)
            [
                'code' => 'G11',
                'name' => 'Grade 11',
                'level' => '11',
                'description' => 'Eleventh Grade - Senior High School',
                'display_order' => 13,
                'is_active' => true
            ],
            [
                'code' => 'G12',
                'name' => 'Grade 12',
                'level' => '12',
                'description' => 'Twelfth Grade - Senior High School',
                'display_order' => 14,
                'is_active' => true
            ]
        ];

        // Insert grades into database
        foreach ($grades as $grade) {
            Grade::create($grade);
        }

        $this->command->info('✅ Naawan Central School grade levels seeded successfully!');
        $this->command->info('📚 Total grade levels created: ' . count($grades));
        $this->command->info('🎓 Coverage: Kindergarten to Grade 12 (K-12 System)');
    }
}
