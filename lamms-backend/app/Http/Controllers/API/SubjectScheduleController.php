<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SubjectScheduleController extends Controller
{
    /**
     * Get predefined time slots
     */
    public function getTimeSlots()
    {
        $predefinedSlots = [
            ['label' => '7:30 AM - 8:30 AM', 'start_time' => '07:30:00', 'end_time' => '08:30:00'],
            ['label' => '8:30 AM - 9:30 AM', 'start_time' => '08:30:00', 'end_time' => '09:30:00'],
            ['label' => '9:30 AM - 10:30 AM', 'start_time' => '09:30:00', 'end_time' => '10:30:00'],
            ['label' => '10:30 AM - 11:30 AM', 'start_time' => '10:30:00', 'end_time' => '11:30:00'],
            ['label' => '11:30 AM - 12:30 PM', 'start_time' => '11:30:00', 'end_time' => '12:30:00'],
            ['label' => '1:00 PM - 2:00 PM', 'start_time' => '13:00:00', 'end_time' => '14:00:00'],
            ['label' => '2:00 PM - 3:00 PM', 'start_time' => '14:00:00', 'end_time' => '15:00:00'],
            ['label' => '3:00 PM - 4:00 PM', 'start_time' => '15:00:00', 'end_time' => '16:00:00'],
            ['label' => '4:00 PM - 5:00 PM', 'start_time' => '16:00:00', 'end_time' => '17:00:00'],
        ];

        return response()->json([
            'success' => true,
            'data' => $predefinedSlots
        ]);
    }

    /**
     * Get all schedules for admin view
     */
    public function getAllSchedules(Request $request)
    {
        try {
            $query = DB::table('subject_schedules as ss')
                ->join('teachers as t', 'ss.teacher_id', '=', 't.id')
                ->join('sections as sec', 'ss.section_id', '=', 'sec.id')
                ->join('subjects as sub', 'ss.subject_id', '=', 'sub.id')
                ->where('ss.is_active', true)
                ->select([
                    'ss.id',
                    'ss.teacher_id',
                    'ss.section_id',
                    'ss.subject_id',
                    'ss.day',
                    'ss.start_time',
                    'ss.end_time',
                    't.first_name as teacher_first_name',
                    't.last_name as teacher_last_name',
                    'sec.name as section_name',
                    'sub.name as subject_name'
                ]);

            // Filter by section if provided
            if ($request->has('section_id')) {
                $query->where('ss.section_id', $request->section_id);
            }

            // Filter by teacher if provided
            if ($request->has('teacher_id')) {
                $query->where('ss.teacher_id', $request->teacher_id);
            }

            $schedules = $query->orderBy('ss.day')
                             ->orderBy('ss.start_time')
                             ->get();

            return response()->json([
                'success' => true,
                'data' => $schedules
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching schedules: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get schedules for a specific teacher
     */
    public function getTeacherSchedules($teacherId)
    {
        try {
            $schedules = DB::table('subject_schedules as ss')
                ->join('sections as sec', 'ss.section_id', '=', 'sec.id')
                ->join('subjects as sub', 'ss.subject_id', '=', 'sub.id')
                ->where('ss.teacher_id', $teacherId)
                ->where('ss.is_active', true)
                ->select([
                    'ss.id',
                    'ss.section_id',
                    'ss.subject_id',
                    'ss.day',
                    'ss.start_time',
                    'ss.end_time',
                    'sec.name as section_name',
                    'sub.name as subject_name'
                ])
                ->orderBy('ss.day')
                ->orderBy('ss.start_time')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $schedules
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching teacher schedules: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching teacher schedules: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create or update a schedule
     */
    public function saveSchedule(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'teacher_id' => 'required|exists:teachers,id',
                'section_id' => 'required|exists:sections,id',
                'subject_id' => 'required|exists:subjects,id',
                'day' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday',
                'start_time' => 'required|date_format:H:i:s',
                'end_time' => 'required|date_format:H:i:s|after:start_time',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Check for time conflicts in the same section
            $conflictCheck = $this->checkTimeConflict(
                $data['section_id'],
                $data['day'],
                $data['start_time'],
                $data['end_time'],
                $request->input('id') // Exclude current record if updating
            );

            if ($conflictCheck['hasConflict']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Time conflict detected',
                    'conflict_details' => $conflictCheck['details']
                ], 409);
            }

            // Create or update schedule
            if ($request->has('id') && $request->id) {
                // Update existing schedule
                DB::table('subject_schedules')
                    ->where('id', $request->id)
                    ->update([
                        'teacher_id' => $data['teacher_id'],
                        'section_id' => $data['section_id'],
                        'subject_id' => $data['subject_id'],
                        'day' => $data['day'],
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'updated_at' => now()
                    ]);

                $scheduleId = $request->id;
                $message = 'Schedule updated successfully';
            } else {
                // Create new schedule
                $scheduleId = DB::table('subject_schedules')->insertGetId([
                    'teacher_id' => $data['teacher_id'],
                    'section_id' => $data['section_id'],
                    'subject_id' => $data['subject_id'],
                    'day' => $data['day'],
                    'start_time' => $data['start_time'],
                    'end_time' => $data['end_time'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                $message = 'Schedule created successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['id' => $scheduleId]
            ]);

        } catch (\Exception $e) {
            Log::error('Error saving schedule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error saving schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a schedule
     */
    public function deleteSchedule($id)
    {
        try {
            $deleted = DB::table('subject_schedules')
                ->where('id', $id)
                ->update([
                    'is_active' => false,
                    'deleted_at' => now(),
                    'updated_at' => now()
                ]);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Schedule deleted successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule not found'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error('Error deleting schedule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting schedule: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check for time conflicts
     */
    private function checkTimeConflict($sectionId, $day, $startTime, $endTime, $excludeId = null)
    {
        $query = DB::table('subject_schedules as ss')
            ->join('subjects as sub', 'ss.subject_id', '=', 'sub.id')
            ->join('teachers as t', 'ss.teacher_id', '=', 't.id')
            ->where('ss.section_id', $sectionId)
            ->where('ss.day', $day)
            ->where('ss.is_active', true)
            ->where(function ($q) use ($startTime, $endTime) {
                // Check for overlapping times
                $q->where(function ($subQ) use ($startTime, $endTime) {
                    // New schedule starts during existing schedule
                    $subQ->where('ss.start_time', '<=', $startTime)
                         ->where('ss.end_time', '>', $startTime);
                })->orWhere(function ($subQ) use ($startTime, $endTime) {
                    // New schedule ends during existing schedule
                    $subQ->where('ss.start_time', '<', $endTime)
                         ->where('ss.end_time', '>=', $endTime);
                })->orWhere(function ($subQ) use ($startTime, $endTime) {
                    // New schedule completely contains existing schedule
                    $subQ->where('ss.start_time', '>=', $startTime)
                         ->where('ss.end_time', '<=', $endTime);
                });
            });

        if ($excludeId) {
            $query->where('ss.id', '!=', $excludeId);
        }

        $conflicts = $query->select([
            'ss.id',
            'ss.start_time',
            'ss.end_time',
            'sub.name as subject_name',
            't.first_name as teacher_first_name',
            't.last_name as teacher_last_name'
        ])->get();

        return [
            'hasConflict' => $conflicts->count() > 0,
            'details' => $conflicts
        ];
    }

    /**
     * Get available time slots for a section on a specific day
     */
    public function getAvailableTimeSlots(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'section_id' => 'required|exists:sections,id',
                'day' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday',
                'exclude_id' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $sectionId = $request->section_id;
            $day = $request->day;
            $excludeId = $request->exclude_id;

            // Get all predefined slots
            $allSlots = [
                ['label' => '7:30 AM - 8:30 AM', 'start_time' => '07:30:00', 'end_time' => '08:30:00'],
                ['label' => '8:30 AM - 9:30 AM', 'start_time' => '08:30:00', 'end_time' => '09:30:00'],
                ['label' => '9:30 AM - 10:30 AM', 'start_time' => '09:30:00', 'end_time' => '10:30:00'],
                ['label' => '10:30 AM - 11:30 AM', 'start_time' => '10:30:00', 'end_time' => '11:30:00'],
                ['label' => '11:30 AM - 12:30 PM', 'start_time' => '11:30:00', 'end_time' => '12:30:00'],
                ['label' => '1:00 PM - 2:00 PM', 'start_time' => '13:00:00', 'end_time' => '14:00:00'],
                ['label' => '2:00 PM - 3:00 PM', 'start_time' => '14:00:00', 'end_time' => '15:00:00'],
                ['label' => '3:00 PM - 4:00 PM', 'start_time' => '15:00:00', 'end_time' => '16:00:00'],
                ['label' => '4:00 PM - 5:00 PM', 'start_time' => '16:00:00', 'end_time' => '17:00:00'],
            ];

            // Get occupied slots
            $occupiedQuery = DB::table('subject_schedules')
                ->where('section_id', $sectionId)
                ->where('day', $day)
                ->where('is_active', true);

            if ($excludeId) {
                $occupiedQuery->where('id', '!=', $excludeId);
            }

            $occupiedSlots = $occupiedQuery->select('start_time', 'end_time')->get();

            // Filter available slots
            $availableSlots = array_filter($allSlots, function ($slot) use ($occupiedSlots) {
                foreach ($occupiedSlots as $occupied) {
                    if ($slot['start_time'] === $occupied->start_time && $slot['end_time'] === $occupied->end_time) {
                        return false; // This slot is occupied
                    }
                }
                return true; // This slot is available
            });

            return response()->json([
                'success' => true,
                'data' => array_values($availableSlots) // Re-index array
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching available time slots: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching available time slots: ' . $e->getMessage()
            ], 500);
        }
    }
}
