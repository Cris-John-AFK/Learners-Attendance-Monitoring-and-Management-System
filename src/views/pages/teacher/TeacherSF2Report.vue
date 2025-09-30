<script setup>
import axios from 'axios';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const toast = useToast();

// Reactive data
const loading = ref(true);
const reportData = ref(null);
const selectedMonth = ref(new Date());
const sectionId = route.params.sectionId;
const submitting = ref(false);

// Computed properties
const maleStudents = computed(() => {
    if (!reportData.value?.students) return [];
    return reportData.value.students.filter((student) => student.gender === 'Male');
});

const femaleStudents = computed(() => {
    if (!reportData.value?.students) return [];
    return reportData.value.students.filter((student) => student.gender === 'Female');
});

// Calculate daily totals for male students
const maleDailyTotals = computed(() => {
    if (!reportData.value?.days_in_month || !maleStudents.value.length) return {};

    const totals = {};
    reportData.value.days_in_month.forEach((day) => {
        let present = 0,
            absent = 0,
            late = 0;

        maleStudents.value.forEach((student) => {
            const status = student.attendance_data?.[day.date];
            if (status === 'present') present++;
            else if (status === 'absent') absent++;
            else if (status === 'late') late++;
        });

        totals[day.date] = { present, absent, late, total: present + absent + late };
    });

    return totals;
});

// Calculate daily totals for female students
const femaleDailyTotals = computed(() => {
    if (!reportData.value?.days_in_month || !femaleStudents.value.length) return {};

    const totals = {};
    reportData.value.days_in_month.forEach((day) => {
        let present = 0,
            absent = 0,
            late = 0;

        femaleStudents.value.forEach((student) => {
            const status = student.attendance_data?.[day.date];
            if (status === 'present') present++;
            else if (status === 'absent') absent++;
            else if (status === 'late') late++;
        });

        totals[day.date] = { present, absent, late, total: present + absent + late };
    });

    return totals;
});

// Calculate combined daily totals
const combinedDailyTotals = computed(() => {
    if (!reportData.value?.days_in_month) return {};

    const totals = {};
    reportData.value.days_in_month.forEach((day) => {
        const maleTotal = maleDailyTotals.value[day.date] || { present: 0, absent: 0, late: 0 };
        const femaleTotal = femaleDailyTotals.value[day.date] || { present: 0, absent: 0, late: 0 };

        totals[day.date] = {
            present: maleTotal.present + femaleTotal.present,
            absent: maleTotal.absent + femaleTotal.absent,
            late: maleTotal.late + femaleTotal.late,
            total: maleTotal.present + maleTotal.absent + maleTotal.late + femaleTotal.present + femaleTotal.absent + femaleTotal.late
        };
    });

    return totals;
});

// Calculate total present for male students across all days
const maleTotalPresent = computed(() => {
    if (!reportData.value?.days_in_month) return 0;
    let total = 0;
    reportData.value.days_in_month.forEach((day) => {
        total += maleDailyTotals.value[day.date]?.present || 0;
    });
    return total;
});

// Calculate total present for female students across all days
const femaleTotalPresent = computed(() => {
    if (!reportData.value?.days_in_month) return 0;
    let total = 0;
    reportData.value.days_in_month.forEach((day) => {
        total += femaleDailyTotals.value[day.date]?.present || 0;
    });
    return total;
});

// Calculate total present for all students across all days
const combinedTotalPresent = computed(() => {
    return maleTotalPresent.value + femaleTotalPresent.value;
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

// Submit to Admin
const submitToAdmin = async () => {
    submitting.value = true;
    try {
        const monthStr = selectedMonth.value.toISOString().slice(0, 7);
        const response = await axios.post(`http://127.0.0.1:8000/api/teacher/reports/sf2/submit/${sectionId}/${monthStr}`);

        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Successfully Submitted!',
                detail: `SF2 report for ${reportData.value.section.name} has been submitted to admin`,
                life: 5000
            });
        } else {
            throw new Error(response.data.message || 'Failed to submit report');
        }
    } catch (error) {
        console.error('Error submitting report:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || 'Failed to submit SF2 report to admin',
            life: 3000
        });
    } finally {
        submitting.value = false;
    }
};

