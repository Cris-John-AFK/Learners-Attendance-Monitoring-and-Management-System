<script setup>
import { SubjectService } from '@/router/service/Subjects';
import { TeacherService } from '@/router/service/TeacherService';
import Calendar from 'primevue/calendar';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import FileUpload from 'primevue/fileupload';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';
import { computed, onBeforeMount, ref } from 'vue';

const toast = useToast();
const teachers = ref(null);
const expandedRows = ref([]);
const searchQuery = ref('');
const editDialog = ref(false);
const editingTeacher = ref(null);
const createDialog = ref(false);
const deleteDialog = ref(false);
const teacherToDelete = ref(null);
const newTeacher = ref({
    name: '',
    department: '',
    roomNumber: '',
    status: 'ACTIVE',
    subjectsCount: 0,
    image: 'default.png',
    assignedGrades: []
});

// Add new refs for subject dialog
const subjectDialog = ref(false);
const selectedTeacher = ref(null);
const newSubject = ref({
    id: null,
    name: '',
    startDate: new Date().toISOString().split('T')[0],
    sectionsCount: 0,
    status: 'ACTIVE'
});

const editSubjectDialog = ref(false);
const editingSubject = ref(null);
const teacherDetailsDialog = ref(false);
const sectionsDialog = ref(false);
const selectedSubject = ref(null);
const createSectionDialog = ref(false);
const newSection = ref({
    name: '',
    studentsCount: 0
});

// Refs for section assignment
const assignSectionDialog = ref(false);
const availableGrades = ref([]);
const selectedGrade = ref(null);
const availableSections = ref([]);
const selectedSection = ref(null);

// For viewing teacher assignments
const assignmentsDialog = ref(false);
const teacherAssignments = ref([]);

// Departments for dropdown
const departments = ['Mathematics', 'Science', 'Filipino', 'English', 'Social Studies', 'Physical Education', 'Music', 'Arts', 'Technology and Livelihood Education', 'Values Education'];

// Status options
const statusOptions = [
    { label: 'Active', value: 'ACTIVE' },
    { label: 'On Leave', value: 'ON_LEAVE' },
    { label: 'Inactive', value: 'INACTIVE' }
];

// Add new refs for subject selection dialog
const subjectSelectionDialog = ref(false);
const availableSubjects = ref([]);
const selectedSubjects = ref([]);
const subjectSearchQuery = ref('');

function expandAll() {
    expandedRows.value = teachers.value.reduce((acc, p) => (acc[p.id] = true) && acc, {});
}

function collapseAll() {
    expandedRows.value = null;
}

function getTeacherStatusSeverity(teacher) {
    switch (teacher.status) {
        case 'ACTIVE':
            return 'success';
        case 'ON_LEAVE':
            return 'warn';
        case 'INACTIVE':
            return 'danger';
        default:
            return null;
    }
}

function getSubjectStatusSeverity(subject) {
    switch (subject.status) {
        case 'ACTIVE':
            return 'success';
        case 'COMPLETED':
            return 'info';
        case 'CANCELLED':
            return 'danger';
        case 'SCHEDULED':
            return 'warn';
        default:
            return null;
    }
}

function filterTeachers(teachers, query) {
    if (!query || !teachers) return teachers;
    return teachers.filter((teacher) => teacher.name.toLowerCase().includes(query.toLowerCase()));
}

const filteredTeachers = computed(() => {
    return filterTeachers(teachers.value, searchQuery.value);
});

function openEdit(teacher) {
    editingTeacher.value = { ...teacher };
    editDialog.value = true;
}

function saveEdit() {
    if (editingTeacher.value) {
        TeacherService.updateTeacher(editingTeacher.value.id, editingTeacher.value);
        loadTeachers();
        editDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Teacher updated successfully',
            life: 3000
        });
    }
}

function openCreateForm() {
    newTeacher.value = {
        name: '',
        department: '',
        roomNumber: '',
        status: 'ACTIVE',
        image: 'default.png',
        assignedGrades: []
    };
    createDialog.value = true;
}

function saveNewTeacher() {
    if (!newTeacher.value.name) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Teacher name is required',
            life: 3000
        });
        return;
    }

    TeacherService.createTeacher(newTeacher.value);
    loadTeachers();
    createDialog.value = false;
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Teacher created successfully',
        life: 3000
    });
}

