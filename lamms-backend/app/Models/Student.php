<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'firstname',
        'lastname', 
        'middlename',
        'extensionname',
        'email',
        'gradelevel',
        'section',
        'studentid',
        'student_id',
        'lrn',
        'gender',
        'sex',
        'birthdate',
        'birthplace',
        'age',
        'psabirthcertno',
        'mothertongue',
        'profilephoto',
        'currentaddress',
        'permanentaddress',
        'contactinfo',
        'father',
        'mother',
        'parentname',
        'parentcontact',
        'status',
        'enrollmentdate',
        'admissiondate',
        'requirements',
        'isindigenous',
        'indigenouscommunity',
        'is4psbeneficiary',
        'householdid',
        'hasdisability',
        'disabilities'
    ];

    protected $casts = [
        'currentaddress' => 'array',
        'permanentaddress' => 'array',
        'father' => 'array',
        'mother' => 'array',
        'requirements' => 'array',
        'disabilities' => 'array',
        'birthdate' => 'date',
        'enrollmentdate' => 'datetime',
        'admissiondate' => 'datetime',
        'isindigenous' => 'boolean',
        'is4psbeneficiary' => 'boolean',
        'hasdisability' => 'boolean'
    ];

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }
}
