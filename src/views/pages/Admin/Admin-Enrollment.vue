<script setup>
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import TabPanel from 'primevue/tabpanel';
import TabView from 'primevue/tabview';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const toast = useToast();
const search = ref('');
const enrollmentDialog = ref(false);
const confirmationDialog = ref(false);
const selectedStudent = ref(null);
const selectedGradeLevel = ref(null);
const selectedSection = ref(null);

// Mock data for admitted students ready for enrollment
const students = ref([
    {
        id: 1,
        studentId: 'STU00001',
        name: 'Juan Dela Cruz',
        email: 'juan@email.com',
        birthdate: '2007-05-20',
        address: 'Brgy. Example, City',
        contact: '09123456789',
        photo: 'https://randomuser.me/api/portraits/men/1.jpg',
        status: 'Admitted',
        enrollmentStatus: 'Not Enrolled'
    },
    {
        id: 2,
        studentId: 'STU00002',
        name: 'Maria Santos',
        email: 'maria@email.com',
        birthdate: '2008-08-15',
        address: 'Purok 2, Another City',
        contact: '09987654321',
        photo: 'https://randomuser.me/api/portraits/women/2.jpg',
        status: 'Admitted',
        enrollmentStatus: 'Not Enrolled'
    },
    {
        id: 3,
        studentId: 'STU00003',
        name: 'Pedro Reyes',
        email: 'pedro@email.com',
        birthdate: '2006-11-03',
        address: 'Sitio Mabuhay, Barangay Matahimik',
        contact: '09765432109',
        photo: 'https://randomuser.me/api/portraits/men/3.jpg',
        status: 'Admitted',
        enrollmentStatus: 'Enrolled',
        gradeLevel: 'Grade 8',
        section: 'Rizal',
        enrollmentDate: '2025-04-15'
    }
]);

// Grade levels and sections
const gradeLevels = [
    { name: 'Grade 7', code: 'G7' },
    { name: 'Grade 8', code: 'G8' },
    { name: 'Grade 9', code: 'G9' },
    { name: 'Grade 10', code: 'G10' },
    { name: 'Grade 11', code: 'G11' },
    { name: 'Grade 12', code: 'G12' }
];

const sections = {
    G7: [{ name: 'Rizal', code: 'RIZ' }, { name: 'Bonifacio', code: 'BON' }, { name: 'Mabini', code: 'MAB' }],
    G8: [{ name: 'Rizal', code: 'RIZ' }, { name: 'Bonifacio', code: 'BON' }, { name: 'Mabini', code: 'MAB' }],
    G9: [{ name: 'Rizal', code: 'RIZ' }, { name: 'Bonifacio', code: 'BON' }, { name: 'Mabini', code: 'MAB' }],
    G10: [{ name: 'Rizal', code: 'RIZ' }, { name: 'Bonifacio', code: 'BON' }, { name: 'Mabini', code: 'MAB' }],
    G11: [{ name: 'STEM', code: 'STEM' }, { name: 'ABM', code: 'ABM' }, { name: 'HUMSS', code: 'HUMSS' }],
    G12: [{ name: 'STEM', code: 'STEM' }, { name: 'ABM', code: 'ABM' }, { name: 'HUMSS', code: 'HUMSS' }]
};

// Subjects per grade level (simplified for demo)
const subjects = {
    G7: ['Mathematics 7', 'Science 7', 'English 7', 'Filipino 7', 'Araling Panlipunan 7', 'MAPEH 7', 'TLE 7', 'ESP 7'],
    G8: ['Mathematics 8', 'Science 8', 'English 8', 'Filipino 8', 'Araling Panlipunan 8', 'MAPEH 8', 'TLE 8', 'ESP 8'],
    G9: ['Mathematics 9', 'Science 9', 'English 9', 'Filipino 9', 'Araling Panlipunan 9', 'MAPEH 9', 'TLE 9', 'ESP 9'],
    G10: ['Mathematics 10', 'Science 10', 'English 10', 'Filipino 10', 'Araling Panlipunan 10', 'MAPEH 10', 'TLE 10', 'ESP 10'],
    G11: {
        STEM: ['Pre-Calculus', 'Basic Calculus', 'General Biology 1', 'General Physics 1', 'General Chemistry 1', 'Research in Daily Life 1'],
        ABM: ['Business Math', 'Business Finance', 'Organization and Management', 'Principles of Marketing', 'Business Ethics', 'Fundamentals of Accountancy'],
        HUMSS: ['Creative Writing', 'Introduction to World Religions', 'Philippine Politics and Governance', 'Disciplines and Ideas in Social Sciences', 'Creative Nonfiction', 'Trends, Networks, and Critical Thinking']
    },
    G12: {
        STEM: ['General Biology 2', 'General Physics 2', 'General Chemistry 2', 'Research in Daily Life 2', 'Work Immersion/Research/Career Advocacy'],
        ABM: ['Applied Economics', 'Business Enterprise Simulation', 'Work Immersion/Research/Career Advocacy', 'Empowerment Technologies', 'Inquiries, Investigations and Immersion'],
        HUMSS: ['Humanities Research', 'Creative Writing', 'Philippine Politics and Governance', 'Community Engagement', 'Work Immersion/Research/Career Advocacy']
    }
};

