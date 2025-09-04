<?php

use App\Http\Controllers\API\GradeController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\AttendanceSessionController;
use App\Http\Controllers\API\SubjectController;
use App\Http\Controllers\API\SectionController;
use App\Http\Controllers\API\TeacherController;
use App\Http\Controllers\API\StudentController;
use App\Http\Controllers\API\StudentManagementController;
use App\Http\Controllers\API\CurriculumController;
use App\Http\Controllers\API\CurriculumGradeController;
use App\Http\Controllers\API\ScheduleController;
use App\Http\Controllers\API\EnrollmentController;
use App\Http\Controllers\API\QRCodeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

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
Route::get('students/{student}/attendance/summary', [StudentController::class, 'getAttendanceSummary']);
Route::post('students/{student}/generate-qr', [StudentController::class, 'generateQRForStudent']);
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
Route::get('/sections/{section}/subjects', [SectionController::class, 'getSubjects']);
Route::get('/sections/{section}/direct-subjects', [SectionController::class, 'getDirectSubjects']);

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

// Curriculum Routes
Route::get('/curriculums', [CurriculumController::class, 'index']);
Route::post('/curriculums', [CurriculumController::class, 'store']);
Route::get('/curriculums/{id}', [CurriculumController::class, 'show']);
Route::put('/curriculums/{id}', [CurriculumController::class, 'update']);
Route::delete('/curriculums/{id}', [CurriculumController::class, 'destroy']);
Route::put('/curriculums/{id}/archive', [CurriculumController::class, 'archive']);
Route::put('/curriculums/{id}/activate', [\App\Http\Controllers\API\CurriculumController::class, 'activate']);

// Curriculum-Grade Routes
Route::get('/curriculums/{id}/grades', [CurriculumController::class, 'getGrades']);
Route::post('/curriculums/{id}/grades', [CurriculumController::class, 'addGrade']);
Route::delete('/curriculums/{id}/grades/{gradeId}', [CurriculumController::class, 'removeGrade']);

// Curriculum-Grade-Section Routes
Route::get('/curriculums/{curriculumId}/grades/{gradeId}/sections', [CurriculumController::class, 'getSections']);
Route::post('/curriculums/{curriculumId}/grades/{gradeId}/sections', [CurriculumController::class, 'addSection']);
Route::delete('/curriculums/{curriculumId}/grades/{gradeId}/sections/{sectionId}', [CurriculumController::class, 'removeSection']);

// Subject routes for sections
Route::get('/curriculums/{curriculumId}/grades/{gradeId}/sections/{sectionId}/subjects', [SectionController::class, 'getSubjects']);
Route::post('/curriculums/{curriculumId}/grades/{gradeId}/sections/{sectionId}/subjects', [SectionController::class, 'addSubjectToSection']);
Route::delete('/curriculums/{curriculumId}/grades/{gradeId}/sections/{sectionId}/subjects/{subjectId}', [SectionController::class, 'removeSubjectFromSection']);

// Teacher assignment routes
Route::post('/curriculums/{curriculumId}/grades/{gradeId}/sections/{sectionId}/teacher', [SectionController::class, 'assignHomeroomTeacher']);
Route::post('/curriculums/{curriculumId}/grades/{gradeId}/sections/{sectionId}/subjects/{subjectId}/teacher', [SectionController::class, 'assignTeacherToSubject']);
Route::get('/sections/{sectionId}/subjects/{subjectId}/teacher', [SectionController::class, 'getSubjectTeacher']);

// Schedule routes
Route::post('/curriculums/{curriculumId}/grades/{gradeId}/sections/{sectionId}/subjects/{subjectId}/schedule', [SectionController::class, 'setSubjectScheduleWithParams']);
Route::get('/sections/{sectionId}/subjects/{subjectId}/schedule', [SectionController::class, 'getSubjectSchedule']);
Route::post('/sections/{sectionId}/subjects/{subjectId}/schedule', [SectionController::class, 'setSubjectSchedule']);

