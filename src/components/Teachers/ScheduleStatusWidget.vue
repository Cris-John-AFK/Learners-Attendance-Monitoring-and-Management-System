<template>
    <div class="schedule-status-widget">
        <!-- Current Schedule Status -->
        <div v-if="currentStatus.isActive" class="current-schedule active">
            <div class="status-header">
                <i class="pi pi-clock text-white-600"></i>
                <span class="status-title">Current Class</span>
                <div class="countdown-timer green">{{ formatTime(currentStatus.timeRemaining) }} remaining</div>
            </div>

            <div class="schedule-details">
                <h4 class="subject-name">{{ currentStatus.schedule.subject_name }}</h4>
                <p class="section-name">{{ currentStatus.schedule.section_name }}</p>
                <div class="time-range">
                    {{ formatTimeRange(currentStatus.schedule.start_time, currentStatus.schedule.end_time) }}
                </div>
            </div>

            <div class="schedule-actions">
                <Button label="Take Attendance" icon="pi pi-users" size="small" class="p-button-success" @click="startAttendanceSession(currentStatus.schedule)" />
            </div>
        </div>

        <!-- Next Schedule -->
        <div v-else-if="currentStatus.nextSchedule" class="next-schedule">
            <div class="status-header">
                <i class="pi pi-calendar text-blue-600"></i>
                <span class="status-title">Next Class</span>
                <div class="countdown-timer" :class="getCountdownClass(currentStatus.timeToNext)">{{ formatTime(currentStatus.timeToNext) }} until start</div>
            </div>

            <div class="schedule-details">
                <h4 class="subject-name">{{ currentStatus.nextSchedule.subject_name }}</h4>
                <p class="section-name">{{ currentStatus.nextSchedule.section_name }}</p>
                <div class="time-range">
                    {{ formatTimeRange(currentStatus.nextSchedule.start_time, currentStatus.nextSchedule.end_time) }}
                </div>
            </div>

            <div class="schedule-actions" v-if="currentStatus.timeToNext <= 15">
                <Button label="Prepare Session" icon="pi pi-cog" size="small" class="p-button-outlined" @click="prepareSession(currentStatus.nextSchedule)" />
            </div>
        </div>

        <!-- No Schedules -->
        <div v-else class="no-schedule">
            <div class="status-header">
                <i class="pi pi-calendar-times text-gray-400"></i>
                <span class="status-title">No Classes Today</span>
            </div>
            <p class="no-schedule-message">You have no more scheduled classes for today.</p>
        </div>
    </div>
</template>

<script setup>
import ScheduleNotificationService from '@/services/ScheduleNotificationService';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const toast = useToast();

// Data
const currentStatus = ref({
    isActive: false,
    schedule: null,
    nextSchedule: null,
    timeRemaining: 0,
    timeToNext: null
});

// Methods
const updateStatus = (data) => {
    currentStatus.value = data.currentStatus || {
        isActive: false,
        schedule: null,
        nextSchedule: null,
        timeRemaining: 0,
        timeToNext: null
    };
};

const formatTime = (minutes) => {
    if (minutes <= 0) return '0m';

    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;

    if (hours > 0) {
        return `${hours}h ${mins}m`;
    }
    return `${mins}m`;
};

const formatTimeRange = (startTime, endTime) => {
    const formatTime = (time) => {
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        return `${displayHour}:${minutes} ${ampm}`;
    };

    return `${formatTime(startTime)} - ${formatTime(endTime)}`;
};

const formatNotificationTime = (timestamp) => {
    const now = new Date();
    const notificationTime = new Date(timestamp);
    const diffMinutes = Math.floor((now - notificationTime) / (1000 * 60));

    if (diffMinutes < 1) return 'Just now';
    if (diffMinutes < 60) return `${diffMinutes}m ago`;

    const diffHours = Math.floor(diffMinutes / 60);
    if (diffHours < 24) return `${diffHours}h ago`;

    return notificationTime.toLocaleDateString();
};

const getCountdownClass = (minutes) => {
    if (minutes <= 5) return 'urgent';
    if (minutes <= 10) return 'warning';
    return 'normal';
};

const getNotificationClass = (priority) => {
    return {
        'notification-high': priority === 'high',
        'notification-warning': priority === 'warning',
        'notification-info': priority === 'info'
    };
};

const startAttendanceSession = (schedule) => {
    console.log('🎯 Starting attendance session for current schedule:', schedule);
    
    // Navigate to subject attendance page for the current active schedule
    router.push({
        name: 'subject-attendance',
        params: {
            subjectId: schedule.subject_id || schedule.subject?.id
        },
        query: {
            sectionId: schedule.section_id,
            scheduleId: schedule.id,
            autoStart: 'true'
        }
    });
};

const prepareSession = (schedule) => {
    toast.add({
        severity: 'info',
        summary: 'Session Preparation',
        detail: `Preparing for ${schedule.subject_name} in ${schedule.section_name}`,
        life: 3000
    });

    // Navigate to session preparation or attendance interface
    router.push({
        name: 'teacher-attendance-sessions',
        query: {
            prepare: 'true',
            scheduleId: schedule.id,
            sectionId: schedule.section_id,
            subjectId: schedule.subject_id
        }
    });
};

const clearNotification = (notificationId) => {
    ScheduleNotificationService.clearNotification(notificationId);
};

// Lifecycle
onMounted(() => {
    // Initialize schedule notification service
    ScheduleNotificationService.initialize();

    // Add listener for updates
    ScheduleNotificationService.addListener(updateStatus);

    // Get initial status
    const initialStatus = ScheduleNotificationService.getCurrentScheduleStatus();

    updateStatus({
        currentStatus: initialStatus
    });
});

onUnmounted(() => {
    // Remove listener
    ScheduleNotificationService.removeListener(updateStatus);
});
</script>

<style scoped>
.schedule-status-widget {
    background: white;
    border-radius: 6px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    border: 1px solid #e5e7eb;
    max-height: 140px;
    max-width: 280px;
}

.current-schedule.active {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border-radius: 4px;
    padding: 0.5rem;
}

.next-schedule {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    border-radius: 4px;
    padding: 0.5rem;
    margin-bottom: 0.25rem;
}

.no-schedule {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
    margin-bottom: 1rem;
}

.status-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.status-title {
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.countdown-timer {
    font-weight: 600;
    font-size: 0.75rem;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
}

.countdown-timer.green {
    background: rgba(255, 255, 255, 0.2);
}

.countdown-timer.urgent {
    background: #ef4444;
    color: white;
}

.countdown-timer.warning {
    background: #f59e0b;
    color: white;
}

.countdown-timer.normal {
    background: rgba(255, 255, 255, 0.2);
}

.schedule-details {
    margin-bottom: 0.5rem;
}

.subject-name {
    font-weight: 600;
    font-size: 0.875rem;
    margin: 0 0 0.125rem 0;
    color: inherit;
}

.section-name {
    font-size: 0.75rem;
    opacity: 0.9;
    margin: 0 0 0.25rem 0;
}

.time-range {
    font-size: 0.75rem;
    font-weight: 500;
    opacity: 0.9;
}

.schedule-actions {
    display: flex;
    gap: 0.25rem;
    margin-top: 0.25rem;
}

.no-schedule-message {
    color: #6b7280;
    margin: 0;
}

@media (max-width: 768px) {
    .schedule-status-widget {
        padding: 0.5rem;
        max-width: 100%;
    }

    .status-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
        margin-bottom: 0.25rem;
    }

    .countdown-timer {
        align-self: flex-end;
        font-size: 0.625rem;
        padding: 0.15rem 0.4rem;
    }
}
</style>
