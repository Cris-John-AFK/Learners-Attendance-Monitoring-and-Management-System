<?php

use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\SubjectController;
use Illuminate\Support\Facades\Route;

// Student routes
Route::apiResource('students', StudentController::class);
Route::get('students/grade/{gradeLevel}', [StudentController::class, 'byGrade']);
Route::get('students/grade/{gradeLevel}/section/{section}', [StudentController::class, 'bySection']);

// Subject routes
Route::apiResource('subjects', SubjectController::class);
Route::get('subjects/grade/{grade}', [SubjectController::class, 'byGrade']);
Route::get('subjects/unique', [SubjectController::class, 'uniqueSubjects']);

// Test route
Route::get('/test', function() {
    return response()->json([
        'message' => 'Laravel API is working!',
        'database' => config('database.connections.pgsql.database')
    ]);
});
