<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Section;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

class NaawaanHomeroomAssignmentSeeder extends Seeder
{
    /**
     * Assign homeroom teachers to sections for Naawan Central School
     * Distributes teachers evenly across sections
     */
    public function run(): void
    {
        // Get all sections without homeroom teachers
        $sections = Section::whereNull('homeroom_teacher_id')
            ->with('curriculumGrade.grade')
            ->orderBy('id')
            ->get();

        // Get all available teachers
        $teachers = Teacher::with('user')->get();

        if ($teachers->count() === 0) {
            $this->command->error('❌ No teachers found! Run teacher seeder first.');
            return;
        }

        if ($sections->count() === 0) {
            $this->command->info('✅ All sections already have homeroom teachers assigned.');
            return;
        }

        $assignmentsCreated = 0;
        $teacherIndex = 0;

        foreach ($sections as $section) {
            // Assign teacher in round-robin fashion
            $teacher = $teachers[$teacherIndex % $teachers->count()];
            
            $section->homeroom_teacher_id = $teacher->id;
            $section->save();
            
            $gradeName = $section->curriculumGrade->grade->name ?? 'Unknown Grade';
            $teacherName = $teacher->first_name . ' ' . $teacher->last_name;
            
            $this->command->info("✅ Assigned {$teacherName} to {$gradeName} - {$section->name}");
            
            $assignmentsCreated++;
            $teacherIndex++;
        }

        $this->command->info('🏫 Naawan Central School homeroom assignments completed!');
        $this->command->info("📊 Total assignments created: {$assignmentsCreated}");
        $this->command->info("👨‍🏫 Teachers utilized: " . min($teachers->count(), $sections->count()));
        
        // Now create homeroom schedules
        $this->call(NaawaanHomeroomScheduleSeeder::class);
    }
}
