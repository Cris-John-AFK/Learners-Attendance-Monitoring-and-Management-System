<template>
    <div class="grid" style="margin: 0 1rem">
        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center h-64 bg-white rounded-xl shadow-sm">
            <ProgressSpinner strokeWidth="4" style="width: 50px; height: 50px" class="text-blue-500" />
            <p class="ml-3 text-gray-500 font-normal">Loading dashboard data...</p>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-xl p-6 mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="pi pi-exclamation-triangle text-red-500 text-xl mr-3"></i>
                    <div>
                        <h3 class="text-red-800 font-semibold">Error Loading Data</h3>
                        <p class="text-red-600 text-sm mt-1">{{ error }}</p>
                    </div>
                </div>
                <button @click="retryLoadData" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors"><i class="pi pi-refresh mr-2"></i>Retry</button>
            </div>
        </div>

        <div v-else>
            <!-- Modern Header with Admin Welcome -->
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-xl shadow-md p-6 mb-6 text-white">
                <div class="grid grid-cols-12 gap-4 items-center">
                    <div class="col-span-12 sm:col-span-7">
                        <h1
                            class="text-2xl font-bold mb-1"
                            style="
                                color: #ffffff;
                                text-shadow:
                                    0 0 1px #fff,
                                    0 0 2px #fff;
                            "
                        >
                            Welcome, Admin
                        </h1>
                        <p class="text-blue-100 font-normal">
                            {{ currentDateTime }}
                        </p>
                    </div>

                    <div class="col-span-12 sm:col-span-5 flex flex-col sm:flex-row gap-2 justify-end">
                        <!-- Attendance Insights placeholder -->
                        <div class="text-right"></div>
                    </div>
                </div>
            </div>

            <!-- Attendance Stats Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow flex items-center">
                    <div class="mr-4 bg-blue-100 p-3 rounded-lg">
                        <i class="pi pi-users text-blue-600 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 mb-1 font-medium">Total Students</div>
                        <div class="text-2xl font-bold">{{ totalStudents }}</div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow flex items-center">
                    <div class="mr-4 bg-green-100 p-3 rounded-lg">
                        <i class="pi pi-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 mb-1 font-medium">Average Attendance</div>
                        <div class="flex items-end">
                            <span class="text-2xl font-bold mr-1">{{ averageAttendance }}%</span>
                            <ProgressBar
                                :value="averageAttendance"
                                class="h-1.5 w-16 mb-1.5"
                                :class="{
                                    'attendance-good': averageAttendance >= 85,
                                    'attendance-warning': averageAttendance < 85 && averageAttendance >= 70,
                                    'attendance-poor': averageAttendance < 70
                                }"
                            />
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow flex items-center">
                    <div class="mr-4 bg-yellow-100 p-3 rounded-lg">
                        <i class="pi pi-exclamation-triangle text-yellow-600 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 mb-1 font-medium">Warning (3+ absences)</div>
                        <div class="text-2xl font-bold">{{ warningCount }}</div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-5 hover:shadow-md transition-shadow flex items-center">
                    <div class="mr-4 bg-red-100 p-3 rounded-lg">
                        <i class="pi pi-exclamation-circle text-red-600 text-xl"></i>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 mb-1 font-medium">Critical (5+ absences)</div>
                        <div class="text-2xl font-bold">{{ criticalCount }}</div>
                    </div>
                </div>
            </div>

            <!-- Attendance Chart & Insights -->
            <div class="grid grid-cols-12 gap-6 mb-6">
                <!-- Attendance Trends Chart -->
                <div class="col-span-12 lg:col-span-8">
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
                            <h2 class="text-lg font-semibold">Attendance Trends</h2>
                            <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                                <!-- Time Period Toggle -->
                                <div class="flex items-center gap-2">
                                    <label class="text-sm font-medium text-gray-600">Period:</label>
                                    <SelectButton v-model="chartView" :options="chartViewOptions" optionLabel="label" optionValue="value" class="text-xs" @change="onChartViewChange" />
                                </div>
                            </div>
                        </div>

                        <div v-if="loading" class="flex flex-col items-center justify-center py-12">
                            <ProgressSpinner strokeWidth="4" style="width: 50px; height: 50px" class="text-blue-500" />
                            <p class="mt-3 text-gray-500 font-normal">Loading chart data...</p>
                        </div>

                        <div v-else-if="error" class="flex flex-col items-center justify-center py-12">
                            <i class="pi pi-chart-bar text-gray-400 text-4xl mb-3"></i>
                            <p class="text-gray-500 font-medium mb-2">Unable to load chart data</p>
                            <button @click="retryLoadData" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1.5 rounded text-sm font-medium transition-colors"><i class="pi pi-refresh mr-1"></i>Retry</button>
                        </div>

                        <div v-else-if="!defaultGradeChartData || defaultGradeChartData.labels.length === 0" class="flex flex-col items-center justify-center py-12">
                            <i class="pi pi-chart-bar text-gray-400 text-4xl mb-3"></i>
                            <p class="text-gray-500 font-medium">No attendance data available</p>
                            <p class="text-gray-400 text-sm">Check back later or contact your administrator</p>
                        </div>

                        <div v-else class="chart-container">
                            <Chart :type="chartType" :data="defaultGradeChartData" :options="chartOptions" style="height: 300px" />
                        </div>
                    </div>
                </div>

                <!-- Attendance Insights Card -->
                <div class="col-span-12 lg:col-span-4">
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <h3 class="text-lg font-semibold mb-4">Attendance Insights</h3>
                        <div class="text-center">
                            <div class="text-sm text-gray-500 mb-2">{{ insights.bestPerformingGrade || 'Overall Statistics' }}</div>
                            <div v-if="!loading && !error" class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Present</span>
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                        <span class="font-semibold">{{ averageAttendance }}%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Absent</span>
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                                        <span class="font-semibold">{{ Math.max(0, Math.round(100 - averageAttendance - 5)) }}%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Late</span>
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                                        <span class="font-semibold">5%</span>
                                    </div>
                                </div>
                                <div v-if="insights.trendAnalysis" class="mt-4 pt-3 border-t border-gray-200">
                                    <p class="text-xs text-gray-500">{{ insights.trendAnalysis }}</p>
                                </div>
                            </div>
                            <div v-else-if="loading" class="flex justify-center py-8">
                                <ProgressSpinner strokeWidth="4" style="width: 30px; height: 30px" class="text-blue-500" />
                            </div>
                            <div v-else class="text-gray-400 text-sm py-8">
                                <i class="pi pi-info-circle mb-2 text-lg block"></i>
                                No insights available
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { GradeService } from '@/router/service/Grades';
import { AdminAttendanceService } from '@/services/AdminAttendanceService';
import Chart from 'primevue/chart';
import ProgressBar from 'primevue/progressbar';
import ProgressSpinner from 'primevue/progressspinner';
import SelectButton from 'primevue/selectbutton';
import { onMounted, onUnmounted, ref } from 'vue';

