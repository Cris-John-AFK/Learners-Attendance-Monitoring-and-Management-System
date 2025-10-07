<!-- eslint-disable prettier/prettier -->
<!-- eslint-disable prettier/prettier -->
<script setup>
import { SubjectService } from '@/router/service/Subjects';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onMounted, ref } from 'vue';

const toast = useToast();
const subjects = ref([]);
const loading = ref(true);
const subjectDialog = ref(false);
const deleteSubjectDialog = ref(false);
const showSubjectDetails = ref(false);
const detailsEditMode = ref(false);
const modalContainer = ref(null);
const searchQuery = ref('');

const subject = ref({
    id: null,
    name: '',
    description: ''
});
const submitted = ref(false);

const getRandomGradient = () => {
    const colors = ['#ff9a9e', '#fad0c4', '#fbc2eb', '#a6c1ee', '#ffdde1', '#ee9ca7', '#ff758c', '#ff7eb3', '#c3cfe2', '#d4fc79', '#96e6a1', '#84fab0', '#8fd3f4', '#a18cd1'];
    const color1 = colors[Math.floor(Math.random() * colors.length)];
    const color2 = colors[Math.floor(Math.random() * colors.length)];
    return `linear-gradient(135deg, ${color1}, ${color2})`; // Enclose in backticks
};

const filteredSubjects = computed(() => {
    console.log('Computing filtered subjects with:', {
        searchQuery: searchQuery.value,
        subjectsCount: subjects.value.length
    });

    // Filter by search query
    if (!searchQuery.value) {
        return subjects.value;
    }

    const query = searchQuery.value.toLowerCase();
    return subjects.value.filter((subject) => {
        const nameMatch = subject.name?.toLowerCase().includes(query);
        const descMatch = subject.description?.toLowerCase().includes(query);
        return nameMatch || descMatch;
    });
});

const cardStyles = computed(() => {
    return Object.fromEntries(filteredSubjects.value.map((subject) => [subject.id, { background: getRandomGradient() }]));
});

const loadSubjects = async () => {
    try {
        loading.value = true;
        console.log('Fetching subjects from API...');

        // Clear the cache to ensure fresh data
        await SubjectService.clearCache();

        const data = await SubjectService.getSubjects();
        console.log('Received subjects from API:', data);

        if (Array.isArray(data)) {
            // Clear existing subjects first to prevent duplicates
            subjects.value = [];
            // Use Map to ensure unique subjects by ID
            const uniqueSubjects = new Map();
            data.forEach(subject => {
                uniqueSubjects.set(subject.id, subject);
            });
            subjects.value = Array.from(uniqueSubjects.values());
            console.log('Subjects updated successfully:', subjects.value.length, 'subjects loaded');
        } else {
            console.error('API returned non-array data:', data);
            subjects.value = [];
        }

        loading.value = false;
    } catch (error) {
        console.error('Error loading subject data:', error);
        if (error.response) {
            console.error('API error response:', error.response.data);
        }
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load subject data: ' + (error.message || 'Unknown error'),
            life: 3000
        });
        loading.value = false;
        subjects.value = []; // Reset to empty array on error
    }
};

const saveSubject = async () => {
    try {
        // Validation
        if (!subject.value.name) {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Please fill in the subject name', life: 3000 });
            return;
        }

        // Format the subject data for the API
        let formattedSubject = {
            name: subject.value.name,
            description: subject.value.description
        };

        console.log('Saving subject with formatted data:', formattedSubject);
        submitted.value = true;

        if (subject.value.id) {
            // Update existing subject
            const updated = await SubjectService.updateSubject(subject.value.id, formattedSubject);
            console.log('Subject updated:', updated);

            // Update local data
            const index = subjects.value.findIndex((s) => s.id === subject.value.id);
            if (index !== -1) {
                subjects.value[index] = { ...updated };
            }

            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Subject updated successfully',
                life: 3000
            });
        } else {
            // Create new subject
            const created = await SubjectService.createSubject(formattedSubject);
            console.log('Subject created:', created);

            // Add to local data
            subjects.value.push(created);

            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Subject created successfully',
                life: 3000
            });
        }

        // Close the dialog and reset form
        subjectDialog.value = false;
        resetSubjectForm();
    } catch (error) {
        console.error('Error saving subject:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save subject: ' + (error.message || 'Unknown error'),
            life: 3000
        });
    } finally {
        submitted.value = false;
    }
};

