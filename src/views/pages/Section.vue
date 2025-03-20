<script setup>
import SakaiCard from '@/components/SakaiCard.vue';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const grades = ref([
    { grade: 'Kinder' },
    { grade: 'Grade 1' },
    { grade: 'Grade 2' },
    { grade: 'Grade 3' },
    { grade: 'Grade 4' },
    { grade: 'Grade 5' },
    { grade: 'Grade 6' }
]);
const selectedGrade = ref(null);
const showSections = (grade) => {
    selectedGrade.value = grade;
};
const showAttendance = () => {
    selectedGrade.value = 'attendance';
};
const goBack = () => {
    selectedGrade.value = null;
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
const getGradeColor = (index) => ({
    backgroundImage: gradeColors[index % gradeColors.length]
});

const cardStyles = computed(() =>
    grades.value.map(() => ({
        background: getRandomGradient()
    }))
);

const toast = useToast();
const expandedRows = ref([]);
const sections = ref([
    {
        id: 'SES001',
        title: 'Math Class - Section A',
        date: '2025-03-10',
        students: [
            { id: 'S001', name: 'John Doe', status: 'Present', remarks: '' },
            { id: 'S002', name: 'Jane Smith', status: 'Absent', remarks: '' }
        ]
    }
]);

const studentDialog = ref(false);
const newStudent = ref({ id: '', name: '', status: 'Present', remarks: '' });
const selectedStudent = ref(null);
const studentSearch = ref('');

const addStudent = () => {
    if (selectedStudent.value) {
        Object.assign(selectedStudent.value, newStudent.value);
        toast.add({ severity: 'success', summary: 'Student Updated', detail: 'Student details updated.', life: 3000 });
    } else {
        sections.value[0].students.push({ ...newStudent.value });
        toast.add({ severity: 'success', summary: 'Student Added', detail: 'New student has been added.', life: 3000 });
    }
    studentDialog.value = false;
    selectedStudent.value = null;
};

const editStudent = (student) => {
    newStudent.value = { ...student };
    selectedStudent.value = student;
    studentDialog.value = true;
};

const deleteStudent = (studentId) => {
    sections.value[0].students = sections.value[0].students.filter((student) => student.id !== studentId);
    toast.add({ severity: 'warn', summary: 'Student Removed', detail: 'Student has been deleted.', life: 3000 });
};

const updateAllDates = (newDate) => {
    sections.value.forEach((section) => {
        section.date = newDate;
    });

    toast.add({
        severity: 'success',
        summary: 'Date Updated',
        detail: `All dates set to ${newDate}`,
        life: 3000
    });
};

const filteredStudents = (students) => {
    if (!studentSearch.value) return students;
    return students.filter((student) => student.name.toLowerCase().includes(studentSearch.value.toLowerCase()));
};

function getStatusSeverity(status) {
    switch (status) {
        case 'Present':
            return 'success';
        case 'Absent':
            return 'danger';
        case 'Late':
            return 'warn';
        default:
            return null;
    }
}
</script>

<template>
    <!-- Show grade selection if no grade is selected -->
    <div v-if="!selectedGrade" class="card-container">
        <Sakai-card
            v-for="(grade, index) in grades"
            :key="index"
            class="custom-card"
            :style="cardStyles[index]"
            @click="showSections(grade.grade)"
        >
            <div class="card-header">
                <h1 class="grade-name">{{ grade.grade }}</h1>
            </div>
        </Sakai-card>
    </div>

    <!-- Show sections for the selected grade -->
    <div v-else-if="selectedGrade !== 'attendance'">
        <div class="card">
            <button @click="goBack" class="mb-4 bg-gray-500 text-white px-4 py-2 rounded">Back</button>
            <div class="font-semibold text-xl mb-4">Sections for {{ selectedGrade }}</div>

            <DataTable v-model:expandedRows="expandedRows" :value="sections" dataKey="id" tableStyle="min-width: 60rem">
                <Column expander style="width: 5rem" />
                <Column field="title" header="Section Name"></Column>

                <Column field="date" header="Date">
                    <template #body="slotProps">
                        <Calendar v-model="slotProps.data.date" dateFormat="yy-mm-dd" showIcon @update:modelValue="updateAllDates(slotProps.data.date)" />
                    </template>
                </Column>

                <template #expansion="slotProps">
                    <div class="p-4">
                        <h5>Attendance for {{ slotProps.data.title }}</h5>
                        <DataTable :value="filteredStudents(slotProps.data.students)">
                            <Column field="id" header="ID" sortable></Column>
                            <Column field="name" header="Name" sortable></Column>
                            <Column field="status" header="Status" sortable>
                                <template #body="student">
                                    <Tag :value="student.data.status" :severity="getStatusSeverity(student.data.status)" />
                                </template>
                            </Column>
                            <Column field="remarks" header="Remarks" sortable></Column>
                            <Column>
                                <template #body="student">
                                    <Button icon="pi pi-pencil" class="p-button-warning mr-2" @click="editStudent(student.data)" />
                                    <Button icon="pi pi-trash" class="p-button-danger" @click="deleteStudent(student.data.id)" />
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>

    <!-- Show attendance view -->
    <div v-else>
        <div class="card">
            <button @click="goBack" class="mb-4 bg-gray-500 text-white px-4 py-2 rounded">Back</button>
            <Toolbar class="mb-6">
                <template #end>
                    <IconField>
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="studentSearch" type="text" placeholder="Search student..." />
                    </IconField>
                </template>
            </Toolbar>
            <div class="font-semibold text-xl mb-4">List of Sections</div>
            <DataTable v-model:expandedRows="expandedRows" :value="sections" dataKey="id" tableStyle="min-width: 60rem">
                <Column expander style="width: 5rem" />
                <Column field="title" header="Sections Name"></Column>

                <Column field="date" header="Date">
                    <template #body="slotProps">
                        <Calendar v-model="slotProps.data.date" dateFormat="yy-mm-dd" showIcon @update:modelValue="updateAllDates(slotProps.data.date)" />
                    </template>
                </Column>

                <template #expansion="slotProps">
                    <div class="p-4">
                        <h5>Attendance for {{ slotProps.data.title }}</h5>
                        <DataTable :value="filteredStudents(slotProps.data.students)">
                            <Column field="id" header="ID" sortable></Column>
                            <Column field="name" header="Name" sortable></Column>
                            <Column field="status" header="Status" sortable>
                                <template #body="student">
                                    <Tag :value="student.data.status" :severity="getStatusSeverity(student.data.status)" />
                                </template>
                            </Column>
                            <Column field="remarks" header="Remarks" sortable></Column>
                            <Column>
                                <template #body="student">
                                    <Button icon="pi pi-pencil" class="p-button-warning mr-2" @click="editStudent(student.data)" />
                                    <Button icon="pi pi-trash" class="p-button-danger" @click="deleteStudent(student.data.id)" />
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>
</template>


<style scoped>
.card-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    justify-content: center;
    align-items: center;
    width: 100%;
    max-width: 900px; /* Set a max width to limit stretching */
    margin: 0 auto; /* Center the grid */
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

.card-body {
    flex-grow: 1;
    background: white;
}
.card-footer {
    display: flex;
    justify-content: space-between;
    padding: 10px;
    border-top: 1px solid #ddd;
    background: white;
}
.card-footer i {
    font-size: 20px;
    cursor: pointer;
    transition: color 0.3s ease;
}
.card-footer i.hover-icon:hover {
    color: #ff5722;
}
</style>
