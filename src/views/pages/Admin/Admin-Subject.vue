<script setup>
import SakaiCard from '@/components/SakaiCard.vue';
import { CurriculumService } from '@/router/service/CurriculumService';
import { computed, onMounted, ref } from 'vue';

const subjects = ref([]);

const getRandomGradient = () => {
    const colors = ['#ff9a9e', '#fad0c4', '#fbc2eb', '#a6c1ee', '#ffdde1', '#ee9ca7', '#ff758c', '#ff7eb3', '#c3cfe2', '#d4fc79', '#96e6a1', '#84fab0', '#8fd3f4', '#a18cd1'];

    const color1 = colors[Math.floor(Math.random() * colors.length)];
    const color2 = colors[Math.floor(Math.random() * colors.length)];

    return `linear-gradient(135deg, ${color1}, ${color2})`;
};

const cardStyles = computed(() =>
    subjects.value.map(() => ({
        background: getRandomGradient()
    }))
);

// Fetch subjects from CurriculumService
const fetchSubjects = async () => {
    const data = await CurriculumService.getSubjects();
    subjects.value = data.map((item, index) => ({
        id: index + 1,
        name: item.name
    }));
};

onMounted(() => {
    fetchSubjects();
});
</script>

<template>
    <div class="card-container">
        <SakaiCard v-for="(subject, index) in subjects" :key="index" class="custom-card" :style="cardStyles[index]">
            <div class="card-header">
                <h1 class="subject-name">{{ subject.name }}</h1>
            </div>
        </SakaiCard>
    </div>
</template>

<style scoped>
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
    transition:
        transform 0.2s,
        box-shadow 0.2s;
}

.custom-card:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.subject-name {
    margin: 0;
    text-align: center;
    word-break: break-word;
    overflow-wrap: break-word;
    white-space: pre-wrap;
}

.subject-name:only-child {
    display: inline-block;
    font-size: 25px;
}
</style>
