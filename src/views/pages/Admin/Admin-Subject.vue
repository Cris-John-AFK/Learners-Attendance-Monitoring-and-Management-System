<!-- eslint-disable prettier/prettier -->
<!-- eslint-disable prettier/prettier -->
<script setup>
import { GradeService } from '@/router/service/Grades';
import { SubjectService } from '@/router/service/Subjects';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onMounted, ref } from 'vue';

const toast = useToast();
const subjects = ref([]);
const grades = ref([]);
const loading = ref(true);
const subjectDialog = ref(false);
const deleteSubjectDialog = ref(false);
const selectedGrade = ref(null);
const showSubjectDetails = ref(false);
const detailsEditMode = ref(false);
const modalContainer = ref(null);

const subject = ref({
    id: null,
    name: '',
    grade: '',
    description: '',
    credits: 3
});
const submitted = ref(false);

const getRandomGradient = () => {
    const colors = ['#ff9a9e', '#fad0c4', '#fbc2eb', '#a6c1ee', '#ffdde1', '#ee9ca7', '#ff758c', '#ff7eb3', '#c3cfe2', '#d4fc79', '#96e6a1', '#84fab0', '#8fd3f4', '#a18cd1'];
    const color1 = colors[Math.floor(Math.random() * colors.length)];
    const color2 = colors[Math.floor(Math.random() * colors.length)];
    return `linear-gradient(135deg, ${color1}, ${color2})`; // Enclose in backticks
};

const filteredSubjects = computed(() => {
    return selectedGrade.value ? subjects.value.filter((s) => s.grade === selectedGrade.value) : subjects.value;
});

const cardStyles = computed(() => {
    return Object.fromEntries(filteredSubjects.value.map((subject) => [subject.id, { background: getRandomGradient() }]));
});

const loadGrades = async () => {
    try {
        const gradesData = await GradeService.getGrades();
        grades.value = gradesData.map((g) => ({
            label: g.name,
            value: g.name
        }));
    } catch (error) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load grades',
            life: 3000
        });
    }
};

const loadSubjects = async () => {
    try {
        loading.value = true;
        const data = await SubjectService.getSubjects();
        subjects.value = data;
        loading.value = false;
    } catch (error) {
        console.error('Error loading subject data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load subject data',
            life: 3000
        });
        loading.value = false;
    }
};

const saveSubject = async () => {
    submitted.value = true;

    if (!subject.value.name.trim() || !subject.value.grade) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Please enter required fields',
            life: 3000
        });
        return;
    }

    try {
        let result;

        if (subject.value.id) {
            result = await SubjectService.updateSubject(subject.value.id, subject.value);
        } else {
            result = await SubjectService.createSubject(subject.value);
        }

        // Refresh the subjects list to include the new subject
        await loadSubjects();

        if (showSubjectDetails.value) {
            detailsEditMode.value = false;
        } else {
        hideDialog();
        }

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: subject.value.id ? 'Subject Updated' : 'Subject Created',
            life: 3000
        });
    } catch (error) {
        console.error('Error saving subject:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save subject: ' + (error.message || 'Unknown error'),
            life: 3000
        });
    }
};

const deleteSubject = async () => {
    try {
        await SubjectService.deleteSubject(subject.value.id);
        await loadSubjects();
        deleteSubjectDialog.value = false;
        if (showSubjectDetails.value) {
            closeDetailsModal();
        }

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Subject Deleted',
            life: 3000
        });
    } catch (error) {
        console.error('Error deleting subject:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete subject',
            life: 3000
        });
    }
};

const hideDialog = () => {
    subjectDialog.value = false;
    submitted.value = false;
};

const openNew = () => {
    // Reset the form
    subject.value = {
        id: null,
        name: '',
        grade: selectedGrade.value || '',
        description: '',
        credits: 3
    };
    submitted.value = false;

    // Set dialog to true and add console log for debugging
    console.log('Opening subject dialog...');
    subjectDialog.value = true;

    // Reset any animations by removing and re-adding the class
    setTimeout(() => {
        const fields = document.querySelectorAll('.animated-field');
        fields.forEach((field) => {
            field.style.animation = 'none';
            field.offsetHeight; // Trigger reflow
            field.style.animation = '';
        });
    }, 50);
};

