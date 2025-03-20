<script setup>
import { PhotoService } from '@/router/service/PhotoService';
import { StudentAttendanceService } from '@/router/service/StudentAttendanceService';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const student = ref(null);
const attendanceRecords = ref([]);
const searchQuery = ref('');

// Fetch student and their attendance logs
async function fetchStudent() {
    try {
        const studentsData = await StudentAttendanceService.getStudentsLarge();
        const studentData = studentsData.find((stud) => stud.id == route.params.id) || null;

        if (studentData) {
            const studentIndex = studentsData.findIndex((stud) => stud.id == studentData.id);
            const photos = await PhotoService.getImages();
            studentData.photo = photos[studentIndex]?.itemImageSrc || '';

            student.value = studentData;

            attendanceRecords.value = student.value.attendance.flatMap((subject) =>
                subject.logs.map((log) => ({
                    subject: subject.subject,
                    teacher: subject.teacher,
                    date: log.date,
                    timeIn: log.timeIn,
                    timeOut: log.timeOut
                }))
            );

            // Sort records by date and time (latest first)
            attendanceRecords.value.sort((a, b) => {
                if (a.date !== b.date) {
                    return new Date(b.date) - new Date(a.date); // Sort by date first
                }
                return b.timeIn.localeCompare(a.timeIn); // Sort by timeIn within the same date
            });
        }
    } catch (error) {
        console.error('Error fetching student:', error);
    }
}

// Computed property for filtered records
const filteredRecords = computed(() => {
    if (!searchQuery.value) return attendanceRecords.value;
    return attendanceRecords.value.filter((record) => Object.values(record).some((value) => String(value).toLowerCase().includes(searchQuery.value.toLowerCase())));
});

// Function to go back to the search page
const goBack = () => {
    router.go(-1);
};
onMounted(fetchStudent);
</script>

<template>
    <div class="container mx-auto p-4">
        <!-- Back to Search Button -->
        <Button label="Back to Search" icon="pi pi-arrow-left" class="mb-4" @click="goBack" />

        <h1 class="text-2xl font-bold">Student Attendance Details</h1>

        <div v-if="student" class="mt-4 flex gap-4">
            <img v-if="student.photo" :src="student.photo" alt="Student Photo" class="w-32 h-32 object-cover rounded-lg border" />
            <div>
                <p><strong>Name:</strong> {{ student.firstName }} {{ student.middleName ? student.middleName + ' ' : '' }}{{ student.lastName }}</p>
                <p><strong>Gender:</strong> {{ student.gender }}</p>
                <p><strong>Grade Level:</strong> {{ student.gradeLevel }}</p>
                <p><strong>Section:</strong> {{ student.section }}</p>
            </div>
        </div>

        <div v-else class="text-red-500 mt-4">
            <p>Student not found.</p>
        </div>

        <h2 class="text-xl font-semibold mt-4">Attendance Records</h2>

        <!-- Search Input -->
        <div class="my-2">
            <InputText v-model="searchQuery" placeholder="Search records..." class="w-full p-2 border rounded" />
        </div>

        <!-- Attendance Table (With Paginator) -->
        <DataTable :value="filteredRecords" paginator :rows="5">
            <Column field="date" header="Date"></Column>
            <Column field="subject" header="Subject"></Column>
            <Column field="teacher" header="Teacher"></Column>
            <Column field="timeIn" header="Time In"></Column>
            <Column field="timeOut" header="Time Out"></Column>
        </DataTable>
    </div>
</template>
