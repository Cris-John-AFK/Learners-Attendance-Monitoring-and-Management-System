<template>
    <Dialog 
        v-model:visible="visible" 
        modal 
        :header="dialogTitle" 
        :style="{ width: '90vw', maxWidth: '1200px' }" 
        @hide="onClose"
        class="attendance-edit-dialog"
    >
        <!-- Filters Section -->
        <div class="filters-section">
            <div class="filters-grid">
                <!-- Status Filter -->
                <div class="filter-group">
                    <label class="filter-label">
                        <i class="pi pi-filter"></i>
                        Filter by Status
                    </label>
                    <Dropdown
                        v-model="statusFilter"
                        :options="statusFilterOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="All Statuses"
                        class="filter-dropdown"
                        @change="onStatusFilterChange"
                    />
                </div>

                <!-- Reason Filter -->
                <div class="filter-group">
                    <label class="filter-label">
                        <i class="pi pi-list"></i>
                        Filter by Reason
                    </label>
                    <Dropdown
                        v-model="reasonFilter"
                        :options="reasonFilterOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="All Reasons"
                        class="filter-dropdown"
                        :disabled="!statusFilter || (statusFilter !== 'Late' && statusFilter !== 'Excused')"
                        @change="applyFilters"
                    />
                </div>

                <!-- Search Filter -->
                <div class="filter-group">
                    <label class="filter-label">
                        <i class="pi pi-search"></i>
                        Search Student
                    </label>
                    <InputText
                        v-model="searchFilter"
                        placeholder="Search by name or ID..."
                        class="filter-input"
                        @input="applyFilters"
                    />
                </div>

                <!-- Clear Filters -->
                <div class="filter-group">
                    <Button
                        label="Clear Filters"
                        icon="pi pi-times"
                        class="clear-filters-btn"
                        @click="clearFilters"
                        :disabled="!hasActiveFilters"
                    />
                </div>
            </div>

            <!-- Active Filters Display -->
            <div v-if="hasActiveFilters" class="active-filters">
                <span class="active-filters-label">Active Filters:</span>
                <Tag v-if="statusFilter" :value="`Status: ${statusFilter}`" severity="info" class="filter-tag" />
                <Tag v-if="reasonFilter" :value="`Reason: ${getReasonName(reasonFilter)}`" severity="info" class="filter-tag" />
                <Tag v-if="searchFilter" :value="`Search: ${searchFilter}`" severity="info" class="filter-tag" />
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions-section">
            <div class="bulk-actions-header">
                <span class="selection-count">{{ selectedStudents.length }} student(s) selected</span>
                <div class="bulk-controls">
                    <Dropdown
                        v-model="bulkStatus"
                        :options="statusOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select Status"
                        class="bulk-dropdown"
                    />
                    <Button
                        label="Apply to Selected"
                        icon="pi pi-check"
                        class="apply-bulk-btn"
                        :disabled="selectedStudents.length === 0 || !bulkStatus"
                        @click="applyBulkStatus"
                    />
                </div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="students-table-section">
            <DataTable
                v-model:selection="selectedStudents"
                :value="filteredStudents"
                dataKey="student_id"
                :paginator="true"
                :rows="10"
                :rowsPerPageOptions="[5, 10, 20, 50]"
                class="students-table"
                :loading="loading"
                selectionMode="multiple"
            >
                <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
                
                <Column field="student_id" header="Student ID" sortable>
                    <template #body="{ data }">
                        <span class="student-id">{{ data.student_id }}</span>
                    </template>
                </Column>

                <Column field="name" header="Student Name" sortable>
                    <template #body="{ data }">
                        <div class="student-info">
                            <div class="student-avatar">
                                {{ getStudentInitials(data.name) }}
                            </div>
                            <span class="student-name">{{ data.name }}</span>
                        </div>
                    </template>
                </Column>

                <Column field="current_status" header="Current Status" sortable>
                    <template #body="{ data }">
                        <Tag 
                            :value="data.current_status" 
                            :severity="getStatusSeverity(data.current_status)"
                            class="status-tag"
                        />
                    </template>
                </Column>

                <Column field="reason" header="Reason">
                    <template #body="{ data }">
                        <span v-if="data.reason" class="reason-text">{{ data.reason }}</span>
                        <span v-else class="no-reason">No reason</span>
                    </template>
                </Column>

                <Column header="Update Status">
                    <template #body="{ data }">
                        <Dropdown
                            v-model="data.new_status"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Select Status"
                            class="status-update-dropdown"
                            @change="onStatusChange(data)"
                        />
                    </template>
                </Column>
            </DataTable>
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
                    label="Save Changes" 
                    icon="pi pi-save" 
                    class="save-btn"
                    :disabled="!hasChanges"
                    @click="saveChanges" 
                />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import axios from 'axios';

