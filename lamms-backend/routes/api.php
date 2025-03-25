<?php

use App\Http\Controllers\API\StudentController;
use Illuminate\Support\Facades\Route;

// Student routes
Route::apiResource('students', StudentController::class);
Route::get('students/grade/{gradeLevel}', [StudentController::class, 'byGrade']);
Route::get('students/grade/{gradeLevel}/section/{section}', [StudentController::class, 'bySection']);
