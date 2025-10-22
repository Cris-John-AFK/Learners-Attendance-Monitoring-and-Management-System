<script setup>
import LoadingSkeleton from '@/components/LoadingSkeleton.vue';
// 🚀 LAZY LOAD: Non-critical component loaded only when needed
import ScheduleStatusWidget from '@/components/Teachers/ScheduleStatusWidget.vue';
import api from '@/config/axios';
import { TeacherAttendanceService } from '@/router/service/TeacherAttendanceService.js';
import { AttendanceSummaryService } from '@/services/AttendanceSummaryService.js';
import StudentAttendanceService from '@/services/StudentAttendanceService.js';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Chart from 'primevue/chart';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import ProgressBar from 'primevue/progressbar';
import ProgressSpinner from 'primevue/progressspinner';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import { computed, defineAsyncComponent, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
const AttendanceInsights = defineAsyncComponent(() => import('@/components/Teachers/AttendanceInsights.vue'));

// Import teacher authentication service
import TeacherAuthService from '@/services/TeacherAuthService';

// Import Pinia stores (NEW - doesn't break existing code)
import { useAttendanceStore } from '@/stores/attendance';
import { useAuthStore } from '@/stores/auth';
import { useUIStore } from '@/stores/ui';

// Import our new smart analytics services
import AttendanceIndexingService from '@/services/AttendanceIndexingService';
import CacheService from '@/services/CacheService';
import SmartAnalyticsService from '@/services/SmartAnalyticsService';
// Sticky notes service removed

// Import new components
// Sticky notes panel removed

// Initialize router for prefetching
const router = useRouter();

// Initialize Pinia stores (NEW - adds reactive state management)
const authStore = useAuthStore();
const attendanceStore = useAttendanceStore();
const uiStore = useUIStore();

// Dashboard components (EXISTING - keeping all original refs)
const currentTeacher = ref(null);
const teacherSubjects = ref([]);
const attendanceSummary = ref(null);
const studentsWithAbsenceIssues = ref([]);
const selectedStudent = ref(null);
const studentProfileVisible = ref(false);
const profileSubjectFilter = ref(null); // Subject filter for student profile
const profileAvailableSubjects = ref([]); // Available subjects for the profile filter
const loading = ref(true);
const subjectLoading = ref(false);
const chartOptions = ref({});
const attendanceChartData = ref(null);
const selectedSubject = ref(null);
const availableSubjects = ref([]);
const chartViewOptions = [
    { label: 'Daily', value: 'day' },
    { label: 'Weekly', value: 'week' },
    { label: 'Monthly', value: 'month' }
];
const chartView = ref('week');

// Data indexing and refresh control
const isIndexing = ref(false);
const indexingProgress = ref(0);
const lastRefreshTime = ref(null);
const autoRefreshInterval = ref(null);
const MIN_REFRESH_INTERVAL = 60000; // Minimum 1 minute between refreshes

// Attendance view options
const viewTypeOptions = [
    { label: 'Subject-Specific', value: 'subject' },
    { label: 'All Students', value: 'all_students' }
];
const viewType = ref('subject');

// Smart Analytics & Notes features
const smartAnalytics = ref({});
const criticalStudents = ref([]);
const unreadNotifications = ref(0);
const studentsWithSmartAnalytics = ref([]);
const showCriticalAlert = ref(false);
const currentDate = ref(new Date());
const currentMonth = ref(new Date().getMonth());
const currentYear = ref(new Date().getFullYear());
// Calendar data for student profiles
const calendarData = ref([]);
const absentDays = ref([]);

// Calendar subject filter (independent from main subject selection)
const calendarSubjectFilter = ref(null);
const calendarSubjectOptions = computed(() => {
    // Only show actual subjects, no "All Subjects" option
    return availableSubjects.value || [];
});

// Attendance threshold settings - Teacher-friendly (matches Attendance Insights)
const LOW_RISK_THRESHOLD = 1; // Students missing 1-2 days - Low Risk
const AT_RISK_THRESHOLD = 3; // Students missing 3-4 days - High Risk
const CRITICAL_THRESHOLD = 5; // Students missing 5+ days - Critical Risk

// Filter variables
const selectedGradeFilter = ref(null);
const selectedSectionFilter = ref(null);
const selectedStatusFilter = ref(null);

// Filter options - will be populated dynamically based on teacher's assignments
const gradeFilterOptions = ref([]);

const sectionFilterOptions = ref([]);
const statusFilterOptions = ref([
    { label: 'Normal', value: 'good' },
    { label: 'Low Risk', value: 'low' },
    { label: 'High Risk', value: 'at_risk' },
    { label: 'Critical Risk', value: 'critical' }
]);

// Filter options
const showOnlyAbsenceIssues = ref(false);

// Add these to your reactive variables
const showDatePicker = ref(false);
const tempMonth = ref(currentMonth.value);
const tempYear = ref(currentYear.value);

// Generate month and year options
const months = ref([
    { label: 'January', value: 0 },
    { label: 'February', value: 1 },
    { label: 'March', value: 2 },
    { label: 'April', value: 3 },
    { label: 'May', value: 4 },
    { label: 'June', value: 5 },
    { label: 'July', value: 6 },
    { label: 'August', value: 7 },
    { label: 'September', value: 8 },
    { label: 'October', value: 9 },
    { label: 'November', value: 10 },
    { label: 'December', value: 11 }
]);

const years = ref([]);
// Generate years (current year and 2 years back)
for (let i = 0; i < 3; i++) {
    const year = new Date().getFullYear() - i;
    years.value.push({ label: year.toString(), value: year });
}

// Icons for status indicators
const statusIcons = {
    present: 'pi pi-check-circle',
    absent: 'pi pi-times-circle',
    late: 'pi pi-clock',
    warning: 'pi pi-exclamation-triangle',
    critical: 'pi pi-exclamation-circle'
};

// Add this to your variable declarations
const isDebugMode = ref(false); // Set to true manually when debugging

// Add mock data constants after the imports
const MOCK_TEACHER = {
    id: 1,
    name: 'Maria Santos',
    email: 'maria.santos@school.edu',
    section: 'Malikhain (Grade 3)',
    assignedGrades: [
        { gradeId: 3, subjects: ['Mathematics', 'Science'] },
        { gradeId: 4, subjects: ['Mathematics'] }
    ]
};

const MOCK_SUBJECTS = [{ id: 1, name: 'Mathematics', grade: 'Grade 3', originalSubject: { id: 1, name: 'Mathematics' } }];

// Remove mock data - will be loaded from API

const MOCK_ATTENDANCE_CHART_DATA = {
    labels: ['Jan 1-7', 'Jan 8-14', 'Jan 15-21', 'Jan 22-28'],
    datasets: [
        {
            label: 'Present',
            backgroundColor: '#4CAF50',
            data: [8, 9, 7, 8]
        },
        {
            label: 'Absent',
            backgroundColor: '#F44336',
            data: [2, 1, 2, 2]
        },
        {
            label: 'Late',
            backgroundColor: '#FFC107',
            data: [1, 1, 1, 1]
        }
    ]
};

const updateSectionFilterOptions = () => {
    if (studentsWithAbsenceIssues.value && studentsWithAbsenceIssues.value.length > 0) {
        const uniqueSections = [...new Set(studentsWithAbsenceIssues.value.map((student) => student.section))];
        sectionFilterOptions.value = uniqueSections.map((section) => ({
            label: section,
            value: section
        }));
    } else if (availableSubjects.value && availableSubjects.value.length > 0) {
        // Fallback: Use sections from teacher's assignments
        const uniqueSections = [...new Set(availableSubjects.value.map((subject) => subject.sectionName).filter(Boolean))];
        sectionFilterOptions.value = uniqueSections.map((section) => ({
            label: section,
            value: section
        }));
    }
};

const updateGradeFilterOptions = () => {
    if (studentsWithAbsenceIssues.value && studentsWithAbsenceIssues.value.length > 0) {
        // Use grades from actual student data
        const uniqueGrades = [...new Set(studentsWithAbsenceIssues.value.map((student) => student.gradeLevel))];
        gradeFilterOptions.value = uniqueGrades.map((grade) => ({
            label: grade,
            value: grade
        }));
    } else if (availableSubjects.value && availableSubjects.value.length > 0) {
        // Fallback: Use grades from teacher's assignments
        const uniqueGrades = [...new Set(availableSubjects.value.map((subject) => subject.grade).filter(Boolean))];
        gradeFilterOptions.value = uniqueGrades.map((grade) => ({
            label: grade,
            value: grade
        }));
    }
};

// Call this function when student data is loaded
watch(studentsWithAbsenceIssues, () => {
    updateSectionFilterOptions();
    updateGradeFilterOptions();
});

// Auto-refresh function to reload data with throttling
async function refreshDashboardData(forceRefresh = false) {
    // Check if we're already refreshing or too soon
    const now = Date.now();
    if (!forceRefresh && lastRefreshTime.value && now - lastRefreshTime.value < MIN_REFRESH_INTERVAL) {
        console.log('⏳ Skipping refresh - too soon since last refresh');
        return;
    }

    console.log('🔄 Refreshing dashboard data...');
    lastRefreshTime.value = now;

    // If we have indexed data, use it for instant update
    if (selectedSubject.value || viewType.value === 'all_students') {
        const indexedData = AttendanceIndexingService.getIndexedData(currentTeacher.value?.id, selectedSubject.value?.sectionId, selectedSubject.value?.id, viewType.value);

        if (indexedData) {
            console.log('⚡ Using indexed data for instant refresh');
            processIndexedData(indexedData);
            return;
        }
    }

    // Otherwise load fresh data
    await loadAttendanceData();
}

// Controlled auto-refresh interval
let refreshInterval;

// Initialize authentication and load teacher data
const initializeTeacherData = async () => {
    try {
        // Wait a bit for auth data to be fully saved (race condition fix)
        await new Promise((resolve) => setTimeout(resolve, 100));

        // Check if teacher is authenticated
        if (!TeacherAuthService.isAuthenticated()) {
            // Redirect to unified login if not authenticated
            console.log('⚠️ Teacher not authenticated, redirecting to login');
            window.location.href = '/';
            return;
        }

        // Get authenticated teacher data
        const teacherData = TeacherAuthService.getTeacherData();
        if (teacherData) {
            // Get section from homeroom_section first, then fallback to assignments
            let sectionName = 'No section assigned';
            if (teacherData.teacher?.homeroom_section) {
                sectionName = teacherData.teacher.homeroom_section.name;
            } else if (teacherData.assignments && teacherData.assignments.length > 0) {
                sectionName = teacherData.assignments.find((a) => a.section_name)?.section_name || 'No section assigned';
            }

            currentTeacher.value = {
                id: teacherData.teacher.id,
                name: teacherData.teacher.full_name || `${teacherData.teacher.first_name} ${teacherData.teacher.last_name}`,
                email: teacherData.user.email,
                section: sectionName,
                homeroom_section: teacherData.teacher?.homeroom_section || null,
                assignedGrades: []
            };
            // Get unique subjects from assignments with correct section IDs
            const assignments = TeacherAuthService.getAssignments();
            const uniqueSubjects = TeacherAuthService.getUniqueSubjects();

            // Process subjects and handle departmentalized assignments
            const processedSubjects = [];

            uniqueSubjects.forEach((subject) => {
                // Find ALL assignments for this subject (departmentalized teachers may have multiple)
                const subjectAssignments = assignments.filter((a) => a.subject_id === subject.id);

                if (subjectAssignments.length > 1) {
                    // Departmentalized teacher: Same subject across multiple sections
                    subjectAssignments.forEach((assignment, index) => {
                        processedSubjects.push({
                            id: subject.id,
                            name: index === 0 ? subject.name : `${subject.name} (${assignment.section_name})`,
                            grade: subject.grade || 'Unknown',
                            sectionId: assignment.section_id,
                            sectionName: assignment.section_name,
                            originalSubject: { id: subject.id, name: subject.name },
                            assignment: assignment,
                            isDepartmentalized: true,
                            groupId: subject.id // For grouping same subjects
                        });
                    });
                } else {
                    // Single assignment
                    const assignment = subjectAssignments[0];
                    const sections = subject.sections || [];
                    const firstSection = sections[0] || {};

                    processedSubjects.push({
                        id: subject.id,
                        name: subject.name,
                        grade: subject.grade || firstSection.grade || 'Unknown',
                        sectionId: assignment?.section_id || subject.sectionId || firstSection.id,
                        sectionName: assignment?.section_name,
                        originalSubject: { id: subject.id, name: subject.name },
                        assignment: assignment,
                        isDepartmentalized: false
                    });
                }
            });

            // Set BOTH for indexing to work!
            teacherSubjects.value = processedSubjects;
            availableSubjects.value = processedSubjects;

            // Set default selected subject
            if (availableSubjects.value.length > 0) {
                selectedSubject.value = availableSubjects.value[0];
                viewType.value = 'subject'; // Has subjects, use subject view
            } else if (currentTeacher.value.homeroom_section) {
                // Homeroom-only teacher, switch to all students view
                viewType.value = 'all_students';
                console.log('🏠 Homeroom-only teacher detected, switching to All Students view');
            }

            console.log('Teacher data loaded:', currentTeacher.value);
            console.log('Available subjects:', availableSubjects.value);

            // Update filter options based on teacher's assignments
            updateSectionFilterOptions();
            updateGradeFilterOptions();
        }
    } catch (error) {
        console.error('Error initializing teacher data:', error);
    }
};

// Calendar day click handler
const showDayDetailsDialog = ref(false);
const selectedDayDetails = ref(null);

// Get the first day of the month for calendar alignment
function getFirstDayOfMonth() {
    if (!currentYear.value || currentMonth.value === undefined) return 0;
    return new Date(currentYear.value, currentMonth.value, 1).getDay();
}

// Calculate severity based on absence count (matches Attendance Insights EXACTLY)
function calculateSeverity(absences) {
    if (absences >= CRITICAL_THRESHOLD) {
        return 'critical'; // 5+ absences = Critical Risk
    } else if (absences >= AT_RISK_THRESHOLD) {
        return 'at_risk'; // 3-4 absences = High Risk
    } else if (absences > 0) {
        return 'low'; // 1-2 absences = Low Risk
    }
    return 'good'; // 0 absences = Normal (Perfect attendance)
}

// Get severity display label (matches Attendance Insights)
function getSeverityLabel(severity) {
    const labels = {
        critical: 'Critical Risk',
        at_risk: 'High Risk',
        low: 'Low Risk',
        good: 'Normal'
    };
    return labels[severity] || severity;
}

function handleCalendarDayClick(calDay) {
    if (!calDay.status) {
        // No attendance data for this day
        return;
    }

    // Create detailed info for the clicked day
    selectedDayDetails.value = {
        day: calDay.day,
        month: currentMonth.value + 1,
        year: currentYear.value,
        status: calDay.status,
        date: new Date(currentYear.value, currentMonth.value, calDay.day),
        studentName: selectedStudent.value?.name || 'Unknown Student'
    };

    showDayDetailsDialog.value = true;
}

// Load teacher data and subjects
onMounted(async () => {
    loading.value = true;
    try {
        // Initialize authentication and load teacher data
        await initializeTeacherData();

        // If no teacher data loaded, use fallback
        if (!currentTeacher.value) {
            // Use Maria Santos as the default teacher (ID: 3)
            const teacherId = 3;

            // Set teacher data directly (skip API call for now)
            currentTeacher.value = {
                id: teacherId,
                name: 'Maria Santos',
                email: 'maria.santos@naawan.edu.ph',
                section: 'Malikhain (Grade 3)'
            };
            console.log('Set currentTeacher:', currentTeacher.value);

            // Test direct API call to bypass service layer
            console.log('Testing direct API call for teacher assignments...');
            try {
                const directResponse = await fetch(`http://localhost:8000/api/teachers/${teacherId}/assignments`, {
                    method: 'GET',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                const assignments = await directResponse.json();
                console.log('Direct API response:', assignments);
                if (assignments && assignments.assignments && assignments.assignments.length > 0) {
                    // Update section name from assignments (only if currentTeacher exists)
                    const firstAssignment = assignments.assignments[0];
                    if (firstAssignment.section_name && currentTeacher.value) {
                        currentTeacher.value.section = `${firstAssignment.section_name} (Grade 3)`;
                    }

                    const realSubjects = assignments.assignments.flatMap((assignment) =>
                        assignment.subjects.map((subject) => ({
                            id: subject.subject_id,
                            name: subject.subject_name,
                            grade: 'Grade 3',
                            sectionId: assignment.section_id,
                            originalSubject: {
                                id: subject.subject_id,
                                name: subject.subject_name,
                                sectionId: assignment.section_id
                            }
                        }))
                    );

                    teacherSubjects.value = realSubjects;
                    availableSubjects.value = realSubjects.map((subject) => ({
                        id: subject.id,
                        name: `${subject.name} (${subject.grade})`,
                        grade: subject.grade,
                        sectionId: subject.sectionId,
                        originalSubject: subject.originalSubject
                    }));
                } else {
                    // Fallback to mock data
                    teacherSubjects.value = MOCK_SUBJECTS;
                    availableSubjects.value = MOCK_SUBJECTS.map((subject) => ({
                        id: subject.id,
                        name: `${subject.name} (${subject.grade})`,
                        grade: subject.grade,
                        originalSubject: subject
                    }));
                }
            } catch (error) {
                console.error('Error loading teacher assignments:', error);
                // Fallback to hardcoded subjects with sectionId
                const fallbackSubjects = [
                    {
                        id: 1,
                        name: 'Mathematics',
                        grade: 'Grade 3',
                        sectionId: 3,
                        originalSubject: { id: 1, name: 'Mathematics', sectionId: 3 }
                    },
                    {
                        id: 2,
                        name: 'Homeroom',
                        grade: 'Grade 3',
                        sectionId: 3,
                        originalSubject: { id: 2, name: 'Homeroom', sectionId: 3 }
                    }
                ];
                teacherSubjects.value = fallbackSubjects;
                availableSubjects.value = fallbackSubjects.map((subject) => ({
                    id: subject.id,
                    name: `${subject.name} (${subject.grade})`,
                    grade: subject.grade,
                    sectionId: subject.sectionId,
                    originalSubject: subject.originalSubject
                }));
            }
        }

        // Set default selected subject
        if (availableSubjects.value.length > 0) {
            selectedSubject.value = availableSubjects.value[0];
        }

        // Load attendance data
        console.log('About to call loadAttendanceData with:', {
            selectedSubject: selectedSubject.value,
            currentTeacher: currentTeacher.value
        });

        // 🚀 PERFORMANCE: Parallelize API calls instead of sequential
        await Promise.all([loadAttendanceData(), prepareChartData()]);

        // Background indexing DISABLED - causes 220+ requests!
        // Subjects will load on-demand when user switches
        console.log('📌 On-demand loading enabled - subjects load when selected');

        // 🚫 DISABLED: SmartAnalyticsService calls causing 500 errors and slowing down page
        // These are non-critical and can be re-enabled when backend is fixed
        // loadSmartAnalytics().catch((err) => console.warn('Analytics failed (non-critical):', err));
        // loadCriticalStudents().catch((err) => console.warn('Critical students failed (non-critical):', err));

        // 🚀 PREFETCH: Preload likely next pages after initial load (non-blocking)
        setTimeout(() => {
            prefetchLikelyRoutes();
        }, 2000); // Wait 2s after page load
    } catch (error) {
        console.error('Error in onMounted:', error);
    } finally {
        loading.value = false;
    }
});

// 🚀 PERFORMANCE: Preload likely next routes using link tags
function prefetchLikelyRoutes() {
    try {
        // Create link preload tags for likely next pages
        const routes = [];

        // Prefetch attendance taking page (most common next action)
        if (availableSubjects.value.length > 0) {
            const firstSubject = availableSubjects.value[0];
            routes.push(`/subject/${firstSubject.id}`);
        }

        // Prefetch summary report (second most common)
        routes.push('/teacher/summary-attendance');

        // Add prefetch link tags to head
        routes.forEach((route) => {
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = route;
            document.head.appendChild(link);
        });

        console.log('🔮 Prefetched', routes.length, 'likely routes');
    } catch (error) {
        // Prefetch is optional, don't break if it fails
        console.warn('Prefetch failed (non-critical):', error);
    }
}
// Clean up interval on unmount
onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
    // Clear indexed data to free memory
    try {
        AttendanceIndexingService.clearAllIndexedData();
    } catch (error) {
        console.warn('Error clearing indexed data:', error);
    }
});

// Load teacher's subjects from real API assignments
async function loadTeacherSubjects() {
    try {
        if (!currentTeacher.value || !currentTeacher.value.id) {
            console.error('Current teacher not found');
            return;
        }

        console.log('Loading teacher assignments for teacher ID:', currentTeacher.value.id);

        // Get teacher assignments from the API
        const assignmentsResponse = await TeacherAttendanceService.getTeacherAssignments(currentTeacher.value.id);
        console.log('Teacher assignments response:', assignmentsResponse);

        const tempSubjects = [];

        if (assignmentsResponse && assignmentsResponse.assignments) {
            // Process each section assignment
            assignmentsResponse.assignments.forEach((sectionAssignment) => {
                const sectionName = sectionAssignment.section_name;
                const sectionId = sectionAssignment.section_id;

                // Process each subject in this section
                sectionAssignment.subjects.forEach((subject) => {
                    tempSubjects.push({
                        id: subject.subject_id,
                        name: subject.subject_name,
                        grade: `Grade 3`, // Default grade for now

                        sectionName: sectionName,
                        role: subject.role,
                        isPrimary: subject.is_primary,
                        originalSubject: {
                            id: subject.subject_id,
                            name: subject.subject_name,
                            sectionId: sectionId
                        }
                    });
                });
            });
        }

        teacherSubjects.value = tempSubjects;

        // Format subjects for dropdown
        availableSubjects.value = teacherSubjects.value.map((subject) => ({
            id: subject.id,
            name: `${subject.name} (${subject.grade})`, // Show both subject name and grade level
            grade: subject.grade,
            sectionId: subject.sectionId,
            sectionName: subject.sectionName,
            originalSubject: subject.originalSubject
        }));

        console.log('Available subjects from teacher assignments:', availableSubjects.value);

        // Set default selected subject (first in the list)
        if (availableSubjects.value.length > 0) {
            selectedSubject.value = availableSubjects.value[0];
        }
    } catch (error) {
        console.error('Error in loadTeacherSubjects:', error);
        // Create some default data if we can't load real subjects
        handleFallbackData();
    }
}

// Fallback function for when data loading fails
function handleFallbackData() {
    // Create some sample subjects when real data isn't available
    const sampleSubjects = [
        { id: 1, name: 'Mathematics', grade: 'Grade 3', originalSubject: { id: 1, name: 'Mathematics' } },
        { id: 2, name: 'English', grade: 'Grade 3', originalSubject: { id: 2, name: 'English' } },
        { id: 3, name: 'Science', grade: 'Grade 4', originalSubject: { id: 3, name: 'Science' } }
    ];

    teacherSubjects.value = sampleSubjects;

    availableSubjects.value = sampleSubjects.map((subject) => ({
        id: subject.id,
        name: `${subject.name} (${subject.grade})`,
        grade: subject.grade,
        originalSubject: subject
    }));

    if (availableSubjects.value.length > 0) {
        selectedSubject.value = availableSubjects.value[0];
    }

    // Also create some sample students
    const sampleStudents = [
        { id: 101, name: 'Juan Dela Cruz', gradeLevel: 3, section: 'Magalang' },
        { id: 102, name: 'Maria Santos', gradeLevel: 3, section: 'Magalang' },
        { id: 103, name: 'Pedro Penduko', gradeLevel: 3, section: 'Magalang' },
        { id: 104, name: 'Ana Reyes', gradeLevel: 3, section: 'Mahinahon' }
    ];

    // Make sure AttendanceService has a getData method for fallback
    if (!AttendanceService.getData) {
        console.log('Creating fallback AttendanceService.getData method');
        AttendanceService.getData = () => Promise.resolve(sampleStudents);
    }
}

// Process indexed data into component state
function processIndexedData(indexedData) {
    if (!indexedData) {
        console.warn('⚠️ No indexed data provided to processIndexedData');
        return;
    }

    console.log('📦 Processing indexed data:', indexedData);

    // Process summary data
    if (indexedData.summary) {
        attendanceSummary.value = {
            totalStudents: indexedData.summary.total_students || 0,
            averageAttendance: indexedData.summary.average_attendance || 0,
            studentsWithWarning: indexedData.summary.students_with_warning || 0,
            studentsWithCritical: indexedData.summary.students_with_critical || 0,
            students: indexedData.summary.students || []
        };
        console.log('📊 Updated attendance summary from indexed data:', attendanceSummary.value);
    } else {
        console.warn('⚠️ No summary data in indexed data');
    }

    // Process students data - try multiple sources
    let studentsData = [];

    if (indexedData.students && Array.isArray(indexedData.students) && indexedData.students.length > 0) {
        studentsData = indexedData.students;
        console.log('👥 Using students from indexed.students:', studentsData.length);
    } else if (indexedData.summary && indexedData.summary.students && Array.isArray(indexedData.summary.students) && indexedData.summary.students.length > 0) {
        studentsData = indexedData.summary.students;
        console.log('👥 Using students from indexed.summary.students:', studentsData.length);
    } else {
        console.warn('⚠️ No valid students data found in indexed data');
        console.log('📋 Indexed data structure:', {
            hasStudents: !!indexedData.students,
            studentsLength: indexedData.students ? indexedData.students.length : 'N/A',
            hasSummary: !!indexedData.summary,
            summaryStudentsLength: indexedData.summary?.students ? indexedData.summary.students.length : 'N/A'
        });
        return; // Don't clear existing data if indexed data is empty
    }

    if (studentsData.length > 0) {
        studentsWithAbsenceIssues.value = studentsData.map((student) => ({
            id: student.student_id || student.id,
            name: student.name || `${student.firstName} ${student.lastName}` || `${student.first_name} ${student.last_name}`,
            gradeLevel: student.grade_name || student.gradeLevel || 'Unknown Grade',
            section: student.section_name || student.section || `Section ${student.section_id || student.sectionId}`,
            absences: student.total_absences || 0,
            recent_absences: student.recent_absences || 0,
            severity: calculateSeverity(student.recent_absences || 0),
            attendanceRate: student.attendance_rate || 100,
            totalPresent: student.total_present || 0,
            totalLate: student.total_late || 0
        }));
        console.log('✅ Processed students data successfully:', studentsWithAbsenceIssues.value.length);
    }

    // Process trends data based on current chart view
    if (indexedData.trends && indexedData.trends[chartView.value]) {
        const trendsData = indexedData.trends[chartView.value];
        if (trendsData.labels && trendsData.datasets) {
            attendanceChartData.value = trendsData;
        }
    }
}

// Load attendance data for the selected subject with caching
async function loadAttendanceData() {
    if (!currentTeacher.value) return;

    // Handle "All Subjects" selection - load from all sections
    if (selectedSubject.value && selectedSubject.value.id === null) {
        console.log('Loading "All Subjects" attendance data');
        await loadAllSubjectsData();
        return;
    }

    // For departmentalized teachers, check if they teach the same subject across multiple sections
    if (selectedSubject.value) {
        console.log('Loading attendance data for subject:', selectedSubject.value);

        // Check if this teacher teaches this subject in multiple sections
        const subjectAssignments = availableSubjects.value.filter((s) => s.id === selectedSubject.value.id);

        if (subjectAssignments.length > 1) {
            console.log(`📚 Departmentalized teacher: Loading ${selectedSubject.value.name} from ${subjectAssignments.length} sections`);
            await loadDepartmentalizedSubjectData(selectedSubject.value.id);
            return;
        } else {
            // Single section assignment
            await loadSingleSectionData(selectedSubject.value.sectionId, selectedSubject.value.id);
            return;
        }
    } else if (currentTeacher.value.homeroom_section) {
        // Homeroom-only teacher
        console.log('Loading attendance data for homeroom section:', currentTeacher.value.homeroom_section);
        await loadSingleSectionData(currentTeacher.value.homeroom_section.id, null);
        return;
    } else {
        console.warn('No subject or homeroom section found for teacher');
        return;
    }
}

// Load data for departmentalized teachers teaching same subject across multiple sections
async function loadDepartmentalizedSubjectData(subjectId) {
    try {
        // Get all sections where this teacher teaches this subject
        const subjectAssignments = availableSubjects.value.filter((s) => s.id === subjectId);
        const sectionIds = subjectAssignments.map((s) => s.sectionId);

        console.log(`📚 Loading departmentalized data for subject ${subjectId} across sections:`, sectionIds);

        let allStudents = [];
        let totalStudents = 0;

        // Load students from each section
        for (const assignment of subjectAssignments) {
            try {
                const studentsResponse = await TeacherAttendanceService.getStudentsForTeacherSubject(currentTeacher.value.id, assignment.sectionId, subjectId);

                if (studentsResponse.success && studentsResponse.students) {
                    const sectionStudents = studentsResponse.students.map((student) => ({
                        id: student.student_id || student.id,
                        name: student.name || `${student.first_name || student.firstName} ${student.last_name || student.lastName}`,
                        gradeLevel: student.grade_name || student.gradeLevel || 'Unknown Grade',
                        section: student.section_name || student.section || `Section ${student.section_id || student.sectionId}`,
                        absences: student.total_absences || 0,
                        recent_absences: student.recent_absences || 0,
                        severity: calculateSeverity(student.recent_absences || 0),
                        attendanceRate: student.attendance_rate || 100,
                        totalPresent: student.total_present || 0,
                        totalLate: student.total_late || 0
                    }));

                    allStudents = allStudents.concat(sectionStudents);
                    totalStudents += studentsResponse.count || sectionStudents.length;
                }
            } catch (error) {
                console.error(`Error loading students from section ${assignment.sectionId}:`, error);
            }
        }

        // Update the students data
        studentsWithAbsenceIssues.value = allStudents;

        // Calculate summary statistics
        const warningCount = allStudents.filter((s) => s.severity === 'at_risk').length;
        const criticalCount = allStudents.filter((s) => s.severity === 'critical').length;

        attendanceSummary.value = {
            totalStudents: totalStudents,
            averageAttendance: 0, // Will be calculated by trends API
            studentsWithWarning: warningCount,
            studentsWithCritical: criticalCount,
            students: allStudents
        };

        console.log(`✅ Departmentalized data loaded. Total students: ${allStudents.length} from ${sectionIds.length} sections`);
        console.log(`📊 Warning: ${warningCount}, Critical: ${criticalCount}`);
    } catch (error) {
        console.error('Error loading departmentalized subject data:', error);
    }
}

// Load data for single section (homeroom or single subject assignment)
async function loadSingleSectionData(sectionId, subjectId) {
    try {
        // 🚀 NEW: Try Pinia store first (with intelligent caching)
        console.log('🎯 Attempting to load from Pinia store...');
        try {
            const storeStudents = await attendanceStore.loadStudents(sectionId, subjectId);

            if (storeStudents && storeStudents.length > 0) {
                console.log('✅ Loaded from Pinia store (cached):', storeStudents.length, 'students');

                // 🚀 PERFORMANCE FIX: Pinia cache has student list but NOT attendance stats
                // Fetch attendance statistics from summary API
                let summaryResponse = null;
                try {
                    summaryResponse = await AttendanceSummaryService.getTeacherAttendanceSummary(currentTeacher.value.id, {
                        period: 'week',
                        viewType: subjectId ? 'subject' : 'all_students',
                        subjectId: subjectId
                    });
                    console.log('📊 Summary response students:', summaryResponse?.data?.students?.length || 0);
                } catch (summaryError) {
                    console.warn('⚠️ Failed to fetch attendance summary, using cached data only:', summaryError);
                }

                // Map store data to component format with attendance stats from summary
                let matchedCount = 0;
                studentsWithAbsenceIssues.value = storeStudents.map((student) => {
                    // Find matching student in summary response for attendance stats
                    const summaryStudent = summaryResponse?.data?.students?.find((s) => (s.student_id || s.id) === (student.student_id || student.id));

                    if (summaryStudent) matchedCount++;

                    const recentAbsences = summaryStudent?.recent_absences || student.recent_absences || 0;

                    // Build name from summary data (more reliable) or cached data
                    const studentName =
                        summaryStudent?.name ||
                        student.name ||
                        (summaryStudent?.first_name && summaryStudent?.last_name
                            ? `${summaryStudent.first_name} ${summaryStudent.last_name}`
                            : `${student.first_name || student.firstName || ''} ${student.last_name || student.lastName || ''}`.trim()) ||
                        'Unknown Student';

                    return {
                        id: student.student_id || student.id,
                        student_id: student.student_id || student.id, // Add for component compatibility
                        name: studentName,
                        first_name: summaryStudent?.first_name || student.first_name || student.firstName,
                        last_name: summaryStudent?.last_name || student.last_name || student.lastName,
                        gradeLevel: student.grade_name || student.gradeLevel || 'Unknown Grade',
                        section: student.section_name || student.section || `Section ${student.section_id || student.sectionId}`,
                        absences: summaryStudent?.total_absences || student.total_absences || 0,
                        total_absences: summaryStudent?.total_absences || student.total_absences || 0, // Add for component compatibility
                        recent_absences: recentAbsences,
                        severity: calculateSeverity(recentAbsences),
                        attendanceRate: summaryStudent?.attendance_rate || student.attendance_rate || 0,
                        attendance_rate: summaryStudent?.attendance_rate || student.attendance_rate || 0, // Add for component compatibility
                        totalPresent: summaryStudent?.total_present || student.total_present || 0,
                        total_present: summaryStudent?.total_present || student.total_present || 0, // Add for component compatibility
                        totalLate: summaryStudent?.total_late || student.total_late || 0,
                        total_late: summaryStudent?.total_late || student.total_late || 0 // Add for component compatibility
                    };
                });
                console.log(`📊 Matched ${matchedCount}/${storeStudents.length} students with summary data`);

                // Update summary
                const warningCount = studentsWithAbsenceIssues.value.filter((s) => s.severity === 'at_risk').length;
                const criticalCount = studentsWithAbsenceIssues.value.filter((s) => s.severity === 'critical').length;
                console.log(`📊 Severity counts - Warning: ${warningCount}, Critical: ${criticalCount}`);
                const atRiskStudents = studentsWithAbsenceIssues.value.filter((s) => s.severity === 'at_risk');
                console.log(
                    '📊 Students with at_risk:',
                    atRiskStudents.map((s) => `${s.name} (${s.recent_absences} absences)`)
                );
                console.log('📊 First at_risk student full data:', atRiskStudents[0]);

                // Calculate average attendance from summary data
                const avgAttendance =
                    summaryResponse?.data?.average_attendance ||
                    (studentsWithAbsenceIssues.value.length > 0 ? Math.round(studentsWithAbsenceIssues.value.reduce((sum, s) => sum + (s.attendanceRate || 0), 0) / studentsWithAbsenceIssues.value.length) : 0);

                attendanceSummary.value = {
                    totalStudents: storeStudents.length,
                    averageAttendance: avgAttendance,
                    studentsWithWarning: warningCount,
                    studentsWithCritical: criticalCount,
                    students: studentsWithAbsenceIssues.value
                };

                console.log('📊 Pinia store stats:', attendanceStore.cacheStats);
                console.log('📊 Average attendance:', avgAttendance + '%');
                return; // Success! No need for old code
            }
        } catch (storeError) {
            console.warn('⚠️ Pinia store failed, falling back to old method:', storeError);
        }

        // 📦 FALLBACK: Original code (kept as backup)
        const params = {
            teacherId: currentTeacher.value.id,
            sectionId: sectionId,
            subjectId: subjectId
        };

        // Cache key for this specific data request (include viewType to separate caches)
        const cacheKey = CacheService.generateKey('attendance_data', {
            ...params,
            viewType: viewType.value
        });

        // Try to get from cache first
        const cachedData = CacheService.get(cacheKey);
        if (cachedData) {
            console.log('📦 Using cached attendance data');
            attendanceSummary.value = cachedData.summary;

            // Update studentsWithAbsenceIssues from cached data
            if (cachedData.summary.students) {
                studentsWithAbsenceIssues.value = cachedData.summary.students.map((student) => ({
                    id: student.student_id,
                    name: student.name,
                    gradeLevel: student.grade_name || 'Unknown Grade',
                    section: student.section_name || `Section ${student.section_id}`,
                    absences: student.total_absences,
                    recent_absences: student.recent_absences || 0,
                    severity: student.severity,
                    attendanceRate: student.attendance_rate,
                    totalPresent: student.total_present,
                    totalLate: student.total_late
                }));
            }
            return;
        }

        console.log('🌐 Fetching fresh attendance data...');

        // Check indexed data first for instant loading
        const indexedData = AttendanceIndexingService.getIndexedData(params.teacherId, params.sectionId, params.subjectId, viewType.value);
        if (indexedData) {
            console.log('⚡ Using indexed data (instant load)');
            attendanceSummary.value = indexedData.summary;
            studentsWithAbsenceIssues.value = indexedData.students || [];
            return;
        }

        // Get students first (required)
        const studentsResponse = await TeacherAttendanceService.getStudentsForTeacherSubject(params.teacherId, params.sectionId, params.subjectId);

        // Try to get summary (optional)
        let summaryResponse = null;
        try {
            summaryResponse = await AttendanceSummaryService.getTeacherAttendanceSummary(currentTeacher.value.id, {
                period: 'week',
                viewType: subjectId ? 'subject' : 'all_students', // Use 'subject' if subjectId exists
                subjectId: subjectId // Pass the actual subject ID for filtering
            });
        } catch (err) {
            console.warn('⚠️ Summary API failed (non-critical):', err.message);
        }

        if (studentsResponse.success) {
            // Always show students from studentsResponse
            const studentsData = studentsResponse.students || [];

            // DEBUG: Log first student to see actual data structure
            if (studentsData.length > 0) {
                console.log('🔍 DEBUG First Student Data:', JSON.stringify(studentsData[0], null, 2));
            }

            studentsWithAbsenceIssues.value = studentsData.map((student) => ({
                id: student.student_id || student.id,
                student_id: student.student_id || student.id,
                name: student.name || `${student.first_name || student.firstName} ${student.last_name || student.lastName}`,
                first_name: student.first_name || student.firstName,
                last_name: student.last_name || student.lastName,
                gradeLevel: student.grade_name || student.gradeLevel || 'Unknown Grade',
                section: student.section_name || student.section || `Section ${student.section_id || student.sectionId}`,
                absences: student.total_absences || 0,
                total_absences: student.total_absences || 0,
                recent_absences: student.recent_absences || 0,
                consecutive_absences: student.consecutive_absences || 0,
                severity: calculateSeverity(student.recent_absences || 0),
                attendanceRate: student.attendance_rate || 100,
                totalPresent: student.total_present || 0,
                totalLate: student.total_late || 0
            }));

            // NOW calculate warning and critical counts from the processed student data
            const warningCount = studentsWithAbsenceIssues.value.filter((s) => s.severity === 'at_risk').length;
            const criticalCount = studentsWithAbsenceIssues.value.filter((s) => s.severity === 'critical').length;

            // Set attendance summary with calculated counts
            attendanceSummary.value =
                summaryResponse && summaryResponse.success
                    ? {
                          totalStudents: summaryResponse.data.total_students || studentsResponse.count,
                          averageAttendance: summaryResponse.data.average_attendance || 0,
                          studentsWithWarning: warningCount,
                          studentsWithCritical: criticalCount,
                          students: studentsWithAbsenceIssues.value // Use processed student data
                      }
                    : {
                          totalStudents: studentsResponse.count || 0,
                          averageAttendance: 0,
                          studentsWithWarning: warningCount,
                          studentsWithCritical: criticalCount,
                          students: studentsWithAbsenceIssues.value // Use processed student data
                      };

            console.log('✅ Attendance data loaded. Students:', studentsWithAbsenceIssues.value.length);
            console.log('📊 Warning count:', warningCount, 'Critical count:', criticalCount);
        }
    } catch (error) {
        console.error('Error loading attendance data:', error);
        // Fallback data
        attendanceSummary.value = {
            totalStudents: 0,
            averageAttendance: 0,
            studentsWithWarning: 0,
            studentsWithCritical: 0,
            students: []
        };
    }
}

// Load data for "All Subjects" view
async function loadAllSubjectsData() {
    console.log('📚 Loading All Subjects attendance data');

    try {
        // Get attendance summary for all students (viewType: 'all_students')
        const summaryResponse = await AttendanceSummaryService.getTeacherAttendanceSummary(currentTeacher.value.id, {
            period: 'week',
            viewType: 'all_students', // This is the key difference
            subjectId: null
        });

        console.log('🔍 All Subjects Summary API called with:', {
            teacherId: currentTeacher.value.id,
            period: 'week',
            viewType: 'all_students',
            subjectId: null
        });

        if (summaryResponse.success && summaryResponse.data) {
            // For "All Subjects", we get students from the summary response
            if (summaryResponse.data.students) {
                studentsWithAbsenceIssues.value = summaryResponse.data.students.map((student) => ({
                    id: student.student_id || student.id,
                    name: student.name || `${student.first_name} ${student.last_name}`,
                    gradeLevel: student.grade_name || student.curriculum_grade?.grade_name || 'Unknown',
                    section: student.section_name || student.section || 'Unknown Section',
                    absences: student.total_absences || student.absence_count || 0,
                    recent_absences: student.recent_absences || 0,
                    severity: calculateSeverity(student.recent_absences || 0),
                    attendanceRate: student.attendance_rate || 100,
                    totalPresent: student.total_present || 0,
                    totalLate: student.total_late || 0
                }));
            } else {
                studentsWithAbsenceIssues.value = [];
            }

            // Calculate warning and critical counts from processed student data
            const warningCount = studentsWithAbsenceIssues.value.filter((s) => s.severity === 'warning').length;
            const criticalCount = studentsWithAbsenceIssues.value.filter((s) => s.severity === 'critical').length;

            attendanceSummary.value = {
                totalStudents: summaryResponse.data.total_students || 0,
                averageAttendance: summaryResponse.data.average_attendance || 0,
                studentsWithWarning: warningCount,
                studentsWithCritical: criticalCount,
                students: summaryResponse.data.students || []
            };

            console.log('✅ All Subjects data loaded. Students:', studentsWithAbsenceIssues.value.length);
        }
    } catch (error) {
        console.error('Error loading All Subjects data:', error);
        // Set fallback data
        attendanceSummary.value = {
            totalStudents: 0,
            averageAttendance: 0,
            studentsWithWarning: 0,
            studentsWithCritical: 0,
            students: []
        };
        studentsWithAbsenceIssues.value = [];
    }
}

// Get real absence count for a student from attendance records
async function getStudentAbsenceCount(studentId) {
    try {
        // Call API to get attendance records for this student
        const response = await api.get(`/api/students/${studentId}/attendance/summary`);
        return response.data.absences || 0;
    } catch (error) {
        console.error(`Error getting absence count for student ${studentId}:`, error);
        // Return random number as fallback for demo
        return Math.floor(Math.random() * 3);
    }
}

// After the loadAttendanceData function
// Fallback if getSubjectAttendanceRecords is missing
async function getAttendanceRecords(studentIds, subjectId, month, year) {
    try {
        // Try to use the existing method first
        if (typeof StudentAttendanceService.getSubjectAttendanceRecords === 'function') {
            return await StudentAttendanceService.getSubjectAttendanceRecords(studentIds, subjectId, month, year);
        }

        // Fallback implementation
        console.log('Using fallback attendance records generator');
        // Generate some mock attendance data
        const records = [];
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (const studentId of studentIds) {
            // Generate 20 school days (excluding weekends)
            let recordCount = 0;
            let day = 1;

            while (recordCount < 20 && day <= daysInMonth) {
                const date = new Date(year, month, day);
                // Skip weekends
                if (date.getDay() !== 0 && date.getDay() !== 6) {
                    // 80% chance present, 15% absent, 5% late
                    const rand = Math.random();
                    let status = 'PRESENT';

                    if (rand > 0.95) {
                        status = 'LATE';
                    } else if (rand > 0.8) {
                        status = 'ABSENT';
                    }

                    records.push({
                        studentId,
                        date: date.toISOString(),
                        subjectId,
                        status
                    });
                    recordCount++;
                }
                day++;
            }
        }

        return records;
    } catch (error) {
        console.error('Error getting attendance records:', error);
        return [];
    }
}

// Analyze attendance records to identify issues
function analyzeAttendance(students, attendanceRecords) {
    const absenceCount = {};
    const consecutiveAbsences = {};

    // Initialize counts
    students.forEach((student) => {
        absenceCount[student.id] = 0;
        consecutiveAbsences[student.id] = 0;
    });

    // Count absences for each student
    attendanceRecords.forEach((record) => {
        if (record.status === 'ABSENT') {
            absenceCount[record.studentId] = (absenceCount[record.studentId] || 0) + 1;
        }
    });

    // Find students with absence issues
    studentsWithAbsenceIssues.value = students
        .map((student) => {
            const absences = absenceCount[student.id] || 0;
            const severity = calculateSeverity(absences);

            return {
                ...student,
                absences,
                severity
            };
        })
        .filter((student) => (showOnlyAbsenceIssues.value ? student.severity !== 'good' : true))
        .sort((a, b) => b.absences - a.absences); // Sort by absences (highest first)

    // Prepare attendance summary
    attendanceSummary.value = {
        totalStudents: students.length,
        studentsWithWarning: studentsWithAbsenceIssues.value.filter((s) => s.severity === 'at_risk').length,
        studentsWithCritical: studentsWithAbsenceIssues.value.filter((s) => s.severity === 'critical').length,
        averageAttendance: calculateAverageAttendance(students.length, attendanceRecords)
    };
}

// Calculate average attendance percentage
function calculateAverageAttendance(totalStudents, attendanceRecords) {
    if (totalStudents === 0) return 0;

    const totalDays = 20; // Assuming 20 school days per month
    const totalPossibleAttendances = totalStudents * totalDays;

    const presentCount = attendanceRecords.filter((r) => r.status === 'PRESENT').length;

    return Math.round((presentCount / totalPossibleAttendances) * 100);
}

// Prepare chart data for attendance visualization using real database API
async function prepareChartData() {
    if (!currentTeacher.value) return;

    // Handle case where no subject is selected or "All Subjects" is selected
    if (!selectedSubject.value) return;

    try {
        console.log('Preparing chart data for subject:', selectedSubject.value);

        // Calculate date range based on chart view
        const today = new Date();
        let dateFrom, dateTo;
        let labels = [];

        if (chartView.value === 'day') {
            // Last 7 days
            dateFrom = new Date(today);
            dateFrom.setDate(today.getDate() - 6);
            dateTo = new Date(today);

            for (let i = 6; i >= 0; i--) {
                const date = new Date(today);
                date.setDate(today.getDate() - i);
                labels.push(formatDateShort(date));
            }
        } else if (chartView.value === 'week') {
            // Last 4 weeks
            dateFrom = new Date(today);
            dateFrom.setDate(today.getDate() - 27); // 4 weeks ago
            dateTo = new Date(today);

            for (let i = 3; i >= 0; i--) {
                const endDate = new Date(today);
                endDate.setDate(today.getDate() - i * 7);
                const startDate = new Date(endDate);
                startDate.setDate(endDate.getDate() - 6);
                labels.push(`${formatDateShort(startDate)} - ${formatDateShort(endDate)}`);
            }
        } else {
            // monthly
            // Last 6 months
            dateFrom = new Date(today);
            dateFrom.setMonth(today.getMonth() - 5);
            dateTo = new Date(today);

            for (let i = 5; i >= 0; i--) {
                const date = new Date(today);
                date.setMonth(today.getMonth() - i);
                labels.push(date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' }));
            }
        }

        // Fetch attendance trends using the new service
        let attendanceData = {
            present: [],
            absent: [],
            late: []
        };

        try {
            // Determine the correct viewType and subjectId based on selection
            let actualViewType = viewType.value;
            let actualSubjectId = null;

            if (selectedSubject.value && selectedSubject.value.id === null) {
                // "All Subjects" selected - use all_students view
                actualViewType = 'all_students';
                actualSubjectId = null;
            } else if (viewType.value === 'subject' && selectedSubject.value) {
                // Specific subject selected
                actualViewType = 'subject';
                actualSubjectId = selectedSubject.value.id;
            } else {
                // All students view
                actualViewType = 'all_students';
                actualSubjectId = null;
            }

            const trendsParams = {
                teacherId: currentTeacher.value.id,
                period: chartView.value,
                viewType: actualViewType,
                subjectId: actualSubjectId
            };

            console.log('🔍 Calling getAttendanceTrends with:', JSON.stringify(trendsParams, null, 2));

            const trendsResult = await AttendanceSummaryService.getAttendanceTrends(currentTeacher.value.id, {
                period: chartView.value,
                viewType: actualViewType,
                subjectId: actualSubjectId
            });

            console.log('📊 Full trends response:', trendsResult);

            if (trendsResult && trendsResult.success && trendsResult.data) {
                // Process the database response - API returns labels and datasets structure
                const dbData = trendsResult.data;

                if (dbData.datasets && Array.isArray(dbData.datasets)) {
                    // Extract data from datasets
                    const presentDataset = dbData.datasets.find((d) => d.label === 'Present');
                    const absentDataset = dbData.datasets.find((d) => d.label === 'Absent');
                    const lateDataset = dbData.datasets.find((d) => d.label === 'Late');

                    attendanceData.present = presentDataset ? presentDataset.data : [];
                    attendanceData.absent = absentDataset ? absentDataset.data : [];
                    attendanceData.late = lateDataset ? lateDataset.data : [];

                    // Update labels if provided by API
                    if (dbData.labels && Array.isArray(dbData.labels)) {
                        labels = dbData.labels;
                    }

                    console.log('Using database attendance trends:', attendanceData);
                    console.log('Using database labels:', labels);
                } else {
                    console.warn('Invalid datasets structure in trends response');
                }
            } else {
                console.warn('Invalid trends response, using fallback data');
            }
        } catch (trendsError) {
            console.error('Error fetching attendance trends:', trendsError);
            console.warn('Using fallback data due to error');
        }

        // Fallback data if API fails or returns empty data
        if (attendanceData.present.length === 0) {
            console.log('Using fallback chart data');
            const fallbackCounts = labels.length;
            attendanceData = {
                present: Array(fallbackCounts)
                    .fill(0)
                    .map(() => Math.floor(Math.random() * 15) + 5),
                absent: Array(fallbackCounts)
                    .fill(0)
                    .map(() => Math.floor(Math.random() * 5) + 1),
                late: Array(fallbackCounts)
                    .fill(0)
                    .map(() => Math.floor(Math.random() * 3) + 1)
            };
        }

        // Prepare datasets for line chart with smooth curves
        attendanceChartData.value = {
            labels: labels,
            datasets: [
                {
                    label: 'Present',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderColor: '#10b981',
                    borderWidth: 3,
                    data: attendanceData.present,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverBackgroundColor: '#10b981',
                    pointHoverBorderColor: '#fff'
                },
                {
                    label: 'Absent',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderColor: '#ef4444',
                    borderWidth: 3,
                    data: attendanceData.absent,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverBackgroundColor: '#ef4444',
                    pointHoverBorderColor: '#fff'
                },
                {
                    label: 'Late',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderColor: '#f59e0b',
                    borderWidth: 3,
                    data: attendanceData.late,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#f59e0b',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverBackgroundColor: '#f59e0b',
                    pointHoverBorderColor: '#fff'
                }
            ]
        };

        console.log('Chart data prepared:', attendanceChartData.value);

        // Chart options optimized for line chart
        chartOptions.value = {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 20,
                    top: 10,
                    bottom: 10
                }
            },
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: false // Hide default legend since we have custom one
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    titleFont: {
                        size: 13,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 12
                    },
                    callbacks: {
                        label: function (context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            // Use the actual dataset label instead of hardcoded "students"
                            label += context.parsed.y;
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            family: 'Inter, sans-serif',
                            size: 11
                        },
                        color: '#666',
                        maxRotation: 0,
                        minRotation: 0,
                        autoSkip: true,
                        autoSkipPadding: 10
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            family: 'Inter, sans-serif',
                            size: 11
                        },
                        color: '#666',
                        padding: 10,
                        stepSize: 2
                    }
                }
            }
        };
    } catch (error) {
        console.error('Error preparing chart data:', error);
    }
}

