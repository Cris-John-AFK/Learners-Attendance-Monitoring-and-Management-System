<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $table = 'student_details';

    protected $fillable = [
        'name',
        'firstName',
        'lastName',
        'middleName',
        'extensionName',
        'email',
        'gradeLevel',
        'section',
        'studentId',
        'student_id',
        'lrn',
        'gender',
        'sex',
        'birthdate',
        'birthplace',
        'age',
        'psaBirthCertNo',
        'motherTongue',
        'profilePhoto',
        'photo',
        'qr_code_path',
        'address',
        'currentAddress',
        'permanentAddress',
        'contactInfo',
        'father',
        'mother',
        'parentName',
        'parentContact',
        'status',
        'enrollmentDate',
        'admissionDate',
        'requirements',
        'isIndigenous',
        'indigenousCommunity',
        'is4PsBeneficiary',
        'householdID',
        'hasDisability',
        'disabilities',
        'isActive'
    ];

    protected $casts = [
        'currentAddress' => 'array',
        'permanentAddress' => 'array',
        'father' => 'array',
        'mother' => 'array',
        'requirements' => 'array',
        'disabilities' => 'array',
        'birthdate' => 'date',
        'enrollmentDate' => 'datetime',
        'admissionDate' => 'datetime',
        'isIndigenous' => 'boolean',
        'is4PsBeneficiary' => 'boolean',
        'hasDisability' => 'boolean',
        'isActive' => 'boolean'
    ];

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class, 'student_section')
                    ->withPivot('school_year', 'is_active')
                    ->withTimestamps();
    }

    public function currentSection()
    {
        return $this->belongsToMany(Section::class, 'student_section')
                    ->withPivot('school_year', 'is_active')
                    ->wherePivot('is_active', true)
                    ->withTimestamps()
                    ->latest('student_section.created_at');
    }

    public function getSectionForYear($schoolYear = '2025-2026')
    {
        return $this->sections()
                    ->wherePivot('school_year', $schoolYear)
                    ->wherePivot('is_active', true)
                    ->first();
    }

    // New attendance-related relationships
    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function enrollmentHistory()
    {
        return $this->hasMany(StudentEnrollmentHistory::class);
    }

    public function qrCode()
    {
        return $this->hasOne(StudentQRCode::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('isActive', true);
    }

    public function scopeForGrade($query, $gradeLevel)
    {
        return $query->where('gradeLevel', $gradeLevel);
    }

    public function scopeInSection($query, $sectionId)
    {
        return $query->whereHas('sections', function($q) use ($sectionId) {
            $q->where('sections.id', $sectionId)->where('student_section.is_active', true);
        });
    }

    // Helper methods for attendance
    public function getAttendanceForDate($date, $sessionId = null)
    {
        $query = $this->attendanceRecords()
            ->whereHas('attendanceSession', function($q) use ($date) {
                $q->where('session_date', $date);
            });

        if ($sessionId) {
            $query->where('attendance_session_id', $sessionId);
        }

        return $query->first();
    }

    public function getAttendanceForDateRange($startDate, $endDate)
    {
        return $this->attendanceRecords()
            ->whereHas('attendanceSession', function($q) use ($startDate, $endDate) {
                $q->whereBetween('session_date', [$startDate, $endDate]);
            })
            ->with(['attendanceSession', 'attendanceStatus'])
            ->get();
    }

    public function getAttendanceStats($startDate, $endDate)
    {
        $records = $this->getAttendanceForDateRange($startDate, $endDate);
        
        return [
            'total_days' => $records->count(),
            'present' => $records->where('attendanceStatus.code', 'P')->count(),
            'absent' => $records->where('attendanceStatus.code', 'A')->count(),
            'late' => $records->where('attendanceStatus.code', 'L')->count(),
            'excused' => $records->where('attendanceStatus.code', 'E')->count(),
            'attendance_rate' => $records->count() > 0 ? 
                ($records->whereIn('attendanceStatus.code', ['P', 'L'])->count() / $records->count()) * 100 : 0
        ];
    }

    public function getCurrentEnrollment()
    {
        return $this->enrollmentHistory()
            ->active()
            ->forSchoolYear(now()->year . '-' . (now()->year + 1))
            ->first();
    }
}
