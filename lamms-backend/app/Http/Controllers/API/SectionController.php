<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        $sections = Section::with('grade')
            ->where('grade_id', $gradeId)
            ->get();
        return response()->json($sections);
    }

    /**
     * Get active sections
     */
    public function getActiveSections()
    {
        $sections = Section::with('grade')
            ->active()
            ->get();
        return response()->json($sections);
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'grade_id' => 'required_without:grade|exists:grades,id',
            'grade' => 'required_without:grade_id',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // If grade is provided as an object, extract the ID
        $gradeId = $request->grade_id;
        if (!$gradeId && isset($request->grade['id'])) {
            $gradeId = $request->grade['id'];
        }

        // Check if section name is unique within the grade
        if (!Section::isNameUniqueInGrade($request->name, $gradeId)) {
            return response()->json([
                'errors' => [
                    'name' => ['Section name already exists in this grade.']
                ]
            ], 422);
        }

        $section = new Section();
        $section->name = $request->name;
        $section->grade_id = $gradeId;
        $section->description = $request->description;
        $section->save();

        return response()->json($section->load('grade'), 201);
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
            'grade_id' => 'required_without:grade|exists:grades,id',
            'grade' => 'required_without:grade_id',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // If grade is provided as an object, extract the ID
        $gradeId = $request->grade_id;
        if (!$gradeId && isset($request->grade['id'])) {
            $gradeId = $request->grade['id'];
        }

        // Check if section name is unique within the grade (excluding current section)
        if (!Section::isNameUniqueInGrade($request->name, $gradeId, $section->id)) {
            return response()->json([
                'errors' => [
                    'name' => ['Section name already exists in this grade.']
                ]
            ], 422);
        }

        $section->name = $request->name;
        $section->grade_id = $gradeId;
        $section->description = $request->description;
        $section->save();

        return response()->json($section->load('grade'));
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
}
