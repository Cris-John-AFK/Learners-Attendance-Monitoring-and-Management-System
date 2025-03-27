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
    <Dialog
        v-model:visible="showModal"
        :modal="true"
        class="section-dialog animated-dialog"
        :contentStyle="{ padding: '0', borderRadius: '16px', overflow: 'hidden' }"
        :pt="{
            root: { class: 'single-modal-container' },
            content: { class: 'single-modal-content' }
        }"
        :showHeader="false">
        <div class="dialog-header-content">
            <div class="education-icon pencil"></div>
            <div class="education-icon book"></div>
            <div class="education-icon globe"></div>
            <div class="education-icon math"></div>
            <h2>Sections</h2>
            <Button icon="pi pi-times" class="p-button-rounded p-button-text p-button-plain close-button"
                @click="showModal = false" />
        </div>

        <div class="dialog-body">
            <div v-if="!selectedSection">
                <div class="section-header">
                    <h3 class="section-title education-title">Sections in {{ selectedGrade }}</h3>
                    <Button label="Add Section" icon="pi pi-plus" class="p-button-success pulse-button" @click="openCreateForm" />
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
                    <div
                        v-for="(section, index) in sections"
                        :key="index"
                        class="section-item"
                        :style="{ background: getRandomGradient(), '--index': index }"
                    >
                        <div class="section-content">
                            <h3 class="section-name">{{ section.name }}</h3>
                            <div class="section-info">
                                <div class="section-details" v-if="section.adviser">
                                    <span><strong>Adviser:</strong> {{ section.adviser }}</span>
                                </div>
                                <div class="section-details" v-if="section.room">
                                    <span><strong>Room:</strong> {{ section.room }}</span>
                                </div>
                                <div class="section-capacity" v-if="section.capacity">
                                    <span><strong>Capacity:</strong> {{ section.capacity }}</span>
                                </div>
                            </div>
                            <div class="section-actions">
                                <Button icon="pi pi-eye" class="p-button-rounded p-button-text animated-icon-button"
                                    aria-label="View students" v-tooltip.top="'View students'"
                                    @click.stop="selectSection(section.name)" />
                                <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger animated-icon-button"
                                    aria-label="Delete section" v-tooltip.top="'Delete section'"
                                    @click.stop="deleteSectionById(section.name)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Students View -->
            <div v-else>
                <div class="section-header">
                    <h3 class="section-title education-title">Students in {{ selectedSection }}</h3>
                    <Button icon="pi pi-arrow-left" label="Back to Sections" class="p-button-text" @click="selectedSection = null" />
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
                    <div v-for="(student, index) in studentsInSection" :key="student.id"
                        class="student-item" :style="{ '--index': index }">
                        <div class="student-content">
                            <i class="pi pi-user student-icon"></i>
                            <span class="student-name">{{ student.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Dialog>

    <!-- Create Section Modal -->
    <Dialog
        v-model:visible="showCreateForm"
        :modal="true"
        class="section-dialog animated-dialog create-section-dialog"
        :contentStyle="{ padding: '0', borderRadius: '16px', overflow: 'hidden' }"
        :pt="{
            root: { class: 'single-modal-container' },
            content: { class: 'single-modal-content' }
        }"
        :showHeader="false">
        <div class="dialog-header-content">
            <div class="education-icon pencil"></div>
            <div class="education-icon book"></div>
            <h2>Add Section</h2>
            <Button icon="pi pi-times" class="p-button-rounded p-button-text p-button-plain close-button"
                @click="showCreateForm = false" />
        </div>

        <div class="dialog-body">
            <div class="p-fluid create-form">
                <div class="field grid">
                    <label for="sectionName" class="col-12 mb-2 form-label">Section Name</label>
                    <div class="col-12 p-0">
                        <InputText id="sectionName" v-model="section.name" required placeholder="Enter section name" class="form-input" />
                    </div>
                </div>

                <div class="field grid">
                    <label for="capacity" class="col-12 mb-2 form-label">Capacity</label>
                    <div class="col-12 p-0">
                        <InputNumber id="capacity" v-model="section.capacity" min="1" placeholder="Enter capacity" class="form-input" />
                    </div>
                </div>

                <div class="field grid">
                    <label for="adviser" class="col-12 mb-2 form-label">Adviser</label>
                    <div class="col-12 p-0">
                        <InputText id="adviser" v-model="section.adviser" placeholder="Enter adviser name" class="form-input" />
                    </div>
                </div>

                <div class="field grid">
                    <label for="room" class="col-12 mb-2 form-label">Room</label>
                    <div class="col-12 p-0">
                        <InputText id="room" v-model="section.room" placeholder="Enter room number" class="form-input" />
                    </div>
                </div>

                <div class="form-actions">
                    <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showCreateForm = false" />
                    <Button label="Save" icon="pi pi-check" class="p-button-success" @click="createSection" />
                </div>
            </div>
        </div>
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

/* Dialog Content Styling */
.dialog-content {
    padding: 0.5rem;
    background: transparent;
    border-radius: 0;
    position: static;
    z-index: auto;
}

.dialog-header-content {
    display: flex;
    align-items: center;
    position: relative;
    padding: 1rem 2rem 1rem;
    width: 100%;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    margin-bottom: 1rem;
}

.dialog-header-content h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1e293b;
    z-index: 2;
    flex-grow: 1;
}

