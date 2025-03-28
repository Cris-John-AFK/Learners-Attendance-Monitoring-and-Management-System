<script setup>
import { GradesService } from '@/router/service/GradesService';
import { SectionService } from '@/router/service/SectionService';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const toast = useToast();
const sections = ref([]);
const grades = ref([]);
const filterGrades = ref([]);
const loading = ref(true);
const sectionDialog = ref(false);
const deleteSectionDialog = ref(false);
const selectedGrade = ref(null);
const showSectionDetails = ref(false);
const detailsEditMode = ref(false);
const modalContainer = ref(null);
const searchQuery = ref('');

const section = ref({
    id: null,
    name: '',
    grade: '',
    description: ''
});
const submitted = ref(false);

const getRandomGradient = () => {
    const colors = ['#ff9a9e', '#fad0c4', '#fbc2eb', '#a6c1ee', '#ffdde1', '#ee9ca7', '#ff758c', '#ff7eb3', '#c3cfe2', '#d4fc79', '#96e6a1', '#84fab0', '#8fd3f4', '#a18cd1'];
    const color1 = colors[Math.floor(Math.random() * colors.length)];
    const color2 = colors[Math.floor(Math.random() * colors.length)];
    return `linear-gradient(135deg, ${color1}, ${color2})`; // Enclose in backticks
};

const filteredSections = computed(() => {
    console.log('Computing filtered sections with:', {
        selectedGrade: selectedGrade.value,
        searchQuery: searchQuery.value,
        sectionsCount: sections.value.length
    });

    // Helper function to normalize grade values for comparison
    const normalizeGrade = (grade) => {
        if (typeof grade === 'object' && grade !== null) {
            return grade.name || grade.label || '';
        }
        return String(grade || '');
    };

    // Helper function to check if a section matches the search query
    const matchesSearch = (section) => {
        if (!searchQuery.value) return true;

        const query = searchQuery.value.toLowerCase();
        const nameMatch = section.name?.toLowerCase().includes(query);
        const gradeMatch = normalizeGrade(section.grade).toLowerCase().includes(query);
        const descMatch = section.description?.toLowerCase().includes(query);

        return nameMatch || gradeMatch || descMatch;
    };

    return sections.value.filter(section => {
        // If no grade is selected or "All Grades" is selected, only apply search filter
        if (!selectedGrade.value || selectedGrade.value.value === null) {
            return matchesSearch(section);
        }

        // Check if the section's grade ID matches the selected grade's ID
        const gradeMatches = section.grade?.id === selectedGrade.value.id;
        return gradeMatches && matchesSearch(section);
    });
});

const cardStyles = computed(() => {
    return Object.fromEntries(filteredSections.value.map((section) => [section.id, { background: getRandomGradient() }]));
});

const loadGrades = async () => {
    try {
        const gradesData = await GradesService.getGrades();
        console.log('Fetched grades from API:', gradesData);

        // Regular grades list for form (without All Grades option)
        grades.value = gradesData.map(grade => ({
            id: grade.id,
            name: grade.name,
            code: grade.code,
            label: grade.name,
            value: grade.id
        }));

        // Filter grades list with All Grades option
        filterGrades.value = [
            { id: null, name: 'All Grades', code: 'ALL', label: 'All Grades', value: null },
            ...grades.value
        ];

        console.log('Processed grades for dropdown:', grades.value);
    } catch (error) {
        console.error('Error loading grades:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load grades',
            life: 3000
        });
    }
};

const loadSections = async () => {
    try {
        loading.value = true;
        console.log('Fetching sections from API...');

        const data = await SectionService.getSections();
        // Transform the data to include grade name
        sections.value = data.map(section => ({
            ...section,
            gradeName: section.grade?.name || 'No Grade'
        }));
        console.log('Loaded sections:', sections.value);

        loading.value = false;
    } catch (error) {
        console.error('Error loading section data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load section data',
            life: 3000
        });
        loading.value = false;
        sections.value = []; // Reset to empty array on error
    }
};

const saveSection = async () => {
    submitted.value = true;

    if (!section.value.name?.trim() || !section.value.grade) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Please fill in all required fields',
            life: 3000
        });
        return;
    }

    try {
        const sectionData = {
            name: section.value.name.trim(),
            grade: section.value.grade,
            description: section.value.description?.trim() || ''
        };

        if (section.value.id) {
            // Update existing section
            await SectionService.updateSection(section.value.id, sectionData);
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Section updated successfully',
                life: 3000
            });
        } else {
            // Create new section
            await SectionService.createSection(sectionData);
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Section created successfully',
                life: 3000
            });
        }

        sectionDialog.value = false;
        section.value = {};
        submitted.value = false;
        await loadSections(); // Refresh the sections list
    } catch (error) {
        console.error('Error saving section:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save section',
            life: 3000
        });
    }
};

