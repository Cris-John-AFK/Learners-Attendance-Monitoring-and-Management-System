<template>
    <!-- Total Absentees -->
    <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card fixed-size-card">
            <div class="flex justify-between mb-4">
                <div>
                    <span class="block text-muted-color font-medium mb-4">Total Absentees</span>
                    <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">3</div>
                    <span class="text-green-500 font-medium flex items-center" v-if="improvementFromYesterday">
                        <i class="pi pi-arrow-up mr-1"></i> 5%
                    </span>
                </div>
                <div class="flex items-center justify-center icon-container bg-orange-100 dark:bg-blue-400/10">
                    <i class="pi pi-user-minus text-red-500"></i>
                </div>
            </div>
            <span class="text-primary font-medium">as of {{ formattedDate }}</span>
        </div>
    </div>

    <!-- Total Presentees -->
    <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card fixed-size-card">
            <div class="flex justify-between mb-4">
                <div>
                    <span class="block text-muted-color font-medium mb-4">Total Presentees</span>
                    <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">9</div>
                </div>
                <div class="flex items-center justify-center icon-container bg-green-100 dark:bg-orange-400/10">
                    <i class="pi pi-user-plus text-green-500"></i>
                </div>
            </div>
            <span class="text-primary font-medium">as of {{ formattedDate }}</span>
        </div>
    </div>

    <!-- Total Students -->
    <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div class="card fixed-size-card">
            <div class="flex justify-between mb-4">
                <div>
                    <span class="block text-muted-color font-medium mb-4">Total Students</span>
                    <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">12</div>
                </div>
                <div class="flex items-center justify-center icon-container bg-cyan-100 dark:bg-cyan-400/10">
                    <i class="pi pi-users text-cyan-500"></i>
                </div>
            </div>
            <span class="text-primary font-medium">as of {{ formattedDate }}</span>
        </div>
    </div>

    <!-- Most Absences -->
    <div class="col-span-12 lg:col-span-6 xl:col-span-3">
        <div
            class="card fixed-size-card cursor-pointer transition-transform transform hover:scale-105 hover:bg-gray-100 dark:hover:bg-gray-800 shadow-lg"
            @click="showModal = true"
        >
            <div class="flex justify-between mb-1">
                <div>
                    <span class="block text-muted-color font-medium mb-4">Most Absences</span>
                    <div class="text-surface-900 dark:text-surface-0 font-medium text-xl">
                        {{ mostAbsentStudent.name }} - {{ mostAbsentStudent.absences }} absents
                    </div>
                    <span class="text-blue-500 text-sm font-medium mt-2 block">Click to view details</span>
                </div>
            </div>
            <span class="text-primary font-medium">as of {{ formattedDate }}</span>
        </div>
    </div>

    <!-- Modal (Dialog) -->
    <Dialog v-model:visible="showModal" modal header="Most Absences (Past Week)">
        <div class="p-4">
            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="border border-gray-300 px-4 py-2">Name</th>
                        <th class="border border-gray-300 px-4 py-2">Section</th>
                        <th class="border border-gray-300 px-4 py-2">Absences</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="student in students" :key="student.name" class="text-center">
                        <td class="border border-gray-300 px-4 py-2">{{ student.name }}</td>
                        <td class="border border-gray-300 px-4 py-2">{{ student.section }}</td>
                        <td class="border border-gray-300 px-4 py-2">{{ student.absences }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import { computed, ref } from 'vue';

const showModal = ref(false);
const improvementFromYesterday = ref(true);

const students = ref([
    { name: "Cris John Canales", section: "A", absences: 20 },
    { name: "Jane Doe", section: "B", absences: 15 },
    { name: "John Smith", section: "C", absences: 10 },
    { name: "Emily Johnson", section: "D", absences: 8 }
]);

const formattedDate = computed(() => {
    const today = new Date();
    return `${today.getMonth() + 1}/${today.getDate()}/${today.getFullYear()}`;
});

const mostAbsentStudent = computed(() => {
    return students.value.reduce((max, student) => (student.absences > max.absences ? student : max), students.value[0]);
});
</script>

<style scoped>
.card {
    padding: 1.5rem;
    border-radius: 8px;
    background-color: white;
    box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease-in-out, background-color 0.2s ease-in-out;
}

/* Each card has the same static width and height */
.fixed-size-card {
    width: 240px;
    height: 160px;
}

/* Icon container */
.icon-container {
    width: 2.5rem;
    height: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
