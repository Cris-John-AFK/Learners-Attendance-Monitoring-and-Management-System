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
        
        // Use getUniqueSubjects() which already filters out homeroom
        const subjects = TeacherAuthService.getUniqueSubjects();
        
        if (subjects && subjects.length > 0) {
            const subjectMenuItems = subjects.map(subject => {
                // For departmentalized teachers (Grade 4-6), show "Subject - Section"
                // For homeroom teachers, just show "Subject"
                let displayLabel = subject.name;
                
                if (subject.sectionName && subject.sectionName !== 'Unknown Section') {
                    // Show format: "English - Grade 4 Silang"
                    displayLabel = `${subject.name} - Grade ${subject.grade} ${subject.sectionName}`;
                }
                
                return {
                    label: displayLabel,
                    icon: 'pi pi-fw pi-book',
                    to: `/subject/${subject.name.toLowerCase().replace(/\s+/g, '')}`
                };
            });
            
            // Update the Subjects section
            const subjectsIndex = model.value.findIndex(item => item.label === 'Subjects');
            if (subjectsIndex !== -1) {
                model.value[subjectsIndex].items = subjectMenuItems;
            }
        }
    } catch (error) {
        console.error('Error loading teacher assignments for menu:', error);
        // Keep Subjects section empty as fallback
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
