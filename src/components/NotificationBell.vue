<template>
    <div class="notification-bell-container">
        <div 
            class="notification-bell" 
            :class="{ 'ringing': hasUnreadNotifications }"
            @click="toggleDropdown"
        >
            <i class="pi pi-bell"></i>
            <span 
                v-if="notificationCount > 0" 
                class="notification-badge"
            >
                {{ notificationCount > 99 ? '99+' : notificationCount }}
            </span>
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
                <button class="view-all-notifications">
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
    emit('notification-clicked', notification);
    closeDropdown();
};

const markAllAsRead = () => {
    emit('mark-all-read');
};

const removeNotification = (notificationId) => {
    emit('remove-notification', notificationId);
};

const formatTime = (timestamp) => {
    const now = new Date();
    const notificationTime = new Date(timestamp);
    const diffInMinutes = Math.floor((now - notificationTime) / (1000 * 60));
    
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
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.notification-bell:hover {
    background: #e9ecef;
    transform: scale(1.05);
}

.notification-bell.ringing {
    animation: shake 0.5s ease-in-out infinite;
}

@keyframes shake {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(-10deg); }
    75% { transform: rotate(10deg); }
}

.notification-bell i {
    font-size: 18px;
    color: #6c757d;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    min-width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: bold;
    border: 2px solid white;
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
    border: none;
    color: #007bff;
    font-size: 14px;
    cursor: pointer;
    padding: 4px 8px;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.mark-all-read:hover {
    background: #e3f2fd;
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
    transition: background-color 0.2s;
}

.notification-item:hover {
    background: #f8f9fa;
}

.notification-item.unread {
    background: #e3f2fd;
    border-left: 4px solid #007bff;
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
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s;
}

.close-notification:hover {
    background: #f1f3f4;
    color: #6c757d;
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
