<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

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
        'disabilities'
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
        'hasDisability' => 'boolean'
    ];

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }
}
