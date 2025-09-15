<script setup>
import { TeacherAttendanceService } from '@/router/service/TeacherAttendanceService';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import ProgressSpinner from 'primevue/progressspinner';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import NotificationService from '@/services/NotificationService';

const router = useRouter();
const toast = useToast();

// Reactive data
const loading = ref(false);
const sessions = ref([]);
const selectedDate = ref(new Date());
const teacherId = ref(3); // Maria Santos
const showSessionDetail = ref(false);
const selectedSession = ref(null);
const sessionStudents = ref([]);
const selectedStudents = ref([]);
const bulkStatus = ref('Present');

// Status options for dropdowns
const statusOptions = [
    { label: 'Present', value: 'Present' },
    { label: 'Absent', value: 'Absent' },
    { label: 'Late', value: 'Late' },
    { label: 'Excused', value: 'Excused' }
];

// Group sessions by date
const sessionsByDate = computed(() => {
    const grouped = {};
    sessions.value.forEach((session) => {
        const date = session.session_date;
        if (!grouped[date]) {
            grouped[date] = [];
        }
        grouped[date].push(session);
    });
    return grouped;
});

// Load attendance sessions for the teacher
const loadAttendanceSessions = async () => {
    loading.value = true;
    try {
        console.log('Loading attendance sessions for teacher:', teacherId.value);
        const response = await TeacherAttendanceService.getTeacherAttendanceSessions(teacherId.value);
        console.log('API Response:', response);
        console.log('Sessions received:', response.sessions?.length || 0);
        sessions.value = response.sessions || [];
        console.log('Sessions stored in reactive variable:', sessions.value.length);
    } catch (error) {
        console.error('Error loading attendance sessions:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load attendance sessions',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Load session details with students
const loadSessionDetails = async (session) => {
    loading.value = true;
    try {
        const response = await TeacherAttendanceService.getSessionAttendanceDetails(session.id);
        sessionStudents.value = response.students || [];
        selectedSession.value = session;
        showSessionDetail.value = true;
        selectedStudents.value = [];
    } catch (error) {
        console.error('Error loading session details:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load session details',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Update student attendance status
const updateStudentStatus = async (studentId, newStatus) => {
    try {
        await TeacherAttendanceService.updateStudentAttendance(selectedSession.value.id, studentId, newStatus);

        // Update local data
        const student = sessionStudents.value.find((s) => s.id === studentId);
        if (student) {
            student.status = newStatus;
        }

        toast.add({
            severity: 'success',
            summary: 'Updated',
            detail: 'Attendance status updated successfully',
            life: 2000
        });
    } catch (error) {
        console.error('Error updating attendance:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update attendance status',
            life: 3000
        });
    }
};

// Bulk update selected students
const bulkUpdateStatus = async () => {
    if (selectedStudents.value.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'No Selection',
            detail: 'Please select students to update',
            life: 3000
        });
        return;
    }

    try {
        const promises = selectedStudents.value.map((studentId) => TeacherAttendanceService.updateStudentAttendance(selectedSession.value.id, studentId, bulkStatus.value));

        await Promise.all(promises);

        // Update local data
        selectedStudents.value.forEach((studentId) => {
            const student = sessionStudents.value.find((s) => s.id === studentId);
            if (student) {
                student.status = bulkStatus.value;
            }
        });

        selectedStudents.value = [];

        toast.add({
            severity: 'success',
            summary: 'Bulk Update Complete',
            detail: `Updated ${promises.length} students to ${bulkStatus.value}`,
            life: 3000
        });
    } catch (error) {
        console.error('Error bulk updating attendance:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to bulk update attendance',
            life: 3000
        });
    }
};

// Get status severity for styling
const getStatusSeverity = (status) => {
    switch (status) {
        case 'Present':
            return 'success';
        case 'Absent':
            return 'danger';
        case 'Late':
            return 'warning';
        case 'Excused':
            return 'info';
        default:
            return 'secondary';
    }
};

// Format time for display
const formatTime = (timeString) => {
    if (!timeString) return '';
    try {
        // Handle time format like "07:20:05" or "14:21:20"
        const time = new Date(`2000-01-01T${timeString}`);
        if (isNaN(time.getTime())) {
            return timeString; // Return original if parsing fails
        }
        return time.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        });
    } catch (error) {
        return timeString;
    }
};

// Format date for display
const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            return dateString; // Return original if parsing fails
        }
        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    } catch (error) {
        return dateString;
    }
};

// Get attendance summary for a session
const getAttendanceSummary = (session) => {
    const total = session.total_students || 0;
    const present = session.present_count || 0;
    const absent = session.absent_count || 0;
    const late = session.late_count || 0;
    const excused = session.excused_count || 0;

    return { total, present, absent, late, excused };
};

// Listen for session completion notifications
let unsubscribeNotifications = null;

const handleNotificationUpdate = (notifications) => {
    console.log('Notification update received:', notifications.length, 'notifications');
    // Check if there's a new session_completed notification
    const latestSessionCompleted = notifications.find(n => 
        n.type === 'session_completed' && !n.read
    );
    
    if (latestSessionCompleted) {
        console.log('Found new session_completed notification, refreshing sessions...');
        // Refresh sessions when a new one is completed
        loadAttendanceSessions();
    } else {
        console.log('No new session_completed notifications found');
    }
};

onMounted(() => {
    loadAttendanceSessions();
    // Subscribe to notifications for auto-refresh
    unsubscribeNotifications = NotificationService.subscribe(handleNotificationUpdate);
});

onUnmounted(() => {
    // Clean up notification listener
    if (unsubscribeNotifications) {
        unsubscribeNotifications();
    }
});
</script>

