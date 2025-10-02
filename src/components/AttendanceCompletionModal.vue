<template>
    <Teleport to="body">
        <div v-if="visible" class="attendance-completion-overlay main-content-only" @click="handleOverlayClick">
            <div class="attendance-completion-modal" @click.stop>
                <div class="modal-header">
                    <div class="success-icon">
                        <i class="pi pi-check-circle"></i>
                    </div>
                    <h2 class="modal-title">Attendance Completed</h2>
                    <p class="modal-subtitle">{{ subjectName }} - {{ sessionDate }}</p>
                </div>

                <div class="modal-stats" v-if="sessionData && sessionData.statistics">
                    <div class="stats-grid">
                        <div class="stat-card present">
                            <div class="stat-icon">
                                <i class="pi pi-check"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-number">{{ sessionData.statistics.present || 0 }}</span>
                                <span class="stat-label">Present</span>
                            </div>
                        </div>
                        <div class="stat-card absent">
                            <div class="stat-icon">
                                <i class="pi pi-times"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-number">{{ sessionData.statistics.absent || 0 }}</span>
                                <span class="stat-label">Absent</span>
                            </div>
                        </div>
                        <div class="stat-card late">
                            <div class="stat-icon">
                                <i class="pi pi-clock"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-number">{{ sessionData.statistics.late || 0 }}</span>
                                <span class="stat-label">Late</span>
                            </div>
                        </div>
                        <div class="stat-card excused">
                            <div class="stat-icon">
                                <i class="pi pi-info-circle"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-number">{{ sessionData.statistics.excused || 0 }}</span>
                                <span class="stat-label">Excused</span>
                            </div>
                        </div>
                    </div>

                    <div class="session-summary">
                        <div class="summary-row">
                            <span class="summary-label">Total Students:</span>
                            <span class="summary-value">{{ sessionData.statistics.total_students || 0 }}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Marked:</span>
                            <span class="summary-value">{{ sessionData.statistics.marked_students || 0 }}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Session Duration:</span>
                            <span class="summary-value">{{ formatSessionDuration() }}</span>
                        </div>
                        <div class="summary-row">
                            <span class="summary-label">Attendance Rate:</span>
                            <span class="summary-value attendance-rate" :class="getAttendanceRateClass()">{{ calculateAttendanceRate() }}%</span>
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <Button label="View Details" icon="pi pi-eye" class="p-button-outlined p-button-info" @click="$emit('view-details')" />
                    <Button label="Edit Attendance" icon="pi pi-pencil" class="p-button-outlined p-button-warning" @click="$emit('edit-attendance')" />
                    <Button label="Start New Session" icon="pi pi-plus" class="p-button-success" @click="$emit('start-new-session')" />
                </div>

                <!-- Windows-style close button -->
                <button class="windows-close-btn" @click="$emit('close')" title="Close">
                    <i class="pi pi-times"></i>
                </button>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import Button from 'primevue/button';
import { computed, ref } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    subjectName: {
        type: String,
        default: ''
    },
    sessionDate: {
        type: String,
        default: ''
    },
    sessionData: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['close', 'view-details', 'edit-attendance', 'start-new-session', 'dont-show-again']);

const dontShowAgainToday = ref(false);

// Helper functions for session data display
const formatSessionDuration = () => {
    // Try different possible property names from the backend response
    const startTime = props.sessionData?.session_start_time || props.sessionData?.start_time || props.sessionData?.created_at;
    const endTime = props.sessionData?.session_end_time || props.sessionData?.end_time || props.sessionData?.completed_at || new Date().toISOString(); // Use current time if no end time

    if (!startTime) {
        return 'N/A';
    }

    try {
        const start = new Date(startTime);
        const end = new Date(endTime);
        const diffMs = end - start;
        const diffMins = Math.floor(diffMs / 60000);

        if (diffMins < 1) {
            return '< 1 min';
        } else if (diffMins < 60) {
            return `${diffMins} min`;
        } else {
            const hours = Math.floor(diffMins / 60);
            const mins = diffMins % 60;
            return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
        }
    } catch (error) {
        console.error('Error calculating session duration:', error);
        return 'N/A';
    }
};

const calculateAttendanceRate = () => {
    if (!props.sessionData?.statistics) return 0;

    const stats = props.sessionData.statistics;
    const totalMarked = stats.marked_students || 0;
    const presentAndLate = (stats.present || 0) + (stats.late || 0);

    if (totalMarked === 0) return 0;
    return Math.round((presentAndLate / totalMarked) * 100);
};

