<!-- eslint-disable prettier/prettier -->
<!-- eslint-disable prettier/prettier -->
<script setup>
import { GradeService } from '@/router/service/Grades';
import { SubjectService } from '@/router/service/Subjects';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

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
const searchQuery = ref('');

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
    console.log('Computing filtered subjects with:', {
        selectedGrade: selectedGrade.value,
        searchQuery: searchQuery.value,
        subjectsCount: subjects.value.length
    });

    // Helper function to normalize grade values for comparison
    const normalizeGrade = (grade) => {
        if (typeof grade === 'object' && grade !== null) {
            // If grade is an object, try to get name or label property
            return grade.name || grade.label || '';
        }
        return String(grade || '');
    };

    // Helper function to check if a subject matches the search query
    const matchesSearch = (subject) => {
        if (!searchQuery.value) return true;

        const query = searchQuery.value.toLowerCase();
        const nameMatch = subject.name?.toLowerCase().includes(query);
        const gradeMatch = normalizeGrade(subject.grade).toLowerCase().includes(query);
        const descMatch = subject.description?.toLowerCase().includes(query);

        return nameMatch || gradeMatch || descMatch;
    };

    // Filter by grade and search query
    return subjects.value.filter(subject => {
        // If no grade is selected or the "All Grades" option is selected (value is null),
        // only apply search filter
        if (!selectedGrade.value || selectedGrade.value.value === null) {
            return matchesSearch(subject);
        }

        // Normalize the selected grade and subject grade for comparison
        const selectedGradeStr = normalizeGrade(selectedGrade.value);
        const subjectGradeStr = normalizeGrade(subject.grade);

        // Log comparison details if needed
        console.log(`Comparing grades: selected=${selectedGradeStr}, subject=${subjectGradeStr}`,
            selectedGradeStr === subjectGradeStr);

        // Match both grade and search query
        return subjectGradeStr === selectedGradeStr && matchesSearch(subject);
    });
});

const cardStyles = computed(() => {
    return Object.fromEntries(filteredSubjects.value.map((subject) => [subject.id, { background: getRandomGradient() }]));
});

