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
        'religion',
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
        'isActive',
        // Enrollment form fields
        'student_type',
        'school_year',
        'enrollment_id',
        'house_no',
        'street',
        'barangay',
        'city_municipality',
        'province',
        'country',
        'zip_code',
        'father_name',
        'father_occupation',
        'father_contact',
        'father_education',
        'mother_name',
        'mother_occupation',
        'mother_contact',
        'mother_education',
        'guardian_name',
        'guardian_occupation',
        'guardian_contact',
        'guardian_address',
        'last_grade_completed',
        'last_school_year',
        'last_school_attended',
        'last_school_address',
        'household_income',
        'enrollment_status'
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

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }
}
