<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceHeatmapControllerSimple extends Controller
{
    public function getAttendanceReasonsHeatmap(Request $request)
    {
        try {
            $teacherId = $request->query('teacher_id');
            $period = $request->query('period', 'week');

            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher ID is required'
                ], 400);
            }

            // Calculate date range based on period
            $endDate = Carbon::now();
            switch ($period) {
                case 'day':
                    $startDate = $endDate->copy()->subDays(7);
                    break;
                case 'week':
                    $startDate = $endDate->copy()->subWeeks(4);
                    break;
                case 'month':
                default:
                    $startDate = $endDate->copy()->subMonths(1);
                    break;
            }

            Log::info("Heatmap Simple: period={$period}, range={$startDate->toDateString()} to {$endDate->toDateString()}");

            // Get real data from the database
            $realData = $this->getRealHeatmapData($teacherId, $startDate, $endDate);
            
            if ($realData) {
                Log::info("Heatmap Simple: Returning real data with " . 
                    count($realData['late_reasons']) . " late reasons, " .
                    count($realData['location_correlations']) . " locations, " .
                    count($realData['timeline_data']) . " timeline points");
                    
                return response()->json([
                    'success' => true,
                    'data' => $realData,
                    'source' => 'database'
                ]);
            }

            // Fallback to demo data
            Log::info("Heatmap Simple: No data found, using demo data");
            return response()->json([
                'success' => true,
                'data' => $this->getDemoData($period, $startDate, $endDate),
                'source' => 'demo'
            ]);

        } catch (\Exception $e) {
            Log::error('Heatmap Simple Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getRealHeatmapData($teacherId, $startDate, $endDate)
    {
        // Get Late reasons with counts
        $lateRecords = DB::table('attendance_records as ar')
            ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
            ->leftJoin('attendance_reasons as areason', 'ar.reason_id', '=', 'areason.id')
            ->where('ases.teacher_id', $teacherId)
            ->where('ast.code', 'L')
            ->whereBetween('ases.session_date', [$startDate, $endDate])
            ->select([
                DB::raw("COALESCE(areason.reason_name, 'No reason specified') as reason_name"),
                DB::raw("COALESCE(areason.category, 'Other') as category"),
                DB::raw('COUNT(*) as count')
            ])
            ->groupBy('areason.reason_name', 'areason.category')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->get();

        // Get Excused reasons with counts
        $excusedRecords = DB::table('attendance_records as ar')
            ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
            ->leftJoin('attendance_reasons as areason', 'ar.reason_id', '=', 'areason.id')
            ->where('ases.teacher_id', $teacherId)
            ->where('ast.code', 'E')
            ->whereBetween('ases.session_date', [$startDate, $endDate])
            ->select([
                DB::raw("COALESCE(areason.reason_name, 'No reason specified') as reason_name"),
                DB::raw("COALESCE(areason.category, 'Other') as category"),
                DB::raw('COUNT(*) as count')
            ])
            ->groupBy('areason.reason_name', 'areason.category')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->get();

        $totalLate = $lateRecords->sum('count');
        $totalExcused = $excusedRecords->sum('count');

        if ($totalLate == 0 && $totalExcused == 0) {
            return null;
        }

        // Get location correlations from student addresses
        $locationData = $this->getLocationCorrelations($teacherId, $startDate, $endDate);

        // Get timeline data
        $timelineData = $this->getTimelineData($teacherId, $startDate, $endDate);

        return [
            'late_reasons' => $lateRecords->map(fn($r) => [
                'reason' => $r->reason_name,
                'count' => (int)$r->count,
                'category' => $r->category
            ])->toArray(),
            'excused_reasons' => $excusedRecords->map(fn($r) => [
                'reason' => $r->reason_name,
                'count' => (int)$r->count,
                'category' => $r->category
            ])->toArray(),
            'location_correlations' => $locationData,
            'timeline_data' => $timelineData,
            'summary' => [
                'total_late_incidents' => (int)$totalLate,
                'total_excused_incidents' => (int)$totalExcused,
                'most_common_late_reason' => $lateRecords->first()?->reason_name ?? 'N/A',
                'most_common_excused_reason' => $excusedRecords->first()?->reason_name ?? 'N/A',
                'most_affected_location' => !empty($locationData) ? $locationData[0]['location'] : 'N/A'
            ]
        ];
    }

    /**
     * Extract location from JSON currentAddress and correlate with attendance issues
     */
    private function getLocationCorrelations($teacherId, $startDate, $endDate)
    {
        // Get all Late/Excused records with student addresses
        $records = DB::table('attendance_records as ar')
            ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
            ->join('student_details as sd', 'ar.student_id', '=', 'sd.id')
            ->where('ases.teacher_id', $teacherId)
            ->whereIn('ast.code', ['L', 'E'])
            ->whereBetween('ases.session_date', [$startDate, $endDate])
            ->select([
                'ast.code as status_code',
                'sd.currentAddress',
                'sd.address',
                'sd.permanentAddress'
            ])
            ->get();

        $locationStats = [];

        foreach ($records as $record) {
            $location = $this->extractLocationFromAddress($record);
            
            if ($location && $location !== 'Unknown') {
                if (!isset($locationStats[$location])) {
                    $locationStats[$location] = [
                        'location' => $location,
                        'late_count' => 0,
                        'excused_count' => 0,
                        'total_count' => 0
                    ];
                }

                if ($record->status_code === 'L') {
                    $locationStats[$location]['late_count']++;
                } else {
                    $locationStats[$location]['excused_count']++;
                }
                $locationStats[$location]['total_count']++;
            }
        }

        // Sort by total count and return top 10
        $sorted = collect($locationStats)
            ->sortByDesc('total_count')
            ->take(10)
            ->values()
            ->toArray();

        return $sorted;
    }

    /**
     * Parse JSON address and extract barangay/street for location
     */
    private function extractLocationFromAddress($record)
    {
        // Try currentAddress first, then address, then permanentAddress
        $addressJson = $record->currentAddress ?? $record->address ?? $record->permanentAddress ?? null;

        if (!$addressJson) {
            return 'Unknown';
        }

        // Try to parse as JSON
        $addressData = json_decode($addressJson, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($addressData)) {
            // Extract barangay first (most useful for local correlation)
            if (!empty($addressData['barangay'])) {
                $barangay = $addressData['barangay'];
                // Add Purok/Street if available for more granularity
                if (!empty($addressData['street']) && strpos(strtolower($addressData['street']), 'purok') !== false) {
                    return $addressData['street'] . ', ' . $barangay;
                }
                return 'Brgy. ' . $barangay;
            }

            // Fallback to city/municipality
            if (!empty($addressData['city'])) {
                return $addressData['city'];
            }
            if (!empty($addressData['city_municipality'])) {
                return $addressData['city_municipality'];
            }
        }

        // If not JSON, try to extract from plain text
        if (is_string($addressJson)) {
            // Look for barangay pattern
            if (preg_match('/barangay\s+([^,]+)/i', $addressJson, $matches)) {
                return 'Brgy. ' . trim($matches[1]);
            }
            if (preg_match('/brgy\.?\s+([^,]+)/i', $addressJson, $matches)) {
                return 'Brgy. ' . trim($matches[1]);
            }
            
            // Return first part if short enough
            $parts = explode(',', $addressJson);
            if (!empty($parts[0]) && strlen(trim($parts[0])) <= 50) {
                return trim($parts[0]);
            }
        }

        return 'Unknown';
    }

    /**
     * Get timeline data for late/excused incidents over the date range
     */
    private function getTimelineData($teacherId, $startDate, $endDate)
    {
        // Get daily counts of Late and Excused
        $dailyData = DB::table('attendance_records as ar')
            ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
            ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
            ->where('ases.teacher_id', $teacherId)
            ->whereIn('ast.code', ['L', 'E'])
            ->whereBetween('ases.session_date', [$startDate, $endDate])
            ->select([
                'ases.session_date',
                'ast.code as status_code',
                DB::raw('COUNT(*) as count')
            ])
            ->groupBy('ases.session_date', 'ast.code')
            ->orderBy('ases.session_date')
            ->get();

        // Organize by date
        $timelineMap = [];
        foreach ($dailyData as $row) {
            $date = $row->session_date;
            if (!isset($timelineMap[$date])) {
                $timelineMap[$date] = ['late' => 0, 'excused' => 0];
            }
            if ($row->status_code === 'L') {
                $timelineMap[$date]['late'] = (int)$row->count;
            } else {
                $timelineMap[$date]['excused'] = (int)$row->count;
            }
        }

        // Convert to array format
        $timeline = [];
        foreach ($timelineMap as $date => $counts) {
            $timeline[] = [
                'date' => $date,
                'late' => $counts['late'],
                'excused' => $counts['excused']
            ];
        }

        return $timeline;
    }

    private function getDemoData($period, $startDate, $endDate)
    {
        $multiplier = $period === 'day' ? 0.5 : ($period === 'week' ? 1.0 : 1.5);

        return [
            'late_reasons' => [
                ['reason' => 'Traffic congestion', 'count' => round(8 * $multiplier), 'category' => 'Transportation'],
                ['reason' => 'Muddy/impassable road', 'count' => round(6 * $multiplier), 'category' => 'Transportation'],
                ['reason' => 'Overslept', 'count' => round(4 * $multiplier), 'category' => 'Personal'],
                ['reason' => 'Heavy rain', 'count' => round(3 * $multiplier), 'category' => 'Weather'],
                ['reason' => 'Far distance from home', 'count' => round(2 * $multiplier), 'category' => 'Transportation']
            ],
            'excused_reasons' => [
                ['reason' => 'Medical appointment', 'count' => round(5 * $multiplier), 'category' => 'Health'],
                ['reason' => 'Family emergency', 'count' => round(4 * $multiplier), 'category' => 'Family'],
                ['reason' => 'Sick/not feeling well', 'count' => round(3 * $multiplier), 'category' => 'Health'],
                ['reason' => 'Death in the family', 'count' => round(2 * $multiplier), 'category' => 'Family'],
                ['reason' => 'Religious observance', 'count' => round(1 * $multiplier), 'category' => 'Religious']
            ],
            'location_correlations' => [
                ['location' => 'Purok 3, Naawan', 'late_count' => round(5 * $multiplier), 'excused_count' => round(3 * $multiplier), 'total_count' => round(8 * $multiplier)],
                ['location' => 'Purok 6, Naawan', 'late_count' => round(4 * $multiplier), 'excused_count' => round(2 * $multiplier), 'total_count' => round(6 * $multiplier)],
                ['location' => 'Purok 7, Naawan', 'late_count' => round(3 * $multiplier), 'excused_count' => round(4 * $multiplier), 'total_count' => round(7 * $multiplier)],
                ['location' => 'Purok 1, Naawan', 'late_count' => round(2 * $multiplier), 'excused_count' => round(1 * $multiplier), 'total_count' => round(3 * $multiplier)],
                ['location' => 'Purok 5, Naawan', 'late_count' => round(1 * $multiplier), 'excused_count' => round(2 * $multiplier), 'total_count' => round(3 * $multiplier)]
            ],
            'timeline_data' => $this->generateDemoTimelineData($startDate, $endDate, $multiplier),
            'summary' => [
                'total_late_incidents' => round(23 * $multiplier),
                'total_excused_incidents' => round(15 * $multiplier),
                'most_common_late_reason' => 'Traffic congestion',
                'most_common_excused_reason' => 'Medical appointment',
                'most_affected_location' => 'Purok 3, Naawan'
            ]
        ];
    }

    private function generateDemoTimelineData($startDate, $endDate, $multiplier)
    {
        $data = [];
        $current = $startDate->copy();
        $dayCount = 0;
        
        while ($current <= $endDate && $dayCount < 14) {
            $data[] = [
                'date' => $current->toDateString(),
                'late' => (int)round(rand(1, 5) * $multiplier),
                'excused' => (int)round(rand(0, 3) * $multiplier)
            ];
            $current->addDays(2);
            $dayCount++;
        }
        
        return $data;
    }
}
