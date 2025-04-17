<script setup>
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const toast = useToast();
const students = ref([]);
const grades = ref([]);
const loading = ref(true);
const studentDialog = ref(false);
const deleteStudentDialog = ref(false);
const expandedRows = ref([]);
const student = ref({
    id: null,
    name: '',
    gender: 'Male',
    gradeLevel: '',
    section: '',
    email: '',
    phone: '',
    address: '',
    photo: null
});
const submitted = ref(false);
const filters = ref({
    grade: null,
    section: null,
    gender: null,
    searchTerm: ''
});
const sections = ref([]);
const totalStudents = ref(0);

// Grade levels for filtering
const gradeLevels = [
    { name: 'Grade 7', code: 'Grade 7' },
    { name: 'Grade 8', code: 'Grade 8' },
    { name: 'Grade 9', code: 'Grade 9' },
    { name: 'Grade 10', code: 'Grade 10' },
    { name: 'Grade 11', code: 'Grade 11' },
    { name: 'Grade 12', code: 'Grade 12' }
];

// Sections for each grade level
const sectionsByGrade = {
    'Grade 7': ['Rizal', 'Bonifacio', 'Mabini'],
    'Grade 8': ['Rizal', 'Bonifacio', 'Mabini'],
    'Grade 9': ['Rizal', 'Bonifacio', 'Mabini'],
    'Grade 10': ['Rizal', 'Bonifacio', 'Mabini'],
    'Grade 11': ['STEM', 'ABM', 'HUMSS'],
    'Grade 12': ['STEM', 'ABM', 'HUMSS']
};

// Load all grade levels and sections
const loadGradesAndSections = () => {
    try {
        grades.value = gradeLevels;
        
        // Set default sections based on first grade
        if (gradeLevels.length > 0) {
            sections.value = sectionsByGrade[gradeLevels[0].code] || [];
        }
    } catch (error) {
        console.error('Error loading grade data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load grade data',
            life: 3000
        });
    }
};

// Load all students from localStorage
const loadStudents = () => {
    try {
        loading.value = true;
        
        // Get enrolled students from localStorage
        const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
        
        // Format students for display
        const formattedStudents = enrolledStudents.map((student, index) => {
            return {
                id: index + 1,
                studentId: student.studentId || `STU${String(index + 1).padStart(5, '0')}`,
                name: student.name || `${student.firstName} ${student.lastName}`,
                firstName: student.firstName,
                lastName: student.lastName,
                email: student.email || 'N/A',
                gender: student.sex || 'Male',
                age: calculateAge(student.birthdate),
                birthdate: student.birthdate ? new Date(student.birthdate).toLocaleDateString() : 'N/A',
                address: formatAddress(student),
                contact: student.contact || student.mother?.contactNumber || 'N/A',
                photo: student.photo || `https://randomuser.me/api/portraits/${student.sex === 'Female' ? 'women' : 'men'}/${index + 1}.jpg`,
                gradeLevel: student.gradeLevel,
                section: student.section,
                enrollmentDate: student.enrollmentDate || new Date().toLocaleDateString(),
                // Store original data for reference
                originalData: student
            };
        });
        
        students.value = formattedStudents;
        totalStudents.value = formattedStudents.length;
    } catch (error) {
        console.error('Error loading student data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load student data',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Calculate age from birthdate
function calculateAge(birthdate) {
    if (!birthdate) return 'N/A';
    
    const birthDate = new Date(birthdate);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    return age;
}

// Format address for display
function formatAddress(student) {
    if (student.currentAddress) {
        const addr = student.currentAddress;
        const parts = [addr.houseNo, addr.street, addr.barangay, addr.city, addr.province].filter(part => part);
        return parts.join(', ') || 'N/A';
    }
    return student.address || 'N/A';
}

// Update sections when grade changes
function updateSections() {
    if (filters.value.grade) {
        sections.value = sectionsByGrade[filters.value.grade] || [];
        filters.value.section = null; // Reset section when grade changes
    } else {
        sections.value = [];
    }
}

// Now the computed property will work properly with the import
const filteredStudents = computed(() => {
    return students.value.filter((student) => {
        // Apply grade filter
        if (filters.value.grade && student.gradeLevel !== filters.value.grade) {
            return false;
        }

        // Apply section filter
        if (filters.value.section && student.section !== filters.value.section) {
            return false;
        }

        // Apply gender filter
        if (filters.value.gender && student.gender !== filters.value.gender) {
            return false;
        }

        // Apply search term
        if (filters.value.searchTerm) {
            const term = filters.value.searchTerm.toLowerCase();
            return student.name.toLowerCase().includes(term) || 
                   student.studentId.toString().includes(term) ||
                   (student.firstName && student.firstName.toLowerCase().includes(term)) ||
                   (student.lastName && student.lastName.toLowerCase().includes(term));
        }

        return true;
    });
});

// Save student
const saveStudent = () => {
    submitted.value = true;

    if (!student.value.name.trim()) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Please enter a name',
            life: 3000
        });
        return;
    }

    try {
        // Get current students from localStorage
        const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
        
        if (student.value.id) {
            // Update existing student
            const index = enrolledStudents.findIndex(s => s.id === student.value.id);
            if (index !== -1) {
                enrolledStudents[index] = {
                    ...enrolledStudents[index],
                    ...student.value
                };
            }
        } else {
            // Create new student
            const newStudent = {
                ...student.value,
                id: enrolledStudents.length + 1,
                studentId: `STU${String(enrolledStudents.length + 1).padStart(5, '0')}`,
                enrollmentDate: new Date().toISOString().split('T')[0],
                status: 'Enrolled'
            };
            enrolledStudents.push(newStudent);
        }
        
        // Save back to localStorage
        localStorage.setItem('enrolledStudents', JSON.stringify(enrolledStudents));
        
        // Reload students
        loadStudents();
        
        studentDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: student.value.id ? 'Student Updated' : 'Student Created',
            life: 3000
        });
    } catch (error) {
        console.error('Error saving student:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save student',
            life: 3000
        });
    }
};

