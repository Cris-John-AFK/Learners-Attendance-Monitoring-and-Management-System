<script setup>
import axios from 'axios';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Dialog from 'primevue/dialog';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onMounted, ref } from 'vue';
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

// Edit mode state
const isEditMode = ref(false);
const showEditDialog = ref(false);
const editingCell = ref(null);
const editAttendanceValue = ref('');

// Day annotation state (for holidays, events, etc.)
const showDayAnnotationDialog = ref(false);
const editingDay = ref(null);
const dayAnnotationValue = ref('');
const dayAnnotations = ref({}); // Store annotations by date

// Computed properties
const maleStudents = computed(() => {
    if (!reportData.value?.students) return [];
    return reportData.value.students.filter((student) => student.gender === 'Male');
});

const femaleStudents = computed(() => {
    if (!reportData.value?.students) return [];
    return reportData.value.students.filter((student) => student.gender === 'Female');
});

// Generate fixed M-T-W-TH-F columns (always 5 weeks = 25 columns)
const fixedWeekdayColumns = computed(() => {
    const totalColumns = 25; // 5 weeks × 5 weekdays
    const weekdays = ['M', 'T', 'W', 'TH', 'F'];
    const columns = [];

    // Create a map of dates from backend data
    const dateMap = {};
    if (reportData.value?.days_in_month) {
        reportData.value.days_in_month.forEach((day) => {
            const date = new Date(day.date);
            const dayOfWeek = date.getDay(); // 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri
            dateMap[day.date] = {
                ...day,
                dayOfWeek
            };
        });
    }

    // Get all dates sorted
    const sortedDates = Object.keys(dateMap).sort();
    let dateIndex = 0;

    // Generate 25 fixed columns
    for (let i = 0; i < totalColumns; i++) {
        const weekdayIndex = i % 5; // 0=M, 1=T, 2=W, 3=TH, 4=F
        const weekdayName = weekdays[weekdayIndex];
        const expectedDayOfWeek = weekdayIndex + 1; // 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri

        // Check if we have a date that matches this weekday position
        let hasDate = false;
        let dateInfo = null;

        if (dateIndex < sortedDates.length) {
            const currentDate = sortedDates[dateIndex];
            const currentDateInfo = dateMap[currentDate];

            // If this date matches the expected weekday
            if (currentDateInfo.dayOfWeek === expectedDayOfWeek) {
                hasDate = true;
                dateInfo = currentDateInfo;
                dateIndex++;
            }
        }

        columns.push({
            date: hasDate ? dateInfo.date : null,
            day: hasDate ? dateInfo.day : '',
            dayName: weekdayName,
            isEmpty: !hasDate
        });
    }

    return columns;
});

// Calculate daily totals for male students - only count past/current dates
const maleDailyTotals = computed(() => {
    if (!reportData.value?.days_in_month || !maleStudents.value.length) return {};

    const totals = {};
    reportData.value.days_in_month.forEach((day) => {
        // For future dates, return 0 totals
        if (isFutureDate(day.date)) {
            totals[day.date] = { present: 0, absent: 0, late: 0, excused: 0, dropout: 0, total: 0 };
            return;
        }

        let present = 0,
            absent = 0,
            late = 0,
            excused = 0,
            dropout = 0;

        maleStudents.value.forEach((student) => {
            const status = student.attendance_data?.[day.date];
            if (status === 'present') present++;
            else if (status === 'absent') absent++;
            else if (status === 'late') late++;
            else if (status === 'excused') excused++;
            else if (status === 'dropout') dropout++;
        });

        totals[day.date] = { present, absent, late, excused, dropout, total: present + absent + late + excused + dropout };
    });

    return totals;
});

// Calculate daily totals for female students - only count past/current dates
const femaleDailyTotals = computed(() => {
    if (!reportData.value?.days_in_month || !femaleStudents.value.length) return {};

    const totals = {};
    reportData.value.days_in_month.forEach((day) => {
        // For future dates, return 0 totals
        if (isFutureDate(day.date)) {
            totals[day.date] = { present: 0, absent: 0, late: 0, excused: 0, dropout: 0, total: 0 };
            return;
        }

        let present = 0,
            absent = 0,
            late = 0,
            excused = 0,
            dropout = 0;

        femaleStudents.value.forEach((student) => {
            const status = student.attendance_data?.[day.date];
            if (status === 'present') present++;
            else if (status === 'absent') absent++;
            else if (status === 'late') late++;
            else if (status === 'excused') excused++;
            else if (status === 'dropout') dropout++;
        });

        totals[day.date] = { present, absent, late, excused, dropout, total: present + absent + late + excused + dropout };
    });

    return totals;
});

