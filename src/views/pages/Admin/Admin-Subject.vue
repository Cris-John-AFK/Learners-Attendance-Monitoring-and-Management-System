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

        <Dialog v-model:visible="subjectDialog" :style="{ width: '450px' }" header="Subject Details" :modal="true" class="p-fluid">
            <div class="field">
                <label for="name">Subject Name</label>
                <InputText id="name" v-model="subject.name" required autofocus :class="{ 'p-invalid': submitted && !subject.name }" />
                <small class="p-error" v-if="submitted && !subject.name">Subject name is required.</small>
            </div>

            <div class="field">
                <label for="grade">Grade Level</label>
                <Dropdown id="grade" v-model="subject.grade" :options="grades" placeholder="Select Grade" required :class="{ 'p-invalid': submitted && !subject.grade }" />
                <small class="p-error" v-if="submitted && !subject.grade">Grade is required.</small>
            </div>

            <div class="field">
                <label for="description">Description</label>
                <Textarea id="description" v-model="subject.description" rows="3" />
            </div>

            <div class="field">
                <label for="credits">Credits</label>
                <InputNumber id="credits" v-model="subject.credits" min="1" max="10" />
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" text @click="subjectDialog = false" />
                <Button label="Save" icon="pi pi-check" @click="saveSubject" />
                <Button v-if="subject.id" label="Delete" icon="pi pi-trash" class="p-button-danger ml-2" @click="deleteSubjectDialog = true" />
            </template>
        </Dialog>

        <Dialog v-model:visible="deleteSubjectDialog" :style="{ width: '450px' }" header="Confirm" :modal="true">
            <div class="flex align-items-center justify-content-center">
                <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
                <span v-if="subject"
                    >Are you sure you want to delete <b>{{ subject.name }}</b> for <b>{{ subject.grade }}</b
                    >?</span
                >
            </div>
            <template #footer>
                <Button label="No" icon="pi pi-times" text @click="deleteSubjectDialog = false" />
                <Button label="Yes" icon="pi pi-check" text @click="deleteSubject" />
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
</style>