const editSubject = (subj) => {
    subject.value = { ...subj };

    // If we're in the details modal, switch to edit mode
    if (showSubjectDetails.value) {
        detailsEditMode.value = true;
    } else {
        // Otherwise open the details modal first, then enable edit mode
        openDetailsModal(subj);
        nextTick(() => {
            detailsEditMode.value = true;
        });
    }
};

const confirmDelete = (subj) => {
    subject.value = { ...subj };
    deleteSubjectDialog.value = true;
};

const openDetailsModal = (subj) => {
    // Set the current subject
    subject.value = { ...subj };

    // Show the modal with animation
    showSubjectDetails.value = true;

    // Reset edit mode
    detailsEditMode.value = false;

    // Apply the animation class
    nextTick(() => {
        if (modalContainer.value) {
            modalContainer.value.classList.add('six');
            document.body.classList.add('modal-active');
        }
    });
};

const closeDetailsModal = () => {
    // Add out class to animate closing
    if (modalContainer.value) {
        modalContainer.value.classList.add('out');
    }

    // Remove body class
    document.body.classList.remove('modal-active');

    // Reset state after animation
    setTimeout(() => {
        showSubjectDetails.value = false;
        detailsEditMode.value = false;
        if (modalContainer.value) {
            modalContainer.value.classList.remove('six', 'out');
        }
    }, 300);
};

onMounted(async () => {
    await loadGrades();
    await loadSubjects();
});
</script>

