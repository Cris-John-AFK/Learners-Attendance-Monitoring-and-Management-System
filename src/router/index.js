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
import AuthService from '@/services/AuthService';

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
                    path: '/teacher/learner-status',
                    name: 'teacher-learner-status',
                    component: () => import('@/views/pages/teacher/LearnerStatus.vue')
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
            meta: { requiresAuth: true },
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
                    path: '/admin-school-calendar',
                    name: 'admin-school-calendar',
                    component: () => import('@/views/pages/Admin/SchoolCalendar.vue')
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
            meta: { requiresAuth: true },
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

// Enhanced route guard with session validation
router.beforeEach(async (to, from, next) => {
    // Check if route requires authentication
    if (to.matched.some(record => record.meta.requiresAuth)) {
        try {
            // Check if user is authenticated (unified auth)
            const isAuthenticated = AuthService.isAuthenticated();
            
            if (!isAuthenticated) {
                console.log('User not authenticated, redirecting to login');
                // Clear any stale data
                AuthService.clearAuthData();
                // Redirect to root login page
                next('/');
                return;
            }

            // Validate session with backend
            const sessionCheck = await AuthService.checkSession();
            
            if (!sessionCheck.valid) {
                console.warn('Session invalid or expired:', sessionCheck.message);
                // Clear auth data and redirect to root login page
                AuthService.clearAuthData();
                next('/');
                return;
            }

            // Check if user has the correct role for the route
            const userRole = AuthService.getUserRole();
            const routePath = to.path;

            // Debug logging
            console.log('🔐 Route guard check:', { userRole, routePath, isAuthenticated: AuthService.isAuthenticated() });

            // Role-based route protection
            if (routePath.startsWith('/teacher') && userRole !== 'teacher') {
                console.warn('Access denied: Teacher role required, got:', userRole);
                next('/');
                return;
            }

            if (routePath.startsWith('/admin') && userRole !== 'admin') {
                console.warn('Access denied: Admin role required, got:', userRole);
                next('/');
                return;
            }

            if (routePath.startsWith('/guardhouse') && userRole !== 'guardhouse') {
                console.warn('Access denied: Guardhouse role required');
                next('/');
                return;
            }

            console.log('User authenticated and authorized, allowing access');
            next();
        } catch (error) {
            console.error('Authentication check failed:', error);
            AuthService.clearAuthData();
            next('/');
        }
    } else {
        // For non-protected routes, check if user is already logged in
        // and redirect to their dashboard
        if (to.path === '/login' && AuthService.isAuthenticated()) {
            const role = AuthService.getUserRole();
            if (role === 'teacher') {
                next('/teacher');
            } else if (role === 'admin') {
                next('/admin');
            } else if (role === 'guardhouse') {
                next('/guardhouse');
            } else {
                next();
            }
        } else {
            next();
        }
    }
});

// Prevent back/forward navigation to protected pages after logout
router.afterEach((to, from) => {
    // If navigating to a protected route without authentication, replace history
    if (to.matched.some(record => record.meta.requiresAuth) && !AuthService.isAuthenticated()) {
        // Force replace the history entry to prevent back button
        window.history.replaceState({}, '', '/');
    }
});

// Disable browser history navigation for authenticated pages
window.addEventListener('popstate', function(event) {
    const currentPath = window.location.pathname;
    const isProtected = currentPath.startsWith('/teacher') || 
                       currentPath.startsWith('/admin') || 
                       currentPath.startsWith('/guardhouse');
    
    // If user navigated back from a protected page to root
    if (currentPath === '/' && isProtected === false && AuthService.isAuthenticated()) {
        // Clear the forward history by pushing current state
        window.history.pushState(null, '', '/');
    }
    
    // If trying to access protected page without authentication
    if (isProtected && !AuthService.isAuthenticated()) {
        event.preventDefault();
        window.history.replaceState(null, '', '/');
        window.location.href = '/';
    }
});

export default router;
