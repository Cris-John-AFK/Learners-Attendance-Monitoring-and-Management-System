<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\Grade;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CurriculumController extends Controller
{
    public function index()
    {
        try {
            // Log database connection details
            Log::info('Attempting to fetch curricula');
            Log::info('DB Connection: ' . config('database.default'));
            Log::info('DB Name: ' . config('database.connections.' . config('database.default') . '.database'));

            // Check if table exists
            $tableExists = Schema::hasTable('curricula');
            Log::info('Curricula table exists: ' . ($tableExists ? 'Yes' : 'No'));

            // Try to fetch data with yearRange accessor
            $curricula = Curriculum::orderBy('created_at', 'desc')->get();

            // Transform the data to include yearRange
            $curricula = $curricula->map(function ($curriculum) {
                return [
                    'id' => $curriculum->id,
                    'name' => $curriculum->name,
                    'yearRange' => $curriculum->yearRange,
                    'description' => $curriculum->description,
                    'is_active' => $curriculum->is_active,
                    'created_at' => $curriculum->created_at,
                    'updated_at' => $curriculum->updated_at
                ];
            });

            Log::info('Query successful, found: ' . $curricula->count() . ' records');

            return response()->json($curricula);
        } catch (\Exception $e) {
            Log::error('Error in CurriculumController@index: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'message' => 'Failed to fetch curricula',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            Log::info('Attempting to create curriculum with data:', $request->all());

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'yearRange.start' => 'required|string|size:4',
                'yearRange.end' => 'required|string|size:4',
                'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean'
            ]);

            $curriculum = new Curriculum();
            $curriculum->name = $validated['name'];
            $curriculum->start_year = $validated['yearRange']['start'];
            $curriculum->end_year = $validated['yearRange']['end'];
            $curriculum->description = $validated['description'] ?? null;
            $curriculum->is_active = $validated['is_active'] ?? true;

            Log::info('Saving curriculum with data:', [
                'name' => $curriculum->name,
                'start_year' => $curriculum->start_year,
                'end_year' => $curriculum->end_year,
                'description' => $curriculum->description,
                'is_active' => $curriculum->is_active
            ]);

            $curriculum->save();

            Log::info('Curriculum created successfully with ID: ' . $curriculum->id);
            return response()->json($curriculum, 201);

        } catch (\Exception $e) {
            Log::error('Error creating curriculum: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'message' => 'Failed to create curriculum',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    public function show($id)
    {
        $curriculum = Curriculum::findOrFail($id);
        return response()->json($curriculum);
    }

    public function update(Request $request, $id)
    {
        $curriculum = Curriculum::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'yearRange.start' => 'sometimes|string|size:4',
            'yearRange.end' => 'sometimes|string|size:4',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|in:Active,Archived,Planned',
            'is_active' => 'sometimes|boolean'
        ]);

        if (isset($validated['name'])) {
            $curriculum->name = $validated['name'];
        }

        if (isset($validated['yearRange']['start'])) {
            $curriculum->start_year = $validated['yearRange']['start'];
        }

        if (isset($validated['yearRange']['end'])) {
            $curriculum->end_year = $validated['yearRange']['end'];
        }

        if (array_key_exists('description', $validated)) {
            $curriculum->description = $validated['description'];
        }

        if (isset($validated['status'])) {
            $curriculum->status = $validated['status'];
        }

        if (isset($validated['is_active'])) {
            $curriculum->is_active = $validated['is_active'];
        }

        $curriculum->save();

        return response()->json($curriculum);
    }

    public function destroy($id)
    {
        $curriculum = Curriculum::findOrFail($id);
        $curriculum->delete();

        return response()->json(null, 204);
    }

    public function archive($id)
    {
        $curriculum = Curriculum::findOrFail($id);
        $curriculum->status = 'Archived';
        $curriculum->save();

        return response()->json($curriculum);
    }

    public function activate($id)
    {
        DB::transaction(function () use ($id) {
            // Set all curriculums to inactive
            Curriculum::where('is_active', true)->update(['is_active' => false]);

            // Set this curriculum to active
            $curriculum = Curriculum::findOrFail($id);
            $curriculum->is_active = true;
            $curriculum->save();
        });

        return response()->json(Curriculum::findOrFail($id));
    }

    public function getGrades($id)
    {
        $curriculum = Curriculum::findOrFail($id);
        return response()->json($curriculum->grades);
    }

    public function addGrade(Request $request, $id)
    {
        $curriculum = Curriculum::findOrFail($id);

        $validated = $request->validate([
            'grade_id' => 'required|exists:grades,id'
        ]);

        $curriculum->grades()->syncWithoutDetaching([$validated['grade_id']]);

        return response()->json(['success' => true]);
    }

    public function removeGrade($curriculumId, $gradeId)
    {
        $curriculum = Curriculum::findOrFail($curriculumId);
        $curriculum->grades()->detach($gradeId);

        return response()->json(['success' => true]);
    }

    /**
     * Get sections for a specific grade in a curriculum
     */
    public function getSections($curriculumId, $gradeId)
    {
        $curriculum = Curriculum::findOrFail($curriculumId);
        $grade = $curriculum->grades()->findOrFail($gradeId);

        $sections = Section::where('grade_id', $gradeId)
            ->where('curriculum_id', $curriculumId)
            ->get();

        return response()->json($sections);
    }

    /**
     * Add a section to a grade in a curriculum
     */
    public function addSection(Request $request, $curriculumId, $gradeId)
    {
        $curriculum = Curriculum::findOrFail($curriculumId);
        $grade = $curriculum->grades()->findOrFail($gradeId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        // Check if section name is unique for this grade and curriculum
        if (!Section::isNameUniqueInGrade($validated['name'], $gradeId)) {
            return response()->json([
                'message' => 'Section name already exists in this grade'
            ], 422);
        }

        $section = new Section([
            'name' => $validated['name'],
            'grade_id' => $gradeId,
            'curriculum_id' => $curriculumId,
            'capacity' => $validated['capacity'] ?? 40,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true
        ]);

        $section->save();

        return response()->json($section, 201);
    }

    /**
     * Remove a section from a grade in a curriculum
     */
    public function removeSection($curriculumId, $gradeId, $sectionId)
    {
        $curriculum = Curriculum::findOrFail($curriculumId);
        $grade = $curriculum->grades()->findOrFail($gradeId);

        $section = Section::where('id', $sectionId)
            ->where('grade_id', $gradeId)
            ->where('curriculum_id', $curriculumId)
            ->firstOrFail();

        $section->delete();

        return response()->json(null, 204);
    }
}
