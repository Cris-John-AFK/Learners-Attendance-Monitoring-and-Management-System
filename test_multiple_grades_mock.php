<?php
// Test API endpoint with mock multiple grades data
$mockData = [
    'success' => true,
    'data' => [
        'date_range' => 'current_year',
        'start_date' => '2025-06-01',
        'end_date' => '2026-05-31',
        'grades' => [
            [
                'grade_id' => 1,
                'grade_name' => 'Grade 1',
                'grade_code' => 'G1',
                'present' => 45,
                'absent' => 12,
                'late' => 8,
                'excused' => 3,
                'total_records' => 68,
                'attendance_rate' => 77.9,
                'student_count' => 6
            ],
            [
                'grade_id' => 2,
                'grade_name' => 'Grade 2',
                'grade_code' => 'G2',
                'present' => 52,
                'absent' => 8,
                'late' => 5,
                'excused' => 2,
                'total_records' => 67,
                'attendance_rate' => 85.1,
                'student_count' => 5
            ],
            [
                'grade_id' => 3,
                'grade_name' => 'Grade 3',
                'grade_code' => 'G3',
                'present' => 53,
                'absent' => 22,
                'late' => 5,
                'excused' => 5,
                'total_records' => 85,
                'attendance_rate' => 68.2,
                'student_count' => 4
            ],
            [
                'grade_id' => 4,
                'grade_name' => 'Grade 4',
                'grade_code' => 'G4',
                'present' => 38,
                'absent' => 15,
                'late' => 6,
                'excused' => 4,
                'total_records' => 63,
                'attendance_rate' => 69.8,
                'student_count' => 7
            ],
            [
                'grade_id' => 5,
                'grade_name' => 'Grade 5',
                'grade_code' => 'G5',
                'present' => 41,
                'absent' => 9,
                'late' => 7,
                'excused' => 2,
                'total_records' => 59,
                'attendance_rate' => 81.4,
                'student_count' => 6
            ]
        ],
        'summary' => [
            'total_present' => 229,
            'total_absent' => 66,
            'total_late' => 31,
            'total_excused' => 16,
            'total_records' => 342,
            'total_students' => 28,
            'overall_attendance_rate' => 76.0
        ]
    ]
];

echo "📊 Mock data for multiple grades:\n";
echo json_encode($mockData, JSON_PRETTY_PRINT);
echo "\n\n";

echo "📈 Chart will show:\n";
echo "- Chart Type: horizontalBar (since > 1 grade)\n";
echo "- Grades: " . count($mockData['data']['grades']) . "\n";
foreach ($mockData['data']['grades'] as $grade) {
    echo "  • {$grade['grade_name']}: Present={$grade['present']}, Absent={$grade['absent']}, Late={$grade['late']}, Excused={$grade['excused']}\n";
}
echo "\n";
echo "This will create a horizontal stacked bar chart like the image you showed!\n";
?>
