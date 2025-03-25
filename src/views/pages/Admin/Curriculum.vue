<script setup lang="ts">
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import { ref } from 'vue';

const products = ref([]);
const showCreateDialog = ref(false);
const showSectionManagement = ref(false);
const selectedGrade = ref(null);
const newGradeLevel = ref('');
const selectedYearRange = ref('2024-2025');
const yearRanges = ref(['2023-2024', '2024-2025', '2025-2026', '2026-2027']);
const sections = ref([]);
const newSection = ref({ name: '', gradeLevel: '', group: '', year: null });
const showSectionDialog = ref(false);
const groups = ref(['Group 1', 'Group 2', 'Group 3', 'Group 4']);
const showSectionDetailsDialog = ref(false);
const selectedSectionDetails = ref(null);
const showAddSectionDialog = ref(false);
const newSectionData = ref({
    id: '',
    subjectName: '',
    teacherName: '',
    startTime: null,
    endTime: null,
    startYear: '',
    endYear: '',
    section: null
});
const sectionDetails = ref([]);
const subjects = ref(['Mathematics', 'Science', 'English', 'History', 'Physics', 'Chemistry']);
const teachers = ref(['Mr. Smith', 'Mrs. Johnson', 'Dr. Williams', 'Ms. Brown']);
const isEditMode = ref(false);
const editingSectionDetail = ref(null);

function openCreateForm() {
    showCreateDialog.value = true;
}

function saveGradeLevel() {
    if (newGradeLevel.value.trim() !== '' && selectedYearRange.value) {
        const grade = {
            name: `Grade ${newGradeLevel.value}`,
            category: `Year ${selectedYearRange.value}`
        };
        products.value.push(grade);
        newGradeLevel.value = '';
        selectedYearRange.value = '2024-2025';
        showCreateDialog.value = false;
    }
}

function openSectionManagement(grade) {
    selectedGrade.value = grade;
    showSectionManagement.value = true;
}

function addSection() {
    if (newSection.value.name.trim() !== '') {
        sections.value.push({ ...newSection.value, gradeLevel: selectedGrade.value.name });
        resetSectionForm();
    }
}

function deleteSection(section) {
    const index = sections.value.findIndex((s) => s.name === section.name && s.gradeLevel === section.gradeLevel);
    if (index !== -1) {
        sections.value.splice(index, 1);
    }
}

function resetSectionForm() {
    newSection.value = { name: '', gradeLevel: '', group: '', year: null };
    showSectionDialog.value = false;
}

function openSectionDetails(section) {
    selectedSectionDetails.value = section;
    showSectionDetailsDialog.value = true;
}

function openAddSectionDialog() {
    isEditMode.value = false;
    editingSectionDetail.value = null;
    newSectionData.value = {
        id: generateId(),
        subjectName: '',
        teacherName: '',
        startTime: null,
        endTime: null,
        startYear: '',
        endYear: '',
        section: selectedSectionDetails.value
    };
    showAddSectionDialog.value = true;
}

function generateId() {
    return 'SEC-' + Math.random().toString(36).substr(2, 9).toUpperCase();
}

function addNewSection() {
    if (validateSectionData()) {
        if (isEditMode.value) {
            const index = sectionDetails.value.findIndex((d) => d.id === editingSectionDetail.value.id);
            if (index !== -1) {
                sectionDetails.value[index] = {
                    ...newSectionData.value,
                    startTime: formatTime(newSectionData.value.startTime),
                    endTime: formatTime(newSectionData.value.endTime)
                };
            }
        } else {
            sectionDetails.value.push({
                ...newSectionData.value,
                startTime: formatTime(newSectionData.value.startTime),
                endTime: formatTime(newSectionData.value.endTime)
            });
        }
        closeAddSectionDialog();
    }
}

function validateSectionData() {
    return newSectionData.value.subjectName && newSectionData.value.teacherName && newSectionData.value.startTime && newSectionData.value.endTime;
}

