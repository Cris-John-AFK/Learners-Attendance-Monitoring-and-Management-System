<script setup>
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import TabPanel from 'primevue/tabpanel';
import TabView from 'primevue/tabview';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const toast = useToast();
const search = ref('');
const loading = ref(true);
const enrollmentDialog = ref(false);
const confirmationDialog = ref(false);
const selectedStudent = ref(null);
const showStudentDetails = ref(false);
const activeStudentTab = ref(0);
const selectedGradeLevel = ref(null);
const selectedSection = ref(null);
const activeTabIndex = ref(0); // Add active tab index for controlling which tab is active

// Initialize with empty array, will be loaded from localStorage
const students = ref([]);

// Grade levels and sections
const gradeLevels = [
    { name: 'Kinder', code: 'K' },
    { name: 'Grade 1', code: '1' },
    { name: 'Grade 2', code: '2' },
    { name: 'Grade 3', code: '3' },
    { name: 'Grade 4', code: '4' },
    { name: 'Grade 5', code: '5' },
    { name: 'Grade 6', code: '6' }
];

const sections = {
    K: [
        { name: 'Joy', code: 'JOY' },
        { name: 'Hope', code: 'HOPE' },
        { name: 'Love', code: 'LOVE' }
    ],
    1: [
        { name: 'Wisdom', code: 'WIS' },
        { name: 'Faith', code: 'FAI' },
        { name: 'Charity', code: 'CHA' }
    ],
    2: [
        { name: 'Wisdom', code: 'WIS' },
        { name: 'Faith', code: 'FAI' },
        { name: 'Charity', code: 'CHA' }
    ],
    3: [
        { name: 'Wisdom', code: 'WIS' },
        { name: 'Faith', code: 'FAI' },
        { name: 'Charity', code: 'CHA' }
    ],
    4: [
        { name: 'Wisdom', code: 'WIS' },
        { name: 'Faith', code: 'FAI' },
        { name: 'Charity', code: 'CHA' }
    ],
    5: [
        { name: 'Wisdom', code: 'WIS' },
        { name: 'Faith', code: 'FAI' },
        { name: 'Charity', code: 'CHA' }
    ],
    6: [
        { name: 'Wisdom', code: 'WIS' },
        { name: 'Faith', code: 'FAI' },
        { name: 'Charity', code: 'CHA' }
    ]
};

// Subjects per grade level (adjusted for elementary)
const subjects = {
    K: ['Alphabet Recognition', 'Number Recognition', 'Basic Reading', 'Writing Readiness', 'Arts', 'Music', 'Physical Movement', 'Values Education'],
    1: ['English 1', 'Filipino 1', 'Mathematics 1', 'Science 1', 'Araling Panlipunan 1', 'MAPEH 1', 'Mother Tongue 1', 'Edukasyon sa Pagpapakatao 1'],
    2: ['English 2', 'Filipino 2', 'Mathematics 2', 'Science 2', 'Araling Panlipunan 2', 'MAPEH 2', 'Mother Tongue 2', 'Edukasyon sa Pagpapakatao 2'],
    3: ['English 3', 'Filipino 3', 'Mathematics 3', 'Science 3', 'Araling Panlipunan 3', 'MAPEH 3', 'Mother Tongue 3', 'Edukasyon sa Pagpapakatao 3'],
    4: ['English 4', 'Filipino 4', 'Mathematics 4', 'Science 4', 'Araling Panlipunan 4', 'MAPEH 4', 'Edukasyon sa Pagpapakatao 4'],
    5: ['English 5', 'Filipino 5', 'Mathematics 5', 'Science 5', 'Araling Panlipunan 5', 'MAPEH 5', 'Edukasyon sa Pagpapakatao 5', 'EPP/TLE 5'],
    6: ['English 6', 'Filipino 6', 'Mathematics 6', 'Science 6', 'Araling Panlipunan 6', 'MAPEH 6', 'Edukasyon sa Pagpapakatao 6', 'EPP/TLE 6']
};

// Load data on component mount
onMounted(() => {
    loadAdmittedStudents();
});