// Computed properties
const filteredStudents = computed(() => {
    if (!search.value) return students.value;
    return students.value.filter((s) => 
        s.name.toLowerCase().includes(search.value.toLowerCase()) || 
        s.studentId.toLowerCase().includes(search.value.toLowerCase())
    );
});

const availableSections = computed(() => {
    if (!selectedGradeLevel.value) return [];
    return sections[selectedGradeLevel.value.code] || [];
});

const selectedSubjects = computed(() => {
    if (!selectedGradeLevel.value) return [];
    
    const gradeCode = selectedGradeLevel.value.code;
    
    // For senior high, we need to check the section (strand)
    if ((gradeCode === 'G11' || gradeCode === 'G12') && selectedSection.value) {
        return subjects[gradeCode][selectedSection.value.name] || [];
    }
    
    // For junior high, just return the subjects for that grade
    return subjects[gradeCode] || [];
});

const enrolledStudents = computed(() => {
    return students.value.filter(s => s.enrollmentStatus === 'Enrolled');
});

const notEnrolledStudents = computed(() => {
    return students.value.filter(s => s.enrollmentStatus === 'Not Enrolled');
});

// Methods
function selectStudent(student) {
    selectedStudent.value = student;
}

function openEnrollmentDialog() {
    if (!selectedStudent.value) {
        toast.add({ severity: 'warn', summary: 'No Student Selected', detail: 'Please select a student to enroll.', life: 3000 });
        return;
    }
    
    if (selectedStudent.value.enrollmentStatus === 'Enrolled') {
        toast.add({ severity: 'info', summary: 'Already Enrolled', detail: 'This student is already enrolled.', life: 3000 });
        return;
    }
    
    // Reset selection
    selectedGradeLevel.value = null;
    selectedSection.value = null;
    enrollmentDialog.value = true;
}

function proceedToConfirmation() {
    if (!selectedGradeLevel.value || !selectedSection.value) {
        toast.add({ severity: 'warn', summary: 'Incomplete Selection', detail: 'Please select both grade level and section.', life: 3000 });
        return;
    }
    
    enrollmentDialog.value = false;
    confirmationDialog.value = true;
}

function confirmEnrollment() {
    // Update student record
    selectedStudent.value.enrollmentStatus = 'Enrolled';
    selectedStudent.value.gradeLevel = selectedGradeLevel.value.name;
    selectedStudent.value.section = selectedSection.value.name;
    selectedStudent.value.enrollmentDate = new Date().toISOString().split('T')[0]; // Current date in YYYY-MM-DD format
    
    // Close dialog and show success message
    confirmationDialog.value = false;
    toast.add({ 
        severity: 'success', 
        summary: 'Enrollment Successful', 
        detail: `${selectedStudent.value.name} has been enrolled in ${selectedGradeLevel.value.name} - ${selectedSection.value.name}.`, 
        life: 3000 
    });
}
</script>

