<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Section;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EnrollmentController extends Controller
{
    /**
     * Display a listing of enrolled students.
     */
    public function index()
    {
        try {
            $students = Student::with(['sections' => function($query) {
                $query->wherePivot('is_active', true);
            }])->get();

            $formattedStudents = $students->map(function ($student) {
                $currentSection = $student->sections->first();
                
                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'firstName' => $student->firstName,
                    'lastName' => $student->lastName,
                    'middleName' => $student->middleName,
                    'email' => $student->email,
                    'gradeLevel' => $student->gradeLevel,
                    'enrollment_id' => $student->studentId,
                    'student_id' => $student->studentId,
                    'grade_level' => $student->gradeLevel,
                    'first_name' => $student->firstName,
                    'last_name' => $student->lastName,
                    'middle_name' => $student->middleName,
                    'email_address' => $student->email,
                    'birthdate' => $student->birthdate,
                    'enrollment_status' => 'Enrolled',
                    'enrollment_date' => $student->created_at->format('Y-m-d'),
                    'is_active' => $student->isActive ?? true,
                    'current_section_name' => $currentSection ? $currentSection->name : null,
                    'current_section_id' => $currentSection ? $currentSection->id : null,
                    'section' => $currentSection ? $currentSection->name : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedStudents
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching enrolled students: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch enrolled students'
            ], 500);
        }
    }

    /**
     * Store a newly created student enrollment.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'grade_level' => 'required|string|max:50',
                'lrn' => 'nullable|string|unique:students,lrn',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = Student::create([
                'name' => trim($request->first_name . ' ' . ($request->middle_name ?? '') . ' ' . $request->last_name),
                'firstName' => $request->first_name,
                'lastName' => $request->last_name,
                'middleName' => $request->middle_name,
                'extensionName' => $request->extension_name,
                'email' => $request->email_address,
                'gradeLevel' => $request->grade_level,
                'lrn' => $request->lrn,
                'studentId' => $request->enrollment_id ?? 'ENR' . time(),
                'birthdate' => $request->birthdate,
                'age' => $request->age,
                'sex' => $request->sex,
                'motherTongue' => $request->mother_tongue,
                'isActive' => true,
            ]);

            return response()->json([
                'success' => true,
                'data' => $student,
                'message' => 'Student enrolled successfully'
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating student enrollment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create student enrollment'
            ], 500);
        }
    }

    /**
     * Display the specified student.
     */
    public function show($id)
    {
        try {
            $student = Student::with(['sections' => function($query) {
                $query->wherePivot('is_active', true);
            }])->findOrFail($id);

            $currentSection = $student->sections->first();

            $formattedStudent = [
                'id' => $student->id,
                'name' => $student->name,
                'firstName' => $student->firstName,
                'lastName' => $student->lastName,
                'middleName' => $student->middleName,
                'email' => $student->email,
                'gradeLevel' => $student->gradeLevel,
                'enrollment_id' => $student->studentId,
                'current_section_name' => $currentSection ? $currentSection->name : null,
                'current_section_id' => $currentSection ? $currentSection->id : null,
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedStudent
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching student: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Student not found'
            ], 404);
        }
    }

    /**
     * Update the specified student.
     */
    public function update(Request $request, $id)
    {
        try {
            $student = Student::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'grade_level' => 'sometimes|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student->update($request->only([
                'firstName', 'lastName', 'middleName', 'email', 'gradeLevel'
            ]));

            return response()->json([
                'success' => true,
                'data' => $student,
                'message' => 'Student updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating student: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update student'
            ], 500);
        }
    }

    /**
     * Remove the specified student.
     */
    public function destroy($id)
    {
        try {
            $student = Student::findOrFail($id);
            $student->delete();

            return response()->json([
                'success' => true,
                'message' => 'Student deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting student: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete student'
            ], 500);
        }
    }

    /**
     * Get available sections for a student based on their grade level.
     */
    public function getAvailableSections($id)
    {
        try {
            $student = Student::findOrFail($id);
            
            if (!$student->gradeLevel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student grade level not set'
                ], 422);
            }

            Log::info('Finding sections for student grade level: ' . $student->gradeLevel);

            $studentGrade = $student->gradeLevel;
            $gradeVariations = [$studentGrade];
            
            // Handle different grade formats
            if (is_numeric($studentGrade)) {
                // If it's just a number like "2", add "Grade 2" format
                $gradeVariations[] = "Grade " . $studentGrade;
            } elseif (preg_match('/^Grade (\d+)$/', $studentGrade, $matches)) {
                // If it's "Grade 2" format, add just the number
                $gradeVariations[] = $matches[1];
            } elseif ($studentGrade === 'K') {
                // Handle Kinder variations
                $gradeVariations[] = 'Kinder 1';
                $gradeVariations[] = 'Kinder 2';
                $gradeVariations[] = 'Kinder';
            } elseif (in_array($studentGrade, ['Kinder', 'Kinder 1', 'Kinder 2'])) {
                // Handle specific Kinder levels
                $gradeVariations[] = 'K';
                $gradeVariations[] = 'Kinder';
                $gradeVariations[] = 'Kinder 1';
                $gradeVariations[] = 'Kinder 2';
            }
            
            Log::info('Searching for sections with grade variations: ' . json_encode($gradeVariations));
            
            // Get sections that match any of the grade variations
            $sections = \App\Models\Section::with(['curriculumGrade.grade'])
                ->whereHas('curriculumGrade.grade', function ($query) use ($gradeVariations) {
                    $query->whereIn('name', $gradeVariations);
                })
                ->where('is_active', true)
                ->get();

            Log::info('Found sections: ' . $sections->count());

            $availableSections = $sections->map(function ($section) {
                return [
                    'id' => $section->id,
                    'name' => $section->name,
                    'grade' => $section->curriculumGrade->grade->name ?? 'Unknown'
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $availableSections
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching available sections: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available sections'
            ], 500);
        }
    }

    /**
     * Assign a student to a section.
     */
    public function assignSection(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'section_id' => 'required|exists:sections,id',
                'school_year' => 'sometimes|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = Student::findOrFail($id);
            
            if (!$student->gradeLevel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student grade level not set'
                ], 422);
            }

            $sectionId = $request->section_id;
            $schoolYear = $request->school_year ?? '2025-2026';

            // Verify the section matches the student's grade level
            $section = \App\Models\Section::with(['curriculumGrade.grade'])->findOrFail($sectionId);
            $sectionGrade = $section->curriculumGrade->grade ?? null;
            
            if (!$sectionGrade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section grade level not found'
                ], 422);
            }
            
            // Normalize grade level to handle different formats for comparison
            $studentGrade = $student->gradeLevel;
            $sectionGradeName = $sectionGrade->name;
            
            $gradeMatch = false;
            
            // Direct match
            if ($studentGrade === $sectionGradeName) {
                $gradeMatch = true;
            }
            // Check if student grade is numeric and section is "Grade X" format
            elseif (is_numeric($studentGrade) && $sectionGradeName === "Grade " . $studentGrade) {
                $gradeMatch = true;
            }
            // Check if student grade is "Grade X" and section is numeric
            elseif (preg_match('/^Grade (\d+)$/', $studentGrade, $matches) && $sectionGradeName === $matches[1]) {
                $gradeMatch = true;
            }
            // Handle Kinder variations
            elseif ($studentGrade === 'K' && in_array($sectionGradeName, ['Kinder', 'Kinder 1', 'Kinder 2'])) {
                $gradeMatch = true;
            }
            elseif (in_array($studentGrade, ['Kinder', 'Kinder 1', 'Kinder 2']) && 
                    (in_array($sectionGradeName, ['K', 'Kinder', 'Kinder 1', 'Kinder 2']))) {
                $gradeMatch = true;
            }
            
            if (!$gradeMatch) {
                return response()->json([
                    'success' => false,
                    'message' => "Section grade level ({$sectionGradeName}) does not match student grade level ({$studentGrade})"
                ], 422);
            }

            // Remove existing section assignment for this school year
            $student->sections()->wherePivot('school_year', $schoolYear)->detach();

            // Assign to new section
            $student->sections()->attach($sectionId, [
                'school_year' => $schoolYear,
                'is_active' => true
            ]);

            // Load the updated student with section
            $student->load(['sections' => function($query) use ($schoolYear) {
                $query->wherePivot('school_year', $schoolYear)->wherePivot('is_active', true);
            }]);

            return response()->json([
                'success' => true,
                'message' => 'Student assigned to section successfully',
                'data' => [
                    'student_id' => $student->id,
                    'section_id' => $sectionId,
                    'section_name' => $section->name,
                    'school_year' => $schoolYear
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error assigning student to section: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign student to section'
            ], 500);
        }
    }

    /**
     * Get enrollment statistics.
     */
    public function getStats()
    {
        try {
            $totalStudents = Student::count();
            $activeStudents = Student::where('isActive', true)->count();
            $studentsWithSections = Student::whereHas('sections', function($query) {
                $query->wherePivot('is_active', true);
            })->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_students' => $totalStudents,
                    'active_students' => $activeStudents,
                    'students_with_sections' => $studentsWithSections,
                    'students_without_sections' => $activeStudents - $studentsWithSections
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching enrollment stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch enrollment statistics'
            ], 500);
        }
    }

    /**
     * Get students by grade level.
     */
    public function byGrade($gradeLevel)
    {
        try {
            $students = Student::where('gradeLevel', $gradeLevel)
                ->with(['sections' => function($query) {
                    $query->wherePivot('is_active', true);
                }])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $students
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching students by grade: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch students by grade level'
            ], 500);
        }
    }
}
