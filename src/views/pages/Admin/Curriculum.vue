<script setup>
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
    startTime: '',
    endTime: '',
    startYear: '',
    endYear: ''
});

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
    showAddSectionDialog.value = true;
}

function addNewSection() {
    sections.value.push({ ...newSectionData.value });

    newSectionData.value = {
        id: '',
        subjectName: '',
        teacherName: '',
        startTime: '',
        endTime: '',
        startYear: '',
        endYear: ''
    };

    showAddSectionDialog.value = false;
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
                        <Button icon="pi pi-pencil" class="p-button-text" @click="editSection(slotProps.data)" tooltip="Edit Section" aria-label="Edit Section" />
                        <Button icon="pi pi-search" class="p-button-text" @click="openSectionDetails(slotProps.data)" tooltip="View Section Details" aria-label="View Section Details" />
                        <Button icon="pi pi-trash" class="p-button-text" @click="deleteSection(slotProps.data)" tooltip="Delete Section" aria-label="Delete Section" />
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
    <Dialog v-model:visible="showSectionDetailsDialog" header="Section Details" modal class="max-w-md w-full rounded-lg">
        <div class="p-4 space-y-4">
            <h3 class="text-lg font-semibold">Sections for {{ selectedGrade?.name }}</h3>

            <!-- Table to display sections -->
            <DataTable :value="sections.filter((section) => section.gradeLevel === selectedGrade?.name)" class="p-datatable-striped">
                <Column field="id" header="ID" sortable />
                <Column field="subjectName" header="Subject Name" sortable />
                <Column field="teacherName" header="Teacher Name" sortable />
                <Column field="startTime" header="Start Time" sortable />
                <Column field="endTime" header="End Time" sortable />
                <Column field="startYear" header="Start Year" sortable />
                <Column field="endYear" header="End Year" sortable />
            </DataTable>

            <div class="flex justify-end gap-2 mt-4">
                <Button label="Add Details" class="p-button-success" @click="openAddSectionDialog" />
                <Button label="Close" class="p-button-secondary" @click="showSectionDetailsDialog = false" />
            </div>
        </div>
    </Dialog>

    <!-- Add Section Dialog -->
    <Dialog v-model:visible="showAddSectionDialog" header="Add Section" modal class="max-w-md w-full rounded-lg">
        <div class="p-4 space-y-4">
            <label class="block text-gray-700 font-medium">ID</label>
            <InputText v-model="newSectionData.id" placeholder="Enter ID" class="w-full" />

            <label class="block text-gray-700 font-medium">Subject Name</label>
            <InputText v-model="newSectionData.subjectName" placeholder="Enter Subject Name" class="w-full" />

            <label class="block text-gray-700 font-medium">Teacher Name</label>
            <InputText v-model="newSectionData.teacherName" placeholder="Enter Teacher Name" class="w-full" />

            <label class="block text-gray-700 font-medium">Start Time</label>
            <InputText v-model="newSectionData.startTime" placeholder="Enter Start Time" class="w-full" />

            <label class="block text-gray-700 font-medium">End Time</label>
            <InputText v-model="newSectionData.endTime" placeholder="Enter End Time" class="w-full" />

            <label class="block text-gray-700 font-medium">Start Year</label>
            <InputText v-model="newSectionData.startYear" placeholder="Enter Start Year" class="w-full" />

            <label class="block text-gray-700 font-medium">End Year</label>
            <InputText v-model="newSectionData.endYear" placeholder="Enter End Year" class="w-full" />

            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" class="p-button-secondary" @click="showAddSectionDialog = false" />
                <Button label="Save" class="p-button-success" @click="addNewSection" />
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
</style>