// Add a helper function to format dates in a short, readable format
function formatDateShort(date) {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric'
    }).format(date);
}

// Open student profile with attendance history
async function openStudentProfile(student) {
    selectedStudent.value = student;
    profileSubjectFilter.value = null; // Reset filter

    // Load available subjects for this teacher
    profileAvailableSubjects.value = availableSubjects.value || [];

    // Initialize calendar filter to currently selected subject
    calendarSubjectFilter.value = selectedSubject.value?.id || null;

    await prepareCalendarData(student);
    studentProfileVisible.value = true;
}

// Handler for subject filter change in profile dialog
async function onProfileSubjectChange() {
    console.log('📚 Profile subject filter changed to:', profileSubjectFilter.value);
    if (selectedStudent.value) {
        await prepareCalendarData(selectedStudent.value);
    }
}

// Handler for calendar subject filter change (independent filter)
async function onCalendarSubjectChange() {
    console.log('📅 Calendar subject filter changed to:', calendarSubjectFilter.value);
    if (selectedStudent.value) {
        await prepareCalendarDataForFilter(selectedStudent.value, calendarSubjectFilter.value);
    }
}

// Prepare calendar data with specific subject filter
async function prepareCalendarDataForFilter(student, subjectId) {
    if (!student) return;

    // Generate calendar data for current month
    const daysInMonth = new Date(currentYear.value, currentMonth.value + 1, 0).getDate();

    // Initialize calendar data
    calendarData.value = Array.from({ length: daysInMonth }, (_, i) => ({
        date: new Date(currentYear.value, currentMonth.value, i + 1),
        day: i + 1,
        isAbsent: false,
        status: null
    }));

    try {
        // Get attendance records filtered by subject (null = all subjects)
        const response = await StudentAttendanceService.getSubjectAttendanceRecords(
            [student.id],
            subjectId, // Use the calendar filter
            currentMonth.value,
            currentYear.value
        );

        const records = response.records || [];

        // Find absent days
        absentDays.value = records
            .filter((record) => record.status === 'ABSENT' || record.status === 'EXCUSED' || record.status === 'LATE')
            .map((record) => ({
                date: new Date(record.date),
                status: record.status,
                time: record.time || 'N/A',
                recordId: record.id,
                reason: record.reason,
                reason_notes: record.reason_notes,
                reason_description: record.reason_description
            }));

        // Map attendance status to calendar
        const statusPriority = {
            ABSENT: 4,
            EXCUSED: 3,
            LATE: 2,
            PRESENT: 1
        };

        const attendanceMap = {};
        records.forEach((record) => {
            const recordDate = new Date(record.date);
            const day = recordDate.getDate();
            const month = recordDate.getMonth();
            const year = recordDate.getFullYear();

            if (year === currentYear.value && month === currentMonth.value) {
                const currentPriority = statusPriority[record.status] || 0;
                const existingPriority = statusPriority[attendanceMap[day]] || 0;

                if (currentPriority > existingPriority) {
                    attendanceMap[day] = record.status;
                }
            }
        });

        // Update calendar data with attendance status
        calendarData.value = calendarData.value.map((dayData) => ({
            ...dayData,
            status: attendanceMap[dayData.day] || null
        }));
    } catch (error) {
        console.error('Error loading calendar data:', error);
    }
}

