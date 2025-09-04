<?php

require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel environment
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\Section;

echo "=== CHECKING SECTION 13 STUDENTS ===\n\n";

try {
    // Check if section 13 exists
    $section = Section::find(13);
    if (!$section) {
        echo "❌ Section 13 does not exist!\n";
        exit;
    }
    
    echo "✅ Section 13 found: {$section->name}\n";
    echo "Description: {$section->description}\n";
    echo "Capacity: {$section->capacity}\n";
    echo "Is Active: " . ($section->is_active ? 'Yes' : 'No') . "\n\n";
    
    // Check students assigned to section 13 through pivot table
    echo "=== CHECKING STUDENTS IN SECTION 13 ===\n";
    $students = $section->activeStudents()->get();
    
    echo "Total students found: " . $students->count() . "\n\n";
    
    if ($students->count() > 0) {
        echo "Students in section:\n";
        foreach ($students as $student) {
            echo "- ID: {$student->id} | Name: {$student->name} | Student ID: {$student->studentId}\n";
        }
        
        echo "\n=== TESTING API ENDPOINT ===\n";
        // Test the actual API endpoint
        $url = 'http://localhost:8000/api/student-management/sections/13/seating-arrangement?teacher_id=1';
        echo "Testing: {$url}\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "HTTP Status: {$httpCode}\n";
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            echo "✅ API working correctly\n";
            echo "Last updated: " . ($data['last_updated'] ?? 'null') . "\n";
            
            // Check if students are in the layout
            $seatPlan = $data['seating_layout']['seatPlan'] ?? [];
            $occupiedSeats = 0;
            foreach ($seatPlan as $row) {
                foreach ($row as $seat) {
                    if ($seat['isOccupied'] ?? false) {
                        $occupiedSeats++;
                    }
                }
            }
            echo "Occupied seats in layout: {$occupiedSeats}\n";
            
            if ($occupiedSeats > 0) {
                echo "✅ Students are properly placed in seating layout\n";
            } else {
                echo "⚠️  No students found in seating layout - this might be the issue!\n";
            }
        } else {
            echo "❌ API Error: {$response}\n";
        }
        
    } else {
        echo "❌ No students found in section 13!\n";
        echo "This explains why the frontend shows all empty seats.\n\n";
        
        // Check student_section table directly
        echo "=== CHECKING STUDENT_SECTION TABLE ===\n";
        $pivotRecords = DB::table('student_section')
            ->where('section_id', 13)
            ->where('is_active', true)
            ->get();
            
        echo "Pivot records found: " . $pivotRecords->count() . "\n";
        
        if ($pivotRecords->count() > 0) {
            foreach ($pivotRecords as $record) {
                echo "- Student ID: {$record->student_id} | Section ID: {$record->section_id} | Active: {$record->is_active}\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}