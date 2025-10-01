<script setup>
import NotificationBell from '@/components/NotificationBell.vue';
import { useLayout } from '@/layout/composables/layout';
import AttendanceSessionService from '@/services/AttendanceSessionService';
import NotificationService from '@/services/NotificationService';
import AuthService from '@/services/AuthService';
import Dialog from 'primevue/dialog';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
// Sticky notes removed

const { toggleMenu } = useLayout();
const router = useRouter();

// Calendar removed for performance

const isProfileOpen = ref(false); // Controls dropdown visibility

const isLogoutSuccess = ref(false); // Controls the log out confirmation modal

// Notification state
const notifications = ref([]);
let unsubscribeNotifications = null;

// Session summary dialog state
const showSessionSummary = ref(false);
const selectedSession = ref(null);

// Notification handlers
const handleNotificationClick = (notification) => {
    console.log('Notification clicked:', notification);
    NotificationService.markAsRead(notification.id);

    // Force reactive update
    notifications.value = [...NotificationService.getNotifications()];

    if (notification.type === 'session_completed') {
        // Show session summary dialog instead of navigating
        showSessionSummaryDialog(notification);
    }
};

const showSessionSummaryDialog = async (notification) => {
    try {
        // Debug: Log the full notification structure
        console.log('Full notification object:', notification);
        console.log('Notification data:', notification.data);

        // Extract session ID from notification - the backend sends session_id in the data
        const sessionId = notification.data?.session_id || notification.sessionId || notification.metadata?.sessionId || notification.data?.id;

        console.log('Extracted session ID:', sessionId);

        if (!sessionId) {
            console.error('No session ID found in notification. Available fields:', Object.keys(notification));
            console.error('Notification data keys:', notification.data ? Object.keys(notification.data) : 'No data object');

            // Try to use data from the notification itself for display
            const notificationData = notification.data || {};
            selectedSession.value = {
                id: 'NO-SESSION-ID',
                subject: notification.subject || notificationData.subject_name || 'Unknown Subject',
                section: notification.section || notificationData.section_name || 'Unknown Section',
                date: notificationData.session_date || new Date().toLocaleDateString(),
                time: notificationData.session_start_time || new Date().toLocaleTimeString(),
                method: notification.method || 'Unknown Method',
                teacher: notificationData.teacher_name || 'Unknown Teacher',
                students: [],
                summary: {
                    totalStudents: notificationData.statistics?.total_students || 0,
                    present: notificationData.statistics?.present || 0,
                    absent: notificationData.statistics?.absent || 0,
                    late: notificationData.statistics?.late || 0,
                    attendanceRate: notificationData.statistics?.total_students > 0 ? Math.round(((notificationData.statistics.present + notificationData.statistics.late) / notificationData.statistics.total_students) * 100) : 0
                }
            };
            showSessionSummary.value = true;
            return;
        }

        // Fetch real session data from API
        const sessionData = await AttendanceSessionService.getSessionSummary(sessionId);

        // Transform API response to match dialog format
        const session = sessionData.session;
        const stats = sessionData.statistics;
        const records = sessionData.attendance_records;

        // Map attendance records to student list with better error handling
        const students = records.map((record) => {
            console.log('Processing record:', record);
            return {
                id: record.student?.id || 'unknown',
                name: `${record.student?.firstName || record.student?.first_name || 'Unknown'} ${record.student?.lastName || record.student?.last_name || 'Student'}`,
                status: getStatusLabel(record.attendanceStatus?.code || record.attendance_status?.code || 'P'),
                timeIn: record.marked_at ? new Date(record.marked_at).toLocaleTimeString() : null
            };
        });

        // Add unmarked students as absent with better error handling
        if (sessionData.unmarked_students) {
            sessionData.unmarked_students.forEach((student) => {
                students.push({
                    id: student.id,
                    name: `${student.firstName || student.first_name || 'Unknown'} ${student.lastName || student.last_name || 'Student'}`,
                    status: 'Absent',
                    timeIn: null
                });
            });
        }

        selectedSession.value = {
            id: session.id,
            subject: session.subject?.name || 'Unknown Subject',
            section: session.section?.name || 'Unknown Section',
            date: new Date(session.session_date).toLocaleDateString(),
            time: session.session_start_time,
            method: session.metadata?.method || 'Manual Entry',
            teacher: session.teacher ? `${session.teacher.first_name} ${session.teacher.last_name}` : 'Unknown Teacher',
            students: students,
            summary: {
                totalStudents: stats.total_students,
                present: stats.present,
                absent: stats.absent + stats.unmarked_students,
                late: stats.late,
                attendanceRate: Math.round(((stats.present + stats.late) / stats.total_students) * 100)
            }
        };

        showSessionSummary.value = true;
    } catch (error) {
        console.error('Error fetching session summary:', error);
        // Fallback to mock data if API fails
        selectedSession.value = {
            id: 'ERROR-LOADING',
            subject: 'Error Loading Data',
            section: 'Please try again',
            date: new Date().toLocaleDateString(),
            time: new Date().toLocaleTimeString(),
            method: 'N/A',
            teacher: 'N/A',
            students: [],
            summary: {
                totalStudents: 0,
                present: 0,
                absent: 0,
                late: 0,
                attendanceRate: 0
            }
        };
        showSessionSummary.value = true;
    }
};

