<script setup>
import { ref, onMounted } from 'vue';
import { TeacherAttendanceService } from '@/router/service/TeacherAttendanceService';
import TeacherAuthService from '@/services/TeacherAuthService';

import AppMenuItem from './AppMenuItem.vue';

const model = ref([
    {
        label: 'Home',
        items: [{ label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/teacher' }]
    },
    {
        label: 'Homeroom',
        icon: 'pi pi-fw pi-home',
        items: []
    },
    {
        separator: true
    },
    {
        label: 'Subjects',
        icon: 'pi pi-fw pi-briefcase',
        items: []
    },
    {
        separator: true
    },
    {
        label: 'Attendance',
        items: [
            {
                label: 'Attendance Records',
                icon: 'pi pi-fw pi-calendar-plus',
                to: '/teacher/attendance-records'
            },
            {
                label: 'Attendance Sessions',
                icon: 'pi pi-fw pi-calendar-times',
                to: '/teacher/attendance-sessions'
            }
        ]
    },
    {
        separator: true
    },
    {
        label: 'Student Management',
        items: [
            {
                label: 'Learner Status',
                icon: 'pi pi-fw pi-users',
                to: '/teacher/learner-status'
            },
            {
                label: 'Student QR Codes',
                icon: 'pi pi-fw pi-qrcode',
                to: '/teacher/student-qrcodes'
            }
        ]
    }
]);

// Load real teacher assignments
onMounted(async () => {
    try {
        // Check if teacher is authenticated
        if (!TeacherAuthService.isAuthenticated()) {
            console.log('Teacher not authenticated, using fallback menu');
            return;
        }
        
        const assignments = TeacherAuthService.getAssignments();
        
        if (assignments && assignments.length > 0) {
            const homeroomSubjects = [];
            const otherSubjects = [];
            
            assignments.forEach(assignment => {
                if (assignment.subject_name) {
                    const menuItem = {
                        label: assignment.subject_name,
                        icon: 'pi pi-fw pi-book',
                        to: `/subject/${assignment.subject_name.toLowerCase().replace(/\s+/g, '')}`
                    };
                    
                    if (assignment.subject_name.toLowerCase() === 'homeroom' || assignment.role === 'homeroom_teacher') {
                        homeroomSubjects.push(menuItem);
                    } else {
                        otherSubjects.push(menuItem);
                    }
                }
            });
            
            // Update the menu model
            const homeroomIndex = model.value.findIndex(item => item.label === 'Homeroom');
            const subjectsIndex = model.value.findIndex(item => item.label === 'Subjects');
            
            if (homeroomIndex !== -1) {
                model.value[homeroomIndex].items = homeroomSubjects;
            }
            if (subjectsIndex !== -1) {
                model.value[subjectsIndex].items = otherSubjects;
            }
        }
    } catch (error) {
        console.error('Error loading teacher assignments for menu:', error);
        // Keep default menu items as fallback
        const homeroomIndex = model.value.findIndex(item => item.label === 'Homeroom');
        const subjectsIndex = model.value.findIndex(item => item.label === 'Subjects');
        
        if (homeroomIndex !== -1) {
            model.value[homeroomIndex].items = [
                {
                    label: 'Homeroom',
                    icon: 'pi pi-fw pi-home',
                    to: '/subject/homeroom'
                }
            ];
        }
        
        if (subjectsIndex !== -1) {
            model.value[subjectsIndex].items = [
                {
                    label: 'Mathematics',
                    icon: 'pi pi-fw pi-book',
                    to: '/subject/mathematics'
                }
            ];
        }
    }
});
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="item">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style lang="scss" scoped>
// Removed specific styling since it's now in the central teacher-fonts.css file
</style>
