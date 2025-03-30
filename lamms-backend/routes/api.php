<?php

use App\Http\Controllers\API\GradeController;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\SubjectController;
use App\Http\Controllers\API\SectionController;
use App\Http\Controllers\API\TeacherController;
use Illuminate\Support\Facades\Route;

// Health check route for testing connectivity
Route::get('/health-check', function() {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is working!',
        'timestamp' => now()->toDateTimeString()
    ]);
});

// Student routes
Route::apiResource('students', StudentController::class);
Route::get('students/grade/{gradeLevel}', [StudentController::class, 'byGrade']);
Route::get('students/grade/{gradeLevel}/section/{section}', [StudentController::class, 'bySection']);
Route::get('subjects/homeroom/assignments', [SubjectController::class, 'getHomeroomAssignments']);
// Subject routes
Route::apiResource('subjects', SubjectController::class);
Route::get('subjects/unique', [SubjectController::class, 'uniqueSubjects']);
Route::patch('subjects/{subject}/toggle-status', [SubjectController::class, 'toggleStatus']);

// Grade routes
Route::apiResource('grades', GradeController::class);
Route::get('grades/active', [GradeController::class, 'getActiveGrades']);
Route::patch('grades/{id}/toggle-status', [GradeController::class, 'toggleStatus']);

// Section routes
Route::apiResource('sections', SectionController::class);
Route::get('sections/grade/{gradeId}', [SectionController::class, 'byGrade']);
Route::get('sections/active', [SectionController::class, 'getActiveSections']);
Route::patch('sections/{section}/toggle-status', [SectionController::class, 'toggleStatus']);
Route::post('sections/{id}/restore', [SectionController::class, 'restore']);

// Teacher routes
Route::prefix('teachers')->group(function () {
    Route::get('/', [TeacherController::class, 'index']);
    Route::get('/active', [TeacherController::class, 'getActiveTeachers']);
    Route::get('/section/{sectionId}', [TeacherController::class, 'getTeachersBySection']);
    Route::post('/', [TeacherController::class, 'store']);
    Route::get('/{teacher}', [TeacherController::class, 'show']);
    Route::put('/{teacher}', [TeacherController::class, 'update']);
    Route::delete('/{teacher}', [TeacherController::class, 'destroy']);
    Route::patch('/{teacher}/toggle-status', [TeacherController::class, 'toggleStatus']);
    Route::put('/{teacher}/assignments', [TeacherController::class, 'updateAssignments']);
    Route::patch('/{teacher}/force-password-reset', [TeacherController::class, 'forcePasswordReset']);
    Route::post('/{id}/restore', [TeacherController::class, 'restore']);
});

// Test route
Route::get('/test', function() {
    return response()->json([
        'message' => 'Laravel API is working!',
        'database' => config('database.connections.pgsql.database')
    ]);
});

// Test section route
Route::get('/test-section', function() {
    return response()->json([
        'message' => 'Section controller test',
        'controller_exists' => class_exists('App\Http\Controllers\API\SectionController'),
        'section_model_exists' => class_exists('App\Models\Section')
    ]);
});
