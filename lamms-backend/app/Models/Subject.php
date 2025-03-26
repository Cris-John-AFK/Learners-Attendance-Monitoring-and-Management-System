<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'grade',
        'description',
        'credits'
    ];

    // Primary key is string
    protected $keyType = 'string';
    public $incrementing = false;
}