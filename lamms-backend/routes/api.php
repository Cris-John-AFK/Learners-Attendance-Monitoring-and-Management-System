<?php

use App\Http\Controllers\API\GradeController;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\SubjectController;
use Illuminate\Support\Facades\Route;

// Student routes
Route::apiResource('students', StudentController::class);
Route::get('students/grade/{gradeLevel}', [StudentController::class, 'byGrade']);
Route::get('students/grade/{gradeLevel}/section/{section}', [StudentController::class, 'bySection']);

// Subject routes
Route::apiResource('subjects', SubjectController::class);
Route::get('subjects/grade/{gradeId}', [SubjectController::class, 'byGrade']);
Route::get('subjects/unique', [SubjectController::class, 'uniqueSubjects']);
Route::get('subjects/available-grades', [SubjectController::class, 'availableGrades']);
Route::patch('subjects/{subject}/toggle-status', [SubjectController::class, 'toggleStatus']);

// Grade routes
Route::apiResource('grades', GradeController::class);
Route::get('grades/active', [GradeController::class, 'getActiveGrades']);
Route::patch('grades/{id}/toggle-status', [GradeController::class, 'toggleStatus']);

// Test route
Route::get('/test', function() {
    return response()->json([
        'message' => 'Laravel API is working!',
        'database' => config('database.connections.pgsql.database')
    ]);
});
