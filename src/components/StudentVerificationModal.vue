<template>
    <Dialog 
        :visible="visible" 
        @update:visible="$emit('update:visible', $event)"
        modal 
        :header="headerText"
        :style="{ width: '500px' }" 
        :closable="false"
        :draggable="false"
        class="verification-modal"
    >
        <div class="verification-content">
            <!-- Student Photo and Info -->
            <div class="student-display">
                <div class="photo-container">
                    <img 
                        :src="student.photo" 
                        :alt="student.name" 
                        class="student-photo"
                        @error="handleImageError"
                    />
                </div>
                
                <div class="student-info">
                    <h3 class="student-name">{{ student.name }}</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">ID:</span>
                            <span class="value">{{ student.id }}</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Grade:</span>
                            <span class="value">{{ student.gradeLevel }}</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Section:</span>
                            <span class="value">{{ student.section }}</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Action:</span>
                            <span class="value record-type" :class="recordTypeClass">
                                <i :class="recordTypeIcon"></i>
                                {{ recordTypeText }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Countdown Timer -->
            <div class="countdown-section">
                <div class="countdown-circle" :class="{ 'urgent': countdown <= 3 }">
                    <div class="countdown-number">{{ countdown }}</div>
                    <div class="countdown-label">seconds</div>
                </div>
                <p class="verification-text">
                    Please verify this student's identity
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <Button 
                    label="Confirm" 
                    icon="pi pi-check" 
                    class="p-button-success confirm-btn"
                    @click="confirmVerification"
                    :disabled="processing"
                />
                <Button 
                    label="Reject" 
                    icon="pi pi-times" 
                    class="p-button-danger reject-btn"
                    @click="rejectVerification"
                    :disabled="processing"
                />
                <Button 
                    label="Next Student" 
                    icon="pi pi-arrow-right" 
                    class="p-button-secondary next-btn"
                    @click="skipToNext"
                    :disabled="processing"
                />
            </div>

            <!-- Processing Indicator -->
            <div v-if="processing" class="processing-overlay">
                <ProgressSpinner />
                <p>Recording attendance...</p>
            </div>
        </div>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import ProgressSpinner from 'primevue/progressspinner';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    student: {
        type: Object,
        required: true
    },
    recordType: {
        type: String,
        required: true,
        validator: (value) => ['check-in', 'check-out'].includes(value)
    }
});

const emit = defineEmits(['update:visible', 'confirm', 'reject', 'skip']);

// Reactive data
const countdown = ref(10);
const processing = ref(false);
let countdownInterval = null;

// Computed properties
const headerText = computed(() => {
    return `Student ${props.recordType === 'check-in' ? 'Check-In' : 'Check-Out'} Verification`;
});

const recordTypeClass = computed(() => {
    return props.recordType === 'check-in' ? 'check-in' : 'check-out';
});

const recordTypeIcon = computed(() => {
    return props.recordType === 'check-in' ? 'pi pi-sign-in' : 'pi pi-sign-out';
});

const recordTypeText = computed(() => {
    return props.recordType === 'check-in' ? 'Check In' : 'Check Out';
});

// Watch for visibility changes to start/stop countdown
watch(() => props.visible, (newVisible) => {
    if (newVisible) {
        startCountdown();
    } else {
        stopCountdown();
    }
});

// Methods
const startCountdown = () => {
    countdown.value = 10;
    countdownInterval = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            // Auto-confirm when countdown reaches 0
            confirmVerification();
        }
    }, 1000);
};

const stopCountdown = () => {
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
};

const confirmVerification = async () => {
    if (processing.value) return;
    
    processing.value = true;
    stopCountdown();
    
    try {
        emit('confirm', {
            student: props.student,
            recordType: props.recordType
        });
    } finally {
        processing.value = false;
    }
};

const rejectVerification = () => {
    if (processing.value) return;
    
    stopCountdown();
    emit('reject', {
        student: props.student,
        recordType: props.recordType
    });
};

const skipToNext = () => {
    if (processing.value) return;
    
    stopCountdown();
    emit('skip');
};

const handleImageError = (event) => {
    // Set default image based on gender or use generic default
    const defaultImage = props.student.gender === 'male' 
        ? '/demo/images/avatar/default-male-student.png'
        : '/demo/images/avatar/default-female-student.png';
    
    event.target.src = defaultImage;
};

// Cleanup on unmount
onUnmounted(() => {
    stopCountdown();
});
</script>

<style scoped>
.verification-modal {
    --primary-color: #3b82f6;
    --success-color: #10b981;
    --danger-color: #ef4444;
    --warning-color: #f59e0b;
}

.verification-content {
    position: relative;
    padding: 1rem;
}

.student-display {
    display: flex;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 12px;
    border: 2px solid #e2e8f0;
}

.photo-container {
    flex-shrink: 0;
}

.student-photo {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.student-info {
    flex: 1;
}

.student-name {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 1rem 0;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background: white;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.info-item .label {
    font-weight: 600;
    color: #64748b;
    font-size: 0.875rem;
}

.info-item .value {
    font-weight: 500;
    color: #1e293b;
}

.record-type {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.record-type.check-in {
    background: #dcfce7;
    color: #166534;
}

.record-type.check-out {
    background: #fef3c7;
    color: #92400e;
}

.countdown-section {
    text-align: center;
    margin-bottom: 2rem;
}

.countdown-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), #1d4ed8);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
    transition: all 0.3s ease;
}

.countdown-circle.urgent {
    background: linear-gradient(135deg, var(--danger-color), #dc2626);
    animation: pulse 1s infinite;
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.countdown-number {
    font-size: 2rem;
    font-weight: 700;
    color: white;
    line-height: 1;
}

.countdown-label {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.9);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.verification-text {
    color: #64748b;
    font-size: 1rem;
    margin: 0;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.action-buttons .p-button {
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.confirm-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.reject-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.next-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
}

.processing-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.95);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    z-index: 10;
}

.processing-overlay p {
    margin-top: 1rem;
    color: #64748b;
    font-weight: 500;
}

/* Responsive design */
@media (max-width: 640px) {
    .student-display {
        flex-direction: column;
        text-align: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>