<template>
    <div class="admin-subject-wrapper">
        <div class="admin-subject-container">
            <!-- Top Section -->
            <div class="top-nav-bar">
                <div class="nav-left">
                    <h2 class="text-2xl font-semibold">Subject Management</h2>
                </div>
                <div class="nav-right">
                    <Dropdown v-model="selectedGrade" :options="grades" optionLabel="label" optionValue="value" placeholder="Filter by grade" class="grade-filter" />
                    <Button label="Add Subject" icon="pi pi-plus" class="add-button p-button-success" @click.prevent="openNew" />
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading-container">
                <ProgressSpinner />
                <p>Loading subjects...</p>
            </div>

            <!-- Cards Grid -->
            <div v-else class="cards-grid">
                <div v-for="subject in filteredSubjects" :key="subject.id" class="subject-card" :style="cardStyles[subject.id]" @click="openDetailsModal(subject)">
                    <div class="card-content">
                        <h1 class="subject-title">{{ subject.name }}</h1>
                        <div class="grade-badge">{{ subject.grade }}</div>
                        <div class="card-actions">
                            <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click.stop="editSubject(subject)" />
                            <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click.stop="confirmDelete(subject)" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredSubjects.length === 0 && !loading" class="empty-state">
                <p>No subjects found. Click "Add Subject" to create one.</p>
            </div>
        </div>

        <!-- Sketch Style Modal -->
        <div v-if="showSubjectDetails" ref="modalContainer" id="modal-container">
            <div class="modal-background" @click="closeDetailsModal">
                <div class="modal" @click.stop>
                    <div class="modal-header">
                        <h2>{{ detailsEditMode ? 'Edit Subject' : 'Subject Details' }}</h2>
                        <Button icon="pi pi-times" class="p-button-rounded p-button-text close-button" @click="closeDetailsModal" aria-label="Close" />
                    </div>

                    <!-- View Mode -->
                    <div v-if="!detailsEditMode" class="modal-content">
                        <div class="subject-details">
                            <div class="detail-row">
                                <label>Name:</label>
                                <span>{{ subject.name }}</span>
                            </div>
                            <div class="detail-row">
                                <label>Grade:</label>
                                <span>{{ subject.grade }}</span>
                        </div>
                            <div class="detail-row">
                                <label>Credits:</label>
                                <span>{{ subject.credits }}</span>
                            </div>
                            <div class="detail-row description">
                                <label>Description:</label>
                                <p>{{ subject.description || 'No description available.' }}</p>
                        </div>
                            </div>
                        <div class="modal-actions">
                            <Button label="Edit" icon="pi pi-pencil" class="p-button-primary" @click="detailsEditMode = true" />
                            <Button label="Delete" icon="pi pi-trash" class="p-button-danger" @click="confirmDelete(subject)" />
                        </div>
                    </div>

                    <!-- Edit Mode -->
                    <div v-else class="modal-content">
                        <div class="edit-form">
                            <div class="field">
                                <label for="name">Name</label>
                                <InputText id="name" v-model="subject.name" required placeholder="Enter subject name" :class="{ 'p-invalid': submitted && !subject.name }" />
                                <small class="p-error" v-if="submitted && !subject.name">Name is required.</small>
                            </div>
                            <div class="field">
                                <label for="grade">Grade</label>
                                <Dropdown id="grade" v-model="subject.grade" :options="grades" placeholder="Select a grade" :class="{ 'p-invalid': submitted && !subject.grade }" />
                                <small class="p-error" v-if="submitted && !subject.grade">Grade is required.</small>
                            </div>
                            <div class="field">
                                <label for="description">Description</label>
                                <Textarea id="description" v-model="subject.description" rows="3" placeholder="Add a description" />
                            </div>
                            <div class="field">
                                <label for="credits">Credits</label>
                                <InputNumber id="credits" v-model="subject.credits" :min="1" :max="10" placeholder="1-10" />
                            </div>
                        </div>
                        <div class="modal-actions">
                            <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="detailsEditMode = false" />
                            <Button label="Save" icon="pi pi-check" class="p-button-success" @click="saveSubject" />
                    </div>
                    </div>

                    <!-- Modal SVG for sketch animation -->
                    <svg class="modal-svg" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="none">
                        <rect x="0" y="0" fill="none" width="100%" height="100%" rx="12" ry="12"></rect>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Add/Edit Dialog (used only for adding new) -->
        <Dialog v-model:visible="subjectDialog" :header="subject.id ? 'Edit Subject' : 'Add Subject'" modal class="p-fluid" :style="{ width: '450px' }" :breakpoints="{ '960px': '75vw', '640px': '90vw' }">
            <div class="field">
                <label for="name">Name</label>
                <InputText id="name" v-model="subject.name" required autofocus :class="{ 'p-invalid': submitted && !subject.name }" />
                <small class="p-error" v-if="submitted && !subject.name">Name is required.</small>
            </div>
            <div class="field">
                <label for="grade">Grade</label>
                <Dropdown id="grade" v-model="subject.grade" :options="grades" placeholder="Select a grade" :class="{ 'p-invalid': submitted && !subject.grade }" />
                <small class="p-error" v-if="submitted && !subject.grade">Grade is required.</small>
            </div>
            <div class="field">
                <label for="description">Description</label>
                <Textarea id="description" v-model="subject.description" rows="3" />
            </div>
            <div class="field">
                <label for="credits">Credits</label>
                <InputNumber id="credits" v-model="subject.credits" :min="1" />
            </div>
            <template #footer>
                <div class="dialog-footer-buttons">
                    <Button label="Cancel" icon="pi pi-times" class="p-button-text cancel-button" @click="hideDialog" />
                    <Button label="Save" icon="pi pi-check" class="p-button-text save-button" @click="saveSubject" />
                </div>
            </template>
        </Dialog>

        <!-- Delete Dialog -->
        <Dialog v-model:visible="deleteSubjectDialog" header="Confirm" modal :style="{ width: '450px' }" :breakpoints="{ '960px': '75vw', '640px': '90vw' }">
            <div class="confirmation-content">
                <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
                <span>Are you sure you want to delete this subject?</span>
            </div>
            <template #footer>
                <Button label="No" icon="pi pi-times" class="p-button-text" @click="deleteSubjectDialog = false" />
                <Button label="Yes" icon="pi pi-check" class="p-button-danger" @click="deleteSubject()" />
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.admin-subject-container {
    padding: 1rem 2rem;
    background: #f8f9fa;
    min-height: 100vh;
}

.top-nav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.nav-left {
    display: flex;
    align-items: center;
}

.nav-right {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 2rem;
}

.subject-card {
    height: 200px;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease;
    position: relative;
}

.subject-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
    animation: cardPulse 0.6s infinite alternate;
}

.subject-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.2) 50%, rgba(255, 255, 255, 0) 100%);
    transform: translateX(-100%);
    transition: transform 0.6s;
}

.subject-card:hover::before {
    transform: translateX(100%);
    animation: shimmer 1.5s infinite;
}

