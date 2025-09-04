<template>
    <div class="student-qrcodes-page p-4">
        <div class="header mb-4">
            <h1 class="text-2xl font-bold">Student QR Codes</h1>
            <p class="text-gray-600">Use these QR codes for attendance tracking</p>
        </div>

        <div class="flex justify-between items-center mb-4">
            <div class="flex gap-2">
                <Button label="Print All QR Codes" icon="pi pi-print" @click="printQRCodes" />
                <Button label="Generate New QR Codes" icon="pi pi-refresh" @click="regenerateQRCodes" class="p-button-outlined" />
            </div>
            <div class="search-container">
                <span class="p-input-icon-left w-full">
                    <i class="pi pi-search" />
                    <InputText v-model="searchQuery" placeholder="Search students..." class="w-full" />
                </span>
            </div>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="flex justify-center items-center py-8">
            <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" />
            <span class="ml-3">Loading student data...</span>
        </div>

        <!-- QR code grid -->
        <div v-else class="qrcode-grid">
            <div v-for="student in filteredStudents" :key="student.id" class="qrcode-item p-3">
                <StudentQRCode :studentId="student.id" :studentName="student.name" />
            </div>
        </div>

        <!-- No results message -->
        <div v-if="!loading && filteredStudents.length === 0" class="text-center py-8 text-gray-500">
            <i class="pi pi-search text-4xl mb-3"></i>
            <p>No students match your search criteria</p>
        </div>
    </div>
</template>

<script setup>
import StudentQRCode from '@/components/StudentQRCode.vue';
import { AttendanceService } from '@/router/service/Attendance';
import { QRCodeAPIService } from '@/router/service/QRCodeAPIService';
import { computed, onMounted, ref } from 'vue';

// State variables
const students = ref([]);
const loading = ref(true);
const searchQuery = ref('');

// Load students on component mount
onMounted(async () => {
    try {
        loading.value = true;
        // Load Grade 3 students for QR code generation
        const studentsData = await AttendanceService.getStudentsByGrade(3);

        if (studentsData && studentsData.length > 0) {
            students.value = studentsData;
            console.log('Loaded students for QR codes:', students.value.length);
        } else {
            console.warn('No Grade 3 students found');
            students.value = [];
        }
    } catch (error) {
        console.error('Error loading students:', error);
        students.value = [];
    } finally {
        loading.value = false;
    }
});

// Computed property for filtered students
const filteredStudents = computed(() => {
    if (!searchQuery.value.trim()) return students.value;

    const query = searchQuery.value.toLowerCase();
    return students.value.filter((student) => student.name.toLowerCase().includes(query) || student.id.toString().includes(query));
});

// Print all QR codes
const printQRCodes = () => {
    window.print();
};

// Regenerate all QR codes
const regenerateQRCodes = async () => {
    try {
        loading.value = true;
        for (const student of students.value) {
            await QRCodeAPIService.generateQRCode(student.id);
        }
        // Force refresh of the component
        students.value = [...students.value];
    } catch (error) {
        console.error('Error regenerating QR codes:', error);
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.qrcode-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
}

.qrcode-item {
    background-color: #f9f9f9;
    border-radius: 8px;
    transition: transform 0.2s;
}

.qrcode-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

@media print {
    .header,
    .flex,
    .search-container,
    .p-button {
        display: none !important;
    }

    .qrcode-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
    }

    .qrcode-item {
        page-break-inside: avoid;
        background-color: white;
        box-shadow: none;
    }
}
</style>
