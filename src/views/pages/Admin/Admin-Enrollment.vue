<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
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

// Expandable cards state
const expandEnrolledCard = ref(false);
const expandNotEnrolledCard = ref(false);

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
    studentId: '', // Unique field

    // Student Information
    lastName: '',
    firstName: '',
    middleName: '',
    extensionName: '',
    birthdate: null,
    age: '',
    sex: 'Male',
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
    fatherFirstName: '',
    fatherLastName: '',
    fatherMiddleName: '',
    fatherContactNumber: '',

    motherFirstName: '',
    motherLastName: '',
    motherMiddleName: '',
    motherContactNumber: '',

    // Previous School Information
    lastGradeCompleted: '',
    lastSchoolYearCompleted: '',
    lastSchoolAttended: '',

    // Contact Information
    emailAddress: '',

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

// Generate unique student ID for enrollment
function generateSerialNumber() {
    const currentYear = new Date().getFullYear();
    const timestamp = Date.now().toString().slice(-6); // Last 6 digits of timestamp
    const randomNum = Math.floor(Math.random() * 1000)
        .toString()
        .padStart(3, '0');
    newStudent.value.studentId = `STU${currentYear}${timestamp}${randomNum}`;
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
        studentId: '',
        lastName: '',
        firstName: '',
        middleName: '',
        extensionName: '',
        birthdate: null,
        age: '',
        sex: 'Male',
        motherTongue: '',
        houseNo: '',
        street: '',
        barangay: '',
        cityMunicipality: '',
        province: '',
        country: 'Philippines',
        zipCode: '',
        fatherFirstName: '',
        fatherLastName: '',
        fatherMiddleName: '',
        fatherContactNumber: '',
        motherFirstName: '',
        motherLastName: '',
        motherMiddleName: '',
        motherContactNumber: '',
        lastGradeCompleted: '',
        lastSchoolYearCompleted: '',
        lastSchoolAttended: '',
        emailAddress: '',
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
        // Generate enrollment ID if not exists
        if (!newStudent.value.studentId) {
            generateSerialNumber();
        }

        // Prepare comprehensive student data for backend API
        const enrollmentData = {
            studentId: newStudent.value.studentId,
            student_id: newStudent.value.studentId,
            status: 'Enrolled',
            isIndigenous: false,
            is4PsBeneficiary: false,
            hasDisability: false,
            isActive: true,
            is_active: true,

            name: `${newStudent.value.firstName} ${newStudent.value.middleName || ''} ${newStudent.value.lastName}`.replace(/\s+/g, ' ').trim(),
            firstName: newStudent.value.firstName,
            lastName: newStudent.value.lastName,
            middleName: newStudent.value.middleName || '',
            extensionName: newStudent.value.extensionName || '',

            lrn: newStudent.value.lrn || '',
            gradeLevel: newStudent.value.gradeLevel,
            section: '',
            schoolYearStart: '2025',
            schoolYearEnd: '2026',

            birthdate: newStudent.value.birthdate ? new Date(newStudent.value.birthdate).toISOString().split('T')[0] : null,
            birthplace: newStudent.value.birthplace || '',
            age: parseInt(newStudent.value.age) || null,
            gender: newStudent.value.sex || 'Male',
            sex: newStudent.value.sex || 'Male',
            motherTongue: newStudent.value.motherTongue || '',
            psaBirthCertNo: '',

            photo: '/demo/images/student-photo.jpg',
            profilePhoto: '/demo/images/student-photo.jpg',
            qr_code: '',

            email: newStudent.value.emailAddress || '',
            contactInfo: newStudent.value.fatherContactNumber || newStudent.value.motherContactNumber || '',

            currentAddress: {
                house_no: newStudent.value.houseNo || '',
                street: newStudent.value.street || '',
                barangay: newStudent.value.barangay || '',
                city_municipality: newStudent.value.cityMunicipality || '',
                province: newStudent.value.province || '',
                country: newStudent.value.country || 'Philippines',
                zip_code: newStudent.value.zipCode || ''
            },
            permanentAddress: {
                house_no: newStudent.value.houseNo || '',
                street: newStudent.value.street || '',
                barangay: newStudent.value.barangay || '',
                city_municipality: newStudent.value.cityMunicipality || '',
                province: newStudent.value.province || '',
                country: newStudent.value.country || 'Philippines',
                zip_code: newStudent.value.zipCode || ''
            },

            father: {
                first_name: newStudent.value.fatherFirstName || '',
                last_name: newStudent.value.fatherLastName || '',
                middle_name: newStudent.value.fatherMiddleName || '',
                contact_number: newStudent.value.fatherContactNumber || ''
            },
            mother: {
                first_name: newStudent.value.motherFirstName || '',
                last_name: newStudent.value.motherLastName || '',
                middle_name: newStudent.value.motherMiddleName || '',
                contact_number: newStudent.value.motherContactNumber || ''
            },
            parentName: `${newStudent.value.fatherFirstName || ''} ${newStudent.value.fatherLastName || ''}`.trim() || `${newStudent.value.motherFirstName || ''} ${newStudent.value.motherLastName || ''}`.trim() || 'N/A',
            parentContact: `Father: ${newStudent.value.fatherContactNumber || 'N/A'}, Mother: ${newStudent.value.motherContactNumber || 'N/A'}`,

            address: `${newStudent.value.houseNo || ''} ${newStudent.value.street || ''}, ${newStudent.value.barangay}, ${newStudent.value.cityMunicipality}, ${newStudent.value.province}`.replace(/\s+/g, ' ').trim(),

            indigenousCommunity: '',
            householdID: '',
            disabilities: [],

            enrollmentDate: new Date().toISOString(),
            admissionDate: new Date().toISOString(),
            requirements: []
        };

        console.log('Sending enrollment data to API:', enrollmentData);

        // Send data to backend API
        const response = await fetch('http://127.0.0.1:8000/api/students', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            },
            body: JSON.stringify(enrollmentData)
        });

        if (!response.ok) {
            const errorData = await response.json();
            console.error('API Error Response:', errorData);

            // Handle validation errors specifically
            if (response.status === 422 && errorData.errors) {
                const validationErrors = Object.entries(errorData.errors)
                    .map(([field, messages]) => `${field}: ${messages.join(', ')}`)
                    .join('\n');
                throw new Error(`Validation failed:\n${validationErrors}`);
            }

            throw new Error(errorData.message || `HTTP ${response.status}: Failed to enroll student`);
        }

        const responseData = await response.json();
        console.log('Student enrolled successfully:', responseData);

        // Close dialog and show success
        newStudentDialog.value = false;
        resetNewStudentForm();

        toast.add({
            severity: 'success',
            summary: 'Enrollment Successful',
            detail: `${newStudent.value.firstName} ${newStudent.value.lastName} has been successfully enrolled and saved to database!`,
            life: 5000
        });

        // Reload data to reflect changes
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

        <!-- Expandable Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 px-6 py-8">
            <!-- Enrolled Students Card -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 cursor-pointer transform hover:scale-105" @click="expandEnrolledCard = !expandEnrolledCard">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex-1">
                        <p class="text-green-100 text-sm font-medium uppercase tracking-wide mb-2">Enrolled</p>
                        <p class="text-4xl font-bold">{{ totalEnrolledStudents }}</p>
                    </div>
                    <div class="bg-green-400 bg-opacity-30 rounded-xl p-4">
                        <i class="pi pi-check-circle text-3xl"></i>
                    </div>
                </div>
                
                <!-- Expandable Content -->
                <div v-if="expandEnrolledCard" class="mt-6 pt-4 border-t border-green-400 border-opacity-30">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-green-400 bg-opacity-20 rounded-lg p-3">
                            <p class="text-green-100 text-xs uppercase tracking-wide">With Sections</p>
                            <p class="text-xl font-bold">{{ fullyEnrolledStudents.length }}</p>
                        </div>
                        <div class="bg-green-400 bg-opacity-20 rounded-lg p-3">
                            <p class="text-green-100 text-xs uppercase tracking-wide">Pending Assignment</p>
                            <p class="text-xl font-bold">{{ pendingEnrollmentStudents.length }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-between items-center">
                        <span class="text-green-100 text-sm">Click to view details</span>
                        <i class="pi pi-angle-up text-green-100"></i>
                    </div>
                </div>
                
                <!-- Collapsed Indicator -->
                <div v-else class="mt-4 flex justify-between items-center">
                    <span class="text-green-100 text-sm">Click to expand</span>
                    <i class="pi pi-angle-down text-green-100"></i>
                </div>
            </div>

            <!-- Not Enrolled Students Card -->
            <div class="bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl p-6 text-white shadow-xl hover:shadow-2xl transition-all duration-300 cursor-pointer transform hover:scale-105" @click="expandNotEnrolledCard = !expandNotEnrolledCard">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex-1">
                        <p class="text-orange-100 text-sm font-medium uppercase tracking-wide mb-2">Not Enrolled</p>
                        <p class="text-4xl font-bold">{{ notEnrolledStudents.length }}</p>
                    </div>
                    <div class="bg-orange-400 bg-opacity-30 rounded-xl p-4">
                        <i class="pi pi-chart-bar text-3xl"></i>
                    </div>
                </div>
                
                <!-- Expandable Content -->
                <div v-if="expandNotEnrolledCard" class="mt-6 pt-4 border-t border-orange-400 border-opacity-30">
                    <div class="grid grid-cols-1 gap-3">
                        <div class="bg-orange-400 bg-opacity-20 rounded-lg p-3">
                            <p class="text-orange-100 text-xs uppercase tracking-wide">Awaiting Enrollment</p>
                            <p class="text-xl font-bold">{{ students.filter(s => s.status === 'Admitted').length }}</p>
                        </div>
                        <div class="bg-orange-400 bg-opacity-20 rounded-lg p-3">
                            <p class="text-orange-100 text-xs uppercase tracking-wide">Total Applicants</p>
                            <p class="text-xl font-bold">{{ students.length }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex justify-between items-center">
                        <span class="text-orange-100 text-sm">Click to view details</span>
                        <i class="pi pi-angle-up text-orange-100"></i>
                    </div>
                </div>
                
                <!-- Collapsed Indicator -->
                <div v-else class="mt-4 flex justify-between items-center">
                    <span class="text-orange-100 text-sm">Click to expand</span>
                    <i class="pi pi-angle-down text-orange-100"></i>
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
                                <Button label="Auto-Assign All" icon="pi pi-bolt" size="small" class="p-button-sm p-button-outlined" @click="autoAssignAllStudents" :disabled="enrolledStudents.filter((s) => !s.section).length === 0" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4">
                            <div v-for="student in pendingEnrollmentStudents" :key="student.id" class="student-card bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden">
                                <!-- Card Header -->
                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 border-b border-gray-100">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center shadow-lg">
                                                <span class="text-white font-bold text-lg">{{ getStudentInitials(student) }}</span>
                                            </div>
                                            <div>
                                                <h4 class="text-xl font-semibold text-gray-900">{{ getStudentDisplayName(student) }}</h4>
                                                <div class="flex items-center space-x-2 mt-1">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <i class="pi pi-graduation-cap mr-1"></i>
                                                        {{ student.gradeLevel || student.grade_level }}
                                                    </span>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                        <i class="pi pi-check-circle mr-1"></i>
                                                        {{ student.status || 'Admitted' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <Button label="Assign Section" icon="pi pi-arrow-right" class="p-button-primary p-button-sm" @click="openSectionAssignment(student)" />
                                    </div>
                                </div>

                                <!-- Card Body -->
                                <div class="p-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        <!-- Student Information -->
                                        <div class="space-y-3">
                                            <h5 class="text-sm font-semibold text-gray-700 uppercase tracking-wide border-b border-gray-200 pb-1">Student Information</h5>
                                            <div class="space-y-2">
                                                <div class="flex items-center text-sm">
                                                    <i class="pi pi-id-card text-gray-400 mr-2 w-4"></i>
                                                    <span class="text-gray-600 font-medium mr-2">ID:</span>
                                                    <span class="text-gray-900">{{ student.studentId || student.enrollment_id || 'N/A' }}</span>
                                                </div>
                                                <div class="flex items-center text-sm">
                                                    <i class="pi pi-calendar text-gray-400 mr-2 w-4"></i>
                                                    <span class="text-gray-600 font-medium mr-2">Birthdate:</span>
                                                    <span class="text-gray-900">{{ student.birthdate || 'N/A' }}</span>
                                                </div>
                                                <div class="flex items-center text-sm">
                                                    <i class="pi pi-user text-gray-400 mr-2 w-4"></i>
                                                    <span class="text-gray-600 font-medium mr-2">Gender:</span>
                                                    <span class="text-gray-900">{{ student.gender || student.sex || 'N/A' }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Contact Information -->
                                        <div class="space-y-3">
                                            <h5 class="text-sm font-semibold text-gray-700 uppercase tracking-wide border-b border-gray-200 pb-1">Contact Information</h5>
                                            <div class="space-y-2">
                                                <div class="flex items-start text-sm">
                                                    <i class="pi pi-envelope text-gray-400 mr-2 w-4 mt-0.5"></i>
                                                    <div>
                                                        <span class="text-gray-600 font-medium">Email:</span>
                                                        <p class="text-gray-900 break-all">{{ student.email || 'N/A' }}</p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center text-sm">
                                                    <i class="pi pi-phone text-gray-400 mr-2 w-4"></i>
                                                    <span class="text-gray-600 font-medium mr-2">Contact:</span>
                                                    <span class="text-gray-900">{{ student.contact || student.phone || 'N/A' }}</span>
                                                </div>
                                                <div class="flex items-start text-sm">
                                                    <i class="pi pi-map-marker text-gray-400 mr-2 w-4 mt-0.5"></i>
                                                    <div>
                                                        <span class="text-gray-600 font-medium">Address:</span>
                                                        <p class="text-gray-900 text-xs leading-relaxed">{{ formatAddress(student) }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Enrollment Details -->
                                        <div class="space-y-3">
                                            <h5 class="text-sm font-semibold text-gray-700 uppercase tracking-wide border-b border-gray-200 pb-1">Enrollment Details</h5>
                                            <div class="space-y-2">
                                                <div class="flex items-center text-sm">
                                                    <i class="pi pi-calendar-plus text-gray-400 mr-2 w-4"></i>
                                                    <span class="text-gray-600 font-medium mr-2">Admission:</span>
                                                    <span class="text-gray-900">{{ student.admissionDate || student.admission_date || 'N/A' }}</span>
                                                </div>
                                                <div class="flex items-center text-sm">
                                                    <i class="pi pi-bookmark text-gray-400 mr-2 w-4"></i>
                                                    <span class="text-gray-600 font-medium mr-2">LRN:</span>
                                                    <span class="text-gray-900">{{ student.lrn || 'Not provided' }}</span>
                                                </div>
                                                <div class="flex items-center text-sm">
                                                    <i class="pi pi-building text-gray-400 mr-2 w-4"></i>
                                                    <span class="text-gray-600 font-medium mr-2">Section:</span>
                                                    <span class="text-orange-600 font-medium">{{ student.section || 'Not Assigned' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="mt-4 pt-4 border-t border-gray-100">
                                        <div class="flex items-center justify-between">
                                            <div class="flex space-x-2">
                                                <Button label="View Details" icon="pi pi-eye" class="p-button-outlined p-button-sm" @click="openStudentModal(student)" />
                                                <Button label="Edit Info" icon="pi pi-pencil" class="p-button-outlined p-button-sm" />
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <i class="pi pi-clock mr-1"></i>
                                                Last updated: {{ new Date().toLocaleDateString() }}
                                            </div>
                                        </div>
                                    </div>
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
                <Dropdown id="sectionSelect" v-model="selectedSectionForAssignment" :options="availableSectionsForAssignment" optionLabel="name" placeholder="Choose a section" class="w-full mt-2" :disabled="!studentToAssign">
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

        <!-- New Student Enrollment Dialog -->
        <Dialog v-model:visible="newStudentDialog" modal :style="{ width: '95vw', maxWidth: '1000px', maxHeight: '90vh' }" :dismissableMask="true" :closable="false" class="enrollment-dialog">
            <template #header>
                <div class="modern-enrollment-header">
                    <div class="header-content">
                        <div class="school-identity">
                            <div class="logo-wrapper">
                                <div class="school-logo-modern">
                                    <i class="pi pi-graduation-cap"></i>
                                </div>
                            </div>
                            <div class="school-details">
                                <h1 class="school-title">Naawan Central School</h1>
                                <p class="school-motto">Excellence in Education • Building Tomorrow's Leaders</p>
                                <div class="enrollment-badge">
                                    <span class="badge-text">Student Enrollment Portal</span>
                                    <span class="academic-year">S.Y. 2025-2026</span>
                                </div>
                            </div>
                        </div>
                        <div class="header-controls">
                            <div class="step-indicator-header">
                                <div class="step-counter">
                                    <span class="current-step">{{ currentStep }}</span>
                                    <span class="step-divider">/</span>
                                    <span class="total-steps">{{ totalSteps }}</span>
                                </div>
                                <span class="step-label">Step Progress</span>
                            </div>
                            <button type="button" class="modern-close-btn" @click="newStudentDialog = false">
                                <i class="pi pi-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <div class="modern-dialog-content">
                <!-- Enhanced Progress Indicator -->
                <div class="progress-section">
                    <div class="progress-info">
                        <h3 class="progress-title">Enrollment Progress</h3>
                        <span class="progress-percentage">{{ Math.round((currentStep / totalSteps) * 100) }}% Complete</span>
                    </div>
                    <div class="progress-track">
                        <div class="progress-fill" :style="{ width: (currentStep / totalSteps) * 100 + '%' }">
                            <div class="progress-glow"></div>
                        </div>
                        <div class="progress-steps">
                            <div v-for="step in totalSteps" :key="step" class="progress-step" :class="{ active: step <= currentStep, current: step === currentStep }">
                                <div class="step-circle">
                                    <i v-if="step < currentStep" class="pi pi-check"></i>
                                    <span v-else>{{ step }}</span>
                                </div>
                                <span class="step-name">{{ step === 1 ? 'Information' : 'Review' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 1: Form Fields -->
                <div v-if="currentStep === 1" class="space-y-8">
                    <!-- Student Type Section -->
                    <div class="modern-form-section" data-section="student-type">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="pi pi-user"></i>
                            </div>
                            <div class="section-title">
                                <h3>Student Type</h3>
                                <p>Select the enrollment category</p>
                            </div>
                        </div>
                        <div class="section-content">
                            <div class="radio-grid">
                                <div v-for="type in studentTypes" :key="type.value" class="radio-card" :class="{ selected: newStudent.studentType === type.value }">
                                    <input type="radio" v-model="newStudent.studentType" :id="type.value" :value="type.value" class="radio-input" />
                                    <label :for="type.value" class="radio-label">
                                        <div class="radio-indicator"></div>
                                        <span class="radio-text">{{ type.name }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- School Information Section -->
                    <div class="modern-form-section" data-section="school-info">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="pi pi-building"></i>
                            </div>
                            <div class="section-title">
                                <h3>School Information</h3>
                                <p>Academic details and enrollment data</p>
                            </div>
                        </div>
                        <div class="section-content">
                            <div class="input-grid">
                                <div class="input-group">
                                    <label class="modern-label">School Year *</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.schoolYear" class="modern-input" readonly />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">LRN (Optional)</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.lrn" placeholder="Leave blank if none" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Grade Level *</label>
                                    <div class="input-wrapper">
                                        <Dropdown v-model="newStudent.gradeLevel" :options="gradeLevels" optionLabel="name" optionValue="code" placeholder="Select Grade Level" class="modern-dropdown" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Student ID</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.studentId" class="modern-input" readonly />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Student Information Section -->
                    <div class="modern-form-section" data-section="student-info">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="pi pi-id-card"></i>
                            </div>
                            <div class="section-title">
                                <h3>Student Information</h3>
                                <p>Personal details and basic information</p>
                            </div>
                        </div>
                        <div class="section-content">
                            <div class="input-grid">
                                <div class="input-group">
                                    <label class="modern-label">Last Name *</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.lastName" placeholder="Enter last name" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">First Name *</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.firstName" placeholder="Enter first name" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Middle Name</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.middleName" placeholder="Enter middle name" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Extension Name</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.extensionName" placeholder="Jr., Sr., III, etc." class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Birthdate *</label>
                                    <div class="input-wrapper">
                                        <Calendar v-model="newStudent.birthdate" dateFormat="mm/dd/yy" placeholder="Select date" class="modern-calendar" @date-select="calculateAge" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Age</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.age" class="modern-input" readonly />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="additional-info">
                                <div class="input-group gender-group">
                                    <label class="modern-label">Sex *</label>
                                    <div class="radio-inline">
                                        <div class="radio-option">
                                            <input type="radio" v-model="newStudent.sex" id="male" value="Male" class="radio-input-inline" />
                                            <label for="male" class="radio-label-inline">Male</label>
                                        </div>
                                        <div class="radio-option">
                                            <input type="radio" v-model="newStudent.sex" id="female" value="Female" class="radio-input-inline" />
                                            <label for="female" class="radio-label-inline">Female</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Mother Tongue</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.motherTongue" placeholder="Enter mother tongue" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information Section -->
                    <div class="modern-form-section" data-section="address-info">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="pi pi-map-marker"></i>
                            </div>
                            <div class="section-title">
                                <h3>Address Information</h3>
                                <p>Current residential address</p>
                            </div>
                        </div>
                        <div class="section-content">
                            <div class="input-grid">
                                <div class="input-group">
                                    <label class="modern-label">House No.</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.houseNo" placeholder="Enter house number" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Street</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.street" placeholder="Enter street" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Barangay *</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.barangay" placeholder="Enter barangay" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">City/Municipality *</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.cityMunicipality" placeholder="Enter city/municipality" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Province *</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.province" placeholder="Enter province" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Country</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.country" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Zip Code</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.zipCode" placeholder="Enter zip code" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Parent/Guardian Information Section -->
                    <div class="modern-form-section" data-section="parent-info">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="pi pi-users"></i>
                            </div>
                            <div class="section-title">
                                <h3>Parent/Guardian Information</h3>
                                <p>Family contact details</p>
                            </div>
                        </div>
                        <div class="section-content">
                            <!-- Father's Information -->
                            <div class="parent-subsection">
                                <h4 class="subsection-title">Father's Information</h4>
                                <div class="input-grid">
                                    <div class="input-group">
                                        <label class="modern-label">Father's Last Name</label>
                                        <div class="input-wrapper">
                                            <InputText v-model="newStudent.fatherLastName" placeholder="Enter father's last name" class="modern-input" />
                                            <div class="input-accent"></div>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label class="modern-label">Father's First Name</label>
                                        <div class="input-wrapper">
                                            <InputText v-model="newStudent.fatherFirstName" placeholder="Enter father's first name" class="modern-input" />
                                            <div class="input-accent"></div>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label class="modern-label">Father's Middle Name</label>
                                        <div class="input-wrapper">
                                            <InputText v-model="newStudent.fatherMiddleName" placeholder="Enter father's middle name" class="modern-input" />
                                            <div class="input-accent"></div>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label class="modern-label">Father's Contact Number</label>
                                        <div class="input-wrapper">
                                            <InputText v-model="newStudent.fatherContactNumber" placeholder="Enter father's contact number" class="modern-input" />
                                            <div class="input-accent"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Mother's Information -->
                            <div class="parent-subsection">
                                <h4 class="subsection-title">Mother's Information</h4>
                                <div class="input-grid">
                                    <div class="input-group">
                                        <label class="modern-label">Mother's Last Name</label>
                                        <div class="input-wrapper">
                                            <InputText v-model="newStudent.motherLastName" placeholder="Enter mother's last name" class="modern-input" />
                                            <div class="input-accent"></div>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label class="modern-label">Mother's First Name</label>
                                        <div class="input-wrapper">
                                            <InputText v-model="newStudent.motherFirstName" placeholder="Enter mother's first name" class="modern-input" />
                                            <div class="input-accent"></div>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label class="modern-label">Mother's Middle Name</label>
                                        <div class="input-wrapper">
                                            <InputText v-model="newStudent.motherMiddleName" placeholder="Enter mother's middle name" class="modern-input" />
                                            <div class="input-accent"></div>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <label class="modern-label">Mother's Contact Number</label>
                                        <div class="input-wrapper">
                                            <InputText v-model="newStudent.motherContactNumber" placeholder="Enter mother's contact number" class="modern-input" />
                                            <div class="input-accent"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Previous School Information Section -->
                    <div class="modern-form-section" data-section="previous-school">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="pi pi-book"></i>
                            </div>
                            <div class="section-title">
                                <h3>Previous School Information</h3>
                                <p>Educational background</p>
                            </div>
                        </div>
                        <div class="section-content">
                            <div class="input-grid">
                                <div class="input-group">
                                    <label class="modern-label">Last Grade Completed</label>
                                    <div class="input-wrapper">
                                        <Dropdown v-model="newStudent.lastGradeCompleted" :options="gradeLevels" optionLabel="name" optionValue="code" placeholder="Select last grade completed" class="modern-dropdown" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label class="modern-label">Last School Year Completed</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.lastSchoolYearCompleted" placeholder="e.g., 2023-2024" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                                <div class="input-group full-width">
                                    <label class="modern-label">Last School Attended</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.lastSchoolAttended" placeholder="Enter name of last school attended" class="modern-input" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact & Additional Information Section -->
                    <div class="modern-form-section" data-section="additional-info">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="pi pi-info-circle"></i>
                            </div>
                            <div class="section-title">
                                <h3>Contact & Additional Information</h3>
                                <p>Email and household details</p>
                            </div>
                        </div>
                        <div class="section-content">
                            <div class="input-grid">
                                <div class="input-group full-width">
                                    <label class="modern-label">Email Address *</label>
                                    <div class="input-wrapper">
                                        <InputText v-model="newStudent.emailAddress" placeholder="Enter email address" class="modern-input" type="email" />
                                        <div class="input-accent"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="income-section">
                                <label class="modern-label">Monthly Household Income *</label>
                                <div class="income-grid">
                                    <div v-for="income in incomeOptions" :key="income.value" class="income-card" :class="{ selected: newStudent.householdIncome === income.value }">
                                        <input type="radio" v-model="newStudent.householdIncome" :id="'income_' + income.value" :value="income.value" class="income-input" />
                                        <label :for="'income_' + income.value" class="income-label">
                                            <div class="income-indicator"></div>
                                            <span class="income-text">{{ income.name }}</span>
                                        </label>
                                    </div>
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
                                    <p><span class="font-medium">Student ID:</span> {{ newStudent.studentId }}</p>
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
                                <input type="checkbox" v-model="termsAccepted" id="termsAccepted" class="mr-3 mt-1" />
                                <label for="termsAccepted" class="text-sm text-gray-700 cursor-pointer">
                                    I hereby certify that the information provided above is true and correct to the best of my knowledge. I understand that any false information may result in the rejection of this enrollment application.
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation Buttons -->
                <div class="flex justify-between pt-6 border-t">
                    <Button v-if="currentStep > 1" label="Previous" icon="pi pi-chevron-left" class="p-button-outlined" @click="prevStep" />
                    <div v-else></div>

                    <div class="flex gap-2">
                        <Button label="Cancel" class="p-button-outlined p-button-secondary" @click="newStudentDialog = false" />
                        <Button v-if="currentStep < totalSteps" label="Next" icon="pi pi-chevron-right" iconPos="right" @click="nextStep" />
                        <Button v-else label="Submit Enrollment" icon="pi pi-check" :disabled="!termsAccepted" @click="submitNewStudent" />
                    </div>
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
    transition: all 0.3s ease;
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
    position: relative;
    z-index: 1;
    width: 4rem;
    height: 4rem;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
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

/* Modern Enrollment Dialog Styling */
.modern-enrollment-header {
    padding: 0;
    background: linear-gradient(135deg, #4a90e2 0%, #5e35b1 100%);
    border-radius: 12px 12px 0 0;
    color: white;
    position: relative;
    overflow: hidden;
    margin: 0;
    width: 100%;
    height: 100%;
}

.modern-enrollment-header .header-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
    padding: 1.5rem 2rem;
    width: 100%;
    height: 100%;
    box-sizing: border-box;
}

.modern-enrollment-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    opacity: 0.3;
}

.modern-enrollment-header .school-identity {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.modern-enrollment-header .logo-wrapper {
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 4rem;
    height: 4rem;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.modern-enrollment-header .school-logo-modern {
    font-size: 1.75rem;
    color: white;
}

.modern-enrollment-header .school-details {
    flex: 1;
}

.modern-enrollment-header .school-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    letter-spacing: -0.025em;
    color: white !important;
}

.modern-enrollment-header .school-motto {
    font-size: 1.1rem;
    margin: 0 0 0.75rem 0;
    opacity: 0.9;
    font-weight: 400;
    color: white !important;
}

.modern-enrollment-header .enrollment-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-weight: 700;
    font-size: 0.9rem;
    background: rgba(255, 255, 255, 0.9);
    color: #1e40af;
}

.modern-enrollment-header .academic-year {
    font-size: 0.9rem;
    color: #1e40af;
    opacity: 0.8;
}

.modern-enrollment-header .header-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.modern-enrollment-header .step-indicator-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.modern-enrollment-header .step-counter {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 1rem;
    font-weight: 600;
    color: white;
}

.modern-enrollment-header .step-divider {
    font-size: 1rem;
    font-weight: 600;
    color: white;
    opacity: 0.5;
}

.modern-enrollment-header .step-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: white;
    opacity: 0.8;
}

.modern-enrollment-header .modern-close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    border-radius: 50%;
    padding: 0.75rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    cursor: pointer;
}

.modern-enrollment-header .modern-close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.modern-dialog-content {
    padding: 1.5rem;
}

.progress-section {
    padding: 1.5rem;
    border-radius: 12px;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    margin-bottom: 1rem;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.progress-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-color);
}

.progress-percentage {
    font-size: 1rem;
    font-weight: 500;
    color: var(--text-color-secondary);
}

.progress-track {
    position: relative;
    height: 10px;
    border-radius: 10px;
    background: var(--surface-200);
    overflow: hidden;
}

.progress-fill {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    border-radius: 10px;
    background: linear-gradient(135deg, #4a90e2 0%, #5e35b1 100%);
    transition: width 0.3s ease;
}

.progress-fill .progress-glow {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #4a90e2 0%, #5e35b1 100%);
    border-radius: 10px;
    opacity: 0.5;
}

.progress-steps {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
}

.progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-color-secondary);
}

.progress-step.active {
    color: var(--primary-color);
}

.progress-step.current {
    color: var(--primary-color);
    font-weight: 600;
}

.step-circle {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--surface-200);
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-color-secondary);
    transition: all 0.3s ease;
}

.progress-step.active .step-circle {
    background: var(--primary-color);
    color: white;
}

.progress-step.current .step-circle {
    background: var(--primary-color);
    color: white;
    transform: scale(1.1);
}

.step-circle i {
    font-size: 0.9rem;
    font-weight: 600;
    color: white;
}

.step-name {
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--text-color-secondary);
}

/* Modern Form Styling */
.form-container {
    display: flex;
    flex-direction: column;
    gap: 2rem;
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modern-form-section {
    background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 20px;
    padding: 0;
    box-shadow:
        0 10px 25px rgba(0, 0, 0, 0.08),
        0 4px 10px rgba(0, 0, 0, 0.03),
        0 1px 3px 0 rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(226, 232, 240, 0.8);
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.modern-form-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #10b981, #059669);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modern-form-section:hover::before {
    opacity: 1;
}

.modern-form-section:hover {
    transform: translateY(-5px);
    box-shadow:
        0 20px 40px rgba(0, 0, 0, 0.12),
        0 8px 16px rgba(0, 0, 0, 0.06);
}

.section-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 2rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    position: relative;
    overflow: hidden;
}

.section-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
    opacity: 0.3;
}

.section-icon {
    position: relative;
    z-index: 1;
    width: 3rem;
    height: 3rem;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
}

.section-icon i {
    font-size: 1.25rem;
    color: white;
}

.section-title {
    position: relative;
    z-index: 1;
}

.section-title h3 {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0 0 0.25rem 0;
    color: white;
}

.section-title p {
    font-size: 0.875rem;
    margin: 0;
    opacity: 0.9;
    color: white;
}

.section-content {
    padding: 2rem;
}

.input-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}

.input-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.input-group.full-width {
    grid-column: 1 / -1;
}

.modern-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
}

