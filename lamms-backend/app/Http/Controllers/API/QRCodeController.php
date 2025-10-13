<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentQRCode;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QRCodeController extends Controller
{
    /**
     * Generate QR code for a student
     */
    public function generateQRCode($studentId)
    {
        try {
            $student = Student::find($studentId);
            if (!$student) {
                return response()->json(['error' => 'Student not found'], 404);
            }

            // Generate new QR code for student
            $qrCode = StudentQRCode::generateForStudent($studentId);

            return response()->json([
                'success' => true,
                'student_id' => $studentId,
                'qr_code_data' => $qrCode->qr_code_data,
                'qr_code_id' => $qrCode->id,
                'generated_at' => $qrCode->generated_at,
                'student_name' => $student->name
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate QR code: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get QR code image for a student
     */
    public function getQRCodeImage($studentId)
    {
        try {
            $student = Student::find($studentId);
            if (!$student) {
                return response()->json(['error' => 'Student not found'], 404);
            }

            $qrCode = StudentQRCode::getActiveQRForStudent($studentId);
            if (!$qrCode) {
                // Generate new QR code if none exists
                $qrCode = StudentQRCode::generateForStudent($studentId);
            }

            // Generate QR code image using SVG format (doesn't require imagick)
            $qrCodeImage = QrCode::format('svg')
                                 ->size(300)
                                 ->margin(2)
                                 ->generate($qrCode->qr_code_data);

            return response($qrCodeImage, 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => 'inline; filename="student_' . $studentId . '_qr.svg"'
            ]);
        } catch (\Exception $e) {
            // Return error details for debugging
            return response()->json([
                'error' => 'Failed to generate QR code image',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Validate QR code and return student information
     * CRITICAL: Check if student is active before allowing QR code usage
     */
    public function validateQRCode(Request $request)
    {
        $request->validate([
            'qr_code_data' => 'required|string'
        ]);

        try {
            $student = StudentQRCode::findStudentByQRCode($request->qr_code_data);

            if (!$student) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Invalid or expired QR code'
                ], 404);
            }

            // CRITICAL: Check if student is active
            $enrollmentStatus = $student->enrollment_status ?? 'active';
            $activeStatuses = ['active', 'enrolled', 'transferred_in'];
            
            if (!in_array($enrollmentStatus, $activeStatuses)) {
                return response()->json([
                    'valid' => false,
                    'message' => 'QR code is disabled - Student is no longer active',
                    'student_status' => $enrollmentStatus
                ], 403);
            }

            return response()->json([
                'valid' => true,
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'firstName' => $student->firstName,
                    'lastName' => $student->lastName,
                    'gradeLevel' => $student->gradeLevel,
                    'email' => $student->email,
                    'lrn' => $student->lrn,
                    'enrollment_status' => $enrollmentStatus
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to validate QR code: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Disable QR codes for non-active students
     * Called when student status changes
     */
    public function disableQRCodesForInactiveStudents()
    {
        try {
            // Get all students with non-active status
            $inactiveStudents = Student::whereIn('enrollment_status', [
                'dropped_out', 'transferred_out', 'withdrawn', 'deceased'
            ])->pluck('id');

            if ($inactiveStudents->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No inactive students found',
                    'disabled_count' => 0
                ]);
            }

            // Disable QR codes for inactive students
            $disabledCount = StudentQRCode::whereIn('student_id', $inactiveStudents)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'disabled_at' => now(),
                    'disabled_reason' => 'Student status changed to inactive'
                ]);

            Log::info("🚫 Disabled QR codes for inactive students", [
                'disabled_count' => $disabledCount,
                'inactive_student_ids' => $inactiveStudents->toArray()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Disabled {$disabledCount} QR codes for inactive students",
                'disabled_count' => $disabledCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error disabling QR codes for inactive students: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to disable QR codes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all QR codes for students
     */
    public function getAllQRCodes()
    {
        try {
            $qrCodes = StudentQRCode::with('student')
                                   ->where('is_active', true)
                                   ->get()
                                   ->map(function ($qrCode) {
                                       return [
                                           'id' => $qrCode->id,
                                           'student_id' => $qrCode->student_id,
                                           'student_name' => $qrCode->student->name,
                                           'qr_code_data' => $qrCode->qr_code_data,
                                           'generated_at' => $qrCode->generated_at,
                                           'last_used_at' => $qrCode->last_used_at
                                       ];
                                   });

            return response()->json($qrCodes);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch QR codes: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get QR code for specific student
     */
    public function getStudentQRCode($studentId)
    {
        try {
            $student = Student::find($studentId);
            if (!$student) {
                return response()->json(['error' => 'Student not found'], 404);
            }

            $qrCode = StudentQRCode::getActiveQRForStudent($studentId);
            if (!$qrCode) {
                return response()->json([
                    'has_qr_code' => false,
                    'student_id' => $studentId,
                    'student_name' => $student->name
                ]);
            }

            return response()->json([
                'has_qr_code' => true,
                'student_id' => $studentId,
                'student_name' => $student->name,
                'qr_code_data' => $qrCode->qr_code_data,
                'qr_code_id' => $qrCode->id,
                'generated_at' => $qrCode->generated_at,
                'last_used_at' => $qrCode->last_used_at
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch student QR code: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 🚀 BULK: Get QR codes for multiple students in one request
     */
    public function getBulkQRCodes(Request $request)
    {
        try {
            $request->validate([
                'student_ids' => 'required|array',
                'student_ids.*' => 'integer|exists:student_details,id'
            ]);

            $studentIds = $request->input('student_ids');
            Log::info('🚀 Bulk QR request for students:', $studentIds);

            // Get all QR codes in one query
            $qrCodes = StudentQRCode::whereIn('student_id', $studentIds)
                ->where('is_active', true)
                ->with('student')
                ->get()
                ->keyBy('student_id');

            $result = [];
            foreach ($studentIds as $studentId) {
                $qrCode = $qrCodes->get($studentId);
                if ($qrCode && $qrCode->qr_code_data) {
                    // Generate SVG from QR code data (same as getQRCodeImage)
                    $qrCodeSvg = QrCode::format('svg')
                                       ->size(300)
                                       ->margin(2)
                                       ->generate($qrCode->qr_code_data);
                    
                    // Ensure it's a string
                    $result[$studentId] = (string) $qrCodeSvg;
                }
            }

            Log::info('✅ Bulk QR response:', ['requested' => count($studentIds), 'found' => count($result)]);

            return response()->json([
                'success' => true,
                'qr_codes' => $result,
                'requested_count' => count($studentIds),
                'found_count' => count($result)
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Bulk QR error:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch bulk QR codes: ' . $e->getMessage()], 500);
        }
    }
}
