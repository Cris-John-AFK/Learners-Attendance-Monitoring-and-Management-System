<script setup>
import { useLayout } from '@/layout/composables/layout';
import { computed, onMounted, ref, watch } from 'vue';

const { getPrimary, getSurface, isDarkTheme } = useLayout();
const chartData = ref(null);
const chartOptions = ref(null);
const selectedView = ref('monthly'); // 'daily', 'weekly', 'monthly'
const selectedDate = ref(new Date().toISOString().slice(0, 10));
const selectedMonth = ref('2025-03'); // Default to March 2025

// Add insight metrics
const insightMetrics = computed(() => {
    if (selectedView.value === 'monthly') {
        return [
            { label: 'Highest Absence Month', value: 'February', icon: 'pi pi-calendar', trend: 'up', trendValue: '4%' },
            { label: 'Average Attendance Rate', value: '92%', icon: 'pi pi-percentage', trend: 'up', trendValue: '2%' },
            { label: 'Most Common Absence Day', value: 'Monday', icon: 'pi pi-clock', trend: 'neutral', trendValue: '-' }
        ];
    } else if (selectedView.value === 'weekly') {
        return [
            { label: 'Highest Absence Day', value: 'Friday', icon: 'pi pi-calendar', trend: 'up', trendValue: '5%' },
            { label: 'Students Improving', value: '7', icon: 'pi pi-arrow-up', trend: 'up', trendValue: '2' },
            { label: 'Weekly Trend', value: 'Improving', icon: 'pi pi-chart-line', trend: 'up', trendValue: '3%' }
        ];
    } else {
        return [
            { label: 'Absences Today', value: selectedDate.value === '2025-03-22' ? '0' : '3', icon: 'pi pi-users', trend: 'down', trendValue: '2' },
            { label: 'On-time Rate', value: '95%', icon: 'pi pi-clock', trend: 'up', trendValue: '3%' },
            { label: 'Daily Trend', value: 'Improving', icon: 'pi pi-chart-line', trend: 'up', trendValue: '2%' }
        ];
    }
});

const staticData = {
    daily: {
        labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        data: {
            '2025-03-18': { present: 10, absent: 1, tardy: 1 },
            '2025-03-19': { present: 9, absent: 2, tardy: 1 },
            '2025-03-20': { present: 11, absent: 0, tardy: 1 },
            '2025-03-21': { present: 8, absent: 2, tardy: 2 },
            '2025-03-22': { present: 12, absent: 0, tardy: 0 }
        }
    },
    weekly: {
        '2025-03': {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            data: [
                { present: 50, absent: 5, tardy: 5 },
                { present: 48, absent: 6, tardy: 6 },
                { present: 52, absent: 4, tardy: 4 },
                { present: 49, absent: 7, tardy: 6 }
            ]
        },
        '2025-04': {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            data: [
                { present: 45, absent: 10, tardy: 3 },
                { present: 50, absent: 5, tardy: 5 },
                { present: 52, absent: 4, tardy: 4 },
                { present: 51, absent: 6, tardy: 5 }
            ]
        }
    },
    monthly: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        data: [
            { present: 200, absent: 20, tardy: 10 },
            { present: 190, absent: 25, tardy: 15 },
            { present: 195, absent: 18, tardy: 17 },
            { present: 205, absent: 22, tardy: 13 },
            { present: 210, absent: 15, tardy: 5 },
            { present: 198, absent: 20, tardy: 12 },
            { present: 202, absent: 18, tardy: 10 },
            { present: 207, absent: 14, tardy: 9 },
            { present: 195, absent: 22, tardy: 11 },
            { present: 200, absent: 19, tardy: 9 },
            { present: 208, absent: 10, tardy: 8 },
            { present: 202, absent: 12, tardy: 6 }
        ]
    }
};

