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
        
        try {
            // Clear any existing content in input fields first to avoid overlap
            $this->clearInputFields($worksheet);
            
            // Based on photo2, target the exact input field boxes (merged cells)
            // These are the actual input field coordinates from the template
            
            // First row input fields (around row 6-7)
            // School ID input box (appears to be around column C-D)
            $this->setValueInInputBox($worksheet, 'C6', '123456', 'School ID');
            
            // School Year input box (appears to be around column F-G)
            $this->setValueInInputBox($worksheet, 'F6', '2024-2025', 'School Year');
            
            // Report for the Month of input box (appears to be around column J-K)
            $monthFormatted = strtoupper(Carbon::createFromFormat('Y-m', $month)->format('F Y'));
            $this->setValueInInputBox($worksheet, 'J6', $monthFormatted, 'Report Month');
            
            // Second row input fields (around row 8-9)
            // Name of School input box (appears to be a wider merged cell around column C-F)
            $this->setValueInInputBox($worksheet, 'C8', 'Naawan CS', 'School Name');
            
            // Grade Level input box (appears to be around column H-I)
            $gradeLevel = $section->grade_level ?? 'Kinder';
            $this->setValueInInputBox($worksheet, 'H8', $gradeLevel, 'Grade Level');
            
            // Section input box (appears to be around column K-L)
            $sectionName = $section->name ?? 'Matatag';
            $this->setValueInInputBox($worksheet, 'K8', $sectionName, 'Section');
            
        } catch (\Exception $e) {
            Log::error("Error populating school info: " . $e->getMessage());
            
            // Fallback to scanning method
            $this->populateFieldByText($worksheet, 'School ID', '123456');
            $this->populateFieldByText($worksheet, 'School Year', '2024-2025');
            $this->populateFieldByText($worksheet, 'Report for the Month of', strtoupper(Carbon::createFromFormat('Y-m', $month)->format('F Y')));
            $this->populateFieldByText($worksheet, 'Name of School', 'Naawan Elementary School');
            $this->populateFieldByText($worksheet, 'Grade Level', $section->grade_level ?? 'Kinder');
            $this->populateFieldByText($worksheet, 'Section', $section->name ?? 'Matatag');
        }
    }
    
    /**
     * Clear input fields and background cells to avoid text overlap
     */
    private function clearInputFields($worksheet)
    {
        try {
            // Clear all potential cells that might contain overlapping data
            $cellsToClear = [
                // Row 5-10 to cover all possible school info areas
                'B5', 'C5', 'D5', 'E5', 'F5', 'G5', 'H5', 'I5', 'J5', 'K5', 'L5', 'M5', 'N5',
                'B6', 'C6', 'D6', 'E6', 'F6', 'G6', 'H6', 'I6', 'J6', 'K6', 'L6', 'M6', 'N6',
                'B7', 'C7', 'D7', 'E7', 'F7', 'G7', 'H7', 'I7', 'J7', 'K7', 'L7', 'M7', 'N7',
                'B8', 'C8', 'D8', 'E8', 'F8', 'G8', 'H8', 'I8', 'J8', 'K8', 'L8', 'M8', 'N8',
                'B9', 'C9', 'D9', 'E9', 'F9', 'G9', 'H9', 'I9', 'J9', 'K9', 'L9', 'M9', 'N9',
                'B10', 'C10', 'D10', 'E10', 'F10', 'G10', 'H10', 'I10', 'J10', 'K10', 'L10', 'M10', 'N10'
            ];
            
            foreach ($cellsToClear as $cell) {
                try {
                    $currentValue = $worksheet->getCell($cell)->getValue();
                    // Clear any text that looks like our data or placeholder text
                    if ($this->shouldClearCell($currentValue)) {
                        $worksheet->setCellValue($cell, '');
                        Log::info("Cleared cell {$cell}: {$currentValue}");
                    }
                } catch (\Exception $e) {
                    // Continue if cell doesn't exist
                    continue;
                }
            }
            
            Log::info("Cleared all potential overlapping cells");
        } catch (\Exception $e) {
            Log::error("Error clearing input fields: " . $e->getMessage());
        }
    }

    /**
     * Determine if a cell should be cleared
     */
    private function shouldClearCell($value)
    {
        if (empty($value)) {
            return false; // Don't clear already empty cells
        }
        
        if (!is_string($value)) {
            return false; // Don't clear non-string values
        }
        
        // Clear if it matches our data patterns
        $dataPatterns = [
            '123456', '2024-2025', 'SEPTEMBER', 'Naawan', 'Kinder', 'Matatag',
            'School ID', 'School Year', 'Report for', 'Name of School', 'Grade Level', 'Section'
        ];
        
        foreach ($dataPatterns as $pattern) {
            if (stripos($value, $pattern) !== false) {
                return true;
            }
        }
        
        // Clear if it looks like placeholder text
        return $this->isPlaceholderText($value);
    }

    /**
     * Set value in the specific input box cell, trying multiple positions if needed
     */
    private function setValueInInputBox($worksheet, $primaryCell, $value, $fieldName)
    {
        try {
            // Define alternative cells based on the primary cell
            $alternativeCells = $this->getAlternativeCells($primaryCell);
            
            // Try primary cell first
            $allCells = array_merge([$primaryCell], $alternativeCells);
            
            foreach ($allCells as $cell) {
                try {
                    $currentValue = $worksheet->getCell($cell)->getValue();
                    
                    // Check if this looks like an input field (empty, has border, or placeholder text)
                    if ($this->isInputFieldCell($worksheet, $cell, $currentValue)) {
                        $worksheet->setCellValue($cell, $value);
                        Log::info("Set {$fieldName} to {$cell}: {$value}");
                        return true;
                    }
                } catch (\Exception $e) {
                    // Continue to next cell if this one fails
                    continue;
                }
            }
            
            // If no suitable cell found, force use primary cell
            $worksheet->setCellValue($primaryCell, $value);
            Log::info("Set {$fieldName} to {$primaryCell} (forced): {$value}");
            return true;
            
        } catch (\Exception $e) {
            Log::error("Error setting {$fieldName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get alternative cells based on primary cell position
     */
    private function getAlternativeCells($primaryCell)
    {
        $column = substr($primaryCell, 0, 1);
        $row = substr($primaryCell, 1);
        
        $alternatives = [];
        
        // Try adjacent columns (left and right)
        if ($column > 'A') {
            $alternatives[] = chr(ord($column) - 1) . $row;
        }
        if ($column < 'Z') {
            $alternatives[] = chr(ord($column) + 1) . $row;
            if (chr(ord($column) + 1) < 'Z') {
                $alternatives[] = chr(ord($column) + 2) . $row;
            }
        }
        
        // Try adjacent rows (up and down)
        if ($row > 1) {
            $alternatives[] = $column . ($row - 1);
        }
        $alternatives[] = $column . ($row + 1);
        $alternatives[] = $column . ($row + 2);
        
        return $alternatives;
    }

    /**
     * Check if a cell looks like an input field
     */
    private function isInputFieldCell($worksheet, $cell, $currentValue)
    {
        try {
            // Cell is good if it's empty
            if (empty($currentValue)) {
                return true;
            }
            
            // Cell is good if it has placeholder text
            if ($this->isPlaceholderText($currentValue)) {
                return true;
            }
            
            // Cell is good if it's a short text that looks like a placeholder
            if (is_string($currentValue) && strlen($currentValue) < 20) {
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Try alternative cell positions for school information
     */
    private function tryAlternativeCells($worksheet, $section, $month)
    {
        $monthFormatted = strtoupper(Carbon::createFromFormat('Y-m', $month)->format('F Y'));
        $gradeLevel = $section->grade_level ?? 'Kinder';
        $sectionName = $section->name ?? 'Matatag';
        
        // Try different possible cell positions based on photo1 structure
        $alternativeCells = [
            // Row 7 alternatives (first row of input fields)
            ['B7', '123456'], ['C7', '123456'], ['D7', '123456'],
            ['E7', '2024-2025'], ['F7', '2024-2025'], ['G7', '2024-2025'],
            ['I7', $monthFormatted], ['J7', $monthFormatted], ['K7', $monthFormatted],
            
            // Row 9 alternatives (second row of input fields)
            ['B9', 'Naawan CS'], ['C9', 'Naawan CS'], ['D9', 'Naawan CS'], ['E9', 'Naawan CS'], ['F9', 'Naawan CS'],
            ['G9', $gradeLevel], ['H9', $gradeLevel], ['I9', $gradeLevel],
            ['J9', $sectionName], ['K9', $sectionName], ['L9', $sectionName],
            
            // Row 8 alternatives (in case there's a middle row)
            ['C8', '123456'], ['F8', '2024-2025'], ['J8', $monthFormatted],
        ];
        
        foreach ($alternativeCells as [$cell, $value]) {
            try {
                $currentValue = $worksheet->getCell($cell)->getValue();
                if (empty($currentValue)) {
                    $worksheet->setCellValue($cell, $value);
                    Log::info("Set alternative cell {$cell}: {$value}");
                }
            } catch (\Exception $e) {
                // Continue to next cell if this one fails
                continue;
            }
        }
    }

    /**
     * Find and populate a field by searching for label text
     */
    private function populateFieldByText($worksheet, $labelText, $value)
    {
        try {
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            
            // Search for the label text in the first 20 rows
            for ($row = 1; $row <= min(20, $highestRow); $row++) {
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $cellValue = $worksheet->getCell($col . $row)->getValue();
                    
                    if (is_string($cellValue) && strpos($cellValue, $labelText) !== false) {
                        // Found the label, now find the input field
                        // Try the next few cells to the right
                        for ($inputCol = chr(ord($col) + 1); $inputCol <= chr(ord($col) + 5); $inputCol++) {
                            if ($inputCol > 'Z') break; // Simple check for single letter columns
                            
                            $inputCell = $inputCol . $row;
                            $inputValue = $worksheet->getCell($inputCell)->getValue();
                            
                            // If cell is empty or has placeholder text, populate it
                            if (empty($inputValue) || $this->isPlaceholderText($inputValue)) {
                                $worksheet->setCellValue($inputCell, $value);
                                Log::info("Populated {$labelText} in cell {$inputCell} with value: {$value}");
                                return true;
                            }
                        }
                        
                        // Also try the same row but different columns (for horizontal layouts)
                        $nextRow = $row + 1;
                        for ($inputCol = 'A'; $inputCol <= chr(ord($col) + 10); $inputCol++) {
                            if ($inputCol > 'Z') break;
                            
                            $inputCell = $inputCol . $nextRow;
                            if ($worksheet->getCell($inputCell)->getValue() === null) {
                                $worksheet->setCellValue($inputCell, $value);
                                Log::info("Populated {$labelText} in cell {$inputCell} with value: {$value}");
                                return true;
                            }
                        }
                    }
                }
            }
            
            Log::warning("Could not find field for: {$labelText}");
            return false;
            
        } catch (\Exception $e) {
            Log::error("Error populating field {$labelText}: " . $e->getMessage());
            return false;
        }
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
                        'name' => 'Naawan Elementary School',
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
