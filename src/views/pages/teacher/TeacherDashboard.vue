<script setup>
import BookFlipLoader from '@/components/BookFlipLoader.vue';
import AttendanceInsights from '@/components/Teachers/AttendanceInsights.vue';
import api from '@/config/axios';
import { TeacherAttendanceService } from '@/router/service/TeacherAttendanceService.js';
import { AttendanceSummaryService } from '@/services/AttendanceSummaryService.js';
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
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

// Dashboard components
const currentTeacher = ref(null);
const teacherSubjects = ref([]);
const attendanceSummary = ref(null);
const studentsWithAbsenceIssues = ref([]);
const selectedStudent = ref(null);
const studentProfileVisible = ref(false);
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

// Attendance view options
const viewTypeOptions = [
    { label: 'Subject-Specific', value: 'subject' },
    { label: 'All Students', value: 'all_students' }
];
const viewType = ref('subject');
const currentDate = ref(new Date());
const currentMonth = ref(new Date().getMonth());
const currentYear = ref(new Date().getFullYear());

// Calendar data for student profiles
const calendarData = ref([]);
const absentDays = ref([]);

// Attendance threshold settings
const WARNING_THRESHOLD = 3; // Yellow warning after 3 absences
const CRITICAL_THRESHOLD = 5; // Red warning after 5 absences

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

// Auto-refresh function to reload data
async function refreshDashboardData() {
    console.log('Refreshing dashboard data...');
    await loadAttendanceData();
}

// Set up auto-refresh every 30 seconds to catch new enrollments
let refreshInterval;

// Load teacher data and subjects
onMounted(async () => {
    loading.value = true;
    try {
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

        // Set default selected subject
        if (availableSubjects.value.length > 0) {
            selectedSubject.value = availableSubjects.value[0];
        }

        // Load attendance data for default subject
        console.log('About to call loadAttendanceData with:', {
            selectedSubject: selectedSubject.value,
            currentTeacher: currentTeacher.value
        });
        try {
            await loadAttendanceData();
        } catch (error) {
            console.error('Error in loadAttendanceData:', error);
        }

        // Prepare chart data which includes setting up chart options
        prepareChartData();

        // Set up auto-refresh to catch new student enrollments
        refreshInterval = setInterval(refreshDashboardData, 10000); // Refresh every 10 seconds for testing
    } catch (error) {
        console.error('Error initializing dashboard:', error);
    } finally {
        loading.value = false;
    }
});

// Clean up interval on unmount
onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
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
                        sectionId: sectionId,
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