// Alternative endpoints for section subjects
Route::get('/sections/{sectionId}/subjects', [SectionController::class, 'getSubjects']);
Route::post('/sections/{sectionId}/subjects', [SectionController::class, 'addSubjectToSection']);
Route::delete('/sections/{sectionId}/subjects/{subjectId}', [SectionController::class, 'removeSubjectFromSection']);
Route::post('/sections/{sectionId}/subjects/{subjectId}/teacher', [SectionController::class, 'assignTeacherToSubject']);
Route::post('/sections/{sectionId}/repair-subjects', [SectionController::class, 'repairSectionSubjectsEndpoint']);

// Curriculum Grade routes with alternative endpoints
Route::get('/curriculum-grade/{curriculum}/{grade}', [CurriculumGradeController::class, 'show']);
Route::get('/curriculum_grade', [CurriculumGradeController::class, 'getByParams']);
Route::get('/curriculums/{curriculumId}/grades/{gradeId}/relationship', [CurriculumGradeController::class, 'relationship']);
Route::get('/sections/curriculum-grade/{curriculumGrade}', [SectionController::class, 'byCurriculumGrade']);

// System-level operations
Route::prefix('system')->group(function () {
    Route::post('repair-sections', function () {
        try {
            Artisan::call('app:repair-section-grade-relationships');
            $output = Artisan::output();

            Log::info('Section repair command executed via API');
            Log::info($output);

            return response()->json([
                'success' => true,
                'message' => 'Section-grade relationships repaired successfully',
                'details' => str_replace("\n", "<br>", $output)
            ]);
        } catch (\Exception $e) {
            Log::error('Error executing section repair command: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to repair section-grade relationships',
                'error' => $e->getMessage()
            ], 500);
        }
    });
});

// Schedule routes
Route::prefix('schedules')->group(function () {
    Route::get('/section/{sectionId}', [ScheduleController::class, 'getSectionSchedules']);
    Route::post('/homeroom/create-all', [ScheduleController::class, 'createHomeroomSchedules']);
    Route::post('/subject', [ScheduleController::class, 'createSubjectSchedule']);
    Route::put('/{id}', [ScheduleController::class, 'updateSchedule']);
    Route::delete('/{id}', [ScheduleController::class, 'deleteSchedule']);
    Route::get('/teacher/{teacherId}', [ScheduleController::class, 'getTeacherSchedule']);
});

// Attendance routes
Route::get('/attendance', [AttendanceController::class, 'getAttendanceByDateAndSubject']);
Route::post('/attendance', [AttendanceController::class, 'markAttendance']);
Route::get('/attendance/statistics', [AttendanceController::class, 'getAttendanceStatistics']);

// New improved attendance routes
Route::prefix('attendance')->group(function () {
    // Get attendance statuses
    Route::get('/statuses', [AttendanceController::class, 'getAttendanceStatuses']);
    
    // Get attendance for specific section, subject and date
    Route::get('/section/{sectionId}/subject/{subjectId}', [AttendanceController::class, 'getAttendance']);
    
    // Mark attendance (bulk)
    Route::post('/mark-bulk', [AttendanceController::class, 'markAttendance']);
    
    // Mark single attendance
    Route::post('/mark-single', [AttendanceController::class, 'markSingleAttendance']);
    
    // Get attendance reports
    Route::get('/reports/section/{sectionId}', [AttendanceController::class, 'getAttendanceReport']);
});

// New Attendance Session routes for solid database system
Route::prefix('attendance-sessions')->group(function () {
    // Session management
    Route::post('/', [AttendanceSessionController::class, 'createSession']);
    Route::get('/teacher/{teacherId}/active', [AttendanceSessionController::class, 'getActiveSessionsForTeacher']);
    Route::post('/{sessionId}/complete', [AttendanceSessionController::class, 'completeSession']);
    Route::get('/{sessionId}/summary', [AttendanceSessionController::class, 'getSessionSummary']);
    
    // Attendance marking within sessions
    Route::post('/{sessionId}/attendance', [AttendanceSessionController::class, 'markSessionAttendance']);
    Route::post('/{sessionId}/qr-attendance', [AttendanceSessionController::class, 'markQRAttendance']);
    
    // Reports
    Route::get('/reports/weekly', [AttendanceSessionController::class, 'getWeeklyReport']);
    Route::get('/reports/monthly', [AttendanceSessionController::class, 'getMonthlyReport']);
});

