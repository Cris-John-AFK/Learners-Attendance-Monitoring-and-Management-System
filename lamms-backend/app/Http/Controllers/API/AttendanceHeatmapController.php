<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceReason;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceHeatmapController extends Controller
{
    /**
     * Get attendance reasons heatmap data with location correlations
     */
    public function getAttendanceReasonsHeatmap(Request $request)
    {
        try {
            Log::info('Heatmap controller called');

            $teacherId = $request->query('teacher_id');
            $period = $request->query('period', 'week');
            $subjectId = $request->query('subject_id');

            Log::info("Parameters: teacherId={$teacherId}, period={$period}, subjectId={$subjectId}");

            if (!$teacherId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher ID is required'
                ], 400);
            }

            Log::info("Loading attendance reasons heatmap for teacher {$teacherId}, period: {$period}, subjectId: {$subjectId}");

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
            
            Log::info("Heatmap date range: {$startDate->toDateString()} to {$endDate->toDateString()} (period: {$period})");

            // Get attendance records with reasons and student locations
            $query = DB::table('attendance_records as ar')
                ->join('attendance_sessions as ases', 'ar.attendance_session_id', '=', 'ases.id')
                ->join('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->join('attendance_reasons as areason', 'ar.reason_id', '=', 'areason.id')
                ->join('student_details as sd', 'ar.student_id', '=', 'sd.id')
                ->where('ases.teacher_id', $teacherId)
                ->whereIn('ast.code', ['L', 'E']) // Late and Excused only
                ->whereNotNull('ar.reason_id')
                ->whereBetween('ases.session_date', [$startDate, $endDate])
                // Filter out dropped/transferred students
                ->where(function($query) {
                    $query->whereIn('sd.enrollment_status', ['active', 'enrolled', 'transferred_in'])
                          ->orWhereNull('sd.enrollment_status');
                })
                ->whereNotIn('sd.enrollment_status', ['dropped_out', 'transferred_out', 'withdrawn', 'deceased']);

            // Filter by subject if specified
            if ($subjectId && $subjectId !== 'null') {
                $query->where('ases.subject_id', $subjectId);
            }

            $records = $query->select([
                'areason.reason_name',
                'areason.reason_type',
                'areason.category',
                'ast.code as status_code',
                'sd.address',
                'sd.currentAddress',
                'sd.permanentAddress',
                'sd.name as student_name',
                'ases.session_date',
                DB::raw('COUNT(*) as occurrence_count')
            ])
            ->groupBy([
                'areason.reason_name',
                'areason.reason_type',
                'areason.category',
                'ast.code',
                'sd.address',
                'sd.currentAddress',
                'sd.permanentAddress',
                'sd.name',
                'ases.session_date'
            ])
            ->orderByRaw('COUNT(*) DESC')
            ->get();
            
            Log::info("Heatmap query returned " . count($records) . " grouped records");
            if (count($records) > 0) {
                // Log sample session dates to debug
                $sampleDates = $records->pluck('session_date')->unique()->take(5)->toArray();
                Log::info("Sample session dates in results: " . json_encode($sampleDates));
            }

            // Process data for heatmap visualization
            $heatmapData = [
                'late_reasons' => [],
                'excused_reasons' => [],
                'location_correlations' => [],
                'timeline_data' => [],
                'summary' => [
                    'total_late_incidents' => 0,
                    'total_excused_incidents' => 0,
                    'most_common_late_reason' => null,
                    'most_common_excused_reason' => null,
                    'most_affected_location' => null
                ]
            ];

            $locationStats = [];
            $reasonStats = ['late' => [], 'excused' => []];
            $timelineStats = [];

            foreach ($records as $record) {
                $statusType = $record->status_code === 'L' ? 'late' : 'excused';
                $location = $this->extractLocation($record);

                // Count by reason
                if (!isset($reasonStats[$statusType][$record->reason_name])) {
                    $reasonStats[$statusType][$record->reason_name] = [
                        'reason' => $record->reason_name,
                        'category' => $record->category,
                        'count' => 0,
                        'students' => []
                    ];
                }
                $reasonStats[$statusType][$record->reason_name]['count'] += $record->occurrence_count;
                $reasonStats[$statusType][$record->reason_name]['students'][] = $record->student_name;

                // Count by location
                if ($location) {
                    if (!isset($locationStats[$location])) {
                        $locationStats[$location] = [
                            'location' => $location,
                            'late_count' => 0,
                            'excused_count' => 0,
                            'total_count' => 0,
                            'reasons' => []
                        ];
                    }

                    if ($statusType === 'late') {
                        $locationStats[$location]['late_count'] += $record->occurrence_count;
                    } else {
                        $locationStats[$location]['excused_count'] += $record->occurrence_count;
                    }
                    $locationStats[$location]['total_count'] += $record->occurrence_count;

                    if (!in_array($record->reason_name, $locationStats[$location]['reasons'])) {
                        $locationStats[$location]['reasons'][] = $record->reason_name;
                    }
                }

                // Timeline data
                $date = Carbon::parse($record->session_date)->format('Y-m-d');
                if (!isset($timelineStats[$date])) {
                    $timelineStats[$date] = ['late' => 0, 'excused' => 0];
                }
                $timelineStats[$date][$statusType] += $record->occurrence_count;

                // Update summary totals
                if ($statusType === 'late') {
                    $heatmapData['summary']['total_late_incidents'] += $record->occurrence_count;
                } else {
                    $heatmapData['summary']['total_excused_incidents'] += $record->occurrence_count;
                }
            }

            // Sort and format data
            $heatmapData['late_reasons'] = array_values($reasonStats['late']);
            $heatmapData['excused_reasons'] = array_values($reasonStats['excused']);
            $heatmapData['location_correlations'] = array_values($locationStats);

            // Sort by count
            usort($heatmapData['late_reasons'], fn($a, $b) => $b['count'] - $a['count']);
            usort($heatmapData['excused_reasons'], fn($a, $b) => $b['count'] - $a['count']);
            usort($heatmapData['location_correlations'], fn($a, $b) => $b['total_count'] - $a['total_count']);

            // Timeline data
            foreach ($timelineStats as $date => $counts) {
                $heatmapData['timeline_data'][] = [
                    'date' => $date,
                    'late' => $counts['late'],
                    'excused' => $counts['excused']
                ];
            }

            // Summary statistics
            if (!empty($heatmapData['late_reasons'])) {
                $heatmapData['summary']['most_common_late_reason'] = $heatmapData['late_reasons'][0]['reason'];
            }
            if (!empty($heatmapData['excused_reasons'])) {
                $heatmapData['summary']['most_common_excused_reason'] = $heatmapData['excused_reasons'][0]['reason'];
            }
            if (!empty($heatmapData['location_correlations'])) {
                $heatmapData['summary']['most_affected_location'] = $heatmapData['location_correlations'][0]['location'];
            }

            Log::info("Heatmap data processed successfully", [
                'late_reasons_count' => count($heatmapData['late_reasons']),
                'excused_reasons_count' => count($heatmapData['excused_reasons']),
                'locations_count' => count($heatmapData['location_correlations'])
            ]);

            return response()->json([
                'success' => true,
                'data' => $heatmapData
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getAttendanceReasonsHeatmap: ' . $e->getMessage(), [
                'teacher_id' => $teacherId ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load attendance reasons heatmap',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Extract location from student address fields
     */
    private function extractLocation($record)
    {
        // Priority: currentAddress > address > permanentAddress
        $address = $record->currentAddress ?: $record->address ?: $record->permanentAddress;

        if (!$address) {
            return 'Unknown Location';
        }

        // Extract meaningful location parts (barangay, municipality, etc.)
        $address = trim($address);

        // Common patterns to extract location
        if (preg_match('/barangay\s+([^,]+)/i', $address, $matches)) {
            return 'Brgy. ' . trim($matches[1]);
        }

        if (preg_match('/brgy\.?\s+([^,]+)/i', $address, $matches)) {
            return 'Brgy. ' . trim($matches[1]);
        }

        // If no barangay found, take first meaningful part
        $parts = explode(',', $address);
        $firstPart = trim($parts[0]);

        // Return first part if it's not too long
        if (strlen($firstPart) <= 50) {
            return $firstPart;
        }

        return 'Various Locations';
    }
}
