<script setup>
import { useLayout } from '@/layout/composables/layout';
import { onMounted, ref, watch } from 'vue';

const { getPrimary, getSurface, isDarkTheme } = useLayout();

const chartData = ref(null);
const chartOptions = ref(null);

function setChartData() {
    const documentStyle = getComputedStyle(document.documentElement);

    return {
        labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        datasets: [
            {
                type: 'bar',
                label: 'Absent',
                backgroundColor: documentStyle.getPropertyValue('--p-red-400'),
                data: [15, 5, 11, 10, 15, 10, 15, 10, 15, 10, 15, 10],
                barThickness: 32
            },
            {
                type: 'bar',
                label: 'Tardy',
                backgroundColor: documentStyle.getPropertyValue('--p-orange-400'),
                data: [15, 5, 15, 10, 14, 10, 15, 10, 15, 10, 1, 10],
                barThickness: 32,
                borderRadius: {
                    topLeft: 8,
                    topRight: 8
                }
            },
            {
                type: 'bar',
                label: 'Present',
                backgroundColor: documentStyle.getPropertyValue('--p-green-400'),
                data: [15, 12, 15, 10, 15, 10, 15, 10, 15, 10, 15, 10],
                borderRadius: {
                    topLeft: 8,
                    topRight: 8
                },
                borderSkipped: true,
                barThickness: 20
            }
        ]
    };
}

function setChartOptions() {
    const documentStyle = getComputedStyle(document.documentElement);
    const borderColor = documentStyle.getPropertyValue('--surface-border');
    const textMutedColor = documentStyle.getPropertyValue('--text-color-secondary');

    return {
        maintainAspectRatio: false,
        aspectRatio: 0.8,
        scales: {
            x: {
                stacked: true,
                ticks: {
                    color: textMutedColor
                },
                grid: {
                    color: 'transparent',
                    borderColor: 'transparent'
                }
            },
            y: {
                stacked: true,
                ticks: {
                    color: textMutedColor
                },
                grid: {
                    color: borderColor,
                    borderColor: 'transparent',
                    drawTicks: false
                }
            }
        }
    };
}

watch([getPrimary, getSurface, isDarkTheme], () => {
    chartData.value = setChartData();
    chartOptions.value = setChartOptions();
});

onMounted(() => {
    chartData.value = setChartData();
    chartOptions.value = setChartOptions();
});
</script>

<template>
    <div class="card">
        <div class="font-semibold text-xl mb-4">Attendance Analytical Chart</div>
        <Chart type="bar" :data="chartData" :options="chartOptions" class="h-80" />
    </div>
</template>