const loading = ref(true);
const currentDateTime = ref('');
let timeInterval = null;
const sectionLoading = ref(false);
const selectedGrade = ref(null);
const gradeOptions = ref([]);
const chartOptions = ref({});
const error = ref(null);

// Statistics data - will be populated from API
const totalStudents = ref(0);
const averageAttendance = ref(0);
const warningCount = ref(0);
const criticalCount = ref(0);

// Summary stats and insights from API
const summaryStats = ref({});
const insights = ref({});
const selectedDateRange = ref('current_year');
const chartType = ref('bar');

// Chart view options - updated to match API date ranges
const chartViewOptions = [
    { label: 'Current Year', value: 'current_year' },
    { label: 'Last 30 Days', value: 'last_30_days' },
    { label: 'Last 7 Days', value: 'last_7_days' }
];
const chartView = ref('current_year');

// View type options
const viewTypeOptions = [
    { label: 'Subject-Specific', value: 'subject' },
    { label: 'All Students', value: 'all_students' }
];
const viewType = ref('subject');

// Chart data for grade-level attendance
const defaultGradeChartData = ref({
    labels: [],
    datasets: [
        {
            label: 'Present',
            backgroundColor: '#10b981',
            borderColor: '#10b981',
            borderWidth: 1,
            data: [],
            stack: 'attendance'
        },
        {
            label: 'Absent',
            backgroundColor: '#ef4444',
            borderColor: '#ef4444',
            borderWidth: 1,
            data: [],
            stack: 'attendance'
        },
        {
            label: 'Late',
            backgroundColor: '#f59e0b',
            borderColor: '#f59e0b',
            borderWidth: 1,
            data: [],
            stack: 'attendance'
        },
        {
            label: 'Excused',
            backgroundColor: '#8b5cf6',
            borderColor: '#8b5cf6',
            borderWidth: 1,
            data: [],
            stack: 'attendance'
        }
    ]
});

// Section-wise attendance data
const gradeSectionsData = ref({});

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
        const response = await AdminAttendanceService.getAttendanceAnalytics(chartView.value, gradeId);

        if (!response.success) {
            throw new Error(response.message || 'Failed to fetch attendance data');
        }

        const analyticsData = response.data;
        console.log('Analytics data received:', analyticsData);

        // Transform data to chart format
        defaultGradeChartData.value = AdminAttendanceService.transformToChartData(analyticsData);

        // Determine chart type based on number of grades
        chartType.value = analyticsData.grades && analyticsData.grades.length > 1 ? 'horizontalBar' : 'bar';

        // Update summary statistics
        const stats = AdminAttendanceService.calculateSummaryStats(analyticsData);
        totalStudents.value = stats.totalStudents;
        averageAttendance.value = Math.round(stats.averageAttendance);
        warningCount.value = stats.warningCount;
        criticalCount.value = stats.criticalCount;

        // Update insights - pass current grade data if viewing single grade
        const currentGradeData = analyticsData.grades && analyticsData.grades.length === 1 ? analyticsData.grades[0] : null;
        insights.value = AdminAttendanceService.getInsights(analyticsData, currentGradeData);

        console.log('Chart data updated:', defaultGradeChartData.value);
        console.log('Summary stats updated:', stats);
        loading.value = false;
    } catch (err) {
        console.error('Error loading attendance data:', err);
        error.value = err.message || 'Failed to load attendance data';
        loading.value = false;
    }
};

