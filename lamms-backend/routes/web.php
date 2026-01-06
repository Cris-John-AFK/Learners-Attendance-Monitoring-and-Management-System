<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return response()->json(['message' => 'LAMMS API is working!']);
});

Route::get('/debug-path', function() {
    return response()->json([
        'path' => request()->path(),
        'url' => request()->url(),
        'fullUrl' => request()->fullUrl(),
        'method' => request()->method(),
    ]);
});

/**
 * Direct non-API routes that should be CORS-enabled
 * These routes are needed because some frontend code is making requests directly to these URLs
 */
Route::get('/teachers', function() {
    return app()->make(App\Http\Controllers\API\TeacherController::class)->index();
});

Route::post('/teachers', function() {
    return app()->make(App\Http\Controllers\API\TeacherController::class)->store(request());
});

Route::get('/subjects', function() {
    return app()->make(App\Http\Controllers\API\SubjectController::class)->index();
});

Route::get('/grades', function() {
    return app()->make(App\Http\Controllers\API\GradeController::class)->index();
});
