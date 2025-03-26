<script setup>
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
    <div class="admin-section-wrapper">
        <div class="admin-section-container">
            <!-- Top Section -->
            <div class="top-nav-bar">
                <div class="nav-left">
                    <h2 class="text-2xl font-semibold">Section Management</h2>
                </div>
            </div>

            <!-- Cards Grid -->
            <div class="cards-grid">
                <div v-for="(grade, index) in grades" :key="index" class="section-card" :style="cardStyles[index]" @click="openSectionsModal(grade.name)">
                    <div class="card-content">
                        <h1 class="grade-title">{{ grade.name }}</h1>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sections Modal -->
    <Dialog v-model:visible="showModal" header="Sections" :modal="true" class="section-dialog">
        <div v-if="!selectedSection">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Sections in {{ selectedGrade }}</h3>
                <Button label="Add Section" icon="pi pi-plus" class="p-button-success" @click="openCreateForm" />
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading-container">
                <ProgressSpinner />
                <p>Loading sections...</p>
            </div>

            <!-- Empty State -->
            <div v-else-if="sections.length === 0" class="empty-state">
                <p>No sections found. Click "Add Section" to create one.</p>
            </div>

            <!-- Sections List -->
            <div v-else class="sections-grid">
                <div v-for="(section, index) in sections" :key="index" class="section-item" :style="{ background: getRandomGradient() }">
                    <div class="section-content">
                        <h3 class="section-name">{{ section.name }}</h3>
                        <div class="section-details" v-if="section.adviser">
                            <span>Adviser: {{ section.adviser }}</span>
                        </div>
                        <div class="section-details" v-if="section.room">
                            <span>Room: {{ section.room }}</span>
                        </div>
                        <div class="section-actions">
                            <Button icon="pi pi-eye" class="p-button-rounded p-button-text" @click.stop="selectSection(section.name)" />
                            <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click.stop="deleteSectionById(section.name)" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students View -->
        <div v-else>
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Students in {{ selectedSection }}</h3>
                <Button icon="pi pi-arrow-left" class="p-button-text" @click="selectedSection = null" />
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading-container">
                <ProgressSpinner />
                <p>Loading students...</p>
            </div>

            <!-- Empty State -->
            <div v-else-if="studentsInSection.length === 0" class="empty-state">
                <i class="pi pi-info-circle text-3xl text-blue-500 mb-2"></i>
                <p>No students in this section.</p>
                <p class="text-sm text-gray-500 mt-2">
                    Students with Grade Level {{ selectedGrade }} and Section {{ selectedSection }}
                    will appear here.
                </p>
            </div>

            <!-- Students List -->
            <div v-else class="students-grid">
                <div v-for="student in studentsInSection" :key="student.id" class="student-item">
                    <div class="student-content">
                        <i class="pi pi-user student-icon"></i>
                        <span class="student-name">{{ student.name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </Dialog>

    <!-- Create Section Modal -->
    <Dialog v-model:visible="showCreateForm" header="Add Section" :modal="true" class="section-dialog">
        <div class="p-fluid">
            <div class="field grid">
                <label for="sectionName" class="col-12 mb-2">Section Name</label>
                <div class="col-12 p-0">
                    <InputText id="sectionName" v-model="section.name" required placeholder="Enter section name" />
                </div>
            </div>

            <div class="field grid">
                <label for="capacity" class="col-12 mb-2">Capacity</label>
                <div class="col-12 p-0">
                    <InputNumber id="capacity" v-model="section.capacity" min="1" placeholder="Enter capacity" />
                </div>
            </div>

            <div class="field grid">
                <label for="adviser" class="col-12 mb-2">Adviser</label>
                <div class="col-12 p-0">
                    <InputText id="adviser" v-model="section.adviser" placeholder="Enter adviser name" />
                </div>
            </div>

            <div class="field grid">
                <label for="room" class="col-12 mb-2">Room</label>
                <div class="col-12 p-0">
                    <InputText id="room" v-model="section.room" placeholder="Enter room number" />
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showCreateForm = false" />
            <Button label="Save" icon="pi pi-check" class="p-button-success" @click="createSection" />
        </template>
    </Dialog>
</template>

<style scoped>
.admin-section-wrapper {
    padding: 1rem;
}

.admin-section-container {
    background-color: #ffffff;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.top-nav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
}

.section-card {
    height: 180px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease;
}

.section-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
}

.card-content {
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 1.5rem;
}

.grade-title {
    font-size: 2rem;
    font-weight: 700;
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* Sections Grid */
.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.section-item {
    border-radius: 10px;
    padding: 1.25rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
    color: white;
}

.section-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.section-content {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.section-name {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.section-details {
    font-size: 0.9rem;
    opacity: 0.9;
}

.section-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 0.75rem;
}

/* Students Grid */
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.student-item {
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 1rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.student-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.student-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.student-icon {
    font-size: 1.25rem;
    color: #3b82f6;
}

.student-name {
    font-size: 1rem;
    font-weight: 500;
    color: #1e293b;
}

/* Loading and Empty States */
.loading-container,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;
    color: #64748b;
}

.loading-container p,
.empty-state p {
    margin-top: 1rem;
}
</style>