// Helper function to map status codes to labels
const getStatusLabel = (code) => {
    switch (code) {
        case 'P':
            return 'Present';
        case 'A':
            return 'Absent';
        case 'L':
            return 'Late';
        case 'E':
            return 'Excused';
        default:
            return 'Unknown';
    }
};

const handleMarkAllRead = () => {
    console.log('Mark all as read clicked');
    NotificationService.markAllAsRead();

    // Force reactive update
    notifications.value = [...NotificationService.getNotifications()];
};

const handleRemoveNotification = (notificationId) => {
    console.log('Remove notification:', notificationId);
    NotificationService.removeNotification(notificationId);

    // Force reactive update
    notifications.value = [...NotificationService.getNotifications()];
};

const logout = async () => {
    try {
        console.log('🚪 Logging out...');
        
        // Use unified AuthService to properly logout
        await AuthService.logout();

        console.log('✅ Logout successful, clearing session data');

        // Show success message
        isLogoutSuccess.value = true;

        // Clear browser history to prevent back navigation
        window.history.pushState(null, '', window.location.href);
        window.history.replaceState(null, '', '/');

        // Redirect to root login page after a short delay
        setTimeout(() => {
            router.replace('/');
        }, 1500);
    } catch (error) {
        console.error('❌ Logout error:', error);
        // Even if logout fails, clear local data and redirect
        AuthService.clearAuthData();
        window.history.replaceState(null, '', '/');
        router.replace('/');
    }
};

// Subscribe to notifications
onMounted(async () => {
    // Initialize teacher for notifications using unified AuthService
    const profile = AuthService.getProfile();
    if (profile && profile.id) {
        console.log('Initializing notifications for teacher:', profile.id);
        await NotificationService.setCurrentTeacher(profile.id);
    }

    // Force refresh notifications on mount
    NotificationService.loadNotifications();
    notifications.value = NotificationService.getNotifications();
    console.log('Initial notifications loaded:', notifications.value.length);

    unsubscribeNotifications = NotificationService.subscribe((updatedNotifications) => {
        console.log('Notifications updated:', updatedNotifications);
        notifications.value = [...updatedNotifications]; // Force reactivity with new array
    });
});

onUnmounted(() => {
    if (unsubscribeNotifications) {
        unsubscribeNotifications();
    }
});
</script>