const openNew = () => {
    subject.value = {
        id: null,
        name: '',
        description: ''
    };
    submitted.value = false;
    subjectDialog.value = true;
};

const editSubject = (editSubject) => {
    subject.value = { ...editSubject };
    subjectDialog.value = true;
};

const confirmDeleteSubject = (subjectToDelete) => {
    subject.value = subjectToDelete;
    deleteSubjectDialog.value = true;
};

const deleteSubject = async () => {
    try {
        await SubjectService.deleteSubject(subject.value.id);
        subjects.value = subjects.value.filter((s) => s.id !== subject.value.id);

        deleteSubjectDialog.value = false;

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Subject deleted successfully',
            life: 3000
        });
    } catch (error) {
        console.error('Error deleting subject:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete subject: ' + (error.message || 'Unknown error'),
            life: 3000
        });
    }
};

const resetSubjectForm = () => {
    subject.value = {
        id: null,
        name: '',
        description: ''
    };
    submitted.value = false;
};

// View subject details
const viewSubjectDetails = (subjectData) => {
    subject.value = { ...subjectData };
    detailsEditMode.value = false;

    // Show the modal with animation
    showSubjectDetails.value = true;

    // Apply the animation class
    nextTick(() => {
        if (modalContainer.value) {
            modalContainer.value.classList.add('six');
            document.body.classList.add('modal-active');
        }
    });
};

// Toggle edit mode in details
const toggleEditMode = () => {
    detailsEditMode.value = !detailsEditMode.value;
};

// Save edited details
const saveDetailsEdit = async () => {
    try {
        const updated = await SubjectService.updateSubject(subject.value.id, {
            name: subject.value.name,
            description: subject.value.description
        });

        // Update in local array
        const index = subjects.value.findIndex((s) => s.id === subject.value.id);
        if (index !== -1) {
            subjects.value[index] = { ...updated };
        }

        detailsEditMode.value = false;

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Subject updated successfully',
            life: 3000
        });
    } catch (error) {
        console.error('Error updating subject:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update subject: ' + (error.message || 'Unknown error'),
            life: 3000
        });
    }
};

onMounted(async () => {
    await loadSubjects();
});
</script>

