<script setup>
import { defineProps, ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    chartData: Object
});

const chartOptions = ref({
    series: [],
    chart: {
        type: 'bar',
        height: 350,
        stacked: true
    },
    plotOptions: {
        bar: {
            horizontal: true,
            barHeight: '60%'
        }
    },
    xaxis: {
        categories: []
    },
    tooltip: {
        shared: false,
        y: {
            formatter: (val) => `${val} students`
        }
    },
    legend: {
        position: 'right'
    }
});

watch(() => props.chartData, (newData) => {
    if (newData) {
        chartOptions.value.series = newData.datasets.map(dataset => ({
            name: dataset.label,  // Ensures correct labeling in the legend
            data: dataset.data,
            color: dataset.backgroundColor // Ensures colors match the dataset
        }));
        chartOptions.value.xaxis.categories = newData.labels;
    }
}, { immediate: true });
</script>

<template>
  <VueApexCharts type="bar" height="350" width="900" :options="chartOptions" :series="chartOptions.series" />
</template>
