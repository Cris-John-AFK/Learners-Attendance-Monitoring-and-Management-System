<template>
    <div class="summary-report-container">
        <!-- Header Controls (No Print) -->
        <div class="no-print mb-4 flex justify-between items-center bg-white p-4 rounded-lg shadow">
            <div class="flex items-center gap-4">
                <h2 class="text-xl font-bold text-gray-800">Summary Attendance Report</h2>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <!-- Quarter Selector (User-Friendly for Elderly Teachers) -->
                <div class="flex items-center gap-2 border-2 border-blue-400 rounded-lg px-4 py-3 bg-blue-50 shadow-sm">
                    <i class="pi pi-calendar text-blue-600 text-lg"></i>
                    <label class="text-base font-bold text-blue-900">School Quarter:</label>
                    <Dropdown 
                        v-model="selectedQuarter" 
                        :options="quarters" 
                        optionLabel="label" 
                        placeholder="Choose Quarter"
                        @change="onQuarterChange"
                        class="w-64"
                    >
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="flex items-center gap-2">
                                <i class="pi pi-book text-blue-600"></i>
                                <span class="font-semibold text-sm">{{ slotProps.value.label }}</span>
                            </div>
                            <span v-else class="text-gray-500">{{ slotProps.placeholder }}</span>
                        </template>
                        <template #option="slotProps">
                            <div class="flex items-center gap-2 p-2 hover:bg-blue-50">
                                <i class="pi pi-calendar text-blue-600"></i>
                                <div>
                                    <div class="font-semibold text-sm">{{ slotProps.option.label }}</div>
                                    <div class="text-xs text-gray-600">{{ slotProps.option.dateRange }}</div>
                                </div>
                            </div>
                        </template>
                    </Dropdown>
                </div>
                
                <!-- OR Custom Date Range -->
                <div class="flex items-center gap-2 border-2 border-gray-300 rounded-lg px-3 py-2 bg-gray-50">
                    <label class="text-sm font-bold text-gray-700">📅 From:</label>
                    <Calendar 
                        v-model="startDate" 
                        dateFormat="mm/dd/yy" 
                        placeholder="Start Date"
                        @date-select="onDateRangeChange"
                        class="w-40"
                        showIcon
                    />
                    <span class="text-gray-500 font-bold">to</span>
                    <label class="text-sm font-bold text-gray-700">📅 To:</label>
                    <Calendar 
                        v-model="endDate" 
                        dateFormat="mm/dd/yy" 
                        placeholder="End Date"
                        @date-select="onDateRangeChange"
                        class="w-40"
                        showIcon
                    />
                </div>

            </div>
        </div>

        <!-- Report Card -->
        <div class="report-card bg-white rounded-lg shadow-lg p-8">
            <!-- Header Section with Logos -->
            <div class="report-header">
                <div class="header-logo-left">
                    <img src="/demo/images/logo.png" alt="School Logo" class="logo-large" />
                </div>
                <div class="header-center">
                    <h2 class="report-title">Summary Attendance Report of Learners</h2>
                    <p class="report-subtitle">(Monthly attendance summary for all students)</p>
                </div>
                <div class="header-logo-right">
                    <img src="/demo/images/deped-logo.png" alt="DepEd Logo" class="logo-large" />
                </div>
            </div>

            <!-- School Information -->
            <div class="school-info-section">
                <div class="info-row">
                    <div class="info-field">
                        <label>Name of School:</label>
                        <input type="text" value="Naawan Central School" class="input-compact" readonly />
                    </div>
                    <div class="info-field">
                        <label>Grade Level:</label>
                        <input type="text" :value="gradeLevel" class="input-compact" readonly />
                    </div>
                    <div class="info-field">
                        <label>Section:</label>
                        <input type="text" :value="sectionName" class="input-compact" readonly />
                    </div>
                </div>
            </div>

            <!-- Summary Attendance Table -->
            <div class="summary-table-section mb-6">
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th rowspan="2" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold">No.</th>
                            <th rowspan="2" class="border-2 border-gray-900 bg-gray-100 p-2 text-left font-bold">Learner's Name<br/>(Last Name, First Name, Middle Name)</th>
                            <th colspan="4" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold">Attendance Summary</th>
                            <th rowspan="2" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold">Attendance Rate</th>
                            <th rowspan="2" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold">Remarks</th>
                        </tr>
                        <tr>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">Present</th>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">Absent</th>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">Late</th>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">Excused</th>
                        </tr>
                    </thead>
                    <tbody v-if="!loading && students.length > 0">
                        <!-- Male Students -->
                        <tr>
                            <td colspan="8" class="border-2 border-gray-900 bg-blue-100 p-2 font-bold text-sm">MALE STUDENTS</td>
                        </tr>
                        <tr v-for="(student, index) in maleStudents" :key="student.id">
                            <td class="border border-gray-900 p-2 text-center text-sm">{{ index + 1 }}</td>
                            <td class="border border-gray-900 p-2 text-sm">{{ student.name }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'present') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'absent') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'late') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'excused') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm font-bold" :class="getAttendanceRateClass(Math.round((calculateStudentTotal(student, 'present') / schoolDays) * 100))">{{ Math.round((calculateStudentTotal(student, 'present') / schoolDays) * 100) }}%</td>
                            <td class="border border-gray-900 p-1 text-sm">{{ student.remarks || '-' }}</td>
                        </tr>
                        <!-- Female Students -->
                        <tr>
                            <td colspan="8" class="border-2 border-gray-900 bg-pink-100 p-2 font-bold text-sm">FEMALE STUDENTS</td>
                        </tr>
                        <tr v-for="(student, index) in femaleStudents" :key="student.id">
                            <td class="border border-gray-900 p-2 text-center text-sm">{{ index + 1 }}</td>
                            <td class="border border-gray-900 p-2 text-sm">{{ student.name }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'present') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'absent') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'late') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'excused') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm font-bold" :class="getAttendanceRateClass(Math.round((calculateStudentTotal(student, 'present') / schoolDays) * 100))">{{ Math.round((calculateStudentTotal(student, 'present') / schoolDays) * 100) }}%</td>
                            <td class="border border-gray-900 p-1 text-sm">{{ student.remarks || '-' }}</td>
                        </tr>
                        <!-- Total Row -->
                        <tr class="bg-gray-100">
                            <td colspan="2" class="border-2 border-gray-900 p-2 text-center font-bold">TOTAL</td>
                            <td class="border-2 border-gray-900 p-1 text-center font-bold">{{ totalPresent }}</td>
                            <td class="border-2 border-gray-900 p-1 text-center font-bold">{{ totalAbsent }}</td>
                            <td class="border-2 border-gray-900 p-1 text-center font-bold">{{ totalLate }}</td>
                            <td class="border-2 border-gray-900 p-1 text-center font-bold">{{ totalExcused }}</td>
                            <td class="border-2 border-gray-900 p-1 text-center font-bold">{{ averageAttendanceRate }}%</td>
                            <td class="border-2 border-gray-900 p-1"></td>
                        </tr>
                    </tbody>
                    <tbody v-else-if="!loading && students.length === 0">
                        <tr>
                            <td colspan="10" class="border border-gray-900 p-8 text-center text-gray-500">
                                <i class="pi pi-info-circle text-4xl mb-2"></i>
                                <p>No student data available</p>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="10" class="border border-gray-900 p-8 text-center">
                                <ProgressSpinner style="width: 40px; height: 40px" strokeWidth="6" />
                                <p class="mt-2 text-gray-600">Loading data...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div> <!-- Close report-card -->
    </div> <!-- Close summary-report-container -->
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useRoute } from 'vue-router';
import Calendar from 'primevue/calendar';
import Dropdown from 'primevue/dropdown';
import Button from 'primevue/button';
import axios from 'axios';
import TeacherAuthService from '@/services/TeacherAuthService';

