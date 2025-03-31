<script setup>
import axios from 'axios';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import { useToast } from 'primevue/usetoast';
import { onMounted, ref, watch } from 'vue';

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
const emit = defineEmits(['update:visible', 'subject-added']);

// Component state
const toast = useToast();
const loading = ref(false);
const availableSubjects = ref([]);
const selectedSubjects = ref([]);
const currentAssignments = ref([]);
const primaryAssignment = ref(null);
const loadingSubjects = ref(new Set());
const availableSections = ref([]);
const selectedSectionId = ref(null);
const homeroom_assignments = ref([]);
// Watch for dialog visibility
watch(() => props.visible, async (newValue) => {
    if (newValue) {
        resetState();
        await loadTeacherData();
    }
});

// Methods
const resetState = () => {
    availableSubjects.value = [];
    selectedSubjects.value = [];
    currentAssignments.value = [];
    primaryAssignment.value = null;
    availableSections.value = [];
    selectedSectionId.value = null;
    loading.value = false;
};
// Add this method
const loadHomeroomAssignments = async () => {
    try {
        // Fetch all homeroom assignments
        const response = await axios.get(`${props.apiBaseUrl}/subjects/homeroom/assignments`);
        if (response.data) {
            homeroom_assignments.value = response.data;
        }
    } catch (error) {
        console.error('Error loading homeroom assignments:', error);
    }
};
const closeDialog = () => {
    emit('update:visible', false);
    // Give the parent component time to refresh data
    setTimeout(() => {
        emit('subject-added', props.teacher.id);
    }, 100);
};

