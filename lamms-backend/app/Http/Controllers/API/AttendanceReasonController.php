<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AttendanceReason;
use Illuminate\Http\Request;

class AttendanceReasonController extends Controller
{
    /**
     * Get all attendance reasons grouped by type
     */
    public function index()
    {
        try {
            $reasons = AttendanceReason::getAllGroupedByType();
            
            return response()->json([
                'success' => true,
                'reasons' => $reasons,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance reasons',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get reasons for a specific type (late or excused)
     */
    public function getByType(Request $request, string $type)
    {
        try {
            if (!in_array($type, ['late', 'excused'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid reason type. Must be "late" or "excused".',
                ], 400);
            }

            $reasons = AttendanceReason::getByType($type);
            
            return response()->json([
                'success' => true,
                'type' => $type,
                'reasons' => $reasons,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance reasons',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
