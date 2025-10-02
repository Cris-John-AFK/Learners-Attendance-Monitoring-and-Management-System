<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumGrade;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CurriculumGradeController extends Controller
{
    /**
     * Get all curriculum-grade relationships with grade info
     */
    public function index()
    {
        try {
            $curriculumGrades = DB::table('curriculum_grade as cg')
                ->join('grades as g', 'cg.grade_id', '=', 'g.id')
                ->select(
                    'cg.id',
                    'cg.curriculum_id',
                    'cg.grade_id',
                    'g.name',
                    'g.level',
                    'g.code'
                )
                ->get()
                ->map(function($cg) {
                    return [
                        'id' => $cg->id,
                        'curriculum_id' => $cg->curriculum_id,
                        'grade_id' => $cg->grade_id,
                        'grade' => [
                            'id' => $cg->grade_id,
                            'name' => $cg->name,
                            'level' => $cg->level,
                            'code' => $cg->code
                        ]
                    ];
                });

            return response()->json($curriculumGrades);
        } catch (\Exception $e) {
            Log::error('Error fetching curriculum grades: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load curriculum grades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific curriculum grade by curriculum and grade IDs
     */
    public function show($curriculumId, $gradeId)
    {
        try {
            $curriculumGrade = CurriculumGrade::where('curriculum_id', $curriculumId)
                ->where('grade_id', $gradeId)
                ->first();

            if (!$curriculumGrade) {
                return response()->json([
                    'message' => 'Curriculum grade relationship not found',
                    'curriculum_id' => $curriculumId,
                    'grade_id' => $gradeId
                ], 404);
            }

            return response()->json($curriculumGrade->load(['grade', 'curriculum']));
        } catch (\Exception $e) {
            Log::error('Error fetching curriculum grade: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load curriculum grade: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific curriculum grade relationship by query parameters
     */
    public function getByParams(Request $request)
    {
        try {
            $curriculumId = $request->input('curriculum_id');
            $gradeId = $request->input('grade_id');

            if (!$curriculumId || !$gradeId) {
                return response()->json([
                    'message' => 'Both curriculum_id and grade_id are required'
                ], 400);
            }

            $curriculumGrade = CurriculumGrade::where('curriculum_id', $curriculumId)
                ->where('grade_id', $gradeId)
                ->first();

            if (!$curriculumGrade) {
                // If relationship doesn't exist, create a temporary one
                return response()->json([
                    'id' => null,
                    'curriculum_id' => (int)$curriculumId,
                    'grade_id' => (int)$gradeId,
                    'display_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                    '_temporary' => true
                ]);
            }

            return response()->json($curriculumGrade);
        } catch (\Exception $e) {
            Log::error('Error fetching curriculum grade by params: ' . $e->getMessage());

            // Return a fallback object to prevent frontend errors
            return response()->json([
                'id' => null,
                'curriculum_id' => (int)$curriculumId ?? 0,
                'grade_id' => (int)$gradeId ?? 0,
                'display_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
                '_fallback' => true
            ]);
        }
    }

    /**
     * Get relationship between curriculum and grade
     */
    public function relationship($curriculumId, $gradeId)
    {
        try {
            // Find the relationship
            $curriculumGrade = CurriculumGrade::where('curriculum_id', $curriculumId)
                ->where('grade_id', $gradeId)
                ->first();

            if (!$curriculumGrade) {
                // Create a temporary response for frontend compatibility
                return response()->json([
                    'id' => null,
                    'curriculum_id' => (int)$curriculumId,
                    'grade_id' => (int)$gradeId,
                    'display_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                    '_temporary' => true
                ]);
            }

            return response()->json($curriculumGrade);
        } catch (\Exception $e) {
            Log::error('Error fetching curriculum grade relationship: ' . $e->getMessage());

            // Return a fallback object
            return response()->json([
                'id' => null,
                'curriculum_id' => (int)$curriculumId,
                'grade_id' => (int)$gradeId,
                'display_order' => 0,
                '_fallback' => true
            ]);
        }
    }
}
