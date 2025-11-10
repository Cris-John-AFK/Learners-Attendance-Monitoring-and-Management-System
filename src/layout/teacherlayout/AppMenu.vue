<script setup>
import TeacherAuthService from '@/services/TeacherAuthService';
import { onMounted, ref, nextTick } from 'vue';
import { useRoute } from 'vue-router';

import AppMenuItem from './AppMenuItem.vue';

const route = useRoute();
const activeIndicator = ref(null);
const expandedSections = ref({
    homeroom: true,  // Start expanded
    other: true      // Start expanded
});

const model = ref([
    {
        label: 'Home',
        items: [{ label: 'Dashboard', icon: 'pi pi-fw pi-home', to: '/teacher', class: 'font-semibold' }]
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
                icon: 'pi pi-fw pi-book',
                to: '/teacher/attendance-records',
                class: 'font-semibold'
            },
            {
                label: 'Attendance Sessions',
                icon: 'pi pi-fw pi-clock',
                to: '/teacher/attendance-sessions',
                class: 'font-semibold'
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
                icon: 'pi pi-fw pi-user-edit',
                to: '/teacher/learner-status',
                class: 'font-semibold'
            },
            {
                label: 'Student QR Codes',
                icon: 'pi pi-fw pi-qrcode',
                to: '/teacher/student-qrcodes',
                class: 'font-semibold'
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
                label: 'SF2 Report Form',
                icon: 'pi pi-fw pi-file-edit',
                to: '/teacher/daily-attendance',
                class: 'font-semibold'
            },
            {
                label: 'Summary Attendance',
                icon: 'pi pi-fw pi-chart-bar',
                to: '/teacher/summary-attendance',
                class: 'font-semibold'
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
                        class: 'font-semibold',
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
                        class: 'font-semibold',
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
    
    // Position the sliding indicator after menu loads
    await nextTick();
    updateIndicatorPosition();
});

// Function to update indicator position based on active menu item
const updateIndicatorPosition = () => {
    nextTick(() => {
        // Find the active link - use router-link-active-exact to avoid parent route matching
        const activeLink = document.querySelector('.layout-menu a.font-semibold.router-link-exact-active') 
                        || document.querySelector('.layout-menu a.font-semibold.router-link-active:not(.router-link-active > .router-link-active)');
        
        if (activeLink && activeIndicator.value) {
            const rect = activeLink.getBoundingClientRect();
            const menuRect = activeLink.closest('.layout-menu').getBoundingClientRect();
            const topPosition = rect.top - menuRect.top + (rect.height / 2) - 8; // Center the 16px circle
            
            activeIndicator.value.style.top = `${topPosition}px`;
            activeIndicator.value.style.opacity = '1';
        } else if (activeIndicator.value) {
            activeIndicator.value.style.opacity = '0';
        }
    });
};

// Watch for route changes and update indicator
import { watch } from 'vue';
watch(() => route.path, () => {
    updateIndicatorPosition();
});

// Toggle section expansion
const toggleSection = (section) => {
    expandedSections.value[section] = !expandedSections.value[section];
    // Update indicator position after animation
    setTimeout(() => updateIndicatorPosition(), 400);
};
</script>

<template>
    <ul class="layout-menu" style="position: relative;">
        <!-- Sliding circle indicator -->
        <div ref="activeIndicator" class="menu-active-indicator"></div>
        
        <template v-for="(item, i) in model" :key="item">
            <!-- Special collapsible container for Homeroom Subjects -->
            <li v-if="!item.separator && item.label === 'Homeroom Subjects'" 
                class="subjects-container"
                :class="{ 'collapsed': !expandedSections.homeroom }">
                <div class="subjects-header" @click="toggleSection('homeroom')">
                    <span class="subjects-title">
                        <span class="pulse-dot"></span>
                        <span class="subjects-icon">📚</span>
                        {{ item.label }}
                    </span>
                    <i class="pi" :class="expandedSections.homeroom ? 'pi-chevron-up' : 'pi-chevron-down'"></i>
                </div>
                <div class="subjects-content" v-show="expandedSections.homeroom">
                    <app-menu-item v-for="subItem in item.items" :key="subItem.label" 
                                   :item="{ items: [subItem] }" :index="i"></app-menu-item>
                </div>
            </li>
            
            <!-- Special collapsible container for Other Subjects -->
            <li v-else-if="!item.separator && item.label === 'Other Subjects'" 
                class="subjects-container"
                :class="{ 'collapsed': !expandedSections.other }">
                <div class="subjects-header" @click="toggleSection('other')">
                    <span class="subjects-title">
                        <span class="pulse-dot"></span>
                        <span class="subjects-icon">📚</span>
                        {{ item.label }}
                    </span>
                    <i class="pi" :class="expandedSections.other ? 'pi-chevron-up' : 'pi-chevron-down'"></i>
                </div>
                <div class="subjects-content" v-show="expandedSections.other">
                    <app-menu-item v-for="subItem in item.items" :key="subItem.label" 
                                   :item="{ items: [subItem] }" :index="i"></app-menu-item>
                </div>
            </li>
            
            <!-- Regular menu items -->
            <app-menu-item v-else-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>

<style scoped>
/* Modern glassmorphism container for subject sections */
.subjects-container {
    background: linear-gradient(135deg, 
        rgba(102, 126, 234, 0.05) 0%, 
        rgba(118, 75, 162, 0.03) 100%);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 0;
    margin: 10px 8px;
    box-shadow: 
        0 4px 16px rgba(102, 126, 234, 0.08),
        0 1px 4px rgba(0, 0, 0, 0.04),
        inset 0 1px 1px rgba(255, 255, 255, 0.8);
    border: 1.5px solid rgba(102, 126, 234, 0.2);
    position: relative;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

/* Clickable header */
.subjects-header {
    padding: 12px 14px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
    user-select: none;
}

.subjects-header:hover {
    background: rgba(102, 126, 234, 0.08);
}

.subjects-header:active {
    background: rgba(102, 126, 234, 0.12);
}

.header-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.subjects-title {
    display: flex;
    align-items: center;
    gap: 8px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 800;
    font-size: 0.85rem;
    letter-spacing: 0.3px;
}

.subjects-icon {
    font-size: 1.1rem;
    filter: drop-shadow(0 2px 4px rgba(102, 126, 234, 0.3));
    animation: float 3s ease-in-out infinite;
}

.subjects-header .pi {
    color: #667eea;
    font-size: 0.9rem;
    transition: transform 0.3s ease;
}

/* Content area with smooth animation */
.subjects-content {
    padding: 0 10px 12px 10px;
    animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Subtle pulsing notification dot */
.pulse-dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    margin-right: 8px;
    position: relative;
    animation: pulse-dot 2s ease-in-out infinite;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
}

.pulse-dot::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: #10b981;
    animation: pulse-ring 2s ease-out infinite;
}

/* Pulsing dot animation */
@keyframes pulse-dot {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    50% {
        transform: scale(1.1);
        box-shadow: 0 0 0 6px rgba(16, 185, 129, 0);
    }
}

/* Expanding ring effect */
@keyframes pulse-ring {
    0% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 0.8;
    }
    100% {
        transform: translate(-50%, -50%) scale(3);
        opacity: 0;
    }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Collapsed state */
.subjects-container.collapsed {
    padding-bottom: 0;
}

/* Animated gradient border */
.subjects-container::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, 
        #667eea 0%, 
        #764ba2 50%, 
        #667eea 100%);
    background-size: 200% 100%;
    animation: gradient-shift 3s ease infinite;
    border-radius: 16px 16px 0 0;
}

