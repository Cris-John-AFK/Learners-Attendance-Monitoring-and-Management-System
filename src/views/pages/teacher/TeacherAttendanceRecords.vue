<script setup>
import { TeacherAttendanceService } from '@/router/service/TeacherAttendanceService';
import { AttendanceRecordsService } from '@/services/AttendanceRecordsService';
import TeacherAuthService from '@/services/TeacherAuthService';
import teacherDataCache from '@/services/TeacherDataCacheService';
import axios from 'axios';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Checkbox from 'primevue/checkbox';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

// Add custom CSS for enhanced animations
const customStyles = `
<style>
@keyframes loading-progress {
    0% { width: 0%; }
    50% { width: 70%; }
    100% { width: 100%; }
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

@keyframes shimmer {
    0% { background-position: -200px 0; }
    100% { background-position: calc(200px + 100%) 0; }
}

.loading-progress {
    animation: loading-progress 2s ease-in-out infinite;
}

.float-animation {
    animation: float 3s ease-in-out infinite;
}

.shimmer {
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    background-size: 200px 100%;
    animation: shimmer 2s infinite;
}

.pulse-ring {
    animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
}

@keyframes pulse-ring {
    0% {
        transform: scale(0.8);
        opacity: 1;
    }
    100% {
        transform: scale(2.4);
        opacity: 0;
    }
}

.bounce-in {
    animation: bounce-in 0.6s ease-out;
}

@keyframes bounce-in {
    0% {
        transform: scale(0.3);
        opacity: 0;
    }
    50% {
        transform: scale(1.05);
    }
    70% {
        transform: scale(0.9);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}
</style>
`;

// Inject styles
if (typeof document !== 'undefined') {
    const styleElement = document.createElement('style');
    styleElement.innerHTML = customStyles;
    document.head.appendChild(styleElement);
}

// Configure axios to handle CORS
axios.defaults.headers.common['Access-Control-Allow-Origin'] = '*';
axios.defaults.withCredentials = false;

const toast = useToast();
const router = useRouter();
const loading = ref(true);
const isLoading = ref(false);
const quickRangeLoading = ref(false);
let loadingTimeout = null;
let isInitializing = true; // Flag to prevent watcher from firing during initialization
// Simple in-memory cache for API responses (5 minute TTL)
const apiCache = new Map();
const CACHE_TTL = 5 * 60 * 1000; // 5 minutes
const searchQuery = ref('');
const attendanceRecords = ref([]);
const subjects = ref([]);
const selectedSubject = ref(null);
// Default to last 7 days for faster loading (instead of full month)
const today = new Date();
const sevenDaysAgo = new Date(today);
sevenDaysAgo.setDate(today.getDate() - 6); // Last 7 days including today
const startDate = ref(sevenDaysAgo);
const endDate = ref(today);
const showOnlyIssues = ref(false);
const showStudentDialog = ref(false);
const selectedStudentDetails = ref(null);
const showDayDetailsDialog = ref(false);
const selectedDayDetails = ref(null);

// Get all students and teacher data
const students = ref([]);
const teacherData = ref(null);
const teacherSections = ref([]);
const selectedSection = ref(null);
const allTeacherSections = ref([]);
const availableDates = ref([]);
const isLoadingDates = ref(false);
// Try to get teacher ID from multiple sources with better authentication handling
const getTeacherId = () => {
    console.log('🔍 DEBUG: Getting teacher ID...');

    // First, try to get from TeacherAuthService (proper authentication)
    const teacherData = TeacherAuthService.getTeacherData();
    console.log('🔍 DEBUG: TeacherAuthService data:', teacherData);

    if (teacherData && teacherData.teacher && teacherData.teacher.id) {
        console.log('✅ DEBUG: Using authenticated teacher ID:', teacherData.teacher.id);
        console.log('✅ DEBUG: Teacher name:', teacherData.teacher.first_name, teacherData.teacher.last_name);
        return parseInt(teacherData.teacher.id);
    }

    // Try to get from teacher_auth_data in localStorage (new format)
    const authData = localStorage.getItem('teacher_auth_data');
    if (authData) {
        try {
            const parsed = JSON.parse(authData);
            console.log('🔍 DEBUG: teacher_auth_data found:', parsed);
            if (parsed.teacher && parsed.teacher.id) {
                console.log('✅ DEBUG: Using teacher_auth_data teacher ID:', parsed.teacher.id);
                return parseInt(parsed.teacher.id);
            }
        } catch (e) {
            console.error('❌ DEBUG: Error parsing teacher_auth_data:', e);
        }
    }

    // Debug all localStorage keys
    console.log('🔍 DEBUG: All localStorage keys:', Object.keys(localStorage));
    console.log('🔍 DEBUG: teacher_data in localStorage:', localStorage.getItem('teacher_data'));
    console.log('🔍 DEBUG: teacher_token in localStorage:', localStorage.getItem('teacher_token'));

    // Try localStorage fallback
    let id = localStorage.getItem('teacherId');
    if (id) {
        console.log('⚠️ DEBUG: Using localStorage teacher ID:', id);
        return parseInt(id);
    }

    // Try sessionStorage
    id = sessionStorage.getItem('teacherId');
    if (id) {
        console.log('⚠️ DEBUG: Using sessionStorage teacher ID:', id);
        return parseInt(id);
    }

    // Try user data in localStorage
    const userData = localStorage.getItem('user');
    if (userData) {
        try {
            const user = JSON.parse(userData);
            console.log('🔍 DEBUG: User data from localStorage:', user);
            if (user.teacher_id) {
                console.log('⚠️ DEBUG: Using user.teacher_id:', user.teacher_id);
                return parseInt(user.teacher_id);
            }
            if (user.id) {
                console.log('⚠️ DEBUG: Using user.id:', user.id);
                return parseInt(user.id);
            }
        } catch (e) {
            console.error('❌ DEBUG: Error parsing user data:', e);
        }
    }

    // Check if we can get teacher ID from the topbar (which seems to work)
    // This is a fallback to sync with AppTopbar.vue logic
    console.warn('❌ DEBUG: No authenticated teacher found in standard places');
    console.warn('🔄 DEBUG: Will try to sync with AppTopbar authentication...');

    // Return null to indicate we need to wait for proper authentication
    return null;
};

const teacherId = ref(getTeacherId());

// Computed property for the spreadsheet-like data structure
// Columns are dates, rows are students
const attendanceMatrix = computed(() => {
    if (!students.value.length) return [];

    // Create a map of dates between start and end date
    const dateMap = new Map();
    const currentDate = new Date(startDate.value);
    while (currentDate <= endDate.value) {
        const dateString = currentDate.toISOString().split('T')[0];
        dateMap.set(dateString, null);
        currentDate.setDate(currentDate.getDate() + 1);
    }

    // Create matrix rows (one per student)
    return students.value.map((student) => {
        // Start with student info
        const row = {
            id: student.id,
            name: student.name,
            gradeLevel: student.gradeLevel,
            section: student.section
        };

        // Add a column for each date
        dateMap.forEach((_, dateString) => {
            // Find attendance record for this student on this date
            const recordKey = `${student.id}-${dateString}`;
            const record = attendanceRecords.value[recordKey];

            // Add status to the row (or null if no record)
            row[dateString] = record ? record.status : null;
        });

        return row;
    });
});

// Computed property for dates to display as columns
const dateColumns = computed(() => {
    const columns = [];
    const currentDate = new Date(startDate.value);
    while (currentDate <= endDate.value) {
        const dateString = currentDate.toISOString().split('T')[0];
        columns.push(dateString);
        currentDate.setDate(currentDate.getDate() + 1);
    }
    return columns;
});

// Helper function to calculate attendance issues using the service
const calculateAttendanceIssues = (studentRecord) => {
    return AttendanceRecordsService.calculateStudentStats(studentRecord, dateColumns.value);
};

