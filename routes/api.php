<?php

use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\TeacherController;
use App\Http\Controllers\API\TeacherAuthController;
use App\Http\Controllers\API\GradeController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\SubjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Default user route
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Student routes
Route::apiResource('students', StudentController::class);
Route::get('students/grade/{gradeLevel}', [StudentController::class, 'byGrade']);
Route::get('students/grade/{gradeLevel}/section/{section}', [StudentController::class, 'bySection']);

// Teacher routes
Route::apiResource('teachers', TeacherController::class);
Route::get('teachers/{teacher}/subjects', [TeacherController::class, 'subjects']);

// Attendance routes
Route::apiResource('attendance', AttendanceController::class);
Route::get('attendance/student/{student}', [AttendanceController::class, 'byStudent']);
Route::get('attendance/subject/{subject}', [AttendanceController::class, 'bySubject']);
Route::get('attendance/weekly/{subject}', [AttendanceController::class, 'weeklyReport']);

// Grade routes
Route::apiResource('grades', GradeController::class);
Route::get('grades/{grade}/sections', [GradeController::class, 'sections']);

// Subject routes
Route::apiResource('subjects', SubjectController::class);
Route::get('subjects/grade/{grade}', [SubjectController::class, 'byGrade']);

// Teacher Authentication routes
Route::prefix('teacher')->group(function () {
    Route::post('login', [TeacherAuthController::class, 'login']);
    Route::post('logout', [TeacherAuthController::class, 'logout']);
    Route::get('profile', [TeacherAuthController::class, 'profile']);
});
