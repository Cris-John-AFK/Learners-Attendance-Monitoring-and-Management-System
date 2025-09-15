<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AttendanceRecordsController extends Controller
{
    /**
     * Get attendance records for a section within date range
     */
    public function getAttendanceRecords(Request $request)
    {
        $validator = Validator::make(array_merge($request->all(), ['section_id' => $request->route('sectionId')]), [
            'section_id' => 'required|integer',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'subject_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $sectionId = $request->route('sectionId');
            
            // Get attendance sessions with records
            $query = DB::table('attendance_sessions as s')
                ->join('attendance_records as r', 's.id', '=', 'r.attendance_session_id')
                ->join('attendance_statuses as st', 'r.attendance_status_id', '=', 'st.id')
                ->leftJoin('subjects as sub', 's.subject_id', '=', 'sub.id')
                ->where('s.section_id', $sectionId)
                ->whereBetween('s.session_date', [$request->start_date, $request->end_date])
                ->where('r.is_current_version', true);

            if ($request->subject_id) {
                $query->where('s.subject_id', $request->subject_id);
            }

            $records = $query->select([
                's.id as session_id',
                's.session_date',
                's.subject_id',
                'sub.name as subject_name',
                'r.student_id',
                'r.arrival_time',
                'r.marked_at',
                'r.remarks',
                'st.name as status_name',
                'st.code as status_code'
            ])->get();

            // Get students for the section
            $students = DB::table('student_details as sd')
                ->join('student_section as ss', 'sd.id', '=', 'ss.student_id')
                ->where('ss.section_id', $sectionId)
                ->where('sd.isActive', true)
                ->where('ss.is_active', true)
                ->select('sd.id', 'sd.firstName', 'sd.lastName', 'sd.gradeLevel')
                ->get()
                ->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'firstName' => $student->firstName,
                        'lastName' => $student->lastName,
                        'name' => $student->firstName . ' ' . $student->lastName,
                        'gradeLevel' => $student->gradeLevel
                    ];
                });

            // Transform into sessions format - group by session_id to preserve individual sessions
            $sessions = [];
            $sessionGroups = $records->groupBy('session_id');
            
            foreach ($sessionGroups as $sessionId => $sessionRecords) {
                $session = [
                    'id' => $sessionId,
                    'session_date' => $sessionRecords->first()->session_date,
                    'subject' => [
                        'id' => $sessionRecords->first()->subject_id,
                        'name' => $sessionRecords->first()->subject_name
                    ],
                    'attendance_records' => []
                ];

                foreach ($sessionRecords as $record) {
                    $session['attendance_records'][] = [
                        'student_id' => $record->student_id,
                        'arrival_time' => $record->arrival_time,
                        'remarks' => $record->remarks,
                        'attendance_status' => [
                            'name' => $record->status_name,
                            'code' => $record->status_code
                        ]
                    ];
                }

                $sessions[] = $session;
            }

            return response()->json([
                'success' => true,
                'sessions' => $sessions,
                'students' => $students->toArray()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching attendance records: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get students in a section for attendance records
     */
    public function getStudentsBySection($sectionId)
    {
        try {
            $students = DB::table('student_section as ss')
                ->join('student_details as sd', 'ss.student_id', '=', 'sd.id')
                ->where('ss.section_id', $sectionId)
                ->where('ss.is_active', true)
                ->select([
                    'sd.id',
                    'sd.firstName',
                    'sd.lastName',
                    DB::raw("CONCAT(sd.firstName, ' ', sd.lastName) as name"),
                    'sd.gradeLevel'
                ])
                ->get();

            return response()->json([
                'success' => true,
                'students' => $students
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching students: ' . $e->getMessage()
            ], 500);
        }
    }
}