const route = useRoute();
const toast = useToast();

// Get section ID from teacher's data
const sectionId = ref(null); // Will be loaded from teacher authentication
const teacherSections = ref([]);

const reportData = ref(null);
const loading = ref(false);
const selectedMonth = ref(null); // Will be set when user selects dates
const selectedQuarter = ref(null);
const sectionName = ref('Loading...');
const gradeLevel = ref('Loading...');

// DepEd School Year 2025-2026 Quarters (Official Calendar)
const quarters = ref([
    {
        label: '1st Quarter',
        value: 'Q1',
        dateRange: 'June 24 - August 29, 2025',
        startDate: new Date(2025, 5, 24), // June 24, 2025
        endDate: new Date(2025, 7, 29)    // August 29, 2025
    },
    {
        label: '2nd Quarter',
        value: 'Q2',
        dateRange: 'September 1 - November 7, 2025',
        startDate: new Date(2025, 8, 1),  // September 1, 2025
        endDate: new Date(2025, 10, 7)    // November 7, 2025
    },
    {
        label: '3rd Quarter',
        value: 'Q3',
        dateRange: 'November 10 - January 30, 2026',
        startDate: new Date(2025, 10, 10), // November 10, 2025
        endDate: new Date(2026, 0, 30)     // January 30, 2026
    },
    {
        label: '4th Quarter',
        value: 'Q4',
        dateRange: 'February 2 - April 10, 2026',
        startDate: new Date(2026, 1, 2),   // February 2, 2026
        endDate: new Date(2026, 3, 10)     // April 10, 2026
    }
]);