// Calculate combined daily totals
const combinedDailyTotals = computed(() => {
    if (!reportData.value?.days_in_month) return {};

    const totals = {};
    reportData.value.days_in_month.forEach((day) => {
        const maleTotal = maleDailyTotals.value[day.date] || { present: 0, absent: 0, late: 0, excused: 0, dropout: 0 };
        const femaleTotal = femaleDailyTotals.value[day.date] || { present: 0, absent: 0, late: 0, excused: 0, dropout: 0 };

        totals[day.date] = {
            present: maleTotal.present + femaleTotal.present,
            absent: maleTotal.absent + femaleTotal.absent,
            late: maleTotal.late + femaleTotal.late,
            excused: maleTotal.excused + femaleTotal.excused,
            dropout: maleTotal.dropout + femaleTotal.dropout,
            total: maleTotal.present + maleTotal.absent + maleTotal.late + maleTotal.excused + maleTotal.dropout + femaleTotal.present + femaleTotal.absent + femaleTotal.late + femaleTotal.excused + femaleTotal.dropout
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

// Toggle Edit SF2 mode
const editSF2 = () => {
    isEditMode.value = !isEditMode.value;

    if (isEditMode.value) {
        toast.add({
            severity: 'success',
            summary: 'Edit Mode Enabled',
            detail: 'Click on any day column to edit attendance',
            life: 3000
        });
    } else {
        toast.add({
            severity: 'info',
            summary: 'Edit Mode Disabled',
            detail: 'Changes saved successfully',
            life: 3000
        });
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

        // Get authenticated teacher ID
        const teacherData = JSON.parse(localStorage.getItem('teacher_data') || '{}');
        const teacherId = teacherData?.teacher?.id || teacherData?.id || 1;

        // Use the simple working endpoint
        const response = await axios.post('http://127.0.0.1:8000/api/sf2/submit', {
            section_id: parseInt(sectionId),
            month: monthStr,
            teacher_id: teacherId
        });

        if (response.data.success) {
            toast.add({
                severity: 'success',
                summary: 'Successfully Submitted!',
                detail: `SF2 report for ${reportData.value?.section?.name || 'Matatag'} has been submitted to admin`,
                life: 5000
            });

            // Stay on the same page - no redirect
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

// Check if a date is in the future
const isFutureDate = (dateString) => {
    if (!dateString) return false;
    const date = new Date(dateString);
    date.setHours(0, 0, 0, 0); // Reset hours to compare dates only
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return date > today; // October 2+ = true, October 1 = false
};

// Helper function to generate remarks based on student status
const getStudentRemarks = (student) => {
    if (!student) return '-';

    // Check if student has dropout/transfer status
    if (student.enrollment_status === 'dropped_out' && student.dropout_reason) {
        // Map reason codes to full text as per DepEd guidelines
        const reasonMap = {
            a1: 'a.1 Had to take care of siblings',
            a2: 'a.2 Early marriage/pregnancy',
            a3: "a.3 Parents' attitude toward schooling",
            a4: 'a.4 Family problems',
            b1: 'b.1 Illness',
            b2: 'b.2 Disease',
            b3: 'b.3 Death',
            b4: 'b.4 Disability',
            b5: 'b.5 Poor academic performance',
            b6: 'b.6 Disinterest/lack of ambitions',
            b7: 'b.7 Hunger/Malnutrition',
            c1: 'c.1 Teacher Factor',
            c2: 'c.2 Physical condition of classroom',
            c3: 'c.3 Peer Factor',
            d1: 'd.1 Distance from home to school',
            d2: 'd.2 Armed conflict (incl. Tribal wars & clan feuds)',
            d3: 'd.3 Calamities/disaster',
            d4: 'd.4 Work-Related',
            d5: 'd.5 Transferred/work'
        };

        const reasonText = reasonMap[student.dropout_reason] || student.dropout_reason;
        return `DROPPED OUT - ${reasonText}`;
    }

    if (student.enrollment_status === 'transferred_out') {
        const reasonMap = {
            a1: 'a.1 Had to take care of siblings',
            a2: 'a.2 Early marriage/pregnancy',
            a3: "a.3 Parents' attitude toward schooling",
            a4: 'a.4 Family problems',
            b1: 'b.1 Illness',
            b2: 'b.2 Disease',
            b4: 'b.4 Disability',
            c1: 'c.1 Teacher Factor',
            c2: 'c.2 Physical condition of classroom',
            c3: 'c.3 Peer Factor',
            d1: 'd.1 Distance from home to school',
            d2: 'd.2 Armed conflict (incl. Tribal wars & clan feuds)',
            d3: 'd.3 Calamities/disaster',
            d4: 'd.4 Work-Related',
            d5: 'd.5 Transferred/work'
        };
        const reasonText = reasonMap[student.dropout_reason] || student.dropout_reason;
        return `TRANSFERRED OUT - ${reasonText}`;
    }

    if (student.enrollment_status === 'transferred_in') {
        return 'TRANSFERRED IN';
    }

    return '-';
};

// Get attendance mark for display - hide marks for future dates
const getAttendanceMark = (status, dateString = null) => {
    // If date is in the future, return empty (will show slash only)
    if (dateString && isFutureDate(dateString)) {
        return '';
    }

    switch (status) {
        case 'present':
            return ''; // Blank for present (only green background)
        case 'absent':
            return '✗';
        case 'late':
            return 'L';
        case 'excused':
            return 'E';
        case 'dropout':
            return 'D';
        default:
            return '-';
    }
};

// Get attendance mark color class based on the mark symbol
const getAttendanceColorClass = (mark, dateString = null) => {
    // If date is in the future, no special class (will show default slash)
    if (dateString && isFutureDate(dateString)) {
        return '';
    }

    switch (mark) {
        case '': // Blank means present
        case '✓':
            return 'attendance-present';
        case '✗':
            return 'attendance-absent';
        case 'L':
            return 'attendance-late';
        case 'E':
            return 'attendance-excused';
        case 'D':
            return 'attendance-dropout';
        default:
            return '';
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
        case 'excused':
            return 'text-blue-600';
        case 'dropout':
            return 'text-purple-600';
        default:
            return 'text-gray-400';
    }
};

// Calculate total absent days for a student - only count past/current dates
const calculateAbsentCount = (student) => {
    if (!student.attendance_data) return 0;

    let absentCount = 0;
    Object.entries(student.attendance_data).forEach(([date, status]) => {
        // Only count absences for dates that have already occurred (not future dates)
        if (status === 'absent' && !isFutureDate(date)) {
            absentCount++;
        }
    });

    return absentCount;
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

// Open edit dialog for attendance cell
const openEditDialog = (student, date, day) => {
    if (!isEditMode.value) {
        toast.add({
            severity: 'warn',
            summary: 'Edit Mode Required',
            detail: 'Please click "Edit (SF2)" button first to enable editing',
            life: 3000
        });
        return;
    }

    editingCell.value = {
        student: student,
        date: date,
        day: day,
        currentValue: getAttendanceMark(student.attendance_data?.[date], date)
    };
    editAttendanceValue.value = getAttendanceMark(student.attendance_data?.[date], date) || '';
    showEditDialog.value = true;
};

// Save attendance edit
const saveAttendanceEdit = async () => {
    if (!editingCell.value) return;

    const { student, date } = editingCell.value;
    const inputValue = editAttendanceValue.value.trim();

    // Convert text input to status
    let status = null;
    if (inputValue === '✓' || inputValue.toLowerCase() === 'p' || inputValue.toLowerCase() === 'present') {
        status = 'present';
    } else if (inputValue === '✗' || inputValue === 'x' || inputValue.toLowerCase() === 'a' || inputValue.toLowerCase() === 'absent') {
        status = 'absent';
    } else if (inputValue === 'L' || inputValue.toLowerCase() === 'l' || inputValue.toLowerCase() === 'late') {
        status = 'late';
    } else if (inputValue === 'E' || inputValue.toLowerCase() === 'e' || inputValue.toLowerCase() === 'excused') {
        status = 'excused';
    } else if (inputValue === 'D' || inputValue.toLowerCase() === 'd' || inputValue.toLowerCase() === 'dropout' || inputValue.toLowerCase() === 'drop out') {
        status = 'dropout';
    } else if (inputValue === '' || inputValue === '-') {
        status = null;
    }

    // ✨ INSTANT UPDATE: Update local data first for immediate visual feedback
    if (!student.attendance_data) {
        student.attendance_data = {};
    }
    const oldStatus = student.attendance_data[date];
    student.attendance_data[date] = status;
    
    // Force Vue reactivity update
    await nextTick();

    // Close dialog immediately for instant feel
    closeEditDialog();
    
    // Show success toast immediately
    toast.add({
        severity: 'success',
        summary: 'Updated',
        detail: `Attendance for ${student.name} on day ${editingCell.value.day} has been updated to ${getAttendanceMark(status) || '✓'}`,
        life: 3000
    });

    // Send update to backend API in background (non-blocking)
    try {
        await axios.post(`/api/teacher/reports/sf2/save-edit`, {
            student_id: student.id,
            date: date,
            status: status,
            section_id: sectionId,
            month: selectedMonth.value.toISOString().slice(0, 7) // YYYY-MM format
        });

        console.log('✅ SF2 edit saved to backend successfully');
    } catch (error) {
        console.error('❌ Error saving SF2 edit to backend:', error);
        
        // Revert the change if backend save failed
        student.attendance_data[date] = oldStatus;
        await nextTick();
        
        toast.add({
            severity: 'error',
            summary: 'Save Failed',
            detail: 'Failed to save to server. Change has been reverted. Please try again.',
            life: 5000
        });
    }
};

// Close edit dialog
const closeEditDialog = () => {
    showEditDialog.value = false;
    editingCell.value = null;
    editAttendanceValue.value = '';
};

// Open day annotation dialog
const openDayAnnotationDialog = (date, day) => {
    if (!isEditMode.value) {
        toast.add({
            severity: 'warn',
            summary: 'Edit Mode Required',
            detail: 'Please click "Edit (SF2)" button first to enable editing',
            life: 3000
        });
        return;
    }

    editingDay.value = { date, day };
    dayAnnotationValue.value = dayAnnotations.value[date] || '';
    showDayAnnotationDialog.value = true;
};

// Save day annotation
const saveDayAnnotation = () => {
    if (!editingDay.value) return;

    const { date, day } = editingDay.value;
    const annotation = dayAnnotationValue.value.trim();

    if (annotation) {
        dayAnnotations.value[date] = annotation;
        toast.add({
            severity: 'success',
            summary: 'Day Annotated',
            detail: `Day ${day} marked as: ${annotation}`,
            life: 3000
        });
    } else {
        // Remove annotation if empty
        delete dayAnnotations.value[date];
        toast.add({
            severity: 'info',
            summary: 'Annotation Removed',
            detail: `Day ${day} annotation cleared`,
            life: 3000
        });
    }

    // TODO: Send to backend API
    // await axios.put(`/api/teacher/reports/sf2/annotate-day`, {
    //     section_id: sectionId,
    //     date: date,
    //     annotation: annotation
    // });

    closeDayAnnotationDialog();
};

// Close day annotation dialog
const closeDayAnnotationDialog = () => {
    showDayAnnotationDialog.value = false;
    editingDay.value = null;
    dayAnnotationValue.value = '';
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
                <Button icon="pi pi-pencil" :label="isEditMode ? 'Exit Edit Mode' : 'Edit (SF2)'" :class="isEditMode ? 'p-button-warning' : 'p-button-info'" @click="editSF2" />
                <Button icon="pi pi-print" label="Print" class="p-button-outlined" @click="printReport" />
                <Button icon="pi pi-download" label="Download Excel" class="p-button-success" @click="downloadExcel" />
                <Button icon="pi pi-send" label="Consolidate for Storing" class="p-button-warning" :loading="submitting" @click="submitToAdmin" />
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
                            <th :colspan="fixedWeekdayColumns.length" class="border-2 border-gray-900 p-1 bg-gray-50 text-center font-bold">
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
                                v-for="(col, index) in fixedWeekdayColumns"
                                :key="`day-${index}`"
                                class="border border-gray-900 p-0.5 bg-gray-50 text-center font-bold relative"
                                :class="[!col.isEmpty && isEditMode ? 'cursor-pointer hover:bg-blue-200' : '', dayAnnotations[col.date] ? 'bg-gray-300' : '']"
                                :style="{ width: '22px', borderLeft: col.dayName === 'M' ? '2px solid #000' : '' }"
                                @click="!col.isEmpty && openDayAnnotationDialog(col.date, col.day)"
                                :title="!col.isEmpty && isEditMode ? 'Click to annotate day (Holiday, Event, etc.)' : dayAnnotations[col.date] || ''"
                            >
                                <div class="text-xs" :class="dayAnnotations[col.date] ? 'text-red-700 font-bold' : ''">{{ col.day }}</div>
                            </th>
                        </tr>
                        <!-- Row 3: Day of Week and Column Headers -->
                        <tr>
                            <!-- Day of week labels -->
                            <th
                                v-for="(col, index) in fixedWeekdayColumns"
                                :key="`dow-${index}`"
                                class="border-2 border-gray-900 p-0.5 text-center text-xs font-bold"
                                :class="[col.isEmpty ? 'bg-gray-100' : dayAnnotations[col.date] ? 'bg-gray-300' : 'bg-white', dayAnnotations[col.date] ? 'text-red-700' : '']"
                                :style="{ height: '24px', borderTop: '2px solid #000', borderBottom: '2px solid #000', borderLeft: col.dayName === 'M' ? '2px solid #000' : '' }"
                            >
                                {{ col.dayName }}
                            </th>
                            <th
                                class="border-2 border-gray-900 bg-gray-50 text-center font-bold"
                                style="width: 60px; padding: 1px 1px; font-size: 6.5px; border-top: 2px solid #000; border-bottom: 2px solid #000; border-left: 2px solid #000; border-right: 2px solid #000"
                            >
                                ABSENT
                            </th>
                            <th
                                class="border-2 border-gray-900 bg-gray-50 text-center font-bold"
                                style="width: 60px; padding: 1px 1px; font-size: 6.5px; border-top: 2px solid #000; border-bottom: 2px solid #000; border-left: 2px solid #000; border-right: 1px solid #000"
                            >
                                TARDY
                            </th>
                        </tr>
                    </thead>

                    <!-- Male Students Section -->
                    <tbody>
                        <tr>
                            <td :colspan="fixedWeekdayColumns.length + 4" class="border-2 border-gray-900 p-1 bg-gray-100 font-bold text-left text-xs" style="height: 22px">MALE</td>
                        </tr>
                        <tr v-for="(student, index) in maleStudents" :key="student.id" style="height: 20px">
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-left: 2px solid #000">{{ index + 1 }}</td>
                            <td class="border border-gray-900 px-2 py-0.5 text-left text-xs" style="border-right: 2px solid #000">{{ student.name }}</td>
                            <!-- Holiday merged cell - only show for first student -->
                            <template v-for="(col, idx) in fixedWeekdayColumns" :key="`male-${student.id}-day-${idx}`">
                                <td
                                    v-if="dayAnnotations[col.date] && index === 0"
                                    :rowspan="maleStudents.length"
                                    class="border border-gray-900 bg-gray-200"
                                    :style="{
                                        borderLeft: col.dayName === 'M' ? '2px solid #000' : '',
                                        padding: '4px 0',
                                        verticalAlign: 'middle',
                                        textAlign: 'center',
                                        height: `${maleStudents.length * 20}px`
                                    }"
                                >
                                    <div
                                        :style="{
                                            writingMode: 'vertical-rl',
                                            textOrientation: 'upright',
                                            fontSize: Math.min(14, Math.max(8, ((maleStudents.length * 20) / dayAnnotations[col.date].length) * 0.8)) + 'px',
                                            color: '#991b1b',
                                            fontWeight: 'bold',
                                            letterSpacing: '-1px',
                                            lineHeight: '1',
                                            whiteSpace: 'nowrap',
                                            margin: '0 auto',
                                            display: 'inline-block'
                                        }"
                                    >
                                        {{ dayAnnotations[col.date] }}
                                    </div>
                                </td>
                                <!-- Regular attendance cells -->
                                <td
                                    v-if="!dayAnnotations[col.date]"
                                    class="border border-gray-900 p-0.5 text-center text-xs font-semibold relative attendance-cell"
                                    :class="[
                                        col.isEmpty ? 'bg-gray-100' : '',
                                        student.attendance_data?.[col.date] === 'late' ? 'tardy-half-shaded' : getAttendanceColorClass(getAttendanceMark(student.attendance_data[col.date], col.date), col.date),
                                        !col.isEmpty && isEditMode ? 'cursor-pointer hover:bg-blue-100 border-2 border-blue-400' : '',
                                        !col.isEmpty && !isEditMode ? 'cursor-not-allowed' : ''
                                    ]"
                                    :style="{ borderLeft: col.dayName === 'M' ? '2px solid #000' : '' }"
                                    @click="!col.isEmpty && openEditDialog(student, col.date, col.day)"
                                    :title="!col.isEmpty && isEditMode ? 'Click to edit' : !col.isEmpty ? 'Enable Edit Mode first' : ''"
                                >
                                    <span v-if="student.attendance_data?.[col.date] === 'late'" class="tardy-text">L</span>
                                    <span v-else>{{ col.isEmpty ? '' : getAttendanceMark(student.attendance_data[col.date], col.date) }}</span>
                                </td>
                            </template>
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-left: 2px solid #000">{{ calculateAbsentCount(student) }}</td>
                            <td class="border border-gray-900 p-0.5 text-center text-xs">0</td>
                            <td class="border border-gray-900 p-0.5 text-left text-xs" style="border-right: 2px solid #000; font-size: 9px; line-height: 1.1">{{ getStudentRemarks(student) }}</td>
                        </tr>
                        <!-- Male Daily Totals Row -->
                        <tr class="bg-gray-50" style="height: 20px">
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-bottom: 2px solid #000; border-left: 2px solid #000"></td>
                            <td class="border border-gray-900 p-1 font-bold text-left text-xs" style="border-bottom: 2px solid #000; border-right: 2px solid #000">MALE | TOTAL Per Day</td>
                            <td
                                v-for="(col, idx) in fixedWeekdayColumns"
                                :key="`male-total-${idx}`"
                                class="border border-gray-900 p-0.5 text-center font-bold text-xs"
                                :class="[col.isEmpty ? 'bg-gray-100' : '', dayAnnotations[col.date] ? 'bg-gray-200' : '']"
                                :style="{ borderBottom: '2px solid #000', borderLeft: col.dayName === 'M' ? '2px solid #000' : '' }"
                            >
                                {{ col.isEmpty || dayAnnotations[col.date] ? '' : maleDailyTotals[col.date]?.present || 0 }}
                            </td>
                            <td class="border border-gray-900 p-0.5 text-center font-bold text-xs" style="border-bottom: 2px solid #000; border-left: 2px solid #000"></td>
                            <td class="border border-gray-900 p-0.5 text-center font-bold text-xs" style="border-bottom: 2px solid #000"></td>
                            <td class="border border-gray-900 p-0.5 text-center font-bold text-xs" style="border-bottom: 2px solid #000; border-right: 2px solid #000">{{ maleTotalPresent }}</td>
                        </tr>

                        <!-- Female Students Section -->
                        <tr>
                            <td :colspan="fixedWeekdayColumns.length + 4" class="border-2 border-gray-900 p-1 bg-gray-100 font-bold text-left text-xs" style="height: 22px">FEMALE</td>
                        </tr>
                        <tr v-for="(student, index) in femaleStudents" :key="student.id" style="height: 20px">
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-left: 2px solid #000">{{ index + 1 }}</td>
                            <td class="border border-gray-900 px-2 py-0.5 text-left text-xs" style="border-right: 2px solid #000">{{ student.name }}</td>
                            <!-- Holiday merged cell - only show for first student -->
                            <template v-for="(col, idx) in fixedWeekdayColumns" :key="`female-${student.id}-day-${idx}`">
                                <td
                                    v-if="dayAnnotations[col.date] && index === 0"
                                    :rowspan="femaleStudents.length"
                                    class="border border-gray-900 bg-gray-200"
                                    :style="{
                                        borderLeft: col.dayName === 'M' ? '2px solid #000' : '',
                                        padding: '4px 0',
                                        verticalAlign: 'middle',
                                        textAlign: 'center',
                                        height: `${femaleStudents.length * 20}px`
                                    }"
                                >
                                    <div
                                        :style="{
                                            writingMode: 'vertical-rl',
                                            textOrientation: 'upright',
                                            fontSize: Math.min(14, Math.max(8, ((femaleStudents.length * 20) / dayAnnotations[col.date].length) * 0.8)) + 'px',
                                            color: '#991b1b',
                                            fontWeight: 'bold',
                                            letterSpacing: '-1px',
                                            lineHeight: '1',
                                            whiteSpace: 'nowrap',
                                            margin: '0 auto',
                                            display: 'inline-block'
                                        }"
                                    >
                                        {{ dayAnnotations[col.date] }}
                                    </div>
                                </td>
                                <!-- Regular attendance cells -->
                                <td
                                    v-if="!dayAnnotations[col.date]"
                                    class="border border-gray-900 p-0.5 text-center text-xs font-semibold relative attendance-cell"
                                    :class="[
                                        col.isEmpty ? 'bg-gray-100' : '',
                                        student.attendance_data?.[col.date] === 'late' ? 'tardy-half-shaded' : getAttendanceColorClass(getAttendanceMark(student.attendance_data[col.date], col.date), col.date),
                                        !col.isEmpty && isEditMode ? 'cursor-pointer hover:bg-blue-100 border-2 border-blue-400' : '',
                                        !col.isEmpty && !isEditMode ? 'cursor-not-allowed' : ''
                                    ]"
                                    :style="{ borderLeft: col.dayName === 'M' ? '2px solid #000' : '' }"
                                    @click="!col.isEmpty && openEditDialog(student, col.date, col.day)"
                                    :title="!col.isEmpty && isEditMode ? 'Click to edit' : !col.isEmpty ? 'Enable Edit Mode first' : ''"
                                >
                                    <span v-if="student.attendance_data?.[col.date] === 'late'" class="tardy-text">L</span>
                                    <span v-else>{{ col.isEmpty ? '' : getAttendanceMark(student.attendance_data[col.date], col.date) }}</span>
                                </td>
                            </template>
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-left: 2px solid #000">{{ calculateAbsentCount(student) }}</td>
                            <td class="border border-gray-900 p-0.5 text-center text-xs">0</td>
                            <td class="border border-gray-900 p-0.5 text-left text-xs" style="border-right: 2px solid #000; font-size: 9px; line-height: 1.1">{{ getStudentRemarks(student) }}</td>
                        </tr>
                        <!-- Female Daily Totals Row -->
                        <tr class="bg-gray-50" style="height: 20px">
                            <td class="border border-gray-900 p-0.5 text-center text-xs" style="border-bottom: 2px solid #000; border-left: 2px solid #000"></td>
                            <td class="border border-gray-900 p-1 font-bold text-left text-xs" style="border-bottom: 2px solid #000; border-right: 2px solid #000">FEMALE | TOTAL Per Day</td>
                            <td
                                v-for="(col, idx) in fixedWeekdayColumns"
                                :key="`female-total-${idx}`"
                                class="border border-gray-900 p-0.5 text-center font-bold text-xs"
                                :class="[col.isEmpty ? 'bg-gray-100' : '', dayAnnotations[col.date] ? 'bg-gray-200' : '']"
                                :style="{ borderBottom: '2px solid #000', borderLeft: col.dayName === 'M' ? '2px solid #000' : '' }"
                            >
                                {{ col.isEmpty || dayAnnotations[col.date] ? '' : femaleDailyTotals[col.date]?.present || 0 }}
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
                                v-for="(col, idx) in fixedWeekdayColumns"
                                :key="`combined-total-${idx}`"
                                class="border border-gray-900 p-0.5 text-center font-bold text-xs"
                                :class="[col.isEmpty ? 'bg-gray-100' : '', dayAnnotations[col.date] ? 'bg-gray-200' : '']"
                                :style="{ borderBottom: '2px solid #000', borderLeft: col.dayName === 'M' ? '2px solid #000' : '' }"
                            >
                                {{ col.isEmpty || dayAnnotations[col.date] ? '' : combinedDailyTotals[col.date]?.present || 0 }}
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
                        <p>(blank) - Present; (x) - Absent; Tardy (half shaded = Upper for Late Comer, Lower for Cutting Classes)</p>
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
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary?.male?.dropouts || 0 }}</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary?.female?.dropouts || 0 }}</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary?.total?.dropouts || 0 }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-800 p-1">Transferred out</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary?.male?.transferred_out || 0 }}</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary?.female?.transferred_out || 0 }}</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary?.total?.transferred_out || 0 }}</td>
                                </tr>
                                <tr>
                                    <td class="border border-gray-800 p-1">Transferred in</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary?.male?.transferred_in || 0 }}</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary?.female?.transferred_in || 0 }}</td>
                                    <td class="border border-gray-800 p-1 text-center">{{ reportData.summary?.total?.transferred_in || 0 }}</td>
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

        <!-- Edit Attendance Dialog -->
        <Dialog v-model:visible="showEditDialog" modal header="Edit Attendance" :style="{ width: '450px' }" class="no-print">
            <div v-if="editingCell" class="space-y-4">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border-l-4 border-blue-500">
                    <p class="text-base font-bold text-gray-800">{{ editingCell.student.name }}</p>
                    <p class="text-sm text-gray-600 mt-1">Day {{ editingCell.day }} - {{ reportData.month_name }} {{ reportData.school_info.school_year }}</p>
                    <p class="text-xs text-gray-500 mt-2 bg-white px-2 py-1 rounded inline-block">
                        Current: <span class="font-semibold">{{ editingCell.currentValue || '-' }}</span>
                    </p>
                </div>

                <div class="flex flex-col gap-3">
                    <label class="text-sm font-semibold text-gray-700">Enter Attendance Mark:</label>
                    <input
                        v-model="editAttendanceValue"
                        type="text"
                        placeholder="Enter: ✓, ✗, L, or custom text"
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:outline-none text-lg font-semibold text-center"
                        @keyup.enter="saveAttendanceEdit"
                        autofocus
                    />
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <p class="text-xs font-medium text-gray-600 mb-2">Quick Reference:</p>
                        <div class="grid grid-cols-5 gap-2 text-xs">
                            <button @click="editAttendanceValue = '✓'" class="bg-green-100 hover:bg-green-200 text-green-800 px-2 py-1 rounded">✓ Present</button>
                            <button @click="editAttendanceValue = '✗'" class="bg-red-100 hover:bg-red-200 text-red-800 px-2 py-1 rounded">✗ Absent</button>
                            <button @click="editAttendanceValue = 'L'" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 px-2 py-1 rounded">L Late</button>
                            <button @click="editAttendanceValue = 'E'" class="bg-blue-100 hover:bg-blue-200 text-blue-800 px-2 py-1 rounded">E Excused</button>
                            <button @click="editAttendanceValue = 'D'" class="bg-purple-100 hover:bg-purple-200 text-purple-800 px-2 py-1 rounded">D Drop Out</button>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 italic">Type any symbol or letter (✓, ✗, L, E, D, /, \\, etc.) or press Enter to save</p>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="closeEditDialog" />
                    <Button label="Save" icon="pi pi-check" class="p-button-success" @click="saveAttendanceEdit" />
                </div>
            </template>
        </Dialog>

        <!-- Day Annotation Dialog (Holiday, Event, etc.) -->
        <Dialog v-model:visible="showDayAnnotationDialog" modal header="Annotate Day" :style="{ width: '450px' }" class="no-print">
            <div v-if="editingDay" class="space-y-4">
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 p-4 rounded-lg border-l-4 border-yellow-500">
                    <p class="text-base font-bold text-gray-800">Day {{ editingDay.day }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ reportData.month_name }} {{ reportData.school_info.school_year }}</p>
                    <p class="text-xs text-gray-500 mt-2 bg-white px-2 py-1 rounded inline-block">
                        Current: <span class="font-semibold">{{ dayAnnotations[editingDay.date] || 'None' }}</span>
                    </p>
                </div>

                <div class="flex flex-col gap-3">
                    <label class="text-sm font-semibold text-gray-700">Enter Day Label:</label>
                    <input
                        v-model="dayAnnotationValue"
                        type="text"
                        placeholder="e.g., Holiday, Event, No Class, etc."
                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:border-yellow-500 focus:outline-none text-base font-medium text-center"
                        @keyup.enter="saveDayAnnotation"
                        autofocus
                        maxlength="20"
                    />

                    <!-- Preview of vertical text -->
                    <div class="bg-gray-100 p-4 rounded-lg text-center">
                        <p class="text-xs font-medium text-gray-600 mb-2">Preview (Vertical Display):</p>
                        <div class="flex justify-center items-center h-32 bg-white rounded border-2 border-dashed border-gray-300">
                            <div v-if="dayAnnotationValue" style="writing-mode: vertical-rl; text-orientation: upright; font-size: 14px; color: #b91c1c; font-weight: bold; letter-spacing: -2px">
                                {{ dayAnnotationValue }}
                            </div>
                            <p v-else class="text-gray-400 text-xs">Type to see preview</p>
                        </div>
                    </div>

                    <div class="bg-blue-50 p-3 rounded-lg">
                        <p class="text-xs font-medium text-blue-800 mb-2">💡 Quick Suggestions:</p>
                        <div class="grid grid-cols-3 gap-2 text-xs">
                            <button @click="dayAnnotationValue = 'Holiday'" class="bg-white hover:bg-blue-100 text-gray-700 px-2 py-1 rounded border">Holiday</button>
                            <button @click="dayAnnotationValue = 'Event'" class="bg-white hover:bg-blue-100 text-gray-700 px-2 py-1 rounded border">Event</button>
                            <button @click="dayAnnotationValue = 'No Class'" class="bg-white hover:bg-blue-100 text-gray-700 px-2 py-1 rounded border">No Class</button>
                            <button @click="dayAnnotationValue = 'Seminar'" class="bg-white hover:bg-blue-100 text-gray-700 px-2 py-1 rounded border">Seminar</button>
                            <button @click="dayAnnotationValue = 'Activity'" class="bg-white hover:bg-blue-100 text-gray-700 px-2 py-1 rounded border">Activity</button>
                            <button @click="dayAnnotationValue = ''" class="bg-red-50 hover:bg-red-100 text-red-700 px-2 py-1 rounded border border-red-200">Clear</button>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 italic">Text will display vertically in the day column. Press Enter to save or leave empty to remove.</p>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="closeDayAnnotationDialog" />
                    <Button label="Save" icon="pi pi-check" class="p-button-warning" @click="saveDayAnnotation" />
                </div>
            </template>
        </Dialog>
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

