<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GeographicAttendanceController extends Controller
{
    /**
     * Get geographic attendance data for heatmap visualization
     */
    public function getGeographicAttendanceData(Request $request)
    {
        try {
            $teacherId = $request->input('teacher_id');
            $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
            $subjectId = $request->input('subject_id');
            $attendanceStatus = $request->input('attendance_status', 'absent'); // absent, late, excused

            Log::info("Geographic attendance request", [
                'teacher_id' => $teacherId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'subject_id' => $subjectId,
                'attendance_status' => $attendanceStatus
            ]);

            // Get teacher's assigned students with their addresses and attendance data
            $query = DB::table('teacher_section_subject as tss')
                ->join('student_section as ss', 'tss.section_id', '=', 'ss.section_id')
                ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
                ->leftJoin('attendance_records as ar', function($join) {
                    $join->on('sd.id', '=', 'ar.student_id');
                })
                ->leftJoin('attendance_sessions as ases', function($join) use ($startDate, $endDate) {
                    $join->on('ar.attendance_session_id', '=', 'ases.id')
                         ->whereBetween('ases.session_date', [$startDate, $endDate]);
                })
                ->leftJoin('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->where('tss.teacher_id', $teacherId)
                ->where('tss.is_active', true)
                ->where('ss.is_active', true)
                ->whereNotNull('sd.currentAddress');

            // Filter by subject if specified
            if ($subjectId) {
                $query->where('tss.subject_id', $subjectId);
            }

            // Filter by attendance status
            if ($attendanceStatus) {
                $statusCodes = [
                    'absent' => 'A',
                    'late' => 'L', 
                    'excused' => 'E',
                    'present' => 'P'
                ];
                
                if (isset($statusCodes[$attendanceStatus])) {
                    $query->where('ast.code', $statusCodes[$attendanceStatus]);
                }
            }

            $results = $query->select([
                'sd.id as student_id',
                'sd.firstName',
                'sd.lastName',
                'sd.currentAddress',
                'ast.code as attendance_status',
                'ases.session_date',
                DB::raw('COUNT(ar.id) as total_records')
            ])
            ->groupBy('sd.id', 'sd.firstName', 'sd.lastName', 'ast.code', 'ases.session_date')
            ->get();

            // Process and group data by geographic location
            $geographicData = $this->processGeographicData($results, $attendanceStatus);

            return response()->json([
                'success' => true,
                'data' => $geographicData,
                'summary' => [
                    'total_locations' => count($geographicData),
                    'date_range' => ['start' => $startDate, 'end' => $endDate],
                    'attendance_status' => $attendanceStatus
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Geographic attendance data error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load geographic attendance data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process raw data into geographic clusters for heatmap
     */
    private function processGeographicData($results, $attendanceStatus)
    {
        $locationGroups = [];

        foreach ($results as $record) {
            $address = json_decode($record->currentAddress, true);
            
            if (!$address) continue;

            // Create location key for grouping
            $locationKey = $this->createLocationKey($address);
            
            if (!isset($locationGroups[$locationKey])) {
                $locationGroups[$locationKey] = [
                    'location' => $address,
                    'coordinates' => $this->getCoordinatesForAddress($address),
                    'students' => [],
                    'total_incidents' => 0,
                    'incident_type' => $attendanceStatus
                ];
            }

            // Add student to this location group
            $studentKey = $record->student_id;
            if (!isset($locationGroups[$locationKey]['students'][$studentKey])) {
                $locationGroups[$locationKey]['students'][$studentKey] = [
                    'id' => $record->student_id,
                    'name' => $record->firstName . ' ' . $record->lastName,
                    'incidents' => 0
                ];
            }

            $locationGroups[$locationKey]['students'][$studentKey]['incidents'] += $record->total_records;
            $locationGroups[$locationKey]['total_incidents'] += $record->total_records;
        }

        // Convert to array and add intensity for heatmap
        $processedData = [];
        foreach ($locationGroups as $locationData) {
            $locationData['students'] = array_values($locationData['students']);
            $locationData['intensity'] = $this->calculateIntensity($locationData['total_incidents']);
            $processedData[] = $locationData;
        }

        // Sort by intensity (highest first)
        usort($processedData, function($a, $b) {
            return $b['total_incidents'] - $a['total_incidents'];
        });

        return $processedData;
    }

    /**
     * Create a unique location key for grouping nearby addresses
     */
    private function createLocationKey($address)
    {
        $barangay = $address['barangay'] ?? 'Unknown';
        $street = $address['street'] ?? '';
        
        // Group by barangay and street/purok for better clustering
        return strtolower($barangay . '_' . $street);
    }

    /**
     * Get approximate coordinates for Philippine addresses
     * This is a simplified version - in production you'd use a proper geocoding service
     */
    private function getCoordinatesForAddress($address)
    {
        // Default coordinates for Naawan, Misamis Oriental
        $baseLatitude = 8.4304;
        $baseLongitude = 124.2897;

        // Add small random offset based on barangay/purok for visualization
        $barangay = strtolower($address['barangay'] ?? 'naawan');
        $street = $address['street'] ?? '';

        // Simple hash-based coordinate generation for consistent positioning
        $latOffset = (crc32($barangay) % 1000) / 100000; // Small offset
        $lngOffset = (crc32($street) % 1000) / 100000;

        return [
            'latitude' => $baseLatitude + $latOffset,
            'longitude' => $baseLongitude + $lngOffset
        ];
    }

    /**
     * Calculate heatmap intensity based on incident count
     */
    private function calculateIntensity($incidentCount)
    {
        if ($incidentCount === 0) return 0;
        if ($incidentCount <= 2) return 0.3;
        if ($incidentCount <= 5) return 0.5;
        if ($incidentCount <= 10) return 0.7;
        return 1.0; // Maximum intensity
    }

    /**
     * Get attendance summary by geographic area
     */
    public function getAttendanceSummaryByArea(Request $request)
    {
        try {
            $teacherId = $request->input('teacher_id');
            $startDate = $request->input('start_date', Carbon::now()->subDays(30)->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

            // Get summary statistics by barangay
            $summary = DB::table('teacher_section_subject as tss')
                ->join('student_section as ss', 'tss.section_id', '=', 'ss.section_id')
                ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
                ->leftJoin('attendance_records as ar', function($join) use ($startDate, $endDate) {
                    $join->on('sd.id', '=', 'ar.student_id')
                         ->whereBetween('ar.created_at', [$startDate, $endDate]);
                })
                ->leftJoin('attendance_statuses as ast', 'ar.attendance_status_id', '=', 'ast.id')
                ->where('tss.teacher_id', $teacherId)
                ->where('tss.is_active', true)
                ->where('ss.is_active', true)
                ->whereNotNull('sd.currentAddress')
                ->select([
                    DB::raw("JSON_EXTRACT(sd.currentAddress, '$.barangay') as barangay"),
                    DB::raw('COUNT(DISTINCT sd.id) as total_students'),
                    DB::raw('COUNT(CASE WHEN ast.code = "A" THEN 1 END) as total_absences'),
                    DB::raw('COUNT(CASE WHEN ast.code = "L" THEN 1 END) as total_late'),
                    DB::raw('COUNT(CASE WHEN ast.code = "E" THEN 1 END) as total_excused'),
                    DB::raw('COUNT(CASE WHEN ast.code = "P" THEN 1 END) as total_present')
                ])
                ->groupBy(DB::raw("JSON_EXTRACT(sd.currentAddress, '$.barangay')"))
                ->orderBy('total_absences', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Attendance summary by area error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load attendance summary by area'
            ], 500);
        }
    }
}
