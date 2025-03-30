<script setup>
import axios from 'axios';
import { useToast } from 'primevue/usetoast';
import { ref, watch } from 'vue';

// Props
const props = defineProps({
    visible: {
        type: Boolean,
        required: true
    },
    teacher: {
        type: Object,
        required: true
    },
    apiBaseUrl: {
        type: String,
        required: true
    },
    mode: {
        type: String,
        default: 'new' // 'new' or 'add-subjects'
    },
    existingAssignments: {
        type: Array,
        default: () => []
    }
});

// Emits
const emit = defineEmits(['update:visible', 'assignment-complete']);

// Component state
const toast = useToast();
const loading = ref(false);
const currentStep = ref(1);
const totalSteps = ref(4);
const selectedRole = ref(null);
const selectedGrade = ref(null);
const selectedSection = ref(null);
const selectedSubjects = ref([]);
const availableGrades = ref([]);
const availableSections = ref([]);
const availableSubjects = ref([]);
const isPrimaryTeacherModeEnabled = ref(false);

// Role options
const roleOptions = [
    {
        label: 'Primary Teacher',
        value: 'primary',
        icon: 'pi pi-user',
        description: 'Assign as main teacher for a section'
    },
    {
        label: 'Subject Teacher',
        value: 'subject',
        icon: 'pi pi-book',
        description: 'Assign to teach specific subjects'
    },
    {
        label: 'Special Education Teacher',
        value: 'special_education',
        icon: 'pi pi-heart',
        description: 'Assign for special education needs'
    },
    {
        label: 'Teaching Assistant',
        value: 'assistant',
        icon: 'pi pi-users',
        description: 'Assign as an assistant teacher'
    }
];

// Watch for dialog visibility
watch(() => props.visible, (newValue) => {
    if (newValue) {
        // Reset wizard state when dialog opens
        resetWizard();

        // If in add-subjects mode, start at step 4 with existing data
        if (props.mode === 'add-subjects' && props.existingAssignments && props.existingAssignments.length > 0) {
            // Set to add-subjects mode with existing primary assignment
            isPrimaryTeacherModeEnabled.value = true;

            // Find primary assignment
            const primaryAssignment = props.existingAssignments.find(a => a && a.is_primary);

            if (primaryAssignment && primaryAssignment.section && primaryAssignment.section.id) {
                // Set role, grade, section from existing primary assignment
                selectedRole.value = 'primary';
                selectedGrade.value = primaryAssignment.grade || null;
                selectedSection.value = primaryAssignment.section;

                // Skip directly to subject selection
                currentStep.value = 4;

                // Load subjects but exclude ones already assigned
                loadSubjectsForExistingTeacher(primaryAssignment.section.id);
            } else {
                // If no primary assignment, revert to normal flow
                loadGrades();
            }
        } else {
            // Normal new assignment flow
            loadGrades();
        }
    }
});

// Methods
const resetWizard = () => {
    currentStep.value = 1;
    selectedRole.value = null;
    selectedGrade.value = null;
    selectedSection.value = null;
    selectedSubjects.value = [];
    availableGrades.value = [];
    availableSections.value = [];
    availableSubjects.value = [];
};

const closeDialog = () => {
    emit('update:visible', false);
};

const goToNextStep = () => {
    if (currentStep.value < totalSteps.value) {
        currentStep.value++;
    }
};

const goToPreviousStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

