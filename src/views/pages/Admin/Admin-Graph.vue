<template>
    <div class="attendance-container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="welcome-header">
                <h1 class="welcome-title">Welcome, Admin Maria Santos</h1>
                <p class="welcome-date">Sunday, August 31, 2025</p>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="pi pi-users"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ totalStudents }}</h3>
                    <p>Total Students</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="pi pi-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ averageAttendance }}%</h3>
                    <p>Average Attendance</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow">
                    <i class="pi pi-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ warningCount }}</h3>
                    <p>Warning (5+ absences)</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="pi pi-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ criticalCount }}</h3>
                    <p>Critical (5+ absences)</p>
                </div>
            </div>
        </div>
        
        <!-- Main Content White Box -->
        <div class="content-box">
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
const selectedGrade = ref(null);
const gradeOptions = ref([]);

// Statistics data
const totalStudents = ref(10);
const averageAttendance = ref(85);
const warningCount = ref(3);
const criticalCount = ref(2);

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

        // Create dropdown options for grades
        gradeOptions.value = [
            { label: 'All Grades', value: 'all' },
            ...gradesData.map((g) => ({ 
                label: `${g.name} - Mathematics`, 
                value: g.name 
            }))
        ];

        // Set default selection
        selectedGrade.value = 'all';

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

const onGradeChange = () => {
    console.log('Grade changed to:', selectedGrade.value);
    if (selectedGrade.value === 'all') {
        // Show all grades data
        loadGradeData();
    } else {
        // Filter to show only selected grade
        filterByGrade(selectedGrade.value);
    }
};

const filterByGrade = (gradeName) => {
    console.log('Filtering by grade:', gradeName);
    
    // Find the index of the selected grade
    const gradeIndex = defaultGradeChartData.value.labels.indexOf(gradeName);
    
    if (gradeIndex !== -1) {
        // Create filtered data with only the selected grade
        defaultGradeChartData.value.labels = [gradeName];
        defaultGradeChartData.value.datasets[0].data = [defaultGradeChartData.value.datasets[0].data[gradeIndex]];
        defaultGradeChartData.value.datasets[1].data = [defaultGradeChartData.value.datasets[1].data[gradeIndex]];
        defaultGradeChartData.value.datasets[2].data = [defaultGradeChartData.value.datasets[2].data[gradeIndex]];
    }
};

onMounted(async () => {
    console.log('Admin-Graph component mounted');
    await loadGradeData();
});
</script>

<style scoped>
.attendance-container {
    width: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    margin-bottom: 30px;
}

.header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 24px 32px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 0 16px 24px 16px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-icon.blue {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-icon.green {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stat-icon.yellow {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
}

.stat-icon.red {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
}

.stat-content h3 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0 0 4px 0;
    color: #2c3e50;
}

.stat-content p {
    font-size: 0.9rem;
    color: #7f8c8d;
    margin: 0;
}

.welcome-header {
    color: white;
}

.welcome-title {
    font-size: 1.8rem;
    font-weight: 600;
    margin: 0 0 4px 0;
    color: white;
}

.welcome-date {
    font-size: 0.95rem;
    margin: 0;
    opacity: 0.9;
    color: white;
}

.search-section {
    min-width: 300px;
}

.search-dropdown {
    width: 100%;
    min-width: 300px;
}

.search-dropdown :deep(.p-dropdown) {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border: none;
    border-radius: 8px;
    padding: 4px;
}

.search-dropdown :deep(.p-dropdown-label) {
    padding: 8px 12px;
    font-size: 0.95rem;
    color: #495057;
}

.search-dropdown :deep(.p-dropdown:focus) {
    outline: none;
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.3);
}

.content-box {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin: 0 16px;
}

.chart-wrapper {
    width: 100%;
    max-width: 1000px;
    height: 400px;
    margin: 0 auto;
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
