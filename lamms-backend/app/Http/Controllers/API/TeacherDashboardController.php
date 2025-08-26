<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\TeacherSectionSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TeacherDashboardController extends Controller
{
    /**
     * Get teacher dashboard data including profile, subjects, and attendance summary
     */
    public function getDashboardData($teacherId)
    {
        try {
            $teacher = Teacher::with(['user', 'sections.grade', 'subjects'])
                ->findOrFail($teacherId);

            // Get teacher's assigned subjects with section info
            $teacherSubjects = TeacherSectionSubject::with([
                'subject',
                'section.grade',
                'teacher'
            ])
            ->where('teacher_id', $teacherId)
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->subject->id,
                    'name' => $assignment->subject->name,
                    'grade' => $assignment->section->grade->name ?? 'Unknown Grade',
                    'section' => $assignment->section->name ?? 'Unknown Section',
                    'originalSubject' => [
                        'id' => $assignment->subject->id,
                        'name' => $assignment->subject->name
                    ]
                ];
            });

            // Get attendance summary for teacher's students
            $attendanceSummary = $this->getAttendanceSummary($teacherId);

            // Get students with attendance issues
            $studentsWithIssues = $this->getStudentsWithAttendanceIssues($teacherId);

            return response()->json([
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->user->name ?? 'Unknown Teacher',
                    'email' => $teacher->user->email ?? '',
                    'subjects' => $teacherSubjects->pluck('name')->unique()->values()
                ],
                'subjects' => $teacherSubjects,
                'attendanceSummary' => $attendanceSummary,
                'studentsWithIssues' => $studentsWithIssues
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load dashboard data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance summary statistics
     */
    private function getAttendanceSummary($teacherId)
    {
        // Get all students under this teacher's sections
        $studentIds = DB::table('teacher_section_subjects')
            ->join('sections', 'teacher_section_subjects.section_id', '=', 'sections.id')
            ->join('students', 'students.section_id', '=', 'sections.id')
            ->where('teacher_section_subjects.teacher_id', $teacherId)
            ->pluck('students.id')
            ->unique();

        $totalStudents = $studentIds->count();

        if ($totalStudents === 0) {
            return [
                'totalStudents' => 0,
                'studentsWithWarning' => 0,
                'studentsWithCritical' => 0,
                'averageAttendance' => 0
            ];
        }

        // Calculate attendance statistics for the last 30 days
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $attendanceStats = DB::table('attendances')
            ->whereIn('student_id', $studentIds)
            ->where('date', '>=', $thirtyDaysAgo)
            ->select(
                'student_id',
                DB::raw('COUNT(*) as total_days'),
                DB::raw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_days'),
                DB::raw('SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_days')
            )
            ->groupBy('student_id')
            ->get();

        $studentsWithWarning = 0;
        $studentsWithCritical = 0;
        $totalAttendanceRate = 0;

        foreach ($attendanceStats as $stat) {
            $attendanceRate = $stat->total_days > 0 ? ($stat->present_days / $stat->total_days) * 100 : 100;
            $totalAttendanceRate += $attendanceRate;

            if ($attendanceRate < 70) {
                $studentsWithCritical++;
            } elseif ($attendanceRate < 85) {
                $studentsWithWarning++;
            }
        }

        $averageAttendance = $attendanceStats->count() > 0 ? 
            round($totalAttendanceRate / $attendanceStats->count(), 1) : 100;

        return [
            'totalStudents' => $totalStudents,
            'studentsWithWarning' => $studentsWithWarning,
            'studentsWithCritical' => $studentsWithCritical,
            'averageAttendance' => $averageAttendance
        ];
    }

    /**
     * Get students with attendance issues
     */
    private function getStudentsWithAttendanceIssues($teacherId)
    {
        // Get students under this teacher's sections
        $students = DB::table('teacher_section_subjects')
            ->join('sections', 'teacher_section_subjects.section_id', '=', 'sections.id')
            ->join('students', 'students.section_id', '=', 'sections.id')
            ->join('grades', 'sections.grade_id', '=', 'grades.id')
            ->where('teacher_section_subjects.teacher_id', $teacherId)
            ->select(
                'students.id',
                'students.name',
                'grades.name as grade_name',
                'sections.name as section_name'
            )
            ->get();

        $studentsWithIssues = [];
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        foreach ($students as $student) {
            $attendanceRecord = DB::table('attendances')
                ->where('student_id', $student->id)
                ->where('date', '>=', $thirtyDaysAgo)
                ->select(
                    DB::raw('COUNT(*) as total_days'),
                    DB::raw('SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent_days')
                )
                ->first();

            $absentDays = $attendanceRecord->absent_days ?? 0;
            $totalDays = $attendanceRecord->total_days ?? 0;
            
            $severity = 'normal';
            if ($absentDays >= 6) {
                $severity = 'critical';
            } elseif ($absentDays >= 4) {
                $severity = 'warning';
            }

            // Only include students with issues
            if ($severity !== 'normal') {
                $studentsWithIssues[] = [
                    'id' => $student->id,
                    'name' => $student->name,
                    'gradeLevel' => $student->grade_name,
                    'section' => $student->section_name,
                    'absences' => $absentDays,
                    'severity' => $severity
                ];
            }
        }

        return $studentsWithIssues;
    }

    /**
     * Get attendance chart data for teacher's classes
     */
    public function getAttendanceChartData($teacherId)
    {
        try {
            // Get last 4 weeks of data
            $weeks = [];
            $datasets = [];

            for ($i = 3; $i >= 0; $i--) {
                $startDate = Carbon::now()->subWeeks($i)->startOfWeek();
                $endDate = Carbon::now()->subWeeks($i)->endOfWeek();
                $weeks[] = $startDate->format('M j') . '-' . $endDate->format('j');
            }

            // Get attendance data for each week
            $attendanceData = [];
            $absentData = [];

            foreach ($weeks as $index => $week) {
                $startDate = Carbon::now()->subWeeks(3 - $index)->startOfWeek();
                $endDate = Carbon::now()->subWeeks(3 - $index)->endOfWeek();

                $weeklyStats = DB::table('teacher_section_subjects')
                    ->join('sections', 'teacher_section_subjects.section_id', '=', 'sections.id')
                    ->join('students', 'students.section_id', '=', 'sections.id')
                    ->join('attendances', 'students.id', '=', 'attendances.student_id')
                    ->where('teacher_section_subjects.teacher_id', $teacherId)
                    ->whereBetween('attendances.date', [$startDate, $endDate])
                    ->select(
                        DB::raw('SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) as present_count'),
                        DB::raw('SUM(CASE WHEN attendances.status = "absent" THEN 1 ELSE 0 END) as absent_count')
                    )
                    ->first();

                $attendanceData[] = $weeklyStats->present_count ?? 0;
                $absentData[] = $weeklyStats->absent_count ?? 0;
            }

            return response()->json([
                'labels' => $weeks,
                'datasets' => [
                    [
                        'label' => 'Present',
                        'data' => $attendanceData,
                        'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                        'borderColor' => 'rgba(34, 197, 94, 1)',
                        'borderWidth' => 2,
                        'borderRadius' => 4,
                        'borderSkipped' => false,
                    ],
                    [
                        'label' => 'Absent',
                        'data' => $absentData,
                        'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                        'borderColor' => 'rgba(239, 68, 68, 1)',
                        'borderWidth' => 2,
                        'borderRadius' => 4,
                        'borderSkipped' => false,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load chart data',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
