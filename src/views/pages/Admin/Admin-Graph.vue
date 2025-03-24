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

    <div class="card-container">
        <SakaiCard v-for="(grade, index) in grades" :key="grade.id" class="custom-card" :style="cardStyles[index]" @click="showSections(grade.name)">
            <div class="card-header">
                <h1 class="grade-name">{{ grade.name }}</h1>
            </div>
        </SakaiCard>
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
import SakaiCard from '@/components/SakaiCard.vue';
import { GradeService } from '@/router/service/Grades';
import Dialog from 'primevue/dialog';
import ProgressSpinner from 'primevue/progressspinner';
import { computed, onMounted, ref } from 'vue';

// Now this will be populated from GradeService
const grades = ref([]);
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

        grades.value = gradesData;

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

const getRandomGradient = () => {
    const colors = ['#ff9a9e', '#fad0c4', '#fbc2eb', '#a6c1ee', '#ffdde1', '#ee9ca7', '#ff758c', '#ff7eb3', '#c3cfe2', '#d4fc79', '#96e6a1', '#84fab0', '#8fd3f4', '#a18cd1'];

    const color1 = colors[Math.floor(Math.random() * colors.length)];
    const color2 = colors[Math.floor(Math.random() * colors.length)];

    return `linear-gradient(135deg, ${color1}, ${color2})`;
};

const cardStyles = computed(() =>
    grades.value.map(() => ({
        background: getRandomGradient()
    }))
);

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

.card-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    justify-content: center;
    align-items: center;
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
    padding: 0 20px 20px 20px;
}

.custom-card {
    width: 200px;
    height: 250px;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    cursor: pointer;
    text-align: center;
    transition:
        transform 0.2s,
        box-shadow 0.2s;
    color: white;
    font-weight: bold;
}

.custom-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.card-header {
    color: white;
    padding: 12px 15px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: bold;
    height: 100%;
}

.grade-name {
    font-size: 26px;
    margin: 0;
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
