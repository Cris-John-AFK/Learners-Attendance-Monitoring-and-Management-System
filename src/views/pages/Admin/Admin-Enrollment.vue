<script setup>
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import RadioButton from 'primevue/radiobutton';
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

// New enrollment form dialog
const newStudentDialog = ref(false);
const currentStep = ref(1);
const totalSteps = ref(2);
const termsAccepted = ref(false);

// New student form data with comprehensive structure
const newStudent = ref({
    // Student Type
    studentType: 'New',

    // School Information
    schoolYear: '2025-2026',
    lrn: '',
    gradeLevel: '',
    serialNumber: '', // Auto-generated enrollment ID

    // Student Information
    lastName: '',
    firstName: '',
    middleName: '',
    extensionName: '',
    birthdate: null,
    age: '',
    sex: 'Male',
    religion: '',
    motherTongue: '',

    // Address Information
    houseNo: '',
    street: '',
    barangay: '',
    cityMunicipality: '',
    province: '',
    country: 'Philippines',
    zipCode: '',

    // Parent/Guardian Information
    fatherLastName: '',
    fatherFirstName: '',
    fatherMiddleName: '',
    fatherContactNumber: '',

    motherLastName: '',
    motherFirstName: '',
    motherMiddleName: '',
    motherContactNumber: '',

    // Previous School Information
    lastGradeCompleted: '',
    lastSchoolYearCompleted: '',
    lastSchoolAttended: '',

    // Contact Information
    emailAddress: '',

    // Health/Disability Information
    hasDisability: false,
    disabilities: [],

    // Household Income
    householdIncome: 'Below 10k'
});

// Initialize with empty array, will be loaded from localStorage
const students = ref([]);
const enrolledStudents = ref([]);

// Student types
const studentTypes = [
    { name: 'New', value: 'New' },
    { name: 'Old', value: 'Old' },
    { name: 'Transfer', value: 'Transfer' },
    { name: 'Balik-Aral', value: 'Balik-Aral' }
];

// Grade levels and sections - will be loaded from database
const gradeLevels = ref([
    { name: 'Kinder', code: 'K' },
    { name: 'Grade 1', code: '1' },
    { name: 'Grade 2', code: '2' },
    { name: 'Grade 3', code: '3' },
    { name: 'Grade 4', code: '4' },
    { name: 'Grade 5', code: '5' },
    { name: 'Grade 6', code: '6' }
]);

// Disability types
const disabilityTypes = ['Visual Impairment', 'Hearing Impairment', 'Physical Disability', 'Intellectual Disability', 'Learning Disability', 'Speech/Language Impairment', 'Autism Spectrum Disorder', 'Multiple Disabilities', 'Other'];

// Household income options
const incomeOptions = [
    { name: 'Below 10k', value: 'Below 10k' },
    { name: 'Between 10k-15k', value: 'Between 10k-15k' },
    { name: 'Above 15k', value: 'Above 15k' }
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
    loadGradesFromDatabase();
    generateSerialNumber();
    loadEnrolledStudents();
});

// Load grades from database
async function loadGradesFromDatabase() {
    try {
        // This would typically fetch from your grades API
        // For now, using the existing static data
        console.log('Loading grades from database...');
    } catch (error) {
        console.error('Error loading grades:', error);
    }
}

// Generate serial number for enrollment ID
function generateSerialNumber() {
    const currentYear = new Date().getFullYear();
    const randomNum = Math.floor(Math.random() * 10000)
        .toString()
        .padStart(4, '0');
    newStudent.value.serialNumber = `ENR${currentYear}${randomNum}`;
}