<template>
    <div class="card p-fluid">
        <div class="flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="text-2xl font-bold m-0"><i class="pi pi-users mr-2"></i>Enrollment Management</h2>
                <p class="text-color-secondary mt-1 mb-0">Enroll admitted students and manage class assignments</p>
            </div>
            <div>
                <span class="p-input-icon-left">
                    <i class="pi pi-search" />
                    <InputText v-model="search" placeholder="Search students..." class="p-inputtext-sm" />
                </span>
            </div>
        </div>

        <div class="grid">
            <!-- Student List Panel -->
            <div class="col-12 md:col-5 lg:col-4">
                <div class="card mb-0">
                    <div class="flex align-items-center justify-content-between mb-3">
                        <h5 class="m-0">Students</h5>
                        <div class="flex gap-2">
                            <Tag severity="success" value="Enrolled" :rounded="true" class="text-xs" />
                            <Tag severity="warning" value="Not Enrolled" :rounded="true" class="text-xs" />
                        </div>
                    </div>
                    
                    <div class="student-list p-2">
                        <div
                            v-for="student in filteredStudents"
                            :key="student.id"
                            @click="selectStudent(student)"
                            class="student-item p-3 border-round mb-2 flex align-items-center"
                            :class="{ 
                                'selected-student': selectedStudent && selectedStudent.id === student.id,
                                'enrolled-student': student.enrollmentStatus === 'Enrolled'
                            }"
                        >
                            <Avatar :image="student.photo" shape="circle" size="large" class="mr-3" />
                            <div class="flex-1">
                                <h6 class="m-0 mb-1">{{ student.name }}</h6>
                                <div class="text-sm text-color-secondary">{{ student.studentId }}</div>
                                <div class="text-xs mt-1">
                                    <Tag :severity="student.enrollmentStatus === 'Enrolled' ? 'success' : 'warning'" 
                                        :value="student.enrollmentStatus" 
                                        class="text-xs" />
                                </div>
                            </div>
                            <i class="pi pi-chevron-right text-color-secondary"></i>
                        </div>
                        <div v-if="filteredStudents.length === 0" class="p-4 text-center text-color-secondary">No students found</div>
                    </div>
                </div>
            </div>

            <!-- Student Details Panel -->
            <div class="col-12 md:col-7 lg:col-8">
                <div v-if="selectedStudent" class="card mb-0">
                    <div class="flex align-items-center justify-content-between mb-4">
                        <div class="flex align-items-center">
                            <Avatar :image="selectedStudent.photo" shape="circle" size="xlarge" class="mr-3" />
                            <div>
                                <h4 class="m-0">{{ selectedStudent.name }}</h4>
                                <p class="text-color-secondary m-0"><i class="pi pi-id-card mr-1"></i>{{ selectedStudent.studentId }}</p>
                            </div>
                        </div>
                        <Button v-if="selectedStudent.enrollmentStatus === 'Not Enrolled'" 
                            label="Enroll Student" 
                            icon="pi pi-user-plus" 
                            class="p-button-success" 
                            @click="openEnrollmentDialog" />
                    </div>

                    <TabView>
                        <!-- Personal Information Tab -->
                        <TabPanel header="Personal Information">
                            <div class="grid">
                                <div class="col-12 md:col-6">
                                    <div class="field">
                                        <label class="font-bold">Birthdate</label>
                                        <div>{{ selectedStudent.birthdate }}</div>
                                    </div>
                                </div>
                                <div class="col-12 md:col-6">
                                    <div class="field">
                                        <label class="font-bold">Contact Number</label>
                                        <div>{{ selectedStudent.contact }}</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="field">
                                        <label class="font-bold">Address</label>
                                        <div>{{ selectedStudent.address }}</div>
                                    </div>
                                </div>
                                <div class="col-12 md:col-6">
                                    <div class="field">
                                        <label class="font-bold">Email</label>
                                        <div>{{ selectedStudent.email }}</div>
                                    </div>
                                </div>
                            </div>
                        </TabPanel>

                        <!-- Enrollment Information Tab -->
                        <TabPanel header="Enrollment Information">
                            <div v-if="selectedStudent.enrollmentStatus === 'Enrolled'" class="card bg-green-50 border-round">
                                <div class="flex align-items-center mb-3">
                                    <i class="pi pi-check-circle text-green-500 text-2xl mr-3"></i>
                                    <div>
                                        <h5 class="m-0 text-green-700">Student Enrolled</h5>
                                        <p class="m-0 mt-1">Enrollment Date: <strong>{{ selectedStudent.enrollmentDate }}</strong></p>
                                    </div>
                                </div>
                                
                                <div class="grid">
                                    <div class="col-12 md:col-6">
                                        <div class="field">
                                            <label class="font-bold">Grade Level</label>
                                            <div>{{ selectedStudent.gradeLevel }}</div>
                                        </div>
                                    </div>
                                    <div class="col-12 md:col-6">
                                        <div class="field">
                                            <label class="font-bold">Section</label>
                                            <div>{{ selectedStudent.section }}</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <h6>Subjects</h6>
                                <ul class="subject-list">
                                    <li v-for="(subject, index) in selectedSubjects" :key="index">{{ subject }}</li>
                                </ul>
                            </div>
                            
                            <div v-else class="card border-round">
                                <div class="flex align-items-center">
                                    <i class="pi pi-info-circle text-blue-500 text-2xl mr-3"></i>
                                    <div>
                                        <h5 class="m-0 text-blue-700">Not Yet Enrolled</h5>
                                        <p class="m-0 mt-1">This student has been admitted but is not yet enrolled in any class.</p>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <Button label="Enroll Now" icon="pi pi-user-plus" class="p-button-success" @click="openEnrollmentDialog" />
                                </div>
                            </div>
                        </TabPanel>
                    </TabView>
                </div>

                <div v-else class="card flex align-items-center justify-content-center" style="min-height: 400px">
                    <div class="text-center">
                        <i class="pi pi-user text-4xl text-color-secondary mb-3"></i>
                        <h5>Select a student to view details</h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enrollment Dialog -->
        <Dialog v-model:visible="enrollmentDialog" header="Enroll Student" :style="{width: '450px'}" :modal="true">
            <div class="enrollment-form p-fluid">
                <div class="field">
                    <label for="gradeLevel">Grade Level</label>
                    <Dropdown id="gradeLevel" v-model="selectedGradeLevel" :options="gradeLevels" optionLabel="name" placeholder="Select Grade Level" class="w-full" />
                </div>
                
                <div class="field">
                    <label for="section">Section</label>
                    <Dropdown id="section" v-model="selectedSection" :options="availableSections" optionLabel="name" placeholder="Select Section" class="w-full" :disabled="!selectedGradeLevel" />
                </div>
            </div>
            
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="enrollmentDialog = false" />
                <Button label="Proceed" icon="pi pi-check" class="p-button-success" @click="proceedToConfirmation" />
            </template>
        </Dialog>
        
        <!-- Confirmation Dialog -->
        <Dialog v-model:visible="confirmationDialog" header="Confirm Enrollment" :style="{width: '500px'}" :modal="true">
            <div v-if="selectedStudent && selectedGradeLevel && selectedSection" class="confirmation-details">
                <div class="student-summary flex align-items-center mb-4">
                    <Avatar :image="selectedStudent.photo" shape="circle" size="large" class="mr-3" />
                    <div>
                        <h5 class="m-0">{{ selectedStudent.name }}</h5>
                        <p class="m-0 text-sm text-color-secondary">{{ selectedStudent.studentId }}</p>
                    </div>
                </div>
                
                <div class="enrollment-summary p-3 border-round mb-3" style="background-color: var(--surface-ground)">
                    <h6>Enrollment Details</h6>
                    <div class="grid">
                        <div class="col-6">
                            <label class="font-bold block mb-1">Grade Level:</label>
                            <span>{{ selectedGradeLevel.name }}</span>
                        </div>
                        <div class="col-6">
                            <label class="font-bold block mb-1">Section:</label>
                            <span>{{ selectedSection.name }}</span>
                        </div>
                    </div>
                </div>
                
                <div class="subject-summary">
                    <h6>Subjects to be Enrolled</h6>
                    <ul class="subject-list">
                        <li v-for="(subject, index) in selectedSubjects" :key="index">{{ subject }}</li>
                    </ul>
                </div>
            </div>
            
            <template #footer>
                <Button label="Back" icon="pi pi-arrow-left" class="p-button-text" @click="confirmationDialog = false; enrollmentDialog = true" />
                <Button label="Confirm Enrollment" icon="pi pi-check" class="p-button-success" @click="confirmEnrollment" />
            </template>
        </Dialog>
        
        <Toast />
    </div>