<template>
    <div class="attendance-sessions-container p-4">
        <Toast />

        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Attendance Sessions</h2>
                <p class="text-gray-600">View and edit your attendance sessions</p>
            </div>
            <Button icon="pi pi-refresh" label="Refresh" @click="loadAttendanceSessions" :loading="loading" class="p-button-outlined" />
        </div>

        <!-- Loading State -->
        <div v-if="loading && sessions.length === 0" class="flex justify-center items-center py-8">
            <ProgressSpinner />
            <span class="ml-3 text-gray-600">Loading attendance sessions...</span>
        </div>

        <!-- Sessions by Date -->
        <div v-else class="space-y-6">
            <div v-for="(dateSessions, date) in sessionsByDate" :key="date" class="date-group">
                <h3 class="text-lg font-semibold text-gray-700 mb-4 border-b pb-2">
                    {{ formatDate(date) }}
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Card v-for="session in dateSessions" :key="session.id" class="session-card cursor-pointer hover:shadow-lg transition-shadow duration-200" @click="loadSessionDetails(session)">
                        <template #header>
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-4 rounded-t-lg">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-semibold text-lg">{{ session.subject_name }}</h4>
                                        <p class="text-blue-100 text-sm">{{ session.section_name }}</p>
                                    </div>
                                    <div class="text-right text-xs">
                                        <div class="mb-1">
                                            <span class="text-blue-200">Started:</span>
                                            <span class="text-white font-medium">{{ formatTime(session.start_time) }}</span>
                                        </div>
                                        <div>
                                            <span class="text-blue-200">Ended:</span>
                                            <span class="text-white font-medium">{{ formatTime(session.end_time) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template #content>
                            <div class="p-4">
                                <!-- Attendance Summary -->
                                <div class="grid grid-cols-2 gap-3 mb-4">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-green-600">
                                            {{ getAttendanceSummary(session).present }}
                                        </div>
                                        <div class="text-xs text-gray-500">Present</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-red-600">
                                            {{ getAttendanceSummary(session).absent }}
                                        </div>
                                        <div class="text-xs text-gray-500">Absent</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-yellow-600">
                                            {{ getAttendanceSummary(session).late }}
                                        </div>
                                        <div class="text-xs text-gray-500">Late</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-blue-600">
                                            {{ getAttendanceSummary(session).excused }}
                                        </div>
                                        <div class="text-xs text-gray-500">Excused</div>
                                    </div>
                                </div>

                                <!-- Total Students -->
                                <div class="text-center pt-3 border-t">
                                    <span class="text-sm text-gray-600"> Total Students: {{ getAttendanceSummary(session).total }} </span>
                                </div>
                            </div>
                        </template>

                        <template #footer>
                            <div class="px-4 pb-4"></div>
                        </template>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="!loading && Object.keys(sessionsByDate).length === 0" class="text-center py-12">
            <i class="pi pi-calendar-times text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-600 mb-2">No Attendance Sessions Found</h3>
            <p class="text-gray-500">You haven't conducted any attendance sessions yet.</p>
        </div>

        <!-- Session Detail Dialog -->
        <Dialog v-model:visible="showSessionDetail" :header="`Edit Attendance - ${selectedSession?.subject_name} (${selectedSession?.section_name})`" :style="{ width: '90vw', maxWidth: '1200px' }" :modal="true" :closable="true">
            <div v-if="selectedSession" class="session-detail">
                <!-- Session Info -->
                <div class="bg-gray-50 p-4 rounded-lg mb-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                        <div><strong>Date:</strong> {{ formatDate(selectedSession.session_date) }}</div>
                        <div><strong>Time:</strong> {{ formatTime(selectedSession.start_time) }} - {{ formatTime(selectedSession.end_time) }}</div>
                        <div><strong>Subject:</strong> {{ selectedSession.subject_name }}</div>
                        <div><strong>Section:</strong> {{ selectedSession.section_name }}</div>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="flex justify-between items-center mb-4 p-3 bg-blue-50 rounded-lg">
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium">Bulk Actions:</span>
                        <Dropdown v-model="bulkStatus" :options="statusOptions" optionLabel="label" optionValue="value" class="w-32" />
                        <Button label="Apply to Selected" icon="pi pi-check" @click="bulkUpdateStatus" :disabled="selectedStudents.length === 0" class="p-button-sm" />
                    </div>
                    <div class="text-sm text-gray-600">{{ selectedStudents.length }} student(s) selected</div>
                </div>

                <!-- Students Table -->
                <DataTable :value="sessionStudents" v-model:selection="selectedStudents" dataKey="id" :paginator="true" :rows="10" class="p-datatable-sm" responsiveLayout="scroll">
                    <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>

                    <Column field="student_id" header="Student ID" sortable>
                        <template #body="{ data }">
                            <span class="font-mono text-sm">{{ data.student_id }}</span>
                        </template>
                    </Column>

                    <Column field="name" header="Student Name" sortable>
                        <template #body="{ data }">
                            <div class="font-medium">{{ data.name }}</div>
                        </template>
                    </Column>

                    <Column field="status" header="Current Status" sortable>
                        <template #body="{ data }">
                            <Tag :value="data.status" :severity="getStatusSeverity(data.status)" class="text-sm" />
                        </template>
                    </Column>

                    <Column header="Update Status">
                        <template #body="{ data }">
                            <Dropdown :modelValue="data.status" @update:modelValue="updateStudentStatus(data.id, $event)" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full" />
                        </template>
                    </Column>
                </DataTable>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.session-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}

.session-card:hover {
    border-color: #3b82f6;
    transform: translateY(-2px);
}

.date-group {
    margin-bottom: 2rem;
}

.p-datatable {
    border-radius: 8px;
    overflow: hidden;
}

.p-dialog .p-dialog-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}
</style>
