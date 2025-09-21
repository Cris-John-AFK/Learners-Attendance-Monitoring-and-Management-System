<template>
    <div class="modern-dashboard" style="margin: 0 1rem">
        <!-- Loading State -->
        <div v-if="loading" class="loading-overlay">
            <div class="loading-content">
                <ProgressSpinner strokeWidth="4" style="width: 60px; height: 60px" class="text-purple-500" />
                <p class="mt-4 text-gray-600 font-medium">Loading attendance analytics...</p>
            </div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="error-card">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="pi pi-exclamation-triangle text-red-500 text-xl mr-3"></i>
                    <div>
                        <h3 class="text-red-800 font-semibold">Error Loading Data</h3>
                        <p class="text-red-600 text-sm mt-1">{{ error }}</p>
                    </div>
                </div>
                <button @click="loadDashboardData" class="retry-btn">
                    <i class="pi pi-refresh mr-2"></i>Retry
                </button>
            </div>
        </div>

        <div v-else>
            <!-- Modern Header with Navigation Breadcrumbs -->
            <div class="dashboard-header">
                <div class="flex items-center justify-between">
                    <div class="header-content">
                        <div class="breadcrumb-nav">
                            <button 
                                v-if="currentView !== 'overview'" 
                                @click="navigateBack" 
                                class="back-btn"
                            >
                                <i class="pi pi-arrow-left"></i>
                            </button>
                            <div class="breadcrumb-text">
                                <span class="breadcrumb-item">Dashboard</span>
                                <span v-if="currentView === 'grade'" class="breadcrumb-separator">></span>
                                <span v-if="currentView === 'grade'" class="breadcrumb-item">{{ selectedGrade?.name }}</span>
                                <span v-if="currentView === 'section'" class="breadcrumb-separator">></span>
                                <span v-if="currentView === 'section'" class="breadcrumb-item">{{ selectedSection?.section_name }}</span>
                            </div>
                        </div>
                        <h1 class="dashboard-title">
                            <span v-if="currentView === 'overview'">Attendance Analytics</span>
                            <span v-else-if="currentView === 'grade'">{{ selectedGrade?.name }} - Sections</span>
                            <span v-else-if="currentView === 'section'">{{ selectedSection?.section_name }} - Students</span>
                        </h1>
                        <p class="dashboard-subtitle">{{ currentDateTime }}</p>
                    </div>
                    
                    <div class="header-controls">
                        <!-- Date Range Filter -->
                        <div class="date-filter">
                            <Dropdown 
                                v-model="selectedDateRange" 
                                :options="dateRangeOptions" 
                                optionLabel="label" 
                                optionValue="value"
                                @change="onDateRangeChange"
                                class="date-dropdown"
                            />
                        </div>
                        
                        <!-- Real-time indicator -->
                        <div class="realtime-indicator">
                            <div class="pulse-dot"></div>
                            <span>Live</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modern Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-card-primary" @click="refreshData">
                    <div class="stat-icon">
                        <i class="pi pi-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Total Students</div>
                        <div class="stat-value">{{ dashboardData.overview?.total_students || 0 }}</div>
                        <div class="stat-trend">
                            <i class="pi pi-arrow-up"></i>
                            <span>Active</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card stat-card-success">
                    <div class="stat-icon">
                        <i class="pi pi-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Present Today</div>
                        <div class="stat-value">{{ dashboardData.overview?.present_percentage || 0 }}%</div>
                        <div class="stat-progress">
                            <div 
                                class="stat-progress-bar" 
                                :style="{ width: (dashboardData.overview?.present_percentage || 0) + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card stat-card-warning">
                    <div class="stat-icon">
                        <i class="pi pi-clock"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Late Arrivals</div>
                        <div class="stat-value">{{ dashboardData.overview?.late_count || 0 }}</div>
                        <div class="stat-trend">
                            <span>{{ dashboardData.overview?.late_percentage || 0 }}%</span>
                        </div>
                    </div>
                </div>

                <div class="stat-card stat-card-danger">
                    <div class="stat-icon">
                        <i class="pi pi-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Absent Today</div>
                        <div class="stat-value">{{ dashboardData.overview?.absent_count || 0 }}</div>
                        <div class="stat-trend">
                            <span>{{ dashboardData.overview?.absent_percentage || 0 }}%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Area with Drill-down Views -->
            <div class="main-content">
                <!-- Overview: Grade Level Cards -->
                <div v-if="currentView === 'overview'" class="overview-content">
                    <div class="content-header">
                        <h2 class="content-title">Grade Level Attendance</h2>
                        <p class="content-subtitle">Click on any grade to view sections</p>
                    </div>
                    
                    <div class="grade-cards-grid">
                        <div 
                            v-for="grade in dashboardData.grade_breakdown" 
                            :key="grade.grade_id"
                            class="grade-card"
                            @click="drillDownToGrade(grade)"
                        >
                            <div class="grade-card-header">
                                <h3 class="grade-name">{{ grade.grade_name }}</h3>
                                <div class="grade-stats">
                                    <span class="student-count">{{ grade.total_students }} students</span>
                                </div>
                            </div>
                            
                            <div class="grade-chart">
                                <Chart 
                                    type="doughnut" 
                                    :data="getGradeChartData(grade)" 
                                    :options="doughnutOptions" 
                                    style="height: 120px;"
                                />
                            </div>
                            
                            <div class="grade-metrics">
                                <div class="metric">
                                    <span class="metric-label">Present</span>
                                    <span class="metric-value present">{{ grade.attendance_percentage }}%</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Absent</span>
                                    <span class="metric-value absent">{{ grade.absent_count }}</span>
                                </div>
                                <div class="metric">
                                    <span class="metric-label">Late</span>
                                    <span class="metric-value late">{{ grade.late_count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grade View: Section Cards -->
                <div v-else-if="currentView === 'grade'" class="grade-content">
                    <div class="content-header">
                        <h2 class="content-title">{{ selectedGrade?.name }} Sections</h2>
                        <p class="content-subtitle">Click on any section to view students</p>
                    </div>
                    
                    <div class="section-cards-grid">
                        <div 
                            v-for="section in dashboardData.sections" 
                            :key="section.section_id"
                            class="section-card"
                            @click="drillDownToSection(section)"
                        >
                            <div class="section-header">
                                <h3 class="section-name">{{ section.section_name }}</h3>
                                <div class="section-teacher">
                                    <i class="pi pi-user"></i>
                                    <span>{{ section.homeroom_teacher || 'No teacher assigned' }}</span>
                                </div>
                            </div>
                            
                            <div class="section-stats">
                                <div class="stat-item">
                                    <span class="stat-number">{{ section.total_students }}</span>
                                    <span class="stat-label">Students</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-number present">{{ section.attendance_percentage }}%</span>
                                    <span class="stat-label">Attendance</span>
                                </div>
                            </div>
                            
                            <div class="section-progress">
                                <div class="progress-bar">
                                    <div 
                                        class="progress-fill" 
                                        :style="{ width: section.attendance_percentage + '%' }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section View: Student Table -->
                <div v-else-if="currentView === 'section'" class="section-content">
                    <div class="content-header">
                        <h2 class="content-title">{{ selectedSection?.section_name }} Students</h2>
                        <p class="content-subtitle">Individual student attendance details</p>
                    </div>
                    
                    <div class="students-table-container">
                        <DataTable 
                            :value="dashboardData.students" 
                            class="modern-table"
                            :paginator="true"
                            :rows="10"
                            responsiveLayout="scroll"
                        >
                            <Column field="full_name" header="Student Name" sortable>
                                <template #body="{ data }">
                                    <div class="student-info">
                                        <span class="student-name">{{ data.full_name }}</span>
                                        <span class="student-id">ID: {{ data.student_id }}</span>
                                    </div>
                                </template>
                            </Column>
                            
                            <Column field="today_status" header="Today's Status" sortable>
                                <template #body="{ data }">
                                    <Tag 
                                        :value="data.today_status || 'No record'" 
                                        :severity="getStatusSeverity(data.today_status)"
                                        class="status-tag"
                                    />
                                </template>
                            </Column>
                            
                            <Column field="latest_time_in" header="Time In">
                                <template #body="{ data }">
                                    <span class="time-display">
                                        {{ data.latest_time_in ? formatTime(data.latest_time_in) : '--' }}
                                    </span>
                                </template>
                            </Column>
                            
                            <Column field="latest_time_out" header="Time Out">
                                <template #body="{ data }">
                                    <span class="time-display">
                                        {{ data.latest_time_out ? formatTime(data.latest_time_out) : '--' }}
                                    </span>
                                </template>
                            </Column>
                            
                            <Column field="attendance_percentage" header="Attendance %" sortable>
                                <template #body="{ data }">
                                    <div class="attendance-cell">
                                        <span class="percentage">{{ data.attendance_percentage }}%</span>
                                        <div class="mini-progress">
                                            <div 
                                                class="mini-progress-fill" 
                                                :style="{ width: data.attendance_percentage + '%' }"
                                            ></div>
                                        </div>
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </div>
            </div>

            <!-- Attendance Trend Chart (Always visible) -->
            <div class="trend-chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">Attendance Trend (Last 7 Days)</h3>
                </div>
                <div class="chart-content">
                    <Chart 
                        type="line" 
                        :data="trendChartData" 
                        :options="lineChartOptions" 
                        style="height: 200px;"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { AttendanceAnalyticsService } from '@/services/AttendanceAnalyticsService';
import Chart from 'primevue/chart';
import ProgressSpinner from 'primevue/progressspinner';
import Dropdown from 'primevue/dropdown';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import { onMounted, onUnmounted, ref, computed } from 'vue';

// Core state
const loading = ref(true);
const error = ref(null);
const currentDateTime = ref('');
let timeInterval = null;
let refreshInterval = null;

// Navigation state
const currentView = ref('overview'); // 'overview', 'grade', 'section'
const selectedGrade = ref(null);
const selectedSection = ref(null);

// Data state
const dashboardData = ref({
    overview: null,
    grade_breakdown: [],
    sections: [],
    students: [],
    attendance_trend: []
});

// Date range filter
const selectedDateRange = ref('lastWeek');
const dateRangeOptions = [
    { label: 'Today', value: 'today' },
    { label: 'Yesterday', value: 'yesterday' },
    { label: 'Last 7 Days', value: 'lastWeek' },
    { label: 'Last 30 Days', value: 'lastMonth' }
];

// Chart data
const trendChartData = ref({
    labels: [],
    datasets: []
});

// Chart options
const doughnutOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        }
    }
});