// Date range for filtering - default to First Quarter
const startDate = ref(new Date(2025, 5, 24)); // June 24, 2025
const endDate = ref(new Date(2025, 7, 29));   // August 29, 2025

// Computed properties using SF2 Report data structure
const students = computed(() => {
    if (!reportData.value?.students) return [];
    return reportData.value.students;
});

const maleStudents = computed(() => {
    if (!reportData.value?.students) return [];
    return reportData.value.students.filter(student => student.gender === 'Male');
});

const femaleStudents = computed(() => {
    if (!reportData.value?.students) return [];
    return reportData.value.students.filter(student => student.gender === 'Female');
});

const schoolDays = computed(() => {
    if (!reportData.value?.days_in_month) return 0;
    if (!startDate.value || !endDate.value) return 0;
    
    const start = new Date(startDate.value);
    start.setHours(0, 0, 0, 0); // Reset time to start of day
    const end = new Date(endDate.value);
    end.setHours(23, 59, 59, 999); // Set time to end of day
    
    // Count only days within the selected date range
    let count = 0;
    reportData.value.days_in_month.forEach(day => {
        const dayDate = new Date(day.date);
        dayDate.setHours(0, 0, 0, 0); // Reset time for comparison
        if (dayDate >= start && dayDate <= end) {
            count++;
        }
    });
    console.log(`📅 School days in range: ${count}`);
    return count;
});

// Calculate totals from attendance_data within selected date range
const calculateStudentTotal = (student, status) => {
    if (!student.attendance_data || !reportData.value?.days_in_month) return 0;
    if (!startDate.value || !endDate.value) return 0;
    
    let count = 0;
    const start = new Date(startDate.value);
    start.setHours(0, 0, 0, 0); // Reset time to start of day
    const end = new Date(endDate.value);
    end.setHours(23, 59, 59, 999); // Set time to end of day
    
    reportData.value.days_in_month.forEach(day => {
        const dayDate = new Date(day.date);
        dayDate.setHours(0, 0, 0, 0); // Reset time for comparison
        
        // Only count if the day is within the selected date range
        if (dayDate >= start && dayDate <= end) {
            if (student.attendance_data[day.date] === status) {
                count++;
                console.log(`✅ Counted ${status} for ${student.name} on ${day.date}`);
            }
        }
    });
    return count;
};

// Calculate total present
const totalPresent = computed(() => {
    return students.value.reduce((sum, student) => sum + calculateStudentTotal(student, 'present'), 0);
});

// Calculate total absent
const totalAbsent = computed(() => {
    return students.value.reduce((sum, student) => sum + calculateStudentTotal(student, 'absent'), 0);
});

// Calculate total late
const totalLate = computed(() => {
    return students.value.reduce((sum, student) => sum + calculateStudentTotal(student, 'late'), 0);
});

// Calculate total excused
const totalExcused = computed(() => {
    return students.value.reduce((sum, student) => sum + calculateStudentTotal(student, 'excused'), 0);
});

