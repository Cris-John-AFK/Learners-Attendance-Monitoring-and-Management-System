<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get students from section 1 (Matatag)
        $students = DB::table('student_details')->whereIn('id', [1, 2, 3, 4])->get();
        
        if ($students->isEmpty()) {
            $this->command->info('No students found. Please run StudentSeeder first.');
            return;
        }

        // Get attendance statuses
        $presentStatus = DB::table('attendance_statuses')->where('name', 'Present')->first();
        $absentStatus = DB::table('attendance_statuses')->where('name', 'Absent')->first();
        $lateStatus = DB::table('attendance_statuses')->where('name', 'Late')->first();
        
        if (!$presentStatus || !$absentStatus || !$lateStatus) {
            $this->command->info('Attendance statuses not found. Creating them...');
            
            // Create attendance statuses if they don't exist
            $presentStatus = (object)['id' => DB::table('attendance_statuses')->insertGetId([
                'name' => 'Present',
                'description' => 'Student is present',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ])];
            
            $lateStatus = (object)['id' => DB::table('attendance_statuses')->insertGetId([
                'name' => 'Late',
                'description' => 'Student is late',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now()
            ])];
            
            $absentStatus = (object)['id' => DB::table('attendance_statuses')->insertGetId([
                'name' => 'Absent',
                'description' => 'Student is absent',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now()
            ])];
        }

        // Generate attendance data for September 2025 (current month)
        $startDate = Carbon::create(2025, 9, 1);
        $endDate = Carbon::create(2025, 9, 30);
        
        // Clear existing attendance sessions and records for September 2025
        $sessionIds = DB::table('attendance_sessions')
            ->whereBetween('session_date', [$startDate, $endDate])
            ->pluck('id');
            
        if ($sessionIds->isNotEmpty()) {
            DB::table('attendance_records')
                ->whereIn('session_id', $sessionIds)
                ->delete();
        }
            
        DB::table('attendance_sessions')
            ->whereBetween('session_date', [$startDate, $endDate])
            ->delete();
        
        $attendanceRecords = [];
        $sessionCount = 0;
        
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            // Only create sessions for weekdays (Monday-Friday)
            if ($currentDate->isWeekday()) {
                // Create attendance session for this day
                $sessionId = DB::table('attendance_sessions')->insertGetId([
                    'teacher_id' => 1, // Assuming teacher ID 1 exists
                    'section_id' => 1, // Section 1 (Matatag)
                    'subject_id' => null, // Homeroom attendance
                    'session_date' => $currentDate->format('Y-m-d'),
                    'session_start_time' => '07:30:00',
                    'session_end_time' => '08:00:00',
                    'session_type' => 'regular',
                    'status' => 'completed',
                    'completed_at' => $currentDate->format('Y-m-d H:i:s'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $sessionCount++;
                
                // Create attendance records for each student
                foreach ($students as $student) {
                    $statusData = $this->generateAttendanceStatus($student->id, $currentDate);
                    
                    $attendanceRecords[] = [
                        'session_id' => $sessionId,
                        'student_id' => $student->id,
                        'attendance_status_id' => $statusData['status_id'],
                        'marked_at' => $currentDate->format('Y-m-d') . ' 07:30:00',
                        'remarks' => $statusData['remarks'],
                        'marked_by' => 1, // Teacher ID 1
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            
            $currentDate->addDay();
        }

        // Insert attendance records
        if (!empty($attendanceRecords)) {
            DB::table('attendance_records')->insert($attendanceRecords);
        }
        
        $this->command->info('Created ' . $sessionCount . ' attendance sessions for September 2025');
        $this->command->info('Created ' . count($attendanceRecords) . ' attendance records for September 2025');
        
        // Show summary
        $presentCount = collect($attendanceRecords)->where('attendance_status_id', $presentStatus->id)->count();
        $absentCount = collect($attendanceRecords)->where('attendance_status_id', $absentStatus->id)->count();
        $lateCount = collect($attendanceRecords)->where('attendance_status_id', $lateStatus->id)->count();
        
        $this->command->info("Summary: Present: {$presentCount}, Absent: {$absentCount}, Late: {$lateCount}");
    }

    /**
     * Generate realistic attendance status for a student on a given date
     */
    private function generateAttendanceStatus($studentId, $date)
    {
        // Get attendance statuses
        $presentStatus = DB::table('attendance_statuses')->where('name', 'Present')->first();
        $absentStatus = DB::table('attendance_statuses')->where('name', 'Absent')->first();
        $lateStatus = DB::table('attendance_statuses')->where('name', 'Late')->first();
        
        // Create different attendance patterns for different students
        $patterns = [
            1 => ['present' => 85, 'late' => 10, 'absent' => 5],     // Good student
            2 => ['present' => 90, 'late' => 5, 'absent' => 5],      // Excellent student  
            3 => ['present' => 75, 'late' => 15, 'absent' => 10],    // Average student
            4 => ['present' => 80, 'late' => 12, 'absent' => 8],     // Good student
        ];
        
        $pattern = $patterns[$studentId] ?? ['present' => 80, 'late' => 10, 'absent' => 10];
        
        // Generate random number 1-100
        $random = rand(1, 100);
        
        if ($random <= $pattern['present']) {
            return [
                'status_id' => $presentStatus->id,
                'remarks' => null
            ];
        } elseif ($random <= $pattern['present'] + $pattern['late']) {
            return [
                'status_id' => $lateStatus->id,
                'remarks' => 'Arrived late'
            ];
        } else {
            return [
                'status_id' => $absentStatus->id,
                'remarks' => 'No show'
            ];
        }
    }
}