// Load admitted students from localStorage
async function loadAdmittedStudents() {
    loading.value = true;
    try {
        // Load admitted students from localStorage where Admission Center saves them
        const enrollmentRegistrations = JSON.parse(localStorage.getItem('enrollmentRegistrations') || '[]');
        const pendingApplicants = JSON.parse(localStorage.getItem('pendingApplicants') || '[]');

        // Combine both sources and filter for admitted students
        const allStudents = [...enrollmentRegistrations, ...pendingApplicants];
        const admittedNotEnrolledStudents = allStudents.filter((student) => student.status === 'Admitted' && (student.enrollmentStatus === 'Not Enrolled' || !student.enrollmentStatus));

        // Format students for display
        const formattedStudents = admittedNotEnrolledStudents.map((student, index) => {
            return {
                id: student.id || index + 1,
                studentId: student.studentId || student.studentid || `STU${String(student.id || index + 1).padStart(5, '0')}`,
                name: student.name || `${student.firstName || student.firstname || ''} ${student.lastName || student.lastname || ''}`.trim(),
                firstName: student.firstName || student.firstname || '',
                lastName: student.lastName || student.lastname || '',
                email: student.email || 'N/A',
                birthdate: student.birthdate ? new Date(student.birthdate).toLocaleDateString() : 'N/A',
                address: student.address || 'N/A',
                contact: student.contact || student.phone || 'N/A',
                photo: student.photo || `https://randomuser.me/api/portraits/${student.gender === 'Female' ? 'women' : 'men'}/${(index % 50) + 1}.jpg`,
                status: 'Admitted',
                enrollmentStatus: 'Not Enrolled',
                gradeLevel: student.gradelevel || '',
                section: student.section || '',
                enrollmentDate: student.enrollmentdate || '',
                originalData: student
            };
        });

        students.value = formattedStudents;
        console.log('Loaded admitted students from localStorage:', formattedStudents);
    } catch (error) {
        console.error('Error loading admitted students from localStorage:', error);
        toast.add({
            severity: 'error',
            summary: 'Loading Error',
            detail: 'Failed to load admitted students.',
            life: 3000
        });
        students.value = [];
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

const notEnrolledStudents = computed(() => {
    return students.value.filter((s) => s.enrollmentStatus === 'Not Enrolled');
});

const totalEnrolledStudents = computed(() => {
    return enrolledStudents.value.length;
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

async function confirmEnrollment() {
    try {
        // Prepare data for database update
        const updateData = {
            enrollmentstatus: 'Enrolled',
            gradelevel: selectedGradeLevel.value.code,
            section: selectedSection.value.name,
            enrollmentdate: new Date().toISOString().split('T')[0]
        };

        // Update student in database
        const response = await fetch(`http://localhost:8000/api/students/${selectedStudent.value.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            },
            body: JSON.stringify(updateData)
        });

        if (!response.ok) {
            throw new Error('Failed to enroll student in database');
        }

        // Update local state
        selectedStudent.value.enrollmentStatus = 'Enrolled';
        selectedStudent.value.gradeLevel = selectedGradeLevel.value.name;
        selectedStudent.value.section = selectedSection.value.name;
        selectedStudent.value.enrollmentDate = new Date().toISOString().split('T')[0];

        // Reload the student list to reflect changes
        await loadAdmittedStudents();

        // Close dialog and show success message
        confirmationDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Enrollment Successful',
            detail: `${selectedStudent.value.name} has been enrolled in ${selectedGradeLevel.value.name} - ${selectedSection.value.name}.`,
            life: 3000
        });

        // Navigate to Student page after enrollment
        setTimeout(() => {
            toast.add({
                severity: 'info',
                summary: 'Student Enrolled',
                detail: `The student is now available in the Student Management page.`,
                life: 5000
            });

            setTimeout(() => {
                window.location.href = '#/admin/students';
            }, 2000);
        }, 1000);
    } catch (error) {
        console.error('Error enrolling student:', error);
        toast.add({
            severity: 'error',
            summary: 'Database Error',
            detail: 'Failed to enroll student. Please try again.',
            life: 3000
        });
    }
}

// New student enrollment functions
function openNewStudentDialog() {
    // Reset form
    resetNewStudentForm();
    generateSerialNumber();
    newStudentDialog.value = true;
    currentStep.value = 1;
}

function resetNewStudentForm() {
    newStudent.value = {
        studentType: 'New',
        schoolYear: '2025-2026',
        lrn: '',
        gradeLevel: '',
        serialNumber: '',
        lastName: '',
        firstName: '',
        middleName: '',
        extensionName: '',
        birthdate: null,
        age: '',
        sex: 'Male',
        religion: '',
        motherTongue: '',
        houseNo: '',
        street: '',
        barangay: '',
        cityMunicipality: '',
        province: '',
        country: 'Philippines',
        zipCode: '',
        fatherLastName: '',
        fatherFirstName: '',
        fatherMiddleName: '',
        fatherContactNumber: '',
        motherLastName: '',
        motherFirstName: '',
        motherMiddleName: '',
        motherContactNumber: '',
        lastGradeCompleted: '',
        lastSchoolYearCompleted: '',
        lastSchoolAttended: '',
        emailAddress: '',
        hasDisability: false,
        disabilities: [],
        householdIncome: 'Below 10k'
    };
    termsAccepted.value = false;
}

// Calculate age from birthdate
function calculateAge() {
    if (newStudent.value.birthdate) {
        const today = new Date();
        const birthDate = new Date(newStudent.value.birthdate);
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }

        newStudent.value.age = age.toString();
    }
}

// Form navigation
function nextStep() {
    if (validateCurrentStep()) {
        currentStep.value++;
    }
}

function previousStep() {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
}

// Form validation
function validateCurrentStep() {
    if (currentStep.value === 1) {
        // Validate required fields for step 1
        if (!newStudent.value.firstName || !newStudent.value.lastName || !newStudent.value.gradeLevel) {
            toast.add({
                severity: 'warn',
                summary: 'Validation Error',
                detail: 'Please fill in all required fields.',
                life: 3000
            });
            return false;
        }
    }
    return true;
}

// Submit new student enrollment
async function submitNewStudent() {
    if (!termsAccepted.value) {
        toast.add({
            severity: 'warn',
            summary: 'Terms Required',
            detail: 'Please accept the terms and conditions.',
            life: 3000
        });
        return;
    }

    try {
        // Prepare student data for backend API
        const enrollmentData = {
            enrollment_id: newStudent.value.serialNumber,
            student_type: newStudent.value.studentType,
            school_year: newStudent.value.schoolYear,
            lrn: newStudent.value.lrn,
            grade_level: newStudent.value.gradeLevel,
            
            // Student Information
            last_name: newStudent.value.lastName,
            first_name: newStudent.value.firstName,
            middle_name: newStudent.value.middleName,
            extension_name: newStudent.value.extensionName,
            birthdate: newStudent.value.birthdate ? new Date(newStudent.value.birthdate).toISOString().split('T')[0] : null,
            age: parseInt(newStudent.value.age) || null,
            sex: newStudent.value.sex,
            religion: newStudent.value.religion,
            mother_tongue: newStudent.value.motherTongue,
            
            // Address Information
            house_no: newStudent.value.houseNo,
            street: newStudent.value.street,
            barangay: newStudent.value.barangay,
            city_municipality: newStudent.value.cityMunicipality,
            province: newStudent.value.province,
            country: newStudent.value.country,
            zip_code: newStudent.value.zipCode,
            
            // Parent/Guardian Information
            father_last_name: newStudent.value.fatherLastName,
            father_first_name: newStudent.value.fatherFirstName,
            father_middle_name: newStudent.value.fatherMiddleName,
            father_contact_number: newStudent.value.fatherContactNumber,
            mother_last_name: newStudent.value.motherLastName,
            mother_first_name: newStudent.value.motherFirstName,
            mother_middle_name: newStudent.value.motherMiddleName,
            mother_contact_number: newStudent.value.motherContactNumber,
            
            // Previous School Information
            last_grade_completed: newStudent.value.lastGradeCompleted,
            last_school_year_completed: newStudent.value.lastSchoolYearCompleted,
            last_school_attended: newStudent.value.lastSchoolAttended,
            
            // Contact Information
            email_address: newStudent.value.emailAddress,
            
            // Health/Disability Information
            has_disability: newStudent.value.hasDisability,
            disabilities: newStudent.value.disabilities.join(','),
            
            // Household Income
            household_income: newStudent.value.householdIncome,
            
            // Status fields
            enrollment_status: 'Enrolled',
            enrollment_date: new Date().toISOString().split('T')[0],
            is_active: true
        };

        // Call backend API to save enrollment
        const response = await fetch('http://127.0.0.1:8000/api/enrollments', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(enrollmentData)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Failed to enroll student');
        }

        const savedStudent = await response.json();

        // Add to enrolled students list for immediate UI update
        const studentForDisplay = {
            id: savedStudent.data.id,
            studentId: savedStudent.data.enrollment_id,
            name: `${newStudent.value.firstName} ${newStudent.value.middleName} ${newStudent.value.lastName}`.trim(),
            firstName: newStudent.value.firstName,
            lastName: newStudent.value.lastName,
            email: newStudent.value.emailAddress || 'N/A',
            birthdate: newStudent.value.birthdate ? new Date(newStudent.value.birthdate).toLocaleDateString() : 'N/A',
            gradeLevel: newStudent.value.gradeLevel,
            enrollmentStatus: 'Enrolled',
            enrollmentDate: new Date().toISOString().split('T')[0],
            status: 'Active'
        };
        
        enrolledStudents.value.push(studentForDisplay);

        // Close dialog and show success
        newStudentDialog.value = false;

        toast.add({
            severity: 'success',
            summary: 'Enrollment Successful',
            detail: `${studentForDisplay.name} has been successfully enrolled and saved to database!`,
            life: 5000
        });

        // Reload data
        await loadAdmittedStudents();
        await loadEnrolledStudents();
    } catch (error) {
        console.error('Error enrolling student:', error);
        toast.add({
            severity: 'error',
            summary: 'Enrollment Error',
            detail: error.message || 'Failed to enroll student. Please try again.',
            life: 3000
        });
    }
}

// Load enrolled students for display
async function loadEnrolledStudents() {
    try {
        // Fetch enrolled students from backend API
        const response = await fetch('http://127.0.0.1:8000/api/enrollments', {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (response.ok) {
            const result = await response.json();
            const apiStudents = result.data || [];
            
            // Format students for display
            const formattedStudents = apiStudents.map(student => ({
                id: student.id,
                studentId: student.enrollment_id,
                name: `${student.first_name || ''} ${student.middle_name || ''} ${student.last_name || ''}`.trim(),
                firstName: student.first_name,
                lastName: student.last_name,
                email: student.email_address || 'N/A',
                birthdate: student.birthdate ? new Date(student.birthdate).toLocaleDateString() : 'N/A',
                gradeLevel: student.grade_level,
                enrollmentStatus: student.enrollment_status,
                enrollmentDate: student.enrollment_date,
                status: student.is_active ? 'Active' : 'Inactive'
            }));
            
            enrolledStudents.value = formattedStudents.filter(s => s.enrollmentStatus === 'Enrolled');
        } else {
            console.warn('Failed to fetch from API, falling back to localStorage');
            // Fallback to localStorage if API fails
            const students = JSON.parse(localStorage.getItem('students') || '[]');
            enrolledStudents.value = students.filter((s) => s.enrollmentStatus === 'Enrolled');
        }
    } catch (error) {
        console.error('Error loading enrolled students:', error);
        // Fallback to localStorage on error
        try {
            const students = JSON.parse(localStorage.getItem('students') || '[]');
            enrolledStudents.value = students.filter((s) => s.enrollmentStatus === 'Enrolled');
        } catch (localError) {
            console.error('Error loading from localStorage:', localError);
            enrolledStudents.value = [];
        }
    }
}
</script>

<template>
    <div class="card p-8 shadow-xl rounded-xl bg-white border border-gray-100">
        <!-- Modern Gradient Header -->
        <div class="modern-header-container mb-8">
            <div class="gradient-header">
                <div class="header-content">
                    <div class="header-left">
                        <div class="header-icon">
                            <i class="pi pi-graduation-cap"></i>
                        </div>
                        <div class="header-text">
                            <h1 class="header-title">Student Enrollment System</h1>
                            <p class="header-subtitle">Naawan Central School</p>
                            <div class="student-count">
                                <i class="pi pi-chart-bar mr-2"></i>
                                Total Admitted: <span class="count-badge">{{ students.length }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <div class="search-container">
                            <span class="p-input-icon-left">
                                <i class="pi pi-search" />
                                <InputText v-model="search" placeholder="Search students..." class="search-input" />
                            </span>
                        </div>
                        <Button label="Add New Student" icon="pi pi-plus" class="add-student-btn ml-4" @click="openNewStudentDialog" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8 px-6 py-8">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg p-6 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium uppercase tracking-wide mb-2">Total Admitted</p>
                        <p class="text-3xl font-bold">{{ students.length }}</p>
                    </div>
                    <div class="bg-blue-400 bg-opacity-30 rounded-lg p-3">
                        <i class="pi pi-users text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-lg p-6 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium uppercase tracking-wide mb-2">Enrolled</p>
                        <p class="text-3xl font-bold">{{ totalEnrolledStudents }}</p>
                    </div>
                    <div class="bg-green-400 bg-opacity-30 rounded-lg p-3">
                        <i class="pi pi-check-circle text-2xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-lg p-6 text-white shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium uppercase tracking-wide mb-2">Not Enrolled</p>
                        <p class="text-3xl font-bold">{{ notEnrolledStudents.length }}</p>
                    </div>
                    <div class="bg-orange-400 bg-opacity-30 rounded-lg p-3">
                        <i class="pi pi-chart-bar text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 px-6">
            <!-- Enrolled Students Section -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="p-4 bg-gray-50 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">Enrolled Students</h3>
                        <p class="text-sm text-gray-600 mt-1">Students who have completed the enrollment process</p>
                    </div>

                    <div v-if="enrolledStudents.length === 0" class="p-6 text-center">
                        <i class="pi pi-users text-3xl text-gray-400 mb-3"></i>
                        <h4 class="text-gray-600 mb-2 font-medium">No Enrolled Students Yet</h4>
                        <p class="text-gray-500 text-sm mb-4">Start by adding new students to the enrollment system</p>
                        <Button label="Add First Student" icon="pi pi-plus" class="p-button-sm" @click="openNewStudentDialog" />
                    </div>

                    <div v-else class="p-4">
                        <div class="grid grid-cols-1 gap-3">
                            <div v-for="student in enrolledStudents" :key="student.id" class="student-card p-3 border rounded-lg hover:shadow-sm transition-all bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <Avatar :image="student.photo || 'https://via.placeholder.com/32'" shape="circle" size="normal" class="mr-3" />
                                        <div>
                                            <h5 class="font-medium text-gray-800 text-sm">{{ student.name }}</h5>
                                            <p class="text-xs text-gray-600">{{ student.studentId }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <Tag value="Enrolled" severity="success" class="text-xs" />
                                        <p class="text-xs text-gray-500 mt-1">{{ student.gradeLevel }} - {{ student.section || 'No Section' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 p-2 bg-blue-50 rounded text-center">
                            <span class="text-sm font-medium text-blue-700">{{ totalEnrolledStudents }} Students Enrolled</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Enrollment Section -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="p-4 bg-gray-50 border-b">
                        <h3 class="text-lg font-semibold text-gray-800">Pending Enrollment</h3>
                        <p class="text-sm text-gray-600 mt-1">Students awaiting enrollment</p>
                    </div>

                    <div class="p-4">
                        <div class="mb-3" id="search-container">
                            <div class="p-input-icon-left w-full">
                                <i class="pi pi-search" />
                                <InputText v-model="search" placeholder="Search students..." class="w-full text-sm" />
                            </div>
                        </div>

                        <div v-if="filteredStudents.length === 0" class="text-center p-4">
                            <i class="pi pi-users text-2xl text-gray-400 mb-2"></i>
                            <p class="text-gray-500 text-sm">No pending enrollments</p>
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="student in filteredStudents.slice(0, 5)"
                                :key="student.id"
                                class="student-item p-3 border rounded-lg hover:bg-gray-50 cursor-pointer transition-colors"
                                :class="{ 'bg-blue-50 border-blue-200': selectedStudent && selectedStudent.id === student.id }"
                                @click="openStudentModal(student)"
                            >
                                <div class="flex items-center">
                                    <Avatar :image="student.photo" shape="circle" size="normal" class="mr-3" />
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium text-sm text-gray-800 truncate">{{ student.name }}</span>
                                            <Tag value="Pending" severity="warning" class="text-xs" />
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">{{ student.studentId }}</div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="filteredStudents.length > 5" class="text-center p-2">
                                <span class="text-xs text-gray-500">+{{ filteredStudents.length - 5 }} more students</span>
                            </div>

                            <div class="mt-3 p-2 bg-orange-50 rounded text-center">
                                <span class="text-sm font-medium text-orange-700">{{ filteredStudents.length }} Pending</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- New Student Enrollment Dialog -->
        <Dialog v-model:visible="newStudentDialog" modal :style="{ width: '900px', maxHeight: '85vh' }" :dismissableMask="true" :closable="true" class="enrollment-dialog">
            <template #header>
                <div class="flex items-center justify-between w-full">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800 mb-1">Student Enrollment Form</h2>
                        <p class="text-sm text-gray-600">Naawan Central School • S.Y. 2025-2026</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">Step {{ currentStep }} of {{ totalSteps }}</span>
                    </div>
                </div>
            </template>

            <div class="p-6">
                <!-- Progress Indicator -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-700">Progress</span>
                        <span class="text-sm text-gray-500">{{ Math.round((currentStep / totalSteps) * 100) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="{ width: (currentStep / totalSteps) * 100 + '%' }"></div>
                    </div>
                </div>

                <!-- Step 1: Form Fields -->
                <div v-if="currentStep === 1" class="space-y-8">
                    <!-- Student Type Section -->
                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Student Type</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <div v-for="type in studentTypes" :key="type.value" class="flex items-center">
                                <RadioButton v-model="newStudent.studentType" :inputId="type.value" :value="type.value" class="mr-2" />
                                <label :for="type.value" class="text-sm font-medium text-gray-700 cursor-pointer">{{ type.name }}</label>
                            </div>
                        </div>
                    </div>

                    <!-- School Information Section -->
                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">School Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">School Year *</label>
                                <InputText v-model="newStudent.schoolYear" class="w-full" readonly />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">LRN (Optional)</label>
                                <InputText v-model="newStudent.lrn" placeholder="Leave blank if none" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Grade Level *</label>
                                <Dropdown v-model="newStudent.gradeLevel" :options="gradeLevels" optionLabel="name" optionValue="code" placeholder="Select Grade Level" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Enrollment ID</label>
                                <InputText v-model="newStudent.serialNumber" class="w-full" readonly />
                                <small class="text-xs text-gray-500">Auto-generated</small>
                            </div>
                        </div>
                    </div>

                    <!-- Student Information Section -->
                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Student Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name *</label>
                                <InputText v-model="newStudent.lastName" placeholder="Enter last name" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name *</label>
                                <InputText v-model="newStudent.firstName" placeholder="Enter first name" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                <InputText v-model="newStudent.middleName" placeholder="Enter middle name" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Extension Name</label>
                                <InputText v-model="newStudent.extensionName" placeholder="Jr., Sr., III, etc." class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate *</label>
                                <Calendar v-model="newStudent.birthdate" dateFormat="mm/dd/yy" placeholder="Select date" class="w-full" @date-select="calculateAge" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                                <InputText v-model="newStudent.age" class="w-full" readonly />
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Sex *</label>
                                <div class="flex gap-4">
                                    <div class="flex items-center">
                                        <RadioButton v-model="newStudent.sex" inputId="male" value="Male" class="mr-2" />
                                        <label for="male" class="text-sm cursor-pointer">Male</label>
                                    </div>
                                    <div class="flex items-center">
                                        <RadioButton v-model="newStudent.sex" inputId="female" value="Female" class="mr-2" />
                                        <label for="female" class="text-sm cursor-pointer">Female</label>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Religion</label>
                                <InputText v-model="newStudent.religion" placeholder="Enter religion" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mother Tongue</label>
                                <InputText v-model="newStudent.motherTongue" placeholder="Enter mother tongue" class="w-full" />
                            </div>
                        </div>
                    </div>
                    <!-- Address Information Section -->
                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Address Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">House No.</label>
                                <InputText v-model="newStudent.houseNo" placeholder="Enter house number" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Street</label>
                                <InputText v-model="newStudent.street" placeholder="Enter street" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Barangay *</label>
                                <InputText v-model="newStudent.barangay" placeholder="Enter barangay" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">City/Municipality *</label>
                                <InputText v-model="newStudent.cityMunicipality" placeholder="Enter city/municipality" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Province *</label>
                                <InputText v-model="newStudent.province" placeholder="Enter province" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                <InputText v-model="newStudent.country" class="w-full" />
                            </div>
                        </div>
                    </div>

                    <!-- Parent/Guardian Information Section -->
                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Parent/Guardian Information</h3>

                        <!-- Guardian's Information -->
                        <div class="mb-6">
                            <h4 class="text-md font-medium text-gray-700 mb-3">Guardian's Information (if applicable)</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Father's Full Name</label>
                                    <InputText v-model="newStudent.fatherName" placeholder="Enter father's full name" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Father's Occupation</label>
                                    <InputText v-model="newStudent.fatherOccupation" placeholder="Enter father's occupation" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Father's Contact Number</label>
                                    <InputText v-model="newStudent.fatherContact" placeholder="Enter father's contact number" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Father's Highest Educational Attainment</label>
                                    <InputText v-model="newStudent.fatherEducation" placeholder="Enter father's education" class="w-full" />
                                </div>
                            </div>
                        </div>

                        <!-- Mother's Information -->
                        <div class="mb-6">
                            <h4 class="text-md font-medium text-gray-700 mb-3">Mother's Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mother's Full Name</label>
                                    <InputText v-model="newStudent.motherName" placeholder="Enter mother's full name" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mother's Occupation</label>
                                    <InputText v-model="newStudent.motherOccupation" placeholder="Enter mother's occupation" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mother's Contact Number</label>
                                    <InputText v-model="newStudent.motherContact" placeholder="Enter mother's contact number" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mother's Highest Educational Attainment</label>
                                    <InputText v-model="newStudent.motherEducation" placeholder="Enter mother's education" class="w-full" />
                                </div>
                            </div>
                        </div>

                        <!-- Guardian Information (if different) -->
                        <div>
                            <h4 class="text-md font-medium text-gray-700 mb-2">Guardian Information (if different from parents)</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Guardian's Full Name</label>
                                    <InputText v-model="newStudent.guardianName" placeholder="Enter guardian's full name" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Relationship to Student</label>
                                    <InputText v-model="newStudent.guardianRelationship" placeholder="Enter relationship" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Guardian's Contact Number</label>
                                    <InputText v-model="newStudent.guardianContact" placeholder="Enter guardian's contact number" class="w-full" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Guardian's Address</label>
                                    <InputText v-model="newStudent.guardianAddress" placeholder="Enter guardian's address" class="w-full" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Previous School Information Section -->
                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Previous School Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Grade Completed</label>
                                <Dropdown v-model="newStudent.lastGradeCompleted" :options="gradeLevels" optionLabel="name" optionValue="code" placeholder="Select last grade completed" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last School Year Completed</label>
                                <InputText v-model="newStudent.lastSchoolYear" placeholder="e.g., 2023-2024" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last School Attended</label>
                                <InputText v-model="newStudent.lastSchoolAttended" placeholder="Enter name of last school attended" class="w-full" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">School Address</label>
                                <InputText v-model="newStudent.lastSchoolAddress" placeholder="Enter school address" class="w-full" />
                            </div>
                        </div>
                    </div>

                    <!-- Contact & Additional Information Section -->
                    <div class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Contact & Additional Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                <InputText v-model="newStudent.emailAddress" placeholder="Enter email address" class="w-full" type="email" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Zip Code</label>
                                <InputText v-model="newStudent.zipCode" placeholder="Enter zip code" class="w-full" />
                            </div>
                        </div>

                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Monthly Household Income *</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div v-for="income in incomeOptions" :key="income.value" class="flex items-center">
                                    <RadioButton v-model="newStudent.householdIncome" :inputId="'income_' + income.value" :value="income.value" class="mr-2" />
                                    <label :for="'income_' + income.value" class="text-sm cursor-pointer">{{ income.name }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Review and Submit -->
                <div v-if="currentStep === 2" class="space-y-6">
                    <div class="bg-blue-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Review Your Information</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="font-medium text-gray-700 mb-2">Student Information</h4>
                                <div class="space-y-1 text-sm">
                                    <p><span class="font-medium">Name:</span> {{ `${newStudent.firstName} ${newStudent.middleName} ${newStudent.lastName} ${newStudent.extensionName}`.trim() }}</p>
                                    <p><span class="font-medium">Student Type:</span> {{ newStudent.studentType }}</p>
                                    <p><span class="font-medium">Grade Level:</span> {{ gradeLevels.find((g) => g.code === newStudent.gradeLevel)?.name || newStudent.gradeLevel }}</p>
                                    <p><span class="font-medium">Enrollment ID:</span> {{ newStudent.serialNumber }}</p>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-medium text-gray-700 mb-2">Contact Information</h4>
                                <div class="space-y-1 text-sm">
                                    <p><span class="font-medium">Email:</span> {{ newStudent.emailAddress }}</p>
                                    <p><span class="font-medium">Address:</span> {{ `${newStudent.houseNo} ${newStudent.street}, ${newStudent.barangay}, ${newStudent.cityMunicipality}`.trim() }}</p>
                                    <p><span class="font-medium">Province:</span> {{ newStudent.province }}</p>
                                    <p><span class="font-medium">Household Income:</span> {{ newStudent.householdIncome }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-white rounded border">
                            <div class="flex items-start">
                                <Checkbox v-model="termsAccepted" inputId="termsAccepted" :binary="true" class="mr-3 mt-1" />
                                <label for="termsAccepted" class="text-sm text-gray-700 cursor-pointer">
                                    I hereby certify that the information provided above is true and correct to the best of my knowledge. I understand that any false information may result in the rejection of this enrollment application.
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex justify-between pt-6 border-t">
                    <Button v-if="currentStep > 1" label="Previous" icon="pi pi-chevron-left" class="p-button-outlined" @click="previousStep" />
                    <div v-else></div>

                    <div class="flex gap-2">
                        <Button label="Cancel" class="p-button-outlined p-button-secondary" @click="newStudentDialog = false" />
                        <Button v-if="currentStep < totalSteps" label="Next" icon="pi pi-chevron-right" iconPos="right" @click="nextStep" />
                        <Button v-else label="Submit Enrollment" icon="pi pi-check" :disabled="!termsAccepted" @click="submitNewStudent" />
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Enrollment Dialog (for existing students) -->
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
                                    <p class="m-0">
                                        Enrollment Date: <strong>{{ selectedStudent?.enrollmentDate }}</strong>
                                    </p>
                                    <p class="m-0 mt-1">
                                        Grade: <strong>{{ selectedStudent?.gradeLevel }}</strong> | Section: <strong>{{ selectedStudent?.section }}</strong>
                                    </p>
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

/* Checkbox styling */
:deep(.p-checkbox) {
    width: 18px;
    height: 18px;
}

:deep(.p-checkbox .p-checkbox-box) {
    width: 18px;
    height: 18px;
    border: 2px solid #d1d5db;
    border-radius: 4px;
    background: white;
    transition: all 0.2s ease;
}

:deep(.p-checkbox .p-checkbox-box.p-highlight) {
    border-color: #3b82f6;
    background-color: #3b82f6;
}

:deep(.p-checkbox .p-checkbox-box .p-checkbox-icon) {
    color: white;
    font-size: 12px;
    font-weight: bold;
}

:deep(.p-checkbox:not(.p-checkbox-disabled) .p-checkbox-box:hover) {
    border-color: #3b82f6;
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

/* Modern Header Styling - Matching Student Management System */
.modern-header-container {
    margin: -2rem -2rem 2rem -2rem;
}

.gradient-header {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
    border-radius: 12px 12px 0 0;
    padding: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
}

.gradient-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    opacity: 0.3;
}

.header-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.header-icon {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    width: 4rem;
    height: 4rem;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.header-icon i {
    font-size: 1.75rem;
    color: white;
}


.header-text {
    flex: 1;
}

.header-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    letter-spacing: -0.025em;
    color: white !important;
}

.header-subtitle {
    font-size: 1.1rem;
    margin: 0 0 0.75rem 0;
    opacity: 0.9;
    font-weight: 400;
    color: white !important;
}

.student-count {
    display: flex;
    align-items: center;
    font-size: 1rem;
    font-weight: 500;
    background: rgba(255, 255, 255, 0.15);
    padding: 0.5rem 1rem;
    border-radius: 25px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    width: fit-content;
}

.count-badge {
    background: rgba(255, 255, 255, 0.9);
    color: #1e40af;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-weight: 700;
    margin-left: 0.5rem;
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.search-container {
    position: relative;
}

.search-input {
    background: rgba(255, 255, 255, 0.95) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    border-radius: 25px !important;
    padding: 0.75rem 1rem 0.75rem 2.75rem !important;
    color: #1e40af !important;
    font-weight: 500 !important;
    width: 300px !important;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease !important;
    height: 44px !important;
}

.search-input::placeholder {
    color: #64748b !important;
}

#search-container .pi-search {
    color: #64748b !important;
    left: 1rem !important;
    z-index: 2;
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
}

.search-input:focus {
    background: white !important;
    border-color: rgba(255, 255, 255, 0.8) !important;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2) !important;
    outline: none !important;
}

.search-container .pi-search {
    color: #64748b !important;
    left: 1rem !important;
    z-index: 2;
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
}

.add-student-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    border-radius: 25px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.add-student-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }

    .header-actions {
        align-items: center;
        width: 100%;
    }

    .search-input {
        width: 100%;
        max-width: 300px;
    }

    .header-title {
        font-size: 1.5rem;
    }

    .grid > .col-12 {
        padding: 0.5rem;
    }

    :deep(.p-tabview-panels) {
        padding: 1rem 0;
    }
}
</style>