<template>
    <div>
        <div class="layout-topbar">
            <div class="layout-topbar-logo-container">
                <button class="layout-menu-button layout-topbar-action" @click="toggleMenu">
                    <i class="pi pi-bars"></i>
                </button>
                <router-link to="/teacher" class="layout-topbar-logo">
                    <img src="/demo/images/logo.png" alt="Logo" />
                    <span>NCS- for Teachers</span>
                </router-link>
            </div>

            <div class="layout-topbar-actions">
                <!-- Notification Bell -->
                <NotificationBell :notifications="notifications" @notification-clicked="handleNotificationClick" @mark-all-read="handleMarkAllRead" @remove-notification="handleRemoveNotification" />

                <button type="button" class="layout-topbar-action" @click="$router.push('/teacher/schedules')">
                    <i class="pi pi-calendar-clock"></i>
                    <span>My Schedules</span>
                </button>

                <!-- Profile Button with Dropdown -->
                <div class="relative">
                    <button type="button" class="layout-topbar-action" @click="isProfileOpen = !isProfileOpen">
                        <i class="pi pi-user"></i>
                        <span>Profile</span>
                    </button>
                    <!-- Styled Dropdown Menu -->
                    <div v-if="isProfileOpen" class="profile-dropdown">
                        <button class="logout-button" @click="logout"><i class="pi pi-sign-out"></i> Log Out</button>
                    </div>
                </div>
            </div>
        </div>


        <!-- Log Out Confirmation Modal -->
        <Dialog v-model="isLogoutSuccess" header="Success" :modal="true" :style="{ width: '250px' }">
            <p>You have successfully logged out.</p>
            <template #footer>
                <button class="p-button p-button-primary" @click="isLogoutSuccess = false">OK</button>
            </template>
        </Dialog>

        <!-- Session Summary Dialog -->
        <Dialog v-model:visible="showSessionSummary" modal :header="`Attendance Session Summary - ${selectedSession?.subject}`" style="width: 70rem; max-width: 90vw">
            <div v-if="selectedSession" class="session-summary-content">
                <!-- Session Header Info -->
                <div class="session-header">
                    <div class="session-info-grid">
                        <div class="info-item">
                            <label>Session ID:</label>
                            <span class="session-id">{{ selectedSession.id }}</span>
                        </div>
                        <div class="info-item">
                            <label>Subject:</label>
                            <span class="subject-name">{{ selectedSession.subject }}</span>
                        </div>
                        <div class="info-item">
                            <label>Section:</label>
                            <span class="section-name">{{ selectedSession.section }}</span>
                        </div>
                        <div class="info-item">
                            <label>Date:</label>
                            <span class="session-date">{{ selectedSession.date }}</span>
                        </div>
                        <div class="info-item">
                            <label>Time:</label>
                            <span class="session-time">{{ selectedSession.time }}</span>
                        </div>
                        <div class="info-item">
                            <label>Method:</label>
                            <span class="attendance-method">{{ selectedSession.method }}</span>
                        </div>
                    </div>
                </div>

                <!-- Attendance Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card total">
                        <div class="card-icon">
                            <i class="pi pi-users"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-number">{{ selectedSession.summary.totalStudents }}</div>
                            <div class="card-label">Total Students</div>
                        </div>
                    </div>
                    <div class="summary-card present">
                        <div class="card-icon">
                            <i class="pi pi-check-circle"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-number">{{ selectedSession.summary.present }}</div>
                            <div class="card-label">Present</div>
                        </div>
                    </div>
                    <div class="summary-card absent">
                        <div class="card-icon">
                            <i class="pi pi-times-circle"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-number">{{ selectedSession.summary.absent }}</div>
                            <div class="card-label">Absent</div>
                        </div>
                    </div>
                    <div class="summary-card late">
                        <div class="card-icon">
                            <i class="pi pi-clock"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-number">{{ selectedSession.summary.late }}</div>
                            <div class="card-label">Late</div>
                        </div>
                    </div>
                    <div class="summary-card rate">
                        <div class="card-icon">
                            <i class="pi pi-chart-pie"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-number">{{ selectedSession.summary.attendanceRate }}%</div>
                            <div class="card-label">Attendance Rate</div>
                        </div>
                    </div>
                </div>

                <!-- Student List -->
                <div class="students-section">
                    <h4>Student Attendance Details</h4>
                    <div class="students-table">
                        <div class="table-header">
                            <div class="col-name">Student Name</div>
                            <div class="col-status">Status</div>
                            <div class="col-time">Time In</div>
                        </div>
                        <div class="table-body">
                            <div v-for="student in selectedSession.students" :key="student.id" class="table-row" :class="student.status.toLowerCase()">
                                <div class="col-name">
                                    <i class="pi pi-user student-icon"></i>
                                    {{ student.name }}
                                </div>
                                <div class="col-status">
                                    <span class="status-badge" :class="student.status.toLowerCase()">
                                        <i class="pi pi-check-circle" v-if="student.status === 'Present'"></i>
                                        <i class="pi pi-times-circle" v-else-if="student.status === 'Absent'"></i>
                                        <i class="pi pi-clock" v-else-if="student.status === 'Late'"></i>
                                        {{ student.status }}
                                    </span>
                                </div>
                                <div class="col-time">
                                    {{ student.timeIn || 'N/A' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.layout-topbar {
    position: fixed;
    height: 4rem;
    z-index: 997;
    left: 0;
    top: 0;
    width: 100%;
    padding: 0 2rem;
    background-color: var(--surface-card);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.layout-topbar-logo-container {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 200px;
}

.layout-topbar-logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
    color: var(--text-color);
}

.layout-topbar-logo img {
    height: 2.5rem;
}

.layout-topbar-center {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
}

.layout-topbar-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 200px;
    justify-content: flex-end;
}

.layout-topbar-action {
    width: auto;
    height: 2.5rem;
    padding: 0 0.5rem;
    border: none;
    background: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-color);
    transition: background-color 0.2s;
}

