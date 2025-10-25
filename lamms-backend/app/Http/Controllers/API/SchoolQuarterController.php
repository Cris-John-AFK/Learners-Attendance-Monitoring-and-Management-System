<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SchoolQuarter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SchoolQuarterController extends Controller
{
    /**
     * Get all school quarters
     */
    public function index()
    {
        try {
            $quarters = SchoolQuarter::orderBy('school_year', 'desc')
                ->orderBy('quarter', 'asc')
                ->get();

            return response()->json($quarters, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load school quarters',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new school quarter
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'school_year' => 'required|string',
            'quarter' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            // Check if quarter already exists
            $exists = SchoolQuarter::where('school_year', $request->school_year)
                ->where('quarter', $request->quarter)
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => 'Quarter already exists',
                    'message' => "The {$request->quarter} for {$request->school_year} already exists."
                ], 409);
            }

            $quarter = SchoolQuarter::create([
                'school_year' => $request->school_year,
                'quarter' => $request->quarter,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'description' => $request->description
            ]);

            return response()->json([
                'message' => 'School quarter created successfully',
                'data' => $quarter
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create school quarter',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific school quarter
     */
    public function show($id)
    {
        try {
            $quarter = SchoolQuarter::findOrFail($id);
            return response()->json($quarter, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'School quarter not found',
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update a school quarter
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'school_year' => 'required|string',
            'quarter' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $quarter = SchoolQuarter::findOrFail($id);

            // Check if another quarter with same school_year and quarter exists
            $exists = SchoolQuarter::where('school_year', $request->school_year)
                ->where('quarter', $request->quarter)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'error' => 'Quarter already exists',
                    'message' => "Another {$request->quarter} for {$request->school_year} already exists."
                ], 409);
            }

            $quarter->update([
                'school_year' => $request->school_year,
                'quarter' => $request->quarter,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'description' => $request->description
            ]);

            return response()->json([
                'message' => 'School quarter updated successfully',
                'data' => $quarter
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update school quarter',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a school quarter
     */
    public function destroy($id)
    {
        try {
            $quarter = SchoolQuarter::findOrFail($id);
            $quarter->delete();

            return response()->json([
                'message' => 'School quarter deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete school quarter',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teachers with access to a specific quarter
     */
    public function getTeachers($quarterId)
    {
        try {
            $quarter = SchoolQuarter::findOrFail($quarterId);
            
            // Get teachers with access to this quarter
            $teachers = $quarter->teachers()
                ->with(['user', 'assignments.section.grade'])
                ->get()
                ->map(function ($teacher) use ($quarter) {
                    // Get teacher's primary assignment (homeroom)
                    $primaryAssignment = $teacher->assignments()
                        ->where('is_active', 1)
                        ->where(function($query) {
                            $query->where('is_primary', 1)
                                ->orWhere('role', 'primary')
                                ->orWhereNull('subject_id'); // Homeroom has no subject
                        })
                        ->with(['section.grade'])
                        ->first();
                    
                    $sectionName = 'No Homeroom';
                    $gradeName = null;
                    
                    if ($primaryAssignment && $primaryAssignment->section) {
                        $sectionName = $primaryAssignment->section->name;
                        if ($primaryAssignment->section->grade) {
                            $gradeName = $primaryAssignment->section->grade->name;
                        }
                    }
                    
                    return [
                        'id' => $teacher->id,
                        'name' => $teacher->first_name . ' ' . $teacher->last_name,
                        'email' => $teacher->user->email ?? 'N/A',
                        'grade' => $gradeName,
                        'section' => $sectionName,
                        'access_granted' => $teacher->pivot->created_at->format('Y-m-d')
                    ];
                });

            return response()->json($teachers, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load teachers',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Grant teacher access to a quarter
     */
    public function grantTeacherAccess(Request $request, $quarterId)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:teachers,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            $quarter = SchoolQuarter::findOrFail($quarterId);
            
            // Check if teacher already has access
            if ($quarter->teachers()->where('teacher_id', $request->teacher_id)->exists()) {
                return response()->json([
                    'error' => 'Teacher already has access to this quarter'
                ], 409);
            }

            // Grant access
            $quarter->teachers()->attach($request->teacher_id);

            return response()->json([
                'message' => 'Teacher access granted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to grant teacher access',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke teacher access from a quarter
     */
    public function revokeTeacherAccess($quarterId, $teacherId)
    {
        try {
            $quarter = SchoolQuarter::findOrFail($quarterId);
            $quarter->teachers()->detach($teacherId);

            return response()->json([
                'message' => 'Teacher access revoked successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to revoke teacher access',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get quarters that a specific teacher has access to
     */
    public function getTeacherQuarters($teacherId)
    {
        try {
            $teacher = \App\Models\Teacher::find($teacherId);
            
            if (!$teacher) {
                return response()->json([
                    'error' => 'Teacher not found'
                ], 404);
            }

            // Get quarters the teacher has access to
            $quarters = $teacher->quarters()
                ->orderBy('school_year', 'desc')
                ->orderBy('quarter', 'asc')
                ->get();

            return response()->json($quarters, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load teacher quarters',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
