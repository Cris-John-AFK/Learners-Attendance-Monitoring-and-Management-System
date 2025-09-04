<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentQRCode;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

            return response()->json([
                'valid' => true,
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'firstName' => $student->firstName,
                    'lastName' => $student->lastName,
                    'gradeLevel' => $student->gradeLevel,
                    'email' => $student->email,
                    'lrn' => $student->lrn
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to validate QR code: ' . $e->getMessage()], 500);
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
}