@keyframes cardPulse {
    0% {
        transform: translateY(-5px) scale(1);
    }
    100% {
        transform: translateY(-5px) scale(1.03);
    }
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

.card-content {
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 1.5rem;
    position: relative;
}

.subject-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: white;
    text-align: center;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.grade-badge {
    position: absolute;
    bottom: 1rem;
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    color: white;
    font-weight: 600;
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

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: 12px;
    color: #6c757d;
}

/* Sketch Modal Styles */
#modal-container {
    position: fixed;
    display: table;
    height: 100%;
    width: 100%;
    top: 0;
    left: 0;
    transform: scale(0);
    z-index: 999;
}

#modal-container.six {
    transform: scale(1);
}

#modal-container.six .modal-background {
    background: rgba(0, 0, 0, 0);
    animation: fadeIn 0.3s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
}

#modal-container.six .modal-background .modal {
    background-color: transparent;
    animation: modalFadeIn 0.3s 0.4s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
}

#modal-container.six .modal-background .modal h2,
#modal-container.six .modal-background .modal .modal-content {
    opacity: 0;
    position: relative;
    animation: modalContentFadeIn 0.3s 0.5s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
}

#modal-container.six .modal-background .modal .modal-svg rect {
    animation:
        sketchIn 0.4s 0.2s cubic-bezier(0.165, 0.84, 0.44, 1) forwards,
        pulseBorder 2s 1s ease-in-out infinite;
}

#modal-container.six.out {
    animation: quickScaleDown 0s 0.3s linear forwards;
}

#modal-container.six.out .modal-background {
    animation: fadeOut 0.3s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
}

#modal-container.six.out .modal-background .modal {
    animation: modalFadeOut 0.3s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
}

#modal-container.six.out .modal-background .modal h2,
#modal-container.six.out .modal-background .modal .modal-content {
    animation: modalContentFadeOut 0.3s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
}

#modal-container.six.out .modal-background .modal .modal-svg rect {
    animation: sketchOut 0.3s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
}

.modal-background {
    display: table-cell;
    background: rgba(0, 0, 0, 0.8);
    text-align: center;
    vertical-align: middle;
    position: relative;
    z-index: 998;
}

.modal {
    background: white;
    padding: 30px;
    display: inline-block;
    border-radius: 12px;
    font-weight: 300;
    position: relative;
    max-width: 600px;
    width: 90%;
    text-align: left;
    z-index: 999;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: -30px -30px 1.5rem -30px;
    padding: 20px 30px;
    border-bottom: 1px solid #e9ecef;
    position: relative;
    z-index: 2;
    background-color: #ffffff;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #344054;
}

.close-button {
    z-index: 3;
    position: relative;
    cursor: pointer !important;
    transition: background-color 0.2s;
    margin-right: -0.5rem;
    margin-top: -0.5rem;
}

.close-button:hover {
    background-color: rgba(0, 0, 0, 0.1) !important;
}

.modal-content {
    position: relative;
    z-index: 1;
    margin-bottom: -10px;
}

