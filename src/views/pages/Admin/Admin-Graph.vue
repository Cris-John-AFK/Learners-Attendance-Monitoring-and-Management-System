<script setup>
import AttendanceChart from "@/views/uikit/AttendanceChart.vue";
import { ref } from "vue";

const grades = ref(
  Array.from({ length: 12 }, (_, i) => ({ grade: `Grade ${i + 1}` }))
);

const selectedGrade = ref(null);

const showSections = (grade) => {
  selectedGrade.value = grade;
  alert(`Showing sections for ${grade}`);
};

// Define gradient colors for each grade
const gradeColors = [
  "linear-gradient(135deg, #ff7eb3, #ff758c)", // Grade 1
  "linear-gradient(135deg, #ff9a8b, #ff6a88)", // Grade 2
  "linear-gradient(135deg, #ff758c, #ff7eb3)", // Grade 3
  "linear-gradient(135deg, #6a11cb, #2575fc)", // Grade 4
  "linear-gradient(135deg, #36d1dc, #5b86e5)", // Grade 5
  "linear-gradient(135deg, #ff512f, #dd2476)", // Grade 6
  "linear-gradient(135deg, #1fa2ff, #12d8fa)", // Grade 7
  "linear-gradient(135deg, #ff6a00, #ee0979)", // Grade 8
  "linear-gradient(135deg, #00c6ff, #0072ff)", // Grade 9
  "linear-gradient(135deg, #f4c4f3, #fc67fa)", // Grade 10
  "linear-gradient(135deg, #ff0844, #ffb199)", // Grade 11
  "linear-gradient(135deg, #a18cd1, #fbc2eb)", // Grade 12
];

// Get the background style for each grade
const getGradeColor = (index) => ({
  backgroundImage: gradeColors[index % gradeColors.length], // Use backgroundImage instead of background
});
</script>

<template>
  <div class="card">
    <h2 class="text-xl font-bold mb-4">Attendance Overview</h2>
    <AttendanceChart />
  </div>

  <div class="card-container">
    <sakai-card
      v-for="(grade, index) in grades"
      :key="index"
      class="custom-card"
      @click="showSections(grade.grade)"
    >
      <div class="card-header" :style="getGradeColor(index)">
        <div class="header-content">
          <h1 class="grade-name">{{ grade.grade }}</h1>
        </div>
        <img
          class="profile-icon"
          src="https://via.placeholder.com/50"
          alt="Profile Icon"
        />
      </div>
      <div class="card-body"></div>
    </sakai-card>
  </div>
</template>

<style scoped>
.card-container {
  display: flex;
  flex-wrap: wrap;
  gap: 90px;
}

.custom-card {
  width: 250px;
  height: 280px;
  border-radius: 10px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  cursor: pointer;
}

.custom-card:hover {
  transform: scale(1.05);
  box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
}

.card-header {
  color: white;
  padding: 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.grade-name {
  font-size: 18px;
  margin: 0;
}

.profile-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
}

.card-body {
  flex-grow: 1;
  background: white;
}
</style>
