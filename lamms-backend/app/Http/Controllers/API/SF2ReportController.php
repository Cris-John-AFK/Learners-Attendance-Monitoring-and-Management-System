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
            
            // Clear any existing duplicate data first
            $this->clearDuplicateData($worksheet);
            
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
        try {
            Log::info("Starting to populate school info for section: " . $section->name);
            
            // Based on the Excel template image, use exact cell coordinates
            // First row of input fields (around row 6-7)
            $monthFormatted = strtoupper(Carbon::createFromFormat('Y-m', $month)->format('F Y'));
            $gradeLevel = $section->grade_level ?? 'Kinder';
            $sectionName = $section->name ?? 'Matatag';
            
            // Clear the specific input cells first to avoid overlaps
            $inputCells = ['D6', 'K6', 'X6', 'D8', 'X8', 'AC8'];
            foreach ($inputCells as $cell) {
                try {
                    $worksheet->setCellValue($cell, '');
                } catch (\Exception $e) {
                    // Continue if cell doesn't exist
                }
            }
            
            // Populate the exact input field cells based on user's coordinates
            // Row 6 - First row of input fields
            $worksheet->setCellValue('C6', '123456');           // School ID: 6 C D E
            $worksheet->setCellValue('K6', '2024-2025');        // School Year: 6 K L M N O
            $worksheet->setCellValue('X6', $monthFormatted);     // Report for the Month of: 6 X Y Z AA AB AC
            
            // Row 8 - Second row of input fields
            $worksheet->setCellValue('D8', 'Naawan Central School'); // Name of School: 8 B
            $worksheet->setCellValue('X8', $gradeLevel);         // Grade Level: 8 X Y
            $worksheet->setCellValue('AC8', $sectionName);       // Section: 8 AC AD AE AF AG AH
            
            Log::info("Successfully populated school info with exact user coordinates:");
            Log::info("- School ID (C6): 123456");
            Log::info("- School Year (K6): 2024-2025");
            Log::info("- Report Month (X6): {$monthFormatted}");
            Log::info("- School Name (D8): Naawan Central School");
            Log::info("- Grade Level (X8): {$gradeLevel}");
            Log::info("- Section (AC8): {$sectionName}");
            
        } catch (\Exception $e) {
            Log::error("Error populating school info: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            // Fallback to scanning method if exact coordinates fail
            try {
                $this->populateFieldByText($worksheet, 'School ID', '123456');
                $this->populateFieldByText($worksheet, 'School Year', '2024-2025');
                $this->populateFieldByText($worksheet, 'Report for the Month of', $monthFormatted);
                $this->populateFieldByText($worksheet, 'Name of School', 'Naawan Central School');
                $this->populateFieldByText($worksheet, 'Grade Level', $gradeLevel);
                $this->populateFieldByText($worksheet, 'Section', $sectionName);
                Log::info("Fallback population completed");
            } catch (\Exception $fallbackError) {
                Log::error("Fallback population also failed: " . $fallbackError->getMessage());
            }
        }
    }
    
    /**
     * Clear only specific cells that contain duplicate data
     */
    private function clearDuplicateData($worksheet)
    {
        try {
            Log::info("Clearing duplicate data from template");
            
            // Only clear cells in the header area that might have duplicate data
            $rowsToCheck = range(5, 10);  // Limit to header rows only
            $columnsToCheck = range(ord('B'), ord('M'));  // Limit to relevant columns
            
            foreach ($rowsToCheck as $row) {
                foreach ($columnsToCheck as $colOrd) {
                    $col = chr($colOrd);
                    $cell = $col . $row;
                    
                    try {
                        $currentValue = $worksheet->getCell($cell)->getValue();
                        
                        // Only clear if it's an exact match of our data
                        if ($this->containsOurData($currentValue)) {
                            $worksheet->setCellValue($cell, '');
                            Log::info("Cleared duplicate data from {$cell}: {$currentValue}");
                        }
                    } catch (\Exception $e) {
                        // Continue if cell doesn't exist
                        continue;
                    }
                }
            }
            
            Log::info("Finished clearing duplicate data");
        } catch (\Exception $e) {
            Log::error("Error clearing duplicate data: " . $e->getMessage());
        }
    }

    /**
     * Check if cell contains our data that should be cleared
     */
    private function containsOurData($value)
    {
        if (empty($value) || !is_string($value)) {
            return false;
        }
        
        // Only clear exact matches of our data to avoid clearing template labels
        $ourExactData = [
            '123456',
            '2024-2025',
            'Naawan Central School',
            'Kinder',
            'Matatag'
        ];
        
        $trimmedValue = trim($value);
        
        // Check for exact matches only
        foreach ($ourExactData as $data) {
            if ($trimmedValue === $data) {
                return true;
            }
        }
        
        // Check for month patterns (SEPTEMBER 2025, etc.)
        if (preg_match('/^[A-Z]+ \d{4}$/', $trimmedValue)) {
            return true;
        }
        
        return false;
    }



    /**
     * Find and populate a field by searching for label text
     */
    private function populateFieldByText($worksheet, $labelText, $value)
    {
        try {
            Log::info("Looking for field: {$labelText} with value: {$value}");
            
            $highestRow = min(15, $worksheet->getHighestRow()); // Limit search to first 15 rows
            $highestColumn = $worksheet->getHighestColumn();
            
            // Search for the label text in the template
            for ($row = 1; $row <= $highestRow; $row++) {
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cellValue = $worksheet->getCell($col . $row)->getValue();
                    
                    if (is_string($cellValue) && stripos($cellValue, $labelText) !== false) {
                        Log::info("Found label '{$labelText}' in cell {$col}{$row}");
                        
                        // Found the label, now find the nearest input field
                        $inputCell = $this->findNearestInputCell($worksheet, $col, $row, $labelText);
                        
                        if ($inputCell) {
                            // Clear the cell first to avoid overlaps
                            $worksheet->setCellValue($inputCell, '');
                            // Then set the new value
                            $worksheet->setCellValue($inputCell, $value);
                            Log::info("Successfully populated {$labelText} in cell {$inputCell} with value: {$value}");
                            return true;
                        }
                    }
                }
            }
            
            Log::warning("Could not find suitable input field for: {$labelText}");
            return false;
            
        } catch (\Exception $e) {
            Log::error("Error populating field {$labelText}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find the nearest input cell for a given label
     */
    private function findNearestInputCell($worksheet, $labelCol, $labelRow, $labelText)
    {
        try {
            // Define search patterns based on common form layouts
            $searchCells = [];
            
            // For horizontal layouts (label on left, input on right)
            for ($i = 1; $i <= 8; $i++) {
                $nextCol = chr(ord($labelCol) + $i);
                if ($nextCol <= 'Z') {
                    $searchCells[] = $nextCol . $labelRow;
                }
            }
            
            // For vertical layouts (label on top, input below)
            for ($i = 1; $i <= 3; $i++) {
                $searchCells[] = $labelCol . ($labelRow + $i);
            }
            
            // Try each potential input cell
            foreach ($searchCells as $cell) {
                try {
                    $cellValue = $worksheet->getCell($cell)->getValue();
                    
                    // Check if this looks like an input field
                    if ($this->isValidInputCell($cellValue, $cell)) {
                        Log::info("Found suitable input cell {$cell} for {$labelText}");
                        return $cell;
                    }
                } catch (\Exception $e) {
                    // Continue to next cell if this one fails
                    continue;
                }
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::error("Error finding input cell: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if a cell is suitable for input
     */
    private function isValidInputCell($cellValue, $cellAddress)
    {
        // Cell is good if it's empty
        if (empty($cellValue)) {
            return true;
        }
        
        // Cell is good if it contains placeholder text
        if (is_string($cellValue) && $this->isPlaceholderText($cellValue)) {
            return true;
        }
        
        // Cell is good if it's a short text that might be a placeholder
        if (is_string($cellValue) && strlen(trim($cellValue)) < 30) {
            // Avoid cells that contain form labels
            $avoidPatterns = ['School', 'Grade', 'Section', 'Report', 'Name', 'Year', 'Month'];
            foreach ($avoidPatterns as $pattern) {
                if (stripos($cellValue, $pattern) !== false) {
                    return false;
                }
            }
            return true;
        }
        
        return false;
    }

    /**
     * Check if text appears to be placeholder text
     */
    private function isPlaceholderText($text)
    {
        if (!is_string($text)) return false;
        
        $placeholders = ['[', 'placeholder', 'enter', 'input', 'fill'];
        $text = strtolower($text);
        
        foreach ($placeholders as $placeholder) {
            if (strpos($text, $placeholder) !== false) {
                return true;
            }
        }
        
        return false;
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
     * Get SF2 report data for teacher view (JSON format)
     */
    public function getReportData($sectionId, $month = null)
    {
        try {
            // Use current month if not specified
            if (!$month) {
                $month = Carbon::now()->format('Y-m');
            }
            
            // Get section with students and teacher
            $section = Section::with(['students', 'teacher'])->findOrFail($sectionId);
            
            // Get students with attendance data
            $students = $this->getStudentsWithAttendance($section, $month);
            
            // Calculate summary statistics
            $maleStudents = $students->where('gender', 'Male');
            $femaleStudents = $students->where('gender', 'Female');
            
            $summary = [
                'male' => [
                    'enrollment' => $maleStudents->count(),
                    'total_present' => $maleStudents->sum('total_present'),
                    'total_absent' => $maleStudents->sum('total_absent'),
                    'attendance_rate' => $maleStudents->count() > 0 ? round($maleStudents->avg('attendance_rate'), 1) : 0
                ],
                'female' => [
                    'enrollment' => $femaleStudents->count(),
                    'total_present' => $femaleStudents->sum('total_present'),
                    'total_absent' => $femaleStudents->sum('total_absent'),
                    'attendance_rate' => $femaleStudents->count() > 0 ? round($femaleStudents->avg('attendance_rate'), 1) : 0
                ],
                'total' => [
                    'enrollment' => $students->count(),
                    'total_present' => $students->sum('total_present'),
                    'total_absent' => $students->sum('total_absent'),
                    'attendance_rate' => $students->count() > 0 ? round($students->avg('attendance_rate'), 1) : 0
                ]
            ];
            
            // Get days in month for headers
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
            $daysInMonth = [];
            
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                if ($currentDate->isWeekday()) {
                    $daysInMonth[] = [
                        'date' => $currentDate->format('Y-m-d'),
                        'day' => $currentDate->format('j'),
                        'dayName' => $currentDate->format('D')
                    ];
                }
                $currentDate->addDay();
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'section' => [
                        'id' => $section->id,
                        'name' => $section->name,
                        'grade_level' => $section->grade_level,
                        'teacher' => $section->teacher ? [
                            'name' => $section->teacher->first_name . ' ' . $section->teacher->last_name,
                            'id' => $section->teacher->id
                        ] : null
                    ],
                    'month' => $month,
                    'month_name' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                    'students' => $students->map(function($student) {
                        return [
                            'id' => $student->id,
                            'name' => $student->lastName . ', ' . $student->firstName . ' ' . $student->middleName,
                            'firstName' => $student->firstName,
                            'lastName' => $student->lastName,
                            'middleName' => $student->middleName,
                            'gender' => $student->gender,
                            'attendance_data' => $student->attendance_data,
                            'total_present' => $student->total_present,
                            'total_absent' => $student->total_absent,
                            'attendance_rate' => $student->attendance_rate
                        ];
                    }),
                    'days_in_month' => $daysInMonth,
                    'summary' => $summary,
                    'school_info' => [
                        'name' => 'Naawan Central School',
                        'school_id' => '123456',
                        'school_year' => '2024-2025',
                        'division' => 'REGION X - NORTHERN MINDANAO',
                        'district' => 'NAAWAN DISTRICT'
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("SF2 Report Data Error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to get SF2 report data',
                'message' => $e->getMessage()
            ], 500);
        }
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
