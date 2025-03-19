<template>
    <div class="attendance-container">
      <h2 class="title">Attendance Overview</h2>
      <AttendanceChart />
    </div>

    <div class="card-container">
        <Sakai-card v-for="(grade, index) in grades" :key="index" class="custom-card" :style="cardStyles[index]" @click="showSections(grade.grade)">
            <div class="card-header">
                <h1 class="grade-name">{{ grade.grade }}</h1>
            </div>
        </Sakai-card>
    </div>
  </template>

  <script setup>
  import SakaiCard from '@/components/SakaiCard.vue';
import AttendanceChart from "@/views/uikit/AttendanceChart.vue";
import { computed, ref } from "vue";

const grades = ref(Array.from({ length: 7 }, (_, i) => ({ grade: `Grade ${i + 1}` })));
const gradeSections = ref({
    'Grade 1': [{ name: 'Section A' }, { name: 'Section B' }],
    'Grade 2': [{ name: 'Section A' }, { name: 'Section B' }],
    'Grade 3': [{ name: 'Section A' }, { name: 'Section B' }],
    'Grade 4': [{ name: 'Section A' }, { name: 'Section B' }],
    'Grade 5': [{ name: 'Section A' }, { name: 'Section B' }],
    'Grade 6': [{ name: 'Section A' }, { name: 'Section B' }],
    'Grade 7': [{ name: 'Section A' }, { name: 'Section B' }],
});
  const selectedGrade = ref(null);

  const showSections = (grade) => {
    selectedGrade.value = grade;
    alert(`Showing sections for ${grade}`);
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
  // Style for each grade
  const getGradeColor = (index) => ({
    background: gradeColors[index % gradeColors.length],
  });

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
  }

  .card-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
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
    background: #f0f0f0;
    transition: transform 0.2s, box-shadow 0.2s;
    color: white;
    font-weight: bold;
}

.custom-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 02);
}

  .card-header {
    color: white;
    padding: 12px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: bold;
  }

  .profile-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
  }
  </style>
