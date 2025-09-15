<script setup>
import { ref, watch } from 'vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
    chartData: Object
});

const chartOptions = ref({
    series: [],
    chart: {
        type: 'bar',
        height: 400,
        stacked: true,
        toolbar: {
            show: true,
            tools: {
                download: true,
                selection: false,
                zoom: false,
                zoomin: false,
                zoomout: false,
                pan: false,
                reset: false
            }
        },
        fontFamily: 'Inter, sans-serif'
    },
    plotOptions: {
        bar: {
            horizontal: true,
            barHeight: '70%',
            borderRadius: 6,
            dataLabels: {
                position: 'center'
            }
        }
    },
    dataLabels: {
        enabled: true,
        style: {
            fontSize: '12px',
            fontWeight: 600,
            colors: ['#fff']
        },
        formatter: function (val) {
            return val > 5 ? val : '';
        }
    },
    xaxis: {
        categories: [],
        title: {
            text: 'Number of Students',
            style: {
                fontSize: '14px',
                fontWeight: 600,
                color: '#374151'
            }
        },
        labels: {
            style: {
                fontSize: '12px',
                fontWeight: 500,
                colors: '#6B7280'
            }
        }
    },
    yaxis: {
        title: {
            text: 'Grade Levels',
            style: {
                fontSize: '14px',
                fontWeight: 600,
                color: '#374151'
            }
        },
        labels: {
            style: {
                fontSize: '12px',
                fontWeight: 500,
                colors: '#374151'
            }
        }
    },
    tooltip: {
        shared: false,
        y: {
            formatter: (val) => `${val} students`
        },
        style: {
            fontSize: '12px'
        }
    },
    legend: {
        position: 'top',
        horizontalAlign: 'center',
        floating: false,
        fontSize: '13px',
        fontWeight: 500,
        markers: {
            width: 12,
            height: 12,
            radius: 3
        },
        itemMargin: {
            horizontal: 15,
            vertical: 5
        }
    },
    grid: {
        borderColor: '#E5E7EB',
        strokeDashArray: 3,
        xaxis: {
            lines: {
                show: true
            }
        },
        yaxis: {
            lines: {
                show: false
            }
        }
    },
    colors: ['#10B981', '#EF4444', '#F59E0B', '#8B5CF6']
});

watch(() => props.chartData, (newData) => {
    if (newData) {
        chartOptions.value.series = newData.datasets.map(dataset => ({
            name: dataset.label,
            data: dataset.data
        }));
        chartOptions.value.xaxis.categories = newData.labels;
    }
}, { immediate: true });
</script>

<template>
  <div class="chart-container">
    <div class="chart-header">
      <h3 class="chart-title">Attendance Overview by Grade Level</h3>
      <p class="chart-subtitle">Real-time student attendance data across all grade levels</p>
    </div>
    <VueApexCharts type="bar" height="400" width="100%" :options="chartOptions" :series="chartOptions.series" />
  </div>
</template>

<style scoped>
.chart-container {
  width: 100%;
  background: white;
  border-radius: 12px;
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.chart-header {
  text-align: center;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 2px solid #f1f5f9;
}

.chart-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 8px 0;
  letter-spacing: -0.025em;
}

.chart-subtitle {
  font-size: 0.95rem;
  color: #64748b;
  margin: 0;
  font-weight: 400;
}
</style>