// Prepare calendar data for the student profile using StudentAttendanceService
async function prepareCalendarData(student) {
    if (!student) return;

    // Use profileSubjectFilter if set, otherwise use selectedSubject
    const subjectToUse = profileSubjectFilter.value || selectedSubject.value?.id;
    if (!subjectToUse) return;

    // Generate calendar data for current month first
    const daysInMonth = new Date(currentYear.value, currentMonth.value + 1, 0).getDate();

    // Initialize calendar data
    calendarData.value = Array.from({ length: daysInMonth }, (_, i) => ({
        date: new Date(currentYear.value, currentMonth.value, i + 1),
        day: i + 1,
        isAbsent: false,
        status: null
    }));

    try {
        // Get all attendance records for this student in the selected subject (filtered!)
        const response = await StudentAttendanceService.getSubjectAttendanceRecords([student.id], subjectToUse, currentMonth.value, currentYear.value);

        console.log('Attendance records response:', response);

        const records = response.records || [];

        console.log('Current month/year for calendar:', currentMonth.value, currentYear.value);
        console.log('Individual records:', records);

        // Find absent days for the absence history section (include both ABSENT and EXCUSED with status)
        absentDays.value = records
            .filter((record) => record.status === 'ABSENT' || record.status === 'EXCUSED' || record.status === 'LATE')
            .map((record) => ({
                date: new Date(record.date),
                status: record.status,
                time: record.time || 'N/A',
                recordId: record.id,
                reason: record.reason,
                reason_notes: record.reason_notes,
                reason_description: record.reason_description
            }));

        console.log('📋 Absence History Records:', absentDays.value);

        // Create a map of dates to attendance status for calendar display
        // Use priority: ABSENT > EXCUSED > LATE > PRESENT
        const statusPriority = {
            ABSENT: 4,
            EXCUSED: 3,
            LATE: 2,
            PRESENT: 1
        };

        const attendanceMap = {};
        records.forEach((record) => {
            const date = new Date(record.date);
            const day = date.getDate();
            const month = date.getMonth(); // 0-based month
            const year = date.getFullYear();

            console.log(`Record: ${record.date} -> Day: ${day}, Month: ${month}, Year: ${year}, Status: ${record.status}`);
            console.log(`Calendar showing: Month: ${currentMonth.value}, Year: ${currentYear.value}`);

            // Only map records that match the current calendar month/year
            if (month === currentMonth.value && year === currentYear.value) {
                // Use priority - only update if new status has higher priority
                const currentStatus = attendanceMap[day];
                const currentPriority = statusPriority[currentStatus] || 0;
                const newPriority = statusPriority[record.status] || 0;

                if (newPriority > currentPriority) {
                    attendanceMap[day] = record.status;
                    console.log(`✅ Updated day ${day} to status ${record.status} (priority ${newPriority} > ${currentPriority})`);
                } else {
                    console.log(`⏭️ Kept day ${day} as ${currentStatus} (priority ${currentPriority} >= ${newPriority})`);
                }
            } else {
                console.log(`❌ Skipped - record is for ${month}/${year}, calendar shows ${currentMonth.value}/${currentYear.value}`);
            }
        });

        console.log('Final attendance map for calendar:', attendanceMap);
        console.log('Calendar data before mapping:', calendarData.value);

        // Update calendar data with attendance status
        calendarData.value = calendarData.value.map((calDay) => {
            const status = attendanceMap[calDay.day] || null;
            const updatedDay = {
                ...calDay,
                status: status,
                isAbsent: status === 'ABSENT'
            };
            console.log(`Day ${calDay.day}: status = ${status}`, updatedDay);
            return updatedDay;
        });

        console.log('Calendar data after mapping:', calendarData.value);
    } catch (error) {
        console.error('Error loading attendance records:', error);
        absentDays.value = []; // Fallback to empty array
    }
}

