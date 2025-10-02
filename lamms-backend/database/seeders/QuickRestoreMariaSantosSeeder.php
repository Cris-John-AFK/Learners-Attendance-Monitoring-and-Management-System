<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuickRestoreMariaSantosSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔧 Quick restore for Maria Santos...');
        
        // Get Maria Santos (teacher_id: 1)
        $maria = DB::table('teachers')->where('id', 1)->first();
        
        if (!$maria) {
            $this->command->error('Maria Santos not found!');
            return;
        }
        
        // Get first available section (from Kindergarten)
        $section = DB::table('sections')
            ->join('curriculum_grade', 'sections.curriculum_grade_id', '=', 'curriculum_grade.id')
            ->join('grades', 'curriculum_grade.grade_id', '=', 'grades.id')
            ->where('grades.level', 'Kinder')
            ->select('sections.*')
            ->first();
        
        if (!$section) {
            $this->command->error('No kindergarten section found! Run ComprehensiveNaawaanSeeder first.');
            return;
        }
        
        // Get English and Arts subjects
        $english = DB::table('subjects')->where('name', 'English')->first();
        $arts = DB::table('subjects')->where('name', 'Arts')->first();
        
        if (!$english || !$arts) {
            $this->command->error('Subjects not found!');
            return;
        }
        
        // Assign Maria Santos to teach English and Arts
        DB::table('teacher_section_subject')->insert([
            [
                'teacher_id' => 1,
                'section_id' => $section->id,
                'subject_id' => $english->id,
                'is_active' => true
            ],
            [
                'teacher_id' => 1,
                'section_id' => $section->id,
                'subject_id' => $arts->id,
                'is_active' => true
            ]
        ]);
        
        $this->command->info("✅ Maria Santos assigned to {$section->name}");
        $this->command->info("   - English");
        $this->command->info("   - Arts");
        $this->command->info('');
        $this->command->info('🎓 Now login as maria.santos to test!');
    }
}
