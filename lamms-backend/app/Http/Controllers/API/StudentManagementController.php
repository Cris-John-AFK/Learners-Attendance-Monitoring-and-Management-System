<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class StudentManagementController extends Controller
{
    /**
     * Get students by section for a teacher
     */
    public function getStudentsBySection($sectionId, Request $request)
    {
        try {
            Log::info("Getting students for section", [
                'section_id' => $sectionId,
                'teacher_id' => $request->query('teacher_id')
            ]);

            // Get teacher_id from query parameters
            $teacherId = $request->query('teacher_id');
            
            if (!$teacherId) {
                return response()->json([
                    'error' => 'Teacher ID is required',
                    'students' => [],
                    'section_id' => $sectionId,
                    'total_count' => 0
                ], 400);
            }

            // Verify teacher has access to this section
            $teacherAssignment = DB::table('teacher_section_subject')
                ->where('teacher_id', $teacherId)
                ->where('section_id', $sectionId)
                ->where('is_active', true)
                ->first();

            if (!$teacherAssignment) {
                Log::warning("Teacher does not have access to section", [
                    'teacher_id' => $teacherId,
                    'section_id' => $sectionId
                ]);
                
                return response()->json([
                    'error' => 'Unauthorized access to this section',
                    'students' => [],
                    'section_id' => $sectionId,
                    'total_count' => 0
                ], 403);
            }

            // Get students assigned to this section through the pivot table
            // Include ALL students (active and inactive) for attendance records
            $section = Section::findOrFail($sectionId);
            $students = $section->students()->get(); // Changed from activeStudents() to students()

            Log::info("Found students for section", [
                'section_id' => $sectionId,
                'student_count' => $students->count()
            ]);

            return response()->json([
                'students' => $students->map(function($student) {
                    return [
                        'id' => $student->id,
                        'name' => $student->name ?? $student->firstName . ' ' . $student->lastName,
                        'firstName' => $student->firstName,
                        'lastName' => $student->lastName,
                        'middleName' => $student->middleName,
                        'studentId' => $student->studentId ?? $student->student_id,
                        'lrn' => $student->lrn,
                        'email' => $student->email,
                        'gender' => $student->gender,
                        'qrCode' => $student->qr_code_path ?? $student->qr_code,
                        'status' => $student->status ?? 'active',
                        'enrollment_status' => $student->enrollment_status ?? $student->status ?? 'Active', // Add enrollment_status
                        'enrollmentDate' => $student->created_at ? $student->created_at->format('Y-m-d') : null
                    ];
                }),
                'section_id' => $sectionId,
                'total_count' => $students->count()
            ]);

        } catch (\Exception $e) {
            Log::error("Error loading students for section", [
                'section_id' => $sectionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to load students',
                'message' => $e->getMessage(),
                'students' => [],
                'section_id' => $sectionId,
                'total_count' => 0
            ], 500);
        }
    }

    /**
     * Get students by subject for attendance
     */
    public function getStudentsBySubject($subjectId, Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|integer|exists:teachers,id',
            'section_id' => 'nullable|integer|exists:sections,id'
        ]);

        try {
            // Get sections where teacher teaches this subject
            $query = DB::table('teacher_section_subject')
                ->where('teacher_id', $request->teacher_id)
                ->where('subject_id', $subjectId)
                ->where('is_active', true);

            if ($request->section_id) {
                $query->where('section_id', $request->section_id);
            }

            $assignments = $query->get();

            if ($assignments->isEmpty()) {
                return response()->json([
                    'error' => 'No students found for this subject assignment'
                ], 404);
            }

            $students = collect();
            foreach ($assignments as $assignment) {
                $section = Section::with(['activeStudents', 'grade'])->find($assignment->section_id);
                if ($section && $section->activeStudents) {
                    $sectionStudents = $section->activeStudents->map(function ($student) use ($section) {
                        return [
                            'id' => $student->id,
                            'name' => $student->name ?? $student->firstName . ' ' . $student->lastName,
                            'studentId' => $student->studentId ?? $student->student_id,
                            'email' => $student->email,
                            'qrCode' => $student->qr_code,
                            'gradeLevel' => $section->grade->name ?? 'Unknown',
                            'section' => $section->name ?? 'Unknown',
                            'sectionId' => $section->id,
                            'status' => $student->status ?? 'active'
                        ];
                    });
                    $students = $students->merge($sectionStudents);
                }
            }

            return response()->json([
                'students' => $students->unique('id')->values(),
                'subject_id' => $subjectId,
                'total_count' => $students->unique('id')->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load students for subject',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate QR codes for students
     */
    public function generateStudentQRCodes(Request $request)
    {
        $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'integer|exists:student_details,id',
            'teacher_id' => 'required|integer|exists:teachers,id'
        ]);

        try {
            $results = [];
            $errors = [];

            foreach ($request->student_ids as $studentId) {
                try {
                    $student = Student::findOrFail($studentId);

                    // Get the student's section through the pivot table
                    $studentSection = DB::table('student_section')
                        ->where('student_id', $studentId)
                        ->where('is_active', true)
                        ->first();

                    if (!$studentSection) {
                        $errors[] = "Student ID {$studentId} not found in any active section";
                        continue;
                    }

                    // Verify teacher has access to this student's section
                    $teacherAssignment = DB::table('teacher_section_subject')
                        ->where('teacher_id', $request->teacher_id)
                        ->where('section_id', $studentSection->section_id)
                        ->where('is_active', true)
                        ->first();

                    if (!$teacherAssignment) {
                        $errors[] = "Unauthorized access to student: {$student->name}";
                        continue;
                    }

                    // Generate QR code if not exists
                    if (!$student->qr_code) {
                        $qrCode = $this->generateUniqueQRCode($student);
                        $student->update(['qr_code' => $qrCode]);
                    }

                    // Generate QR code image
                    $qrImagePath = $this->generateQRCodeImage($student);

                    $results[] = [
                        'student_id' => $student->id,
                        'name' => $student->name,
                        'student_number' => $student->student_id,
                        'qr_code' => $student->qr_code,
                        'qr_image_path' => $qrImagePath,
                        'success' => true
                    ];

                } catch (\Exception $e) {
                    $errors[] = "Failed to generate QR for student ID {$studentId}: " . $e->getMessage();
                }
            }

            return response()->json([
                'message' => 'QR code generation completed',
                'results' => $results,
                'errors' => $errors,
                'success_count' => count($results),
                'error_count' => count($errors)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate QR codes',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get student seating arrangement
     */
    public function getSeatingArrangement($sectionId, Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|integer|exists:teachers,id',
            'subject_id' => 'nullable|integer|exists:subjects,id'
        ]);

        try {
            // Verify teacher access
            $teacherAssignment = DB::table('teacher_section_subject')
                ->where('teacher_id', $request->teacher_id)
                ->where('section_id', $sectionId)
                ->where('is_active', true)
                ->first();

            if (!$teacherAssignment) {
                return response()->json([
                    'error' => 'Unauthorized access to this section'
                ], 403);
            }

            // Get seating arrangement from database or create default (section-based, not subject-specific)
            $seatingData = DB::table('seating_arrangements')
                ->where('section_id', $sectionId)
                ->where('teacher_id', $request->teacher_id)
                ->first();

            if ($seatingData) {
                $seatingLayout = json_decode($seatingData->layout, true);
            } else {
                // Create default seating arrangement
                $section = Section::findOrFail($sectionId);
                $students = $section->activeStudents()
                    ->select('student_details.id', 'student_details.firstName', 'student_details.lastName', 'student_details.name', 'student_details.studentId')
                    ->get();

                $seatingLayout = $this->createDefaultSeatingLayout($students);
            }

            return response()->json([
                'section_id' => $sectionId,
                'subject_id' => $request->subject_id,
                'seating_layout' => $seatingLayout,
                'last_updated' => $seatingData->updated_at ?? null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load seating arrangement',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save student seating arrangement
     */
    public function saveSeatingArrangement(Request $request)
    {
        Log::info('Seating arrangement save request received', [
            'data' => $request->all(),
            'headers' => $request->headers->all()
        ]);

        try {
            // Handle subject_id conversion from string to integer if needed
            $requestData = $request->all();
            if (isset($requestData['subject_id']) && !is_numeric($requestData['subject_id'])) {
                // Try to find subject by name or code
                $subject = \App\Models\Subject::where('name', 'ILIKE', $requestData['subject_id'])
                    ->orWhere('code', 'ILIKE', $requestData['subject_id'])
                    ->first();
                
                if ($subject) {
                    $requestData['subject_id'] = $subject->id;
                    Log::info('Converted subject identifier to ID', [
                        'original' => $request->input('subject_id'),
                        'resolved_id' => $subject->id,
                        'subject_name' => $subject->name
                    ]);
                } else {
                    Log::error('Subject not found for identifier', [
                        'subject_identifier' => $requestData['subject_id']
                    ]);
                    return response()->json([
                        'message' => 'Subject not found',
                        'error' => 'Invalid subject identifier: ' . $requestData['subject_id']
                    ], 422);
                }
            }

            $validator = \Illuminate\Support\Facades\Validator::make($requestData, [
                'section_id' => 'required|integer|exists:sections,id',
                'subject_id' => 'nullable|integer|exists:subjects,id',
                'teacher_id' => 'required|integer|exists:teachers,id',
                'seating_layout' => 'required|array'
            ]);

            if ($validator->fails()) {
                Log::error('Seating arrangement validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'input' => $requestData
                ]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
        } catch (\Exception $e) {
            Log::error('Validation exception in saveSeatingArrangement', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Validation error',
                'error' => $e->getMessage()
            ], 500);
        }

        try {
            // Verify teacher access
            $teacherAssignment = DB::table('teacher_section_subject')
                ->where('teacher_id', $requestData['teacher_id'])
                ->where('section_id', $requestData['section_id'])
                ->where('is_active', true)
                ->first();

            if (!$teacherAssignment) {
                return response()->json([
                    'error' => 'Unauthorized access to this section'
                ], 403);
            }

            // Save or update seating arrangement (section-based, not subject-specific)
            $existing = DB::table('seating_arrangements')
                ->where('section_id', $requestData['section_id'])
                ->where('teacher_id', $requestData['teacher_id'])
                ->first();

            if ($existing) {
                // Update existing record
                DB::table('seating_arrangements')
                    ->where('id', $existing->id)
                    ->update([
                        'layout' => json_encode($requestData['seating_layout']),
                        'subject_id' => null,
                        'updated_at' => now()
                    ]);
            } else {
                // Insert new record
                DB::table('seating_arrangements')->insert([
                    'section_id' => $requestData['section_id'],
                    'teacher_id' => $requestData['teacher_id'],
                    'layout' => json_encode($requestData['seating_layout']),
                    'subject_id' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'message' => 'Seating arrangement saved successfully',
                'section_id' => $requestData['section_id'],
                'subject_id' => $requestData['subject_id']
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to save seating arrangement',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset/clear student seating arrangement
     */
    public function resetSeatingArrangement(Request $request)
    {
        $request->validate([
            'section_id' => 'required|integer|exists:sections,id',
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'teacher_id' => 'required|integer|exists:teachers,id'
        ]);

        try {
            // Verify teacher access
            $teacherAssignment = DB::table('teacher_section_subject')
                ->where('teacher_id', $request->teacher_id)
                ->where('section_id', $request->section_id)
                ->where('is_active', true)
                ->first();

            if (!$teacherAssignment) {
                return response()->json([
                    'error' => 'Unauthorized access to this section'
                ], 403);
            }

            // Delete seating arrangement from database (section-based)
            $deleted = DB::table('seating_arrangements')
                ->where('section_id', $request->section_id)
                ->where('teacher_id', $request->teacher_id)
                ->delete();

            return response()->json([
                'message' => 'Seating arrangement reset successfully',
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'deleted_records' => $deleted
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to reset seating arrangement',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import students to section
     */
    public function importStudents(Request $request)
    {
        $request->validate([
            'section_id' => 'required|integer|exists:sections,id',
            'teacher_id' => 'required|integer|exists:teachers,id',
            'students' => 'required|array',
            'students.*.name' => 'required|string|max:255',
            'students.*.student_id' => 'required|string|unique:student_details,student_id',
            'students.*.email' => 'nullable|email|unique:student_details,email'
        ]);

        try {
            // Verify teacher access
            $teacherAssignment = DB::table('teacher_section_subject')
                ->where('teacher_id', $request->teacher_id)
                ->where('section_id', $request->section_id)
                ->where('is_active', true)
                ->first();

            if (!$teacherAssignment) {
                return response()->json([
                    'error' => 'Unauthorized access to this section'
                ], 403);
            }

            $results = [];
            $errors = [];

            DB::beginTransaction();

            foreach ($request->students as $studentData) {
                try {
                    $student = Student::create([
                        'name' => $studentData['name'],
                        'student_id' => $studentData['student_id'],
                        'email' => $studentData['email'] ?? null,
                        'section_id' => $request->section_id,
                        'qr_code' => $this->generateUniqueQRCode(null, $studentData['student_id']),
                        'status' => 'active'
                    ]);

                    $results[] = [
                        'id' => $student->id,
                        'name' => $student->name,
                        'student_id' => $student->student_id,
                        'qr_code' => $student->qr_code,
                        'success' => true
                    ];

                } catch (\Exception $e) {
                    $errors[] = "Failed to import student {$studentData['name']}: " . $e->getMessage();
                }
            }

            if (empty($errors)) {
                DB::commit();
                return response()->json([
                    'message' => 'Students imported successfully',
                    'imported_students' => $results,
                    'success_count' => count($results)
                ]);
            } else {
                DB::rollback();
                return response()->json([
                    'error' => 'Some students failed to import',
                    'errors' => $errors,
                    'successful_imports' => $results
                ], 422);
            }

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Failed to import students',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique QR code for student
     */
    private function generateUniqueQRCode($student = null, $studentId = null)
    {
        do {
            $qrCode = 'STU_' . ($studentId ?? $student->student_id) . '_' . Str::random(8);
        } while (Student::where('qr_code', $qrCode)->exists());

        return $qrCode;
    }

    /**
     * Generate QR code image file
     */
    private function generateQRCodeImage($student)
    {
        $qrData = json_encode([
            'student_id' => $student->id,
            'student_number' => $student->student_id,
            'qr_code' => $student->qr_code,
            'name' => $student->name
        ]);

        $fileName = $student->student_id . '_qr.png';
        $filePath = 'qr-codes/' . $fileName;

        // Generate QR code image
        $qrImage = QrCode::format('png')
            ->size(200)
            ->margin(2)
            ->generate($qrData);

        // Save to public directory
        Storage::disk('public')->put($filePath, $qrImage);

        return '/storage/' . $filePath;
    }

    /**
     * Create default seating layout
     */
    private function createDefaultSeatingLayout($students)
    {
        $rows = 9;
        $cols = 9;
        $seatPlan = [];

        // Initialize empty layout
        for ($row = 0; $row < $rows; $row++) {
            $seatPlan[$row] = [];
            for ($col = 0; $col < $cols; $col++) {
                $seatPlan[$row][$col] = [
                    'id' => null,
                    'name' => null,
                    'studentId' => null,
                    'isOccupied' => false,
                    'status' => null
                ];
            }
        }

        // Place students in layout (start from row 1, leave first row empty for spacing)
        $studentIndex = 0;
        foreach ($students as $student) {
            if ($studentIndex >= ($rows - 1) * $cols) break; // Leave space

            $row = intval($studentIndex / $cols) + 1; // Start from row 1
            $col = $studentIndex % $cols;

            $seatPlan[$row][$col] = [
                'id' => $student->id,
                'name' => $student->name ?? $student->firstName . ' ' . $student->lastName,
                'studentId' => $student->studentId,
                'isOccupied' => true,
                'status' => null
            ];

            $studentIndex++;
        }

        // Return in the format expected by frontend
        return [
            'rows' => $rows,
            'columns' => $cols,
            'seatPlan' => $seatPlan,
            'showTeacherDesk' => true,
            'showStudentIds' => true
        ];
    }
}
