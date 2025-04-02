<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumGrade;
use App\Models\Grade;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\SubjectSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class SectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sections = Section::with('grade')->get();
        return response()->json($sections);
    }

    /**
     * Get sections by grade
     */
    public function byGrade($gradeId)
    {
        $sections = Section::with('grade')
            ->where('grade_id', $gradeId)
            ->get();
        return response()->json($sections);
    }

    /**
     * Get active sections
     */
    public function getActiveSections()
    {
        try {
            Log::info('Fetching active sections');

            // Check if the table exists
            if (!Schema::hasTable('sections')) {
                Log::error('Sections table does not exist');
                return response()->json(['message' => 'Sections table does not exist'], 500);
            }

            // Check if the is_active column exists
            if (!Schema::hasColumn('sections', 'is_active')) {
                Log::error('is_active column does not exist in sections table');
                return response()->json(['message' => 'is_active column does not exist'], 500);
            }

            // Query with logging
            Log::info('Querying active sections with grade relationship');
            $sections = Section::with('grade')
                ->where('is_active', true)
                ->get();

            Log::info('Found ' . $sections->count() . ' active sections');
            return response()->json($sections);
        } catch (\Exception $e) {
            Log::error('Error fetching active sections: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'message' => 'Failed to load active sections: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle section status
     */
    public function toggleStatus(Section $section)
    {
        $section->is_active = !$section->is_active;
        $section->save();
        return response()->json($section->load('grade'));
    }

    /**
     * Get sections by curriculum grade
     */
    public function byCurriculumGrade($curriculumGradeId)
    {
        try {
            $sections = Section::where('curriculum_grade_id', $curriculumGradeId)
                ->with(['curriculumGrade.grade', 'homeroomTeacher'])
                ->get();

            return response()->json($sections);
        } catch (\Exception $e) {
            Log::error("Error getting sections by curriculum grade: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get sections',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'curriculum_grade_id' => 'required|exists:curriculum_grade,id',
            'homeroom_teacher_id' => 'nullable|exists:teachers,id',
            'description' => 'nullable|string',
            'capacity' => 'integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if section name is unique within the curriculum grade
        if (!Section::isNameUniqueInCurriculumGrade($request->name, $request->curriculum_grade_id)) {
            return response()->json([
                'errors' => [
                    'name' => ['Section name already exists in this grade.']
                ]
            ], 422);
        }

        try {
            $section = Section::create($request->all());
            return response()->json($section->load(['curriculumGrade.grade', 'homeroomTeacher']), 201);
        } catch (\Exception $e) {
            Log::error('Error creating section: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create section: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Section $section)
    {
        return response()->json($section->load('grade'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Section $section)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'grade_id' => 'required_without:grade|exists:grades,id',
            'grade' => 'required_without:grade_id',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // If grade is provided as an object, extract the ID
        $gradeId = $request->grade_id;
        if (!$gradeId && isset($request->grade['id'])) {
            $gradeId = $request->grade['id'];
        }

        // Check if section name is unique within the grade (excluding current section)
        if (!Section::isNameUniqueInGrade($request->name, $gradeId, $section->id)) {
            return response()->json([
                'errors' => [
                    'name' => ['Section name already exists in this grade.']
                ]
            ], 422);
        }

        $section->name = $request->name;
        $section->grade_id = $gradeId;
        $section->description = $request->description;
        $section->save();

        return response()->json($section->load('grade'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Section $section)
    {
        $section->delete();
        return response()->json(null, 204);
    }

    /**
     * Restore a soft-deleted section
     */
    public function restore($id)
    {
        $section = Section::withTrashed()->findOrFail($id);
        $section->restore();
        return response()->json($section->load('grade'));
    }

    /**
     * Get all subjects for a specific section
     */
    public function getSectionSubjects(Request $request, $curriculumId = null, $gradeId = null, $sectionId = null)
    {
        // If we're using the simplified route
        if ($sectionId === null && $request->route('sectionId')) {
            $sectionId = $request->route('sectionId');
        }

        try {
            Log::info("Getting subjects for section: {$sectionId}");

            $section = Section::findOrFail($sectionId);

            // Get subjects through the direct section_subject pivot table
            $subjects = $section->directSubjects()->get();

            // If no subjects found in direct relationship, try the three-way pivot
            if ($subjects->isEmpty()) {
                Log::info("No subjects found in direct relationship for section {$sectionId}, trying three-way pivot");
                $subjects = $section->subjects()->get();

                // If still no subjects, as a last resort return all subjects
                if ($subjects->isEmpty()) {
                    Log::info("No subjects found for section {$sectionId}, returning all subjects instead");
                    $subjects = Subject::all();
                }
            }

            return response()->json($subjects);
        } catch (\Exception $e) {
            Log::error("Error getting subjects for section: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get subjects for section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a subject to a section
     */
    public function addSubjectToSection(Request $request, $curriculumId = null, $gradeId = null, $sectionId = null)
    {
        // If we're using the simplified route
        if ($sectionId === null && $request->route('sectionId')) {
            $sectionId = $request->route('sectionId');
        }

        try {
            $validated = $request->validate([
                'subject_id' => 'required|exists:subjects,id'
            ]);

            $section = Section::findOrFail($sectionId);
            $subjectId = $validated['subject_id'];

            // First, check if we already have this subject in the section_subject table
            if ($section->directSubjects()->where('subject_id', $subjectId)->exists()) {
                return response()->json([
                    'message' => 'Subject already exists in this section'
                ], 422);
            }

            // Add subject to section using the direct relationship
            $section->directSubjects()->attach($subjectId);

            // Return the subject with its relationship data
            $subject = Subject::find($subjectId);

            return response()->json([
                'message' => 'Subject added to section successfully',
                'subject_id' => $subjectId,
                'section_id' => $sectionId,
                'pivot' => [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            ], 201);
        } catch (\Exception $e) {
            Log::error("Error adding subject to section: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to add subject to section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a subject from a section
     */
    public function removeSubjectFromSection(Request $request, $curriculumId = null, $gradeId = null, $sectionId = null, $subjectId = null)
    {
        // If we're using the simplified route
        if ($sectionId === null && $request->route('sectionId')) {
            $sectionId = $request->route('sectionId');
            $subjectId = $request->route('subjectId');
        }

        try {
            $section = Section::findOrFail($sectionId);

            // Remove subject from section using direct relationship
            $section->directSubjects()->detach($subjectId);

            return response()->json([
                'message' => 'Subject removed from section successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error removing subject from section: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to remove subject from section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign a homeroom teacher to a section
     */
    public function assignHomeRoomTeacher(Request $request, $curriculumId, $gradeId, $sectionId)
    {
        try {
            $validated = $request->validate([
                'teacher_id' => 'required|exists:teachers,id'
            ]);

            $section = Section::findOrFail($sectionId);
            $section->homeroom_teacher_id = $validated['teacher_id'];
            $section->save();

            return response()->json([
                'message' => 'Homeroom teacher assigned successfully',
                'teacher_id' => $validated['teacher_id'],
                'section_id' => $sectionId
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error assigning homeroom teacher: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to assign homeroom teacher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign a teacher to a subject in a section
     */
    public function assignTeacherToSubject(Request $request, $curriculumId, $gradeId, $sectionId, $subjectId)
    {
        try {
            $validated = $request->validate([
                'teacher_id' => 'required|exists:teachers,id'
            ]);

            // Find the section
            $section = Section::findOrFail($sectionId);

            // Check if the subject exists
            $subject = Subject::findOrFail($subjectId);

            // Update or create the teacher-section-subject relationship
            DB::table('teacher_section_subject')
                ->updateOrInsert(
                    [
                        'teacher_id' => $validated['teacher_id'],
                        'section_id' => $sectionId,
                        'subject_id' => $subjectId
                    ],
                    [
                        'is_primary' => true,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );

            return response()->json([
                'message' => 'Teacher assigned to subject successfully',
                'teacher_id' => $validated['teacher_id'],
                'subject_id' => $subjectId,
                'section_id' => $sectionId
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error assigning teacher to subject: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to assign teacher to subject',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the teacher assigned to a subject in a section
     */
    public function getSubjectTeacher($sectionId, $subjectId)
    {
        try {
            // Query the teacher-section-subject relationship
            $assignment = DB::table('teacher_section_subject')
                ->where('section_id', $sectionId)
                ->where('subject_id', $subjectId)
                ->where('is_active', true)
                ->first();

            if (!$assignment) {
                return response()->json([
                    'message' => 'No teacher assigned to this subject'
                ], 404);
            }

            // Get teacher details
            $teacher = Teacher::find($assignment->teacher_id);

            if (!$teacher) {
                return response()->json([
                    'message' => 'Teacher not found'
                ], 404);
            }

            return response()->json($teacher);
        } catch (\Exception $e) {
            Log::error("Error getting subject teacher: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get subject teacher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set the schedule for a subject in a section
     */
    public function setSubjectSchedule(Request $request, $curriculumId, $gradeId, $sectionId, $subjectId)
    {
        try {
            $validated = $request->validate([
                'day' => 'required|string',
                'start_time' => 'required|string',
                'end_time' => 'required|string',
                'teacher_id' => 'nullable|exists:teachers,id'
            ]);

            // Check for schedule conflicts if a teacher is assigned
            if (!empty($validated['teacher_id'])) {
                // Convert times to standard format for comparison
                $startTime = date('H:i:s', strtotime($validated['start_time']));
                $endTime = date('H:i:s', strtotime($validated['end_time']));

                // Check if this would create a scheduling conflict for the teacher
                $conflict = SubjectSchedule::hasConflict(
                    $validated['teacher_id'],
                    $validated['day'],
                    $startTime,
                    $endTime
                );

                if ($conflict) {
                    return response()->json([
                        'message' => 'Teacher already has a schedule at this time',
                        'error' => 'Schedule conflict detected'
                    ], 422);
                }
            }

            // Create or update the schedule
            $schedule = SubjectSchedule::updateOrCreate(
                [
                    'section_id' => $sectionId,
                    'subject_id' => $subjectId,
                    'day' => $validated['day']
                ],
                [
                    'start_time' => $startTime ?? $validated['start_time'],
                    'end_time' => $endTime ?? $validated['end_time'],
                    'teacher_id' => $validated['teacher_id'] ?? null
                ]
            );

            return response()->json([
                'message' => 'Schedule set successfully',
                'schedule' => $schedule
            ], 200);
        } catch (\Exception $e) {
            Log::error("Error setting subject schedule: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to set subject schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the schedule for a subject in a section
     */
    public function getSubjectSchedule($sectionId, $subjectId)
    {
        try {
            $schedules = SubjectSchedule::where('section_id', $sectionId)
                ->where('subject_id', $subjectId)
                ->get();

            return response()->json($schedules);
        } catch (\Exception $e) {
            Log::error("Error getting subject schedule: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get subject schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
