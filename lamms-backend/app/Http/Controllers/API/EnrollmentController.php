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
                             ->orderBy('created_at', 'desc')
                             ->get();
            
            return response()->json([
                'success' => true,
                'data' => $students
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
            $validator = Validator::make($request->all(), [
                'firstName' => 'required|string|max:255',
                'lastName' => 'required|string|max:255',
                'gradeLevel' => 'required|string',
                'email' => 'required|email|unique:student_details,email',
                'birthdate' => 'required|date',
                'sex' => 'required|in:Male,Female',
                'barangay' => 'required|string',
                'city_municipality' => 'required|string',
                'province' => 'required|string',
                'household_income' => 'required|string'
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

            // Prepare student data
            $studentData = [
                // Basic Information
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'middleName' => $request->middleName,
                'extensionName' => $request->extensionName,
                'name' => trim($request->firstName . ' ' . $request->middleName . ' ' . $request->lastName . ' ' . $request->extensionName),
                'email' => $request->email,
                'birthdate' => $request->birthdate,
                'age' => $request->age,
                'sex' => $request->sex,
                'gender' => $request->sex, // Map sex to gender for compatibility
                'gradeLevel' => $request->gradeLevel,
                'lrn' => $request->lrn,
                'motherTongue' => $request->motherTongue,
                'religion' => $request->religion,
                
                // Enrollment specific fields
                'student_type' => $request->studentType ?? 'New',
                'school_year' => $request->schoolYear ?? '2025-2026',
                'enrollment_id' => $enrollmentId,
                'enrollment_status' => 'Enrolled',
                'enrollmentDate' => now(),
                'status' => 'Enrolled',
                'isActive' => true,
                
                // Address Information
                'house_no' => $request->houseNo,
                'street' => $request->street,
                'barangay' => $request->barangay,
                'city_municipality' => $request->cityMunicipality,
                'province' => $request->province,
                'country' => $request->country ?? 'Philippines',
                'zip_code' => $request->zipCode,
                'address' => trim($request->houseNo . ' ' . $request->street . ', ' . $request->barangay . ', ' . $request->cityMunicipality),
                
                // Parent/Guardian Information
                'father_name' => $request->fatherName,
                'father_occupation' => $request->fatherOccupation,
                'father_contact' => $request->fatherContact,
                'father_education' => $request->fatherEducation,
                'mother_name' => $request->motherName,
                'mother_occupation' => $request->motherOccupation,
                'mother_contact' => $request->motherContact,
                'mother_education' => $request->motherEducation,
                'guardian_name' => $request->guardianName,
                'guardian_occupation' => $request->guardianOccupation,
                'guardian_contact' => $request->guardianContact,
                'guardian_address' => $request->guardianAddress,
                
                // Previous School Information
                'last_grade_completed' => $request->lastGradeCompleted,
                'last_school_year' => $request->lastSchoolYear,
                'last_school_attended' => $request->lastSchoolAttended,
                'last_school_address' => $request->lastSchoolAddress,
                
                // Additional Information
                'household_income' => $request->householdIncome ?? 'Below 10k',
                'hasDisability' => $request->hasDisability ?? false,
                'disabilities' => $request->disabilities ?? [],
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
