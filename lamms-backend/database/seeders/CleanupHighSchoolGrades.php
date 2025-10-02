<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CleanupHighSchoolGrades extends Seeder
{
    /**
     * Remove all high school grades (Grade 7-12) and related data
     * from Naawan Central School (Elementary School Only)
     */
    public function run(): void
    {
        $this->command->info('🧹 Cleaning up high school grades from elementary school database...');
        
        DB::beginTransaction();
        
        try {
            // Get IDs of high school grades (Grade 7-12)
            $highSchoolGradeCodes = ['G7', 'G8', 'G9', 'G10', 'G11', 'G12'];
            $highSchoolGradeIds = DB::table('grades')
                ->whereIn('code', $highSchoolGradeCodes)
                ->pluck('id')
                ->toArray();
            
            if (empty($highSchoolGradeIds)) {
                $this->command->info('   ✓ No high school grades found. Database is already clean!');
                DB::commit();
                return;
            }
            
            $this->command->info('   Found ' . count($highSchoolGradeIds) . ' high school grades to remove...');
            
            // Get curriculum_grade IDs for these high school grades
            $curriculumGradeIds = DB::table('curriculum_grade')
                ->whereIn('grade_id', $highSchoolGradeIds)
                ->pluck('id')
                ->toArray();
            
            $this->command->info('   Found ' . count($curriculumGradeIds) . ' curriculum-grade relationships...');
            
            // Delete sections linked to high school curriculum_grades
            $deletedSections = DB::table('sections')
                ->whereIn('curriculum_grade_id', $curriculumGradeIds)
                ->delete();
            $this->command->info("   ✓ Deleted $deletedSections high school sections");
            
            // Delete curriculum_grade entries first (this will cascade to other tables if foreign keys are set)
            $deletedCurriculumGrades = DB::table('curriculum_grade')
                ->whereIn('id', $curriculumGradeIds)
                ->delete();
            $this->command->info("   ✓ Deleted $deletedCurriculumGrades curriculum-grade entries");
            
            // Delete the grade entries themselves
            $deletedGrades = DB::table('grades')
                ->whereIn('id', $highSchoolGradeIds)
                ->delete();
            $this->command->info("   ✓ Deleted $deletedGrades high school grade levels");
            
            // Delete high school subjects (Grade 7+ subjects)
            $highSchoolSubjects = [
                'Oral Communication', 'General Mathematics', 'Earth and Life Science',
                'Personal Development', 'Physical Education and Health', 'Filipino (Komunikasyon)',
                'Understanding Culture', 'Introduction to Philosophy', 'Statistics and Probability'
            ];
            
            $deletedSubjects = DB::table('subjects')
                ->whereIn('name', $highSchoolSubjects)
                ->delete();
            $this->command->info("   ✓ Deleted $deletedSubjects high school-only subjects");
            
            DB::commit();
            
            $this->command->info('');
            $this->command->info('✅ ===== CLEANUP COMPLETE =====');
            $this->command->info('🎓 Naawan Central School now has ONLY elementary grades (Kinder-Grade 6)');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error during cleanup: ' . $e->getMessage());
            throw $e;
        }
    }
}