function setChartData() {
    const documentStyle = getComputedStyle(document.documentElement);
    let data;

    if (selectedView.value === 'daily') {
        data = staticData.daily.data[selectedDate.value] || { present: 10, absent: 1, tardy: 1 };
        return {
            labels: [selectedDate.value],
            datasets: [
                {
                    type: 'bar',
                    label: 'Absent',
                    backgroundColor: documentStyle.getPropertyValue('--p-red-400'),
                    data: [data.absent],
                    barThickness: 32
                },
                {
                    type: 'bar',
                    label: 'Tardy',
                    backgroundColor: documentStyle.getPropertyValue('--p-orange-400'),
                    data: [data.tardy],
                    barThickness: 32,
                    borderRadius: { topLeft: 8, topRight: 8 }
                },
                {
                    type: 'bar',
                    label: 'Present',
                    backgroundColor: documentStyle.getPropertyValue('--p-green-400'),
                    data: [data.present],
                    borderRadius: { topLeft: 8, topRight: 8 },
                    borderSkipped: true,
                    barThickness: 20
                }
            ]
        };
    } else if (selectedView.value === 'weekly') {
        data = staticData.weekly[selectedMonth.value] || staticData.weekly['2025-03'];
        return {
            labels: data.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Absent',
                    backgroundColor: documentStyle.getPropertyValue('--p-red-400'),
                    data: data.data.map((d) => d.absent),
                    barThickness: 32
                },
                {
                    type: 'bar',
                    label: 'Tardy',
                    backgroundColor: documentStyle.getPropertyValue('--p-orange-400'),
                    data: data.data.map((d) => d.tardy),
                    barThickness: 32,
                    borderRadius: { topLeft: 8, topRight: 8 }
                },
                {
                    type: 'bar',
                    label: 'Present',
                    backgroundColor: documentStyle.getPropertyValue('--p-green-400'),
                    data: data.data.map((d) => d.present),
                    borderRadius: { topLeft: 8, topRight: 8 },
                    borderSkipped: true,
                    barThickness: 20
                }
            ]
        };
    } else {
        data = staticData[selectedView.value];
        return {
            labels: data.labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Absent',
                    backgroundColor: documentStyle.getPropertyValue('--p-red-400'),
                    data: data.data.map((d) => d.absent),
                    barThickness: 32
                },
                {
                    type: 'bar',
                    label: 'Tardy',
                    backgroundColor: documentStyle.getPropertyValue('--p-orange-400'),
                    data: data.data.map((d) => d.tardy),
                    barThickness: 32,
                    borderRadius: { topLeft: 8, topRight: 8 }
                },
                {
                    type: 'bar',
                    label: 'Present',
                    backgroundColor: documentStyle.getPropertyValue('--p-green-400'),
                    data: data.data.map((d) => d.present),
                    borderRadius: { topLeft: 8, topRight: 8 },
                    borderSkipped: true,
                    barThickness: 20
                }
            ]
        };
    }
}

watch([selectedView, selectedDate, selectedMonth], () => {
    chartData.value = setChartData();
});

onMounted(() => {
    chartData.value = setChartData();
    chartOptions.value = {
        maintainAspectRatio: false,
        aspectRatio: 0.8,
        scales: { x: { stacked: true }, y: { stacked: true } }
    };
});
</script>

<template>
    <div class="card">
        <div class="flex justify-between items-center mb-4">
            <div class="font-semibold text-xl">Attendance Analytics Carrot Chart</div>
            <div class="flex gap-2">
                <select v-model="selectedView" class="border rounded px-2 py-1">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
                <input v-if="selectedView === 'daily'" type="date" v-model="selectedDate" class="border rounded px-2 py-1" />
                <input v-if="selectedView === 'weekly'" type="month" v-model="selectedMonth" class="border rounded px-2 py-1" />
            </div>
        </div>

        <!-- Insights Row -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div v-for="(metric, index) in insightMetrics" :key="index" class="bg-gray-50 dark:bg-gray-800 rounded-lg p-3 flex items-center">
                <div class="mr-3 w-10 h-10 rounded-full flex items-center justify-center bg-blue-100 dark:bg-blue-900">
                    <i :class="metric.icon" class="text-blue-500"></i>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ metric.label }}</div>
                    <div class="font-semibold">{{ metric.value }}</div>
                    <div class="flex items-center mt-1 text-xs">
                        <span
                            :class="{
                                'text-green-500': metric.trend === 'up',
                                'text-red-500': metric.trend === 'down',
                                'text-gray-500': metric.trend === 'neutral'
                            }"
                        >
                            <i :class="metric.trend === 'up' ? 'pi pi-arrow-up' : metric.trend === 'down' ? 'pi pi-arrow-down' : ''" class="mr-1"></i>
                            {{ metric.trendValue }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <Chart type="bar" :data="chartData" :options="chartOptions" class="h-80" />
    </div>
</template>