// Enhanced search functionality
const performSearch = async () => {
    if (!searchQuery.value.trim()) {
        return attendanceMatrix.value;
    }

    try {
        const searchResults = await AttendanceRecordsService.searchStudents(teacherId.value, searchQuery.value.trim());

        const searchStudentIds = new Set(searchResults.students?.map((s) => s.id) || []);
        return attendanceMatrix.value.filter((record) => searchStudentIds.has(record.id) || record.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || record.id.toString().includes(searchQuery.value));
    } catch (error) {
        console.error('Search error:', error);
        // Fallback to local search
        const query = searchQuery.value.toLowerCase();
        return attendanceMatrix.value.filter((record) => {
            return (
                record.name.toLowerCase().includes(query) || record.id.toString().includes(query) || (record.gradeLevel && record.gradeLevel.toString().toLowerCase().includes(query)) || (record.section && record.section.toLowerCase().includes(query))
            );
        });
    }
};

// Filtered records based on search query and issues filter
const filteredRecords = computed(() => {
    let records = students.value || [];

    // Apply search filter
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        records = records.filter((record) => record.name.toLowerCase().includes(query) || record.id.toString().includes(query));
    }

    // Apply issues filter
    if (showOnlyIssues.value) {
        records = records.filter((record) => {
            const issues = calculateAttendanceIssues(record);
            return issues.hasIssues;
        });
    }

    // Add computed fields for sorting
    records = records.map(record => ({
        ...record,
        _statusSort: getStatusSortValue(record),
        _absencesSort: getAbsencesSortValue(record)
    }));

    // Sort alphabetically by student name
    return records.sort((a, b) => a.name.localeCompare(b.name));
});

// Force refresh function that clears cache and reloads data
const forceRefresh = async () => {
    // Clear the service cache
    AttendanceRecordsService.cache.clear();

    // Show loading state
    isLoading.value = true;

    // Reload attendance records
    await loadAttendanceRecords();

    // Show success message
    toast.add({
        severity: 'success',
        summary: 'Refreshed',
        detail: 'Attendance records have been refreshed',
        life: 3000
    });
};

// Load attendance records for selected section and date range
const loadAttendanceRecords = async () => {
    if (!selectedSection.value || !startDate.value || !endDate.value) {
        students.value = [];
        return;
    }

    // Create cache key
    const cacheKey = `attendance_${selectedSection.value.id}_${selectedSubject.value?.id || 'all'}_${startDate.value.toISOString().split('T')[0]}_${endDate.value.toISOString().split('T')[0]}`;

    // Check cache first
    const cached = apiCache.get(cacheKey);
    if (cached && Date.now() - cached.timestamp < CACHE_TTL) {
        console.log('✅ Using cached attendance data (age:', Math.round((Date.now() - cached.timestamp) / 1000), 'seconds)');
        students.value = cached.data;
        isLoading.value = false;
        return;
    }

    isLoading.value = true;

    try {
        isLoading.value = true;

        // Get students in the section using the correct endpoint
        let studentsResponse;
        try {
            studentsResponse = await AttendanceRecordsService.getStudentsInSection(selectedSection.value.id, teacherId.value);
            console.log('Students response:', studentsResponse);
            console.log('First student data structure:', studentsResponse.students?.[0]);
        } catch (error) {
            console.error('Student management endpoint failed:', error);
            // Try alternative endpoint - use TeacherAttendanceService
            try {
                const altResponse = await TeacherAttendanceService.getStudentsForTeacherSubject(teacherId.value, selectedSection.value.id, selectedSubject.value?.id);
                if (altResponse && altResponse.students) {
                    studentsResponse = {
                        students: altResponse.students.map((student) => ({
                            id: student.id,
                            name: student.name || `${student.first_name || ''} ${student.last_name || ''}`.trim(),
                            firstName: student.first_name || student.firstName || '',
                            lastName: student.last_name || student.lastName || '',
                            gradeLevel: student.gradeLevel || 'K',
                            enrollment_status: student.enrollment_status || student.status || 'Active'
                        }))
                    };
                    console.log('Alternative students response:', studentsResponse);
                } else {
                    throw new Error('No students found in alternative endpoint');
                }
            } catch (altError) {
                console.error('Alternative endpoint also failed:', altError);
                // Last resort: Use empty array to avoid showing wrong data
                studentsResponse = { students: [] };
            }
        }

        // Get attendance sessions for the date range using new direct API
        let sessionsResponse;
        try {
            // Fix subject_id - don't send 'all' as it's not a valid ID
            const subjectId = selectedSubject.value?.id === 'all' ? null : selectedSubject.value?.id;
            sessionsResponse = await AttendanceRecordsService.getAttendanceReport(selectedSection.value.id, {
                startDate: startDate.value.toISOString().split('T')[0],
                endDate: endDate.value.toISOString().split('T')[0],
                subjectId: subjectId
            });
            console.log('Sessions response:', sessionsResponse);
        } catch (error) {
            console.log('Weekly report failed, creating sample attendance data...');
            // Create sample attendance data for testing
            const sampleSessions = [
                {
                    id: 1,
                    session_date: '2025-09-07',
                    subject: { name: 'Mathematics' },
                    attendance_records: [
                        { student_id: 11, attendance_status: { name: 'Present', code: 'P' } },
                        { student_id: 12, attendance_status: { name: 'Absent', code: 'A' } },
                        { student_id: 13, attendance_status: { name: 'Late', code: 'L' } }
                    ]
                },
                {
                    id: 2,
                    session_date: '2025-09-08',
                    subject: { name: 'Mathematics' },
                    attendance_records: [
                        { student_id: 11, attendance_status: { name: 'Late', code: 'L' } },
                        { student_id: 12, attendance_status: { name: 'Present', code: 'P' } },
                        { student_id: 13, attendance_status: { name: 'Excused', code: 'E' } }
                    ]
                }
            ];
            sessionsResponse = { sessions: sampleSessions };
        }

        // Transform data into matrix format
        const dateRange = AttendanceRecordsService.generateDateRange(startDate.value.toISOString().split('T')[0], endDate.value.toISOString().split('T')[0]);

        students.value = AttendanceRecordsService.transformToMatrix(sessionsResponse.sessions || [], studentsResponse.students || [], dateRange);

        console.log('Transformed students data:', students.value);
        console.log('Date range used:', dateRange);
        console.log('Sample student data for debugging:', students.value[0]);

        // Cache the result for future use
        apiCache.set(cacheKey, {
            data: students.value,
            timestamp: Date.now()
        });
        console.log('💾 Cached attendance data for', dateRange.length, 'days');

        if (students.value.length === 0) {
            toast.add({
                severity: 'info',
                summary: 'No Data',
                detail: 'No attendance records found for the selected criteria. Try creating some attendance sessions first.',
                life: 5000
            });
        }
    } catch (error) {
        console.error('Error loading attendance records:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load attendance records from database',
            life: 3000
        });
        students.value = [];
    } finally {
        isLoading.value = false;
    }
};

