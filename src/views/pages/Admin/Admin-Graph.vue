<template>
    <div class="attendance-container">
        <h2 class="title">Attendance Overview</h2>
        <div v-if="loading" class="loading-container">
            <ProgressSpinner />
            <p>Loading attendance data...</p>
        </div>
        <div v-else-if="defaultGradeChartData.labels.length > 0" class="chart-wrapper">
            <AttendanceChart :chartData="defaultGradeChartData" />
        </div>
        <div v-else class="no-data-message">
            <i class="pi pi-exclamation-triangle"></i>
            <p>No attendance data available</p>
        </div>
    </div>

    <Dialog v-model:visible="showModal" modal header="Section Attendance Overview" :style="{ width: '80vw' }">
        <div v-if="sectionLoading" class="loading-container">
            <ProgressSpinner />
            <p>Loading section data...</p>
        </div>
        <div v-else-if="selectedGradeData && selectedGradeData.labels.length > 0" class="chart-wrapper">
            <AttendanceChart :chartData="selectedGradeData" />
        </div>
        <div v-else class="no-data-message">
            <i class="pi pi-info-circle"></i>
            <p>No attendance data available for this grade</p>
        </div>
    </Dialog>
</template>

<script setup>
import AttendanceChart from '@/components/Admin/AttendanceChart.vue';
import { GradeService } from '@/router/service/Grades';
import Dialog from 'primevue/dialog';
import ProgressSpinner from 'primevue/progressspinner';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const sectionLoading = ref(false);

// Default chart data for grade-level attendance
const defaultGradeChartData = ref({
    labels: [],
    datasets: [
        { label: 'Present', backgroundColor: '#90EE90', data: [] },
        { label: 'Absent', backgroundColor: '#FFB6C1', data: [] },
        { label: 'Late', backgroundColor: '#FFD580', data: [] }
    ]
});

// Section-wise attendance data
const gradeSectionsData = ref({});

const showModal = ref(false);
const selectedGradeData = ref(null);

const loadGradeData = async () => {
    try {
        loading.value = true;
        console.log('Loading grade data...');

        // Fetch grades from the centralized GradeService
        const gradesData = await GradeService.getGrades();
        console.log('Grades data received:', gradesData);

        if (!gradesData || gradesData.length === 0) {
            console.warn('No grades data received');
            loading.value = false;
            return;
        }

        // Update the labels in the default chart data
        defaultGradeChartData.value.labels = gradesData.map((g) => g.name);

        // Generate mock attendance data for each grade (this would be replaced with real data)
        const presentData = gradesData.map(() => Math.floor(Math.random() * 20) + 30);
        const absentData = gradesData.map(() => Math.floor(Math.random() * 10) + 3);
        const lateData = gradesData.map(() => Math.floor(Math.random() * 8) + 2);

        defaultGradeChartData.value.datasets[0].data = presentData;
        defaultGradeChartData.value.datasets[1].data = absentData;
        defaultGradeChartData.value.datasets[2].data = lateData;

        console.log('Chart data updated:', defaultGradeChartData.value);

        // Now build the sections data using the real sections from Grades.js
        gradesData.forEach((grade) => {
            if (!grade.sections || grade.sections.length === 0) {
                console.warn(`No sections found for grade ${grade.name}`);
                return;
            }

            // For each grade, create an entry in gradeSectionsData with its sections
            gradeSectionsData.value[grade.name] = {
                labels: grade.sections || [],
                datasets: [
                    {
                        label: 'Present',
                        backgroundColor: '#90EE90',
                        data: (grade.sections || []).map(() => Math.floor(Math.random() * 15) + 75)
                    },
                    {
                        label: 'Absent',
                        backgroundColor: '#FFB6C1',
                        data: (grade.sections || []).map(() => Math.floor(Math.random() * 8) + 2)
                    },
                    {
                        label: 'Late',
                        backgroundColor: '#FFD580',
                        data: (grade.sections || []).map(() => Math.floor(Math.random() * 10) + 5)
                    }
                ]
            };
        });

        console.log('Sections data prepared:', gradeSectionsData.value);
        loading.value = false;
    } catch (error) {
        console.error('Error loading grade data:', error);
        loading.value = false;
    }
};

const showSections = (gradeName) => {
    sectionLoading.value = true;
    showModal.value = true;

    // Short timeout to show loading state even if data is immediately available
    setTimeout(() => {
        console.log(`Showing sections for ${gradeName}:`, gradeSectionsData.value[gradeName]);
        selectedGradeData.value = gradeSectionsData.value[gradeName] || null;
        sectionLoading.value = false;
    }, 300);
};

onMounted(async () => {
    console.log('Admin-Graph component mounted');
    await loadGradeData();
});
</script>

<style scoped>
.attendance-container {
    width: 100%;
    padding: 20px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    margin-bottom: 30px;
}

.chart-wrapper {
    width: 100%;
    max-width: 1000px;
    height: 400px;
}

.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    gap: 1rem;
    min-height: 300px;
}

.no-data-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: #6c757d;
    gap: 0.5rem;
    min-height: 300px;
}

.no-data-message i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.title {
    margin-bottom: 1.5rem;
    color: #495057;
    font-size: 1.75rem;
    text-align: center;
    font-weight: 600;
}
</style>
