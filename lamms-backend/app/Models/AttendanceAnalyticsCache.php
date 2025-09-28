<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use App\Models\Student;
use App\Models\Attendance;

class AttendanceAnalyticsCache extends Model
{
    use HasFactory;

    protected $table = 'attendance_analytics_cache';

    protected $fillable = [
        'student_id',
        'analysis_date',
        'total_absences_this_year',
        'total_tardies_last_30_days',
        'attendance_percentage_last_30_days',
        'subject_specific_data',
        'pattern_analysis',
        'risk_level',
        'exceeds_18_absence_limit',
        'last_updated'
    ];

    protected $casts = [
        'analysis_date' => 'date',
        'subject_specific_data' => 'array',
        'pattern_analysis' => 'array',
        'exceeds_18_absence_limit' => 'boolean',
        'last_updated' => 'datetime'
    ];

    /**
     * Risk level constants
     */
    const RISK_LOW = 'low';
    const RISK_MEDIUM = 'medium';
    const RISK_HIGH = 'high';
    const RISK_CRITICAL = 'critical';

    /**
     * Get all risk levels
     */
    public static function getRiskLevels(): array
    {
        return [
            self::RISK_LOW => 'Low Risk',
            self::RISK_MEDIUM => 'Medium Risk',
            self::RISK_HIGH => 'High Risk',
            self::RISK_CRITICAL => 'Critical Risk'
        ];
    }

    /**
     * Get risk level colors for UI
     */
    public static function getRiskColors(): array
    {
        return [
            self::RISK_LOW => 'text-green-600 bg-green-100',
            self::RISK_MEDIUM => 'text-yellow-600 bg-yellow-100',
            self::RISK_HIGH => 'text-orange-600 bg-orange-100',
            self::RISK_CRITICAL => 'text-red-600 bg-red-100'
        ];
    }

    /**
     * Relationship: Student
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Scope: Get by risk level
     */
    public function scopeByRiskLevel($query, string $riskLevel)
    {
        return $query->where('risk_level', $riskLevel);
    }

    /**
     * Scope: Get high risk students (high + critical)
     */
    public function scopeHighRisk($query)
    {
        return $query->whereIn('risk_level', [self::RISK_HIGH, self::RISK_CRITICAL]);
    }

    /**
     * Scope: Get students exceeding 18 absence limit
     */
    public function scopeExceeding18Limit($query)
    {
        return $query->where('exceeds_18_absence_limit', true);
    }

    /**
     * Scope: Get recent analysis (today's data)
     */
    public function scopeRecent($query)
    {
        return $query->where('analysis_date', now()->toDateString());
    }

    /**
     * Scope: Get stale cache (needs update)
     */
    public function scopeStale($query, int $hoursThreshold = 24)
    {
        return $query->where('last_updated', '<', now()->subHours($hoursThreshold));
    }

    /**
     * Get risk level color class
     */
    public function getRiskColorAttribute(): string
    {
        $colors = self::getRiskColors();
        return $colors[$this->risk_level] ?? $colors[self::RISK_LOW];
    }

    /**
     * Get formatted attendance percentage
     */
    public function getFormattedAttendancePercentageAttribute(): string
    {
        return number_format($this->attendance_percentage_last_30_days, 1) . '%';
    }

    /**
     * Check if cache is stale
     */
    public function isStale(int $hoursThreshold = 24): bool
    {
        return $this->last_updated < now()->subHours($hoursThreshold);
    }

    /**
     * Get detected patterns as formatted strings
     */
    public function getFormattedPatternsAttribute(): array
    {
        $patterns = $this->pattern_analysis ?? [];
        $formatted = [];
        
        foreach ($patterns as $pattern => $data) {
            switch ($pattern) {
                case 'monday_absences':
                    if ($data['count'] >= 3) {
                        $formatted[] = "Frequently absent on Mondays ({$data['count']} times)";
                    }
                    break;
                case 'friday_absences':
                    if ($data['count'] >= 3) {
                        $formatted[] = "Frequently absent on Fridays ({$data['count']} times)";
                    }
                    break;
                case 'consecutive_absences':
                    if ($data['max_consecutive'] >= 3) {
                        $formatted[] = "Longest absence streak: {$data['max_consecutive']} days";
                    }
                    break;
                case 'subject_specific':
                    foreach ($data as $subject => $subjectData) {
                        if ($subjectData['absence_rate'] > 30) {
                            $formatted[] = "High absence rate in {$subject} ({$subjectData['absence_rate']}%)";
                        }
                    }
                    break;
            }
        }
        
        return $formatted;
    }

