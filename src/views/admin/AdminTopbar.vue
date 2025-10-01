<script setup>
import { useLayout } from '@/layout/composables/layout';
import { ref, onMounted, onUnmounted } from 'vue';
import AuthService from '@/services/AuthService';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';

const { toggleMenu } = useLayout();
const router = useRouter();
const toast = useToast();
const isCalendarOpen = ref(false);
const isProfileOpen = ref(false);
const isNotificationOpen = ref(false);
const notificationCount = ref(0);
const submittedReports = ref([]);
const pollingInterval = ref(null);

const logout = async () => {
    try {
        console.log(' Admin logging out...');

        // Use unified AuthService to properly logout
        await AuthService.logout();

        console.log('Logout successful, clearing session data');

        // Clear browser history to prevent back navigation
        window.history.pushState(null, '', window.location.href);
        window.history.replaceState(null, '', '/');

        // Redirect to root login page
        router.replace('/');
    } catch (error) {
        console.error('Logout error:', error);
        // Even if logout fails, clear local data and redirect
        AuthService.clearAuthData();
        window.history.replaceState(null, '', '/');
        router.replace('/');
    }
};

// Load SF2 submitted reports
const loadSubmittedReports = async () => {
    try {
        const response = await fetch('http://127.0.0.1:8000/api/admin/reports/submitted');
        const data = await response.json();

        if (data.success) {
            const newReports = data.data;
            const previousReports = submittedReports.value;

            // Check for truly new submissions
            const newSubmissions = newReports.filter((report) => {
                return report.status === 'submitted' && !previousReports.some((prev) => prev.id === report.id);
            });

            // Update submitted reports
            submittedReports.value = newReports;

            // Update notification count (only count 'submitted' status reports)
            const submittedCount = newReports.filter((report) => report.status === 'submitted').length;
            notificationCount.value = submittedCount;

            // Show toast notification for new submissions (only after initial load)
            if (newSubmissions.length > 0 && previousReports.length > 0) {
                newSubmissions.forEach((report) => {
                    toast.add({
                        severity: 'info',
                        summary: 'New SF2 Report Submitted',
                        detail: `${report.teacher_name} submitted SF2 report for ${report.section_name}`,
                        life: 5000
                    });
                });
            }
        }
    } catch (error) {
        console.error('Error loading submitted reports:', error);
    }
};

// Start polling for new submissions
const startPolling = () => {
    loadSubmittedReports();
    pollingInterval.value = setInterval(() => {
        loadSubmittedReports();
    }, 30000); // Poll every 30 seconds
};

// Stop polling
const stopPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
        pollingInterval.value = null;
    }
};

// Navigate to collected reports page
const goToCollectedReports = () => {
    isNotificationOpen.value = false;
    router.push('/admin-collected-reports');
};

// Handle notification click
const handleNotificationClick = () => {
    isNotificationOpen.value = !isNotificationOpen.value;
};

// Format time ago helper
const formatTimeAgo = (timestamp) => {
    if (!timestamp) return 'Just now';
    
    const now = new Date();
    const submittedDate = new Date(timestamp);
    const diffInSeconds = Math.floor((now - submittedDate) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
    return `${Math.floor(diffInSeconds / 86400)} days ago`;
};

// Listen for manual refresh events from other components
const handleRefreshNotifications = () => {
    console.log('Manual notification refresh triggered');
    loadSubmittedReports();
};

onMounted(() => {
    startPolling();
    // Listen for custom event to refresh notifications immediately
    window.addEventListener('refreshNotifications', handleRefreshNotifications);
});

onUnmounted(() => {
    stopPolling();
    window.removeEventListener('refreshNotifications', handleRefreshNotifications);
});
</script>

<template>
    <div>
        <div class="layout-topbar">
            <div class="layout-topbar-logo-container">
                <button class="layout-menu-button layout-topbar-action" @click="toggleMenu">
                    <i class="pi pi-bars"></i>
                </button>
                <router-link to="/admin" class="layout-topbar-logo">
                    <img src="/demo/images/logo.png" alt="Logo" />
                    <span>NCS - for Admin</span>
                </router-link>
            </div>

            <div class="layout-topbar-actions">
                <button type="button" class="layout-topbar-action" @click="isCalendarOpen = true">
                    <i class="pi pi-calendar"></i>
                    <span>Calendar</span>
                </button>
                
                <!-- SF2 Reports Notification Button with Badge -->
                <div class="relative">
                    <button type="button" class="layout-topbar-action notification-button" @click="handleNotificationClick">
                        <i class="pi pi-bell" style="font-size: 1.2rem;"></i>
                        <span v-if="notificationCount > 0" class="notification-badge">{{ notificationCount }}</span>
                    </button>
                    <!-- Notifications Dropdown Panel -->
                    <div v-if="isNotificationOpen" class="notifications-panel">
                        <div class="panel-header">
                            <h3>Notifications</h3>
                            <button class="close-btn" @click="isNotificationOpen = false">
                                <i class="pi pi-times"></i>
                            </button>
                        </div>
                        <div class="panel-tabs">
                            <button class="tab-btn active">All</button>
                            <button class="tab-btn">Unread</button>
                        </div>
                        <div class="panel-content">
                            <!-- When there are notifications -->
                            <div v-if="notificationCount > 0" class="notifications-list">
                                <div class="notifications-section">
                                    <div class="section-label">Earlier</div>
                                    <div v-for="report in submittedReports.filter(r => r.status === 'submitted')" :key="report.id" class="notification-item-card" @click="goToCollectedReports">
                                        <div class="notification-avatar">
                                            <i class="pi pi-user" style="color: white;"></i>
                                        </div>
                                        <div class="notification-content">
                                            <p class="notification-text">
                                                <strong>{{ report.teacher_name }}</strong> submitted SF2 Daily Attendance Report for <strong>{{ report.section_name }}</strong>
                                            </p>
                                            <p class="notification-time">{{ formatTimeAgo(report.submitted_at) }}</p>
                                        </div>
                                        <div class="unread-dot"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- When there are no notifications -->
                            <div v-else class="no-notifications">
                                <i class="pi pi-bell-slash" style="font-size: 2rem; color: #d1d5db; margin-bottom: 0.5rem;"></i>
                                <p>No notifications</p>
                            </div>
                        </div>
                    </div>
                </div>
                
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

        <!-- Calendar Modal -->
        <Dialog v-model="isCalendarOpen" header="School Activities Calendar" :modal="true" :style="{ width: '290px' }">
            <VCalendar :attributes="attributes" />
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

.logout-button {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 8px 12px;
    border: none;
    background: #f56565; /* Red button */
    color: white;
    font-size: 14px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.2s;
}

.logout-button:hover {
    background: #c53030; /* Darker red on hover */
}

.logout-button i {
    margin-right: 6px;
}

.profile-dropdown {
    position: absolute;
    right: 0;
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    min-width: 120px;
    z-index: 100;
}

/* Notification Styles */
.notification-button {
    position: relative;
}

.notification-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #ef4444;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
    }
}

