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
            DB::beginTransaction();
            
            $sessionDate = Carbon::today()->toDateString();
            
            // Get all today's records from guardhouse_attendance
            $todayRecords = DB::table('guardhouse_attendance')
                ->join('student_details', 'guardhouse_attendance.student_id', '=', 'student_details.id')
                ->where('guardhouse_attendance.date', $sessionDate)
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
            
            if ($todayRecords->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No records found for today to archive'
                ]);
            }
            
            // Check if session already exists for today
            $existingSession = DB::table('guardhouse_archive_sessions')
                ->where('session_date', $sessionDate)
                ->first();
            
            if ($existingSession) {
                return response()->json([
                    'success' => false,
                    'message' => 'Today\'s session has already been archived'
                ]);
            }
            
            // Create archive session
            $archiveSession = DB::table('guardhouse_archive_sessions')->insertGetId([
                'session_date' => $sessionDate,
                'total_records' => $todayRecords->count(),
                'archived_at' => now(),
                'archived_by' => auth()->id() ?? 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Archive the records
            $archiveRecords = [];
            foreach ($todayRecords as $record) {
                $archiveRecords[] = [
                    'session_id' => $archiveSession,
                    'student_id' => $record->student_id,
                    'student_name' => $record->student_name,
                    'grade_level' => $record->grade_level,
                    'section' => $record->section,
                    'record_type' => $record->record_type,
                    'timestamp' => $record->timestamp,
                    'session_date' => $sessionDate,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            
            DB::table('guardhouse_archived_records')->insert($archiveRecords);
            
            // IMPORTANT: Delete the records from the main table after archiving
            DB::table('guardhouse_attendance')
                ->where('date', $sessionDate)
                ->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Session archived successfully',
                'session_id' => $archiveSession,
                'records_archived' => count($archiveRecords),
                'session_date' => $sessionDate
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive session',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get archived sessions (organized by date-based cards)
     */
    public function getArchivedSessions(Request $request)
    {
        try {
            // Get archived sessions (date-based cards)
            $sessions = DB::table('guardhouse_archive_sessions')
                ->orderBy('session_date', 'desc')
                ->get();
            
            $result = [];
            foreach ($sessions as $session) {
                $result[] = [
                    'session_id' => $session->id,
                    'session_date' => $session->session_date,
                    'total_records' => $session->total_records,
                    'archived_at' => $session->archived_at,
                    'archived_by' => $session->archived_by
                ];
            }
            
            return response()->json([
                'success' => true,
                'sessions' => $result
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch archived sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get records for a specific archived session
     */
    public function getSessionRecords(Request $request, $sessionId)
    {
        try {
            $records = DB::table('guardhouse_archived_records')
                ->where('session_id', $sessionId)
                ->orderBy('timestamp', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'records' => $records
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch session records',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