const hideDialog = () => {
    sectionDialog.value = false;
    submitted.value = false;
};

const openNew = () => {
    section.value = {
        id: null,
        name: '',
        grade: null,
        description: ''
    };
    submitted.value = false;
    nextTick(() => {
        const fields = document.querySelectorAll('.animated-field');
        fields.forEach(field => {
            field.style.animation = 'none';
            setTimeout(() => {
                field.style.animation = '';
            }, 10);
        });
        sectionDialog.value = true;
    });
};

const editSection = (sect) => {
    section.value = {
        ...sect,
        grade: typeof sect.grade === 'object' ? sect.grade : sect.grade
    };

    if (showSectionDetails.value) {
        detailsEditMode.value = true;
    } else {
        openDetailsModal(sect);
        nextTick(() => {
            detailsEditMode.value = true;
        });
    }
};

const confirmDelete = (sect) => {
    section.value = { ...sect };
    deleteSectionDialog.value = true;
};

const openDetailsModal = (sect) => {
    section.value = { ...sect };
    showSectionDetails.value = true;
    detailsEditMode.value = false;
    nextTick(() => {
        if (modalContainer.value) {
            modalContainer.value.classList.add('six');
            document.body.classList.add('modal-active');
        }
    });
};

const closeDetailsModal = () => {
    if (modalContainer.value) {
        modalContainer.value.classList.add('out');
    }
    document.body.classList.remove('modal-active');
    setTimeout(() => {
        showSectionDetails.value = false;
        detailsEditMode.value = false;
        if (modalContainer.value) {
            modalContainer.value.classList.remove('six', 'out');
        }
    }, 300);
};

const filterSections = () => {
    console.log('Filtering sections...');
};

const clearSearch = () => {
    searchQuery.value = '';
    filterSections();
};

const deleteSection = async () => {
    try {
        await SectionService.deleteSection(section.value.id);
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Section deleted successfully',
            life: 3000
        });
        deleteSectionDialog.value = false;
        section.value = {};
        await loadSections(); // Refresh the sections list
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

onMounted(async () => {
    await loadGrades();
    await loadSections();
});