// Calculate average attendance rate
const averageAttendanceRate = computed(() => {
    if (students.value.length === 0 || !reportData.value?.days_in_month) return 0;
    
    const totalDays = reportData.value.days_in_month.length;
    if (totalDays === 0) return 0;
    
    const totalAttendanceRate = students.value.reduce((sum, student) => {
        const presentDays = calculateStudentTotal(student, 'present');
        const rate = (presentDays / totalDays) * 100;
        return sum + rate;
    }, 0);
    
    return Math.round(totalAttendanceRate / students.value.length);
});

// Fetch teacher's sections
const fetchTeacherSections = async () => {
    try {
        console.log('🔍 Fetching teacher sections from API...');
        const response = await axios.get('http://127.0.0.1:8000/api/teacher/sections');
        console.log('📥 Sections API response:', response.data);
        
        if (response.data.success && response.data.data.length > 0) {
            teacherSections.value = response.data.data;
            console.log('✅ Found sections:', teacherSections.value);
            
            // Use first section as default if no sectionId is set
            if (!sectionId.value) {
                sectionId.value = response.data.data[0].id;
                console.log('✅ Using first section ID:', sectionId.value);
                console.log('📋 Section details:', response.data.data[0]);
            }
            return true;
        }
        console.warn('⚠️ No sections found in response');
        return false;
    } catch (error) {
        console.error('❌ Error fetching teacher sections:', error);
        console.error('Error details:', error.response?.data || error.message);
        return false;
    }
};

// Load SF2 report data for ALL months in the selected date range
const loadAttendanceData = async () => {
    loading.value = true;
    console.log('🔄 Loading attendance data...');
    console.log('Current sectionId:', sectionId.value);
    console.log('Date range:', startDate.value, 'to', endDate.value);
    
    try {
        console.log('🎯 Using section ID:', sectionId.value);
        
        // Get all months in the date range
        const months = [];
        const start = new Date(startDate.value);
        const end = new Date(endDate.value);
        
        let current = new Date(start.getFullYear(), start.getMonth(), 1);
        const endMonth = new Date(end.getFullYear(), end.getMonth(), 1);
        
        while (current <= endMonth) {
            months.push({
                year: current.getFullYear(),
                month: current.getMonth() + 1,
                monthStr: `${current.getFullYear()}-${String(current.getMonth() + 1).padStart(2, '0')}`
            });
            current.setMonth(current.getMonth() + 1);
        }
        
        console.log('📅 Loading data for months:', months.map(m => m.monthStr).join(', '));
        
        // Load data for all months and merge
        const allStudentsMap = new Map();
        const allDays = [];
        let sectionData = null;
        
        for (const monthInfo of months) {
            try {
                const apiUrl = `http://127.0.0.1:8000/api/teacher/reports/sf2/data/${sectionId.value}/${monthInfo.monthStr}`;
                console.log('🌐 Loading:', apiUrl);
                
                const response = await axios.get(apiUrl);
                
                if (response.data.success) {
                    const data = response.data.data;
                    
                    // Store section data (same for all months)
                    if (!sectionData) {
                        sectionData = data.section;
                    }
                    
                    // Merge days
                    allDays.push(...data.days_in_month);
                    
                    // Merge student data
                    data.students.forEach(student => {
                        const studentId = student.id; // API returns 'id', not 'student_id'
                        
                        if (!allStudentsMap.has(studentId)) {
                            // Create new student entry with their attendance data
                            allStudentsMap.set(studentId, {
                                ...student,
                                attendance_data: { ...student.attendance_data } // Copy attendance data
                            });
                        } else {
                            // Merge attendance data for existing student
                            const existingStudent = allStudentsMap.get(studentId);
                            Object.assign(existingStudent.attendance_data, student.attendance_data);
                        }
                    });
                }
            } catch (monthError) {
                console.warn(`⚠️ Could not load data for ${monthInfo.monthStr}:`, monthError.message);
            }
        }
        
        // Build final report data with remarks for dropped out/transferred students
        const studentsWithRemarks = Array.from(allStudentsMap.values()).map(student => {
            let remarks = '';
            
            // Generate remarks based on enrollment status
            if (student.enrollment_status === 'dropped_out') {
                remarks = student.dropout_reason 
                    ? `Dropped Out: ${student.dropout_reason}` 
                    : 'Dropped Out';
            } else if (student.enrollment_status === 'transferred_out') {
                remarks = student.dropout_reason 
                    ? `Transferred Out: ${student.dropout_reason}` 
                    : 'Transferred Out';
            } else if (student.enrollment_status === 'withdrawn') {
                remarks = student.dropout_reason 
                    ? `Withdrawn: ${student.dropout_reason}` 
                    : 'Withdrawn';
            }
            
            return {
                ...student,
                remarks: remarks || student.remarks || '-'
            };
        });
        
        reportData.value = {
            section: sectionData,
            month: `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}`,
            month_name: `${start.toLocaleString('default', { month: 'long' })} ${start.getFullYear()} - ${end.toLocaleString('default', { month: 'long' })} ${end.getFullYear()}`,
            students: studentsWithRemarks,
            days_in_month: allDays,
            summary: {
                total_students: allStudentsMap.size,
                total_days: allDays.length
            }
        };
        
        console.log('📊 Loaded attendance data for', months.length, 'months');
        console.log('👥 Total students:', allStudentsMap.size);
        console.log('📅 Total days:', allDays.length);
        
    } catch (error) {
        console.error('❌ Error loading attendance data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to load attendance data',
            life: 3000
        });
    } finally {
        loading.value = false;
        console.log('✅ Loading complete. Students:', students.value.length);
    }
};