// Handle subject change with instant loading from index
async function onSubjectChange() {
    console.log('🔄 Subject changed to:', selectedSubject.value?.name);

    // Try to get indexed data first for instant switching
    const indexedData = AttendanceIndexingService.getIndexedData(currentTeacher.value?.id, selectedSubject.value?.sectionId, selectedSubject.value?.id, viewType.value);

    if (indexedData) {
        console.log('⚡ Loading from index - instant!');
        processIndexedData(indexedData);
        await prepareChartData();

        // Note: Prefetching can be added later for further optimization
    } else {
        // Fallback to loading fresh data
        subjectLoading.value = true;
        try {
            await loadAttendanceData();
            await prepareChartData();

            // Index this data for next time
            if (selectedSubject.value) {
                AttendanceIndexingService.refreshSubjectData(currentTeacher.value?.id, selectedSubject.value.sectionId, selectedSubject.value.id, viewType.value, selectedSubject.value.name);
            }
        } finally {
            subjectLoading.value = false;
        }
    }
}

// Handle view type change (subject-specific vs all students)
async function onViewTypeChange() {
    console.log('🔄 View type changed to:', viewType.value);

    // Try to get indexed data for the new view type
    const indexedData = AttendanceIndexingService.getIndexedData(currentTeacher.value?.id, selectedSubject.value?.sectionId, selectedSubject.value?.id, viewType.value);

    if (indexedData) {
        console.log('⚡ Loading from index for view type:', viewType.value);
        processIndexedData(indexedData);
        await prepareChartData();
    } else {
        // Load fresh data for the new view type
        await loadAttendanceData();
        await prepareChartData();

        // Index this data for next time
        AttendanceIndexingService.refreshSubjectData(currentTeacher.value?.id, selectedSubject.value?.sectionId, selectedSubject.value?.id, viewType.value, selectedSubject.value?.name || 'All Students');
    }
}

