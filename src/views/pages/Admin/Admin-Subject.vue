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
import { computed, onMounted, ref } from 'vue';

const toast = useToast();
const subjects = ref([]);
const grades = ref([]);
const loading = ref(true);
const subjectDialog = ref(false);
const deleteSubjectDialog = ref(false);
const selectedGrade = ref(null);

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
    return Object.fromEntries(
        filteredSubjects.value.map((subject) => [subject.id, { background: getRandomGradient() }])
    );
});


const loadGrades = async () => {
    try {
        const gradesData = await GradeService.getGrades();
        grades.value = gradesData.map((g) => g.name);
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
        hideDialog();

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
    console.log('Subject dialog state:', subjectDialog.value);
};

const editSubject = (subj) => {
    subject.value = { ...subj };
    subjectDialog.value = true;
};

const confirmDelete = (subj) => {
    subject.value = { ...subj };
    deleteSubjectDialog.value = true;
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
                    <!-- Test button for direct dialog control -->
                </div>
                <div class="nav-right">
                    <Dropdown v-model="selectedGrade" :options="grades" placeholder="Filter by grade" class="grade-filter" />
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
                <div v-for="subject in filteredSubjects" :key="subject.id" class="subject-card" :style="cardStyles[subject.id]" @click="editSubject(subject)">
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
            <div v-if="filteredSubjects.length === 0" class="empty-state">
                <p>No subjects found. Click "Add Subject" to create one.</p>
            </div>
        </div>

        <!-- Subject Dialog - Sakai Style -->
        <div v-if="subjectDialog" class="card">
            <div class="sakai-dialog-overlay" @click="hideDialog"></div>
            <div class="sakai-dialog-container">
                <div class="sakai-dialog">
                    <div class="sakai-dialog-header">
                        <h5>{{ subject.id ? 'Edit Subject' : 'Add Subject' }}</h5>
                        <Button icon="pi pi-times" class="p-button-rounded p-button-text" @click="hideDialog" />
                    </div>
                    <div class="sakai-dialog-content">
                        <div class="field grid">
                            <label for="name" class="col-12 mb-2">Name</label>
                            <div class="col-12 p-0">
                                <InputText id="name" v-model="subject.name" required placeholder="Enter subject name" :class="{ 'p-invalid': submitted && !subject.name }" />
                                <small class="p-error" v-if="submitted && !subject.name">Name is required.</small>
                            </div>
                        </div>
                        <div class="field grid">
                            <label for="grade" class="col-12 mb-2">Grade</label>
                            <div class="col-12 p-0">
                                <Dropdown id="grade" v-model="subject.grade" :options="grades" placeholder="Select a grade" :class="{ 'p-invalid': submitted && !subject.grade }" />
                                <small class="p-error" v-if="submitted && !subject.grade">Grade is required.</small>
                            </div>
                        </div>
                        <div class="field grid">
                            <label for="description" class="col-12 mb-2">Description</label>
                            <div class="col-12 p-0">
                                <Textarea id="description" v-model="subject.description" rows="3" placeholder="Add a description" />
                            </div>
                        </div>
                        <div class="field grid">
                            <label for="credits" class="col-12 mb-2">Credits</label>
                            <div class="col-12 p-0">
                                <InputNumber id="credits" v-model="subject.credits" :min="1" :max="10" placeholder="1-10" />
                            </div>
                        </div>
                    </div>
                    <div class="sakai-dialog-footer">
                        <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="hideDialog" />
                        <Button label="Save" icon="pi pi-check" class="p-button-text" @click="saveSubject" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Dialog -->
        <Dialog v-model="deleteSubjectDialog" header="Confirm" modal style="width: 450px">
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

.nav-right {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.grade-filter {
    min-width: 200px;
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
}

.subject-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
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

.dialog-content {
    padding: 0 0.25rem;
}

.field {
    margin-bottom: 1rem;
}

.field label {
    font-size: 0.875rem;
    margin-bottom: 0.375rem;
}

.field small.p-error {
    color: #f44336;
    font-size: 0.7rem;
    margin-top: 0.25rem;
    display: block;
}

/* Animation classes */
.animated {
    animation-duration: 0.4s;
    animation-fill-mode: both;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translate3d(0, -20px, 0);
    }
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

@keyframes fadeOutUp {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
        transform: translate3d(0, -20px, 0);
    }
}

.fadeInDown {
    animation-name: fadeInDown;
}

.fadeOutUp {
    animation-name: fadeOutUp;
}

/* Enhanced dialog styles */
:deep(.p-dialog) {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    transform-origin: center top;
}

:deep(.p-dialog-header) {
    padding: 0.75rem 1.25rem;
    background: #ffffff;
    border-bottom: 1px solid #f0f0f0;
}

:deep(.p-dialog-title) {
    font-size: 1rem;
    font-weight: 600;
    color: #344054;
}

:deep(.p-dialog-content) {
    padding: 1rem;
    background: #ffffff;
}

:deep(.p-dialog-footer) {
    padding: 0.75rem 1.25rem;
    background: #ffffff;
    border-top: 1px solid #f0f0f0;
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

:deep(.p-button) {
    border-radius: 4px;
    padding: 0.4rem 0.75rem;
    transition: all 0.2s ease;
    font-size: 0.85rem;
}

:deep(.p-button .p-button-icon) {
    font-size: 0.9rem;
    margin-right: 0.3rem;
}

:deep(.p-dropdown),
:deep(.p-select) {
    width: 100%;
}

/* Sakai Dialog Styles */
.sakai-dialog-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
    z-index: 1000;
}

.sakai-dialog-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1001;
}

.sakai-dialog {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    width: 500px;
    max-width: 95vw;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.sakai-dialog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f0f0f0;
}

.sakai-dialog-header h5 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #343a40;
}

.sakai-dialog-content {
    padding: 1.5rem;
    overflow-y: auto;
}

.sakai-dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border-top: 1px solid #f0f0f0;
}
</style>
