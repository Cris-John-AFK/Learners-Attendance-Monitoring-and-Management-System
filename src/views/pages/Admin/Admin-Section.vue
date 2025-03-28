<script setup>
import { GradesService } from '@/router/service/GradesService';
import ProgressSpinner from 'primevue/progressspinner';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const toast = useToast();
const grades = ref([]);
const loading = ref(true);
const showModal = ref(false);
const selectedGrade = ref(null);
const sections = ref([]);
const showCreateForm = ref(false);
const section = ref({
    name: '',
    capacity: 40,
    adviser: '',
    room: ''
});

// Load grades from database
const loadGrades = async () => {
    try {
        loading.value = true;
        const gradesData = await GradesService.getGrades();
        console.log('Fetched grades from API:', gradesData);
        grades.value = gradesData;
        loading.value = false;
    } catch (error) {
        console.error('Error loading grades:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load grades',
            life: 3000
        });
        loading.value = false;
    }
};

const getRandomGradient = () => {
    const colors = ['#ff9a9e', '#fad0c4', '#fbc2eb', '#a6c1ee', '#ffdde1', '#ee9ca7', '#ff758c', '#ff7eb3', '#c3cfe2', '#d4fc79', '#96e6a1', '#84fab0', '#8fd3f4', '#a18cd1'];
    const color1 = colors[Math.floor(Math.random() * colors.length)];
    const color2 = colors[Math.floor(Math.random() * colors.length)];
    return `linear-gradient(135deg, ${color1}, ${color2})`;
};

const cardStyles = computed(() => {
    return grades.value.map(() => ({
        background: getRandomGradient()
    }));
});

onMounted(async () => {
    await loadGrades();
});
</script>

<template>
    <div class="admin-section-wrapper">
        <div class="admin-section-container">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h2 class="section-title">Section Management</h2>
                        <p class="section-subtitle">Manage sections for each grade level</p>
                    </div>
                </div>

                <!-- Loading State -->
                <div v-if="loading" class="loading-state">
                    <ProgressSpinner />
                    <p>Loading grades...</p>
                </div>

                <!-- Empty State -->
                <div v-else-if="grades.length === 0" class="empty-state">
                    <div class="empty-icon">
                        <i class="pi pi-book"></i>
                    </div>
                    <h3>No Grades Found</h3>
                    <p>Please add grades in the Grade Management page first.</p>
                </div>

                <!-- Grade Cards Grid -->
                <div v-else class="cards-grid">
                    <div v-for="(grade, index) in grades"
                         :key="grade.id"
                         class="grade-card"
                         :style="cardStyles[index]"
                         @click="openSectionsModal(grade)">
                        <div class="card-content">
                            <h3 class="grade-title">{{ grade.name }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.admin-section-wrapper {
    padding: 1rem;
}

.admin-section-container {
    background-color: #ffffff;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.card {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 2rem;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.section-title {
    font-size: 2rem;
    color: #1a365d;
    margin: 0;
}

.section-subtitle {
    color: #4a5568;
    margin: 0.5rem 0 0 0;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
}

.grade-card {
    height: 180px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    cursor: pointer;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.grade-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
}

.card-content {
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 1.5rem;
}

.grade-title {
    font-size: 2rem;
    font-weight: 700;
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    margin: 0;
}

.loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 3rem;
}

.empty-state {
    text-align: center;
    padding: 3rem;
}

.empty-icon {
    font-size: 3rem;
    color: #4a5568;
    margin-bottom: 1rem;
}
</style>
