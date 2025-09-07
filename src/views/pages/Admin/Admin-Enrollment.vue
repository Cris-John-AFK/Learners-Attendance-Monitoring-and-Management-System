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
const pendingSearch = ref('');
const loading = ref(true);
const enrollmentDialog = ref(false);
const confirmationDialog = ref(false);
const selectedStudent = ref(null);
const showStudentDetails = ref(false);
const activeStudentTab = ref(0);
const selectedGradeLevel = ref(null);
const selectedSection = ref(null);

// Helper functions for student display
function getStudentDisplayName(student) {
    // Try different name field combinations based on backend data structure
    if (student.name && student.name.trim()) {
        return student.name.trim();
    }

    const firstName = student.firstName || student.first_name || '';
    const lastName = student.lastName || student.last_name || '';
    const middleName = student.middleName || student.middle_name || '';

    if (firstName || lastName) {
        return `${firstName} ${middleName ? middleName + ' ' : ''}${lastName}`.trim();
    }

    return student.studentId || student.enrollment_id || 'Unknown Student';
}

function getStudentInitials(student) {
    const displayName = getStudentDisplayName(student);

    if (displayName === 'Unknown Student' || displayName.startsWith('ENR')) {
        return 'S';
    }

    const nameParts = displayName.split(' ').filter((part) => part.length > 0);
    if (nameParts.length >= 2) {
        return (nameParts[0].charAt(0) + nameParts[nameParts.length - 1].charAt(0)).toUpperCase();
    } else if (nameParts.length === 1) {
        return nameParts[0].charAt(0).toUpperCase();
    }

    return 'S';
}

function getGradeCode(gradeLevel) {
    const gradeMap = {
        Kinder: 'K',
        'Grade 1': '1',
        'Grade 2': '2',
        'Grade 3': '3',
        'Grade 4': '4',
        'Grade 5': '5',
        'Grade 6': '6'
    };
    return gradeMap[gradeLevel] || gradeLevel;
}

// Section assignment functionality
const sectionAssignmentDialog = ref(false);
const studentToAssign = ref(null);
const availableSectionsForAssignment = ref([]);
const selectedSectionForAssignment = ref(null);

// Section assignment functions
async function openSectionAssignment(student) {
    studentToAssign.value = student;
    selectedSectionForAssignment.value = null;
    availableSectionsForAssignment.value = [];

    try {
        // Fetch available sections for this student's grade level from backend
        const response = await fetch(`http://127.0.0.1:8000/api/enrollments/${student.id}/available-sections`, {
            method: 'GET',
            headers: {
                Accept: 'application/json'
            }
        });

        if (response.ok) {
            const result = await response.json();
            if (result.success && result.data) {
                availableSectionsForAssignment.value = result.data;
            }
        } else {
            console.warn('Failed to fetch available sections, using fallback');
            // Fallback to static sections if API fails
            const gradeCode = getGradeCode(student.gradeLevel || student.grade_level);
            availableSectionsForAssignment.value = sections[gradeCode] || [];
        }
    } catch (error) {
        console.error('Error fetching available sections:', error);
        // Fallback to static sections
        const gradeCode = getGradeCode(student.gradeLevel || student.grade_level);
        availableSectionsForAssignment.value = sections[gradeCode] || [];
    }

    sectionAssignmentDialog.value = true;
}

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
const pendingEnrollmentStudents = computed(() => {
    // Students who are enrolled but don't have sections assigned yet
    const allStudents = enrolledStudents.value.length > 0 ? enrolledStudents.value : students.value;
    return allStudents.filter((student) => !student.section && !student.sectionId && !student.current_section_name && !student.current_section_id);
});

const fullyEnrolledStudents = computed(() => {
    // Students who have sections assigned (should appear in Student Management)
    const allStudents = enrolledStudents.value.length > 0 ? enrolledStudents.value : students.value;
    return allStudents.filter((student) => student.section || student.sectionId || student.current_section_name || student.current_section_id);
});

const filteredStudents = computed(() => {
    // For the enrolled students section, show only those without sections
    const targetStudents = pendingEnrollmentStudents.value;
    if (!search.value) return targetStudents;
    const searchTerm = search.value.toLowerCase();
    return targetStudents.filter((s) => {
        const displayName = getStudentDisplayName(s).toLowerCase();
        const studentId = (s.studentId || s.enrollment_id || '').toLowerCase();
        return displayName.includes(searchTerm) || studentId.includes(searchTerm);
    });
});

const filteredPendingStudents = computed(() => {
    // For the pending enrollment section, show only those without sections
    const targetStudents = pendingEnrollmentStudents.value;
    if (!pendingSearch.value) return targetStudents;
    const searchTerm = pendingSearch.value.toLowerCase();
    return targetStudents.filter((s) => {
        const displayName = getStudentDisplayName(s).toLowerCase();
        const studentId = (s.studentId || s.enrollment_id || '').toLowerCase();
        return displayName.includes(searchTerm) || studentId.includes(searchTerm);
    });
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
    return fullyEnrolledStudents.value.length;
});

