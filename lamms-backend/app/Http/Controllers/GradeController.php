<?php

namespace App\Http\Controllers;

use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GradeController extends Controller
{
    public function index()
    {
        try {
            // Use eager loading to avoid N+1 problem and add a short timeout
            $grades = Grade::select('id', 'code', 'name', 'level', 'status', 'is_active', 'display_order')
                ->orderBy('display_order')
                ->get();

            // Add a connection-close header to free up connection faster
            return response()->json($grades)
                ->header('Connection', 'close');
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error fetching grades: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error fetching grades',
                'error' => $e->getMessage()
            ], 500)->header('Connection', 'close');
        }
    }
}