.subject-details {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.detail-row {
    display: flex;
    flex-direction: column;
}

.detail-row label {
    font-weight: 600;
    color: #344054;
    margin-bottom: 0.25rem;
}

.detail-row span,
.detail-row p {
    color: #475467;
}

.detail-row.description {
    margin-top: 0.5rem;
}

.detail-row.description p {
    line-height: 1.5;
    white-space: pre-line;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin: 2rem -30px -30px -30px;
    padding: 20px 30px;
    border-top: 1px solid #e9ecef;
    background-color: #f8fafc;
    border-bottom-left-radius: 12px;
    border-bottom-right-radius: 12px;
}

.edit-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.edit-form .field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.edit-form label {
    font-weight: 600;
    color: #344054;
}

.modal-svg {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
    border-radius: 12px;
    z-index: 0;
    pointer-events: none;
}

.modal-svg rect {
    stroke: #3b82f6;
    stroke-width: 2px;
    stroke-dasharray: 1500;
    stroke-dashoffset: 1500;
    rx: 12px;
    ry: 12px;
}

/* Modal Animations */
@keyframes fadeIn {
    0% {
        background: rgba(0, 0, 0, 0);
    }
    100% {
        background: rgba(0, 0, 0, 0.7);
    }
}

@keyframes fadeOut {
    0% {
        background: rgba(0, 0, 0, 0.7);
    }
    100% {
        background: rgba(0, 0, 0, 0);
    }
}

@keyframes modalFadeIn {
    0% {
        background-color: transparent;
        transform: scale(0.95);
    }
    100% {
        background-color: white;
        transform: scale(1);
    }
}

@keyframes modalFadeOut {
    0% {
        background-color: white;
        transform: scale(1);
    }
    100% {
        background-color: transparent;
        transform: scale(0.95);
    }
}

@keyframes modalContentFadeIn {
    0% {
        opacity: 0;
        top: -20px;
    }
    70% {
        opacity: 1;
        top: 5px;
    }
    100% {
        opacity: 1;
        top: 0;
    }
}

@keyframes modalContentFadeOut {
    0% {
        opacity: 1;
        top: 0px;
    }
    100% {
        opacity: 0;
        top: -20px;
    }
}

@keyframes sketchIn {
    0% {
        stroke-dashoffset: 1500;
    }
    100% {
        stroke-dashoffset: 0;
    }
}

@keyframes sketchOut {
    0% {
        stroke-dashoffset: 0;
    }
    100% {
        stroke-dashoffset: 1500;
    }
}

@keyframes pulseBorder {
    0% {
        stroke: #3b82f6;
        stroke-width: 2px;
    }
    50% {
        stroke: #10b981;
        stroke-width: 3px;
    }
    100% {
        stroke: #3b82f6;
        stroke-width: 2px;
    }
}

@keyframes quickScaleDown {
    0% {
        transform: scale(1);
    }
    99.9% {
        transform: scale(1);
    }
    100% {
        transform: scale(0);
    }
}

@keyframes buttonShine {
    0% {
        left: -100%;
        top: -100%;
    }
    20%,
    100% {
        left: 100%;
        top: 100%;
    }
}

@keyframes floatingInput {
    0% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-5px);
    }
    100% {
        transform: translateY(0px);
    }
}

:deep(.p-inputtext),
:deep(.p-dropdown),
:deep(.p-dropdown-panel),
:deep(.p-textarea),
:deep(.p-inputnumber) {
    width: 100%;
    padding: 0.5rem 0.5rem;
    border: 1px solid #d0d5dd;
    border-radius: 4px;
    transition: all 0.2s ease;
    font-size: 0.875rem;
    height: auto;
    line-height: 1.5;
}

:deep(.p-dropdown-panel) {
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

/* Enhanced dialog styles */
:deep(.p-dialog) {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
}

:deep(.p-button) {
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease,
        background-color 0.2s ease !important;
}

:deep(.p-button:hover:not(.p-disabled)) {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1) !important;
}

body.modal-active {
    overflow: hidden;
}

/* Single media query for responsiveness */
@media (max-width: 768px) {
    .modal {
        width: 95%;
        padding: 20px;
    }

    :deep(.add-subject-dialog) {
        width: 95vw !important;
    }

    .animated-field {
        gap: 0.35rem;
    }
}

/* Enhanced Add Subject Dialog styles */
:deep(.add-subject-dialog) {
    box-shadow:
        0 15px 35px rgba(50, 50, 93, 0.1),
        0 5px 15px rgba(0, 0, 0, 0.07);
}

:deep(.add-subject-dialog .p-dialog-header) {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    padding: 1.5rem;
}

:deep(.add-subject-dialog .p-dialog-title) {
    font-weight: 700;
    font-size: 1.35rem;
    letter-spacing: 0.5px;
    position: relative;
    display: inline-block;
    animation: titlePulse 2s infinite alternate;
}

:deep(.add-subject-dialog .p-dialog-content) {
    padding: 2rem;
    background: white;
}

.add-subject-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.animated-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    opacity: 0;
    transform: translateY(20px);
    animation: fieldAppear 0.5s forwards;
    position: relative;
}

.animated-field:nth-child(1) {
    animation-delay: 0.1s;
}

.animated-field:nth-child(2) {
    animation-delay: 0.2s;
}

.animated-field:nth-child(3) {
    animation-delay: 0.3s;
}

.animated-field:nth-child(4) {
    animation-delay: 0.4s;
}

.animated-field label {
    font-weight: 600;
    color: #4b5563;
    font-size: 0.9rem;
    position: relative;
    display: inline-block;
}

.animated-field label::after {
    content: '';
    position: absolute;
    bottom: -4px;
    left: 0;
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
    transition: width 0.3s ease;
}

.animated-field:hover label::after {
    width: 100%;
}