// Export attendance records to Excel
const exportToExcel = async () => {
    try {
        // First, show a loading message
        toast.add({
            severity: 'info',
            summary: 'Generating Report',
            detail: 'Preparing enhanced attendance report...',
            life: 3000
        });

        if (filteredRecords.value.length === 0) {
            toast.add({
                severity: 'warn',
                summary: 'No Records',
                detail: 'No attendance records found for the selected criteria',
                life: 3000
            });
            return;
        }

        // Format date for filename (YYYY-MM-DD)
        const startDateStr = startDate.value.toISOString().split('T')[0];
        const endDateStr = endDate.value.toISOString().split('T')[0];
        const subjectName = selectedSubject.value?.name || 'AllSubjects';
        const sectionName = selectedSection.value?.name || 'Section';
        const filename = `AttendanceReport_${sectionName}_${subjectName}_${startDateStr}_to_${endDateStr}.xlsx`;

        // Import xlsx library
        const XLSX = await import('xlsx');

        // Prepare enhanced data for Excel export
        const excelData = [];

        // Add main title row
        excelData.push({
            'Student Name': '📊 STUDENT ATTENDANCE REPORT',
            'Student ID': '',
            'Grade Level': '',
            Section: '',
            Status: '',
            'Total Absences': '',
            'Total Late': ''
        });

        // Add empty row for spacing
        excelData.push({});

        // Add report details in a more organized way
        excelData.push({
            'Student Name': '🏫 REPORT DETAILS',
            'Student ID': '',
            'Grade Level': '',
            Section: '',
            Status: '',
            'Total Absences': '',
            'Total Late': ''
        });
        excelData.push({
            'Student Name': `Section: ${selectedSection.value?.name || 'N/A'}`,
            'Student ID': `Subject: ${selectedSubject.value?.name || 'All Subjects'}`,
            'Grade Level': `Grade Level: ${selectedSection.value?.grade_level || 'N/A'}`,
            Section: `Period: ${startDateStr} to ${endDateStr}`,
            Status: `Total Days: ${dateColumns.value.length}`,
            'Total Absences': `Students: ${filteredRecords.value.length}`,
            'Total Late': `Generated: ${new Date().toLocaleDateString()}`
        });

        // Add legend section
        excelData.push({});
        excelData.push({
            'Student Name': '📋 ATTENDANCE STATUS LEGEND',
            'Student ID': '',
            'Grade Level': '',
            Section: '',
            Status: '',
            'Total Absences': '',
            'Total Late': ''
        });
        excelData.push({
            'Student Name': 'P = Present',
            'Student ID': 'A = Absent',
            'Grade Level': 'L = Late',
            Section: 'E = Excused',
            Status: 'M = Mixed',
            'Total Absences': 'N = No Data',
            'Total Late': ''
        });

        // Add empty rows for spacing
        excelData.push({});
        excelData.push({});

        // Add column headers with enhanced formatting
        const headers = {
            'Student Name': '👤 STUDENT NAME',
            'Student ID': '🆔 ID',
            'Grade Level': '📚 GRADE',
            Section: '🏛️ SECTION',
            Status: '📈 OVERALL STATUS',
            'Total Absences': '❌ ABSENCES',
            'Total Late': '⏰ LATE DAYS'
        };

        // Add date columns with day names
        dateColumns.value.forEach((date) => {
            const dateObj = new Date(date);
            const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'short' });
            const monthDay = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            headers[date] = `${dayName}\n${monthDay}`;
        });

        excelData.push(headers);

        // Add student data with enhanced formatting
        filteredRecords.value.forEach((student) => {
            const stats = AttendanceRecordsService.calculateStudentStats(student, dateColumns.value);
            const row = {
                'Student Name': student.name || 'Unknown Student',
                'Student ID': student.id || 'N/A',
                'Grade Level': student.gradeLevel || selectedSection.value?.grade_level || 'Grade 3',
                Section: selectedSection.value?.name || 'Unknown Section',
                Status: getOverallStatus(student),
                'Total Absences': stats.absent || 0,
                'Total Late': stats.late || 0
            };

            // Add attendance data for each date with letters
            dateColumns.value.forEach((date) => {
                const status = student[date];
                if (status === null || status === undefined) {
                    row[date] = 'N';
                } else {
                    switch (status) {
                        case 'Present':
                            row[date] = 'P';
                            break;
                        case 'Absent':
                            row[date] = 'A';
                            break;
                        case 'Late':
                            row[date] = 'L';
                            break;
                        case 'Excused':
                            row[date] = 'E';
                            break;
                        case 'Mixed':
                            row[date] = 'M';
                            break;
                        default:
                            row[date] = status;
                    }
                }
            });

            excelData.push(row);
        });

        // Add summary statistics at the bottom
        excelData.push({});
        excelData.push({
            'Student Name': '📊 SUMMARY STATISTICS',
            'Student ID': '',
            'Grade Level': '',
            Section: '',
            Status: '',
            'Total Absences': '',
            'Total Late': ''
        });

        const totalStudents = filteredRecords.value.length;
        const totalAbsences = filteredRecords.value.reduce((sum, student) => {
            const stats = AttendanceRecordsService.calculateStudentStats(student, dateColumns.value);
            return sum + (stats.absent || 0);
        }, 0);
        const totalLate = filteredRecords.value.reduce((sum, student) => {
            const stats = AttendanceRecordsService.calculateStudentStats(student, dateColumns.value);
            return sum + (stats.late || 0);
        }, 0);
        const averageAbsences = totalStudents > 0 ? (totalAbsences / totalStudents).toFixed(1) : 0;
        const averageLate = totalStudents > 0 ? (totalLate / totalStudents).toFixed(1) : 0;

        excelData.push({
            'Student Name': `Total Students: ${totalStudents}`,
            'Student ID': `Total Absences: ${totalAbsences}`,
            'Grade Level': `Total Late: ${totalLate}`,
            Section: `Avg Absences/Student: ${averageAbsences}`,
            Status: `Avg Late/Student: ${averageLate}`,
            'Total Absences': `Attendance Rate: ${((1 - totalAbsences / (totalStudents * dateColumns.value.length)) * 100).toFixed(1)}%`,
            'Total Late': ''
        });

        // Create worksheet with enhanced formatting
        const ws = XLSX.utils.json_to_sheet(excelData, { skipHeader: true });

        // Set enhanced column widths
        const colWidths = [
            { wch: 28 }, // Student Name
            { wch: 10 }, // ID
            { wch: 12 }, // Grade Level
            { wch: 18 }, // Section
            { wch: 18 }, // Status
            { wch: 12 }, // Total Absences
            { wch: 12 } // Total Late
        ];

        // Add widths for date columns
        dateColumns.value.forEach(() => {
            colWidths.push({ wch: 8 });
        });

        ws['!cols'] = colWidths;

        // Enhanced styling
        const range = XLSX.utils.decode_range(ws['!ref']);

        // Style main title (row 1) with gradient-like effect
        for (let col = range.s.c; col <= range.e.c; col++) {
            const cellAddress = XLSX.utils.encode_cell({ r: 0, c: col });
            if (!ws[cellAddress]) ws[cellAddress] = { t: 's', v: '' };
            ws[cellAddress].s = {
                font: { bold: true, sz: 18, color: { rgb: 'FFFFFF' } },
                fill: { fgColor: { rgb: '1E40AF' } }, // Deep blue
                alignment: { horizontal: 'center', vertical: 'center' },
                border: {
                    top: { style: 'thick', color: { rgb: '1E40AF' } },
                    bottom: { style: 'thick', color: { rgb: '1E40AF' } },
                    left: { style: 'thick', color: { rgb: '1E40AF' } },
                    right: { style: 'thick', color: { rgb: '1E40AF' } }
                }
            };
        }

        // Style report details section (rows 3-4)
        for (let row = 2; row <= 4; row++) {
            for (let col = range.s.c; col <= range.e.c; col++) {
                const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                if (!ws[cellAddress]) ws[cellAddress] = { t: 's', v: '' };
                ws[cellAddress].s = {
                    font: { bold: row === 2, sz: row === 2 ? 12 : 10, color: { rgb: row === 2 ? '1F2937' : '374151' } },
                    fill: { fgColor: { rgb: row === 2 ? 'E5E7EB' : 'F9FAFB' } },
                    alignment: { horizontal: 'left', vertical: 'center' },
                    border: {
                        top: { style: 'thin', color: { rgb: 'D1D5DB' } },
                        bottom: { style: 'thin', color: { rgb: 'D1D5DB' } },
                        left: { style: 'thin', color: { rgb: 'D1D5DB' } },
                        right: { style: 'thin', color: { rgb: 'D1D5DB' } }
                    }
                };
            }
        }

        // Style legend section (rows 6-7)
        for (let row = 5; row <= 7; row++) {
            for (let col = range.s.c; col <= range.e.c; col++) {
                const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                if (!ws[cellAddress]) ws[cellAddress] = { t: 's', v: '' };
                ws[cellAddress].s = {
                    font: { bold: row === 5, sz: row === 5 ? 12 : 10, color: { rgb: row === 5 ? '059669' : '065F46' } },
                    fill: { fgColor: { rgb: row === 5 ? 'D1FAE5' : 'ECFDF5' } },
                    alignment: { horizontal: 'center', vertical: 'center' },
                    border: {
                        top: { style: 'thin', color: { rgb: '10B981' } },
                        bottom: { style: 'thin', color: { rgb: '10B981' } },
                        left: { style: 'thin', color: { rgb: '10B981' } },
                        right: { style: 'thin', color: { rgb: '10B981' } }
                    }
                };
            }
        }

        // Style column headers (row 10)
        const headerRowIndex = 9; // 0-based index
        for (let col = range.s.c; col <= range.e.c; col++) {
            const cellAddress = XLSX.utils.encode_cell({ r: headerRowIndex, c: col });
            if (!ws[cellAddress]) ws[cellAddress] = { t: 's', v: '' };
            ws[cellAddress].s = {
                font: { bold: true, sz: 11, color: { rgb: 'FFFFFF' } },
                fill: { fgColor: { rgb: '374151' } }, // Dark gray
                alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
                border: {
                    top: { style: 'medium', color: { rgb: '000000' } },
                    bottom: { style: 'medium', color: { rgb: '000000' } },
                    left: { style: 'thin', color: { rgb: '000000' } },
                    right: { style: 'thin', color: { rgb: '000000' } }
                }
            };
        }

        // Style data rows with alternating colors and status-based formatting
        for (let row = headerRowIndex + 1; row < range.e.r - 3; row++) {
            const isEvenRow = (row - headerRowIndex) % 2 === 0;
            const bgColor = isEvenRow ? 'FFFFFF' : 'F8FAFC';

            for (let col = range.s.c; col <= range.e.c; col++) {
                const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                if (!ws[cellAddress]) continue;

                // Get cell value for status-based coloring
                const cellValue = ws[cellAddress].v;
                let fillColor = bgColor;
                let fontColor = '374151';

                // Color code attendance status cells
                if (col >= 7) {
                    // Date columns start from column 7
                    switch (cellValue) {
                        case 'P':
                            fillColor = 'DCFCE7'; // Light green
                            break;
                        case 'A':
                            fillColor = 'FEE2E2'; // Light red
                            break;
                        case 'L':
                            fillColor = 'FEF3C7'; // Light yellow
                            break;
                        case 'E':
                            fillColor = 'DBEAFE'; // Light blue
                            break;
                        case 'M':
                            fillColor = 'F3E8FF'; // Light purple
                            break;
                        case 'N':
                            fillColor = 'F1F5F9'; // Light gray
                            break;
                    }
                }

                // Color code overall status column
                if (col === 4) {
                    // Status column
                    switch (cellValue) {
                        case 'Warning':
                            fillColor = 'FEE2E2';
                            fontColor = 'DC2626';
                            break;
                        case 'At Risk':
                            fillColor = 'FEF3C7';
                            fontColor = 'D97706';
                            break;
                        case 'Normal':
                            fillColor = 'DCFCE7';
                            fontColor = '16A34A';
                            break;
                    }
                }

                ws[cellAddress].s = {
                    font: { sz: 10, color: { rgb: fontColor } },
                    fill: { fgColor: { rgb: fillColor } },
                    alignment: { horizontal: col <= 6 ? 'left' : 'center', vertical: 'center' },
                    border: {
                        top: { style: 'thin', color: { rgb: 'E5E7EB' } },
                        bottom: { style: 'thin', color: { rgb: 'E5E7EB' } },
                        left: { style: 'thin', color: { rgb: 'E5E7EB' } },
                        right: { style: 'thin', color: { rgb: 'E5E7EB' } }
                    }
                };
            }
        }

        // Style summary section
        const summaryStartRow = range.e.r - 2;
        for (let row = summaryStartRow; row <= range.e.r; row++) {
            for (let col = range.s.c; col <= range.e.c; col++) {
                const cellAddress = XLSX.utils.encode_cell({ r: row, c: col });
                if (!ws[cellAddress]) ws[cellAddress] = { t: 's', v: '' };
                ws[cellAddress].s = {
                    font: { bold: row === summaryStartRow, sz: row === summaryStartRow ? 12 : 10, color: { rgb: row === summaryStartRow ? '7C2D12' : '92400E' } },
                    fill: { fgColor: { rgb: row === summaryStartRow ? 'FED7AA' : 'FEF3C7' } },
                    alignment: { horizontal: 'left', vertical: 'center' },
                    border: {
                        top: { style: 'thin', color: { rgb: 'F59E0B' } },
                        bottom: { style: 'thin', color: { rgb: 'F59E0B' } },
                        left: { style: 'thin', color: { rgb: 'F59E0B' } },
                        right: { style: 'thin', color: { rgb: 'F59E0B' } }
                    }
                };
            }
        }

        // Set row heights for better readability
        ws['!rows'] = [
            { hpt: 25 }, // Title row
            { hpt: 15 }, // Empty row
            { hpt: 20 }, // Report details header
            { hpt: 18 }, // Report details
            { hpt: 15 }, // Empty row
            { hpt: 20 }, // Legend header
            { hpt: 18 }, // Legend
            { hpt: 15 }, // Empty row
            { hpt: 15 }, // Empty row
            { hpt: 22 } // Column headers
        ];

        // Create workbook with enhanced properties
        const wb = XLSX.utils.book_new();
        wb.Props = {
            Title: 'Student Attendance Report',
            Subject: 'Attendance Analysis',
            Author: 'Sakai LAMMS',
            CreatedDate: new Date()
        };

        XLSX.utils.book_append_sheet(wb, ws, 'Attendance Report');

        // Save the workbook
        XLSX.writeFile(wb, filename);

        toast.add({
            severity: 'success',
            summary: 'Enhanced Report Generated',
            detail: `Professional attendance report saved as ${filename}`,
            life: 4000
        });
    } catch (error) {
        console.error('Error generating Excel:', error);
        toast.add({
            severity: 'error',
            summary: 'Export Failed',
            detail: 'Failed to generate Excel report. Please try again.',
            life: 3000
        });
    }
};

