<script setup>
import { AttendanceService } from '@/router/service/Estudyante';
import { SubjectService } from '@/router/service/Subjects';
import axios from 'axios';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue'; // Added missing watch import

// Configure axios to handle CORS
axios.defaults.headers.common['Access-Control-Allow-Origin'] = '*';
axios.defaults.withCredentials = false;

const toast = useToast();
const loading = ref(true);
const searchQuery = ref('');
const attendanceRecords = ref([]);
const subjects = ref([]);
const selectedSubject = ref(null);
const startDate = ref(new Date(new Date().setDate(1))); // First day of current month
const endDate = ref(new Date()); // Today

// Get all students for the selected subject
const students = ref([]);

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

// Filtered records based on search query
const filteredRecords = computed(() => {
    if (!searchQuery.value) return attendanceMatrix.value;

    const query = searchQuery.value.toLowerCase();
    return attendanceMatrix.value.filter((record) => {
        return record.name.toLowerCase().includes(query) || record.id.toString().includes(query) || (record.gradeLevel && record.gradeLevel.toString().toLowerCase().includes(query)) || (record.section && record.section.toLowerCase().includes(query));
    });
});

// Load attendance records for the selected subject and date range
const loadAttendanceRecords = async () => {
    loading.value = true;
    try {
        if (!selectedSubject.value) {
            attendanceRecords.value = {};
            students.value = [];
            loading.value = false;
            return;
        }

        // Load students for this subject
        students.value = await AttendanceService.getStudentsBySubject(selectedSubject.value.code);

        // Get attendance data from seat plan
        const seatPlanKey = `seatPlan_${selectedSubject.value.code}`;
        const seatPlanData = localStorage.getItem(seatPlanKey);

        // Get attendance records from localStorage as fallback
        const allRecords = JSON.parse(localStorage.getItem('attendanceRecords') || '{}');

        // Initialize attendance records
        attendanceRecords.value = {};

        // Create a map of all dates in the selected range
        const dateMap = new Map();
        const currentDate = new Date(startDate.value);
        while (currentDate <= endDate.value) {
            const dateString = currentDate.toISOString().split('T')[0];
            dateMap.set(dateString, true);
            currentDate.setDate(currentDate.getDate() + 1);
        }

        // Process seat plan data if available
        let seatPlanAttendance = {};
        if (seatPlanData) {
            try {
                const parsedData = JSON.parse(seatPlanData);
                if (parsedData && parsedData.seatPlan) {
                    // Extract attendance data from seat plan
                    const seatPlan = parsedData.seatPlan;

                    // Process each seat in the seat plan
                    seatPlan.forEach((row) => {
                        row.forEach((seat) => {
                            if (seat.isOccupied && seat.studentId) {
                                seatPlanAttendance[seat.studentId] = seat.status || '';
                            }
                        });
                    });
                }
            } catch (parseError) {
                console.error('Error parsing seat plan data:', parseError);
            }
        }

        // For each student and each date in the range, create attendance records
        students.value.forEach((student) => {
            // For each date in the range
            dateMap.forEach((_, dateString) => {
                const recordKey = `${student.id}-${dateString}`;

                // Check if we have a record in localStorage
                const existingRecord = allRecords[recordKey];

                if (existingRecord) {
                    // Use existing record from localStorage
                    attendanceRecords.value[recordKey] = existingRecord;
                } else {
                    // Create a new record with status from seat plan or default to empty string
                    const status = seatPlanAttendance[student.id] || '';

                    attendanceRecords.value[recordKey] = {
                        date: dateString,
                        studentId: student.id,
                        studentName: student.name,
                        status: status,
                        time: new Date().toLocaleTimeString(),
                        remarks: ''
                    };
                }
            });
        });

        toast.add({
            severity: 'success',
            summary: 'Records Loaded',
            detail: `Loaded attendance records for ${selectedSubject.value.name}`,
            life: 3000
        });
    } catch (error) {
        console.error('Error loading attendance records:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load attendance records',
            life: 3000
        });
    } finally {
        loading.value = false;
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

// Load subjects on component mount
onMounted(async () => {
    try {
        // Load subjects
        // eslint-disable-next-line no-undef
        subjects.value = await SubjectService.getSubjects();

        // Mock subjects if none are returned
        if (!subjects.value.length) {
            subjects.value = [
                { name: 'Mathematics', code: 'MATH101' },
                { name: 'Science', code: 'SCI101' },
                { name: 'English', code: 'ENG101' },
                { name: 'History', code: 'HIST101' }
            ];
        }

        // Auto-select first subject
        if (subjects.value.length > 0) {
            selectedSubject.value = subjects.value[0];
            await loadAttendanceRecords();
        }
    } catch (error) {
        console.error('Error loading subjects:', error);
    } finally {
        loading.value = false;
    }
});

// Watch for changes in subject or date range
// eslint-disable-next-line no-undef
watch([selectedSubject, startDate, endDate], () => {
    loadAttendanceRecords();
});

// Function to get status class
const getStatusClass = (status) => {
    if (!status || status === '') return 'status-none';

    switch (status) {
        case 'Present':
            return 'status-present';
        case 'Late':
            return 'status-late';
        case 'Absent':
            return 'status-absent';
        case 'Excused':
            return 'status-excused';
        default:
            return 'status-none';
    }
};

// Function to format date for display
const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
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
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="field">
                    <label for="subject" class="block mb-1">Subject</label>
                    <Dropdown id="subject" v-model="selectedSubject" :options="subjects" optionLabel="name" placeholder="Select Subject" class="w-full" />
                </div>

                <div class="field">
                    <label for="startDate" class="block mb-1">Start Date</label>
                    <Calendar id="startDate" v-model="startDate" dateFormat="yy-mm-dd" class="w-full" :maxDate="endDate" />
                </div>

                <div class="field">
                    <label for="endDate" class="block mb-1">End Date</label>
                    <Calendar id="endDate" v-model="endDate" dateFormat="yy-mm-dd" class="w-full" :minDate="startDate" />
                </div>

                <div class="field">
                    <label for="search" class="block mb-1">Search</label>
                    <span class="p-input-icon-left w-full">
                        <i class="pi pi-search" />
                        <InputText id="search" v-model="searchQuery" placeholder="Search students..." class="w-full" />
                    </span>
                </div>
            </div>
        </div>

        <!-- Attendance Records Table -->
        <DataTable :value="filteredRecords" :loading="loading" responsiveLayout="scroll" class="attendance-table" stripedRows scrollable scrollHeight="500px">
            <!-- Fixed columns for student info -->
            <Column field="name" header="Student Name" :frozen="true" style="min-width: 200px" />
            <Column field="id" header="ID" :frozen="true" style="min-width: 100px" />
            <Column field="gradeLevel" header="Grade" :frozen="true" style="min-width: 80px" />
            <Column field="section" header="Section" :frozen="true" style="min-width: 100px" />

            <!-- Dynamic columns for dates -->
            <Column v-for="date in dateColumns" :key="date" :field="date" :header="formatDate(date)" style="min-width: 100px">
                <template #body="{ data, field }">
                    <div :class="['status-cell', getStatusClass(data[field])]">
                        <i v-if="data[field] === 'Present'" class="pi pi-check-circle text-green-500"></i>
                        <span v-else>{{ data[field] || '' }}</span>
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