.input-wrapper {
    position: relative;
}

.modern-input,
.modern-dropdown,
.modern-calendar {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 0.875rem;
    background: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    z-index: 1;
}

.modern-input:focus,
.modern-dropdown:focus,
.modern-calendar:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow:
        0 0 0 3px rgba(79, 70, 229, 0.1),
        0 4px 12px rgba(79, 70, 229, 0.15);
    transform: translateY(-2px);
}

.input-accent {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, #4f46e5, #7c3aed);
    transform: scaleX(0);
    transition: transform 0.3s ease;
    border-radius: 0 0 12px 12px;
}

.modern-input:focus + .input-accent,
.modern-dropdown:focus + .input-accent,
.modern-calendar:focus + .input-accent {
    transform: scaleX(1);
}

.additional-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.radio-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.radio-card {
    position: relative;
    border-radius: 12px;
    border: 2px solid #e5e7eb;
    background: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.radio-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.radio-card.selected::before {
    opacity: 0.1;
}

.radio-card.selected {
    border-color: #4f46e5;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(79, 70, 229, 0.15);
}

.radio-input {
    display: none;
}

.radio-label {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    cursor: pointer;
    font-weight: 500;
    color: #374151;
    transition: color 0.3s ease;
}

.radio-card.selected .radio-label {
    color: #4f46e5;
}

.radio-indicator {
    width: 20px;
    height: 20px;
    border: 2px solid #d1d5db;
    border-radius: 50%;
    background: white;
    position: relative;
    transition: all 0.3s ease;
}

.radio-card.selected .radio-indicator {
    border-color: #4f46e5;
    background: #4f46e5;
}

.radio-card.selected .radio-indicator::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    transform: translate(-50%, -50%);
}

