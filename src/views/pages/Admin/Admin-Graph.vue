<template>
    <div class="grid" style="margin: 0 1rem">
        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center items-center h-64 bg-white rounded-xl shadow-sm">
            <ProgressSpinner strokeWidth="4" style="width: 50px; height: 50px" class="text-blue-500" />
            <p class="ml-3 text-gray-500 font-normal">Loading dashboard data...</p>
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
                        <div class="text-right">
                            <div class="text-blue-100 text-sm">Attendance Insights</div>
                        </div>
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
                                <!-- View Type Toggle -->
                                <div class="flex items-center gap-2">
                                    <label class="text-sm font-medium text-gray-600">View:</label>
                                    <SelectButton v-model="viewType" :options="viewTypeOptions" optionLabel="label" optionValue="value" class="text-xs" @change="onViewTypeChange" />
                                </div>
                                <!-- Time Period Toggle -->
                                <div class="flex items-center gap-2">
                                    <label class="text-sm font-medium text-gray-600">Period:</label>
                                    <SelectButton v-model="chartView" :options="chartViewOptions" optionLabel="label" optionValue="value" class="text-xs" @change="onChartViewChange" />
                                </div>
                            </div>
                        </div>

                        <div v-if="!defaultGradeChartData || defaultGradeChartData.labels.length === 0" class="flex flex-col items-center justify-center py-12">
                            <ProgressSpinner strokeWidth="4" style="width: 50px; height: 50px" class="text-blue-500" />
                            <p class="mt-3 text-gray-500 font-normal">Loading chart data...</p>
                        </div>

                        <div v-else class="chart-container">
                            <Chart type="bar" :data="defaultGradeChartData" :options="chartOptions" style="height: 300px" />
                        </div>
                    </div>
                </div>

                <!-- Attendance Insights Card -->
                <div class="col-span-12 lg:col-span-4">
                    <div class="bg-white rounded-xl shadow-sm p-5">
                        <h3 class="text-lg font-semibold mb-4">Attendance Insights</h3>
                        <div class="text-center">
                            <div class="text-sm text-gray-500 mb-2">Mathematics (Grade 3)</div>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Present</span>
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                        <span class="font-semibold">{{ Math.round(averageAttendance) }}%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Absent</span>
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                                        <span class="font-semibold">{{ Math.round(100 - averageAttendance - 5) }}%</span>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Late</span>
                                    <div class="flex items-center">
                                        <div class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                                        <span class="font-semibold">5%</span>
                                    </div>
                                </div>
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

// Statistics data
const totalStudents = ref(10);
const averageAttendance = ref(85);
const warningCount = ref(3);
const criticalCount = ref(2);

// Chart view options
const chartViewOptions = [
    { label: 'Daily', value: 'day' },
    { label: 'Weekly', value: 'week' },
    { label: 'Monthly', value: 'month' }
];
const chartView = ref('week');

// View type options
const viewTypeOptions = [
    { label: 'Subject-Specific', value: 'subject' },
    { label: 'All Students', value: 'all_students' }
];
const viewType = ref('subject');

// Default chart data for grade-level attendance
const defaultGradeChartData = ref({
    labels: [],
    datasets: [
        {
            label: 'Present',
            backgroundColor: 'rgba(76, 175, 80, 0.8)',
            borderColor: '#4CAF50',
            borderWidth: 1,
            borderRadius: 4,
            data: [],
            hoverBackgroundColor: 'rgba(76, 175, 80, 1)'
        },
        {
            label: 'Absent',
            backgroundColor: 'rgba(244, 67, 54, 0.8)',
            borderColor: '#F44336',
            borderWidth: 1,
            borderRadius: 4,
            data: [],
            hoverBackgroundColor: 'rgba(244, 67, 54, 1)'
        },
        {
            label: 'Late',
            backgroundColor: 'rgba(255, 193, 7, 0.8)',
            borderColor: '#FFC107',
            borderWidth: 1,
            borderRadius: 4,
            data: [],
            hoverBackgroundColor: 'rgba(255, 193, 7, 1)'
        }
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

        // Setup chart options
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

// Setup chart options
const setupChartOptions = () => {
    chartOptions.value = {
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
                boxPadding: 4
            }
        },
        responsive: true,
        maintainAspectRatio: false,
        barPercentage: 0.8,
        categoryPercentage: 0.9,
        animation: {
            duration: 1000,
            easing: 'easeOutQuart'
        },
        scales: {
            x: {
                grid: {
                    display: false,
                    drawBorder: false
                },
                ticks: {
                    font: {
                        family: 'Inter, sans-serif',
                        size: 11
                    },
                    color: '#666'
                }
            },
            y: {
                grid: {
                    color: 'rgba(0, 0, 0, 0.05)',
                    drawBorder: false
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
                    dash: [4, 4]
                }
            }
        }
    };
};

// Handle view type change
const onViewTypeChange = () => {
    console.log('View type changed to:', viewType.value);
    loadGradeData();
};

// Handle chart view change
const onChartViewChange = () => {
    console.log('Chart view changed to:', chartView.value);
    loadGradeData();
};

// Function to update current date and time
const updateDateTime = () => {
    const now = new Date();
    currentDateTime.value = now.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    }) + ' - ' + now.toLocaleTimeString('en-US', {
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
    
    await loadGradeData();
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
