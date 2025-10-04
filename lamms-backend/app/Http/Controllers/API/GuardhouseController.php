<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GuardhouseController extends Controller
{
    /**
     * Get student by QR code and return verification data
     */
    public function verifyQRCode(Request $request)
    {
        try {
            $qrCode = $request->input('qr_code');
            
            if (!$qrCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR code is required'
                ], 400);
            }

            // Find student by QR code
            $student = DB::table('student_qr_codes')
                ->join('student_details', 'student_qr_codes.student_id', '=', 'student_details.id')
                ->where('student_qr_codes.qr_code_data', $qrCode)
                ->where('student_qr_codes.is_active', true)
                ->select(
                    'student_details.id',
                    'student_details.firstName',
                    'student_details.lastName', 
                    'student_details.gradeLevel',
                    'student_details.section',
                    'student_details.gender',
                    'student_details.profilePhoto',
                    'student_qr_codes.qr_code_data'
                )
                ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid QR code or student not found'
                ], 404);
            }

            // Get today's attendance records for this student
            $today = Carbon::today();
            $todayRecords = DB::table('guardhouse_attendance')
                ->where('student_id', $student->id)
                ->whereDate('date', $today)
                ->orderBy('timestamp', 'desc')
                ->get();

            // Determine next record type
            $nextRecordType = 'check-in';
            if ($todayRecords->count() > 0) {
                $lastRecord = $todayRecords->first();
                $nextRecordType = $lastRecord->record_type === 'check-in' ? 'check-out' : 'check-in';
            }

            // Set default photo if not available
            $photoPath = $student->profilePhoto;
            if (!$photoPath) {
                $photoPath = $student->gender === 'male' 
                    ? '/demo/images/avatar/default-male-student.png'
                    : '/demo/images/avatar/default-female-student.png';
            }

            return response()->json([
                'success' => true,
                'student' => [
                    'id' => $student->id,
                    'name' => $student->firstName . ' ' . $student->lastName,
                    'firstName' => $student->firstName,
                    'lastName' => $student->lastName,
                    'gradeLevel' => $student->gradeLevel,
                    'section' => $student->section,
                    'gender' => $student->gender,
                    'photo' => $photoPath,
                    'qr_code' => $student->qr_code_data
                ],
                'next_record_type' => $nextRecordType,
                'today_records_count' => $todayRecords->count()
            ]);

        } catch (\Exception $e) {
            Log::error('GuardhouseController@verifyQRCode error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Record attendance (check-in or check-out)
     */
    public function recordAttendance(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required|integer',
                'qr_code_data' => 'required|string',
                'record_type' => 'required|in:check-in,check-out',
                'is_manual' => 'boolean',
                'notes' => 'nullable|string'
            ]);

            $studentId = $request->input('student_id');
            $qrCodeData = $request->input('qr_code_data');
            $recordType = $request->input('record_type');
            $isManual = $request->input('is_manual', false);
            $notes = $request->input('notes');

            // Verify student exists
            $student = DB::table('student_details')->where('id', $studentId)->first();
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Check for duplicate records within 5 minutes
            $fiveMinutesAgo = Carbon::now()->subMinutes(5);
            $recentRecord = DB::table('guardhouse_attendance')
                ->where('student_id', $studentId)
                ->where('record_type', $recordType)
                ->where('timestamp', '>=', $fiveMinutesAgo)
                ->first();

            if ($recentRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate record detected. Please wait 5 minutes before scanning again.'
                ], 409);
            }

            // Create attendance record
            $attendanceId = DB::table('guardhouse_attendance')->insertGetId([
                'student_id' => $studentId,
                'qr_code_data' => $qrCodeData,
                'record_type' => $recordType,
                'timestamp' => Carbon::now(),
                'date' => Carbon::today(),
                'guard_name' => 'Bread Doe',
                'guard_id' => 'G-12345',
                'is_manual' => $isManual,
                'notes' => $notes,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            // Get the created record with student info
            $record = DB::table('guardhouse_attendance')
                ->join('student_details', 'guardhouse_attendance.student_id', '=', 'student_details.id')
                ->where('guardhouse_attendance.id', $attendanceId)
                ->select(
                    'guardhouse_attendance.*',
                    'student_details.firstName',
                    'student_details.lastName',
                    'student_details.gradeLevel',
                    'student_details.section',
                    'student_details.profilePhoto',
                    'student_details.gender'
                )
                ->first();

            // Format response
            $photoPath = $record->profilePhoto;
            if (!$photoPath) {
                $photoPath = $record->gender === 'male' 
                    ? '/demo/images/avatar/default-male-student.png'
                    : '/demo/images/avatar/default-female-student.png';
            }

            return response()->json([
                'success' => true,
                'message' => ucfirst($recordType) . ' recorded successfully',
                'record' => [
                    'id' => $record->id,
                    'student_id' => $record->student_id,
                    'student_name' => $record->firstName . ' ' . $record->lastName,
                    'grade_level' => $record->gradeLevel,
                    'section' => $record->section,
                    'photo' => $photoPath,
                    'record_type' => $record->record_type,
                    'timestamp' => $record->timestamp,
                    'date' => $record->date,
                    'is_manual' => $record->is_manual,
                    'notes' => $record->notes
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('GuardhouseController@recordAttendance error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Get attendance records for today
     */
    public function getTodayRecords(Request $request)
    {
        try {
            $date = $request->input('date', Carbon::today()->toDateString());
            $recordType = $request->input('record_type'); // 'check-in', 'check-out', or null for all
            $search = $request->input('search');

            $query = DB::table('guardhouse_attendance')
                ->join('student_details', 'guardhouse_attendance.student_id', '=', 'student_details.id')
                ->whereDate('guardhouse_attendance.date', $date)
                ->select(
                    'guardhouse_attendance.*',
                    'student_details.firstName',
                    'student_details.lastName',
                    'student_details.gradeLevel',
                    'student_details.section',
                    'student_details.profilePhoto',
                    'student_details.gender'
                );

            if ($recordType) {
                $query->where('guardhouse_attendance.record_type', $recordType);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('student_details.firstName', 'ILIKE', "%{$search}%")
                      ->orWhere('student_details.lastName', 'ILIKE', "%{$search}%")
                      ->orWhere('student_details.gradeLevel', 'ILIKE', "%{$search}%")
                      ->orWhere('student_details.section', 'ILIKE', "%{$search}%")
                      ->orWhere('guardhouse_attendance.student_id', 'LIKE', "%{$search}%");
                });
            }

            $records = $query->orderBy('guardhouse_attendance.timestamp', 'desc')->get();

            // Format records
            $formattedRecords = $records->map(function($record) {
                $photoPath = $record->profilePhoto;
                if (!$photoPath) {
                    $photoPath = $record->gender === 'male' 
                        ? '/demo/images/avatar/default-male-student.png'
                        : '/demo/images/avatar/default-female-student.png';
                }

                return [
                    'id' => $record->id,
                    'student_id' => $record->student_id,
                    'name' => $record->firstName . ' ' . $record->lastName,
                    'gradeLevel' => $record->gradeLevel,
                    'section' => $record->section,
                    'recordType' => $record->record_type, // Frontend expects camelCase
                    'record_type' => $record->record_type, // Keep for backward compatibility
                    'timestamp' => $record->timestamp,
                    'date' => $record->date,
                    'photo' => $photoPath,
                    'guard_name' => $record->guard_name,
                    'guard_id' => $record->guard_id,
                    'is_manual' => $record->is_manual,
                    'notes' => $record->notes,
                    'recordId' => $record->id . '-' . strtotime($record->timestamp) // Unique ID for frontend
                ];
            });

            return response()->json([
                'success' => true,
                'records' => $formattedRecords,
                'total' => $records->count()
            ]);

        } catch (\Exception $e) {
            Log::error('GuardhouseController@getTodayRecords error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Manual check-in/check-out by student ID
     */
    public function manualRecord(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required|integer',
                'record_type' => 'required|in:check-in,check-out',
                'notes' => 'nullable|string'
            ]);

            $studentId = $request->input('student_id');
            $recordType = $request->input('record_type');
            $notes = $request->input('notes', 'Manual entry by guard');

            // Get student info
            $student = DB::table('student_details')->where('id', $studentId)->first();
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Get QR code for this student
            $qrCode = DB::table('student_qr_codes')
                ->where('student_id', $studentId)
                ->where('is_active', true)
                ->first();

            $qrCodeData = $qrCode ? $qrCode->qr_code_data : 'MANUAL_ENTRY';

            // Create attendance record
            $attendanceId = DB::table('guardhouse_attendance')->insertGetId([
                'student_id' => $studentId,
                'qr_code_data' => $qrCodeData,
                'record_type' => $recordType,
                'timestamp' => Carbon::now(),
                'date' => Carbon::today(),
                'guard_name' => 'Bread Doe',
                'guard_id' => 'G-12345',
                'is_manual' => true,
                'notes' => $notes,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            // Format response
            $photoPath = $student->profilePhoto;
            if (!$photoPath) {
                $photoPath = $student->gender === 'male' 
                    ? '/demo/images/avatar/default-male-student.png'
                    : '/demo/images/avatar/default-female-student.png';
            }

            return response()->json([
                'success' => true,
                'message' => 'Manual ' . $recordType . ' recorded successfully',
                'record' => [
                    'id' => $attendanceId,
                    'student_id' => $studentId,
                    'student_name' => $student->firstName . ' ' . $student->lastName,
                    'grade_level' => $student->gradeLevel,
                    'section' => $student->section,
                    'photo' => $photoPath,
                    'record_type' => $recordType,
                    'timestamp' => Carbon::now(),
                    'date' => Carbon::today(),
                    'is_manual' => true,
                    'notes' => $notes
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('GuardhouseController@manualRecord error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error occurred'
            ], 500);
        }
    }

    /**
     * Get historical attendance records (Admin only)
     */
    public function getHistoricalRecords(Request $request)
    {
        try {
            $date = $request->input('date');
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 50);
            $search = $request->input('search');
            $recordType = $request->input('record_type');

            if (!$date) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date parameter is required'
                ], 400);
            }

            // Check if data is in cache first
            $cacheData = DB::table('guardhouse_attendance_cache')
                ->where('cache_date', $date)
                ->first();

            if ($cacheData && !$search && !$recordType) {
                // Return cached data
                $records = json_decode($cacheData->records_data, true);
                
                // Paginate cached data
                $offset = ($page - 1) * $perPage;
                $paginatedRecords = array_slice($records, $offset, $perPage);
                
                return response()->json([
                    'success' => true,
                    'records' => $paginatedRecords,
                    'total' => count($records),
                    'page' => $page,
                    'per_page' => $perPage,
                ]);
            }
            
            // Query new archive system for historical data
            $sessions = DB::table('guardhouse_archive_sessions')
                ->where('session_date', $date)
                ->get();
            
            if ($sessions->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'last_page' => 1
                    ]
                ]);
            }
            
            $sessionIds = $sessions->pluck('id');
            
            $query = DB::table('guardhouse_archived_records')
                ->whereIn('session_id', $sessionIds);

            if ($recordType) {
                $query->where('record_type', $recordType);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('student_name', 'ILIKE', "%{$search}%")
                      ->orWhere('student_id', 'ILIKE', "%{$search}%");
                });
            }

            $totalRecords = $query->count();
            $records = $query->orderBy('timestamp', 'desc')
                           ->offset(($page - 1) * $perPage)
                           ->limit($perPage)
                           ->get();

            // Format records for new archive system
            $formattedRecords = $records->map(function($record) {
                return [
                    'id' => $record->id,
                    'student_id' => $record->student_id,
                    'name' => $record->student_name,
                    'gradeLevel' => $record->grade_level,
                    'section' => $record->section,
                    'recordType' => $record->record_type,
                    'timestamp' => $record->timestamp,
                    'date' => $record->session_date,
                    'photo' => '/demo/images/avatar/default-student.png', // Default photo
                    'guard_name' => 'System',
                    'is_manual' => false,
                    'notes' => null
                ];
            });

            return response()->json([
                'success' => true,
                'records' => $formattedRecords,
                'total' => $totalRecords,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($totalRecords / $perPage),
                'cached' => false
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching historical records: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch historical records'
            ], 500);
        }
    }

    /**
     * Get attendance statistics for admin dashboard
     */
    public function getAttendanceStats(Request $request)
    {
        try {
            $startDate = $request->input('start_date', Carbon::today()->subDays(7)->toDateString());
            $endDate = $request->input('end_date', Carbon::today()->toDateString());

            // Get daily stats from cache and current records
            $stats = [];
            
            // Current day stats (from main table)
            $todayStats = DB::table('guardhouse_attendance')
                ->whereDate('date', Carbon::today())
                ->selectRaw('
                    COUNT(CASE WHEN record_type = \'check-in\' THEN 1 END) as checkins,
                    COUNT(CASE WHEN record_type = \'check-out\' THEN 1 END) as checkouts,
                    date
                ')
                ->groupBy('date')
                ->first();

            if ($todayStats) {
                $stats[] = [
                    'date' => $todayStats->date,
                    'checkins' => $todayStats->checkins,
                    'checkouts' => $todayStats->checkouts,
                    'total' => $todayStats->checkins + $todayStats->checkouts
                ];
            }

            // Historical stats (from cache)
            $historicalStats = DB::table('guardhouse_attendance_cache')
                ->whereBetween('cache_date', [$startDate, $endDate])
                ->where('cache_date', '<', Carbon::today())
                ->select('cache_date as date', 'total_checkins as checkins', 'total_checkouts as checkouts')
                ->get();

            foreach ($historicalStats as $stat) {
                $stats[] = [
                    'date' => $stat->date,
                    'checkins' => $stat->checkins,
                    'checkouts' => $stat->checkouts,
                    'total' => $stat->checkins + $stat->checkouts
                ];
            }

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching attendance stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance statistics'
            ], 500);
        }
    }

    /**
     * Toggle scanner status (Admin function to enable/disable guardhouse scanner)
     */
    public function toggleScanner(Request $request)
    {
        try {
            $enabled = $request->input('enabled', true);
            
            // Store scanner status in cache or database
            // Using cache table for simplicity
            $cacheKey = 'guardhouse_scanner_enabled';
            
            // Check if cache entry exists
            $existingCache = DB::table('cache')->where('key', $cacheKey)->first();
            
            if ($existingCache) {
                // Update existing cache entry
                DB::table('cache')->where('key', $cacheKey)->update([
                    'value' => serialize($enabled),
                    'expiration' => Carbon::now()->addYears(1)->timestamp // Long expiration
                ]);
            } else {
                // Create new cache entry
                DB::table('cache')->insert([
                    'key' => $cacheKey,
                    'value' => serialize($enabled),
                    'expiration' => Carbon::now()->addYears(1)->timestamp
                ]);
            }

            Log::info('Scanner status toggled', [
                'enabled' => $enabled,
                'admin_action' => true,
                'timestamp' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => $enabled ? 'Scanner enabled successfully' : 'Scanner disabled successfully',
                'scanner_enabled' => $enabled
            ]);

        } catch (\Exception $e) {
            Log::error('GuardhouseController@toggleScanner error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle scanner status'
            ], 500);
        }
    }

    /**
     * Get current scanner status
     */
    public function getScannerStatus(Request $request)
    {
        try {
            $cacheKey = 'guardhouse_scanner_enabled';
            
            $cache = DB::table('cache')->where('key', $cacheKey)->first();
            
            $enabled = true; // Default to enabled
            if ($cache) {
                $enabled = unserialize($cache->value);
            }

            return response()->json([
                'success' => true,
                'scanner_enabled' => $enabled
            ]);

        } catch (\Exception $e) {
            Log::error('GuardhouseController@getScannerStatus error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get scanner status',
                'scanner_enabled' => true // Default fallback
            ], 500);
        }
    }
}