const printReport = () => {
    window.print();
};

const exportExcel = () => {
    toast.add({
        severity: 'info',
        summary: 'Export',
        detail: 'Excel export functionality coming soon',
        life: 3000
    });
};

const getMonthName = () => {
    if (!selectedMonth.value) return '';
    const months = ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'];
    const month = selectedMonth.value.getMonth();
    const year = selectedMonth.value.getFullYear();
    return `${months[month]} ${year}`;
};

const getAttendanceRateClass = (rate) => {
    if (rate >= 95) return 'text-green-700';
    if (rate >= 85) return 'text-blue-700';
    if (rate >= 75) return 'text-yellow-700';
    return 'text-red-700';
};

const onDateRangeChange = () => {
    // Only load if both dates are selected
    if (startDate.value && endDate.value) {
        // Validate that end date is after start date
        if (endDate.value < startDate.value) {
            toast.add({
                severity: 'warn',
                summary: 'Invalid Date Range',
                detail: 'End date must be after start date',
                life: 3000
            });
            return;
        }
        // Update selectedMonth based on date range
        selectedMonth.value = new Date(startDate.value);
        console.log('📅 Date range changed, loading data...');
        loadAttendanceData();
    } else {
        console.log('⏳ Waiting for both dates to be selected...');
    }
};

// Quarter change handler
const onQuarterChange = () => {
    if (selectedQuarter.value) {
        startDate.value = selectedQuarter.value.startDate;
        endDate.value = selectedQuarter.value.endDate;
        selectedMonth.value = new Date(selectedQuarter.value.startDate);
        
        toast.add({
            severity: 'info',
            summary: '📚 Quarter Selected',
            detail: `Loading ${selectedQuarter.value.label} data (${selectedQuarter.value.dateRange})`,
            life: 3000
        });
        
        loadAttendanceData();
    }
};