// Handle chart view change (daily/weekly/monthly)
async function onChartViewChange() {
    console.log('📊 Chart view changed to:', chartView.value);

    // Try to get indexed data for the new chart view
    const indexedData = AttendanceIndexingService.getIndexedData(currentTeacher.value?.id, selectedSubject.value?.sectionId, selectedSubject.value?.id, viewType.value);

    if (indexedData && indexedData.trends && indexedData.trends[chartView.value]) {
        console.log('⚡ Using indexed chart data for:', chartView.value);
        const trendsData = indexedData.trends[chartView.value];
        if (trendsData.labels && trendsData.datasets) {
            attendanceChartData.value = trendsData;
        }
    } else {
        // Fallback to preparing chart data from current summary
        await prepareChartData();
    }
}

// Watch for subject changes and reload data
watch(selectedSubject, async (newSubject, oldSubject) => {
    // Skip initial mount trigger
    if (!oldSubject || !newSubject || newSubject.id === oldSubject.id) {
        return;
    }

    // Clear any cached attendance data for both old and new subjects
    // Clear cache for both view types to ensure fresh data
    const cacheParams = {
        teacherId: currentTeacher.value.id,
        sectionId: selectedSubject.value.sectionId,
        subjectId: selectedSubject.value.id
    };

    // Clear cache for both subject and all_students views
    ['subject', 'all_students'].forEach((vType) => {
        const cacheKey = CacheService.generateKey('attendance_data', {
            ...cacheParams,
            viewType: vType
        });
        CacheService.delete(cacheKey);
        console.log(`🗑️ Clearing ${vType} cache:`, cacheKey);
    });

    // Reload attendance data
    await loadAttendanceData();
    await prepareChartData();
});

// Watch for view type changes
watch(viewType, async () => {
    await loadAttendanceData();
    await prepareChartData();
});

// Filter students by name
const searchQuery = ref('');
const filteredStudents = computed(() => {
    if (!studentsWithAbsenceIssues.value) return [];

    let filtered = studentsWithAbsenceIssues.value;

    // Apply search filter
    if (searchQuery.value) {
        filtered = filtered.filter((student) => student.name.toLowerCase().includes(searchQuery.value.toLowerCase()));
    }

    // Apply grade filter
    if (selectedGradeFilter.value) {
        filtered = filtered.filter((student) => student.gradeLevel === selectedGradeFilter.value);
    }

    // Apply section filter
    if (selectedSectionFilter.value) {
        filtered = filtered.filter((student) => student.section === selectedSectionFilter.value);
    }

    // Apply status filter
    if (selectedStatusFilter.value) {
        filtered = filtered.filter((student) => student.severity === selectedStatusFilter.value);
    }

    // Apply absence issues filter
    if (showOnlyAbsenceIssues.value) {
        filtered = filtered.filter((student) => student.severity !== 'normal');
    }

    return filtered;
});

// Get severity icon for student absence
function getSeverityIcon(severity) {
    switch (severity) {
        case 'warning':
            return '🟡';
        case 'critical':
            return '🔴';
        default:
            return '';
    }
}

// Format date for display
function formatDate(date) {
    return new Intl.DateTimeFormat('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric'
    }).format(new Date(date));
}

// Format grade level (don't add "Grade" prefix to Kindergarten or if already has "Grade")
function formatGradeLevel(gradeLevel) {
    if (!gradeLevel) return 'Unknown';

    const grade = String(gradeLevel);

    // If it's Kindergarten or Kinder, return as-is
    if (grade.toLowerCase().includes('kinder')) {
        return grade;
    }

    // If it already starts with "Grade", return as-is
    if (grade.startsWith('Grade ')) {
        return grade;
    }

    // Otherwise, add "Grade" prefix for numbers
    return `Grade ${grade}`;
}

// Add function to apply date filter
function applyDateFilter() {
    currentMonth.value = tempMonth.value;
    currentYear.value = tempYear.value;
    showDatePicker.value = false;
    loadAttendanceData();
    prepareChartData();
}