// Add function to fetch available subjects from Subject.js
async function loadAvailableSubjects() {
    try {
        const subjects = await SubjectService.getSubjects();
        availableSubjects.value = subjects.map((subject) => ({
            id: subject.id,
            name: subject.name,
            grade: subject.grade
        }));
    } catch (error) {
        console.error('Error loading subjects:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load available subjects',
            life: 3000
        });
    }
}

// Replace openSubjectDialog with this new function
function openSubjectSelectionDialog(teacher) {
    selectedTeacher.value = teacher;
    selectedSubjects.value = [];
    subjectSearchQuery.value = '';
    loadAvailableSubjects();
    subjectSelectionDialog.value = true;
}

// Filter subjects based on search query
const filteredSubjects = computed(() => {
    if (!subjectSearchQuery.value.trim() || !availableSubjects.value) {
        return availableSubjects.value;
    }

    const query = subjectSearchQuery.value.toLowerCase();
    return availableSubjects.value.filter((subject) => subject.name.toLowerCase().includes(query) || subject.grade.toLowerCase().includes(query));
});

// Add function to add a subject to the selected list
function addSubjectToSelection(subject) {
    // Check if the subject is already selected
    if (!selectedSubjects.value.some((s) => s.id === subject.id)) {
        selectedSubjects.value.push(subject);
    }
}

// Add function to remove a subject from the selected list
function removeSubjectFromSelection(subject) {
    selectedSubjects.value = selectedSubjects.value.filter((s) => s.id !== subject.id);
}

// Replace the saveSelectedSubjects function with this updated version
async function saveSelectedSubjects() {
    if (!selectedTeacher.value || selectedSubjects.value.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Please select at least one subject',
            life: 3000
        });
        return;
    }

    try {
        // Ensure the teacher has a subjects array
        if (!selectedTeacher.value.subjects) {
            selectedTeacher.value.subjects = [];
        }

        // Track how many subjects were successfully added
        let addedCount = 0;

        // Assign each selected subject to the teacher
        for (const subject of selectedSubjects.value) {
            // Create a new subject object with the necessary properties
            const newSubject = {
                id: subject.id,
                name: subject.name,
                grade: subject.grade,
                startDate: new Date().toISOString().split('T')[0],
                sectionsCount: 0,
                status: 'SCHEDULED',
                sections: []
            };

            // Check if the subject is already assigned
            const exists = selectedTeacher.value.subjects.some((s) => s.id === subject.id);

            if (!exists) {
                // Only add if not already assigned
                TeacherService.addSubject(selectedTeacher.value.id, newSubject);
                addedCount++;
            }
        }

        // Refresh data
        loadTeachers();
        subjectSelectionDialog.value = false;

        // Show success message
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `${addedCount} subject(s) assigned successfully`,
            life: 3000
        });
    } catch (error) {
        console.error('Error assigning subjects:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to assign subjects: ' + error.message,
            life: 3000
        });
    }
}

// Add function for image handling
function onImageUpload(event) {
    const file = event.files[0];
    if (file) {
        newTeacher.value.image = file.name;
    }
}

// Add function for edit image handling
function onEditImageUpload(event) {
    const file = event.files[0];
    if (file) {
        editingTeacher.value.image = file.name;
    }
}

function openEditSubject(subject) {
    editingSubject.value = { ...subject };
    editSubjectDialog.value = true;
}

function saveEditSubject() {
    if (selectedTeacher.value && editingSubject.value) {
        TeacherService.updateSubject(selectedTeacher.value.id, editingSubject.value.id, editingSubject.value);
        loadTeachers(); // Refresh data
    }
    editSubjectDialog.value = false;
    editingSubject.value = null;
}

function openTeacherDetailsDialog(teacher) {
    selectedTeacher.value = TeacherService.getTeacherById(teacher.id);
    teacherDetailsDialog.value = true;
}

function openSectionsDialog(subject) {
    selectedSubject.value = subject;
    sectionsDialog.value = true;
}

function openCreateSectionDialog() {
    newSection.value = {
        name: '',
        studentsCount: 0
    };
    createSectionDialog.value = true;
}