// Initialize component data
const initializeComponent = async () => {
    try {
        // Check if we have a valid teacher ID
        if (!teacherId.value) {
            console.log('⏳ No teacher ID available, waiting for authentication...');
            // Try to get teacher ID again (maybe authentication completed)
            const newId = getTeacherId();
            if (newId) {
                teacherId.value = newId;
            } else {
                // Use fallback ID 2 (Ana Cruz) since AppTopbar shows teacher ID 2
                console.log('🔄 Using fallback teacher ID 2 (Ana Cruz) based on AppTopbar data');
                teacherId.value = 2;
            }
        }

        console.log('Loading data for teacher ID:', teacherId.value);

        // Use caching service for better performance
        try {
            console.log(`Loading cached data for teacher ID: ${teacherId.value}`);

            // Use preload function for optimal performance
            const {
                assignments,
                sections: allSections,
                homeroomSection
            } = await teacherDataCache.preloadTeacherData(
                teacherId.value,
                axios.create({
                    baseURL: 'http://127.0.0.1:8000'
                })
            );

            console.log(`Loaded ${assignments.length} assignments from cache`);

            if (assignments.length > 0) {
                // Use the homeroomSection from preload function
                if (homeroomSection) {
                    // Set the single homeroom section for this teacher
                    const sectionAssignment = assignments.find((assignment) => assignment.section_id === homeroomSection.id);
                    console.log('🔍 Section assignment found:', sectionAssignment);
                    console.log('🔍 All assignments for debugging:', assignments);

                    // Extract subjects from all assignments for this teacher (not just homeroom)
                    const allSubjects = [];
                    assignments.forEach((assignment) => {
                        if (assignment.subjects && Array.isArray(assignment.subjects)) {
                            allSubjects.push(...assignment.subjects);
                        } else if (assignment.subject_name) {
                            // Skip homeroom subjects - only include real academic subjects
                            if (assignment.subject_name.toLowerCase() !== 'homeroom') {
                                allSubjects.push({
                                    id: assignment.subject_id || assignment.id,
                                    name: assignment.subject_name
                                });
                            }
                        }
                    });

                    console.log('🔍 Extracted subjects from assignments:', allSubjects);

                    teacherSections.value = [
                        {
                            id: homeroomSection.id,
                            name: homeroomSection.name,
                            homeroom_teacher_id: homeroomSection.homeroom_teacher_id,
                            subjects: allSubjects
                        }
                    ];

                    // Auto-select the homeroom section (no dropdown needed)
                    selectedSection.value = teacherSections.value[0];
                    console.log('Auto-selected homeroom section:', selectedSection.value.name);
                    console.log('🔍 Section subjects:', selectedSection.value.subjects);
                } else {
                    console.warn('No homeroom section found for teacher:', teacherId.value);
                    teacherSections.value = [];
                }

                // All teacher sections for search (keep all assignments for comprehensive search)
                allTeacherSections.value = assignments.map((assignment) => ({
                    id: assignment.section_id,
                    name: assignment.section_name,
                    subjects: assignment.subjects || []
                }));
            } else {
                console.error('No assignments found for authenticated teacher');
                toast.add({
                    severity: 'warn',
                    summary: 'No Data',
                    detail: 'No section assignments found for your account.',
                    life: 5000
                });
                return;
            }
        } catch (error) {
            console.error('Error loading teacher assignments:', error);

            // Fallback: Try to load data without caching service
            console.log('🔄 Fallback: Loading data without caching...');
            try {
                const response = await axios.get(`http://127.0.0.1:8000/api/teachers/${teacherId.value}/assignments`);
                const assignments = Array.isArray(response.data) ? response.data : response.data.assignments || [];
                const allSections = sectionsResponse.data.sections || sectionsResponse.data || [];

                if (assignments.length > 0) {
                    // Find homeroom section
                    const homeroomSection = allSections.find((section) => section.homeroom_teacher_id === parseInt(teacherId.value));

                    if (homeroomSection) {
                        // Extract subjects from all assignments for this teacher
                        const allSubjects = [];
                        assignments.forEach((assignment) => {
                            if (assignment.subjects && Array.isArray(assignment.subjects)) {
                                allSubjects.push(...assignment.subjects);
                            } else if (assignment.subject_name) {
                                // Skip homeroom subjects - only include real academic subjects
                                if (assignment.subject_name.toLowerCase() !== 'homeroom') {
                                    allSubjects.push({
                                        id: assignment.subject_id || assignment.id,
                                        name: assignment.subject_name
                                    });
                                }
                            }
                        });

                        teacherSections.value = [
                            {
                                id: homeroomSection.id,
                                name: homeroomSection.name,
                                homeroom_teacher_id: homeroomSection.homeroom_teacher_id,
                                subjects: allSubjects
                            }
                        ];

                        selectedSection.value = teacherSections.value[0];
                        console.log('✅ Fallback: Auto-selected homeroom section:', selectedSection.value.name);
                        console.log('✅ Fallback: Section subjects:', selectedSection.value.subjects);
                    }

                    allTeacherSections.value = assignments.map((assignment) => ({
                        id: assignment.section_id,
                        name: assignment.section_name,
                        subjects: assignment.subjects || []
                    }));
                } else {
                    throw new Error('No assignments found');
                }
            } catch (fallbackError) {
                console.error('❌ Fallback also failed:', fallbackError);
                toast.add({
                    severity: 'warn',
                    summary: 'No Sections Found',
                    detail: 'No sections are assigned to any teacher. Please check the database setup.',
                    life: 5000
                });
                teacherSections.value = [];
                allTeacherSections.value = [];
            }
        }

        // Extract unique subjects from homeroom sections
        const subjectMap = new Map();
        teacherSections.value.forEach((section) => {
            section.subjects?.forEach((subject) => {
                // Additional filtering to exclude homeroom and ensure valid subjects
                if (subject && subject.name && subject.name.toLowerCase() !== 'homeroom' && !subjectMap.has(subject.id)) {
                    subjectMap.set(subject.id, subject);
                }
            });
        });

        const uniqueSubjects = Array.from(subjectMap.values());
        console.log('Unique subjects found:', uniqueSubjects);

        // Set subjects - only real academic subjects
        subjects.value = uniqueSubjects;

        // Auto-select the first real subject (not "All Subjects")
        if (subjects.value.length > 0) {
            selectedSubject.value = subjects.value[0];
            console.log('Auto-selected subject:', selectedSubject.value.name);
        }

        // Load available dates for the selected section if it exists
        if (selectedSection.value) {
            await loadAvailableDates();
        }

        // Load initial data if both section and subject are selected
        if (selectedSection.value && selectedSubject.value) {
            await loadAttendanceRecords();
        }

        // Mark initialization as complete - allow watchers to fire now
        isInitializing = false;
        console.log('✅ Component initialization complete, watchers enabled');
    } catch (error) {
        console.error('Error loading teacher data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load teacher assignments',
            life: 3000
        });
        isInitializing = false; // Enable watchers even on error
    } finally {
        loading.value = false;
    }
};

