<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const route = useRoute();
const router = useRouter();
const toast = useToast();

// Reactive data
const loading = ref(true);
const reportData = ref(null);
const selectedMonth = ref(new Date());
const sectionId = route.params.sectionId;

// Computed properties
const maleStudents = computed(() => {
    if (!reportData.value?.students) return [];
    return reportData.value.students.filter(student => student.gender === 'Male');
});

const femaleStudents = computed(() => {
    if (!reportData.value?.students) return [];
    return reportData.value.students.filter(student => student.gender === 'Female');
});

// Load SF2 report data
const loadReportData = async () => {
    loading.value = true;
    try {
        const monthStr = selectedMonth.value.toISOString().slice(0, 7); // YYYY-MM format
        const response = await axios.get(`http://127.0.0.1:8000/api/teacher/reports/sf2/data/${sectionId}/${monthStr}`);
        
        if (response.data.success) {
            reportData.value = response.data.data;
        } else {
            throw new Error(response.data.message || 'Failed to load report data');
        }
    } catch (error) {
        console.error('Error loading SF2 report:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load SF2 report data',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Print report
const printReport = () => {
    window.print();
};

// Download Excel report
const downloadExcel = async () => {
    try {
        const monthStr = selectedMonth.value.toISOString().slice(0, 7);
        const response = await axios.get(`http://127.0.0.1:8000/api/teacher/reports/sf2/download/${sectionId}/${monthStr}`, {
            responseType: 'blob'
        });
        
        // Create download link
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `SF2_Daily_Attendance_${reportData.value.section.name}_${reportData.value.month_name.replace(' ', '_')}.xlsx`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'SF2 report downloaded successfully',
            life: 3000
        });
    } catch (error) {
        console.error('Error downloading report:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to download SF2 report',
            life: 3000
        });
    }
};

// Get attendance mark for display
const getAttendanceMark = (status) => {
    switch (status) {
        case 'present': return '✓';
        case 'absent': return '✗';
        case 'late': return 'L';
        default: return '-';
    }
};

// Get attendance mark color
const getAttendanceColor = (status) => {
    switch (status) {
        case 'present': return 'text-green-600';
        case 'absent': return 'text-red-600';
        case 'late': return 'text-yellow-600';
        default: return 'text-gray-400';
    }
};

// Go back to attendance records
const goBack = () => {
    router.push('/teacher/attendance-records');
};

// Watch for month changes
const onMonthChange = () => {
    loadReportData();
};

// Initialize component
onMounted(() => {
    loadReportData();
});
</script>