function saveNewSection() {
    if (selectedTeacher.value && selectedSubject.value) {
        TeacherService.addSection(selectedTeacher.value.id, selectedSubject.value.id, newSection.value);
        // Update the selected subject with fresh data
        const teacher = TeacherService.getTeacherById(selectedTeacher.value.id);
        selectedTeacher.value = teacher;
        selectedSubject.value = teacher.subjects.find((s) => s.id === selectedSubject.value.id);

        loadTeachers(); // Refresh all data
    }
    createSectionDialog.value = false;
}

function confirmDeleteTeacher(teacher) {
    teacherToDelete.value = teacher;
    deleteDialog.value = true;
}

function deleteTeacher() {
    if (teacherToDelete.value) {
        TeacherService.deleteTeacher(teacherToDelete.value.id);
        loadTeachers();
        deleteDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Teacher deleted successfully',
            life: 3000
        });
    }
}

// Open section assignment dialog
function openAssignSectionDialog(teacher) {
    selectedTeacher.value = teacher;
    loadAvailableGrades();
    selectedGrade.value = null;
    availableSections.value = [];
    selectedSection.value = null;
    assignSectionDialog.value = true;
}

// Load available grades for assignment
function loadAvailableGrades() {
    if (!selectedTeacher.value) return;

    // Get all available assignments for this teacher
    const availableAssignments = TeacherService.getAvailableSections(selectedTeacher.value.id);

    // Extract just the grades for the dropdown
    availableGrades.value = availableAssignments.map((item) => ({
        label: item.gradeName,
        value: item.gradeId
    }));
}

// When a grade is selected, load its available sections
function onGradeChange() {
    if (!selectedTeacher.value || !selectedGrade.value) {
        availableSections.value = [];
        return;
    }

    const availableAssignments = TeacherService.getAvailableSections(selectedTeacher.value.id);
    const gradeAssignment = availableAssignments.find((item) => item.gradeId === selectedGrade.value);

    if (gradeAssignment) {
        availableSections.value = gradeAssignment.sections.map((section) => ({
            label: section,
            value: section
        }));
    } else {
        availableSections.value = [];
    }
}

// Assign a section to a teacher
function assignSection() {
    if (!selectedTeacher.value || !selectedGrade.value || !selectedSection.value) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Please select grade and section',
            life: 3000
        });
        return;
    }

    TeacherService.assignTeacherToSection(selectedTeacher.value.id, selectedGrade.value, selectedSection.value);

    loadTeachers();
    assignSectionDialog.value = false;
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: `Section ${selectedSection.value} assigned successfully`,
        life: 3000
    });
}

// View teacher's assignments
function viewAssignments(teacher) {
    selectedTeacher.value = teacher;
    teacherAssignments.value = TeacherService.getTeacherAssignments(teacher.id);
    assignmentsDialog.value = true;
}

// Remove assignment
function removeAssignment(assignment) {
    if (!selectedTeacher.value) return;

    TeacherService.removeTeacherFromSection(selectedTeacher.value.id, assignment.gradeId, assignment.sectionName);

    // Refresh assignments list
    teacherAssignments.value = TeacherService.getTeacherAssignments(selectedTeacher.value.id);
    loadTeachers();

    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: `Assignment removed successfully`,
        life: 3000
    });
}

function loadTeachers() {
    teachers.value = TeacherService.getTeachers();
}

onBeforeMount(() => {
    loadTeachers();
});
</script>

