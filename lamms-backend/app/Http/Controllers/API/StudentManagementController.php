<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Section;
use App\Models\TeacherSectionSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $request->validate([
            'teacher_id' => 'required|integer|exists:teachers,id'
        ]);

        try {
            // Verify teacher has access to this section
            $teacherAssignment = TeacherSectionSubject::where([
                'teacher_id' => $request->teacher_id,
                'section_id' => $sectionId
            ])->first();

            if (!$teacherAssignment) {
                return response()->json([
                    'error' => 'Unauthorized access to this section'
                ], 403);
            }

            $students = Student::with(['section.grade'])
                ->where('section_id', $sectionId)
                ->orderBy('name')
                ->get()
                ->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'name' => $student->name,
                        'studentId' => $student->student_id,
                        'email' => $student->email,
                        'qrCode' => $student->qr_code,
                        'gradeLevel' => $student->section->grade->name ?? 'Unknown',
                        'section' => $student->section->name ?? 'Unknown',
                        'status' => $student->status ?? 'active',
                        'enrollmentDate' => $student->created_at->format('Y-m-d')
                    ];
                });

            return response()->json([
                'students' => $students,
                'section_id' => $sectionId,
                'total_count' => $students->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load students',
                'message' => $e->getMessage()
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
            $query = TeacherSectionSubject::with(['section.students.section.grade'])
                ->where('teacher_id', $request->teacher_id)
                ->where('subject_id', $subjectId);

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
                $sectionStudents = $assignment->section->students->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'name' => $student->name,
                        'studentId' => $student->student_id,
                        'email' => $student->email,
                        'qrCode' => $student->qr_code,
                        'gradeLevel' => $student->section->grade->name ?? 'Unknown',
                        'section' => $student->section->name ?? 'Unknown',
                        'sectionId' => $student->section_id,
                        'status' => $student->status ?? 'active'
                    ];
                });
                $students = $students->merge($sectionStudents);
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
            'student_ids.*' => 'integer|exists:students,id',
            'teacher_id' => 'required|integer|exists:teachers,id'
        ]);

        try {
            $results = [];
            $errors = [];

            foreach ($request->student_ids as $studentId) {
                try {
                    $student = Student::findOrFail($studentId);

                    // Verify teacher has access to this student's section
                    $teacherAssignment = TeacherSectionSubject::where([
                        'teacher_id' => $request->teacher_id,
                        'section_id' => $student->section_id
                    ])->first();

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
            $teacherAssignment = TeacherSectionSubject::where([
                'teacher_id' => $request->teacher_id,
                'section_id' => $sectionId
            ])->first();

            if (!$teacherAssignment) {
                return response()->json([
                    'error' => 'Unauthorized access to this section'
                ], 403);
            }

            // Get seating arrangement from database or create default
            $seatingData = DB::table('seating_arrangements')
                ->where('section_id', $sectionId)
                ->where('subject_id', $request->subject_id)
                ->first();

            if ($seatingData) {
                $seatingLayout = json_decode($seatingData->layout, true);
            } else {
                // Create default seating arrangement
                $students = Student::where('section_id', $sectionId)
                    ->select('id', 'name', 'student_id')
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
        $request->validate([
            'section_id' => 'required|integer|exists:sections,id',
            'subject_id' => 'nullable|integer|exists:subjects,id',
            'teacher_id' => 'required|integer|exists:teachers,id',
            'seating_layout' => 'required|array'
        ]);

        try {
            // Verify teacher access
            $teacherAssignment = TeacherSectionSubject::where([
                'teacher_id' => $request->teacher_id,
                'section_id' => $request->section_id
            ])->first();

            if (!$teacherAssignment) {
                return response()->json([
                    'error' => 'Unauthorized access to this section'
                ], 403);
            }

            // Save or update seating arrangement
            DB::table('seating_arrangements')->updateOrInsert(
                [
                    'section_id' => $request->section_id,
                    'subject_id' => $request->subject_id
                ],
                [
                    'layout' => json_encode($request->seating_layout),
                    'teacher_id' => $request->teacher_id,
                    'updated_at' => now()
                ]
            );

            return response()->json([
                'message' => 'Seating arrangement saved successfully',
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to save seating arrangement',
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
            'students.*.student_id' => 'required|string|unique:students,student_id',
            'students.*.email' => 'nullable|email|unique:students,email'
        ]);

        try {
            // Verify teacher access
            $teacherAssignment = TeacherSectionSubject::where([
                'teacher_id' => $request->teacher_id,
                'section_id' => $request->section_id
            ])->first();

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
        $rows = 6;
        $cols = 6;
        $layout = [];

        // Initialize empty layout
        for ($row = 0; $row < $rows; $row++) {
            $layout[$row] = [];
            for ($col = 0; $col < $cols; $col++) {
                $layout[$row][$col] = null;
            }
        }

        // Place students in layout
        $studentIndex = 0;
        foreach ($students as $student) {
            if ($studentIndex >= $rows * $cols) break;

            $row = intval($studentIndex / $cols);
            $col = $studentIndex % $cols;

            $layout[$row][$col] = [
                'id' => $student->id,
                'name' => $student->name,
                'studentId' => $student->student_id
            ];

            $studentIndex++;
        }

        return $layout;
    }
}
