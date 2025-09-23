<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GateAttendanceController extends Controller
{
    /**
     * Record a gate attendance scan
     */
    public function recordScan(Request $request)
    {
        try {
            $validated = $request->validate([
                'student_id' => 'required|integer|exists:student_details,id',
                'type' => 'required|in:check_in,check_out',
                'gate_location' => 'string|nullable',
                'scanner_device' => 'string|nullable',
            ]);

            $now = Carbon::now();
            
            $gateRecord = DB::table('gate_attendance')->insert([
                'student_id' => $validated['student_id'],
                'type' => $validated['type'],
                'scan_time' => $now,
                'scan_date' => $now->toDateString(),
                'gate_location' => $validated['gate_location'] ?? 'main_gate',
                'scanner_device' => $validated['scanner_device'] ?? 'qr_scanner',
                'metadata' => json_encode([
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => $now->toISOString()
                ]),
                'is_valid' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Gate attendance recorded successfully',
                'data' => [
                    'student_id' => $validated['student_id'],
                    'type' => $validated['type'],
                    'scan_time' => $now->format('Y-m-d H:i:s'),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record gate attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get gate attendance records for a student
     */
    public function getStudentGateRecords($studentId, Request $request)
    {
        try {
            $date = $request->get('date', Carbon::today()->toDateString());
            
            $records = DB::table('gate_attendance')
                ->where('student_id', $studentId)
                ->where('scan_date', $date)
                ->orderBy('scan_time', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch gate records',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