// Load attendance data for the selected subject using real database API
async function loadAttendanceData() {
    if (!selectedSubject.value || !currentTeacher.value) return;

    try {
        console.log('Loading attendance data for subject:', selectedSubject.value);

        // Use the same API as the attendance page to get students
        const sectionId = selectedSubject.value.sectionId || selectedSubject.value.originalSubject?.sectionId || 3; // Use Malikhain section ID
        console.log('Using sectionId:', sectionId, 'for subject:', selectedSubject.value.name);

        console.log('Calling getStudentsForTeacherSubject with params:', {
            teacherId: currentTeacher.value.id,
            sectionId: sectionId,
            subjectId: selectedSubject.value.id
        });

        const studentsResponse = await TeacherAttendanceService.getStudentsForTeacherSubject(currentTeacher.value.id, sectionId, selectedSubject.value.id);

        console.log('Raw students response:', studentsResponse);

        let students = [];
        if (studentsResponse && studentsResponse.students) {
            students = studentsResponse.students;
            console.log('Students loaded from API:', students);
        } else if (studentsResponse && Array.isArray(studentsResponse)) {
            students = studentsResponse;
            console.log('Students loaded from API (array format):', students);
        } else {
            console.error('No students found in response:', studentsResponse);
        }

        // Calculate date range (current month)
        const currentDate = new Date();
        const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
        const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);

        // Get attendance summary using the new service
        let summaryData = null;
        try {
            const summaryResult = await AttendanceSummaryService.getTeacherAttendanceSummary(currentTeacher.value.id, {
                period: chartView.value,
                viewType: viewType.value,
                subjectId: viewType.value === 'subject' ? selectedSubject.value.id : null
            });

            if (summaryResult && summaryResult.success) {
                summaryData = summaryResult.data;
                console.log('Attendance summary from database:', summaryData);
            }
        } catch (summaryError) {
            console.error('Error fetching attendance summary:', summaryError);
        }

        // If no summary data, calculate from students
        if (!summaryData) {
            summaryData = {
                totalStudents: students.length,
                averageAttendance: 85,
                studentsWithWarning: 0,
                studentsWithCritical: 0
            };
        } else {
            // Map API response fields to frontend expected fields
            summaryData = {
                totalStudents: summaryData.total_students || students.length,
                averageAttendance: summaryData.average_attendance || 0,
                studentsWithWarning: summaryData.students_with_warning || 0,
                studentsWithCritical: summaryData.students_with_critical || 0,
                students: summaryData.students || []
            };
        }

        attendanceSummary.value = summaryData;

        // Process students for absence issues using real data
        studentsWithAbsenceIssues.value = students.map((student) => {
            // Use real absence data from summary if available
            const studentSummary = summaryData?.students?.find((s) => s.student_id === student.id);
            const absences = studentSummary?.total_absences || 0;
            const recentAbsences = studentSummary?.recent_absences || 0;

            let severity = 'normal';
            if (recentAbsences >= CRITICAL_THRESHOLD) {
                severity = 'critical';
            } else if (recentAbsences >= WARNING_THRESHOLD) {
                severity = 'warning';
            }

            return {
                id: student.id,
                name: student.first_name + ' ' + student.last_name,
                gradeLevel: student.grade_level || 3,
                section: student.section_name || 'Unknown',
                absences: absences,
                recentAbsences: recentAbsences,
                severity: severity
            };
        });

        console.log('Attendance data loaded successfully');
        console.log('Updated attendance summary:', attendanceSummary.value);
        console.log('Updated students list:', studentsWithAbsenceIssues.value);
    } catch (error) {
        console.error('Error loading attendance data:', error);
        // Fallback data
        attendanceSummary.value = {
            totalStudents: 0,
            averageAttendance: 0,
            studentsWithWarning: 0,
            studentsWithCritical: 0
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
            let severity = 'normal';

            if (absences >= CRITICAL_THRESHOLD) {
                severity = 'critical';
            } else if (absences >= WARNING_THRESHOLD) {
                severity = 'warning';
            }

            return {
                ...student,
                absences,
                severity
            };
        })
        .filter((student) => (showOnlyAbsenceIssues.value ? student.severity !== 'normal' : true))
        .sort((a, b) => b.absences - a.absences); // Sort by absences (highest first)

    // Prepare attendance summary
    attendanceSummary.value = {
        totalStudents: students.length,
        studentsWithWarning: studentsWithAbsenceIssues.value.filter((s) => s.severity === 'warning').length,
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
    if (!selectedSubject.value || !currentTeacher.value) return;

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
            const trendsResult = await AttendanceSummaryService.getAttendanceTrends(currentTeacher.value.id, {
                period: chartView.value,
                viewType: viewType.value,
                subjectId: viewType.value === 'subject' ? selectedSubject.value.id : null
            });

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

        // Prepare datasets for each status with real data and enhanced styling
        attendanceChartData.value = {
            labels: labels,
            datasets: [
                {
                    label: 'Present',
                    backgroundColor: 'rgba(76, 175, 80, 0.8)',
                    borderColor: '#4CAF50',
                    borderWidth: 1,
                    borderRadius: 4,
                    data: attendanceData.present,
                    hoverBackgroundColor: 'rgba(76, 175, 80, 1)'
                },
                {
                    label: 'Absent',
                    backgroundColor: 'rgba(244, 67, 54, 0.8)',
                    borderColor: '#F44336',
                    borderWidth: 1,
                    borderRadius: 4,
                    data: attendanceData.absent,
                    hoverBackgroundColor: 'rgba(244, 67, 54, 1)'
                },
                {
                    label: 'Late',
                    backgroundColor: 'rgba(255, 193, 7, 0.8)',
                    borderColor: '#FFC107',
                    borderWidth: 1,
                    borderRadius: 4,
                    data: attendanceData.late,
                    hoverBackgroundColor: 'rgba(255, 193, 7, 1)'
                }
            ]
        };

        console.log('Chart data prepared:', attendanceChartData.value);

        // Chart options with enhanced styling for modern look
        chartOptions.value = {
            plugins: {
                legend: {
                    position: 'top',
                    align: 'center',
                    labels: {
                        font: {
                            family: 'Inter, sans-serif',
                            size: 12,
                            weight: 500
                        },
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 20
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#333',
                    titleFont: {
                        family: 'Inter, sans-serif',
                        size: 13,
                        weight: 600
                    },
                    bodyColor: '#555',
                    bodyFont: {
                        family: 'Inter, sans-serif',
                        size: 12
                    },
                    borderColor: '#e1e1e1',
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    boxPadding: 4
                }
            },
            responsive: true,
            maintainAspectRatio: false,
            barPercentage: 0.8,
            categoryPercentage: 0.9,
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            family: 'Inter, sans-serif',
                            size: 11
                        },
                        color: '#666'
                    }
                },
                y: {
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
                        padding: 8
                    },
                    border: {
                        dash: [4, 4]
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
function openStudentProfile(student) {
    selectedStudent.value = student;
    prepareCalendarData(student);
    studentProfileVisible.value = true;
}

// Prepare calendar data for the student profile using StudentAttendanceService
function prepareCalendarData(student) {
    if (!selectedSubject.value || !student) return;

    // Get all attendance records for this student in this subject
    const records = StudentAttendanceService.getSubjectAttendanceRecords([student.id], selectedSubject.value.id, currentMonth.value, currentYear.value);

    // Find absent days
    absentDays.value = records.filter((record) => record.status === 'ABSENT').map((record) => new Date(record.date));

    // Generate calendar data for current month
    const daysInMonth = new Date(currentYear.value, currentMonth.value + 1, 0).getDate();

    // Generate calendar entries
    calendarData.value = Array.from({ length: daysInMonth }, (_, i) => {
        const date = new Date(currentYear.value, currentMonth.value, i + 1);
        const isAbsent = absentDays.value.some((d) => d.getDate() === date.getDate() && d.getMonth() === date.getMonth() && d.getFullYear() === date.getFullYear());

        return {
            date,
            day: i + 1,
            isAbsent
        };
    });
}

// Handle subject change
async function onSubjectChange() {
    subjectLoading.value = true;
    try {
        await loadAttendanceData();
        await prepareChartData();
    } finally {
        subjectLoading.value = false;
    }
}

// Handle view type change (subject-specific vs all students)
function onViewTypeChange() {
    console.log('View type changed to:', viewType.value);
    loadAttendanceData();
    prepareChartData();
}

// Handle chart view change (daily/weekly/monthly)
function onChartViewChange() {
    console.log('Chart view changed to:', chartView.value);
    prepareChartData();
}

// Watch for subject changes to update data
watch(selectedSubject, (newSubject) => {
    if (newSubject) {
        console.log('Subject changed to:', newSubject);
        loadAttendanceData();
        prepareChartData();
    }
});

// Watch for chart view changes to update chart data
watch(chartView, (newView) => {
    console.log('Chart view changed to:', newView);
    prepareChartData();
});

// Filter students by name
const searchQuery = ref('');
const filteredStudents = computed(() => {
    if (!searchQuery.value.trim()) return studentsWithAbsenceIssues.value;

    const query = searchQuery.value.toLowerCase();
    return studentsWithAbsenceIssues.value.filter((student) => student.name.toLowerCase().includes(query));
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
    }).format(date);
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
</script>

<template>
    <div class="grid" style="margin: 0 1rem">
        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center h-64 bg-white rounded-xl shadow-sm">
            <BookFlipLoader size="medium" text="Loading dashboard data..." :show-text="true" />
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

                    <div class="col-span-12 sm:col-span-5 flex flex-col sm:flex-row gap-2 justify-end">
                        <Dropdown v-model="selectedSubject" :options="availableSubjects" optionLabel="name" placeholder="Select Subject" class="w-full bg-white/10 backdrop-blur-sm border border-white/20 rounded-3xl" @change="onSubjectChange" />
                    </div>
                </div>
            </div>

            <!-- Attendance Stats Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
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
                    <div class="mr-4 bg-green-100 p-3 rounded-lg">
                        <i class="pi pi-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 mb-1 font-medium">Average Attendance</div>
                        <div class="flex items-end">
                            <span class="text-2xl font-bold mr-1">{{ attendanceSummary?.averageAttendance || 0 }}%</span>
                            <ProgressBar
                                :value="attendanceSummary?.averageAttendance || 0"
                                class="h-1.5 w-16 mb-1.5"
                                :class="{
                                    'attendance-good': (attendanceSummary?.averageAttendance || 0) >= 85,
                                    'attendance-warning': (attendanceSummary?.averageAttendance || 0) < 85 && (attendanceSummary?.averageAttendance || 0) >= 70,
                                    'attendance-poor': (attendanceSummary?.averageAttendance || 0) < 70
                                }"
                            />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow flex items-center">
                    <div class="mr-4 bg-yellow-100 p-3 rounded-lg">
                        <i class="pi pi-exclamation-triangle text-yellow-600 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 mb-1 font-medium">Warning ({{ WARNING_THRESHOLD }}+ absences)</div>
                        <div class="text-2xl font-bold">{{ attendanceSummary?.studentsWithWarning || 0 }}</div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow flex items-center">
                    <div class="mr-4 bg-red-100 p-3 rounded-lg">
                        <i class="pi pi-exclamation-circle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 mb-1 font-medium">Critical ({{ CRITICAL_THRESHOLD }}+ absences)</div>
                        <div class="text-2xl font-bold">{{ attendanceSummary?.studentsWithCritical || 0 }}</div>
                    </div>
                </div>
            </div>

            <!-- Attendance Chart & Alerts -->
            <div class="grid grid-cols-12 gap-6 mb-6">
                <!-- Attendance Trends Chart -->
                <div class="col-span-12 lg:col-span-8">
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
                            <h2 class="text-lg font-semibold">Attendance Trends</h2>
                            <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                                <!-- View Type Toggle -->
                                <div class="flex items-center gap-2">
                                    <label class="text-sm font-medium text-gray-600">View:</label>
                                    <SelectButton v-model="viewType" :options="viewTypeOptions" optionLabel="label" optionValue="value" class="text-xs" @change="onViewTypeChange" />
                                </div>
                                <!-- Time Period Toggle -->
                                <div class="flex items-center gap-2">
                                    <label class="text-sm font-medium text-gray-600">Period:</label>
                                    <SelectButton v-model="chartView" :options="chartViewOptions" optionLabel="label" optionValue="value" class="text-xs" @change="onChartViewChange" />
                                </div>
                            </div>
                        </div>

                        <div v-if="!selectedSubject" class="flex flex-col items-center justify-center py-12 text-gray-500">
                            <i class="pi pi-chart-bar text-4xl mb-3 text-gray-300"></i>
                            <p class="font-normal">Please select a subject to view attendance trends</p>
                        </div>

                        <div v-else-if="!attendanceChartData" class="flex flex-col items-center justify-center py-12">
                            <ProgressSpinner strokeWidth="4" style="width: 50px; height: 50px" class="text-blue-500" />
                            <p class="mt-3 text-gray-500 font-normal">Loading chart data...</p>
                        </div>

                        <div v-else class="chart-container">
                            <Chart type="bar" :data="attendanceChartData" :options="chartOptions" :key="Date.now()" style="height: 300px" />
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
                    <AttendanceInsights :students="attendanceSummary?.students || []" :selectedSubject="selectedSubject" />
                </div>
            </div>

            <!-- Student List with Attendance Issues -->
            <div class="bg-white rounded-xl shadow-sm p-5">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                    <h2 class="text-lg font-semibold flex items-center">
                        <i class="pi pi-list text-blue-600 mr-2"></i>
                        Student Attendance
                        <span v-if="selectedSubject" class="text-sm font-normal text-gray-500 block sm:inline sm:ml-2">
                            {{ selectedSubject.name }}
                        </span>
                    </h2>

                    <div class="flex flex-col sm:flex-row gap-3 mt-2 sm:mt-0">
                        <div class="p-inputgroup w-full sm:w-64">
                            <span class="p-inputgroup-addon"> </span>
                            <InputText v-model="searchQuery" placeholder="Search students..." class="w-full rounded-lg" />
                        </div>

                        <div class="flex items-center bg-gray-50 p-2 rounded-lg">
                            <Checkbox v-model="showOnlyAbsenceIssues" :binary="true" id="showIssues" />
                            <label for="showIssues" class="ml-2 text-sm font-medium">Show only students with issues</label>
                        </div>
                    </div>
                </div>

                <div v-if="!selectedSubject" class="flex flex-col items-center justify-center py-12 text-gray-500">
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
                                v-if="slotProps.data.severity !== 'normal'"
                                :class="{
                                    'pi pi-exclamation-triangle text-yellow-500': slotProps.data.severity === 'warning',
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
                                        'bg-green-100 text-green-600': slotProps.data.severity === 'normal',
                                        'bg-yellow-100 text-yellow-600': slotProps.data.severity === 'warning',
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
                            <span class="text-sm">Grade {{ slotProps.data.gradeLevel }}</span>
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
                                    'bg-green-100 text-green-800': slotProps.data.severity === 'normal',
                                    'bg-yellow-100 text-yellow-800': slotProps.data.severity === 'warning',
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
                                :severity="slotProps.data.severity === 'critical' ? 'danger' : slotProps.data.severity === 'warning' ? 'warning' : 'success'"
                                :value="slotProps.data.severity === 'critical' ? 'Critical' : slotProps.data.severity === 'warning' ? 'Warning' : 'Normal'"
                                class="px-3 py-1.5 text-sm font-medium rounded-full"
                            />
                        </template>
                    </Column>

                    <Column header="Actions" style="width: 100px">
                        <template #body="slotProps">
                            <Button icon="pi pi-eye" class="p-button-rounded p-button-text p-button-sm" @click="openStudentProfile(slotProps.data)" v-tooltip.top="'View student details'" />
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
                        <p class="text-gray-500 text-sm mb-1">Attendance Rate</p>
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

                <h4 class="font-medium mb-3">Attendance Calendar: {{ selectedSubject?.name }}</h4>
                <div class="calendar-view mb-6 p-4 bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="calendar-header grid grid-cols-7 gap-1 mb-2">
                        <div v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day" class="text-center font-medium text-gray-600 text-xs">
                            {{ day }}
                        </div>
                    </div>

                    <div class="calendar-days grid grid-cols-7 gap-1">
                        <!-- Empty cells for days before the 1st of the month -->
                        <div v-for="i in new Date(currentYear, currentMonth, 1).getDay()" :key="`empty-${i}`" class="h-10 w-10"></div>

                        <!-- Calendar days -->
                        <div
                            v-for="calDay in calendarData"
                            :key="calDay.day"
                            class="calendar-day h-10 w-10 flex items-center justify-center rounded-full transition-all"
                            :class="{
                                'bg-red-100 text-red-800 border border-red-200': calDay.isAbsent,
                                'hover:bg-gray-100': !calDay.isAbsent,
                                'bg-gray-200 text-gray-400': new Date(currentYear, currentMonth, calDay.day).getDay() === 0 || new Date(currentYear, currentMonth, calDay.day).getDay() === 6
                            }"
                        >
                            {{ calDay.day }}
                        </div>
                    </div>
                </div>

                <h4 class="font-medium mb-3">Absence History</h4>
                <div class="absence-history">
                    <div v-if="absentDays.length > 0" class="space-y-3">
                        <div v-for="(day, index) in absentDays" :key="index" class="p-3 border-l-4 border-red-500 bg-red-50 rounded-r-lg flex justify-between items-center">
                            <div class="flex items-center">
                                <i class="pi pi-calendar-times text-red-500 mr-2"></i>
                                <span class="font-medium">{{ formatDate(day) }}</span>
                            </div>
                            <span class="text-gray-500 text-sm px-2 py-1 bg-white rounded-full">Unexcused</span>
                        </div>
                    </div>
                    <div v-else class="flex items-center justify-center p-4 bg-gray-50 rounded-lg border border-gray-200 text-gray-500 italic">
                        <i class="pi pi-check-circle text-green-500 mr-2"></i>
                        No absence records found for this student in this subject.
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-between">
                    <Button label="Close" icon="pi pi-times" @click="studentProfileVisible = false" class="p-button-text" />
                    <div>
                        <Button label="Contact Parent" icon="pi pi-envelope" class="p-button-primary" />
                    </div>
                </div>
            </template>
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
            <h3 class="loading-text">HULATA KAY WA TA NAG DALI</h3>
        </div>
    </div>
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