const lineChartOptions = ref({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            max: 100,
            ticks: {
                callback: function(value) {
                    return value + '%';
                }
            }
        }
    }
});

// Load dashboard data based on current view
const loadDashboardData = async () => {
    try {
        loading.value = true;
        error.value = null;

        const dateRange = AttendanceAnalyticsService.getDateRangePresets()[selectedDateRange.value];
        
        if (currentView.value === 'overview') {
            // Load overview data
            const response = await AttendanceAnalyticsService.getOverview(dateRange.from, dateRange.to);
            if (response.success) {
                dashboardData.value.overview = response.data.overview;
                dashboardData.value.grade_breakdown = response.data.grade_breakdown || [];
                dashboardData.value.attendance_trend = response.data.attendance_trend || [];
                
                // Update trend chart
                trendChartData.value = AttendanceAnalyticsService.transformTrendToChartData(response.data.attendance_trend);
            }
        } else if (currentView.value === 'grade' && selectedGrade.value) {
            // Load grade details
            const response = await AttendanceAnalyticsService.getGradeDetails(selectedGrade.value.grade_id, dateRange.from, dateRange.to);
            if (response.success) {
                dashboardData.value.sections = response.data.sections || [];
            }
        } else if (currentView.value === 'section' && selectedSection.value) {
            // Load section details
            const response = await AttendanceAnalyticsService.getSectionDetails(selectedSection.value.section_id, dateRange.from, dateRange.to);
            if (response.success) {
                dashboardData.value.students = response.data.students || [];
            }
        }

        loading.value = false;
    } catch (err) {
        console.error('Error loading dashboard data:', err);
        error.value = err.message || 'Failed to load dashboard data';
        loading.value = false;
    }
};