<template>
    <div class="card">
        <div class="flex justify-between items-center">
            <h3>Teacher Management</h3>
            <Button label="Register Teacher" icon="pi pi-plus" @click="openCreateForm" />
        </div>

        <div class="font-semibold text-xl mb-4"></div>
        <DataTable v-model:expandedRows="expandedRows" :value="filteredTeachers" dataKey="id" tableStyle="min-width: 60rem">
            <template #header>
                <div class="flex justify-between align-items-center">
                    <span class="p-input-icon-left">
                        <i class="pi pi-search" :style="{ padding: '10px' }" />
                        <InputText v-model="searchQuery" placeholder="Search Teachers..." />
                    </span>
                    <div class="flex flex-wrap justify-end gap-2">
                        <Button text icon="pi pi-plus" label="Expand All" @click="expandAll" />
                        <Button text icon="pi pi-minus" label="Collapse All" @click="collapseAll" />
                    </div>
                </div>
            </template>

            <Column expander style="width: 5rem" />
            <Column field="name" header="Teacher Name"></Column>
            <Column header="Photo">
                <template #body="slotProps">
                    <img :src="`https://primefaces.org/cdn/primevue/images/avatar/${slotProps.data.image}`" :alt="slotProps.data.name" class="shadow-lg rounded-full" width="64" />
                </template>
            </Column>

            <Column field="department" header="Department"></Column>
            <Column field="roomNumber" header="Room No."></Column>
            <Column field="subjectsCount" header="Subjects" class="text-center custom-column"></Column>

            <Column header="Status">
                <template #body="slotProps">
                    <Tag :value="slotProps.data.status" :severity="getTeacherStatusSeverity(slotProps.data)" />
                </template>
            </Column>
            <Column header="Add Subject">
                <template #body="slotProps">
                    <Button icon="pi pi-plus" class="p-button-rounded p-button-success p-button-sm" @click="openSubjectSelectionDialog(slotProps.data)" tooltip="Add Subjects" tooltipOptions="{ position: 'top' }" />
                </template>
            </Column>
            <Column header="Actions">
                <template #body="slotProps">
                    <div class="flex gap-2">
                        <Button icon="pi pi-search" class="p-button-rounded p-button-text" @click="openTeacherDetailsDialog(slotProps.data)" />
                        <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click="openEdit(slotProps.data)" />
                    </div>
                </template>
            </Column>

            <template #expansion="slotProps">
                <div class="p-4">
                    <h5>Subjects taught by {{ slotProps.data.name }}</h5>
                    <DataTable :value="slotProps.data.subjects">
                        <Column field="id" header="Id" sortable></Column>
                        <Column field="name" header="Subject Name" sortable></Column>
                        <Column field="startDate" header="Start Date" sortable></Column>
                        <Column field="sectionsCount" header="Sections" sortable>
                            <template #body="slotProps">
                                <div class="flex items-center gap-2">{{ slotProps.data.sectionsCount }} / 6</div>
                            </template>
                        </Column>
                        <Column field="status" header="Status" sortable>
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.status" :severity="getSubjectStatusSeverity(slotProps.data)" />
                            </template>
                        </Column>

                        <Column header="Actions" sortable headerStyle="width:4rem">
                            <template #body="slotProps">
                                <div class="flex gap-2">
                                    <Button icon="pi pi-search" class="p-button-rounded p-button-text" @click="openSectionsDialog(slotProps.data)" />
                                    <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click="openEditSubject(slotProps.data)" />
                                </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </template>
        </DataTable>

        <Dialog v-model:visible="editDialog" modal header="Edit Teacher" :style="{ width: '450px' }">
            <div class="p-fluid" v-if="editingTeacher">
                <!-- Teacher Name -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="name" class="font-medium">Teacher Name</label>
                    <InputText id="name" v-model="editingTeacher.name" class="w-full" />
                </div>

                <!-- Photo Section -->
                <div class="field" :style="{ padding: '10px' }">
                    <label class="font-medium">Current Photo</label>
                    <div class="flex align-items-center gap-2 mb-3">
                        <img :src="`https://primefaces.org/cdn/primevue/images/avatar/${editingTeacher.image}`" :alt="editingTeacher.name" class="shadow-lg rounded-full border border-gray-300 p-1" width="80" />
                    </div>

                    <label class="font-medium">Change Photo</label>
                    <FileUpload mode="basic" accept="image/*" :maxFileSize="1000000" @upload="onEditImageUpload" :auto="true" chooseLabel="Choose New Photo" class="w-full" />
                </div>

                <!-- Department -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="department" class="font-medium">Department</label>
                    <InputText id="department" v-model="editingTeacher.department" class="w-full" />
                </div>

                <!-- Room Number -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="roomNumber" class="font-medium">Room Number</label>
                    <InputText id="roomNumber" v-model="editingTeacher.roomNumber" class="w-full" />
                </div>

                <!-- Status Dropdown -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="edit-status" class="font-medium">Status</label>
                    <Dropdown id="edit-status" v-model="editingTeacher.status" :options="['ACTIVE', 'ON_LEAVE', 'INACTIVE']" placeholder="Select Status" class="w-full" />
                </div>
            </div>

            <!-- Footer Buttons -->
            <template #footer>
                <div class="flex justify-content-between w-full">
                    <Button label="Cancel" icon="pi pi-times" @click="editDialog = false" class="p-button-text" />
                    <Button label="Save" icon="pi pi-check" @click="saveEdit" class="p-button-success" autofocus />
                </div>
            </template>
        </Dialog>

        <Dialog v-model:visible="createDialog" modal header="Register New Teacher" :style="{ width: '450px' }">
            <div class="p-fluid">
                <!-- Teacher Name -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="new-name" class="font-medium">Teacher Name</label>
                    <InputText id="new-name" v-model="newTeacher.name" class="w-full" />
                </div>

                <!-- Photo Upload -->
                <div class="field" :style="{ padding: '10px' }">
                    <label class="font-medium">Photo</label>
                    <FileUpload mode="basic" accept="image/*" :maxFileSize="1000000" @upload="onImageUpload" :auto="true" chooseLabel="Choose Photo" class="w-full" />
                </div>

                <!-- Department -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="new-department" class="font-medium">Department</label>
                    <InputText id="new-department" v-model="newTeacher.department" class="w-full" />
                </div>

                <!-- Room Number -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="new-roomNumber" class="font-medium">Room Number</label>
                    <InputText id="new-roomNumber" v-model="newTeacher.roomNumber" class="w-full" />
                </div>

                <!-- Status -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="new-status" class="font-medium">Status</label>
                    <Dropdown id="new-status" v-model="newTeacher.status" :options="['ACTIVE', 'ON_LEAVE', 'INACTIVE']" placeholder="Select Status" class="w-full" />
                </div>
            </div>

            <!-- Footer Buttons -->
            <template #footer>
                <div class="flex justify-content-between w-full">
                    <Button label="Cancel" icon="pi pi-times" @click="createDialog = false" class="p-button-text" />
                    <Button label="Save" icon="pi pi-check" @click="saveNewTeacher" class="p-button-success" autofocus />
                </div>
            </template>
        </Dialog>

        <!-- Dialog for new subject -->
        <Dialog v-model:visible="subjectDialog" modal header="Add New Subject" :style="{ width: '450px' }">
            <div class="p-fluid" :style="{ padding: '10px' }">
                <!-- Subject Name -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="subject-name" class="font-medium">Subject Name</label>
                    <InputText id="subject-name" v-model="newSubject.name" class="w-full" />
                </div>

                <!-- Start Date -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="subject-date" class="font-medium">Start Date</label>
                    <Calendar id="subject-date" v-model="newSubject.startDate" dateFormat="yy-mm-dd" class="w-full" />
                </div>

                <!-- Initial Sections Count -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="sections" class="font-medium">Initial Sections Count</label>
                    <InputNumber id="sections" v-model="newSubject.sectionsCount" :min="0" :max="6" class="w-full" />
                </div>

                <!-- Status Dropdown -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="status" class="font-medium">Status</label>
                    <Dropdown id="status" v-model="newSubject.status" :options="['SCHEDULED', 'ACTIVE', 'COMPLETED', 'CANCELLED']" placeholder="Select Status" class="w-full" />
                </div>
            </div>

            <!-- Footer Buttons -->
            <template #footer>
                <div class="flex justify-content-between w-full">
                    <Button label="Cancel" icon="pi pi-times" @click="subjectDialog = false" class="p-button-text" />
                    <Button label="Save" icon="pi pi-check" @click="saveNewSubject" class="p-button-success" autofocus />
                </div>
            </template>
        </Dialog>

        <!-- Dialog for editing subject -->
        <Dialog v-model:visible="editSubjectDialog" modal header="Edit Subject" :style="{ width: '450px' }">
            <div class="p-fluid" v-if="editingSubject">
                <!-- Subject Name -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="edit-subject-name" class="font-medium">Subject Name</label>
                    <InputText id="edit-subject-name" v-model="editingSubject.name" class="w-full" />
                </div>

                <!-- Start Date -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="edit-subject-date" class="font-medium">Start Date</label>
                    <Calendar id="edit-subject-date" v-model="editingSubject.startDate" dateFormat="yy-mm-dd" class="w-full" />
                </div>

                <!-- Sections Count (Disabled) -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="edit-sections" class="font-medium">Sections Count</label>
                    <InputNumber id="edit-sections" v-model="editingSubject.sectionsCount" :min="0" :max="6" disabled class="w-full opacity-70" />
                </div>

                <!-- Status Dropdown -->
                <div class="field" :style="{ padding: '10px' }">
                    <label for="edit-subject-status" class="font-medium">Status</label>
                    <Dropdown id="edit-subject-status" v-model="editingSubject.status" :options="['SCHEDULED', 'ACTIVE', 'COMPLETED', 'CANCELLED']" placeholder="Select Status" class="w-full" />
                </div>
            </div>

            <!-- Footer Buttons -->
            <template #footer>
                <div class="flex justify-content-between w-full">
                    <Button label="Cancel" icon="pi pi-times" @click="editSubjectDialog = false" class="p-button-text" />
                    <Button label="Save" icon="pi pi-check" @click="saveEditSubject" class="p-button-success" autofocus />
                </div>
            </template>
        </Dialog>

        <!-- Teacher Details Dialog -->
        <Dialog v-model:visible="teacherDetailsDialog" modal header="Teacher Details" :style="{ width: '450px' }">
            <div class="p-fluid" v-if="selectedTeacher">
                <div class="field" :style="{ padding: '10px' }">
                    <label>Teacher Name</label>
                    <div class="p-inputtext">{{ selectedTeacher.name }}</div>
                </div>
                <div class="field" :style="{ padding: '10px' }">
                    <label>Department</label>
                    <div class="p-inputtext">{{ selectedTeacher.department }}</div>
                </div>
                <div class="field" :style="{ padding: '10px' }">
                    <label>Room Number</label>
                    <div class="p-inputtext">{{ selectedTeacher.roomNumber }}</div>
                </div>
                <div class="field" :style="{ padding: '10px' }">
                    <label>Status</label>
                    <div class="p-inputtext">
                        <Tag :value="selectedTeacher.status" :severity="getTeacherStatusSeverity(selectedTeacher)" />
                    </div>
                </div>
                <div class="field" :style="{ padding: '10px' }">
                    <label>Subjects</label>
                    <DataTable :value="selectedTeacher.subjects" scrollable scrollHeight="200px">
                        <Column field="name" header="Subject Name"></Column>
                        <Column field="startDate" header="Start Date"></Column>
                        <Column field="sectionsCount" header="Sections"></Column>
                        <Column field="status" header="Status">
                            <template #body="slotProps">
                                <Tag :value="slotProps.data.status" :severity="getSubjectStatusSeverity(slotProps.data)" />
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="teacherDetailsDialog = false" text />
            </template>
        </Dialog>

        <!-- Sections Dialog -->
        <Dialog v-model:visible="sectionsDialog" modal header="Sections" :style="{ width: '500px' }">
            <div>
                <div class="flex justify-between items-center">
                    <h3>Sections in {{ selectedSubject?.name }}</h3>
                    <Button class="p-button-success" type="button" aria-label="Create" @click="openCreateSectionDialog">
                        <span class="p-button-icon p-button-icon-left pi pi-plus"></span>
                        <span class="p-button-label">Create</span>
                    </Button>
                </div>
                <ul class="section-list">
                    <li v-for="section in selectedSubject?.sections" :key="section.id" class="section-item">
                        <span class="section-name">{{ section.name }} ({{ section.studentsCount }} students)</span>
                        <div class="section-buttons">
                            <Button class="p-button-text p-button-sm" type="button" aria-label="View">
                                <span class="p-button-icon p-button-icon-left pi pi-eye"></span>
                                <span class="p-button-label">View</span>
                            </Button>
                            <Button class="p-button-text p-button-sm" type="button" aria-label="Edit">
                                <Button class="p-button-text p-button-sm" type="button" aria-label="Edit">
                                    <span class="p-button-icon p-button-icon-left pi pi-pencil"></span>
                                    <span class="p-button-label">Edit</span>
                                </Button>
                            </Button>
                        </div>
                    </li>
                    <li v-if="!selectedSubject?.sections || selectedSubject.sections.length === 0" class="no-sections">No sections found for this subject</li>
                </ul>
            </div>
            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="sectionsDialog = false" text />
            </template>
        </Dialog>

        <!-- Create Section Dialog -->
        <Dialog v-model:visible="createSectionDialog" modal header="Create New Section" :style="{ width: '450px' }">
            <div class="p-fluid">
                <div class="field">
                    <label for="section-name">Section Name</label>
                    <InputText id="section-name" v-model="newSection.name" />
                </div>
                <div class="field">
                    <label for="students-count">Initial Students Count</label>
                    <InputNumber id="students-count" v-model="newSection.studentsCount" :min="0" :max="50" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="createSectionDialog = false" text />
                <Button label="Save" icon="pi pi-check" @click="saveNewSection" autofocus />
            </template>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:visible="deleteDialog" modal header="Confirm Deletion" :style="{ width: '450px' }" class="delete-dialog">
            <div class="flex align-items-center justify-content-center py-4">
                <i class="pi pi-exclamation-triangle mr-3 text-yellow-500" style="font-size: 2rem" />
                <span class="text-lg">
                    Are you sure you want to delete <span class="font-bold">{{ teacherToDelete?.name }}</span
                    >?
                </span>
            </div>
            <p class="text-center text-gray-600 mt-2">This will remove the teacher and all their assignments. This action cannot be undone.</p>

            <template #footer>
                <div class="flex justify-content-center gap-3 w-full">
                    <Button label="Cancel" icon="pi pi-times" class="p-button-outlined" @click="deleteDialog = false" />
                    <Button label="Delete" icon="pi pi-check" class="p-button-danger" @click="deleteTeacher" />
                </div>
            </template>
        </Dialog>

        <!-- Assign Section Dialog -->
        <Dialog v-model:visible="assignSectionDialog" modal header="Assign Section" :style="{ width: '450px' }">
            <div v-if="selectedTeacher" class="p-fluid">
                <p class="mb-3">
                    Assigning section to: <strong>{{ selectedTeacher.name }}</strong>
                </p>

                <div class="field mb-4">
                    <label for="grade" class="font-medium">Grade Level</label>
                    <Dropdown id="grade" v-model="selectedGrade" :options="availableGrades" optionLabel="label" optionValue="value" placeholder="Select Grade Level" @change="onGradeChange" />
                </div>

                <div class="field mb-4">
                    <label for="section" class="font-medium">Section</label>
                    <Dropdown id="section" v-model="selectedSection" :options="availableSections" optionLabel="label" optionValue="value" placeholder="Select Section" :disabled="!selectedGrade || availableSections.length === 0" />
                    <small v-if="selectedGrade && availableSections.length === 0" class="text-gray-500"> No available sections in this grade level </small>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="assignSectionDialog = false" />
                <Button label="Assign" icon="pi pi-check" class="p-button-success" @click="assignSection" :disabled="!selectedGrade || !selectedSection" />
            </template>
        </Dialog>

        <!-- View Assignments Dialog -->
        <Dialog v-model:visible="assignmentsDialog" modal header="Teacher Assignments" :style="{ width: '500px' }">
            <div v-if="selectedTeacher" class="p-fluid">
                <h4 class="mb-3 font-medium">{{ selectedTeacher.name }}'s Assignments</h4>

                <div v-if="teacherAssignments.length > 0" class="assignments-list">
                    <div v-for="(assignment, idx) in teacherAssignments" :key="idx" class="assignment-item p-3 mb-2 border-round flex align-items-center justify-content-between" :class="{ 'bg-gray-100': idx % 2 === 0 }">
                        <div>
                            <span class="font-medium">{{ assignment.gradeName }}</span> -
                            <span>{{ assignment.sectionName }}</span>
                        </div>
                        <Button icon="pi pi-times" class="p-button-rounded p-button-danger p-button-text" @click="removeAssignment(assignment)" />
                    </div>
                </div>
                <div v-else class="text-center p-4 text-gray-500">
                    <i class="pi pi-info-circle mb-3" style="font-size: 2rem"></i>
                    <p>No assignments found for this teacher</p>
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" class="p-button-text" @click="assignmentsDialog = false" />
            </template>
        </Dialog>

        <!-- Add Subject Selection Dialog -->
        <Dialog v-model:visible="subjectSelectionDialog" modal header="Select Subjects" :style="{ width: '600px' }" class="subject-selection-dialog">
            <div v-if="selectedTeacher" class="p-fluid">
                <p class="mb-3">
                    Assign subjects to: <strong>{{ selectedTeacher.name }}</strong>
                </p>

                <!-- Selected Subjects -->
                <div v-if="selectedSubjects.length > 0" class="selected-subjects mb-4">
                    <h5 class="font-medium mb-2">Selected Subjects:</h5>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="subject in selectedSubjects" :key="subject.id" class="selected-subject-tag p-2 border-round flex align-items-center gap-2">
                            <span>{{ subject.name }} ({{ subject.grade }})</span>
                            <Button icon="pi pi-times" class="p-button-rounded p-button-text p-button-sm" @click="removeSubjectFromSelection(subject)" />
                        </div>
                    </div>
                </div>

                <!-- Search and Subject List -->
                <div class="subject-search mb-3">
                    <span class="p-input-icon-left w-full">
                        <i class="pi pi-search" />
                        <InputText v-model="subjectSearchQuery" placeholder="Search subjects..." class="w-full" />
                    </span>
                </div>

                <div class="available-subjects">
                    <h5 class="font-medium mb-2">Available Subjects:</h5>
                    <div v-if="availableSubjects.length === 0" class="text-center p-4 bg-gray-100 border-round">
                        <i class="pi pi-info-circle mb-2" style="font-size: 1.5rem"></i>
                        <p>Loading available subjects...</p>
                    </div>
                    <div v-else-if="filteredSubjects.length === 0" class="text-center p-4 bg-gray-100 border-round">
                        <i class="pi pi-search-minus mb-2" style="font-size: 1.5rem"></i>
                        <p>No subjects found matching your search</p>
                    </div>
                    <div v-else class="subject-list">
                        <div
                            v-for="subject in filteredSubjects"
                            :key="subject.id"
                            class="subject-item p-3 mb-2 border-round flex align-items-center justify-content-between"
                            :class="{ selected: selectedSubjects.some((s) => s.id === subject.id) }"
                            @click="addSubjectToSelection(subject)"
                        >
                            <div>
                                <span class="font-medium">{{ subject.name }}</span>
                                <span class="text-sm text-500 ml-2">{{ subject.grade }}</span>
                            </div>
                            <Button icon="pi pi-plus" class="p-button-rounded p-button-text p-button-sm" v-if="!selectedSubjects.some((s) => s.id === subject.id)" />
                            <Button icon="pi pi-check" class="p-button-rounded p-button-text p-button-success p-button-sm" v-else disabled />
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="subjectSelectionDialog = false" />
                <Button label="Assign Selected" icon="pi pi-check" class="p-button-success" @click="saveSelectedSubjects" :disabled="selectedSubjects.length === 0" />
            </template>
        </Dialog>
    </div>
