<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\TeacherSectionSubject;
use App\Models\Student;

class Section extends Model
{
    use HasFactory, SoftDeletes;

    // Enable timestamps for proper Laravel functionality
    public $timestamps = true;

    protected $fillable = [
        'name',
        'curriculum_grade_id',
        'homeroom_teacher_id',
        'description',
        'capacity',
        'is_active'
    ];

    // Removed automatic eager loading to prevent performance issues
    // protected $with = ['curriculumGrade', 'homeroomTeacher'];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer'
    ];

    // Automatically include filtered students in JSON response
    protected $appends = ['students'];

    /**
     * Determine if grade_id column exists in the table
     */
    protected static $hasGradeIdColumn = null;

    /**
     * Check if the grade_id column exists
     */
    public static function hasGradeIdColumn()
    {
        if (static::$hasGradeIdColumn === null) {
            static::$hasGradeIdColumn = Schema::hasColumn('sections', 'grade_id');
        }
        return static::$hasGradeIdColumn;
    }

    public function curriculumGrade()
    {
        return $this->belongsTo(CurriculumGrade::class);
    }

    public function homeroomTeacher()
    {
        return $this->belongsTo(Teacher::class, 'homeroom_teacher_id');
    }

    // Alias for easier access in SF2 reports
    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'homeroom_teacher_id');
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'teacher_section_subject')
            ->withPivot('subject_id', 'is_primary', 'is_active');
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_section_subject')
            ->withPivot('teacher_id', 'is_primary', 'is_active');
    }

    public function teacherAssignments()
    {
        return $this->hasMany(TeacherSectionSubject::class);
    }

    public function schedules()
    {
        return $this->hasMany(SubjectSchedule::class);
    }

    /**
     * Scope a query to only include active sections.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if section name is unique within the curriculum grade
     */
    public static function isNameUniqueInCurriculumGrade($name, $curriculumGradeId, $excludeSectionId = null)
    {
        $query = static::where('name', $name)
            ->where('curriculum_grade_id', $curriculumGradeId);

        if ($excludeSectionId) {
            $query->where('id', '!=', $excludeSectionId);
        }

        return !$query->exists();
    }

    /**
     * Check if section name is unique within the grade
     */
    public static function isNameUniqueInGrade($name, $gradeId, $excludeSectionId = null)
    {
        if (self::hasGradeIdColumn()) {
            // Use direct grade_id if it exists
            $query = static::where('name', $name)
                ->where('grade_id', $gradeId);
        } else {
            // Use curriculum_grade if grade_id doesn't exist
            $curriculumGradeIds = DB::table('curriculum_grade')
                ->where('grade_id', $gradeId)
                ->pluck('id');

            $query = static::where('name', $name)
                ->whereIn('curriculum_grade_id', $curriculumGradeIds);
        }

        if ($excludeSectionId) {
            $query->where('id', '!=', $excludeSectionId);
        }

        return !$query->exists();
    }

    /**
     * Get the grade level through the curriculum_grade relationship
     */
    public function grade()
    {
        // First try direct relationship if grade_id column exists
        if (self::hasGradeIdColumn() && $this->grade_id) {
            return $this->belongsTo(Grade::class, 'grade_id');
        }

        // Otherwise try through curriculum_grade
        return $this->hasOneThrough(
            Grade::class,
            CurriculumGrade::class,
            'id', // Foreign key on curriculum_grade table
            'id', // Foreign key on grades table
            'curriculum_grade_id', // Local key on sections table
            'grade_id' // Local key on curriculum_grade table
        );
    }

    /**
     * Direct relationship with subjects through the section_subject pivot table
     */
    public function directSubjects()
    {
        return $this->belongsToMany(Subject::class, 'section_subject');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_section')
                    ->withPivot('school_year', 'is_active')
                    ->withTimestamps();
    }

    public function activeStudents()
    {
        return $this->belongsToMany(Student::class, 'student_section')
                    ->withPivot('school_year', 'is_active')
                    ->wherePivot('is_active', true)
                    // CRITICAL: Filter out dropped out students
                    ->where(function($query) {
                        $query->whereIn('student_details.status', ['Enrolled', 'enrolled', 'active'])
                              ->orWhereNull('student_details.status');
                    })
                    ->whereNotIn('student_details.status', ['Dropped Out', 'dropped_out', 'Transferred Out', 'transferred_out', 'Withdrawn', 'withdrawn', 'Deceased', 'deceased'])
                    ->withTimestamps();
    }

    /**
     * Custom accessor to return filtered students (excludes dropped out students)
     */
    public function getStudentsAttribute()
    {
        // Get active students with proper filtering
        $students = DB::table('student_details as sd')
            ->join('student_section as ss', function($join) {
                $join->on('sd.id', '=', 'ss.student_id')
                     ->where('ss.section_id', '=', $this->id)
                     ->where('ss.is_active', '=', 1);
            })
            ->where(function($query) {
                $query->whereIn('sd.enrollment_status', ['active', 'enrolled', 'transferred_in'])
                      ->orWhereNull('sd.enrollment_status');
            })
            ->whereNotIn('sd.enrollment_status', ['dropped_out', 'transferred_out', 'withdrawn', 'deceased'])
            ->select([
                'sd.id',
                'sd.firstName as first_name',
                'sd.lastName as last_name', 
                'sd.middleName as middle_name',
                'sd.lrn',
                'sd.qr_code_path as qr_code',
                'sd.gender',
                'sd.age',
                'sd.status'
            ])
            ->orderBy('sd.lastName')
            ->orderBy('sd.firstName')
            ->get();

        return $students;
    }

    /**
     * Make sure we can always get the grade even if the relationship isn't perfect
     */
    public function getGradeAttribute()
    {
        // First try eager loaded grade
        if ($this->relationLoaded('grade') && $this->getRelation('grade')) {
            return $this->getRelation('grade');
        }

        // Then try direct grade_id if column exists
        if (self::hasGradeIdColumn() && $this->grade_id) {
            $grade = Grade::find($this->grade_id);
            if ($grade) {
                $this->setRelation('grade', $grade);
                return $grade;
            }
        }

        // Then try curriculum_grade_id
        if ($this->curriculum_grade_id) {
            $curriculumGrade = CurriculumGrade::find($this->curriculum_grade_id);
            if ($curriculumGrade) {
                $grade = Grade::find($curriculumGrade->grade_id);
                if ($grade) {
                    $this->setRelation('grade', $grade);
                    return $grade;
                }
            }
        }

        return null;
    }
}