<template>
    <div class="admin-subject-wrapper">
        <!-- Light geometric background shapes -->
        <div class="background-container">
            <div class="geometric-shape circle"></div>
            <div class="geometric-shape square"></div>
            <div class="geometric-shape triangle"></div>
            <div class="geometric-shape rectangle"></div>
            <div class="geometric-shape diamond"></div>
        </div>

        <div class="admin-subject-container">
            <!-- Top Section -->
            <div class="top-nav-bar">
                <div class="nav-left">
                    <h2 class="text-2xl font-semibold">Subject Management</h2>
                </div>
                <div class="search-container">
                    <div class="search-input-wrapper">
                        <i class="pi pi-search search-icon"></i>
                        <input type="text" placeholder="Search subjects..." class="search-input" v-model="searchQuery" />
                        <button v-if="searchQuery" class="clear-search-btn" @click="searchQuery = ''">
                            <i class="pi pi-times"></i>
                        </button>
                    </div>
                </div>
                <div class="nav-right">
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
                <div v-for="subject in filteredSubjects" :key="subject.id" class="subject-card" :style="cardStyles[subject.id]" @click="viewSubjectDetails(subject)">
                    <!-- Floating symbols -->
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>

                    <div class="card-content">
                        <h1 class="subject-title">{{ subject.name }}</h1>
                        <div class="card-actions">
                            <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click.stop="editSubject(subject)" />
                            <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click.stop="confirmDeleteSubject(subject)" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredSubjects.length === 0 && !loading" class="empty-state">
                <p>No subjects found. Click "Add Subject" to create one.</p>
            </div>
        </div>
    </div>

    <!-- Sketch Style Modal -->
    <div v-if="showSubjectDetails" ref="modalContainer" id="modal-container">
        <div class="modal-background" @click="showSubjectDetails = false">
            <div class="modal" @click.stop>
                <div class="modal-header">
                    <h2>{{ detailsEditMode ? 'Edit Subject' : 'Subject Details' }}</h2>
                    <Button icon="pi pi-times" class="p-button-rounded p-button-text close-button" @click="showSubjectDetails = false" aria-label="Close" />
                </div>

                <!-- View Mode -->
                <div v-if="!detailsEditMode" class="modal-content">
                    <div class="subject-details">
                        <div class="detail-row">
                            <label>Name:</label>
                            <span>{{ subject.name }}</span>
                        </div>
                        <div class="detail-row description">
                            <label>Description:</label>
                            <p>{{ subject.description || 'No description available.' }}</p>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <Button label="Edit" icon="pi pi-pencil" class="p-button-primary" @click="toggleEditMode" />
                        <Button label="Delete" icon="pi pi-trash" class="p-button-danger" @click="confirmDeleteSubject(subject)" />
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
                            <label for="description">Description</label>
                            <Textarea id="description" v-model="subject.description" rows="3" placeholder="Add a description" />
                        </div>
                    </div>
                    <div class="modal-actions">
                        <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="toggleEditMode" />
                        <Button label="Save" icon="pi pi-check" class="p-button-raised p-button-primary save-button-custom" @click="saveDetailsEdit" />
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
    <Dialog v-model:visible="subjectDialog" :header="subject.id ? 'Edit Subject' : 'Add Subject'" modal class="p-fluid subject-dialog" :style="{ width: '500px' }" :breakpoints="{ '960px': '75vw', '640px': '90vw' }">
        <div class="dialog-form-container p-5">
            <!-- Floating particles -->
            <div class="dialog-particle"></div>
            <div class="dialog-particle"></div>
            <div class="dialog-particle"></div>

            <div class="field animated-field p-5">
                <label for="name"> <i class="pi pi-book mr-2"></i>Subject Name </label>
                <InputText id="name" v-model="subject.name" required placeholder="Enter subject name" :class="{ 'p-invalid': submitted && !subject.name }" />
                <small class="p-error" v-if="submitted && !subject.name">Name is required.</small>
            </div>

            <div class="field animated-field p-5">
                <label for="description"> <i class="pi pi-info-circle mr-2"></i>Description </label>
                <Textarea id="description" v-model="subject.description" rows="3" placeholder="Enter a short description of the subject" autoResize />
            </div>
        </div>

        <template #footer>
            <div class="dialog-footer-buttons p-5">
                <Button label="Cancel" icon="pi pi-times" class="p-button-text cancel-button" @click="subjectDialog = false" />
                <Button label="Save" icon="pi pi-check" class="p-button-raised p-button-primary save-button-custom" @click="saveSubject" />
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
            <Button label="Yes" icon="pi pi-check" class="p-button-danger" @click="deleteSubject" />
        </template>
    </Dialog>
</template>

<style scoped>
.admin-subject-wrapper {
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
    animation:
        rotate 25s linear infinite,
        float 18s ease-in-out infinite;
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
    animation:
        float 22s ease-in-out infinite,
        opacity-pulse 15s ease-in-out infinite;
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
    animation:
        float 23s ease-in-out infinite reverse,
        opacity-pulse 18s ease-in-out infinite;
}

