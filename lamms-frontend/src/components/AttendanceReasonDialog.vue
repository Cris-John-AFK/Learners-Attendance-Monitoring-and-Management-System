<template>
    <Dialog v-model:visible="visible" modal :header="dialogTitle" :style="{ width: '500px' }" @hide="onClose">
        <div class="flex flex-column gap-3">
            <!-- Reason Selection -->
            <div class="field">
                <label for="reason" class="font-semibold">Reason <span class="text-red-500">*</span></label>
                <Dropdown
                    id="reason"
                    v-model="selectedReasonId"
                    :options="availableReasons"
                    optionLabel="reason_name"
                    optionValue="id"
                    placeholder="Select a reason"
                    class="w-full"
                    :class="{ 'p-invalid': submitted && !selectedReasonId }"
                    @change="onReasonChange"
                />
                <small v-if="submitted && !selectedReasonId" class="p-error">Reason is required.</small>
            </div>

            <!-- Additional Notes (Optional for all, Required if "Other" is selected) -->
            <div v-if="showNotesField" class="field">
                <label for="notes" class="font-semibold">
                    Additional Notes
                    <span v-if="isOtherSelected" class="text-red-500">*</span>
                    <span v-else class="text-gray-500">(Optional)</span>
                </label>
                <Textarea
                    id="notes"
                    v-model="reasonNotes"
                    rows="3"
                    class="w-full"
                    :class="{ 'p-invalid': submitted && isOtherSelected && !reasonNotes }"
                    placeholder="Provide additional details..."
                />
                <small v-if="submitted && isOtherSelected && !reasonNotes" class="p-error">
                    Please provide details when selecting "Other".
                </small>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" text @click="onClose" />
            <Button label="Confirm" icon="pi pi-check" @click="onConfirm" />
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
.p-invalid {
    border-color: #e24c4c;
}

.p-error {
    color: #e24c4c;
}
</style>
