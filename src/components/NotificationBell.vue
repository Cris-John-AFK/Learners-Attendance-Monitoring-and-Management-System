<template>
    <div class="notification-bell-container">
        <div 
            class="notification-bell" 
            :class="{ 'ringing': hasUnreadNotifications }"
            @click="toggleDropdown"
        >
            <div class="bell-container">
                <i class="pi pi-bell"></i>
                <div v-if="hasUnreadNotifications" class="red-indicator"></div>
                <span 
                    v-if="notificationCount > 0" 
                    class="notification-badge"
                    :key="notificationCount"
                >
                    {{ notificationCount }}
                </span>
            </div>
        </div>

        <!-- Notification Dropdown -->
        <div 
            v-if="showDropdown" 
            class="notification-dropdown"
            @click.stop
        >
            <div class="notification-header">
                <h3>Notifications</h3>
                <button 
                    v-if="notifications.length > 0"
                    class="mark-all-read"
                    @click="markAllAsRead"
                >
                    Mark all as read
                </button>
            </div>

            <div class="notification-list">
                <div 
                    v-if="notifications.length === 0" 
                    class="no-notifications"
                >
                    <i class="pi pi-bell-slash"></i>
                    <p>No notifications</p>
                </div>

                <div 
                    v-for="notification in notifications" 
                    :key="notification.id"
                    class="notification-item"
                    :class="{ 'unread': !notification.read }"
                    @click="handleNotificationClick(notification)"
                >
                    <div class="notification-icon">
                        <i class="pi pi-check-circle" v-if="notification.type === 'session_completed'"></i>
                        <i class="pi pi-info-circle" v-else></i>
                    </div>
                    
                    <div class="notification-content">
                        <div class="notification-title">{{ notification.title }}</div>
                        <div class="notification-message">{{ notification.message }}</div>
                        <div class="notification-time">{{ formatTime(notification.timestamp) }}</div>
                    </div>

                    <div class="notification-actions">
                        <button 
                            class="close-notification"
                            @click.stop="removeNotification(notification.id)"
                        >
                            <i class="pi pi-times"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="notification-footer" v-if="notifications.length > 3">
                <button class="view-all-notifications" @click="viewAllNotifications">
                    View all notifications
                </button>
            </div>
        </div>

        <!-- Overlay to close dropdown -->
        <div 
            v-if="showDropdown" 
            class="notification-overlay"
            @click="closeDropdown"
        ></div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

// Props
const props = defineProps({
    notifications: {
        type: Array,
        default: () => []
    }
});

// Emits
const emit = defineEmits(['notification-clicked', 'mark-all-read', 'remove-notification']);

// State
const showDropdown = ref(false);

// Computed
const notificationCount = computed(() => {
    return props.notifications.filter(n => !n.read).length;
});

const hasUnreadNotifications = computed(() => {
    return notificationCount.value > 0;
});

// Methods
const toggleDropdown = () => {
    showDropdown.value = !showDropdown.value;
};

const closeDropdown = () => {
    showDropdown.value = false;
};

const handleNotificationClick = (notification) => {
    // Mark as read immediately in the local state
    if (!notification.read) {
        notification.read = true;
    }
    emit('notification-clicked', notification);
    closeDropdown();
};

const markAllAsRead = () => {
    // Mark all notifications as read in local state
    props.notifications.forEach(n => n.read = true);
    emit('mark-all-read');
};

const removeNotification = (notificationId) => {
    emit('remove-notification', notificationId);
};

const viewAllNotifications = () => {
    closeDropdown();
    // Navigate to notifications page (you can create this route)
    window.location.href = '/teacher/notifications';
};

const formatTime = (timestamp) => {
    if (!timestamp) return 'Just now';
    
    const now = new Date();
    const notificationTime = new Date(timestamp);
    
    // Check if the date is valid
    if (isNaN(notificationTime.getTime())) {
        return 'Just now';
    }
    
    const diffInMinutes = Math.floor((now - notificationTime) / (1000 * 60));
    
    // Handle negative differences (future dates)
    if (diffInMinutes < 0) return 'Just now';
    if (diffInMinutes < 1) return 'Just now';
    if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
    
    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return `${diffInHours}h ago`;
    
    const diffInDays = Math.floor(diffInHours / 24);
    return `${diffInDays}d ago`;
};

