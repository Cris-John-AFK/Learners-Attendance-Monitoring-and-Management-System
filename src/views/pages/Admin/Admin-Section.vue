<script setup>
import SakaiCard from '@/components/SakaiCard.vue';
import { GradeService } from '@/router/service/Grades';
import { AttendanceService } from '@/router/service/Students';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';
const toast = useToast();
const grades = ref([]);
const selectedGrade = ref(null);
const selectedGradeId = ref(null);
const sections = ref([]);
const sectionDialog = ref(false);
const deleteSectionDialog = ref(false);
const section = ref({
    name: '',
    capacity: 40,
    adviser: '',
    room: ''
});
const submitted = ref(false);
const loading = ref(false);
const showModal = ref(false);
const showCreateForm = ref(false);
const showEditForm = ref(false);
const selectedSection = ref(null);
const studentsInSection = ref([]);

// Load grades from centralized service
const loadGrades = async () => {
    try {
        loading.value = true;
        const gradesData = await GradeService.getGrades();
        grades.value = gradesData;
        loading.value = false;
        console.log('Loaded grades:', grades.value);
    } catch (error) {
        console.error('Error loading grades:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load grade data',
            life: 3000
        });
        loading.value = false;
    }
};

// Load sections for a specific grade using centralized service
const loadSections = async (gradeId) => {
    if (!gradeId) return;

    try {
        loading.value = true;
        console.log('Loading sections for grade ID:', gradeId);
        const sectionsData = await GradeService.getSectionsByGrade(gradeId);
        sections.value = sectionsData;
        loading.value = false;
        console.log('Loaded sections:', sections.value);
    } catch (error) {
        console.error('Error loading sections:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load section data',
            life: 3000
        });
        loading.value = false;
    }
};

// Load students for a specific section
const loadStudentsForSection = async (sectionName) => {
    if (!selectedGradeId.value || !sectionName) return;

    try {
        loading.value = true;
        console.log('Fetching students for grade:', selectedGradeId.value, 'section:', sectionName);

        // Convert selected grade ID to a number for proper filtering
        let gradeLevel;
        if (selectedGradeId.value === 'K') {
            gradeLevel = 0; // Kinder is usually represented as 0
        } else {
            gradeLevel = parseInt(selectedGradeId.value);
        }

        // Get all students from AttendanceService directly
        const allStudents = await AttendanceService.getData();
        console.log('All students from AttendanceService:', allStudents);

        // Filter students by grade level and section
        studentsInSection.value = allStudents.filter((student) => student.gradeLevel === gradeLevel && student.section === sectionName);

        console.log('Filtered students:', studentsInSection.value);
        loading.value = false;
    } catch (error) {
        console.error('Error loading students:', error);
        studentsInSection.value = [];
        loading.value = false;
    }
};

const getRandomGradient = () => {
    const colors = ['#ff9a9e', '#fad0c4', '#fbc2eb', '#a6c1ee', '#ffdde1', '#ee9ca7', '#ff758c', '#ff7eb3', '#c3cfe2', '#d4fc79', '#96e6a1', '#84fab0', '#8fd3f4', '#a18cd1'];

    const color1 = colors[Math.floor(Math.random() * colors.length)];
    const color2 = colors[Math.floor(Math.random() * colors.length)];

    return `linear-gradient(135deg, ${color1}, ${color2})`;
};

const cardStyles = computed(() =>
    grades.value.map(() => ({
        background: getRandomGradient()
    }))
);

// Open Sections Modal for a selected grade
const openSectionsModal = async (grade) => {
    try {
        const gradeObj = grades.value.find((g) => g.name === grade);

        if (gradeObj) {
            selectedGrade.value = gradeObj.name;
            selectedGradeId.value = gradeObj.id;

            await loadSections(gradeObj.id);

            showModal.value = true;
            selectedSection.value = null;
        } else {
            console.error('Grade not found:', grade);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Grade not found',
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error opening sections modal:', error);
    }
};

// Select a section to view its students
const selectSection = async (sectionName) => {
    selectedSection.value = sectionName;
    await loadStudentsForSection(sectionName);
};

// Create a new section
const createSection = async () => {
    if (!section.value.name || !selectedGradeId.value) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Please enter a section name',
            life: 3000
        });
        return;
    }

    // Add basic validation for section names
    if (section.value.name.length < 3) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Section name should be at least 3 characters long',
            life: 3000
        });
        return;
    }

    try {
        await GradeService.createSection(selectedGradeId.value, section.value);
        await loadSections(selectedGradeId.value);

        showCreateForm.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Section Created',
            life: 3000
        });
    } catch (error) {
        console.error('Error creating section:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to create section',
            life: 3000
        });
    }
};

// Open form to create a new section
const openCreateForm = () => {
    section.value = {
        name: '',
        capacity: 40,
        adviser: '',
        room: ''
    };
    showCreateForm.value = true;
};

