<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class StudentController extends Controller
{
    public function index()
    {
        return Student::all();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'gradelevel' => 'required|string',
            'section' => 'required|string',
            'studentid' => 'nullable|string|unique:students',
            'student_id' => 'nullable|string|unique:students',
            'email' => 'nullable|email',
            'gender' => 'nullable|string',
            'birthdate' => 'nullable|date',
            'lrn' => 'nullable|string',
            'photo' => 'nullable|string',
            'profilephoto' => 'nullable|string'
        ]);

        $data = $request->all();
        
        // Handle photo upload if base64 data is provided
        if ($request->has('photo') && $request->photo && strpos($request->photo, 'data:image') === 0) {
            $photoPath = $this->saveBase64Image($request->photo, 'photos');
            $data['profilephoto'] = $photoPath;
            $data['photo'] = $photoPath;
            \Log::info('Photo saved to: ' . $photoPath);
        }
        
        // Generate and save QR code
        if ($request->has('lrn') && $request->lrn) {
            $qrPath = $this->generateAndSaveQRCode($request->lrn);
            $data['qr_code_path'] = $qrPath;
            \Log::info('QR code saved to: ' . $qrPath);
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
            'name' => 'required|string',
            'gradelevel' => 'required|string',
            'section' => 'required|string',
            'email' => 'nullable|email',
            'gender' => 'nullable|string',
            'birthdate' => 'nullable|date',
            'lrn' => 'nullable|string'
        ]);

        $student->update($request->all());
        return response()->json($student);
    }

    public function destroy(Student $student)
    {
        $student->delete();
        return response()->json(['message' => 'Student deleted']);
    }

    public function byGrade($gradeLevel)
    {
        return Student::where('gradelevel', $gradeLevel)->get();
    }

    public function bySection($gradeLevel, $section)
    {
        return Student::where('gradelevel', $gradeLevel)
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

    private function generateAndSaveQRCode($lrn)
    {
        // Create QR codes directory if it doesn't exist
        $qrDir = public_path('qr-codes');
        if (!file_exists($qrDir)) {
            mkdir($qrDir, 0755, true);
        }
        
        // Generate QR code filename
        $fileName = $lrn . '_qr.png';
        $filePath = $qrDir . '/' . $fileName;
        
        // Generate QR code content with student LRN
        $qrContent = "LRN: " . $lrn;
        
        // Generate and save QR code using SimpleSoftwareIO/simple-qrcode
        QrCode::format('png')
              ->size(200)
              ->margin(1)
              ->generate($qrContent, $filePath);
        
        return 'qr-codes/' . $fileName;
    }
}
