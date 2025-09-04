<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class StudentController extends Controller
{
    public function index()
    {
        return Student::with(['sections' => function($query) {
            $query->wherePivot('is_active', true)
                  ->wherePivot('school_year', '2025-2026');
        }])->get()->map(function($student) {
            $currentSection = $student->sections->first();
            
            return [
                'id' => $student->id,
                'name' => $student->name,
                'firstName' => $student->firstName,
                'lastName' => $student->lastName,
                'middleName' => $student->middleName,
                'extensionName' => $student->extensionName,
                'email' => $student->email,
                'gradeLevel' => $student->gradeLevel,
                'section' => $currentSection ? $currentSection->name : null,
                'current_section_name' => $currentSection ? $currentSection->name : null,
                'current_section_id' => $currentSection ? $currentSection->id : null,
                'sectionId' => $currentSection ? $currentSection->id : null,
                'studentId' => $student->studentId,
                'student_id' => $student->student_id,
                'lrn' => $student->lrn,
                'gender' => $student->gender,
                'sex' => $student->sex,
                'birthdate' => $student->birthdate,
                'birthplace' => $student->birthplace,
                'age' => $student->age,
                'psaBirthCertNo' => $student->psaBirthCertNo,
                'motherTongue' => $student->motherTongue,
                'profilePhoto' => $student->profilePhoto,
                'photo' => $student->photo,
                'qr_code_path' => $student->qr_code_path,
                'address' => $student->address,
                'currentAddress' => $student->currentAddress,
                'permanentAddress' => $student->permanentAddress,
                'contactInfo' => $student->contactInfo,
                'parentContact' => $student->parentContact,
                'father' => $student->father,
                'mother' => $student->mother,
                'parentName' => $student->parentName,
                'status' => $student->status,
                'enrollmentDate' => $student->enrollmentDate,
                'admissionDate' => $student->admissionDate,
                'requirements' => $student->requirements,
                'isIndigenous' => $student->isIndigenous,
                'indigenousCommunity' => $student->indigenousCommunity,
                'is4PsBeneficiary' => $student->is4PsBeneficiary,
                'householdID' => $student->householdID,
                'hasDisability' => $student->hasDisability,
                'disabilities' => $student->disabilities,
                'isActive' => $student->isActive,
                'is_active' => $student->isActive,
                'created_at' => $student->created_at,
                'updated_at' => $student->updated_at,
            ];
        });
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'gradeLevel' => 'required|string|max:50',
            'section' => 'nullable|string|max:50',
            'studentId' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|string|max:10',
            'birthdate' => 'nullable|date',
            'lrn' => 'nullable|string|max:50',
            'photo' => 'nullable|string',
            'profilePhoto' => 'nullable|string',
            'age' => 'nullable|integer|min:0|max:150',
            'firstName' => 'nullable|string|max:100',
            'middleName' => 'nullable|string|max:100',
            'lastName' => 'nullable|string|max:100',
            'extensionName' => 'nullable|string|max:20',
            'contactInfo' => 'nullable|string|max:255',
            'parentContact' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'status' => 'nullable|string|max:50',
            'enrollmentDate' => 'nullable|date',
            'admissionDate' => 'nullable|date'
        ]);

        $data = $request->all();

        // Set default placeholder photo if no photo provided
        if (!$request->has('photo') || !$request->photo) {
            $data['photo'] = '/demo/images/student-photo.jpg';
            $data['profilePhoto'] = '/demo/images/student-photo.jpg';
        }

        // Handle photo upload if base64 data is provided
        if ($request->has('photo') && $request->photo && strpos($request->photo, 'data:image') === 0) {
            $photoPath = $this->saveBase64Image($request->photo, 'photos');
            $data['profilePhoto'] = $photoPath;
            $data['photo'] = $photoPath;
            Log::info('Photo saved to: ' . $photoPath);
        }

        // Generate and save QR code
        if ($request->has('lrn') && $request->lrn) {
            try {
                // Use studentId if provided, otherwise use LRN as fallback
                $studentIdForQR = $request->studentId ?: $request->lrn;
                $qrPath = $this->generateAndSaveQRCode($request->lrn, $studentIdForQR);
                $data['qr_code_path'] = $qrPath;
                Log::info('QR code saved to: ' . $qrPath . ' for LRN: ' . $request->lrn);
            } catch (\Exception $e) {
                Log::error('QR code generation error for LRN ' . $request->lrn . ': ' . $e->getMessage());
                Log::error('QR code generation stack trace: ' . $e->getTraceAsString());
                // Continue without QR code if there's an error
            }
        } else {
            Log::info('No LRN provided, skipping QR code generation');
        }

        $student = Student::create($data);
        return response()->json($student, 201);
    }

    public function show(Student $student)
    {
        return $student;
    }

    public function update(Request $request, Student $student)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'gradeLevel' => 'sometimes|required|string|max:50',
            'section' => 'nullable|string|max:50',
            'studentId' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'gender' => 'nullable|string|max:10',
            'birthdate' => 'nullable|date',
            'lrn' => 'nullable|string|max:50',
            'photo' => 'nullable|string',
            'profilePhoto' => 'nullable|string',
            'age' => 'nullable|integer|min:0|max:150',
            'firstName' => 'nullable|string|max:100',
            'middleName' => 'nullable|string|max:100',
            'lastName' => 'nullable|string|max:100',
            'extensionName' => 'nullable|string|max:20',
            'contactInfo' => 'nullable|string|max:255',
            'parentContact' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'status' => 'nullable|string|max:50',
            'enrollmentDate' => 'nullable|date',
            'admissionDate' => 'nullable|date'
        ]);

        $data = $request->all();

        // Handle photo upload if base64 data is provided
        if ($request->has('photo') && $request->photo && strpos($request->photo, 'data:image') === 0) {
            try {
                $photoPath = $this->saveBase64Image($request->photo, 'photos');
                $data['profilePhoto'] = $photoPath;
                $data['photo'] = $photoPath;
                Log::info('Photo updated and saved to: ' . $photoPath);
            } catch (\Exception $e) {
                Log::error('Photo save error: ' . $e->getMessage());
                // Continue without photo if there's an error
                unset($data['photo']);
                unset($data['profilePhoto']);
            }
        }

        // Generate and save QR code if LRN is provided
        if ($request->has('lrn') && $request->lrn) {
            try {
                // Use studentId if provided, otherwise use LRN as fallback
                $studentIdForQR = $request->studentId ?: $request->lrn;
                $qrPath = $this->generateAndSaveQRCode($request->lrn, $studentIdForQR);
                $data['qr_code_path'] = $qrPath;
                Log::info('QR code updated and saved to: ' . $qrPath . ' for LRN: ' . $request->lrn);
            } catch (\Exception $e) {
                Log::error('QR code generation error for LRN ' . $request->lrn . ': ' . $e->getMessage());
                Log::error('QR code generation stack trace: ' . $e->getTraceAsString());
                // Continue without QR code if there's an error
            }
        } else {
            Log::info('No LRN provided, skipping QR code generation');
        }

        $student->update($data);
        return response()->json($student);
    }

    public function destroy(Student $student)
    {
        $student->delete();
        return response()->json(['message' => 'Student deleted']);
    }

    public function byGrade($gradeLevel)
    {
        return Student::where('gradeLevel', $gradeLevel)->get();
    }

    public function bySection($gradeLevel, $section)
    {
        return Student::where('gradeLevel', $gradeLevel)
                      ->where('section', $section)
                      ->get();
    }

    private function saveBase64Image($base64String, $folder = 'uploads')
    {
        // Extract the image data from base64 string
        $image_parts = explode(";base64,", $base64String);
        $image_type_aux = explode("image/", $image_parts[0]);
        $image_type = $image_type_aux[1];
        $image_base64 = base64_decode($image_parts[1]);

        // Generate unique filename
        $fileName = uniqid() . '.' . $image_type;
        $filePath = public_path($folder . '/' . $fileName);

        // Create directory if it doesn't exist
        if (!file_exists(public_path($folder))) {
            mkdir(public_path($folder), 0755, true);
        }

        // Save the image
        file_put_contents($filePath, $image_base64);

        return $folder . '/' . $fileName;
    }

    private function generateAndSaveQRCode($lrn, $studentId = null)
    {
        // Create QR codes directory if it doesn't exist
        $qrDir = public_path('qr-codes');
        if (!file_exists($qrDir)) {
            mkdir($qrDir, 0755, true);
        }

        // Use studentId if available, otherwise use LRN
        $qrData = $studentId ? $studentId : $lrn;
        $fileName = $qrData . '_qr.png';
        $filePath = $qrDir . '/' . $fileName;

        // Delete old QR code if it exists
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        // Generate QR code as PNG image using BaconQrCode
        try {
            $renderer = new ImageRenderer(
                new RendererStyle(300, 2),
                new \BaconQrCode\Renderer\Image\ImagickImageBackEnd()
            );
            $writer = new Writer($renderer);
            $qrCodeString = $writer->writeString($qrData);

            file_put_contents($filePath, $qrCodeString);

            Log::info('QR code generated and saved: ' . $filePath);

            return 'qr-codes/' . $fileName;
        } catch (\Exception $e) {
            Log::error('QR code generation failed: ' . $e->getMessage());

            // Fallback to SVG if PNG fails
            try {
                $fileName = $qrData . '_qr.svg';
                $filePath = $qrDir . '/' . $fileName;

                $renderer = new ImageRenderer(
                    new RendererStyle(300, 2),
                    new SvgImageBackEnd()
                );
                $writer = new Writer($renderer);
                $qrCodeString = $writer->writeString($qrData);

                file_put_contents($filePath, $qrCodeString);

                Log::info('QR code generated as SVG: ' . $filePath);

                return 'qr-codes/' . $fileName;
            } catch (\Exception $svgError) {
                Log::error('SVG QR code generation also failed: ' . $svgError->getMessage());
                throw $e;
            }
        }
    }
}
