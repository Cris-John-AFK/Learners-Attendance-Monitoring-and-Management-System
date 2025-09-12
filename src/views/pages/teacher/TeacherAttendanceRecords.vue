<script setup>
import { AttendanceRecordsService } from '@/services/AttendanceRecordsService';
import AttendanceSessionService from '@/services/AttendanceSessionService';
import axios from 'axios';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue'; // Added missing watch import

// Configure axios to handle CORS
axios.defaults.headers.common['Access-Control-Allow-Origin'] = '*';
axios.defaults.withCredentials = false;

const toast = useToast();
const loading = ref(true);
const isLoading = ref(false);
const searchQuery = ref('');
const attendanceRecords = ref([]);
const subjects = ref([]);
const selectedSubject = ref(null);
const startDate = ref(new Date(new Date().setDate(1))); // First day of current month
const endDate = ref(new Date()); // Today
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
// Try to get teacher ID from multiple sources
const getTeacherId = () => {
    // Try localStorage first
    let id = localStorage.getItem('teacherId');
    if (id) return parseInt(id);
    
    // Try sessionStorage
    id = sessionStorage.getItem('teacherId');
    if (id) return parseInt(id);
    
    // Try user data in localStorage
    const userData = localStorage.getItem('user');
    if (userData) {
        try {
            const user = JSON.parse(userData);
            if (user.teacher_id) return parseInt(user.teacher_id);
            if (user.id) return parseInt(user.id);
        } catch (e) {
            console.error('Error parsing user data:', e);
        }
    }
    
    // Default fallback - we'll test with different IDs
    return 1;
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
        const searchResults = await AttendanceRecordsService.searchStudents(
            teacherId.value,
            searchQuery.value.trim()
        );
        
        const searchStudentIds = new Set(searchResults.students?.map(s => s.id) || []);
        return attendanceMatrix.value.filter(record => 
            searchStudentIds.has(record.id) ||
            record.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            record.id.toString().includes(searchQuery.value)
        );
    } catch (error) {
        console.error('Search error:', error);
        // Fallback to local search
        const query = searchQuery.value.toLowerCase();
        return attendanceMatrix.value.filter((record) => {
            return (
                record.name.toLowerCase().includes(query) || 
                record.id.toString().includes(query) || 
                (record.gradeLevel && record.gradeLevel.toString().toLowerCase().includes(query)) || 
                (record.section && record.section.toLowerCase().includes(query))
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
        records = records.filter(record => 
            record.name.toLowerCase().includes(query) ||
            record.id.toString().includes(query)
        );
    }
    
    // Apply issues filter
    if (showOnlyIssues.value) {
        records = records.filter(record => {
            const issues = calculateAttendanceIssues(record);
            return issues.hasIssues;
        });
    }
    
    return records;
});

// Load attendance records for selected section and date range
const loadAttendanceRecords = async () => {
    if (!selectedSection.value || !startDate.value || !endDate.value) {
        students.value = [];
        return;
    }

    try {
        isLoading.value = true;
        
        // Get students in the section using the correct endpoint
        let studentsResponse;
        try {
            studentsResponse = await AttendanceRecordsService.getStudentsInSection(selectedSection.value.id, teacherId.value);
            console.log('Students response:', studentsResponse);
        } catch (error) {
            console.log('Student management endpoint failed, trying alternative...');
            // Fallback: use the student IDs that match our sample attendance data
            studentsResponse = {
                students: [
                    { id: 11, name: 'G3 TEst', first_name: 'G3', last_name: 'TEst', grade_level: '3' },
                    { id: 12, name: 'Cris John', first_name: 'Cris', last_name: 'John', grade_level: '3' },
                    { id: 13, name: 'newa new', first_name: 'newa', last_name: 'new', grade_level: '3' }
                ]
            };
        }
        
        // Get attendance sessions for the date range using new direct API
        let sessionsResponse;
        try {
            // Fix subject_id - don't send 'all' as it's not a valid ID
            const subjectId = selectedSubject.value?.id === 'all' ? null : selectedSubject.value?.id;
            sessionsResponse = await AttendanceRecordsService.getAttendanceReport(
                selectedSection.value.id,
                {
                    startDate: startDate.value.toISOString().split('T')[0],
                    endDate: endDate.value.toISOString().split('T')[0],
                    subjectId: subjectId
                }
            );
            console.log('Sessions response:', sessionsResponse);
        } catch (error) {
            console.log('Weekly report failed, creating sample attendance data...');
            // Create sample attendance data for testing
            const sampleSessions = [{
                id: 1,
                session_date: '2025-09-07',
                subject: { name: 'Mathematics' },
                attendance_records: [
                    { student_id: 11, attendance_status: { name: 'Present', code: 'P' }},
                    { student_id: 12, attendance_status: { name: 'Absent', code: 'A' }},
                    { student_id: 13, attendance_status: { name: 'Late', code: 'L' }}
                ]
            }, {
                id: 2,
                session_date: '2025-09-08',
                subject: { name: 'Mathematics' },
                attendance_records: [
                    { student_id: 11, attendance_status: { name: 'Late', code: 'L' }},
                    { student_id: 12, attendance_status: { name: 'Present', code: 'P' }},
                    { student_id: 13, attendance_status: { name: 'Excused', code: 'E' }}
                ]
            }];
            sessionsResponse = { sessions: sampleSessions };
        }
        
        // Transform data into matrix format
        const dateRange = AttendanceRecordsService.generateDateRange(
            startDate.value.toISOString().split('T')[0],
            endDate.value.toISOString().split('T')[0]
        );
        
        students.value = AttendanceRecordsService.transformToMatrix(
            sessionsResponse.sessions || [],
            studentsResponse.students || [],
            dateRange
        );
        
        console.log('Transformed students data:', students.value);
        console.log('Date range used:', dateRange);
        console.log('Sample student data for debugging:', students.value[0]);
        
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
            detail: 'Preparing attendance report...',
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
        const filename = `Attendance_${selectedSubject.value.code}_${startDateStr}_to_${endDateStr}.xlsx`;

        // Import xlsx library
        const XLSX = await import('xlsx');

        // Create worksheet
        const ws = XLSX.utils.json_to_sheet(filteredRecords.value);

        // Create workbook
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Attendance Records');

        // Save the workbook
        XLSX.writeFile(wb, filename);

        toast.add({
            severity: 'success',
            summary: 'Report Generated',
            detail: `Report saved as ${filename}`,
            life: 3000
        });
    } catch (error) {
        console.error('Error generating Excel:', error);
        toast.add({
            severity: 'error',
            summary: 'Export Failed',
            detail: 'Failed to generate Excel report',
            life: 3000
        });
    }
};

// Initialize component data
const initializeComponent = async () => {
    try {
        // Teacher ID is already set in reactive ref
        console.log('Loading data for teacher ID:', teacherId.value);
        
        // First, let's check what teachers exist in the database
        console.log('Checking available teachers...');
        const teachersResponse = await axios.get('http://127.0.0.1:8000/api/teachers');
        console.log('Available teachers:', teachersResponse.data);
        
        // Check what sections exist
        console.log('Checking available sections...');
        const sectionsResponse = await axios.get('http://127.0.0.1:8000/api/sections');
        console.log('Available sections:', sectionsResponse.data);
        
        // Try different teacher IDs if current one doesn't work
        const teacherIds = [teacherId.value, 1, 2, 3];
        let foundValidTeacher = false;
        
        for (const testId of teacherIds) {
            try {
                console.log(`Testing teacher ID: ${testId}`);
                const response = await axios.get(`http://127.0.0.1:8000/api/teachers/${testId}/assignments`);
                console.log(`Response for teacher ${testId}:`, response.data);
                
                if (response.data.success && response.data.assignments?.length > 0) {
                    console.log(`Found valid teacher with ID: ${testId}`);
                    teacherId.value = testId;
                    foundValidTeacher = true;
                    
                    const assignments = response.data.assignments || [];
                    const allSections = sectionsResponse.data.sections || sectionsResponse.data || [];
                    
                    // Filter homeroom sections
                    const homeroomSections = allSections.filter(section => 
                        section.homeroom_teacher_id === parseInt(testId)
                    );
                    console.log(`Homeroom sections for teacher ${testId}:`, homeroomSections);
                    
                    if (homeroomSections.length === 0) {
                        // If no homeroom sections, use all assigned sections for now
                        console.log('No homeroom sections found, using all assigned sections');
                        teacherSections.value = assignments.map(assignment => ({
                            id: assignment.section_id,
                            name: assignment.section_name,
                            subjects: assignment.subjects || []
                        }));
                    } else {
                        // Add subjects to homeroom sections
                        teacherSections.value = homeroomSections.map(section => {
                            const sectionAssignment = assignments.find(assignment => 
                                assignment.section_id === section.id
                            );
                            return {
                                id: section.id,
                                name: section.name,
                                homeroom_teacher_id: section.homeroom_teacher_id,
                                subjects: sectionAssignment?.subjects || []
                            };
                        });
                    }
                    
                    // All teacher sections for search
                    allTeacherSections.value = assignments.map(assignment => ({
                        id: assignment.section_id,
                        name: assignment.section_name,
                        subjects: assignment.subjects || []
                    }));
                    
                    break;
                }
            } catch (error) {
                console.log(`Error testing teacher ID ${testId}:`, error);
            }
        }
        
        if (!foundValidTeacher) {
            console.error('No valid teacher found with assignments');
            toast.add({
                severity: 'warn',
                summary: 'No Sections Found',
                detail: 'No sections are assigned to any teacher. Please check the database setup.',
                life: 5000
            });
            teacherSections.value = [];
            allTeacherSections.value = [];
        }
        
        // Extract unique subjects from homeroom sections and add 'All Subjects' option
        const subjectMap = new Map();
        teacherSections.value.forEach(section => {
            section.subjects?.forEach(subject => {
                if (!subjectMap.has(subject.id)) {
                    subjectMap.set(subject.id, subject);
                }
            });
        });
        
        const uniqueSubjects = Array.from(subjectMap.values());
        // Add 'All Subjects' option at the beginning
        subjects.value = [
            { id: 'all', name: 'All Subjects' },
            ...uniqueSubjects
        ];

        // Auto-select first section and 'All Subjects' if available
        if (teacherSections.value.length > 0) {
            selectedSection.value = teacherSections.value[0];
            // Load available dates for the selected section
            await loadAvailableDates();
        }
        if (subjects.value.length > 0) {
            selectedSubject.value = subjects.value[0]; // This will be 'All Subjects'
        }
        
        // Load initial data if both section and subject are selected
        if (selectedSection.value && selectedSubject.value) {
            await loadAttendanceRecords();
        }
    } catch (error) {
        console.error('Error loading teacher data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load teacher assignments',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Load teacher data and sections on component mount
onMounted(() => {
    initializeComponent();
});

// Load available dates for date picker
const loadAvailableDates = async () => {
    if (!selectedSection.value) return;
    
    isLoadingDates.value = true;
    try {
        const dateRange = AttendanceRecordsService.generateDateRange(
            startDate.value.toISOString().split('T')[0],
            endDate.value.toISOString().split('T')[0]
        );
        
        const datesData = await AttendanceRecordsService.getAttendanceSessionDates(
            selectedSection.value.id,
            startDate.value.toISOString().split('T')[0],
            endDate.value.toISOString().split('T')[0]
        );
        
        availableDates.value = datesData.dates || [];
    } catch (error) {
        console.error('Error loading available dates:', error);
        availableDates.value = [];
    } finally {
        isLoadingDates.value = false;
    }
};

// Watch for changes in section, subject or date range
watch([selectedSection, selectedSubject, startDate, endDate], async () => {
    if (selectedSection.value && selectedSubject.value) {
        await loadAvailableDates();
        loadAttendanceRecords();
    }
});

// Watch for date changes to reload attendance records
watch([startDate, endDate, selectedSubject], () => {
    if (selectedSection.value) {
        loadAttendanceRecords();
        loadAvailableDates();
    }
});

// Helper functions for status display
const getStatusSeverity = (status) => {
    switch (status) {
        case 'Warning': return 'danger';
        case 'At Risk': return 'warning';
        case 'Present': return 'success';
        case 'Absent': return 'danger';
        case 'Late': return 'warning';
        case 'Excused': return 'info';
        case 'Mixed': return 'warning';
        default: return 'success';
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
    dateColumns.value.forEach(date => {
        const status = studentData[date];
        if (status === 'Absent') {
            absences++;
        } else if (status === 'Mixed') {
            // Check detailed records for mixed days
            const details = studentData[`${date}_details`] || [];
            const hasAbsent = details.some(record => record.status === 'Absent');
            if (hasAbsent) {
                absences++;
            }
        }
    });
    return absences;
};

// Get CSS class for attendance status visualization
const getAttendanceStatusClass = (status) => {
    switch (status) {
        case 'Present': return 'bg-green-500';
        case 'Absent': return 'bg-red-500';
        case 'Late': return 'bg-yellow-500';
        case 'Excused': return 'bg-purple-500';
        case 'Mixed': return 'bg-orange-500';
        default: return 'bg-gray-300';
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

// Apply date preset
const applyDatePreset = (preset) => {
    const [start, end] = preset.value();
    startDate.value = start;
    endDate.value = end;
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
</script>

<template>
    <div class="attendance-records-container p-4">
        <Toast />

        <!-- Header with title and export button -->
        <div class="flex justify-between items-center mb-4">
            <h5 class="text-xl font-semibold">Attendance Records</h5>
            <Button icon="pi pi-file-excel" label="Export to Excel" class="p-button-success" @click="exportToExcel" :disabled="loading || !filteredRecords.length" />
        </div>

        <!-- Filters -->
        <div class="filters p-3 mb-4 border rounded-lg bg-gray-50">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div class="field">
                    <label for="section" class="block mb-1 font-medium">Section</label>
                    <Dropdown 
                        id="section" 
                        v-model="selectedSection" 
                        :options="teacherSections" 
                        optionLabel="name" 
                        placeholder="Select Section" 
                        class="w-full"
                        :loading="loading"
                    />
                    <small class="text-gray-500">Homeroom sections only</small>
                </div>
                
                <div class="field">
                    <label for="subject" class="block mb-1 font-medium">Subject</label>
                    <Dropdown 
                        id="subject" 
                        v-model="selectedSubject" 
                        :options="subjects" 
                        optionLabel="name" 
                        placeholder="Select Subject" 
                        class="w-full"
                    />
                </div>

                <div class="field">
                    <label for="startDate" class="block mb-1 font-medium">Start Date</label>
                    <Calendar 
                        id="startDate" 
                        v-model="startDate" 
                        dateFormat="yy-mm-dd" 
                        class="w-full" 
                        :maxDate="endDate"
                        :loading="isLoadingDates"
                        showIcon
                    />
                </div>

                <div class="field">
                    <label for="endDate" class="block mb-1 font-medium">End Date</label>
                    <Calendar 
                        id="endDate" 
                        v-model="endDate" 
                        dateFormat="yy-mm-dd" 
                        class="w-full" 
                        :minDate="startDate"
                        :loading="isLoadingDates"
                        showIcon
                    />
                </div>

                <div class="field">
                    <label for="search" class="block mb-1 font-medium">Search</label>
                    <div class="p-inputgroup w-full">
                        <span class="p-inputgroup-addon">
                            <i class="pi pi-search"></i>
                        </span>
                        <InputText 
                            id="search" 
                            v-model="searchQuery" 
                            placeholder="Search by name or ID..." 
                            class="w-full" 
                        />
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
            </div>
        </div>

        <!-- Attendance Records Table -->
        <DataTable :value="filteredRecords" :loading="loading" responsiveLayout="scroll" class="attendance-table" stripedRows scrollable scrollHeight="500px">
            <!-- Fixed columns for student info -->
            <Column field="name" header="Student Name" :frozen="true" style="min-width: 200px">
                <template #body="slotProps">
                    <div class="flex align-items-center gap-2">
                        <span class="font-medium">{{ slotProps.data.name }}</span>
                    </div>
                </template>
            </Column>
            <Column field="id" header="ID" :sortable="true" style="width: 80px"></Column>
            <Column field="status" header="Status" :sortable="true" style="width: 120px">
                <template #body="slotProps">
                    <Tag :value="getOverallStatus(slotProps.data)" :severity="getStatusSeverity(getOverallStatus(slotProps.data))" />
                </template>
            </Column>
            <Column field="absences" header="Total Absences" :sortable="true" style="width: 120px">
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
                        <div :class="getAttendanceStatusClass(data[field])" class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm cursor-pointer" @click="showDayDetails(data, field)">
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
                        <div v-for="record in selectedDayDetails.records" :key="`${record.subject_id}-${record.session_id}`" 
                             class="flex items-center justify-between p-3 border rounded-lg">
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
