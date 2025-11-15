<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceHeatmapControllerSimple extends Controller
{
    public function getAttendanceReasonsHeatmap(Request $request)
    {
        try {
            $teacherId = $request->query('teacher_id');

            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher ID is required'
                ], 400);
            }

            // Realistic test data based on the database structure
            return response()->json([
                'success' => true,
                'data' => [
                    'late_reasons' => [
                        ['reason' => 'Traffic congestion', 'count' => 8, 'category' => 'Transportation'],
                        ['reason' => 'Muddy/impassable road', 'count' => 6, 'category' => 'Transportation'],
                        ['reason' => 'Overslept', 'count' => 4, 'category' => 'Personal'],
                        ['reason' => 'Heavy rain', 'count' => 3, 'category' => 'Weather'],
                        ['reason' => 'Far distance from home', 'count' => 2, 'category' => 'Transportation']
                    ],
                    'excused_reasons' => [
                        ['reason' => 'Medical appointment', 'count' => 5, 'category' => 'Health'],
                        ['reason' => 'Family emergency', 'count' => 4, 'category' => 'Family'],
                        ['reason' => 'Sick/not feeling well', 'count' => 3, 'category' => 'Health'],
                        ['reason' => 'Death in the family', 'count' => 2, 'category' => 'Family'],
                        ['reason' => 'Religious observance', 'count' => 1, 'category' => 'Religious']
                    ],
                    'location_correlations' => [
                        ['location' => 'Brgy. Naawan', 'late_count' => 5, 'excused_count' => 3],
                        ['location' => 'Brgy. Riverside', 'late_count' => 4, 'excused_count' => 2],
                        ['location' => 'Brgy. Mountain View', 'late_count' => 3, 'excused_count' => 4],
                        ['location' => 'Brgy. Central', 'late_count' => 2, 'excused_count' => 1],
                        ['location' => 'Brgy. Coastal', 'late_count' => 1, 'excused_count' => 2],
                        ['location' => 'Unknown Location', 'late_count' => 1, 'excused_count' => 1]
                    ],
                    'timeline_data' => [
                        ['date' => '2025-11-11', 'late' => 3, 'excused' => 2],
                        ['date' => '2025-11-12', 'late' => 2, 'excused' => 3],
                        ['date' => '2025-11-13', 'late' => 4, 'excused' => 1],
                        ['date' => '2025-11-14', 'late' => 1, 'excused' => 2],
                        ['date' => '2025-11-15', 'late' => 2, 'excused' => 1]
                    ],
                    'summary' => [
                        'total_late_incidents' => 23,
                        'total_excused_incidents' => 15,
                        'most_common_late_reason' => 'Traffic congestion',
                        'most_common_excused_reason' => 'Medical appointment',
                        'most_affected_location' => 'Brgy. Naawan'
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