.radio-inline {
    display: flex;
    gap: 1.5rem;
}

.radio-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.radio-input-inline {
    width: 18px;
    height: 18px;
    accent-color: #4f46e5;
}

.radio-label-inline {
    font-size: 0.875rem;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
}

.parent-subsection {
    margin-bottom: 2rem;
}

.parent-subsection:last-child {
    margin-bottom: 0;
}

.subsection-title {
    font-size: 1rem;
    font-weight: 600;
    color: #4f46e5;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e5e7eb;
    position: relative;
}

.subsection-title::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 60px;
    height: 2px;
    background: linear-gradient(90deg, #4f46e5, #7c3aed);
}

.income-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.income-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.income-card {
    position: relative;
    border-radius: 12px;
    border: 2px solid #e5e7eb;
    background: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
}

.income-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, #10b981, #059669);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.income-card.selected::before {
    opacity: 0.1;
}

.income-card.selected {
    border-color: #10b981;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.15);
}

.income-input {
    display: none;
}

.income-label {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    cursor: pointer;
    font-weight: 500;
    color: #374151;
    transition: color 0.3s ease;
}

.income-card.selected .income-label {
    color: #10b981;
}

.income-indicator {
    width: 20px;
    height: 20px;
    border: 2px solid #d1d5db;
    border-radius: 50%;
    background: white;
    position: relative;
    transition: all 0.3s ease;
}

.income-card.selected .income-indicator {
    border-color: #10b981;
    background: #10b981;
}

.income-card.selected .income-indicator::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    transform: translate(-50%, -50%);
}
</style>
