<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{
    /**
     * Get schedules for a specific section
     */
    public function getSectionSchedules($sectionId)
    {
        try {
            $section = Section::findOrFail($sectionId);
            
            $schedules = Schedule::where('section_id', $sectionId)
                ->with(['subject', 'teacher'])
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();

            return response()->json([
                'success' => true,
                'section' => $section->load(['curriculumGrade.grade', 'homeroomTeacher']),
                'schedules' => $schedules
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching section schedules: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch schedules'
            ], 500);
        }
    }

    /**
     * Create homeroom schedule for all sections
     */
    public function createHomeroomSchedules(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'days' => 'required|array',
            'days.*' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $created = 0;
            $skipped = 0;

            // Get all sections with homeroom teachers
            $sections = Section::whereNotNull('homeroom_teacher_id')
                ->with(['homeroomTeacher', 'curriculumGrade.grade'])
                ->get();

            foreach ($sections as $section) {
                foreach ($request->days as $day) {
                    // Check if homeroom schedule already exists
                    $existingSchedule = Schedule::where('section_id', $section->id)
                        ->where('day_of_week', $day)
                        ->where('period_type', 'homeroom')
                        ->first();

                    if (!$existingSchedule) {
                        Schedule::create([
                            'section_id' => $section->id,
                            'subject_id' => null, // Homeroom doesn't have a specific subject
                            'teacher_id' => $section->homeroom_teacher_id,
                            'day_of_week' => $day,
                            'start_time' => $request->start_time,
                            'end_time' => $request->end_time,
                            'period_type' => 'homeroom',
                            'room_number' => $section->name . ' Classroom',
                            'notes' => 'Daily homeroom period',
                            'is_active' => true
                        ]);
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Homeroom schedules created successfully",
                'created' => $created,
                'skipped' => $skipped,
                'total_sections' => $sections->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating homeroom schedules: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create homeroom schedules'
            ], 500);
        }
    }

    /**
     * Create subject schedule
     */
    public function createSubjectSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check for overlapping schedules
            $overlapping = Schedule::where('section_id', $request->section_id)
                ->where('day_of_week', $request->day_of_week)
                ->where(function ($query) use ($request) {
                    $query->where(function ($q) use ($request) {
                        $q->where('start_time', '<=', $request->start_time)
                          ->where('end_time', '>', $request->start_time);
                    })->orWhere(function ($q) use ($request) {
                        $q->where('start_time', '<', $request->end_time)
                          ->where('end_time', '>=', $request->end_time);
                    })->orWhere(function ($q) use ($request) {
                        $q->where('start_time', '>=', $request->start_time)
                          ->where('end_time', '<=', $request->end_time);
                    });
                })
                ->exists();

            if ($overlapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule conflicts with existing schedule'
                ], 422);
            }

            $schedule = Schedule::create([
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'teacher_id' => $request->teacher_id,
                'day_of_week' => $request->day_of_week,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'period_type' => 'subject',
                'room_number' => $request->room_number,
                'notes' => $request->notes,
                'is_active' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subject schedule created successfully',
                'schedule' => $schedule->load(['subject', 'teacher', 'section'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating subject schedule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subject schedule'
            ], 500);
        }
    }

    /**
     * Update schedule
     */
    public function updateSchedule(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'day_of_week' => 'sometimes|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i',
            'room_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $schedule = Schedule::findOrFail($id);
            
            // Check for overlaps if time or day is being changed
            if ($request->has(['start_time', 'end_time', 'day_of_week'])) {
                $startTime = $request->start_time ?? $schedule->start_time;
                $endTime = $request->end_time ?? $schedule->end_time;
                $dayOfWeek = $request->day_of_week ?? $schedule->day_of_week;

                if ($schedule->overlaps($startTime, $endTime, $dayOfWeek)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Schedule conflicts with existing schedule'
                    ], 422);
                }
            }

            $schedule->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Schedule updated successfully',
                'schedule' => $schedule->load(['subject', 'teacher', 'section'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating schedule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update schedule'
            ], 500);
        }
    }

    /**
     * Delete schedule
     */
    public function deleteSchedule($id)
    {
        try {
            $schedule = Schedule::findOrFail($id);
            $schedule->delete();

            return response()->json([
                'success' => true,
                'message' => 'Schedule deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting schedule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete schedule'
            ], 500);
        }
    }

    /**
     * Get teacher's schedule
     */
    public function getTeacherSchedule($teacherId)
    {
        try {
            $teacher = Teacher::findOrFail($teacherId);
            
            $schedules = Schedule::where('teacher_id', $teacherId)
                ->with(['subject', 'section.curriculumGrade.grade'])
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get();

            return response()->json([
                'success' => true,
                'teacher' => $teacher,
                'schedules' => $schedules
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching teacher schedule: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch teacher schedule'
            ], 500);
        }
    }
}