// API calls
const loadGrades = async () => {
    try {
        loading.value = true;
        const response = await axios.get(`${props.apiBaseUrl}/grades`);
        availableGrades.value = response.data;

        if (availableGrades.value.length === 0) {
            toast.add({
                severity: 'warn',
                summary: 'No Grades',
                detail: 'No grade levels found in the system. Please add grade levels first.',
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error loading grades:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load grade levels. Please try again.',
            life: 3000
        });
        availableGrades.value = [];
    } finally {
        loading.value = false;
    }
};

const loadSections = async (gradeId) => {
    try {
        loading.value = true;
        const response = await axios.get(`${props.apiBaseUrl}/sections/grade/${gradeId}`);
        availableSections.value = response.data;

        if (availableSections.value.length === 0) {
            toast.add({
                severity: 'warn',
                summary: 'No Sections',
                detail: 'No sections found for this grade. Please add sections first.',
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error loading sections:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load sections. Please try again.',
            life: 3000
        });
        availableSections.value = [];
    } finally {
        loading.value = false;
    }
};

const loadSubjects = async () => {
    try {
        loading.value = true;
        const response = await axios.get(`${props.apiBaseUrl}/subjects`);
        availableSubjects.value = response.data;

        console.log('Available subjects loaded:', availableSubjects.value);

        // If primary teacher is selected, automatically add Homeroom subject
        // but still allow selecting other subjects
        if (selectedRole.value === 'primary') {
            // Check if there's already a Homeroom subject (case insensitive)
            console.log('Searching for Homeroom subject...');
            const homeroomSubject = availableSubjects.value.find(
                s => s.name.toLowerCase() === 'homeroom'
            );

            console.log('Homeroom subject found:', homeroomSubject);

            if (homeroomSubject) {
                selectedSubjects.value = [homeroomSubject];
                console.log('Selected subjects updated with Homeroom:', selectedSubjects.value);
            } else {
                // Instead of creating a fake homeroom subject with string ID,
                // show a warning that homeroom subject doesn't exist
                toast.add({
                    severity: 'warn',
                    summary: 'Homeroom Subject Missing',
                    detail: 'Homeroom subject not found in the system. Please create it first.',
                    life: 5000
                });
            }
        }

        if (availableSubjects.value.length === 0) {
            toast.add({
                severity: 'warn',
                summary: 'No Subjects',
                detail: 'No subjects found in the system. Please add subjects first.',
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error loading subjects:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load subjects. Please try again.',
            life: 3000
        });
        availableSubjects.value = [];
    } finally {
        loading.value = false;
    }
};

// Load subjects but don't automatically select homeroom for existing primary teachers
const loadSubjectsForExistingTeacher = async (sectionId) => {
    try {
        loading.value = true;
        const response = await axios.get(`${props.apiBaseUrl}/subjects`);
        availableSubjects.value = response.data || [];

        // Pre-select already assigned subjects
        if (props.existingAssignments && props.existingAssignments.length > 0 && Array.isArray(availableSubjects.value) && sectionId) {
            // Get all subject IDs this teacher already has
            const assignedSubjectIds = props.existingAssignments
                .filter(a => a.section && a.subject && a.section.id === sectionId)
                .map(a => a.subject.id);

            // Mark these as already assigned (we'll show them differently in UI)
            availableSubjects.value.forEach(subject => {
                if (subject && subject.id && assignedSubjectIds.includes(subject.id)) {
                    subject.alreadyAssigned = true;
                }
            });
        }

        if (!Array.isArray(availableSubjects.value) || availableSubjects.value.length === 0) {
            toast.add({
                severity: 'warn',
                summary: 'No Subjects',
                detail: 'No subjects found in the system. Please add subjects first.',
                life: 3000
            });
            if (!Array.isArray(availableSubjects.value)) {
                availableSubjects.value = []; // Ensure it's always an array
            }
        }
    } catch (error) {
        console.error('Error loading subjects:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load subjects. Please try again.',
            life: 3000
        });
        availableSubjects.value = [];
    } finally {
        loading.value = false;
    }
};

// Selection handlers
const selectRole = (role) => {
    selectedRole.value = role.value;
    goToNextStep();
};

const selectGrade = (grade) => {
    selectedGrade.value = grade;
    loadSections(grade.id);
    goToNextStep();
};

const selectSection = (section) => {
    selectedSection.value = section;
    loadSubjects();
    goToNextStep();
};

const toggleSubjectSelection = (subject) => {
    // For primary teachers, ensure that Homeroom is always included
    // but allow adding other subjects
    if (selectedRole.value === 'primary') {
        // Don't allow removing the Homeroom subject for primary teachers
        const isHomeroom = subject.name.toLowerCase() === 'homeroom';
        if (isHomeroom && selectedSubjects.value.some(s => s.id === subject.id)) {
            return; // Prevent removing homeroom subject
        }

        // For other subjects, toggle selection
        const index = selectedSubjects.value.findIndex(s => s.id === subject.id);
        if (index === -1) {
            selectedSubjects.value.push(subject);
        } else {
            selectedSubjects.value.splice(index, 1);
        }
        return;
    }

    // For non-primary teachers, normal toggle behavior
    const index = selectedSubjects.value.findIndex(s => s.id === subject.id);
    if (index === -1) {
        selectedSubjects.value.push(subject);
    } else {
        selectedSubjects.value.splice(index, 1);
    }
};

// Submit assignment (simplified with separate flows for primary and subjects)
const completeAssignment = async () => {
    // Skip validation in add-subjects mode if nothing selected (will show info message later)
    if (props.mode === 'new' && selectedSubjects.value.length === 0) {
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: selectedRole.value === 'primary'
                ? 'Homeroom subject not found. Please add a homeroom subject first.'
                : 'Please select at least one subject',
            life: 3000
        });
        return;
    }

    // Validate that we have a selected section
    if (!selectedSection.value || !selectedSection.value.id) {
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'No section selected. Please select a section first.',
            life: 3000
        });
        return;
    }

    try {
        loading.value = true;

        // Different API handling based on mode
        if (props.mode === 'add-subjects') {
            // ======== ADD SUBJECTS TO EXISTING TEACHER FLOW ========

            // First, get current assignments to avoid duplicates
            let currentAssignments = [];
            try {
                const teacherResponse = await axios.get(
                    `${props.apiBaseUrl}/teachers/${props.teacher.id}?_=${new Date().getTime()}`
                );

                if (teacherResponse.data && teacherResponse.data.assignments) {
                    currentAssignments = teacherResponse.data.assignments.map(a => ({
                        id: a.id,
                        section_id: Number(a.section_id),
                        subject_id: Number(a.subject_id),
                        is_primary: a.is_primary === true,
                        role: a.role || 'subject'
                    }));
                    console.log('Current assignments from server:', currentAssignments);
                }
            } catch (error) {
                console.warn('Could not fetch current assignments, using props data:', error);
                if (props.existingAssignments && props.existingAssignments.length > 0) {
                    currentAssignments = props.existingAssignments.map(a => ({
                        id: a.id,
                        section_id: Number(a.section?.id || a.section_id),
                        subject_id: Number(a.subject?.id || a.subject_id),
                        is_primary: a.is_primary === true,
                        role: a.role || 'subject'
                    }));
                }
            }

            // Build a map of existing assignments for quick lookup (section_id-subject_id -> assignment)
            const existingAssignmentMap = new Map();
            currentAssignments.forEach(a => {
                const key = `${a.section_id}-${a.subject_id}`;
                existingAssignmentMap.set(key, a);
            });

            // Process each selected subject
            const newAssignments = [];
            const skippedSubjects = [];

            for (const subject of selectedSubjects.value) {
                const key = `${selectedSection.value.id}-${subject.id}`;

                // Skip if already assigned (using Map for O(1) lookup)
                if (subject.alreadyAssigned || existingAssignmentMap.has(key)) {
                    skippedSubjects.push(subject.name);
                    continue;
                }

                // Add as new assignment
                newAssignments.push({
                    section_id: selectedSection.value.id,
                    subject_id: subject.id,
                    is_primary: false, // Never primary for add-subjects flow
                    role: 'subject'    // Always subject role for add-subjects flow
                });
            }

            // If we skipped subjects, inform the user
            if (skippedSubjects.length > 0) {
                toast.add({
                    severity: 'info',
                    summary: 'Already Assigned',
                    detail: `Skipped already assigned subjects: ${skippedSubjects.join(', ')}`,
                    life: 4000
                });

                // If all subjects were skipped, just close the dialog
                if (newAssignments.length === 0) {
                    toast.add({
                        severity: 'info',
                        summary: 'No Changes',
                        detail: 'All selected subjects are already assigned',
                        life: 3000
                    });
                    closeDialog();
                    emit('assignment-complete', props.teacher.id);
                    return;
                }
            }

            // If nothing to add, show message and close
            if (newAssignments.length === 0) {
                toast.add({
                    severity: 'info',
                    summary: 'No Changes',
                    detail: 'No new subjects were selected to add',
                    life: 3000
                });
                closeDialog();
                emit('assignment-complete', props.teacher.id);
                return;
            }

            // Combine existing assignments with new ones
            const allAssignments = [...currentAssignments, ...newAssignments];
            console.log('All assignments to send:', allAssignments);

            // Send all assignments to the API
            await axios.put(
                `${props.apiBaseUrl}/teachers/${props.teacher.id}/assignments`,
                { assignments: allAssignments }
            );

            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: `Added ${newAssignments.length} new subject(s) successfully`,
                life: 3000
            });
        } else {
            // ======== NEW ASSIGNMENT FLOW (PRIMARY TEACHER) ========

            // Get current assignments for OTHER sections only
            let otherSectionAssignments = [];
            try {
                const teacherResponse = await axios.get(
                    `${props.apiBaseUrl}/teachers/${props.teacher.id}?_=${new Date().getTime()}`
                );

                if (teacherResponse.data && teacherResponse.data.assignments) {
                    // Keep only assignments for OTHER sections
                    otherSectionAssignments = teacherResponse.data.assignments
                        .filter(a => Number(a.section_id) !== Number(selectedSection.value.id))
                        .map(a => ({
                            id: a.id,
                            section_id: Number(a.section_id),
                            subject_id: Number(a.subject_id),
                            is_primary: a.is_primary === true,
                            role: a.role || 'subject'
                        }));
                    console.log('Preserving assignments for other sections:', otherSectionAssignments);
                }
            } catch (error) {
                console.warn('Could not fetch current assignments:', error);
                // Continue with an empty array - we'll just assign to this section
            }

            // For primary teachers, make sure Homeroom is included
            if (selectedRole.value === 'primary') {
                console.log('Primary teacher, checking for Homeroom subject...');
                const homeroomSubject = availableSubjects.value.find(
                    s => s.name.toLowerCase() === 'homeroom'
                );

                console.log('Homeroom subject:', homeroomSubject);

                if (homeroomSubject) {
                    // Check if Homeroom is already selected
                    const hasHomeroom = selectedSubjects.value.some(s => s.id === homeroomSubject.id);

                    // If not, add it to selected subjects
                    if (!hasHomeroom) {
                        console.log('Adding Homeroom to selected subjects');
                        selectedSubjects.value.push(homeroomSubject);
                    }
                }
            }

            // Process the selected subjects for this section
            const newSectionAssignments = selectedSubjects.value.map(subject => {
                const isHomeroom = subject.name && subject.name.toLowerCase() === 'homeroom';

                return {
                    section_id: selectedSection.value.id,
                    subject_id: subject.id,
                    is_primary: selectedRole.value === 'primary' && isHomeroom,
                    role: (selectedRole.value === 'primary' && isHomeroom) ? 'primary' : 'subject'
                };
            });

            // Combine with assignments from other sections
            const allAssignments = [...otherSectionAssignments, ...newSectionAssignments];
            console.log('All assignments to send:', allAssignments);

            // Send all assignments to the API
            await axios.put(
            `${props.apiBaseUrl}/teachers/${props.teacher.id}/assignments`,
                { assignments: allAssignments }
        );

        toast.add({
            severity: 'success',
            summary: 'Success',
                detail: selectedRole.value === 'primary'
                    ? 'Teacher assigned as Primary Teacher successfully'
                : 'Teacher assigned successfully',
            life: 3000
        });
        }

        // Close dialog and emit completion event
        closeDialog();
        emit('assignment-complete', props.teacher.id);

    } catch (error) {
        console.error('Error assigning teacher:', error);

        // Extract error details for better feedback
        let errorDetail = 'Unknown error occurred';

        if (error.response) {
            // The request was made and the server responded with an error status
            console.log('Error response data:', error.response.data);

            if (error.response.status === 405) {
                errorDetail = 'Method not allowed. The API only supports PUT for teacher assignments.';
            } else if (error.response.data && error.response.data.message) {
                errorDetail = error.response.data.message;
            } else if (error.response.data && error.response.data.error) {
                errorDetail = error.response.data.error;
            } else {
                errorDetail = `Server error (${error.response.status})`;
            }

            // Check for duplicate assignment error messages
            if (typeof errorDetail === 'string' &&
                (errorDetail.includes('duplicate') ||
                 errorDetail.includes('already assigned') ||
                 errorDetail.includes('constraint') ||
                 errorDetail.includes('unique'))) {

                toast.add({
                    severity: 'error',
                    summary: 'Assignment Error',
                    detail: 'This teacher is already assigned to this section and subject',
                    life: 5000
                });

                // Close and refresh anyway
                closeDialog();
                emit('assignment-complete', props.teacher.id);
                return;
            }
        } else if (error.request) {
            // The request was made but no response was received
            errorDetail = 'No response from server. Please check your connection.';
        } else {
            // Something happened in setting up the request
            errorDetail = error.message || 'Unknown error';
        }

            toast.add({
                severity: 'error',
                summary: 'Error',
            detail: `Failed to assign teacher: ${errorDetail}`,
                life: 5000
            });

        // Always refresh the data even on error
        closeDialog();
        emit('assignment-complete', props.teacher.id);
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog :visible="visible"
           :style="{ width: '550px' }"
           :closable="false"
           :modal="true"
           class="assignment-wizard-dialog"
           @update:visible="emit('update:visible', $event)">
        <template #header>
            <div class="wizard-header">
                <h2>Assign Teacher</h2>
                <div class="step-indicator">
                    <span>Step {{ currentStep }} of {{ totalSteps }}</span>
                </div>
            </div>
        </template>

        <div class="assignment-wizard-content">
            <!-- Step 1: Role Selection -->
            <div v-if="currentStep === 1" class="wizard-step role-selection-step">
                <h3>Select Teacher Role</h3>
                <p class="step-description">Choose the role for {{ teacher.first_name }} {{ teacher.last_name }}</p>

                <div class="role-options">
                    <div v-for="role in roleOptions"
                        :key="role.value"
                        class="role-option-card"
                        :class="{ 'selected': selectedRole === role.value }"
                        @click="selectRole(role)">
                        <div class="role-icon">
                            <i :class="role.icon"></i>
                        </div>
                        <div class="role-details">
                            <h4>{{ role.label }}</h4>
                            <p>{{ role.description }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Grade Selection -->
            <div v-if="currentStep === 2" class="wizard-step grade-selection-step">
                <h3>Select Grade Level</h3>
                <p class="step-description">Choose the grade level for {{ selectedRole === 'primary' ? 'primary teacher' : 'teacher' }} assignment</p>

                <div v-if="loading" class="loader-container">
                    <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
                    <p>Loading grades...</p>
                </div>

                <div v-else-if="availableGrades.length === 0" class="empty-state">
                    <i class="pi pi-exclamation-triangle" style="font-size: 2rem; color: #f59e0b;"></i>
                    <p>No grade levels found. Please add grade levels first.</p>
                </div>

                <div v-else class="grade-options">
                    <div v-for="grade in availableGrades"
                        :key="grade.id"
                        class="grade-option-card"
                        :class="{ 'selected': selectedGrade && selectedGrade.id === grade.id }"
                        @click="selectGrade(grade)">
                        <div class="grade-icon">
                            <i class="pi pi-book"></i>
                        </div>
                        <div class="grade-details">
                            <h4>{{ grade.name }}</h4>
                            <p v-if="grade.description">{{ grade.description }}</p>
                            <p v-else>Grade level {{ grade.name }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Section Selection -->
            <div v-if="currentStep === 3" class="wizard-step section-selection-step">
                <h3>Select Section</h3>
                <p class="step-description">Choose the section for {{ selectedGrade?.name }} grade</p>

                <div v-if="loading" class="loader-container">
                    <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
                    <p>Loading sections...</p>
                </div>

                <div v-else-if="availableSections.length === 0" class="empty-state">
                    <i class="pi pi-exclamation-triangle" style="font-size: 2rem; color: #f59e0b;"></i>
                    <p>No sections found for this grade. Please add sections first.</p>
                </div>

                <div v-else class="section-options">
                    <div v-for="section in availableSections"
                        :key="section.id"
                        class="section-option-card"
                        :class="{ 'selected': selectedSection && selectedSection.id === section.id }"
                        @click="selectSection(section)">
                        <div class="section-icon">
                            <i class="pi pi-users"></i>
                        </div>
                        <div class="section-details">
                            <h4>{{ section.name }}</h4>
                            <p v-if="section.description">{{ section.description }}</p>
                            <p v-else>Section {{ section.name }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 4: Subject Selection -->
            <div v-if="currentStep === 4" class="wizard-step subject-selection-step">
                <h3>{{ props.mode === 'add-subjects' ? 'Add Subjects' : (selectedRole === 'primary' ? 'Primary Teacher Assignment' : 'Select Subjects') }}</h3>
                <p class="step-description">
                    {{ props.mode === 'add-subjects'
                       ? 'Select additional subjects for this teacher to teach in this section'
                       : (selectedRole === 'primary'
                          ? 'Primary teachers are assigned to Homeroom subject and can teach additional subjects'
                          : 'Choose the subjects you want to assign')
                    }}
                </p>

                <div v-if="loading" class="loader-container">
                    <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
                    <p>Loading subjects...</p>
                </div>

                <div v-else-if="availableSubjects.length === 0" class="empty-state">
                    <i class="pi pi-exclamation-triangle" style="font-size: 2rem; color: #f59e0b;"></i>
                    <p>No subjects found. Please add subjects first.</p>
                </div>

                <div v-else>
                    <!-- Primary teacher assignment summary when in new mode -->
                    <div v-if="selectedRole === 'primary' && props.mode === 'new'" class="primary-assignment-summary">
                        <div class="summary-card">
                            <div class="summary-row">
                                <span class="summary-label">Role:</span>
                                <span class="summary-value">Primary Teacher</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Grade:</span>
                                <span class="summary-value">{{ selectedGrade?.name }}</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Section:</span>
                                <span class="summary-value">{{ selectedSection?.name }}</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Primary:</span>
                                <span v-if="selectedSubjects.some(s => s.name.toLowerCase() === 'homeroom')" class="summary-value">
                                    <span class="homeroom-badge">Homeroom</span>
                                </span>
                                <span v-else class="summary-value" style="color: var(--red-500);">Homeroom subject not found</span>
                            </div>
                        </div>

                        <!-- Info message for primary teacher assignments -->
                        <div v-if="selectedSubjects.some(s => s.name.toLowerCase() === 'homeroom')"
                                class="primary-description"
                                style="background-color: var(--green-50); border-left-color: var(--green-500);">
                            <i class="pi pi-info-circle" style="color: var(--green-500);"></i>
                            <p style="color: var(--green-800);">
                                <strong>Primary Teacher Assignment:</strong> The Homeroom subject will be set as the primary assignment.
                                Any additional subjects below will be assigned as regular subject assignments.
                            </p>
                        </div>

                        <!-- Warning message for missing homeroom subject -->
                        <div v-else
                             class="primary-description"
                             style="background-color: var(--red-50); border-left-color: var(--red-500);">
                            <i class="pi pi-exclamation-triangle" style="color: var(--red-500);"></i>
                            <p style="color: var(--red-800);">No Homeroom subject found in the system. Please add a Homeroom subject before assigning primary teachers.</p>
                        </div>
                    </div>

                    <!-- Summary when in add-subjects mode -->
                    <div v-if="props.mode === 'add-subjects'" class="primary-assignment-summary">
                        <div class="summary-card">
                            <div class="summary-row">
                                <span class="summary-label">Teacher:</span>
                                <span class="summary-value">{{ props.teacher.first_name }} {{ props.teacher.last_name }}</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Grade:</span>
                                <span class="summary-value">{{ selectedGrade?.name }}</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Section:</span>
                                <span class="summary-value">{{ selectedSection?.name }}</span>
                            </div>
                            <div class="summary-row">
                                <span class="summary-label">Current:</span>
                                <span class="summary-value">
                                    {{ props.existingAssignments && selectedSection ?
                                        props.existingAssignments.filter(a => a.section && a.section.id === selectedSection.id).length
                                        : 0 }} subjects assigned
                                </span>
                            </div>
                        </div>

                        <div class="primary-description" style="background-color: var(--blue-50); border-left-color: var(--blue-500);">
                            <i class="pi pi-info-circle" style="color: var(--blue-500);"></i>
                            <p style="color: var(--blue-800);">Select additional subjects for this teacher to teach. Subjects that are already assigned are marked accordingly.</p>
                        </div>
                    </div>

                    <!-- Additional subjects heading - only for primary in new mode -->
                    <div v-if="selectedRole === 'primary' && props.mode === 'new'" class="additional-subjects-section">
                        <h4>Additional Teaching Subjects</h4>
                        <p class="step-description">You can assign additional subjects for this primary teacher (optional)</p>
                    </div>

                    <!-- Subject selection for all modes -->
                    <div class="subject-options">
                        <div v-for="subject in availableSubjects"
                            :key="subject.id"
                            class="subject-option-card"
                            :class="{
                                'selected': selectedSubjects.some(s => s.id === subject.id),
                                'disabled': (selectedRole === 'primary' && subject.name.toLowerCase() === 'homeroom') || subject.alreadyAssigned,
                                'already-assigned': subject.alreadyAssigned,
                                'homeroom-subject': subject.name.toLowerCase() === 'homeroom'
                            }"
                            @click="!subject.alreadyAssigned && toggleSubjectSelection(subject)">
                            <div class="subject-icon">
                                <i :class="subject.name.toLowerCase() === 'homeroom' ? 'pi pi-home' : 'pi pi-bookmark'"></i>
                            </div>
                            <div class="subject-details">
                                <h4>{{ subject.name }}</h4>
                                <p v-if="subject.description">{{ subject.description }}</p>
                                <p v-else>Subject {{ subject.name }}</p>

                                <div class="subject-badges">
                                    <span v-if="selectedRole === 'primary' && subject.name.toLowerCase() === 'homeroom'"
                                          class="primary-badge">Primary Assignment</span>
                                    <span v-else-if="selectedRole === 'primary' && selectedSubjects.some(s => s.id === subject.id)"
                                          class="secondary-badge">Additional Subject</span>
                                    <span v-if="subject.alreadyAssigned"
                                          class="already-assigned-badge">Already Assigned</span>
                                </div>
                            </div>
                            <div class="subject-checkbox">
                                <i v-if="subject.alreadyAssigned" class="pi pi-check" style="color: var(--surface-600);"></i>
                                <i v-else-if="selectedSubjects.some(s => s.id === subject.id)" class="pi pi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="wizard-footer">
                <Button v-if="currentStep > 1"
                    label="Back"
                    icon="pi pi-chevron-left"
                    class="p-button-text"
                    @click="goToPreviousStep" />

                <Button label="Cancel"
                    icon="pi pi-times"
                    class="p-button-text"
                    @click="closeDialog" />

                <Button v-if="currentStep === totalSteps"
                    label="Confirm Assignment"
                    icon="pi pi-check"
                    class="p-button-success"
                    :loading="loading"
                    @click="completeAssignment" />
            </div>
        </template>
    </Dialog>
</template>

<style scoped>
.wizard-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 1rem;
}

.wizard-header h2 {
    margin: 0 0 0.5rem 0;
    color: var(--primary-color);
}

.step-indicator {
    font-size: 0.9rem;
    color: var(--text-color-secondary);
    background-color: var(--surface-100);
    padding: 0.25rem 1rem;
    border-radius: 1rem;
}

.assignment-wizard-content {
    min-height: 300px;
    padding: 1rem 0;
}

.wizard-step {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.wizard-step h3 {
    margin: 0;
    color: var(--text-color);
    font-size: 1.2rem;
}

.step-description {
    color: var(--text-color-secondary);
    margin: 0 0 1rem 0;
}

/* Role selection styles */
.role-options {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.role-option-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 1.5rem 1rem;
    border-radius: 8px;
    border: 1px solid var(--surface-200);
    cursor: pointer;
    transition: all 0.2s ease;
}

.role-option-card:hover {
    background-color: var(--surface-50);
    transform: translateY(-3px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.role-option-card.selected {
    background-color: var(--primary-50);
    border-color: var(--primary-300);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.role-icon {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 1rem;
}

.role-details {
    text-align: center;
}

.role-details h4 {
    margin: 0 0 0.5rem 0;
    color: var(--text-color);
}

.role-details p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-color-secondary);
}

/* Grade, Section, Subject selection styles */
.grade-options,
.section-options,
.subject-options {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    max-height: 300px;
    overflow-y: auto;
    padding: 0.5rem;
}

.grade-option-card,
.section-option-card,
.subject-option-card {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--surface-200);
    cursor: pointer;
    transition: all 0.2s ease;
}

.grade-option-card:hover,
.section-option-card:hover,
.subject-option-card:hover {
    background-color: var(--surface-50);
}

.grade-option-card.selected,
.section-option-card.selected,
.subject-option-card.selected {
    background-color: var(--primary-50);
    border-color: var(--primary-300);
}

.grade-icon,
.section-icon,
.subject-icon {
    font-size: 1.5rem;
    color: var(--primary-color);
    margin-right: 1rem;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--surface-100);
    border-radius: 50%;
}

.grade-details,
.section-details,
.subject-details {
    flex: 1;
}

.grade-details h4,
.section-details h4,
.subject-details h4 {
    margin: 0 0 0.25rem 0;
    color: var(--text-color);
}

.grade-details p,
.section-details p,
.subject-details p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-color-secondary);
}