@keyframes gradient-shift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

/* Subtle glow effect on hover */
.subjects-container:hover {
    box-shadow: 
        0 12px 40px rgba(102, 126, 234, 0.18),
        0 4px 12px rgba(102, 126, 234, 0.12),
        inset 0 1px 1px rgba(255, 255, 255, 0.9);
    border-color: rgba(102, 126, 234, 0.35);
    transform: translateY(-2px);
}

/* Decorative corner accent */
.subjects-container::after {
    content: '';
    position: absolute;
    bottom: 0;
    right: 0;
    width: 60px;
    height: 60px;
    background: radial-gradient(circle at bottom right, 
        rgba(102, 126, 234, 0.08) 0%, 
        transparent 70%);
    border-radius: 16px;
    pointer-events: none;
}

/* Section headers styling */
:deep(.layout-menu .layout-menuitem-root-text) {
    font-weight: 600;
    font-size: 0.85rem;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

/* Hide the default section header since we have custom header */
.subjects-container :deep(.layout-menuitem-root-text) {
    display: none;
}

/* Button-style menu items */
:deep(.layout-menu a.font-semibold),
:deep(.layout-menu .font-semibold) {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    border-radius: 10px !important;
    padding: 14px 18px !important;
    margin: 6px 12px !important;
    font-weight: 600 !important;
    font-size: 1rem !important;
    box-shadow: 0 3px 10px rgba(102, 126, 234, 0.4) !important;
    transition: all 0.3s ease !important;
    border: none !important;
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
}

/* Hover effect */
:deep(.layout-menu a.font-semibold:hover),
:deep(.layout-menu .font-semibold:hover) {
    background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%) !important;
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.6) !important;
    transform: translateY(-3px) scale(1.02) !important;
}

/* Icon styling */
:deep(.layout-menu a.font-semibold .layout-menuitem-icon),
:deep(.layout-menu .font-semibold .layout-menuitem-icon) {
    color: white !important;
    font-size: 1.2rem !important;
    margin-right: 4px !important;
}

/* Text styling */
:deep(.layout-menu a.font-semibold .layout-menuitem-text),
:deep(.layout-menu .font-semibold .layout-menuitem-text) {
    color: white !important;
    font-weight: 600 !important;
    font-size: 1rem !important;
}

/* Active/selected state */
:deep(.layout-menu a.font-semibold.active-route),
:deep(.layout-menu a.font-semibold.router-link-active),
:deep(.layout-menu .font-semibold.active-route) {
    background: linear-gradient(135deg, #4c51bf 0%, #553c7b 100%) !important;
    box-shadow: 0 5px 18px rgba(76, 81, 191, 0.7) !important;
    transform: scale(1.03) !important;
}
</style>

<style lang="scss" scoped>
// Removed specific styling since it's now in the central teacher-fonts.css file
</style>
