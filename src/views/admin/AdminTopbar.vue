<script setup>
import { useLayout } from '@/layout/composables/layout';
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const { toggleMenu } = useLayout();
const router = useRouter();
const isCalendarOpen = ref(false);
const isProfileOpen = ref(false);
const isNotificationOpen = ref(false);
const notificationCount = ref(3); // Example notification count

const logout = () => {
    // Clear user session data
    localStorage.removeItem('user');
    sessionStorage.removeItem('user');

    // Redirect to homepage
    router.push('/');
};
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
                
                <!-- Notification Button with Badge -->
                <div class="relative">
                    <button type="button" class="layout-topbar-action notification-button" @click="isNotificationOpen = !isNotificationOpen">
                        <i class="pi pi-bell"></i>
                        <span>Notifications</span>
                        <span v-if="notificationCount > 0" class="notification-badge">{{ notificationCount }}</span>
                    </button>
                    <!-- Notification Dropdown -->
                    <div v-if="isNotificationOpen" class="notification-dropdown">
                        <div class="notification-header">
                            <h4>Notifications</h4>
                            <span class="notification-count">{{ notificationCount }} new</span>
                        </div>
                        <div class="notification-list">
                            <div class="notification-item">
                                <i class="pi pi-user-plus text-blue-500"></i>
                                <div>
                                    <p class="notification-title">New Student Registration</p>
                                    <p class="notification-time">2 minutes ago</p>
                                </div>
                            </div>
                            <div class="notification-item">
                                <i class="pi pi-exclamation-triangle text-orange-500"></i>
                                <div>
                                    <p class="notification-title">Attendance Report Due</p>
                                    <p class="notification-time">1 hour ago</p>
                                </div>
                            </div>
                            <div class="notification-item">
                                <i class="pi pi-check-circle text-green-500"></i>
                                <div>
                                    <p class="notification-title">System Backup Completed</p>
                                    <p class="notification-time">3 hours ago</p>
                                </div>
                            </div>
                        </div>
                        <div class="notification-footer">
                            <button class="view-all-btn">View All Notifications</button>
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

.notification-dropdown {
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 8px;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    min-width: 320px;
    max-width: 400px;
    z-index: 1000;
    overflow: hidden;
}

.notification-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--surface-border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--surface-ground);
}

.notification-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: var(--text-color);
}

.notification-count {
    background: #3b82f6;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.notification-list {
    max-height: 300px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 20px;
    border-bottom: 1px solid var(--surface-border);
    display: flex;
    align-items: flex-start;
    gap: 12px;
    transition: background-color 0.2s;
    cursor: pointer;
}

.notification-item:hover {
    background: var(--surface-hover);
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item i {
    font-size: 16px;
    margin-top: 2px;
    flex-shrink: 0;
}

.notification-title {
    margin: 0 0 4px 0;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-color);
    line-height: 1.3;
}

.notification-time {
    margin: 0;
    font-size: 12px;
    color: var(--text-color-secondary);
}

.notification-footer {
    padding: 12px 20px;
    border-top: 1px solid var(--surface-border);
    background: var(--surface-ground);
}

.view-all-btn {
    width: 100%;
    padding: 8px 16px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.2s;
}

.view-all-btn:hover {
    background: #2563eb;
}

.text-blue-500 {
    color: #3b82f6;
}

.text-orange-500 {
    color: #f97316;
}

.text-green-500 {
    color: #10b981;
}

.relative {
    position: relative;
}
</style>
