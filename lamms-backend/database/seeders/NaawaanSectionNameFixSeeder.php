<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Section;
use App\Models\Grade;
use Illuminate\Support\Facades\DB;

class NaawaanSectionNameFixSeeder extends Seeder
{
    /**
     * Fix section names to be unique across all grades
     * Format: [Grade][Section] (e.g., K1-Matatag, G7-Masigasig)
     */
    public function run(): void
    {
        $sections = Section::with('curriculumGrade.grade')->orderBy('id')->get();
        $updated = 0;

        foreach ($sections as $section) {
            $grade = $section->curriculumGrade->grade;
            $gradeCode = $grade->code;
            $originalName = $section->name;
            
            // Create unique section name: GradeCode-SectionName
            $newName = $gradeCode . '-' . $originalName;
            
            $section->update([
                'name' => $newName,
                'description' => "Section {$newName} for {$grade->name} - Naawan Central School"
            ]);
            
            $this->command->info("✅ Updated: {$originalName} → {$newName} ({$grade->name})");
            $updated++;
        }

        $this->command->info('🏫 Section names updated successfully!');
        $this->command->info("📊 Total sections updated: {$updated}");
        $this->command->info('🎯 All section names are now unique across the school');
    }
}