// Edit student
function editStudent(studentData) {
    student.value = { ...studentData };
    studentDialog.value = true;
}

// Confirm delete student
function confirmDeleteStudent(studentData) {
    student.value = { ...studentData };
    deleteStudentDialog.value = true;
}

// Delete student
const deleteStudent = () => {
    try {
        // Get current students from localStorage
        const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
        
        // Filter out the student to delete
        const updatedStudents = enrolledStudents.filter(s => s.id !== student.value.id);
        
        // Save back to localStorage
        localStorage.setItem('enrolledStudents', JSON.stringify(updatedStudents));
        
        // Reload students
        loadStudents();
        
        deleteStudentDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Student Deleted',
            life: 3000
        });
    } catch (error) {
        console.error('Error deleting student:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete student',
            life: 3000
        });
    }
};

// Initialize component
onMounted(() => {
    loadGradesAndSections();
    loadStudents();
});
</script>

<template>
    <div class="card p-6 shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h2 class="text-2xl font-semibold mb-1"><i class="pi pi-users mr-2"></i>Student Management</h2>
                <p class="text-color-secondary">Total Students: <span class="font-bold">{{ totalStudents }}</span></p>
            </div>
            <div class="flex gap-2">
                <span class="p-input-icon-left w-full md:w-20rem">
                    <i class="pi pi-search" />
                    <InputText v-model="filters.searchTerm" placeholder="Search students..." class="w-full" />
                </span>
                <Button label="Add Student" icon="pi pi-plus" class="p-button-success" @click="studentDialog = true" />
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1">Grade Level</label>
                <Dropdown v-model="filters.grade" :options="grades" optionLabel="name" optionValue="code" 
                    placeholder="Select Grade" class="w-full" @change="updateSections" />
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1">Section</label>
                <Dropdown v-model="filters.section" :options="sections" placeholder="Select Section" 
                    class="w-full" :disabled="!filters.grade" />
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1">Gender</label>
                <Dropdown v-model="filters.gender" :options="[{name: 'Male', value: 'Male'}, {name: 'Female', value: 'Female'}]" 
                    optionLabel="name" optionValue="value" placeholder="Select Gender" class="w-full" />
            </div>
        </div>

        <!-- Student List -->
        <div class="grid">
            <div class="col-12">
                <DataTable v-model:expandedRows="expandedRows" :value="filteredStudents" dataKey="id" 
                    class="p-datatable-sm" :loading="loading" stripedRows 
                    responsiveLayout="scroll" :paginator="filteredStudents.length > 10" :rows="10">
                    <Column expander style="width: 3rem" />
                    <Column header="Student" style="min-width: 200px">
                        <template #body="slotProps">
                            <div class="flex align-items-center">
                                <Avatar :image="slotProps.data.photo" shape="circle" size="large" class="mr-2" />
                                <div>
                                    <div class="font-bold">{{ slotProps.data.name }}</div>
                                    <div class="text-sm text-color-secondary">{{ slotProps.data.studentId }}</div>
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column field="gradeLevel" header="Grade" sortable style="width: 120px" />
                    <Column field="section" header="Section" sortable style="width: 120px" />
                    <Column field="age" header="Age" sortable style="width: 80px">
                        <template #body="slotProps">
                            <span>{{ slotProps.data.age }}</span>
                        </template>
                    </Column>
                    <Column header="Gender" sortable style="width: 100px">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.gender" :severity="slotProps.data.gender === 'Male' ? 'info' : 'success'" />
                        </template>
                    </Column>
                    <Column field="email" header="Email" sortable style="min-width: 200px" />
                    <Column field="contact" header="Contact" style="width: 130px" />
                    <Column header="Actions" style="width: 8rem">
                        <template #body="slotProps">
                            <div class="flex gap-1">
                                <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click="editStudent(slotProps.data)" />
                                <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click="confirmDeleteStudent(slotProps.data)" />
                            </div>
                        </template>
                    </Column>
                    <template #expansion="slotProps">
                        <div class="p-4 surface-hover border-round-bottom">
                            <h5 class="mb-3">Student Details</h5>
                            <div class="grid">
                                <div class="col-12 md:col-6 lg:col-4">
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Full Name</div>
                                        <div>{{ slotProps.data.name }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Student ID</div>
                                        <div>{{ slotProps.data.studentId }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Birthdate</div>
                                        <div>{{ slotProps.data.birthdate }}</div>
                                    </div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4">
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Email</div>
                                        <div>{{ slotProps.data.email }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Contact</div>
                                        <div>{{ slotProps.data.contact }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Address</div>
                                        <div>{{ slotProps.data.address }}</div>
                                    </div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4">
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Grade & Section</div>
                                        <div>{{ slotProps.data.gradeLevel }} - {{ slotProps.data.section }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Enrollment Date</div>
                                        <div>{{ slotProps.data.enrollmentDate }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    
                    <!-- Empty state -->
                    <template #empty>
                        <div class="p-4 text-center">
                            <i class="pi pi-search text-4xl text-color-secondary mb-3"></i>
                            <p>No students found. Try adjusting your filters or add a new student.</p>
                        </div>
                    </template>
                    
                    <!-- Loading state -->
                    <template #loading>
                        <div class="p-4 text-center">
                            <i class="pi pi-spin pi-spinner text-4xl text-color-secondary mb-3"></i>
                            <p>Loading student data...</p>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Student Dialog -->
        <Dialog v-model:visible="studentDialog" modal header="Student Details" :style="{ width: '500px' }">
            <div class="p-4 space-y-4 left-2">
                <div>
                    <label for="name" class="block text-gray-700 font-medium">Student Name</label>
                    <InputText id="name" v-model="student.name" placeholder="Enter Student Name" class="w-full" />
                </div>
                <div>
                    <label for="gradeLevel" class="block font-medium">Grade Level</label>
                    <Dropdown id="gradeLevel" v-model="student.gradeLevel" :options="[0, 1, 2, 3, 4, 5, 6]" optionLabel="label" optionValue="value" placeholder="Select Grade Level" class="w-full" />
                </div>
                <div>
                    <label for="section" class="block font-medium">Section</label>
                    <Dropdown id="section" v-model="student.section" :options="['A', 'B', 'C', 'D', 'E']" placeholder="Select Section" class="w-full" />
                </div>
                <div>
                    <label for="gender" class="block font-medium">Gender</label>
                    <Dropdown id="gender" v-model="student.gender" :options="['Male', 'Female']" placeholder="Select Gender" class="w-full" />
                </div>
                <div class="flex justify-end space-x-2 mt-4">
                    <Button label="Cancel" class="p-button-text" @click="studentDialog = false" />
                    <Button label="Save" icon="pi pi-check" class="p-button-success" @click="saveStudent" />
                </div>
            </div>
        </Dialog>

        <!-- Delete Confirmation -->
        <Dialog v-model:visible="deleteStudentDialog" modal header="Confirm Deletion" :style="{ width: '400px' }">
            <div class="p-4">
                <p>Are you sure you want to delete this student?</p>
                <div class="flex justify-end space-x-2 mt-4">
                    <Button label="No" class="p-button-text" @click="deleteStudentDialog = false" />
                    <Button label="Yes" icon="pi pi-trash" class="p-button-danger" @click="deleteStudent" />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
:deep(.p-datatable-striped .p-datatable-tbody > tr:nth-child(even)) {
    background-color: #f9fafb;
}

:deep(.p-datatable-thead th) {
    background-color: #e5e7eb;
    font-weight: bold;
}

:deep(.p-dialog) {
    border-radius: 12px;
}

:deep(.p-button-success) {
    background-color: #22c55e;
    border: none;
}

:deep(.p-button-success:hover) {
    background-color: #16a34a;
}

:deep(.p-dialog) {
    border-radius: 12px;
}

:deep(.p-inputtext-lg) {
    padding: 0.75rem;
    border-radius: 8px;
}

:deep(.p-button-success) {
    background-color: #22c55e;
    border: none;
}

:deep(.p-button-success:hover) {
    background-color: #16a34a;
}
</style>
