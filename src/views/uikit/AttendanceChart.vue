<script setup>
import { ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const chartOptions = ref({
  series: [
    {
      name: 'Present',
      data: [44, 55, 41, 37, 22, 43, 21]
    },
    {
      name: 'Absent',
      data: [53, 32, 33, 52, 13, 43, 32]
    },
    {
      name: 'Late',
      data: [12, 17, 11, 9, 15, 11, 20]
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
    enabled: false
  },
  stroke: {
    width: 2,
  },
  title: {
    text: 'Attendance History'
  },
  xaxis: {
    categories: ['Math', 'Science', 'History', 'English', 'PE', 'Art', 'Music']
  },
  yaxis: {
    title: {
      text: 'Subjects'
    }
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
    <VueApexCharts type="bar" height="350" :options="chartOptions" :series="chartOptions.series" />
  </div>
</template>
