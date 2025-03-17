<script setup>
import { StudentAttendanceService } from '@/router/service/StudentAttendanceService';
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const searchId = ref('');
const dialogVisible = ref(false);

async function searchStudentById() {
    const data = await StudentAttendanceService.getStudentsLarge();
    const foundStudent = data.find((student) => student.id == searchId.value);

    if (foundStudent) {
        router.push(`/guest/student/${searchId.value}`);
    } else {
        dialogVisible.value = true; // Show overlay if not found
    }
}
</script>

<template>
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold">Search Student Attendance</h1>

        <div class="mt-4 flex gap-4">
            <InputText v-model="searchId" placeholder="Enter Student ID" class="p-inputtext-lg" />
            <Button label="Search" @click="searchStudentById" />
        </div>

        <!-- Not Found Overlay -->
        <Dialog v-model:visible="dialogVisible" header="Error" :modal="true" :closable="true">
            <p class="text-red-500">Student ID not found.</p>
        </Dialog>
    </div>
</template>