/* Simple float animation */
@keyframes float {
    0%,
    100% {
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
    0%,
    100% {
        opacity: 0.05;
    }
    50% {
        opacity: 0.1;
    }
}

.admin-subject-container {
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

/* Override for subject cards to match new sky theme */
.subject-card {
    background: linear-gradient(135deg, rgba(211, 233, 255, 0.9), rgba(233, 244, 255, 0.9)) !important;
    border: 1px solid rgba(74, 135, 213, 0.3);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    position: relative;
    height: 220px;
    border-radius: 16px;
    transition: all 0.4s ease;
    backdrop-filter: blur(5px);
}

/* Remove the white shimmer animation */
.subject-card::before {
    content: none;
}

/* Make symbols more visible */
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

.subject-card:nth-child(3n + 1) .symbol {
    animation-duration: 7s;
}

.subject-card .symbol:nth-child(1) {
    top: 10%;
    left: 10%;
    animation-delay: 0s;
}
.subject-card .symbol:nth-child(2) {
    top: 30%;
    left: 80%;
    animation-delay: 1s;
}
.subject-card .symbol:nth-child(3) {
    top: 70%;
    left: 30%;
    animation-delay: 2s;
}
.subject-card .symbol:nth-child(4) {
    top: 60%;
    left: 70%;
    animation-delay: 3s;
}
.subject-card .symbol:nth-child(5) {
    top: 20%;
    left: 50%;
    animation-delay: 4s;
}

/* Math symbol content variations */
.subject-card:nth-child(7n) .symbol:nth-child(1)::after {
    content: 'π';
    font-size: 18px;
}
.subject-card:nth-child(7n) .symbol:nth-child(2)::after {
    content: '∑';
    font-size: 20px;
}
.subject-card:nth-child(7n) .symbol:nth-child(3)::after {
    content: '∞';
    font-size: 24px;
}
.subject-card:nth-child(7n) .symbol:nth-child(4)::after {
    content: '√';
    font-size: 20px;
}
.subject-card:nth-child(7n) .symbol:nth-child(5)::after {
    content: 'θ';
    font-size: 18px;
}

.subject-card:nth-child(7n + 1) .symbol:nth-child(1)::after {
    content: 'H₂O';
    font-size: 16px;
}
.subject-card:nth-child(7n + 1) .symbol:nth-child(2)::after {
    content: 'CO₂';
    font-size: 16px;
}
.subject-card:nth-child(7n + 1) .symbol:nth-child(3)::after {
    content: 'E=mc²';
    font-size: 14px;
}
.subject-card:nth-child(7n + 1) .symbol:nth-child(4)::after {
    content: 'H⁺';
    font-size: 16px;
}
.subject-card:nth-child(7n + 1) .symbol:nth-child(5)::after {
    content: 'NaCl';
    font-size: 16px;
}

.subject-card:nth-child(7n + 2) .symbol:nth-child(1)::after {
    content: 'A';
    font-size: 22px;
}
.subject-card:nth-child(7n + 2) .symbol:nth-child(2)::after {
    content: 'B';
    font-size: 24px;
}
.subject-card:nth-child(7n + 2) .symbol:nth-child(3)::after {
    content: 'C';
    font-size: 20px;
}
.subject-card:nth-child(7n + 2) .symbol:nth-child(4)::after {
    content: 'X';
    font-size: 18px;
}
.subject-card:nth-child(7n + 2) .symbol:nth-child(5)::after {
    content: 'Y';
    font-size: 22px;
}

.subject-card:nth-child(7n + 3) .symbol:nth-child(1)::after {
    content: '1';
    font-size: 22px;
}
.subject-card:nth-child(7n + 3) .symbol:nth-child(2)::after {
    content: '2';
    font-size: 24px;
}
.subject-card:nth-child(7n + 3) .symbol:nth-child(3)::after {
    content: '3';
    font-size: 18px;
}
.subject-card:nth-child(7n + 3) .symbol:nth-child(4)::after {
    content: '7';
    font-size: 20px;
}
.subject-card:nth-child(7n + 3) .symbol:nth-child(5)::after {
    content: '9';
    font-size: 22px;
}

.subject-card:nth-child(7n + 4) .symbol:nth-child(1)::after {
    content: '∫';
    font-size: 22px;
}
.subject-card:nth-child(7n + 4) .symbol:nth-child(2)::after {
    content: '∂';
    font-size: 20px;
}
.subject-card:nth-child(7n + 4) .symbol:nth-child(3)::after {
    content: '∇';
    font-size: 24px;
}
.subject-card:nth-child(7n + 4) .symbol:nth-child(4)::after {
    content: '∆';
    font-size: 22px;
}
.subject-card:nth-child(7n + 4) .symbol:nth-child(5)::after {
    content: 'Ω';
    font-size: 20px;
}

.subject-card:nth-child(7n + 5) .symbol:nth-child(1)::after {
    content: 'α';
    font-size: 20px;
}
.subject-card:nth-child(7n + 5) .symbol:nth-child(2)::after {
    content: 'β';
    font-size: 22px;
}
.subject-card:nth-child(7n + 5) .symbol:nth-child(3)::after {
    content: 'γ';
    font-size: 24px;
}
.subject-card:nth-child(7n + 5) .symbol:nth-child(4)::after {
    content: 'δ';
    font-size: 18px;
}
.subject-card:nth-child(7n + 5) .symbol:nth-child(5)::after {
    content: 'ω';
    font-size: 20px;
}

.subject-card:nth-child(7n + 6) .symbol:nth-child(1)::after {
    content: '+';
    font-size: 24px;
}
.subject-card:nth-child(7n + 6) .symbol:nth-child(2)::after {
    content: '−';
    font-size: 24px;
}
.subject-card:nth-child(7n + 6) .symbol:nth-child(3)::after {
    content: '×';
    font-size: 22px;
}
.subject-card:nth-child(7n + 6) .symbol:nth-child(4)::after {
    content: '÷';
    font-size: 20px;
}
.subject-card:nth-child(7n + 6) .symbol:nth-child(5)::after {
    content: '=';
    font-size: 24px;
}

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

.subject-title {
    color: #1a365d !important;
    text-shadow: 0 2px 6px rgba(74, 135, 213, 0.4) !important;
    font-size: 1.75rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 0.5rem;
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

.top-nav-bar {
    border-bottom: 1px solid rgba(74, 135, 213, 0.2) !important;
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
}

.subject-card:hover {
    transform: translateY(-8px) !important;
    box-shadow:
        0 15px 30px rgba(0, 0, 0, 0.15),
        0 0 25px rgba(74, 135, 213, 0.4) !important;
    border: 1px solid rgba(74, 135, 213, 0.5);
}

.subject-card::before {
    content: none;
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
    background: rgba(211, 233, 255, 0.7);
    border-radius: 16px;
    color: #1a365d;
    border: 1px solid rgba(74, 135, 213, 0.2);
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
    background-color: rgba(240, 248, 255, 0.95);
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
    stroke: #4a87d5;
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
    border-bottom: 1px solid #d1e4fb;
    position: relative;
    z-index: 2;
    background-color: #e8f3ff;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1a365d;
}

.close-button {
    z-index: 3;
    position: relative;
    cursor: pointer !important;
    transition: background-color 0.2s;
    margin-right: -0.5rem;
    margin-top: -0.5rem;
    color: #1a365d !important;
}

.close-button:hover {
    background-color: rgba(74, 135, 213, 0.2) !important;
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
    color: #1a365d;
    margin-bottom: 0.25rem;
}

.detail-row span,
.detail-row p {
    color: #2c5282;
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
    border-top: 1px solid #d1e4fb;
    background-color: #f0f8ff;
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
    color: #1a365d;
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
    stroke: #4a87d5;
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
        stroke: #4a87d5;
        stroke-width: 2px;
    }
    50% {
        stroke: #78a6f0;
        stroke-width: 3px;
    }
    100% {
        stroke: #4a87d5;
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
        top: -50%;
        left: -50%;
    }
    30%,
    100% {
        top: 150%;
        left: 150%;
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

/* Style the add button to match theme */
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

.dialog-form-container {
    position: relative;
    z-index: 10;
    padding: 1.5rem !important;
    background: transparent;
    overflow: visible;
}

.dialog-particle {
    position: absolute;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    z-index: 1;
    pointer-events: none;
}

.dialog-particle:nth-child(1) {
    top: 20px;
    left: 20px;
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

.animated-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    opacity: 0;
    transform: translateY(20px);
    animation: fieldFadeIn 0.5s ease forwards;
    position: relative;
}

.animated-field:nth-child(1) {
    animation-delay: 0.1s;
}

.animated-field:nth-child(2) {
    animation-delay: 0.2s;
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

:deep(.save-button-custom) {
    background: linear-gradient(135deg, #4a87d5, #6b9de8) !important;
    border: none !important;
    box-shadow: 0 4px 12px rgba(74, 135, 213, 0.3) !important;
}

:deep(.save-button-custom:hover) {
    background: linear-gradient(135deg, #3c7dd4, #5a96e3) !important;
    box-shadow: 0 6px 16px rgba(74, 135, 213, 0.5) !important;
}

@keyframes fieldFadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes pulse-light {
    0% {
        opacity: 0;
    }
    100% {
        opacity: 1;
    }
}

/* Single media query for responsiveness */
@media (max-width: 768px) {
    .modal {
        width: 95%;
        padding: 20px;
    }
}

/* Enhanced subject dialog */
:deep(.subject-dialog) {
    box-shadow:
        0 15px 35px rgba(50, 50, 93, 0.1),
        0 5px 15px rgba(0, 0, 0, 0.07);
    overflow: visible !important;
}

:deep(.subject-dialog .p-dialog-header) {
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    padding: 1.5rem;
}

:deep(.subject-dialog .p-dialog-content) {
    background: rgba(18, 24, 40, 0.85) !important;
    color: #fff !important;
    padding: 0 !important;
}

.subject-description {
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    flex-grow: 1;
}

.status-badge {
    display: inline-block;
    font-weight: 600;
}
</style>
