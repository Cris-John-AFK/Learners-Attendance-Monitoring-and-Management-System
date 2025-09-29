import ApiTest from '@/components/ApiTest.vue';
import LoginLayout from '@/layout/LoginLayout/LoginLayout.vue';
import AdminLayout from '@/layout/adminlayout/AdminLayout.vue';
import EnrollmentLayout from '@/layout/enrollmentlayout/EnrollmentLayout.vue';
import GuardHouseLayout from '@/layout/guardhouselayout/GuardHouseLayout.vue';
import GuestLayout from '@/layout/guestlayout/GuestLayout.vue';
import AppLayout from '@/layout/teacherlayout/AppLayout.vue';
import StudentQRCodes from '@/views/pages/teacher/StudentQRCodes.vue';
import TeacherDashboard from '@/views/pages/teacher/TeacherDashboard.vue';
import TeacherSettings from '@/views/pages/teacher/TeacherSettings.vue';
import TeacherSubjectAttendance from '@/views/pages/teacher/TeacherSubjectAttendance.vue';
import { createRouter, createWebHistory } from 'vue-router';
import TeacherAuthService from '@/services/TeacherAuthService';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'homepage',
            component: () => import('@/views/homepagelanding/HomePageLand.vue'),
            meta: {
                layout: () => import('@/layout/homepagelayout/LayoutHomePage.vue')
            }
        },
        {
            path: '/login',
            component: LoginLayout,
            children: [
                {
                    path: '',
                    name: 'login-page',
                    component: () => import('@/views/pages/Login/LoginPage.vue')
                }
            ]
        },
        {
            path: '/teacher',
            component: AppLayout,
            meta: { requiresAuth: true },
            children: [
                {
                    path: '/teacher',
                    name: 'dashboard',
                    component: TeacherDashboard
                },
                {
                    path: '/pages/attendance',
                    name: 'attendance',
                    component: () => import('@/views/pages/Attendance.vue')
                },
                {
                    path: '/pages/report',
                    name: 'report',
                    component: () => import('@/views/pages/Report.vue')
                },
                {
                    path: '/teacher/attendance-records',
                    name: 'teacher-attendance-records',
                    component: () => import('@/views/pages/teacher/TeacherAttendanceRecords.vue')
                },
                {
                    path: '/teacher/attendance-sessions',
                    name: 'teacher-attendance-sessions',
                    component: () => import('@/views/pages/teacher/TeacherAttendanceSessions.vue')
                },
                {
                    path: '/pages/settings',
                    name: 'settings',
                    component: TeacherSettings
                },
                {
                    path: '/subject/homeroom',
                    name: 'homeroom-attendance',
                    component: TeacherSubjectAttendance,
                    props: { subjectId: '2', subjectName: 'Homeroom' }
                },
                {
                    path: '/subject/mathematics',
                    name: 'mathematics-attendance', 
                    component: TeacherSubjectAttendance,
                    props: { subjectId: '1', subjectName: 'Mathematics' }
                },
                {
                    path: '/subject/:subjectId',
                    name: 'subject-attendance',
                    component: TeacherSubjectAttendance
                },
                {
                    path: '/teacher/student-qrcodes',
                    name: 'student-qrcodes',
                    component: StudentQRCodes
                },
                {
                    path: '/teacher/sf2-report/:sectionId',
                    name: 'teacher-sf2-report',
                    component: () => import('@/views/pages/teacher/TeacherSF2Report.vue')
                },
                {
                    path: '/teacher/schedules',
                    name: 'teacher-schedules',
                    component: () => import('@/views/pages/teacher/TeacherSectionSchedules.vue')
                },
                {
                    path: '/teacher/create-schedule',
                    name: 'teacher-create-schedule',
                    component: () => import('@/views/pages/teacher/CreateSchedule.vue')
                }
            ]
        },
        {
            path: '/admin',
            component: AdminLayout,
            children: [
                {
                    path: '/admin',
                    name: 'admin-graph',
                    component: () => import('@/views/pages/Admin/Admin-Graph.vue')
                },
                {
                    path: '/admin-teacher',
                    name: 'admin-teacher',
                    component: () => import('@/views/pages/Admin/Admin-Teacher.vue')
                },
                {
                    path: '/admin-enrollment',
                    name: 'admin-enrollment',
                    component: () => import('@/views/pages/Admin/Admin-Enrollment.vue')
                },

                {
                    path: '/admin-student',
                    name: 'admin-student',
                    component: () => import('@/views/pages/Admin/Admin-Student.vue')
                },
                {
                    path: '/admin-admission',
                    name: 'admin-admission',
                    component: () => import('@/views/pages/Admin/Admin-Admission.vue')
                },
                {
                    path: '/admin-student-statistics',
                    name: 'admin-student-statistics',
                    component: () => import('@/views/pages/Admin/Admin-StudentStatistics.vue')
                },

                {
                    path: '/admin-section',
                    name: 'admin-section',
                    component: () => import('@/views/pages/Admin/Admin-Section.vue')
                },
                {
                    path: '/admin-collected-reports',
                    name: 'admin-collected-reports',
                    component: () => import('@/views/pages/Admin/Admin-CollectedReports.vue')
                },
                {
                    path: '/admin-subject',
                    name: 'admin-subject',
                    component: () => import('@/views/pages/Admin/Admin-Subject.vue')
                },
                {
                    path: '/admin-settings',
                    name: 'admin-settings',
                    component: () => import('@/views/pages/Admin/Admin-Settings.vue')
                },

                {
                    path: '/archive',
                    name: 'archive',
                    component: () => import('@/views/pages/Admin/Archive.vue')
                },
                {
                    path: '/curriculum',
                    name: 'curriculum',
                    component: () => import('@/views/pages/Admin/Curriculum.vue')
                },
                {
                    path: '/admin/subject-scheduling',
                    name: 'admin-subject-scheduling',
                    component: () => import('@/views/pages/Admin/SubjectScheduling.vue')
                }
            ]
        },
        {
            path: '/guest',
            component: GuestLayout,
            children: [
                {
                    path: '/guest',
                    name: 'guest-dashboard',
                    component: () => import('@/views/guest/GuestDashboard.vue')
                },
                {
                    path: '/guest/student-search',
                    name: 'student-search',
                    component: () => import('@/components/dashboard/SearchStudentId.vue')
                },
                {
                    path: '/guest/student/:id',
                    name: 'student-details',
                    component: () => import('@/views/guest/StudentDetails.vue')
                }
            ]
        },
        {
            path: '/guardhouse',
            component: GuardHouseLayout,
            children: [
                {
                    path: '/guardhouse',
                    name: 'guardhouse',
                    component: () => import('@/views/guardhouse/GuardHouse.vue')
                },
                {
                    path: '/qr-scanner',
                    name: 'QrScanner',
                    component: () => import('@/components/dashboard/QRScanner.vue')
                }
            ]
        },
        {
            path: '/enrollment',
            component: EnrollmentLayout,
            children: [
                {
                    path: '',
                    name: 'EnrollmentPage',
                    component: () => import('@/layout/enrollmentlayout/EnrollmentPage.vue')
                },
                {
                    path: 'landing',
                    name: 'EnrollmentLanding',
                    component: () => import('@/views/Enrollment/EnrollmentLanding.vue')
                },
                {
                    path: 'old-student',
                    name: 'OldStudent',
                    component: () => import('@/views/Enrollment/OldStudentForm.vue')
                },
                {
                    path: 'new-student',
                    name: 'NewStudent',
                    component: () => import('@/views/Enrollment/RegistrationForm.vue')
                },
                {
                    path: 'transfer-student',
                    name: 'TransferStudent',
                    component: () => import('@/views/Enrollment/TransferStudentForm.vue')
                }
            ]
        },
        {
            path: '/enrollment/registration',
            name: 'registration',
            component: () => import('@/views/Enrollment/RegistrationForm.vue'),
            meta: {
                layout: 'enrollment'
            }
        },
        {
            path: '/enrollment/confirmation',
            name: 'registration-confirmation',
            component: () => import('@/views/Enrollment/RegistrationConfirmation.vue'),
            meta: {
                layout: 'enrollment'
            }
        },
        {
            path: '/api-test',
            component: ApiTest
        }
    ]
});

// Route guard for teacher authentication
router.beforeEach((to, from, next) => {
    // Check if route requires authentication
    if (to.matched.some(record => record.meta.requiresAuth)) {
        try {
            // Check if teacher is authenticated (synchronous method)
            const isAuthenticated = TeacherAuthService.isAuthenticated();
            
            if (!isAuthenticated) {
                console.log('Teacher not authenticated, redirecting to login');
                // Redirect to general login (homepage) if not authenticated
                next('/');
            } else {
                console.log('Teacher authenticated, allowing access');
                next();
            }
        } catch (error) {
            console.error('Authentication check failed:', error);
            next('/');
        }
    } else {
        next();
    }
});

export default router;
