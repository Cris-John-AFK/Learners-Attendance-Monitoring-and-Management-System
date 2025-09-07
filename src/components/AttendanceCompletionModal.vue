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
                
                <div class="modal-stats" v-if="sessionData">
                    <div class="stat-item">
                        <span class="stat-number">{{ presentCount }}</span>
                        <span class="stat-label">Present</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">{{ absentCount }}</span>
                        <span class="stat-label">Absent</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number">{{ lateCount }}</span>
                        <span class="stat-label">Late</span>
                    </div>
                </div>

                <div class="modal-actions">
                    <Button 
                        label="View Details" 
                        icon="pi pi-eye" 
                        class="p-button-outlined p-button-info"
                        @click="$emit('view-details')"
                    />
                    <Button 
                        label="Edit Attendance" 
                        icon="pi pi-pencil" 
                        class="p-button-outlined p-button-warning"
                        @click="$emit('edit-attendance')"
                    />
                    <Button 
                        label="Start New Session" 
                        icon="pi pi-plus" 
                        class="p-button-success"
                        @click="$emit('start-new-session')"
                    />
                </div>

                <div class="modal-footer">
                    <div class="dont-show-again">
                        <label class="checkbox-label">
                            <input 
                                type="checkbox" 
                                v-model="dontShowAgainToday"
                                @change="handleDontShowAgainChange"
                            />
                            <span class="checkmark"></span>
                            Don't show again today
                        </label>
                    </div>
                    
                    <Button 
                        icon="pi pi-times" 
                        class="p-button-text p-button-rounded close-btn"
                        @click="handleClose"
                    />
                </div>
                
                <Button 
                    icon="pi pi-times" 
                    class="modal-close-btn p-button-text p-button-plain"
                    @click="$emit('close')"
                    v-tooltip.left="'Dismiss'"
                />
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';

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

const presentCount = computed(() => {
    return props.sessionData?.statistics?.present || 
           props.sessionData?.attendance_records?.filter(r => r.status_code === 'P').length || 0;
});

const absentCount = computed(() => {
    return props.sessionData?.statistics?.absent || 
           props.sessionData?.attendance_records?.filter(r => r.status_code === 'A').length || 0;
});

const lateCount = computed(() => {
    return props.sessionData?.statistics?.late || 
           props.sessionData?.attendance_records?.filter(r => r.status_code === 'L').length || 0;
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

.modal-stats {
    display: flex;
    justify-content: space-around;
    margin: 2rem 0;
    gap: 1.5rem;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 800;
    color: #059669;
    line-height: 1;
}

.stat-label {
    font-size: 0.875rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-top: 0.5rem;
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

.checkbox-label input[type="checkbox"] {
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

.checkbox-label input[type="checkbox"]:checked + .checkmark {
    background-color: #3b82f6;
    border-color: #3b82f6;
}

.checkbox-label input[type="checkbox"]:checked + .checkmark::after {
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

.close-btn {
    width: 2rem !important;
    height: 2rem !important;
    padding: 0 !important;
    min-width: auto !important;
}

.modal-close-btn {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 2rem !important;
    height: 2rem !important;
    padding: 0 !important;
    min-width: auto !important;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
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