    /**
     * Generate recommendations based on analytics
     */
    public function generateRecommendations(): array
    {
        $recommendations = [];
        
        // 18+ absence limit recommendations
        if ($this->exceeds_18_absence_limit) {
            $recommendations[] = [
                'type' => 'critical',
                'title' => 'Chronic Absenteeism Alert',
                'message' => "Student has {$this->total_absences_this_year} absences (exceeds 18 limit)",
                'actions' => [
                    'Schedule immediate parent conference',
                    'Implement daily check-in system',
                    'Consider attendance contract',
                    'Refer to guidance counselor'
                ]
            ];
        }
        
        // Low attendance percentage
        if ($this->attendance_percentage_last_30_days < 80) {
            $recommendations[] = [
                'type' => 'high',
                'title' => 'Low Attendance Rate',
                'message' => "Only {$this->formatted_attendance_percentage} attendance in last 30 days",
                'actions' => [
                    'Contact parents within 3 days',
                    'Investigate barriers to attendance',
                    'Provide academic support for missed work'
                ]
            ];
        }
        
        // High tardiness
        if ($this->total_tardies_last_30_days >= 5) {
            $recommendations[] = [
                'type' => 'medium',
                'title' => 'Frequent Tardiness',
                'message' => "{$this->total_tardies_last_30_days} late arrivals in last 30 days",
                'actions' => [
                    'Discuss morning routine with family',
                    'Consider transportation issues',
                    'Implement punctuality rewards'
                ]
            ];
        }
        
        // Pattern-based recommendations
        $patterns = $this->formatted_patterns;
        foreach ($patterns as $pattern) {
            if (strpos($pattern, 'Monday') !== false || strpos($pattern, 'Friday') !== false) {
                $recommendations[] = [
                    'type' => 'medium',
                    'title' => 'Weekend-Adjacent Absences',
                    'message' => $pattern,
                    'actions' => [
                        'Discuss weekend scheduling with family',
                        'Monitor for extended weekend patterns',
                        'Consider family engagement strategies'
                    ]
                ];
            }
        }
        
        return $recommendations;
    }

    /**
     * Static method to generate or update analytics for a student
     */
    public static function generateForStudent(int $studentId, ?Carbon $analysisDate = null): self
    {
        $analysisDate = $analysisDate ?? now();
        $schoolYearStart = self::getSchoolYearStart($analysisDate);
        
        // Calculate analytics first
        $analytics = self::calculateAnalytics($studentId, $schoolYearStart, $analysisDate);
        
        // Add required fields
        $analytics['student_id'] = $studentId;
        $analytics['analysis_date'] = $analysisDate->toDateString();
        $analytics['last_updated'] = now();
        
        // Get or create cache record with all data
        $cache = self::updateOrCreate(
            [
                'student_id' => $studentId,
                'analysis_date' => $analysisDate->toDateString()
            ],
            $analytics
        );
        
        return $cache->fresh();
    }

    /**
     * Calculate analytics for a student
     */
    private static function calculateAnalytics(int $studentId, Carbon $schoolYearStart, Carbon $analysisDate): array
    {
        $last30Days = $analysisDate->copy()->subDays(30);
        
        // Get all attendance records for this school year
        $yearAttendance = Attendance::where('student_id', $studentId)
            ->where('date', '>=', $schoolYearStart)
            ->where('date', '<=', $analysisDate)
            ->get();
        
        // Get last 30 days attendance
        $last30DaysAttendance = $yearAttendance->where('date', '>=', $last30Days);
        
        // Calculate basic metrics
        $totalAbsencesThisYear = $yearAttendance->whereIn('status', ['absent', 'excused'])->count();
        $totalTardiesLast30Days = $last30DaysAttendance->where('status', 'late')->count();
        
        $totalDaysLast30 = $last30DaysAttendance->count();
        $presentDaysLast30 = $last30DaysAttendance->where('status', 'present')->count();
        $attendancePercentage = $totalDaysLast30 > 0 ? ($presentDaysLast30 / $totalDaysLast30) * 100 : 100;
        
        // Calculate subject-specific data
        $subjectData = self::calculateSubjectSpecificData($studentId, $last30Days, $analysisDate);
        
        // Analyze patterns
        $patternAnalysis = self::analyzePatterns($yearAttendance);
        
        // Determine risk level
        $riskLevel = self::calculateRiskLevel($totalAbsencesThisYear, $attendancePercentage, $totalTardiesLast30Days);
        
        return [
            'total_absences_this_year' => $totalAbsencesThisYear,
            'total_tardies_last_30_days' => $totalTardiesLast30Days,
            'attendance_percentage_last_30_days' => round($attendancePercentage, 2),
            'subject_specific_data' => $subjectData,
            'pattern_analysis' => $patternAnalysis,
            'risk_level' => $riskLevel,
            'exceeds_18_absence_limit' => $totalAbsencesThisYear >= 18
        ];
    }