.close-button {
    margin-left: auto;
    width: 2.5rem;
    height: 2.5rem;
    transition: all 0.3s ease;
    z-index: 5;
}

.close-button:hover {
    background-color: rgba(0, 0, 0, 0.05);
    transform: rotate(90deg);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
    color: #1e293b;
}

/* Sections Grid */
.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.25rem;
    margin-top: 1.25rem;
}

.section-item {
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease;
    color: white;
    animation: fade-in-up 0.5s ease forwards;
    animation-delay: calc(var(--index, 0) * 0.1s);
    opacity: 0;
    position: relative;
    overflow: hidden;
}

.section-item::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
    opacity: 0;
    transform: scale(1);
    animation: ripple 8s linear infinite;
}

.section-item:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.section-content {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    position: relative;
    z-index: 2;
}

.section-name {
    font-size: 1.35rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    letter-spacing: 0.02em;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.section-info {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-top: 0.2rem;
    margin-bottom: 0.5rem;
}

.section-details, .section-capacity {
    font-size: 0.95rem;
    opacity: 0.95;
    line-height: 1.4;
    padding-left: 0.25rem;
}

.section-details strong, .section-capacity strong {
    font-weight: 600;
    margin-right: 4px;
}

.section-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1rem;
}

/* Students Grid */
.students-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
    margin-top: 1.25rem;
}

.student-item {
    background-color: #f8f9fa;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.06);
    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease;
    animation: fade-in-up 0.4s ease forwards;
    animation-delay: calc(var(--index, 0) * 0.08s);
    opacity: 0;
}

.student-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    background-color: #f1f5f9;
}

.student-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.student-icon {
    font-size: 1.5rem;
    color: #3b82f6;
    width: 40px;
    height: 40px;
    background-color: rgba(59, 130, 246, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
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
    padding: 3rem 1rem;
    text-align: center;
    color: #64748b;
    background-color: rgba(248, 250, 252, 0.5);
    border-radius: 12px;
    min-height: 200px;
}

.loading-container p,
.empty-state p {
    margin-top: 1rem;
    font-size: 1rem;
}

/* Educational icons */
.education-icon {
    position: absolute;
    width: 26px;
    height: 26px;
    opacity: 0.2;
    animation: float 3s ease-in-out infinite;
    z-index: 1;
}

.education-icon.pencil {
    top: -15px;
    right: 20%;
    animation-delay: 0s;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%233b82f6'%3E%3Cpath d='M20.71 7.04c.39-.39.39-1.04 0-1.41l-2.34-2.34c-.37-.39-1.02-.39-1.41 0l-1.84 1.83 3.75 3.75M3 17.25V21h3.75L17.81 9.93l-3.75-3.75L3 17.25z'/%3E%3C/svg%3E") no-repeat center center;
}

.education-icon.book {
    top: 5px;
    right: 8%;
    animation-delay: 0.5s;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%233b82f6'%3E%3Cpath d='M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z'/%3E%3C/svg%3E") no-repeat center center;
}

.education-icon.globe {
    bottom: 0;
    right: 35%;
    animation-delay: 1s;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%233b82f6'%3E%3Cpath d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z'/%3E%3C/svg%3E") no-repeat center center;
}

.education-icon.math {
    bottom: -15px;
    right: 18%;
    animation-delay: 1.5s;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%233b82f6'%3E%3Cpath d='M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14h-2v-4h-2v4H8v-2h4v-4h2v4h2v2z'/%3E%3C/svg%3E") no-repeat center center;
}

/* Animation styles */
.animated-dialog {
    animation: dialog-entry 0.5s ease-out;
    position: relative;
    overflow: hidden;
}

.animated-dialog::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #4bc0c8, #c779d0, #feac5e);
    background-size: 200% 200%;
    animation: gradient-shift 5s ease infinite;
    z-index: 2;
}

