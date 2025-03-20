<template>
    <div class="attendance-container">
      <h2 class="title">Attendance Overview</h2>
      <AttendanceChart :chartData="defaultGradeChartData" />
    </div>

    <div class="card-container">
        <Sakai-card
            v-for="(grade, index) in grades"
            :key="index"
            class="custom-card"
            :style="cardStyles[index]"
            @click="showSections(grade.grade)">
            <div class="card-header">
                <h1 class="grade-name">{{ grade.grade }}</h1>
            </div>
        </Sakai-card>
    </div>

    <Dialog v-model:visible="showModal" modal header="Section Attendance Overview" :style="{ width: '80vw' }">
      <AttendanceChart v-if="selectedGradeData" :chartData="selectedGradeData" />
    </Dialog>
</template>

<script setup>
import AttendanceChart from "@/components/Admin/AttendanceChart.vue";
import SakaiCard from '@/components/SakaiCard.vue';
import Dialog from 'primevue/dialog';
import { computed, ref } from "vue";

const grades = ref([
    { grade: 'Kinder' },
    { grade: 'Grade 1' },
    { grade: 'Grade 2' },
    { grade: 'Grade 3' },
    { grade: 'Grade 4' },
    { grade: 'Grade 5' },
    { grade: 'Grade 6' }
]);

// Default chart data for grade-level attendance
const defaultGradeChartData = ref({
    labels: ['Kinder', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6'],
    datasets: [
        { label: 'Present', backgroundColor: '#90EE90', data: [45, 48, 42, 40, 43, 38, 35] },
        { label: 'Absent', backgroundColor: '#FFB6C1', data: [5, 4, 8, 10, 7, 12, 15] },
        { label: 'Late', backgroundColor: '#FFD580', data: [7, 5, 6, 5, 6, 4, 7] }
    ]
});


// Section-wise attendance data
const gradeSectionsData = ref({
    'Kinder': {
        labels: ['Section A', 'Section B', 'Section C', 'Section D'],
        datasets: [
            { label: 'Present', backgroundColor: '#90EE90', data: [90, 85, 88, 80] },
            { label: 'Absent', backgroundColor: '#FFB6C1', data: [5, 8, 7, 10] },
            { label: 'Late', backgroundColor: '#FFD580', data: [5, 7, 5, 10] }
        ]
    },
    'Grade 1': {
        labels: ['Section A', 'Section B', 'Section C', 'Section D'],
        datasets: [
            { label: 'Present', backgroundColor: '#90EE90', data: [85, 88, 80, 75] },
            { label: 'Absent', backgroundColor: '#FFB6C1', data: [8, 7, 10, 12] },
            { label: 'Late', backgroundColor: '#FFD580', data: [7, 5, 10, 13] }
        ]
    },
    'Grade 2': {
        labels: ['Section A', 'Section B', 'Section C', 'Section D'],
        datasets: [
            { label: 'Present', backgroundColor: '#90EE90', data: [88, 85, 82, 89] },
            { label: 'Absent', backgroundColor: '#FFB6C1', data: [7, 10, 9, 5] },
            { label: 'Late', backgroundColor: '#FFD580', data: [5, 5, 9, 6] }
        ]
    },
    'Grade 3': {
        labels: ['Section A', 'Section B', 'Section C', 'Section D'],
        datasets: [
            { label: 'Present', backgroundColor: '#90EE90', data: [88, 85, 82, 89] },
            { label: 'Absent', backgroundColor: '#FFB6C1', data: [7, 10, 9, 5] },
            { label: 'Late', backgroundColor: '#FFD580', data: [5, 5, 9, 6] }
        ]
    },
    'Grade 4': {
        labels: ['Section A', 'Section B', 'Section C', 'Section D'],
        datasets: [
            { label: 'Present', backgroundColor: '#90EE90', data: [88, 85, 82, 89] },
            { label: 'Absent', backgroundColor: '#FFB6C1', data: [7, 10, 9, 5] },
            { label: 'Late', backgroundColor: '#FFD580', data: [5, 5, 9, 6] }
        ]
    },
    'Grade 5': {
        labels: ['Section A', 'Section B', 'Section C', 'Section D'],
        datasets: [
            { label: 'Present', backgroundColor: '#90EE90', data: [88, 85, 82, 89] },
            { label: 'Absent', backgroundColor: '#FFB6C1', data: [7, 10, 9, 5] },
            { label: 'Late', backgroundColor: '#FFD580', data: [5, 5, 9, 6] }
        ]
    },
    'Grade 6': {
        labels: ['Section A', 'Section B', 'Section C', 'Section D'],
        datasets: [
            { label: 'Present', backgroundColor: '#90EE90', data: [88, 85, 82, 89] },
            { label: 'Absent', backgroundColor: '#FFB6C1', data: [7, 10, 9, 5] },
            { label: 'Late', backgroundColor: '#FFD580  ', data: [5, 5, 9, 6] }
        ]
    }
});

const showModal = ref(false);
const selectedGradeData = ref(null);

const showSections = (grade) => {
    selectedGradeData.value = gradeSectionsData.value[grade] || null;
    showModal.value = true;
};

const getRandomGradient = () => {
    const colors = [
        '#ff9a9e', '#fad0c4', '#fad0c4', '#fbc2eb', '#a6c1ee',
        '#ffdde1', '#ee9ca7', '#ff758c', '#ff7eb3', '#c3cfe2',
        '#d4fc79', '#96e6a1', '#84fab0', '#8fd3f4', '#a18cd1'
    ];

    const color1 = colors[Math.floor(Math.random() * colors.length)];
    const color2 = colors[Math.floor(Math.random() * colors.length)];

    return `linear-gradient(135deg, ${color1}, ${color2})`;
};

const cardStyles = computed(() =>
    grades.value.map(() => ({
        background: getRandomGradient()
    }))
);
</script>

<style scoped>
.attendance-container {
    width: 100%;
    padding: 10px;
    box-sizing: border-box;
    justify-content: center;
    align-items: center;
    display: grid;
}

.card-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    justify-content: center;
    align-items: center;
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
}

.custom-card {
    width: 200px;
    height: 250px;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    cursor: pointer;
    text-align: center;
    transition: transform 0.2s, box-shadow 0.2s;
    color: white;
    font-weight: bold;
}

.custom-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.card-header {
    color: white;
    padding: 12px 15px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: bold;
}
</style>