/* Notifications Panel - Facebook Style */
.notifications-panel {
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    background: #242526;
    border: 1px solid #3e4042;
    border-radius: 8px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.25);
    width: 360px;
    max-width: 90vw;
    z-index: 1000;
    overflow: hidden;
}

.panel-header {
    padding: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #3e4042;
}

.panel-header h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: #e4e6eb;
}

.close-btn {
    background: #3a3b3c;
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.2s;
}

.close-btn:hover {
    background: #4e4f50;
}

.close-btn i {
    color: #b0b3b8;
    font-size: 16px;
}

/* Tabs */
.panel-tabs {
    display: flex;
    padding: 0 8px;
    background: #242526;
    border-bottom: 1px solid #3e4042;
}

.tab-btn {
    flex: 1;
    padding: 12px 16px;
    background: none;
    border: none;
    color: #b0b3b8;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}

.tab-btn:hover {
    background: #3a3b3c;
}

.tab-btn.active {
    color: #2d88ff;
    border-bottom-color: #2d88ff;
}

.panel-content {
    padding: 0;
    max-height: 400px;
    overflow-y: auto;
    background: #242526;
}

/* Scrollbar styling */
.panel-content::-webkit-scrollbar {
    width: 8px;
}

.panel-content::-webkit-scrollbar-track {
    background: #242526;
}

.panel-content::-webkit-scrollbar-thumb {
    background: #4e4f50;
    border-radius: 4px;
}

.panel-content::-webkit-scrollbar-thumb:hover {
    background: #5a5b5c;
}

/* When there are notifications */
.notifications-list {
    padding: 0;
}

.notifications-section {
    padding: 8px 0;
}

.section-label {
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 600;
    color: #b0b3b8;
}

.notification-item-card {
    padding: 12px 16px;
    display: flex;
    gap: 12px;
    cursor: pointer;
    transition: background-color 0.2s;
    position: relative;
}

.notification-item-card:hover {
    background-color: #3a3b3c;
}

/* Avatar */
.notification-avatar {
    flex-shrink: 0;
    width: 56px;
    height: 56px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    position: relative;
}

.notification-avatar i {
    font-size: 24px;
}

/* Content */
.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-text {
    margin: 0 0 4px 0;
    font-size: 15px;
    color: #e4e6eb;
    line-height: 1.4;
}

.notification-text strong {
    font-weight: 600;
    color: #e4e6eb;
}

.notification-time {
    margin: 0;
    font-size: 13px;
    color: #2d88ff;
    font-weight: 500;
}

/* Unread dot */
.unread-dot {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    width: 12px;
    height: 12px;
    background: #2d88ff;
    border-radius: 50%;
}

/* No notifications state */
.no-notifications {
    padding: 60px 20px;
    text-align: center;
    color: #b0b3b8;
}

.no-notifications i {
    color: #3a3b3c;
}

.no-notifications p {
    margin: 8px 0 0 0;
    font-size: 17px;
    font-weight: 600;
    color: #b0b3b8;
}

.relative {
    position: relative;
}
</style>
