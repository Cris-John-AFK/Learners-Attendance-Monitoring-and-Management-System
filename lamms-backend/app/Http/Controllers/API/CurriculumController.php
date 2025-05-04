<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;

use App\Models\Curriculum; // Added this line
use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\Validator;

class CurriculumController extends Controller
{
    // Only one curriculum exists. Always return the first (or create if not exists)
    public function index(Request $request)
    {
        try {
            $curriculum = Curriculum::with(['grades', 'subjects'])->first();
            if (!$curriculum) {
                // Optionally, auto-create the single curriculum if not present
                $curriculum = Curriculum::create([
                    'name' => 'Default Curriculum',
                    'start_year' => now()->year,
                    'end_year' => now()->year + 1,
                    'is_active' => true,
                    'status' => 'Active',
                ]);
            }
            return response()->json($curriculum);
        } catch (\Exception $e) {
            Log::error('Error in curriculum index: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to retrieve curriculum: ' . $e->getMessage()], 500);
        }
    }

    // Disallow creating new curricula. Only one curriculum is allowed.
    public function store(Request $request)
    {
        return response()->json(['message' => 'Only one curriculum is allowed.'], 403);
    }

    public function show(Curriculum $curriculum)
    {
        return response()->json($curriculum->load(['grades', 'subjects']));
    }

    public function update(Request $request, Curriculum $curriculum)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255', // Changed 'required' to 'sometimes|required'
            'start_year' => 'nullable|integer|min:2000',
            'end_year' => 'nullable|integer|min:2000',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:Draft,Published,Archived',
            'is_active' => 'nullable|boolean',
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

            // Deactivate other curricula if activating this one
            if ($request->is_active) {
                Curriculum::where('id', '!=', $curriculum->id)
                    ->update(['is_active' => false]);
            }

            $curriculum->update([
                'name' => $request->name,
                'start_year' => $request->start_year,
                'end_year' => $request->end_year,
                'description' => $request->description,
                'status' => $request->status ?? $curriculum->status,
                'is_active' => $request->has('is_active') ? $request->is_active : $curriculum->is_active
            ]);

            // Sync grades if provided
            if ($request->has('grades') && is_array($request->grades)) {
                // Sync grades using the standard method (timestamps handled by model/DB)
                $curriculum->grades()->sync($request->grades);
            }

            DB::commit();
            return response()->json($curriculum->load(['grades', 'subjects']));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update curriculum: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update curriculum: ' . $e->getMessage()], 500);
        }
    }

    // No activation needed; only one curriculum is always active.
    public function activate(Curriculum $curriculum)
    {
        return response()->json(['message' => 'Activation is not needed. The single curriculum is always active.'], 200);
    }

    // No deactivation needed; only one curriculum is always active.
    public function deactivate(Curriculum $curriculum)
    {
        return response()->json(['message' => 'Deactivation is not needed. The single curriculum is always active.'], 200);
    }

    // Always return the single curriculum
    public function getActive()
    {
        $curriculum = Curriculum::with(['grades', 'subjects'])->first();
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

            // Detach subject for the specific grade
            $curriculum->subjects()
                       ->wherePivot('grade_id', $request->grade_id) // Add condition for grade_id
                       ->detach($request->subject_id);

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
                 'curriculum_id' => 'required|exists:curricula,id', // Reverted back to curricula based on store method check
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