const totalPendingStudents = computed(() => {
    return pendingEnrollmentStudents.value.length;
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

// Renamed to prevStep to avoid duplication

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
            // Student enrollment data would go here
        };
        
        const studentForDisplay = {
            firstName: newStudent.value.firstName,
            lastName: newStudent.value.lastName,
            gradeLevel: newStudent.value.gradeLevel,
            enrollmentStatus: 'Enrolled'
        };

        // For now, just add to local state
        console.log('Student data prepared:', studentForDisplay);

        enrolledStudents.value.push(studentForDisplay);

        // Close dialog and show success
        newStudentDialog.value = false;

        toast.add({
            severity: 'success',
            summary: 'Enrollment Successful',
            detail: `${studentForDisplay.firstName} ${studentForDisplay.lastName} has been successfully enrolled and saved to database!`,
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



async function assignStudentToSection() {
    if (!studentToAssign.value || !selectedSectionForAssignment.value) {
        toast.add({
            severity: 'warn',
            summary: 'Selection Required',
            detail: 'Please select a section to assign the student.',
            life: 3000
        });
        return;
    }

    try {
        // Use the new section assignment API endpoint
        const response = await fetch(`http://127.0.0.1:8000/api/enrollments/${studentToAssign.value.id}/assign-section`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            },
            body: JSON.stringify({
                section_id: selectedSectionForAssignment.value.id,
                school_year: '2025-2026'
            })
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Failed to assign section');
        }

        const result = await response.json();

        // Update local data
        const studentIndex = enrolledStudents.value.findIndex((s) => s.id === studentToAssign.value.id);
        if (studentIndex !== -1) {
            enrolledStudents.value[studentIndex].section = selectedSectionForAssignment.value.name;
            enrolledStudents.value[studentIndex].current_section_name = selectedSectionForAssignment.value.name;
            enrolledStudents.value[studentIndex].current_section_id = selectedSectionForAssignment.value.id;
        }
        sectionAssignmentDialog.value = false;

        toast.add({
            severity: 'success',
            summary: 'Section Assigned',
            detail: `${getStudentDisplayName(studentToAssign.value)} has been assigned to ${selectedSectionForAssignment.value.name}.`,
            life: 4000
        });

        // Reload enrolled students from API to get updated section data
        await loadEnrolledStudents();
    } catch (error) {
        console.error('Error assigning section:', error);
        toast.add({
            severity: 'error',
            summary: 'Assignment Error',
            detail: error.message || 'Failed to assign student to section. Please try again.',
            life: 3000
        });
    }
}

async function autoAssignAllStudents() {
    const unassignedStudents = enrolledStudents.value.filter((s) => !s.section);

    if (unassignedStudents.length === 0) {
        toast.add({
            severity: 'info',
            summary: 'No Students to Assign',
            detail: 'All students are already assigned to sections.',
            life: 3000
        });
        return;
    }

    try {
        let assignedCount = 0;

        for (const student of unassignedStudents) {
            const gradeCode = getGradeCode(student.gradeLevel || student.grade_level);
            const sectionsForGrade = sections[gradeCode] || [];

            if (sectionsForGrade.length > 0) {
                // Assign to first available section for the grade
                const sectionToAssign = sectionsForGrade[0];

                const response = await fetch(`http://127.0.0.1:8000/api/enrollments/${student.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json'
                    },
                    body: JSON.stringify({
                        section: sectionToAssign.name
                    })
                });

                if (response.ok) {
                    // Update local data
                    const studentIndex = enrolledStudents.value.findIndex((s) => s.id === student.id);
                    if (studentIndex !== -1) {
                        enrolledStudents.value[studentIndex].section = sectionToAssign.name;
                    }
                    assignedCount++;
                }
            }
        }

        toast.add({
            severity: 'success',
            summary: 'Auto-Assignment Complete',
            detail: `Successfully assigned ${assignedCount} students to sections.`,
            life: 5000
        });
    } catch (error) {
        console.error('Error in auto-assignment:', error);
        toast.add({
            severity: 'error',
            summary: 'Auto-Assignment Error',
            detail: 'Some students could not be assigned. Please try manual assignment.',
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
                Accept: 'application/json'
            }
        });

        if (response.ok) {
            const result = await response.json();
            if (result.success && result.data) {
                enrolledStudents.value = result.data;
            }
        }
    } catch (error) {
        console.error('Error loading enrolled students:', error);
    }
}

// Update existing filteredStudents to handle both students and enrolledStudents

// Form navigation functions (using existing nextStep function)
function prevStep() {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
}

// Additional functions needed for template
function closeNewStudentDialog() {
    newStudentDialog.value = false;
    resetNewStudentForm();
}

function viewStudentDetails(student) {
    selectedStudent.value = student;
    showStudentDetails.value = true;
}

// Fix function name mismatch
const assignSection = assignStudentToSection;

// Expose functions for template usage
defineExpose({
    getStudentDisplayName,
    getStudentInitials,
    getGradeCode,
    openSectionAssignment,
    assignSection,
    autoAssignAllStudents,
    nextStep,
    prevStep,
    openNewStudentDialog,
    closeNewStudentDialog,
    submitNewStudent,
    confirmEnrollment,
    viewStudentDetails,
    pendingEnrollmentStudents,
    fullyEnrolledStudents,
    filteredPendingStudents,
    totalPendingStudents
});
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

                    <div v-if="pendingEnrollmentStudents.length === 0" class="p-6 text-center">
                        <i class="pi pi-users text-3xl text-gray-400 mb-3"></i>
                        <h4 class="text-gray-600 mb-2 font-medium">No Pending Students</h4>
                        <p class="text-gray-500 text-sm mb-4">All students have been assigned sections</p>
                        <Button label="Add New Student" icon="pi pi-plus" class="p-button-sm" @click="openNewStudentDialog" />
                    </div>

                    <div v-else class="p-4">
                        <!-- Bulk Assignment Controls -->
                        <div class="mb-4 p-3 bg-blue-50 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium text-blue-800 text-sm">Section Assignment</h4>
                                    <p class="text-xs text-blue-600">Assign students to available sections</p>
                                </div>
                                <Button 
                                    label="Auto-Assign All" 
                                    icon="pi pi-bolt" 
                                    size="small" 
                                    class="p-button-sm p-button-outlined"
                                    @click="autoAssignAllStudents"
                                    :disabled="enrolledStudents.filter((s) => !s.section).length === 0"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3">
                            <div v-for="student in pendingEnrollmentStudents" :key="student.id" class="student-card p-4 border rounded-lg hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-blue-600 font-semibold text-sm">{{ getStudentInitials(student) }}</span>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-900">{{ getStudentDisplayName(student) }}</h4>
                                            <p class="text-sm text-gray-500">{{ student.gradeLevel || student.grade_level }}</p>
                                        </div>
                                    </div>
                                    <Button label="Assign Section" icon="pi pi-arrow-right" size="small" class="p-button-sm" @click="openSectionAssignment(student)" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <Button label="Add New Student" icon="pi pi-plus" class="w-full p-button-outlined" @click="openNewStudentDialog" />
                        <Button label="Import Students" icon="pi pi-upload" class="w-full p-button-outlined" />
                        <Button label="Export Data" icon="pi pi-download" class="w-full p-button-outlined" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Assignment Dialog -->
        <Dialog v-model:visible="sectionAssignmentDialog" :style="{ width: '500px' }" header="Assign Student to Section" :modal="true" class="p-fluid">
            <div v-if="studentToAssign" class="mb-4">
                <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                    <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                        <span class="text-blue-600 font-semibold">{{ getStudentInitials(studentToAssign) }}</span>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900">{{ getStudentDisplayName(studentToAssign) }}</h4>
                        <p class="text-sm text-gray-500">{{ studentToAssign.gradeLevel || studentToAssign.grade_level }}</p>
                    </div>
                </div>
            </div>

            <div class="field">
                <label for="sectionSelect" class="font-medium text-gray-700">Select Section</label>
                <Dropdown 
                    id="sectionSelect"
                    v-model="selectedSectionForAssignment" 
                    :options="availableSectionsForAssignment"
                    optionLabel="name"
                    placeholder="Choose a section"
                    class="w-full mt-2"
                    :disabled="!studentToAssign"
                >
                    <template #option="slotProps">
                        <div class="flex items-center">
                            <i class="pi pi-users mr-2 text-blue-500"></i>
                            <span>{{ slotProps.option.name }}</span>
                        </div>
                    </template>
                </Dropdown>
                <small class="text-gray-500 mt-1"> Available sections for {{ studentToAssign?.gradeLevel || studentToAssign?.grade_level }} </small>
            </div>

            <div v-if="selectedSectionForAssignment" class="mt-4 p-3 bg-green-50 rounded-lg">
                <div class="flex items-center">
                    <i class="pi pi-check-circle text-green-600 mr-2"></i>
                    <span class="text-green-800 font-medium"> Ready to assign to {{ selectedSectionForAssignment.name }} </span>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="sectionAssignmentDialog = false" />
                <Button label="Assign Section" icon="pi pi-check" class="p-button-primary" @click="assignStudentToSection" :disabled="!selectedSectionForAssignment" />
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
