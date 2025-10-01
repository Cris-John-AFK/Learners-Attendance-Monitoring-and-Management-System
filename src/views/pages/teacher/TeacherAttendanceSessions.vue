<script setup>
import { TeacherAttendanceService } from '@/router/service/TeacherAttendanceService';
import TeacherAuthService from '@/services/TeacherAuthService';
import AttendanceReasonDialog from '@/components/AttendanceReasonDialog.vue';
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
const teacherId = ref(null); // Will be set from authenticated teacher data
const showSessionDetail = ref(false);
const selectedSession = ref(null);
const sessionStudents = ref([]);
const selectedStudents = ref([]);
const bulkStatus = ref('Present');

// Attendance Reason Dialog states
const showReasonDialog = ref(false);
const reasonDialogType = ref('late');
const pendingStatusChange = ref(null);

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

// Initialize teacher data from authentication
const initializeTeacherData = async () => {
    try {
        // Check if teacher is authenticated
        if (!TeacherAuthService.isAuthenticated()) {
            console.warn('Teacher not authenticated, redirecting to login');
            router.push('/teacher-login');
            return false;
        }

        // Get authenticated teacher data
        const teacherData = TeacherAuthService.getTeacherData();
        if (teacherData && teacherData.teacher) {
            teacherId.value = teacherData.teacher.id;
            console.log('Initialized teacher ID from authenticated data:', teacherId.value);
            return true;
        } else {
            console.error('No teacher data found in authentication');
            router.push('/teacher-login');
            return false;
        }
    } catch (error) {
        console.error('Error initializing teacher data:', error);
        router.push('/teacher-login');
        return false;
    }
};

// Load attendance sessions for the teacher
const loadAttendanceSessions = async () => {
    if (!teacherId.value) {
        console.error('No teacher ID available');
        return;
    }

    loading.value = true;
    const startTime = Date.now();
    
    try {
        console.log('Loading attendance sessions for teacher:', teacherId.value);
        const response = await TeacherAttendanceService.getTeacherAttendanceSessions(teacherId.value);
        console.log('API Response:', response);
        console.log('Sessions received:', response.sessions?.length || 0);
        
        // Calculate remaining time to show loading (minimum 600ms)
        const elapsedTime = Date.now() - startTime;
        const minLoadingTime = 600;
        const remainingTime = Math.max(0, minLoadingTime - elapsedTime);
        
        // Wait for minimum loading time to ensure smooth animation
        await new Promise(resolve => setTimeout(resolve, remainingTime));
        
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
        
        // Map students with reason data from attendance_reason relationship
        sessionStudents.value = (response.students || []).map(student => ({
            ...student,
            reason_name: student.attendance_reason?.reason_name || null,
            reason_notes: student.reason_notes || null
        }));
        
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
    // Show reason dialog for Late or Excused status
    if (newStatus === 'Late' || newStatus === 'Excused') {
        const student = sessionStudents.value.find((s) => s.id === studentId);
        pendingStatusChange.value = {
            studentId,
            studentName: student?.name || `${student?.first_name} ${student?.last_name}`,
            newStatus
        };
        reasonDialogType.value = newStatus.toLowerCase();
        showReasonDialog.value = true;
        return;
    }

    // For other statuses, update directly
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

// Handle reason dialog confirmation
const onReasonConfirmed = async (reasonData) => {
    if (!pendingStatusChange.value) return;
    
    const { studentId, newStatus, studentName } = pendingStatusChange.value;
    
    try {
        await TeacherAttendanceService.updateStudentAttendance(
            selectedSession.value.id, 
            studentId, 
            newStatus,
            reasonData.reason_id,
            reasonData.reason_notes
        );

        // Update local data with reason
        const student = sessionStudents.value.find((s) => s.id === studentId);
        if (student) {
            student.status = newStatus;
            student.reason_id = reasonData.reason_id;
            student.reason_notes = reasonData.reason_notes;
            student.reason_name = reasonData.reason_name;
            student.attendance_reason = {
                id: reasonData.reason_id,
                reason_name: reasonData.reason_name,
                status: 'active'
            };
        }

        toast.add({
            severity: 'success',
            summary: 'Updated',
            detail: `${studentName} marked as ${newStatus} - ${reasonData.reason_name}`,
            life: 3000
        });
    } catch (error) {
        console.error('Error updating attendance with reason:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update attendance status',
            life: 3000
        });
    }
    
    // Clear pending
    pendingStatusChange.value = null;
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

onMounted(async () => {
    // Initialize teacher data first
    const initialized = await initializeTeacherData();
    if (initialized) {
        // Load attendance sessions only if teacher data was initialized successfully
        await loadAttendanceSessions();
    }
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

        <!-- Smooth Minimal Loading -->
        <transition name="fade-slide">
            <div v-if="loading && sessions.length === 0" class="loading-container">
                <div class="loading-content">
                    <!-- Simple Spinner -->
                    <div class="simple-spinner"></div>
                    
                    <!-- Text -->
                    <p class="loading-text">Loading sessions...</p>
                </div>
            </div>
        </transition>

        <!-- Sessions by Date -->
        <div v-if="!loading || sessions.length > 0" class="space-y-6">
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
                            <Tag 
                                :value="data.status" 
                                :severity="getStatusSeverity(data.status)" 
                                :class="['text-sm', data.status === 'Late' ? 'late-status-badge' : '']" 
                            />
                        </template>
                    </Column>

                    <Column header="Reason" style="min-width: 200px">
                        <template #body="{ data }">
                            <span v-if="data.reason_name" class="text-sm">
                                {{ data.reason_name }}
                                <span v-if="data.reason_notes" class="text-gray-500 text-xs block mt-1">
                                    Note: {{ data.reason_notes }}
                                </span>
                            </span>
                            <span v-else class="text-gray-400 text-sm italic">No reason</span>
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

        <!-- Attendance Reason Dialog -->
        <AttendanceReasonDialog
            v-model="showReasonDialog"
            :status-type="reasonDialogType"
            :student-name="pendingStatusChange?.studentName"
            @confirm="onReasonConfirmed"
        />
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

/* Smooth Minimal Loading */
.loading-container {
    min-height: 400px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.loading-content {
    text-align: center;
}

.simple-spinner {
    width: 50px;
    height: 50px;
    margin: 0 auto 1.5rem;
    border: 3px solid #e5e7eb;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.loading-text {
    color: #64748b;
    font-size: 1rem;
    margin: 0;
}

/* Late Status Badge - Force Yellow Color */
.late-status-badge {
    background-color: #f59e0b !important;
    color: white !important;
}

/* Smooth Transitions */
.fade-slide-enter-active {
    transition: all 0.4s ease-out;
}

.fade-slide-leave-active {
    transition: all 0.3s ease-in;
}

.fade-slide-enter-from {
    opacity: 0;
    transform: translateY(-10px);
}

.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(10px);
}
</style>
