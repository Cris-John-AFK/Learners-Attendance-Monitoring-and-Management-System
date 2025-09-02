<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\CurriculumGrade;
use Illuminate\Support\Facades\DB;

class NaawaanCurriculumSeeder extends Seeder
{
    /**
     * Seed curriculum for Naawan Central School
     * Creates a simple school year-based curriculum structure
     */
    public function run(): void
    {
        // Clear existing curriculum data to avoid duplicates (PostgreSQL compatible)
        DB::statement('TRUNCATE TABLE curriculum_grade RESTART IDENTITY CASCADE;');
        DB::statement('TRUNCATE TABLE curricula RESTART IDENTITY CASCADE;');

        // Create main curriculum for current school year
        $currentYear = now()->year;
        $nextYear = $currentYear + 1;

        $curriculum = Curriculum::create([
            'name' => "Naawan Central School Curriculum SY {$currentYear}-{$nextYear}",
            'description' => "Official K-12 curriculum for Naawan Central School following DepEd guidelines for School Year {$currentYear}-{$nextYear}",
            'start_year' => $currentYear,
            'end_year' => $nextYear,
            'status' => 'Active',
            'is_active' => true
        ]);

        // Get all grades and associate them with the curriculum
        $grades = Grade::orderBy('display_order')->get();
        
        foreach ($grades as $grade) {
            CurriculumGrade::create([
                'curriculum_id' => $curriculum->id,
                'grade_id' => $grade->id
            ]);
        }

        $this->command->info('🏫 Naawan Central School curriculum seeded successfully!');
        $this->command->info("📅 School Year: {$currentYear}-{$nextYear}");
        $this->command->info("📚 Grade levels associated: " . $grades->count());
        $this->command->info('✅ Ready for section and subject assignments');
    }
}