// Navigation functions
const drillDownToGrade = async (grade) => {
    selectedGrade.value = grade;
    currentView.value = 'grade';
    await loadDashboardData();
};

const drillDownToSection = async (section) => {
    selectedSection.value = section;
    currentView.value = 'section';
    await loadDashboardData();
};

const navigateBack = async () => {
    if (currentView.value === 'section') {
        currentView.value = 'grade';
        selectedSection.value = null;
    } else if (currentView.value === 'grade') {
        currentView.value = 'overview';
        selectedGrade.value = null;
    }
    await loadDashboardData();
};

// Chart data helpers
const getGradeChartData = (grade) => {
    return {
        labels: ['Present', 'Absent', 'Late'],
        datasets: [{
            data: [
                grade.present_count || 0,
                grade.absent_count || 0,
                grade.late_count || 0
            ],
            backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
            borderWidth: 0
        }]
    };
};

// Utility functions
const getStatusSeverity = (status) => {
    switch (status) {
        case 'present': return 'success';
        case 'absent': return 'danger';
        case 'late': return 'warning';
        default: return 'info';
    }
};

const formatTime = (timeString) => {
    if (!timeString) return '--';
    try {
        const time = new Date(`2000-01-01T${timeString}`);
        return time.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    } catch {
        return timeString;
    }
};