// Load teacher data including current assignments
const loadTeacherData = async () => {
    try {
        loading.value = true;

        // Get teacher data with current assignments
        const teacherResponse = await axios.get(
            `${props.apiBaseUrl}/teachers/${props.teacher.id}?_=${new Date().getTime()}`
        );

        if (!teacherResponse.data) {
            throw new Error('Failed to load teacher data');
        }

        const teacherData = teacherResponse.data;

        // Process assignments and extract sections
        if (teacherData.assignments && Array.isArray(teacherData.assignments)) {
            // Process all assignments
            currentAssignments.value = teacherData.assignments.map(a => ({
                id: a.id,
                section_id: Number(a.section_id),
                section_name: a.section_name || a.section?.name,
                subject_id: Number(a.subject_id),
                subject_name: a.subject_name || a.subject?.name,
                is_primary: a.is_primary === true,
                role: a.role || 'subject'
            }));

            // Find primary assignment
            primaryAssignment.value = currentAssignments.value.find(a => a.is_primary || a.role === 'primary');

            // Try to get section name from teacher data if not in assignment
            if (primaryAssignment.value && !primaryAssignment.value.section_name) {
                // Try to get section name from teacher primary section
                if (teacherData.primary_section && teacherData.primary_section.name) {
                    primaryAssignment.value.section_name = teacherData.primary_section.name;
                } else if (props.teacher.section && props.teacher.section.name) {
                    primaryAssignment.value.section_name = props.teacher.section.name;
                } else if (props.teacher.section_name) {
                    primaryAssignment.value.section_name = props.teacher.section_name;
                }
            }

            // Extract unique sections this teacher is assigned to
            const uniqueSections = new Map();
            currentAssignments.value.forEach(assignment => {
                if (!uniqueSections.has(assignment.section_id)) {
                    uniqueSections.set(assignment.section_id, {
                        id: assignment.section_id,
                        name: assignment.section_name,
                        is_primary: assignment.is_primary || assignment.role === 'primary'
                    });
                }
            });

            // Convert to array and add display name
            availableSections.value = Array.from(uniqueSections.values()).map(section => ({
                ...section,
                display_name: `${section.name}${section.is_primary ? ' (Primary)' : ''}`
            }));

            // If there's at least one section
            if (availableSections.value.length > 0) {
                // Default to primary section if it exists, otherwise first section
                const primarySection = availableSections.value.find(s => s.is_primary);
                selectedSectionId.value = primarySection ? primarySection.id : availableSections.value[0].id;

                // Load subjects for selected section
                await loadAvailableSubjects();
            } else {
                toast.add({
                    severity: 'error',
                    summary: 'No Sections',
                    detail: 'Teacher is not assigned to any section yet',
                    life: 4000
                });
                closeDialog();
            }
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

// Add watch for section change
watch(() => selectedSectionId.value, async (newValue) => {
    if (newValue) {
        await loadAvailableSubjects();
    }
});

// Load available subjects
const loadAvailableSubjects = async () => {
    try {
        if (!selectedSectionId.value) {
            return;
        }

        loading.value = true;
        const response = await axios.get(`${props.apiBaseUrl}/subjects`);

        if (!response.data) {
            throw new Error('Failed to load subjects');
        }

        // Get all subjects
        const allSubjects = response.data;

        // Get current section assignments
        const currentSectionAssignments = currentAssignments.value
            .filter(a => a.section_id === selectedSectionId.value);

        // Get subject IDs already assigned to this section
        const currentSectionSubjectIds = currentSectionAssignments
            .map(a => a.subject_id)
            .filter(id => id); // Filter out null/undefined

        // Process subjects for display
        availableSubjects.value = allSubjects.map(subject => {
            const isHomeroom = subject.name.toLowerCase() === 'homeroom';
            const alreadyAssigned = currentSectionSubjectIds.includes(Number(subject.id));

            // If it's homeroom, check if any teacher has it assigned
            let isHomeroomAssigned = false;
            if (isHomeroom) {
                // Check if this subject is already assigned to this section (by any teacher)
                isHomeroomAssigned = homeroom_assignments.some(
                    a => a.section_id === selectedSectionId.value &&
                         a.subject_id === Number(subject.id)
                );
            }

            return {
                ...subject,
                alreadyAssigned: alreadyAssigned,
                disabled: isHomeroom && isHomeroomAssigned && !alreadyAssigned,
                isPrimary: currentSectionAssignments.some(a =>
                    a.subject_id === Number(subject.id) && (a.is_primary || a.role === 'primary')
                )
            };
        });

    } catch (error) {
        console.error('Error loading subjects:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load subjects',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Toggle subject selection
const toggleSubjectSelection = (subject) => {
    // Skip primary and already assigned subjects
    if (subject.isPrimary || subject.alreadyAssigned) {
        return;
    }

    // Toggle selection
    const index = selectedSubjects.value.findIndex(s => s.id === subject.id);
    if (index === -1) {
        selectedSubjects.value.push(subject);
    } else {
        selectedSubjects.value.splice(index, 1);
    }
};

// Save selected subjects
const saveSubjects = async () => {
    // Skip if nothing selected
    if (selectedSubjects.value.length === 0) {
        toast.add({
            severity: 'info',
            summary: 'No Subjects Selected',
            detail: 'Please select at least one subject to add',
            life: 3000
        });
        return;
    }

    try {
        loading.value = true;
        const teacherId = props.teacher.id;
        const sectionId = selectedSectionId.value;

        console.log(`Adding subjects for Teacher ID=${teacherId}, Section ID=${sectionId}`);

        // Get current teacher data to see existing assignments
        const teacherResponse = await axios.get(`${props.apiBaseUrl}/teachers/${teacherId}`);
        if (!teacherResponse.data) {
            throw new Error('Failed to get current teacher data');
        }

        const teacherData = teacherResponse.data;

        // Extract existing assignments and format them properly
        const existingAssignments = [];
        if (teacherData.assignments && Array.isArray(teacherData.assignments)) {
            teacherData.assignments.forEach(assignment => {
                // Only include assignments with proper IDs to avoid duplication
                if (assignment.id) {
                    existingAssignments.push({
                        id: assignment.id,
                        teacher_id: Number(teacherId),
                        section_id: Number(assignment.section_id),
                        subject_id: Number(assignment.subject_id),
                        is_primary: assignment.is_primary === true,
                        is_active: assignment.is_active !== false,
                        role: assignment.role || 'subject'
                    });
                }
            });
        }

        console.log('Existing assignments:', existingAssignments);

        // Create new assignments for selected subjects
        const newAssignments = [];
        for (const subject of selectedSubjects.value) {
            // Check if this assignment already exists
            const exists = existingAssignments.some(existing =>
                existing.section_id === Number(sectionId) &&
                existing.subject_id === Number(subject.id)
            );

            // Only add if it doesn't exist
            if (!exists) {
                newAssignments.push({
                    teacher_id: Number(teacherId),
                    section_id: Number(sectionId),
                    subject_id: Number(subject.id),
                    is_primary: false,
                    is_active: true,
                    role: 'subject'
                });
            }
        }

        console.log('New assignments to add:', newAssignments);

        // If all selected subjects already exist, nothing to do
        if (newAssignments.length === 0) {
            toast.add({
                severity: 'info',
                summary: 'No Changes',
                detail: 'These subjects are already assigned to the teacher',
                life: 3000
            });
            loading.value = false;
            return;
        }

        // Combine existing and new assignments
        const updatedAssignments = [...existingAssignments, ...newAssignments];

        // Send the update using the standard Laravel endpoint
        const response = await axios.put(
            `${props.apiBaseUrl}/teachers/${teacherId}/assignments`,
            { assignments: updatedAssignments }
        );

        console.log('Update response:', response.data);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Subjects added successfully',
            life: 3000
        });

        // Reload the page to show changes
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    } catch (error) {
        console.error('Error saving subjects:', error);
        let errorMessage = 'Failed to add subjects';

        if (error.response) {
            console.error('Response error data:', error.response.data);
            if (error.response.data && error.response.data.message) {
                errorMessage = error.response.data.message;
            }
        }

        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: errorMessage,
            life: 5000
        });
    } finally {
        loading.value = false;
    }
};

// Direct method as a last resort
const directlyAddSubjects = async () => {
    const teacherId = props.teacher.id;
    const sectionId = selectedSectionId.value;

    // Create an array to track results
    const results = {
        success: false,
        totalAttempted: selectedSubjects.value.length,
        successCount: 0,
        errors: []
    };

    // Process each subject individually
    for (const subject of selectedSubjects.value) {
        try {
            // Try a simpler, direct approach using axios
            await axios.post(`${props.apiBaseUrl}/manual-assignment`, {
                teacher_id: Number(teacherId),
                section_id: Number(sectionId),
                subject_id: Number(subject.id),
                is_primary: false,
                is_active: true,
                role: 'subject'
            });

            results.successCount++;
        } catch (error) {
            console.error(`Failed to add subject ${subject.name}:`, error);
            results.errors.push({
                subject: subject.name,
                error: error.message
            });
        }
    }

    // Set overall success flag if at least one subject was added
    results.success = results.successCount > 0;
    return results;
};

// Helper functions
const getSubjectColorClass = (subjectName) => {
    const colorMapping = {
        'Homeroom': 'bg-indigo-100 text-indigo-800',
        'Math': 'bg-blue-100 text-blue-800',
        'Science': 'bg-green-100 text-green-800',
        'English': 'bg-purple-100 text-purple-800',
        'History': 'bg-amber-100 text-amber-800',
        'Art': 'bg-pink-100 text-pink-800',
        'Music': 'bg-orange-100 text-orange-800',
        'Physical Education': 'bg-emerald-100 text-emerald-800',
        'Foreign Language': 'bg-cyan-100 text-cyan-800',
        'Social Studies': 'bg-amber-100 text-amber-800',
        'Computer Science': 'bg-gray-100 text-gray-800'
    };

    return colorMapping[subjectName] || 'bg-gray-100 text-gray-800';
};

const getSubjectIcon = (subjectName) => {
    const iconMapping = {
        'Homeroom': 'pi-home',
        'Math': 'pi-calculator',
        'Science': 'pi-flask',
        'English': 'pi-book',
        'History': 'pi-clock',
        'Art': 'pi-palette',
        'Music': 'pi-music',
        'Physical Education': 'pi-heart',
        'Foreign Language': 'pi-globe',
        'Social Studies': 'pi-users',
        'Computer Science': 'pi-desktop'
    };

    return iconMapping[subjectName] || 'pi-book';
};
// Call this in your onMounted hook
onMounted(async () => {
    await loadHomeroomAssignments();
    // If the dialog is visible when mounted, load the teacher data
    if (props.visible) {
        resetState();
        await loadTeacherData();
    }
});
</script>

<template>
    <Dialog
        :visible="visible"
        @update:visible="closeDialog"
        modal
        header="Add Teaching Subjects"
        :style="{ width: '520px' }"
        :closable="!loading"
        :closeOnEscape="!loading"
        class="teacher-subject-adder-dialog"
    >
        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center py-4">
            <i class="pi pi-spin pi-spinner text-2xl text-blue-500"></i>
        </div>

        <div v-else>
            <!-- Section Selector (if multiple sections) -->
            <div v-if="availableSections.length > 1" class="mb-4">
                <h3 class="text-lg font-medium mb-2">Select Section</h3>
                <label for="section-dropdown" class="block text-sm font-medium text-gray-700 mb-1 section-selection-label">
                    Teaching Section
                </label>
                <Dropdown
                    id="section-dropdown"
                    v-model="selectedSectionId"
                    :options="availableSections"
                    optionLabel="display_name"
                    optionValue="id"
                    placeholder="Select section"
                    class="w-full"
                />
            </div>

            <!-- Current Section Info -->
            <div class="p-3 bg-blue-50 rounded-md mt-3">
                <div class="flex items-center text-blue-700">
                    <i class="pi pi-user-edit mr-2"></i>
                    <span>Section: {{ availableSections.find(s => s.id === selectedSectionId)?.name || 'Loading...' }}</span>
                </div>
            </div>

            <!-- Instructions -->
            <div class="flex items-start my-4">
                <i class="pi pi-info-circle text-blue-500 mr-2"></i>
                <div class="text-sm text-gray-700">
                    Select additional subjects for this teacher to teach in the selected section. Already assigned subjects cannot be selected.
                </div>
            </div>

            <!-- Current Assignments -->
            <h3 class="text-lg font-medium mb-2">Current Assignments</h3>
            <div class="border rounded-md mb-5 bg-white overflow-hidden shadow-sm">
                <div v-for="subject in availableSubjects.filter(s => s.alreadyAssigned || s.isPrimary)"
                    :key="subject.id"
                    class="flex items-center relative overflow-hidden border-b last:border-b-0 subject-item"
                    :class="subject.name === 'Math' ? 'math-subject' : 'homeroom-subject'">

                    <div class="subject-icon">
                        <i class="pi"
                           :class="getSubjectIcon(subject.name)">
                        </i>
                    </div>

                    <span class="subject-name">{{ subject.name }}</span>

                    <div class="ml-auto">
                        <span v-if="subject.isPrimary"
                            class="primary-badge">
                            Primary
                        </span>
                        <span v-else
                            class="assigned-badge">
                            Assigned
                        </span>
                    </div>

                    <!-- Animation background elements for assigned subjects -->
                    <div class="subject-flowing-animation"></div>

                    <!-- Math animation -->
                    <div v-if="subject.name === 'Math'" class="math-animation">
                        <span class="math-symbol">+</span>
                        <span class="math-symbol">-</span>
                        <span class="math-symbol">×</span>
                        <span class="math-symbol">÷</span>
                        <span class="math-symbol">π</span>
                        <span class="math-symbol">√</span>
                        <span class="math-symbol">∑</span>
                        <span class="math-symbol">∫</span>
                    </div>
                </div>

                <div v-if="availableSubjects.filter(s => s.alreadyAssigned || s.isPrimary).length === 0"
                    class="text-center p-3 text-gray-500 italic">
                    No subjects currently assigned to this section
                </div>
            </div>

            <!-- Available Subjects -->
            <h3 class="text-lg font-medium mb-2">Available Subjects</h3>
            <div class="border rounded-md mb-4 bg-white overflow-hidden shadow-sm">
                <div
                    v-for="subject in availableSubjects.filter(s => !s.alreadyAssigned && !s.isPrimary)"
                    :key="subject.id"
                    :class="{
                        'selected-subject': selectedSubjects.some(s => s.id === subject.id),
                        'already-assigned': subject.alreadyAssigned,
                        'primary': subject.isPrimary,
                        'disabled': subject.disabled
                    }"
                    @click="!subject.disabled && toggleSubjectSelection(subject)"
                    class="flex items-center cursor-pointer relative overflow-hidden border-b last:border-b-0 subject-item selectable-subject"
                >
                    <!-- Selection checkmark with prominent animation -->
                    <div class="select-checkbox mr-3"
                         :class="{'selected': selectedSubjects.some(s => s.id === subject.id)}">
                        <i class="pi pi-check check-icon"></i>
                    </div>

                    <div class="subject-icon"
                         :class="{'pulse-animation': selectedSubjects.some(s => s.id === subject.id)}">
                        <i class="pi" :class="getSubjectIcon(subject.name)"></i>
                    </div>

                    <span class="subject-name">{{ subject.name }}</span>

                    <!-- Disabled message for Homeroom -->
                    <div v-if="subject.disabled" class="disabled-message ml-auto">
                        (Already assigned to another teacher)
                    </div>

                    <!-- Selection animation -->
                    <div class="selection-ripple"
                         :class="{'active': selectedSubjects.some(s => s.id === subject.id)}"></div>

                    <!-- Math animation -->
                    <div v-if="subject.name === 'Math'" class="math-animation">
                        <span class="math-symbol">+</span>
                        <span class="math-symbol">-</span>
                        <span class="math-symbol">×</span>
                        <span class="math-symbol">÷</span>
                        <span class="math-symbol">π</span>
                        <span class="math-symbol">√</span>
                        <span class="math-symbol">∑</span>
                        <span class="math-symbol">∫</span>
                    </div>
                </div>

                <div v-if="availableSubjects.filter(s => !s.alreadyAssigned && !s.isPrimary).length === 0"
                    class="text-center p-3 text-gray-500 italic">
                    No additional subjects available for this section
                </div>
            </div>
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
                    label="Add Subjects"
                    icon="pi pi-check"
                    @click="saveSubjects"
                    :disabled="selectedSubjects.length === 0 || loading"
                    :loading="loading"
                    class="p-button-success"
                />
            </div>
        </template>
    </Dialog>
</template>

<style scoped>
.teacher-subject-adder-dialog :deep(.p-dialog-header) {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.teacher-subject-adder-dialog :deep(.p-dialog-content) {
    padding: 1.5rem 1.5rem 1.75rem;
}

.teacher-subject-adder-dialog :deep(.p-dialog-footer) {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
}

/* Subject items styling */
.subject-item {
    padding: 1rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.subject-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 100%;
    margin-right: 0.875rem;
    transition: all 0.3s ease;
    position: relative;
    z-index: 2;
}

.subject-name {
    font-weight: 500;
    font-size: 1rem;
    transition: all 0.3s ease;
    position: relative;
    z-index: 2;
}

/* Math subject styling */
.math-subject {
    background-color: #EFF6FF;
}

.math-subject .subject-icon {
    background-color: rgba(59, 130, 246, 0.15);
    color: #3B82F6;
}

/* Homeroom subject styling */
.homeroom-subject {
    background-color: #EEF2FF;
}

.homeroom-subject .subject-icon {
    background-color: rgba(99, 102, 241, 0.15);
    color: #6366F1;
}

/* Badge styling */
.primary-badge, .assigned-badge {
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 9999px;
    color: white;
    position: relative;
    z-index: 2;
}

.primary-badge {
    background-color: #6366F1;
}

.assigned-badge {
    background-color: #3B82F6;
}

/* Selectable subjects styling */
.selectable-subject {
    background-color: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.selectable-subject:hover {
    background-color: #F0F9FF;
    transform: translateX(3px);
    box-shadow: 0 0 15px rgba(59, 130, 246, 0.15);
}

.selectable-subject .subject-icon {
    background-color: rgba(107, 114, 128, 0.1);
    color: #6B7280;
}

.selected-subject {
    background-color: #F0F9FF;
    border-left: 4px solid #3B82F6;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.selected-subject .subject-icon {
    background-color: rgba(59, 130, 246, 0.15);
    color: #3B82F6;
    transform: scale(1.1);
}

.selected-subject .subject-name {
    color: #3B82F6;
    font-weight: 600;
}

/* Enhanced checkmark UI */
.select-checkbox {
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    border: 2px solid #d1d5db;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
    z-index: 2;
    overflow: hidden;
}

.select-checkbox.selected {
    border-color: #3B82F6;
    background-color: #3B82F6;
    transform: scale(1.1);
}

.check-icon {
    color: white;
    font-size: 0.75rem;
    transform: scale(0);
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.select-checkbox.selected .check-icon {
    transform: scale(1);
    opacity: 1;
}

/* Advanced animations */
@keyframes pulse {
    0% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.5);
    }
    70% {
        transform: scale(1.05);
        box-shadow: 0 0 0 10px rgba(59, 130, 246, 0);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
    }
}

.pulse-animation {
    animation: pulse 2s infinite;
}

/* Selection ripple effect */
.selection-ripple {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1;
    overflow: hidden;
    pointer-events: none;
}

.selection-ripple.active::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.2) 0%, transparent 70%);
    transform: scale(0);
    animation: ripple 1.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

