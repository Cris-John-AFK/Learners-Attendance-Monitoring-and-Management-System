<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class CurriculumController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Curriculum::query();

            // Check if specific fields are requested
            if ($request->has('fields')) {
                $fields = explode(',', $request->fields);
                $validFields = array_intersect($fields, ['id', 'name', 'status', 'is_active', 'start_year', 'end_year', 'description']);

                // If there are valid fields, select only those fields
                if (!empty($validFields)) {
                    $query->select($validFields);
                }

                // Only load relationships if not requesting specific fields or if relationships are in the fields
                if (in_array('grades', $fields) || !$request->has('fields')) {
                    $query->with('grades');
                }

                if (in_array('subjects', $fields) || !$request->has('fields')) {
                    $query->with('subjects');
                }
            } else {
                // If no fields specified, load all relationships
                $query->with(['grades', 'subjects']);
            }

            $curriculums = $query->get();
            return response()->json($curriculums);
        } catch (\Exception $e) {
            Log::error('Error in curriculum index: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to retrieve curriculums: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'start_year' => 'nullable|integer|min:2000',
            'end_year' => 'nullable|integer|min:2000',
            'description' => 'nullable|string',
            'grades' => 'nullable|array',
            'grades.*' => 'exists:grades,id'
        ]);

        // Custom validation for end_year > start_year
        if ($request->has('start_year') && $request->has('end_year') &&
            $request->start_year !== null && $request->end_year !== null) {
            if ((int)$request->end_year <= (int)$request->start_year) {
                return response()->json([
                    'errors' => [
                        'end_year' => ['End year must be greater than start year']
                    ]
                ], 422);
            }
        }

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Log the request data for debugging
            Log::info('Creating curriculum with data:', [
                'name' => $request->name,
                'start_year' => $request->start_year,
                'end_year' => $request->end_year,
                'description' => $request->description
            ]);

            // Create curriculum with only the columns that definitely exist in the table
            $curriculum = new Curriculum();
            $curriculum->name = $request->name;
            $curriculum->start_year = $request->start_year !== null ? $request->start_year : null;
            $curriculum->end_year = $request->end_year !== null ? $request->end_year : null;
            $curriculum->description = $request->description;
            $curriculum->is_active = false;

            // Check which table exists and set appropriate columns
            $tableName = Schema::hasTable('curricula') ? 'curricula' : 'curriculums';
            Log::info('Using table: ' . $tableName);

            // Set status to Draft by default if the column exists
            if (Schema::hasColumn($tableName, 'status')) {
                $curriculum->status = 'Draft';
            }

            $curriculum->save();

            // Attach grades to curriculum if provided
            if ($request->has('grades') && is_array($request->grades)) {
                try {
                    $curriculum->grades()->attach($request->grades);
                } catch (\Exception $attachException) {
                    // If attaching with timestamps fails, try without timestamps
                    if (strpos($attachException->getMessage(), 'curriculum_grade.created_at') !== false) {
                        Log::info('Attaching grades without timestamps due to missing timestamp columns');
                        // Manually attach without timestamps
                        foreach ($request->grades as $gradeId) {
                            DB::table('curriculum_grade')->insert([
                                'curriculum_id' => $curriculum->id,
                                'grade_id' => $gradeId
                            ]);
                        }
                    } else {
                        // If it's a different error, rethrow it
                        throw $attachException;
                    }
                }
            }

            DB::commit();
            return response()->json($curriculum->load(['grades', 'subjects']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create curriculum: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create curriculum: ' . $e->getMessage()], 500);
        }
    }

    public function show(Curriculum $curriculum)
    {
        return response()->json($curriculum->load(['grades', 'subjects']));
    }

    public function update(Request $request, Curriculum $curriculum)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'start_year' => 'nullable|integer|min:2000',
            'end_year' => 'nullable|integer|min:2000',
            'description' => 'nullable|string',
            'grades' => 'nullable|array',
            'grades.*' => 'exists:grades,id'
        ]);

        // Custom validation for end_year > start_year
        if ($request->has('start_year') && $request->has('end_year') &&
            $request->start_year !== null && $request->end_year !== null) {
            if ((int)$request->end_year <= (int)$request->start_year) {
                return response()->json([
                    'errors' => [
                        'end_year' => ['End year must be greater than start year']
                    ]
                ], 422);
            }
        }

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $curriculum->update([
                'name' => $request->name,
                'start_year' => $request->start_year,
                'end_year' => $request->end_year,
                'description' => $request->description
            ]);

            // Sync grades if provided
            if ($request->has('grades') && is_array($request->grades)) {
                try {
                    $curriculum->grades()->sync($request->grades);
                } catch (\Exception $syncException) {
                    // If syncing with timestamps fails, try without timestamps
                    if (strpos($syncException->getMessage(), 'curriculum_grade.created_at') !== false) {
                        Log::info('Syncing grades without timestamps due to missing timestamp columns');
                        // Manually sync without timestamps
                        DB::table('curriculum_grade')->where('curriculum_id', $curriculum->id)->delete();
                        foreach ($request->grades as $gradeId) {
                            DB::table('curriculum_grade')->insert([
                                'curriculum_id' => $curriculum->id,
                                'grade_id' => $gradeId
                            ]);
                        }
                    } else {
                        // If it's a different error, rethrow it
                        throw $syncException;
                    }
                }
            }

            DB::commit();
            return response()->json($curriculum->load(['grades', 'subjects']));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update curriculum: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update curriculum: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Curriculum $curriculum)
    {
        try {
            $curriculum->delete();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete curriculum'], 500);
        }
    }

    public function activate(Curriculum $curriculum)
    {
        try {
            $curriculum->activate();
            return response()->json($curriculum);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to activate curriculum'], 500);
        }
    }

    public function deactivate(Curriculum $curriculum)
    {
        try {
            $curriculum->deactivate();
            return response()->json($curriculum);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to deactivate curriculum'], 500);
        }
    }

    public function getActive()
    {
        $curriculum = Curriculum::active()->first();
        return response()->json($curriculum);
    }

    public function addSubject(Request $request, Curriculum $curriculum)
    {
        $validator = Validator::make($request->all(), [
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id',
            'units' => 'required|integer|min:1',
            'hours_per_week' => 'required|integer|min:1',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Check if grade belongs to curriculum
            if (!$curriculum->grades()->where('grades.id', $request->grade_id)->exists()) {
                return response()->json(['message' => 'Grade does not belong to this curriculum'], 422);
            }

            // Attach subject with pivot data
            $curriculum->subjects()->attach($request->subject_id, [
                'grade_id' => $request->grade_id,
                'units' => $request->units,
                'hours_per_week' => $request->hours_per_week,
                'description' => $request->description
            ]);

            DB::commit();
            return response()->json($curriculum->load(['grades', 'subjects']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add subject to curriculum'], 500);
        }
    }

    public function removeSubject(Request $request, Curriculum $curriculum)
    {
        $validator = Validator::make($request->all(), [
            'grade_id' => 'required|exists:grades,id',
            'subject_id' => 'required|exists:subjects,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Detach subject
            $curriculum->subjects()->detach($request->subject_id);

            DB::commit();
            return response()->json($curriculum->load(['grades', 'subjects']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to remove subject from curriculum'], 500);
        }
    }

    public function getSubjectsByGrade(Curriculum $curriculum, Grade $grade)
    {
        $subjects = $curriculum->getSubjectsByGrade($grade->id);
        return response()->json($subjects);
    }

    public function addSubjectToGrade(Request $request)
    {
        try {
        $validated = $request->validate([
                'curriculum_id' => 'required|exists:curricula,id',
                'grade_id' => 'required|exists:grades,id',
                'subject_id' => 'required|exists:subjects,id',
                'units' => 'nullable|integer|min:1',
                'hours_per_week' => 'nullable|integer|min:1',
                'description' => 'nullable|string'
            ]);

            // Check if the curriculum-grade relationship exists
            $curriculumGrade = DB::table('curriculum_grade')
                ->where('curriculum_id', $validated['curriculum_id'])
                ->where('grade_id', $validated['grade_id'])
                ->first();

            if (!$curriculumGrade) {
                return response()->json([
                    'message' => 'Grade is not associated with this curriculum'
                ], 400);
            }

            // Create or update the curriculum-grade-subject relationship
            DB::table('curriculum_grade_subject')->updateOrInsert(
                [
                    'curriculum_id' => $validated['curriculum_id'],
                    'grade_id' => $validated['grade_id'],
                    'subject_id' => $validated['subject_id']
                ],
                [
                    'units' => $validated['units'] ?? 1,
                    'hours_per_week' => $validated['hours_per_week'] ?? 1,
                    'description' => $validated['description'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now()
                ]
            );

            return response()->json([
                'message' => 'Subject successfully added to curriculum grade'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to add subject to curriculum grade',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

