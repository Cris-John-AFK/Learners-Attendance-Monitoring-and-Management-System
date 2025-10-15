<?php

use App\Http\Controllers\API\GradeController;
use App\Http\Controllers\API\AttendanceController;
use App\Http\Controllers\API\AttendanceSessionController;
use App\Http\Controllers\API\AttendanceReasonController;
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
use App\Http\Controllers\API\TeacherAuthController;
use App\Http\Controllers\API\AttendanceAnalyticsController;
use App\Http\Controllers\API\GuardhouseController;
use App\Http\Controllers\API\StudentStatusController;
use App\Http\Controllers\API\UnifiedAuthController;
use App\Http\Controllers\TeacherAssignmentValidationController;
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

// ========================================
// UNIFIED AUTHENTICATION ROUTES
// ========================================
Route::prefix('auth')->group(function () {
    // Public routes (no authentication required)
    Route::post('/login', [UnifiedAuthController::class, 'login']);

    // Protected routes (require authentication)
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureSingleSession::class])->group(function () {
        Route::post('/logout', [UnifiedAuthController::class, 'logout']);
        Route::get('/me', [UnifiedAuthController::class, 'me']);
        Route::get('/check-session', [UnifiedAuthController::class, 'checkSession']);
    });
});

// Simple SF2 Submission Routes (Fix for submit button)
Route::post('/sf2/submit', [App\Http\Controllers\API\SimpleSF2Controller::class, 'submitToAdmin']);
Route::get('/sf2/submitted', [App\Http\Controllers\API\SimpleSF2Controller::class, 'getSubmittedReports']);

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
Route::get('/sections/{sectionId}/students', [AttendanceSessionController::class, 'getStudentsForTeacherSubject']);

// Teacher routes
Route::prefix('teachers')->group(function () {
    Route::post('/login', [TeacherAuthController::class, 'login']);
    Route::get('/', [TeacherController::class, 'index']);
    Route::get('/active', [TeacherController::class, 'getActiveTeachers']);
    Route::get('/section/{sectionId}', [TeacherController::class, 'getTeachersBySection']);
    Route::get('/{teacher}', [TeacherController::class, 'show']);
    Route::post('/', [TeacherController::class, 'store']);
    Route::put('/{teacher}', [TeacherController::class, 'update']);
    Route::delete('/{teacher}', [TeacherController::class, 'destroy']);
    Route::post('/{teacher}/restore', [TeacherController::class, 'restore']);
    Route::post('/{teacher}/assignments', [TeacherController::class, 'updateAssignments']);
    Route::post('/{teacher}/sections', [TeacherController::class, 'assignSection']);
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
Route::get('/curriculum-grades', [CurriculumGradeController::class, 'index']);
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

    // Attendance reasons routes
    Route::get('/reasons', [AttendanceReasonController::class, 'index']);
    Route::get('/reasons/{type}', [AttendanceReasonController::class, 'getByType']);

    // Get attendance for specific section, subject and date
    Route::get('/section/{sectionId}/subject/{subjectId}', [AttendanceController::class, 'getAttendance']);

    // Mark attendance (bulk)
    Route::post('/mark-bulk', [AttendanceController::class, 'markAttendance']);

    // Mark single attendance
    Route::post('/mark-single', [AttendanceController::class, 'markSingleAttendance']);

    // Get attendance reports
    Route::get('/reports/section/{sectionId}', [AttendanceController::class, 'getAttendanceReport']);

    // Get attendance records for students (for calendar display)
    Route::get('/records', [AttendanceController::class, 'getAttendanceRecords']);

    // Dashboard routes for teacher analytics
    Route::get('/trends', [AttendanceController::class, 'getAttendanceTrends']);
    Route::get('/summary', [AttendanceController::class, 'getAttendanceSummary']);
});