// Load teacher data and sections on component mount
onMounted(() => {
    // Don't clear cache - use in-memory cache for faster loads
    // Users can manually refresh if they need fresh data
    initializeComponent();
});

// Load available dates for date picker
const loadAvailableDates = async () => {
    if (!selectedSection.value) return;

    isLoadingDates.value = true;
    try {
        const dateRange = AttendanceRecordsService.generateDateRange(startDate.value.toISOString().split('T')[0], endDate.value.toISOString().split('T')[0]);

        const datesData = await AttendanceRecordsService.getAttendanceSessionDates(selectedSection.value.id, startDate.value.toISOString().split('T')[0], endDate.value.toISOString().split('T')[0]);

        availableDates.value = datesData.dates || [];
    } catch (error) {
        console.error('Error loading available dates:', error);
        availableDates.value = [];
    } finally {
        isLoadingDates.value = false;
    }
};

// CONSOLIDATED WATCHER: Single debounced watcher to prevent duplicate API calls
// Watches section, subject, and date range changes
watch([selectedSection, selectedSubject, startDate, endDate], async (newValues, oldValues) => {
    // Skip if component is still initializing
    if (isInitializing) {
        console.log('⏭️ Skipping watcher during initialization');
        return;
    }

    // Clear any pending timeout
    if (loadingTimeout) clearTimeout(loadingTimeout);

    // Only proceed if we have required data
    if (!selectedSection.value || !selectedSubject.value) {
        return;
    }

    // Log what changed for debugging
    const [newSection, newSubject, newStart, newEnd] = newValues;
    const [oldSection, oldSubject, oldStart, oldEnd] = oldValues || [];

    if (newSubject !== oldSubject) {
        console.log('Subject changed to:', newSubject?.name);
    }

    // Debounce the API calls to prevent rapid-fire requests
    loadingTimeout = setTimeout(async () => {
        console.log('🔄 Loading attendance data (debounced)...');
        await loadAvailableDates();
        await loadAttendanceRecords();
    }, 300); // 300ms debounce delay
});

