<script setup>
import TeacherAuthService from '@/services/TeacherAuthService';
import { onMounted, ref } from 'vue';

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
        label: 'Homeroom Subjects',
        icon: 'pi pi-fw pi-home',
        items: []
    },
    {
        separator: true
    },
    {
        label: 'Other Subjects',
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
    },
    {
        separator: true
    },
    {
        label: 'Reports',
        items: [
            {
                label: 'Daily Attendance',
                icon: 'pi pi-fw pi-calendar-plus',
                to: '/teacher/daily-attendance'
            },
            {
                label: 'Summary Attendance',
                icon: 'pi pi-fw pi-chart-bar',
                to: '/teacher/summary-attendance'
            }
        ]
    }
]);

// Enhanced teacher ID detection (same as attendance records)
const getTeacherId = () => {
    // First, try to get from TeacherAuthService
    const teacherData = TeacherAuthService.getTeacherData();
    if (teacherData && teacherData.teacher && teacherData.teacher.id) {
        return parseInt(teacherData.teacher.id);
    }

    // Try teacher_auth_data in localStorage
    const authData = localStorage.getItem('teacher_auth_data');
    if (authData) {
        try {
            const parsed = JSON.parse(authData);
            if (parsed.teacher && parsed.teacher.id) {
                return parseInt(parsed.teacher.id);
            }
        } catch (e) {
            console.error('Error parsing teacher_auth_data:', e);
        }
    }

    // Use fallback ID 2 (Ana Cruz) since AppTopbar shows teacher ID 2
    console.log('🔄 Menu: Using fallback teacher ID 2 (Ana Cruz)');
    return 2;
};

// Load real teacher assignments
onMounted(async () => {
    try {
        // Get teacher ID using enhanced detection
        const teacherId = getTeacherId();
        console.log('📋 Menu: Loading subjects for teacher ID:', teacherId);

        // Load assignments directly from API (same as attendance records)
        try {
            const response = await fetch(`http://127.0.0.1:8000/api/teachers/${teacherId}/assignments`);
            const assignments = await response.json();
            const assignmentsArray = Array.isArray(assignments) ? assignments : assignments.assignments || [];

            console.log('📋 Menu: Loaded assignments:', assignmentsArray);

            // Debug: Log first assignment to see data structure
            if (assignmentsArray.length > 0) {
                console.log('📋 Menu: First assignment structure:', JSON.stringify(assignmentsArray[0], null, 2));
            }

            // Find teacher's homeroom section first
            const homeroomAssignment = assignmentsArray.find((assignment) => !assignment.subject_id && assignment.is_primary);
            const homeroomSectionName = homeroomAssignment?.section?.name || homeroomAssignment?.section_name;

            console.log('📋 Menu: Teacher homeroom section:', homeroomSectionName);

            // Separate homeroom subjects from other subjects
            const homeroomSubjects = [];
            const otherSubjects = [];

            assignmentsArray.forEach((assignment) => {
                if (assignment.subject_id && assignment.subject_name) {
                    const subjectData = {
                        id: assignment.subject_id || assignment.id,
                        name: assignment.subject_name,
                        sectionName: assignment.section_name || assignment.section?.name,
                        grade: assignment.section?.grade_level || 'Unknown Grade',
                        sectionId: assignment.section_id
                    };

                    // If this subject is taught in the teacher's homeroom section, it's a homeroom subject
                    const subjectSectionName = assignment.section_name || assignment.section?.name;
                    if (subjectSectionName === homeroomSectionName) {
                        homeroomSubjects.push(subjectData);
                    } else {
                        otherSubjects.push(subjectData);
                    }
                }
            });

            console.log('📋 Menu: Homeroom subjects:', homeroomSubjects);
            console.log('📋 Menu: Other subjects:', otherSubjects);

            // Process homeroom subjects
            if (homeroomSubjects && homeroomSubjects.length > 0) {
                const homeroomMenuItems = homeroomSubjects.map((subject) => {
                    let displayLabel = subject.name;

                    // Shorten common subject names
                    if (subject.name === 'Mother Tongue-Based Multilingual Education') {
                        displayLabel = 'Mother Tongue';
                    } else if (subject.name === 'Physical Education') {
                        displayLabel = 'PE';
                    }

                    // Add section info for clarity
                    if (subject.sectionName) {
                        displayLabel = `${displayLabel} (${subject.sectionName})`;
                    }

                    return {
                        label: displayLabel,
                        icon: 'pi pi-fw pi-book',
                        to: {
                            name: 'subject-attendance',
                            params: {
                                subjectId: subject.id
                            },
                            query: {
                                sectionName: subject.sectionName,
                                teacherId: teacherId
                            }
                        }
                    };
                });

                // Update the Homeroom Subjects section
                const homeroomIndex = model.value.findIndex((item) => item.label === 'Homeroom Subjects');
                if (homeroomIndex !== -1) {
                    model.value[homeroomIndex].items = homeroomMenuItems;
                }

                console.log('📋 Menu: Updated homeroom subjects menu with', homeroomMenuItems.length, 'items');
            }

            // Process other subjects
            if (otherSubjects && otherSubjects.length > 0) {
                // Check if this is a departmental teacher (teaching multiple sections)
                const allSameSections = otherSubjects.every((s) => s.sectionName === otherSubjects[0].sectionName);

                const otherSubjectMenuItems = otherSubjects.map((subject) => {
                    let displayLabel = subject.name;

                    // Shorten common subject names
                    if (subject.name === 'Mother Tongue-Based Multilingual Education') {
                        displayLabel = 'Mother Tongue';
                    } else if (subject.name === 'Physical Education') {
                        displayLabel = 'PE';
                    }

                    // Always show section info for other subjects to distinguish them
                    if (subject.sectionName && subject.sectionName !== 'Unknown Section') {
                        // Format grade level for display (Kindergarten -> K, Grade 1 -> 1, etc.)
                        let gradeDisplay = subject.grade;
                        if (gradeDisplay && gradeDisplay !== 'Unknown Grade') {
                            if (gradeDisplay.toLowerCase().includes('kindergarten') || gradeDisplay.toLowerCase().includes('kinder')) {
                                gradeDisplay = 'K';
                            } else if (gradeDisplay.toLowerCase().includes('grade')) {
                                const gradeNumber = gradeDisplay.match(/\d+/);
                                gradeDisplay = gradeNumber ? gradeNumber[0] : gradeDisplay;
                            }
                            displayLabel = `${displayLabel} (${gradeDisplay}-${subject.sectionName})`;
                        } else {
                            displayLabel = `${displayLabel} (${subject.sectionName})`;
                        }
                    }

                    return {
                        label: displayLabel,
                        icon: 'pi pi-fw pi-book',
                        to: {
                            name: 'subject-attendance',
                            params: {
                                subjectId: subject.id
                            },
                            query: {
                                sectionName: subject.sectionName,
                                teacherId: teacherId
                            }
                        }
                    };
                });

                // Update the Other Subjects section
                const otherSubjectsIndex = model.value.findIndex((item) => item.label === 'Other Subjects');
                if (otherSubjectsIndex !== -1) {
                    model.value[otherSubjectsIndex].items = otherSubjectMenuItems;
                }

                console.log('📋 Menu: Updated other subjects menu with', otherSubjectMenuItems.length, 'items');
            }
        } catch (apiError) {
            console.error('Error loading assignments from API:', apiError);
            // Keep Subjects section empty as fallback
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
