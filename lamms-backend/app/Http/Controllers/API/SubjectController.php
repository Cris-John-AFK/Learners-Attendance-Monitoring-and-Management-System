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
            // Test database connection
            DB::connection()->getPdo();
            Log::info('Database connection successful');

            // Check if the subjects table exists
            if (!Schema::hasTable('subjects')) {
                Log::error('Subjects table does not exist');
                return response()->json(['error' => 'Subjects table does not exist'], 500);
            }

            // Try to get subjects
            $subjects = Subject::all();

            // Log the number of subjects found
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
            'name' => 'required|string|max:255',
            'grade' => 'required|string|max:255',
            'description' => 'nullable|string',
            'credits' => 'nullable|integer|min:1|max:10'
        ]);

        // Generate ID if not provided (similar to the frontend logic)
        if (!$request->id) {
            $prefix = Str::upper(Str::substr($request->name, 0, 4));
            $gradeNum = preg_replace('/\D/', '', $request->grade);
            $id = $prefix . ($gradeNum ?: '');

            // If there's a duplicate ID, append a random character
            if (Subject::find($id)) {
                $id .= chr(rand(65, 90)); // Random uppercase letter A-Z
            }

            $request->merge(['id' => $id]);
        }

        return Subject::create($request->all());
    }

    /**
     * Get a specific subject
     */
    public function show(Subject $subject)
    {
        return $subject;
    }

    /**
     * Update a subject
     */
    public function update(Request $request, Subject $subject)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'grade' => 'required|string|max:255',
            'description' => 'nullable|string',
            'credits' => 'nullable|integer|min:1|max:10'
        ]);

        $subject->update($request->all());
        return $subject;
    }

    /**
     * Delete a subject
     */
    public function destroy(Subject $subject)
    {
        $subject->delete();
        return response()->json(['message' => 'Subject deleted successfully']);
    }

    /**
     * Get subjects by grade
     */
    public function byGrade($grade)
    {
        return Subject::where('grade', $grade)->get();
    }

    /**
     * Get unique subjects (one per name)
     */
    public function uniqueSubjects()
    {
        // Get all subjects ordered by name
        $subjects = Subject::orderBy('name')->get();

        // Filter to get only unique names
        $uniqueSubjects = [];
        $subjectNames = [];

        foreach ($subjects as $subject) {
            if (!in_array($subject->name, $subjectNames)) {
                $subjectNames[] = $subject->name;
                $uniqueSubjects[] = $subject;
            }
        }

        return $uniqueSubjects;
    }
}
