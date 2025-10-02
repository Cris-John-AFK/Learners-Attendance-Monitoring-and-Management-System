<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CleanupKinderGrades extends Seeder
{
    /**
     * Remove duplicate Kinder grades - keep only ONE "Kinder" grade
     */
    public function run(): void
    {
        $this->command->info('🧹 Cleaning up duplicate Kinder grades...');
        
        DB::beginTransaction();
        
        try {
            // Find all Kinder-related grades
            $kinderGrades = DB::table('grades')
                ->where('name', 'LIKE', '%Kinder%')
                ->orWhere('level', 'LIKE', '%Kinder%')
                ->orWhere('code', 'LIKE', 'K%')
                ->get();
            
            $this->command->info('   Found ' . count($kinderGrades) . ' Kinder grades:');
            foreach ($kinderGrades as $grade) {
                $this->command->info("      - ID {$grade->id}: {$grade->name} (Code: {$grade->code}, Level: {$grade->level})");
            }
            
            // Keep only the one with code 'K' (Kindergarten)
            $keepGrade = $kinderGrades->firstWhere('code', 'K');
            
            if (!$keepGrade) {
                $this->command->error('   ✗ No Kindergarten grade with code "K" found!');
                DB::rollback();
                return;
            }
            
            $this->command->info("   ✓ Keeping grade: {$keepGrade->name} (ID: {$keepGrade->id})");
            
            // Get IDs of grades to delete (all Kinder grades except the one we want to keep)
            $deleteGradeIds = $kinderGrades->where('id', '!=', $keepGrade->id)->pluck('id')->toArray();
            
            if (empty($deleteGradeIds)) {
                $this->command->info('   ✓ No duplicate Kinder grades to delete!');
                DB::commit();
                return;
            }
            
            $this->command->info('   Deleting ' . count($deleteGradeIds) . ' duplicate Kinder grades...');
            
            // Get curriculum_grade IDs for these duplicate Kinder grades
            $curriculumGradeIds = DB::table('curriculum_grade')
                ->whereIn('grade_id', $deleteGradeIds)
                ->pluck('id')
                ->toArray();
            
            if (!empty($curriculumGradeIds)) {
                // Delete sections linked to duplicate Kinder curriculum_grades
                $deletedSections = DB::table('sections')
                    ->whereIn('curriculum_grade_id', $curriculumGradeIds)
                    ->delete();
                $this->command->info("   ✓ Deleted $deletedSections sections for duplicate Kinder grades");
                
                // Delete curriculum_grade entries
                $deletedCurriculumGrades = DB::table('curriculum_grade')
                    ->whereIn('id', $curriculumGradeIds)
                    ->delete();
                $this->command->info("   ✓ Deleted $deletedCurriculumGrades curriculum-grade entries");
            }
            
            // Delete the duplicate grade entries themselves
            $deletedGrades = DB::table('grades')
                ->whereIn('id', $deleteGradeIds)
                ->delete();
            $this->command->info("   ✓ Deleted $deletedGrades duplicate Kinder grade levels");
            
            DB::commit();
            
            $this->command->info('');
            $this->command->info('✅ ===== CLEANUP COMPLETE =====');
            $this->command->info('🎓 Now there is only ONE Kindergarten grade!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error during cleanup: ' . $e->getMessage());
            throw $e;
        }
    }
}
