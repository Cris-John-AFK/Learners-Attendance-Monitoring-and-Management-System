<script setup>
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import { ref } from 'vue';

const products = ref([]);
const showCreateDialog = ref(false);
const showStudentManagement = ref(false);
const selectedGrade = ref(null);
const newGradeLevel = ref('');
const selectedYearRange = ref('2024-2025');
const yearRanges = ref(['2023-2024', '2024-2025', '2025-2026', '2026-2027']);
const students = ref([]);
const newStudent = ref({ name: '', gradeLevel: '', age: '', birthdate: '' });
const showStudentDialog = ref(false);

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

function openStudentManagement(grade) {
    selectedGrade.value = grade;
    showStudentManagement.value = true;
}

function addStudent() {
    if (newStudent.value.name.trim() !== '') {
        students.value.push({ ...newStudent.value, gradeLevel: selectedGrade.value.name });
        newStudent.value = { name: '', gradeLevel: '', age: '', birthdate: '' };
        showStudentDialog.value = false;
    }
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
                    <div class="p-6 border border-gray-300 rounded-lg bg-indigo-50 shadow-md cursor-pointer" @click="openStudentManagement(item)">
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

    <!-- Student Management Section -->
    <div v-if="showStudentManagement" class="card p-6 shadow-lg rounded-lg bg-white mt-6">
        <h2 class="text-2xl font-semibold mb-6">Student Management - {{ selectedGrade?.name }}</h2>
        <div class="flex justify-between items-center mb-4">
            <Button label="Add Student" icon="pi pi-user-plus" class="p-button-success" @click="showStudentDialog = true" />
        </div>
        <DataTable :value="students.filter((s) => s.gradeLevel === selectedGrade?.name)" class="p-datatable-striped">
            <Column field="name" header="Name" sortable />
            <Column field="gradeLevel" header="Grade Level" sortable />
            <Column field="age" header="Age" sortable />
            <Column field="birthdate" header="Birthdate" sortable />
        </DataTable>
        <div class="border-t border-gray-300 mt-4 pt-4 flex justify-center">
            <Button label="Close" class="p-button-secondary" @click="showStudentManagement = false" />
        </div>
    </div>

    <!-- Add Student Dialog -->
    <Dialog v-model:visible="showStudentDialog" header="Add Student" modal class="max-w-md w-full rounded-lg">
        <div class="p-4 space-y-4">
            <label class="block text-gray-700 font-medium">Student Name</label>
            <InputText v-model="newStudent.name" placeholder="Enter Student Name" class="w-full" />
            <label class="block text-gray-700 font-medium">Age</label>
            <InputText v-model="newStudent.age" type="number" class="w-full" />
            <label class="block text-gray-700 font-medium">Birthdate</label>
            <InputText v-model="newStudent.birthdate" placeholder="YYYY-MM-DD" class="w-full" />
            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" class="p-button-secondary" @click="showStudentDialog = false" />
                <Button label="Add" class="p-button-success" @click="addStudent" />
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
