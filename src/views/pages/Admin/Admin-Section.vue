<script setup>
import SakaiCard from '@/components/SakaiCard.vue';
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
    const colors = ['#ff9a9e', '#fad0c4', '#fad0c4', '#fbc2eb', '#a6c1ee', '#ffdde1', '#ee9ca7', '#ff758c', '#ff7eb3', '#c3cfe2', '#d4fc79', '#96e6a1', '#84fab0', '#8fd3f4', '#a18cd1'];

    const color1 = colors[Math.floor(Math.random() * colors.length)];
    const color2 = colors[Math.floor(Math.random() * colors.length)];

    return `linear-gradient(135deg, ${color1}, ${color2})`;
};

const cardStyles = computed(() =>
    grades.value.map(() => ({
        background: getRandomGradient()
    }))
);

const grades = ref([{ grade: 'Kinder' }, { grade: 'Grade 1' }, { grade: 'Grade 2' }, { grade: 'Grade 3' }, { grade: 'Grade 4' }, { grade: 'Grade 5' }, { grade: 'Grade 6' }]);

const gradeSections = ref({
    Kinder: [{ name: 'Section A' }, { name: 'Section B' }],
    'Grade 1': [{ name: 'Section A' }, { name: 'Section B' }],
    'Grade 2': [{ name: 'Section A' }, { name: 'Section B' }],
    'Grade 3': [{ name: 'Section A' }, { name: 'Section B' }],
    'Grade 4': [{ name: 'Section A' }, { name: 'Section B' }],
    'Grade 5': [{ name: 'Section A' }, { name: 'Section B' }],
    'Grade 6': [{ name: 'Section A' }, { name: 'Section B' }]
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

// State Variables
const selectedGrade = ref(null);
const selectedSection = ref(null);
const showModal = ref(false);
const showCreateForm = ref(false);
const showEditForm = ref(false);

const newSection = ref({ name: '', students: '' });
const editedSection = ref({ name: '', students: [], index: null });

// Open Sections Modal
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
    showModal.value = false;
    showCreateForm.value = true;
    newSection.value = { name: '', students: '' };
};

const createSection = () => {
    if (!newSection.value.name) return;

    gradeSections.value[selectedGrade.value] = [...(gradeSections.value[selectedGrade.value] || []), { name: newSection.value.name }];

    sectionStudents.value[newSection.value.name] = newSection.value.students
        ? newSection.value.students.split(',').map((name) => ({
              id: `S${Math.floor(Math.random() * 1000)}`,
              name: name.trim()
          }))
        : [];

    toast.add({ severity: 'success', summary: 'Section Added', detail: 'New section created.', life: 3000 });

    showCreateForm.value = false;
};

// Open Edit Section Form
const openEditForm = (section, index) => {
    editedSection.value = {
        name: section.name,
        index,
        students: sectionStudents.value[section.name] || []
    };
    showEditForm.value = true;
};

// Update Section
const updateSection = () => {
    if (!editedSection.value.name) return;

    // Update section name
    const oldSectionName = gradeSections.value[selectedGrade.value][editedSection.value.index].name;
    const newSectionName = editedSection.value.name;

    gradeSections.value[selectedGrade.value][editedSection.value.index].name = newSectionName;

    // Update students list
    sectionStudents.value[newSectionName] = [...editedSection.value.students];

    // Remove old section students if name changed
    if (oldSectionName !== newSectionName) {
        delete sectionStudents.value[oldSectionName];
    }

    toast.add({ severity: 'success', summary: 'Section Updated', detail: 'Section details updated.', life: 3000 });

    showEditForm.value = false;
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

    <!-- Sections Modal -->
    <Dialog v-model:visible="showModal" :style="{ width: '500px' }" header="Sections" :modal="true">
        <div v-if="!selectedSection">
            <div class="flex justify-between items-center">
                <h3>Sections in {{ selectedGrade }}</h3>
                <Button label="Create" icon="pi pi-plus" class="p-button-success" @click="openCreateForm" />
            </div>
            <ul class="section-list">
                <li v-for="(section, index) in gradeSections[selectedGrade]" :key="index" class="section-item">
                    <span class="section-name" @click="selectSection(section.name)">{{ section.name }}</span>
                    <div class="section-buttons">
                        <Button label="View" icon="pi pi-eye" class="p-button-text p-button-sm" @click="selectSection(section.name)" />
                        <Button label="Edit" icon="pi pi-pencil" class="p-button-text p-button-sm" @click="openEditForm(section, index)" />
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
    <Dialog v-model:visible="showEditForm" :style="{ width: '500px' }" header="Edit Section" :modal="true">
        <h3 class="mb-2">Edit Section</h3>

        <label class="font-bold">Section Name</label>
        <InputText v-model="editedSection.name" placeholder="Enter section name" class="w-full mb-3" />

        <label class="font-bold">Students</label>
        <MultiSelect v-model="editedSection.students" :options="allStudents" optionLabel="name" placeholder="Select students" class="w-full mb-3" />

        <div class="flex justify-end gap-2">
            <Button label="Cancel" class="p-button-text" @click="showEditForm = false" />
            <Button label="Save" class="p-button-success" @click="updateSection" />
        </div>
    </Dialog>

    <!-- Create Section Modal -->
    <Dialog v-model:visible="showCreateForm" :style="{ width: '500px' }" header="Create New Section" :modal="true">
        <h3>Create New Section</h3>
        <InputText v-model="newSection.name" placeholder="Enter section name" class="w-full mb-2" />
        <Textarea v-model="newSection.students" placeholder="Enter student names (comma-separated)" class="w-full mb-2" />
        <div class="flex justify-end gap-2">
            <Button label="Cancel" class="p-button-text" @click="showCreateForm = false" />
            <Button label="Save" class="p-button-success" @click="createSection" />
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
    transition:
        background 0.3s,
        transform 0.2s;
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
    transition:
        transform 0.2s,
        box-shadow 0.2s;
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
    color: white;
    font-weight: bold;
}
</style>