// Load admitted students from localStorage
function loadAdmittedStudents() {
    loading.value = true;
    try {
        // Get admitted students from localStorage
        const admittedStudents = JSON.parse(localStorage.getItem('admittedApplicants') || '[]');

        // Get enrolled students from localStorage
        const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');

        // Create a Map to track unique students by ID or name
        const uniqueStudentsMap = new Map();

        // Process admitted students and keep only unique ones
        admittedStudents.forEach((student) => {
            // Create a unique key using studentId or name
            const uniqueKey = student.studentId || `${student.firstName}-${student.lastName}`;

            // Only add if not already in the map
            if (!uniqueStudentsMap.has(uniqueKey)) {
                uniqueStudentsMap.set(uniqueKey, student);
            }
        });

        // Convert map values to array and format for display
        const formattedStudents = Array.from(uniqueStudentsMap.values()).map((student, index) => {
            // Check if student is already enrolled
            const enrolledStudent = enrolledStudents.find((es) => es.studentId === student.studentId || (es.firstName === student.firstName && es.lastName === student.lastName));

            return {
                id: index + 1,
                studentId: student.studentId || `STU${String(index + 1).padStart(5, '0')}`,
                name: student.name || `${student.firstName} ${student.lastName}`,
                firstName: student.firstName,
                lastName: student.lastName,
                email: student.email || 'N/A',
                birthdate: student.birthdate ? new Date(student.birthdate).toLocaleDateString() : 'N/A',
                address: formatAddress(student),
                contact: student.contact || 'N/A',
                photo: student.photo || `https://randomuser.me/api/portraits/${student.sex === 'Female' ? 'women' : 'men'}/${index + 1}.jpg`,
                status: 'Admitted',
                enrollmentStatus: enrolledStudent ? 'Enrolled' : 'Not Enrolled',
                gradeLevel: enrolledStudent?.gradeLevel || '',
                section: enrolledStudent?.section || '',
                enrollmentDate: enrolledStudent?.enrollmentDate || '',
                // Store original data for reference
                originalData: student
            };
        });

        students.value = formattedStudents;
    } catch (error) {
        console.error('Error loading admitted students:', error);
        toast.add({
            severity: 'error',
            summary: 'Error Loading Data',
            detail: 'Failed to load student data.',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
}

// Format address for display
function formatAddress(student) {
    if (student.currentAddress) {
        const addr = student.currentAddress;
        const parts = [addr.houseNo, addr.street, addr.barangay, addr.city, addr.province].filter((part) => part);
        return parts.join(', ') || 'N/A';
    }
    return student.address || 'N/A';
}

// Computed properties
const filteredStudents = computed(() => {
    if (!search.value) return students.value;
    const searchTerm = search.value.toLowerCase();
    return students.value.filter((s) => s.name?.toLowerCase().includes(searchTerm) || s.studentId?.toLowerCase().includes(searchTerm) || s.firstName?.toLowerCase().includes(searchTerm) || s.lastName?.toLowerCase().includes(searchTerm));
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
    return students.value.filter((s) => s.enrollmentStatus === 'Enrolled');
});

const notEnrolledStudents = computed(() => {
    return students.value.filter((s) => s.enrollmentStatus === 'Not Enrolled');
});

// Methods
function selectStudent(student) {
    selectedStudent.value = student;
}

function openStudentModal(student) {
    selectedStudent.value = student;
    showStudentDetails.value = true;
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

    // Close the student details modal first
    showStudentDetails.value = false;
    
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
    try {
        // Update student record in the UI
        selectedStudent.value.enrollmentStatus = 'Enrolled';
        selectedStudent.value.gradeLevel = selectedGradeLevel.value.name;
        selectedStudent.value.section = selectedSection.value.name;
        selectedStudent.value.enrollmentDate = new Date().toISOString().split('T')[0]; // Current date in YYYY-MM-DD format

        // Update in localStorage
        updateEnrollmentInStorage(selectedStudent.value);

        // Switch to Enrolled tab
        activeTabIndex.value = 1;

        // Close dialog and show success message
        confirmationDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Enrollment Successful',
            detail: `${selectedStudent.value.name} has been enrolled in ${selectedGradeLevel.value.name} - ${selectedSection.value.name}.`,
            life: 3000
        });

        // Ask if user wants to view the student in Student page
        setTimeout(() => {
            toast.add({
                severity: 'info',
                summary: 'Student Added',
                detail: `The student is now available in the Student Management page.`,
                life: 5000
            });

            // Navigate to Student page after a brief delay
            setTimeout(() => {
                window.location.href = '#/admin/students';
            }, 2000);
        }, 1000);
    } catch (error) {
        console.error('Error enrolling student:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to enroll student.',
            life: 3000
        });
    }
}

