<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Schedule;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

class NaawaanHomeroomScheduleSeeder extends Seeder
{
    /**
     * Seed homeroom schedules for all sections with homeroom teachers
     * Creates 7:30-8:00 AM homeroom period Monday to Friday
     */
    public function run(): void
    {
        // Clear existing homeroom schedules
        DB::statement('DELETE FROM schedules WHERE period_type = \'homeroom\';');

        $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $homeroomStart = '07:30:00';
        $homeroomEnd = '08:00:00';
        
        // Get all sections with homeroom teachers
        $sections = Section::whereNotNull('homeroom_teacher_id')
            ->with(['homeroomTeacher', 'curriculumGrade.grade'])
            ->get();

        $schedulesCreated = 0;

        foreach ($sections as $section) {
            foreach ($weekdays as $day) {
                Schedule::create([
                    'section_id' => $section->id,
                    'subject_id' => null, // Homeroom doesn't have a specific subject
                    'teacher_id' => $section->homeroom_teacher_id,
                    'day_of_week' => $day,
                    'start_time' => $homeroomStart,
                    'end_time' => $homeroomEnd,
                    'period_type' => 'homeroom',
                    'room_number' => $section->name . ' Classroom',
                    'notes' => 'Daily homeroom period - attendance, announcements, guidance',
                    'is_active' => true
                ]);
                
                $schedulesCreated++;
            }
        }

        $this->command->info('🏫 Naawan Central School homeroom schedules seeded successfully!');
        $this->command->info("📅 Schedule: Monday-Friday, 7:30-8:00 AM");
        $this->command->info("📊 Total homeroom schedules created: {$schedulesCreated}");
        $this->command->info("🏛️ Sections with homeroom teachers: " . $sections->count());
        
        if ($sections->count() === 0) {
            $this->command->warn('⚠️  No sections have homeroom teachers assigned yet!');
            $this->command->info('💡 Assign homeroom teachers first, then run this seeder again.');
        }
    }
}