const loadGrades = async () => {
    try {
        const gradesData = await GradeService.getGrades();
        // Add "All Grades" option at the beginning of the array
        grades.value = [
            { label: 'All Grades', value: null },
            ...gradesData.map((g) => ({
                label: g.name,
                value: g.name  // Use the name as the value for consistency
            }))
        ];
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
        console.log('Fetching subjects from API...');

        // Clear the cache to ensure fresh data
        await SubjectService.clearCache();

        const data = await SubjectService.getSubjects();
        console.log('Received subjects from API:', data);

        if (Array.isArray(data)) {
            subjects.value = data;
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
        if (!subject.value.name || !subject.value.grade || !subject.value.credits) {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Please fill in all required fields', life: 3000 });
            return;
        }

        // Format the grade value properly
        // If grade is an object with a name property, use that as the grade value
        let formattedSubject = { ...subject.value };

        if (typeof formattedSubject.grade === 'object' && formattedSubject.grade !== null) {
            console.log('Grade is an object:', formattedSubject.grade);
            if (formattedSubject.grade.name) {
                formattedSubject.grade = formattedSubject.grade.name;
            } else if (formattedSubject.grade.label) {
                formattedSubject.grade = formattedSubject.grade.label;
            }
        }

        console.log('Saving subject with data:', formattedSubject);

        submitted.value = true;

        if (subject.value.id) {
            // Update existing subject
            const updated = await SubjectService.updateSubject(subject.value.id, formattedSubject);
            console.log('Subject updated:', updated);

            // Update local data
            const index = subjects.value.findIndex(s => s.id === subject.value.id);
            if (index !== -1) {
                subjects.value[index] = { ...updated };
            }

            toast.add({ severity: 'success', summary: 'Success', detail: 'Subject Updated', life: 3000 });

            // Close the details modal if it's open
            if (showSubjectDetails.value) {
                closeDetailsModal();
            }
        } else {
            // Create new subject
            const created = await SubjectService.createSubject(formattedSubject);
            console.log('Subject created:', created);

            // Force refresh of subjects to ensure we have latest data
            await loadSubjects();

            toast.add({ severity: 'success', summary: 'Success', detail: 'Subject Created', life: 3000 });
        }

        hideDialog();
    } catch (error) {
        console.error('Error saving subject:', error);
        let errorMessage = 'Failed to save subject';

        if (error.response && error.response.data) {
            if (error.response.data.errors) {
                // Format validation errors
                const validationErrors = Object.values(error.response.data.errors).flat().join(', ');
                errorMessage = `Validation error: ${validationErrors}`;
            } else if (error.response.data.message) {
                errorMessage = error.response.data.message;
            }
        } else if (error.message) {
            errorMessage = error.message;
        }

        toast.add({ severity: 'error', summary: 'Error', detail: errorMessage, life: 3000 });
    } finally {
        submitted.value = false;
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
    // Reset form fields first
    subject.value = {
        id: null,
        name: '',
        grade: null,
        description: '',
        credits: 3
    };

    submitted.value = false;

    // Use nextTick to ensure DOM is updated after form reset
    nextTick(() => {
        // Reset animations for any fields
        const fields = document.querySelectorAll('.animated-field');
        fields.forEach(field => {
            field.style.animation = 'none';
            // Trigger reflow to reset animations
            setTimeout(() => {
                field.style.animation = '';
            }, 10);
        });

        console.log('Opening dialog with reset form');
        subjectDialog.value = true;
    });
};

const editSubject = (subj) => {
    // Make a copy of the subject data with proper grade structure
    subject.value = {
        ...subj,
        // If the grade is already in the correct format (an object), use it,
        // otherwise create a proper grade object
        grade: typeof subj.grade === 'object' ? subj.grade : subj.grade
    };

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

const filterSubjects = () => {
    // Implement the search functionality
    console.log('Filtering subjects...');
};

const clearSearch = () => {
    searchQuery.value = '';
    filterSubjects();
};

onMounted(async () => {
    await loadGrades();
    await loadSubjects();
});

// Add a watcher for dialog visibility
watch(subjectDialog, (newValue) => {
    console.log('Subject dialog visibility changed:', newValue);
    if (newValue) {
        // Dialog opened
        nextTick(() => {
            const formContainer = document.querySelector('.dialog-form-container');
            if (formContainer) {
                const style = window.getComputedStyle(formContainer);
                console.log('Form container visibility:', {
                    display: style.display,
                    opacity: style.opacity,
                    zIndex: style.zIndex,
                    overflow: style.overflow,
                    visibility: style.visibility
                });
            } else {
                console.log('Form container element not found');
            }
        });
    }
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
                    <h2 class="text-2xl font-semibold ">Subject Management</h2>
                </div>
                <div class="search-container">
                    <div class="search-input-wrapper">
                        <i class="pi pi-search search-icon"></i>
                        <input type="text" placeholder="Search subjects..." class="search-input" v-model="searchQuery" @input="filterSubjects" />
                        <button v-if="searchQuery" class="clear-search-btn" @click="clearSearch">
                            <i class="pi pi-times"></i>
                        </button>
                    </div>
                </div>
                <div class="nav-right">
                    <div class="grade-filter">
                        <Select
                            v-model="selectedGrade"
                            :options="grades"
                            optionLabel="label"
                            placeholder="Filter by Grade"
                            @change="filterSubjects"
                            class="p-inputtext-sm"
                        />
                    </div>
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
                    <!-- Floating symbols -->
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>

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
                                <div class="select-wrapper">
                                    <Select
                                        id="grade"
                                        v-model="subject.grade"
                                        :options="grades"
                                        optionLabel="label"
                                        placeholder="Select a grade"
                                        :class="{ 'p-invalid': submitted && !subject.grade }"
                                        appendTo="body"
                                    />
                                </div>
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
                            <Button label="Save" icon="pi pi-check" class="p-button-raised p-button-primary save-button-custom" @click="saveSubject" />
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

                <div class="field animated-field">
                    <label for="name">
                        <i class="pi pi-book mr-2"></i>Subject Name
                    </label>
                    <InputText
                        id="name"
                        v-model="subject.name"
                        required
                        placeholder="Enter subject name"
                        :class="{ 'p-invalid': submitted && !subject.name }"
                    />
                    <small class="p-error" v-if="submitted && !subject.name">Name is required.</small>
                </div>

                <div class="field animated-field">
                    <label for="grade">
                        <i class="pi pi-tag mr-2"></i>Grade Level
                    </label>
                    <div class="select-wrapper">
                        <Select
                            id="grade"
                            v-model="subject.grade"
                            :options="grades"
                            optionLabel="label"
                            placeholder="Select a grade"
                            :class="{ 'p-invalid': submitted && !subject.grade }"
                            appendTo="body"
                        />
                    </div>
                    <small class="p-error" v-if="submitted && !subject.grade">Grade is required.</small>
                </div>

                <div class="field animated-field">
                    <label for="description">
                        <i class="pi pi-info-circle mr-2"></i>Description
                    </label>
                    <Textarea
                        id="description"
                        v-model="subject.description"
                        rows="3"
                        placeholder="Enter a short description of the subject"
                        autoResize
                    />
                </div>

                <div class="field animated-field">
                    <label for="credits">
                        <i class="pi pi-star mr-2"></i>Credits
                    </label>
                    <div class="p-inputgroup credits-group">
                        <InputNumber
                            id="credits"
                            v-model="subject.credits"
                            :min="1"
                            :max="10"
                            placeholder="1-10"
                            showButtons
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="dialog-footer-buttons">
                    <Button
                        label="Cancel"
                        icon="pi pi-times"
                        class="p-button-text cancel-button"
                        @click="hideDialog"
                    />
                    <Button
                        label="Save"
                        icon="pi pi-check"
                        class="p-button-raised p-button-primary save-button-custom"
                        @click="saveSubject"
                    />
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
    display: flex; align-items: center;
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

.subject-card:nth-child(3n+1) .symbol {
    animation-duration: 7s;
}

.subject-card .symbol:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
.subject-card .symbol:nth-child(2) { top: 30%; left: 80%; animation-delay: 1s; }
.subject-card .symbol:nth-child(3) { top: 70%; left: 30%; animation-delay: 2s; }
.subject-card .symbol:nth-child(4) { top: 60%; left: 70%; animation-delay: 3s; }
.subject-card .symbol:nth-child(5) { top: 20%; left: 50%; animation-delay: 4s; }

/* Math symbol content variations */
.subject-card:nth-child(7n) .symbol:nth-child(1)::after { content: "π"; font-size: 18px; }
.subject-card:nth-child(7n) .symbol:nth-child(2)::after { content: "∑"; font-size: 20px; }
.subject-card:nth-child(7n) .symbol:nth-child(3)::after { content: "∞"; font-size: 24px; }
.subject-card:nth-child(7n) .symbol:nth-child(4)::after { content: "√"; font-size: 20px; }
.subject-card:nth-child(7n) .symbol:nth-child(5)::after { content: "θ"; font-size: 18px; }

.subject-card:nth-child(7n+1) .symbol:nth-child(1)::after { content: "H₂O"; font-size: 16px; }
.subject-card:nth-child(7n+1) .symbol:nth-child(2)::after { content: "CO₂"; font-size: 16px; }
.subject-card:nth-child(7n+1) .symbol:nth-child(3)::after { content: "E=mc²"; font-size: 14px; }
.subject-card:nth-child(7n+1) .symbol:nth-child(4)::after { content: "H⁺"; font-size: 16px; }
.subject-card:nth-child(7n+1) .symbol:nth-child(5)::after { content: "NaCl"; font-size: 16px; }

.subject-card:nth-child(7n+2) .symbol:nth-child(1)::after { content: "A"; font-size: 22px; }
.subject-card:nth-child(7n+2) .symbol:nth-child(2)::after { content: "B"; font-size: 24px; }
.subject-card:nth-child(7n+2) .symbol:nth-child(3)::after { content: "C"; font-size: 20px; }
.subject-card:nth-child(7n+2) .symbol:nth-child(4)::after { content: "X"; font-size: 18px; }
.subject-card:nth-child(7n+2) .symbol:nth-child(5)::after { content: "Y"; font-size: 22px; }

.subject-card:nth-child(7n+3) .symbol:nth-child(1)::after { content: "1"; font-size: 22px; }
.subject-card:nth-child(7n+3) .symbol:nth-child(2)::after { content: "2"; font-size: 24px; }
.subject-card:nth-child(7n+3) .symbol:nth-child(3)::after { content: "3"; font-size: 18px; }
.subject-card:nth-child(7n+3) .symbol:nth-child(4)::after { content: "7"; font-size: 20px; }
.subject-card:nth-child(7n+3) .symbol:nth-child(5)::after { content: "9"; font-size: 22px; }

.subject-card:nth-child(7n+4) .symbol:nth-child(1)::after { content: "∫"; font-size: 22px; }
.subject-card:nth-child(7n+4) .symbol:nth-child(2)::after { content: "∂"; font-size: 20px; }
.subject-card:nth-child(7n+4) .symbol:nth-child(3)::after { content: "∇"; font-size: 24px; }
.subject-card:nth-child(7n+4) .symbol:nth-child(4)::after { content: "∆"; font-size: 22px; }
.subject-card:nth-child(7n+4) .symbol:nth-child(5)::after { content: "Ω"; font-size: 20px; }

.subject-card:nth-child(7n+5) .symbol:nth-child(1)::after { content: "α"; font-size: 20px; }
.subject-card:nth-child(7n+5) .symbol:nth-child(2)::after { content: "β"; font-size: 22px; }
.subject-card:nth-child(7n+5) .symbol:nth-child(3)::after { content: "γ"; font-size: 24px; }
.subject-card:nth-child(7n+5) .symbol:nth-child(4)::after { content: "δ"; font-size: 18px; }
.subject-card:nth-child(7n+5) .symbol:nth-child(5)::after { content: "ω"; font-size: 20px; }

.subject-card:nth-child(7n+6) .symbol:nth-child(1)::after { content: "+"; font-size: 24px; }
.subject-card:nth-child(7n+6) .symbol:nth-child(2)::after { content: "−"; font-size: 24px; }
.subject-card:nth-child(7n+6) .symbol:nth-child(3)::after { content: "×"; font-size: 22px; }
.subject-card:nth-child(7n+6) .symbol:nth-child(4)::after { content: "÷"; font-size: 20px; }
.subject-card:nth-child(7n+6) .symbol:nth-child(5)::after { content: "="; font-size: 24px; }

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

.grade-badge {
    background: rgba(74, 135, 213, 0.25) !important;
    color: #1a365d !important;
    box-shadow: 0 0 10px rgba(74, 135, 213, 0.3);
    position: absolute;
    bottom: 1rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
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
    30%, 100% {
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
.subject-dialog {
    z-index: 1100 !important;
    border-radius: 16px;
    overflow: visible !important;
}

.subject-dialog .p-dialog-content {
    display: block !important;
    min-height: 200px !important;
    background: rgba(18, 24, 40, 0.85) !important;
    color: #fff !important;
    border-radius: 0 0 16px 16px;
    padding: 0 !important;
    opacity: 1 !important;
    overflow: visible !important;
}

.subject-dialog .p-dialog-header {
    background: rgba(30, 41, 59, 0.9) !important;
    color: #fff !important;
    border-radius: 16px 16px 0 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
    animation: fieldFadeIn 0.5s ease forwards;
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
    background: linear-gradient(135deg, #4a87d5, #6b9de8) !important;
    border: none !important;
    box-shadow: 0 4px 12px rgba(74, 135, 213, 0.3) !important;
}

:deep(.save-button:hover) {
    background: linear-gradient(135deg, #3c7dd4, #5a96e3) !important;
    box-shadow: 0 6px 16px rgba(74, 135, 213, 0.5) !important;
}

:deep(.animated-field .p-dropdown:hover),
:deep(.animated-field .p-inputtext:hover),
:deep(.animated-field .p-textarea:hover),
:deep(.animated-field .p-inputnumber:hover) {
    border-color: #93c5fd;
}

:deep(.animated-field .p-inputnumber) {
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.3s ease;
    animation: floatingInput 3s ease-in-out infinite;
}

:deep(.animated-field .p-inputnumber-buttons-stacked .p-button.p-inputnumber-button) {
    background: linear-gradient(135deg, #93c5fd, #60a5fa);
    color: #1e3a8a;
    border: none;
}

:deep(.animated-field .p-inputnumber-buttons-stacked .p-button.p-inputnumber-button:hover) {
    background: linear-gradient(135deg, #60a5fa, #3b82f6);
}

:deep(.animated-field .p-dropdown-panel) {
    background: #f0f8ff;
}

:deep(.animated-field .p-dropdown-panel .p-dropdown-items .p-dropdown-item.p-highlight) {
    background: linear-gradient(135deg, rgba(74, 135, 213, 0.2), rgba(107, 157, 232, 0.2));
    color: #1a365d;
}

/* Dialog button fixes */
:deep(.p-dialog-footer .p-button.p-component.p-button-text:not(.p-button-danger)) {
    color: #1a365d !important;
}

:deep(.cancel-button) {
    color: #1a365d !important;
}

/* Animation for card content during hover */
.subject-card:hover .card-content {
    transform: scale(1.02);
    transition: transform 0.3s ease;
}

.card-content {
    transition: transform 0.3s ease;
}
.grade-filter {
    margin-right: 1rem;
}
.grade-filter :deep(.p-dropdown) {
    background: rgba(211, 233, 255, 0.8) !important;
    border: 1px solid rgba(74, 135, 213, 0.3) !important;
    border-radius: 8px !important; min-width: 150px;
}
.grade-filter :deep(.p-dropdown-label) {
    color: #1a365d !important; font-weight: 500;
}
.grade-filter :deep(.p-dropdown-trigger) {
    color: #4a87d5 !important;
}
grade-filter :deep(.p-dropdown:hover) {
    border-color: rgba(74, 135, 213, 0.6) !important;
    box-shadow: 0 0 0 1px rgba(74, 135, 213, 0.2) !important;
}
/* Style the grade filter dropdown to match theme */
:deep(.grade-filter) {
    background: rgba(211, 233, 255, 0.8) !important;
    border: 1px solid rgba(74, 135, 213, 0.3) !important;
    border-radius: 8px !important;
    color: #1a365d !important;
    margin-right: 1rem;
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

/* Search bar styles */
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

/* Card interaction animations */
.subject-card:hover .symbol {
    color: rgba(26, 54, 93, 0.6);
}


.subject-card:hover::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at center, rgba(74, 135, 213, 0.1), transparent 70%);
    pointer-events: none;
    z-index: 1;
    animation: pulse-light 1.5s infinite alternate;
}

@keyframes pulse-light {
    0% {
        opacity: 0;
    }
    100% {
        opacity: 1;
    }
}

/* Restore button styles */
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

/* Update card action buttons */
.card-actions .p-button {
    color: #1a365d !important;
    background: rgba(211, 233, 255, 0.5) !important;
}

.card-actions .p-button:hover {
    background: rgba(211, 233, 255, 0.8) !important;
}

.card-actions .p-button.p-button-danger {
    color: #c53030 !important;
}

.card-actions .p-button.p-button-danger:hover {
    background: rgba(254, 215, 215, 0.8) !important;
}

/* Update loading state to match theme */
.loading-container {
    color: #1a365d;
}

:deep(.p-progressspinner .p-progressspinner-circle) {
    stroke: #4a87d5 !important;
}

/* Update form controls */
:deep(.p-inputtext:enabled:focus),
:deep(.p-select:enabled:focus) {
    border-color: #4a87d5;
    box-shadow: 0 0 0 1px rgba(74, 135, 213, 0.25);
}

:deep(.p-select-panel .p-select-items .p-select-item.p-highlight) {
    background: rgba(74, 135, 213, 0.1);
    color: #1a365d;
}

:deep(.p-select:not(.p-disabled).p-focus),
:deep(.p-inputtext:enabled:hover) {
    border-color: #4a87d5;
}

/* Fix dialog form container - remove margin */
.dialog-form-container.mt-4 {
    margin-top: 0 !important;
}

/* Fix dialog content visibility issue */
.dialog-particle {
    position: absolute;
    width: 10px;
    height: 10px;
    background: rgba(74, 135, 213, 0.2);
    border-radius: 50%;
    pointer-events: none;
    z-index: 0; /* Lower z-index so it doesn't overlay form content */
    animation: particleFloat 15s infinite linear;
}

.dialog-form-container {
    position: relative;
    z-index: 5 !important;
    margin-top: 0 !important;
    padding: 1rem !important;
    background: transparent !important;
}

:deep(.subject-dialog) {
    z-index: 1000 !important;
    overflow: visible !important;
}

:deep(.subject-dialog .p-dialog-content) {
    padding: 2rem !important;
    background: linear-gradient(170deg, #f0f8ff 0%, #e0f2ff 100%) !important;
    background-size: 200% 200% !important;
    animation: gradientBG 15s ease infinite !important;
    position: relative;
    z-index: 1;
    overflow: visible !important;
}

@keyframes gradientBG {
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

/* Form field styles */
.dialog-form-container .field label {
    font-weight: 600;
    font-size: 1rem;
    color: #1a365d;
    margin-bottom: 0.5rem;
    display: block;
    transition: all 0.3s ease;
    position: relative;
}

.dialog-form-container .field label::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, #4a87d5, #6b9de8);
    transition: width 0.3s ease;
}

.dialog-form-container .field:hover label::after {
    width: 100%;
}

.dialog-form-container input,
.dialog-form-container textarea,
.dialog-form-container .p-inputnumber,
.dialog-form-container .p-select {
    border: 2px solid rgba(74, 135, 213, 0.2);
    border-radius: 12px;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 10px rgba(74, 135, 213, 0.1);
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(5px);
    width: 100%;
}

.dialog-form-container input:focus,
.dialog-form-container textarea:focus,
.dialog-form-container .p-inputnumber:focus,
.dialog-form-container .p-select:focus {
    border-color: #4a87d5;
    box-shadow:
        0 0 0 2px rgba(74, 135, 213, 0.1),
        0 8px 16px rgba(74, 135, 213, 0.15);
    transform: translateY(-2px);
    background: white;
}

.dialog-form-container input:hover,
.dialog-form-container textarea:hover,
.dialog-form-container .p-inputnumber:hover,
.dialog-form-container .p-select:hover {
    border-color: #4a87d5;
    box-shadow: 0 6px 12px rgba(74, 135, 213, 0.15);
}

/* Dialog footer styles */
:deep(.p-dialog .p-dialog-footer) {
    background: linear-gradient(to top, #e0f2ff, #f0f8ff);
    padding: 1.5rem 2rem;
    border-top: 1px solid rgba(74, 135, 213, 0.2);
    position: relative;
}

:deep(.p-dialog .p-dialog-footer::before) {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.8),
        transparent
    );
}

/* Button styles */
.dialog-footer-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    position: relative;
    z-index: 50;
}

.cancel-button {
    position: relative;
    overflow: hidden;
    border-radius: 10px !important;
    background: rgba(211, 233, 255, 0.8) !important;
    color: #1a365d !important;
    border: 1px solid rgba(74, 135, 213, 0.2) !important;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05) !important;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
    transform: scale(1);
}

.cancel-button:hover {
    background: rgba(224, 242, 255, 0.9) !important;
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.08) !important;
}

.save-button {
    position: relative;
    overflow: hidden;
    border-radius: 10px !important;
    background: linear-gradient(135deg, #4a87d5, #6b9de8) !important;
    border: none !important;
    box-shadow:
        0 4px 15px rgba(74, 135, 213, 0.3),
        0 0 0 2px rgba(74, 135, 213, 0.1) !important;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
    transform: scale(1);
}

.save-button:hover {
    background: linear-gradient(135deg, #3c7dd4, #5a96e3) !important;
    transform: translateY(-3px) scale(1.05);
    box-shadow:
        0 8px 20px rgba(74, 135, 213, 0.4),
        0 0 0 2px rgba(74, 135, 213, 0.2) !important;
}

/* Button shine animation */
.save-button::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        transparent,
        transparent,
        rgba(255, 255, 255, 0.2),
        transparent,
        transparent
    );
    transform: rotate(45deg);
    animation: buttonShine 3s infinite;
}

@keyframes buttonShine {
    0% {
        top: -50%;
        left: -50%;
    }
    30%, 100% {
        top: 150%;
        left: 150%;
    }
}

/* Select panel styles */
:deep(.p-select-panel) {
    position: absolute !important;
    z-index: 9999 !important;
    border-radius: 12px;
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(74, 135, 213, 0.2);
    background: white;
    min-width: 100%;
    max-height: 300px;
    overflow: auto;
}

:deep(.p-select-panel .p-select-items) {
    padding: 0.5rem;
}

:deep(.p-select-panel .p-select-item) {
    padding: 0.75rem 1rem;
    margin: 0.25rem 0;
    border-radius: 8px;
    cursor: pointer;
}

:deep(.p-select-panel .p-select-item:hover) {
    background: rgba(74, 135, 213, 0.1);
}

/* Dialog mask blur effect */
:deep(.p-dialog-mask) {
    backdrop-filter: blur(5px);
    background-color: rgba(0, 0, 0, 0.4);
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

/* Style for the select wrapper to ensure proper dropdown display */
.select-wrapper {
    position: relative;
    z-index: 100;
}

/* Additional styles for overlay panels */
:deep(.p-component-overlay) {
    z-index: 9999 !important;
}

.field {
    position: relative;
    z-index: 20;
    margin-bottom: 1.5rem;
}

.animated-field label {
    display: block;
    margin-bottom: 0.5rem;
    color: #fff;
    font-weight: 500;
}

.p-inputtext, .p-inputnumber, .p-dropdown, .p-calendar {
    width: 100%;
    background: rgba(255, 255, 255, 0.1) !important;
    color: #1a365d !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    border-radius: 8px !important;
    transition: all 0.3s ease;
}

.p-inputtext:enabled:focus,
.p-inputnumber:enabled:focus,
.p-dropdown:enabled:focus,
.p-calendar:enabled:focus,
.p-select:enabled:focus {
    box-shadow: 0 0 0 2px rgba(100, 181, 246, 0.4) !important;
    border-color: #64b5f6 !important;
}

.save-button-custom {
    background: linear-gradient(135deg, #7c4dff, #448aff) !important;
    border: none !important;
    box-shadow: 0 4px 12px rgba(124, 77, 255, 0.3) !important;
    transition: all 0.3s ease !important;
}
</style>