/* Attendance mark color coding */
.attendance-present {
    background-color: #d1fae5 !important;
    color: #065f46 !important;
}

.attendance-absent {
    background-color: #fee2e2 !important;
    color: #991b1b !important;
}

.attendance-late {
    background-color: #fef3c7 !important;
    color: #92400e !important;
}

.attendance-excused {
    background-color: #dbeafe !important;
    color: #1e40af !important;
}

.attendance-dropout {
    background-color: #f3e8ff !important;
    color: #7e22ce !important;
}

/* Half-shaded tardy cell (diagonal triangle shading for Late Comer) */
.tardy-half-shaded {
    position: relative;
    background: linear-gradient(to bottom right, #fbbf24 0%, #fbbf24 49%, transparent 50%, transparent 100%) !important;
    color: #92400e !important;
}

.tardy-half-shaded .tardy-text {
    position: relative;
    z-index: 2;
    font-weight: bold;
    color: #92400e;
}

/* Diagonal slash ONLY in cells with day numbers (not empty cells) - Backslash \ direction - EXCLUDE absent and tardy cells */
.attendance-table-container tbody tr td.border.relative:not([colspan]):not(.bg-gray-100):not(.attendance-absent):not(.tardy-half-shaded) {
    background-image: linear-gradient(to bottom right, transparent calc(50% - 0.4px), #9ca3af calc(50% - 0.4px), #9ca3af calc(50% + 0.4px), transparent calc(50% + 0.4px));
}

/* Keep diagonal slash even with color backgrounds */
.attendance-table-container tbody td.relative.attendance-present:not(.bg-gray-100) {
    background-image: none !important; /* No slash for present - plain green background */
    background-color: #d1fae5 !important;
}

.attendance-table-container tbody td.relative.attendance-absent:not(.bg-gray-100) {
    background-color: #fee2e2 !important;
}

.attendance-table-container tbody td.relative.attendance-late:not(.bg-gray-100) {
    background-image: linear-gradient(to bottom right, transparent calc(50% - 0.4px), #f59e0b calc(50% - 0.4px), #f59e0b calc(50% + 0.4px), transparent calc(50% + 0.4px)), linear-gradient(to bottom, #fef3c7, #fef3c7) !important;
}

.attendance-table-container tbody td.relative.attendance-excused:not(.bg-gray-100) {
    background-image: linear-gradient(to bottom right, transparent calc(50% - 0.4px), #3b82f6 calc(50% - 0.4px), #3b82f6 calc(50% + 0.4px), transparent calc(50% + 0.4px)), linear-gradient(to bottom, #dbeafe, #dbeafe) !important;
}

.attendance-table-container tbody td.relative.attendance-dropout:not(.bg-gray-100) {
    background-image: linear-gradient(to bottom right, transparent calc(50% - 0.4px), #a855f7 calc(50% - 0.4px), #a855f7 calc(50% + 0.4px), transparent calc(50% + 0.4px)), linear-gradient(to bottom, #f3e8ff, #f3e8ff) !important;
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

    /* Remove colors in print - keep it plain */
    .attendance-present,
    .attendance-absent,
    .attendance-late,
    .attendance-excused,
    .attendance-dropout {
        background-color: white !important;
        color: black !important;
    }

    /* Half-shaded tardy cells in print - maintain diagonal triangle shading */
    .tardy-half-shaded {
        background: linear-gradient(to bottom right, #d1d5db 0%, #d1d5db 49%, white 50%, white 100%) !important;
        color: black !important;
    }

    .tardy-half-shaded .tardy-text {
        color: black !important;
        font-weight: bold !important;
    }

    /* Keep diagonal slash in print - EXCLUDE present, absent and tardy cells from slash */
    .attendance-table-container tbody tr td.border.relative:not([colspan]):not(.bg-gray-100):not(.attendance-present):not(.attendance-absent):not(.tardy-half-shaded) {
        background-image: linear-gradient(to bottom right, transparent calc(50% - 0.4px), #333 calc(50% - 0.4px), #333 calc(50% + 0.4px), transparent calc(50% + 0.4px)) !important;
    }

    /* No slash for present cells in print - plain white background */
    .attendance-table-container tbody td.relative.attendance-present:not(.bg-gray-100) {
        background-image: none !important;
        background-color: white !important;
    }

    .attendance-table-container tbody td.relative.attendance-absent:not(.bg-gray-100) {
        background-image: none !important;
        background-color: white !important;
    }

    .attendance-table-container tbody td.relative.attendance-late:not(.bg-gray-100),
    .attendance-table-container tbody td.relative.attendance-excused:not(.bg-gray-100),
    .attendance-table-container tbody td.relative.attendance-dropout:not(.bg-gray-100) {
        background-image: linear-gradient(to bottom right, transparent calc(50% - 0.4px), #333 calc(50% - 0.4px), #333 calc(50% + 0.4px), transparent calc(50% + 0.4px)) !important;
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

    /* Fix the large blank space between header and table */
    .sf2-report-card .mb-6 {
        margin-bottom: 4px !important;
    }

    .sf2-report-card .mb-8 {
        margin-bottom: 4px !important;
    }

    /* Specifically target the attendance table container */
    .attendance-table-container.mb-8 {
        margin-bottom: 4px !important;
        margin-top: 0px !important;
    }

    /* Remove any extra spacing from school info section */
    .sf2-report-card > div:nth-child(3) {
        margin-bottom: 0px !important;
    }

    /* Attendance table print optimization - make more compact */
    .attendance-table-container {
        overflow: visible !important;
        page-break-inside: auto !important;
        margin-bottom: 2px !important;
    }

    .attendance-table-container table {
        font-size: 6px !important;
        width: 100% !important;
        page-break-inside: auto !important;
    }

    .attendance-table-container th,
    .attendance-table-container td {
        padding: 0.5px 1px !important;
        font-size: 6px !important;
        line-height: 1 !important;
    }

    /* Force summary section to stay on same page */
    .grid {
        page-break-inside: avoid !important;
        page-break-before: avoid !important;
        overflow: visible !important;
        margin-top: 2px !important;
    }

    /* Compact summary section spacing */
    .grid.gap-6 {
        gap: 2px !important;
    }

    .mt-8 {
        margin-top: 2px !important;
    }

    /* Make summary section more compact */
    .grid h3 {
        font-size: 8px !important;
        margin-bottom: 1px !important;
    }

    .grid p {
        font-size: 5px !important;
        margin: 0.5px 0 !important;
        line-height: 1.1 !important;
    }

    .grid .space-y-2 > * + * {
        margin-top: 1px !important;
    }

    .grid .space-y-1 > * + * {
        margin-top: 0.5px !important;
    }

    /* Compact summary table */
    .grid table {
        font-size: 5px !important;
    }

    .grid table th,
    .grid table td {
        padding: 1px !important;
        font-size: 5px !important;
    }

    /* Remove extra padding from grid columns */
    .grid .p-3 {
        padding: 4px !important;
    }

    .grid .p-2 {
        padding: 2px !important;
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

/* Override empty cell styling - keep gray background for empty columns */
.bg-gray-100.attendance-present,
.bg-gray-100.attendance-absent,
.bg-gray-100.attendance-late,
.bg-gray-100.attendance-excused,
.bg-gray-100.attendance-dropout {
    background-color: #f3f4f6 !important;
}
</style>