// New Attendance Session routes for solid database system
Route::prefix('attendance-sessions')->group(function () {
    // Session management
    Route::post('/', [AttendanceSessionController::class, 'createSession']);
    Route::get('/teacher/{teacherId}/active', [AttendanceSessionController::class, 'getActiveSessionsForTeacher']);
    Route::post('/{sessionId}/complete', [AttendanceSessionController::class, 'completeSession']);
    Route::get('/{sessionId}/summary', [AttendanceSessionController::class, 'getSessionSummary']);
    Route::get('/{sessionId}/details', [AttendanceSessionController::class, 'getSessionAttendanceDetails']);

    // Attendance marking within sessions
    Route::post('/{sessionId}/attendance', [AttendanceSessionController::class, 'markSessionAttendance']);
    Route::post('/{sessionId}/qr-attendance', [AttendanceSessionController::class, 'markQRAttendance']);
    Route::put('/{sessionId}/students/{studentId}', [AttendanceSessionController::class, 'updateStudentAttendance']);
    Route::post('/{sessionId}/auto-mark-absent', [AttendanceSessionController::class, 'autoMarkAbsent']);

    // Reports
    Route::get('/reports/weekly', [AttendanceSessionController::class, 'getWeeklyReport']);
    Route::get('/reports/monthly', [AttendanceSessionController::class, 'getMonthlyReport']);

    // Session editing and history (for maximum reliability)
    Route::put('/{sessionId}/edit', [AttendanceSessionController::class, 'editSession']);
    Route::get('/{sessionId}/history', [AttendanceSessionController::class, 'getSessionHistory']);
});

// Teacher-specific attendance routes
Route::prefix('teachers/{teacherId}')->group(function () {
    Route::get('/assignments', [App\Http\Controllers\AttendanceSessionController::class, 'getTeacherAssignments']);
    Route::get('/', [App\Http\Controllers\AttendanceSessionController::class, 'getTeacherData']);
    Route::get('/attendance-sessions', [AttendanceSessionController::class, 'getTeacherAttendanceSessions']);
});

// Attendance summary routes
Route::get('/attendance/summary', [App\Http\Controllers\API\AttendanceSummaryController::class, 'getTeacherAttendanceSummary']);
Route::get('/attendance/trends', [App\Http\Controllers\API\AttendanceSummaryController::class, 'getTeacherAttendanceTrends']);
Route::get('/attendance/records', [App\Http\Controllers\API\AttendanceSummaryController::class, 'getStudentAttendanceRecords']);

// Admin attendance analytics routes
Route::get('/admin/attendance/analytics', [App\Http\Controllers\API\AdminAttendanceAnalyticsController::class, 'getAttendanceAnalytics']);
Route::get('/admin/attendance/trends', [App\Http\Controllers\API\AdminAttendanceAnalyticsController::class, 'getAttendanceTrends']);

// Test routes
Route::get('/test', [App\Http\Controllers\API\TestController::class, 'test']);
Route::get('/test/db', [App\Http\Controllers\API\TestController::class, 'testDb']);
Route::get('/test/attendance', [App\Http\Controllers\API\TestController::class, 'testAttendance']);

// Enhanced attendance session routes
Route::post('/attendance-sessions', [AttendanceSessionController::class, 'createSession']);
Route::post('/attendance-sessions/mark', [AttendanceSessionController::class, 'markAttendance']);
Route::post('/attendance-sessions/{sessionId}/complete', [AttendanceSessionController::class, 'completeSession']);
Route::post('/attendance-sessions/{sessionId}/cleanup', [App\Http\Controllers\API\AttendanceSessionController::class, 'cleanupExistingSession']);
Route::post('/attendance-sessions/cleanup-all', [App\Http\Controllers\API\AttendanceSessionController::class, 'cleanupAllSessions']);

// Students endpoint for attendance sessions
Route::get('/attendance-sessions/students', [App\Http\Controllers\AttendanceSessionController::class, 'getStudentsForTeacherSubject']);