onMounted(async () => {
    console.log('🚀 Component mounted. Loading teacher data...');
    
    // Get teacher's section ID from authentication
    const teacherData = TeacherAuthService.getTeacherData();
    console.log('👨‍🏫 Teacher data:', teacherData);
    
    // Try to get section from teacher.homeroom_section first, then from assignments
    let homeroomSection = null;
    
    if (teacherData?.teacher?.homeroom_section) {
        homeroomSection = teacherData.teacher.homeroom_section;
        console.log('✅ Found homeroom section in teacher object:', homeroomSection);
    } else if (teacherData?.assignments && teacherData.assignments.length > 0) {
        // Find homeroom assignment (subject_id is null)
        const homeroomAssignment = teacherData.assignments.find(a => a.subject_id === null || a.subject_name === 'Homeroom');
        if (homeroomAssignment && homeroomAssignment.section) {
            homeroomSection = homeroomAssignment.section;
            console.log('✅ Found homeroom section in assignments:', homeroomSection);
        }
    }
    
    if (homeroomSection) {
        sectionId.value = homeroomSection.id;
        sectionName.value = homeroomSection.name || 'Unknown Section';
        
        // Extract grade_level - it might be nested in a grade object or directly available
        if (homeroomSection.grade_level) {
            gradeLevel.value = homeroomSection.grade_level;
        } else if (homeroomSection.grade && homeroomSection.grade.name) {
            gradeLevel.value = homeroomSection.grade.name;
        } else if (homeroomSection.grade && homeroomSection.grade.grade_name) {
            gradeLevel.value = homeroomSection.grade.grade_name;
        } else {
            gradeLevel.value = 'Unknown Grade';
        }
        
        console.log('✅ Teacher section loaded:', {
            id: sectionId.value,
            name: sectionName.value,
            grade: gradeLevel.value,
            rawSection: homeroomSection
        });
        
        // Set default to First Quarter
        selectedQuarter.value = quarters.value[0];
        selectedMonth.value = new Date(2025, 5, 24); // June 24, 2025
        
        // Auto-load First Quarter data
        toast.add({
            severity: 'success',
            summary: '✅ Ready',
            detail: `Loading 1st Quarter attendance for ${sectionName.value}...`,
            life: 3000
        });
        
        loadAttendanceData();
    } else {
        console.error('❌ No homeroom section found in teacher data');
        console.log('Available data:', {
            teacher: teacherData?.teacher,
            assignments: teacherData?.assignments
        });
        
        sectionName.value = 'No Section';
        gradeLevel.value = 'N/A';
        
        toast.add({
            severity: 'error',
            summary: '❌ Error',
            detail: 'Could not load your homeroom section. Please contact administrator.',
            life: 5000
        });
    }
});
</script>

<style scoped>
.summary-report-container {
    min-height: 100vh;
    background-color: #f8fafc;
    padding: 1rem;
}

.report-card {
    font-family: 'Arial', 'Calibri', sans-serif;
    line-height: 1.2;
}

/* Header Section */
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1rem 0;
}

.header-logo-left,
.header-logo-right {
    flex: 0 0 100px;
}

.header-center {
    flex: 1;
    text-align: center;
    padding: 0 2rem;
}

.logo-large {
    width: 90px;
    height: 90px;
    object-fit: contain;
}

.report-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    line-height: 1.4;
    color: #2c3e50;
}

.report-subtitle {
    margin: 0.25rem 0 0 0;
    font-size: 0.75rem;
    color: #666;
    font-style: italic;
}

/* School Info */
.school-info-section {
    margin-bottom: 1.5rem;
    background: white;
}

.info-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.75rem;
    align-items: center;
}

.info-field {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}

.info-field label {
    font-weight: 600;
    white-space: nowrap;
    font-size: 0.85rem;
    color: #333;
}

.info-field input.input-compact {
    flex: 1;
    padding: 0.4rem 0.6rem;
    border: 1px solid #333;
    background: white;
    font-size: 0.85rem;
    border-radius: 0;
}

.info-field input.input-compact:focus {
    outline: none;
    border: 2px solid #4a90e2;
}

.border-circle {
    border-radius: 50%;
}

/* Summary Table */
.summary-table-section {
    overflow-x: auto;
}

.summary-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.summary-table th,
.summary-table td {
    border: 1px solid #1f2937;
    padding: 0.5rem;
}

.summary-table thead th {
    background-color: #f3f4f6;
    font-weight: 600;
    text-align: center;
}

.summary-table tbody tr:hover {
    background-color: #f9fafb;
}

/* Print Styles */
@media print {
    .no-print {
        display: none !important;
    }

    .summary-report-container {
        padding: 0;
        background: white;
    }

    .report-card {
        box-shadow: none;
    }

    .p-button, .p-dropdown {
        display: none !important;
    }
}
</style>