// Teacher-specific attendance routes
Route::get('/teachers/{teacherId}/assignments', [AttendanceController::class, 'getTeacherAssignments']);
Route::get('/teachers/{teacherId}/sections/{sectionId}/subjects/{subjectId}/students', [AttendanceController::class, 'getStudentsForTeacherSubject']);
Route::post('/teachers/{teacherId}/attendance', [AttendanceController::class, 'markTeacherAttendance']);

// Student Management routes for seating arrangements and student operations
Route::prefix('student-management')->group(function () {
    // Student listing routes
    Route::get('/sections/{sectionId}/students', [StudentManagementController::class, 'getStudentsBySection']);
    Route::get('/subjects/{subjectId}/students', [StudentManagementController::class, 'getStudentsBySubject']);
    
    // Seating arrangement routes
    Route::get('/sections/{sectionId}/seating-arrangement', [StudentManagementController::class, 'getSeatingArrangement']);
    Route::post('/seating-arrangement/save', [StudentManagementController::class, 'saveSeatingArrangement']);
    Route::post('/seating-arrangement/reset', [StudentManagementController::class, 'resetSeatingArrangement']);
    
    // QR code generation
    Route::post('/generate-qr-codes', [StudentManagementController::class, 'generateStudentQRCodes']);
    
    // Student import
    Route::post('/import-students', [StudentManagementController::class, 'importStudents']);
});

// Student details routes (missing endpoints)
Route::get('/student-details', [StudentController::class, 'index']);
Route::get('/student-details/{id}', [StudentController::class, 'show']);
Route::get('/student-details/{id}/attendance', [StudentController::class, 'getAttendanceRecords']);
Route::get('/students/grade/{gradeLevel}', [StudentController::class, 'getByGrade']);
Route::get('/student-details/grade/{gradeLevel}/section/{section}', [StudentController::class, 'getByGradeAndSection']);

// Add these routes if missing
Route::get('/teachers', [TeacherController::class, 'index']);
Route::post('/teachers', [TeacherController::class, 'store']);
Route::get('/subjects', [SubjectController::class, 'index']);
Route::get('/grades', [GradeController::class, 'index']);

// Enrollment routes
Route::apiResource('enrollments', EnrollmentController::class);
Route::get('enrollments/stats', [EnrollmentController::class, 'getStats']);
Route::post('enrollments/{id}/assign-section', [EnrollmentController::class, 'assignSection']);
Route::get('enrollments/{id}/available-sections', [EnrollmentController::class, 'getAvailableSections']);
Route::get('enrollments/{id}', [EnrollmentController::class, 'show']);
Route::put('enrollments/{id}', [EnrollmentController::class, 'update']);
Route::delete('enrollments/{id}', [EnrollmentController::class, 'destroy']);
Route::get('enrollments/grade/{gradeLevel}', [EnrollmentController::class, 'byGrade']);

// QR Code routes
Route::post('/qr-codes/generate/{studentId}', [QRCodeController::class, 'generateQRCode']);
Route::get('/qr-codes/image/{studentId}', [QRCodeController::class, 'getQRCodeImage']);
Route::post('/qr-codes/validate', [QRCodeController::class, 'validateQRCode']);
Route::get('/qr-codes', [QRCodeController::class, 'getAllQRCodes']);
Route::get('/qr-codes/student/{studentId}', [QRCodeController::class, 'getStudentQRCode']);

// Student Management and Seating Arrangement routes
Route::prefix('student-management')->group(function () {
    Route::get('/sections/{sectionId}/students', [\App\Http\Controllers\API\StudentManagementController::class, 'getStudentsBySection']);
    Route::post('/sections/{sectionId}/generate-qr-bulk', [\App\Http\Controllers\API\StudentManagementController::class, 'generateQRCodesBulk']);
    Route::get('/sections/{sectionId}/seating-arrangement', [\App\Http\Controllers\API\StudentManagementController::class, 'getSeatingArrangement']);
    Route::post('/seating-arrangement/save', [\App\Http\Controllers\API\StudentManagementController::class, 'saveSeatingArrangement']);
    Route::post('/students/import', [\App\Http\Controllers\API\StudentManagementController::class, 'importStudents']);
});
