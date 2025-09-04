<?php

echo "=== TESTING UPDATED SEATING ARRANGEMENT API ===\n\n";

$url = 'http://localhost:8000/api/student-management/sections/13/seating-arrangement?teacher_id=1';
echo "Testing: {$url}\n\n";

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

echo "HTTP Status: {$httpCode}\n\n";

if ($httpCode == 200) {
    $data = json_decode($response, true);
    echo "✅ API Response Successful\n";
    echo "Section ID: " . $data['section_id'] . "\n";
    echo "Subject ID: " . ($data['subject_id'] ?? 'null') . "\n";
    echo "Last Updated: " . ($data['last_updated'] ?? 'null') . "\n\n";
    
    $layout = $data['seating_layout'];
    echo "Layout Structure:\n";
    echo "- Rows: " . $layout['rows'] . "\n";
    echo "- Columns: " . $layout['columns'] . "\n";
    echo "- Show Teacher Desk: " . ($layout['showTeacherDesk'] ? 'true' : 'false') . "\n";
    echo "- Show Student IDs: " . ($layout['showStudentIds'] ? 'true' : 'false') . "\n\n";
    
    // Count occupied seats
    $occupiedSeats = 0;
    $studentList = [];
    
    foreach ($layout['seatPlan'] as $rowIndex => $row) {
        foreach ($row as $colIndex => $seat) {
            if ($seat['isOccupied']) {
                $occupiedSeats++;
                $studentList[] = [
                    'position' => "Row " . ($rowIndex + 1) . ", Col " . ($colIndex + 1),
                    'name' => $seat['name'],
                    'studentId' => $seat['studentId'],
                    'id' => $seat['id']
                ];
            }
        }
    }
    
    echo "📊 Summary:\n";
    echo "- Total occupied seats: {$occupiedSeats}\n";
    echo "- Students placed:\n";
    
    foreach ($studentList as $student) {
        echo "  * {$student['position']}: {$student['name']} ({$student['studentId']})\n";
    }
    
    if ($occupiedSeats > 0) {
        echo "\n🎉 SUCCESS! Students are now properly placed in the seating arrangement!\n";
        echo "🔄 Please refresh your frontend to see the students.\n";
    } else {
        echo "\n❌ ISSUE: No students found in seating arrangement.\n";
    }
    
} else {
    echo "❌ API Error: {$response}\n";
}