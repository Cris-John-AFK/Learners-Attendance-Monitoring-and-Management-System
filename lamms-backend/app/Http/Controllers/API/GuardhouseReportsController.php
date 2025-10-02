<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GuardhouseReportsController extends Controller
{
    /**
     * Get live feed data for admin dashboard
     */
    public function getLiveFeed(Request $request)
    {
        try {
            $today = Carbon::today()->toDateString();
            
            // Get today's check-ins
            $checkIns = DB::table('guardhouse_attendance')
                ->join('student_details', 'guardhouse_attendance.student_id', '=', 'student_details.id')
                ->where('guardhouse_attendance.date', $today)
                ->where('guardhouse_attendance.record_type', 'check-in')
                ->orderBy('guardhouse_attendance.timestamp', 'desc')
                ->limit(20)
                ->select(
                    'guardhouse_attendance.id',
                    'guardhouse_attendance.student_id',
                    DB::raw('CONCAT(student_details."firstName", \' \', student_details."lastName") as student_name'),
                    'student_details.gradeLevel as grade_level',
                    'student_details.section',
                    'guardhouse_attendance.timestamp',
                    'guardhouse_attendance.record_type'
                )
                ->get();
            
            // Get today's check-outs
            $checkOuts = DB::table('guardhouse_attendance')
                ->join('student_details', 'guardhouse_attendance.student_id', '=', 'student_details.id')
                ->where('guardhouse_attendance.date', $today)
                ->where('guardhouse_attendance.record_type', 'check-out')
                ->orderBy('guardhouse_attendance.timestamp', 'desc')
                ->limit(20)
                ->select(
                    'guardhouse_attendance.id',
                    'guardhouse_attendance.student_id',
                    DB::raw('CONCAT(student_details."firstName", \' \', student_details."lastName") as student_name'),
                    'student_details.gradeLevel as grade_level',
                    'student_details.section',
                    'guardhouse_attendance.timestamp',
                    'guardhouse_attendance.record_type'
                )
                ->get();
            
            return response()->json([
                'success' => true,
                'check_ins' => $checkIns,
                'check_outs' => $checkOuts
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch live feed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Toggle scanner status
     */
    public function toggleScanner(Request $request)
    {
        try {
            $enabled = $request->input('enabled', true);
            
            // Store scanner status in cache or database settings table
            // For now, we'll use cache
            cache(['guardhouse_scanner_enabled' => $enabled], now()->addHours(24));
            
            return response()->json([
                'success' => true,
                'enabled' => $enabled,
                'message' => $enabled ? 'Scanner enabled' : 'Scanner disabled'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle scanner',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Archive current session
     */
    public function archiveSession(Request $request)
    {
        try {
            $sessionDate = $request->input('session_date', Carbon::today()->toDateString());
            $records = $request->input('records', []);
            
            // Create or get archive session
            $archiveSession = DB::table('guardhouse_archive_sessions')->insertGetId([
                'session_date' => $sessionDate,
                'total_records' => count($records),
                'archived_at' => now(),
                'archived_by' => auth()->id() ?? 1, // Get current admin ID
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Archive the records
            if (!empty($records)) {
                $archiveRecords = [];
                foreach ($records as $record) {
                    $archiveRecords[] = [
                        'session_id' => $archiveSession,
                        'student_id' => $record['student_id'] ?? null,
                        'student_name' => $record['student_name'] ?? '',
                        'grade_level' => $record['grade_level'] ?? '',
                        'section' => $record['section'] ?? '',
                        'record_type' => $record['record_type'] ?? '',
                        'timestamp' => $record['timestamp'] ?? now(),
                        'session_date' => $sessionDate,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
                
                DB::table('guardhouse_archived_records')->insert($archiveRecords);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Session archived successfully',
                'session_id' => $archiveSession,
                'records_archived' => count($records)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive session',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get archived sessions
     */
    public function getArchivedSessions(Request $request)
    {
        try {
            $query = DB::table('guardhouse_archived_records');
            
            // Apply filters
            if ($request->has('date')) {
                $query->where('session_date', $request->input('date'));
            }
            
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('student_name', 'ILIKE', "%{$search}%")
                      ->orWhere('student_id', 'ILIKE', "%{$search}%");
                });
            }
            
            if ($request->has('type') && $request->input('type') !== 'all') {
                $query->where('record_type', $request->input('type'));
            }
            
            $records = $query->orderBy('timestamp', 'desc')
                           ->limit(500)
                           ->get();
            
            return response()->json([
                'success' => true,
                'records' => $records
            ]);
            
        } catch (\Exception $e) {
            // If archived tables don't exist, try to get from main guardhouse_attendance table
            try {
                $query = DB::table('guardhouse_attendance')
                    ->join('student_details', 'guardhouse_attendance.student_id', '=', 'student_details.id');
                
                // Apply filters
                if ($request->has('date')) {
                    $query->where('guardhouse_attendance.date', $request->input('date'));
                }
                
                if ($request->has('search')) {
                    $search = $request->input('search');
                    $query->where(function($q) use ($search) {
                        $q->where(DB::raw('CONCAT(student_details."firstName", \' \', student_details."lastName")'), 'ILIKE', "%{$search}%")
                          ->orWhere('guardhouse_attendance.student_id', '=', $search);
                    });
                }
                
                if ($request->has('type') && $request->input('type') !== 'all') {
                    $query->where('guardhouse_attendance.record_type', $request->input('type'));
                }
                
                $records = $query->select(
                        'guardhouse_attendance.id',
                        'guardhouse_attendance.student_id',
                        DB::raw('CONCAT(student_details."firstName", \' \', student_details."lastName") as student_name'),
                        'student_details.gradeLevel as grade_level',
                        'student_details.section',
                        'guardhouse_attendance.timestamp',
                        'guardhouse_attendance.record_type',
                        'guardhouse_attendance.date as session_date'
                    )
                    ->orderBy('guardhouse_attendance.timestamp', 'desc')
                    ->limit(500)
                    ->get();
                
                return response()->json([
                    'success' => true,
                    'records' => $records
                ]);
                
            } catch (\Exception $fallbackError) {
                return response()->json([
                    'success' => true,
                    'records' => [],
                    'message' => 'No archived records found'
                ]);
            }
        }
    }
}