.education-title {
    position: relative;
    display: inline-block;
    color: #1e293b;
}

.education-title::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, #007bff, #6610f2);
    transform: scaleX(0);
    transform-origin: bottom right;
    transition: transform 0.5s ease;
    animation: line-draw 1.5s ease forwards;
}

.pulse-button {
    animation: pulse 2s infinite;
    box-shadow: 0 0 0 rgba(52, 211, 153, 0.4);
    transition: all 0.3s ease;
    padding: 0.75rem 1.25rem;
    font-weight: 500;
}

.pulse-button:hover {
    transform: translateY(-2px);
}

/* Form Styling */
.create-form {
    padding: 0.5rem 1rem 1rem;
}

.form-label {
    color: #3b82f6;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-input {
    transition: all 0.2s ease-in-out;
    border-radius: 8px !important;
}

.form-input:focus {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.15) !important;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
}

.create-section-dialog :deep(.p-inputtext:focus),
.create-section-dialog :deep(.p-inputnumber:focus) {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
    outline: none;
}

/* Dialog styling */
:deep(.section-dialog) {
    margin: auto !important;
    position: relative !important;
    top: auto !important;
    left: auto !important;
    bottom: auto !important;
    right: auto !important;
    transform: none !important;
    max-height: 90vh !important;
    min-width: 600px !important;
    max-width: 90vw !important;
    border-radius: 16px !important;
    overflow: hidden !important;
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2), 0 8px 10px rgba(0, 0, 0, 0.1) !important;
    background-color: white !important;
}

:deep(.single-modal-container) {
    border-radius: 16px !important;
    overflow: hidden !important;
    box-shadow: none !important;
    background: none !important;
}

:deep(.single-modal-content) {
    border-radius: 16px !important;
    padding: 0 !important;
    background-color: white !important;
}

:deep(.p-dialog) {
    border-radius: 16px !important;
    overflow: hidden !important;
    background-color: white !important;
}

:deep(.p-dialog-content) {
    border-radius: 16px !important;
    overflow: auto !important;
    background-color: white !important;
}

:deep(.p-component-overlay) {
    background-color: rgba(0, 0, 0, 0.5) !important;
    backdrop-filter: blur(3px);
}

.animated-icon-button {
    position: relative;
    overflow: hidden;
    transition: transform 0.3s ease, background-color 0.3s ease;
}

.animated-icon-button:hover {
    transform: scale(1.1);
}

.animated-icon-button:active {
    transform: scale(0.95);
}

.animated-icon-button::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.5s ease, height 0.5s ease;
}

.animated-icon-button:hover::before {
    width: 150%;
    height: 150%;
}

.animated-icon-button::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    transform: translate(-50%, -50%);
    transition: width 0.5s ease, height 0.5s ease;
}

.animated-icon-button:hover::after {
    width: 150%;
    height: 150%;
}

.animated-icon-button::before,
.animated-icon-button::after {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(52, 211, 153, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(52, 211, 153, 0);
    }
}

@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes line-draw {
    from {
        transform: scaleX(0);
        transform-origin: bottom right;
    }
    to {
        transform: scaleX(1);
        transform-origin: bottom left;
    }
}

@keyframes ripple {
    0% {
        opacity: 0;
        transform: scale(0.5);
    }
    30% {
        opacity: 0.3;
    }
    100% {
        opacity: 0;
        transform: scale(1.2);
    }
}

@keyframes gradient-shift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

/* Media Queries for Responsive Design */
@media (max-width: 768px) {
    :deep(.section-dialog) {
        min-width: 90vw !important;
    }

    .sections-grid {
        grid-template-columns: 1fr;
    }

    .students-grid {
        grid-template-columns: 1fr;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .pulse-button {
        width: 100%;
    }
}

/* Additional animation keyframes */
@keyframes dialog-entry {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes float {
    0% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
    100% {
        transform: translateY(0);
    }
}

@keyframes subtle-shift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

.dialog-body {
    padding: 0 1.5rem 1.5rem;
    max-height: 70vh;
    overflow-y: auto;
}
</style>
