<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Section;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SF2ReportController extends Controller
{
    /**
     * Download SF2 report for a specific section
     */
    public function download($sectionId)
    {
        try {
            // Get section with students
            $section = Section::with(['students', 'teacher'])->findOrFail($sectionId);
            
            // Get current month attendance data
            $currentMonth = Carbon::now()->format('Y-m');
            
            return $this->generateSF2Report($section, $currentMonth);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate SF2 report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download SF2 report for a specific section and month
     */
    public function downloadByMonth($sectionId, $month)
    {
        try {
            \Log::info("SF2 Download Request - Section ID: {$sectionId}, Month: {$month}");
            
            // Load section with students and teacher
            $section = Section::with(['students', 'teacher'])->findOrFail($sectionId);
            \Log::info("Section loaded: " . $section->name);
            
            // Generate and return SF2 report
            return $this->generateSF2Report($section, $month);
            
        } catch (\Exception $e) {
            \Log::error("SF2 Download Error: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to generate SF2 report',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Generate SF2 Excel report
     */
    private function generateSF2Report($section, $month)
    {
        try {
            // Load the SF2 template
            $templatePath = public_path('templates/School Form Attendance Report of Learners.xlsx');
            
            if (!file_exists($templatePath)) {
                throw new \Exception('SF2 template file not found at: ' . $templatePath);
            }

            Log::info("Loading template from: " . $templatePath);
            
            // Load the template
            $spreadsheet = IOFactory::load($templatePath);
            $worksheet = $spreadsheet->getActiveSheet();

            Log::info("Template loaded successfully");

            // Get students with attendance data
            $students = $this->getStudentsWithAttendance($section, $month);
            
            Log::info("Found " . count($students) . " students");
            
            // Populate school information
            $this->populateSchoolInfo($worksheet, $section, $month);
            
            // Populate student data
            $this->populateStudentData($worksheet, $students);
            
            // Populate summary data
            $this->populateSummaryData($worksheet, $students);

            // Generate filename
            $monthName = Carbon::createFromFormat('Y-m', $month)->format('F_Y');
            $filename = "SF2_Daily_Attendance_{$section->name}_{$monthName}.xlsx";

            Log::info("Generating file: " . $filename);

            // Create temporary file path
            $tempFile = storage_path('app/temp/' . uniqid('sf2_') . '.xlsx');
            
            // Ensure temp directory exists
            $tempDir = dirname($tempFile);
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // Save the file
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFile);
            
            Log::info("File saved to: " . $tempFile);
            
            // Verify file was created and has content
            if (!file_exists($tempFile) || filesize($tempFile) == 0) {
                throw new \Exception('Failed to create Excel file');
            }
            
            Log::info("File size: " . filesize($tempFile) . " bytes");
            
            // Return the Excel file as download
            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"'
            ])->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            Log::error("SF2 Generation Error: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Get students with their attendance data for the specified month
     */
    private function getStudentsWithAttendance($section, $month)
    {
        $students = $section->students()->orderBy('gender')->orderBy('lastName')->get();
        
        // Get attendance data for the month
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        
        foreach ($students as $student) {
            // Get attendance records for this student in the specified month
            $attendanceRecords = Attendance::where('student_id', $student->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->get()
                ->keyBy('date');
            
            // Calculate attendance statistics
            $totalDays = 0;
            $presentDays = 0;
            $absentDays = 0;
            $attendanceData = [];
            
            // Generate attendance data for each day of the month
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                // Skip weekends (assuming school days are Monday-Friday)
                if ($currentDate->isWeekday()) {
                    $dateKey = $currentDate->format('Y-m-d');
                    $attendance = $attendanceRecords->get($dateKey);
                    
                    if ($attendance) {
                        $status = $attendance->status; // 'present', 'absent', 'late'
                        $attendanceData[$dateKey] = $status;
                        
                        if ($status === 'present' || $status === 'late') {
                            $presentDays++;
                        } else {
                            $absentDays++;
                        }
                        $totalDays++;
                    } else {
                        // No record means absent
                        $attendanceData[$dateKey] = 'absent';
                        $absentDays++;
                        $totalDays++;
                    }
                }
                $currentDate->addDay();
            }
            
            // Calculate attendance rate
            $attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;
            
            // Add calculated data to student object
            $student->attendance_data = $attendanceData;
            $student->total_present = $presentDays;
            $student->total_absent = $absentDays;
            $student->attendance_rate = $attendanceRate;
        }
        
        return $students;
    }

    /**
     * Populate school information in the template
     */
    private function populateSchoolInfo($worksheet, $section, $month)
    {
        $monthName = Carbon::createFromFormat('Y-m', $month)->format('F Y');
        
        // School information (adjust cell references based on your template)
        $worksheet->setCellValue('B3', 'Kagawasan Elementary School');
        $worksheet->setCellValue('B4', $section->name);
        $worksheet->setCellValue('B5', $section->teacher->name ?? 'N/A');
        $worksheet->setCellValue('B6', $monthName);
        $worksheet->setCellValue('B7', '2024-2025'); // School year
    }

    /**
     * Populate student attendance data in the template
     */
    private function populateStudentData($worksheet, $students)
    {
        $maleStudents = $students->where('gender', 'Male');
        $femaleStudents = $students->where('gender', 'Female');
        
        $currentRow = 10; // Starting row for student data (adjust based on template)
        
        // Male students section
        foreach ($maleStudents as $student) {
            $worksheet->setCellValue("A{$currentRow}", "{$student->lastName}, {$student->firstName} {$student->middleName}");
            
            // Populate daily attendance (columns B to AF for 31 days)
            $col = 'B';
            foreach ($student->attendance_data as $date => $status) {
                $mark = $this->getAttendanceMark($status);
                $worksheet->setCellValue("{$col}{$currentRow}", $mark);
                $col++;
            }
            
            // Summary columns (adjust column letters based on template)
            $worksheet->setCellValue("AG{$currentRow}", $student->total_present);
            $worksheet->setCellValue("AH{$currentRow}", $student->total_absent);
            $worksheet->setCellValue("AI{$currentRow}", $student->attendance_rate . '%');
            
            $currentRow++;
        }
        
        // Female students section (skip a row for section header)
        $currentRow += 2;
        
        foreach ($femaleStudents as $student) {
            $worksheet->setCellValue("A{$currentRow}", "{$student->lastName}, {$student->firstName} {$student->middleName}");
            
            // Populate daily attendance
            $col = 'B';
            foreach ($student->attendance_data as $date => $status) {
                $mark = $this->getAttendanceMark($status);
                $worksheet->setCellValue("{$col}{$currentRow}", $mark);
                $col++;
            }
            
            // Summary columns
            $worksheet->setCellValue("AG{$currentRow}", $student->total_present);
            $worksheet->setCellValue("AH{$currentRow}", $student->total_absent);
            $worksheet->setCellValue("AI{$currentRow}", $student->attendance_rate . '%');
            
            $currentRow++;
        }
    }

    /**
     * Populate summary statistics in the template
     */
    private function populateSummaryData($worksheet, $students)
    {
        $maleStudents = $students->where('gender', 'Male');
        $femaleStudents = $students->where('gender', 'Female');
        
        // Calculate summary statistics
        $maleCount = $maleStudents->count();
        $femaleCount = $femaleStudents->count();
        $totalCount = $students->count();
        
        $malePresent = $maleStudents->sum('total_present');
        $femalePresent = $femaleStudents->sum('total_present');
        $totalPresent = $students->sum('total_present');
        
        $maleAbsent = $maleStudents->sum('total_absent');
        $femaleAbsent = $femaleStudents->sum('total_absent');
        $totalAbsent = $students->sum('total_absent');
        
        $maleAttendanceRate = $maleCount > 0 ? round($maleStudents->avg('attendance_rate'), 1) : 0;
        $femaleAttendanceRate = $femaleCount > 0 ? round($femaleStudents->avg('attendance_rate'), 1) : 0;
        $overallAttendanceRate = $totalCount > 0 ? round($students->avg('attendance_rate'), 1) : 0;
        
        // Populate summary section (adjust cell references based on template)
        // Enrollment data
        $worksheet->setCellValue('M10', $maleCount);     // Male enrollment
        $worksheet->setCellValue('N10', $femaleCount);   // Female enrollment
        $worksheet->setCellValue('O10', $totalCount);    // Total enrollment
        
        // Late enrollment (usually 0 for existing students)
        $worksheet->setCellValue('M11', 0);
        $worksheet->setCellValue('N11', 0);
        $worksheet->setCellValue('O11', 0);
        
        // Registered learners (same as enrollment for this example)
        $worksheet->setCellValue('M12', $maleCount);
        $worksheet->setCellValue('N12', $femaleCount);
        $worksheet->setCellValue('O12', $totalCount);
        
        // Percentage of enrollment (100% for existing students)
        $worksheet->setCellValue('M13', '100%');
        $worksheet->setCellValue('N13', '100%');
        $worksheet->setCellValue('O13', '100%');
        
        // Average daily attendance
        $worksheet->setCellValue('M14', $maleAttendanceRate . '%');
        $worksheet->setCellValue('N14', $femaleAttendanceRate . '%');
        $worksheet->setCellValue('O14', $overallAttendanceRate . '%');
        
        // Percentage of attendance for the month
        $worksheet->setCellValue('M15', $maleAttendanceRate . '%');
        $worksheet->setCellValue('N15', $femaleAttendanceRate . '%');
        $worksheet->setCellValue('O15', $overallAttendanceRate . '%');
        
        // Students absent for 5 consecutive days (would need additional logic)
        $worksheet->setCellValue('M16', 0);
        $worksheet->setCellValue('N16', 0);
        $worksheet->setCellValue('O16', 0);
        
        // Dropouts, transfers (would need status tracking)
        $worksheet->setCellValue('M17', 0); // Male dropouts
        $worksheet->setCellValue('N17', 0); // Female dropouts
        $worksheet->setCellValue('O17', 0); // Total dropouts
        
        $worksheet->setCellValue('M18', 0); // Male transferred out
        $worksheet->setCellValue('N18', 0); // Female transferred out
        $worksheet->setCellValue('O18', 0); // Total transferred out
        
        $worksheet->setCellValue('M19', 0); // Male transferred in
        $worksheet->setCellValue('N19', 0); // Female transferred in
        $worksheet->setCellValue('O19', 0); // Total transferred in
    }

    /**
     * Convert attendance status to display mark
     */
    private function getAttendanceMark($status)
    {
        switch ($status) {
            case 'present':
                return '✓';
            case 'absent':
                return '✗';
            case 'late':
                return 'L';
            default:
                return '-';
        }
    }
}