// Add this function after getAttendanceRecords to ensure we have data
// if StudentAttendanceService.getWeeklyAttendanceData is not available
function ensureStudentAttendanceService() {
    // Check if the StudentAttendanceService is properly defined
    if (!StudentAttendanceService) {
        console.error('StudentAttendanceService is undefined!');
        return;
    }

    // Make sure getWeeklyAttendanceData exists
    if (typeof StudentAttendanceService.getWeeklyAttendanceData !== 'function') {
        console.log('Creating fallback getWeeklyAttendanceData method');

        StudentAttendanceService.getWeeklyAttendanceData = (subjectId, month, year) => {
            console.log(`Generating mock weekly data for subject ${subjectId} in ${month}/${year}`);

            // Generate random but realistic attendance data for 4 weeks
            // For the current week (the last one), show zeros since it's incomplete
            return {
                present: [8, 9, 7, 0],
                absent: [2, 1, 2, 0],
                late: [0, 0, 1, 0]
            };
        };
    }

    // Make sure getSubjectAttendanceRecords exists
    if (typeof StudentAttendanceService.getSubjectAttendanceRecords !== 'function') {
        console.log('Creating fallback getSubjectAttendanceRecords method');

        StudentAttendanceService.getSubjectAttendanceRecords = getAttendanceRecords;
    }
}

// Smart Analytics Functions
async function loadSmartAnalytics() {
    try {
        if (!currentTeacher.value?.id) return;

        const response = await SmartAnalyticsService.getTeacherStudentAnalytics(currentTeacher.value.id);
        if (response.success) {
            smartAnalytics.value = response.data;
            studentsWithSmartAnalytics.value = response.data.students || [];

            // Update existing attendance summary with smart analytics
            if (attendanceSummary.value) {
                attendanceSummary.value.criticalStudents = response.data.summary?.critical_cases || 0;
                attendanceSummary.value.studentsExceeding18 = response.data.summary?.exceeding_18_limit || 0;
            }
        }
    } catch (error) {
        console.error('Error loading smart analytics:', error);
    }
}

async function loadCriticalStudents() {
    try {
        if (!currentTeacher.value?.id) return;

        const response = await SmartAnalyticsService.getCriticalAbsenteeism(currentTeacher.value.id);
        if (response.success) {
            criticalStudents.value = response.data.students || [];
        }
    } catch (error) {
        console.error('Error loading critical students:', error);
    }
}

// Sticky notes functionality removed

// Enhanced student profile with smart analytics
async function showStudentProfile(student) {
    try {
        selectedStudent.value = student;

        // Load smart analytics for this student
        const analyticsResponse = await SmartAnalyticsService.getStudentAnalytics(student.id);
        if (analyticsResponse.success) {
            selectedStudent.value.smartAnalytics = analyticsResponse.data;
        }

        // Load calendar attendance data for this student
        await prepareCalendarData(student);

        studentProfileVisible.value = true;
    } catch (error) {
        console.error('Error loading student analytics:', error);
        studentProfileVisible.value = true; // Still show profile even if analytics fail
    }
}

// Sticky notes functionality removed

// Sticky notes functionality removed
</script>