</template>

<style scoped>
/* Student list styling */
.student-item {
    cursor: pointer;
    transition: all 0.2s;
    background-color: var(--surface-card);
    border: 1px solid var(--surface-border);
}

.student-item:hover {
    background-color: var(--surface-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
}

.selected-student {
    border-left: 4px solid var(--primary-color);
    background-color: var(--primary-50);
}

.enrolled-student {
    border-left-color: var(--green-500);
}

/* Tab panel styling */
:deep(.p-tabview-nav) {
    border-bottom: 2px solid var(--surface-border);
}

:deep(.p-tabview-nav li.p-highlight .p-tabview-nav-link) {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

:deep(.p-tabview-panels) {
    padding: 1.5rem 0;
}

/* Subject list styling */
.subject-list {
    list-style-type: none;
    padding: 0;
    margin: 0;
}

.subject-list li {
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.5rem;
    background-color: var(--surface-ground);
    border-radius: 6px;
    font-size: 0.9rem;
}

.subject-list li:last-child {
    margin-bottom: 0;
}

/* Card styling */
:deep(.card) {
    background: var(--surface-card);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow:
        0 2px 1px -1px rgba(0, 0, 0, 0.1),
        0 1px 1px 0 rgba(0, 0, 0, 0.07),
        0 1px 3px 0 rgba(0, 0, 0, 0.06);
}

/* Dialog styling */
:deep(.p-dialog-content) {
    padding: 1.5rem;
}

:deep(.p-dialog-footer) {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--surface-border);
}

/* Button styling */
:deep(.p-button) {
    border-radius: 6px;
}

/* Status colors */
.bg-green-50 {
    background-color: #f0fdf4;
}

.text-green-500 {
    color: #22c55e;
}

.text-green-700 {
    color: #15803d;
}

.text-blue-500 {
    color: #3b82f6;
}

.text-blue-700 {
    color: #1d4ed8;
}

/* Responsive adjustments */
@media screen and (max-width: 768px) {
    .grid > .col-12 {
        padding: 0.5rem;
    }

    :deep(.p-tabview-panels) {
        padding: 1rem 0;
    }
}
</style>