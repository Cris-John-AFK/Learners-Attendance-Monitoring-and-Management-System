<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Student;
use App\Models\User;
use App\Models\Attendance;

class StudentArchive extends Model
{
    use HasFactory;

    protected $table = 'student_archive';

    protected $fillable = [
        'original_student_id',
        'student_data',
        'attendance_summary',
        'status_history',
        'notes_summary',
        'final_status',
        'archive_reason',
        'archived_date',
        'archived_by_user_id',
        'can_be_restored',
        'auto_archive_date'
    ];

    protected $casts = [
        'student_data' => 'array',
        'attendance_summary' => 'array',
        'status_history' => 'array',
        'notes_summary' => 'array',
        'archived_date' => 'date',
        'can_be_restored' => 'boolean',
        'auto_archive_date' => 'datetime'
    ];

    /**
     * Relationship: User who archived the student
     */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    /**
     * Scope: Get restorable archives
     */
    public function scopeRestorable($query)
    {
        return $query->where('can_be_restored', true);
    }

    /**
     * Scope: Get archives by final status
     */
    public function scopeByFinalStatus($query, string $status)
    {
        return $query->where('final_status', $status);
    }

    /**
     * Scope: Get archives within date range
     */
    public function scopeArchivedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('archived_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Get auto-archived records
     */
    public function scopeAutoArchived($query)
    {
        return $query->whereNotNull('auto_archive_date');
    }

    /**
     * Scope: Get manually archived records
     */
    public function scopeManuallyArchived($query)
    {
        return $query->whereNull('auto_archive_date');
    }

    /**
     * Get student's full name from archived data
     */
    public function getStudentNameAttribute(): string
    {
        $data = $this->student_data;
        
        if (isset($data['firstName']) && isset($data['lastName'])) {
            $name = trim($data['firstName'] . ' ' . $data['lastName']);
            if (isset($data['middleName']) && !empty($data['middleName'])) {
                $name = $data['firstName'] . ' ' . $data['middleName'] . ' ' . $data['lastName'];
            }
            return $name;
        }
        
        return $data['name'] ?? 'Unknown Student';
    }

    /**
     * Get student's grade level from archived data
     */
    public function getGradeLevelAttribute(): string
    {
        return $this->student_data['gradeLevel'] ?? 'Unknown';
    }

    /**
     * Get student's section from archived data
     */
    public function getSectionAttribute(): string
    {
        return $this->student_data['section'] ?? 'Unknown';
    }

    /**
     * Get attendance percentage from summary
     */
    public function getAttendancePercentageAttribute(): float
    {
        return $this->attendance_summary['overall_percentage'] ?? 0.0;
    }

    /**
     * Get total absences from summary
     */
    public function getTotalAbsencesAttribute(): int
    {
        return $this->attendance_summary['total_absences'] ?? 0;
    }

    /**
     * Get days since archived
     */
    public function getDaysSinceArchivedAttribute(): int
    {
        return $this->archived_date->diffInDays(now());
    }

    /**
     * Check if archive is eligible for auto-deletion (e.g., after 1 year)
     */
    public function isEligibleForDeletion(int $daysThreshold = 365): bool
    {
        return $this->days_since_archived >= $daysThreshold;
    }

    /**
     * Check if this was auto-archived
     */
    public function wasAutoArchived(): bool
    {
        return !is_null($this->auto_archive_date);
    }

    /**
     * Get formatted archive reason
     */
    public function getFormattedArchiveReasonAttribute(): string
    {
        if ($this->wasAutoArchived()) {
            return "Auto-archived: " . $this->archive_reason;
        }
        
        return $this->archive_reason;
    }

    /**
     * Static method to create archive from student
     */
    public static function createFromStudent(
        Student $student,
        string $finalStatus,
        string $reason,
        int $archivedByUserId,
        bool $isAutoArchive = false
    ): self {
        // Get attendance summary
        $attendanceSummary = self::generateAttendanceSummary($student);
        
        // Get status history
        $statusHistory = StudentStatusChange::where('student_id', $student->id)
            ->orderBy('changed_at')
            ->get()
            ->toArray();
        
        // Get notes summary
        $notesSummary = TeacherNote::where('student_id', $student->id)
            ->get(['title', 'content', 'color', 'created_at'])
            ->toArray();
        
        return self::create([
            'original_student_id' => $student->id,
            'student_data' => $student->toArray(),
            'attendance_summary' => $attendanceSummary,
            'status_history' => $statusHistory,
            'notes_summary' => $notesSummary,
            'final_status' => $finalStatus,
            'archive_reason' => $reason,
            'archived_date' => now()->toDateString(),
            'archived_by_user_id' => $archivedByUserId,
            'can_be_restored' => in_array($finalStatus, ['suspended', 'medical_leave']),
            'auto_archive_date' => $isAutoArchive ? now() : null
        ]);
    }

    /**
     * Generate attendance summary for archiving
     */
    private static function generateAttendanceSummary(Student $student): array
    {
        // Get current school year start date (assuming August 1st)
        $schoolYearStart = now()->month >= 8 
            ? now()->startOfYear()->addMonths(7) // August of current year
            : now()->subYear()->startOfYear()->addMonths(7); // August of previous year
        
        $totalDays = $schoolYearStart->diffInDays(now());
        $attendanceRecords = Attendance::where('student_id', $student->id)
            ->where('date', '>=', $schoolYearStart)
            ->get();
        
        $totalAbsences = $attendanceRecords->whereIn('status', ['absent', 'excused'])->count();
        $totalPresent = $attendanceRecords->where('status', 'present')->count();
        $totalTardies = $attendanceRecords->where('status', 'late')->count();
        
        $attendancePercentage = $totalDays > 0 ? ($totalPresent / $totalDays) * 100 : 0;
        
        return [
            'school_year_start' => $schoolYearStart->toDateString(),
            'total_school_days' => $totalDays,
            'total_present' => $totalPresent,
            'total_absences' => $totalAbsences,
            'total_tardies' => $totalTardies,
            'overall_percentage' => round($attendancePercentage, 2),
            'exceeded_18_absence_limit' => $totalAbsences >= 18,
            'summary_generated_at' => now()->toDateTimeString()
        ];
    }

    /**
     * Restore student from archive
     */
    public function restoreStudent(): ?Student
    {
        if (!$this->can_be_restored) {
            return null;
        }
        
        // Create new student record with original data
        $studentData = $this->student_data;
        $studentData['current_status'] = 'active';
        $studentData['status_changed_date'] = now()->toDateString();
        
        // Remove timestamps to let Eloquent handle them
        unset($studentData['created_at'], $studentData['updated_at']);
        
        $restoredStudent = Student::create($studentData);
        
        // Create status change record
        StudentStatusChange::create([
            'student_id' => $restoredStudent->id,
            'changed_by_user_id' => auth()->id(),
            'previous_status' => $this->final_status,
            'new_status' => 'active',
            'reason_category' => 'others',
            'reason_note' => 'Restored from archive',
            'effective_date' => now()->toDateString(),
            'is_current' => true,
            'changed_at' => now()
        ]);
        
        // Mark archive as non-restorable to prevent duplicate restorations
        $this->update(['can_be_restored' => false]);
        
        return $restoredStudent;
    }
}