const getAttendanceRateClass = () => {
    const rate = calculateAttendanceRate();
    if (rate >= 90) return 'excellent';
    if (rate >= 75) return 'good';
    if (rate >= 60) return 'fair';
    return 'poor';
};

const presentCount = computed(() => {
    return props.sessionData?.statistics?.present || props.sessionData?.attendance_records?.filter((r) => r.status_code === 'P').length || 0;
});

const absentCount = computed(() => {
    return props.sessionData?.statistics?.absent || props.sessionData?.attendance_records?.filter((r) => r.status_code === 'A').length || 0;
});

const lateCount = computed(() => {
    return props.sessionData?.statistics?.late || props.sessionData?.attendance_records?.filter((r) => r.status_code === 'L').length || 0;
});

const handleOverlayClick = () => {
    // Close modal when clicking outside
    handleClose();
};

const handleClose = () => {
    if (dontShowAgainToday.value) {
        emit('dont-show-again');
    } else {
        emit('close');
    }
};

const handleDontShowAgainChange = () => {
    // This will be handled when the modal is closed
};
</script>

<style scoped>
.attendance-completion-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease-in-out;
}

/* Main content area only overlay */
.attendance-completion-overlay.main-content-only {
    top: 4rem; /* Account for topbar height */
    left: 16rem; /* Account for sidebar width */
}

@media (max-width: 991px) {
    .attendance-completion-overlay.main-content-only {
        left: 0; /* Full width on mobile */
    }
}

.attendance-completion-modal {
    background: white;
    border-radius: 16px;
    padding: 2.5rem;
    text-align: center;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    animation: modalSlideUp 0.4s ease-out;
}

.modal-header {
    margin-bottom: 2rem;
}

.success-icon {
    margin-bottom: 1rem;
}

.success-icon .pi-check-circle {
    font-size: 4rem;
    color: #22c55e;
}

.modal-title {
    font-size: 1.875rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.5rem 0;
}

.modal-subtitle {
    font-size: 1rem;
    color: #6b7280;
    margin: 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    background: #f9fafb;
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    font-size: 1.2rem;
}

.stat-card.present .stat-icon {
    background: #dcfce7;
    color: #16a34a;
}

.stat-card.absent .stat-icon {
    background: #fee2e2;
    color: #dc2626;
}

.stat-card.late .stat-icon {
    background: #fef3c7;
    color: #d97706;
}

.stat-card.excused .stat-icon {
    background: #dbeafe;
    color: #2563eb;
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.session-summary {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 1rem;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e2e8f0;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-label {
    font-weight: 500;
    color: #374151;
}

.summary-value {
    font-weight: 600;
    color: #111827;
}

.attendance-rate.excellent {
    color: #16a34a;
}

.attendance-rate.good {
    color: #059669;
}

.attendance-rate.fair {
    color: #d97706;
}

.attendance-rate.poor {
    color: #dc2626;
}

.modal-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 1.5rem;
}

.modal-actions .p-button {
    padding: 0.75rem 1.25rem;
    font-weight: 600;
    border-radius: 8px;
}

.modal-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.dont-show-again {
    display: flex;
    align-items: center;
}

.checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 0.875rem;
    color: #6b7280;
    user-select: none;
}

.checkbox-label input[type='checkbox'] {
    display: none;
}

.checkmark {
    width: 18px;
    height: 18px;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    margin-right: 8px;
    position: relative;
    transition: all 0.2s ease;
}

.checkbox-label input[type='checkbox']:checked + .checkmark {
    background-color: #3b82f6;
    border-color: #3b82f6;
}

.checkbox-label input[type='checkbox']:checked + .checkmark::after {
    content: '';
    position: absolute;
    left: 5px;
    top: 2px;
    width: 4px;
    height: 8px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.windows-close-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    font-size: 16px;
    transition: all 0.2s ease;
    z-index: 10;
}

.windows-close-btn:hover {
    background: #ef4444;
    color: white;
}

.windows-close-btn:active {
    background: #dc2626;
    transform: scale(0.95);
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Responsive design */
@media (max-width: 768px) {
    .attendance-completion-modal {
        padding: 2rem;
        margin: 1rem;
    }

    .modal-title {
        font-size: 1.5rem;
    }

    .modal-stats {
        flex-direction: column;
        gap: 1rem;
    }

    .stat-number {
        font-size: 2rem;
    }

    .modal-actions {
        flex-direction: column;
    }

    .modal-actions .p-button {
        width: 100%;
    }
}
</style>