.layout-topbar-action:hover {
    background-color: var(--surface-hover);
}

.layout-topbar-action i {
    font-size: 1.25rem;
}

.profile-dropdown {
    position: absolute;
    right: 0;
    top: 100%;
    background: white;
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    min-width: 120px;
    z-index: 100;
    border-radius: 6px;
    overflow: hidden;
}

.logout-button {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 8px 12px;
    border: none;
    background: #f56565;
    color: white;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.2s;
}

.logout-button:hover {
    background: #c53030;
}

.logout-button i {
    margin-right: 6px;
}

@media (max-width: 991px) {
    .layout-topbar {
        padding: 0 1rem;
    }

    .layout-topbar-logo-container {
        min-width: auto;
    }

    .layout-topbar-actions {
        min-width: auto;
    }

    .layout-topbar-action span {
        display: none;
    }
}

/* Session Summary Dialog Styles */
.session-summary-content {
    max-height: 70vh;
    overflow-y: auto;
}

.session-header {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 8px;
    border: 1px solid #dee2e6;
}

.session-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-item label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-item span {
    font-size: 1rem;
    font-weight: 500;
    color: #212529;
}

.session-id {
    font-family: 'Courier New', monospace;
    background: #e3f2fd;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    color: #1976d2;
}

.attendance-method {
    background: #f3e5f5;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    color: #7b1fa2;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-card {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.summary-card.total {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    border-left: 4px solid #2196f3;
}

.summary-card.present {
    background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
    border-left: 4px solid #4caf50;
}

.summary-card.absent {
    background: linear-gradient(135deg, #ffebee, #ffcdd2);
    border-left: 4px solid #f44336;
}

.summary-card.late {
    background: linear-gradient(135deg, #fff3e0, #ffe0b2);
    border-left: 4px solid #ff9800;
}

.summary-card.rate {
    background: linear-gradient(135deg, #f3e5f5, #e1bee7);
    border-left: 4px solid #9c27b0;
}

.card-icon {
    margin-right: 1rem;
    font-size: 2rem;
    opacity: 0.8;
}

.summary-card.total .card-icon {
    color: #2196f3;
}
.summary-card.present .card-icon {
    color: #4caf50;
}
.summary-card.absent .card-icon {
    color: #f44336;
}
.summary-card.late .card-icon {
    color: #ff9800;
}
.summary-card.rate .card-icon {
    color: #9c27b0;
}

.card-content {
    flex: 1;
}

.card-number {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.card-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.students-section {
    margin-top: 2rem;
}

.students-section h4 {
    margin: 0 0 1rem 0;
    color: #495057;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.students-table {
    border: 1px solid #dee2e6;
    border-radius: 8px;
    overflow: hidden;
    background: white;
}

.table-header {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    color: #495057;
}

.table-header > div {
    padding: 1rem;
    border-right: 1px solid #dee2e6;
}

.table-header > div:last-child {
    border-right: none;
}

.table-body {
    max-height: 300px;
    overflow-y: auto;
}

.table-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    border-bottom: 1px solid #f1f3f4;
    transition: background-color 0.2s ease;
}

.table-row:hover {
    background: #f8f9fa;
}

.table-row.present {
    border-left: 4px solid #4caf50;
}

.table-row.absent {
    border-left: 4px solid #f44336;
}

.table-row.late {
    border-left: 4px solid #ff9800;
}

.table-row > div {
    padding: 1rem;
    border-right: 1px solid #f1f3f4;
    display: flex;
    align-items: center;
}

.table-row > div:last-child {
    border-right: none;
}

.student-icon {
    margin-right: 0.5rem;
    color: #6c757d;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.status-badge.present {
    background: #e8f5e8;
    color: #2e7d32;
}

.status-badge.absent {
    background: #ffebee;
    color: #c62828;
}

.status-badge.late {
    background: #fff3e0;
    color: #ef6c00;
}

/* Hide topbar when printing */
@media print {
    .layout-topbar {
        display: none !important;
    }
}
</style>
