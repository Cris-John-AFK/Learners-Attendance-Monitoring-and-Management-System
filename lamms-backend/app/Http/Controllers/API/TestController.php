<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestController extends Controller
{
    public function test()
    {
        try {
            // Test basic response
            return response()->json([
                'success' => true,
                'message' => 'API is working',
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function testDb()
    {
        try {
            // Test database connection
            $grades = DB::table('grades')->count();
            
            return response()->json([
                'success' => true,
                'message' => 'Database connection working',
                'grades_count' => $grades
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function testAttendance()
    {
        try {
            // Test attendance tables
            $grades = DB::table('grades')->where('is_active', true)->get();
            $attendanceRecords = DB::table('attendance_records')->count();
            $attendanceSessions = DB::table('attendance_sessions')->count();
            
            // Test attendance_statuses table
            $hasStatusTable = true;
            $statusCount = 0;
            try {
                $statusCount = DB::table('attendance_statuses')->count();
            } catch (\Exception $e) {
                $hasStatusTable = false;
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Attendance tables test',
                'data' => [
                    'grades_count' => count($grades),
                    'attendance_records_count' => $attendanceRecords,
                    'attendance_sessions_count' => $attendanceSessions,
                    'has_status_table' => $hasStatusTable,
                    'status_count' => $statusCount,
                    'grades' => $grades->take(3)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
