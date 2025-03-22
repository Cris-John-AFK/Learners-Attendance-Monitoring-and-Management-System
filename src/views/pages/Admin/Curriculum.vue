<script setup>
import { TeacherService } from '@/router/service/TeacherService';
import { CurriculumService } from '@/router/service/CurriculumService';
import Calendar from 'primevue/calendar';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import { computed, onBeforeMount, ref } from 'vue';
import { useRouter } from 'vue-router';

const teachers = ref(null);
const expandedRows = ref([]);
const searchQuery = ref('');
const editDialog = ref(false);
const editingTeacher = ref(null);
const createDialog = ref(false);
const newTeacher = ref({
    name: '',
    status: 'ACTIVE',
    subjectsCount: 0
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
const subjectDetailsDialog = ref(false);
const sectionsDialog = ref(false);
const selectedSubject = ref(null);
const createSectionDialog = ref(false);
const newSection = ref({
    name: '',
    studentsCount: 0
});

// Add router at the top of your setup script
const router = useRouter();

// Add a direct click handler for subjects
function handleSubjectClick(subject) {
    console.log('Direct click on subject:', subject);

    // If the subject ID is 101, show a specific dialog instead of navigating
    if (subject.id === 101) {
        // Set selected subject for use in the dialog
        selectedSubject.value = subject;
        // Open a new dialog to show curriculum content for this subject
        subjectDetailsDialog.value = true;
    } else {
        // For other subjects, use the original navigation
        selectedTeacher.value && navigateToSubjectModule({ data: subject });
    }
}

// Load subjects from CurriculumService
const availableSubjects = ref([]);

// Get curriculum subjects data
function loadSubjects() {
    CurriculumService.getSubjects().then((data) => {
        availableSubjects.value = data;
    });
}

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
        loadTeachers(); // Refresh data
        editDialog.value = false;
        editingTeacher.value = null;
    }
}

function openCreateForm() {
    newTeacher.value = {
        name: '',
        status: 'ACTIVE',
        subjectsCount: 0
    };
    createDialog.value = true;
}

function saveNewTeacher() {
    TeacherService.createTeacher(newTeacher.value);
    loadTeachers(); // Refresh data
    createDialog.value = false;
    newTeacher.value = {
        name: '',
        status: 'ACTIVE',
        subjectsCount: 0
    };
}

// Add subject functions
function openSubjectDialog(teacher) {
    selectedTeacher.value = teacher;
    newSubject.value = {
        name: '',
        startDate: new Date().toISOString().split('T')[0],
        sectionsCount: 0,
        status: 'SCHEDULED'
    };
    subjectDialog.value = true;
}

