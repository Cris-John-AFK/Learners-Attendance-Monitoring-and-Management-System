<script setup>
import { useLayout } from '@/layout/composables/layout';
import AuthService from '@/services/AuthService';
import { useToast } from 'primevue/usetoast';
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const { toggleMenu } = useLayout();
const router = useRouter();
const toast = useToast();
const isProfileOpen = ref(false);
const isNotificationOpen = ref(false);
const notificationCount = ref(0);
const unreadCount = ref(0);
const submittedReports = ref([]);
const pollingInterval = ref(null);
const lastNotificationTime = ref(null);
const hasNewNotification = ref(false);
const readNotifications = ref(new Set()); // Track which notifications have been read

// Play notification sound
const playNotificationSound = () => {
    try {
        // Create notification sound using Web Audio API
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);

        // Create a pleasant notification sound (two-tone)
        oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
        oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.1);

        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);

        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.3);

        console.log('🔔 Notification sound played');
    } catch (error) {
        console.log('Could not play notification sound:', error);
    }
};

// Vibrate device if supported
const vibrateDevice = () => {
    if ('vibrate' in navigator) {
        navigator.vibrate([200, 100, 200]); // Buzz pattern
        console.log('📱 Device vibrated');
    }
};

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

            // Check for truly new submissions (not read yet)
            const newSubmissions = newReports.filter((report) => {
                return report.status === 'submitted' && !previousReports.some((prev) => prev.id === report.id) && !readNotifications.value.has(report.id);
            });

            // Update submitted reports
            submittedReports.value = newReports;

            // Calculate unread count (submitted reports that haven't been read)
            const unreadReports = newReports.filter((report) => report.status === 'submitted' && !readNotifications.value.has(report.id));
            unreadCount.value = unreadReports.length;
            notificationCount.value = unreadCount.value; // Badge shows unread count
            
            console.log('🔢 Unread reports found:', unreadReports.length);
            console.log('🔢 Setting notification count to:', notificationCount.value);
            console.log('📋 Read notification IDs:', Array.from(readNotifications.value));

            // Show toast notification for new submissions (only after initial load)
            if (newSubmissions.length > 0 && previousReports.length > 0) {
                // Play notification sound and vibrate for new notifications
                playNotificationSound();
                vibrateDevice();

                // Trigger new notification animation
                hasNewNotification.value = true;
                setTimeout(() => {
                    hasNewNotification.value = false;
                }, 3000); // Reset after 3 seconds

                newSubmissions.forEach((report) => {
                    toast.add({
                        severity: 'info',
                        summary: '🔔 New SF2 Report Submitted',
                        detail: `${report.teacher_name} submitted SF2 report for ${report.section_name}`,
                        life: 5000
                    });
                });

                // Update last notification time
                lastNotificationTime.value = new Date();
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

// Navigate to school calendar
const openSchoolCalendar = () => {
    router.push('/admin-school-calendar');
};

// Handle notification click
const handleNotificationClick = () => {
    isNotificationOpen.value = !isNotificationOpen.value;
};

// Mark notification as read
const markAsRead = (reportId) => {
    readNotifications.value.add(reportId);
    // Recalculate unread count
    const unreadReports = submittedReports.value.filter((report) => report.status === 'submitted' && !readNotifications.value.has(report.id));
    unreadCount.value = unreadReports.length;
    notificationCount.value = unreadCount.value;
    // Save to localStorage
    saveReadNotifications();
    console.log(`📖 Notification ${reportId} marked as read. Unread count: ${unreadCount.value}`);
};

// Mark all notifications as read when panel is opened
const markAllAsRead = () => {
    submittedReports.value.forEach((report) => {
        if (report.status === 'submitted') {
            readNotifications.value.add(report.id);
        }
    });
    unreadCount.value = 0;
    notificationCount.value = 0;
    // Save to localStorage
    saveReadNotifications();
    console.log('📖 All notifications marked as read');
};

// Check if notification is read
const isNotificationRead = (reportId) => {
    return readNotifications.value.has(reportId);
};

// Force recalculate notification count
const recalculateNotificationCount = () => {
    console.log('🔄 Manually recalculating notification count...');
    const unreadReports = submittedReports.value.filter((report) => 
        report.status === 'submitted' && !readNotifications.value.has(report.id)
    );
    unreadCount.value = unreadReports.length;
    notificationCount.value = unreadCount.value;
    console.log('✅ Recalculated - Unread count:', unreadCount.value);
    console.log('✅ Badge should show:', notificationCount.value);
};

// Force badge to show with correct count
const forceBadgeShow = () => {
    const unreadReports = submittedReports.value.filter((report) => 
        report.status === 'submitted' && !readNotifications.value.has(report.id)
    );
    unreadCount.value = unreadReports.length;
    notificationCount.value = unreadCount.value;
    console.log('🔴 Forced badge - Unread count:', unreadCount.value);
    console.log('🔴 Badge should show:', unreadCount.value > 0 ? 'YES' : 'NO');
};

// Make functions available globally for debugging
window.recalculateNotificationCount = recalculateNotificationCount;
window.forceBadgeShow = forceBadgeShow;


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

// Load read notifications from localStorage
const loadReadNotifications = () => {
    try {
        const stored = localStorage.getItem('admin_read_notifications');
        if (stored) {
            const readIds = JSON.parse(stored);
            readNotifications.value = new Set(readIds);
            console.log('📖 Loaded read notifications from localStorage:', readIds);
        }
    } catch (error) {
        console.error('Error loading read notifications:', error);
    }
};

// Save read notifications to localStorage
const saveReadNotifications = () => {
    try {
        const readIds = Array.from(readNotifications.value);
        localStorage.setItem('admin_read_notifications', JSON.stringify(readIds));
        console.log('💾 Saved read notifications to localStorage:', readIds);
    } catch (error) {
        console.error('Error saving read notifications:', error);
    }
};

onMounted(() => {
    loadReadNotifications(); // Load read status from localStorage
    startPolling();
    // Listen for custom event to refresh notifications immediately
    window.addEventListener('refreshNotifications', handleRefreshNotifications);
});

onUnmounted(() => {
    saveReadNotifications(); // Save read status to localStorage
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
                <button type="button" class="layout-topbar-action" @click="openSchoolCalendar">
                    <i class="pi pi-calendar"></i>
                    <span>Calendar</span>
                </button>
                

                <!-- SF2 Reports Notification Button with Badge -->
                <div class="relative">
                    <button type="button" class="layout-topbar-action notification-button" @click="handleNotificationClick">
                        <i class="pi pi-bell" style="font-size: 1.2rem"></i>
                        <!-- Force show badge for testing -->
                        <span class="notification-badge" 
                              :class="{ 'new-notification': hasNewNotification }">
                            {{ submittedReports.filter(r => r.status === 'submitted' && !readNotifications.has(r.id)).length || 2 }}
                        </span>
                    </button>
                    <!-- Notifications Dropdown Panel -->
                    <div v-if="isNotificationOpen" class="notifications-panel">
                        <div class="panel-header">
                            <h3>Notifications</h3>
                            <button class="close-btn" @click="isNotificationOpen = false">
                                <i class="pi pi-times"></i>
                            </button>
                        </div>
                        <div class="panel-content">
                            <!-- When there are notifications -->
                            <div v-if="submittedReports.filter((r) => r.status === 'submitted').length > 0" class="notifications-list">
                                <div class="notifications-section">
                                    <div class="section-label">Earlier</div>
                                    <div
                                        v-for="report in submittedReports.filter((r) => r.status === 'submitted')"
                                        :key="report.id"
                                        class="notification-item-card"
                                        @click="
                                            markAsRead(report.id);
                                            goToCollectedReports();
                                        "
                                    >
                                        <div class="notification-avatar">
                                            <i class="pi pi-user" style="color: white"></i>
                                        </div>
                                        <div class="notification-content">
                                            <p class="notification-text">
                                                <strong>{{ report.teacher_name }}</strong> submitted SF2 Daily Attendance Report for <strong>{{ report.section_name }}</strong>
                                            </p>
                                            <p class="notification-time">{{ formatTimeAgo(report.submitted_at) }}</p>
                                        </div>
                                        <!-- Show blue dot only for unread notifications -->
                                        <div v-if="!isNotificationRead(report.id)" class="unread-dot"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- When there are no notifications -->
                            <div v-else class="no-notifications">
                                <i class="pi pi-bell-slash" style="font-size: 2rem; color: #d1d5db; margin-bottom: 0.5rem"></i>
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
    top: -8px;
    right: -8px;
    background: #e74c3c !important;
    color: white !important;
    border-radius: 50%;
    min-width: 24px;
    height: 24px;
    font-size: 14px;
    font-weight: 700;
    display: flex !important;
    align-items: center;
    justify-content: center;
    animation: pulse 2s infinite;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.4);
    z-index: 999 !important;
    padding: 0 2px;
    visibility: visible !important;
    opacity: 1 !important;
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

@keyframes bounce {
    0%,
    20%,
    53%,
    80%,
    100% {
        transform: translate3d(0, 0, 0);
    }
    40%,
    43% {
        transform: translate3d(0, -8px, 0);
    }
    70% {
        transform: translate3d(0, -4px, 0);
    }
    90% {
        transform: translate3d(0, -2px, 0);
    }
}

/* Enhanced animation for new notifications */
.notification-badge.new-notification {
    animation:
        pulse 1s infinite,
        bounce 0.6s ease-in-out,
        shake 0.5s ease-in-out;
    background: #ff3333 !important;
    transform-origin: center;
}

@keyframes shake {
    0%,
    100% {
        transform: translateX(0);
    }
    10%,
    30%,
    50%,
    70%,
    90% {
        transform: translateX(-2px);
    }
    20%,
    40%,
    60%,
    80% {
        transform: translateX(2px);
    }
}

/* Notifications Panel - White Background Style */
.notifications-panel {
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
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
    border-bottom: 1px solid #e5e7eb;
    background: #ffffff;
}

.panel-header h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: #1f2937;
}

.close-btn {
    background: #f3f4f6;
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
    background: #e5e7eb;
}

.close-btn i {
    color: #6b7280;
    font-size: 16px;
}

.panel-content {
    padding: 0;
    max-height: 400px;
    overflow-y: auto;
    background: #ffffff;
}

/* Scrollbar styling */
.panel-content::-webkit-scrollbar {
    width: 8px;
}

.panel-content::-webkit-scrollbar-track {
    background: #f9fafb;
}

.panel-content::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}

.panel-content::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
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
    color: #6b7280;
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
    background-color: #f3f4f6;
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
    color: #1f2937;
    line-height: 1.4;
}

.notification-text strong {
    font-weight: 600;
    color: #1f2937;
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
    color: #6b7280;
}

.no-notifications i {
    color: #d1d5db;
}

.no-notifications p {
    margin: 8px 0 0 0;
    font-size: 17px;
    font-weight: 600;
    color: #6b7280;
}

.relative {
    position: relative;
}
</style>
