<template>
    <div class="grid">
        <div class="col-12">
            <div class="card">
                <!-- Header -->
                <div class="flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="m-0">Summary Attendance Report</h3>
                        <p class="text-600 mt-1 mb-0">Monthly attendance summary for all students</p>
                    </div>
                    <div class="flex align-items-center gap-2">
                        <Button 
                            label="Print Report" 
                            icon="pi pi-print" 
                            @click="printReport"
                            class="p-button-outlined"
                        />
                        <Button 
                            label="Export Excel" 
                            icon="pi pi-file-excel" 
                            @click="exportExcel"
                            class="p-button-success"
                        />
                    </div>
                </div>

                <!-- Date Range Selector -->
                <div class="grid mb-4">
                    <div class="col-12 md:col-4">
                        <label class="block text-900 font-medium mb-2">Month</label>
                        <Dropdown 
                            v-model="selectedMonth" 
                            :options="monthOptions" 
                            optionLabel="label" 
                            optionValue="value"
                            placeholder="Select Month"
                            @change="loadAttendanceData"
                            class="w-full"
                        />
                    </div>
                    <div class="col-12 md:col-4">
                        <label class="block text-900 font-medium mb-2">Year</label>
                        <Dropdown 
                            v-model="selectedYear" 
                            :options="yearOptions" 
                            placeholder="Select Year"
                            @change="loadAttendanceData"
                            class="w-full"
                        />
                    </div>
                    <div class="col-12 md:col-4">
                        <label class="block text-900 font-medium mb-2">Section</label>
                        <Dropdown 
                            v-model="selectedSection" 
                            :options="sectionOptions" 
                            optionLabel="label" 
                            optionValue="value"
                            placeholder="All Sections"
                            @change="loadAttendanceData"
                            class="w-full"
                        />
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="text-center py-6">
                    <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="8" />
                    <p class="mt-3 text-600">Loading attendance data...</p>
                </div>

                <!-- Summary Statistics -->
                <div v-else-if="summaryData" class="grid mb-4">
                    <div class="col-12 md:col-3">
                        <div class="card bg-blue-50 border-blue-200">
                            <div class="flex align-items-center">
                                <div class="bg-blue-500 border-circle w-3rem h-3rem flex align-items-center justify-content-center mr-3">
                                    <i class="pi pi-users text-white text-xl"></i>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-blue-900">{{ summaryData.total_students }}</div>
                                    <div class="text-blue-600">Total Students</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 md:col-3">
                        <div class="card bg-green-50 border-green-200">
                            <div class="flex align-items-center">
                                <div class="bg-green-500 border-circle w-3rem h-3rem flex align-items-center justify-content-center mr-3">
                                    <i class="pi pi-check-circle text-white text-xl"></i>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-green-900">{{ summaryData.average_attendance }}%</div>
                                    <div class="text-green-600">Average Attendance</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 md:col-3">
                        <div class="card bg-orange-50 border-orange-200">
                            <div class="flex align-items-center">
                                <div class="bg-orange-500 border-circle w-3rem h-3rem flex align-items-center justify-content-center mr-3">
                                    <i class="pi pi-calendar text-white text-xl"></i>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-orange-900">{{ schoolDays }}</div>
                                    <div class="text-orange-600">School Days</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 md:col-3">
                        <div class="card bg-purple-50 border-purple-200">
                            <div class="flex align-items-center">
                                <div class="bg-purple-500 border-circle w-3rem h-3rem flex align-items-center justify-content-center mr-3">
                                    <i class="pi pi-chart-bar text-white text-xl"></i>
                                </div>
                                <div>
                                    <div class="text-2xl font-bold text-purple-900">{{ maleStudents.length }}/{{ femaleStudents.length }}</div>
                                    <div class="text-purple-600">Male/Female</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attendance Report Table -->
                <div v-if="!loading && students.length > 0" id="attendance-report">
                    <!-- Male Students Section -->
                    <div class="mb-4">
                        <h4 class="text-blue-600 mb-3">
                            <i class="pi pi-mars mr-2"></i>
                            Male Students ({{ maleStudents.length }})
                        </h4>
                        <DataTable 
                            :value="maleStudents" 
                            class="p-datatable-sm"
                            :paginator="false"
                            responsiveLayout="scroll"
                        >
                            <Column field="name" header="Student Name" :sortable="true">
                                <template #body="slotProps">
                                    <div class="flex align-items-center">
                                        <div class="bg-blue-100 border-circle w-2rem h-2rem flex align-items-center justify-content-center mr-2">
                                            <i class="pi pi-user text-blue-600"></i>
                                        </div>
                                        <span class="font-medium">{{ slotProps.data.name }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column field="total_present" header="Present" :sortable="true">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.total_present" severity="success" />
                                </template>
                            </Column>
                            <Column field="total_absences" header="Absent" :sortable="true">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.total_absences" severity="danger" />
                                </template>
                            </Column>
                            <Column field="total_late" header="Late" :sortable="true">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.total_late" severity="warning" />
                                </template>
                            </Column>
                            <Column field="attendance_rate" header="Attendance Rate" :sortable="true">
                                <template #body="slotProps">
                                    <div class="flex align-items-center">
                                        <ProgressBar 
                                            :value="slotProps.data.attendance_rate" 
                                            class="w-6rem mr-2"
                                            :showValue="false"
                                        />
                                        <span class="font-medium">{{ slotProps.data.attendance_rate }}%</span>
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>

                    <!-- Female Students Section -->
                    <div class="mb-4">
                        <h4 class="text-pink-600 mb-3">
                            <i class="pi pi-venus mr-2"></i>
                            Female Students ({{ femaleStudents.length }})
                        </h4>
                        <DataTable 
                            :value="femaleStudents" 
                            class="p-datatable-sm"
                            :paginator="false"
                            responsiveLayout="scroll"
                        >
                            <Column field="name" header="Student Name" :sortable="true">
                                <template #body="slotProps">
                                    <div class="flex align-items-center">
                                        <div class="bg-pink-100 border-circle w-2rem h-2rem flex align-items-center justify-content-center mr-2">
                                            <i class="pi pi-user text-pink-600"></i>
                                        </div>
                                        <span class="font-medium">{{ slotProps.data.name }}</span>
                                    </div>
                                </template>
                            </Column>
                            <Column field="total_present" header="Present" :sortable="true">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.total_present" severity="success" />
                                </template>
                            </Column>
                            <Column field="total_absences" header="Absent" :sortable="true">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.total_absences" severity="danger" />
                                </template>
                            </Column>
                            <Column field="total_late" header="Late" :sortable="true">
                                <template #body="slotProps">
                                    <Tag :value="slotProps.data.total_late" severity="warning" />
                                </template>
                            </Column>
                            <Column field="attendance_rate" header="Attendance Rate" :sortable="true">
                                <template #body="slotProps">
                                    <div class="flex align-items-center">
                                        <ProgressBar 
                                            :value="slotProps.data.attendance_rate" 
                                            class="w-6rem mr-2"
                                            :showValue="false"
                                        />
                                        <span class="font-medium">{{ slotProps.data.attendance_rate }}%</span>
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else-if="!loading && students.length === 0" class="text-center py-8">
                    <i class="pi pi-chart-bar text-400 text-6xl mb-4"></i>
                    <h4 class="text-600 mb-2">No Attendance Data</h4>
                    <p class="text-500 mb-4">No attendance records found for the selected period.</p>
                    <Button label="Refresh" icon="pi pi-refresh" @click="loadAttendanceData" class="p-button-outlined" />
                </div>
            </div>
        </div>
    </div>
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

onMounted(() => {
    loadAttendanceData();
});
</script>

<style scoped>
@media print {
    .p-button, .p-dropdown {
        display: none !important;
    }
}

.border-circle {
    border-radius: 50%;
}
</style>
