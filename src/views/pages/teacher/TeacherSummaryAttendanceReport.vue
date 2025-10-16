<template>
    <div class="summary-report-container">
        <!-- Header Controls (No Print) -->
        <div class="no-print mb-4 flex justify-between items-center bg-white p-4 rounded-lg shadow">
            <div class="flex items-center gap-4">
                <Button icon="pi pi-arrow-left" label="Back" class="p-button-outlined" @click="$router.back()" />
                <h2 class="text-xl font-bold text-gray-800">Summary Attendance Report</h2>
            </div>
            <div class="flex items-center gap-3">
                <!-- Date Range Selector -->
                <div class="flex items-center gap-2 border-2 border-gray-300 rounded-lg px-3 py-2 bg-gray-50">
                    <label class="text-sm font-medium text-gray-700">From:</label>
                    <Calendar 
                        v-model="startDate" 
                        dateFormat="mm/dd/yy" 
                        placeholder="Start Date"
                        @date-select="onDateRangeChange"
                        class="w-40"
                        showIcon
                    />
                    <span class="text-gray-500">-</span>
                    <label class="text-sm font-medium text-gray-700">To:</label>
                    <Calendar 
                        v-model="endDate" 
                        dateFormat="mm/dd/yy" 
                        placeholder="End Date"
                        @date-select="onDateRangeChange"
                        class="w-40"
                        showIcon
                    />
                </div>
                <Button icon="pi pi-print" label="Print" class="p-button-outlined" @click="printReport" />
                <Button icon="pi pi-download" label="Export Excel" class="p-button-success" @click="exportExcel" />
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
                        <label>School ID:</label>
                        <input type="text" value="123456" class="input-compact" readonly />
                    </div>
                    <div class="info-field">
                        <label>School Year:</label>
                        <input type="text" value="2024-2025" class="input-compact" readonly />
                    </div>
                    <div class="info-field">
                        <label>Report for the Month of:</label>
                        <input type="text" :value="getMonthName()" class="input-compact" readonly />
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-field">
                        <label>Name of School:</label>
                        <input type="text" value="Naawan Central School" class="input-compact" readonly />
                    </div>
                    <div class="info-field">
                        <label>Grade Level:</label>
                        <input type="text" value="Kinder" class="input-compact" readonly />
                    </div>
                    <div class="info-field">
                        <label>Section:</label>
                        <input type="text" value="Malikhain" class="input-compact" readonly />
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
                            <th colspan="2" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold">Sex</th>
                            <th colspan="4" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold">Attendance Summary</th>
                            <th rowspan="2" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold">Attendance Rate</th>
                            <th rowspan="2" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold">Remarks</th>
                        </tr>
                        <tr>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">M</th>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">F</th>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">Present</th>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">Absent</th>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">Late</th>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">Excused</th>
                        </tr>
                    </thead>
                    <tbody v-if="!loading && students.length > 0">
                        <!-- Male Students -->
                        <tr>
                            <td colspan="10" class="border-2 border-gray-900 bg-blue-100 p-2 font-bold text-sm">MALE STUDENTS</td>
                        </tr>
                        <tr v-for="(student, index) in maleStudents" :key="student.id">
                            <td class="border border-gray-900 p-2 text-center text-sm">{{ index + 1 }}</td>
                            <td class="border border-gray-900 p-2 text-sm">{{ student.name }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">✓</td>
                            <td class="border border-gray-900 p-1 text-center text-sm"></td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ student.total_present || 0 }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ student.total_absences || 0 }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ student.total_late || 0 }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ student.total_excused || 0 }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm font-bold" :class="getAttendanceRateClass(student.attendance_rate)">{{ student.attendance_rate }}%</td>
                            <td class="border border-gray-900 p-1 text-sm">{{ student.remarks || '-' }}</td>
                        </tr>
                        <!-- Female Students -->
                        <tr>
                            <td colspan="10" class="border-2 border-gray-900 bg-pink-100 p-2 font-bold text-sm">FEMALE STUDENTS</td>
                        </tr>
                        <tr v-for="(student, index) in femaleStudents" :key="student.id">
                            <td class="border border-gray-900 p-2 text-center text-sm">{{ index + 1 }}</td>
                            <td class="border border-gray-900 p-2 text-sm">{{ student.name }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm"></td>
                            <td class="border border-gray-900 p-1 text-center text-sm">✓</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ student.total_present || 0 }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ student.total_absences || 0 }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ student.total_late || 0 }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ student.total_excused || 0 }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm font-bold" :class="getAttendanceRateClass(student.attendance_rate)">{{ student.attendance_rate }}%</td>
                            <td class="border border-gray-900 p-1 text-sm">{{ student.remarks || '-' }}</td>
                        </tr>
                        <!-- Total Row -->
                        <tr class="bg-gray-100">
                            <td colspan="2" class="border-2 border-gray-900 p-2 text-center font-bold">TOTAL</td>
                            <td class="border-2 border-gray-900 p-1 text-center font-bold">{{ maleStudents.length }}</td>
                            <td class="border-2 border-gray-900 p-1 text-center font-bold">{{ femaleStudents.length }}</td>
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
import TeacherAuthService from '@/services/TeacherAuthService';

