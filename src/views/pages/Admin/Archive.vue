<template>
    <div class="p-6">
        <h2 class="text-2xl font-semibold mb-4">Archive</h2>

        <!-- Search Bar -->
        <div class="mb-4 flex items-center">
            <InputText v-model="searchQuery" placeholder="Search archives..." class="w-1/3 p-inputtext-lg" />
        </div>

        <!-- Archived Sections -->
        <h3 class="text-xl font-semibold mt-6">Archived Sections</h3>
        <div class="grid grid-cols-3 gap-4 mt-4">
            <div v-for="section in filteredSections" :key="section.id" class="folder-card">
                <i class="pi pi-folder text-yellow-500 text-4xl"></i>
                <p class="text-lg font-medium">{{ section.name }}</p>
                <span class="text-gray-500 text-sm">{{ section.archivedDate }}</span>
                <Button label="Recover" icon="pi pi-refresh" class="p-button-sm p-button-success mt-2" @click="confirmRecover(section, 'section')" />
            </div>
        </div>

        <!-- Folders for Graduated & Dropped Students -->
        <h3 class="text-xl font-semibold mt-6">Archived Students</h3>
        <div class="grid grid-cols-3 gap-4 mt-4">
            <div class="folder-card" @click="openModal('Graduated')">
                <i class="pi pi-folder text-green-500 text-4xl"></i>
                <p class="text-lg font-medium">Graduated Students</p>
            </div>
            <div class="folder-card" @click="openModal('Dropped')">
                <i class="pi pi-folder text-red-500 text-4xl"></i>
                <p class="text-lg font-medium">Dropped Students</p>
            </div>
        </div>

        <!-- Modal for Student List -->
        <Dialog v-model:visible="showModal" modal :header="modalTitle" class="max-w-md w-full rounded-lg">
            <div class="p-4 space-y-4">
                <div v-for="student in filteredStudents" :key="student.id" class="file-card">
                    <i class="pi pi-file text-blue-500 text-3xl"></i>
                    <p class="text-sm font-medium">{{ student.name }}</p>
                    <span class="badge" :class="statusClass(student.status)">{{ student.status }}</span>
                    <span class="text-gray-500 text-xs">{{ student.archivedDate }}</span>
                    <Button label="Recover" icon="pi pi-refresh" class="p-button-sm p-button-success mt-2" @click="confirmRecover(student, 'student')" />
                </div>
            </div>
        </Dialog>

        <!-- Confirm Recover Dialog -->
        <Dialog v-model:visible="confirmDialog" modal header="Confirm Recovery" class="max-w-md w-full rounded-lg">
            <p>Are you sure you want to recover this {{ recoverType }}?</p>
            <div class="flex justify-end mt-4">
                <Button label="Cancel" class="p-button-text" @click="confirmDialog = false" />
                <Button label="Recover" class="p-button-success" @click="recoverItem" />
            </div>
        </Dialog>
    </div>
</template>

<script setup>
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const toast = useToast();

const searchQuery = ref('');
const showModal = ref(false);
const confirmDialog = ref(false);
const modalTitle = ref('');
const recoverType = ref('');
const selectedItem = ref(null);

const archivedSections = ref([
    { id: 1, name: 'Grade 3 - Section A', archivedDate: '2025-03-10' },
    { id: 2, name: 'Grade 5 - Section B', archivedDate: '2025-02-28' }
]);

const archivedStudents = ref([
    { id: 1, name: 'John Doe', status: 'Dropped', archivedDate: '2025-02-15' },
    { id: 2, name: 'Jane Smith', status: 'Graduated', archivedDate: '2024-06-20' }
]);

const filteredSections = computed(() => {
    return archivedSections.value.filter(section =>
        section.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const filteredStudents = computed(() => {
    return archivedStudents.value.filter(student =>
        modalTitle.value.includes(student.status)
    );
});

const openModal = (status) => {
    modalTitle.value = `${status} Students`;
    showModal.value = true;
};

const confirmRecover = (item, type) => {
    selectedItem.value = item;
    recoverType.value = type;
    confirmDialog.value = true;
};

const recoverItem = () => {
    if (recoverType.value === 'section') {
        archivedSections.value = archivedSections.value.filter(s => s.id !== selectedItem.value.id);
    } else if (recoverType.value === 'student') {
        archivedStudents.value = archivedStudents.value.filter(s => s.id !== selectedItem.value.id);
    }

    toast.add({ severity: 'success', summary: 'Recovered', detail: `${selectedItem.value.name} has been recovered`, life: 3000 });
    confirmDialog.value = false;
    showModal.value = false;
};

// ✅ **Fix: Define the `statusClass` function**
const statusClass = (status) => {
    return status === 'Graduated' ? 'bg-green-500 text-white' : 'bg-red-500 text-white';
};
</script>

<style scoped>
.folder-card, .file-card {
    @apply bg-white p-4 rounded-lg shadow flex flex-col items-center cursor-pointer;
    transition: transform 0.2s ease-in-out;
}
.folder-card:hover, .file-card:hover {
    transform: scale(1.05);
}
.badge {
    @apply text-xs font-bold px-2 py-1 rounded mt-2;
}
</style>
