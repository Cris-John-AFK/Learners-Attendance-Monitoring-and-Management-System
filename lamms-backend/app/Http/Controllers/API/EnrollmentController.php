<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EnrollmentController extends Controller
{
    /**
     * Get all enrolled students
     */
    public function index()
    {
        try {
            $students = Student::where('enrollment_status', 'Enrolled')
                             ->with(['currentSection.curriculumGrade.grade'])
                             ->orderBy('created_at', 'desc')
                             ->get();

            // Add section information to each student
            $studentsWithSections = $students->map(function ($student) {
                $currentSection = $student->getSectionForYear('2025-2026');
                $student->current_section_name = $currentSection ? $currentSection->name : null;
                $student->current_section_id = $currentSection ? $currentSection->id : null;
                return $student;
            });

            return response()->json([
                'success' => true,
                'data' => $studentsWithSections
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
     * Store a new student enrollment
     */
    public function store(Request $request)
    {
        try {
            // Clean email field if it's not a valid email before validation
            if ($request->email_address && !filter_var($request->email_address, FILTER_VALIDATE_EMAIL)) {
                $request->merge(['email_address' => null]);
            }

            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'grade_level' => 'required|string',
                'email_address' => 'nullable|email|max:255',
                'birthdate' => 'nullable|date',
                'sex' => 'required|in:Male,Female',
                'enrollment_id' => 'required|string|unique:student_details,enrollment_id',
                'student_type' => 'required|string',
                'school_year' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate enrollment ID if not provided
            $enrollmentId = $request->enrollment_id;
            if (!$enrollmentId) {
                $currentYear = date('Y');
                $randomNum = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $enrollmentId = "ENR{$currentYear}{$randomNum}";
            }

            // Prepare student data mapping to existing database columns
            $studentData = [
                // Required fields that exist in database
                'studentId' => $request->enrollment_id, // Map enrollment_id to studentId
                'student_id' => $request->enrollment_id, // Also map to student_id column (NOT NULL constraint)
                'firstName' => $request->first_name,
                'lastName' => $request->last_name,
                'middleName' => $request->middle_name,
                'extensionName' => $request->extension_name,
                'name' => trim($request->first_name . ' ' . ($request->middle_name ? $request->middle_name . ' ' : '') . $request->last_name . ($request->extension_name ? ' ' . $request->extension_name : '')),
                'email' => $request->email_address,
                'gradeLevel' => $request->grade_level,
                'birthdate' => $request->birthdate,
                'age' => $request->age,
                'sex' => $request->sex,
                'lrn' => $request->lrn,
                'motherTongue' => $request->mother_tongue,
                'religion' => $request->religion,
                'status' => 'Enrolled',
                'enrollmentDate' => $request->enrollment_date,
                'isActive' => $request->is_active ?? true,
                'hasDisability' => $request->has_disability ?? false,
                'disabilities' => $request->disabilities,

                // New enrollment fields
                'student_type' => $request->student_type,
                'school_year' => $request->school_year,
                'enrollment_id' => $request->enrollment_id,
                'enrollment_status' => $request->enrollment_status ?? 'Enrolled',
                'house_no' => $request->house_no,
                'street' => $request->street,
                'barangay' => $request->barangay,
                'city_municipality' => $request->city_municipality,
                'province' => $request->province,
                'country' => $request->country ?? 'Philippines',
                'zip_code' => $request->zip_code,
                'last_grade_completed' => $request->last_grade_completed,
                'last_school_attended' => $request->last_school_attended,
                'household_income' => $request->household_income ?? 'Below 10k',

                // Parent information (map to existing columns)
                'father' => trim($request->father_first_name . ' ' . $request->father_last_name),
                'mother' => trim($request->mother_first_name . ' ' . $request->mother_last_name),
                'parentContact' => $request->father_contact_number ?: $request->mother_contact_number,
            ];

            // Create the student record
            $student = Student::create($studentData);

            Log::info('Student enrolled successfully', ['student_id' => $student->id, 'enrollment_id' => $enrollmentId]);

            return response()->json([
                'success' => true,
                'message' => 'Student enrolled successfully',
                'data' => $student
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error enrolling student: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to enroll student: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific enrolled student
     */
    public function show($id)
    {
        try {
            $student = Student::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $student
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
     * Update an enrolled student
     */
    public function update(Request $request, $id)
    {
        try {
            $student = Student::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'firstName' => 'sometimes|required|string|max:255',
                'lastName' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:student_details,email,' . $id,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Student updated successfully',
                'data' => $student
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
     * Delete an enrolled student
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
     * Assign student to section
     */
    public function assignSection(Request $request, $id)
    {
        try {
            $student = Student::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'section_id' => 'required|exists:sections,id',
                'school_year' => 'string|nullable'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $sectionId = $request->section_id;
            $schoolYear = $request->school_year ?? '2025-2026';

            // Verify the section matches the student's grade level
            $section = \App\Models\Section::with(['curriculumGrade.grade'])->findOrFail($sectionId);
            $sectionGrade = $section->curriculumGrade->grade ?? null;

            if (!$sectionGrade || $sectionGrade->name !== $student->gradeLevel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section grade level does not match student grade level'
                ], 422);
            }

            // Remove existing section assignment for this school year
            $student->sections()->wherePivot('school_year', $schoolYear)->detach();

            // Assign to new section
            $student->sections()->attach($sectionId, [
                'school_year' => $schoolYear,
                'is_active' => true
            ]);

            // Update the legacy section field for backward compatibility
            $student->update(['section' => $section->name]);

            Log::info('Student assigned to section', [
                'student_id' => $student->id,
                'section_id' => $sectionId,
                'school_year' => $schoolYear
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Student assigned to section successfully',
                'data' => [
                    'student' => $student->fresh(),
                    'section' => $section
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
     * Get available sections for a student's grade level
     */
    public function getAvailableSections($id)
    {
        try {
            $student = Student::findOrFail($id);

            // Get sections that match the student's grade level
            $sections = \App\Models\Section::with(['curriculumGrade.grade'])
                ->whereHas('curriculumGrade.grade', function ($query) use ($student) {
                    $query->where('name', $student->gradeLevel);
                })
                ->where('is_active', true)
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $sections
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
     * Get enrollment statistics
     */
    public function getStats()
    {
        try {
            $totalEnrolled = Student::where('enrollment_status', 'Enrolled')->count();
            $totalAdmitted = Student::count();
            $notEnrolled = $totalAdmitted - $totalEnrolled;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_admitted' => $totalAdmitted,
                    'total_enrolled' => $totalEnrolled,
                    'not_enrolled' => $notEnrolled
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
}