// Subject Schedule Management routes
Route::prefix('subject-schedules')->group(function () {
    Route::get('/time-slots', [App\Http\Controllers\API\SubjectScheduleController::class, 'getTimeSlots']);
    Route::get('/all', [App\Http\Controllers\API\SubjectScheduleController::class, 'getAllSchedules']);
    Route::get('/teacher/{teacherId}', [App\Http\Controllers\API\SubjectScheduleController::class, 'getTeacherSchedules']);
    Route::get('/available-slots', [App\Http\Controllers\API\SubjectScheduleController::class, 'getAvailableTimeSlots']);
    Route::post('/check-conflict', [App\Http\Controllers\API\SubjectScheduleController::class, 'checkTimeConflict']);
    Route::post('/save', [App\Http\Controllers\API\SubjectScheduleController::class, 'saveSchedule']);
    Route::post('/teacher/{teacherId}/remove-duplicates', [App\Http\Controllers\API\SubjectScheduleController::class, 'removeDuplicates']);
    Route::delete('/{id}', [App\Http\Controllers\API\SubjectScheduleController::class, 'deleteSchedule']);
});

// Schedule Notification routes
Route::prefix('schedule-notifications')->group(function () {
    Route::get('/teacher/{teacherId}/upcoming', [App\Http\Controllers\API\ScheduleNotificationController::class, 'getUpcomingSchedules']);
    Route::get('/teacher/{teacherId}/current-status', [App\Http\Controllers\API\ScheduleNotificationController::class, 'getCurrentScheduleStatus']);
    Route::get('/schedule/{scheduleId}/active-session', [App\Http\Controllers\API\ScheduleNotificationController::class, 'getActiveSession']);
    Route::post('/validate-timing', [App\Http\Controllers\API\ScheduleNotificationController::class, 'validateSessionTiming']);
    Route::get('/auto-absence/needed', [App\Http\Controllers\API\ScheduleNotificationController::class, 'getSchedulesNeedingAutoAbsence']);
    Route::post('/auto-absence/process', [App\Http\Controllers\API\ScheduleNotificationController::class, 'processAutoAbsence']);
    Route::post('/auto-absence/session/{sessionId}', [App\Http\Controllers\API\ScheduleNotificationController::class, 'markAutoAbsence']);
    Route::post('/auto-create-session', [App\Http\Controllers\API\ScheduleNotificationController::class, 'autoCreateSession']);
});

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
Route::post('/qr-codes/disable-inactive', [QRCodeController::class, 'disableQRCodesForInactiveStudents']);
Route::post('/qr-codes/bulk', [QRCodeController::class, 'getBulkQRCodes']); // 🚀 BULK endpoint

// Student Management and Seating Arrangement routes
Route::prefix('student-management')->group(function () {
    Route::get('/sections/{sectionId}/students', [\App\Http\Controllers\API\StudentManagementController::class, 'getStudentsBySection']);
    Route::post('/sections/{sectionId}/generate-qr-bulk', [\App\Http\Controllers\API\StudentManagementController::class, 'generateQRCodesBulk']);
    Route::get('/sections/{sectionId}/seating-arrangement', [\App\Http\Controllers\API\StudentManagementController::class, 'getSeatingArrangement']);
    Route::post('/seating-arrangement/save', [\App\Http\Controllers\API\StudentManagementController::class, 'saveSeatingArrangement']);
});

// Teacher Authentication routes
Route::prefix('teacher')->group(function () {
    Route::post('/login', [TeacherAuthController::class, 'login']);
    Route::post('/logout', [TeacherAuthController::class, 'logout']);
    Route::get('/profile', [TeacherAuthController::class, 'profile']);
});

// Geographic Attendance Analytics routes
Route::prefix('geographic-attendance')->group(function () {
    Route::get('/heatmap-data', [\App\Http\Controllers\API\GeographicAttendanceController::class, 'getGeographicAttendanceData']);
    Route::get('/area-summary', [\App\Http\Controllers\API\GeographicAttendanceController::class, 'getAttendanceSummaryByArea']);
});

// Attendance Records routes (simplified direct database access)
Route::prefix('attendance-records')->group(function () {
    Route::get('/section/{sectionId}', [\App\Http\Controllers\API\AttendanceRecordsController::class, 'getAttendanceRecords']);
    Route::get('/students/{sectionId}', [\App\Http\Controllers\API\AttendanceRecordsController::class, 'getStudentsBySection']);
    Route::post('/students/import', [\App\Http\Controllers\API\StudentManagementController::class, 'importStudents']);
});

