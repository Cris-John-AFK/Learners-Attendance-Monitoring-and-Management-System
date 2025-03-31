<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubjectController extends Controller
{
    /**
     * Get all subjects
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

            $subjects = Subject::all();

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
     * Create a new subject
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:subjects',
            'description' => 'nullable|string',
            'credits' => 'nullable|integer|min:1|max:10',
            'is_active' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            // Generate a unique code based on subject name
            $baseName = Str::upper(Str::substr($request->name, 0, 3));
            $code = $baseName;
            $counter = 1;

            // Keep trying until we find a unique code
            while (Subject::where('code', $code)->exists()) {
                $code = $baseName . str_pad($counter, 2, '0', STR_PAD_LEFT);
                $counter++;
            }

            // Create the subject with the generated code
            $subject = Subject::create(array_merge(
                $request->all(),
                ['code' => $code]
            ));

            DB::commit();

            // Return the created subject
            return response()->json($subject, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating subject: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function getHomeroomAssignments()
    {
        try {
            // Find homeroom subject
            $homeroom = Subject::where('name', 'Homeroom')->first();

            if (!$homeroom) {
                return response()->json([]);
            }
            // Get all assignments for this subject
            $assignments = \App\Models\TeacherSectionSubject::where('subject_id', $homeroom->id)
                ->with(['teacher', 'section'])
                ->get();

            return response()->json($assignments);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error fetching homeroom assignments: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get a specific subject
     */
    public function show(Subject $subject)
    {
        return response()->json($subject);
    }

    /**
     * Update a subject
     */
    public function update(Request $request, Subject $subject)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:subjects,name,' . $subject->id,
            'description' => 'nullable|string',
            'credits' => 'nullable|integer|min:1|max:10',
            'is_active' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            // Update the subject without modifying the code
            $subject->update($request->except(['code']));

            DB::commit();

            return response()->json($subject);
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
                    'subjects' => Subject::where('name', $subject->name)->get()
                ];
            });
    }

    /**
     * Toggle subject active status
     */
    public function toggleStatus(Subject $subject)
    {
        $subject->is_active = !$subject->is_active;
        $subject->save();
        return response()->json($subject);
    }
}
