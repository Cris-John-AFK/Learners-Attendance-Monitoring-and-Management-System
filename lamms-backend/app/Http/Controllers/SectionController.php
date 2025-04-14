<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SectionController extends Controller
{
    /**
     * Get sections for a specific grade in the active curriculum
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'grade_id' => 'required|exists:grades,id',
            'curriculum_id' => 'required|exists:curriculums,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sections = Section::with(['teacher', 'subjects'])
            ->where('grade_id', $request->grade_id)
            ->where('curriculum_id', $request->curriculum_id)
            ->get();

        return response()->json($sections);
    }

    /**
     * Create a new section
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'curriculum_id' => 'required|exists:curriculums,id',
            'grade_id' => 'required|exists:grades,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'room_number' => 'required|string|max:50',
            'max_students' => 'required|integer|min:1',
            'subject_ids' => 'required|array',
            'subject_ids.*' => 'exists:subjects,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if the curriculum is active
        $curriculum = Curriculum::findOrFail($request->curriculum_id);
        if (!$curriculum->is_active) {
            return response()->json(['error' => 'Cannot create sections in an inactive curriculum'], 422);
        }

        // Start transaction
        DB::beginTransaction();

        try {
            // Create the section
            $section = Section::create([
                'name' => $request->name,
                'curriculum_id' => $request->curriculum_id,
                'grade_id' => $request->grade_id,
                'teacher_id' => $request->teacher_id,
                'room_number' => $request->room_number,
                'max_students' => $request->max_students,
                'is_active' => true
            ]);

            // Get the grade to check if it needs a primary teacher
            $grade = Grade::findOrFail($request->grade_id);
            $gradeCode = strtoupper($grade->code);
            $needsPrimaryTeacher = in_array($gradeCode, ['K1', 'K2', 'G1', 'G2', 'G3']);

            // If this is a primary teacher grade (K1-G3), validate teacher assignment
            if ($needsPrimaryTeacher && !$request->teacher_id) {
                throw new \Exception('Primary teacher is required for this grade level');
            }

            // Get default schedule times
            $defaultTimes = Section::getDefaultScheduleTimes();
            $timeIndex = 0;

            // Attach subjects with default schedules
            foreach ($request->subject_ids as $subjectId) {
                if ($timeIndex >= count($defaultTimes)) {
                    $timeIndex = 0; // Reset to start of next day if we run out of times
                }

                $time = $defaultTimes[$timeIndex];

                // For primary teacher grades, use the same teacher for all subjects
                $teacherId = $needsPrimaryTeacher ? $request->teacher_id : null;

                $section->subjects()->attach($subjectId, [
                    'teacher_id' => $teacherId,
                    'schedule_start' => $time['start'],
                    'schedule_end' => $time['end'],
                    'day_of_week' => 'Monday', // Default to Monday, can be changed later
                    'room_number' => $request->room_number
                ]);

                $timeIndex++;
            }

            DB::commit();

            // Load relationships for response
            $section->load(['teacher', 'subjects']);
            return response()->json($section, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update section schedule
     */
    public function updateSchedule(Request $request, Section $section, Subject $subject)
    {
        $validator = Validator::make($request->all(), [
            'schedule_start' => 'required|date_format:H:i:s',
            'schedule_end' => 'required|date_format:H:i:s|after:schedule_start',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday',
            'room_number' => 'required|string|max:50',
            'teacher_id' => 'nullable|exists:teachers,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check for schedule conflicts
        if ($section->hasScheduleConflict(
            $request->day_of_week,
            $request->schedule_start,
            $request->schedule_end,
            $subject->id
        )) {
            return response()->json(['error' => 'Schedule conflict detected'], 422);
        }

        // If teacher is specified, check for their schedule conflicts
        if ($request->teacher_id) {
            $teacher = Teacher::findOrFail($request->teacher_id);
            if ($teacher->hasScheduleConflict(
                $request->day_of_week,
                $request->schedule_start,
                $request->schedule_end,
                $section->id,
                $subject->id
            )) {
                return response()->json(['error' => 'Teacher schedule conflict detected'], 422);
            }
        }

        // Update the schedule
        $section->subjects()->updateExistingPivot($subject->id, [
            'schedule_start' => $request->schedule_start,
            'schedule_end' => $request->schedule_end,
            'day_of_week' => $request->day_of_week,
            'room_number' => $request->room_number,
            'teacher_id' => $request->teacher_id
        ]);

        return response()->json(['message' => 'Schedule updated successfully']);
    }

    /**
     * Remove a subject from a section
     */
    public function removeSubject(Section $section, Subject $subject)
    {
        $section->subjects()->detach($subject->id);
        return response()->json(['message' => 'Subject removed successfully']);
    }

    /**
     * Add a subject to a section
     */
    public function addSubject(Request $request, Section $section)
    {
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|exists:subjects,id',
            'schedule_start' => 'required|date_format:H:i:s',
            'schedule_end' => 'required|date_format:H:i:s|after:schedule_start',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday',
            'room_number' => 'required|string|max:50',
            'teacher_id' => 'nullable|exists:teachers,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if subject is already assigned
        if ($section->subjects()->where('subjects.id', $request->subject_id)->exists()) {
            return response()->json(['error' => 'Subject is already assigned to this section'], 422);
        }

        // Check for schedule conflicts
        if ($section->hasScheduleConflict(
            $request->day_of_week,
            $request->schedule_start,
            $request->schedule_end
        )) {
            return response()->json(['error' => 'Schedule conflict detected'], 422);
        }

        // If teacher is specified, check for their schedule conflicts
        if ($request->teacher_id) {
            $teacher = Teacher::findOrFail($request->teacher_id);
            if ($teacher->hasScheduleConflict(
                $request->day_of_week,
                $request->schedule_start,
                $request->schedule_end,
                $section->id
            )) {
                return response()->json(['error' => 'Teacher schedule conflict detected'], 422);
            }
        }

        // Attach the subject
        $section->subjects()->attach($request->subject_id, [
            'schedule_start' => $request->schedule_start,
            'schedule_end' => $request->schedule_end,
            'day_of_week' => $request->day_of_week,
            'room_number' => $request->room_number,
            'teacher_id' => $request->teacher_id
        ]);

        return response()->json(['message' => 'Subject added successfully']);
    }
}
