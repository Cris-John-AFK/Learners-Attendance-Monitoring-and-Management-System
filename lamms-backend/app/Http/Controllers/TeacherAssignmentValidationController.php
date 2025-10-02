<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeacherAssignmentValidationController extends Controller
{
    /**
     * Get teacher's current assignments for validation
     */
    public function getTeacherAssignments($teacherId)
    {
        try {
            // First, check if teacher is a homeroom teacher for any section
            // Check what columns exist in sections table
            $homeroomSections = DB::table('sections')
                ->where('homeroom_teacher_id', $teacherId)
                ->select('id', 'name', 'curriculum_grade_id')
                ->get();
            
            // Get grade information by joining with curriculum_grade and grades
            foreach ($homeroomSections as $section) {
                try {
                    $gradeInfo = DB::table('curriculum_grade as cg')
                        ->join('grades as g', 'cg.grade_id', '=', 'g.id')
                        ->where('cg.id', $section->curriculum_grade_id)
                        ->select('g.name as grade_name')
                        ->first();
                    
                    $section->grade_level = $gradeInfo ? $gradeInfo->grade_name : 'Unknown';
                } catch (\Exception $e) {
                    $section->grade_level = 'Unknown';
                }
            }

            $assignments = [];

            // Add homeroom assignments
            foreach ($homeroomSections as $section) {
                $assignments[] = [
                    'id' => 'homeroom_' . $section->id,
                    'teacher_id' => $teacherId,
                    'section_id' => $section->id,
                    'subject_id' => null,
                    'is_primary' => true,
                    'subject_name' => 'Homeroom',
                    'section' => [
                        'id' => $section->id,
                        'name' => $section->name,
                        'grade_level' => $section->grade_level
                    ]
                ];
            }

            // Try to get subject assignments from teacher_section_subject table if it exists
            try {
                $subjectAssignments = DB::table('teacher_section_subject as tss')
                    ->join('sections as s', 'tss.section_id', '=', 's.id')
                    ->join('subjects as sub', 'tss.subject_id', '=', 'sub.id')
                    ->leftJoin('curriculum_grade as cg', 's.curriculum_grade_id', '=', 'cg.id')
                    ->leftJoin('grades as g', 'cg.grade_id', '=', 'g.id')
                    ->where('tss.teacher_id', $teacherId)
                    ->select([
                        'tss.id',
                        'tss.teacher_id',
                        'tss.section_id', 
                        'tss.subject_id',
                        's.name as section_name',
                        'g.name as grade_level',
                        'sub.name as subject_name'
                    ])
                    ->get();

                foreach ($subjectAssignments as $assignment) {
                    $assignments[] = [
                        'id' => $assignment->id,
                        'teacher_id' => $assignment->teacher_id,
                        'section_id' => $assignment->section_id,
                        'subject_id' => $assignment->subject_id,
                        'is_primary' => false,
                        'subject_name' => $assignment->subject_name,
                        'section' => [
                            'id' => $assignment->section_id,
                            'name' => $assignment->section_name,
                            'grade_level' => $assignment->grade_level
                        ]
                    ];
                }
            } catch (\Exception $subjectError) {
                // If teacher_section_subject table doesn't exist or has issues, just continue with homeroom data
                Log::info("Could not fetch subject assignments for teacher {$teacherId}: " . $subjectError->getMessage());
            }

            Log::info("Teacher {$teacherId} assignments found:", [
                'homeroom_count' => count($homeroomSections),
                'subject_count' => isset($subjectAssignments) ? count($subjectAssignments) : 0,
                'total_assignments' => count($assignments)
            ]);

            return response()->json($assignments);

        } catch (\Exception $e) {
            Log::error("Error fetching teacher assignments for teacher {$teacherId}: " . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to fetch teacher assignments',
                'message' => $e->getMessage(),
                'assignments' => [] // Return empty array as fallback
            ], 200); // Return 200 instead of 500 to prevent frontend errors
        }
    }

    /**
     * Validate if a teacher can be assigned to a specific grade level as homeroom
     */
    public function validateHomeroomAssignment(Request $request)
    {
        $teacherId = $request->input('teacher_id');
        $gradeLevel = $request->input('grade_level');
        $role = $request->input('role', 'primary');

        try {
            // If not a primary/homeroom role, allow assignment
            if ($role !== 'primary') {
                return response()->json([
                    'valid' => true,
                    'message' => 'Subject teacher assignments are allowed across all grades.'
                ]);
            }

            // Get teacher's current assignments
            $assignments = DB::table('teacher_section_subject as tss')
                ->join('sections as s', 'tss.section_id', '=', 's.id')
                ->where('tss.teacher_id', $teacherId)
                ->select('s.grade_level')
                ->distinct()
                ->pluck('grade_level')
                ->toArray();

            // If no current assignments, allow any grade
            if (empty($assignments)) {
                return response()->json([
                    'valid' => true,
                    'message' => 'New teacher can be assigned to any grade level.'
                ]);
            }

            // Define grade categories
            $k3Grades = ['Kinder', 'Grade 1', 'Grade 2', 'Grade 3'];
            $grade46Grades = ['Grade 4', 'Grade 5', 'Grade 6'];

            // Check what categories the teacher currently teaches
            $teachesK3 = !empty(array_intersect($assignments, $k3Grades));
            $teachesGrade46 = !empty(array_intersect($assignments, $grade46Grades));

            // Determine if the requested grade is compatible
            $requestedIsK3 = in_array($gradeLevel, $k3Grades);
            $requestedIsGrade46 = in_array($gradeLevel, $grade46Grades);

            // Validation rules
            if ($teachesK3 && !$teachesGrade46 && $requestedIsGrade46) {
                return response()->json([
                    'valid' => false,
                    'message' => 'This teacher currently teaches K-3 students and cannot be assigned as homeroom teacher to Grade 4-6. K-3 teachers must teach all subjects to their homeroom students only.'
                ]);
            }

            if (!$teachesK3 && $teachesGrade46 && $requestedIsK3) {
                return response()->json([
                    'valid' => false,
                    'message' => 'This teacher is a Grade 4-6 departmental teacher and cannot be assigned as homeroom teacher to K-3. Grade 4-6 teachers are subject specialists who teach across multiple sections.'
                ]);
            }

            if ($teachesK3 && $teachesGrade46) {
                return response()->json([
                    'valid' => false,
                    'message' => 'This teacher has mixed K-3 and Grade 4-6 assignments, which violates school policy. Please review their current assignments first.'
                ]);
            }

            return response()->json([
                'valid' => true,
                'message' => 'Assignment is compatible with teacher\'s current grade level.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Unable to validate teacher assignment. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