// Helper function to get grade ID by name
const getGradeIdByName = (gradeName) => {
    if (!gradeOptions.value || gradeName === 'all') return null;
    const grade = gradeOptions.value.find((g) => g.value === gradeName);
    return grade ? grade.id : null;
};

// Retry loading data
const retryLoadData = async () => {
    console.log('Retrying data load...');
    await loadAttendanceData();
};

// Load grades for dropdown options
const loadGradeOptions = async () => {
    try {
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

        // Load real attendance data from API first
        await loadAttendanceData();

        // Setup chart options after data is loaded
        setupChartOptions();

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
    loadAttendanceData();
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

// Setup chart options
const setupChartOptions = () => {
    const isHorizontal = chartType.value === 'horizontalBar';

    chartOptions.value = {
        indexAxis: isHorizontal ? 'y' : 'x',
        plugins: {
            legend: {
                position: 'top',
                align: 'center',
                labels: {
                    font: {
                        family: 'Inter, sans-serif',
                        size: 12,
                        weight: 500
                    },
                    usePointStyle: true,
                    pointStyle: 'circle',
                    padding: 20
                }
            },
            tooltip: {
                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                titleColor: '#333',
                titleFont: {
                    family: 'Inter, sans-serif',
                    size: 13,
                    weight: 600
                },
                bodyColor: '#555',
                bodyFont: {
                    family: 'Inter, sans-serif',
                    size: 12
                },
                borderColor: '#e1e1e1',
                borderWidth: 1,
                cornerRadius: 8,
                padding: 12,
                boxPadding: 4,
                callbacks: {
                    label: function (context) {
                        const label = context.dataset.label || '';
                        const value = context.parsed[isHorizontal ? 'x' : 'y'];
                        const total = context.chart.data.datasets.reduce((sum, dataset) => {
                            return sum + (dataset.data[context.dataIndex] || 0);
                        }, 0);
                        const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        },
        responsive: true,
        maintainAspectRatio: false,
        barPercentage: 0.7,
        categoryPercentage: 0.8,
        animation: {
            duration: 1000,
            easing: 'easeOutQuart'
        },
        scales: {
            x: {
                stacked: isHorizontal,
                grid: {
                    display: !isHorizontal,
                    drawBorder: false,
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                    font: {
                        family: 'Inter, sans-serif',
                        size: 11
                    },
                    color: '#666',
                    padding: 8
                },
                border: {
                    display: false
                }
            },
            y: {
                stacked: !isHorizontal,
                grid: {
                    display: isHorizontal,
                    drawBorder: false,
                    color: 'rgba(0, 0, 0, 0.05)'
                },
                ticks: {
                    font: {
                        family: 'Inter, sans-serif',
                        size: 11
                    },
                    color: '#666',
                    padding: 8
                },
                border: {
                    display: false
                }
            }
        }
    };
};

// Handle view type change
const onViewTypeChange = () => {
    console.log('View type changed to:', viewType.value);
    loadAttendanceData();
};

// Handle chart view change
const onChartViewChange = () => {
    console.log('Chart view changed to:', chartView.value);
    loadAttendanceData();
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

onMounted(async () => {
    console.log('Admin-Graph component mounted');

    // Initialize date/time and start real-time updates
    updateDateTime();
    timeInterval = setInterval(updateDateTime, 1000); // Update every second

    await loadGradeOptions();
});

onUnmounted(() => {
    if (timeInterval) {
        clearInterval(timeInterval);
    }
});
</script>

<style scoped>
/* Modern chart container styling */
.chart-container {
    width: 100%;
    height: 300px;
    position: relative;
}

/* Progress bar styling for attendance */
:deep(.attendance-good .p-progressbar-value) {
    background: linear-gradient(90deg, #10b981, #059669);
}

:deep(.attendance-warning .p-progressbar-value) {
    background: linear-gradient(90deg, #f59e0b, #d97706);
}

:deep(.attendance-poor .p-progressbar-value) {
    background: linear-gradient(90deg, #ef4444, #dc2626);
}

/* Modern table styling */
:deep(.modern-table) {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

:deep(.modern-table .p-datatable-header) {
    background: #f8fafc;
    border: none;
    padding: 1rem;
}

:deep(.modern-table .p-datatable-thead > tr > th) {
    background: #f1f5f9;
    border: none;
    font-weight: 600;
    color: #475569;
    padding: 0.75rem 1rem;
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
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e2e8f0;
}

/* Student name hover effect */
.student-name {
    transition: color 0.2s;
    font-weight: 500;
}

/* Loading and empty state styling */
.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    gap: 1rem;
    min-height: 200px;
}

.no-data-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: #6c757d;
    gap: 0.5rem;
    min-height: 200px;
}

.no-data-message i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}
</style>
