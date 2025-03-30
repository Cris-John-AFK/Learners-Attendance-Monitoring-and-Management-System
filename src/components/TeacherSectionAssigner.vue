<script setup>
import axios from 'axios';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
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
    }
});

// Emits
const emit = defineEmits(['update:visible', 'section-assigned']);

// Component state
const toast = useToast();
const loading = ref(false);
const availableSections = ref([]);
const availableRoles = ref([
    { name: 'Subject Teacher', value: 'subject' },
    { name: 'Special Needs Teacher', value: 'special_needs' },
    { name: 'Co-Teacher', value: 'co_teacher' },
    { name: 'Counselor', value: 'counselor' }
]);
const selectedSection = ref(null);
const selectedRole = ref(null);
const currentAssignments = ref([]);

// Watch for dialog visibility
watch(() => props.visible, async (newValue) => {
    if (newValue) {
        resetState();
        await loadTeacherData();
        await loadAvailableSections();
    }
});

// Methods
const resetState = () => {
    availableSections.value = [];
    selectedSection.value = null;
    selectedRole.value = availableRoles.value[0]; // Default to Subject Teacher
    currentAssignments.value = [];
    loading.value = false;
};

const closeDialog = () => {
    emit('update:visible', false);
};

// Load teacher data including current assignments
const loadTeacherData = async () => {
    try {
        loading.value = true;

        // Get teacher data with current assignments
        const response = await axios.get(
            `${props.apiBaseUrl}/teachers/${props.teacher.id}?_=${new Date().getTime()}`
        );

        if (!response.data) {
            throw new Error('Failed to load teacher data');
        }

        const teacherData = response.data;

        // Process assignments
        if (teacherData.assignments && Array.isArray(teacherData.assignments)) {
            currentAssignments.value = teacherData.assignments.map(a => ({
                id: a.id,
                section_id: Number(a.section_id),
                section_name: a.section_name || a.section?.name,
                subject_id: Number(a.subject_id),
                subject_name: a.subject_name || a.subject?.name,
                is_primary: a.is_primary === true,
                role: a.role || 'subject'
            }));
        }
    } catch (error) {
        console.error('Error loading teacher data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load teacher data',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Load available sections
const loadAvailableSections = async () => {
    try {
        loading.value = true;

        // Get all sections
        const response = await axios.get(`${props.apiBaseUrl}/sections/active`);

        if (!response.data) {
            throw new Error('Failed to load sections');
        }

        const allSections = response.data;

        // Get sections this teacher is already assigned to
        const assignedSectionIds = currentAssignments.value.map(a => a.section_id);

        // Filter out sections the teacher is already assigned to
        availableSections.value = allSections
            .filter(section => !assignedSectionIds.includes(Number(section.id)))
            .map(section => ({
                id: section.id,
                name: section.name,
                grade: section.grade?.name || 'Unknown Grade',
                display_name: `${section.name} (${section.grade?.name || 'Unknown Grade'})`
            }));

    } catch (error) {
        console.error('Error loading sections:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load sections',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Save section assignment
const saveAssignment = async () => {
    if (!selectedSection.value || !selectedRole.value) {
        toast.add({
            severity: 'info',
            summary: 'Selection Required',
            detail: 'Please select a section and role',
            life: 3000
        });
        return;
    }

    try {
        loading.value = true;

        // Get current teacher data
        const teacherResponse = await axios.get(
            `${props.apiBaseUrl}/teachers/${props.teacher.id}`
        );

        if (!teacherResponse.data) {
            throw new Error('Failed to load current teacher data');
        }

        const currentTeacherData = teacherResponse.data;
        const currentAssignmentsFromServer = currentTeacherData.assignments || [];

        // Create new assignment
        const newAssignment = {
            section_id: selectedSection.value.id,
            subject_id: null, // No subject assigned initially
            is_primary: false,
            role: selectedRole.value.value
        };

        // Add to existing assignments
        const updatedAssignments = [...currentAssignmentsFromServer, newAssignment];

        // Create the payload
        const payload = {
            ...currentTeacherData,
            assignments: updatedAssignments
        };

        // Remove unnecessary fields
        delete payload.created_at;
        delete payload.updated_at;

        // Send the update
        const updateResponse = await axios.put(
            `${props.apiBaseUrl}/teachers/${props.teacher.id}`,
            payload
        );

        // Success notification
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Teacher assigned to ${selectedSection.value.name} as ${selectedRole.value.name}`,
            life: 3000
        });

        // Close dialog and notify parent component
        closeDialog();
        setTimeout(() => {
            emit('section-assigned', props.teacher.id);
        }, 500);
    } catch (error) {
        console.error('Error assigning section:', error);

        let errorMessage = 'Failed to assign section';
        if (error.response && error.response.data && error.response.data.message) {
            errorMessage = error.response.data.message;
        }

        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: errorMessage,
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog
        :visible="visible"
        @update:visible="closeDialog"
        modal
        header="Assign to Additional Section"
        :style="{ width: '520px' }"
        :closable="!loading"
        :closeOnEscape="!loading"
        class="teacher-section-assigner-dialog"
    >
        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center py-4">
            <i class="pi pi-spin pi-spinner text-2xl text-blue-500"></i>
        </div>

        <div v-else>
            <!-- Instructions -->
            <div class="flex items-start my-4">
                <i class="pi pi-info-circle text-blue-500 mr-2"></i>
                <div class="text-sm text-gray-700">
                    Assign this teacher to an additional section. They will not be the primary teacher of this section.
                </div>
            </div>

            <!-- Current Section Assignments -->
            <h3 class="text-lg font-medium mb-2">Current Assignments</h3>
            <div class="border rounded-md mb-5 bg-white overflow-hidden">
                <div v-for="assignment in currentAssignments" :key="assignment.id"
                    class="flex items-center border-b last:border-b-0 p-3 relative overflow-hidden section-item"
                    :class="{'primary-section': assignment.is_primary}">

                    <div class="flex-grow">
                        <div class="font-medium">{{ assignment.section_name }}</div>
                        <div class="text-sm text-gray-600">
                            {{ assignment.is_primary ? 'Primary Teacher' : 'Role: ' +
                            (assignment.role === 'subject' ? 'Subject Teacher' :
                             assignment.role === 'special_needs' ? 'Special Needs Teacher' :
                             assignment.role === 'co_teacher' ? 'Co-Teacher' :
                             assignment.role === 'counselor' ? 'Counselor' : assignment.role) }}
                        </div>
                    </div>

                    <div v-if="assignment.is_primary"
                        class="ml-auto px-2 py-1 bg-indigo-100 text-indigo-800 text-xs font-semibold rounded">
                        Primary
                    </div>
                    <div v-else
                        class="ml-auto px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded">
                        {{ assignment.subject_name ? 'Subject' : assignment.role }}
                    </div>

                    <!-- Animation background elements -->
                    <div class="section-flowing-animation"></div>

                    <!-- Section animation elements -->
                    <div class="section-animation">
                        <span class="section-symbol">👥</span>
                        <span class="section-symbol">📚</span>
                        <span class="section-symbol">🎓</span>
                        <span class="section-symbol">✏️</span>
                    </div>
                </div>

                <div v-if="currentAssignments.length === 0"
                    class="text-center p-3 text-gray-500 italic">
                    No sections currently assigned
                </div>
            </div>

            <!-- Section Selection -->
            <h3 class="text-lg font-medium mb-2">Assign New Section</h3>
            <div class="mb-4">
                <label for="section" class="block text-sm font-medium text-gray-700 mb-1 section-selection-label">
                    Select Section
                </label>
                <Dropdown
                    id="section"
                    v-model="selectedSection"
                    :options="availableSections"
                    optionLabel="display_name"
                    placeholder="Select a section"
                    class="w-full"
                />
            </div>

            <!-- Role Selection -->
            <div class="mb-4">
                <label for="role" class="block text-sm font-medium text-gray-700 mb-1 section-selection-label">
                    Select Role
                </label>
                <Dropdown
                    id="role"
                    v-model="selectedRole"
                    :options="availableRoles"
                    optionLabel="name"
                    placeholder="Select a role"
                    class="w-full"
                />
            </div>

            <p class="text-sm text-gray-600 italic mt-2">
                Note: After assigning to a section, you can add specific subjects using the 'Add Subjects' button.
            </p>
        </div>

        <!-- Footer actions -->
        <template #footer>
            <div class="flex justify-end gap-3 py-2">
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    @click="closeDialog"
                    class="p-button-text"
                    :disabled="loading"
                />
                <Button
                    label="Assign Section"
                    icon="pi pi-check"
                    @click="saveAssignment"
                    :disabled="!selectedSection || !selectedRole || loading"
                    :loading="loading"
                    class="p-button-success"
                />
            </div>
        </template>
    </Dialog>
</template>

<style scoped>
.teacher-section-assigner-dialog :deep(.p-dialog-header) {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.teacher-section-assigner-dialog :deep(.p-dialog-content) {
    padding: 1.5rem 1.5rem 1.75rem;
}

.teacher-section-assigner-dialog :deep(.p-dialog-footer) {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.teacher-section-assigner-dialog :deep(.p-dropdown) {
    width: 100%;
}

/* Section items styling */
.section-item {
    padding: 1rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.primary-section {
    background-color: #EEF2FF;
}

/* Flowing animation for sections */
.section-flowing-animation {
    position: absolute;
    top: 0;
    right: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg,
        rgba(255,255,255,0) 0%,
        rgba(255,255,255,0.1) 25%,
        rgba(255,255,255,0.2) 50%,
        rgba(255,255,255,0.1) 75%,
        rgba(255,255,255,0) 100%);
    transform: translateX(-100%);
    animation: flow 3s infinite;
    z-index: 1;
}

@keyframes flow {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

/* Section animation */
.section-animation {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    pointer-events: none;
    overflow: hidden;
    opacity: 0.5;
    z-index: 1;
}

.section-symbol {
    position: absolute;
    font-size: 0.9rem;
    opacity: 0;
    animation: float 8s infinite;
}

.section-symbol:nth-child(1) {
    top: 20%;
    left: 80%;
    animation-delay: 0s;
}

.section-symbol:nth-child(2) {
    top: 50%;
    left: 85%;
    animation-delay: 2s;
    font-size: 1rem;
}

.section-symbol:nth-child(3) {
    top: 30%;
    left: 90%;
    animation-delay: 4s;
    font-size: 0.9rem;
}

.section-symbol:nth-child(4) {
    top: 60%;
    left: 75%;
    animation-delay: 6s;
    font-size: 0.8rem;
}

@keyframes float {
    0% {
        transform: translate(0, 0) rotate(0deg);
        opacity: 0;
    }
    10% {
        opacity: 0.7;
    }
    50% {
        transform: translate(-25px, -15px) rotate(5deg);
        opacity: 0.5;
    }
    90% {
        opacity: 0.7;
    }
    100% {
        transform: translate(-50px, -25px) rotate(0deg);
        opacity: 0;
    }
}

/* Enhanced dropdown styling */
.teacher-section-assigner-dialog :deep(.p-dropdown) {
    border-color: #d1d5db;
    transition: all 0.3s ease;
}

.teacher-section-assigner-dialog :deep(.p-dropdown:hover),
.teacher-section-assigner-dialog :deep(.p-dropdown.p-focus) {
    border-color: #3B82F6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    transform: translateY(-2px);
}

/* Animated label */
.section-selection-label {
    display: inline-block;
    position: relative;
    overflow: hidden;
}

.section-selection-label::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, #3B82F6, #6366F1, #8B5CF6, #3B82F6);
    background-size: 300% 100%;
    animation: gradient-shift 3s ease infinite;
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.6s cubic-bezier(0.65, 0.05, 0.36, 1);
}

.section-selection-label:hover::after {
    transform: scaleX(1);
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
</style>
