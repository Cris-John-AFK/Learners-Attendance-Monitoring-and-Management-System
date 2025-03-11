<script setup>
import { ref } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const chartOptions = ref({
  series: [
    {
      name: 'Present',
      data: [44, 55, 41, 37, 22, 43, 21, 12, 13 , 25, 18, 26]
    },
    {
      name: 'Absent',
      data: [53, 32, 33, 52, 13, 43, 32, 30 , 21 , 12, 8 , 10]
    },
    {
      name: 'Late',
      data: [12, 17, 11, 9, 15, 11, 20, 10, 8, 7, 15, 2]
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
  xaxis: {
    categories: ['1', '2', '3', '4', '5', '6', '7', '8', '9' , '10' , '11' , '12' ,]
  },
  yaxis: {
    title: {
      text: 'Grade Level'
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