const props = defineProps({
    modelValue: Boolean,
    sessionData: Object,
    subjectName: String,
    sectionName: String
});

const emit = defineEmits(['update:modelValue', 'save', 'close']);

// Reactive data
const visible = computed({
    get() { return props.modelValue; },
    set(value) { emit('update:modelValue', value); }
});

const dialogTitle = computed(() => {
    return `Edit Attendance - ${props.subjectName} (${props.sectionName})`;
});

const students = ref([]);
const filteredStudents = ref([]);
const selectedStudents = ref([]);
const loading = ref(false);

// Filter states
const statusFilter = ref(null);
const reasonFilter = ref(null);
const searchFilter = ref('');
const bulkStatus = ref(null);

// Filter options
const statusFilterOptions = ref([
    { label: 'All Statuses', value: null },
    { label: 'Present', value: 'Present' },
    { label: 'Absent', value: 'Absent' },
    { label: 'Late', value: 'Late' },
    { label: 'Excused', value: 'Excused' }
]);

const reasonFilterOptions = ref([
    { label: 'All Reasons', value: null }
]);

const statusOptions = [
    { label: 'Present', value: 'Present' },
    { label: 'Absent', value: 'Absent' },
    { label: 'Late', value: 'Late' },
    { label: 'Excused', value: 'Excused' }
];

// Computed properties
const hasActiveFilters = computed(() => {
    return statusFilter.value || reasonFilter.value || searchFilter.value;
});

const hasChanges = computed(() => {
    return students.value.some(student => 
        student.new_status && student.new_status !== student.current_status
    );
});

// Methods
const loadReasons = async (statusType) => {
    try {
        const response = await axios.get(`http://localhost:8000/api/attendance/reasons/${statusType.toLowerCase()}`);
        if (response.data.success) {
            const reasons = response.data.reasons.map(reason => ({
                label: reason.reason_name,
                value: reason.id
            }));
            reasonFilterOptions.value = [
                { label: 'All Reasons', value: null },
                ...reasons
            ];
        }
    } catch (error) {
        console.error('Failed to load reasons:', error);
    }
};

const onStatusFilterChange = async () => {
    if (statusFilter.value === 'Late' || statusFilter.value === 'Excused') {
        await loadReasons(statusFilter.value);
    } else {
        reasonFilter.value = null;
        reasonFilterOptions.value = [{ label: 'All Reasons', value: null }];
    }
    applyFilters();
};

const applyFilters = () => {
    let filtered = [...students.value];

    // Apply status filter
    if (statusFilter.value) {
        filtered = filtered.filter(student => student.current_status === statusFilter.value);
    }

    // Apply reason filter
    if (reasonFilter.value) {
        filtered = filtered.filter(student => student.reason_id === reasonFilter.value);
    }

    // Apply search filter
    if (searchFilter.value) {
        const search = searchFilter.value.toLowerCase();
        filtered = filtered.filter(student => 
            student.name.toLowerCase().includes(search) ||
            student.student_id.toLowerCase().includes(search)
        );
    }

    filteredStudents.value = filtered;
};

const clearFilters = () => {
    statusFilter.value = null;
    reasonFilter.value = null;
    searchFilter.value = '';
    reasonFilterOptions.value = [{ label: 'All Reasons', value: null }];
    applyFilters();
};

const getReasonName = (reasonId) => {
    const reason = reasonFilterOptions.value.find(r => r.value === reasonId);
    return reason ? reason.label : 'Unknown';
};