// Delete a section
const deleteSectionById = async (sectionName) => {
    if (!selectedGradeId.value || !sectionName) return;

    try {
        await GradeService.deleteSection(selectedGradeId.value, sectionName);

        await loadSections(selectedGradeId.value);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Section Deleted',
            life: 3000
        });
    } catch (error) {
        console.error('Error deleting section:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete section',
            life: 3000
        });
    }
};

// Initialize component
onMounted(async () => {
    await loadGrades();
});
</script>

<template>
    <div class="card-container">
        <Sakai-card v-for="(grade, index) in grades" :key="index" class="custom-card" :style="cardStyles[index]" @click="openSectionsModal(grade.name)">
            <div class="card-header">
                <h1 class="grade-name">{{ grade.name }}</h1>
            </div>
        </Sakai-card>
    </div>

    <!-- Sections Modal -->
    <Dialog v-model:visible="showModal" :style="{ width: '500px' }" header="Sections" :modal="true">
        <div v-if="!selectedSection">
            <div class="flex justify-between items-center">
                <h3>Sections in {{ selectedGrade }}</h3>
                <Button label="Create" icon="pi pi-plus" class="p-button-success" @click="openCreateForm" />
            </div>
            <div v-if="loading" class="text-center py-4">
                <i class="pi pi-spin pi-spinner text-3xl"></i>
                <p>Loading sections...</p>
            </div>
            <div v-else-if="sections.length === 0" class="text-center py-4">
                <p>No sections found. Create a new section to get started.</p>
            </div>
            <ul v-else class="section-list">
                <li v-for="(section, index) in sections" :key="index" class="section-item">
                    <span class="section-name" @click="selectSection(section.name)">{{ section.name }}</span>
                    <div class="section-buttons">
                        <Button label="View" icon="pi pi-eye" class="p-button-text p-button-sm" @click="selectSection(section.name)" />
                        <Button label="Delete" icon="pi pi-trash" class="p-button-danger p-button-sm" @click="deleteSectionById(section.name)" />
                    </div>
                </li>
            </ul>
        </div>
        <div v-else>
            <h3>Students in {{ selectedSection }}</h3>
            <div v-if="loading" class="text-center py-4">
                <i class="pi pi-spin pi-spinner text-3xl"></i>
                <p>Loading students...</p>
            </div>
            <div v-else-if="studentsInSection.length === 0" class="text-center py-4 mt-3">
                <i class="pi pi-info-circle text-3xl text-blue-500 mb-2"></i>
                <p>No students in this section.</p>
                <p class="text-sm text-gray-500 mt-2">
                    Students that have Grade Level {{ selectedGrade }} and Section {{ selectedSection }}
                    will appear here.
                </p>
            </div>
            <ul v-else class="student-list">
                <li v-for="student in studentsInSection" :key="student.id" class="student-item">
                    <i class="pi pi-user student-icon"></i>
                    <span class="student-name">{{ student.name }}</span>
                </li>
            </ul>

            <Button label="Back" icon="pi pi-arrow-left" class="mt-4" @click="selectedSection = null" />
        </div>
    </Dialog>

    <!-- Create Section Modal -->
    <Dialog v-model:visible="showCreateForm" :style="{ width: '500px' }" header="Create New Section" :modal="true">
        <div class="p-fluid">
            <div class="field">
                <label for="sectionName">Section Name</label>
                <InputText id="sectionName" v-model="section.name" required />
            </div>

            <div class="field">
                <label for="capacity">Capacity</label>
                <InputNumber id="capacity" v-model="section.capacity" min="1" />
            </div>

            <div class="field">
                <label for="adviser">Adviser</label>
                <InputText id="adviser" v-model="section.adviser" />
            </div>

            <div class="field">
                <label for="room">Room</label>
                <InputText id="room" v-model="section.room" />
            </div>

            <div class="flex justify-content-end">
                <Button label="Cancel" icon="pi pi-times" class="p-button-text mr-2" @click="showCreateForm = false" />
                <Button label="Save" icon="pi pi-check" @click="createSection" />
            </div>
        </div>
    </Dialog>
</template>

<style scoped>
.student-list {
    list-style: none;
    padding: 0;
    margin-top: 10px;
}

.student-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 8px;
    margin-bottom: 8px;
    transition:
        background 0.3s,
        transform 0.2s;
}

.student-icon {
    font-size: 18px;
    color: #007ad9;
}

.student-name {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}
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
}

.custom-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 02);
}

.section-item {
    padding: 10px;
    cursor: pointer;
    background: #eee;
    margin: 5px 0;
}
.section-item:hover {
    background: #ddd;
}

.section-list {
    list-style: none;
    padding: 0;
    margin-top: 10px;
}

.section-name {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.section-buttons {
    display: flex;
    gap: 8px;
}

.custom-card {
    color: white;
    font-weight: bold;
}
</style>
