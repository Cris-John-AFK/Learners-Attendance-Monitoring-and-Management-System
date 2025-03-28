<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubjectController extends Controller
{
    /**
     * Get all subjects with their grades
     */
    public function index()
    {
        try {
            DB::connection()->getPdo();
            Log::info('Database connection successful');

            if (!Schema::hasTable('subjects')) {
                Log::error('Subjects table does not exist');
                return response()->json(['error' => 'Subjects table does not exist'], 500);
            }

            $subjects = Subject::with('grades')->get();
            Log::info('Found ' . $subjects->count() . ' subjects');

            return response()->json($subjects);
        } catch (\PDOException $e) {
            Log::error('Database connection error: ' . $e->getMessage());
            return response()->json(['error' => 'Database connection error: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error('Error in SubjectController@index: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a new subject with grade assignments
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:subjects',
            'description' => 'nullable|string',
            'credits' => 'nullable|integer|min:1|max:10',
            'grade_ids' => 'required|array',
            'grade_ids.*' => 'exists:grades,id',
            'is_active' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            // Generate ID if not provided
            if (!$request->id) {
                $prefix = Str::upper(Str::substr($request->name, 0, 4));
                $id = $prefix;

                // If there's a duplicate ID, append a random character
                while (Subject::find($id)) {
                    $id = $prefix . chr(rand(65, 90)); // Random uppercase letter A-Z
                }

                $request->merge(['id' => $id]);
            }

            // Create the subject
            $subject = Subject::create($request->except('grade_ids'));

            // Attach grades
            $subject->grades()->attach($request->grade_ids);

            DB::commit();

            // Return the subject with its grades
            return Subject::with('grades')->find($subject->id);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating subject: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get a specific subject with its grades
     */
    public function show(Subject $subject)
    {
        return $subject->load('grades');
    }

    /**
     * Update a subject and its grade assignments
     */
    public function update(Request $request, Subject $subject)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:subjects,name,' . $subject->id,
            'description' => 'nullable|string',
            'credits' => 'nullable|integer|min:1|max:10',
            'grade_ids' => 'required|array',
            'grade_ids.*' => 'exists:grades,id',
            'is_active' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            $subject->update($request->except('grade_ids'));
            $subject->grades()->sync($request->grade_ids);

            DB::commit();

            return $subject->load('grades');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating subject: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a subject
     */
    public function destroy(Subject $subject)
    {
        try {
            DB::beginTransaction();

            // The grade relationships will be automatically deleted due to cascade
            $subject->delete();

            DB::commit();
            return response()->json(['message' => 'Subject deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting subject: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get subjects by grade
     */
    public function byGrade($gradeId)
    {
        $grade = Grade::findOrFail($gradeId);
        return $grade->subjects;
    }

    /**
     * Get unique subjects (one per name)
     */
    public function uniqueSubjects()
    {
        return Subject::select('name')
            ->distinct()
            ->orderBy('name')
            ->get()
            ->map(function ($subject) {
                return [
                    'name' => $subject->name,
                    'subjects' => Subject::where('name', $subject->name)
                        ->with('grades')
                        ->get()
                ];
            });
    }

    /**
     * Get all available grades for subject assignment
     */
    public function availableGrades()
    {
        return Grade::where('is_active', true)
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Toggle subject active status
     */
    public function toggleStatus(Subject $subject)
    {
        $subject->is_active = !$subject->is_active;
        $subject->save();
        return $subject->load('grades');
    }
}
