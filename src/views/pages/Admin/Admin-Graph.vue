<template>
    <div class="attendance-container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="welcome-header">
                <h1 class="welcome-title">Welcome, Admin</h1>
                <p class="text-blue-100 font-normal">
                    {{ new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
                </p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="pi pi-users"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ summaryStats.totalStudents }}</h3>
                    <p>Total Records</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="pi pi-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ summaryStats.averageAttendance }}%</h3>
                    <p>Average Attendance</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon yellow">
                    <i class="pi pi-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ summaryStats.warningCount }}</h3>
                    <p>Warning (&lt;80% attendance)</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="pi pi-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3>{{ summaryStats.criticalCount }}</h3>
                    <p>Critical (&lt;70% attendance)</p>
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
                <div class="chart-controls">
                    <div class="chart-info">
                        <h2 class="chart-main-title">Student Attendance Analytics</h2>
                        <p class="chart-description">Monitor attendance patterns across all grade levels with detailed breakdowns</p>
                    </div>
                    <div class="chart-filters">
                        <div class="filter-group">
                            <label class="filter-label">Date Range:</label>
                            <select v-model="selectedDateRange" @change="onDateRangeChange" class="filter-select">
                                <option v-for="option in dateRangeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">View by:</label>
                            <select v-model="selectedGrade" @change="onGradeChange" class="filter-select">
                                <option v-for="option in gradeOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </div>
                        <div class="summary-stats">
                            <div class="mini-stat">
                                <span class="mini-stat-value">{{ summaryStats.totalStudents }}</span>
                                <span class="mini-stat-label">Total Records</span>
                            </div>
                            <div class="mini-stat">
                                <span class="mini-stat-value">{{ summaryStats.averageAttendance }}%</span>
                                <span class="mini-stat-label">Attendance Rate</span>
                            </div>
                        </div>
                    </div>
                </div>
                <AttendanceChart :chartData="defaultGradeChartData" />
                <div class="chart-insights">
                    <div class="insight-card">
                        <div class="insight-icon green">
                            <i class="pi pi-check-circle"></i>
                        </div>
                        <div class="insight-content">
                            <h4>Highest Attendance</h4>
                            <p>{{ insights.bestPerformingGrade }} with {{ insights.highestAttendanceRate }}% attendance rate</p>
                        </div>
                    </div>
                    <div class="insight-card">
                        <div class="insight-icon orange">
                            <i class="pi pi-exclamation-triangle"></i>
                        </div>
                        <div class="insight-content">
                            <h4>Needs Attention</h4>
                            <p>{{ insights.worstPerformingGrade }} requires follow-up with {{ insights.lowestAttendanceRate }}% attendance</p>
                        </div>
                    </div>
                    <div class="insight-card">
                        <div class="insight-icon blue">
                            <i class="pi pi-chart-bar"></i>
                        </div>
                        <div class="insight-content">
                            <h4>Overall Trend</h4>
                            <p>{{ insights.trendAnalysis }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else-if="error" class="error-message">
                <div class="error-icon">
                    <i class="pi pi-times-circle"></i>
                </div>
                <div class="error-content">
                    <h3>Unable to Load Data</h3>
                    <p>{{ error }}</p>
                    <button @click="loadAttendanceData" class="retry-button">
                        <i class="pi pi-refresh"></i> Try Again
                    </button>
                </div>
            </div>
            <div v-else class="no-data-message">
                <div class="no-data-icon">
                    <i class="pi pi-info-circle"></i>
                </div>
                <div class="no-data-content">
                    <h3>No Data Available</h3>
                    <p>No attendance records found for the selected date range.</p>
                    <p class="no-data-suggestion">Try selecting a different date range or check if attendance has been recorded.</p>
                </div>
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
import { AdminAttendanceService } from '@/services/AdminAttendanceService';
import { GradeService } from '@/router/service/Grades';
import Dialog from 'primevue/dialog';
import ProgressSpinner from 'primevue/progressspinner';
import { onMounted, ref } from 'vue';

const loading = ref(true);
const sectionLoading = ref(false);
const error = ref(null);

// Filter options
const selectedDateRange = ref('current_year');
const selectedGrade = ref('all');

const dateRangeOptions = ref([
    { label: 'Current School Year', value: 'current_year' },
    { label: 'Last 30 Days', value: 'last_30_days' },
    { label: 'Last 7 Days', value: 'last_7_days' }
]);

const gradeOptions = ref([
    { label: 'All Grades', value: 'all' }
]);

// Data states
const summaryStats = ref({
    totalStudents: 0,
    averageAttendance: 0,
    warningCount: 0,
    criticalCount: 0
});

const insights = ref({
    bestPerformingGrade: 'N/A',
    highestAttendanceRate: 0,
    worstPerformingGrade: 'N/A',
    lowestAttendanceRate: 0,
    trendAnalysis: 'Loading...'
});

// Chart data for grade-level attendance
const defaultGradeChartData = ref({
    labels: [],
    datasets: [
        { label: 'Present', backgroundColor: '#10b981', data: [] },
        { label: 'Absent', backgroundColor: '#ef4444', data: [] },
        { label: 'Late', backgroundColor: '#f59e0b', data: [] },
        { label: 'Excused', backgroundColor: '#8b5cf6', data: [] }
    ]
});

// Modal data
const showModal = ref(false);
const selectedGradeData = ref(null);

// Load attendance data from API
const loadAttendanceData = async () => {
    try {
        loading.value = true;
        error.value = null;
        console.log('Loading attendance data...');

        // Get grade ID for filtering if not 'all'
        const gradeId = selectedGrade.value === 'all' ? null : getGradeIdByName(selectedGrade.value);

        // Fetch attendance analytics
        const response = await AdminAttendanceService.getAttendanceAnalytics(selectedDateRange.value, gradeId);
        
        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch attendance data');
        }

        const analyticsData = response.data;
        console.log('Analytics data received:', analyticsData);

        // Transform data to chart format
        defaultGradeChartData.value = AdminAttendanceService.transformToChartData(analyticsData);

        // Update summary statistics
        summaryStats.value = AdminAttendanceService.calculateSummaryStats(analyticsData);

        // Update insights
        insights.value = AdminAttendanceService.getInsights(analyticsData);

        console.log('Chart data updated:', defaultGradeChartData.value);
        loading.value = false;

    } catch (err) {
        console.error('Error loading attendance data:', err);
        error.value = err.message || 'Failed to load attendance data';
        loading.value = false;
    }
};

