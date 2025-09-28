<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceAnalyticsCache;
use App\Models\Student;
use App\Models\TeacherSectionSubject;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceAnalyticsService
{
    /**
     * Generate comprehensive analytics for a student
     */
    public function generateStudentAnalytics(int $studentId, ?Carbon $analysisDate = null): array
    {
        $analysisDate = $analysisDate ?? now();

        // Get or generate cached analytics
        $cache = AttendanceAnalyticsCache::generateForStudent($studentId, $analysisDate);

        // Generate recommendations
        $recommendations = $this->generateRecommendations($cache);

        // Get student info
        $student = Student::find($studentId);

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->firstName . ' ' . $student->lastName,
                'grade' => $student->gradeLevel,
                'section' => $student->section,
                'current_status' => $student->current_status
            ],
            'analytics' => [
                'total_absences_this_year' => $cache->total_absences_this_year,
                'total_tardies_last_30_days' => $cache->total_tardies_last_30_days,
                'attendance_percentage_last_30_days' => $cache->attendance_percentage_last_30_days,
                'risk_level' => $cache->risk_level,
                'exceeds_18_absence_limit' => $cache->exceeds_18_absence_limit,
                'patterns_detected' => $cache->formatted_patterns,
                'subject_specific_data' => $cache->subject_specific_data,
                'last_updated' => $cache->last_updated->format('Y-m-d H:i:s')
            ],
            'recommendations' => $recommendations,
            'urgency_legend' => $this->getUrgencyLegend()
        ];
    }

    /**
     * Generate intelligent recommendations based on analytics
     */
    public function generateRecommendations(AttendanceAnalyticsCache $cache): array
    {
        $recommendations = [
            'positive_improvements' => [],
            'areas_of_concern' => [],
            'recommended_next_steps' => []
        ];

        $student = $cache->student;
        $analytics = $cache;

        // POSITIVE IMPROVEMENTS DETECTION
        $improvements = $this->detectPositiveImprovements($student->id, $analytics);
        $recommendations['positive_improvements'] = $improvements;

        // AREAS OF CONCERN DETECTION
        $concerns = $this->detectAreasOfConcern($analytics);
        $recommendations['areas_of_concern'] = $concerns;

        // RECOMMENDED NEXT STEPS
        $nextSteps = $this->generateNextSteps($analytics, $concerns);
        $recommendations['recommended_next_steps'] = $nextSteps;

        return $recommendations;
    }

    /**
     * Detect positive improvements in attendance
     */
    private function detectPositiveImprovements(int $studentId, AttendanceAnalyticsCache $analytics): array
    {
        $improvements = [];

        // Compare with previous month
        $previousMonth = AttendanceAnalyticsCache::where('student_id', $studentId)
            ->where('analysis_date', '<', $analytics->analysis_date)
            ->orderBy('analysis_date', 'desc')
            ->first();

        if ($previousMonth) {
            // Attendance percentage improvement
            $percentageImprovement = $analytics->attendance_percentage_last_30_days - $previousMonth->attendance_percentage_last_30_days;
            if ($percentageImprovement >= 10) {
                $improvements[] = [
                    'type' => 'improvement',
                    'icon' => '📈',
                    'message' => "Attendance improved by " . number_format($percentageImprovement, 1) . "% from last month",
                    'evidence' => "From {$previousMonth->formatted_attendance_percentage} to {$analytics->formatted_attendance_percentage}"
                ];
            }

            // Tardiness improvement
            $tardinessImprovement = $previousMonth->total_tardies_last_30_days - $analytics->total_tardies_last_30_days;
            if ($tardinessImprovement >= 3) {
                $improvements[] = [
                    'type' => 'improvement',
                    'icon' => '⏰',
                    'message' => "Tardiness reduced by {$tardinessImprovement} instances",
                    'evidence' => "From {$previousMonth->total_tardies_last_30_days} to {$analytics->total_tardies_last_30_days} late arrivals"
                ];
            }

            // Risk level improvement
            $riskLevels = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
            if ($riskLevels[$analytics->risk_level] < $riskLevels[$previousMonth->risk_level]) {
                $improvements[] = [
                    'type' => 'improvement',
                    'icon' => '✅',
                    'message' => "Risk level improved from {$previousMonth->risk_level} to {$analytics->risk_level}",
                    'evidence' => "Consistent positive attendance patterns detected"
                ];
            }
        }

        // Perfect attendance streaks
        $perfectDays = $this->calculatePerfectAttendanceStreak($studentId);
        if ($perfectDays >= 7) {
            $improvements[] = [
                'type' => 'achievement',
                'icon' => '🏆',
                'message' => "Perfect attendance streak: {$perfectDays} days",
                'evidence' => "No absences or tardies in the last {$perfectDays} school days"
            ];
        }

        // Subject-specific improvements
        foreach ($analytics->subject_specific_data as $subject => $data) {
            if ($data['attendance_rate'] >= 95 && $data['total_sessions'] >= 10) {
                $improvements[] = [
                    'type' => 'subject_excellence',
                    'icon' => '📚',
                    'message' => "Excellent attendance in {$subject}",
                    'evidence' => "{$data['attendance_rate']}% attendance rate ({$data['present_count']}/{$data['total_sessions']} sessions)"
                ];
            }
        }

        return $improvements;
    }

    /**
     * Detect areas of concern
     */
    private function detectAreasOfConcern(AttendanceAnalyticsCache $analytics): array
    {
        $concerns = [];

        // CRITICAL: 18+ absence limit exceeded
        if ($analytics->exceeds_18_absence_limit) {
            $concerns[] = [
                'urgency' => 'critical',
                'icon' => '🚨',
                'title' => 'Chronic Absenteeism Alert',
                'message' => "Student has {$analytics->total_absences_this_year} absences (exceeds 18 limit)",
                'evidence' => "Total absences this school year: {$analytics->total_absences_this_year}/18 limit",
                'impact' => 'May affect academic progression and graduation requirements'
            ];
        }

        // HIGH: Low attendance percentage
        if ($analytics->attendance_percentage_last_30_days < 80) {
            $urgency = $analytics->attendance_percentage_last_30_days < 70 ? 'critical' : 'high';
            $concerns[] = [
                'urgency' => $urgency,
                'icon' => '📉',
                'title' => 'Low Attendance Rate',
                'message' => "Only {$analytics->formatted_attendance_percentage} attendance in last 30 days",
                'evidence' => "Below 80% attendance threshold (school policy requirement)",
                'impact' => 'Missing significant instructional time affecting academic performance'
            ];
        }

        // MEDIUM-HIGH: Frequent tardiness
        if ($analytics->total_tardies_last_30_days >= 5) {
            $urgency = $analytics->total_tardies_last_30_days >= 8 ? 'high' : 'medium';
            $concerns[] = [
                'urgency' => $urgency,
                'icon' => '⏰',
                'title' => 'Frequent Tardiness Pattern',
                'message' => "{$analytics->total_tardies_last_30_days} late arrivals in last 30 days",
                'evidence' => "Exceeds acceptable tardiness threshold (3 occurrences per month)",
                'impact' => 'Disrupts learning and indicates potential scheduling issues'
            ];
        }

        // Pattern-based concerns
        $patterns = $analytics->formatted_patterns;
        foreach ($patterns as $pattern) {
            if (strpos($pattern, 'Monday') !== false || strpos($pattern, 'Friday') !== false) {
                $concerns[] = [
                    'urgency' => 'medium',
                    'icon' => '📅',
                    'title' => 'Weekend-Adjacent Absence Pattern',
                    'message' => $pattern,
                    'evidence' => 'Consistent pattern suggests extended weekend activities',
                    'impact' => 'May indicate family scheduling conflicts or transportation issues'
                ];
            }

            if (strpos($pattern, 'consecutive') !== false) {
                $concerns[] = [
                    'urgency' => 'high',
                    'icon' => '📊',
                    'title' => 'Extended Absence Periods',
                    'message' => $pattern,
                    'evidence' => 'Multiple consecutive absences detected',
                    'impact' => 'Extended absences significantly impact learning continuity'
                ];
            }
        }

        // Subject-specific concerns
        foreach ($analytics->subject_specific_data as $subject => $data) {
            if ($data['absence_rate'] > 30 && $data['total_sessions'] >= 5) {
                $urgency = $data['absence_rate'] > 50 ? 'high' : 'medium';
                $concerns[] = [
                    'urgency' => $urgency,
                    'icon' => '📖',
                    'title' => "Poor Attendance in {$subject}",
                    'message' => "{$data['absence_rate']}% absence rate in {$subject}",
                    'evidence' => "Absent {$data['absent_count']} out of {$data['total_sessions']} sessions",
                    'impact' => "Subject-specific learning gaps may develop"
                ];
            }
        }

        // Sort by urgency (critical first)
        $urgencyOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        usort($concerns, function ($a, $b) use ($urgencyOrder) {
            return $urgencyOrder[$b['urgency']] - $urgencyOrder[$a['urgency']];
        });

        return $concerns;
    }

    /**
     * Generate specific next steps based on concerns
     */
    private function generateNextSteps(AttendanceAnalyticsCache $analytics, array $concerns): array
    {
        $nextSteps = [];

        // Critical actions for 18+ absences
        if ($analytics->exceeds_18_absence_limit) {
            $nextSteps[] = [
                'urgency' => 'critical',
                'icon' => '📞',
                'action' => 'Schedule immediate parent conference',
                'timeline' => 'Within 24 hours',
                'details' => 'Discuss chronic absenteeism and develop intervention plan'
            ];

            $nextSteps[] = [
                'urgency' => 'critical',
                'icon' => '📋',
                'action' => 'Implement daily check-in system',
                'timeline' => 'Starting tomorrow',
                'details' => 'Monitor daily attendance and provide immediate support'
            ];

            $nextSteps[] = [
                'urgency' => 'critical',
                'icon' => '📄',
                'action' => 'Create attendance contract',
                'timeline' => 'Within 3 days',
                'details' => 'Formal agreement with specific attendance goals and consequences'
            ];
        }

        // High priority actions for low attendance
        if ($analytics->attendance_percentage_last_30_days < 80) {
            $nextSteps[] = [
                'urgency' => 'high',
                'icon' => '👨‍👩‍👧‍👦',
                'action' => 'Contact parents within 3 days',
                'timeline' => 'Within 72 hours',
                'details' => 'Discuss attendance concerns and identify barriers'
            ];

            $nextSteps[] = [
                'urgency' => 'high',
                'icon' => '🔍',
                'action' => 'Investigate attendance barriers',
                'timeline' => 'This week',
                'details' => 'Transportation, health, family, or academic issues'
            ];
        }

        // Tardiness interventions
        if ($analytics->total_tardies_last_30_days >= 5) {
            $nextSteps[] = [
                'urgency' => 'medium',
                'icon' => '🌅',
                'action' => 'Discuss morning routine with family',
                'timeline' => 'Next parent contact',
                'details' => 'Address potential scheduling or transportation issues'
            ];

            if ($analytics->total_tardies_last_30_days >= 8) {
                $nextSteps[] = [
                    'urgency' => 'high',
                    'icon' => '🚌',
                    'action' => 'Evaluate transportation options',
                    'timeline' => 'Within 1 week',
                    'details' => 'Consider alternative transportation or schedule adjustments'
                ];
            }
        }

        // Pattern-specific interventions
        $patterns = $analytics->formatted_patterns;
        foreach ($patterns as $pattern) {
            if (strpos($pattern, 'Monday') !== false || strpos($pattern, 'Friday') !== false) {
                $nextSteps[] = [
                    'urgency' => 'medium',
                    'icon' => '📅',
                    'action' => 'Address weekend scheduling conflicts',
                    'timeline' => 'Next family meeting',
                    'details' => 'Discuss family activities and school attendance priorities'
                ];
            }
        }

        // Subject-specific interventions
        foreach ($analytics->subject_specific_data as $subject => $data) {
            if ($data['absence_rate'] > 40) {
                $nextSteps[] = [
                    'urgency' => 'medium',
                    'icon' => '📚',
                    'action' => "Provide {$subject} catch-up support",
                    'timeline' => 'Next week',
                    'details' => 'Arrange tutoring or makeup sessions for missed content'
                ];
            }
        }

        // Positive reinforcement for improvements
        if (empty($concerns)) {
            $nextSteps[] = [
                'urgency' => 'low',
                'icon' => '🎉',
                'action' => 'Continue positive reinforcement',
                'timeline' => 'Ongoing',
                'details' => 'Acknowledge good attendance and maintain current strategies'
            ];
        }

        // Sort by urgency
        $urgencyOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        usort($nextSteps, function ($a, $b) use ($urgencyOrder) {
            return $urgencyOrder[$b['urgency']] - $urgencyOrder[$a['urgency']];
        });

        return $nextSteps;
    }

    /**
     * Calculate perfect attendance streak
     */
    private function calculatePerfectAttendanceStreak(int $studentId): int
    {
        $recentAttendance = Attendance::where('student_id', $studentId)
            ->where('date', '>=', now()->subDays(30))
            ->orderBy('date', 'desc')
            ->get();

        $streak = 0;
        foreach ($recentAttendance as $record) {
            if ($record->status === 'present') {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Get urgency legend for UI
     */
    public function getUrgencyLegend(): array
    {
        return [
            'critical' => [
                'label' => 'Critical',
                'color' => 'text-red-600 bg-red-100 border-red-200',
                'description' => 'Immediate action required - chronic absenteeism threshold exceeded'
            ],
            'high' => [
                'label' => 'High Priority',
                'color' => 'text-orange-600 bg-orange-100 border-orange-200',
                'description' => 'Action needed within 3 days - significant attendance concerns'
            ],
            'medium' => [
                'label' => 'Medium Priority',
                'color' => 'text-yellow-600 bg-yellow-100 border-yellow-200',
                'description' => 'Monitor closely - patterns developing that need attention'
            ],
            'low' => [
                'label' => 'Low Priority',
                'color' => 'text-green-600 bg-green-100 border-green-200',
                'description' => 'Maintain current approach - attendance is satisfactory'
            ]
        ];
    }

    /**
     * Generate analytics for all students in a teacher's sections
     */
    public function generateTeacherStudentAnalytics(int $teacherId): array
    {
        // Get all students assigned to this teacher
        $teacherAssignments = TeacherSectionSubject::where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->with(['section.students'])
            ->get();

        $allStudents = collect();
        foreach ($teacherAssignments as $assignment) {
            $students = $assignment->section->students()->where('current_status', 'active')->get();
            $allStudents = $allStudents->merge($students);
        }

        // Remove duplicates
        $uniqueStudents = $allStudents->unique('id');

        $analytics = [];
        $summary = [
            'total_students' => $uniqueStudents->count(),
            'critical_risk' => 0,
            'high_risk' => 0,
            'medium_risk' => 0,
            'low_risk' => 0,
            'exceeding_18_limit' => 0
        ];

        foreach ($uniqueStudents as $student) {
            $studentAnalytics = $this->generateStudentAnalytics($student->id);
            $analytics[] = $studentAnalytics;

            // Update summary counts
            $riskLevel = $studentAnalytics['analytics']['risk_level'];
            $summary[$riskLevel . '_risk']++;

            if ($studentAnalytics['analytics']['exceeds_18_absence_limit']) {
                $summary['exceeding_18_limit']++;
            }
        }

        // Sort by risk level (critical first)
        $riskOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
        usort($analytics, function ($a, $b) use ($riskOrder) {
            $aRisk = $riskOrder[$a['analytics']['risk_level']];
            $bRisk = $riskOrder[$b['analytics']['risk_level']];
            return $bRisk - $aRisk;
        });

        return [
            'summary' => $summary,
            'students' => $analytics,
            'generated_at' => now()->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Create notifications for critical attendance issues
     */
    public function createAttendanceNotifications(int $studentId): void
    {
        $analytics = AttendanceAnalyticsCache::where('student_id', $studentId)
            ->where('analysis_date', now()->toDateString())
            ->first();

        if (!$analytics) {
            return;
        }

        $student = Student::find($studentId);
        if (!$student) {
            return;
        }

        // Get teachers for this student
        $teacherIds = TeacherSectionSubject::whereHas('section.students', function ($query) use ($studentId) {
            $query->where('student_details.id', $studentId);
        })->pluck('teacher_id')->unique();

        // Create notifications for critical issues
        if ($analytics->exceeds_18_absence_limit) {
            foreach ($teacherIds as $teacherId) {
                $teacher = \App\Models\Teacher::find($teacherId);
                if ($teacher && $teacher->user) {
                    Notification::createAttendanceAlert(
                        $teacher->user->id,
                        $studentId,
                        'critical',
                        [
                            'total_absences' => $analytics->total_absences_this_year,
                            'attendance_percentage' => $analytics->attendance_percentage_last_30_days,
                            'alert_type' => '18_absence_limit_exceeded'
                        ]
                    );
                }
            }
        }

        // Create notifications for high risk students
        if ($analytics->risk_level === 'high' || $analytics->risk_level === 'critical') {
            foreach ($teacherIds as $teacherId) {
                $teacher = \App\Models\Teacher::find($teacherId);
                if ($teacher && $teacher->user) {
                    Notification::createAttendanceAlert(
                        $teacher->user->id,
                        $studentId,
                        $analytics->risk_level,
                        [
                            'risk_level' => $analytics->risk_level,
                            'attendance_percentage' => $analytics->attendance_percentage_last_30_days,
                            'total_tardies' => $analytics->total_tardies_last_30_days
                        ]
                    );
                }
            }
        }
    }
}