// SF2 Report routes
Route::get('admin/reports/sf2/download/{sectionId}', [\App\Http\Controllers\API\SF2ReportController::class, 'download'])->name('sf2.download');
Route::get('admin/reports/sf2/download/{sectionId}/{month}', [\App\Http\Controllers\API\SF2ReportController::class, 'downloadByMonth'])->name('sf2.download.month');

// Teacher SF2 Report routes
Route::get('teacher/reports/sf2/data/{sectionId}', [\App\Http\Controllers\API\SF2ReportController::class, 'getReportData'])->name('teacher.sf2.data');
Route::get('teacher/reports/sf2/data/{sectionId}/{month}', [\App\Http\Controllers\API\SF2ReportController::class, 'getReportData'])->name('teacher.sf2.data.month');
Route::get('teacher/reports/sf2/download/{sectionId}', [\App\Http\Controllers\API\SF2ReportController::class, 'download'])->name('teacher.sf2.download');
Route::get('teacher/reports/sf2/download/{sectionId}/{month}', [\App\Http\Controllers\API\SF2ReportController::class, 'downloadByMonth'])->name('teacher.sf2.download.month');
Route::post('teacher/reports/sf2/submit/{sectionId}/{month}', [\App\Http\Controllers\API\SF2ReportController::class, 'submitToAdmin'])->name('teacher.sf2.submit');
Route::post('teacher/reports/sf2/save-edit', [\App\Http\Controllers\API\SF2ReportController::class, 'saveAttendanceEdit'])->name('teacher.sf2.save.edit');

// Admin routes for managing submitted SF2 reports
Route::get('admin/reports/submitted', [\App\Http\Controllers\API\SF2ReportController::class, 'getSubmittedReports'])->name('admin.sf2.submitted');
Route::get('admin/reports/sf2/submitted/{sectionId}/{month}', [\App\Http\Controllers\API\SF2ReportController::class, 'getSubmittedReportData'])->name('admin.sf2.submitted.data');
Route::put('admin/reports/submitted/{reportId}/status', [\App\Http\Controllers\API\SF2ReportController::class, 'updateReportStatus'])->name('admin.sf2.update.status');

// Section student count route
Route::get('sections/{sectionId}/students/count', [\App\Http\Controllers\API\SectionController::class, 'getStudentCount'])->name('sections.students.count');

// Attendance Analytics routes for Admin Dashboard
Route::prefix('admin/attendance-analytics')->group(function () {
    Route::get('/overview', [AttendanceAnalyticsController::class, 'getOverview']);
    Route::get('/grade/{gradeId}', [AttendanceAnalyticsController::class, 'getGradeDetails']);
    Route::get('/section/{sectionId}', [AttendanceAnalyticsController::class, 'getSectionDetails']);
});

// Guardhouse routes for attendance tracking
Route::prefix('guardhouse')->group(function () {
    Route::get('/test', [GuardhouseController::class, 'test']);
    Route::post('/verify-qr', [GuardhouseController::class, 'verifyQRCode']);
    Route::post('/record-attendance', [GuardhouseController::class, 'recordAttendance']);
    Route::get('/today-records', [GuardhouseController::class, 'getTodayRecords']);
    Route::post('/manual-record', [GuardhouseController::class, 'manualRecord']);

    // Admin-only routes for historical data
    Route::get('/historical-records', [GuardhouseController::class, 'getHistoricalRecords']);
    Route::get('/attendance-stats', [GuardhouseController::class, 'getAttendanceStats']);

    // Scanner control routes (Admin functionality)
    Route::post('/toggle-scanner', [GuardhouseController::class, 'toggleScanner']);
    Route::get('/scanner-status', [GuardhouseController::class, 'getScannerStatus']);

    // New routes for GuardHouse Reports Admin Page
    Route::get('/live-feed', [App\Http\Controllers\API\GuardhouseReportsController::class, 'getLiveFeed']);
    Route::post('/archive-session', [App\Http\Controllers\API\GuardhouseReportsController::class, 'archiveSession']);
    Route::get('/archived-sessions', [App\Http\Controllers\API\GuardhouseReportsController::class, 'getArchivedSessions']);
    Route::get('/session-records/{sessionId}', [App\Http\Controllers\API\GuardhouseReportsController::class, 'getSessionRecords']);
});