// Get attendance mark for display
const getAttendanceMark = (status) => {
    switch (status) {
        case 'present':
            return '✓';
        case 'absent':
            return '✗';
        case 'late':
            return 'L';
        default:
            return '-';
    }
};

// Get attendance mark color
const getAttendanceColor = (status) => {
    switch (status) {
        case 'present':
            return 'text-green-600';
        case 'absent':
            return 'text-red-600';
        case 'late':
            return 'text-yellow-600';
        default:
            return 'text-gray-400';
    }
};

// Get day of week abbreviation
const getDayOfWeek = (dateString) => {
    const date = new Date(dateString);
    const days = ['S', 'M', 'T', 'W', 'TH', 'F', 'S'];
    return days[date.getDay()];
};

// Get day of week styling class
const getDayOfWeekClass = (dateString) => {
    const date = new Date(dateString);
    const dayOfWeek = date.getDay();

    // Weekend styling (Saturday = 6, Sunday = 0)
    if (dayOfWeek === 0 || dayOfWeek === 6) {
        return 'bg-gray-200';
    }
    // Weekday styling
    return 'bg-white';
};

// Get column border class for visual separation
const getColumnBorderClass = (dateString) => {
    const date = new Date(dateString);
    const dayOfWeek = date.getDay();

    // Add thick border before Sunday (start of week)
    if (dayOfWeek === 0) {
        return 'weekend-column';
    }
    // Add medium border before Monday (after weekend)
    if (dayOfWeek === 1) {
        return 'week-separator';
    }

    return '';
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
                <Button icon="pi pi-arrow-left" label="Back" class="p-button-outlined" @click="goBack" />
                <h2 class="text-xl font-bold text-gray-800">SF2 Daily Attendance Report</h2>
            </div>

            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium">Month:</label>
                    <Calendar v-model="selectedMonth" view="month" dateFormat="MM yy" @date-select="onMonthChange" class="w-32" />
                </div>
                <Button icon="pi pi-print" label="Print" class="p-button-outlined" @click="printReport" />
                <Button icon="pi pi-download" label="Download Excel" class="p-button-success" @click="downloadExcel" />
                <Button icon="pi pi-send" label="Submit to Admin" class="p-button-warning" :loading="submitting" @click="submitToAdmin" />
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
            <div class="text-center mb-6">
                <div class="flex justify-between items-center mb-6">
                    <!-- Left DepEd Seal Logo -->
                    <div class="w-32 h-32 flex items-center justify-center">
                        <img src="/demo/images/dep-ed-logo.png" alt="DepEd Seal" class="w-28 h-28 object-contain" />
                    </div>

                    <!-- Center Title -->
                    <div class="flex-1 text-center px-4">
                        <h1 class="text-lg font-bold mb-2 leading-tight">School Form 2 (SF2) Daily Attendance Report of Learners</h1>
                        <p class="text-xs text-gray-700 italic">(This replaces Form 1, Form 2 & Form 3 used in previous years)</p>
                    </div>

                    <!-- Right DepEd Logo -->
                    <div class="w-40 h-32 flex items-center justify-center">
                        <img src="/demo/images/deped-logo.png" alt="DepEd Logo" class="w-36 h-24 object-contain" />
                    </div>
                </div>
            </div>

            <!-- School Information Form Fields -->
            <div class="mb-6">
                <!-- First Row -->
                <div class="grid grid-cols-3 gap-6 mb-4 text-sm">
                    <div class="flex items-center">
                        <span class="font-medium mr-2">School ID:</span>
                        <div class="border border-gray-800 flex-1 px-2 py-1 min-h-[24px] bg-white">
                            {{ reportData.school_info.school_id || '127935' }}
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="font-medium mr-2">School Year:</span>
                        <div class="border border-gray-800 flex-1 px-2 py-1 min-h-[24px] bg-white">
                            {{ reportData.school_info.school_year || '2025 - 2026' }}
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="font-medium mr-2">Report for the Month of:</span>
                        <div class="border border-gray-800 flex-1 px-2 py-1 min-h-[24px] bg-white font-bold">
                            {{ reportData.month_name?.toUpperCase() || 'AUGUST' }}
                        </div>
                    </div>
                </div>

                <!-- Second Row -->
                <div class="grid grid-cols-3 gap-6 mb-6 text-sm">
                    <div class="flex items-center">
                        <span class="font-medium mr-2">Name of School:</span>
                        <div class="border border-gray-800 flex-1 px-2 py-1 min-h-[24px] bg-white">
                            {{ reportData.school_info.name || 'Naawan Central School' }}
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="font-medium mr-2">Grade Level:</span>
                        <div class="border border-gray-800 flex-1 px-2 py-1 min-h-[24px] bg-white">
                            {{ reportData.section.grade_level || 'Kinder' }}
                        </div>
                    </div>
                    <div class="flex items-center">
                        <span class="font-medium mr-2">Section:</span>
                        <div class="border border-gray-800 flex-1 px-2 py-1 min-h-[24px] bg-white font-bold">
                            {{ reportData.section.name || 'GREAT AM' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Attendance Table -->
            <div class="attendance-table-container mb-8">
                <table class="w-full border-collapse border-2 border-gray-900 text-xs">
                    <!-- Table Header -->
                    <thead>
                        <!-- Row 1: Main Headers -->
                        <tr>
                            <th rowspan="3" class="border-2 border-gray-900 p-1 bg-gray-50 text-center font-bold" style="width: 30px; vertical-align: middle; border-left: 2px solid #000">
                                <div class="text-xs">No.</div>
                            </th>
                            <th rowspan="3" class="border-2 border-gray-900 p-2 bg-gray-50 text-left font-bold" style="width: 200px; vertical-align: middle; border-right: 2px solid #000">
                                <div class="text-xs leading-tight">LEARNER'S NAME<br />(Last Name, First Name, Middle Name)</div>
                            </th>
                            <th :colspan="reportData.days_in_month.length" class="border-2 border-gray-900 p-1 bg-gray-50 text-center font-bold">
                                <div class="text-xs">(1st row for date, 2nd row for Day: M,T,W,TH,F) for the present (✓), (✗) for absent, and (L) for late</div>
                            </th>
                            <th colspan="2" rowspan="2" class="border-2 border-gray-900 p-1 bg-gray-50 text-center font-bold" style="border-left: 2px solid #000; vertical-align: middle">
                                <div class="text-xs">Total for the<br />Month</div>
                            </th>
                            <th rowspan="3" class="border-2 border-gray-900 p-1 bg-gray-50 text-center font-bold" style="width: 150px; vertical-align: middle; border-right: 2px solid #000">
                                <div class="text-xs leading-tight">REMARKS (If DROPPED OUT, state reason, please refer to legend number 2. If TRANSFERRED IN/OUT, write the name of School.)</div>
                            </th>
                        </tr>
                        <!-- Row 2: Day Numbers -->
                        <tr>
                            <th
                                v-for="day in reportData.days_in_month"
                                :key="day.date"
                                class="border border-gray-900 p-0.5 bg-gray-50 text-center font-bold"
                                :style="{ width: '22px', borderLeft: getDayOfWeek(day.date) === 'M' ? '2px solid #000' : '' }"
                            >
                                <div class="text-xs">{{ day.day }}</div>
                            </th>
                        </tr>
                        <!-- Row 3: Day of Week and Column Headers -->
                        <tr>
                            <!-- Day of week labels -->
                            <th
                                v-for="day in reportData.days_in_month"
                                :key="`dow-${day.date}`"
                                class="border-2 border-gray-900 p-0.5 text-center text-xs font-bold"
                                :class="getDayOfWeekClass(day.date)"
                                :style="{ height: '24px', borderTop: '2px solid #000', borderBottom: '2px solid #000', borderLeft: getDayOfWeek(day.date) === 'M' ? '2px solid #000' : '' }"
                            >
                                {{ getDayOfWeek(day.date) }}
                            </th>
                            <th class="border-2 border-gray-900 bg-gray-50 text-center text-xs font-bold" style="width: 40px; padding: 2px 6px 2px 2px; border-top: 2px solid #000; border-bottom: 2px solid #000; border-left: 1px solid #000; border-right: 1px solid #000">ABSENT</th>
                            <th class="border-2 border-gray-900 p-0.5 bg-gray-50 text-center text-xs font-bold" style="width: 40px; border-top: 2px solid #000; border-bottom: 2px solid #000">TARDY</th>
                        </tr>
                    </thead>

                    <!-- Male Students Section -->
                    <tbody>
                        <tr>
                            <td :colspan="reportData.days_in_month.length + 4" class="border-2 border-gray-900 p-1 bg-gray-100 font-bold text-left text-xs" style="height: 22px">MALE</td>
                        </tr>
                        <tr v-for="(student, index) in maleStudents" :key="student.id" style="height: 20px">
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-left: 2px solid #000">{{ index + 1 }}</td>
                            <td class="border border-gray-900 px-2 py-0.5 text-left text-xs" style="border-right: 2px solid #000">{{ student.name }}</td>
                            <td
                                v-for="day in reportData.days_in_month"
                                :key="day.date"
                                class="border border-gray-900 p-0.5 text-center text-xs"
                                :class="getAttendanceColor(student.attendance_data[day.date])"
                                :style="{ borderLeft: getDayOfWeek(day.date) === 'M' ? '2px solid #000' : '' }"
                            >
                                {{ getAttendanceMark(student.attendance_data[day.date]) }}
                            </td>
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-left: 2px solid #000">{{ student.total_absent || 0 }}</td>
                            <td class="border border-gray-900 p-0.5 text-center text-xs">0</td>
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-right: 2px solid #000">-</td>
                        </tr>
                        <!-- Male Daily Totals Row -->
                        <tr class="bg-gray-50" style="height: 20px">
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-bottom: 2px solid #000; border-left: 2px solid #000"></td>
                            <td class="border border-gray-900 p-1 font-bold text-left text-xs" style="border-bottom: 2px solid #000; border-right: 2px solid #000">MALE | TOTAL Per Day</td>
                            <td
                                v-for="day in reportData.days_in_month"
                                :key="day.date"
                                class="border border-gray-900 p-0.5 text-center font-bold text-xs"
                                :style="{ borderBottom: '2px solid #000', borderLeft: getDayOfWeek(day.date) === 'M' ? '2px solid #000' : '' }"
                            >
                                {{ maleDailyTotals[day.date]?.present || 0 }}
                            </td>
                            <td class="border border-gray-900 p-0.5 text-center font-bold text-xs" style="border-bottom: 2px solid #000; border-left: 2px solid #000"></td>
                            <td class="border border-gray-900 p-0.5 text-center font-bold text-xs" style="border-bottom: 2px solid #000"></td>
                            <td class="border border-gray-900 p-0.5 text-center font-bold text-xs" style="border-bottom: 2px solid #000; border-right: 2px solid #000">{{ maleTotalPresent }}</td>
                        </tr>

                        <!-- Female Students Section -->
                        <tr>
                            <td :colspan="reportData.days_in_month.length + 4" class="border-2 border-gray-900 p-1 bg-gray-100 font-bold text-left text-xs" style="height: 22px">FEMALE</td>
                        </tr>
                        <tr v-for="(student, index) in femaleStudents" :key="student.id" style="height: 20px">
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-left: 2px solid #000">{{ index + 1 }}</td>
                            <td class="border border-gray-900 px-2 py-0.5 text-left text-xs" style="border-right: 2px solid #000">{{ student.name }}</td>
                            <td
                                v-for="day in reportData.days_in_month"
                                :key="day.date"
                                class="border border-gray-900 p-0.5 text-center text-xs"
                                :class="getAttendanceColor(student.attendance_data[day.date])"
                                :style="{ borderLeft: getDayOfWeek(day.date) === 'M' ? '2px solid #000' : '' }"
                            >
                                {{ getAttendanceMark(student.attendance_data[day.date]) }}
                            </td>
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-left: 2px solid #000">{{ student.total_absent || 0 }}</td>
                            <td class="border border-gray-900 p-0.5 text-center text-xs">0</td>
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-right: 2px solid #000">-</td>
                        </tr>
                        <!-- Female Daily Totals Row -->
                        <tr class="bg-gray-50" style="height: 20px">
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-bottom: 2px solid #000; border-left: 2px solid #000"></td>
                            <td class="border border-gray-900 p-1 font-bold text-left text-xs" style="border-bottom: 2px solid #000; border-right: 2px solid #000">FEMALE | TOTAL Per Day</td>
                            <td
                                v-for="day in reportData.days_in_month"
                                :key="day.date"
                                class="border border-gray-900 p-0.5 text-center font-bold text-xs"
                                :style="{ borderBottom: '2px solid #000', borderLeft: getDayOfWeek(day.date) === 'M' ? '2px solid #000' : '' }"
                            >
                                {{ femaleDailyTotals[day.date]?.present || 0 }}
                            </td>
                            <td class="border border-gray-900 p-0.5 text-center font-bold text-xs" style="border-bottom: 2px solid #000; border-left: 2px solid #000"></td>
                            <td class="border border-gray-900 p-0.5 text-center font-bold text-xs" style="border-bottom: 2px solid #000"></td>
                            <td class="border border-gray-900 p-0.5 text-center font-bold text-xs" style="border-bottom: 2px solid #000; border-right: 2px solid #000">{{ femaleTotalPresent }}</td>
                        </tr>

                        <!-- Combined Total Row -->
                        <tr class="bg-gray-50" style="height: 20px">
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-bottom: 2px solid #000; border-left: 2px solid #000"></td>
                            <td class="border border-gray-900 p-1 font-bold text-left text-xs" style="border-bottom: 2px solid #000; border-right: 2px solid #000">Combined TOTAL PER DAY</td>
                            <td
                                v-for="day in reportData.days_in_month"
                                :key="day.date"
                                class="border border-gray-900 p-0.5 text-center font-bold text-xs"
                                :style="{ borderBottom: '2px solid #000', borderLeft: getDayOfWeek(day.date) === 'M' ? '2px solid #000' : '' }"
                            >
                                {{ combinedDailyTotals[day.date]?.present || 0 }}
                            </td>
                            <td class="border border-gray-900 p-0.5 text-center font-bold text-xs" style="border-bottom: 2px solid #000; border-left: 2px solid #000"></td>
                            <td class="border border-gray-900 p-0.5 text-center font-bold text-xs" style="border-bottom: 2px solid #000"></td>
                            <td class="border border-gray-900 p-0.5 text-center font-bold text-xs" style="border-bottom: 2px solid #000; border-right: 2px solid #000">{{ combinedTotalPresent }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Summary Statistics -->
            <div class="grid grid-cols-3 gap-6 text-xs mt-8">
                <!-- Left Column - Guidelines -->
                <div class="p-3">
                    <h3 class="font-bold underline">GUIDELINES:</h3>
                    <div class="space-y-2 text-xs">
                        <p>1. The attendance shall be accomplished daily. Refer to the codes for checking learners' attendance.</p>
                        <p>2. Dates shall be written in the preceding columns beside Learner's Name.</p>
                        <p>3. To compute the following:</p>

                        <div class="ml-4 space-y-2">
                            <div class="flex items-center">
                                <span class="w-4">a.</span>
                                <span class="italic mr-2">Percentage of Enrollment =</span>
                                <div class="text-center border-b border-gray-800 px-2">
                                    <div>Registered Learner as of End of the Month</div>
                                    <div class="border-b border-gray-800 mb-1"></div>
                                    <div>Enrollment as of 1st Friday of June</div>
                                </div>
                                <span class="ml-2">x 100</span>
                            </div>

                            <div class="flex items-center">
                                <span class="w-4">b.</span>
                                <span class="italic mr-2">Average Daily Attendance =</span>
                                <div class="text-center border-b border-gray-800 px-2">
                                    <div>Total Daily Attendance</div>
                                    <div class="border-b border-gray-800 mb-1"></div>
                                    <div>Number of School Days in reporting month</div>
                                </div>
                            </div>

                            <div class="flex items-center">
                                <span class="w-4">c.</span>
                                <span class="italic mr-2">Percentage of Attendance for the month =</span>
                                <div class="text-center border-b border-gray-800 px-2">
                                    <div>Average daily attendance</div>
                                    <div class="border-b border-gray-800 mb-1"></div>
                                    <div>Registered Learner as of End of the month</div>
                                </div>
                                <span class="ml-2">x 100</span>
                            </div>
                        </div>

                        <p>
                            4. Every End of the month, the class adviser will submit this form to the office of the principal for recording of summary table into the School Form 4. Once signed by the principal, this form should be returned to the
                            adviser.
                        </p>
                        <p>5. The adviser will extend neccessary intervention including but not limited to home visitation to learner/s that committed 5 consecutive days of absences or those with potentials of dropping out</p>
                        <p>6. Attendance performance of learner is expected to reflect in Form 137 and Form 138 every grading period</p>
                        <p class="ml-4">* Beginning of School Year cut-off report is every 1st Friday of School Calendar Days</p>
                    </div>
                </div>

                <!-- Middle Column - Codes for Checking Attendance -->
                <div class="border border-gray-800 p-3">
                    <h3 class="font-bold text-center mb-2">CODES FOR CHECKING ATTENDANCE</h3>
                    <div class="space-y-1 text-xs">
                        <p><strong>Mark Present:</strong> (✓) Absent: Tardy (half shaded) Upper (L) for Late, Lower (E) for Excused</p>
                        <p><strong>REASONS/CAUSES OF DROP OUTS</strong></p>
                        <p><strong>Domestic Related Factors</strong></p>
                        <p>a.1 Had to take care of siblings</p>
                        <p>a.2 Early marriage/pregnancy</p>
                        <p>a.3 Parents' attitude toward schooling</p>
                        <p>a.4 Family problems</p>
                        <p><strong>Individual Related Factors</strong></p>
                        <p>b.1 Illness</p>
                        <p>b.2 Overage</p>
                        <p>b.3 Death</p>
                        <p>b.4 Drug Abuse</p>
                        <p>b.5 Poor academic performance</p>
                        <p>b.6 Lack of interest/distractions</p>
                        <p>b.7 Hunger/Malnutrition</p>
                        <p>b.8 Child labor/street children</p>
                        <p>c.1 Teacher Factor</p>
                        <p>c.2 Physical condition of classroom</p>
                        <p>c.3 Peer influence</p>
                        <p>d Geographic/Environmental</p>
                        <p>e.1 Distance between home and school</p>
                        <p>e.2 Armed conflict (incl. Tribal wars & conflicts)</p>
                        <p>e.3 Calamities/Disasters</p>
                        <p>e.4 Financial Related</p>
                        <p>e.5 Child labor: work</p>
                        <p>f Others: _______</p>
                    </div>
                </div>

                <!-- Right Column - Summary Data -->
                <div class="space-y-4">
                    <div class="border border-gray-800">
                        <div class="bg-gray-100 p-2 text-center font-bold">Summary for the Month</div>
                        <table class="w-full border-collapse text-xs">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="border border-gray-800 p-1 text-left"></th>
                                    <th class="border border-gray-800 p-1 text-center">M</th>
                                    <th class="border border-gray-800 p-1 text-center">F</th>
                                    <th class="border border-gray-800 p-1 text-center">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="border border-gray-800 p-1">• Enrollment as of (1st Friday of June)</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary.male.enrollment }}</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary.female.enrollment }}</td>
                                    <td class="border border-gray-800 p-1 text-center font-bold">{{ reportData.summary.total.enrollment }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-800 p-1">Late Enrollment during the month (beyond cut-off)</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-800 p-1">Registered Learner as of end of the month</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary.male.enrollment }}</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary.female.enrollment }}</td>
                                    <td class="border border-gray-800 p-1 text-center font-bold">{{ reportData.summary.total.enrollment }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-800 p-1">Percentage of Enrollment as of end of the month</td>
                                    <td class="border border-gray-800 p-1 text-center">100%</td>
                                    <td class="border border-gray-800 p-1 text-center">100%</td>
                                    <td class="border border-gray-800 p-1 text-center">100%</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-800 p-1">Average Daily Attendance</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary.male.attendance_rate }}%</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary.female.attendance_rate }}%</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary.total.attendance_rate }}%</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-800 p-1">Percentage of Attendance for the month</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary.male.attendance_rate }}%</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary.female.attendance_rate }}%</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary.total.attendance_rate }}%</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-800 p-1">Number of students absent for 5 consecutive days or more</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-800 p-1">Drop out</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-800 p-1">Transferred out</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-800 p-1">Transferred in</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                    <td class="border border-gray-800 p-1 text-center">0</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="mt-8 text-sm bg-white">
                <!-- Certification Statement -->
                <div class="mb-6">
                    <p class="italic">I certify that this is a true and correct report.</p>
                </div>

                <!-- Signatures Section - Horizontal Layout -->
                <div class="flex items-start justify-between">
                    <!-- Left: Prepared by -->
                    <div style="width: 25%">
                        <div class="flex items-baseline gap-2 mb-1">
                            <span>Prepared by:</span>
                            <span class="font-medium">{{ reportData.section.teacher?.name || 'Maria Santos' }}</span>
                        </div>
                        <div class="border-b border-gray-800 w-64 mb-1"></div>
                        <p class="text-xs italic">(Signature of Teacher over Printed Name)</p>
                    </div>

                    <!-- Right: Attested by -->
                    <div style="width: 25%; padding-right: 2rem">
                        <div class="flex items-baseline gap-2 mb-1">
                            <span>Attested by:</span>
                            <span class="font-medium">Principal Name</span>
                        </div>
                        <div class="border-b border-gray-800 w-64 mb-1"></div>
                        <p class="text-xs italic">(Signature of School Head over Printed Name)</p>
                    </div>
                </div>
            </div>

            <!-- Form Footer -->
            <div class="mt-8 text-xs text-center text-gray-600 bg-white">
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
    font-family: 'Arial', 'Calibri', sans-serif;
    line-height: 1.2;
}

