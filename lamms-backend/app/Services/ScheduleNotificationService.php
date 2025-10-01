<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScheduleNotificationService
{
    /**
     * Get upcoming schedules for a teacher that need notifications
     */
    public function getUpcomingSchedules($teacherId, $date = null)
    {
        $date = $date ?: Carbon::now()->format('Y-m-d');
        $currentTime = Carbon::now();
        $dayOfWeek = Carbon::parse($date)->format('l'); // Monday, Tuesday, etc.

        try {
            $schedules = DB::table('subject_schedules as ss')
                ->join('sections as sec', 'ss.section_id', '=', 'sec.id')
                ->join('subjects as sub', 'ss.subject_id', '=', 'sub.id')
                ->where('ss.teacher_id', $teacherId)
                ->where('ss.day', $dayOfWeek)
                ->where('ss.is_active', true)
                ->select([
                    'ss.id',
                    'ss.teacher_id',
                    'ss.start_time',
                    'ss.end_time',
                    'ss.day',
                    'sec.name as section_name',
                    'sub.name as subject_name',
                    'ss.section_id',
                    'ss.subject_id'
                ])
                ->orderBy('ss.start_time')
                ->get();

            // Add timing information for each schedule
            return $schedules->map(function ($schedule) use ($date, $currentTime) {
                $scheduleStart = Carbon::parse($date . ' ' . $schedule->start_time);
                $scheduleEnd = Carbon::parse($date . ' ' . $schedule->end_time);
                
                // Calculate time differences
                $minutesToStart = $currentTime->diffInMinutes($scheduleStart, false);
                $minutesToEnd = $currentTime->diffInMinutes($scheduleEnd, false);
                
                // Determine schedule status
                $status = $this->getScheduleStatus($currentTime, $scheduleStart, $scheduleEnd);
                
                return (object) array_merge((array) $schedule, [
                    'schedule_datetime_start' => $scheduleStart,
                    'schedule_datetime_end' => $scheduleEnd,
                    'minutes_to_start' => $minutesToStart,
                    'minutes_to_end' => $minutesToEnd,
                    'status' => $status,
                    'notification_type' => $this->getNotificationType($minutesToStart, $minutesToEnd, $status)
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error getting upcoming schedules: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Get schedule status based on current time
     */
    private function getScheduleStatus($currentTime, $scheduleStart, $scheduleEnd)
    {
        if ($currentTime->lt($scheduleStart)) {
            return 'upcoming';
        } elseif ($currentTime->between($scheduleStart, $scheduleEnd)) {
            return 'ongoing';
        } else {
            return 'completed';
        }
    }

    /**
     * Get notification type based on timing
     */
    private function getNotificationType($minutesToStart, $minutesToEnd, $status)
    {
        if ($status === 'upcoming' && $minutesToStart <= 10 && $minutesToStart > 0) {
            return 'starting_soon';
        } elseif ($status === 'ongoing' && $minutesToEnd <= 10 && $minutesToEnd > 0) {
            return 'ending_soon';
        } elseif ($status === 'ongoing') {
            return 'in_progress';
        } elseif ($status === 'completed') {
            // Check if schedule just ended (within last 5 minutes)
            if ($minutesToEnd >= -5 && $minutesToEnd < 0) {
                return 'ended';
            }
        }
        
        return null;
    }

    /**
     * Get active session for a schedule
     */
    public function getActiveSession($scheduleId, $date = null)
    {
        $date = $date ?: Carbon::now()->format('Y-m-d');

        try {
            $session = DB::table('attendance_sessions')
                ->where('schedule_id', $scheduleId)
                ->where('session_date', $date)
                ->where('status', 'active')
                ->first();

            return $session;
        } catch (\Exception $e) {
            Log::error('Error getting active session: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if a schedule needs auto-absence marking
     */
    public function getSchedulesNeedingAutoAbsence($date = null)
    {
        $date = $date ?: Carbon::now()->format('Y-m-d');
        $currentTime = Carbon::now();
        $dayOfWeek = $currentTime->format('l');

        try {
            // Get schedules that have ended but haven't been auto-marked
            $schedules = DB::table('subject_schedules as ss')
                ->leftJoin('attendance_sessions as ats', function($join) use ($date) {
                    $join->on('ss.id', '=', 'ats.schedule_id')
                         ->where('ats.session_date', '=', $date);
                })
                ->where('ss.day', $dayOfWeek)
                ->where('ss.is_active', true)
                ->whereRaw("CONCAT(?, ' ', ss.end_time) < ?", [$date, $currentTime->format('Y-m-d H:i:s')])
                ->where(function($query) {
                    $query->whereNull('ats.auto_absence_marked')
                          ->orWhere('ats.auto_absence_marked', false);
                })
                ->whereNotNull('ats.id') // Only sessions that exist
                ->select([
                    'ss.id as schedule_id',
                    'ss.teacher_id',
                    'ss.section_id',
                    'ss.subject_id',
                    'ss.end_time',
                    'ats.id as session_id',
                    'ats.session_status'
                ])
                ->get();

            return $schedules;
        } catch (\Exception $e) {
            Log::error('Error getting schedules needing auto-absence: ' . $e->getMessage());
            return collect();
        }
    }

    /**
     * Mark auto-absence for a session
     */
    public function markAutoAbsence($sessionId)
    {
        try {
            DB::beginTransaction();

            // Get session details
            $session = DB::table('attendance_sessions')->where('id', $sessionId)->first();
            if (!$session) {
                throw new \Exception("Session not found: {$sessionId}");
            }

            // Get all students in the section who don't have attendance records for this session
            $studentsWithoutAttendance = DB::table('student_section as ss')
                ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
                ->leftJoin('attendance_records as ar', function($join) use ($sessionId) {
                    $join->on('ss.student_id', '=', 'ar.student_id')
                         ->where('ar.session_id', '=', $sessionId);
                })
                ->where('ss.section_id', $session->section_id)
                ->where('ss.is_active', true)
                ->where('sd.is_active', true)
                ->whereNull('ar.id') // Students without attendance records
                ->select('ss.student_id')
                ->get();

            // Get "Absent" status ID
            $absentStatus = DB::table('attendance_statuses')
                ->where('code', 'A')
                ->where('is_active', true)
                ->first();

            if (!$absentStatus) {
                throw new \Exception("Absent status not found");
            }

            // Mark all students without attendance as absent
            // Get the teacher_id from the session
            $teacherId = $session->teacher_id;
            
            $attendanceRecords = [];
            foreach ($studentsWithoutAttendance as $student) {
                $attendanceRecords[] = [
                    'attendance_session_id' => $sessionId,
                    'student_id' => $student->student_id,
                    'attendance_status_id' => $absentStatus->id,
                    'marked_by_teacher_id' => $teacherId,
                    'marked_at' => Carbon::now(),
                    'remarks' => 'Auto-marked absent after scheduled class time',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ];
            }

            if (!empty($attendanceRecords)) {
                DB::table('attendance_records')->insert($attendanceRecords);
            }

            // Update session to mark auto-absence as completed
            DB::table('attendance_sessions')
                ->where('id', $sessionId)
                ->update([
                    'auto_absence_marked' => true,
                    'auto_absence_marked_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);

            DB::commit();

            Log::info("Auto-marked {count} students as absent for session {sessionId}", [
                'count' => count($attendanceRecords),
                'sessionId' => $sessionId
            ]);

            return [
                'success' => true,
                'students_marked' => count($attendanceRecords),
                'session_id' => $sessionId
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error marking auto-absence: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ];
        }
    }

    /**
     * Process all schedules that need auto-absence marking
     */
    public function processAutoAbsenceMarking($date = null)
    {
        $schedules = $this->getSchedulesNeedingAutoAbsence($date);
        $results = [];

        foreach ($schedules as $schedule) {
            $result = $this->markAutoAbsence($schedule->session_id);
            $results[] = array_merge($result, [
                'schedule_id' => $schedule->schedule_id,
                'teacher_id' => $schedule->teacher_id
            ]);
        }

        return $results;
    }

    /**
     * Validate if a teacher can start a session at the current time
     */
    public function validateSessionTiming($teacherId, $sectionId, $subjectId, $startTime = null)
    {
        $startTime = $startTime ?: Carbon::now();
        $date = $startTime->format('Y-m-d');
        $dayOfWeek = $startTime->format('l');

        try {
            // Find matching schedule
            $schedule = DB::table('subject_schedules')
                ->where('teacher_id', $teacherId)
                ->where('section_id', $sectionId)
                ->where('subject_id', $subjectId)
                ->where('day', $dayOfWeek)
                ->where('is_active', true)
                ->first();

            if (!$schedule) {
                return [
                    'is_valid' => false,
                    'warning_type' => 'no_schedule',
                    'message' => 'No schedule found for this subject on ' . $dayOfWeek,
                    'can_proceed' => true
                ];
            }

            $scheduledStart = Carbon::parse($date . ' ' . $schedule->start_time);
            $scheduledEnd = Carbon::parse($date . ' ' . $schedule->end_time);
            $minutesDiff = $startTime->diffInMinutes($scheduledStart, false);

            // Within scheduled time (perfect)
            if ($startTime->between($scheduledStart, $scheduledEnd)) {
                return [
                    'is_valid' => true,
                    'warning_type' => null,
                    'message' => 'Session started within scheduled time',
                    'can_proceed' => true,
                    'schedule_id' => $schedule->id
                ];
            }

            // Early start (warning)
            if ($minutesDiff > 0) {
                return [
                    'is_valid' => false,
                    'warning_type' => 'early_start',
                    'message' => "You are starting {$minutesDiff} minutes early. Scheduled time is {$schedule->start_time}.",
                    'can_proceed' => true,
                    'minutes_early' => $minutesDiff,
                    'scheduled_time' => $schedule->start_time,
                    'schedule_id' => $schedule->id
                ];
            }

            // Late start (warning)
            if ($minutesDiff < 0) {
                $minutesLate = abs($minutesDiff);
                return [
                    'is_valid' => false,
                    'warning_type' => 'late_start',
                    'message' => "You are starting {$minutesLate} minutes late. Scheduled time was {$schedule->start_time}.",
                    'can_proceed' => true,
                    'minutes_late' => $minutesLate,
                    'scheduled_time' => $schedule->start_time,
                    'schedule_id' => $schedule->id
                ];
            }

        } catch (\Exception $e) {
            Log::error('Error validating session timing: ' . $e->getMessage());
            
            return [
                'is_valid' => false,
                'warning_type' => 'error',
                'message' => 'Unable to validate session timing',
                'can_proceed' => true
            ];
        }
    }

    /**
     * Auto-create session and mark all students absent
     */
    public function autoCreateSessionAndMarkAbsent($scheduleId, $teacherId, $sectionId, $subjectId, $scheduleDate, $startTime, $endTime)
    {
        try {
            DB::beginTransaction();

            // Check if session already exists for this specific schedule (matching times)
            $existingSession = DB::table('attendance_sessions')
                ->where('teacher_id', $teacherId)
                ->where('section_id', $sectionId)
                ->where('subject_id', $subjectId)
                ->where('session_date', $scheduleDate)
                ->where('session_start_time', $startTime)
                ->where('session_end_time', $endTime)
                ->whereIn('status', ['active', 'completed'])
                ->first();

            if ($existingSession) {
                // Session exists for this specific schedule - we'll mark only unmarked students as absent
                Log::info("Session already exists (ID: {$existingSession->id}, status: {$existingSession->status}) for schedule {$scheduleId} at {$startTime}-{$endTime}, will mark unmarked students as absent");
                $sessionId = $existingSession->id;
                
                // Update session status to completed if still active
                if ($existingSession->status === 'active') {
                    DB::table('attendance_sessions')
                        ->where('id', $sessionId)
                        ->update([
                            'status' => 'completed',
                            'updated_at' => Carbon::now()
                        ]);
                    Log::info("Updated session {$sessionId} status to completed");
                }
            } else {
                // No session exists - create one
                $currentTime = Carbon::now()->format('H:i:s');
                $sessionId = DB::table('attendance_sessions')->insertGetId([
                    'teacher_id' => $teacherId,
                    'section_id' => $sectionId,
                    'subject_id' => $subjectId,
                    'session_date' => $scheduleDate,
                    'session_start_time' => $startTime,
                    'session_end_time' => $endTime,
                    'session_type' => 'regular',
                    'status' => 'completed', // Mark as completed since schedule already ended
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]);

                Log::info("Auto-created session {$sessionId} for schedule {$scheduleId}");
            }

            // Get all students in the section
            $students = DB::table('student_section as ss')
                ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
                ->where('ss.section_id', $sectionId)
                ->where('ss.is_active', true)
                ->where('sd.is_active', true)
                ->select('ss.student_id')
                ->get();

            Log::info("Total students in section {$sectionId}: " . count($students));

            // Get students who already have attendance records for this session
            $existingAttendance = DB::table('attendance_records')
                ->where('attendance_session_id', $sessionId)
                ->pluck('student_id')
                ->toArray();

            Log::info("Students with existing attendance for session {$sessionId}: " . count($existingAttendance), [
                'student_ids' => $existingAttendance
            ]);

            // Filter out students who already have attendance
            $studentsNeedingAttendance = $students->filter(function($student) use ($existingAttendance) {
                return !in_array($student->student_id, $existingAttendance);
            });

            Log::info("Students needing attendance: " . count($studentsNeedingAttendance));

            if ($studentsNeedingAttendance->isEmpty()) {
                Log::info("All students already have attendance for session {$sessionId}, no auto-marking needed");
                DB::commit();
                return [
                    'success' => true,
                    'session_id' => $sessionId,
                    'marked_absent_count' => 0,
                    'all_marked' => true,
                    'message' => 'All students already marked by teacher'
                ];
            }

            // Get "Absent" status ID
            $absentStatus = DB::table('attendance_statuses')
                ->where('code', 'A')
                ->where('is_active', true)
                ->first();

            if (!$absentStatus) {
                throw new \Exception("Absent status not found");
            }

            // Mark only students without attendance as absent
            $attendanceRecords = [];
            foreach ($studentsNeedingAttendance as $student) {
                $attendanceRecords[] = [
                    'attendance_session_id' => $sessionId,
                    'student_id' => $student->student_id,
                    'attendance_status_id' => $absentStatus->id,
                    'marked_by_teacher_id' => $teacherId,
                    'marked_at' => Carbon::now(),
                    'remarks' => 'Auto-marked absent - no attendance session was created for scheduled class',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ];
            }

            if (!empty($attendanceRecords)) {
                DB::table('attendance_records')->insert($attendanceRecords);
            }

            DB::commit();

            Log::info("Auto-marked {count} students as absent for auto-created session {sessionId}", [
                'count' => count($attendanceRecords),
                'sessionId' => $sessionId
            ]);

            return [
                'success' => true,
                'session_id' => $sessionId,
                'marked_absent_count' => count($attendanceRecords)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error auto-creating session and marking absent: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
