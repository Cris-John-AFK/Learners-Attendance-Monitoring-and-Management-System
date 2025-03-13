<script setup>
import { ref } from 'vue';
import { useToast } from 'primevue/usetoast';

const grades = ref(Array.from({ length: 12 }, (_, i) => ({ grade: `Grade ${i + 1}` })));
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
const gradeColors = [
    'linear-gradient(135deg, #ff7eb3, #ff758c)',
    'linear-gradient(135deg, #ff9a8b, #ff6a88)',
    'linear-gradient(135deg, #ff758c, #ff7eb3)',
    'linear-gradient(135deg, #6a11cb, #2575fc)',
    'linear-gradient(135deg, #36d1dc, #5b86e5)',
    'linear-gradient(135deg, #ff512f, #dd2476)',
    'linear-gradient(135deg, #1fa2ff, #12d8fa)',
    'linear-gradient(135deg, #ff6a00, #ee0979)',
    'linear-gradient(135deg, #00c6ff, #0072ff)',
    'linear-gradient(135deg, #f4c4f3, #fc67fa)',
    'linear-gradient(135deg, #ff0844, #ffb199)',
    'linear-gradient(135deg, #a18cd1, #fbc2eb)'
];
const getGradeColor = (index) => ({
    backgroundImage: gradeColors[index % gradeColors.length]
});

const toast = useToast();
const expandedRows = ref([]);
const sessions = ref([
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

const openNew = () => {
    newStudent.value = { id: '', name: '', status: 'Present', remarks: '' };
    studentDialog.value = true;
};

const addStudent = () => {
    if (selectedStudent.value) {
        Object.assign(selectedStudent.value, newStudent.value);
        toast.add({ severity: 'success', summary: 'Student Updated', detail: 'Student details updated.', life: 3000 });
    } else {
        sessions.value[0].students.push({ ...newStudent.value });
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
    sessions.value[0].students = sessions.value[0].students.filter((student) => student.id !== studentId);
    toast.add({ severity: 'warn', summary: 'Student Removed', detail: 'Student has been deleted.', life: 3000 });
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
    <div v-if="selectedGrade !== 'attendance'" class="card-container">
        <sakai-card v-for="(grade, index) in grades" :key="index" class="custom-card" @click="showSections(grade.grade)">
            <div class="card-header" :style="getGradeColor(index)">
                <h1 class="grade-name">{{ grade.grade }}</h1>
            </div>
            <div class="card-body"></div>
            <div class="card-footer">
                <i class="pi pi-id-card hover-icon" @click.stop="showAttendance"></i>
                <i class="pi pi-folder"></i>
            </div>
        </sakai-card>
    </div>
    <div v-else>
        <div class="card">
            <button @click="goBack" class="mb-4 bg-gray-500 text-white px-4 py-2 rounded">Back</button>
            <Toolbar class="mb-6">
                <template #start>
                    <Button label="New" icon="pi pi-plus" severity="secondary" class="mr-2" @click="openNew" />
                </template>
            </Toolbar>
            <div class="font-semibold text-xl mb-4">Attendance History</div>
            <DataTable v-model:expandedRows="expandedRows" :value="sessions" dataKey="id" tableStyle="min-width: 60rem">
                <Column expander style="width: 5rem" />
                <Column field="title" header="Session Title"></Column>
                <Column field="date" header="Date"></Column>
                <template #expansion="slotProps">
                    <div class="p-4">
                        <h5>Attendance for {{ slotProps.data.title }}</h5>
                        <DataTable :value="slotProps.data.students">
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
    <Dialog v-model:visible="studentDialog" :style="{ width: '450px' }" header="Manage Student" :modal="true">
        <div class="flex flex-col gap-4">
            <InputText v-model="newStudent.id" placeholder="Student ID" />
            <InputText v-model="newStudent.name" placeholder="Student Name" />
            <Dropdown v-model="newStudent.status" :options="['Present', 'Absent', 'Late']" placeholder="Select Status" />
            <Textarea v-model="newStudent.remarks" placeholder="Remarks" rows="3" />
        </div>
        <template #footer>
            <Button label="Cancel" text @click="studentDialog = false" />
            <Button label="Save" icon="pi pi-check" @click="addStudent" />
        </template>
    </Dialog>
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
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
    cursor: pointer;
}
.custom-card:hover {
    transform: scale(1.05);
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
}
.card-header {
    color: white;
    padding: 15px;
    text-align: center;
}
.grade-name {
    font-size: 18px;
    margin: 0;
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
