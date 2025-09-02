<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Section;
use App\Models\Grade;
use App\Models\Curriculum;
use App\Models\CurriculumGrade;
use Illuminate\Support\Facades\DB;

class NaawaanSectionsSeeder extends Seeder
{
    /**
     * Seed sections for Naawan Central School
     * Creates 5 sections per grade level with realistic Filipino section names
     */
    public function run(): void
    {
        // Clear existing sections to avoid duplicates (PostgreSQL compatible)
        DB::statement('TRUNCATE TABLE sections RESTART IDENTITY CASCADE;');

        // Get or create the main curriculum
        $curriculum = Curriculum::firstOrCreate([
            'name' => 'Naawan Central School Curriculum'
        ], [
            'description' => 'Official curriculum for Naawan Central School following K-12 system',
            'start_year' => now()->year,
            'end_year' => now()->year + 1,
            'is_active' => true
        ]);

        // Get all grades
        $grades = Grade::orderBy('display_order')->get();

        // Filipino section names representing positive values and local culture
        $sectionNames = [
            'Matatag',    // Strong/Resilient
            'Masigasig',  // Diligent/Hardworking
            'Malikhain',  // Creative
            'Mapagmahal', // Loving/Caring
            'Matulungin'  // Helpful
        ];

        $sectionsCreated = 0;

        foreach ($grades as $grade) {
            // Ensure curriculum-grade relationship exists
            $curriculumGrade = CurriculumGrade::firstOrCreate([
                'curriculum_id' => $curriculum->id,
                'grade_id' => $grade->id
            ]);

            // Create 5 sections for each grade
            foreach ($sectionNames as $index => $sectionName) {
                $capacity = $this->getCapacityByGradeLevel($grade->level);
                
                Section::create([
                    'name' => $sectionName,
                    'description' => "Section {$sectionName} for {$grade->name} - Naawan Central School",
                    'capacity' => $capacity,
                    'curriculum_grade_id' => $curriculumGrade->id,
                    'homeroom_teacher_id' => null, // Will be assigned by admin
                    'is_active' => true
                ]);

                $sectionsCreated++;
            }

            $this->command->info("✅ Created 5 sections for {$grade->name}");
        }

        $this->command->info("🏫 Naawan Central School sections seeded successfully!");
        $this->command->info("📊 Total sections created: {$sectionsCreated}");
        $this->command->info("👥 Sections per grade: 5");
        $this->command->info("🎯 Ready for homeroom teacher assignments by admin");
    }

    /**
     * Get appropriate class capacity based on grade level
     * Following Philippine DepEd guidelines for class sizes
     */
    private function getCapacityByGradeLevel(string $level): int
    {
        return match($level) {
            '0' => 25,      // Kindergarten: smaller classes for better attention
            '1', '2', '3' => 30,  // Primary: moderate class sizes
            '4', '5', '6' => 35,  // Intermediate: standard class sizes
            '7', '8', '9', '10' => 40,  // Junior High: larger classes
            '11', '12' => 35,     // Senior High: smaller for specialized learning
            default => 30
        };
    }
}
