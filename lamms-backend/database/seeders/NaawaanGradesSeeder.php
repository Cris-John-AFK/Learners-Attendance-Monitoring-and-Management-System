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
            // KINDERGARTEN
            [
                'code' => 'KG',
                'name' => 'Kindergarten',
                'level' => '0',
                'description' => 'Kindergarten (Age 5-6)',
                'display_order' => 1,
                'is_active' => true
            ],

            // PRIMARY EDUCATION (Grades 1-6)
            [
                'code' => 'G1',
                'name' => 'Grade 1',
                'level' => '1',
                'description' => 'First Grade - Primary Education',
                'display_order' => 2,
                'is_active' => true
            ],
            [
                'code' => 'G2',
                'name' => 'Grade 2',
                'level' => '2',
                'description' => 'Second Grade - Primary Education',
                'display_order' => 3,
                'is_active' => true
            ],
            [
                'code' => 'G3',
                'name' => 'Grade 3',
                'level' => '3',
                'description' => 'Third Grade - Primary Education',
                'display_order' => 4,
                'is_active' => true
            ],
            [
                'code' => 'G4',
                'name' => 'Grade 4',
                'level' => '4',
                'description' => 'Fourth Grade - Intermediate Education',
                'display_order' => 5,
                'is_active' => true
            ],
            [
                'code' => 'G5',
                'name' => 'Grade 5',
                'level' => '5',
                'description' => 'Fifth Grade - Intermediate Education',
                'display_order' => 6,
                'is_active' => true
            ],
            [
                'code' => 'G6',
                'name' => 'Grade 6',
                'level' => '6',
                'description' => 'Sixth Grade - Intermediate Education',
                'display_order' => 7,
                'is_active' => true
            ]
        ];

        // Insert grades into database
        foreach ($grades as $grade) {
            Grade::create($grade);
        }

        $this->command->info('✅ Naawan Central School grade levels seeded successfully!');
        $this->command->info('📚 Total grade levels created: ' . count($grades));
        $this->command->info('🎓 Coverage: Kindergarten to Grade 6 (Elementary Education)');
    }
}