// School Calendar routes
Route::prefix('calendar')->group(function () {
    Route::get('/events', [App\Http\Controllers\API\SchoolCalendarController::class, 'index']);
    Route::post('/events', [App\Http\Controllers\API\SchoolCalendarController::class, 'store']);
    Route::put('/events/{id}', [App\Http\Controllers\API\SchoolCalendarController::class, 'update']);
    Route::delete('/events/{id}', [App\Http\Controllers\API\SchoolCalendarController::class, 'destroy']);
});

// Smart Attendance Analytics Routes
Route::prefix('analytics')->group(function () {
    // Student Analytics
    Route::get('/student/{studentId}', [App\Http\Controllers\API\SmartAttendanceAnalyticsController::class, 'getStudentAnalytics']);
    Route::get('/student/{studentId}/weekly', [App\Http\Controllers\API\SmartAttendanceAnalyticsController::class, 'getStudentWeeklyAttendance']);
    Route::post('/student/{studentId}/refresh', [App\Http\Controllers\API\SmartAttendanceAnalyticsController::class, 'refreshStudentAnalytics']);
    Route::get('/student/{studentId}/patterns', [App\Http\Controllers\API\SmartAttendanceAnalyticsController::class, 'getAttendancePatterns']);

    // Teacher Analytics
    Route::get('/teacher/students', [App\Http\Controllers\API\SmartAttendanceAnalyticsController::class, 'getTeacherStudentAnalytics']);

    // Critical Cases
    Route::get('/critical-absenteeism', [App\Http\Controllers\API\SmartAttendanceAnalyticsController::class, 'getCriticalAbsenteeism']);

    // Bulk Operations
    Route::post('/bulk-refresh', [App\Http\Controllers\API\SmartAttendanceAnalyticsController::class, 'bulkRefreshAnalytics']);

    // UI Support
    Route::get('/urgency-legend', [App\Http\Controllers\API\SmartAttendanceAnalyticsController::class, 'getUrgencyLegend']);
});

// Teacher Dashboard Enhancement Routes
Route::prefix('teacher')->group(function () {
    // Optimized Dashboard Routes
    Route::get('/{teacherId}/dashboard', [App\Http\Controllers\API\TeacherDashboardController::class, 'getDashboardData']);
    Route::get('/{teacherId}/chart-data', [App\Http\Controllers\API\TeacherDashboardController::class, 'getAttendanceChartData']);

    // Sticky notes functionality removed

    // Student Management (Three-View System)
    Route::prefix('students')->group(function () {
        Route::get('/', [App\Http\Controllers\API\TeacherStudentManagementController::class, 'getStudents']);
        Route::post('/{studentId}/change-status', [App\Http\Controllers\API\TeacherStudentManagementController::class, 'changeStudentStatus']);
        Route::get('/status-options', [App\Http\Controllers\API\TeacherStudentManagementController::class, 'getStatusOptions']);
    });

    // Learner Status Management Routes
    Route::prefix('{teacherId}/learner-status')->group(function () {
        Route::get('/students', [StudentStatusController::class, 'getStudentsForTeacher']);
        Route::put('/students/{studentId}/status', [StudentStatusController::class, 'updateStudentStatus']);
        Route::get('/students/{studentId}/history', [StudentStatusController::class, 'getStudentStatusHistory']);
    });
});

// Admin Student Management & Archive System Routes
Route::prefix('admin')->group(function () {
    // Student Management (replaces delete functionality)
    Route::prefix('students')->group(function () {
        Route::get('/', [App\Http\Controllers\API\AdminStudentManagementController::class, 'index']);
        Route::post('/{studentId}/change-status', [App\Http\Controllers\API\AdminStudentManagementController::class, 'changeStatus']);
        Route::get('/{studentId}/status-history', [App\Http\Controllers\API\AdminStudentManagementController::class, 'getStatusHistory']);
        Route::post('/{studentId}/archive', [App\Http\Controllers\API\AdminStudentManagementController::class, 'archiveStudent']);
    });

    // Archive Management
    Route::prefix('archive')->group(function () {
        Route::get('/students', [App\Http\Controllers\API\AdminStudentManagementController::class, 'getArchivedStudents']);
        Route::post('/students/{archiveId}/restore', [App\Http\Controllers\API\AdminStudentManagementController::class, 'restoreStudent']);
    });
});

