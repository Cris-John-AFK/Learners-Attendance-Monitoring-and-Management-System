<script setup>
import { GradeService } from '@/router/service/Grades';
import { AttendanceService } from '@/router/service/Students';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const toast = useToast();
const students = ref([]);
const grades = ref([]);
const loading = ref(true);
const studentDialog = ref(false);
const deleteStudentDialog = ref(false);
const expandedRows = ref([]);
const student = ref({
    id: null,
    name: '',
    gender: 'Male',
    gradeLevel: 1,
    section: 'A',
    email: '',
    phone: '',
    address: '',
    photo: null
});
const submitted = ref(false);
const filters = ref({
    grade: null,
    section: null,
    gender: null,
    searchTerm: ''
});
const sections = ref([]);

// Load all grade levels and sections
const loadGradesAndSections = async () => {
    try {
        // Get all grades - using the centralized service
        const gradesData = await GradeService.getGrades();
        grades.value = gradesData;

        // Set default sections based on first grade
        if (gradesData.length > 0) {
            sections.value = gradesData[0].sections;
        }
    } catch (error) {
        console.error('Error loading grade data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load grade data',
            life: 3000
        });
    }
};

// Load all students - using the centralized student service
const loadStudents = async () => {
    try {
        loading.value = true;
        // Get from centralized store
        const data = await AttendanceService.getData();
        students.value = data;
        loading.value = false;
    } catch (error) {
        console.error('Error loading student data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load student data',
            life: 3000
        });
        loading.value = false;
    }
};

// Now the computed property will work properly with the import
const filteredStudents = computed(() => {
    return students.value.filter((student) => {
        // Apply grade filter
        if (filters.value.grade && student.gradeLevel !== parseInt(filters.value.grade)) {
            return false;
        }

        // Apply section filter
        if (filters.value.section && student.section !== filters.value.section) {
            return false;
        }

        // Apply gender filter
        if (filters.value.gender && student.gender !== filters.value.gender) {
            return false;
        }

        // Apply search term
        if (filters.value.searchTerm) {
            const term = filters.value.searchTerm.toLowerCase();
            return student.name.toLowerCase().includes(term) || student.id.toString().includes(term);
        }

        return true;
    });
});

// Save student - using centralized service for create/update
const saveStudent = async () => {
    submitted.value = true;

    if (!student.value.name.trim()) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Please enter a name',
            life: 3000
        });
        return;
    }

    try {
        if (student.value.id) {
            // Update existing student using centralized service
            await AttendanceService.updateStudent(student.value.id, student.value);
        } else {
            // Create new student using centralized service
            await AttendanceService.createStudent(student.value);
        }

        // Reload from centralized store
        await loadStudents();

        studentDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: student.value.id ? 'Student Updated' : 'Student Created',
            life: 3000
        });
    } catch (error) {
        console.error('Error saving student:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save student',
            life: 3000
        });
    }
};

// Delete student - using centralized service
const deleteStudent = async () => {
    try {
        await AttendanceService.deleteStudent(student.value.id);

        // Reload from centralized store
        await loadStudents();

        deleteStudentDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Student Deleted',
            life: 3000
        });
    } catch (error) {
        console.error('Error deleting student:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete student',
            life: 3000
        });
    }
};

// Initialize component
onMounted(async () => {
    await loadGradesAndSections();
    await loadStudents();
});
</script>