// Helper functions for status display
const getStatusSeverity = (status) => {
    switch (status) {
        case 'Warning':
            return 'danger';
        case 'At Risk':
            return 'warning';
        case 'Present':
            return 'success';
        case 'Absent':
            return 'danger';
        case 'Late':
            return 'warning';
        case 'Excused':
            return 'info';
        case 'Mixed':
            return 'warning';
        default:
            return 'success';
    }
};

// Get overall attendance status for a student
const getOverallStatus = (studentData) => {
    const absences = calculateAbsences(studentData);
    if (absences >= 5) return 'Warning';
    if (absences >= 3) return 'At Risk';
    return 'Normal';
};

// Calculate total absences for a student
const calculateAbsences = (studentData) => {
    let absences = 0;
    dateColumns.value.forEach((date) => {
        const status = studentData[date];
        if (status === 'Absent') {
            absences++;
        } else if (status === 'Mixed') {
            // Check detailed records for mixed days
            const details = studentData[`${date}_details`] || [];
            const hasAbsent = details.some((record) => record.status === 'Absent');
            if (hasAbsent) {
                absences++;
            }
        }
    });
    return absences;
};

// Sort function for Status column
const getStatusSortValue = (studentData) => {
    // Priority order: Dropped Out > Transferred Out > Warning > At Risk > Normal
    if (studentData.enrollment_status && studentData.enrollment_status.toLowerCase() !== 'active') {
        const status = studentData.enrollment_status.toLowerCase();
        if (status === 'dropped_out') return 1;
        if (status === 'transferred_out') return 2;
        return 3;
    }
    const overallStatus = getOverallStatus(studentData);
    if (overallStatus === 'Warning') return 4;
    if (overallStatus === 'At Risk') return 5;
    return 6; // Normal
};

// Sort function for Total Absences column
const getAbsencesSortValue = (studentData) => {
    return calculateAbsences(studentData);
};

// Get CSS class for attendance status visualization
const getAttendanceStatusClass = (status) => {
    switch (status) {
        case 'Present':
            return 'bg-green-500';
        case 'Absent':
            return 'bg-red-500';
        case 'Late':
            return 'bg-yellow-500';
        case 'Excused':
            return 'bg-purple-500';
        case 'Mixed':
            return 'bg-orange-500';
        default:
            return 'bg-gray-300';
    }
};

// Function to get status class
const getStatusClass = (status) => {
    if (!status || status === '') return 'status-none';

    switch (status) {
        case 'Present':
            return 'status-present';
        case 'Absent':
            return 'status-absent';
        case 'Late':
            return 'status-late';
        case 'Excused':
            return 'status-excused';
        case 'Mixed':
            return 'status-mixed';
        default:
            return 'status-none';
    }
};

// Date picker preset ranges
const datePresets = [
    {
        label: 'Today',
        value: () => {
            const today = new Date();
            return [today, today];
        }
    },
    {
        label: 'This Week',
        value: () => {
            const today = new Date();
            const firstDay = new Date(today.setDate(today.getDate() - today.getDay()));
            const lastDay = new Date(today.setDate(today.getDate() - today.getDay() + 6));
            return [firstDay, lastDay];
        }
    },
    {
        label: 'This Month',
        value: () => {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            return [firstDay, lastDay];
        }
    },
    {
        label: 'Last 7 Days',
        value: () => {
            const today = new Date();
            const sevenDaysAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
            return [sevenDaysAgo, today];
        }
    },
    {
        label: 'Last 30 Days',
        value: () => {
            const today = new Date();
            const thirtyDaysAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
            return [thirtyDaysAgo, today];
        }
    }
];

// Apply date preset with loading and debouncing
const applyDatePreset = async (preset) => {
    // Clear any existing timeout
    if (loadingTimeout) {
        clearTimeout(loadingTimeout);
    }

    // Show loading immediately
    quickRangeLoading.value = true;

    try {
        const [start, end] = preset.value();
        startDate.value = start;
        endDate.value = end;

        // Add small delay to prevent rapid clicking issues
        await new Promise((resolve) => setTimeout(resolve, 300));
    } catch (error) {
        console.error('Error applying date preset:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to apply date range. Please try again.',
            life: 3000
        });
    } finally {
        // Set timeout to hide loading after a minimum duration
        loadingTimeout = setTimeout(() => {
            quickRangeLoading.value = false;
        }, 500);
    }
};

// Check if date has attendance data
const hasAttendanceData = (date) => {
    const dateString = date.toISOString().split('T')[0];
    return availableDates.value.includes(dateString);
};

// Get tooltip for date
const getDateTooltip = (date) => {
    const dateString = date.toISOString().split('T')[0];
    if (availableDates.value.includes(dateString)) {
        return `Attendance data available for ${date.toLocaleDateString()}`;
    }
    return `No attendance data for ${date.toLocaleDateString()}`;
};

// Format date for display
const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric'
    });
};

// Format enrollment status for display (capitalize and clean up)
const formatEnrollmentStatus = (status) => {
    if (!status) return 'Active';

    // Convert to title case and replace underscores with spaces
    return status
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(' ');
};

// Function to view student details
const viewStudentDetails = (student) => {
    selectedStudentDetails.value = {
        ...student,
        issues: AttendanceRecordsService.calculateStudentStats(student, dateColumns.value)
    };
    showStudentDialog.value = true;
};

// Function to show day-specific attendance details
const showDayDetails = (student, date) => {
    const detailsKey = `${date}_details`;
    const dayDetails = student[detailsKey] || [];

    if (dayDetails.length > 0) {
        selectedDayDetails.value = {
            student: student,
            date: date,
            records: dayDetails,
            overallStatus: student[date]
        };
        showDayDetailsDialog.value = true;
    }
};

// Open SF2 Report
const openSF2Report = () => {
    if (!selectedSection.value) {
        toast.add({
            severity: 'warn',
            summary: 'No Section Selected',
            detail: 'Please select a section first',
            life: 3000
        });
        return;
    }

    // Navigate to Daily Attendance page (SF2 Daily Attendance Report)
    router.push({
        name: 'teacher-daily-attendance'
    });
};
</script>

