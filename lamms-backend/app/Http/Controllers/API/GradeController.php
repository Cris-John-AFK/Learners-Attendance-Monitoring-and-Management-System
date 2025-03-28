<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GradeController extends Controller
{
    /**
     * Display a listing of all grades.
     */
    public function index()
    {
        $grades = Grade::orderBy('display_order')->get();
        return response()->json($grades);
    }

    /**
     * Store a newly created grade.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:10|unique:grades',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'display_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $grade = Grade::create($request->all());
        return response()->json($grade, 201);
    }

    /**
     * Display the specified grade.
     */
    public function show(string $id)
    {
        $grade = Grade::findOrFail($id);
        return response()->json($grade);
    }

    /**
     * Update the specified grade.
     */
    public function update(Request $request, string $id)
    {
        $grade = Grade::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|max:10|unique:grades,code,' . $id,
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'display_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $grade->update($request->all());
        return response()->json($grade);
    }

    /**
     * Remove the specified grade.
     */
    public function destroy(string $id)
    {
        $grade = Grade::findOrFail($id);

        // Future implementation: Check for related data before deletion
        // For now, simply delete the grade

        $grade->delete();
        return response()->json(null, 204);
    }

    /**
     * Get active grades.
     */
    public function getActiveGrades()
    {
        $grades = Grade::where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return response()->json($grades);
    }

    /**
     * Toggle grade active status.
     */
    public function toggleStatus(string $id)
    {
        $grade = Grade::findOrFail($id);
        $grade->is_active = !$grade->is_active;
        $grade->save();

        return response()->json($grade);
    }
}
