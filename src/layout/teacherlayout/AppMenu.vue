<script setup>
import { ref, onMounted } from 'vue';
import { TeacherAttendanceService } from '@/router/service/TeacherAttendanceService';

import AppMenuItem from './AppMenuItem.vue';

const model = ref([
    {
        label: 'Home',
        items: [{ label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/teacher' }]
    },
    {
        label: 'Homeroom Subjects',
        icon: 'pi pi-fw pi-briefcase',
        items: []
    },
    {
        separator: true
    },
    {
        label: 'Other Subjects',
        items: []
    },
    {
        separator: true
    },
    {
        label: 'Tools',
        items: [
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
        const teacherId = 3; // Maria Santos
        const assignments = await TeacherAttendanceService.getTeacherAssignments(teacherId);
        
        if (assignments && assignments.assignments && assignments.assignments.length > 0) {
            const homeroomSubjects = [];
            const otherSubjects = [];
            
            assignments.assignments.forEach(assignment => {
                assignment.subjects.forEach(subject => {
                    const menuItem = {
                        label: subject.subject_name,
                        icon: 'pi pi-fw pi-book',
                        to: `/subject/${subject.subject_code.toLowerCase()}`
                    };
                    
                    if (subject.role === 'homeroom_teacher' || subject.subject_code === 'HR') {
                        homeroomSubjects.push(menuItem);
                    } else {
                        otherSubjects.push(menuItem);
                    }
                });
            });
            
            // Update the menu model
            const homeroomIndex = model.value.findIndex(item => item.label === 'Homeroom Subjects');
            const otherIndex = model.value.findIndex(item => item.label === 'Other Subjects');
            
            if (homeroomIndex !== -1) {
                model.value[homeroomIndex].items = homeroomSubjects;
            }
            if (otherIndex !== -1) {
                model.value[otherIndex].items = otherSubjects;
            }
        }
    } catch (error) {
        console.error('Error loading teacher assignments for menu:', error);
        // Keep default menu items as fallback
        const homeroomIndex = model.value.findIndex(item => item.label === 'Homeroom Subjects');
        if (homeroomIndex !== -1) {
            model.value[homeroomIndex].items = [
                {
                    label: 'Mathematics',
                    icon: 'pi pi-fw pi-book',
                    to: '/subject/mathematics'
                },
                {
                    label: 'Homeroom',
                    icon: 'pi pi-fw pi-book',
                    to: '/subject/homeroom'
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