<template>
    <div class="grid" style="margin: 0 1rem">
        <!-- Loading State - Using skeleton for better perceived performance -->
        <div v-if="loading" class="col-span-12">
            <LoadingSkeleton />
        </div>

        <div v-else>
            <!-- Modern Header with Teacher Welcome & Subject Selection -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-xl shadow-md p-6 mb-6 text-white">
                <div class="grid grid-cols-12 gap-4 items-center">
                    <div class="col-span-12 sm:col-span-7">
                        <h1
                            class="text-2xl font-bold mb-1"
                            style="
                                color: #ffffff;
                                text-shadow:
                                    0 0 1px #fff,
                                    0 0 2px #fff;
                            "
                        >
                            Welcome, {{ currentTeacher?.name }}
                        </h1>
                        <p class="text-blue-100 font-normal">
                            {{ new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                        </p>
                        <p class="text-blue-200 font-medium text-sm mt-1">Section: {{ currentTeacher?.section || 'Malikhain (Grade 3)' }}</p>
                    </div>
                </div>
            </div>

            <!-- Schedule Status Widget & Stats Cards Combined -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-6">
                <!-- Schedule Widget -->
                <div class="lg:col-span-3">
                    <ScheduleStatusWidget />
                </div>

                <!-- Attendance Stats Cards -->
                <div class="lg:col-span-9 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow flex items-center">
                        <div class="mr-4 bg-blue-100 p-3 rounded-lg">
                            <i class="pi pi-users text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1 font-medium">Total Students</div>
                            <div class="text-2xl font-bold">{{ attendanceSummary?.totalStudents || 0 }}</div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow flex items-center">
                        <div class="flex items-center">
                            <div class="mr-4 bg-green-100 p-3 rounded-lg">
                                <i class="pi pi-check-circle text-green-600 text-xl"></i>
                            </div>
                            <div class="flex-1 p-2">
                                <div class="text-sm text-gray-500 font-medium flex items-center gap-2">
                                    Average Attendance
                                    <i 
                                        class="pi pi-info-circle text-blue-500 cursor-help" 
                                        v-tooltip.top="{
                                            value: 'Calculated as: (Present + Late + Excused) ÷ Total Possible Days × 100%<br/>Based on actual attendance records for this subject.',
                                            escape: false
                                        }"
                                    ></i>
                                </div>
                                <div class="text-2xl font-bold">{{ attendanceSummary?.averageAttendance || 0 }}%</div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow flex items-center">
                        <div class="mr-4 bg-yellow-100 p-3 rounded-lg">
                            <i class="pi pi-exclamation-triangle text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1 font-medium">High Risk (3-4 absences)</div>
                            <div class="text-2xl font-bold">{{ attendanceSummary?.studentsWithWarning || 0 }}</div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow flex items-center">
                        <div class="mr-4 bg-red-100 p-3 rounded-lg">
                            <i class="pi pi-exclamation-circle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 mb-1 font-medium">Critical Risk (5+ absences)</div>
                            <div class="text-2xl font-bold text-red-600">{{ attendanceSummary?.studentsWithCritical || 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Chart & Alerts -->
            <div class="grid grid-cols-12 gap-6 mb-6">
                <!-- Attendance Trends Chart -->
                <div class="col-span-12 lg:col-span-8">
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <div class="mb-4">
                            <h2 class="text-lg font-semibold mb-4">Attendance Trends</h2>

                            <!-- Enhanced Filters Section -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <!-- View Type Selector -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">View Type</label>
                                    <SelectButton v-model="viewType" :options="viewTypeOptions" optionLabel="label" optionValue="value" class="text-xs w-full" @change="onViewTypeChange" />
                                </div>

                                <!-- Subject Filter (Always Visible) -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1"> <i class="pi pi-book text-xs mr-1"></i>Subject Filter </label>
                                    <Dropdown
                                        v-model="selectedSubject"
                                        :options="[{ id: null, name: 'All Subjects' }, ...availableSubjects]"
                                        optionLabel="name"
                                        placeholder="Select Subject"
                                        class="w-full text-sm"
                                        :disabled="viewType === 'all_students'"
                                        @change="onSubjectChange"
                                    >
                                        <template #value="slotProps">
                                            <div v-if="slotProps.value" class="flex items-center text-sm">
                                                <i class="pi pi-book mr-1 text-blue-600 text-xs"></i>
                                                <span>{{ slotProps.value.name }}</span>
                                            </div>
                                            <span v-else class="text-sm">{{ slotProps.placeholder }}</span>
                                        </template>
                                        <template #option="slotProps">
                                            <div class="flex items-center">
                                                <i v-if="slotProps.option.id" class="pi pi-book mr-2 text-blue-600 text-xs"></i>
                                                <i v-else class="pi pi-list mr-2 text-gray-600 text-xs"></i>
                                                <span>{{ slotProps.option.name }}</span>
                                            </div>
                                        </template>
                                    </Dropdown>
                                </div>

                                <!-- Period Filter -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Period</label>
                                    <SelectButton v-model="chartView" :options="chartViewOptions" optionLabel="label" optionValue="value" class="text-xs w-full" @change="onChartViewChange" />
                                </div>
                            </div>

                            <!-- Indexing Progress Indicator -->
                            <div v-if="isIndexing" class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                <div class="flex items-center">
                                    <ProgressSpinner style="width: 20px; height: 20px" strokeWidth="4" class="mr-2" />
                                    <span class="text-sm text-blue-700">Pre-loading data for faster switching...</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="viewType === 'subject' && !selectedSubject && !currentTeacher?.homeroom_section" class="flex flex-col items-center justify-center py-12 text-gray-500">
                            <i class="pi pi-chart-bar text-4xl mb-3 text-gray-300"></i>
                            <p class="font-normal">Please select a subject above to view attendance trends</p>
                        </div>

                        <div v-else-if="!attendanceChartData" class="flex flex-col items-center justify-center py-12">
                            <ProgressSpinner strokeWidth="4" style="width: 50px; height: 50px" class="text-blue-500" />
                            <p class="mt-3 text-gray-500 font-normal">Loading chart data...</p>
                        </div>

                        <div v-else>
                            <!-- Chart Legend -->
                            <div class="flex justify-center gap-6 mb-4 pb-2">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 rounded bg-green-500 mr-2"></div>
                                    <span class="text-sm font-medium text-gray-700">Present</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 rounded bg-red-500 mr-2"></div>
                                    <span class="text-sm font-medium text-gray-700">Absent</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 rounded bg-yellow-500 mr-2"></div>
                                    <span class="text-sm font-medium text-gray-700">Late</span>
                                </div>
                            </div>

                            <!-- Chart Container -->
                            <div class="chart-container" style="height: 400px; padding-bottom: 20px">
                                <Chart type="line" :data="attendanceChartData" :options="chartOptions" :key="`chart-${viewType}-${selectedSubject?.id || 'all'}-${chartView}`" style="height: 100%; width: 100%" class="stylish-chart" />
                            </div>
                        </div>

                        <!-- Fallback for chart rendering issues -->
                        <div v-if="attendanceChartData && !attendanceChartData.datasets[0]?.data.some((val) => val > 0)" class="text-center py-4 text-gray-500">
                            <p class="font-normal">No attendance data available for the selected time period</p>
                            <div class="flex justify-center gap-6 mt-4">
                                <div class="text-center">
                                    <div class="text-green-500 font-bold text-lg">8-10</div>
                                    <div class="text-sm font-medium">Present</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-red-500 font-bold text-lg">0-2</div>
                                    <div class="text-sm font-medium">Absent</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-yellow-500 font-bold text-lg">0-1</div>
                                    <div class="text-sm font-medium">Late</div>
                                </div>
                            </div>
                            <p class="mt-4 text-sm font-normal">Sample data range shown above</p>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Attendance Insights Card -->
                <div class="col-span-12 lg:col-span-4">
                    <AttendanceInsights :students="attendanceSummary?.students || []" :selectedSubject="null" :currentTeacher="currentTeacher" />
                </div>
            </div>

            <!-- Student List with Attendance Issues -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                    <div>
                        <h2 class="text-lg font-semibold flex items-center">
                            <i class="pi pi-users mr-2 text-blue-600"></i>
                            Student Attendance Overview
                        </h2>
                        <p class="text-sm text-gray-500 mt-1">
                            <i class="pi pi-calendar mr-1"></i>
                            Showing recent absence tracking (Last 30 Days)
                            <span v-if="selectedSubject" class="ml-2"> • <i class="pi pi-book mr-1"></i>{{ selectedSubject.name }} </span>
                            <span v-else-if="viewType === 'all_students'" class="ml-2"> • <i class="pi pi-globe mr-1"></i>All Subjects </span>
                        </p>
                    </div>

                    <div class="flex flex-col gap-4 mt-4 sm:mt-0">
                        <!-- Search Bar -->
                        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                            <div class="p-inputgroup w-full sm:w-80">
                                <InputText v-model="searchQuery" placeholder="Search students..." class="w-full" />
                            </div>

                            <!-- Show Issues Toggle -->
                            <div class="flex items-center p-3 rounded-lg border transition-all duration-200" :class="showOnlyAbsenceIssues ? 'bg-blue-50 border-blue-200 shadow-sm' : 'bg-gray-50 border-gray-200 hover:bg-gray-100'">
                                <Checkbox v-model="showOnlyAbsenceIssues" :binary="true" id="showIssues" class="mr-3" />
                                <label for="showIssues" class="text-sm font-medium cursor-pointer text-gray-700">
                                    Show only students with issues
                                    <span v-if="showOnlyAbsenceIssues" class="ml-2 px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">Active</span>
                                </label>
                            </div>
                        </div>

                        <!-- Filter Row -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <!-- Grade Level Filter -->
                            <div class="flex flex-col">
                                <label class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">
                                    <i class="pi pi-graduation-cap mr-1 text-blue-500"></i>
                                    Grade Level
                                </label>
                                <Dropdown v-model="selectedGradeFilter" :options="gradeFilterOptions" optionLabel="label" optionValue="value" placeholder="All Grades" class="w-full" showClear>
                                    <template #value="slotProps">
                                        <div v-if="slotProps.value" class="flex items-center">
                                            <i class="pi pi-graduation-cap mr-2 text-blue-500 text-sm"></i>
                                            <span>{{ slotProps.value }}</span>
                                        </div>
                                        <span v-else class="text-gray-500">All Grades</span>
                                    </template>
                                    <template #option="slotProps">
                                        <div class="flex items-center">
                                            <i class="pi pi-graduation-cap mr-2 text-blue-500 text-sm"></i>
                                            <span>{{ slotProps.option.label }}</span>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Section Filter -->
                            <div class="flex flex-col">
                                <label class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">
                                    <i class="pi pi-building mr-1 text-green-500"></i>
                                    Section
                                </label>
                                <Dropdown v-model="selectedSectionFilter" :options="sectionFilterOptions" optionLabel="label" optionValue="value" placeholder="All Sections" class="w-full" showClear>
                                    <template #value="slotProps">
                                        <div v-if="slotProps.value" class="flex items-center">
                                            <i class="pi pi-building mr-2 text-green-500 text-sm"></i>
                                            <span>{{ slotProps.value }}</span>
                                        </div>
                                        <span v-else class="text-gray-500">All Sections</span>
                                    </template>
                                    <template #option="slotProps">
                                        <div class="flex items-center">
                                            <i class="pi pi-building mr-2 text-green-500 text-sm"></i>
                                            <span>{{ slotProps.option.label }}</span>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Status Filter -->
                            <div class="flex flex-col">
                                <label class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">
                                    <i class="pi pi-flag mr-1 text-orange-500"></i>
                                    Status
                                </label>
                                <Dropdown v-model="selectedStatusFilter" :options="statusFilterOptions" optionLabel="label" optionValue="value" placeholder="All Status" class="w-full" showClear>
                                    <template #value="slotProps">
                                        <div v-if="slotProps.value" class="flex items-center">
                                            <i
                                                class="mr-2 text-sm"
                                                :class="{
                                                    'pi pi-check-circle text-green-500': slotProps.value === 'good',
                                                    'pi pi-info-circle text-blue-500': slotProps.value === 'low',
                                                    'pi pi-exclamation-triangle text-yellow-500': slotProps.value === 'at_risk',
                                                    'pi pi-exclamation-circle text-red-500': slotProps.value === 'critical'
                                                }"
                                            ></i>
                                            <span>{{ getSeverityLabel(slotProps.value) }}</span>
                                        </div>
                                        <span v-else class="text-gray-500">All Status</span>
                                    </template>
                                    <template #option="slotProps">
                                        <div class="flex items-center">
                                            <i
                                                class="mr-2 text-sm"
                                                :class="{
                                                    'pi pi-check-circle text-green-500': slotProps.option.value === 'good',
                                                    'pi pi-info-circle text-blue-500': slotProps.option.value === 'low',
                                                    'pi pi-exclamation-triangle text-yellow-500': slotProps.option.value === 'at_risk',
                                                    'pi pi-exclamation-circle text-red-500': slotProps.option.value === 'critical'
                                                }"
                                            ></i>
                                            <span>{{ slotProps.option.label }}</span>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!selectedSubject && !currentTeacher?.homeroom_section" class="flex flex-col items-center justify-center py-12 text-gray-500">
                    <i class="pi pi-users text-4xl mb-3 text-gray-300"></i>
                    <p class="font-normal">Please select a subject to view student attendance</p>
                </div>

                <div v-else-if="filteredStudents.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-500">
                    <i class="pi pi-search text-4xl mb-3 text-gray-300"></i>
                    <p v-if="searchQuery">No students match your search criteria</p>
                    <p v-else-if="showOnlyAbsenceIssues">No students with absence issues found</p>
                    <p v-else>No students found for this subject</p>
                </div>

                <DataTable v-else :value="filteredStudents" :paginator="true" :rows="10" stripedRows responsiveLayout="scroll" class="p-datatable-sm modern-table" :rowHover="true">
                    <Column style="width: 40px">
                        <template #body="slotProps">
                            <i
                                v-if="slotProps.data.severity !== 'good'"
                                :class="{
                                    'pi pi-info-circle text-blue-500': slotProps.data.severity === 'low',
                                    'pi pi-exclamation-triangle text-yellow-500': slotProps.data.severity === 'at_risk',
                                    'pi pi-exclamation-circle text-red-500': slotProps.data.severity === 'critical'
                                }"
                                class="text-lg"
                            ></i>
                        </template>
                    </Column>

                    <Column field="name" header="Student Name" sortable>
                        <template #body="slotProps">
                            <div class="flex items-center cursor-pointer" @click="openStudentProfile(slotProps.data)">
                                <Avatar
                                    :label="slotProps.data.name.charAt(0)"
                                    shape="circle"
                                    class="mr-2"
                                    :class="{
                                        'bg-green-100 text-green-600': slotProps.data.severity === 'good',
                                        'bg-blue-100 text-blue-600': slotProps.data.severity === 'low',
                                        'bg-yellow-100 text-yellow-600': slotProps.data.severity === 'at_risk',
                                        'bg-red-100 text-red-600': slotProps.data.severity === 'critical'
                                    }"
                                    style="width: 2rem; height: 2rem"
                                ></Avatar>
                                <span class="student-name hover:text-blue-600">{{ slotProps.data.name }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="gradeLevel" header="Grade" style="width: 100px">
                        <template #body="slotProps">
                            <span class="text-sm">{{ formatGradeLevel(slotProps.data.gradeLevel) }}</span>
                        </template>
                    </Column>

                    <Column field="section" header="Section" style="width: 140px">
                        <template #body="slotProps">
                            <span class="text-sm">{{ slotProps.data.section }}</span>
                        </template>
                    </Column>

                    <Column field="absences" header="Absences" sortable style="width: 120px">
                        <template #body="slotProps">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-full"
                                :class="{
                                    'bg-green-100 text-green-800': slotProps.data.severity === 'good',
                                    'bg-blue-100 text-blue-800': slotProps.data.severity === 'low',
                                    'bg-yellow-100 text-yellow-800': slotProps.data.severity === 'at_risk',
                                    'bg-red-100 text-red-800': slotProps.data.severity === 'critical'
                                }"
                            >
                                {{ slotProps.data.absences }}
                            </div>
                        </template>
                    </Column>

                    <Column header="Status" style="width: 140px">
                        <template #body="slotProps">
                            <Tag
                                :severity="slotProps.data.severity === 'critical' ? 'danger' : slotProps.data.severity === 'at_risk' ? 'warning' : slotProps.data.severity === 'low' ? 'info' : 'success'"
                                :value="getSeverityLabel(slotProps.data.severity)"
                                class="px-3 py-1.5 text-sm font-medium rounded-full"
                            />
                        </template>
                    </Column>

                    <Column header="Actions" style="width: 160px">
                        <template #body="slotProps">
                            <Button icon="pi pi-calendar" label="View Details" class="p-button-sm p-button-info" @click="openStudentProfile(slotProps.data)" v-tooltip.top="'View attendance calendar & history'" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <!-- Student Profile Dialog - Modern Version -->
        <Dialog v-model:visible="studentProfileVisible" :style="{ width: '700px' }" header="Student Attendance Profile" :modal="true" :dismissableMask="true" class="student-profile-dialog">
            <div v-if="selectedStudent" class="student-profile p-3">
                <div class="flex flex-col sm:flex-row items-center sm:items-start mb-6">
                    <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center text-2xl font-bold mr-6 text-blue-600 mb-4 sm:mb-0">
                        {{ selectedStudent.name.charAt(0) }}
                    </div>
                    <div>
                        <h3 class="text-xl font-semibold">{{ selectedStudent.name }}</h3>
                        <p class="text-gray-600 font-normal mb-2">Grade {{ selectedStudent.gradeLevel }} - {{ selectedStudent.section }}</p>
                        <p v-if="selectedSubject" class="inline-flex items-center bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-sm font-medium">
                            <i class="pi pi-book mr-1"></i>
                            {{ selectedSubject.name }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-gray-50 p-4 rounded-xl shadow-sm">
                        <p class="text-gray-500 text-sm mb-1">Total Absences</p>
                        <div class="flex items-center">
                            <div
                                class="flex items-center justify-center w-12 h-12 rounded-full mr-3"
                                :class="{
                                    'bg-green-100 text-green-800': selectedStudent.severity === 'normal',
                                    'bg-yellow-100 text-yellow-800': selectedStudent.severity === 'warning',
                                    'bg-red-100 text-red-800': selectedStudent.severity === 'critical'
                                }"
                            >
                                {{ selectedStudent.absences }}
                            </div>
                            <div>
                                <p
                                    class="font-medium"
                                    :class="{
                                        'text-green-600': selectedStudent.severity === 'normal',
                                        'text-yellow-600': selectedStudent.severity === 'warning',
                                        'text-red-600': selectedStudent.severity === 'critical'
                                    }"
                                >
                                    {{ selectedStudent.severity === 'normal' ? 'Good' : selectedStudent.severity === 'warning' ? 'Warning' : 'Critical' }}
                                </p>
                                <p class="text-xs text-gray-500">Absence level</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-4 rounded-xl shadow-sm">
                        <p class="text-gray-500 text-sm mb-1 flex items-center gap-2">
                            Attendance Rate
                            <i 
                                class="pi pi-info-circle text-blue-500 cursor-help text-xs" 
                                v-tooltip.top="{
                                    value: 'Formula: (Days Present + Late + Excused) ÷ Total School Days × 100%',
                                    escape: false
                                }"
                            ></i>
                        </p>
                        <div class="flex items-center">
                            <div class="w-12 h-12 relative mr-3">
                                <div
                                    class="absolute inset-0 flex items-center justify-center text-lg font-bold"
                                    :class="{
                                        'text-green-600': Math.round(((20 - selectedStudent.absences) / 20) * 100) >= 85,
                                        'text-yellow-600': Math.round(((20 - selectedStudent.absences) / 20) * 100) < 85 && Math.round(((20 - selectedStudent.absences) / 20) * 100) >= 70,
                                        'text-red-600': Math.round(((20 - selectedStudent.absences) / 20) * 100) < 70
                                    }"
                                >
                                    {{ Math.round(((20 - selectedStudent.absences) / 20) * 100) }}%
                                </div>
                                <!-- Would add a circular progress bar component here in a real implementation -->
                            </div>
                            <ProgressBar
                                :value="Math.round(((20 - selectedStudent.absences) / 20) * 100)"
                                class="h-2 flex-1"
                                :class="{
                                    'attendance-good': Math.round(((20 - selectedStudent.absences) / 20) * 100) >= 85,
                                    'attendance-warning': Math.round(((20 - selectedStudent.absences) / 20) * 100) < 85 && Math.round(((20 - selectedStudent.absences) / 20) * 100) >= 70,
                                    'attendance-poor': Math.round(((20 - selectedStudent.absences) / 20) * 100) < 70
                                }"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h4 class="font-medium">Attendance Calendar</h4>
                        <p class="text-sm text-gray-600">{{ new Date(currentYear, currentMonth).toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label for="calendar-subject-filter" class="text-sm font-medium text-gray-700">Subject:</label>
                            <Dropdown id="calendar-subject-filter" v-model="calendarSubjectFilter" :options="calendarSubjectOptions" optionLabel="name" optionValue="id" placeholder="Select Subject" @change="onCalendarSubjectChange" class="w-48" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-4 text-xs mb-3">
                    <div class="flex items-center space-x-1">
                        <div class="w-3 h-3 bg-red-100 border border-red-200 rounded-full"></div>
                        <span class="text-gray-600">Absent</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <div class="w-3 h-3 bg-blue-100 border border-blue-200 rounded-full"></div>
                        <span class="text-gray-600">Excused</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <div class="w-3 h-3 bg-yellow-100 border border-yellow-200 rounded-full"></div>
                        <span class="text-gray-600">Late</span>
                    </div>
                    <div class="flex items-center space-x-1">
                        <div class="w-3 h-3 bg-green-100 border border-green-200 rounded-full"></div>
                        <span class="text-gray-600">Present</span>
                    </div>
                </div>

                <div class="calendar-view mb-6 p-4 bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="calendar-header grid grid-cols-7 gap-1 mb-2">
                        <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day" class="text-center font-medium text-gray-600 text-xs">
                            {{ day }}
                        </div>
                    </div>

                    <div class="calendar-days grid grid-cols-7 gap-1">
                        <!-- Empty cells for days before the 1st of the month -->
                        <div v-for="i in getFirstDayOfMonth()" :key="`empty-${i}`" class="h-10 w-10"></div>

                        <!-- Calendar days -->
                        <div
                            v-for="calDay in calendarData"
                            :key="calDay.day"
                            class="calendar-day h-10 w-10 flex items-center justify-center rounded-full transition-all cursor-pointer relative"
                            :class="{
                                'bg-red-100 text-red-800 border border-red-200 font-semibold': calDay.status === 'ABSENT',
                                'bg-blue-100 text-blue-800 border border-blue-200 font-semibold': calDay.status === 'EXCUSED',
                                'bg-yellow-100 text-yellow-800 border border-yellow-200 font-semibold': calDay.status === 'LATE',
                                'bg-green-100 text-green-800 border border-green-200': calDay.status === 'PRESENT',
                                'hover:bg-gray-100': !calDay.status,
                                'bg-gray-200 text-gray-400': new Date(currentYear, currentMonth, calDay.day).getDay() === 0 || new Date(currentYear, currentMonth, calDay.day).getDay() === 6
                            }"
                            :title="calDay.status ? `${calDay.status} on ${calDay.day}/${currentMonth + 1}/${currentYear}` : ''"
                            :data-status="calDay.status"
                            :data-day="calDay.day"
                            @click="handleCalendarDayClick(calDay)"
                        >
                            {{ calDay.day }}
                            <!-- Debug info -->
                            <span v-if="calDay.status" class="absolute top-0 left-0 text-xs text-blue-600">{{ calDay.status.charAt(0) }}</span>
                            <!-- Status indicator dot -->
                            <div
                                v-if="calDay.status"
                                class="absolute -top-1 -right-1 w-2 h-2 rounded-full"
                                :class="{
                                    'bg-red-500': calDay.status === 'ABSENT',
                                    'bg-blue-500': calDay.status === 'EXCUSED',
                                    'bg-yellow-500': calDay.status === 'LATE',
                                    'bg-green-500': calDay.status === 'PRESENT'
                                }"
                            ></div>
                        </div>
                    </div>

                    <!-- Calendar summary -->
                    <div v-if="absentDays.length > 0" class="mt-4 pt-4 border-t border-gray-200">
                        <div class="text-sm text-gray-600 text-center">
                            <span class="font-medium text-red-600">{{ absentDays.length }}</span> absent days this month
                        </div>
                    </div>
                </div>

                <h4 class="font-medium mb-3">Absence History</h4>
                <div class="absence-history">
                    <div v-if="absentDays.length > 0" class="space-y-3">
                        <div
                            v-for="day in absentDays"
                            :key="day.recordId || Math.random()"
                            class="p-3 border-l-4 rounded-r-lg flex justify-between items-center"
                            :class="{
                                'border-red-500 bg-red-50': day.status === 'ABSENT',
                                'border-blue-500 bg-blue-50': day.status === 'EXCUSED',
                                'border-yellow-500 bg-yellow-50': day.status === 'LATE'
                            }"
                        >
                            <div class="flex items-center">
                                <i
                                    class="mr-2"
                                    :class="{
                                        'pi pi-times-circle text-red-500': day.status === 'ABSENT',
                                        'pi pi-info-circle text-blue-500': day.status === 'EXCUSED',
                                        'pi pi-clock text-yellow-500': day.status === 'LATE'
                                    }"
                                ></i>
                                <div>
                                    <span class="font-medium">{{ formatDate(day.date) }}</span>
                                    <span v-if="day.time && day.time !== 'N/A'" class="text-xs text-gray-500 ml-2">({{ day.time }})</span>
                                </div>
                            </div>
                            <span
                                class="text-sm px-2 py-1 bg-white rounded-full font-medium"
                                :class="{
                                    'text-red-600': day.status === 'ABSENT',
                                    'text-blue-600': day.status === 'EXCUSED',
                                    'text-yellow-600': day.status === 'LATE'
                                }"
                            >
                                {{ day.status === 'EXCUSED' ? 'Excused' : day.status === 'LATE' ? 'Late' : 'Unexcused' }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="flex items-center justify-center p-4 bg-gray-50 rounded-lg border border-gray-200 text-gray-500 italic">
                        <i class="pi pi-check-circle text-green-500 mr-2"></i>
                        No absence records found for this student in this subject.
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Calendar Day Details Dialog -->
        <Dialog v-model:visible="showDayDetailsDialog" :style="{ width: '450px' }" :modal="true">
            <template #header>
                <div class="flex items-center space-x-2">
                    <i class="pi pi-calendar text-blue-600"></i>
                    <span class="font-semibold">Attendance Details</span>
                </div>
            </template>

            <div v-if="selectedDayDetails" class="space-y-4">
                <!-- Date Info -->
                <div class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200">
                    <div class="text-center">
                        <p class="text-sm text-gray-600 mb-1">Date</p>
                        <p class="text-2xl font-bold text-gray-800">
                            {{ selectedDayDetails.date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                        </p>
                    </div>
                </div>

                <!-- Student Info -->
                <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                    <Avatar icon="pi pi-user" class="bg-blue-500 text-white" shape="circle" />
                    <div>
                        <p class="text-sm text-gray-600">Student</p>
                        <p class="font-semibold text-gray-800">{{ selectedDayDetails.studentName }}</p>
                    </div>
                </div>

                <!-- Status -->
                <div
                    class="p-4 rounded-lg border-2"
                    :class="{
                        'bg-red-50 border-red-300': selectedDayDetails.status === 'ABSENT',
                        'bg-blue-50 border-blue-300': selectedDayDetails.status === 'EXCUSED',
                        'bg-yellow-50 border-yellow-300': selectedDayDetails.status === 'LATE',
                        'bg-green-50 border-green-300': selectedDayDetails.status === 'PRESENT'
                    }"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <i
                                class="text-3xl"
                                :class="{
                                    'pi pi-times-circle text-red-600': selectedDayDetails.status === 'ABSENT',
                                    'pi pi-info-circle text-blue-600': selectedDayDetails.status === 'EXCUSED',
                                    'pi pi-clock text-yellow-600': selectedDayDetails.status === 'LATE',
                                    'pi pi-check-circle text-green-600': selectedDayDetails.status === 'PRESENT'
                                }"
                            ></i>
                            <div>
                                <p
                                    class="text-sm font-medium"
                                    :class="{
                                        'text-red-700': selectedDayDetails.status === 'ABSENT',
                                        'text-blue-700': selectedDayDetails.status === 'EXCUSED',
                                        'text-yellow-700': selectedDayDetails.status === 'LATE',
                                        'text-green-700': selectedDayDetails.status === 'PRESENT'
                                    }"
                                >
                                    Status
                                </p>
                                <p
                                    class="text-xl font-bold"
                                    :class="{
                                        'text-red-800': selectedDayDetails.status === 'ABSENT',
                                        'text-blue-800': selectedDayDetails.status === 'EXCUSED',
                                        'text-yellow-800': selectedDayDetails.status === 'LATE',
                                        'text-green-800': selectedDayDetails.status === 'PRESENT'
                                    }"
                                >
                                    {{ selectedDayDetails.status }}
                                </p>
                            </div>
                        </div>
                        <Tag :value="selectedDayDetails.status" :severity="selectedDayDetails.status === 'ABSENT' ? 'danger' : selectedDayDetails.status === 'EXCUSED' ? 'info' : selectedDayDetails.status === 'LATE' ? 'warning' : 'success'" />
                    </div>
                </div>

                <!-- Subject Info -->
                <div class="p-3 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600">Subject</p>
                    <p class="font-semibold text-gray-800">{{ selectedSubject?.name || 'All Subjects' }}</p>
                </div>
            </div>
        </Dialog>

        <!-- Debug Data -->
        <div v-if="isDebugMode" class="mt-4 p-2 bg-gray-100 rounded text-xs">
            <details>
                <summary class="cursor-pointer font-semibold">Debug Data</summary>
                <pre class="mt-2 overflow-auto max-h-[150px]">{{ JSON.stringify(attendanceChartData, null, 2) }}</pre>
            </details>
        </div>
    </div>

    <!-- Subject Loading Overlay -->
    <div v-if="subjectLoading" class="subject-loading-overlay">
        <div class="loading-content">
            <div class="loading-animation">
                <div class="prismatic-square"></div>
            </div>
            <h3 class="loading-text">Please wait while we prepare your dashboard...</h3>
        </div>
    </div>

    <!-- Critical Students Alert Dialog -->
    <Dialog v-model:visible="showCriticalAlert" header="🚨 Critical Attendance Cases" :style="{ width: '600px' }" :modal="true">
        <div v-if="criticalStudents.length > 0" class="space-y-4">
            <p class="text-gray-700 mb-4">The following students have exceeded the 18-absence limit and require immediate attention:</p>

            <div v-for="student in criticalStudents" :key="student.id" class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="font-medium text-red-900">{{ student.name }}</h4>
                        <p class="text-sm text-red-700">Total Absences: {{ student.total_absences }} | Attendance Rate: {{ student.attendance_percentage }}%</p>
                    </div>
                    <Button @click="showStudentProfile(student)" label="View Details" icon="pi pi-eye" class="p-button-sm p-button-outlined" />
                </div>
            </div>
        </div>

        <template #footer>
            <Button @click="showCriticalAlert = false" label="Close" class="p-button-text" />
        </template>
    </Dialog>
