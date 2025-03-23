<script setup>
import { CurriculumService } from '@/router/service/CurriculumService';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import { onMounted, ref } from 'vue';

const grades = ref([]);
const showCreateDialog = ref(false);
const showSubjectManagementDialog = ref(false);
const showAddSubjectDialog = ref(false);
const selectedGrade = ref(null);
const newGradeLevel = ref('');
const selectedYearRange = ref('2024-2025');
const yearRanges = ref(['2023-2024', '2024-2025', '2025-2026', '2026-2027']);
const allSubjects = ref([]);
const selectedSubjects = ref([]);

onMounted(() => {
    CurriculumService.getSubjects().then((data) => {
        allSubjects.value = data.map((subject) => subject.name);
    });
});

function openCreateForm() {
    showCreateDialog.value = true;
}

function saveGradeLevel() {
    if (newGradeLevel.value.trim() !== '' && selectedYearRange.value) {
        const grade = {
            name: `Grade ${newGradeLevel.value}`,
            category: `Year ${selectedYearRange.value}`,
            subjects: []
        };
        grades.value.push(grade);
        newGradeLevel.value = '';
        selectedYearRange.value = '2024-2025';
        showCreateDialog.value = false;
    }
}

function openSubjectManagement(grade) {
    selectedGrade.value = grade;
    showSubjectManagementDialog.value = true;
}

function openAddSubjectDialog() {
    selectedSubjects.value = [];
    showAddSubjectDialog.value = true;
}

function saveSelectedSubjects() {
    if (selectedGrade.value) {
        selectedGrade.value.subjects = [...new Set([...(selectedGrade.value.subjects || []), ...selectedSubjects.value])];
        showAddSubjectDialog.value = false;
    }
}
</script>

<template>
    <div class="flex flex-col bg-gray-100 p-6 rounded-lg shadow-lg">
        <!-- Add Grade Level Section -->
        <div class="card bg-white p-6 rounded-lg shadow-md">
            <div class="font-semibold text-2xl text-indigo-600">Grade Level Management</div>
            <div class="flex justify-between items-center my-4">
                <Button label="Add Grade Level" icon="pi pi-plus" class="p-button-success bg-green-500 text-white" @click="openCreateForm" />
            </div>
        </div>

        <!-- Grade Levels Section -->
        <div class="card bg-white p-6 rounded-lg shadow-md mt-6">
            <div class="font-semibold text-2xl text-indigo-600">Grade Levels</div>
            <div class="grid grid-cols-12 gap-4 mt-4">
                <div v-for="(grade, index) in grades" :key="index" class="col-span-12 sm:col-span-6 lg:col-span-4 p-2">
                    <div class="p-6 border border-gray-300 rounded-lg bg-indigo-50 shadow-md cursor-pointer" @click="openSubjectManagement(grade)">
                        <div class="text-lg font-medium text-indigo-800">{{ grade.name }}</div>
                        <span class="text-sm text-gray-600">{{ grade.category }}</span>
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

    <!-- Subject Management Dialog -->
    <Dialog v-model:visible="showSubjectManagementDialog" header="Subject Management" modal class="w-[800px] h-[600px]">
        <div class="flex flex-col gap-4 h-full">
            <h2 class="text-xl font-semibold text-gray-700">{{ selectedGrade?.name }}</h2>
            <div class="flex justify-start mt-4">
                <Button label="Add Subject" class="p-button-success" @click="openAddSubjectDialog" />
            </div>
            <div class="grid grid-cols-12 gap-4 overflow-auto">
                <div v-for="(subject, index) in selectedGrade?.subjects" :key="index" class="col-span-12 sm:col-span-6 lg:col-span-4 p-2">
                    <div class="p-6 border border-gray-300 rounded-lg bg-blue-50 shadow-md">
                        <div class="text-lg font-medium text-blue-800">{{ subject }}</div>
                    </div>
                </div>
            </div>
        </div>
    </Dialog>

    <!-- Add Subject Dialog -->
    <Dialog v-model:visible="showAddSubjectDialog" header="Add Subjects" modal class="w-96">
        <div class="flex flex-col gap-4">
            <label class="font-medium text-gray-700">Select Subjects:</label>
            <MultiSelect v-model="selectedSubjects" :options="allSubjects" placeholder="Choose Subjects" class="w-full border border-gray-300 p-2 rounded-lg" />
            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" class="p-button-secondary" @click="showAddSubjectDialog = false" />
                <Button label="Save" class="p-button-success" @click="saveSelectedSubjects" />
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
