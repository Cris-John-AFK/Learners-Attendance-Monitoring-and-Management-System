<script setup>
import { GradesService } from '@/router/service/GradesService';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputSwitch from 'primevue/inputswitch';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { onMounted, ref, watch } from 'vue';

const toast = useToast();
const grades = ref([]);
const loading = ref(true);
const gradeDialog = ref(false);
const deleteGradeDialog = ref(false);
const grade = ref({
    code: '',
    name: '',
    is_active: true
});
const selectedGrades = ref(null);
const submitted = ref(false);
const filters = ref({
    global: { value: null, matchMode: 'contains' }
});

// Grade type options
const gradeTypes = ref([
    { label: 'Kinder', value: 'KINDER' },
    { label: 'Grade', value: 'GRADE' },
    { label: 'ALS', value: 'ALS' }
]);

const selectedGradeType = ref(null);
const gradeValue = ref(null);

// Watch for changes in grade type and value to update code and name
watch([selectedGradeType, gradeValue], ([newType, newValue]) => {
    if (newType && newValue !== null) {
        if (newType.value === 'KINDER') {
            grade.value.code = `K${newValue}`;
            grade.value.name = `Kinder ${newValue}`;
        } else if (newType.value === 'GRADE') {
            grade.value.code = `${newValue}`;
            grade.value.name = `Grade ${newValue}`;
        } else if (newType.value === 'ALS') {
            grade.value.code = `ALS${newValue}`;
            grade.value.name = `ALS ${newValue}`;
        }
    }
}, { immediate: true });

// Computed property for display order
const calculateDisplayOrder = (gradeType, value) => {
    if (!gradeType || value === null) return 0;

    if (gradeType === 'KINDER') {
        return value; // Kinder starts at position 1
    } else if (gradeType === 'GRADE') {
        return 2 + Number(value); // Grade 1 starts at position 3 (after Kinder)
    } else if (gradeType === 'ALS') {
        return 100 + Number(value); // ALS will always be at the end
    }
    return 0;
};

// Watch for changes to update display order
watch([selectedGradeType, gradeValue], ([newType, newValue]) => {
    if (newType && newValue !== null) {
        grade.value.display_order = calculateDisplayOrder(newType.value, newValue);
    }
});

