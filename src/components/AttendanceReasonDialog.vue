<template>
    <Dialog 
        v-model:visible="visible" 
        modal 
        :header="dialogTitle" 
        :style="{ width: '550px' }" 
        @hide="onClose"
        class="reason-dialog"
    >
        <!-- Student Info Header -->
        <div class="student-info-header">
            <div class="student-avatar">
                <i class="pi pi-user"></i>
            </div>
            <div class="student-details">
                <h3 class="student-name">{{ props.studentName || 'Student' }}</h3>
                <div class="status-badge" :class="statusType">
                    <i :class="statusIcon"></i>
                    <span>{{ statusType === 'late' ? 'Late' : 'Excused' }}</span>
                </div>
            </div>
        </div>

        <div class="dialog-content">
            <!-- Reason Selection -->
            <div class="reason-field">
                <label for="reason" class="field-label">
                    <i class="pi pi-list"></i>
                    Select Reason <span class="required">*</span>
                </label>
                <Dropdown
                    id="reason"
                    v-model="selectedReasonId"
                    :options="availableReasons"
                    optionLabel="reason_name"
                    optionValue="id"
                    placeholder="Choose a reason..."
                    class="reason-dropdown"
                    :class="{ 'p-invalid': submitted && !selectedReasonId }"
                    @change="onReasonChange"
                />
                <small v-if="submitted && !selectedReasonId" class="error-message">
                    <i class="pi pi-exclamation-triangle"></i>
                    Please select a reason
                </small>
            </div>

            <!-- Additional Notes -->
            <div v-if="showNotesField" class="notes-field">
                <label for="notes" class="field-label">
                    <i class="pi pi-file-edit"></i>
                    Additional Notes
                    <span v-if="isOtherSelected" class="required">*</span>
                    <span v-else class="optional">(Optional)</span>
                </label>
                <Textarea
                    id="notes"
                    v-model="reasonNotes"
                    rows="4"
                    class="notes-textarea"
                    :class="{ 'p-invalid': submitted && isOtherSelected && !reasonNotes }"
                    :placeholder="isOtherSelected ? 'Please provide specific details...' : 'Add any additional information...'"
                />
                <small v-if="submitted && isOtherSelected && !reasonNotes" class="error-message">
                    <i class="pi pi-exclamation-triangle"></i>
                    Details are required when selecting "Other"
                </small>
            </div>
        </div>

        <template #footer>
            <div class="dialog-footer">
                <Button 
                    label="Cancel" 
                    icon="pi pi-times" 
                    class="cancel-btn" 
                    @click="onClose" 
                />
                <Button 
                    label="Confirm" 
                    icon="pi pi-check" 
                    class="confirm-btn"
                    :disabled="!selectedReasonId || (isOtherSelected && !reasonNotes)"
                    @click="onConfirm" 
                />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import axios from 'axios';

const props = defineProps({
    modelValue: Boolean,
    statusType: {
        type: String,
        required: true,
        validator: (value) => ['late', 'excused'].includes(value)
    },
    studentName: String
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const visible = computed({
    get() {
        return props.modelValue;
    },
    set(value) {
        emit('update:modelValue', value);
    }
});

const dialogTitle = computed(() => {
    const status = props.statusType === 'late' ? 'Late' : 'Excused';
    return props.studentName 
        ? `${status} Reason - ${props.studentName}`
        : `Select ${status} Reason`;
});

const availableReasons = ref([]);
const selectedReasonId = ref(null);
const reasonNotes = ref('');
const submitted = ref(false);
const showNotesField = ref(false);

const isOtherSelected = computed(() => {
    const selectedReason = availableReasons.value.find(r => r.id === selectedReasonId.value);
    return selectedReason && selectedReason.reason_name === 'Other';
});

const statusIcon = computed(() => {
    return props.statusType === 'late' ? 'pi pi-clock' : 'pi pi-info-circle';
});

// Load reasons when dialog opens
watch(visible, async (newValue) => {
    if (newValue) {
        await loadReasons();
        // Reset form
        selectedReasonId.value = null;
        reasonNotes.value = '';
        submitted.value = false;
    }
});

const loadReasons = async () => {
    try {
        const response = await axios.get(`http://localhost:8000/api/attendance/reasons/${props.statusType}`);
        if (response.data.success) {
            availableReasons.value = response.data.reasons;
        }
    } catch (error) {
        console.error('Failed to load attendance reasons:', error);
    }
};

const onReasonChange = () => {
    showNotesField.value = true;
};

const onConfirm = () => {
    submitted.value = true;
    
    if (!selectedReasonId.value) {
        return;
    }
    
    if (isOtherSelected.value && !reasonNotes.value) {
        return;
    }

    const selectedReason = availableReasons.value.find(r => r.id === selectedReasonId.value);
    
    emit('confirm', {
        reason_id: selectedReasonId.value,
        reason_name: selectedReason.reason_name,
        reason_notes: reasonNotes.value || null
    });
    
    visible.value = false;
};

const onClose = () => {
    visible.value = false;
};
</script>

<style scoped>
/* Dialog Styling */
.reason-dialog :deep(.p-dialog-header) {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px 12px 0 0;
    padding: 1.5rem;
}

.reason-dialog :deep(.p-dialog-header .p-dialog-title) {
    font-weight: 600;
    font-size: 1.1rem;
}

.reason-dialog :deep(.p-dialog-content) {
    padding: 0;
    border-radius: 0 0 12px 12px;
}

/* Student Info Header */
.student-info-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-bottom: 1px solid #e2e8f0;
    margin: -1.5rem -1.5rem 0 -1.5rem;
}

.student-avatar {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}

.student-details {
    flex: 1;
}

.student-name {
    margin: 0 0 0.5rem 0;
    font-size: 1.2rem;
    font-weight: 600;
    color: #1e293b;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.status-badge.late {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.status-badge.excused {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
}

/* Dialog Content */
.dialog-content {
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* Field Styling */
.reason-field,
.notes-field {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.field-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #374151;
    font-size: 0.95rem;
}

.field-label i {
    color: #6366f1;
}

.required {
    color: #ef4444;
    font-weight: 700;
}

.optional {
    color: #6b7280;
    font-weight: 400;
    font-style: italic;
}

/* Dropdown Styling */
.reason-dropdown {
    width: 100%;
    border-radius: 8px;
    border: 2px solid #e5e7eb;
    transition: all 0.2s ease;
}

.reason-dropdown:hover {
    border-color: #d1d5db;
}

.reason-dropdown:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Textarea Styling */
.notes-textarea {
    width: 100%;
    border-radius: 8px;
    border: 2px solid #e5e7eb;
    transition: all 0.2s ease;
    font-family: inherit;
    resize: vertical;
    min-height: 100px;
}

.notes-textarea:hover {
    border-color: #d1d5db;
}

.notes-textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Error Styling */
.p-invalid {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}

.error-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #ef4444;
    font-size: 0.875rem;
    font-weight: 500;
}

.error-message i {
    color: #ef4444;
}

/* Footer Styling */
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    margin: 0 -1.5rem -1.5rem -1.5rem;
    border-radius: 0 0 12px 12px;
}

.cancel-btn {
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.cancel-btn:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
    color: #374151;
}

.confirm-btn {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    transition: all 0.2s ease;
}

.confirm-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

.confirm-btn:disabled {
    background: #d1d5db;
    color: #9ca3af;
    box-shadow: none;
    cursor: not-allowed;
}

/* Animation */
.reason-dialog :deep(.p-dialog) {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>
