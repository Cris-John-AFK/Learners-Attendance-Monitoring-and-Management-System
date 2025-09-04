<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AttendanceStatus;
use App\Models\Student;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;

class TestAttendanceSystem extends Command
{
    protected $signature = 'attendance:test';
    protected $description = 'Test the attendance system setup';

    public function handle()
    {
        $this->info('Testing Attendance System');
        $this->info('========================');

        try {
            // Test 1: Check if attendance statuses were created
            $this->info('1. Testing Attendance Statuses:');
            $statuses = AttendanceStatus::all();
            $this->info("   Found {$statuses->count()} attendance statuses:");
            foreach ($statuses as $status) {
                $this->line("   - {$status->code}: {$status->name}");
            }

            // Test 2: Check students
            $this->info('2. Testing Students:');
            $students = Student::take(5)->get();
            $this->info("   Found {$students->count()} students (showing first 5):");
            foreach ($students as $student) {
                $name = $student->name ?? $student->firstName . ' ' . $student->lastName;
                $this->line("   - ID: {$student->id}, Name: {$name}");
            }

            // Test 3: Check sections
            $this->info('3. Testing Sections:');
            $sections = Section::take(3)->get();
            $this->info("   Found {$sections->count()} sections (showing first 3):");
            foreach ($sections as $section) {
                $this->line("   - ID: {$section->id}, Name: {$section->name}");
            }

            // Test 4: Check subjects
            $this->info('4. Testing Subjects:');
            $subjects = Subject::take(3)->get();
            $this->info("   Found {$subjects->count()} subjects (showing first 3):");
            foreach ($subjects as $subject) {
                $this->line("   - ID: {$subject->id}, Name: {$subject->name}");
            }

            // Test 5: Check teachers
            $this->info('5. Testing Teachers:');
            $teachers = Teacher::take(3)->get();
            $this->info("   Found {$teachers->count()} teachers (showing first 3):");
            foreach ($teachers as $teacher) {
                $name = $teacher->first_name . ' ' . $teacher->last_name;
                $this->line("   - ID: {$teacher->id}, Name: {$name}");
            }

            $this->info('✅ Attendance system setup appears to be working!');
            $this->info('Next steps:');
            $this->line('- Use the API endpoints to mark attendance');
            $this->line('- Create frontend interface to interact with attendance');
            $this->line('- Test with actual student data');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}