<template>
    <div class="sf2-report-container">
        <Toast />
        
        <!-- Header Controls (No Print) -->
        <div class="no-print mb-6 flex justify-between items-center bg-white p-4 rounded-lg shadow">
            <div class="flex items-center gap-4">
                <Button 
                    icon="pi pi-arrow-left" 
                    label="Back" 
                    class="p-button-outlined" 
                    @click="goBack" 
                />
                <h2 class="text-xl font-bold text-gray-800">SF2 Daily Attendance Report</h2>
            </div>
            
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium">Month:</label>
                    <Calendar 
                        v-model="selectedMonth" 
                        view="month" 
                        dateFormat="MM yy"
                        @date-select="onMonthChange"
                        class="w-32"
                    />
                </div>
                <Button 
                    icon="pi pi-print" 
                    label="Print" 
                    class="p-button-outlined" 
                    @click="printReport" 
                />
                <Button 
                    icon="pi pi-download" 
                    label="Download Excel" 
                    class="p-button-success" 
                    @click="downloadExcel" 
                />
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center h-64">
            <div class="text-center">
                <i class="pi pi-spin pi-spinner text-4xl text-blue-500 mb-4"></i>
                <p class="text-gray-600">Loading SF2 Report...</p>
            </div>
        </div>

        <!-- SF2 Report Card -->
        <div v-else-if="reportData" class="sf2-report-card bg-white rounded-lg shadow-lg p-8 max-w-full overflow-x-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="flex justify-between items-start mb-4">
                    <!-- DepEd Logo (Left) -->
                    <div class="flex items-center">
                        <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mr-4">
                            <span class="text-white font-bold text-xs">DepEd</span>
                        </div>
                        <div class="text-left text-sm">
                            <p class="font-medium">Republic of the Philippines</p>
                            <p class="font-medium">Department of Education</p>
                            <p>{{ reportData.school_info.division }}</p>
                            <p>{{ reportData.school_info.district }}</p>
                        </div>
                    </div>
                    
                    <!-- Center Title -->
                    <div class="flex-1 text-center">
                        <h1 class="text-xl font-bold mb-2">School Form 2 (SF2) Daily Attendance Report of Learners</h1>
                        <p class="text-sm text-gray-600">(This replaces Form 1, Form 2 and Form 3 used in previous years)</p>
                    </div>
                    
                    <!-- DepEd Logo (Right) -->
                    <div class="flex items-center">
                        <div class="text-right text-sm mr-4">
                            <p class="font-bold text-red-600">DepEd</p>
                            <p class="font-bold text-blue-600">SULONG EDUKALIDAD</p>
                        </div>
                        <div class="w-16 h-16 bg-red-600 rounded-full flex items-center justify-center">
                            <span class="text-white font-bold text-xs">Logo</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- School Information -->
            <div class="grid grid-cols-2 gap-8 mb-6 text-sm">
                <div class="space-y-2">
                    <div class="flex">
                        <span class="font-medium w-24">School ID:</span>
                        <span class="border-b border-gray-400 flex-1 px-2">{{ reportData.school_info.school_id }}</span>
                    </div>
                    <div class="flex">
                        <span class="font-medium w-24">School Year:</span>
                        <span class="border-b border-gray-400 flex-1 px-2">{{ reportData.school_info.school_year }}</span>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex">
                        <span class="font-medium w-32">Report for the Month of:</span>
                        <span class="border-b border-gray-400 flex-1 px-2">{{ reportData.month_name }}</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-8 mb-8 text-sm">
                <div class="flex">
                    <span class="font-medium w-24">Name of School:</span>
                    <span class="border-b border-gray-400 flex-1 px-2">{{ reportData.school_info.name }}</span>
                </div>
                <div class="flex">
                    <span class="font-medium w-20">Grade Level:</span>
                    <span class="border-b border-gray-400 flex-1 px-2">{{ reportData.section.grade_level }}</span>
                </div>
                <div class="flex">
                    <span class="font-medium w-16">Section:</span>
                    <span class="border-b border-gray-400 flex-1 px-2">{{ reportData.section.name }}</span>
                </div>
            </div>

            <!-- Main Attendance Table -->
            <div class="attendance-table-container mb-8">
                <table class="w-full border-collapse border border-gray-800 text-xs">
                    <!-- Table Header -->
                    <thead>
                        <tr>
                            <th rowspan="2" class="border border-gray-800 p-2 bg-gray-100 text-left w-48">
                                LEARNER'S NAME<br>
                                <span class="text-xs font-normal">(Last Name, First Name, Middle Name)</span>
                            </th>
                            <th colspan="31" class="border border-gray-800 p-2 bg-gray-100 text-center">
                                Days of the month (Put check mark (✓) for each day present, (✗) for absent, and (L) for late)
                            </th>
                            <th rowspan="2" class="border border-gray-800 p-2 bg-gray-100 text-center w-16">
                                Total for the Month<br>
                                <span class="text-xs font-normal">ABSENT</span>
                            </th>
                            <th rowspan="2" class="border border-gray-800 p-2 bg-gray-100 text-center w-16">
                                Total for the Month<br>
                                <span class="text-xs font-normal">TARDY</span>
                            </th>
                            <th rowspan="2" class="border border-gray-800 p-2 bg-gray-100 text-center w-32">
                                REMARKS<br>
                                <span class="text-xs font-normal">(e.g. DROPPED OUT (date), TRANSFERRED: IN (date) or OUT (date), etc.)</span>
                            </th>
                        </tr>
                        <tr>
                            <!-- Day numbers -->
                            <th v-for="day in reportData.days_in_month" :key="day.date" 
                                class="border border-gray-800 p-1 bg-gray-100 text-center w-6">
                                {{ day.day }}
                            </th>
                        </tr>
                    </thead>
                    
                    <!-- Male Students Section -->
                    <tbody>
                        <tr>
                            <td colspan="35" class="border border-gray-800 p-2 bg-blue-100 font-bold text-center">
                                MALE | TOTAL Per Day
                            </td>
                        </tr>
                        <tr v-for="student in maleStudents" :key="student.id">
                            <td class="border border-gray-800 p-2 font-medium">{{ student.name }}</td>
                            <td v-for="day in reportData.days_in_month" :key="day.date" 
                                class="border border-gray-800 p-1 text-center"
                                :class="getAttendanceColor(student.attendance_data[day.date])">
                                {{ getAttendanceMark(student.attendance_data[day.date]) }}
                            </td>
                            <td class="border border-gray-800 p-1 text-center font-medium">{{ student.total_absent }}</td>
                            <td class="border border-gray-800 p-1 text-center font-medium">0</td>
                            <td class="border border-gray-800 p-1 text-center">-</td>
                        </tr>
                        
                        <!-- Female Students Section -->
                        <tr>
                            <td colspan="35" class="border border-gray-800 p-2 bg-pink-100 font-bold text-center">
                                FEMALE | TOTAL Per Day
                            </td>
                        </tr>
                        <tr v-for="student in femaleStudents" :key="student.id">
                            <td class="border border-gray-800 p-2 font-medium">{{ student.name }}</td>
                            <td v-for="day in reportData.days_in_month" :key="day.date" 
                                class="border border-gray-800 p-1 text-center"
                                :class="getAttendanceColor(student.attendance_data[day.date])">
                                {{ getAttendanceMark(student.attendance_data[day.date]) }}
                            </td>
                            <td class="border border-gray-800 p-1 text-center font-medium">{{ student.total_absent }}</td>
                            <td class="border border-gray-800 p-1 text-center font-medium">0</td>
                            <td class="border border-gray-800 p-1 text-center">-</td>
                        </tr>
                        
                        <!-- Combined Total Row -->
                        <tr class="bg-yellow-100">
                            <td class="border border-gray-800 p-2 font-bold text-center">Combined TOTAL PER DAY</td>
                            <td v-for="day in reportData.days_in_month" :key="day.date" 
                                class="border border-gray-800 p-1 text-center font-bold">
                                -
                            </td>
                            <td class="border border-gray-800 p-1 text-center font-bold">{{ reportData.summary.total.total_absent }}</td>
                            <td class="border border-gray-800 p-1 text-center font-bold">0</td>
                            <td class="border border-gray-800 p-1 text-center">-</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Summary Statistics -->
            <div class="grid grid-cols-2 gap-8 text-sm">
                <!-- Left Column - Guidelines -->
                <div class="space-y-4">
                    <h3 class="font-bold underline">GUIDELINES:</h3>
                    <div class="space-y-2">
                        <p>1. This form should be accomplished daily. SF2 is to be submitted to the school head/principal at the end of each month.</p>
                        <p>2. "Learner" refers to anyone who is enrolled in the school.</p>
                        <p>3. Percentage of Enrollment = (Total enrollment as of end of the month ÷ Total enrollment as of the beginning of the month) × 100</p>
                        <p>4. Average Daily Attendance = (Total number of days in reporting month ÷ Number of school days in reporting month) × 100</p>
                        <p>5. Every end of the month, this form should be submitted to the office of the school head/principal for consolidation.</p>
                    </div>
                </div>

                <!-- Right Column - Summary Data -->
                <div class="border border-gray-800">
                    <table class="w-full border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border border-gray-800 p-2 text-left">LEARNERS</th>
                                <th class="border border-gray-800 p-2 text-center">M</th>
                                <th class="border border-gray-800 p-2 text-center">F</th>
                                <th class="border border-gray-800 p-2 text-center">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="border border-gray-800 p-2">1. Enrollment as of (beginning of the reporting month)</td>
                                <td class="border border-gray-800 p-2 text-center">{{ reportData.summary.male.enrollment }}</td>
                                <td class="border border-gray-800 p-2 text-center">{{ reportData.summary.female.enrollment }}</td>
                                <td class="border border-gray-800 p-2 text-center font-bold">{{ reportData.summary.total.enrollment }}</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-800 p-2">2. Late Enrollment during the month</td>
                                <td class="border border-gray-800 p-2 text-center">0</td>
                                <td class="border border-gray-800 p-2 text-center">0</td>
                                <td class="border border-gray-800 p-2 text-center font-bold">0</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-800 p-2">3. Registered Learners as of end of the month</td>
                                <td class="border border-gray-800 p-2 text-center">{{ reportData.summary.male.enrollment }}</td>
                                <td class="border border-gray-800 p-2 text-center">{{ reportData.summary.female.enrollment }}</td>
                                <td class="border border-gray-800 p-2 text-center font-bold">{{ reportData.summary.total.enrollment }}</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-800 p-2">4. Percentage of Enrollment</td>
                                <td class="border border-gray-800 p-2 text-center">100%</td>
                                <td class="border border-gray-800 p-2 text-center">100%</td>
                                <td class="border border-gray-800 p-2 text-center font-bold">100%</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-800 p-2">5. Average Daily Attendance</td>
                                <td class="border border-gray-800 p-2 text-center">{{ reportData.summary.male.attendance_rate }}%</td>
                                <td class="border border-gray-800 p-2 text-center">{{ reportData.summary.female.attendance_rate }}%</td>
                                <td class="border border-gray-800 p-2 text-center font-bold">{{ reportData.summary.total.attendance_rate }}%</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-800 p-2">6. Percentage of Attendance for the month</td>
                                <td class="border border-gray-800 p-2 text-center">{{ reportData.summary.male.attendance_rate }}%</td>
                                <td class="border border-gray-800 p-2 text-center">{{ reportData.summary.female.attendance_rate }}%</td>
                                <td class="border border-gray-800 p-2 text-center font-bold">{{ reportData.summary.total.attendance_rate }}%</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-800 p-2">7. Number of students absent for 5 consecutive days or more</td>
                                <td class="border border-gray-800 p-2 text-center">0</td>
                                <td class="border border-gray-800 p-2 text-center">0</td>
                                <td class="border border-gray-800 p-2 text-center font-bold">0</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-800 p-2">8. Drop out</td>
                                <td class="border border-gray-800 p-2 text-center">0</td>
                                <td class="border border-gray-800 p-2 text-center">0</td>
                                <td class="border border-gray-800 p-2 text-center font-bold">0</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-800 p-2">9. Transferred out</td>
                                <td class="border border-gray-800 p-2 text-center">0</td>
                                <td class="border border-gray-800 p-2 text-center">0</td>
                                <td class="border border-gray-800 p-2 text-center font-bold">0</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-800 p-2">10. Transferred in</td>
                                <td class="border border-gray-800 p-2 text-center">0</td>
                                <td class="border border-gray-800 p-2 text-center">0</td>
                                <td class="border border-gray-800 p-2 text-center font-bold">0</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 grid grid-cols-2 gap-8 text-sm">
                <div class="space-y-4">
                    <div>
                        <p class="mb-2">Prepared by:</p>
                        <div class="border-b border-gray-800 w-48 mb-1"></div>
                        <p class="text-center">{{ reportData.section.teacher?.name || 'Teacher Name' }}</p>
                        <p class="text-center text-xs">Teacher</p>
                    </div>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="mb-2">Noted by:</p>
                        <div class="border-b border-gray-800 w-48 mb-1"></div>
                        <p class="text-center">Principal Name</p>
                        <p class="text-center text-xs">School Head/Principal</p>
                    </div>
                </div>
            </div>

            <!-- Form Footer -->
            <div class="mt-8 text-xs text-center text-gray-600">
                <p>School Form 2 - Page 2 of ___</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else class="text-center py-12">
            <i class="pi pi-exclamation-triangle text-4xl text-red-500 mb-4"></i>
            <p class="text-gray-600">Failed to load SF2 report data</p>
            <Button label="Try Again" class="mt-4" @click="loadReportData" />
        </div>
    </div>
</template>

<style scoped>
.sf2-report-container {
    min-height: 100vh;
    background-color: #f8fafc;
    padding: 1rem;
}

.sf2-report-card {
    font-family: 'Times New Roman', serif;
    line-height: 1.4;
}

.attendance-table-container {
    overflow-x: auto;
}

.attendance-table-container table {
    min-width: 1200px;
}

/* Print Styles */
@media print {
    .no-print {
        display: none !important;
    }
    
    .sf2-report-container {
        background: white;
        padding: 0;
        margin: 0;
    }
    
    .sf2-report-card {
        box-shadow: none;
        border-radius: 0;
        padding: 20px;
        margin: 0;
    }
    
    .attendance-table-container table {
        font-size: 10px;
    }
    
    body {
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .sf2-report-card {
        padding: 1rem;
        font-size: 0.8rem;
    }
    
    .attendance-table-container table {
        font-size: 10px;
    }
}
</style>