.attendance-table-container {
    overflow-x: auto;
}

.attendance-table-container table {
    min-width: 1200px;
    table-layout: fixed;
}

.attendance-table-container th,
.attendance-table-container td {
    border: 1px solid #000;
    padding: 2px 4px;
    line-height: 1.2;
}

/* Compact row styling */
.attendance-table-container tbody tr {
    height: 20px;
}

/* Header row styling */
.attendance-table-container thead th {
    background-color: #f3f4f6;
    font-weight: 600;
}

/* Day of week cells - simple white background */
.attendance-table-container thead tr:last-child th {
    background-color: #ffffff !important;
}

/* Section header rows (MALE, FEMALE) */
.attendance-table-container tbody tr td[colspan] {
    background-color: #e5e7eb;
    font-weight: bold;
    text-align: left;
}

/* Weekend column styling */
.weekend-column {
    border-left: 2px solid #4b5563 !important;
}

/* Week separator */
.week-separator {
    border-left: 2px solid #9ca3af !important;
}

/* Remove colorful backgrounds from attendance cells */
.text-green-600,
.text-red-600,
.text-yellow-600 {
    color: inherit !important;
}

/* Print Styles */
@media print {
    /* Hide no-print elements */
    .no-print {
        display: none !important;
    }

    /* Page setup - Multiple pages allowed */
    @page {
        size: A4 landscape;
        margin: 0.5cm;
    }

    /* Remove container backgrounds and constraints */
    body,
    html {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        height: auto !important;
        overflow: visible !important;
    }

    .sf2-report-container {
        background: white !important;
        padding: 0 !important;
        margin: 0 !important;
        min-height: auto !important;
        height: auto !important;
        overflow: visible !important;
    }

    /* Allow report card to flow naturally across multiple pages */
    .sf2-report-card {
        position: relative !important;
        width: 100% !important;
        max-width: 100% !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 10px !important;
        margin: 0 !important;
        background: white !important;
        overflow: visible !important;
        page-break-after: auto !important;
        height: auto !important;
    }

    /* Attendance table print optimization */
    .attendance-table-container {
        overflow: visible !important;
        page-break-inside: avoid;
    }

    .attendance-table-container table {
        font-size: 7px !important;
        width: 100% !important;
        page-break-inside: avoid;
    }

    .attendance-table-container th,
    .attendance-table-container td {
        padding: 1px 2px !important;
        font-size: 7px !important;
    }

    /* Summary section - allow page break if needed */
    .grid {
        page-break-inside: auto !important;
        overflow: visible !important;
    }

    /* Reduce margins and padding for compact print */
    .sf2-report-card h1,
    .sf2-report-card h2,
    .sf2-report-card h3 {
        margin: 2px 0 !important;
        font-size: 10px !important;
    }

    .sf2-report-card p {
        margin: 1px 0 !important;
        font-size: 7px !important;
    }

    /* Preserve colors and backgrounds */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    /* Ensure footer section is visible */
    .sf2-report-card > div {
        page-break-inside: avoid;
    }

    /* Scale down text in summary sections */
    .text-xs {
        font-size: 6px !important;
    }

    /* Reduce spacing */
    .mb-6 {
        margin-bottom: 8px !important;
    }

    .mb-8 {
        margin-bottom: 10px !important;
    }

    .mt-8 {
        margin-top: 10px !important;
    }

    .gap-6 {
        gap: 8px !important;
    }

    /* Ensure white background for footer/signature sections */
    .bg-white {
        background-color: white !important;
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
