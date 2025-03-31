<script setup>
import { GradesService } from '@/router/service/GradesService';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputSwitch from 'primevue/inputswitch';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
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

        <div class="admin-grade-container">
            <!-- Top Section -->
            <div class="top-nav-bar">
                <div class="nav-left">
                    <h2 class="text-2xl font-semibold">Grade Level Management</h2>
                </div>
                <div class="search-container">
                    <div class="search-input-wrapper">
                        <i class="pi pi-search search-icon"></i>
                        <input type="text" placeholder="Search grades..." class="search-input" v-model="filters.global.value" />
                        <button v-if="filters.global.value" class="clear-search-btn" @click="filters.global.value = ''">
                            <i class="pi pi-times"></i>
                        </button>
                    </div>
                </div>
                <div class="nav-right">
                    <Button label="Add New Grade" icon="pi pi-plus" class="add-button p-button-success" @click.prevent="openNew" />
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading-container">
                <ProgressSpinner />
                <p>Loading grades...</p>
            </div>

            <!-- Cards Grid -->
            <div v-else class="cards-grid">
                <div v-for="grade in grades" :key="grade.id" class="subject-card" :style="{ background: 'linear-gradient(135deg, rgba(211, 233, 255, 0.9), rgba(233, 244, 255, 0.9))' }">
                    <!-- Floating symbols -->
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>

                    <div class="card-content">
                        <h1 class="subject-title">{{ grade.name }}</h1>
                        <div class="card-actions">
                            <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click.stop="editGrade(grade)" />
                            <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click.stop="confirmDeleteGrade(grade)" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="grades.length === 0 && !loading" class="empty-state">
                <p>No grades found. Click "Add New Grade" to create one.</p>
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

.nav-right {
    display: flex;
    align-items: center;
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

.top-nav-bar {
    border-bottom: 1px solid rgba(74, 135, 213, 0.2);
    margin-bottom: 2.5rem;
    padding-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.top-nav-bar .nav-left h2 {
    color: #1a365d;
    font-size: 2rem;
    font-weight: 600;
    margin: 0;
    text-shadow: 0 2px 6px rgba(74, 135, 213, 0.3);
    letter-spacing: 0.5px;
}

.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: #1a365d;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2.5rem;
    padding: 0.5rem;
}

.subject-card {
    height: 220px;
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    transition: all 0.4s ease;
    position: relative;
    border: 1px solid rgba(74, 135, 213, 0.3);
}

.subject-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15), 0 0 25px rgba(74, 135, 213, 0.4);
    border: 1px solid rgba(74, 135, 213, 0.5);
}

.subject-card .symbol {
    position: absolute;
    color: rgba(26, 54, 93, 0.5);
    font-family: 'Courier New', monospace;
    pointer-events: none;
    z-index: 1;
    animation: float-symbol 8s linear infinite;
    font-weight: bold;
}

.subject-card:nth-child(3n) .symbol {
    animation-duration: 10s;
}

.subject-card:nth-child(3n+1) .symbol {
    animation-duration: 7s;
}

.subject-card .symbol:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
.subject-card .symbol:nth-child(2) { top: 30%; left: 80%; animation-delay: 1s; }
.subject-card .symbol:nth-child(3) { top: 70%; left: 30%; animation-delay: 2s; }
.subject-card .symbol:nth-child(4) { top: 60%; left: 70%; animation-delay: 3s; }
.subject-card .symbol:nth-child(5) { top: 20%; left: 50%; animation-delay: 4s; }

/* Math symbol content variations */
.subject-card:nth-child(7n) .symbol:nth-child(1)::after { content: "K"; font-size: 18px; }
.subject-card:nth-child(7n) .symbol:nth-child(2)::after { content: "1"; font-size: 20px; }
.subject-card:nth-child(7n) .symbol:nth-child(3)::after { content: "2"; font-size: 24px; }
.subject-card:nth-child(7n) .symbol:nth-child(4)::after { content: "3"; font-size: 20px; }
.subject-card:nth-child(7n) .symbol:nth-child(5)::after { content: "4"; font-size: 18px; }

.subject-card:nth-child(7n+1) .symbol:nth-child(1)::after { content: "5"; font-size: 16px; }
.subject-card:nth-child(7n+1) .symbol:nth-child(2)::after { content: "6"; font-size: 16px; }
.subject-card:nth-child(7n+1) .symbol:nth-child(3)::after { content: "K1"; font-size: 14px; }
.subject-card:nth-child(7n+1) .symbol:nth-child(4)::after { content: "K2"; font-size: 16px; }
.subject-card:nth-child(7n+1) .symbol:nth-child(5)::after { content: "G1"; font-size: 16px; }

@keyframes float-symbol {
    0% {
        transform: translateY(0) translateX(0) rotate(0deg);
        opacity: 0;
    }
    20% {
        opacity: 1;
    }
    80% {
        opacity: 1;
    }
    100% {
        transform: translateY(-100px) translateX(20px) rotate(360deg);
        opacity: 0;
    }
}

.card-content {
    position: relative;
    z-index: 2;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 1.5rem;
}

.subject-title {
    color: #1a365d;
    text-shadow: 0 2px 6px rgba(74, 135, 213, 0.4);
    font-size: 1.75rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 0.5rem;
}

.card-actions {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: flex;
    gap: 0.25rem;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.subject-card:hover .card-actions {
    opacity: 1;
}

.search-container {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 0 1.5rem;
}

.search-input-wrapper {
    position: relative;
    width: 100%;
    max-width: 500px;
    background: rgba(211, 233, 255, 0.8);
    border-radius: 10px;
    border: 1px solid rgba(74, 135, 213, 0.3);
    overflow: hidden;
    display: flex;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.search-input-wrapper:focus-within {
    border-color: rgba(74, 135, 213, 0.6);
    box-shadow: 0 6px 16px rgba(74, 135, 213, 0.2);
}

.search-input {
    flex: 1;
    background: transparent;
    border: none;
    height: 42px;
    padding: 0 1rem;
    color: #1a365d;
    font-size: 0.95rem;
    width: 100%;
}

.search-input::placeholder {
    color: rgba(26, 54, 93, 0.6);
}

.search-input:focus {
    outline: none;
}

.search-icon {
    color: rgba(26, 54, 93, 0.6);
    margin-left: 1rem;
}

.clear-search-btn {
    background: transparent;
    border: none;
    color: rgba(26, 54, 93, 0.6);
    cursor: pointer;
    margin-right: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.clear-search-btn:hover {
    color: #1a365d;
    background: rgba(74, 135, 213, 0.1);
}

:deep(.add-button) {
    border-radius: 8px !important;
    background: linear-gradient(135deg, #4a87d5, #6b9de8) !important;
    border: none !important;
    box-shadow: 0 4px 12px rgba(74, 135, 213, 0.3) !important;
    transition: all 0.3s ease !important;
}

:deep(.add-button:hover) {
    box-shadow: 0 6px 16px rgba(74, 135, 213, 0.5) !important;
    transform: translateY(-2px) !important;
}

/* Keep existing dialog styles */
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

    .header-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .grade-cards-container {
        grid-template-columns: 1fr;
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