function updateEnrollmentInStorage(student) {
    // Get current data from localStorage
    const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');

    // Create enrollment record
    const enrollmentRecord = {
        ...student.originalData,
        enrollmentStatus: 'Enrolled',
        gradeLevel: student.gradeLevel,
        section: student.section,
        enrollmentDate: student.enrollmentDate
    };

    // Add to enrolled students
    enrolledStudents.push(enrollmentRecord);

    // Save back to localStorage
    localStorage.setItem('enrolledStudents', JSON.stringify(enrolledStudents));
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
                        <h5 class="m-0">Student List</h5>
                        <div class="p-input-icon-left w-full md:w-10rem">
                            <i class="pi pi-search" />
                            <InputText v-model="search" placeholder="Search students..." class="w-full" />
                        </div>
                    </div>

                    <!-- Not Enrolled Students -->
                    <TabView v-model:activeIndex="activeTabIndex">
                        <TabPanel header="Not Enrolled">
                            <div class="p-2">
                                <p v-if="notEnrolledStudents.length === 0" class="text-center text-color-secondary p-3">No students to enroll</p>
                                <div v-else class="student-list">
                                    <div v-for="student in notEnrolledStudents" :key="student.id" class="student-item p-3 mb-2" :class="{ selected: selectedStudent && selectedStudent.id === student.id }" @click="openStudentModal(student)">
                                        <div class="flex align-items-center">
                                            <Avatar :image="student.photo" shape="circle" size="large" class="mr-2" />
                                            <div class="flex-1">
                                                <div class="flex align-items-center justify-content-between">
                                                    <span class="font-medium">{{ student.name }}</span>
                                                    <Tag value="Not Enrolled" severity="warning" />
                                                </div>
                                                <div class="text-color-secondary text-sm mt-1">{{ student.studentId }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="enrollment-stats text-sm text-color-secondary text-center mt-3">Total: {{ notEnrolledStudents.length }} not enrolled students</div>
                                </div>
                            </div>
                        </TabPanel>
                    </TabView>
                </div>
            </div>

        </div>

        <!-- Enrollment Dialog -->
        <Dialog v-model:visible="enrollmentDialog" header="Enroll Student" :style="{ width: '450px', zIndex: '1100' }" :modal="true">
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
        <Dialog v-model:visible="confirmationDialog" header="Confirm Enrollment" :style="{ width: '500px', zIndex: '1200' }" :modal="true">
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
                <Button
                    label="Back"
                    icon="pi pi-arrow-left"
                    class="p-button-text"
                    @click="
                        confirmationDialog = false;
                        enrollmentDialog = true;
                    "
                />
                <Button label="Confirm Enrollment" icon="pi pi-check" class="p-button-success" @click="confirmEnrollment" />
            </template>
        </Dialog>

        <!-- Student Details Modal -->
        <Dialog v-model:visible="showStudentDetails" :modal="true" :dismissableMask="true" :style="{ width: '50vw' }" :breakpoints="{ '960px': '75vw', '641px': '90vw' }" class="student-details-dialog p-0" :showHeader="false">
            <div class="card-container p-0">
                <!-- Header with gradient background -->
                <div class="student-header">
                    <div class="flex align-items-center">
                        <Avatar :image="selectedStudent?.photo" shape="circle" size="xlarge" class="mr-3" />
                        <div>
                            <h3 class="m-0 text-white">{{ selectedStudent?.name }}</h3>
                            <p class="m-0 text-white-alpha-70"><i class="pi pi-id-card mr-1"></i>{{ selectedStudent?.studentId }}</p>
                        </div>
                    </div>
                </div>

                <!-- Tab navigation -->
                <div class="tab-navigation">
                    <div class="tab-item" :class="{ 'active-tab': activeStudentTab === 0 }" @click="activeStudentTab = 0">
                        <i class="pi pi-user"></i>
                        <span>Personal Information</span>
                    </div>
                    <div class="tab-item" :class="{ 'active-tab': activeStudentTab === 1 }" @click="activeStudentTab = 1">
                        <i class="pi pi-graduation-cap"></i>
                        <span>Enrollment Information</span>
                    </div>
                </div>

                <!-- Tab content -->
                <div class="tab-content p-3">
                    <!-- Personal Information Tab -->
                    <div v-if="activeStudentTab === 0">
                        <div class="info-list">
                            <div class="info-item">
                                <div class="info-label">Birthdate</div>
                                <div class="info-value">{{ selectedStudent?.birthdate }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact Number</div>
                                <div class="info-value">{{ selectedStudent?.contact }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Address</div>
                                <div class="info-value">{{ selectedStudent?.address }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value">{{ selectedStudent?.email }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Enrollment Information Tab -->
                    <div v-if="activeStudentTab === 1">
                        <div v-if="selectedStudent?.enrollmentStatus === 'Enrolled'" class="status-card bg-green-50">
                            <div class="flex align-items-center">
                                <i class="pi pi-check-circle text-green-500 text-2xl mr-3"></i>
                                <div>
                                    <h5 class="m-0 text-green-700">Student Enrolled</h5>
                                    <p class="m-0">Enrollment Date: <strong>{{ selectedStudent?.enrollmentDate }}</strong></p>
                                    <p class="m-0 mt-1">Grade: <strong>{{ selectedStudent?.gradeLevel }}</strong> | Section: <strong>{{ selectedStudent?.section }}</strong></p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="status-card bg-blue-50">
                            <div class="flex align-items-center">
                                <i class="pi pi-info-circle text-blue-500 text-2xl mr-3"></i>
                                <div>
                                    <h5 class="m-0 text-blue-700">Not Yet Enrolled</h5>
                                    <p class="m-0">This student has been admitted but is not yet enrolled in any class.</p>
                                    
                                    <div class="mt-3">
                                        <Button label="Enroll Now" icon="pi pi-user-plus" class="p-button-success" @click="openEnrollmentDialog" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Close button at the bottom -->
                <div class="p-3 flex justify-content-end">
                    <Button icon="pi pi-times" label="Close" class="p-button-rounded p-button-secondary" @click="showStudentDetails = false" />
                </div>
            </div>
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

/* Student Details Dialog Styling */
.student-details-dialog :deep(.p-dialog-content) {
    padding: 0 !important;
    border-radius: 8px;
    overflow: hidden;
}

.card-container {
    background: var(--surface-card);
    border-radius: 8px;
    overflow: hidden;
    position: relative;
}

.student-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, #4a90e2 0%, #5e35b1 100%);
    color: white;
}

.tab-navigation {
    display: flex;
    background-color: var(--surface-50);
    border-bottom: 1px solid var(--surface-200);
}

.tab-item {
    padding: 1rem;
    flex: 1;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.tab-item:hover {
    background-color: var(--surface-100);
}

.active-tab {
    background-color: white;
    border-bottom: 3px solid var(--primary-color);
    font-weight: 600;
    color: var(--primary-color);
}

.tab-content {
    padding: 1.5rem;
    min-height: 300px;
}

.info-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem;
    border-bottom: 1px solid var(--surface-200);
}

.info-label {
    font-weight: 600;
    color: var(--text-color-secondary);
}

.info-value {
    color: var(--text-color);
}

.status-card {
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 1rem;
}

.bg-blue-50 {
    background-color: #eff6ff;
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