:deep(.animated-field .p-inputtext:focus),
:deep(.animated-field .p-dropdown:focus),
:deep(.animated-field .p-textarea:focus),
:deep(.animated-field .p-inputnumber:focus) {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 1px rgba(139, 92, 246, 0.2);
}

:deep(.add-subject-dialog .p-dialog-footer) {
    background: #f9fafb;
    padding: 1rem 2rem;
    border-top: 1px solid #f3f4f6;
}

.dialog-footer-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

:deep(.cancel-button) {
    transition: all 0.3s ease;
    color: #6b7280;
}

:deep(.cancel-button:hover) {
    background-color: #f3f4f6 !important;
    transform: translateY(-2px);
}

:deep(.save-button) {
    background: linear-gradient(135deg, #10b981, #059669);
    border: none;
    color: white;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

:deep(.save-button:hover) {
    background: linear-gradient(135deg, #059669, #047857) !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

:deep(.save-button::after) {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(transparent, transparent, rgba(255, 255, 255, 0.2), transparent, transparent);
    transform: rotate(45deg);
    animation: buttonShine 3s infinite;
}

@keyframes fieldAppear {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes titlePulse {
    0% {
        text-shadow: 0 0 5px rgba(255, 255, 255, 0.5);
    }
    100% {
        text-shadow: 0 0 15px rgba(255, 255, 255, 0.8);
    }
}

:deep(.animated-field .p-inputtext),
:deep(.animated-field .p-dropdown),
:deep(.animated-field .p-textarea) {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.3s ease;
    animation: floatingInput 3s ease-in-out infinite;
}

:deep(.animated-field:nth-child(1) .p-inputtext),
:deep(.animated-field:nth-child(1) .p-dropdown),
:deep(.animated-field:nth-child(1) .p-textarea) {
    animation-delay: 0s;
}

:deep(.animated-field:nth-child(2) .p-inputtext),
:deep(.animated-field:nth-child(2) .p-dropdown),
:deep(.animated-field:nth-child(2) .p-textarea) {
    animation-delay: 0.5s;
}

:deep(.animated-field:nth-child(3) .p-inputtext),
:deep(.animated-field:nth-child(3) .p-dropdown),
:deep(.animated-field:nth-child(3) .p-textarea) {
    animation-delay: 1s;
}

:deep(.animated-field:nth-child(4) .p-inputtext),
:deep(.animated-field:nth-child(4) .p-dropdown),
:deep(.animated-field:nth-child(4) .p-textarea),
:deep(.animated-field:nth-child(4) .p-inputnumber) {
    animation-delay: 1.5s;
}

:deep(.animated-field .p-inputtext:focus),
:deep(.animated-field .p-dropdown:focus),
:deep(.animated-field .p-textarea:focus),
:deep(.animated-field .p-inputnumber:focus) {
    border-color: #8b5cf6;
    box-shadow: 0 0 0 1px rgba(139, 92, 246, 0.2);
    animation-play-state: paused;
}

:deep(.animated-field .p-dropdown:hover),
:deep(.animated-field .p-inputtext:hover),
:deep(.animated-field .p-textarea:hover),
:deep(.animated-field .p-inputnumber:hover) {
    border-color: #c7d2fe;
}

:deep(.animated-field .p-inputnumber) {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.3s ease;
    animation: floatingInput 3s ease-in-out infinite;
}

:deep(.animated-field .p-inputnumber-buttons-stacked .p-button.p-inputnumber-button) {
    background: linear-gradient(135deg, #c7d2fe, #a5b4fc);
    color: #4338ca;
    border: none;
}

:deep(.animated-field .p-inputnumber-buttons-stacked .p-button.p-inputnumber-button:hover) {
    background: linear-gradient(135deg, #a5b4fc, #818cf8);
}

:deep(.animated-field .p-dropdown-panel) {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

:deep(.animated-field .p-dropdown-panel .p-dropdown-items .p-dropdown-item.p-highlight) {
    background: linear-gradient(135deg, #c7d2fe, #a5b4fc);
    color: #4338ca;
}

:deep(.animated-field .p-dropdown) {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.3s ease;
    animation: floatingInput 3s ease-in-out infinite;
    display: flex;
    align-items: center;
    height: auto;
    min-height: 42px;
}

:deep(.p-dropdown-panel) {
    z-index: 9999 !important;
}

:deep(.p-dropdown .p-dropdown-trigger) {
    padding: 0 0.5rem;
    cursor: pointer;
}
</style>
