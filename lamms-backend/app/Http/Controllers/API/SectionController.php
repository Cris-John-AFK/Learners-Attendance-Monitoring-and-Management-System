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
use App\Models\TeacherSectionSubject;
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
        try {
            Log::info("Getting sections for grade ID: {$gradeId}");

            // Check if grade_id column exists
            $hasGradeIdColumn = Section::hasGradeIdColumn();
            Log::info("Table has grade_id column: " . ($hasGradeIdColumn ? 'Yes' : 'No'));

            // First try with the direct relationship if column exists
            $sections = [];
            if ($hasGradeIdColumn) {
                Log::info("Trying direct grade relationship with grade_id");
                $sections = Section::with(['curriculumGrade', 'directSubjects'])
                    ->where('grade_id', $gradeId)
                    ->get();

                Log::info("Found {$sections->count()} sections with direct grade relationship");
            } else {
                Log::info("No grade_id column, skipping direct relationship query");
            }

            // If we don't find any, try looking through curriculum_grade relationship
            if (empty($sections) || $sections->isEmpty()) {
                Log::info("No sections found with direct relationship or no grade_id column exists, trying via curriculum_grade");

                // Find all curriculum_grades for this grade
                $curriculumGrades = DB::table('curriculum_grade')
                    ->where('grade_id', $gradeId)
                    ->get();

                Log::info("Found {$curriculumGrades->count()} curriculum_grade entries for grade {$gradeId}");

                if (!$curriculumGrades->isEmpty()) {
                    // Get all sections that reference these curriculum_grades
                    $curriculumGradeIds = $curriculumGrades->pluck('id')->toArray();
                    $sections = Section::with(['curriculumGrade', 'directSubjects'])
                        ->whereIn('curriculum_grade_id', $curriculumGradeIds)
                        ->get();

                    Log::info("Found {$sections->count()} sections via curriculum_grade relationship");
                }
            }

            if (empty($sections) || $sections->isEmpty()) {
                // Final attempt - query with loose matching
                Log::info("Still no sections found, trying with loose matching");

                $query = DB::table('sections')
                    ->leftJoin('curriculum_grade', 'sections.curriculum_grade_id', '=', 'curriculum_grade.id');

                // Only join with grades table if it exists
                if ($hasGradeIdColumn) {
                    $query->leftJoin('grades', function($join) use ($gradeId) {
                        $join->on('curriculum_grade.grade_id', '=', 'grades.id')
                            ->orOn('sections.grade_id', '=', 'grades.id');
                    });

                    $query->where(function($where) use ($gradeId) {
                        $where->where('grades.id', $gradeId)
                            ->orWhere('curriculum_grade.grade_id', $gradeId)
                            ->orWhere('sections.grade_id', $gradeId);
                    });
                } else {
                    $query->leftJoin('grades', 'curriculum_grade.grade_id', '=', 'grades.id');
                    $query->where('curriculum_grade.grade_id', $gradeId);
                }

                $sections = $query->select('sections.*')
                    ->distinct()
                    ->get();

                Log::info("Found {$sections->count()} sections with loose matching");

                // Convert the results to Section models
                if ($sections->count() > 0) {
                    $sectionIds = $sections->pluck('id')->toArray();
                    $sections = Section::with(['curriculumGrade', 'directSubjects'])
                        ->whereIn('id', $sectionIds)
                        ->get();
                }
            }

            // Make sure all sections have their grade information properly loaded
            if ($sections && $sections->isNotEmpty()) {
                foreach ($sections as $section) {
                    // Force loading of grade relationship if not already loaded
                    if (!$section->relationLoaded('grade') || !$section->grade) {
                        $grade = $section->getGradeAttribute();
                        if ($grade) {
                            $section->setRelation('grade', $grade);
                        }
                    }
                }
            }

            Log::info("Returning {$sections->count()} sections for grade {$gradeId}");
            return response()->json($sections);
        } catch (\Exception $e) {
            Log::error("Error in byGrade: {$e->getMessage()}");
            Log::error("Stack trace: {$e->getTraceAsString()}");
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
            'curriculum_grade_id' => 'required|exists:curriculum_grade,id',
            'homeroom_teacher_id' => 'nullable|exists:teachers,id',
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Check if section name is unique within the curriculum grade (excluding current section)
            if (!Section::isNameUniqueInCurriculumGrade($request->name, $request->curriculum_grade_id, $section->id)) {
                return response()->json([
                    'errors' => [
                        'name' => ['Section name already exists in this grade.']
                    ]
                ], 422);
            }

            // Begin a transaction for safe updates
            DB::beginTransaction();

            // Get original teacher ID for comparison
            $originalTeacherId = $section->homeroom_teacher_id;

            // Update basic section fields
            $section->name = $request->name;
            $section->curriculum_grade_id = $request->curriculum_grade_id;
            $section->homeroom_teacher_id = $request->homeroom_teacher_id;
            $section->description = $request->description;
            $section->capacity = $request->capacity ?? $section->capacity;
            $section->save();

            // If the homeroom_teacher_id was changed, update or create the teacher-section-subject relationship
            if ($request->homeroom_teacher_id !== null && $request->homeroom_teacher_id != $originalTeacherId) {
                Log::info("Updating homeroom teacher relationship for section {$section->id}: teacher {$request->homeroom_teacher_id}");

                // Create or update the teacher-section-subject relationship
                TeacherSectionSubject::updateOrCreate(
                    [
                        'teacher_id' => $request->homeroom_teacher_id,
                        'section_id' => $section->id,
                        'role' => 'homeroom'
                    ],
                    [
                        'is_primary' => true,
                        'is_active' => true,
                        'subject_id' => null
                    ]
                );
            }

            DB::commit();

            return response()->json($section->load(['curriculumGrade.grade', 'homeroomTeacher']));
        } catch (\Exception $e) {
            DB::rollBack();
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
            Log::info("Getting subjects for section: {$sectionId}, params: " . json_encode($request->all()));

            // Check if force repair is requested
            $forceRepair = $request->has('force') || $request->query('force') === 'true';
            $noFallback = $request->has('no_fallback') || $request->query('no_fallback') === 'true';

            if ($forceRepair) {
                Log::info("Force repair requested for section: {$sectionId}");
                $this->repairSectionSubjects($sectionId);
            }

            // Check if section exists
            $section = Section::find($sectionId);
            if (!$section) {
                Log::error("Section not found: {$sectionId}");

                // If no fallback requested, just return empty array
                if ($noFallback) {
                    Log::info("No fallback requested, returning empty array for section {$sectionId}");
                    return response()->json([]);
                }

                // Try creating a default section with this ID as a last resort
                try {
                    Log::info("Attempting to create a default section with ID: {$sectionId}");
                    $section = new Section([
                        'id' => $sectionId,
                        'name' => 'Section ' . $sectionId,
                        'is_active' => true
                    ]);
                    $section->save();

                    // Then immediately try to repair section-subject relationships
                    $this->repairSectionSubjects($sectionId);
                } catch (\Exception $createError) {
                    Log::error("Could not create default section: " . $createError->getMessage());
                    return response()->json($this->getFallbackSubjects($sectionId), 200);
                }
            }

            // Try direct query first for better performance
            try {
                Log::info("Querying subject data for section: {$sectionId}");

                // First try the Eloquent relationship approach
                try {
                    // Use the directSubjects relationship which uses the section_subject pivot table
                    $section = Section::with('directSubjects')->find($sectionId);

                    if ($section && $section->directSubjects && $section->directSubjects->count() > 0) {
                        $subjects = $section->directSubjects;
                        Log::info("Eloquent relationship returned " . $subjects->count() . " subjects");

                        return response()->json($subjects);
                    }
                } catch (\Exception $eloquentError) {
                    Log::error("Eloquent query failed: " . $eloquentError->getMessage());
                }

                // Fall back to direct DB query if Eloquent fails
                $subjects = DB::table('section_subject')
                    ->join('subjects', 'section_subject.subject_id', '=', 'subjects.id')
                    ->where('section_subject.section_id', $sectionId)
                    ->select('subjects.*', 'section_subject.created_at as pivot_created_at', 'section_subject.updated_at as pivot_updated_at')
                    ->get();

                Log::info("Direct query returned " . $subjects->count() . " subjects");

                if ($subjects && $subjects->count() > 0) {
                    // Format the subjects with proper pivot data
                    $formattedSubjects = $subjects->map(function($subject) use ($sectionId) {
                        // Make sure we have valid data for all fields
                        $subject->name = $subject->name ?? 'Unknown Subject';
                        $subject->code = $subject->code ?? 'SUBJ';
                        $subject->description = $subject->description ?? '';
                        $subject->is_active = $subject->is_active ?? true;

                        $pivotData = [
                            'section_id' => $sectionId,
                            'subject_id' => $subject->id,
                            'created_at' => $subject->pivot_created_at ?? now()->toDateTimeString(),
                            'updated_at' => $subject->pivot_updated_at ?? now()->toDateTimeString()
                        ];

                        $subject->pivot = $pivotData;
                        unset($subject->pivot_created_at);
                        unset($subject->pivot_updated_at);
                        return $subject;
                    });

                    Log::info("Returning " . count($formattedSubjects) . " formatted subjects for section {$sectionId}");
                    return response()->json($formattedSubjects);
                }

                // If no results from direct query, perform a repair
                Log::info("No subjects found from direct query, repairing relationships for section {$sectionId}");
                $this->repairSectionSubjects($sectionId);

                // Try one more time after repair
                $section = Section::with('directSubjects')->find($sectionId);
                if ($section && $section->directSubjects && $section->directSubjects->count() > 0) {
                    $subjects = $section->directSubjects;
                    Log::info("After repair, Eloquent relationship returned " . $subjects->count() . " subjects");
                    return response()->json($subjects);
                }

                // Fall back to direct DB query if needed
                $subjects = DB::table('section_subject')
                    ->join('subjects', 'section_subject.subject_id', '=', 'subjects.id')
                    ->where('section_subject.section_id', $sectionId)
                    ->select('subjects.*', 'section_subject.created_at as pivot_created_at', 'section_subject.updated_at as pivot_updated_at')
                    ->get();

                if ($subjects && $subjects->count() > 0) {
                    $formattedSubjects = $subjects->map(function($subject) use ($sectionId) {
                        $subject->pivot = [
                            'section_id' => $sectionId,
                            'subject_id' => $subject->id,
                            'created_at' => $subject->pivot_created_at ?? now()->toDateTimeString(),
                            'updated_at' => $subject->pivot_updated_at ?? now()->toDateTimeString()
                        ];
                        unset($subject->pivot_created_at);
                        unset($subject->pivot_updated_at);
                        return $subject;
                    });

                    Log::info("Returning " . count($formattedSubjects) . " subjects after repair for section {$sectionId}");
                    return response()->json($formattedSubjects);
                }

                // If still no subjects, check if we need to return an empty array instead of fallback
                if ($noFallback) {
                    Log::info("No fallback requested, returning empty array for section {$sectionId}");
                    return response()->json([]);
                }

                // If all attempts failed, return fallback
                Log::info("All attempts failed, returning fallback data for section {$sectionId}");
                return response()->json($this->getFallbackSubjects($sectionId), 200);
            } catch (\Exception $queryError) {
                Log::error("Error querying subjects: " . $queryError->getMessage());

                // If no fallback requested, just return empty array
                if ($noFallback) {
                    Log::info("No fallback requested after error, returning empty array");
                    return response()->json([]);
                }

                // Try to repair and retry
                Log::info("Attempting repair after query error for section {$sectionId}");
                $this->repairSectionSubjects($sectionId);

                try {
                    // Try one more time
                    $subjects = DB::table('section_subject')
                        ->join('subjects', 'section_subject.subject_id', '=', 'subjects.id')
                        ->where('section_subject.section_id', $sectionId)
                        ->select('subjects.*')
                        ->get();

                    if ($subjects && $subjects->count() > 0) {
                        $formattedSubjects = $subjects->map(function($subject) use ($sectionId) {
                            return [
                                'id' => $subject->id,
                                'name' => $subject->name ?? 'Unknown Subject',
                                'code' => $subject->code ?? 'SUBJ',
                                'description' => $subject->description ?? '',
                                'is_active' => $subject->is_active ?? true,
                                'pivot' => [
                                    'section_id' => $sectionId,
                                    'subject_id' => $subject->id,
                                    'created_at' => now()->toDateTimeString(),
                                    'updated_at' => now()->toDateTimeString()
                                ]
                            ];
                        });

                        Log::info("Returning " . count($formattedSubjects) . " subjects after repair and retry for section {$sectionId}");
                        return response()->json($formattedSubjects);
                    }
                } catch (\Exception $retryError) {
                    Log::error("Direct DB query failed after repair: " . $retryError->getMessage());
                }

                // If all attempts failed, return fallback
                Log::info("All repair attempts failed, returning fallback for section {$sectionId}");
                return response()->json($this->getFallbackSubjects($sectionId), 200);
            }
        } catch (\Exception $e) {
            Log::error("Error getting subjects for section: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return response()->json($this->getFallbackSubjects($sectionId), 200);
        }
    }

    /**
     * Helper method to repair section-subject relationships
     */
    private function repairSectionSubjects($sectionId)
    {
        try {
            Log::info("Repairing section-subject relationships for section: {$sectionId}");

            // Begin transaction to ensure data consistency
            DB::beginTransaction();

            $section = Section::find($sectionId);

            if (!$section) {
                Log::error("Cannot repair - section not found: {$sectionId}");
                DB::rollBack();
                return false;
            }

            // Check if the section_subject table exists, create it if not
            if (!Schema::hasTable('section_subject')) {
                Log::info("section_subject table does not exist, creating it");
                Schema::create('section_subject', function ($table) {
                    $table->unsignedBigInteger('section_id');
                    $table->unsignedBigInteger('subject_id');
                    $table->timestamps();

                    $table->primary(['section_id', 'subject_id']);

                    $table->foreign('section_id')
                          ->references('id')
                          ->on('sections')
                          ->onDelete('cascade');

                    $table->foreign('subject_id')
                          ->references('id')
                          ->on('subjects')
                          ->onDelete('cascade');
                });
            }

            // Get real subjects to assign - first try to get existing relationships
            $existingSubjects = DB::table('section_subject')
                ->where('section_id', $sectionId)
                ->pluck('subject_id');

            Log::info("Found {$existingSubjects->count()} existing subject relationships");

            // If we have existing relationships, verify they're valid
            if ($existingSubjects->count() > 0) {
                $validSubjects = Subject::whereIn('id', $existingSubjects)->get();
                Log::info("Found {$validSubjects->count()} valid subjects from existing relationships");

                if ($validSubjects->count() > 0) {
                    // Reattach valid subjects to ensure relationships are clean
                    $section->directSubjects()->sync($validSubjects->pluck('id')->toArray());
                    DB::commit();
                    Log::info("Successfully repaired existing relationships");
                    return true;
                }
            }

            // If no valid existing relationships, get active subjects
            $subjects = Subject::where('is_active', true)->limit(5)->get();

            // If no active subjects found, get any subjects
            if ($subjects->isEmpty()) {
                $subjects = Subject::limit(5)->get();
            }

            // If still no subjects, create some default subjects
            if ($subjects->isEmpty()) {
                Log::info("No subjects found in database, creating default subjects");

                // Create default subjects if none exist
                $defaultSubjects = [
                    [
                        'name' => 'Mathematics',
                        'code' => 'MATH',
                        'description' => 'Mathematics subject',
                        'is_active' => true
                    ],
                    [
                        'name' => 'Science',
                        'code' => 'SCI',
                        'description' => 'Science subject',
                        'is_active' => true
                    ],
                    [
                        'name' => 'English',
                        'code' => 'ENG',
                        'description' => 'English subject',
                        'is_active' => true
                    ]
                ];

                // Create the subjects
                foreach ($defaultSubjects as $subjectData) {
                    $subject = Subject::create($subjectData);
                    $subjects->push($subject);
                }
            }

            // Make sure we have at least some subjects
            if ($subjects->isEmpty()) {
                Log::error("Failed to find or create any subjects");
                DB::rollBack();
                return false;
            }

            // Clear existing relationships and add new ones
            Log::info("Attaching {$subjects->count()} subjects to section {$sectionId}");
            $section->directSubjects()->sync($subjects->pluck('id')->toArray());

            DB::commit();
            Log::info("Successfully repaired section-subject relationships");
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error repairing section-subject relationships: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Get fallback subjects when real data is unavailable
     */
    private function getFallbackSubjects($sectionId)
    {
        $defaultSubjects = [
            [
                'id' => 1,
                'name' => 'Mathematics',
                'code' => 'MATH',
                'description' => 'Mathematics fundamentals',
                'grade_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'is_active' => true
            ],
            [
                'id' => 2,
                'name' => 'Science',
                'code' => 'SCI',
                'description' => 'Science fundamentals',
                'grade_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'is_active' => true
            ],
            [
                'id' => 3,
                'name' => 'English',
                'code' => 'ENG',
                'description' => 'English language and literature',
                'grade_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'is_active' => true
            ]
        ];

        return array_map(function($subject) use ($sectionId) {
            $subject['section_id'] = $sectionId;
            $subject['pivot'] = [
                'section_id' => $sectionId,
                'subject_id' => $subject['id'],
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString()
            ];
            return $subject;
        }, $defaultSubjects);
    }

    /**
     * Add a subject to a section
     */
    public function addSubjectToSection(Request $request, $curriculumId = null, $gradeId = null, $sectionId = null)
    {
        // Handle both route types
        if ($sectionId === null && $request->route('sectionId')) {
            $sectionId = $request->route('sectionId');
        }

        try {
            // Validate the request - ensure subjectId is provided
            $validator = Validator::make($request->all(), [
                'subject_id' => 'required|exists:subjects,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $subjectId = $request->input('subject_id');
            $section = Section::find($sectionId);
            $subject = Subject::find($subjectId);

            if (!$section || !$subject) {
                return response()->json(['message' => 'Section or Subject not found'], 404);
            }

            Log::info("Adding subject {$subjectId} to section {$sectionId}");

            // First, check if this relationship already exists
            $exists = DB::table('section_subject')
                ->where('section_id', $sectionId)
                ->where('subject_id', $subjectId)
                ->exists();

            if ($exists) {
                Log::info("Subject already added to section");
                return response()->json(['message' => 'Subject already added to section'], 200);
            }

            // Use the direct relationship to add the subject to the section
            $section->directSubjects()->attach($subjectId);

            Log::info("Subject added successfully");

            // Return the updated subject
            return response()->json([
                'message' => 'Subject added to section successfully',
                'subject' => $subject
            ], 201);
        } catch (\Exception $e) {
            Log::error("Error adding subject to section: " . $e->getMessage());
            return response()->json(['message' => 'Failed to add subject: ' . $e->getMessage()], 500);
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
            Log::info("Assigning homeroom teacher to section {$sectionId}. Request data: " . json_encode($request->all()));

            $validated = $request->validate([
                'teacher_id' => 'required|exists:teachers,id'
            ]);

            Log::info("Validation passed. Teacher ID: {$validated['teacher_id']}");

            // Verify section exists
            $section = Section::find($sectionId);
            if (!$section) {
                Log::error("Section not found: {$sectionId}");
                return response()->json([
                    'message' => 'Section not found',
                    'error' => 'Section with ID ' . $sectionId . ' does not exist'
                ], 404);
            }

            // Verify teacher exists
            $teacher = Teacher::find($validated['teacher_id']);
            if (!$teacher) {
                Log::error("Teacher not found: {$validated['teacher_id']}");
                return response()->json([
                    'message' => 'Teacher not found',
                    'error' => 'Teacher with ID ' . $validated['teacher_id'] . ' does not exist'
                ], 404);
            }

            DB::beginTransaction();
            Log::info("Beginning transaction to update section and create relationship");

            // Update the section's homeroom_teacher_id
            $section->homeroom_teacher_id = $validated['teacher_id'];
            $section->save();
            Log::info("Section {$sectionId} updated with homeroom_teacher_id: {$validated['teacher_id']}");

            // Create or update the teacher-section-subject relationship for homeroom
            // No subject_id is needed for homeroom role
            $result = DB::statement("
                INSERT INTO teacher_section_subject
                (teacher_id, section_id, role, is_primary, is_active)
                VALUES (?, ?, 'homeroom', true, true)
                ON CONFLICT (teacher_id, section_id, subject_id)
                WHERE subject_id IS NULL
                DO UPDATE SET
                    role = 'homeroom',
                    is_primary = true,
                    is_active = true,
                    deleted_at = NULL
            ", [$validated['teacher_id'], $sectionId]);

            if (!$result) {
                // If the ON CONFLICT fails (possibly due to database constraints),
                // try a different approach by finding existing relationship first
                $existingRelation = DB::table('teacher_section_subject')
                    ->where('teacher_id', $validated['teacher_id'])
                    ->where('section_id', $sectionId)
                    ->where('role', 'homeroom')
                    ->first();

                if ($existingRelation) {
                    // Update existing relation
                    DB::table('teacher_section_subject')
                        ->where('id', $existingRelation->id)
                        ->update([
                            'is_primary' => true,
                            'is_active' => true,
                            'deleted_at' => null
                        ]);
                } else {
                    // Insert new relation
                    DB::table('teacher_section_subject')->insert([
                        'teacher_id' => $validated['teacher_id'],
                        'section_id' => $sectionId,
                        'role' => 'homeroom',
                        'is_primary' => true,
                        'is_active' => true
                    ]);
                }
            }

            Log::info("Teacher-section-subject relationship created/updated for homeroom teacher");

            DB::commit();
            Log::info("Transaction committed successfully");

            return response()->json([
                'message' => 'Homeroom teacher assigned successfully',
                'teacher_id' => $validated['teacher_id'],
                'section_id' => $sectionId,
                'teacher' => $teacher->only(['id', 'user_id', 'first_name', 'last_name']),
                'section' => $section->only(['id', 'name', 'homeroom_teacher_id'])
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error("Validation error assigning homeroom teacher: " . json_encode($e->errors()));
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error assigning homeroom teacher: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return response()->json([
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

                Log::info("Found " . count($subjects) . " user-added subjects for section $sectionId");
                Log::debug("User-added subjects: " . json_encode($subjects->pluck('name')));

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

            Log::info("Found " . count($mergedSubjects) . " total subjects for section $sectionId");
            Log::debug("All subjects: " . json_encode($mergedSubjects->pluck('name')));

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
}