// Notification System Routes
Route::prefix('notifications')->group(function () {
    Route::get('/', [App\Http\Controllers\API\NotificationController::class, 'index']);
    Route::post('/', [App\Http\Controllers\API\NotificationController::class, 'store']);
    Route::post('/{notificationId}/mark-read', [App\Http\Controllers\API\NotificationController::class, 'markAsRead']);
    Route::post('/mark-all-read', [App\Http\Controllers\API\NotificationController::class, 'markAllAsRead']);
    Route::get('/unread-count', [App\Http\Controllers\API\NotificationController::class, 'getUnreadCount']);
    Route::get('/statistics', [App\Http\Controllers\API\NotificationController::class, 'getStatistics']);
    Route::delete('/{notificationId}', [App\Http\Controllers\API\NotificationController::class, 'destroy']);

    // Development/Testing route
    Route::post('/test', [App\Http\Controllers\API\NotificationController::class, 'createTestNotification']);
});

// Debug/Testing Routes (remove in production)
Route::get('/debug/analytics-cache', function() {
    $cacheCount = \App\Models\AttendanceAnalyticsCache::count();
    $todayCache = \App\Models\AttendanceAnalyticsCache::where('analysis_date', now()->toDateString())->count();
    $criticalCases = \App\Models\AttendanceAnalyticsCache::where('exceeds_18_absence_limit', true)->count();

    return response()->json([
        'total_cache_records' => $cacheCount,
        'today_cache_records' => $todayCache,
        'critical_cases' => $criticalCases,
        'sample_records' => \App\Models\AttendanceAnalyticsCache::latest()->take(3)->get()
    ]);
});

Route::post('/debug/generate-analytics-cache', function() {
    $students = \App\Models\Student::take(5)->get();
    $generated = 0;

    foreach ($students as $student) {
        try {
            \App\Models\AttendanceAnalyticsCache::generateForStudent($student->id);
            $generated++;
        } catch (\Exception $e) {
            Log::error("Failed to generate analytics for student {$student->id}: " . $e->getMessage());
        }
    }

    return response()->json([
        'success' => true,
        'message' => "Generated analytics cache for {$generated} students",
        'generated_count' => $generated
    ]);
});

// Teacher Assignment Validation Routes
Route::get('/teachers/{teacherId}/assignments', [TeacherAssignmentValidationController::class, 'getTeacherAssignments']);
Route::post('/teachers/validate-homeroom-assignment', [TeacherAssignmentValidationController::class, 'validateHomeroomAssignment']);

Route::get('/students/{studentId}/qr-card/download', [QRCodeController::class, 'downloadQRCard']);

// Fix SF2 Grade Levels (one-time fix for existing data)
Route::get('/fix-sf2-grade-levels', function() {
    try {
        Log::info('Starting SF2 grade level fix...');
        $reports = DB::table('submitted_sf2_reports')->get();
        Log::info('Found ' . count($reports) . ' reports');
        $updated = 0;
        
        foreach ($reports as $report) {
            Log::info('Processing report', ['id' => $report->id, 'section_id' => $report->section_id]);
            
            $section = DB::table('sections')
                ->join('curriculum_grades', 'sections.curriculum_grade_id', '=', 'curriculum_grades.id')
                ->join('grades', 'curriculum_grades.grade_id', '=', 'grades.id')
                ->where('sections.id', $report->section_id)
                ->select('grades.name as grade_name')
                ->first();
            
            if ($section) {
                Log::info('Found grade', ['grade' => $section->grade_name]);
                DB::table('submitted_sf2_reports')
                    ->where('id', $report->id)
                    ->update(['grade_level' => $section->grade_name]);
                $updated++;
            } else {
                Log::warning('Section not found', ['section_id' => $report->section_id]);
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Grade levels updated successfully',
            'updated' => $updated,
            'total' => count($reports)
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fix grade levels',
            'error' => $e->getMessage()
        ], 500);
    }
});
