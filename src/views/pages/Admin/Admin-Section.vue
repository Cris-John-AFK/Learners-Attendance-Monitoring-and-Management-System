<script setup>
import SakaiCard from '@/components/SakaiCard.vue';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const deleteSection = (index) => {
    confirmDelete(index);
};

const confirmDelete = (index) => {
    if (confirm('Are you sure you want to delete this section?')) {
        gradeSections.value[selectedGrade.value].splice(index, 1);
    }
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

const sectionStudents = ref({
    'Section A': [
        { id: 'S001', name: 'John Doe' },
        { id: 'S002', name: 'Jane Smith' }
    ],
    'Section B': [
        { id: 'S003', name: 'Alice Brown' },
        { id: 'S004', name: 'Bob White' }
    ]
});

const selectedGrade = ref(null);
const selectedSection = ref(null);
const showModal = ref(false);
const showCreateForm = ref(false);
const newSection = ref({ name: '', students: '' });
const toast = useToast();

const openSectionsModal = (grade) => {
    selectedGrade.value = grade;
    showModal.value = true;
    selectedSection.value = null;
};

const selectSection = (section) => {
    selectedSection.value = section;
    if (!sectionStudents.value[selectedSection.value]) {
        sectionStudents.value[selectedSection.value] = []; // Initialize if missing
    }
};

const openCreateForm = () => {
    showCreateForm.value = true;
    newSection.value = { name: '', students: '' };
};

const createSection = () => {
    if (!newSection.value.name) return;
    gradeSections.value[selectedGrade.value] = [
        ...(gradeSections.value[selectedGrade.value] || []),
        { name: newSection.value.name }
    ];
    sectionStudents.value[newSection.value.name] = newSection.value.students
        ? newSection.value.students.split(',').map(name => ({ id: `S${Math.floor(Math.random() * 1000)}`, name: name.trim() }))
        : [];
    toast.add({ severity: 'success', summary: 'Section Added', detail: 'New section created.', life: 3000 });
    showCreateForm.value = false;
};
</script>

<template>
    <div class="card-container">
        <Sakai-card v-for="(grade, index) in grades" :key="index" class="custom-card" :style="cardStyles[index]" @click="openSectionsModal(grade.grade)">
            <div class="card-header">
                <h1 class="grade-name">{{ grade.grade }}</h1>
            </div>
        </Sakai-card>
    </div>

    <Dialog v-model:visible="showModal" :style="{ width: '500px' }" header="Sections" :modal="true">
        <div v-if="!selectedSection">
            <div class="flex justify-between items-center">
                <h3>Sections in {{ selectedGrade }}</h3>
                <Button label="Create" icon="pi pi-plus" @click="openCreateForm" />
            </div>
            <ul class="section-list">
                <li v-for="(section, index) in gradeSections[selectedGrade]"
                    :key="index"
                    class="section-item"
                >
                    <span class="section-name" @click="selectSection(section.name)">{{ section.name }}</span>
                    <div class="section-buttons">
                        <Button label="View" icon="pi pi-eye" class="p-button-text p-button-sm" @click="selectSection(section.name)" />
                        <Button label="Edit" icon="pi pi-pencil" class="p-button-text p-button-sm" />
                        <Button label="Delete" icon="pi pi-trash" class="p-button-danger p-button-sm" @click="deleteSection(index)" />
                    </div>
                </li>
            </ul>
        </div>
        <div v-else>
            <h3>Students in {{ selectedSection }}</h3>
            <ul v-if="sectionStudents[selectedSection]?.length" class="student-list">
                <li v-for="student in sectionStudents[selectedSection]" :key="student.id" class="student-item">
                    <i class="pi pi-user student-icon"></i>
                    <span class="student-name">{{ student.name }}</span>
                </li>
            </ul>
            <p v-else>No students in this section.</p>

            <Button label="Back" icon="pi pi-arrow-left" @click="selectedSection = null" />
        </div>
    </Dialog>
</template>


<style scoped>
.student-list {
    list-style: none;
    padding: 0;
    margin-top: 10px;
}

.student-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: background 0.3s, transform 0.2s;
}

.student-icon {
    font-size: 18px;
    color: #007ad9;
}

.student-name {
    font-size: 16px;
    font-weight: 600;
    color: #333;
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
}

.custom-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 02);
}

.section-item {
    padding: 10px;
    cursor: pointer;
    background: #eee;
    margin: 5px 0;
}
.section-item:hover {
    background: #ddd;
}

.section-list {
    list-style: none;
    padding: 0;
    margin-top: 10px;
}

.section-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: background 0.3s, transform 0.2s;
    cursor: pointer;
}

.section-item:hover {
    background: #e0f7fa;
    transform: scale(1.02);
}

.section-name {
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.section-buttons {
    display: flex;
    gap: 8px;
}

.custom-card {
    color: white;  /* Ensures text is readable on gradient */
    font-weight: bold;
}

</style>