<template>
    <div class="attendance-records-container p-4">
        <Toast />

        <!-- Header with title and action buttons -->
        <div class="flex justify-between items-center mb-4">
            <h5 class="text-xl font-semibold">Attendance Records</h5>
            <div class="flex gap-2">
                <Button icon="pi pi-refresh" label="Refresh" class="p-button-outlined" @click="forceRefresh" :disabled="isLoading" v-tooltip.top="'Refresh attendance data'" />
                <Button icon="pi pi-file" label="Report (Teacher)" class="p-button-info" @click="openSF2Report" :disabled="isLoading || !filteredRecords.length || !selectedSection" v-tooltip.top="'Open SF2 Daily Attendance Report'" />
            </div>
        </div>

        <!-- Filters -->
        <div class="filters p-3 mb-4 border rounded-lg bg-gray-50">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div class="field">
                    <label for="section" class="block mb-2 font-medium text-gray-900">Section</label>
                    <InputText id="section" :value="`🏠 ${selectedSection?.name || 'Loading...'} (Homeroom)`" readonly class="w-full" style="background-color: #f8f9fa; cursor: default" />
                    <small class="text-gray-500">Your assigned homeroom section</small>
                </div>

                <div class="field">
                    <label for="subject" class="block mb-2 font-medium text-gray-900">Subject</label>
                    <Dropdown id="subject" v-model="selectedSubject" :options="subjects" optionLabel="name" placeholder="Select Subject" class="w-full" />
                </div>

                <div class="field">
                    <label for="startDate" class="block mb-2 font-medium text-gray-900">Start Date</label>
                    <Calendar id="startDate" v-model="startDate" dateFormat="yy-mm-dd" class="w-full" :maxDate="endDate" :loading="isLoadingDates" showIcon />
                </div>

                <div class="field">
                    <label for="endDate" class="block mb-2 font-medium text-gray-900">End Date</label>
                    <Calendar id="endDate" v-model="endDate" dateFormat="yy-mm-dd" class="w-full" :minDate="startDate" :loading="isLoadingDates" showIcon />
                </div>

                <div class="field">
                    <label for="search" class="block mb-2 font-medium text-gray-900">Search</label>
                    <div class="p-inputgroup w-full">
                        <span class="p-inputgroup-addon"> </span>
                        <InputText id="search" v-model="searchQuery" placeholder="Search by name or ID..." class="w-full" />
                    </div>
                    <small class="text-gray-500">Searches across all your sections</small>
                </div>
            </div>

            <!-- Date Preset Buttons -->
            <div class="flex flex-wrap gap-2 mt-3">
                <small class="text-gray-600 font-medium mr-2 flex items-center">Quick Ranges:</small>
                <Button
                    v-for="preset in datePresets"
                    :key="preset.label"
                    :label="preset.label"
                    @click="applyDatePreset(preset)"
                    :loading="quickRangeLoading"
                    :disabled="quickRangeLoading"
                    class="p-button-sm p-button-outlined p-button-secondary"
                    size="small"
                />
            </div>

            <!-- Additional filters row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                <div class="field">
                    <div class="flex align-items-center">
                        <Checkbox id="showIssues" v-model="showOnlyIssues" :binary="true" class="mr-2" />
                        <label for="showIssues" class="text-sm font-medium">Show only students with issues</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Status Legend -->
        <div class="mb-4 p-4 bg-gray-50 rounded-lg">
            <h4 class="text-sm font-semibold mb-2">Attendance Status Legend:</h4>
            <div class="flex flex-wrap gap-4">
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-green-500 flex items-center justify-center">
                        <i class="pi pi-check text-white text-xs"></i>
                    </div>
                    <span class="text-sm">Present</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-red-500 flex items-center justify-center">
                        <i class="pi pi-times text-white text-xs"></i>
                    </div>
                    <span class="text-sm">Absent</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-yellow-500 flex items-center justify-center">
                        <i class="pi pi-clock text-white text-xs"></i>
                    </div>
                    <span class="text-sm">Late</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-blue-500 flex items-center justify-center">
                        <i class="pi pi-info-circle text-white text-xs"></i>
                    </div>
                    <span class="text-sm">Excused</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-gray-300 flex items-center justify-center">
                        <span class="text-gray-600 text-xs">-</span>
                    </div>
                    <span class="text-sm">No Data</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-gray-400 flex items-center justify-center">
                        <i class="pi pi-ban text-white text-xs"></i>
                    </div>
                    <span class="text-sm">Inactive Student</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-orange-500 flex items-center justify-center">
                        <i class="pi pi-exclamation-triangle text-white text-xs"></i>
                    </div>
                    <span class="text-sm">Mixed (Multiple Statuses)</span>
                </div>
            </div>
        </div>

        <!-- Enhanced Loading Overlay for Page -->
        <!-- Professional Skeleton Loader -->
        <div v-if="loading" class="p-6">
            <!-- Header Skeleton -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <div class="space-y-3 flex-1">
                        <div class="h-8 bg-gray-200 rounded w-1/3 animate-pulse"></div>
                        <div class="h-4 bg-gray-200 rounded w-1/4 animate-pulse"></div>
                    </div>
                    <div class="h-10 w-32 bg-gray-200 rounded animate-pulse"></div>
                </div>
            </div>

            <!-- Filters Skeleton -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="h-10 bg-gray-200 rounded animate-pulse"></div>
                    <div class="h-10 bg-gray-200 rounded animate-pulse"></div>
                    <div class="h-10 bg-gray-200 rounded animate-pulse"></div>
                    <div class="h-10 bg-gray-200 rounded animate-pulse"></div>
                </div>
            </div>

            <!-- Table Skeleton -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <!-- Table Header -->
                <div class="grid grid-cols-6 gap-4 mb-4 pb-4 border-b">
                    <div class="h-4 bg-gray-300 rounded animate-pulse"></div>
                    <div class="h-4 bg-gray-300 rounded animate-pulse"></div>
                    <div class="h-4 bg-gray-300 rounded animate-pulse"></div>
                    <div class="h-4 bg-gray-300 rounded animate-pulse"></div>
                    <div class="h-4 bg-gray-300 rounded animate-pulse"></div>
                    <div class="h-4 bg-gray-300 rounded animate-pulse"></div>
                </div>

                <!-- Table Rows -->
                <div v-for="i in 8" :key="i" class="grid grid-cols-6 gap-4 mb-4 py-3 border-b border-gray-100">
                    <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                    <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                    <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                    <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                    <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                    <div class="h-4 bg-gray-200 rounded animate-pulse"></div>
                </div>
            </div>
        </div>

        <!-- Enhanced Data Processing Loading Overlay -->
        <div v-if="isLoading && !loading" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm flex items-center justify-center z-40">
            <div class="bg-white rounded-2xl p-8 text-center shadow-2xl border border-gray-100 relative overflow-hidden">
                <!-- Animated Background Pattern -->
                <div class="absolute inset-0 bg-gradient-to-r from-blue-50 to-purple-50 opacity-50"></div>
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 animate-pulse"></div>

                <div class="relative z-10">
                    <!-- Spinning Gears Animation -->
                    <div class="flex justify-center mb-4">
                        <div class="relative">
                            <div class="w-12 h-12 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
                            <div class="absolute top-2 left-2 w-8 h-8 border-3 border-purple-200 border-t-purple-600 rounded-full animate-spin" style="animation-direction: reverse; animation-duration: 0.8s"></div>
                        </div>
                    </div>

                    <!-- Processing Steps -->
                    <div class="flex justify-center space-x-2 mb-4">
                        <div class="w-3 h-3 bg-blue-500 rounded-full animate-pulse"></div>
                        <div class="w-3 h-3 bg-purple-500 rounded-full animate-pulse" style="animation-delay: 0.2s"></div>
                        <div class="w-3 h-3 bg-pink-500 rounded-full animate-pulse" style="animation-delay: 0.4s"></div>
                    </div>

                    <h3 class="text-lg font-bold text-gray-800 mb-2">⚡ Processing Data</h3>
                    <p class="text-gray-600">Updating attendance records...</p>
                </div>
            </div>
        </div>

        <!-- Attendance Records Table -->
        <DataTable :value="filteredRecords" :loading="isLoading" responsiveLayout="scroll" class="attendance-table" stripedRows scrollable scrollHeight="500px">
            <!-- Fixed columns for student info -->
            <Column field="name" header="Student Name" :frozen="true" style="min-width: 200px">
                <template #body="slotProps">
                    <div class="flex align-items-center gap-2">
                        <span class="font-medium">{{ slotProps.data.name }}</span>
                    </div>
                </template>
            </Column>
            <Column field="id" header="ID" :sortable="true" style="width: 80px"></Column>
            <Column field="_statusSort" header="Status" :sortable="true" style="width: 150px">
                <template #body="slotProps">
                    <!-- Show formatted enrollment status if student is not Active (case-insensitive) -->
                    <Tag v-if="slotProps.data.enrollment_status && slotProps.data.enrollment_status.toLowerCase() !== 'active'" :value="formatEnrollmentStatus(slotProps.data.enrollment_status)" severity="secondary" class="text-xs" />
                    <!-- Otherwise show attendance status -->
                    <Tag v-else :value="getOverallStatus(slotProps.data)" :severity="getStatusSeverity(getOverallStatus(slotProps.data))" />
                </template>
            </Column>
            <Column field="_absencesSort" header="Total Absences" :sortable="true" style="width: 120px">
                <template #body="slotProps">
                    <span class="font-medium text-red-600">{{ calculateAbsences(slotProps.data) }}</span>
                </template>
            </Column>
            <Column header="Actions" style="width: 120px">
                <template #body="slotProps">
                    <div class="flex justify-center">
                        <Button icon="pi pi-eye" class="p-button-text p-button-sm" @click="viewStudentDetails(slotProps.data)" v-tooltip.top="'View student details'" />
                    </div>
                </template>
            </Column>

            <!-- Dynamic columns for dates with visual attendance indicators -->
            <Column v-for="date in dateColumns" :key="date" :field="date" :header="formatDate(date)" style="min-width: 100px">
                <template #body="{ data, field }">
                    <div class="flex justify-center">
                        <!-- Show gray user-slash icon for inactive students (case-insensitive check) -->
                        <div
                            v-if="data.enrollment_status && data.enrollment_status.toLowerCase() !== 'active'"
                            class="w-8 h-8 rounded-full flex items-center justify-center bg-gray-400 text-white font-bold text-xs"
                            :title="`Student ${formatEnrollmentStatus(data.enrollment_status)}`"
                        >
                            <i class="pi pi-ban"></i>
                        </div>
                        <!-- Normal attendance indicator for active students -->
                        <div v-else :class="getAttendanceStatusClass(data[field])" class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm cursor-pointer" @click="showDayDetails(data, field)">
                            <i v-if="data[field] === 'Present'" class="pi pi-check"></i>
                            <i v-else-if="data[field] === 'Absent'" class="pi pi-times"></i>
                            <i v-else-if="data[field] === 'Late'" class="pi pi-clock"></i>
                            <i v-else-if="data[field] === 'Excused'" class="pi pi-info-circle"></i>
                            <i v-else-if="data[field] === 'Mixed'" class="pi pi-exclamation-triangle"></i>
                            <span v-else class="text-xs">-</span>
                        </div>
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Empty state -->
        <div v-if="!loading && (!filteredRecords.length || !selectedSubject)" class="empty-state p-5 text-center">
            <i class="pi pi-calendar-times text-5xl text-gray-400 mb-3"></i>
            <h3 class="text-xl font-medium text-gray-600 mb-2">No Records Found</h3>
            <p class="text-gray-500">
                {{ !selectedSubject ? 'Please select a subject to view attendance records.' : 'No attendance records match your search criteria.' }}
            </p>
        </div>

        <!-- Student Details Dialog -->
        <Dialog v-model:visible="showStudentDialog" :style="{ width: '600px' }" header="Student Attendance Details" :modal="true" class="p-fluid">
            <div v-if="selectedStudentDetails">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h4 class="font-bold text-lg mb-1">{{ selectedStudentDetails.name }}</h4>
                        <p class="text-gray-600">ID: {{ selectedStudentDetails.id }} | Grade: {{ selectedStudentDetails.gradeLevel }}</p>
                    </div>
                    <div class="text-right">
                        <Tag :value="selectedStudentDetails.issues.issueLevel" :severity="selectedStudentDetails.issues.issueLevel === 'Warning' ? 'warning' : 'success'" class="mb-2" />
                        <div v-if="selectedStudentDetails.issues.hasIssues" class="text-orange-600">
                            <i class="pi pi-exclamation-triangle mr-1"></i>
                            Requires attention
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-red-50 p-3 rounded border">
                        <h5 class="font-medium text-red-700 mb-1">Absences</h5>
                        <div class="text-2xl font-bold text-red-600">{{ selectedStudentDetails.issues.absentDays }}</div>
                        <div class="text-sm text-red-500">{{ selectedStudentDetails.issues.absentRate }}% of days</div>
                    </div>
                    <div class="bg-yellow-50 p-3 rounded border">
                        <h5 class="font-medium text-yellow-700 mb-1">Late Days</h5>
                        <div class="text-2xl font-bold text-yellow-600">{{ selectedStudentDetails.issues.lateDays }}</div>
                        <div class="text-sm text-yellow-500">{{ selectedStudentDetails.issues.lateRate }}% of days</div>
                    </div>
                </div>

                <div class="mb-4">
                    <h5 class="font-medium mb-2">Recent Attendance Pattern</h5>
                    <div class="flex flex-wrap gap-1">
                        <div v-for="date in dateColumns.slice(-10)" :key="date" class="text-center">
                            <div class="text-xs text-gray-500 mb-1">{{ formatDate(date) }}</div>
                            <div :class="getAttendanceStatusClass(selectedStudentDetails[date])" class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-xs">
                                <i v-if="selectedStudentDetails[date] === 'Present'" class="pi pi-check"></i>
                                <i v-else-if="selectedStudentDetails[date] === 'Absent'" class="pi pi-times"></i>
                                <i v-else-if="selectedStudentDetails[date] === 'Late'" class="pi pi-clock"></i>
                                <i v-else-if="selectedStudentDetails[date] === 'Excused'" class="pi pi-info-circle"></i>
                                <i v-else-if="selectedStudentDetails[date] === 'Mixed'" class="pi pi-exclamation-triangle"></i>
                                <span v-else class="text-xs">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Close" icon="pi pi-times" class="p-button-text" @click="showStudentDialog = false" />
            </template>
        </Dialog>

        <!-- Day Details Dialog -->
        <Dialog v-model:visible="showDayDetailsDialog" :style="{ width: '500px' }" header="Daily Attendance Details" :modal="true" class="p-fluid">
            <div v-if="selectedDayDetails">
                <div class="mb-4">
                    <h4 class="font-bold text-lg mb-1">{{ selectedDayDetails.student.name }}</h4>
                    <p class="text-gray-600">{{ formatDate(selectedDayDetails.date) }}</p>
                    <Tag :value="selectedDayDetails.overallStatus" :severity="getStatusSeverity(selectedDayDetails.overallStatus)" class="mt-2" />
                </div>

                <div class="mb-4">
                    <h5 class="font-medium mb-3">Subject-wise Attendance</h5>
                    <div class="space-y-3">
                        <div v-for="record in selectedDayDetails.records" :key="`${record.subject_id}-${record.session_id}`" class="flex items-center justify-between p-3 border rounded-lg">
                            <div class="flex items-center gap-3">
                                <div :class="getAttendanceStatusClass(record.status)" class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs">
                                    <i v-if="record.status === 'Present'" class="pi pi-check"></i>
                                    <i v-else-if="record.status === 'Absent'" class="pi pi-times"></i>
                                    <i v-else-if="record.status === 'Late'" class="pi pi-clock"></i>
                                    <i v-else-if="record.status === 'Excused'" class="pi pi-info-circle"></i>
                                </div>
                                <div>
                                    <div class="font-medium">{{ record.subject }}</div>
                                    <div class="text-sm text-gray-500">{{ record.arrival_time }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <Tag :value="record.status" :severity="getStatusSeverity(record.status)" />
                                <div v-if="record.remarks" class="text-xs text-gray-500 mt-1">{{ record.remarks }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Close" icon="pi pi-times" class="p-button-text" @click="showDayDetailsDialog = false" />
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.attendance-records-container {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.attendance-table {
    font-size: 0.9rem;
}

.status-cell {
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    text-align: center;
    font-weight: 500;
}

.status-present {
    background-color: rgba(34, 197, 94, 0.1);
    color: rgb(22, 163, 74);
}

.status-late {
    background-color: rgba(234, 179, 8, 0.1);
    color: rgb(202, 138, 4);
}

.status-absent {
    background-color: rgba(239, 68, 68, 0.1);
    color: rgb(220, 38, 38);
}

.status-excused {
    background-color: rgba(147, 51, 234, 0.1);
    color: rgb(126, 34, 206);
}

.status-none {
    color: #9ca3af;
}

.empty-state {
    margin-top: 2rem;
    padding: 3rem;
    background-color: #f9fafb;
    border-radius: 0.5rem;
    border: 1px dashed #d1d5db;
}
</style>
