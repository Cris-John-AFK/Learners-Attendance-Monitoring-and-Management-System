<script setup>
import { ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const chartOptions = ref({
  series: [
    {
      name: 'Present',
      data: [45, 48, 42, 40, 43, 38, 35],
      color: '#28a745' // Green for Present
    },
    {
      name: 'Late',
      data: [7, 5, 6, 5, 6, 4, 7],
      color: '#fd7e14' // Orange for Late
    },
    {
      name: 'Absent',
      data: [5, 4, 8, 10, 7, 12, 15],
      color: '#dc3545' // Red for Absent
    },
  ],
  chart: {
    type: 'bar',
    height: 350,
    stacked: true,
    dropShadow: {
      enabled: true,
      blur: 1,
      opacity: 0.5
    }
  },
  plotOptions: {
    bar: {
      horizontal: true,
      barHeight: '60%',
    }
  },
  dataLabels: {
    enabled: false,
  },
  stroke: {
    width: 2,
  },
  xaxis: {
    categories: ['Kinder', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'],
  },
  yaxis: {
  },
  tooltip: {
    shared: false,
    y: {
      formatter: (val) => `${val} students`
    }
  },
  fill: {
    type: 'pattern',
    opacity: 1,
    pattern: {
      style: ['circles', 'slantedLines', 'verticalLines', 'horizontalLines'],
    }
  },
  states: {
    hover: {
      filter: 'none'
    }
  },
  legend: {
    position: 'right',
    offsetY: 40
  }
});
</script>

<template>
  <div>
    <VueApexCharts type="bar" height="350" width="900" :options="chartOptions" :series="chartOptions.series" />
  </div>
</template>
