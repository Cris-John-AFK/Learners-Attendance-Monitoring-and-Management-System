<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\CurriculumGrade;
use App\Models\TeacherSectionSubject;
use App\Models\SubjectSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            Log::info('Getting all sections');
            $sections = Section::with(['curriculumGrade.grade', 'homeroomTeacher'])->get();
            Log::info("Found {$sections->count()} sections");
            return response()->json($sections);
        } catch (\Exception $e) {
            Log::error('Error in index: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch sections',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sections by grade
     */
    public function byGrade($gradeId)
    {
        try {
            Log::info("Getting sections for grade ID: {$gradeId}");

            // Lightweight query with selective fields (removed 'status' column that doesn't exist)
            $sections = Section::select(['id', 'name', 'capacity', 'is_active', 'curriculum_grade_id', 'homeroom_teacher_id'])
                ->with([
                    'curriculumGrade:id,grade_id,curriculum_id',
                    'curriculumGrade.grade:id,code,name',
                    'homeroomTeacher:id,first_name,last_name'
                ])
                ->whereHas('curriculumGrade', function($query) use ($gradeId) {
                    $query->where('grade_id', $gradeId);
                })
                ->get();

            Log::info("Found {$sections->count()} sections for grade {$gradeId}");
            return response()->json($sections);
        } catch (\Exception $e) {
            Log::error("Error in byGrade: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch sections',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Section $section)
    {
        try {
            Log::info("Getting section {$section->id}");
            $section->load(['curriculumGrade.grade', 'homeroomTeacher']);
            return response()->json($section);
        } catch (\Exception $e) {
            Log::error("Error in show: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch section',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Log::info('Creating new section with data: ' . json_encode($request->all()));

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'curriculum_grade_id' => 'required|exists:curriculum_grade,id',
            'homeroom_teacher_id' => 'nullable|exists:teachers,id',
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for section creation: ' . json_encode($validator->errors()));
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $section = Section::create($request->all());
            return response()->json($section->load(['curriculumGrade.grade', 'homeroomTeacher']), 201);
        } catch (\Exception $e) {
            Log::error('Error creating section: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Section $section)
    {
        Log::info("Updating section {$section->id} with data: " . json_encode($request->all()));

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'curriculum_grade_id' => 'required|exists:curriculum_grade,id',
            'homeroom_teacher_id' => 'nullable|exists:teachers,id',
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for section update: ' . json_encode($validator->errors()));
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $section->update($request->all());
            return response()->json($section->load(['curriculumGrade.grade', 'homeroomTeacher']));
        } catch (\Exception $e) {
            Log::error('Error updating section: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Section $section)
    {
        try {
            Log::info("Deleting section {$section->id}");
            $section->delete();
            return response()->json(['message' => 'Section deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Error deleting section: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign homeroom teacher to section
     */
    public function assignHomeroomTeacher($curriculumId, $gradeId, $sectionId, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Find the section
            $section = Section::findOrFail($sectionId);

            Log::info("Attempting to assign teacher {$request->teacher_id} to section {$section->id}");

            // Update the section with homeroom teacher
            $section->homeroom_teacher_id = $request->teacher_id;
            $saved = $section->save();

            Log::info("Save result: " . ($saved ? 'success' : 'failed'));

            // Refresh the section from database to get latest data
            $section->refresh();

            Log::info("Section after refresh - homeroom_teacher_id: " . $section->homeroom_teacher_id);

            return response()->json([
                'success' => true,
                'message' => 'Homeroom teacher assigned successfully',
                'section' => $section->load(['curriculumGrade.grade', 'homeroomTeacher'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error assigning homeroom teacher: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign homeroom teacher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign a teacher to a subject in a section
     */
    public function assignTeacherToSubject($sectionId, $subjectId, Request $request)
    {
        try {
            $validated = $request->validate([
                'teacher_id' => 'required|exists:teachers,id'
            ]);

            // Find the section and subject
            $section = Section::findOrFail($sectionId);
            $subject = Subject::findOrFail($subjectId);

            // First, ensure the subject is added to the section_subject table
            $section->directSubjects()->syncWithoutDetaching([$subjectId]);

            // Create or update the teacher-section-subject relationship using the TeacherSectionSubject model
            $assignment = TeacherSectionSubject::updateOrCreate(
                [
                    'teacher_id' => $validated['teacher_id'],
                    'section_id' => $sectionId,
                    'subject_id' => $subjectId
                ],
                [
                    'is_primary' => false,
                    'is_active' => true,
                    'role' => 'subject'
                ]
            );

            // Load relationships for the response
            $assignment->load(['teacher', 'section', 'subject']);

            // Return the updated assignment with related data
            return response()->json([
                'message' => 'Teacher assigned to subject successfully',
                'data' => [
                    'teacher_id' => $validated['teacher_id'],
                    'subject_id' => $subjectId,
                    'section_id' => $sectionId,
                    'assignment' => $assignment
                ]
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
     * Set the schedule for a subject in a section (direct route)
     */
    public function setSubjectSchedule(Request $request, $sectionId, $subjectId)
    {
        return $this->setSubjectScheduleWithParams($request, null, null, $sectionId, $subjectId);
    }

    /**
     * Set the schedule for a subject in a section (with all parameters)
     */
    public function setSubjectScheduleWithParams(Request $request, $curriculumId = null, $gradeId = null, $sectionId, $subjectId)
    {
        try {
            Log::info("Setting schedule for section {$sectionId}, subject {$subjectId}");
            Log::info("Request data: " . json_encode($request->all()));
            
            $validated = $request->validate([
                'day' => 'required|string',
                'start_time' => 'required|string',
                'end_time' => 'required|string',
                'teacher_id' => 'nullable|exists:teachers,id'
            ]);

            // Convert times to standard format for comparison
            $startTime = date('H:i:s', strtotime($validated['start_time']));
            $endTime = date('H:i:s', strtotime($validated['end_time']));
            
            Log::info("Converted times - Start: {$startTime}, End: {$endTime}");

            // Check for schedule conflicts if a teacher is assigned
            if (!empty($validated['teacher_id'])) {
                // Check if this would create a scheduling conflict for the teacher
                $existingSchedule = SubjectSchedule::where('teacher_id', $validated['teacher_id'])
                    ->where('day', $validated['day'])
                    ->where(function($query) use ($startTime, $endTime) {
                        $query->whereBetween('start_time', [$startTime, $endTime])
                              ->orWhereBetween('end_time', [$startTime, $endTime])
                              ->orWhere(function($q) use ($startTime, $endTime) {
                                  $q->where('start_time', '<=', $startTime)
                                    ->where('end_time', '>=', $endTime);
                              });
                    })
                    ->exists();

                if ($existingSchedule) {
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
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'teacher_id' => $validated['teacher_id'] ?? null
                ]
            );
            
            Log::info("Schedule created/updated successfully: " . json_encode($schedule->toArray()));

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

    /**
     * API endpoint to repair section-subject relationships
     */
    public function repairSectionSubjectsEndpoint(Request $request, $sectionId)
    {
        try {
            Log::info("API request to repair section-subject relationships for section: {$sectionId}");

            $section = Section::find($sectionId);
            if (!$section) {
                return response()->json([
                    'message' => 'Section not found',
                    'success' => false
                ], 404);
            }

            $success = $this->repairSectionSubjects($sectionId);

            if ($success) {
                return response()->json([
                    'message' => 'Section-subject relationships repaired successfully',
                    'success' => true
                ]);
            } else {
                return response()->json([
                    'message' => 'Failed to repair section-subject relationships',
                    'success' => false
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error("Error in repair endpoint: " . $e->getMessage());
            return response()->json([
                'message' => 'Error repairing section-subject relationships: ' . $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    /**
     * Helper method to repair section-subject relationships
     */
    private function repairSectionSubjects($sectionId)
    {
        try {
            Log::info("Repairing section-subject relationships for section: {$sectionId}");

            // Implementation would go here based on your business logic
            // This is a placeholder for the actual repair logic

            return true;
        } catch (\Exception $e) {
            Log::error("Error in repairSectionSubjects: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get subjects for a section
     */
    /**
     * Get subjects for a section (nested route version)
     */
    public function getSectionSubjects(Request $request, $curriculumId, $gradeId, $sectionId)
    {
        try {
            Log::info("Getting subjects for section via nested route: curriculum={$curriculumId}, grade={$gradeId}, section={$sectionId}");
            Log::info("Request params: " . json_encode($request->all()));

            $section = Section::findOrFail($sectionId);
            $userAddedOnly = $request->boolean('user_added_only');

            Log::info("User added only flag: " . ($userAddedOnly ? 'true' : 'false'));

            if ($userAddedOnly) {
                Log::info("Getting ONLY user-added subjects for section $sectionId");
                $subjects = $section->directSubjects()->get();
                
                // Load schedules for user-added subjects too
                $subjects = $subjects->map(function ($subject) use ($sectionId) {
                    $schedules = \App\Models\SubjectSchedule::where('section_id', $sectionId)
                        ->where('subject_id', $subject->id)
                        ->get();
                    $subject->schedules = $schedules;
                    return $subject;
                });
                
                Log::info("Found " . count($subjects) . " user-added subjects for section $sectionId");
                return response()->json($subjects);
            }

            Log::info("Getting ALL subjects for section $sectionId (including auto-assigned)");
            $allSubjects = $section->subjects()->with('schedules')->get();
            $directSubjects = $section->directSubjects()->with('schedules')->get();
            $mergedSubjects = $allSubjects->concat($directSubjects)->unique('id');

            // Load schedules for each subject
            $mergedSubjects = $mergedSubjects->map(function ($subject) use ($sectionId) {
                $schedules = \App\Models\SubjectSchedule::where('section_id', $sectionId)
                    ->where('subject_id', $subject->id)
                    ->get();
                $subject->schedules = $schedules;
                return $subject;
            });

            Log::info("Found " . count($mergedSubjects) . " total subjects for section $sectionId");
            Log::debug("Subjects with schedules: " . json_encode($mergedSubjects->toArray()));
            return response()->json($mergedSubjects);
        } catch (\Exception $e) {
            Log::error("Error in getSectionSubjects: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get subjects for a section (direct route version)
     */
    public function getSubjects(Request $request, $sectionId)
    {
        try {
            Log::info("Getting subjects for section: $sectionId with params: " . json_encode($request->all()));

            $section = Section::findOrFail($sectionId);
            $userAddedOnly = $request->boolean('user_added_only');

            Log::info("User added only flag: " . ($userAddedOnly ? 'true' : 'false'));

            // If user_added_only is true, only return subjects that were manually added through directSubjects
            if ($userAddedOnly) {
                Log::info("Getting ONLY user-added subjects for section $sectionId");

                // Use the directSubjects relationship which uses the section_subject pivot table
                // This specifically gets only manually added subjects
                $subjects = $section->directSubjects()->get();

                // Load schedules for user-added subjects too
                $subjects = $subjects->map(function ($subject) use ($sectionId) {
                    $schedules = \App\Models\SubjectSchedule::where('section_id', $sectionId)
                        ->where('subject_id', $subject->id)
                        ->where('is_active', true)
                        ->get();
                    $subject->schedules = $schedules;
                    return $subject;
                });

                Log::info("Found " . count($subjects) . " user-added subjects for section $sectionId");
                Log::debug("User-added subjects with schedules: " . json_encode($subjects->toArray()));

                return response()->json($subjects);
            }

            // If not filtering, return a combination of both relationships to ensure
            // we get all subjects associated with this section
            Log::info("Getting ALL subjects for section $sectionId (including auto-assigned)");

            // Get subjects from both relationships
            $allSubjects = $section->subjects()->get();
            $directSubjects = $section->directSubjects()->get();

            // Merge them and ensure uniqueness by ID
            $mergedSubjects = $allSubjects->concat($directSubjects)->unique('id');

            // Load schedules for each subject
            $mergedSubjects = $mergedSubjects->map(function ($subject) use ($sectionId) {
                $schedules = \App\Models\SubjectSchedule::where('section_id', $sectionId)
                    ->where('subject_id', $subject->id)
                    ->get();
                $subject->schedules = $schedules;
                return $subject;
            });

            Log::info("Found " . count($mergedSubjects) . " total subjects for section $sectionId");
            Log::debug("All subjects: " . json_encode($mergedSubjects->pluck('name')));
            Log::debug("Subjects with schedules: " . json_encode($mergedSubjects->toArray()));

            return response()->json($mergedSubjects);
        } catch (\Exception $e) {
            Log::error("Error in getSubjects: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get only directly added subjects for a section
     * This only returns subjects that were explicitly added through the section_subject relationship
     */
    public function getDirectSubjects(Request $request, $sectionId)
    {
        try {
            Log::info("Getting direct subjects for section: $sectionId with params: " . json_encode($request->all()));

            $section = Section::findOrFail($sectionId);

            // Use the directSubjects relationship which uses the section_subject pivot table
            // This specifically gets only manually added subjects
            $subjects = $section->directSubjects()->get();

            Log::info("Found " . count($subjects) . " directly added subjects for section $sectionId");
            Log::debug("Direct subjects: " . json_encode($subjects->pluck('name')));

            return response()->json($subjects);
        } catch (\Exception $e) {
            Log::error("Error in getDirectSubjects: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Add a subject to a section (nested route version)
     */
    public function addSubjectToSection(Request $request, $curriculumId, $gradeId, $sectionId)
    {
        try {
            Log::info("Adding subject to section via nested route: curriculum={$curriculumId}, grade={$gradeId}, section={$sectionId}");
            Log::info("Request data: " . json_encode($request->all()));
            
            $validated = $request->validate([
                'subject_id' => 'required|exists:subjects,id'
            ]);

            $section = Section::findOrFail($sectionId);

            // Add the subject to the section using the pivot table
            $section->directSubjects()->syncWithoutDetaching([$validated['subject_id']]);

            Log::info("Successfully added subject {$validated['subject_id']} to section {$sectionId}");

            return response()->json([
                'message' => 'Subject added to section successfully',
                'section_id' => $sectionId,
                'subject_id' => $validated['subject_id'],
                'curriculum_id' => $curriculumId,
                'grade_id' => $gradeId
            ]);
        } catch (\Exception $e) {
            Log::error("Error adding subject to section via nested route: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to add subject to section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a subject to a section (direct route version)
     */
    public function addSubject(Request $request, $sectionId)
    {
        try {
            Log::info("Adding subject to section via direct route: section={$sectionId}");
            Log::info("Request data: " . json_encode($request->all()));
            
            $validated = $request->validate([
                'subject_id' => 'required|exists:subjects,id'
            ]);

            $section = Section::findOrFail($sectionId);

            // Add the subject to the section using the pivot table
            $section->directSubjects()->syncWithoutDetaching([$validated['subject_id']]);

            Log::info("Successfully added subject {$validated['subject_id']} to section {$sectionId}");

            return response()->json([
                'message' => 'Subject added to section successfully',
                'section_id' => $sectionId,
                'subject_id' => $validated['subject_id']
            ]);
        } catch (\Exception $e) {
            Log::error("Error adding subject to section via direct route: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to add subject to section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a subject from a section (nested route version)
     */
    public function removeSubjectFromSection($curriculumId, $gradeId, $sectionId, $subjectId)
    {
        try {
            Log::info("Removing subject from section via nested route: curriculum={$curriculumId}, grade={$gradeId}, section={$sectionId}, subject={$subjectId}");
            
            $section = Section::findOrFail($sectionId);

            // Remove the subject from the section
            $section->directSubjects()->detach($subjectId);

            Log::info("Successfully removed subject {$subjectId} from section {$sectionId}");

            return response()->json([
                'message' => 'Subject removed from section successfully',
                'section_id' => $sectionId,
                'subject_id' => $subjectId,
                'curriculum_id' => $curriculumId,
                'grade_id' => $gradeId
            ]);
        } catch (\Exception $e) {
            Log::error("Error removing subject from section via nested route: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to remove subject from section',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a subject from a section (direct route version)
     */
    public function removeSubject($sectionId, $subjectId)
    {
        try {
            Log::info("Removing subject from section via direct route: section={$sectionId}, subject={$subjectId}");
            
            $section = Section::findOrFail($sectionId);

            // Remove the subject from the section
            $section->directSubjects()->detach($subjectId);

            Log::info("Successfully removed subject {$subjectId} from section {$sectionId}");

            return response()->json([
                'message' => 'Subject removed from section successfully',
                'section_id' => $sectionId,
                'subject_id' => $subjectId
            ]);
        } catch (\Exception $e) {
            Log::error("Error removing subject from section via direct route: " . $e->getMessage());
            return response()->json([
                'message' => 'Failed to remove subject from section',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