const getStudentInitials = (name) => {
    return name.split(' ').map(n => n.charAt(0)).join('').toUpperCase();
};

const getStatusSeverity = (status) => {
    const severityMap = {
        'Present': 'success',
        'Absent': 'danger',
        'Late': 'warning',
        'Excused': 'info'
    };
    return severityMap[status] || 'secondary';
};

const onStatusChange = (student) => {
    // Handle status change logic here
    console.log('Status changed for student:', student);
};

const applyBulkStatus = () => {
    selectedStudents.value.forEach(student => {
        student.new_status = bulkStatus.value;
    });
};

const saveChanges = () => {
    const changes = students.value
        .filter(student => student.new_status && student.new_status !== student.current_status)
        .map(student => ({
            student_id: student.student_id,
            old_status: student.current_status,
            new_status: student.new_status
        }));
    
    emit('save', changes);
};

const onClose = () => {
    emit('close');
};

// Load students when dialog opens
watch(visible, (newValue) => {
    if (newValue && props.sessionData) {
        loadStudents();
    }
});

const loadStudents = () => {
    // Mock data - replace with actual API call
    students.value = [
        { student_id: 'NCS-2025-00017', name: 'Angela Aquino', current_status: 'Present', reason: null, reason_id: null, new_status: null },
        { student_id: 'NCS-2025-00023', name: 'Carlos Gomez', current_status: 'Present', reason: null, reason_id: null, new_status: null },
        { student_id: 'NCS-2025-00011', name: 'Christian Santos', current_status: 'Present', reason: null, reason_id: null, new_status: null },
        // Add more mock data as needed
    ];
    applyFilters();
};
</script>

<style scoped>
/* Dialog Styling */
.attendance-edit-dialog :deep(.p-dialog-header) {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem;
}

.attendance-edit-dialog :deep(.p-dialog-content) {
    padding: 0;
}

/* Filters Section */
.filters-section {
    padding: 1.5rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
}

.filter-label i {
    color: #6366f1;
}

.filter-dropdown,
.filter-input {
    width: 100%;
    border-radius: 6px;
}

.clear-filters-btn {
    margin-top: 1.5rem;
    background: #ef4444;
    border-color: #ef4444;
    color: white;
}

.clear-filters-btn:hover:not(:disabled) {
    background: #dc2626;
    border-color: #dc2626;
}

.clear-filters-btn:disabled {
    background: #d1d5db;
    border-color: #d1d5db;
    color: #9ca3af;
}

/* Active Filters */
.active-filters {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    padding-top: 1rem;
    border-top: 1px solid #e2e8f0;
}

.active-filters-label {
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
}

.filter-tag {
    background: #dbeafe;
    color: #1e40af;
}

/* Bulk Actions */
.bulk-actions-section {
    padding: 1rem 1.5rem;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
}

.bulk-actions-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.selection-count {
    font-weight: 600;
    color: #374151;
}

.bulk-controls {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.bulk-dropdown {
    min-width: 150px;
}

.apply-bulk-btn {
    background: #10b981;
    border-color: #10b981;
    color: white;
}

.apply-bulk-btn:hover:not(:disabled) {
    background: #059669;
    border-color: #059669;
}

/* Students Table */
.students-table-section {
    padding: 1.5rem;
}

.students-table {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.student-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.student-avatar {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.75rem;
    font-weight: 600;
}

.student-name {
    font-weight: 500;
    color: #374151;
}

.student-id {
    font-family: monospace;
    font-size: 0.875rem;
    color: #6b7280;
}

.status-tag {
    font-weight: 500;
}

.reason-text {
    color: #374151;
    font-size: 0.875rem;
}

.no-reason {
    color: #9ca3af;
    font-style: italic;
    font-size: 0.875rem;
}

.status-update-dropdown {
    min-width: 120px;
}

/* Footer */
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1.5rem;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}

.cancel-btn {
    background: transparent;
    color: #6b7280;
    border: 2px solid #e5e7eb;
}

.cancel-btn:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
    color: #374151;
}

.save-btn {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
}

.save-btn:hover:not(:disabled) {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
}

.save-btn:disabled {
    background: #d1d5db;
    color: #9ca3af;
}
</style>
