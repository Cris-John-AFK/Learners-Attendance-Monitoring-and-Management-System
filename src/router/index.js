import AppLayout from '@/layout/AppLayout.vue';
import AdminLayout from '@/layout/adminlayout/AdminLayout.vue';
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
                    path: '/pages/settings',
                    name: 'settings',
                    component: () => import('@/views/pages/Settings.vue')
                },
                {
                    path: '/pages/section',
                    name: 'section',
                    component: () => import('@/views/pages/Section.vue')
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
                }
            ],
        }
    ]
});

export default router;
