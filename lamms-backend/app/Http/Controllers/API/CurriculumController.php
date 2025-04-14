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

            if (!$tableExists) {
                return response()->json([
                    'message' => 'Table curricula does not exist',
                    'error' => 'Database schema not properly set up'
                ], 500);
            }

            // Try to fetch data with yearRange accessor
            $curricula = Curriculum::query();

            // Add order by if timestamps are enabled
            if (Schema::hasColumn('curricula', 'created_at')) {
                $curricula->orderBy('created_at', 'desc');
            }

            $curricula = $curricula->get();

            // Transform the data to include yearRange
            $curricula = $curricula->map(function ($curriculum) {
                $data = [
                    'id' => $curriculum->id,
                    'name' => $curriculum->name,
                    'yearRange' => $curriculum->yearRange,
                    'is_active' => $curriculum->is_active
                ];

                // Add optional fields if they exist
                if (isset($curriculum->description)) {
                    $data['description'] = $curriculum->description;
                }

                if (isset($curriculum->status)) {
                    $data['status'] = $curriculum->status;
                }

                if (isset($curriculum->created_at)) {
                    $data['created_at'] = $curriculum->created_at;
                    $data['updated_at'] = $curriculum->updated_at;
                }

                return $data;
            });

            Log::info('Query successful, found: ' . $curricula->count() . ' records');

            return response()->json($curricula);
        } catch (\Exception $e) {
            Log::error('Error in CurriculumController@index: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'message' => 'Failed to fetch curricula',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            Log::info('Attempting to create curriculum with data:', $request->all());

            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'yearRange.start' => 'required|string|size:4',
                'yearRange.end' => 'required|string|size:4',
                'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean'
            ]);

            // Check if a curriculum with the same year range already exists
            $startYear = $validated['yearRange']['start'];
            $endYear = $validated['yearRange']['end'];
            $existingCurriculum = Curriculum::where('start_year', $startYear)
                ->where('end_year', $endYear)
                ->first();

            if ($existingCurriculum) {
                return response()->json([
                    'message' => 'A curriculum with this year range already exists',
                    'error' => 'Duplicate year range: ' . $startYear . '-' . $endYear
                ], 422);
            }

            $curriculum = new Curriculum();
            // Generate a consistent name format using the year range
            $curriculum->name = 'Curriculum ' . $startYear . '-' . $endYear;
            $curriculum->start_year = $startYear;
            $curriculum->end_year = $endYear;
            $curriculum->description = $validated['description'] ?? null;
            $curriculum->is_active = $validated['is_active'] ?? false;
            $curriculum->status = 'Draft';

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
            'status' => 'sometimes|string|in:Active,Draft,Archived',
            'is_active' => 'sometimes|boolean'
        ]);

        // If updating the year range, check for duplicates
        if (isset($validated['yearRange']['start']) && isset($validated['yearRange']['end'])) {
            $startYear = $validated['yearRange']['start'];
            $endYear = $validated['yearRange']['end'];

            // Check if another curriculum with the same year range exists
            $existingCurriculum = Curriculum::where('start_year', $startYear)
                ->where('end_year', $endYear)
                ->where('id', '!=', $id) // Exclude the current curriculum
                ->first();

            if ($existingCurriculum) {
                return response()->json([
                    'message' => 'Another curriculum with this year range already exists',
                    'error' => 'Duplicate year range: ' . $startYear . '-' . $endYear
                ], 422);
            }
        }

        if (isset($validated['name'])) {
            $curriculum->name = $validated['name'];
        }

        if (isset($validated['yearRange']['start'])) {
            $curriculum->start_year = $validated['yearRange']['start'];
        }

        if (isset($validated['yearRange']['end'])) {
            $curriculum->end_year = $validated['yearRange']['end'];
        }

        // Update the name if both start and end years are being updated
        if (isset($validated['yearRange']['start']) && isset($validated['yearRange']['end'])) {
            $curriculum->name = 'Curriculum ' . $validated['yearRange']['start'] . '-' . $validated['yearRange']['end'];
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
            // Set all curriculums to inactive and Draft status
            Curriculum::where('is_active', true)
                ->update([
                    'is_active' => false,
                    'status' => 'Draft'
                ]);

            // Set this curriculum to active with Active status
            $curriculum = Curriculum::findOrFail($id);
            $curriculum->is_active = true;
            $curriculum->status = 'Active';
            $curriculum->save();
        });

        return response()->json(Curriculum::findOrFail($id));
    }

    public function getGrades($id)
    {
        try {
            // Log attempt to fetch grades
            Log::info('Attempting to fetch grades for curriculum ID: ' . $id);

            $curriculum = Curriculum::findOrFail($id);

            // Check if curriculum exists
            if (!$curriculum) {
                Log::warning('Curriculum not found with ID: ' . $id);
                return response()->json(['error' => 'Curriculum not found'], 404);
            }

            // Get grades with additional logging
            Log::info('Found curriculum, retrieving grades');
            $grades = $curriculum->grades;

            // Log success
            Log::info('Successfully retrieved ' . count($grades) . ' grades for curriculum ID: ' . $id);

            return response()->json($grades);
        } catch (\Exception $e) {
            // Log the detailed error
            Log::error('Error fetching grades for curriculum: ' . $e->getMessage());
            Log::error('Error trace: ' . $e->getTraceAsString());

            // Return a more informative error response
            return response()->json([
                'error' => 'Failed to fetch grades for curriculum',
                'message' => $e->getMessage()
            ], 500);
        }
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