</template>

<style scoped>
.section-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.section-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid #efefef;
}

.section-item:last-child {
    border-bottom: none;
}

.section-name {
    font-weight: 500;
}

.section-buttons {
    display: flex;
    gap: 0.5rem;
}

.no-sections {
    padding: 1rem;
    text-align: center;
    color: #6c757d;
    font-style: italic;
}

.custom-column {
    text-align: center;
}

.delete-dialog :deep(.p-dialog-header) {
    background-color: #fee2e2;
    color: #b91c1c;
}

.delete-dialog :deep(.p-dialog-header-close-icon) {
    color: #b91c1c;
}

.assignments-list {
    max-height: 300px;
    overflow-y: auto;
}

.assignment-item {
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    transition: background-color 0.2s;
}

.assignment-item:hover {
    background-color: #e9ecef;
}

.selected-subject-tag {
    background-color: #e9ecef;
    border: 1px solid #dee2e6;
    transition: background-color 0.2s;
}

.selected-subject-tag:hover {
    background-color: #dee2e6;
}

.subject-list {
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid #e9ecef;
    border-radius: 6px;
}

.subject-item {
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    cursor: pointer;
    transition: all 0.2s;
}

.subject-item:hover {
    background-color: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.subject-item.selected {
    background-color: #cfe2ff;
    border-color: #9ec5fe;
}

.subject-selection-dialog :deep(.p-dialog-content) {
    max-height: 70vh;
    overflow-y: auto;
}
</style>