// Close dropdown when clicking outside
const handleClickOutside = (event) => {
    if (!event.target.closest('.notification-bell-container')) {
        closeDropdown();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.notification-bell-container {
    position: relative;
    display: inline-block;
}

.notification-bell {
    position: relative;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f8f9fa, #ffffff);
    border: 2px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.notification-bell:hover {
    background: linear-gradient(135deg, #e9ecef, #f1f3f4);
    transform: scale(1.08) translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border-color: #007bff;
}

.notification-bell:hover .notification-badge {
    transform: scale(1.1);
    box-shadow: 0 3px 12px rgba(255, 71, 87, 0.6);
}

.notification-bell.ringing {
    animation: bell-shake 0.6s ease-in-out infinite;
}

.notification-bell.ringing i {
    color: #ff4757;
}

.notification-bell.ringing .notification-badge {
    animation: pulse-badge 1s infinite, bounce-badge 0.6s ease-in-out infinite;
}

@keyframes bell-shake {
    0%, 100% { transform: rotate(0deg); }
    10%, 30%, 50%, 70%, 90% { transform: rotate(-8deg); }
    20%, 40%, 60%, 80% { transform: rotate(8deg); }
}

@keyframes pulse-badge {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 2px 8px rgba(255, 71, 87, 0.4);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 3px 12px rgba(255, 71, 87, 0.6);
    }
}

@keyframes bounce-badge {
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-2px) scale(1.1); }
}

.bell-container {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.red-indicator {
    position: absolute;
    top: -3px;
    right: -3px;
    width: 8px;
    height: 8px;
    background: #dc3545;
    border-radius: 50%;
    border: 2px solid white;
    z-index: 2;
}

.notification-bell i {
    font-size: 20px;
    color: #495057;
    transition: all 0.3s ease;
}

.notification-bell:hover i {
    color: #007bff;
    transform: scale(1.1);
}

.notification-badge {
    position: absolute;
    top: -12px;
    right: -8px;
    background: linear-gradient(135deg, #ff4757, #ff3742);
    color: white;
    border-radius: 50%;
    min-width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(255, 71, 87, 0.4);
    z-index: 10;
    animation: pulse-badge 2s infinite;
    transform: scale(1);
    transition: all 0.3s ease;
}

.notification-dropdown {
    position: absolute;
    top: 50px;
    right: 0;
    width: 380px;
    max-height: 500px;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    overflow: hidden;
}

.notification-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8f9fa;
}

.notification-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #212529;
}

.mark-all-read {
    background: none;
    border: 1px solid #007bff;
    color: #007bff;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    padding: 6px 12px;
    border-radius: 20px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.mark-all-read::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 123, 255, 0.1), transparent);
    transition: left 0.5s ease;
}

.mark-all-read:hover {
    background: #007bff;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

.mark-all-read:hover::before {
    left: 100%;
}

.notification-list {
    max-height: 350px;
    overflow-y: auto;
}

.no-notifications {
    padding: 40px 20px;
    text-align: center;
    color: #6c757d;
}

.no-notifications i {
    font-size: 32px;
    margin-bottom: 12px;
    display: block;
}

.notification-item {
    display: flex;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f3f4;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.notification-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 0;
    background: linear-gradient(90deg, rgba(0, 123, 255, 0.1), rgba(0, 123, 255, 0.05));
    transition: width 0.3s ease;
}

.notification-item:hover {
    background: #f8f9fa;
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.notification-item:hover::before {
    width: 100%;
}

.notification-item.unread {
    background: linear-gradient(90deg, #e3f2fd, #f8f9fa);
    border-left: 4px solid #007bff;
    position: relative;
}

.notification-item.unread::after {
    content: '';
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    width: 8px;
    height: 8px;
    background: #007bff;
    border-radius: 50%;
    animation: pulse-unread 2s infinite;
}

@keyframes pulse-unread {
    0%, 100% {
        opacity: 1;
        transform: translateY(-50%) scale(1);
    }
    50% {
        opacity: 0.6;
        transform: translateY(-50%) scale(1.2);
    }
}

.notification-icon {
    margin-right: 12px;
    display: flex;
    align-items: flex-start;
    padding-top: 2px;
}

.notification-icon i {
    font-size: 16px;
    color: #28a745;
}

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-title {
    font-weight: 600;
    font-size: 14px;
    color: #212529;
    margin-bottom: 4px;
}

.notification-message {
    font-size: 13px;
    color: #6c757d;
    line-height: 1.4;
    margin-bottom: 4px;
}

.notification-time {
    font-size: 12px;
    color: #adb5bd;
}

.notification-actions {
    margin-left: 8px;
    display: flex;
    align-items: flex-start;
}

.close-notification {
    background: none;
    border: none;
    color: #adb5bd;
    cursor: pointer;
    padding: 6px;
    border-radius: 50%;
    transition: all 0.3s ease;
    opacity: 0;
    transform: scale(0.8);
}

.notification-item:hover .close-notification {
    opacity: 1;
    transform: scale(1);
}

.close-notification:hover {
    background: #ff4757;
    color: white;
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(255, 71, 87, 0.3);
}

.notification-footer {
    padding: 12px 20px;
    border-top: 1px solid #e9ecef;
    background: #f8f9fa;
    text-align: center;
}

.view-all-notifications {
    background: none;
    border: none;
    color: #007bff;
    font-size: 14px;
    cursor: pointer;
    padding: 8px 16px;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.view-all-notifications:hover {
    background: #e3f2fd;
}

.notification-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 999;
    background: transparent;
}
</style>
