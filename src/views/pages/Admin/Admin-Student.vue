<script setup>
import { ref } from 'vue';

const students = ref([
    { id: 1, name: 'John Doe', qrId: 'QR123456', gradeLevel: 'Grade 3', age: 9, birthdate: '2015-04-12' },
    { id: 2, name: 'Jane Smith', qrId: 'QR654321', gradeLevel: 'Grade 5', age: 11, birthdate: '2013-07-08' },
    { id: 3, name: 'Alice Johnson', qrId: 'QR987654', gradeLevel: 'Grade 1', age: 7, birthdate: '2017-09-21' },
    { id: 4, name: 'Bob Williams', qrId: 'QR456789', gradeLevel: 'Kinder', age: 6, birthdate: '2018-12-05' }
]);

const expandedRows = ref([]);
const gradeLevels = ['Kinder', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'];
const showModal = ref(false);

const newStudent = ref({
    name: '',
    qrId: '',
    gradeLevel: '',
    age: '',
    birthdate: ''
});

const expandAll = () => {
    expandedRows.value = students.value.reduce((acc, s) => (acc[s.id] = true) && acc, {});
};

const collapseAll = () => {
    expandedRows.value = null;
};

const openCreateForm = () => {
    showModal.value = true;
};

const closeCreateForm = () => {
    showModal.value = false;
};

const addStudent = () => {
    if (newStudent.value.name && newStudent.value.gradeLevel) {
        students.value.push({
            id: students.value.length + 1,
            name: newStudent.value.name,
            qrId: `QR${100000 + students.value.length}`,
            gradeLevel: newStudent.value.gradeLevel,
            age: newStudent.value.age,
            birthdate: newStudent.value.birthdate
        });

        newStudent.value = { name: '', qrId: '', gradeLevel: '', age: '', birthdate: '' };
        closeCreateForm();
    }
};
</script>

<template>
    <div class="card p-6 shadow-lg rounded-lg bg-white">
        <h2 class="text-2xl font-semibold mb-6">Student Management</h2>

        <div class="flex justify-between items-center mb-4">
            <Button label="Create" icon="pi pi-plus" class="p-button-success" @click="openCreateForm" />
            <div class="flex gap-2">
                <Button text icon="pi pi-plus" label="Expand All" @click="expandAll" />
                <Button text icon="pi pi-minus" label="Collapse All" @click="collapseAll" />
            </div>
        </div>

        <DataTable v-model:expandedRows="expandedRows" :value="students" dataKey="id" class="p-datatable-striped">
            <Column expander style="width: 3rem" />
            <Column field="name" header="Name" sortable />
            <Column field="qrId" header="QR ID" sortable />
            <Column field="gradeLevel" header="Grade Level" sortable />
            <Column field="age" header="Age" sortable />
            <Column field="birthdate" header="Birthdate" sortable />

            <template #expansion="slotProps">
                <div class="p-4 bg-gray-100 rounded-md shadow-md">
                    <h5 class="text-lg font-semibold">Details for {{ slotProps.data.name }}</h5>
                    <p class="mt-2"><strong>QR ID:</strong> {{ slotProps.data.qrId }}</p>
                    <p><strong>Grade Level:</strong> {{ slotProps.data.gradeLevel }}</p>
                    <p><strong>Age:</strong> {{ slotProps.data.age }}</p>
                    <p><strong>Birthdate:</strong> {{ slotProps.data.birthdate }}</p>
                </div>
            </template>
        </DataTable>

        <!-- Modal -->
        <Dialog v-model:visible="showModal" modal header="Create Student" class="max-w-md w-full rounded-lg">
            <div class="p-4 space-y-4">
                <!-- Student Name -->
                <div>
                    <label for="name" class="block text-gray-700 font-medium">Student Name</label>
                    <InputText id="name" v-model="newStudent.name" placeholder="Enter Student Name" class="w-full p-inputtext-lg" />
                </div>

                <!-- Grade Level -->
                <div>
                    <label for="gradeLevel" class="block text-gray-700 font-medium">Grade Level</label>
                    <Dropdown id="gradeLevel" v-model="newStudent.gradeLevel" :options="gradeLevels" placeholder="Select Grade Level" class="w-full p-inputtext-lg" />
                </div>

                <!-- Age & Birthdate (Side by Side) -->
                <div class="flex gap-4">
                    <div class="flex-1">
                        <label for="age" class="block text-gray-700 font-medium">Age</label>
                        <InputText id="age" v-model="newStudent.age" type="number" placeholder="Enter Age" class="w-full p-inputtext-lg" />
                    </div>

                    <div class="flex-1">
                        <label for="birthdate" class="block text-gray-700 font-medium">Birthdate</label>
                        <Calendar id="birthdate" v-model="newStudent.birthdate" placeholder="Select Birthdate" class="w-full p-inputtext-lg" />
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex justify-end space-x-2 mt-4">
                    <Button label="Cancel" class="p-button-text" @click="closeCreateForm" />
                    <Button label="Add Student" icon="pi pi-user-plus" class="p-button-success" @click="addStudent" />
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