function saveNewSubject() {
    if (selectedTeacher.value) {
        TeacherService.addSubject(selectedTeacher.value.id, newSubject.value);
        loadTeachers(); // Refresh data
    }
    subjectDialog.value = false;
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

function loadTeachers() {
    teachers.value = TeacherService.getTeachers();
}

// Fix the navigation function to ensure it works properly
function navigateToSubjectModule(event) {
    const subject = event.data;
    console.log('Navigating to subject:', subject);
    try {
        // Navigate to the subject module with the subject data, preserving the layout
        router.push({
            name: 'subject-details',
            params: { id: subject.id },
            query: {
                teacherId: selectedTeacher.value?.id,
                preserveLayout: true // This flag indicates to maintain the same layout
            }
        });
    } catch (error) {
        // Fallback if route doesn't exist
        console.error('Navigation error:', error);
        // Try a more generic route as fallback
        router.push('/subjects/' + subject.id + '?teacherId=' + selectedTeacher.value?.id);
    }
}

onBeforeMount(() => {
    loadTeachers();
    loadSubjects();
});
</script>

<template>
    <div class="card">
        <div class="flex justify-between items-center">
            <h3>Curriculum Management</h3>
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
            <Column field="name" header="Grades Level"></Column>
            <Column field="subjectsCount" header="Subjects" class="text-center custom-column"></Column>

            <Column header="Status">
                <template #body="slotProps">
                    <Tag :value="slotProps.data.status" :severity="getTeacherStatusSeverity(slotProps.data)" />
                </template>
            </Column>
            <Column header="Add Subject">
                <template #body="slotProps">
                    <Button icon="pi pi-plus" @click="openSubjectDialog(slotProps.data)" />
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
                    <DataTable :value="slotProps.data.subjects" @row-click="navigateToSubjectModule" class="cursor-pointer subject-table">
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
                                    <Button icon="pi pi-search" class="p-button-rounded p-button-text" @click.stop="openSectionsDialog(slotProps.data)" />
                                    <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click.stop="openEditSubject(slotProps.data)" />
                                </div>
                            </template>
                        </Column>

                        <!-- Add a dedicated click column for better mobile support -->
                        <Column headerStyle="width:3rem">
                            <template #body="slotProps">
                                <Button icon="pi pi-arrow-right" class="p-button-rounded p-button-success p-button-outlined" @click="handleSubjectClick(slotProps.data)" />
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

        <!-- Subject Details Dialog that shows curriculum information -->
        <Dialog v-model:visible="subjectDetailsDialog" modal header="Subject Details" :style="{ width: '650px' }">
            <div v-if="selectedSubject">
                <h3>{{ selectedSubject.name }} - Curriculum Information</h3>

                <!-- Show special content only when the subject ID is 101 (Algebra II) -->
                <div v-if="selectedSubject.id === 101" class="algebra-curriculum">
                    <h4>Algebra II Curriculum - SY 2023-2024</h4>

                    <div class="curriculum-table">
                        <table class="w-full">
                            <thead>
                                <tr>
                                    <th class="text-left p-2">Course Code</th>
                                    <th class="text-left p-2">Descriptive Title</th>
                                    <th class="text-left p-2">Units</th>
                                    <th class="text-left p-2">Hours per Week</th>
                                    <th class="text-left p-2">Pre-requisite</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="p-2">MATH 101</td>
                                    <td class="p-2">Algebra II</td>
                                    <td class="p-2">3</td>
                                    <td class="p-2">3</td>
                                    <td class="p-2">MATH 100</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">
                        <h5>Course Description</h5>
                        <p>This course introduces advanced algebraic concepts, including equations, inequalities, functions, polynomials, rational expressions, and applications.</p>
                    </div>

                    <div class="mt-4">
                        <h5>Learning Outcomes</h5>
                        <ul class="pl-4">
                            <li>Solve and graph linear equations and inequalities</li>
                            <li>Perform operations with polynomial expressions</li>
                            <li>Factor polynomial expressions</li>
                            <li>Solve and graph quadratic equations</li>
                            <li>Solve rational equations</li>
                        </ul>
                    </div>

                    <div class="mt-4">
                        <h5>Course Schedule</h5>
                        <div class="grid">
                            <div class="col-6">
                                <div><strong>Start Date:</strong> 2025-01-15</div>
                                <div><strong>End Date:</strong> 2025-05-30</div>
                            </div>
                            <div class="col-6">
                                <div><strong>Schedule:</strong> MWF 9:00-10:30 AM</div>
                                <div><strong>Classroom:</strong> Room 101</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Show default content for other subjects -->
                <div v-else class="my-4">
                    <h4>Available Subject Types</h4>
                    <DataTable :value="availableSubjects" class="p-datatable-sm">
                        <Column field="name" header="Subject Name"></Column>
                        <Column field="applicableLevels" header="Applicable Levels">
                            <template #body="slotProps">
                                <div class="flex flex-wrap gap-1">
                                    <Tag v-for="level in slotProps.data.applicableLevels" :key="level" :value="level" severity="info" class="mr-1" />
                                </div>
                            </template>
                        </Column>
                        <Column headerStyle="width:8rem">
                            <template #body>
                                <Button icon="pi pi-plus" label="Apply" class="p-button-sm p-button-outlined" @click="subjectDetailsDialog = false" />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <div class="curriculum-details mt-4">
                    <h4>Subject Details</h4>
                    <div class="grid">
                        <div class="col-6">
                            <div class="detail-item">
                                <label>Subject ID:</label>
                                <span>{{ selectedSubject.id }}</span>
                            </div>
                            <div class="detail-item">
                                <label>Name:</label>
                                <span>{{ selectedSubject.name }}</span>
                            </div>
                            <div class="detail-item">
                                <label>Start Date:</label>
                                <span>{{ selectedSubject.startDate }}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="detail-item">
                                <label>Section Count:</label>
                                <span>{{ selectedSubject.sectionsCount }}</span>
                            </div>
                            <div class="detail-item">
                                <label>Status:</label>
                                <Tag :value="selectedSubject.status" :severity="getSubjectStatusSeverity(selectedSubject)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="subjectDetailsDialog = false" text />
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

.cursor-pointer tbody tr {
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    border-radius: 4px;
    margin-bottom: 2px;
}

.cursor-pointer tbody tr:hover {
    background-color: rgba(76, 175, 80, 0.3); /* Green hover color */
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.cursor-pointer tbody tr:active {
    transform: translateY(0);
    background-color: rgba(76, 175, 80, 0.5); /* Darker green for active state */
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.subject-table tbody tr td {
    padding: 12px 8px;
}

.subject-table tbody tr:hover::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 4px;
    background-color: #4caf50;
    border-top-left-radius: 4px;
    border-bottom-left-radius: 4px;
}

.subject-table tbody tr {
    border-left: 2px solid transparent;
    border-bottom: 1px solid #f0f0f0;
}

.curriculum-details .detail-item {
    margin-bottom: 1rem;
}

.curriculum-details .detail-item label {
    font-weight: bold;
    display: block;
    margin-bottom: 0.25rem;
    color: #555;
}

.curriculum-details .detail-item span {
    display: block;
}

.algebra-curriculum table {
    border-collapse: collapse;
    width: 100%;
}

.algebra-curriculum th,
.algebra-curriculum td {
    border: 1px solid #ddd;
}

.algebra-curriculum th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.algebra-curriculum ul {
    list-style-type: disc;
}
</style>