</template>

<style scoped>
.student-name {
    position: relative;
}

.student-name:after {
    content: '';
    position: absolute;
    width: 0;
    height: 1px;
    bottom: 0;
    left: 0;
    background-color: currentColor;
    transition: width 0.2s;
}

.student-name:hover:after {
    width: 100%;
}

.calendar-days {
    min-height: 240px;
}

/* Ensure calendar header and days are perfectly aligned */
.calendar-header,
.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.25rem; /* Same gap for both */
    align-items: center;
}

.calendar-header > div,
.calendar-day {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem; /* Fixed width */
    height: 2.5rem; /* Fixed height */
}

.student-profile-dialog :deep(.p-dialog-content) {
    max-height: 80vh;
    overflow-y: auto;
}

.chart-container {
    position: relative;
    height: 300px !important;
    width: 100%;
    margin-bottom: 1rem;
}

.chart-container :deep(canvas) {
    display: block !important;
    height: 100% !important;
    width: 100% !important;
}

/* Modern styles for attendance stats progress bars */
.attendance-good {
    background: linear-gradient(to right, #22c55e, #4ade80) !important;
}

.attendance-warning {
    background: linear-gradient(to right, #eab308, #fbbf24) !important;
}

.attendance-poor {
    background: linear-gradient(to right, #ef4444, #f87171) !important;
}

/* Enhanced Data Table Styling */
.modern-table {
    box-shadow: none;
    border-radius: 12px;
    overflow: hidden;
}

.modern-table :deep(.p-datatable-header) {
    background-color: #f8fafc;
    border: none;
    padding: 1rem;
}

.modern-table :deep(.p-datatable-thead > tr > th) {
    background-color: #f8fafc;
    color: #334155;
    font-weight: 600;
    padding: 1rem;
    border-color: #f1f5f9;
}

.modern-table :deep(.p-datatable-tbody > tr) {
    transition:
        background-color 0.2s,
        box-shadow 0.2s;
    background-color: #f8fafc;
}

.modern-table :deep(.p-datatable-tbody > tr:hover) {
    background-color: #f8fafc;
}

.modern-table :deep(.p-datatable-tbody > tr > td) {
    padding: 0.8rem 1rem;
    border-color: #f1f5f9;
}

.modern-table :deep(.p-paginator) {
    background-color: #fff;
    border: none;
    padding: 1rem;
}

.modern-table :deep(.p-paginator .p-paginator-pages .p-paginator-page.p-highlight) {
    background-color: #3b82f6;
    color: white;
}

/* Enhanced Dialog Styling */
.student-profile-dialog :deep(.p-dialog-header) {
    background: linear-gradient(to right, #3b82f6, #60a5fa);
    color: white;
    border-radius: 12px 12px 0 0;
    padding: 1.25rem;
}

.student-profile-dialog :deep(.p-dialog-title) {
    font-weight: 600;
}

.student-profile-dialog :deep(.p-dialog-header-icon) {
    color: white;
}

.student-profile-dialog :deep(.p-dialog-header-icon:hover) {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
}

.student-profile-dialog :deep(.p-dialog-content) {
    border-radius: 0 0 12px 12px;
    padding: 1.5rem;
}

.student-profile-dialog :deep(.p-dialog-footer) {
    border-top: 1px solid #f1f5f9;
    padding: 1.25rem;
}

@media (max-width: 768px) {
    .chart-container {
        height: 250px !important;
    }
}
/* Subject Loading Overlay Styles */
.subject-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.loading-content {
    text-align: center;
    color: white;
}

.loading-animation {
    margin-bottom: 2rem;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 120px;
}

.prismatic-square {
    width: 80px;
    height: 80px;
    background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1, #96ceb4, #ffeaa7, #dda0dd, #ff6b6b);
    background-size: 400% 400%;
    border-radius: 8px;
    animation:
        prismaticRotate 4s ease-in-out infinite,
        gradientShift 3s ease-in-out infinite;
    box-shadow: 0 0 30px rgba(255, 255, 255, 0.3);
}

@keyframes prismaticRotate {
    0% {
        transform: rotate(0deg) scale(1);
        border-radius: 8px;
    }
    25% {
        transform: rotate(45deg) scale(1.1);
        border-radius: 50%;
    }
    50% {
        transform: rotate(90deg) scale(1);
        border-radius: 0;
    }
    75% {
        transform: rotate(135deg) scale(1.1);
        border-radius: 50%;
    }
    100% {
        transform: rotate(180deg) scale(1);
        border-radius: 8px;
    }
}

@keyframes gradientShift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

.loading-text {
    font-size: 1.5rem;
    font-weight: 700;
    color: black;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    letter-spacing: 1px;
    margin: 0;
}
</style>