@keyframes ripple {
    0% {
        transform: scale(0.8);
        opacity: 1;
    }
    100% {
        transform: scale(2);
        opacity: 0;
    }
}

/* Flowing animation for assigned subjects */
.subject-flowing-animation {
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

/* Math animation enhancement */
.math-animation {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    pointer-events: none;
    overflow: hidden;
    opacity: 0.7;
    z-index: 1;
}

.math-symbol {
    position: absolute;
    font-size: 0.9rem;
    opacity: 0;
    color: rgba(59, 130, 246, 0.6);
    animation: float 8s infinite;
    text-shadow: 0 0 10px rgba(59, 130, 246, 0.4);
    font-weight: bold;
}

.math-symbol:nth-child(1) {
    top: 20%;
    left: 80%;
    animation-delay: 0s;
}

.math-symbol:nth-child(2) {
    top: 40%;
    left: 70%;
    animation-delay: 1s;
    font-size: 1.1rem;
}

.math-symbol:nth-child(3) {
    top: 30%;
    left: 60%;
    animation-delay: 2s;
    font-size: 0.9rem;
}

.math-symbol:nth-child(4) {
    top: 10%;
    left: 75%;
    animation-delay: 3s;
    font-size: 1rem;
}

.math-symbol:nth-child(5) {
    top: 50%;
    left: 65%;
    animation-delay: 4s;
    font-size: 1.1rem;
}

.math-symbol:nth-child(6) {
    top: 60%;
    left: 85%;
    animation-delay: 5s;
    font-size: 1rem;
}

.math-symbol:nth-child(7) {
    top: 20%;
    left: 90%;
    animation-delay: 6s;
    font-size: 0.9rem;
}

.math-symbol:nth-child(8) {
    top: 70%;
    left: 75%;
    animation-delay: 7s;
    font-size: 1rem;
}

@keyframes float {
    0% {
        transform: translate(0, 0) rotate(0deg);
        opacity: 0;
    }
    10% {
        opacity: 0.9;
    }
    50% {
        transform: translate(-25px, -15px) rotate(15deg);
        opacity: 0.7;
    }
    90% {
        opacity: 0.9;
    }
    100% {
        transform: translate(-50px, -25px) rotate(0deg);
        opacity: 0;
    }
}

/* Enhanced dropdown styling */
.teacher-subject-adder-dialog :deep(.p-dropdown) {
    border-color: #d1d5db;
    transition: all 0.3s ease;
}

.teacher-subject-adder-dialog :deep(.p-dropdown:hover),
.teacher-subject-adder-dialog :deep(.p-dropdown.p-focus) {
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

.subject-item.disabled {
    opacity: 0.7;
    cursor: not-allowed;
    background-color: #f5f5f5;
}

.subject-item.disabled:hover {
    background-color: #f0f0f0;
}

.disabled-message {
    font-size: 0.8rem;
    color: #888;
    font-style: italic;
}
</style>
