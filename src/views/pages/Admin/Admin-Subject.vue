<script setup>
import { ref, computed } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Button from 'primevue/button';

const subjects = ref([
    { id: 1, name: 'Mathematics', gradeLevels: ['Grade 1', 'Grade 2', 'Grade 3'] },
    { id: 2, name: 'Science', gradeLevels: ['Grade 4', 'Grade 5', 'Grade 6'] }
]);

const gradeLevels = ['Kinder 1', 'Kinder 2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'];

const searchQuery = ref('');
const editDialog = ref(false);
const createDialog = ref(false);
const editingSubject = ref(null);

const newSubject = ref({
    name: '',
    gradeLevels: []
});

const filteredSubjects = computed(() => {
    return subjects.value.filter((subject) => {
        return subject.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || subject.gradeLevels.some((level) => level.toLowerCase().includes(searchQuery.value.toLowerCase()));
    });
});

function openCreateForm() {
    newSubject.value = { name: '', gradeLevels: [] };
    createDialog.value = true;
}

function saveNewSubject() {
    if (newSubject.value.name && newSubject.value.gradeLevels.length) {
        subjects.value.push({
            id: subjects.value.length + 1,
            ...newSubject.value
        });
        createDialog.value = false;
    }
}

function openEditForm(subject) {
    editingSubject.value = { ...subject };
    newSubject.value = { ...subject };
    editDialog.value = true;
}

function saveEditSubject() {
    if (editingSubject.value) {
        const index = subjects.value.findIndex((s) => s.id === editingSubject.value.id);
        if (index !== -1) {
            subjects.value[index] = { ...editingSubject.value, ...newSubject.value };
        }
        editDialog.value = false;
        editingSubject.value = null;
    }
}

function deleteSubject(subject) {
    const index = subjects.value.findIndex((s) => s.id === subject.id);
    if (index !== -1) {
        subjects.value.splice(index, 1);
    }
}
</script>

<template>
    <div class="p-4 bg-white shadow-md rounded-lg">
        <h2 class="text-xl font-bold mb-4">Subject Management</h2>

        <!-- Search and Create Button -->
        <div class="flex justify-between items-center mb-4">
            <Button label="Create Subject" class="p-button-success" @click="openCreateForm" />
            <InputText v-model="searchQuery" placeholder="Search subjects..." class="w-1/3" />
        </div>

        <!-- Subject List -->
        <div>
            <table class="table-auto w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border border-gray-300 p-2">Subject Name</th>
                        <th class="border border-gray-300 p-2">Grade Levels</th>
                        <th class="border border-gray-300 p-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="subject in filteredSubjects" :key="subject.id" class="hover:bg-gray-100">
                        <td class="border border-gray-300 p-2">{{ subject.name }}</td>
                        <td class="border border-gray-300 p-2">
                            {{ subject.gradeLevels.join(', ') }}
                        </td>
                        <td class="border border-gray-300 p-2">
                            <div class="flex space-x-2">
                                <Button icon="pi pi-pencil" class="p-button-rounded p-button-primary" @click="openEditForm(subject)" />
                                <Button icon="pi pi-trash" class="p-button-rounded p-button-danger" @click="deleteSubject(subject)" />
                            </div>
                        </td>
                    </tr>
                    <tr v-if="filteredSubjects.length === 0">
                        <td colspan="3" class="text-center p-4">No subjects found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Dialog -->
        <Dialog v-model:visible="createDialog" header="Create Subject" modal class="w-1/2">
            <div class="p-4">
                <label class="block font-medium mb-2">Subject Name</label>
                <InputText v-model="newSubject.name" placeholder="Enter subject name" class="w-full mb-4" />
                <label class="block font-medium mb-2">Grade Levels</label>
                <MultiSelect v-model="newSubject.gradeLevels" :options="gradeLevels" placeholder="Select grade levels" class="w-full" />
                <div class="flex justify-end space-x-2 mt-4">
                    <Button label="Cancel" class="p-button-text" @click="createDialog = false" />
                    <Button label="Add Subject" class="p-button-success" @click="saveNewSubject" />
                </div>
            </div>
        </Dialog>

        <!-- Edit Dialog -->
        <Dialog v-model:visible="editDialog" header="Edit Subject" modal class="w-1/2">
            <div class="p-4">
                <label class="block font-medium mb-2">Subject Name</label>
                <InputText v-model="newSubject.name" placeholder="Enter subject name" class="w-full mb-4" />
                <label class="block font-medium mb-2">Grade Levels</label>
                <MultiSelect v-model="newSubject.gradeLevels" :options="gradeLevels" placeholder="Select grade levels" class="w-full" />
                <div class="flex justify-end space-x-2 mt-4">
                    <Button label="Cancel" class="p-button-text" @click="editDialog = false" />
                    <Button label="Save Changes" class="p-button-primary" @click="saveEditSubject" />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
.table-auto th,
.table-auto td {
    text-align: left;
    padding: 0.5rem;
}
</style>