    /**
     * Calculate subject-specific attendance data
     */
    private static function calculateSubjectSpecificData(int $studentId, Carbon $startDate, Carbon $endDate): array
    {
        $subjectData = [];
        
        $attendanceBySubject = Attendance::where('student_id', $studentId)
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotNull('subject_id')
            ->with('subject')
            ->get()
            ->groupBy('subject_id');
        
        foreach ($attendanceBySubject as $subjectId => $records) {
            $subject = $records->first()->subject;
            if (!$subject) continue;
            
            $total = $records->count();
            $present = $records->where('status', 'present')->count();
            $absent = $records->whereIn('status', ['absent', 'excused'])->count();
            
            $subjectData[$subject->name] = [
                'total_sessions' => $total,
                'present_count' => $present,
                'absent_count' => $absent,
                'attendance_rate' => $total > 0 ? round(($present / $total) * 100, 1) : 100,
                'absence_rate' => $total > 0 ? round(($absent / $total) * 100, 1) : 0
            ];
        }
        
        return $subjectData;
    }

    /**
     * Analyze attendance patterns
     */
    private static function analyzePatterns($attendanceRecords): array
    {
        $patterns = [];
        
        // Analyze day-of-week patterns
        $dayPatterns = $attendanceRecords->groupBy(function ($record) {
            return Carbon::parse($record->date)->format('l'); // Full day name
        });
        
        foreach (['Monday', 'Friday'] as $day) {
            if (isset($dayPatterns[$day])) {
                $dayRecords = $dayPatterns[$day];
                $absences = $dayRecords->whereIn('status', ['absent', 'excused'])->count();
                $patterns[strtolower($day) . '_absences'] = ['count' => $absences];
            }
        }
        
        // Analyze consecutive absences
        $absenceDates = $attendanceRecords
            ->whereIn('status', ['absent', 'excused'])
            ->pluck('date')
            ->map(function ($date) {
                return Carbon::parse($date);
            })
            ->sort()
            ->values();
        
        $maxConsecutive = 0;
        $currentConsecutive = 0;
        
        for ($i = 0; $i < $absenceDates->count(); $i++) {
            if ($i === 0 || $absenceDates[$i]->diffInDays($absenceDates[$i - 1]) === 1) {
                $currentConsecutive++;
            } else {
                $maxConsecutive = max($maxConsecutive, $currentConsecutive);
                $currentConsecutive = 1;
            }
        }
        $maxConsecutive = max($maxConsecutive, $currentConsecutive);
        
        $patterns['consecutive_absences'] = ['max_consecutive' => $maxConsecutive];
        
        return $patterns;
    }

    /**
     * Calculate risk level based on metrics
     */
    private static function calculateRiskLevel(int $totalAbsences, float $attendancePercentage, int $tardies): string
    {
        // Critical: 18+ absences OR attendance < 70%
        if ($totalAbsences >= 18 || $attendancePercentage < 70) {
            return self::RISK_CRITICAL;
        }
        
        // High: 12+ absences OR attendance < 80% OR 8+ tardies
        if ($totalAbsences >= 12 || $attendancePercentage < 80 || $tardies >= 8) {
            return self::RISK_HIGH;
        }
        
        // Medium: 6+ absences OR attendance < 90% OR 4+ tardies
        if ($totalAbsences >= 6 || $attendancePercentage < 90 || $tardies >= 4) {
            return self::RISK_MEDIUM;
        }
        
        return self::RISK_LOW;
    }

    /**
     * Get school year start date
     */
    private static function getSchoolYearStart(Carbon $date): Carbon
    {
        // School year starts in August
        return $date->month >= 8 
            ? $date->copy()->startOfYear()->addMonths(7) // August of current year
            : $date->copy()->subYear()->startOfYear()->addMonths(7); // August of previous year
    }
}
