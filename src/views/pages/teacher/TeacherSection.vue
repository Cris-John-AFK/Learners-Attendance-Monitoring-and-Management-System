<script setup>
import SakaiCard from '@/components/SakaiCard.vue';
import { AttendanceService } from '@/router/service/Students';
import { SubjectService } from '@/router/service/Subjects';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const grades = ref([{ grade: 'Kinder' }, { grade: 'Grade 1' }, { grade: 'Grade 2' }, { grade: 'Grade 3' }, { grade: 'Grade 4' }, { grade: 'Grade 5' }, { grade: 'Grade 6' }]);
const selectedGrade = ref(null);
const showSections = (grade) => {
    selectedGrade.value = grade;
    fetchSections(grade);
};
const showAttendance = () => {
    selectedGrade.value = 'attendance';
};
const goBack = () => {
    selectedGrade.value = null;
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

const toast = useToast();
const expandedRows = ref([]);
const sections = ref([]);
const students = ref([]);

const studentDialog = ref(false);
const newStudent = ref({ id: '', name: '', status: 'Present', remarks: '' });
const selectedStudent = ref(null);
const studentSearch = ref('');

// Fetch sections for the selected grade
const fetchSections = async (grade) => {
    try {
        // First get the sections from the SubjectService
        const sectionsData = await SubjectService.getSectionsByGrade(grade);

        // Now for each section, fetch the students using the enhanced relationship
        sections.value = await Promise.all(
            sectionsData.map(async (section) => {
                // Get students for this section with optimized filtering
                const sectionStudents = await SubjectService.getStudentsBySection(section.id);

                return {
                    ...section,
                    students: sectionStudents
                };
            })
        );

        toast.add({
            severity: 'success',
            summary: 'Sections Loaded',
            detail: `Loaded ${sections.value.length} sections for ${grade}`,
            life: 3000
        });
    } catch (error) {
        console.error('Error fetching sections:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load sections. Please try again.',
            life: 3000
        });
    }
};

const addStudent = async () => {
    try {
        if (selectedStudent.value) {
            // Update existing student
            const currentSection = sections.value.find((s) => s.students.some((st) => st.id === selectedStudent.value.id));

            if (!currentSection) {
                throw new Error('Cannot find section for student');
            }

            // Use the SubjectService to update status
            await SubjectService.updateStudentStatus(currentSection.id, selectedStudent.value.id, newStudent.value.status, newStudent.value.remarks);

            // Also use AttendanceService to record attendance
            await AttendanceService.recordAttendance(selectedStudent.value.id, {
                date: new Date().toISOString().split('T')[0],
                status: newStudent.value.status,
                time: new Date().toLocaleTimeString(),
                remarks: newStudent.value.remarks
            });

            // Update local state
            Object.assign(selectedStudent.value, newStudent.value);
            toast.add({
                severity: 'success',
                summary: 'Student Updated',
                detail: 'Student attendance has been updated.',
                life: 3000
            });
        } else {
            // For adding a new student, we would normally call the backend
            // For now, just update local state
            sections.value[0].students.push({ ...newStudent.value });
            toast.add({
                severity: 'success',
                summary: 'Student Added',
                detail: 'New student has been added.',
                life: 3000
            });
        }

        studentDialog.value = false;
        selectedStudent.value = null;
    } catch (error) {
        console.error('Error updating student:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update student. Please try again.',
            life: 3000
        });
    }
};

const editStudent = (student) => {
    newStudent.value = { ...student };
    selectedStudent.value = student;
    studentDialog.value = true;
};

const updateAllDates = async (newDate) => {
    try {
        // Update date for all sections
        const updatePromises = sections.value.map((section) => SubjectService.updateSectionDate(section.id, newDate));

        await Promise.all(updatePromises);

        // Update local state
        sections.value.forEach((section) => {
            section.date = newDate;
        });

        toast.add({
            severity: 'success',
            summary: 'Date Updated',
            detail: `All dates set to ${newDate}`,
            life: 3000
        });
    } catch (error) {
        console.error('Error updating dates:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update dates. Please try again.',
            life: 3000
        });
    }
};

const deleteStudent = async (studentId) => {
    try {
        // In a real app, we would call an API to delete the student
        // For now, just update the local state
        sections.value.forEach((section) => {
            section.students = section.students.filter((student) => student.id !== studentId);
        });

        toast.add({
            severity: 'success',
            summary: 'Student Removed',
            detail: 'Student has been removed from the section.',
            life: 3000
        });
    } catch (error) {
        console.error('Error deleting student:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete student. Please try again.',
            life: 3000
        });
    }
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

// Enhanced initialization - load both student and section data
onMounted(async () => {
    try {
        // Fetch all students for reference
        const studentsData = await AttendanceService.getData();
        students.value = studentsData;

        console.log('Loaded student data:', students.value.length, 'students');
    } catch (error) {
        console.error('Error loading student data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load student data. Please try again.',
            life: 3000
        });
    }
});
</script>

<template>
    <!-- Show grade selection if no grade is selected -->
    <div v-if="!selectedGrade" class="card-container">
        <Sakai-card v-for="(grade, index) in grades" :key="index" class="custom-card" :style="cardStyles[index]" @click="showSections(grade.grade)">
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
                                    <Button icon="pi pi-pencil" class="p-button-warning mr-2" @click="editStudent(student.data)" title="Edit" />
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
    max-width: 900px;
    /* Set a max width to limit stretching */
    margin: 0 auto;
    /* Center the grid */
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