<template>
    <div class="card p-6 shadow-lg rounded-lg bg-white">
        <h2 class="text-2xl font-semibold mb-6">Student Management</h2>

        <div class="flex justify-between items-center mb-4">
            <Button label="Create" icon="pi pi-plus" class="p-button-success" @click="studentDialog = true" />

            <InputText v-model="filters.searchTerm" placeholder="Search students..." class="p-inputtext-lg w-1/3" />
        </div>

        <DataTable v-model:expandedRows="expandedRows" :value="filteredStudents" dataKey="id" class="p-datatable-striped">
            <Column expander style="width: 3rem" />
            <Column field="name" header="Name" sortable />
            <Column field="id" header="QR ID" sortable />
            <Column field="gradeLevel" header="Grade Level" sortable />
            <Column header="Age" sortable>
                <template #body>
                    <span>--</span>
                    <!-- Placeholder for age -->
                </template>
            </Column>
            <Column header="Birthdate" sortable>
                <template #body>
                    <span>--</span>
                    <!-- Placeholder for birthdate -->
                </template>
            </Column>

            <template #expansion="slotProps">
                <div class="p-4 bg-gray-100 rounded-md shadow-md">
                    <h5 class="text-lg font-semibold">Details for {{ slotProps.data.name }}</h5>
                    <p class="mt-2"><strong>QR ID:</strong> {{ slotProps.data.id }}</p>
                    <p><strong>Grade Level:</strong> {{ slotProps.data.gradeLevel }}</p>
                    <p><strong>Section:</strong> {{ slotProps.data.section }}</p>
                    <p><strong>Gender:</strong> {{ slotProps.data.gender }}</p>
                    <div class="flex justify-end mt-3">
                        <Button
                            icon="pi pi-pencil"
                            class="p-button-warning mr-2"
                            @click="
                                student = { ...slotProps.data };
                                studentDialog = true;
                            "
                        />
                        <Button
                            icon="pi pi-trash"
                            class="p-button-danger"
                            @click="
                                student = { ...slotProps.data };
                                deleteStudentDialog = true;
                            "
                        />
                    </div>
                </div>
            </template>
        </DataTable>

        <!-- Student Dialog -->
        <Dialog v-model:visible="studentDialog" modal header="Student Details" :style="{ width: '500px' }">
            <div class="p-4 space-y-4 left-2">
                <div>
                    <label for="name" class="block text-gray-700 font-medium">Student Name</label>
                    <InputText id="name" v-model="student.name" placeholder="Enter Student Name" class="w-full" />
                </div>
                <div>
                    <label for="gradeLevel" class="block font-medium">Grade Level</label>
                    <Dropdown id="gradeLevel" v-model="student.gradeLevel" :options="[0, 1, 2, 3, 4, 5, 6]" optionLabel="label" optionValue="value" placeholder="Select Grade Level" class="w-full" />
                </div>
                <div>
                    <label for="section" class="block font-medium">Section</label>
                    <Dropdown id="section" v-model="student.section" :options="['A', 'B', 'C', 'D', 'E']" placeholder="Select Section" class="w-full" />
                </div>
                <div>
                    <label for="gender" class="block font-medium">Gender</label>
                    <Dropdown id="gender" v-model="student.gender" :options="['Male', 'Female']" placeholder="Select Gender" class="w-full" />
                </div>
                <div class="flex justify-end space-x-2 mt-4">
                    <Button label="Cancel" class="p-button-text" @click="studentDialog = false" />
                    <Button label="Save" icon="pi pi-check" class="p-button-success" @click="saveStudent" />
                </div>
            </div>
        </Dialog>

        <!-- Delete Confirmation -->
        <Dialog v-model:visible="deleteStudentDialog" modal header="Confirm Deletion" :style="{ width: '400px' }">
            <div class="p-4">
                <p>Are you sure you want to delete this student?</p>
                <div class="flex justify-end space-x-2 mt-4">
                    <Button label="No" class="p-button-text" @click="deleteStudentDialog = false" />
                    <Button label="Yes" icon="pi pi-trash" class="p-button-danger" @click="deleteStudent" />
                </div>
            </div>
        </Dialog>
    </div>
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

:deep(.p-dialog) {
    border-radius: 12px;
}

:deep(.p-inputtext-lg) {
    padding: 0.75rem;
    border-radius: 8px;
}

:deep(.p-button-success) {
    background-color: #22c55e;
    border: none;
}

:deep(.p-button-success:hover) {
    background-color: #16a34a;
}
</style>