const toast = useToast();

const students = ref([]);
const summaryData = ref(null);
const loading = ref(false);
const selectedMonth = ref(new Date().getMonth() + 1);
const selectedYear = ref(new Date().getFullYear());
const selectedSection = ref(null);

// Date range for filtering
const startDate = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1)); // First day of current month
const endDate = ref(new Date()); // Today

const monthOptions = [
    { label: 'January', value: 1 },
    { label: 'February', value: 2 },
    { label: 'March', value: 3 },
    { label: 'April', value: 4 },
    { label: 'May', value: 5 },
    { label: 'June', value: 6 },
    { label: 'July', value: 7 },
    { label: 'August', value: 8 },
    { label: 'September', value: 9 },
    { label: 'October', value: 10 },
    { label: 'November', value: 11 },
    { label: 'December', value: 12 }
];

const yearOptions = [2023, 2024, 2025, 2026];
const sectionOptions = ref([]);

const maleStudents = computed(() => 
    students.value.filter(student => student.gender === 'Male')
);

const femaleStudents = computed(() => 
    students.value.filter(student => student.gender === 'Female')
);

const schoolDays = computed(() => {
    const daysInMonth = new Date(selectedYear.value, selectedMonth.value, 0).getDate();
    return Math.floor(daysInMonth * 0.7); // Approximate school days (excluding weekends)
});

// Calculate total present
const totalPresent = computed(() => {
    return students.value.reduce((sum, student) => sum + (student.total_present || 0), 0);
});

// Calculate total absent
const totalAbsent = computed(() => {
    return students.value.reduce((sum, student) => sum + (student.total_absences || 0), 0);
});

// Calculate total late
const totalLate = computed(() => {
    return students.value.reduce((sum, student) => sum + (student.total_late || 0), 0);
});

// Calculate total excused
const totalExcused = computed(() => {
    return students.value.reduce((sum, student) => sum + (student.total_excused || 0), 0);
});

// Calculate average attendance rate
const averageAttendanceRate = computed(() => {
    if (students.value.length === 0) return 0;
    const sum = students.value.reduce((total, student) => total + (student.attendance_rate || 0), 0);
    return Math.round(sum / students.value.length);
});

const getTeacherId = () => {
    const teacherData = TeacherAuthService.getTeacherData();
    if (teacherData && teacherData.teacher && teacherData.teacher.id) {
        return parseInt(teacherData.teacher.id);
    }

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

    return 2; // Fallback
};

const loadAttendanceData = async () => {
    loading.value = true;
    
    try {
        const teacherId = getTeacherId();
        const params = new URLSearchParams({
            teacher_id: teacherId,
            period: 'month',
            view_type: 'all_students'
        });

        if (selectedSection.value) {
            params.append('section_id', selectedSection.value);
        }

        const response = await fetch(`http://127.0.0.1:8000/api/attendance/summary?${params}`);
        
        if (!response.ok) {
            throw new Error(`Failed to load attendance data: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            students.value = data.data.students.map(student => ({
                ...student,
                name: `${student.first_name} ${student.last_name}`,
                attendance_rate: Math.round(student.attendance_rate || 0)
            }));
            
            summaryData.value = data.data;
            
            console.log('📊 Summary Attendance: Loaded data:', data.data);
        } else {
            throw new Error(data.message || 'Failed to load attendance data');
        }
        
    } catch (error) {
        console.error('Error loading attendance data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load attendance data',
            life: 3000
        });
    } finally {
        loading.value = false;
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
    const months = ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'];
    return `${months[selectedMonth.value - 1]} ${selectedYear.value}`;
};

const getAttendanceRateClass = (rate) => {
    if (rate >= 95) return 'text-green-700';
    if (rate >= 85) return 'text-blue-700';
    if (rate >= 75) return 'text-yellow-700';
    return 'text-red-700';
};

const onDateRangeChange = () => {
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
        loadAttendanceData();
    }
};

onMounted(() => {
    loadAttendanceData();
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