watch(sectionDialog, (newValue) => {
    console.log('Section dialog visibility changed:', newValue);
    if (newValue) {
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

watch(sectionDialog, async (newValue, oldValue) => {
    if (!newValue && oldValue) {
        await loadGrades();
    }
});

onMounted(async () => {
    await loadGrades();
    await loadSections();
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
                    <h2 class="text-2xl font-semibold ">Section Management</h2>
                </div>
                <div class="search-container">
                    <div class="search-input-wrapper">
                        <i class="pi pi-search search-icon"></i>
                        <input type="text" placeholder="Search sections..." class="search-input" v-model="searchQuery" @input="filterSections" />
                        <button v-if="searchQuery" class="clear-search-btn" @click="clearSearch">
                            <i class="pi pi-times"></i>
                        </button>
                    </div>
                </div>
                <div class="nav-right">
                    <div class="grade-filter">
                        <Select
                            v-model="selectedGrade"
                            :options="filterGrades"
                            optionLabel="label"
                            placeholder="Filter by Grade"
                            @change="filterSections"
                            class="p-inputtext-sm"
                        />
                    </div>
                    <Button label="Add Section" icon="pi pi-plus" class="add-button p-button-success" @click.prevent="openNew" />
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading-container">
                <ProgressSpinner />
                <p>Loading sections...</p>
            </div>

            <!-- Cards Grid -->
            <div v-else class="cards-grid">
                <div v-for="section in filteredSections" :key="section.id" class="subject-card" :style="cardStyles[section.id]" @click="openDetailsModal(section)">
                    <!-- Floating symbols -->
                    <span class="symbol">§</span>
                    <span class="symbol">✦</span>
                    <span class="symbol">✧</span>
                    <span class="symbol">⚬</span>
                    <span class="symbol">◇</span>

                    <div class="card-content">
                        <h1 class="subject-title">{{ section.name }}</h1>
                        <div class="grade-badge">{{ section.grade?.name || 'No Grade' }}</div>
                        <div class="card-actions">
                            <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click.stop="editSection(section)" />
                            <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click.stop="confirmDelete(section)" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredSections.length === 0 && !loading" class="empty-state">
                <p>No sections found. Click "Add Section" to create one.</p>
            </div>
        </div>

        <!-- Sketch Style Modal -->
        <div v-if="showSectionDetails" ref="modalContainer" id="modal-container">
            <div class="modal-background" @click="closeDetailsModal">
                <div class="modal" @click.stop>
                    <div class="modal-header">
                        <h2>{{ detailsEditMode ? 'Edit Section' : 'Section Details' }}</h2>
                        <Button icon="pi pi-times" class="p-button-rounded p-button-text close-button" @click="closeDetailsModal" aria-label="Close" />
                    </div>

                    <!-- View Mode -->
                    <div v-if="!detailsEditMode" class="modal-content">
                        <div class="subject-details">
                            <div class="detail-row">
                                <label>Name:</label>
                                <span>{{ section.name }}</span>
                            </div>
                            <div class="detail-row">
                                <label>Grade:</label>
                                <span>{{ section.grade?.name || 'No Grade' }}</span>
                            </div>
                            <div class="detail-row description">
                                <label>Description:</label>
                                <p>{{ section.description || 'No description available.' }}</p>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <Button label="Edit" icon="pi pi-pencil" class="p-button-primary" @click="detailsEditMode = true" />
                            <Button label="Delete" icon="pi pi-trash" class="p-button-danger" @click="confirmDelete(section)" />
                        </div>
                    </div>

                    <!-- Edit Mode -->
                    <div v-else class="modal-content">
                        <div class="edit-form">
                            <div class="field">
                                <label for="name">Name</label>
                                <InputText id="name" v-model="section.name" required placeholder="Enter section name" :class="{ 'p-invalid': submitted && !section.name }" />
                                <small class="p-error" v-if="submitted && !section.name">Name is required.</small>
                            </div>
                            <div class="field">
                                <label for="grade">Grade</label>
                                <div class="select-wrapper">
                                    <Select
                                        id="grade"
                                        v-model="section.grade"
                                        :options="grades"
                                        optionLabel="name"
                                        placeholder="Select a grade"
                                        :class="{ 'p-invalid': submitted && !section.grade }"
                                        appendTo="body"
                                    />
                                </div>
                                <small class="p-error" v-if="submitted && !section.grade">Grade is required.</small>
                            </div>
                            <div class="field">
                                <label for="description">Description</label>
                                <Textarea id="description" v-model="section.description" rows="3" placeholder="Add a description" />
                            </div>
                        </div>
                        <div class="modal-actions">
                            <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="detailsEditMode = false" />
                            <Button label="Save" icon="pi pi-check" class="p-button-raised p-button-primary save-button-custom" @click="saveSection" />
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
        <Dialog v-model:visible="sectionDialog" :header="section.id ? 'Edit Section' : 'Add Section'" modal class="p-fluid section-dialog" :style="{ width: '500px' }" :breakpoints="{ '960px': '75vw', '640px': '90vw' }">
            <div class="dialog-form-container p-5">
                <!-- Floating particles -->
                <div class="dialog-particle"></div>
                <div class="dialog-particle"></div>
                <div class="dialog-particle"></div>

                <div class="field animated-field">
                    <label for="name">
                        <i class="pi pi-book mr-2"></i>Section Name
                    </label>
                    <InputText
                        id="name"
                        v-model="section.name"
                        required
                        placeholder="Enter section name"
                        :class="{ 'p-invalid': submitted && !section.name }"
                    />
                    <small class="p-error" v-if="submitted && !section.name">Name is required.</small>
                </div>

                <div class="field animated-field">
                    <label for="grade">
                        <i class="pi pi-tag mr-2"></i>Grade Level
                    </label>
                    <div class="select-wrapper">
                        <Select
                            id="grade"
                            v-model="section.grade"
                            :options="grades"
                            optionLabel="name"
                            placeholder="Select a grade"
                            :class="{ 'p-invalid': submitted && !section.grade }"
                            appendTo="body"
                        />
                    </div>
                    <small class="p-error" v-if="submitted && !section.grade">Grade is required.</small>
                </div>

                <div class="field animated-field">
                    <label for="description">
                        <i class="pi pi-info-circle mr-2"></i>Description
                    </label>
                    <Textarea
                        id="description"
                        v-model="section.description"
                        rows="3"
                        placeholder="Enter a short description of the section"
                        autoResize
                    />
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
                        @click="saveSection"
                    />
                </div>
            </template>
        </Dialog>

        <!-- Delete Dialog -->
        <Dialog v-model:visible="deleteSectionDialog" header="Confirm" modal :style="{ width: '450px' }" :breakpoints="{ '960px': '75vw', '640px': '90vw' }">
            <div class="confirmation-content">
                <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem" />
                <span>Are you sure you want to delete this section?</span>
            </div>
            <template #footer>
                <Button label="No" icon="pi pi-times" class="p-button-text" @click="deleteSectionDialog = false" />
                <Button label="Yes" icon="pi pi-check" class="p-button-danger" @click="deleteSection()" />
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

/* Card and Grid Styles */
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
    background: linear-gradient(135deg, rgba(211, 233, 255, 0.9), rgba(233, 244, 255, 0.9));
    border: 1px solid rgba(74, 135, 213, 0.3);
    backdrop-filter: blur(5px);
}

.subject-card:hover {
    transform: translateY(-8px);
    box-shadow:
        0 15px 30px rgba(0, 0, 0, 0.15),
        0 0 25px rgba(74, 135, 213, 0.4);
    border: 1px solid rgba(74, 135, 213, 0.5);
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
    font-size: 1.75rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 6px rgba(74, 135, 213, 0.4);
}

.grade-badge {
    background: rgba(74, 135, 213, 0.25);
    color: #1a365d;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    position: absolute;
    bottom: 1rem;
    box-shadow: 0 0 10px rgba(74, 135, 213, 0.3);
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

@keyframes pulse-light {
    0% {
        opacity: 0;
    }
    100% {
        opacity: 1;
    }
}

/* Navigation and Search Styles */
.top-nav-bar {
    border-bottom: 1px solid rgba(74, 135, 213, 0.2);
    margin-bottom: 2.5rem;
    padding-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nav-left h2 {
    color: #1a365d;
    font-size: 2rem;
    font-weight: 600;
    margin: 0;
    text-shadow: 0 2px 6px rgba(74, 135, 213, 0.3);
    letter-spacing: 0.5px;
}

.nav-right {
    display: flex;
    align-items: center;
}

.grade-filter {
    margin-right: 1rem;
}

.grade-filter :deep(.p-dropdown) {
    background: rgba(211, 233, 255, 0.8) !important;
    border: 1px solid rgba(74, 135, 213, 0.3) !important;
    border-radius: 8px !important;
    min-width: 150px;
}

.grade-filter :deep(.p-dropdown-label) {
    color: #1a365d !important;
    font-weight: 500;
}

.grade-filter :deep(.p-dropdown-trigger) {
    color: #4a87d5 !important;
}

.grade-filter :deep(.p-dropdown:hover) {
    border-color: rgba(74, 135, 213, 0.6) !important;
    box-shadow: 0 0 0 1px rgba(74, 135, 213, 0.2) !important;
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

/* Loading and Empty States */
.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: #1a365d;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    background: rgba(211, 233, 255, 0.7);
    border-radius: 16px;
    color: #1a365d;
    border: 1px solid rgba(74, 135, 213, 0.2);
}

:deep(.p-progressspinner .p-progressspinner-circle) {
    stroke: #4a87d5 !important;
}

/* Modal and Dialog Styles */
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

/* Dialog Styles */
:deep(.section-dialog) {
    z-index: 1100 !important;
    border-radius: 16px;
    overflow: visible !important;
}

:deep(.section-dialog .p-dialog-content) {
    padding: 2rem !important;
    background: linear-gradient(170deg, #f0f8ff 0%, #e0f2ff 100%) !important;
    background-size: 200% 200% !important;
    animation: gradientBG 15s ease infinite !important;
    position: relative;
    z-index: 1;
    overflow: visible !important;
}

.dialog-form-container {
    position: relative;
    z-index: 5 !important;
    margin-top: 0 !important;
    padding: 1rem !important;
    background: transparent !important;
}

.dialog-particle {
    position: absolute;
    width: 10px;
    height: 10px;
    background: rgba(74, 135, 213, 0.2);
    border-radius: 50%;
    pointer-events: none;
    z-index: 0;
    animation: particleFloat 15s infinite linear;
}

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

/* Form Field Styles */
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
    color: black;
    font-weight: 500;
}

.p-inputtext,
.p-inputnumber,
.p-dropdown,
.p-calendar {
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