const getGrades = async () => {
    try {
        loading.value = true;
        const data = await GradesService.getGrades();
        grades.value = data;
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load grades', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const openNew = () => {
    grade.value = {
        code: '',
        name: '',
        is_active: true
    };
    selectedGradeType.value = null;
    gradeValue.value = null;
    submitted.value = false;
    gradeDialog.value = true;
};

const editGrade = (editGrade) => {
    grade.value = { ...editGrade };
    // Parse existing grade to set form values
    if (grade.value.code.startsWith('K')) {
        selectedGradeType.value = gradeTypes.value[0];
        gradeValue.value = grade.value.code.substring(1);
    } else if (grade.value.code.startsWith('ALS')) {
        selectedGradeType.value = gradeTypes.value[2];
        gradeValue.value = grade.value.code.substring(3);
    } else {
        selectedGradeType.value = gradeTypes.value[1];
        gradeValue.value = grade.value.code;
    }
    gradeDialog.value = true;
};

const confirmDeleteGrade = (editGrade) => {
    grade.value = { ...editGrade };
    deleteGradeDialog.value = true;
};

const saveGrade = async () => {
    submitted.value = true;

    if (!grade.value.code?.trim() || !grade.value.name?.trim()) {
        return;
    }

    try {
        if (grade.value.id) {
            await GradesService.updateGrade(grade.value.id, grade.value);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Grade Updated', life: 3000 });
        } else {
            await GradesService.createGrade(grade.value);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Grade Created', life: 3000 });
        }

        await getGrades();
        hideDialog();
    } catch (error) {
        let errorMessage = 'Failed to save grade';

        if (error.response && error.response.data) {
            if (error.response.data.errors) {
                const validationErrors = Object.values(error.response.data.errors).flat().join(', ');
                errorMessage = `Validation error: ${validationErrors}`;
            } else if (error.response.data.message) {
                errorMessage = error.response.data.message;
            }
        }

        toast.add({ severity: 'error', summary: 'Error', detail: errorMessage, life: 3000 });
    }
};

const hideDialog = () => {
    gradeDialog.value = false;
    submitted.value = false;
};

const deleteGrade = async () => {
    try {
        await GradesService.deleteGrade(grade.value.id);
        grades.value = grades.value.filter(g => g.id !== grade.value.id);
        deleteGradeDialog.value = false;
        toast.add({ severity: 'success', summary: 'Success', detail: 'Grade Deleted', life: 3000 });
    } catch (error) {
        let errorMessage = 'Failed to delete grade';

        if (error.response && error.response.data && error.response.data.message) {
            errorMessage = error.response.data.message;
        }

        toast.add({ severity: 'error', summary: 'Error', detail: errorMessage, life: 3000 });
    }
};

const toggleGradeStatus = async (gradeItem) => {
    try {
        const updatedGrade = await GradesService.toggleGradeStatus(gradeItem.id);
        const index = grades.value.findIndex(g => g.id === gradeItem.id);
        if (index !== -1) {
            grades.value[index] = updatedGrade;
        }
        toast.add({ severity: 'success', summary: 'Success', detail: `Grade ${updatedGrade.is_active ? 'Activated' : 'Deactivated'}`, life: 3000 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to toggle grade status', life: 3000 });
    }
};

onMounted(() => {
    getGrades();
});
</script>

<template>
    <div class="admin-grade-wrapper">
        <!-- Light geometric background shapes -->
        <div class="background-container">
            <div class="geometric-shape circle"></div>
            <div class="geometric-shape square"></div>
            <div class="geometric-shape triangle"></div>
            <div class="geometric-shape rectangle"></div>
            <div class="geometric-shape diamond"></div>
        </div>

        <Toast />

        <div class="admin-grade-container">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="grade-title">Grade Level Management</h2>
                        <p class="grade-subtitle">Manage elementary grade levels (Kinder to Grade 6)</p>
                    </div>
                    <Button label="Add New Grade" icon="pi pi-plus" class="add-button" @click="openNew" />
                </div>

                <div class="search-container mb-4">
                    <span class="p-input-icon-left w-full">
                        <i class="pi pi-search" />
                        <InputText v-model="filters.global.value" placeholder="Search grades..." class="search-input w-full" />
                    </span>
                </div>

                <DataTable
                    v-model:filters="filters"
                    :value="grades"
                    v-model:selection="selectedGrades"
                    dataKey="id"
                    :paginator="true"
                    :rows="10"
                    filterDisplay="menu"
                    :loading="loading"
                    :rowsPerPageOptions="[5, 10, 25]"
                    paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                    currentPageReportTemplate="Showing {first} to {last} of {totalRecords} grades"
                    responsiveLayout="scroll"
                    class="grade-table"
                    stripedRows
                    :rowHover="true"
                >
                    <template #empty>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="pi pi-book"></i>
                            </div>
                            <h3>No Grades Found</h3>
                            <p>Click "Add New Grade" to create your first grade level.</p>
                            <Button label="Add New Grade" icon="pi pi-plus" class="p-button-primary" @click="openNew" />
                        </div>
                    </template>
                    <template #loading>
                        <div class="loading-state">
                            <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" />
                            <p>Loading grades...</p>
                        </div>
                    </template>
                    <Column field="code" header="Code" sortable style="min-width: 10rem">
                        <template #body="slotProps">
                            <span class="grade-code">{{ slotProps.data.code }}</span>
                        </template>
                    </Column>
                    <Column field="name" header="Name" sortable style="min-width: 14rem">
                        <template #body="slotProps">
                            <span class="grade-name">{{ slotProps.data.name }}</span>
                        </template>
                    </Column>
                    <Column field="is_active" header="Status" sortable style="min-width: 10rem">
                        <template #body="slotProps">
                            <span :class="'grade-badge status-' + (slotProps.data.is_active ? 'active' : 'inactive')">
                                {{ slotProps.data.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </template>
                    </Column>
                    <Column header="Actions" :exportable="false" style="min-width: 12rem">
                        <template #body="slotProps">
                            <div class="action-buttons">
                                <Button icon="pi pi-pencil" rounded outlined severity="primary" @click="editGrade(slotProps.data)" tooltip="Edit" tooltipOptions="{ position: 'top' }" />
                                <Button icon="pi pi-trash" rounded outlined severity="danger" @click="confirmDeleteGrade(slotProps.data)" tooltip="Delete" tooltipOptions="{ position: 'top' }" />
                                <Button
                                    :icon="slotProps.data.is_active ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                    rounded
                                    outlined
                                    :severity="slotProps.data.is_active ? 'warning' : 'success'"
                                    @click="toggleGradeStatus(slotProps.data)"
                                    :tooltip="slotProps.data.is_active ? 'Deactivate' : 'Activate'"
                                    :tooltipOptions="{ position: 'top' }"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <!-- Grade Dialog -->
        <Dialog v-model:visible="gradeDialog" :style="{ width: '500px' }" header="Grade Details" modal class="p-fluid grade-dialog">
            <div class="dialog-form-container p-4">
                <div class="field mb-4">
                    <label for="gradeType">Grade Type</label>
                    <Dropdown
                        id="gradeType"
                        v-model="selectedGradeType"
                        :options="gradeTypes"
                        optionLabel="label"
                        placeholder="Select Grade Type"
                        :class="{ 'p-invalid': submitted && !selectedGradeType }"
                    />
                    <small class="p-error" v-if="submitted && !selectedGradeType">Grade type is required.</small>
                </div>

                <div class="field mb-4" v-if="selectedGradeType">
                    <label :for="selectedGradeType.value === 'ALS' ? 'alsValue' : 'gradeValue'">
                        {{ selectedGradeType.label }} {{ selectedGradeType.value === 'ALS' ? 'Level' : 'Number' }}
                    </label>
                    <div v-if="selectedGradeType.value === 'ALS'">
                        <InputText
                            id="alsValue"
                            v-model="gradeValue"
                            placeholder="Enter ALS level"
                            :class="{ 'p-invalid': submitted && !gradeValue }"
                        />
                    </div>
                    <div v-else>
                        <InputNumber
                            id="gradeValue"
                            v-model="gradeValue"
                            :min="1"
                            :max="selectedGradeType.value === 'KINDER' ? 2 : 6"
                            placeholder="Enter number"
                            :class="{ 'p-invalid': submitted && !gradeValue }"
                        />
                    </div>
                    <small class="p-error" v-if="submitted && !gradeValue">Value is required.</small>
                    <small class="helper-text" v-if="selectedGradeType.value === 'KINDER'">Enter 1 or 2 for Kinder level</small>
                    <small class="helper-text" v-if="selectedGradeType.value === 'GRADE'">Enter 1-6 for Grade level</small>
                </div>

                <div class="field mb-4">
                    <label>Generated Code</label>
                    <InputText v-model="grade.code" disabled />
                </div>

                <div class="field mb-4">
                    <label>Generated Name</label>
                    <InputText v-model="grade.name" disabled />
                </div>

                <div class="field mb-4">
                    <div class="flex align-items-center status-toggle">
                        <label for="is_active" class="mr-3">Active Status</label>
                        <InputSwitch id="is_active" v-model="grade.is_active" />
                        <span class="status-label">{{ grade.is_active ? 'Active' : 'Inactive' }}</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" outlined @click="hideDialog" class="mr-2" />
                <Button label="Save" icon="pi pi-check" @click="saveGrade" :loading="loading" />
            </template>
        </Dialog>

        <!-- Delete Grade Dialog -->
        <Dialog v-model:visible="deleteGradeDialog" :style="{ width: '450px' }" header="Confirm Deletion" modal class="p-fluid delete-dialog">
            <div class="confirmation-content">
                <i class="pi pi-exclamation-triangle mr-3 warning-icon" />
                <div class="confirmation-message">
                    <h3>Delete Grade?</h3>
                    <p>Are you sure you want to delete <b>{{ grade.name }}</b>?</p>
                    <p class="text-sm">This action cannot be undone.</p>
                </div>
            </div>
            <template #footer>
                <Button label="No, Keep It" icon="pi pi-times" outlined @click="deleteGradeDialog = false" class="mr-2" />
                <Button label="Yes, Delete" icon="pi pi-trash" severity="danger" @click="deleteGrade" />
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.admin-grade-wrapper {
    position: relative;
    overflow: hidden;
    min-height: 100vh;
    background-color: #e0f2ff;
    border-radius: 0 0 24px 0;
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.1);
}

/* Background container for shapes */
.background-container {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
    opacity: 0.4;
    z-index: 0;
    border-radius: 0 0 24px 0;
}

/* Base styles for all geometric shapes */
.geometric-shape {
    position: absolute;
    opacity: 0.2;
    filter: blur(1px);
    z-index: 0;
}

/* Circle shape */
.circle {
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background-color: #4a87d5;
    top: -80px;
    right: -80px;
    animation: float 20s ease-in-out infinite;
}

/* Square shape */
.square {
    width: 200px;
    height: 200px;
    background-color: #6b9de8;
    bottom: 10%;
    left: -80px;
    transform: rotate(30deg);
    animation: rotate 25s linear infinite, float 18s ease-in-out infinite;
}

/* Triangle shape */
.triangle {
    width: 0;
    height: 0;
    border-left: 150px solid transparent;
    border-right: 150px solid transparent;
    border-bottom: 260px solid #5a96e3;
    top: 40%;
    right: -100px;
    opacity: 0.15;
    animation: float 22s ease-in-out infinite, opacity-pulse 15s ease-in-out infinite;
}

/* Rectangle shape */
.rectangle {
    width: 400px;
    height: 120px;
    background-color: #78a6f0;
    bottom: -50px;
    right: 20%;
    transform: rotate(-15deg);
    animation: float 24s ease-in-out infinite;
}

/* Diamond shape */
.diamond {
    width: 200px;
    height: 200px;
    background-color: #3c7dd4;
    transform: rotate(45deg);
    top: 15%;
    left: 10%;
    animation: float 23s ease-in-out infinite reverse, opacity-pulse 18s ease-in-out infinite;
}

/* Simple float animation */
@keyframes float {
    0%, 100% {
        transform: translate(0, 0) rotate(0deg);
    }
    25% {
        transform: translate(15px, 15px) rotate(2deg);
    }
    50% {
        transform: translate(5px, -10px) rotate(-2deg);
    }
    75% {
        transform: translate(-15px, 8px) rotate(1deg);
    }
}

/* Slow rotation animation */
@keyframes rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Subtle opacity animation */
@keyframes opacity-pulse {
    0%, 100% {
        opacity: 0.05;
    }
    50% {
        opacity: 0.1;
    }
}

.admin-grade-container {
    position: relative;
    z-index: 2;
    padding: 1.5rem 2.5rem;
    background: rgba(220, 236, 255, 0.85);
    backdrop-filter: blur(10px);
    min-height: 100vh;
    color: #1a365d;
    border-radius: 0 0 24px 0;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05) inset;
    animation: subtle-glow 10s ease-in-out infinite alternate;
}

@keyframes subtle-glow {
    0% {
        box-shadow: 0 0 30px 10px rgba(74, 135, 213, 0.05) inset;
    }
    50% {
        box-shadow: 0 0 40px 15px rgba(107, 157, 232, 0.08) inset;
    }
    100% {
        box-shadow: 0 0 30px 10px rgba(74, 135, 213, 0.05) inset;
    }
}

.card {
    background: rgba(255, 255, 255, 0.8);
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.4);
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.grade-title {
    color: #1a365d;
    font-size: 2rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(74, 135, 213, 0.2);
    letter-spacing: 0.5px;
}

.grade-subtitle {
    color: #4a5568;
    margin: 0.25rem 0 0 0;
    font-size: 1.1rem;
}

/* Search container */
.search-container {
    position: relative;
    margin-bottom: 1.5rem;
}

/* Add button styling */
:deep(.add-button) {
    background: linear-gradient(135deg, #4a87d5, #6b9de8) !important;
    border: none !important;
    box-shadow: 0 4px 12px rgba(74, 135, 213, 0.3) !important;
    transition: all 0.3s ease !important;
    padding: 0.75rem 1.25rem !important;
    border-radius: 12px !important;
}

:deep(.add-button:hover) {
    box-shadow: 0 6px 16px rgba(74, 135, 213, 0.5) !important;
    transform: translateY(-2px) !important;
}

/* Search input styling */
.search-input {
    background: rgba(211, 233, 255, 0.8);
    border: 1px solid rgba(74, 135, 213, 0.3);
    border-radius: 12px;
    transition: all 0.3s ease;
    padding: 0.75rem 0.75rem 0.75rem 2.5rem;
    font-size: 1rem;
}

.search-input:focus {
    box-shadow: 0 0 0 2px rgba(74, 135, 213, 0.3);
    border-color: rgba(74, 135, 213, 0.5);
    background: white;
}

:deep(.p-input-icon-left > i) {
    left: 1rem;
    color: #4a87d5;
}

/* Grade table styling */
.grade-table {
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

:deep(.p-datatable-wrapper) {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 16px;
    overflow: hidden;
}

:deep(.p-datatable-header) {
    background: rgba(74, 135, 213, 0.1);
    border: none;
}

:deep(.p-datatable-thead > tr > th) {
    background: rgba(74, 135, 213, 0.15);
    color: #1a365d;
    font-weight: 600;
    border-bottom: 2px solid rgba(74, 135, 213, 0.2);
    padding: 1rem;
    text-transform: uppercase;
    font-size: 0.9rem;
    letter-spacing: 0.05rem;
}

:deep(.p-datatable-tbody > tr) {
    background: rgba(255, 255, 255, 0.7);
    transition: all 0.2s ease;
}

:deep(.p-datatable-tbody > tr:hover) {
    background: rgba(74, 135, 213, 0.1);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

:deep(.p-datatable-tbody > tr > td) {
    padding: 1rem;
    border-bottom: 1px solid rgba(74, 135, 213, 0.1);
}

:deep(.p-datatable-tbody > tr:nth-child(even)) {
    background-color: rgba(235, 244, 254, 0.7);
}

:deep(.p-paginator) {
    background: rgba(255, 255, 255, 0.7);
    border: none;
    padding: 1rem;
    border-radius: 0 0 16px 16px;
}

/* Cell content styling */
.grade-code {
    font-weight: 600;
    font-size: 1.1rem;
    color: #2c5282;
}

.grade-name {
    font-weight: 500;
}

.grade-description {
    color: #4a5568;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.display-order {
    font-weight: 600;
    color: #4a5568;
}

/* Status badge styling */
.grade-badge {
    padding: 0.4rem 1rem;
    border-radius: 100px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
}

.grade-badge.status-active {
    background-color: rgba(72, 187, 120, 0.2);
    color: #2f855a;
}

.grade-badge.status-active::before {
    content: '';
    display: inline-block;
    width: 8px;
    height: 8px;
    background-color: #2f855a;
    border-radius: 50%;
    margin-right: 6px;
}

.grade-badge.status-inactive {
    background-color: rgba(229, 62, 62, 0.2);
    color: #c53030;
}

.grade-badge.status-inactive::before {
    content: '';
    display: inline-block;
    width: 8px;
    height: 8px;
    background-color: #c53030;
    border-radius: 50%;
    margin-right: 6px;
}

/* Action buttons styling */
.action-buttons {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.75rem;
}

:deep(.action-buttons .p-button) {
    width: 2.5rem;
    height: 2.5rem;
}

:deep(.action-buttons .p-button:hover) {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Empty state styling */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    color: #4a5568;
    text-align: center;
    gap: 1rem;
}

.empty-icon {
    background: rgba(74, 135, 213, 0.1);
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
}

.empty-icon i {
    font-size: 2.5rem;
    color: #4a87d5;
}

.empty-state h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #2d3748;
    margin: 0;
}

.empty-state p {
    font-size: 1.1rem;
    margin: 0.5rem 0 1.5rem 0;
}

/* Loading state styling */
.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem;
    color: #4a5568;
    gap: 1.5rem;
}

.loading-state p {
    font-size: 1.1rem;
}

/* Grade dialog styling */
.grade-dialog {
    border-radius: 20px;
    overflow: hidden;
}

:deep(.grade-dialog .p-dialog-header) {
    background: linear-gradient(135deg, #3c7dd4, #5a96e3);
    color: white;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding: 1.25rem 1.5rem;
}

:deep(.grade-dialog .p-dialog-title) {
    font-size: 1.5rem;
    font-weight: 600;
}

:deep(.grade-dialog .p-dialog-content) {
    background: linear-gradient(170deg, #f0f8ff 0%, #e0f2ff 100%);
    color: #1a365d;
    padding: 0;
}

.dialog-form-container {
    position: relative;
    padding: 1.5rem;
}

.dialog-particle {
    position: absolute;
    background: rgba(74, 135, 213, 0.2);
    border-radius: 50%;
    pointer-events: none;
}

.dialog-particle:nth-child(1) {
    top: 20px;
    left: 20px;
    width: 40px;
    height: 40px;
    animation: float 8s infinite ease-in-out;
}

.dialog-particle:nth-child(2) {
    bottom: 40px;
    right: 30px;
    width: 50px;
    height: 50px;
    animation: float 10s infinite ease-in-out;
}

.dialog-particle:nth-child(3) {
    top: 50%;
    left: 50%;
    width: 30px;
    height: 30px;
    animation: float 7s infinite ease-in-out;
}

/* Form field styling */
.field {
    margin-bottom: 1.5rem;
}

.field label {
    color: #1a365d;
    font-weight: 600;
    margin-bottom: 0.5rem;
    display: block;
    font-size: 1rem;
}

.helper-text {
    color: #718096;
    font-size: 0.875rem;
    margin-top: 0.25rem;
    display: block;
}

/* InputGroup styling */
:deep(.p-inputgroup-addon) {
    background: rgba(74, 135, 213, 0.2);
    border-color: rgba(74, 135, 213, 0.3);
    color: #3c7dd4;
    padding: 0 1rem;
}

:deep(.p-inputtext),
:deep(.p-textarea),
:deep(.p-inputnumber .p-inputtext) {
    background: white;
    color: #1a365d;
    border: 1px solid rgba(74, 135, 213, 0.3);
    border-radius: 8px;
    transition: all 0.3s ease;
    padding: 0.75rem 1rem;
    font-size: 1rem;
}

:deep(.p-inputtext:enabled:focus),
:deep(.p-textarea:enabled:focus),
:deep(.p-inputnumber:enabled:focus .p-inputtext) {
    border-color: #4a87d5;
    box-shadow: 0 0 0 2px rgba(74, 135, 213, 0.25);
}

:deep(.p-inputtext:enabled:hover),
:deep(.p-textarea:enabled:hover),
:deep(.p-inputnumber:enabled:hover .p-inputtext) {
    border-color: #4a87d5;
}

:deep(.p-inputtext.p-invalid),
:deep(.p-dropdown.p-invalid) {
    border-color: #e53e3e;
}

.status-toggle {
    background: rgba(255, 255, 255, 0.5);
    padding: 0.75rem 1rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
}

.status-label {
    margin-left: 0.75rem;
    font-weight: 500;
    color: #4a5568;
}

:deep(.p-inputswitch.p-inputswitch-checked .p-inputswitch-slider) {
    background: linear-gradient(to right, #4a87d5, #6b9de8);
}

:deep(.p-dialog-footer) {
    background: rgba(237, 242, 247, 0.7);
    border-top: 1px solid rgba(74, 135, 213, 0.1);
    padding: 1.25rem;
    text-align: right;
}

/* Delete dialog styling */
.delete-dialog :deep(.p-dialog-header) {
    background: linear-gradient(135deg, #e53e3e, #fc8181);
}

.confirmation-content {
    display: flex;
    align-items: flex-start;
    padding: 1.5rem 1rem 0.5rem;
}

.warning-icon {
    font-size: 2.5rem;
    color: #e53e3e;
    margin-right: 1rem;
}

.confirmation-message h3 {
    margin: 0 0 0.5rem 0;
    color: #e53e3e;
}

.confirmation-message p {
    margin: 0.25rem 0;
}

.text-sm {
    font-size: 0.875rem;
    color: #718096;
    margin-top: 0.75rem;
}

:deep(.p-button) {
    border-radius: 8px;
}

/* Media query for mobile devices */
@media (max-width: 768px) {
    .admin-grade-container {
        padding: 1rem;
    }

    .card {
        padding: 1.25rem;
    }

    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .grade-title {
        font-size: 1.5rem;
    }

    .action-buttons {
        gap: 0.25rem;
    }

    :deep(.action-buttons .p-button) {
        width: 2rem;
        height: 2rem;
    }

    :deep(.p-datatable-tbody > tr > td) {
        padding: 0.75rem 0.5rem;
    }
}

/* Add these new styles */
:deep(.p-dropdown) {
    width: 100%;
    background: white;
    border: 1px solid rgba(74, 135, 213, 0.3);
    border-radius: 8px;
    transition: all 0.3s ease;
}

:deep(.p-dropdown:hover) {
    border-color: #4a87d5;
}

:deep(.p-dropdown.p-focus) {
    border-color: #4a87d5;
    box-shadow: 0 0 0 2px rgba(74, 135, 213, 0.25);
}

:deep(.p-dropdown-panel) {
    background: white;
    border: none;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

:deep(.p-dropdown-item) {
    padding: 0.75rem 1rem;
    color: #1a365d;
    transition: all 0.2s ease;
}

:deep(.p-dropdown-item:hover) {
    background: rgba(74, 135, 213, 0.1);
}

:deep(.p-dropdown-item.p-highlight) {
    background: rgba(74, 135, 213, 0.2);
    color: #2c5282;
}

/* Style for disabled inputs */
:deep(.p-inputtext:disabled) {
    background: rgba(74, 135, 213, 0.05);
    color: #4a5568;
    border: 1px solid rgba(74, 135, 213, 0.2);
    cursor: not-allowed;
}
</style>