/* Subject checkbox */
.subject-checkbox {
    font-size: 1.2rem;
    color: var(--primary-color);
}

/* Primary assignment summary */
.primary-assignment-summary {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.summary-card {
    background-color: var(--surface-50);
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid var(--surface-200);
}

.summary-row {
    display: flex;
    margin-bottom: 0.75rem;
    align-items: center;
}

.summary-row:last-child {
    margin-bottom: 0;
}

.summary-label {
    width: 80px;
    font-weight: 600;
    color: var(--text-color-secondary);
}

.summary-value {
    font-weight: 500;
    color: var(--text-color);
}

.primary-description {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    background-color: var(--blue-50);
    border-radius: 8px;
    border-left: 4px solid var(--blue-500);
}

.primary-description i {
    color: var(--blue-500);
    font-size: 1.2rem;
}

.primary-description p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--blue-900);
}

/* Loading and empty states */
.loader-container,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;
}

.loader-container p,
.empty-state p {
    margin: 1rem 0 0 0;
    color: var(--text-color-secondary);
}

/* Footer */
.wizard-footer {
    display: flex;
    justify-content: space-between;
    padding-top: 1rem;
}

/* Responsive design */
@media (max-width: 600px) {
    .role-options {
        grid-template-columns: 1fr;
    }
}