// Event handlers
const onDateRangeChange = async () => {
    await loadDashboardData();
};

const refreshData = async () => {
    await loadDashboardData();
};

// Function to update current date and time
const updateDateTime = () => {
    const now = new Date();
    currentDateTime.value =
        now.toLocaleDateString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }) +
        ' - ' +
        now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });
};

// Lifecycle hooks
onMounted(async () => {
    console.log('Modern Admin Dashboard mounted');

    // Initialize date/time and start real-time updates
    updateDateTime();
    timeInterval = setInterval(updateDateTime, 1000);

    // Load initial dashboard data
    await loadDashboardData();

    // Setup real-time data refresh every 30 seconds
    refreshInterval = setInterval(async () => {
        console.log('Auto-refreshing dashboard data...');
        await loadDashboardData();
    }, 30000);
});

onUnmounted(() => {
    if (timeInterval) {
        clearInterval(timeInterval);
    }
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>

<style scoped>
/* Modern Dashboard Styling */
.modern-dashboard {
    min-height: 100vh;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    padding: 1rem;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.loading-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 2rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

/* Error Card */
.error-card {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border: 1px solid #fecaca;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.retry-btn {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.retry-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
}

/* Dashboard Header */
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    color: white;
    box-shadow: 0 20px 40px rgba(102, 126, 234, 0.2);
}

.header-content {
    flex: 1;
}

.breadcrumb-nav {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.back-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    padding: 0.5rem;
    border-radius: 8px;
    margin-right: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.back-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateX(-2px);
}

.breadcrumb-text {
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    opacity: 0.9;
}

.breadcrumb-item {
    font-weight: 500;
}

.breadcrumb-separator {
    margin: 0 0.5rem;
    opacity: 0.7;
}

.dashboard-title {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.dashboard-subtitle {
    opacity: 0.9;
    font-size: 1.1rem;
}

.header-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.date-dropdown {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
}

.realtime-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.9rem;
}

.pulse-dot {
    width: 8px;
    height: 8px;
    background: #10b981;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    cursor: pointer;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    font-size: 1.5rem;
}

.stat-card-primary .stat-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.stat-card-success .stat-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.stat-card-warning .stat-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.stat-card-danger .stat-icon {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.stat-content {
    flex: 1;
}

.stat-label {
    font-size: 0.9rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.stat-trend {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: #10b981;
}

.stat-progress {
    height: 4px;
    background: #e5e7eb;
    border-radius: 2px;
    overflow: hidden;
}

.stat-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #10b981 0%, #059669 100%);
    transition: width 0.8s ease;
}

/* Main Content */
.main-content {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.content-header {
    margin-bottom: 2rem;
    text-align: center;
}

.content-title {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.content-subtitle {
    color: #6b7280;
    font-size: 1.1rem;
}

/* Grade Cards Grid */
.grade-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.5rem;
}

.grade-card {
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.grade-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(102, 126, 234, 0.15);
    border-color: #667eea;
}

.grade-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.grade-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
}

.student-count {
    font-size: 0.9rem;
    color: #6b7280;
}

.grade-chart {
    margin-bottom: 1rem;
}

.grade-metrics {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.metric {
    text-align: center;
}

.metric-label {
    font-size: 0.8rem;
    color: #6b7280;
    display: block;
    margin-bottom: 0.25rem;
}

.metric-value {
    font-weight: 600;
    font-size: 1.1rem;
}

.metric-value.present { color: #10b981; }
.metric-value.absent { color: #ef4444; }
.metric-value.late { color: #f59e0b; }

/* Section Cards Grid */
.section-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.section-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.section-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
    border-color: #667eea;
}

.section-header {
    margin-bottom: 1rem;
}

.section-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.section-teacher {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #6b7280;
    font-size: 0.9rem;
}

.section-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    display: block;
}

.stat-number.present {
    color: #10b981;
}

.stat-label {
    font-size: 0.8rem;
    color: #6b7280;
}

.section-progress {
    margin-top: 1rem;
}

.progress-bar {
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981 0%, #059669 100%);
    transition: width 0.8s ease;
}

/* Students Table */
.students-table-container {
    margin-top: 1rem;
}

:deep(.modern-table) {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

:deep(.modern-table .p-datatable-thead > tr > th) {
    background: #f8fafc;
    border: none;
    font-weight: 600;
    color: #374151;
    padding: 1rem;
}

:deep(.modern-table .p-datatable-tbody > tr) {
    border: none;
    transition: background-color 0.2s;
}

:deep(.modern-table .p-datatable-tbody > tr:hover) {
    background: #f8fafc;
}

:deep(.modern-table .p-datatable-tbody > tr > td) {
    border: none;
    padding: 1rem;
    border-bottom: 1px solid #e5e7eb;
}

.student-info {
    display: flex;
    flex-direction: column;
}

.student-name {
    font-weight: 600;
    color: #1f2937;
}

.student-id {
    font-size: 0.8rem;
    color: #6b7280;
}

.time-display {
    font-family: monospace;
    font-weight: 500;
}

.attendance-cell {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.percentage {
    font-weight: 600;
}

.mini-progress {
    height: 4px;
    background: #e5e7eb;
    border-radius: 2px;
    overflow: hidden;
}

.mini-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981 0%, #059669 100%);
    transition: width 0.8s ease;
}

/* Trend Chart */
.trend-chart-container {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.chart-header {
    margin-bottom: 1rem;
}

.chart-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1f2937;
}

/* Status Tags */
:deep(.status-tag) {
    font-weight: 500;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-header {
        padding: 1.5rem;
    }
    
    .dashboard-title {
        font-size: 2rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .grade-cards-grid,
    .section-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .header-controls {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
