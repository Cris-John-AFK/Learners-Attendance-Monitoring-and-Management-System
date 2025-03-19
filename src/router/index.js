import AppLayout from '@/layout/AppLayout.vue';
import AdminLayout from '@/layout/adminlayout/AdminLayout.vue';
import GuardHouseLayout from '@/layout/guardhouselayout/GuardHouseLayout.vue';
import GuestLayout from '@/layout/guestlayout/GuestLayout.vue';
import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            component: AppLayout,
            children: [
                {
                    path: '/',
                    name: 'dashboard',
                    component: () => import('@/views/Dashboard.vue')
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
                    path: '/pages/section',
                    name: 'section',
                    component: () => import('@/views/pages/Section.vue')
                },
                {
                    path: '/pages/settings',
                    name: 'settings',
                    component: () => import('@/views/pages/Settings.vue')
                }
            ]
        },
        {
            path: '/admin',
            component: AdminLayout,
            children: [
                {
                    path: '/admin',
                    name: 'admin-dashboard',
                    component: () => import('@/views/admin/AdminDashboard.vue')
                },
                {
                    path: '/admin-graph',
                    name: 'admin-graph',
                    component: () => import('@/views/pages/Admin/Admin-Graph.vue')
                },
                {
                    path: '/admin-teacher',
                    name: 'admin-teacher',
                    component: () => import('@/views/pages/Admin/Admin-Teacher.vue')
                },
                {
                    path: '/admin-student',
                    name: 'admin-student',
                    component: () => import('@/views/pages/Admin/Admin-Student.vue')
                },
                {
                    path: '/admin-section',
                    name: 'admin-section',
                    component: () => import('@/views/pages/Admin/Admin-Section.vue')
                },
                {
                    path: '/admin-settings',
                    name: 'admin-settings',
                    component: () => import('@/views/pages/Admin/Admin-Settings.vue')
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
        }
    ]
});

export default router;