/* Subject option card disabled state */
.subject-option-card.disabled {
    opacity: 0.8;
    background-color: var(--surface-100);
    cursor: default;
}

.subject-option-card.disabled:hover {
    background-color: var(--surface-100);
}

/* Subject badges */
.subject-badges {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.5rem;
    flex-wrap: wrap;
}

/* Primary badge */
.primary-badge {
    display: inline-block;
    background-color: var(--primary-color);
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 1rem;
}

/* Already assigned badge */
.already-assigned-badge {
    display: inline-block;
    background-color: var(--surface-400);
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 1rem;
}

/* Already assigned subject card */
.subject-option-card.already-assigned {
    background-color: var(--surface-50);
    border-color: var(--surface-300);
    opacity: 0.75;
}

.subject-option-card.already-assigned:hover {
    background-color: var(--surface-50);
    cursor: default;
}

/* Additional subjects section */
.additional-subjects-section {
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

.additional-subjects-section h4 {
    margin: 0 0 0.5rem 0;
    color: var(--text-color);
    font-size: 1.1rem;
}

.additional-subjects-section p {
    margin: 0 0 1rem 0;
    color: var(--text-color-secondary);
    font-size: 0.9rem;
}

.homeroom-badge {
    display: inline-block;
    background-color: var(--green-500);
    color: white;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 1rem;
}

/* Homeroom subject card */
.subject-option-card.homeroom-subject {
    border-left: 4px solid var(--green-500);
    background-color: var(--green-50);
}

.subject-option-card.homeroom-subject.selected {
    background-color: var(--green-100);
    border-color: var(--green-500);
}

/* Secondary badge */
.secondary-badge {
    display: inline-block;
    background-color: var(--blue-500);
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.15rem 0.5rem;
    border-radius: 1rem;
}
</style>

