<script setup>
import SakaiCard from '@/components/SakaiCard.vue';
import { GradeService } from '@/router/service/Grades';
import { SubjectService } from '@/router/service/Subjects';
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

    return `linear-gradient(135deg, ${color1}, ${color2})`;
};

const filteredSubjects = computed(() => {
    if (!selectedGrade.value) {
        const uniqueSubjects = [];
        const subjectNames = new Set();

        for (const subj of subjects.value) {
            if (!subjectNames.has(subj.name)) {
                subjectNames.add(subj.name);
                uniqueSubjects.push(subj);
            }
        }

        return uniqueSubjects;
    }

    return subjects.value.filter((s) => s.grade === selectedGrade.value);
});

const cardStyles = computed(() =>
    filteredSubjects.value.map(() => ({
        background: getRandomGradient()
    }))
);

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
        if (subject.value.id) {
            await SubjectService.updateSubject(subject.value.id, subject.value);
        } else {
            await SubjectService.createSubject(subject.value);
        }

        await loadSubjects();

        subjectDialog.value = false;
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
            detail: 'Failed to save subject',
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

const openNew = () => {
    subject.value = {
        id: null,
        name: '',
        grade: selectedGrade.value || '',
        description: '',
        credits: 3
    };
    subjectDialog.value = true;
};

const editSubject = (subj) => {
    subject.value = { ...subj };
    subjectDialog.value = true;
};

onMounted(async () => {
    await loadGrades();
    await loadSubjects();
});
</script>

<template>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold">Subject Management</h2>
            <div class="flex gap-3">
                <Dropdown v-model="selectedGrade" :options="grades" placeholder="Filter by grade" class="w-56" />
                <Button label="Add Subject" icon="pi pi-plus" class="p-button-success" @click="openNew" />
            </div>
        </div>

        <div class="card-container">
            <SakaiCard v-for="(subject, index) in filteredSubjects" :key="index" class="custom-card" :style="cardStyles[index]" @click="editSubject(subject)">
                <div class="card-header">
                    <h1 class="subject-name">{{ subject.name }}</h1>
                    <p class="grade-info">{{ subject.grade }}</p>
                </div>
            </SakaiCard>
        </div>

        <Dialog v-model:visible="subjectDialog" :style="{ width: '500px' }" header="Subject Details" :modal="true" class="p-fluid subject-dialog">
            <div class="field mb-4">
                <label for="name" class="font-medium mb-2 block">Subject Name</label>
                <InputText id="name" v-model="subject.name" required autofocus :class="{ 'p-invalid': submitted && !subject.name }" placeholder="Enter subject name" class="w-full p-inputtext-lg" />
                <small class="p-error" v-if="submitted && !subject.name">Subject name is required.</small>
            </div>

            <div class="field mb-4">
                <label for="grade" class="font-medium mb-2 block">Grade Level</label>
                <Dropdown id="grade" v-model="subject.grade" :options="grades" placeholder="Select Grade" required :class="{ 'p-invalid': submitted && !subject.grade }" class="w-full p-inputtext-lg" />
                <small class="p-error" v-if="submitted && !subject.grade">Grade is required.</small>
            </div>

            <div class="field mb-4">
                <label for="description" class="font-medium mb-2 block">Description</label>
                <Textarea id="description" v-model="subject.description" rows="3" placeholder="Brief description of the subject" class="w-full" />
            </div>

            <div class="field mb-4">
                <label for="credits" class="font-medium mb-2 block">Credits</label>
                <InputNumber
                    id="credits"
                    v-model="subject.credits"
                    min="1"
                    max="10"
                    showButtons
                    buttonLayout="horizontal"
                    decrementButtonClass="p-button-secondary"
                    incrementButtonClass="p-button-secondary"
                    incrementButtonIcon="pi pi-plus"
                    decrementButtonIcon="pi pi-minus"
                    class="w-full p-inputtext-lg"
                />
            </div>

            <template #footer>
                <div class="flex justify-content-between w-full">
                    <Button v-if="subject.id" label="Delete" icon="pi pi-trash" class="p-button-danger p-button-outlined" @click="deleteSubjectDialog = true" />
                    <div class="flex gap-2">
                        <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="subjectDialog = false" />
                        <Button label="Save" icon="pi pi-check" class="p-button-primary" @click="saveSubject" />
                    </div>
                </div>
            </template>
        </Dialog>

        <Dialog v-model:visible="deleteSubjectDialog" :style="{ width: '450px' }" header="Confirm Deletion" :modal="true" class="delete-dialog">
            <div class="flex align-items-center justify-content-center py-4">
                <i class="pi pi-exclamation-triangle mr-3 text-yellow-500" style="font-size: 2rem" />
                <span class="text-lg">
                    Are you sure you want to delete <span class="font-bold">{{ subject.name }}</span> for <span class="font-bold">{{ subject.grade }}</span
                    >?
                </span>
            </div>
            <p class="text-center text-gray-600 mt-2">This action cannot be undone.</p>
            <template #footer>
                <div class="flex justify-content-center gap-3 w-full">
                    <Button label="Cancel" icon="pi pi-times" class="p-button-outlined" @click="deleteSubjectDialog = false" />
                    <Button label="Delete" icon="pi pi-check" class="p-button-danger" @click="deleteSubject" />
                </div>
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.card-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.custom-card {
    width: 200px;
    height: 250px;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    cursor: pointer;
    text-align: center;
    background: #f0f0f0;
    transition:
        transform 0.2s,
        box-shadow 0.2s;
    color: white;
    font-weight: bold;
    position: relative;
}

.custom-card:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.subject-name {
    margin: 0;
    text-align: center;
    word-break: break-word;
    overflow-wrap: break-word;
    white-space: pre-wrap;
    font-size: 25px;
}

.grade-info {
    position: absolute;
    bottom: 10px;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 14px;
    opacity: 0.8;
}

.card-header {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 100%;
    padding: 20px;
}

:deep(.subject-dialog) {
    border-radius: 12px;
    overflow: hidden;
}

:deep(.subject-dialog .p-dialog-header) {
    background-color: #f8f9fa;
    padding: 1.5rem;
    border-bottom: 1px solid #e9ecef;
}

:deep(.subject-dialog .p-dialog-content) {
    padding: 1.5rem;
}

:deep(.subject-dialog .p-dialog-footer) {
    padding: 1.25rem 1.5rem;
    background-color: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

:deep(.subject-dialog input:focus),
:deep(.subject-dialog textarea:focus),
:deep(.subject-dialog .p-dropdown:focus) {
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
}

:deep(.subject-dialog .p-inputtext),
:deep(.subject-dialog .p-dropdown) {
    border-radius: 8px;
}

:deep(.delete-dialog .p-dialog-header) {
    background-color: #fee2e2;
    color: #b91c1c;
}

:deep(.delete-dialog .p-dialog-header-close-icon) {
    color: #b91c1c;
}

/* Make inputs and dropdowns consistent height */
:deep(.p-inputtext-lg) {
    height: 48px;
}

/* Ensure dropdown has same height as inputs */
:deep(.p-dropdown) {
    height: 48px;
    display: flex;
    align-items: center;
}

/* Style for the form field labels */
.field .font-medium {
    font-size: 0.95rem;
    color: #495057;
}

/* Add some animation to the modals */
:deep(.p-dialog) {
    transform: translateY(20px);
    opacity: 0;
    transition:
        transform 0.3s,
        opacity 0.3s;
}

:deep(.p-dialog-visible) {
    transform: translateY(0);
    opacity: 1;
}
</style>