// Load grades for dropdown options
const loadGradeOptions = async () => {
    try {
        const gradesData = await GradeService.getGrades();
        console.log('Grades data received:', gradesData);

        if (gradesData && gradesData.length > 0) {
            gradeOptions.value = [
                { label: 'All Grades', value: 'all' },
                ...gradesData.map((g) => ({
                    label: g.name,
                    value: g.name,
                    id: g.id
                }))
            ];
        }
    } catch (err) {
        console.error('Error loading grades:', err);
    }
};

// Helper function to get grade ID by name
const getGradeIdByName = (gradeName) => {
    const grade = gradeOptions.value.find(g => g.value === gradeName);
    return grade ? grade.id : null;
};

// Event handlers
const onDateRangeChange = () => {
    console.log('Date range changed to:', selectedDateRange.value);
    loadAttendanceData();
};

const onGradeChange = () => {
    console.log('Grade changed to:', selectedGrade.value);
    loadAttendanceData();
};

// Initialize component
onMounted(async () => {
    console.log('Admin-Graph component mounted');
    await loadGradeOptions();
    await loadAttendanceData();
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
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
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
    padding: 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin: 0 16px;
    overflow: hidden;
}

.chart-wrapper {
    width: 100%;
    margin: 0 auto;
}

.chart-controls {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 24px 24px 0 24px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 20px;
}

.chart-info {
    flex: 1;
    min-width: 300px;
}

.chart-main-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 8px 0;
    letter-spacing: -0.025em;
}

.chart-description {
    font-size: 1rem;
    color: #64748b;
    margin: 0;
    line-height: 1.5;
}

.chart-filters {
    display: flex;
    flex-direction: column;
    gap: 16px;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 12px;
}

.filter-label {
    font-size: 0.9rem;
    font-weight: 600;
    color: #374151;
    white-space: nowrap;
}

.filter-select {
    padding: 8px 12px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    background: white;
    font-size: 0.9rem;
    color: #374151;
    min-width: 200px;
    transition: border-color 0.2s ease;
}

.filter-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.summary-stats {
    display: flex;
    gap: 20px;
}

.mini-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.mini-stat-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}

.mini-stat-label {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 4px;
    text-align: center;
}

.chart-insights {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    padding: 24px;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}

.insight-card {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.insight-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.insight-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: white;
    flex-shrink: 0;
}

.insight-icon.green {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.insight-icon.orange {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.insight-icon.blue {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}

.insight-content h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 8px 0;
}

.insight-content p {
    font-size: 0.9rem;
    color: #64748b;
    margin: 0;
    line-height: 1.5;
}

@media (max-width: 768px) {
    .chart-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .chart-filters {
        align-items: stretch;
    }
    
    .summary-stats {
        justify-content: space-between;
    }
    
    .filter-select {
        min-width: auto;
        width: 100%;
    }
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

.no-data-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
}

.no-data-icon i {
    font-size: 2rem;
    color: #1976d2;
}

.no-data-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 1rem 0;
}

.no-data-content p {
    font-size: 1rem;
    color: #64748b;
    margin: 0 0 0.5rem 0;
    line-height: 1.6;
}

.no-data-suggestion {
    font-size: 0.9rem;
    color: #94a3b8;
    font-style: italic;
}

.error-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 2rem;
    min-height: 300px;
    text-align: center;
}

.error-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
}

.error-icon i {
    font-size: 2rem;
    color: #d32f2f;
}

.error-content h3 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 1rem 0;
}

.error-content p {
    font-size: 1rem;
    color: #64748b;
    margin: 0 0 1.5rem 0;
    line-height: 1.6;
}

.retry-button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.retry-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.title {
    margin-bottom: 1.5rem;
    color: #495057;
    font-size: 1.75rem;
    text-align: center;
    font-weight: 600;
}
</style>
