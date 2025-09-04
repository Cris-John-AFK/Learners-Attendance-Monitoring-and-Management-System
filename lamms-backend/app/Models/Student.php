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
}
