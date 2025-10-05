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
            
            // Populate day headers first
            $this->populateDayHeaders($worksheet, $month);
            
            // Apply wrap text to learner's name header (red boxed area)
            $worksheet->getStyle('B10:B12')->getAlignment()->setWrapText(true);
            
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
        
        // If no students found in section, use sample data for testing
        if ($students->isEmpty()) {
            Log::info("No students found in section {$section->id}, using sample data");
            
            // Create sample student objects
            $sampleStudents = collect([
                (object)[
                    'id' => 1,
                    'firstName' => 'Juan',
                    'lastName' => 'Dela Cruz',
                    'middleName' => 'Santos',
                    'gender' => 'Male',
                    'lrn' => '123456789012'
                ],
                (object)[
                    'id' => 2,
                    'firstName' => 'Maria',
                    'lastName' => 'Cruz',
                    'middleName' => 'Garcia',
                    'gender' => 'Female',
                    'lrn' => '123456789013'
                ],
                (object)[
                    'id' => 3,
                    'firstName' => 'Pedro',
                    'lastName' => 'Martinez',
                    'middleName' => 'Lopez',
                    'gender' => 'Male',
                    'lrn' => '123456789014'
                ],
                (object)[
                    'id' => 4,
                    'firstName' => 'Ana',
                    'lastName' => 'Reyes',
                    'middleName' => 'Flores',
                    'gender' => 'Female',
                    'lrn' => '123456789015'
                ],
                (object)[
                    'id' => 5,
                    'firstName' => 'Carlos',
                    'lastName' => 'Santos',
                    'middleName' => 'Rivera',
                    'gender' => 'Male',
                    'lrn' => '123456789016'
                ],
                (object)[
                    'id' => 6,
                    'firstName' => 'Carmen',
                    'lastName' => 'Gonzales',
                    'middleName' => 'Torres',
                    'gender' => 'Female',
                    'lrn' => '123456789017'
                ]
            ]);
            
            $students = $sampleStudents;
        }
        
        // Get attendance data for the month
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        
        Log::info("Getting attendance data for section {$section->id} from {$startDate} to {$endDate}");
        
        // Get real attendance records from the production system
        $attendanceRecords = [];
        try {
            $records = \DB::table('attendance_sessions as s')
                ->join('attendance_records as r', 's.id', '=', 'r.attendance_session_id')
                ->join('attendance_statuses as st', 'r.attendance_status_id', '=', 'st.id')
                ->where('s.section_id', $section->id)
                ->whereBetween('s.session_date', [$startDate, $endDate])
                ->where('r.is_current_version', true)
                ->select([
                    's.session_date',
                    'r.student_id',
                    'st.name as status_name',
                    'st.code as status_code'
                ])
                ->get();
                
            // Group by student and date
            foreach ($records as $record) {
                $attendanceRecords[$record->student_id][$record->session_date] = [
                    'status_name' => $record->status_name,
                    'status_code' => $record->status_code
                ];
            }
            
            Log::info("Found " . count($records) . " real attendance records");
            
            // Log all unique status names for debugging
            $uniqueStatuses = $records->pluck('status_name')->unique();
            Log::info("Unique attendance statuses found: " . $uniqueStatuses->implode(', '));
            
        } catch (\Exception $e) {
            Log::info("Error fetching real attendance data: " . $e->getMessage());
        }
        
        // Get saved SF2 edits for this section and month
        $sf2Edits = [];
        try {
            $edits = \DB::table('sf2_attendance_edits')
                ->where('section_id', $section->id)
                ->where('month', $month)
                ->get();
                
            foreach ($edits as $edit) {
                $sf2Edits[$edit->student_id][$edit->date] = $edit->status;
            }
            
            Log::info("Found " . count($edits) . " SF2 attendance edits", [
                'section_id' => $section->id,
                'month' => $month,
                'edits' => $edits->toArray()
            ]);
        } catch (\Exception $e) {
            Log::info("Error fetching SF2 edits: " . $e->getMessage());
        }
        
        Log::info("Processing students for SF2", [
            'section_id' => $section->id,
            'month' => $month,
            'student_count' => $students->count(),
            'student_ids' => $students->pluck('id')->toArray()
        ]);
        
        foreach ($students as $student) {
            // Calculate attendance statistics
            $totalDays = 0;
            $presentDays = 0;
            $absentDays = 0;
            $lateDays = 0;
            $attendanceData = [];
            
            // Generate attendance data for each day of the month
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                // Skip weekends (assuming school days are Monday-Friday)
                if ($currentDate->isWeekday()) {
                    $dateKey = $currentDate->format('Y-m-d');
                    
                    // Check if there's a saved SF2 edit for this student and date
                    $sf2Edit = $sf2Edits[$student->id][$dateKey] ?? null;
                    
                    if ($sf2Edit) {
                        // Use SF2 edit (manual override) - highest priority
                        $status = $sf2Edit;
                        Log::info("Student {$student->id} on {$dateKey}: Using SF2 edit -> {$status}");
                    } else {
                        // Use original attendance data
                        $studentAttendance = $attendanceRecords[$student->id][$dateKey] ?? null;
                        
                        if ($studentAttendance) {
                            // Use real attendance data and map to simple status
                            $statusName = strtolower($studentAttendance['status_name']);
                            
                            if (in_array($statusName, ['present', 'on time'])) {
                                $status = 'present';
                            } elseif (in_array($statusName, ['late', 'tardy', 'warning'])) {
                                $status = 'late';
                            } elseif (in_array($statusName, ['excused'])) {
                                $status = 'present'; // Treat excused as present for SF2
                            } else {
                                $status = 'absent';
                            }
                            
                            Log::info("Student {$student->id} on {$dateKey}: {$statusName} -> {$status}");
                        } else {
                            // No attendance record means absent (no session was held or student was not marked)
                            $status = 'absent';
                        }
                    }
                    
                    // Count the status for statistics
                    if ($status === 'present') {
                        $presentDays++;
                    } elseif ($status === 'late') {
                        $lateDays++;
                        $presentDays++; // Late is still considered present for totals
                    } else {
                        $absentDays++;
                    }
                    
                    $attendanceData[$dateKey] = $status;
                    $totalDays++;
                }
                $currentDate->addDay();
            }
            
            // Calculate attendance rate
            $attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;
            
            // Add calculated data to student object
            $student->attendance_data = $attendanceData;
            $student->total_present = $presentDays;
            $student->total_absent = $absentDays;
            $student->total_late = $lateDays;
            $student->attendance_rate = $attendanceRate;
            
            Log::info("Student {$student->firstName} {$student->lastName}: Present: {$presentDays}, Absent: {$absentDays}, Late: {$lateDays}");
        }
        
        return $students;
    }

    /**
     * Generate sample attendance status for testing when no real data exists
     */
    private function generateSampleAttendanceStatus($studentId, $date)
    {
        // Create different attendance patterns for different students
        $patterns = [
            1 => ['present' => 85, 'late' => 10, 'absent' => 5],     // Good student
            2 => ['present' => 90, 'late' => 5, 'absent' => 5],      // Excellent student  
            3 => ['present' => 75, 'late' => 15, 'absent' => 10],    // Average student
            4 => ['present' => 80, 'late' => 12, 'absent' => 8],     // Good student
        ];
        
        $pattern = $patterns[$studentId] ?? ['present' => 80, 'late' => 10, 'absent' => 10];
        
        // Use date as seed for consistent results
        $seed = (int)$date->format('Ymd') + $studentId;
        srand($seed);
        $random = rand(1, 100);
        
        if ($random <= $pattern['present']) {
            return 'present';
        } elseif ($random <= $pattern['present'] + $pattern['late']) {
            return 'late';
        } else {
            return 'absent';
        }
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
     * Populate student attendance data in the template matching exact SF2 format
     */
    private function populateStudentData($worksheet, $students)
    {
        try {
            Log::info("Starting to populate student data in SF2 format");
            
            $maleStudents = $students->where('gender', 'Male');
            $femaleStudents = $students->where('gender', 'Female');
            
            // Student data should start after the day headers (rows 11-12)
            // Row 13 will be the MALE header, students start at row 14
            $currentRow = 13;
            
            // Add MALE section header at row 13
            $worksheet->setCellValue("A{$currentRow}", "MALE");
            $worksheet->setCellValue("B{$currentRow}", "(Last Name, First Name, Middle Name)");
            $currentRow++;
            
            // Male students section
            $maleIndex = 1;
            foreach ($maleStudents as $student) {
                // Column A: Student number
                $worksheet->setCellValue("A{$currentRow}", $maleIndex);
                
                // Column B: Student name
                $worksheet->setCellValue("B{$currentRow}", "{$student->lastName}, {$student->firstName} {$student->middleName}");
                
                // Populate daily attendance starting from column C (day 1) to column AG (day 31)
                $this->populateDailyAttendance($worksheet, $student, $currentRow);
                
                // Summary columns at the end (shifted one column right)
                $worksheet->setCellValue("AC{$currentRow}", $student->total_absent);   // ABSENT
                
                $worksheet->setCellValue("AD{$currentRow}", 0);                       // TARDY
                
                $currentRow++;
                $maleIndex++;
            }
            
            // Add MALE TOTAL Per Day at exactly row 35 (A35)
            $this->addMaleTotalRow($worksheet, $maleStudents, 35);
            
            // Set FEMALE section to start directly at row 36 (no header)
            $currentRow = 36;
            
            // Female students section - start numbering from 1
            $femaleIndex = 1; // Start female numbering from 1
            foreach ($femaleStudents as $student) {
                // Column A: Student number
                $worksheet->setCellValue("A{$currentRow}", $femaleIndex);
                
                // Column B: Student name
                $worksheet->setCellValue("B{$currentRow}", "{$student->lastName}, {$student->firstName} {$student->middleName}");
                
                // Populate daily attendance
                $this->populateDailyAttendance($worksheet, $student, $currentRow);
                
                // Summary columns (shifted one column right)
                $worksheet->setCellValue("AC{$currentRow}", $student->total_absent);   // ABSENT
                
                $worksheet->setCellValue("AD{$currentRow}", 0);                       // TARDY
                
                $currentRow++;
                $femaleIndex++;
            }
            
            // Add FEMALE TOTAL Per Day at existing row 60 (use existing template row)
            $this->addFemaleTotalRow($worksheet, $femaleStudents, 61);
            
            // Add Combined TOTAL PER DAY at existing row 61 (use existing template row)
            $this->addCombinedTotalRow($worksheet, $students, 62);
            
            // Apply center alignment to summary columns (ABSENT, TARDY, PRESENT)
            $this->applyCenterAlignmentToSummaryColumns($worksheet);
            
            // Apply vertical text and center alignment to total rows
            $this->applyVerticalTextToTotalRows($worksheet);
            
            Log::info("Successfully populated " . count($students) . " students in SF2 format with all totals");
            
        } catch (\Exception $e) {
            Log::error("Error populating student data: " . $e->getMessage());
        }
    }
    
    /**
     * Add MALE TOTAL Per Day row at exactly row 35 (A35)
     */
    private function addMaleTotalRow($worksheet, $maleStudents, $row)
    {
        try {
            Log::info("Adding MALE TOTAL Per Day at row {$row}");
            
            // Set exactly at A35: "MALE | TOTAL Per Day"
            $worksheet->setCellValue("A{$row}", "MALE | TOTAL Per Day");
            
            // Get the month from the first student's attendance data to calculate daily totals
            if ($maleStudents->count() > 0) {
                $firstStudent = $maleStudents->first();
                if (!empty($firstStudent->attendance_data)) {
                    $firstDate = array_keys($firstStudent->attendance_data)[0];
                    $month = Carbon::createFromFormat('Y-m-d', $firstDate)->format('Y-m');
                    
                    // Build column mapping for weekdays
                    $columnMapping = $this->buildWeekdayColumnMapping($month);
                    
                    // Calculate daily totals for male students
                    $dailyTotals = $this->calculateMaleDailyTotals($maleStudents, $month);
                    
                    // Populate daily totals in each column (D, E, F, G, etc.)
                    foreach ($dailyTotals as $dayNumber => $totals) {
                        if (isset($columnMapping[$dayNumber])) {
                            $column = $columnMapping[$dayNumber];
                            $presentCount = $totals['present'] ?? 0;
                            $worksheet->setCellValue("{$column}{$row}", $presentCount);
                        }
                    }
                    
                    // Summary columns for male totals
                    $totalAbsent = $maleStudents->sum('total_absent');
                    $totalPresent = $maleStudents->sum('total_present');
                    
                     // ABSENT
                    
                    $worksheet->setCellValue("AG{$row}", $totalPresent);  // PRESENT
                    $worksheet->getStyle("AG{$row}")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_GENERAL);
                                 // TARDY
                    
                }
            }
            
            Log::info("Successfully added MALE TOTAL Per Day at A{$row}");
            
        } catch (\Exception $e) {
            Log::error("Error adding MALE TOTAL row: " . $e->getMessage());
        }
    }
    
    /**
     * Add FEMALE TOTAL Per Day row at specified position
     */
    private function addFemaleTotalRow($worksheet, $femaleStudents, $row)
    {
        try {
            Log::info("Adding FEMALE TOTAL Per Day at row {$row}");
            
            // Don't overwrite the existing "FEMALE | TOTAL Per Day" text - it's already in the template
            // Just populate the daily totals and summary columns
            
            // Get the month from the first student's attendance data to calculate daily totals
            if ($femaleStudents->count() > 0) {
                $firstStudent = $femaleStudents->first();
                if (!empty($firstStudent->attendance_data)) {
                    $firstDate = array_keys($firstStudent->attendance_data)[0];
                    $month = Carbon::createFromFormat('Y-m-d', $firstDate)->format('Y-m');
                    
                    // Build column mapping for weekdays
                    $columnMapping = $this->buildWeekdayColumnMapping($month);
                    
                    // Calculate daily totals for female students
                    $dailyTotals = $this->calculateFemaleDailyTotals($femaleStudents, $month);
                    
                    // Populate daily totals in each column (D, E, F, G, etc.)
                    foreach ($dailyTotals as $dayNumber => $totals) {
                        if (isset($columnMapping[$dayNumber])) {
                            $column = $columnMapping[$dayNumber];
                            $presentCount = $totals['present'] ?? 0;
                            $worksheet->setCellValue("{$column}{$row}", $presentCount);
                        }
                    }
                    
                    // Summary columns for female totals
                    $totalAbsent = $femaleStudents->sum('total_absent');
                    $totalPresent = $femaleStudents->sum('total_present');
                    
                    
                    $worksheet->setCellValue("AE{$row}", $totalPresent);  // PRESENT
                    
                  
                }
            }
            
            Log::info("Successfully added FEMALE TOTAL Per Day at A{$row}");
            
        } catch (\Exception $e) {
            Log::error("Error adding FEMALE TOTAL row: " . $e->getMessage());
        }
    }
    
    /**
     * Add Combined TOTAL PER DAY row at specified position
     */
    private function addCombinedTotalRow($worksheet, $allStudents, $row)
    {
        try {
            Log::info("Adding Combined TOTAL PER DAY at row {$row}");
            
            // Don't overwrite the existing "Combined TOTAL PER DAY" text - it's already in the template
            // Just populate the daily totals and summary columns
            
            // Get the month from the first student's attendance data to calculate daily totals
            if ($allStudents->count() > 0) {
                $firstStudent = $allStudents->first();
                if (!empty($firstStudent->attendance_data)) {
                    $firstDate = array_keys($firstStudent->attendance_data)[0];
                    $month = Carbon::createFromFormat('Y-m-d', $firstDate)->format('Y-m');
                    
                    // Build column mapping for weekdays
                    $columnMapping = $this->buildWeekdayColumnMapping($month);
                    
                    // Calculate daily totals for all students combined
                    $dailyTotals = $this->calculateCombinedDailyTotals($allStudents, $month);
                    
                    // Populate daily totals in each column (D, E, F, G, etc.)
                    foreach ($dailyTotals as $dayNumber => $totals) {
                        if (isset($columnMapping[$dayNumber])) {
                            $column = $columnMapping[$dayNumber];
                            $presentCount = $totals['present'] ?? 0;
                            $worksheet->setCellValue("{$column}{$row}", $presentCount);
                        }
                    }
                    
                    // Summary columns for combined totals
                    $totalAbsent = $allStudents->sum('total_absent');
                    $totalPresent = $allStudents->sum('total_present');
                    
                    $worksheet->setCellValue("AE{$row}", $totalPresent);  // PRESENT
                  
                   
                }
            }
            
            Log::info("Successfully added Combined TOTAL PER DAY at A{$row}");
            
        } catch (\Exception $e) {
            Log::error("Error adding Combined TOTAL row: " . $e->getMessage());
        }
    }

    /**
     * Calculate daily totals for male students only
     */
    private function calculateMaleDailyTotals($maleStudents, $month)
    {
        try {
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
            
            $dailyTotals = [];
            
            // Loop through each day of the month
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                if ($currentDate->isWeekday()) {
                    $dayNumber = $currentDate->day;
                    $dateKey = $currentDate->format('Y-m-d');
                    
                    $presentCount = 0;
                    $absentCount = 0;
                    $lateCount = 0;
                    
                    // Count attendance for male students only on this day
                    foreach ($maleStudents as $student) {
                        $status = $student->attendance_data[$dateKey] ?? 'absent';
                        
                        switch ($status) {
                            case 'present':
                                $presentCount++;
                                break;
                            case 'absent':
                                $absentCount++;
                                break;
                            case 'late':
                                $lateCount++;
                                break;
                        }
                    }
                    
                    $dailyTotals[$dayNumber] = [
                        'present' => $presentCount,
                        'absent' => $absentCount,
                        'late' => $lateCount,
                        'total' => $presentCount + $absentCount + $lateCount
                    ];
                }
                
                $currentDate->addDay();
            }
            
            return $dailyTotals;
            
        } catch (\Exception $e) {
            Log::error("Error calculating male daily totals: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate daily totals for female students only
     */
    private function calculateFemaleDailyTotals($femaleStudents, $month)
    {
        try {
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
            
            $dailyTotals = [];
            
            // Loop through each day of the month
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                if ($currentDate->isWeekday()) {
                    $dayNumber = $currentDate->day;
                    $dateKey = $currentDate->format('Y-m-d');
                    
                    $presentCount = 0;
                    $absentCount = 0;
                    $lateCount = 0;
                    
                    // Count attendance for female students only on this day
                    foreach ($femaleStudents as $student) {
                        $status = $student->attendance_data[$dateKey] ?? 'absent';
                        
                        switch ($status) {
                            case 'present':
                                $presentCount++;
                                break;
                            case 'absent':
                                $absentCount++;
                                break;
                            case 'late':
                                $lateCount++;
                                break;
                        }
                    }
                    
                    $dailyTotals[$dayNumber] = [
                        'present' => $presentCount,
                        'absent' => $absentCount,
                        'late' => $lateCount,
                        'total' => $presentCount + $absentCount + $lateCount
                    ];
                }
                
                $currentDate->addDay();
            }
            
            return $dailyTotals;
            
        } catch (\Exception $e) {
            Log::error("Error calculating female daily totals: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate daily totals for all students combined
     */
    private function calculateCombinedDailyTotals($allStudents, $month)
    {
        try {
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
            
            $dailyTotals = [];
            
            // Loop through each day of the month
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                if ($currentDate->isWeekday()) {
                    $dayNumber = $currentDate->day;
                    $dateKey = $currentDate->format('Y-m-d');
                    
                    $presentCount = 0;
                    $absentCount = 0;
                    $lateCount = 0;
                    
                    // Count attendance for all students on this day
                    foreach ($allStudents as $student) {
                        $status = $student->attendance_data[$dateKey] ?? 'absent';
                        
                        switch ($status) {
                            case 'present':
                                $presentCount++;
                                break;
                            case 'absent':
                                $absentCount++;
                                break;
                            case 'late':
                                $lateCount++;
                                break;
                        }
                    }
                    
                    $dailyTotals[$dayNumber] = [
                        'present' => $presentCount,
                        'absent' => $absentCount,
                        'late' => $lateCount,
                        'total' => $presentCount + $absentCount + $lateCount
                    ];
                }
                
                $currentDate->addDay();
            }
            
            return $dailyTotals;
            
        } catch (\Exception $e) {
            Log::error("Error calculating combined daily totals: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Populate day headers in the calendar format matching the SF2 template
     */
    private function populateDayHeaders($worksheet, $month)
    {
        try {
            Log::info("Populating consecutive weekday headers for month: {$month}");
            
            // Get the actual month and year
            $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
            
            // Sequential columns starting from D (no gaps for weekends)
            $columns = ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH'];
            
            $columnIndex = 0;
            $currentDate = $startDate->copy();
            
            // Find what day of week the 1st falls on and adjust starting position
            $firstDayOfWeek = $startDate->dayOfWeek; // 0=Sunday, 1=Monday, 2=Tuesday, etc.
            
            // Adjust column index based on what day the 1st falls on
            // If 1st is Monday (1), start at column 0
            // If 1st is Tuesday (2), start at column 1  
            // If 1st is Wednesday (3), start at column 2
            // If 1st is Thursday (4), start at column 3
            // If 1st is Friday (5), start at column 4
            // If 1st is Saturday (6) or Sunday (0), find next Monday
            
            if ($firstDayOfWeek == 0) { // Sunday - move to Monday
                $currentDate->addDay();
                $columnIndex = 0;
            } elseif ($firstDayOfWeek == 6) { // Saturday - move to Monday  
                $currentDate->addDays(2);
                $columnIndex = 0;
            } else {
                // Weekday - adjust column position based on day of week
                $columnIndex = $firstDayOfWeek - 1; // Monday=0, Tuesday=1, Wednesday=2, Thursday=3, Friday=4
            }
            
            Log::info("First day of month falls on: " . $startDate->format('l') . " (day {$firstDayOfWeek})");
            Log::info("Starting at column index: {$columnIndex}");
            
            // Loop through each day of the month and assign to proper week positions
            while ($currentDate <= $endDate && $columnIndex < count($columns)) {
                // Only process weekdays (Monday to Friday)
                if ($currentDate->isWeekday()) {
                    $column = $columns[$columnIndex];
                    $dayNumber = $currentDate->day;
                    
                    // Row 11: Weekday numbers in proper calendar week positions
                    $worksheet->setCellValue("{$column}11", $dayNumber);
                    
                    Log::info("Set weekday {$dayNumber} ({$currentDate->format('D')}) in column {$column} (position {$columnIndex})");
                    
                    $columnIndex++;
                }
                
                $currentDate->addDay();
            }
            
            Log::info("Successfully populated {$columnIndex} weekday positions for {$month}");
            Log::info("Total columns available: " . count($columns));
            
            // Debug: Show which columns were used
            for ($i = 0; $i < $columnIndex && $i < count($columns); $i++) {
                Log::info("Column {$i}: {$columns[$i]}");
            }
            
            // Fill ALL columns with proper M T W TH F pattern like Picture 2
            // Summary columns are AC (ABSENT), AD (TARDY), AE, AF, AG, AH, AI, AJ, AK (PRESENT) - don't overwrite these
            $summaryColumns = ['AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK']; // Columns to avoid
            
            Log::info("Filling ALL columns with M T W TH F pattern like Picture 2");
            
            // Override ALL columns with M T W TH F pattern (like Picture 2)
            $dayPattern = ['M', 'T', 'W', 'TH', 'F'];
            
            for ($i = 0; $i < count($columns); $i++) {
                $column = $columns[$i];
                
                // Skip if this is a summary column
                if (in_array($column, $summaryColumns)) {
                    Log::info("Skipping summary column: {$column}");
                    continue;
                }
                
                // Use column index for consistent M T W TH F M T W TH F pattern
                $dayAbbrev = $dayPattern[$i % 5];
                
                // Only set day abbreviation (row 12), keep existing day numbers if they exist
                $worksheet->setCellValue("{$column}12", $dayAbbrev);
                
                // Apply same formatting
                $cellStyle = $worksheet->getStyle("{$column}12");
                $cellStyle->getAlignment()->setTextRotation(255);
                $cellStyle->getAlignment()->setWrapText(true);
                
                Log::info("Set column {$column} (index {$i}) with pattern day: {$dayAbbrev}");
            }
            
        } catch (\Exception $e) {
            Log::error("Error populating day headers: " . $e->getMessage());
        }
    }
    
    /**
     * Get day of week abbreviation
     */
    private function getDayOfWeekAbbreviation($dayOfWeek)
    {
        $abbreviations = [
            0 => 'S',   // Sunday
            1 => 'M',   // Monday
            2 => 'T',   // Tuesday
            3 => 'W',   // Wednesday
            4 => 'TH',  // Thursday
            5 => 'F',   // Friday
            6 => 'S'    // Saturday
        ];
        
        return $abbreviations[$dayOfWeek] ?? 'U';
    }
    
    /**
     * Populate daily attendance for a student across all days of the month
     */
    private function populateDailyAttendance($worksheet, $student, $row)
    {
        try {
            // Get the month from the first attendance record to determine the month context
            if (empty($student->attendance_data)) {
                return;
            }
            
            // Get month from first attendance record
            $firstDate = array_keys($student->attendance_data)[0];
            $month = Carbon::createFromFormat('Y-m-d', $firstDate)->format('Y-m');
            
            // Build dynamic column mapping based on weekdays only
            $columnMapping = $this->buildWeekdayColumnMapping($month);
            
            // Populate attendance for each day
            foreach ($student->attendance_data as $date => $status) {
                $dateObj = Carbon::createFromFormat('Y-m-d', $date);
                
                // Only process weekdays
                if ($dateObj->isWeekday()) {
                    $dayNumber = $dateObj->day;
                    
                    if (isset($columnMapping[$dayNumber])) {
                        $column = $columnMapping[$dayNumber];
                        $mark = $this->getAttendanceMark($status);
                        $worksheet->setCellValue("{$column}{$row}", $mark);
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Error populating daily attendance for student: " . $e->getMessage());
        }
    }
    
    /**
     * Build column mapping for weekdays only - consecutive columns without gaps
     */
    private function buildWeekdayColumnMapping($month)
    {
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        
        // Sequential columns starting from D (same as day headers)
        $columns = ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH'];
        
        $mapping = [];
        $columnIndex = 0;
        $currentDate = $startDate->copy();
        
        // Find what day of week the 1st falls on and adjust starting position (same logic as day headers)
        $firstDayOfWeek = $startDate->dayOfWeek; // 0=Sunday, 1=Monday, 2=Tuesday, etc.
        
        if ($firstDayOfWeek == 0) { // Sunday - move to Monday
            $currentDate->addDay();
            $columnIndex = 0;
        } elseif ($firstDayOfWeek == 6) { // Saturday - move to Monday  
            $currentDate->addDays(2);
            $columnIndex = 0;
        } else {
            // Weekday - adjust column position based on day of week
            $columnIndex = $firstDayOfWeek - 1; // Monday=0, Tuesday=1, Wednesday=2, Thursday=3, Friday=4
        }
        
        // Map each weekday to calendar-week-aligned columns (same as day headers)
        while ($currentDate <= $endDate && $columnIndex < count($columns)) {
            $dayNumber = $currentDate->day;
            
            // Only map weekdays and ensure valid day number
            if ($currentDate->isWeekday() && $dayNumber > 0) {
                $mapping[$dayNumber] = $columns[$columnIndex];
                Log::info("Attendance mapping: day {$dayNumber} ({$currentDate->format('D')}) to column {$columns[$columnIndex]} (position {$columnIndex})");
                $columnIndex++;
            }
            
            $currentDate->addDay();
        }
        
        return $mapping;
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
        $worksheet->setCellValue('AH66', $maleCount);     // Male enrollment
        $worksheet->setCellValue('AI66', $femaleCount);   // Female enrollment
        $worksheet->setCellValue('AJ66', $totalCount);    // Total enrollment
        
        // Late enrollment (usually 0 for existing students)
        $worksheet->setCellValue('AH68', 0);
        $worksheet->setCellValue('AI68', 0);
        $worksheet->setCellValue('AJ68', 0);
        
        // Registered learners (same as enrollment for this example)
        $worksheet->setCellValue('AH70', $maleCount);
        $worksheet->setCellValue('AI70', $femaleCount);
        $worksheet->setCellValue('AJ70', $totalCount);
        
        // Percentage of enrollment (100% for existing students)
        $worksheet->setCellValue('AH72', '100%');
        $worksheet->setCellValue('AI72', '100%');
        $worksheet->setCellValue('AJ72', '100%');
        
        // Average daily attendance
        $worksheet->setCellValue('AH74', $maleAttendanceRate . '%');
        $worksheet->setCellValue('AI74', $femaleAttendanceRate . '%');
        $worksheet->setCellValue('AJ74', $overallAttendanceRate . '%');
        
        // Percentage of attendance for the month
        $worksheet->setCellValue('AH75', $maleAttendanceRate . '%');
        $worksheet->setCellValue('AI75', $femaleAttendanceRate . '%');
        $worksheet->setCellValue('AJ75', $overallAttendanceRate . '%');
        
        // Students absent for 5 consecutive days (would need additional logic)
        $worksheet->setCellValue('AH77', 0);
        $worksheet->setCellValue('AI77', 0);
        $worksheet->setCellValue('AJ77', 0);
        
        // Dropouts, transfers (would need status tracking)
        $worksheet->setCellValue('AH79', 0); // Male dropouts
        $worksheet->setCellValue('AI79', 0); // Female dropouts
        $worksheet->setCellValue('AJ79', 0); // Total dropouts
        
        $worksheet->setCellValue('AH81', 0); // Male transferred out
        $worksheet->setCellValue('AI81', 0); // Female transferred out
        $worksheet->setCellValue('AJ81', 0); // Total transferred out
        
        $worksheet->setCellValue('AH83', 0); // Male transferred in
        $worksheet->setCellValue('AI83', 0); // Female transferred in
        $worksheet->setCellValue('AJ83', 0); // Total transferred in
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
     * Submit SF2 report to admin
     */
    public function submitToAdmin($sectionId, $month)
    {
        try {
            // Get section with teacher information
            $section = Section::with(['teacher'])->findOrFail($sectionId);
            
            // Get currently authenticated teacher
            $authenticatedTeacher = auth('teacher')->user();
            $submittedByTeacherId = $authenticatedTeacher ? $authenticatedTeacher->id : $section->teacher_id;
            
            Log::info("SF2 Submission - Teacher Info", [
                'section_id' => $sectionId,
                'section_name' => $section->name,
                'section_teacher_id' => $section->teacher_id,
                'section_teacher_name' => $section->teacher ? $section->teacher->first_name . ' ' . $section->teacher->last_name : 'N/A',
                'authenticated_teacher_id' => $authenticatedTeacher ? $authenticatedTeacher->id : null,
                'authenticated_teacher_name' => $authenticatedTeacher ? $authenticatedTeacher->first_name . ' ' . $authenticatedTeacher->last_name : 'N/A',
                'submitted_by_will_be' => $submittedByTeacherId
            ]);
            
            // Check if already submitted for this section and month
            $existingSubmission = \DB::table('submitted_sf2_reports')
                ->where('section_id', $sectionId)
                ->where('month', $month)
                ->first();
            
            // Get the actual SF2 report data to store
            $sf2Data = $this->getReportDataForSubmission($sectionId, $month);
            
            Log::info("Storing SF2 submission data", [
                'section_id' => $sectionId,
                'month' => $month,
                'students_count' => count($sf2Data['students']),
                'teacher_id' => $submittedByTeacherId,
                'is_resubmission' => $existingSubmission ? true : false,
                'sample_student_data' => isset($sf2Data['students'][0]) ? $sf2Data['students'][0] : 'No students'
            ]);
            
            if ($existingSubmission) {
                // Update existing submission (allow resubmission)
                \DB::table('submitted_sf2_reports')
                    ->where('id', $existingSubmission->id)
                    ->update([
                        'status' => 'submitted', // Reset to submitted status
                        'submitted_at' => now(), // Update submission time
                        'sf2_data' => json_encode($sf2Data), // Update with latest data
                        'updated_at' => now(),
                        'reviewed_at' => null, // Clear review timestamp
                        'reviewed_by' => null, // Clear reviewer
                        'admin_notes' => null // Clear admin notes
                    ]);
                
                $submissionId = $existingSubmission->id;
                $isResubmission = true;
                
                Log::info("SF2 report resubmitted successfully", [
                    'submission_id' => $submissionId,
                    'section_id' => $sectionId,
                    'month' => $month
                ]);
            } else {
                // Create new submission record
                $submissionId = \DB::table('submitted_sf2_reports')->insertGetId([
                    'section_id' => $sectionId,
                    'section_name' => $section->name,
                    'grade_level' => $section->grade_level ?? 'Kinder',
                    'month' => $month,
                    'month_name' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                    'report_type' => 'SF2',
                    'status' => 'submitted',
                    'submitted_by' => $submittedByTeacherId, // Use authenticated teacher ID
                    'submitted_at' => now(),
                    'sf2_data' => json_encode($sf2Data),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $isResubmission = false;
                
                Log::info("SF2 report submitted successfully with real data", [
                    'submission_id' => $submissionId,
                    'section_id' => $sectionId,
                    'month' => $month,
                    'teacher_id' => $section->teacher_id,
                    'data_size' => strlen(json_encode($sf2Data))
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => $isResubmission 
                    ? 'SF2 report resubmitted successfully to admin' 
                    : 'SF2 report submitted successfully to admin',
                'data' => [
                    'submission_id' => $submissionId,
                    'section_name' => $section->name,
                    'month' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                    'status' => 'submitted',
                    'submitted_at' => now()->toISOString(),
                    'students_count' => count($sf2Data['students']),
                    'is_resubmission' => $isResubmission
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error submitting SF2 report: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get SF2 report data for submission (internal method)
     */
    private function getReportDataForSubmission($sectionId, $month)
    {
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
        
        return [
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
                    'attendance' => $student->attendance_data, // Use 'attendance' key for frontend compatibility
                    'attendance_data' => $student->attendance_data, // Also include attendance_data key for compatibility
                    'totalPresent' => $student->total_present,
                    'totalAbsent' => $student->total_absent,
                    'attendanceRate' => $student->attendance_rate,
                    'status' => 'active'
                ];
            })->values()->toArray(),
            'days_in_month' => $daysInMonth,
            'summary' => $summary,
            'school_info' => [
                'name' => 'Naawan Central School',
                'school_id' => '123456',
                'school_year' => '2024-2025',
                'division' => 'REGION X - NORTHERN MINDANAO',
                'district' => 'NAAWAN DISTRICT'
            ]
        ];
    }

    /**
     * Get all submitted SF2 reports for admin
     */
    public function getSubmittedReports()
    {
        try {
            $reports = \DB::table('submitted_sf2_reports')
                ->leftJoin('teachers', 'submitted_sf2_reports.submitted_by', '=', 'teachers.id')
                ->select(
                    'submitted_sf2_reports.*',
                    'teachers.first_name as teacher_first_name',
                    'teachers.last_name as teacher_last_name'
                )
                ->orderBy('submitted_at', 'desc')
                ->get();

            // Transform the data for frontend
            $transformedReports = $reports->map(function ($report) {
                $teacherName = trim(($report->teacher_first_name ?? '') . ' ' . ($report->teacher_last_name ?? ''));
                
                // Log for debugging
                Log::info("Teacher name for submission", [
                    'submission_id' => $report->id,
                    'submitted_by' => $report->submitted_by,
                    'teacher_first_name' => $report->teacher_first_name,
                    'teacher_last_name' => $report->teacher_last_name,
                    'teacher_name' => $teacherName
                ]);
                
                return [
                    'id' => $report->id,
                    'section_id' => $report->section_id,
                    'section_name' => $report->section_name,
                    'grade_level' => $report->grade_level,
                    'month' => $report->month,
                    'month_name' => $report->month_name,
                    'report_type' => $report->report_type,
                    'status' => $report->status,
                    'teacher_name' => !empty($teacherName) ? $teacherName : 'Unknown Teacher',
                    'submitted_by' => $report->submitted_by,
                    'submitted_at' => $report->submitted_at,
                    'reviewed_at' => $report->reviewed_at,
                    'admin_notes' => $report->admin_notes,
                    'submitted' => $report->status === 'submitted' // For notification badge
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedReports
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching submitted reports: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch submitted reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stored SF2 submission data for admin view
     */
    public function getSubmittedReportData($sectionId, $month)
    {
        try {
            Log::info("Admin fetching submitted SF2 data", [
                'section_id' => $sectionId,
                'month' => $month,
                'request_url' => request()->url()
            ]);
            
            // Find the submitted report for this section and month
            $submittedReport = \DB::table('submitted_sf2_reports')
                ->where('section_id', $sectionId)
                ->where('month', $month)
                ->first();
            
            if (!$submittedReport) {
                Log::info("No submitted SF2 report found, generating from current data", [
                    'section_id' => $sectionId,
                    'month' => $month
                ]);
                
                // If no submitted report found, generate from current data
                try {
                    $sf2Data = $this->getReportDataForSubmission($sectionId, $month);
                    
                    return response()->json([
                        'success' => true,
                        'data' => $sf2Data,
                        'submission_info' => [
                            'id' => null,
                            'status' => 'not_submitted',
                            'submitted_at' => null,
                            'submitted_by' => null,
                            'reviewed_at' => null,
                            'reviewed_by' => null,
                            'admin_notes' => null
                        ],
                        'message' => 'Generated from current data - no submission found'
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to generate SF2 data: " . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'No submitted SF2 report found and failed to generate current data',
                        'error' => $e->getMessage()
                    ], 404);
                }
            }
            
            // Parse the stored SF2 data
            $sf2Data = json_decode($submittedReport->sf2_data, true);
            
            if (!$sf2Data) {
                Log::error("Invalid SF2 data in submission", [
                    'submission_id' => $submittedReport->id,
                    'sf2_data_preview' => substr($submittedReport->sf2_data, 0, 200)
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid SF2 data in submission'
                ], 500);
            }
            
            Log::info("Successfully retrieved submitted SF2 data", [
                'submission_id' => $submittedReport->id,
                'student_count' => count($sf2Data['students'] ?? []),
                'status' => $submittedReport->status
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $sf2Data,
                'submission_info' => [
                    'id' => $submittedReport->id,
                    'status' => $submittedReport->status,
                    'submitted_at' => $submittedReport->submitted_at,
                    'submitted_by' => $submittedReport->submitted_by,
                    'reviewed_at' => $submittedReport->reviewed_at,
                    'reviewed_by' => $submittedReport->reviewed_by,
                    'admin_notes' => $submittedReport->admin_notes
                ],
                'message' => 'Exact data teacher submitted'
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error in getSubmittedReportData: " . $e->getMessage(), [
                'section_id' => $sectionId,
                'month' => $month,
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch submitted SF2 data',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'section_id' => $sectionId,
                    'month' => $month,
                    'line' => $e->getLine(),
                    'file' => basename($e->getFile())
                ]
            ], 500);
        }
    }

    /**
     * Generate test students with full month attendance (X for absent)
     */
    private function generateTestStudentsWithFullMonth($month)
    {
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
        
        $students = [];
        
        // Sample students
        $sampleStudents = [
            ['id' => 1, 'firstName' => 'Juan', 'lastName' => 'Dela Cruz', 'middleName' => 'Santos', 'gender' => 'Male'],
            ['id' => 2, 'firstName' => 'Pedro', 'lastName' => 'Martinez', 'middleName' => 'Lopez', 'gender' => 'Male'],
            ['id' => 3, 'firstName' => 'Carlos', 'lastName' => 'Santos', 'middleName' => 'Rivera', 'gender' => 'Male'],
            ['id' => 4, 'firstName' => 'Maria', 'lastName' => 'Garcia', 'middleName' => 'Cruz', 'gender' => 'Female'],
            ['id' => 5, 'firstName' => 'Carmen', 'lastName' => 'Lopez', 'middleName' => 'Torres', 'gender' => 'Female'],
            ['id' => 6, 'firstName' => 'Ana', 'lastName' => 'Rodriguez', 'middleName' => 'Flores', 'gender' => 'Female'],
        ];
        
        foreach ($sampleStudents as $studentData) {
            $attendance = [];
            $attendance_data = [];
            
            // Fill all weekdays with 'absent' (X marks)
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                if ($currentDate->isWeekday()) {
                    $dateKey = $currentDate->format('Y-m-d');
                    $attendance[$dateKey] = 'absent';
                    $attendance_data[$dateKey] = 'absent';
                }
                $currentDate->addDay();
            }
            
            $students[] = [
                'id' => $studentData['id'],
                'name' => $studentData['lastName'] . ', ' . $studentData['firstName'] . ' ' . $studentData['middleName'],
                'firstName' => $studentData['firstName'],
                'lastName' => $studentData['lastName'],
                'middleName' => $studentData['middleName'],
                'gender' => $studentData['gender'],
                'attendance' => $attendance,
                'attendance_data' => $attendance_data,
                'totalPresent' => 0,
                'totalAbsent' => count($attendance),
                'attendanceRate' => 0
            ];
        }
        
        return $students;
    }

    /**
     * Original getSubmittedReportData method (backup)
     */
    public function getSubmittedReportDataOriginal($sectionId, $month)
    {
        try {
            Log::info("Admin fetching submitted SF2 data", [
                'section_id' => $sectionId,
                'month' => $month,
                'request_url' => request()->url()
            ]);
            
            // Find the submitted report for this section and month
            $submittedReport = \DB::table('submitted_sf2_reports')
                ->where('section_id', $sectionId)
                ->where('month', $month)
                ->first();
            
            if (!$submittedReport) {
                Log::info("No submitted SF2 report found, generating from current data", [
                    'section_id' => $sectionId,
                    'month' => $month
                ]);
                
                // If no submitted report found, generate from current data
                try {
                    $sf2Data = $this->getReportDataForSubmission($sectionId, $month);
                    
                    return response()->json([
                        'success' => true,
                        'data' => $sf2Data,
                        'submission_info' => [
                            'id' => null,
                            'status' => 'not_submitted',
                            'submitted_at' => null,
                            'submitted_by' => null,
                            'reviewed_at' => null,
                            'reviewed_by' => null,
                            'admin_notes' => null
                        ],
                        'message' => 'Generated from current data - no submission found'
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to generate SF2 data: " . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'No submitted SF2 report found and failed to generate current data',
                        'error' => $e->getMessage()
                    ], 404);
                }
            }
            
            // Decode the stored SF2 data
            $sf2Data = null;
            if (!empty($submittedReport->sf2_data)) {
                $sf2Data = json_decode($submittedReport->sf2_data, true);
                Log::info("Found stored SF2 data", [
                    'submission_id' => $submittedReport->id,
                    'students_count' => isset($sf2Data['students']) ? count($sf2Data['students']) : 0
                ]);
            }
            
            // If no stored SF2 data (legacy submission) or failed to decode, generate it now
            if (!$sf2Data) {
                Log::info("No stored SF2 data found, generating from current data", [
                    'submission_id' => $submittedReport->id,
                    'has_sf2_data' => !empty($submittedReport->sf2_data)
                ]);
                
                try {
                    // Generate SF2 data from current attendance records INCLUDING SF2 edits
                    $sf2Data = $this->getReportDataForSubmission($sectionId, $month);
                    
                    // Update the submission with the generated data for future use
                    \DB::table('submitted_sf2_reports')
                        ->where('id', $submittedReport->id)
                        ->update([
                            'sf2_data' => json_encode($sf2Data),
                            'updated_at' => now()
                        ]);
                    
                    Log::info("Generated and stored SF2 data for legacy submission", [
                        'submission_id' => $submittedReport->id,
                        'students_count' => count($sf2Data['students'] ?? [])
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error("Failed to generate SF2 data for legacy submission", [
                        'submission_id' => $submittedReport->id,
                        'error' => $e->getMessage()
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to retrieve or generate SF2 data for this submission'
                    ], 500);
                }
            }
            Log::info("Returning SF2 data to admin", [
                'submission_id' => $submittedReport->id,
                'students_count' => isset($sf2Data['students']) ? count($sf2Data['students']) : 0,
                'sample_student' => isset($sf2Data['students'][0]) ? $sf2Data['students'][0] : 'No students'
            ]);

            return response()->json([
                'success' => true,
                'data' => $sf2Data,
                'submission_info' => [
                    'id' => $submittedReport->id,
                    'status' => $submittedReport->status,
                    'submitted_at' => $submittedReport->submitted_at,
                    'submitted_by' => $submittedReport->submitted_by,
                    'reviewed_at' => $submittedReport->reviewed_at,
                    'reviewed_by' => $submittedReport->reviewed_by,
                    'admin_notes' => $submittedReport->admin_notes
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error fetching submitted SF2 data: " . $e->getMessage(), [
                'section_id' => $sectionId,
                'month' => $month,
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile()
            ]);
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch submitted SF2 data',
                'error' => $e->getMessage(),
                'debug_info' => [
                    'section_id' => $sectionId,
                    'month' => $month,
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Save SF2 attendance edits
     */
    public function saveAttendanceEdit(Request $request)
    {
        try {
            $studentId = $request->input('student_id');
            $date = $request->input('date');
            $status = $request->input('status');
            $sectionId = $request->input('section_id');
            $month = $request->input('month');
            
            Log::info('Saving SF2 attendance edit', [
                'student_id' => $studentId,
                'date' => $date,
                'status' => $status,
                'section_id' => $sectionId,
                'month' => $month,
                'request_data' => $request->all()
            ]);
            
            // Create or update SF2 edit record
            $editRecord = \DB::table('sf2_attendance_edits')->updateOrInsert(
                [
                    'student_id' => $studentId,
                    'date' => $date,
                    'section_id' => $sectionId,
                    'month' => $month
                ],
                [
                    'status' => $status,
                    'updated_at' => now(),
                    'created_at' => now()
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Attendance edit saved successfully',
                'data' => [
                    'student_id' => $studentId,
                    'date' => $date,
                    'status' => $status
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error saving SF2 attendance edit: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save attendance edit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the status of a submitted report
     */
    public function updateReportStatus($reportId, Request $request)
    {
        try {
            $status = $request->input('status');
            $adminNotes = $request->input('admin_notes', '');

            // Validate status
            $validStatuses = ['submitted', 'reviewed', 'approved', 'rejected'];
            if (!in_array($status, $validStatuses)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status provided'
                ], 400);
            }

            // Update the report
            $updated = \DB::table('submitted_sf2_reports')
                ->where('id', $reportId)
                ->update([
                    'status' => $status,
                    'reviewed_at' => now(),
                    'reviewed_by' => 1, // TODO: Get actual admin ID from auth
                    'admin_notes' => $adminNotes,
                    'updated_at' => now()
                ]);

            if ($updated) {
                Log::info("Report status updated", [
                    'report_id' => $reportId,
                    'new_status' => $status,
                    'admin_notes' => $adminNotes
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Report status updated successfully',
                    'data' => [
                        'report_id' => $reportId,
                        'status' => $status,
                        'reviewed_at' => now()->toISOString()
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Report not found'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error("Error updating report status: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update report status',
                'error' => $e->getMessage()
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

    /**
     * Apply center alignment to summary columns (ABSENT, TARDY, PRESENT)
     */
    private function applyCenterAlignmentToSummaryColumns($worksheet)
    {
        try {
            Log::info("Applying center alignment to summary columns");
            
            // Define the summary columns that need center alignment
            $summaryColumns = ['AC', 'AD', 'AE', 'AG']; // ABSENT, PRESENT, TARDY columns
            
            // Get the highest row with data
            $highestRow = $worksheet->getHighestRow();
            
            // Apply vertical text and wrap text to MALE TOTAL row (row 35) - columns D to AB only
            $maleRowColumns = ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB'];
            foreach ($maleRowColumns as $column) {
                $cell = "{$column}35";
                $worksheet->getStyle($cell)->getAlignment()->setWrapText(true);   // Wrap text
                $worksheet->getStyle($cell)->getAlignment()->setTextRotation(255); // Vertical text (255 = vertical stacked)
                $worksheet->getStyle($cell)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                $worksheet->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }
            
            // Apply center alignment to all summary columns from row 10 to the highest row
            foreach ($summaryColumns as $column) {
                $range = "{$column}10:{$column}{$highestRow}";
                $worksheet->getStyle($range)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $worksheet->getStyle($range)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
                Log::info("Applied center alignment to range: {$range}");
            }
            
            // Also center align the header row (around row 11-12) for the summary columns
            foreach ($summaryColumns as $column) {
                $headerRange = "{$column}11:{$column}12";
                $worksheet->getStyle($headerRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $worksheet->getStyle($headerRange)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }
            
            Log::info("Successfully applied center alignment to summary columns");
            
        } catch (\Exception $e) {
            Log::error("Error applying center alignment: " . $e->getMessage());
        }
    }

    /**
     * Apply vertical text and center alignment to total rows
     */
    private function applyVerticalTextToTotalRows($worksheet)
    {
        try {
            Log::info("Applying center alignment to total rows");
            
            // Define the daily attendance columns (D to AB for days 1-31)
            $dailyColumns = ['D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB'];
            
            // Apply center alignment to MALE TOTAL row (row 35)
            foreach ($dailyColumns as $column) {
                $cell = "{$column}35";
                $worksheet->getStyle($cell)->getAlignment()->setWrapText(true);
                $worksheet->getStyle($cell)->getAlignment()->setTextRotation(255); // Vertical text
                $worksheet->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $worksheet->getStyle($cell)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }
            
            // Apply center alignment to FEMALE TOTAL row (row 61)
            foreach ($dailyColumns as $column) {
                $cell = "{$column}61";
                $worksheet->getStyle($cell)->getAlignment()->setWrapText(true);
                $worksheet->getStyle($cell)->getAlignment()->setTextRotation(255); // Vertical text
                $worksheet->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $worksheet->getStyle($cell)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }
            
            // Apply center alignment to Combined TOTAL row (row 62)
            foreach ($dailyColumns as $column) {
                $cell = "{$column}62";
                $worksheet->getStyle($cell)->getAlignment()->setWrapText(true);
                $worksheet->getStyle($cell)->getAlignment()->setTextRotation(255); // Vertical text
                $worksheet->getStyle($cell)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $worksheet->getStyle($cell)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            }
            
            Log::info("Successfully applied center alignment to total rows");
            
        } catch (\Exception $e) {
            Log::error("Error applying center alignment to total rows: " . $e->getMessage());
        }
    }
}