function formatTime(date) {
    if (!date) return '';
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function deleteSectionDetail(detail) {
    const index = sectionDetails.value.findIndex((d) => d.id === detail.id);
    if (index !== -1) {
        sectionDetails.value.splice(index, 1);
    }
}

function editSectionDetail(detail) {
    isEditMode.value = true;
    editingSectionDetail.value = { ...detail };
    newSectionData.value = {
        ...detail,
        startTime: parseTime(detail.startTime),
        endTime: parseTime(detail.endTime),
        section: selectedSectionDetails.value
    };
    showAddSectionDialog.value = true;
}

function parseTime(timeString) {
    if (!timeString) return null;
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(parseInt(hours));
    date.setMinutes(parseInt(minutes));
    return date;
}

function closeAddSectionDialog() {
    showAddSectionDialog.value = false;
    isEditMode.value = false;
    editingSectionDetail.value = null;
    newSectionData.value = {
        id: generateId(),
        subjectName: '',
        teacherName: '',
        startTime: null,
        endTime: null,
        startYear: '',
        endYear: '',
        section: selectedSectionDetails.value
    };
}
</script>

<template>
    <div class="flex flex-col bg-gray-100 p-6 rounded-lg shadow-lg">
        <div class="card bg-white p-6 rounded-lg shadow-md">
            <div class="font-semibold text-2xl text-indigo-600">Curriculum Management</div>
            <div class="flex justify-between items-center my-4">
                <Button label="Create" icon="pi pi-plus" class="p-button-success bg-green-500 text-white" @click="openCreateForm" />
            </div>

            <div class="grid grid-cols-12 gap-4">
                <div v-for="(item, index) in products" :key="index" class="col-span-12 sm:col-span-6 lg:col-span-4 p-2">
                    <div class="p-6 border border-gray-300 rounded-lg bg-indigo-50 shadow-md cursor-pointer" @click="openSectionManagement(item)">
                        <div class="text-lg font-medium text-indigo-800">{{ item.name }}</div>
                        <span class="text-sm text-gray-600">{{ item.category }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Grade Level Dialog -->
    <Dialog v-model:visible="showCreateDialog" header="Add Grade Level" modal class="w-96">
        <div class="flex flex-col gap-4">
            <label class="font-medium text-gray-700">Grade Level:</label>
            <InputText v-model="newGradeLevel" placeholder="Enter Grade Level" class="p-inputtext-lg border border-gray-300 p-2 rounded-lg" />
            <label class="font-medium text-gray-700">Year Range:</label>
            <Dropdown v-model="selectedYearRange" :options="yearRanges" placeholder="Select Year Range" class="w-full border border-gray-300 p-2 rounded-lg" />
            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" class="p-button-secondary" @click="showCreateDialog = false" />
                <Button label="Save" class="p-button-success" @click="saveGradeLevel" />
            </div>
        </div>
    </Dialog>

    <!-- Section Management Section -->
    <div v-if="showSectionManagement" class="card p-6 shadow-lg rounded-lg bg-white mt-6">
        <h2 class="text-2xl font-semibold mb-6">Section Management - {{ selectedGrade?.name }}</h2>
        <div class="flex justify-between items-center mb-4">
            <Button label="Add Section" icon="pi pi-user-plus" class="p-button-success" @click="showSectionDialog = true" />
        </div>
        <DataTable :value="sections.filter((s) => s.gradeLevel === selectedGrade?.name)" class="p-datatable-striped">
            <Column field="name" header="Name" sortable />
            <Column field="gradeLevel" header="Grade Level" sortable />
            <Column field="year" header="Year" sortable />
            <Column header="Action">
                <template #body="slotProps">
                    <div class="flex space-x-2">
                        <Button icon="pi pi-pencil" class="p-button-text" @click="$emit('edit-section', slotProps.data)" tooltip="Edit Section" aria-label="Edit Section" />
                        <Button icon="pi pi-search" class="p-button-text" @click="$emit('open-section-details', slotProps.data)" tooltip="View Section Details" aria-label="View Section Details" />
                        <Button icon="pi pi-trash" class="p-button-text" @click="$emit('delete-section', slotProps.data)" tooltip="Delete Section" aria-label="Delete Section" />
                    </div>
                </template>
            </Column>
        </DataTable>
        <div class="border-t border-gray-300 mt-4 pt-4 flex justify-center">
            <Button label="Close" class="p-button-secondary" @click="showSectionManagement = false" />
        </div>
    </div>

    <!-- Add Section Dialog -->
    <Dialog v-model:visible="showSectionDialog" header="Add Section" modal class="max-w-md w-full rounded-lg">
        <div class="p-4 space-y-4">
            <label class="block text-gray-700 font-medium">Section Name</label>
            <InputText v-model="newSection.name" placeholder="Enter Section Name" class="w-full" />
            <label class="block text-gray-700 font-medium">Group</label>
            <Dropdown v-model="newSection.group" :options="groups" placeholder="Select Group" class="w-full border border-gray-300 p-2 rounded-lg" />
            <label class="block text-gray-700 font-medium">Year</label>
            <Calendar v-model="newSection.year" :showIcon="true" placeholder="Select Year" />
            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" class="p-button-secondary" @click="resetSectionForm" />
                <Button label="Save" class="p-button-success" @click="addSection()" />
            </div>
        </div>
    </Dialog>

    <!-- Section Details Dialog -->
    <Dialog v-model:visible="showSectionDetailsDialog" :header="`Section Details - ${selectedSectionDetails?.name}`" modal class="w-11/12 max-w-6xl" :maximizable="true">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Schedule Details</h3>
                <Button label="Add Schedule" icon="pi pi-plus" class="p-button-success" @click="openAddSectionDialog" />
            </div>

            <!-- Updated Table -->
            <DataTable
                :value="sectionDetails.filter((d) => d.section?.name === selectedSectionDetails?.name)"
                class="p-datatable-striped"
                responsiveLayout="stack"
                :rows="10"
                :rowsPerPageOptions="[5, 10, 20, 50]"
                paginator
                paginatorTemplate="CurrentPageReport FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
                currentPageReportTemplate="Showing {first} to {last} of {totalRecords}"
                :resizableColumns="true"
                columnResizeMode="fit"
                showGridlines
                :scrollable="true"
                scrollHeight="400px"
            >
                <Column field="id" header="ID" sortable style="min-width: 100px" />
                <Column field="subjectName" header="Subject" sortable style="min-width: 150px" />
                <Column field="teacherName" header="Teacher" sortable style="min-width: 150px" />
                <Column field="startTime" header="Start Time" sortable style="min-width: 120px" />
                <Column field="endTime" header="End Time" sortable style="min-width: 120px" />
                <Column field="startYear" header="Start Year" sortable style="min-width: 120px" />
                <Column field="endYear" header="End Year" sortable style="min-width: 120px" />
                <Column header="Actions" style="min-width: 100px">
                    <template #body="slotProps">
                        <div class="flex gap-2">
                            <Button icon="pi pi-pencil" class="p-button-text p-button-warning" @click="editSectionDetail(slotProps.data)" tooltip="Edit Schedule" />
                            <Button icon="pi pi-trash" class="p-button-text p-button-danger" @click="deleteSectionDetail(slotProps.data)" tooltip="Delete Schedule" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </Dialog>

    <!-- Add Section Dialog -->
    <Dialog v-model:visible="showAddSectionDialog" :header="isEditMode ? 'Edit Schedule' : 'Add Schedule'" modal class="max-w-md w-full rounded-lg">
        <div class="p-4 space-y-4">
            <div class="field">
                <label class="block text-gray-700 font-medium">Subject</label>
                <Dropdown v-model="newSectionData.subjectName" :options="subjects" placeholder="Select Subject" class="w-full" />
            </div>

            <div class="field">
                <label class="block text-gray-700 font-medium">Teacher</label>
                <Dropdown v-model="newSectionData.teacherName" :options="teachers" placeholder="Select Teacher" class="w-full" />
            </div>

            <div class="field">
                <label class="block text-gray-700 font-medium">Start Time</label>
                <Calendar v-model="newSectionData.startTime" timeOnly placeholder="Select Start Time" class="w-full" />
            </div>

            <div class="field">
                <label class="block text-gray-700 font-medium">End Time</label>
                <Calendar v-model="newSectionData.endTime" timeOnly placeholder="Select End Time" class="w-full" />
            </div>

            <div class="field">
                <label class="block text-gray-700 font-medium">Start Year</label>
                <InputText v-model="newSectionData.startYear" placeholder="Enter Start Year" class="w-full" />
            </div>

            <div class="field">
                <label class="block text-gray-700 font-medium">End Year</label>
                <InputText v-model="newSectionData.endYear" placeholder="Enter End Year" class="w-full" />
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" class="p-button-secondary" @click="closeAddSectionDialog" />
                <Button :label="isEditMode ? 'Update' : 'Save'" class="p-button-success" @click="addNewSection" :disabled="!validateSectionData()" />
            </div>
        </div>
    </Dialog>
</template>

<style scoped>
:deep(.p-datatable-striped .p-datatable-tbody > tr:nth-child(even)) {
    background-color: #f9fafb;
}
:deep(.p-datatable-thead th) {
    background-color: #e5e7eb;
    font-weight: bold;
}
:deep(.p-dialog) {
    border-radius: 12px;
}
:deep(.p-button-success) {
    background-color: #22c55e;
    border: none;
}
:deep(.p-button-success:hover) {
    background-color: #16a34a;
}

:deep(.p-datatable) {
    font-size: 0.95rem;
}

:deep(.p-datatable .p-datatable-header) {
    background-color: #f8fafc;
    padding: 1rem;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    background-color: #f1f5f9;
    color: #334155;
    padding: 0.75rem;
    font-weight: 600;
    text-align: left;
}

:deep(.p-datatable .p-datatable-tbody > tr) {
    background-color: #ffffff;
    transition: background-color 0.2s;
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
    background-color: #f1f5f9;
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
    padding: 0.75rem;
    border: 1px solid #e2e8f0;
}

:deep(.p-paginator) {
    padding: 1rem;
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
}

:deep(.p-dialog.w-11\/12) {
    max-height: 90vh;
    overflow-y: auto;
}

:deep(.p-dialog-content) {
    padding: 0 !important;
}
</style>
