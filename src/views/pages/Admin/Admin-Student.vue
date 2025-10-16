<script setup>
import { useToast } from 'primevue/usetoast';
import QRCode from 'qrcode';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Calendar from 'primevue/calendar';

const toast = useToast();
const router = useRouter();
const students = ref([]);
const grades = ref([]);
const loading = ref(true);
const studentDialog = ref(false);
const deleteStudentDialog = ref(false);
const expandedRows = ref([]);
const qrCodes = ref({});
const student = ref({
    id: null,
    // Personal Information
    firstName: '',
    middleName: '',
    lastName: '',
    extensionName: '',
    name: '',
    birthdate: null,
    age: '',
    gender: 'Male',

    // Academic Information
    schoolYear: '2025-2026',
    gradeLevel: '',
    section: '',
    learnerType: 'New/Move In',
    learnerStatus: 'WITH LRN',
    lastGradeCompleted: '',
    lastSYAttended: '',
    previousSchool: '',
    schoolId: '',

    // LRN and PSA
    lrn: '',
    psaBirthCert: '',

    // Contact Information
    email: '',
    phone: '',
    parentContact: '',

    // Address Information
    address: '',
    houseNo: '',
    street: '',
    barangay: '',
    city: '',
    province: '',

    // Current Address (Detailed)
    currentHouseNo: '',
    currentStreet: '',
    currentBarangay: '',
    currentCity: '',
    currentProvince: '',
    currentCountry: 'Philippines',
    currentZipCode: '',

    // Permanent Address
    sameAsCurrentAddress: true,
    permanentHouseNo: '',
    permanentStreet: '',
    permanentBarangay: '',
    permanentCity: '',
    permanentProvince: '',
    permanentCountry: 'Philippines',
    permanentZipCode: '',

    // Additional Information
    photo: null,
    profilephoto: null,
    enrollmentDate: new Date().toISOString().split('T')[0],
    status: 'Enrolled',
    isActive: true,

    // Basic Education Fields
    placeOfBirth: '',
    motherTongue: '',
    isIndigenous: false,
    indigenousCommunity: '',
    is4PsBeneficiary: false,
    householdID: '',
    hasDisability: false,
    disabilities: [],
    houseIncome: '',

    // Parent's/Guardian's Information
    fatherLastName: '',
    fatherFirstName: '',
    fatherMiddleName: '',
    fatherContactNumber: '',
    fatherOccupation: '',
    motherMaidenLastName: '',
    motherMaidenFirstName: '',
    motherMaidenMiddleName: '',
    motherContactNumber: '',
    motherOccupation: ''
});

const submitted = ref(false);
const termsAccepted = ref(false);
const currentStep = ref(1);
const totalSteps = ref(2);
const successDialog = ref(false);
const showFireworks = ref(false);
const filters = ref({
    grade: null,
    section: null,
    gender: null,
    status: null,
    searchTerm: ''
});
const sections = ref([]);
const totalStudents = ref(0);
const qrCodeDialog = ref(false);
const selectedStudent = ref(null);
const fileInput = ref(null);
const viewStudentDialog = ref(false);
const isEdit = ref(false);
const statusChangeDialog = ref(false);
const selectedStudentForStatus = ref(null);
const newStudentStatus = ref('');
const statusChangeReason = ref('');
const statusEffectiveDate = ref(new Date());

const selectedStudentAge = computed(() => calculateAge(selectedStudent.value?.birthdate));
const originalStudentClone = ref(null);

// Grade levels loaded from database
const gradeLevels = ref([]);

// Sections loaded from database
const allSections = ref([]);
const sectionsByGrade = ref({});

// Computed property to check if any filter is selected
const hasActiveFilter = computed(() => {
    return filters.value.grade !== null || 
           filters.value.section !== null || 
           filters.value.gender !== null || 
           filters.value.status !== null;
});

const disabilityTypes = [
    'Visual Impairment',
    'Hearing Impairment',
    'Learning Disability',
    'Intellectual Disability',
    'Blind',
    'Autism Spectrum Disorder',
    'Low Vision',
    'Speech/Language Disorder',
    'Emotional-Behavioral Disorder',
    'Cerebral Palsy',
    'Orthopedic/Physical Handicap',
    'Special Health Problem/Chronic Disease',
    'Multiple Disorder',
    'Cancer'
];

// Load sections from database
const loadSectionsFromDatabase = async () => {
    try {
        const response = await fetch('http://127.0.0.1:8000/api/sections', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const sectionsData = await response.json();
        console.log('Loaded sections from API:', sectionsData);
        
        allSections.value = sectionsData;
        
        // Group sections by grade (removing duplicates)
        const grouped = {};
        sectionsData.forEach(section => {
            // Extract grade name from curriculum_grade relationship
            let gradeName = 'Unknown';
            if (section.curriculum_grade && section.curriculum_grade.grade) {
                gradeName = section.curriculum_grade.grade.name;
            } else if (section.curriculumGrade && section.curriculumGrade.grade) {
                gradeName = section.curriculumGrade.grade.name;
            }
            
            if (!grouped[gradeName]) {
                grouped[gradeName] = [];
            }
            
            // Only add if not already in the array (prevent duplicates)
            if (!grouped[gradeName].includes(section.name)) {
                grouped[gradeName].push(section.name);
            }
        });
        
        sectionsByGrade.value = grouped;
        console.log('Sections grouped by grade (unique):', grouped);
        
        // Set default sections for filter
        if (filters.value.grade) {
            sections.value = grouped[filters.value.grade] || [];
        }
    } catch (error) {
        console.error('Error loading sections:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load sections from database',
            life: 3000
        });
    }
};

// Load grades from database
const loadGradesFromDatabase = async () => {
    try {
        const response = await fetch('http://127.0.0.1:8000/api/grades', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const gradesData = await response.json();
        console.log('Loaded grades from API:', gradesData);
        
        // Format grades for dropdown
        gradeLevels.value = gradesData.map(grade => ({
            name: grade.name,
            code: grade.name
        }));
        
        grades.value = gradeLevels.value;
    } catch (error) {
        console.error('Error loading grades:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load grades from database',
            life: 3000
        });
    }
};

// Load all grade levels and sections
const loadGradesAndSections = async () => {
    try {
        await loadGradesFromDatabase();
        await loadSectionsFromDatabase();
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

// Generate QR code from LRN
const generateQRCode = async (lrn) => {
    if (!lrn) return '';

    // Return cached QR code if available
    if (qrCodes.value[lrn]) {
        return qrCodes.value[lrn];
    }

    try {
        const qrDataUrl = await QRCode.toDataURL(lrn, {
            width: 128,
            margin: 1,
            color: {
                dark: '#4a90e2',
                light: '#ffffff'
            }
        });
        // Cache the QR code
        qrCodes.value[lrn] = qrDataUrl;
        return qrDataUrl;
    } catch (error) {
        console.error('Error generating QR code:', error);
        return '';
    }
};

// Navigate to enrollment statistics page
const viewEnrollmentStats = () => {
    if (selectedStudent.value) {
        router.push({
            path: '/admin-student-statistics',
            query: {
                name: selectedStudent.value.name,
                photo: selectedStudent.value.photo || ''
            }
        });
        // Set QR codes from backend data
        students.value.forEach((student) => {
            if (student.qrCodePath && student.lrn) {
                qrCodes.value[student.lrn] = student.qrCodePath;
            }
        });
        // Generate QR codes for students without backend QR codes
        generateAllQRCodes();
    }
};

// Generate QR codes for all students
const generateAllQRCodes = async () => {
    for (const student of students.value) {
        if (student.lrn && !qrCodes.value[student.lrn]) {
            await generateQRCode(student.lrn);
        }
    }
};

// Get proper photo URL for student
const getStudentPhotoUrl = (student) => {
    console.log('Student photo data length:', student.photo ? student.photo.length : 'null', 'for student:', student.name);

    if (!student.photo || student.photo === 'N/A' || student.photo === '' || student.photo === null || student.photo === '/demo/images/student-photo.jpg') {
        console.log('Using placeholder for student:', student.name);
        return 'demo/images/student-photo.jpg';
    }

    // If it's base64 data, validate it's properly formatted
    if (student.photo.startsWith('data:image')) {
        // Check if base64 data is valid (not too short, has proper format)
        if (student.photo.length < 100 || !student.photo.includes('base64,')) {
            console.log('Invalid base64 data for student:', student.name, 'using placeholder');
            return 'demo/images/student-photo.jpg';
        }
        return student.photo;
    }

    // If it's a full URL, return as is
    if (student.photo.startsWith('http')) {
        return student.photo;
    }

    // If it's a path starting with /, make it absolute with backend URL
    if (student.photo.startsWith('/')) {
        return `http://127.0.0.1:8000${student.photo}`;
    }

    // If it's just a filename in photos directory
    if (student.photo.includes('photos/')) {
        return `http://127.0.0.1:8000/${student.photo}`;
    }

    // Default fallback
    console.log('Using fallback placeholder for student:', student.name);
    return 'demo/images/student-photo.jpg';
};

// Handle photo loading errors
const handlePhotoError = (event) => {
    const img = event.target;
    img.src = 'demo/images/student-photo.jpg';
};

// Generate QR code for specific student
const generateStudentQR = async (student) => {
    if (student.id) {
        try {
            // Import QRCodeAPIService
            const { QRCodeAPIService } = await import('@/router/service/QRCodeAPIService');

            // Generate QR code using new API
            const result = await QRCodeAPIService.generateQRCode(student.id);
            console.log('QR code generated:', result);

            if (result.success) {
                // Update the student's QR code path to use the new API endpoint
                const qrImageUrl = QRCodeAPIService.getQRCodeImageURL(student.id);
                qrCodes.value[student.lrn || student.id] = qrImageUrl;

                // Update the student object if needed
                const studentIndex = students.value.findIndex((s) => s.id === student.id);
                if (studentIndex !== -1) {
                    students.value[studentIndex].qrCodePath = qrImageUrl;
                }

                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: `QR code generated for ${student.firstName} ${student.lastName}`,
                    life: 3000
                });

                // Navigate to Student QR Codes page to show the generated QR code
                router.push('/teacher/student-qrcodes');
            } else {
                throw new Error('Failed to generate QR code');
            }
        } catch (error) {
            console.error('Error generating QR code:', error);
            toast.add({
                severity: 'error',
                summary: 'QR Generation Failed',
                detail: 'Could not generate QR code',
                life: 3000
            });
        }
    }
};

// Load all students from database via API
const loadStudents = async () => {
    try {
        loading.value = true;

        // Fetch students from Laravel API
        const response = await fetch('http://127.0.0.1:8000/api/students', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const apiStudents = await response.json();
        console.log('Raw API response:', apiStudents);

        // Format students data for frontend
        const formattedStudents = apiStudents.map((student) => {
            // Map backend field names to frontend field names
            const formatted = {
                ...student,
                // Map qr_code_path from backend to qrCodePath for frontend
                qrCodePath: student.qr_code_path ? `http://127.0.0.1:8000/${student.qr_code_path}` : null,
                // Ensure other fields are properly mapped
                photo: student.profilePhoto || student.photo,
                contact: student.contactInfo || student.parentContact || '',
                address: student.address || (student.currentAddress ? (typeof student.currentAddress === 'string' ? student.currentAddress : JSON.stringify(student.currentAddress)) : ''),
                // Calculate age if birthdate exists
                age: student.birthdate ? calculateAge(student.birthdate) : student.age || null,
                // Ensure boolean fields are properly set
                isActive: student.is_active !== undefined ? student.is_active : true
            };

            console.log('Formatted student:', formatted);
            return formatted;
        });

        students.value = formattedStudents;

        // Set QR codes from backend data
        students.value.forEach((student) => {
            if (student.qrCodePath && student.lrn) {
                qrCodes.value[student.lrn] = student.qrCodePath;
            }
        });

        totalStudents.value = formattedStudents.length;

        // Update the filter counts
        updateFilterCounts();
    } catch (error) {
        console.error('Error loading student data from API:', error);
        toast.add({
            severity: 'error',
            summary: 'Connection Error',
            detail: 'Failed to load students from database. Please check if the server is running.',
            life: 5000
        });
        students.value = [];
        totalStudents.value = 0;
    } finally {
        loading.value = false;
    }
};

// Update filter counts for UI display
const updateFilterCounts = () => {
    // Count students by grade level
    const gradeCounts = {};
    gradeLevels.value.forEach((grade) => {
        gradeCounts[grade.code] = students.value.filter((s) => s.gradeLevel === grade.code).length;
    });

    // Count students by gender
    const maleCounts = students.value.filter((s) => s.gender && s.gender.toLowerCase() === 'male').length;
    const femaleCounts = students.value.filter((s) => s.gender && s.gender.toLowerCase() === 'female').length;

    console.log('Grade counts:', gradeCounts);
    console.log('Gender counts - Male:', maleCounts, 'Female:', femaleCounts);
};

// Calculate age from birthdate
function calculateAge(birthdate) {
    if (!birthdate || birthdate === 'N/A') return 0;

    const birthDate = new Date(birthdate);
    if (isNaN(birthDate.getTime())) return 0;

    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }

    return age > 0 ? age : 0;
}

// Format address for display
function formatAddress(student) {
    if (student.currentAddress) {
        const addr = student.currentAddress;
        const parts = [addr.houseNo, addr.street, addr.barangay, addr.city, addr.province].filter((part) => part);
        return parts.join(', ');
    }
    return student.address || '';
}

// Get student status display text
function getStudentStatusDisplay(student) {
    // Get the status value
    let status = student.current_status || student.enrollment_status;
    
    // If no enrollment_status, fall back to isActive
    if (!status) {
        return student.isActive ? 'Active' : 'Inactive';
    }
    
    // Format database values for display
    const statusMap = {
        'active': 'Active',
        'dropped_out': 'Dropped Out',
        'transferred_out': 'Transferred Out',
        'transferred_in': 'Transferred In',
        'graduated': 'Graduated'
    };
    
    return statusMap[status] || status;
}

// Get student status severity for Tag component
function getStudentStatusSeverity(student) {
    const status = getStudentStatusDisplay(student).toLowerCase();
    if (status.includes('active') || status.includes('enrolled')) {
        return 'success';
    } else if (status.includes('dropped') || status.includes('inactive')) {
        return 'danger';
    } else if (status.includes('transferred')) {
        return 'warning';
    }
    return 'info';
}

// Get student reason for status change
function getStudentReason(student) {
    // Mapping of dropout reason codes to full descriptions (DepEd SF2 Form)
    // This matches the exact mapping used in SF2ReportController.php
    const reasonMap = {
        // Category A - Family-related
        'a1': 'a.1 Had to take care of siblings',
        'a.1': 'a.1 Had to take care of siblings',
        'a2': 'a.2 Early marriage/pregnancy',
        'a.2': 'a.2 Early marriage/pregnancy',
        'a3': 'a.3 Parents\' attitude toward schooling',
        'a.3': 'a.3 Parents\' attitude toward schooling',
        'a4': 'a.4 Family problems',
        'a.4': 'a.4 Family problems',
        
        // Category B - Health/Personal
        'b1': 'b.1 Illness',
        'b.1': 'b.1 Illness',
        'b2': 'b.2 Disease',
        'b.2': 'b.2 Disease',
        'b3': 'b.3 Death',
        'b.3': 'b.3 Death',
        'b4': 'b.4 Disability',
        'b.4': 'b.4 Disability',
        'b5': 'b.5 Poor academic performance',
        'b.5': 'b.5 Poor academic performance',
        'b6': 'b.6 Disinterest/lack of ambitions',
        'b.6': 'b.6 Disinterest/lack of ambitions',
        'b7': 'b.7 Hunger/Malnutrition',
        'b.7': 'b.7 Hunger/Malnutrition',
        
        // Category C - School Environment
        'c1': 'c.1 Teacher Factor',
        'c.1': 'c.1 Teacher Factor',
        'c2': 'c.2 Physical condition of classroom',
        'c.2': 'c.2 Physical condition of classroom',
        'c3': 'c.3 Peer Factor',
        'c.3': 'c.3 Peer Factor',
        
        // Category D - External Factors
        'd1': 'd.1 Distance from home to school',
        'd.1': 'd.1 Distance from home to school',
        'd2': 'd.2 Armed conflict (incl. Tribal wars & clan feuds)',
        'd.2': 'd.2 Armed conflict (incl. Tribal wars & clan feuds)',
        'd3': 'd.3 Calamities/disaster',
        'd.3': 'd.3 Calamities/disaster',
        'd4': 'd.4 Work-Related',
        'd.4': 'd.4 Work-Related',
        'd5': 'd.5 Transferred/work',
        'd.5': 'd.5 Transferred/work'
    };
    
    if (student.dropout_reason) {
        const code = student.dropout_reason.toLowerCase().trim();
        return reasonMap[code] || student.dropout_reason;
    }
    if (student.dropout_reason_category) {
        return student.dropout_reason_category;
    }
    return null;
}

// Update sections when grade changes
function updateSections() {
    if (filters.value.grade) {
        sections.value = sectionsByGrade.value[filters.value.grade] || [];
        filters.value.section = null; // Reset section when grade changes
    } else {
        sections.value = [];
    }
}

// Reset all filters
function resetFilters() {
    filters.value = {
        grade: null,
        section: null,
        gender: null,
        status: null,
        searchTerm: ''
    };
    sections.value = [];
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

        // Apply status filter
        if (filters.value.status !== null) {
            const studentStatus = student.current_status || student.enrollment_status || (student.isActive ? 'Active' : 'Inactive');
            if (studentStatus !== filters.value.status) {
                return false;
            }
        }

        // Apply search term
        if (filters.value.searchTerm) {
            const term = filters.value.searchTerm.toLowerCase();
            return (
                student.name.toLowerCase().includes(term) || student.studentId.toString().includes(term) || (student.firstName && student.firstName.toLowerCase().includes(term)) || (student.lastName && student.lastName.toLowerCase().includes(term))
            );
        }

        return true;
    });
});

// Save student to database via API
const saveStudent = async () => {
    submitted.value = true;

    // Check terms acceptance first
    if (!termsAccepted.value) {
        return;
    }

    // Validation - only check for name and grade level
    const hasName = student.value.firstName?.trim() || student.value.name?.trim();
    if (!hasName) {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: 'Please enter at least a first name',
            life: 3000
        });
        return;
    }

    if (!student.value.gradeLevel) {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: 'Please select a grade level',
            life: 3000
        });
        return;
    }

    // Auto-generate full name if not provided
    if (!student.value.name && student.value.firstName) {
        updateFullName();
    }

    // Auto-generate address if not provided
    if (!student.value.address && student.value.houseNo) {
        updateFullAddress();
    }

    // Calculate age if birthdate is provided
    if (student.value.birthdate && !student.value.age) {
        calculateStudentAge();
    }

    try {
        // Prepare student data for API (using correct camelCase field names)
        const studentId = student.value.studentId || `STU${String(Date.now()).slice(-5)}`;
        const studentData = {
            // Required fields for Laravel validation
            name: student.value.name || `${student.value.firstName || ''} ${student.value.lastName || ''}`.trim() || 'Unknown',
            gradeLevel: student.value.gradeLevel || 'Grade 1',
            section: student.value.section || 'Default',

            // Optional fields
            firstName: student.value.firstName || '',
            middleName: student.value.middleName || '',
            lastName: student.value.lastName || '',
            extensionName: student.value.extensionName || '',
            birthdate: (() => {
                if (!student.value.birthdate) return null;
                try {
                    const date = new Date(student.value.birthdate);
                    if (isNaN(date.getTime())) return null;
                    return date.toISOString().split('T')[0];
                } catch (e) {
                    return null;
                }
            })(),
            age: (() => {
                const ageValue = student.value.age || calculateAge(student.value.birthdate);
                return ageValue && ageValue !== 'N/A' && !isNaN(ageValue) ? parseInt(ageValue) : 0;
            })(),
            gender: student.value.gender || 'Male',
            sex: student.value.gender || 'Male',
            email: student.value.email && student.value.email.trim() !== '' ? student.value.email.trim() : null,
            contactInfo: student.value.phone || '',
            parentContact: student.value.parentContact || student.value.phone || '',
            address: student.value.address || '',
            lrn: student.value.lrn || `${new Date().getFullYear()}${String(Date.now()).slice(-8)}`,
            studentId: studentId,
            // Include base64 photo data for backend processing
            photo: student.value.photo || null,
            profilePhoto: student.value.photo || student.value.profilePhoto || null,
            status: student.value.status || 'Enrolled',
            isActive: student.value.isActive !== undefined ? student.value.isActive : true,
            enrollmentDate: (() => {
                try {
                    const date = new Date(student.value.enrollmentDate);
                    if (isNaN(date.getTime())) return new Date().toISOString().split('T')[0];
                    return date.toISOString().split('T')[0];
                } catch (e) {
                    return new Date().toISOString().split('T')[0];
                }
            })(),
            admissionDate: (() => {
                if (!student.value.admissionDate || student.value.admissionDate === 'N/A') {
                    return new Date().toISOString().split('T')[0];
                }
                try {
                    const date = new Date(student.value.admissionDate);
                    if (isNaN(date.getTime())) return new Date().toISOString().split('T')[0];
                    return date.toISOString().split('T')[0];
                } catch (e) {
                    return new Date().toISOString().split('T')[0];
                }
            })(),

            // Basic Education Fields
            placeOfBirth: student.value.placeOfBirth || '',
            motherTongue: student.value.motherTongue || '',
            isIndigenous: student.value.isIndigenous || false,
            indigenousCommunity: student.value.indigenousCommunity || '',
            is4PsBeneficiary: student.value.is4PsBeneficiary || false,
            householdID: student.value.householdID || '',
            hasDisability: student.value.hasDisability || false,
            disabilities: student.value.disabilities || [],
            houseIncome: student.value.houseIncome || '',

            // Parent Information
            fatherLastName: student.value.fatherLastName || '',
            fatherFirstName: student.value.fatherFirstName || '',
            fatherMiddleName: student.value.fatherMiddleName || '',
            fatherContactNumber: student.value.fatherContactNumber || '',
            fatherOccupation: student.value.fatherOccupation || '',
            motherMaidenLastName: student.value.motherMaidenLastName || '',
            motherMaidenFirstName: student.value.motherMaidenFirstName || '',
            motherMaidenMiddleName: student.value.motherMaidenMiddleName || '',
            motherContactNumber: student.value.motherContactNumber || '',
            motherOccupation: student.value.motherOccupation || ''
        };

        console.log('Saving student data:', studentData);
        console.log('Student ID for update:', student.value.id);

        let response;
        if (student.value.id) {
            // Update existing student
            console.log('Updating existing student with ID:', student.value.id);
            response = await fetch(`http://127.0.0.1:8000/api/students/${student.value.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(studentData)
            });
        } else {
            // Create new student
            console.log('Creating new student');
            response = await fetch('http://127.0.0.1:8000/api/students', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(studentData)
            });
        }

        console.log('API Response status:', response.status);
        console.log('API Response headers:', response.headers);

        if (!response.ok) {
            const errorText = await response.text();
            console.error('API Error Response:', errorText);
            let errorData;
            try {
                errorData = JSON.parse(errorText);
            } catch (e) {
                errorData = { message: errorText };
            }
            throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
        }

        // Success - database save worked
        const result = await response.json();
        console.log('Student saved successfully:', result);

        // Reload students from database to get updated data
        await loadStudents();

        // Show success dialog with fireworks for new enrollments FIRST
        if (!result.id) {
            successDialog.value = true;
            showFireworks.value = true;
            setTimeout(() => {
                showFireworks.value = false;
            }, 5000);
        } else {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Student updated successfully!',
                life: 3000
            });
        }

        // Close dialog and reset form AFTER showing success
        studentDialog.value = false;
        resetStudentForm();
        currentStep.value = 1;
        termsAccepted.value = false;
        submitted.value = false;
    } catch (error) {
        console.error('Error saving student to database:', error);

        // Show detailed error message
        let errorMessage = 'Failed to save student to database.';
        if (error.message) {
            errorMessage += ` Error: ${error.message}`;
        }

        toast.add({
            severity: 'error',
            summary: 'Database Error',
            detail: errorMessage,
            life: 8000
        });

        // Don't close dialog on error so user can retry
        submitted.value = false;
    }
};

// Edit student - populate form with existing data
function editStudent(studentData) {
    // Map existing student data to the comprehensive form structure
    student.value = {
        id: studentData.id,
        // Personal Information
        firstName: studentData.firstName || studentData.name?.split(' ')[0] || '',
        middleName: studentData.middleName || '',
        lastName: studentData.lastName || studentData.name?.split(' ').slice(1).join(' ') || '',
        extensionName: studentData.extensionName || '',
        name: studentData.name || '',
        birthdate: studentData.birthdate && studentData.birthdate !== 'N/A' ? new Date(studentData.birthdate) : null,
        age: studentData.age || '',
        gender: studentData.gender || 'Male',

        // Academic Information
        schoolYear: studentData.schoolYear || '2025-2026',
        gradeLevel: studentData.gradeLevel || '',
        section: studentData.section || '',
        learnerType: studentData.learnerType || 'New/Move In',
        learnerStatus: studentData.learnerStatus || 'WITH LRN',
        lastGradeCompleted: studentData.lastGradeCompleted || '',
        lastSYAttended: studentData.lastSYAttended || '',
        previousSchool: studentData.previousSchool || '',
        schoolId: studentData.schoolId || '',

        // LRN and PSA
        lrn: studentData.lrn || '',
        psaBirthCert: studentData.psaBirthCert || '',

        // Contact Information
        email: studentData.email || '',
        phone: studentData.contact || studentData.phone || '',
        parentContact: studentData.parentContact || '',

        // Address Information
        address: studentData.address || '',
        houseNo: studentData.houseNo || '',
        street: studentData.street || '',
        barangay: studentData.barangay || '',
        city: studentData.city || '',
        province: studentData.province || '',

        // Additional Information
        photo: studentData.photo || null,
        profilephoto: studentData.profilephoto || null,
        enrollmentDate: studentData.enrollmentDate || new Date().toISOString().split('T')[0],
        status: studentData.status || 'Enrolled',
        isActive: studentData.isActive !== undefined ? studentData.isActive : true,

        // Basic Education Fields
        motherTongue: studentData.motherTongue || '',
        isIndigenous: studentData.isIndigenous || false,
        indigenousCommunity: studentData.indigenousCommunity || '',
        is4PsBeneficiary: studentData.is4PsBeneficiary || false,
        householdID: studentData.householdID || '',
        hasDisability: studentData.hasDisability || false,
        disabilities: studentData.disabilities || [],
        houseIncome: studentData.houseIncome || ''
    };

    // Update sections based on grade level
    if (student.value.gradeLevel) {
        updateStudentSections();
    }
    
    studentDialog.value = true;
}

// Confirm delete student
function confirmDeleteStudent(studentData) {
    student.value = { ...studentData };
    deleteStudentDialog.value = true;
}

// Open status change dialog
function openStatusChangeDialog(studentData) {
    selectedStudentForStatus.value = { ...studentData };
    newStudentStatus.value = studentData.enrollment_status || 'active';
    statusChangeReason.value = studentData.dropout_reason || '';
    // Set effective date to existing date or today
    statusEffectiveDate.value = studentData.status_effective_date ? new Date(studentData.status_effective_date) : new Date();
    statusChangeDialog.value = true;
}

// Save student status change
const saveStatusChange = async () => {
    try {
        if (!selectedStudentForStatus.value) return;
        
        // Format the effective date properly
        const effectiveDate = statusEffectiveDate.value instanceof Date 
            ? statusEffectiveDate.value.toISOString().split('T')[0]
            : new Date(statusEffectiveDate.value).toISOString().split('T')[0];
        
        const updateData = {
            ...selectedStudentForStatus.value,
            current_status: newStudentStatus.value,
            enrollment_status: newStudentStatus.value,
            dropout_reason: statusChangeReason.value,
            status_changed_date: new Date().toISOString().split('T')[0],
            status_effective_date: effectiveDate
        };

        const response = await fetch(`http://127.0.0.1:8000/api/students/${selectedStudentForStatus.value.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(updateData)
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Student status updated successfully!',
            life: 3000
        });

        statusChangeDialog.value = false;
        await loadStudents();
    } catch (error) {
        console.error('Error updating student status:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update student status. Please try again.',
            life: 5000
        });
    }
};

// Delete student from database via API
const deleteStudent = async () => {
    try {
        // Delete from database first
        const response = await fetch(`http://127.0.0.1:8000/api/students/${student.value.id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        console.log('Student deleted from database:', student.value.id);

        // Reload students from database
        await loadStudents();

        deleteStudentDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Student deleted successfully!',
            life: 3000
        });
    } catch (error) {
        console.error('Error deleting student from database:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete student from database. Please check your connection.',
            life: 5000
        });
    }
};

// View student details dialog
function viewStudentDetails(studentData) {
    selectedStudent.value = studentData;
    viewStudentDialog.value = true;
}

// Edit student from dialog (opens enrollment form)
function editStudentFromDialog() {
    if (selectedStudent.value) {
        // Close the view dialog first
        viewStudentDialog.value = false;
        // Open the edit form with the selected student data
        editStudent(selectedStudent.value);
    }
}

// Row click handler to open student info dialog
function onRowClick(event) {
    viewStudentDetails(event.data);
}

// Show QR code in a dialog
function showQRCode(studentData) {
    selectedStudent.value = studentData;
    qrCodeDialog.value = true;
}

// Print QR code
function printQRCode() {
    if (!selectedStudent.value || !qrCodes.value[selectedStudent.value.lrn]) return;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Student LRN QR Code</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    text-align: center;
                    padding: 20px;
                }
                .container {
                    max-width: 400px;
                    margin: 0 auto;
                    border: 1px solid #ddd;
                    padding: 20px;
                    border-radius: 8px;
                }
                .qr-code {
                    width: 200px;
                    height: 200px;
                    margin: 0 auto 20px;
                }
                h2 {
                    margin-bottom: 5px;
                }
                p {
                    margin: 5px 0;
                    color: #666;
                }
                .school-name {
                    font-size: 18px;
                    font-weight: bold;
                    margin-bottom: 20px;
                }
                @media print {
                    .no-print {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="school-name">Learners Attendance Monitoring System</div>
                <img src="${qrCodes.value[selectedStudent.value.lrn]}" class="qr-code" />
                <h2>${selectedStudent.value.name}</h2>
                <p>LRN: ${selectedStudent.value.lrn}</p>
                <p>${selectedStudent.value.gradeLevel} - ${selectedStudent.value.section}</p>
            </div>
            <div class="no-print" style="margin-top: 20px;">
                <button onclick="window.print()">Print</button>
                <button onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Generate Temporary ID card
function generateTempId() {
    if (!selectedStudent.value) return;

    const student = selectedStudent.value;
    const qrSrc = qrCodes.value[student.lrn] || '';
    const today = new Date().toISOString().slice(0, 10);

    // Debug: Log student data to console
    console.log('Student data for ID generation:', {
        name: student.name,
        gradeLevel: student.gradeLevel,
        section: student.section,
        lrn: student.lrn,
        birthdate: student.birthdate
    });

    const win = window.open('', '_blank');
    const timestamp = Date.now(); // Cache buster

    // Force clear any cached content
    win.document.open();
    win.document.clear();
    win.document.write(`
        <html>
        <head>
            <title>Temporary ID - ${student.name} - ${timestamp}</title>
            <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
            <meta http-equiv="Pragma" content="no-cache">
            <meta http-equiv="Expires" content="0">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { 
                    font-family: 'Arial', sans-serif; 
                    background: #f0f2f5;
                    padding: 20px;
                    min-height: 100vh;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }
                
                .card-wrapper { 
                    display: flex; 
                    gap: 30px; 
                    justify-content: center;
                    align-items: center;
                }
                
                .id-card {
                    width: 320px;
                    height: 600px;
                    border-radius: 15px;
                    overflow: hidden;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    position: relative;
                }
                
                /* FRONT SIDE */
                .front {
                    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
                    position: relative;
                    overflow: hidden;
                }
                
                .front::before {
                    content: '';
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    width: 100%;
                    height: 60%;
                    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1000 320'%3E%3Cpath fill='%23ffffff' fill-opacity='0.1' d='M0,96L48,112C96,128,192,160,288,186.7C384,213,480,235,576,213.3C672,192,768,128,864,128C960,128,1056,192,1152,208C1248,224,1344,192,1392,176L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z'%3E%3C/path%3E%3C/svg%3E") no-repeat bottom;
                    background-size: cover;
                }
                
                .front::after {
                    content: '';
                    position: absolute;
                    bottom: -50px;
                    right: -50px;
                    width: 200px;
                    height: 200px;
                    background: rgba(255,255,255,0.05);
                    border-radius: 50%;
                }
                
                .school-logo {
                    position: absolute;
                    top: 20px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 60px;
                    height: 60px;
                    background: white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                }
                
                .school-logo img {
                    width: 40px;
                    height: 40px;
                    object-fit: contain;
                }
                
                .school-header {
                    text-align: center;
                    padding: 20px 20px 30px;
                    color: white;
                }
                
                .school-name {
                    font-size: 18px;
                    font-weight: bold;
                    margin-top: 75px;
                    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
                }
                
                .student-photo {
                    width: 100px;
                    height: 100px;
                    border-radius: 50%;
                    object-fit: cover;
                    border: 4px solid white;
                    display: block;
                    margin: 15px auto;
                    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
                }
                
                .student-name {
                    color: white;
                    font-size: 20px;
                    font-weight: bold;
                    text-align: center;
                    margin: 10px 0 5px;
                    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
                }
                
                .name-label {
                    color: rgba(255,255,255,0.8);
                    font-size: 11px;
                    text-align: center;
                    margin-bottom: 15px;
                }
                
                .qr-code {
                    width: 160px;
                    height: 160px;
                    background: white;
                    border-radius: 8px;
                    padding: 8px;
                    display: block;
                    margin: 15px auto;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                }
                
                .student-info {
                    background: rgba(255,255,255,0.95);
                    margin: 0 20px 15px;
                    padding: 12px;
                    border-radius: 6px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    clear: both;
                }
                
                .info-label {
                    color: #2a5298;
                    font-size: 10px;
                    font-weight: bold;
                    margin-bottom: 1px;
                    text-transform: uppercase;
                }
                
                .info-value {
                    color: #333;
                    font-size: 12px;
                    font-weight: 600;
                    line-height: 1.2;
                }
                
                /* BACK SIDE */
                .back {
                    background: #f8f9fa;
                    color: #333;
                }
                
                .deped-header {
                    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
                    color: white;
                    padding: 20px;
                    text-align: center;
                }
                
                .deped-logo {
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                
                .deped-subtitle {
                    font-size: 12px;
                    opacity: 0.9;
                }
                
                .back-content {
                    padding: 25px 20px;
                }
                
                .info-section {
                    margin-bottom: 20px;
                }
                
                .section-title {
                    color: #2a5298;
                    font-weight: bold;
                    font-size: 13px;
                    margin-bottom: 8px;
                    text-transform: uppercase;
                }
                
                .info-box {
                    background: white;
                    padding: 10px;
                    border-radius: 6px;
                    border-left: 4px solid #2a5298;
                    font-size: 12px;
                    line-height: 1.4;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                }
                
                .signature-area {
                    text-align: center;
                    margin: 25px 0;
                    padding: 15px;
                    background: white;
                    border-radius: 8px;
                    border: 2px dashed #ddd;
                }
                
                .signature-line {
                    border-bottom: 2px solid #333;
                    width: 120px;
                    margin: 0 auto 8px;
                    height: 25px;
                }
                
                .signature-label {
                    font-size: 11px;
                    font-weight: bold;
                    color: #666;
                }
                
                .validity-box {
                    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
                    color: white;
                    padding: 12px;
                    text-align: center;
                    border-radius: 8px;
                    font-weight: bold;
                    font-size: 13px;
                    margin-bottom: 10px;
                }
                
                .non-transferable {
                    text-align: center;
                    font-size: 10px;
                    color: #666;
                    font-style: italic;
                }
                
                /* Buttons */
                .no-print {
                    text-align: center;
                    margin-top: 30px;
                }
                
                .btn {
                    background: linear-gradient(135deg, #2a5298, #1e3c72);
                    color: white;
                    border: none;
                    padding: 12px 24px;
                    margin: 0 8px;
                    border-radius: 25px;
                    cursor: pointer;
                    font-weight: bold;
                    transition: all 0.3s ease;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                }
                
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 16px rgba(0,0,0,0.3);
                }
                
                /* Print Styles */
                @media print {
                    * { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; }
                    body { 
                        background: white !important; 
                        padding: 0 !important; 
                        margin: 0 !important;
                    }
                    .card-wrapper { 
                        gap: 15px; 
                        page-break-inside: avoid;
                        display: flex !important;
                        flex-direction: row !important;
                    }
                    .id-card { 
                        box-shadow: none !important; 
                        page-break-inside: avoid;
                        break-inside: avoid;
                    }
                    .front {
                        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%) !important;
                        -webkit-print-color-adjust: exact !important;
                        color-adjust: exact !important;
                    }
                    .back {
                        background: #f8f9fa !important;
                        -webkit-print-color-adjust: exact !important;
                        color-adjust: exact !important;
                    }
                    .deped-header {
                        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%) !important;
                        -webkit-print-color-adjust: exact !important;
                        color-adjust: exact !important;
                    }
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            <div class="card-wrapper">
                <!-- FRONT SIDE -->
                <div class="id-card front">
                    <div class="school-logo">
                        <img src="/demo/images/logo.png" alt="School Logo" />
                    </div>
                    
                    <div class="school-header">
                        <div class="school-name">Naawan Central School</div>
                    </div>
                    
                    <img src="${student.photo ? (student.photo.startsWith('data:') ? student.photo : `http://localhost:8000/${student.photo}`) : 'https://via.placeholder.com/120x120?text=Photo'}" class="student-photo" />
                    
                    <div class="student-name">${student.name.toUpperCase()}</div>
                    <div class="name-label">NAME</div>
                    
                    <img src="${qrSrc}" class="qr-code" />
                    
                    <div class="student-info">
                        <div class="info-label">LRN:</div>
                        <div class="info-value">${student.studentId || student.lrn}</div>
                    </div>
                    
                </div>
                
                <!-- BACK SIDE -->
                <div class="id-card back">
                    <div class="deped-header">
                        <div class="deped-logo">DepED</div>
                        <div class="deped-subtitle">DEPARTMENT OF EDUCATION</div>
                    </div>
                    
                    <div class="back-content">
                        <div class="info-section">
                            <div class="section-title">Emergency Contact</div>
                            <div class="info-box">
                                <strong>PARENT/GUARDIAN:</strong> ${student.parentName || 'MARK MILLER'}<br>
                                <strong>PHONE:</strong> ${student.contact || '484-421-377'}
                            </div>
                        </div>
                        
                        <div class="info-section">
                            <div class="section-title">Allergies/Medical Conditions:</div>
                            <div class="info-box">${student.medicalConditions || 'PEANUT ALLERGY'}</div>
                        </div>
                        
                        <div class="info-section">
                            <div class="section-title">Home Address:</div>
                            <div class="info-box">${student.address || '1244 HORSESHOE LANE NEWARK, PA 19714'}</div>
                        </div>
                        
                        <div class="info-section">
                            <div class="section-title">School Year:</div>
                            <div class="info-box">${new Date().getFullYear()}-${new Date().getFullYear() + 1}</div>
                        </div>
                        
                        <div class="signature-area">
                            <div class="signature-line"></div>
                            <div class="signature-label">SIGNATURE</div>
                        </div>
                        
                        <div class="validity-box">
                            VALIDITY PERIOD: AY ${new Date().getFullYear()}-${new Date().getFullYear() + 1}
                        </div>
                        
                        <div class="non-transferable">
                            THIS ID CARD IS NON-TRANSFERABLE
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="no-print">
                <button class="btn" onclick="window.print()">Print</button>
                <button class="btn" onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
    `);
    win.document.close();
}
function updatePhoto(studentData) {
    if (fileInput.value) {
        fileInput.value.click();
    }
}

async function handlePhotoUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Validate file type and size
    if (!file.type.startsWith('image/')) {
        toast.add({
            severity: 'error',
            summary: 'Invalid File',
            detail: 'Please select an image file',
            life: 3000
        });
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        // 5MB limit
        toast.add({
            severity: 'error',
            summary: 'File Too Large',
            detail: 'Please select an image smaller than 5MB',
            life: 3000
        });
        return;
    }

    const reader = new FileReader();
    reader.onload = async () => {
        try {
            // Update the photo in the UI immediately
            selectedStudent.value.photo = reader.result;

            // Use the original data from the database for the update
            const originalData = selectedStudent.value.originalData || {};

            // Prepare data for database update - use original data and only update the photo
            const studentData = {
                ...originalData,
                photo: reader.result // Send as 'photo' field for backend processing
            };

            console.log('Updating student photo for ID:', selectedStudent.value.id);

            // Save to database
            const response = await fetch(`http://127.0.0.1:8000/api/students/${selectedStudent.value.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(studentData)
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('API Error Response:', errorText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            console.log('Photo updated successfully:', result);

            // Reload students to get updated data
            await loadStudents();

            toast.add({
                severity: 'success',
                summary: 'Photo Updated',
                detail: 'Student photo updated successfully!',
                life: 3000
            });
        } catch (error) {
            console.error('Error updating photo:', error);

            // Revert the UI change on error
            if (selectedStudent.value.originalData?.profilePhoto) {
                selectedStudent.value.photo = selectedStudent.value.originalData.profilePhoto;
            }

            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to update photo. Please try again.',
                life: 3000
            });
        }
    };
    reader.readAsDataURL(file);
}
function startEditProfile() {
    originalStudentClone.value = { ...selectedStudent.value };
    isEdit.value = true;
}
function cancelInlineEdit() {
    // revert changes
    selectedStudent.value = { ...originalStudentClone.value };
    isEdit.value = false;
}
async function saveInlineProfile() {
    try {
        // Calculate age if birthdate changed
        if (selectedStudent.value.birthdate) {
            selectedStudent.value.age = calculateAge(selectedStudent.value.birthdate);
        }

        // Prepare student data for API update (matching Laravel validation requirements)
        const studentData = {
            // Required fields for Laravel validation
            name: selectedStudent.value.name || `${selectedStudent.value.firstName || ''} ${selectedStudent.value.lastName || ''}`.trim() || 'Unknown',
            gradeLevel: selectedStudent.value.gradeLevel || 'Grade 1',
            section: selectedStudent.value.section || 'Default',

            // Optional fields
            firstName: selectedStudent.value.firstName || selectedStudent.value.name?.split(' ')[0] || '',
            middleName: selectedStudent.value.middleName || '',
            lastName: selectedStudent.value.lastName || selectedStudent.value.name?.split(' ').slice(1).join(' ') || '',
            extensionName: selectedStudent.value.extensionName || '',
            birthdate: selectedStudent.value.birthdate ? new Date(selectedStudent.value.birthdate).toISOString().split('T')[0] : null,
            age: selectedStudent.value.age || calculateAge(selectedStudent.value.birthdate) || 0,
            gender: selectedStudent.value.gender || 'Male',
            sex: selectedStudent.value.gender || 'Male',
            email: selectedStudent.value.email && selectedStudent.value.email.trim() !== '' ? selectedStudent.value.email.trim() : null,
            contactInfo: selectedStudent.value.contact || selectedStudent.value.phone || '',
            parentContact: selectedStudent.value.parentContact || selectedStudent.value.contact || '',
            address: selectedStudent.value.address || '',
            lrn: selectedStudent.value.lrn || '',
            profilePhoto: selectedStudent.value.photo || selectedStudent.value.profilePhoto || null,
            status: selectedStudent.value.status || 'Enrolled',
            isActive: selectedStudent.value.isActive !== undefined ? selectedStudent.value.isActive : true
        };

        console.log('Sending student data to API:', studentData);

        // Update student in backend database
        console.log('Updating student with ID:', selectedStudent.value.id);
        const response = await fetch(`http://127.0.0.1:8000/api/students/${selectedStudent.value.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(studentData)
        });

        console.log('API Response status:', response.status);

        if (!response.ok) {
            const errorText = await response.text();
            console.error('API Error Response:', errorText);
            let errorData;
            try {
                errorData = JSON.parse(errorText);
            } catch (e) {
                errorData = { message: errorText };
            }
            throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
        }

        const updatedStudent = await response.json();
        console.log('Student updated in database:', updatedStudent);

        // Update QR code if LRN changed and new QR code path is provided
        if (updatedStudent.qr_code_path && selectedStudent.value.lrn) {
            const qrPath = `http://127.0.0.1:8000/${updatedStudent.qr_code_path}`;
            qrCodes.value[selectedStudent.value.lrn] = qrPath;

            // Update the student's QR code path in the table
            const studentIndex = students.value.findIndex((s) => s.id === selectedStudent.value.id);
            if (studentIndex !== -1) {
                students.value[studentIndex].qrCodePath = qrPath;
            }
        }

        // Also update localStorage as backup
        try {
            const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
            const idx = enrolledStudents.findIndex((s) => {
                if (s.studentId && selectedStudent.value.studentId) {
                    return s.studentId === selectedStudent.value.studentId;
                }
                return s.id === selectedStudent.value.id;
            });
            if (idx > -1) {
                enrolledStudents[idx] = { ...selectedStudent.value };
                localStorage.setItem('enrolledStudents', JSON.stringify(enrolledStudents));
            }
        } catch (localError) {
            console.warn('Failed to update localStorage backup:', localError);
        }

        // Reload students from database to reflect changes
        await loadStudents();

        isEdit.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Student updated and saved to database!',
            life: 3000
        });
    } catch (error) {
        console.error('Error updating student in database:', error);

        // Fallback to localStorage only if API fails
        try {
            const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
            const idx = enrolledStudents.findIndex((s) => {
                if (s.studentId && selectedStudent.value.studentId) {
                    return s.studentId === selectedStudent.value.studentId;
                }
                return s.id === selectedStudent.value.id;
            });
            if (idx > -1) {
                selectedStudent.value.age = calculateAge(selectedStudent.value.birthdate);
                enrolledStudents[idx] = { ...selectedStudent.value };
                localStorage.setItem('enrolledStudents', JSON.stringify(enrolledStudents));
                await loadStudents();
                isEdit.value = false;
                toast.add({
                    severity: 'warn',
                    summary: 'Saved Locally',
                    detail: 'Database unavailable. Changes saved to local storage only.',
                    life: 5000
                });
            }
        } catch (localError) {
            console.error('Failed to save to localStorage:', localError);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to update student in both database and localStorage',
                life: 3000
            });
        }
    }
}

function updateSignature(studentData) {
    toast.add({ severity: 'info', summary: 'Update E-Signature', detail: 'Feature not implemented yet', life: 3000 });
}

// Helper functions for the new enrollment form
function updateFullName() {
    const parts = [student.value.firstName, student.value.middleName, student.value.lastName, student.value.extensionName].filter((part) => part && part.trim());
    student.value.name = parts.join(' ');
}

function updateFullAddress() {
    const parts = [student.value.houseNo, student.value.barangay, student.value.city, student.value.province].filter((part) => part && part.trim());
    student.value.address = parts.join(', ');
}

function updateStudentSections() {
    if (student.value.gradeLevel) {
        sections.value = sectionsByGrade[student.value.gradeLevel] || [];
        student.value.section = ''; // Reset section when grade changes
    } else {
        sections.value = [];
    }
}

function calculateStudentAge() {
    if (student.value.birthdate) {
        student.value.age = calculateAge(student.value.birthdate);
    }
}

function openStudentDialog() {
    // Reset form to ensure clean state
    student.value = {
        id: null,
        // Personal Information
        firstName: '',
        middleName: '',
        lastName: '',
        extensionName: '',
        name: '',
        birthdate: null,
        age: '',
        gender: 'Male',

        // Academic Information
        schoolYear: '2025-2026',
        gradeLevel: '',
        section: '',
        learnerType: 'New/Move In',
        learnerStatus: 'WITH LRN',
        lastGradeCompleted: '',
        lastSYAttended: '',
        previousSchool: '',
        schoolId: '',

        // LRN and PSA
        lrn: '',
        psaBirthCert: '',

        // Contact Information
        email: '',
        phone: '',
        parentContact: '',

        // Address Information
        address: '',
        houseNo: '',
        street: '',
        barangay: '',
        city: '',
        province: '',

        // Additional Information
        photo: null,
        profilephoto: null,
        enrollmentDate: new Date().toISOString().split('T')[0],
        status: '',

        // Basic Education Fields
        motherTongue: '',
        isIndigenous: false,
        indigenousCommunity: '',
        is4PsBeneficiary: false,
        householdID: '',
        hasDisability: false,
        disabilities: [],
        houseIncome: '',

        // Parent Information
        fatherOccupation: '',
        motherOccupation: ''
    };
    studentDialog.value = true;
}

function resetStudentForm() {
    Object.assign(student.value, {
        name: '',
        firstName: '',
        middleName: '',
        lastName: '',
        extensionName: '',
        lrn: '',
        gradeLevel: '',
        section: '',
        gender: '',
        birthdate: null,
        age: '',
        email: '',
        phone: '',
        parentContact: '',
        schoolYear: '2025-2026',
        learnerType: 'New/Move In',
        learnerStatus: 'WITHOUT LRN',
        lastGradeCompleted: '',
        lastSYAttended: '',
        previousSchool: '',
        schoolId: '',
        psaBirthCert: '',
        currentHouseNo: '',
        currentStreet: '',
        currentBarangay: '',
        currentCity: '',
        currentProvince: '',
        currentCountry: 'Philippines',
        currentZipCode: '',
        sameAsCurrentAddress: true,
        permanentHouseNo: '',
        permanentStreet: '',
        permanentBarangay: '',
        permanentCity: '',
        permanentProvince: '',
        permanentCountry: 'Philippines',
        permanentZipCode: '',
        placeOfBirth: '',
        motherTongue: '',
        isIndigenous: false,
        indigenousCommunity: '',
        is4PsBeneficiary: false,
        householdID: '',
        hasDisability: false,
        disabilities: [],
        houseIncome: '',
        fatherLastName: '',
        fatherFirstName: '',
        fatherMiddleName: '',
        fatherContactNumber: '',
        fatherOccupation: '',
        motherMaidenLastName: '',
        motherMaidenFirstName: '',
        motherMaidenMiddleName: '',
        motherContactNumber: '',
        motherOccupation: ''
    });
}

function cancelStudentDialog() {
    openStudentDialog();
    studentDialog.value = false;
    currentStep.value = 1;
    termsAccepted.value = false;
}

const nextStep = () => {
    if (currentStep.value < totalSteps.value) {
        currentStep.value++;
    }
};

const previousStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

// Initialize component
onMounted(() => {
    loadGradesAndSections();
    loadStudents();

    // Set QR codes from backend data first
    students.value.forEach((student) => {
        if (student.qrCodePath && student.lrn) {
            qrCodes.value[student.lrn] = student.qrCodePath;
        }
    });

    // Generate QR codes for students without backend QR codes
    setTimeout(() => {
        generateAllQRCodes();
    }, 500);
});
</script>

<template>
    <input type="file" accept="image/*" ref="fileInput" class="hidden" @change="handlePhotoUpload" />
    <div class="card p-8 shadow-xl rounded-xl bg-white border border-gray-100">
        <!-- Modern Gradient Header -->
        <div class="modern-header-container mb-8">
            <div class="gradient-header">
                <div class="header-content">
                    <div class="header-left">
                        <div class="header-icon">
                            <i class="pi pi-users"></i>
                        </div>
                        <div class="header-text">
                            <h1 class="header-title">Student Management System</h1>
                            <p class="header-subtitle">Naawan Central School</p>
                            <div class="student-count">
                                <i class="pi pi-chart-bar mr-2"></i>
                                Total Enrolled Students: <span class="count-badge">{{ totalStudents }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <div class="search-container">
                            <span class="p-input-icon-left">
                                <i class="pi pi-search" />
                                <InputText v-model="filters.searchTerm" placeholder="Search students..." class="search-input" />
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-6 mb-8 p-4 bg-gray-50 rounded-lg border">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1">Grade Level</label>
                <Dropdown v-model="filters.grade" :options="grades" optionLabel="name" optionValue="code" placeholder="Select Grade" class="w-full" @change="updateSections" />
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1">Section</label>
                <Dropdown v-model="filters.section" :options="sections" placeholder="Select Section" class="w-full" :disabled="!filters.grade" />
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1">Gender</label>
                <Dropdown
                    v-model="filters.gender"
                    :options="[
                        { name: 'Male', value: 'Male' },
                        { name: 'Female', value: 'Female' }
                    ]"
                    optionLabel="name"
                    optionValue="value"
                    placeholder="Select Gender"
                    class="w-full"
                />
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1">Status</label>
                <Dropdown
                    v-model="filters.status"
                    :options="[
                        { name: 'All Statuses', value: null },
                        { name: 'Active', value: 'active' },
                        { name: 'Dropped Out', value: 'dropped_out' },
                        { name: 'Transferred Out', value: 'transferred_out' },
                        { name: 'Transferred In', value: 'transferred_in' },
                        { name: 'Graduated', value: 'graduated' }
                    ]"
                    optionLabel="name"
                    optionValue="value"
                    placeholder="Select Status"
                    class="w-full"
                />
            </div>
            <div class="flex flex-col justify-end min-w-[150px]">
                <Button label="Reset Filters" icon="pi pi-refresh" class="p-button-outlined p-button-secondary h-[42px]" @click="resetFilters" />
            </div>
        </div>

        <!-- Student List -->
        <div class="mt-6">
            <!-- Message when no filter is selected -->
            <div v-if="!hasActiveFilter" class="bg-blue-50 border border-blue-200 rounded-lg p-8 text-center">
                <i class="pi pi-filter text-4xl text-blue-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-blue-900 mb-2">Select a Filter to View Students</h3>
                <p class="text-blue-700">Please select at least one filter (Grade, Section, Gender, or Status) from the dropdown menus above to display the student list.</p>
            </div>

            <!-- Student Table (shown only when filter is active) -->
            <div v-else class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <DataTable
                    :value="filteredStudents"
                    :paginator="true"
                    :rows="10"
                    :rowsPerPageOptions="[5, 10, 20, 50]"
                    :loading="loading"
                    stripedRows
                    showGridlines
                    responsiveLayout="stack"
                    class="p-datatable-sm modern-datatable"
                    :globalFilterFields="['firstName', 'lastName', 'middleName', 'lrn', 'gradeLevel', 'section']"
                >
                    <Column header="#" style="width: 3rem">
                        <template #body="slotProps">
                            <span>{{ slotProps.index + 1 }}</span>
                        </template>
                    </Column>
                    <Column header="Student" style="width: 180px">
                        <template #body="slotProps">
                            <div class="flex align-items-center">
                                <Avatar :image="getStudentPhotoUrl(slotProps.data)" shape="circle" size="large" class="mr-2" />
                                <div>
                                    <div class="font-bold">{{ slotProps.data.name }}</div>
                                    <div class="text-sm text-color-secondary">{{ slotProps.data.studentId }}</div>
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column field="gradeLevel" header="Grade" sortable style="width: 80px" />
                    <Column field="section" header="Section" sortable style="width: 90px" />
                    <Column field="lrn" header="LRN" sortable style="width: 130px">
                        <template #body="slotProps">
                            <span class="font-semibold">{{ slotProps.data.lrn }}</span>
                        </template>
                    </Column>

                    <Column field="age" header="Age" sortable style="width: 50px">
                        <template #body="slotProps">
                            <span>{{ slotProps.data.age }}</span>
                        </template>
                    </Column>
                    <Column header="Gender" sortable style="width: 80px">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.gender" :severity="slotProps.data.gender === 'Male' ? 'info' : 'success'" />
                        </template>
                    </Column>
                    <Column field="email" header="Email" sortable style="width: 160px" />
                    <Column field="contact" header="Contact" style="width: 100px" />
                    <Column header="Status" style="width: 100px">
                        <template #body="slotProps">
                            <div class="flex align-items-center gap-2">
                                <Tag 
                                    :value="getStudentStatusDisplay(slotProps.data)" 
                                    :severity="getStudentStatusSeverity(slotProps.data)" 
                                />
                                <Button
                                    icon="pi pi-pencil"
                                    class="p-button-rounded p-button-text p-button-sm p-button-info"
                                    @click="openStatusChangeDialog(slotProps.data)"
                                    title="Change Student Status"
                                />
                            </div>
                        </template>
                    </Column>
                    <Column header="Reason" style="min-width: 200px; max-width: 300px">
                        <template #body="slotProps">
                            <div class="text-sm" style="white-space: normal; word-wrap: break-word;">
                                {{ getStudentReason(slotProps.data) || 'N/A' }}
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <!-- View Student Details Dialog -->
        <Dialog v-model:visible="viewStudentDialog" modal :style="{ width: '850px' }" :dismissableMask="true">
            <template #header>
                <div class="flex justify-between items-center w-full pr-2">
                    <span class="text-lg font-semibold">Student Information - {{ selectedStudent ? selectedStudent.name : '' }}</span>
                    <div class="flex gap-2">
                        <Button label="Enrollment Statistics" icon="pi pi-chart-bar" class="p-button-primary p-button-sm" @click="viewEnrollmentStats" />
                        <Button label="Generate Temporary ID" icon="pi pi-id-card" class="p-button-danger p-button-sm" @click="generateTempId" />
                    </div>
                </div>
            </template>

            <div v-if="selectedStudent" class="grid md:grid-cols-3 gap-6 p-4">
                <!-- Left column -->
                <div class="flex flex-col items-center space-y-4">
                    <p class="text-gray-600 font-medium">Temporary ID Photo</p>
                    <img v-if="selectedStudent.photo && selectedStudent.photo !== 'N/A'" :src="getStudentPhotoUrl(selectedStudent)" alt="Student Photo" class="w-48 h-48 rounded-full object-cover ring-2 ring-gray-300" @error="handlePhotoError" />
                    <div v-else class="w-48 h-48 rounded-full bg-gray-200 flex items-center justify-center text-gray-500">
                        <i class="pi pi-user text-4xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-600 font-medium">QR Code</p>
                    <div v-if="selectedStudent.lrn" class="flex flex-col items-center">
                        <img v-if="qrCodes[selectedStudent.lrn]" :src="qrCodes[selectedStudent.lrn]" class="w-48 h-48 border rounded-md object-contain" alt="QR Code" />
                        <div v-else class="w-48 h-48 border rounded-md bg-gray-100 flex items-center justify-center">
                            <Button label="Generate QR" icon="pi pi-qrcode" class="p-button-sm p-button-outlined" @click="generateStudentQR(selectedStudent)" />
                        </div>
                    </div>
                    <p v-else class="text-xs text-gray-400">No LRN Available</p>
                </div>

                <!-- Right column -->
                <div v-if="!isEdit" class="md:col-span-2 space-y-2">
                    <h2 class="font-bold text-2xl mb-1">{{ selectedStudent.name }}</h2>
                    <p class="text-gray-600 mb-3">{{ selectedStudent.studentId }}</p>
                    <hr />
                    <div class="space-y-2 text-sm mt-2">
                        <p><span class="font-semibold">Grade & Section:</span> {{ selectedStudent.gradeLevel }} - {{ selectedStudent.section }}</p>
                        <p><span class="font-semibold">Sex:</span> {{ selectedStudent.gender }}</p>
                        <p><span class="font-semibold">Date of Birth:</span> {{ selectedStudent.birthdate || 'N/A' }}</p>
                        <p><span class="font-semibold">Age:</span> {{ selectedStudentAge || 'N/A' }}</p>
                        <p><span class="font-semibold">Enrollment Date:</span> {{ selectedStudent.enrollmentDate }}</p>
                        <p><span class="font-semibold">LRN:</span> {{ selectedStudent.lrn }}</p>
                        <p>
                            <span class="font-semibold">Status:</span>
                            <Tag :value="selectedStudent.isActive ? 'Active' : 'Inactive'" :severity="selectedStudent.isActive ? 'success' : 'danger'" class="ml-2" />
                        </p>
                        <p><span class="font-semibold">Address:</span> {{ selectedStudent.address }}</p>
                        <p><span class="font-semibold">Email:</span> {{ selectedStudent.email }}</p>
                        <p><span class="font-semibold">Contact:</span> {{ selectedStudent.contact }}</p>
                    </div>

                    <div class="col-12 sm:col-6">
                        <label class="font-semibold">Email</label>
                        <p>{{ selectedStudent.email }}</p>
                    </div>
                    <div class="col-12 sm:col-6">
                        <label class="font-semibold">Contact</label>
                        <p>{{ selectedStudent.contact }}</p>
                    </div>
                </div>
                <div v-else class="md:col-span-2">
                    <div class="grid grid-cols-2 gap-4 text-sm mt-2">
                        <div>
                            <label class="font-semibold">Student Name</label>
                            <InputText v-model="selectedStudent.name" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Grade Level</label>
                            <Dropdown v-model="selectedStudent.gradeLevel" :options="['Kindergarten', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6']" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Section</label>
                            <Dropdown v-model="selectedStudent.section" :options="sectionsByGrade[selectedStudent.gradeLevel] || []" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Gender</label>
                            <Dropdown v-model="selectedStudent.gender" :options="['Male', 'Female']" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Date of Birth</label>
                            <Calendar v-model="selectedStudent.birthdate" showIcon dateFormat="yy-mm-dd" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">LRN</label>
                            <InputText v-model="selectedStudent.lrn" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Student Status</label>
                            <Dropdown
                                v-model="selectedStudent.isActive"
                                :options="[
                                    { name: 'Active', value: true },
                                    { name: 'Inactive', value: false }
                                ]"
                                optionLabel="name"
                                optionValue="value"
                                class="w-full"
                            />
                        </div>
                        <div>
                            <label class="font-semibold">Email</label>
                            <InputText v-model="selectedStudent.email" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Address</label>
                            <InputText v-model="selectedStudent.address" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Contact</label>
                            <InputText v-model="selectedStudent.contact" class="w-full" />
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2 w-full">
                    <Button label="Update Photo" icon="pi pi-camera" class="p-button-warning p-button-sm" @click="updatePhoto(selectedStudent)" />
                    <Button label="Update E-Signature" icon="pi pi-pencil" class="p-button-danger p-button-sm" @click="updateSignature(selectedStudent)" />
                    <Button v-if="!isEdit" label="Update Profile" icon="pi pi-user-edit" class="p-button-warning p-button-sm" @click="startEditProfile" />
                    <Button v-if="isEdit" label="Save Changes" icon="pi pi-check" class="p-button-success p-button-sm" @click="saveInlineProfile" />
                    <Button v-if="isEdit" label="Cancel" icon="pi pi-times" class="p-button-text p-button-sm" @click="cancelInlineEdit" />
                </div>
            </template>
        </Dialog>

        <!-- Student Dialog - Enrollment Form Style -->
        <Dialog v-model:visible="studentDialog" modal :style="{ width: '1200px', maxHeight: '90vh' }" :dismissableMask="true" :closable="false" class="enrollment-dialog">
            <template #header>
                <div class="enrollment-header-wrapper">
                    <Button icon="pi pi-times" class="p-button-text p-button-plain close-button" @click="studentDialog = false" />
                    <div class="enrollment-header">
                        <h2 class="enrollment-title">BASIC EDUCATION ENROLLMENT FORM</h2>
                        <p class="enrollment-subtitle">Naawan Central School</p>
                        <p class="enrollment-sy">S.Y. 2025-2026</p>
                    </div>
                </div>
            </template>

            <div class="enrollment-form-container">
                <!-- Step 1: Form Fields -->
                <div v-if="currentStep === 1">
                    <!-- LEARNER INFORMATION SECTION -->
                    <div class="form-section">
                        <h3 class="section-title">LEARNER INFORMATION</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label>School Year</label>
                                <InputText v-model="student.schoolYear" readonly class="readonly-field" />
                            </div>
                            <div class="form-group">
                                <label>Grade Level</label>
                                <Dropdown v-model="student.gradeLevel" :options="gradeLevels" optionLabel="name" optionValue="code" placeholder="-- Please select --" class="w-full" @change="updateStudentSections" />
                            </div>
                            <div class="form-group">
                                <label>Type</label>
                                <div class="radio-group">
                                    <div class="radio-item">
                                        <RadioButton v-model="student.learnerType" inputId="oldStudent" value="Old Student" />
                                        <label for="oldStudent">Old Student</label>
                                    </div>
                                    <div class="radio-item">
                                        <RadioButton v-model="student.learnerType" inputId="transferIn" value="Transfer In" />
                                        <label for="transferIn">Transfer In</label>
                                    </div>
                                    <div class="radio-item">
                                        <RadioButton v-model="student.learnerType" inputId="newMoveIn" value="New/Move In" />
                                        <label for="newMoveIn">New/Move In</label>
                                    </div>
                                    <div class="radio-item">
                                        <RadioButton v-model="student.learnerType" inputId="balikAral" value="Balik-Aral" />
                                        <label for="balikAral">Balik-Aral</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Learner Status</label>
                                <Dropdown
                                    v-model="student.learnerStatus"
                                    :options="[
                                        { name: 'WITH LRN', value: 'WITH LRN' },
                                        { name: 'WITHOUT LRN', value: 'WITHOUT LRN' }
                                    ]"
                                    optionLabel="name"
                                    optionValue="value"
                                    placeholder="Select Status"
                                    class="w-full"
                                />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Last Grade Level Completed</label>
                                <Dropdown v-model="student.lastGradeCompleted" :options="gradeLevels" optionLabel="name" optionValue="code" placeholder="-- Please select --" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label>Last S.Y. Attended</label>
                                <InputText v-model="student.lastSYAttended" placeholder="e.g., 2023-2024" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label>Name of Previous School</label>
                                <InputText v-model="student.previousSchool" placeholder="Enter previous school name" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label>School ID</label>
                                <InputText v-model="student.schoolId" placeholder="Enter School ID" class="w-full" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>PSA Birth Cert. No. (if available upon registration)</label>
                                <InputText v-model="student.psaBirthCert" placeholder="Enter PSA Birth Certificate Number" class="w-full" />
                            </div>
                            <div v-if="student.learnerStatus === 'WITH LRN'" class="form-group">
                                <label>LRN <span class="text-red-500">*</span></label>
                                <InputText v-model="student.lrn" placeholder="Enter Learner Reference Number" class="w-full" />
                                <small v-if="!student.lrn" class="text-red-500">LRN is required for learners with LRN status</small>
                            </div>
                        </div>
                    </div>

                    <!-- PERSONAL INFORMATION SECTION -->
                    <div class="form-section">
                        <h3 class="section-title">PERSONAL INFORMATION</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name</label>
                                <InputText v-model="student.firstName" placeholder="Enter first name" class="w-full" @input="updateFullName" />
                                <small v-if="!student.firstName" class="text-red-500">First Name is required</small>
                            </div>
                            <div class="form-group">
                                <label>Middle Name</label>
                                <InputText v-model="student.middleName" placeholder="Enter middle name" class="w-full" @input="updateFullName" />
                            </div>
                            <div class="form-group">
                                <label>Last Name</label>
                                <InputText v-model="student.lastName" placeholder="Enter last name" class="w-full" @input="updateFullName" />
                                <small v-if="!student.lastName" class="text-red-500">Last Name is required</small>
                            </div>
                            <div class="form-group">
                                <label>Extension Name <span class="text-gray-400">(Optional)</span></label>
                                <InputText v-model="student.extensionName" placeholder="Jr., Sr., III, etc. (Optional)" class="w-full" @input="updateFullName" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Date of Birth</label>
                                <Calendar v-model="student.birthdate" showIcon dateFormat="mm/dd/yy" placeholder="Select date" class="w-full" @date-select="calculateStudentAge" />
                                <small v-if="!student.birthdate" class="text-red-500">Date of Birth is required</small>
                            </div>
                            <div class="form-group">
                                <label>Place of Birth</label>
                                <InputText v-model="student.placeOfBirth" placeholder="Enter place of birth" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label>Sex</label>
                                <Dropdown
                                    v-model="student.gender"
                                    :options="[
                                        { name: 'Male', value: 'Male' },
                                        { name: 'Female', value: 'Female' }
                                    ]"
                                    optionLabel="name"
                                    optionValue="value"
                                    placeholder="Select sex"
                                    class="w-full"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- BASIC EDUCATION SECTION -->
                    <div class="form-section">
                        <h3 class="section-title">BASIC EDUCATION INFORMATION</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Mother Tongue</label>
                                <InputText v-model="student.motherTongue" placeholder="Enter mother tongue" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label>House Income (Monthly)</label>
                                <InputText v-model="student.houseIncome" placeholder="Enter monthly house income" class="w-full" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Belonging to any Indigenous Peoples (IP) Community/Indigenous Cultural Community</label>
                                <div class="radio-group">
                                    <div class="radio-item">
                                        <RadioButton v-model="student.isIndigenous" inputId="indigenousYes" :value="true" />
                                        <label for="indigenousYes">Yes</label>
                                    </div>
                                    <div class="radio-item">
                                        <RadioButton v-model="student.isIndigenous" inputId="indigenousNo" :value="false" />
                                        <label for="indigenousNo">No</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="student.isIndigenous" class="form-row">
                            <div class="form-group">
                                <label>If Yes, please specify:</label>
                                <InputText v-model="student.indigenousCommunity" placeholder="Please specify the indigenous community" class="w-full" />
                                <small class="text-red-500">Please specify the indigenous community</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Is your family a beneficiary of 4Ps?</label>
                                <div class="radio-group">
                                    <div class="radio-item">
                                        <RadioButton v-model="student.is4PsBeneficiary" inputId="fourPsYes" :value="true" />
                                        <label for="fourPsYes">Yes</label>
                                    </div>
                                    <div class="radio-item">
                                        <RadioButton v-model="student.is4PsBeneficiary" inputId="fourPsNo" :value="false" />
                                        <label for="fourPsNo">No</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="student.is4PsBeneficiary" class="form-row">
                            <div class="form-group">
                                <label>If Yes, write the 4Ps Household ID Number below:</label>
                                <InputText v-model="student.householdID" placeholder="Enter 4Ps Household ID Number" class="w-full" />
                                <small class="text-red-500">Household ID is required</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Is the child a Learner with Disability?</label>
                                <div class="radio-group">
                                    <div class="radio-item">
                                        <RadioButton v-model="student.hasDisability" inputId="disabilityYes" :value="true" />
                                        <label for="disabilityYes">Yes</label>
                                    </div>
                                    <div class="radio-item">
                                        <RadioButton v-model="student.hasDisability" inputId="disabilityNo" :value="false" />
                                        <label for="disabilityNo">No</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="student.hasDisability" class="form-row">
                            <div class="form-group">
                                <label>If Yes, specify the type of disability:</label>
                                <div class="disability-checkboxes">
                                    <div v-for="disabilityType in disabilityTypes" :key="disabilityType" class="checkbox-item">
                                        <Checkbox v-model="student.disabilities" :inputId="'disability_' + disabilityType.replace(/[^a-zA-Z0-9]/g, '_')" :value="disabilityType" />
                                        <label :for="'disability_' + disabilityType.replace(/[^a-zA-Z0-9]/g, '_')" class="ml-2">{{ disabilityType }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PARENT'S/GUARDIAN'S INFORMATION SECTION -->
                    <div class="form-section">
                        <h3 class="section-title">PARENT'S/GUARDIAN'S INFORMATION</h3>

                        <!-- Father's Information -->
                        <div class="parent-subsection">
                            <h4 class="subsection-title">Father's Name</h4>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <InputText v-model="student.fatherLastName" placeholder="Enter father's last name" class="w-full" />
                                    <small v-if="!student.fatherLastName" class="text-red-500">Last Name is required</small>
                                </div>
                                <div class="form-group">
                                    <label>First Name</label>
                                    <InputText v-model="student.fatherFirstName" placeholder="Enter father's first name" class="w-full" />
                                    <small v-if="!student.fatherFirstName" class="text-red-500">First Name is required</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Middle Name</label>
                                    <InputText v-model="student.fatherMiddleName" placeholder="Enter father's middle name" class="w-full" />
                                </div>
                                <div class="form-group">
                                    <label>Contact Number</label>
                                    <InputText v-model="student.fatherContactNumber" placeholder="Enter father's contact number" class="w-full" />
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Occupation</label>
                                    <InputText v-model="student.fatherOccupation" placeholder="Enter father's occupation" class="w-full" />
                                </div>
                            </div>
                        </div>

                        <!-- Mother's Information -->
                        <div class="parent-subsection">
                            <h4 class="subsection-title">Mother's Maiden Name</h4>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <InputText v-model="student.motherMaidenLastName" placeholder="Enter mother's maiden last name" class="w-full" />
                                </div>
                                <div class="form-group">
                                    <label>First Name</label>
                                    <InputText v-model="student.motherMaidenFirstName" placeholder="Enter mother's maiden first name" class="w-full" />
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Middle Name</label>
                                    <InputText v-model="student.motherMaidenMiddleName" placeholder="Enter mother's maiden middle name" class="w-full" />
                                </div>
                                <div class="form-group">
                                    <label>Contact Number</label>
                                    <InputText v-model="student.motherContactNumber" placeholder="Enter mother's contact number" class="w-full" />
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Occupation</label>
                                    <InputText v-model="student.motherOccupation" placeholder="Enter mother's occupation" class="w-full" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CONTACT INFORMATION SECTION -->
                    <div class="form-section">
                        <h3 class="section-title">CONTACT INFORMATION</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Email Address</label>
                                <InputText v-model="student.email" placeholder="Enter email address" class="w-full" type="email" />
                                <small v-if="!student.email" class="text-red-500">Email Address is required</small>
                            </div>
                            <div class="form-group">
                                <label>Contact Number</label>
                                <InputText v-model="student.phone" placeholder="Enter contact number" class="w-full" />
                                <small v-if="!student.phone" class="text-red-500">Contact Number is required</small>
                            </div>
                            <div class="form-group">
                                <label>Parent/Guardian Contact</label>
                                <InputText v-model="student.parentContact" placeholder="Enter parent/guardian contact" class="w-full" />
                                <small v-if="!student.parentContact" class="text-red-500">Parent/Guardian Contact is required</small>
                            </div>
                        </div>
                    </div>

                    <!-- CURRENT ADDRESS SECTION -->
                    <div class="form-section">
                        <h3 class="section-title">CURRENT ADDRESS</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label>House No.</label>
                                <InputText v-model="student.currentHouseNo" placeholder="Enter house number" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label>Street/Sitio/Purok</label>
                                <InputText v-model="student.currentStreet" placeholder="Enter street, sitio, or purok" class="w-full" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Barangay</label>
                                <InputText v-model="student.currentBarangay" placeholder="Enter barangay" class="w-full" />
                                <small v-if="!student.currentBarangay" class="text-red-500">Barangay is required</small>
                            </div>
                            <div class="form-group">
                                <label>Municipality/City</label>
                                <InputText v-model="student.currentCity" placeholder="Enter municipality or city" class="w-full" />
                                <small v-if="!student.currentCity" class="text-red-500">City is required</small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Province</label>
                                <InputText v-model="student.currentProvince" placeholder="Enter province" class="w-full" />
                            </div>
                            <div class="form-group">
                                <label>Country</label>
                                <InputText v-model="student.currentCountry" placeholder="Enter country" class="w-full" />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Zip Code</label>
                                <InputText v-model="student.currentZipCode" placeholder="Enter zip code" class="w-full" />
                            </div>
                        </div>
                    </div>

                    <!-- PERMANENT ADDRESS SECTION -->
                    <div class="form-section">
                        <h3 class="section-title">PERMANENT ADDRESS</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Same with your Current Address?</label>
                                <div class="radio-group">
                                    <div class="radio-item">
                                        <RadioButton v-model="student.sameAsCurrentAddress" inputId="sameAddressYes" :value="true" />
                                        <label for="sameAddressYes">Yes</label>
                                    </div>
                                    <div class="radio-item">
                                        <RadioButton v-model="student.sameAsCurrentAddress" inputId="sameAddressNo" :value="false" />
                                        <label for="sameAddressNo">No</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="!student.sameAsCurrentAddress">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>House No./Street</label>
                                    <InputText v-model="student.permanentHouseNo" placeholder="Enter house number and street" class="w-full" />
                                </div>
                                <div class="form-group">
                                    <label>Street Name</label>
                                    <InputText v-model="student.permanentStreet" placeholder="Enter street name" class="w-full" />
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Barangay</label>
                                    <InputText v-model="student.permanentBarangay" placeholder="Enter barangay" class="w-full" />
                                </div>
                                <div class="form-group">
                                    <label>Municipality/City</label>
                                    <InputText v-model="student.permanentCity" placeholder="Enter municipality or city" class="w-full" />
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Province</label>
                                    <InputText v-model="student.permanentProvince" placeholder="Enter province" class="w-full" />
                                </div>
                                <div class="form-group">
                                    <label>Country</label>
                                    <InputText v-model="student.permanentCountry" placeholder="Enter country" class="w-full" />
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Zip Code</label>
                                    <InputText v-model="student.permanentZipCode" placeholder="Enter zip code" class="w-full" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION ASSIGNMENT -->
                    <div class="form-section">
                        <h3 class="section-title">SECTION ASSIGNMENT</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Section</label>
                                <Dropdown v-model="student.section" :options="sections" placeholder="Select Section" class="w-full" :disabled="!student.gradeLevel" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Review Information -->
                <div v-if="currentStep === 2">
                    <!-- REVIEW INFORMATION SECTION -->
                    <div class="form-section">
                        <h3 class="section-title">REVIEW INFORMATION</h3>

                        <!-- Enrollment Information -->
                        <div class="review-subsection">
                            <h4 class="review-subsection-title">Enrollment Information</h4>
                            <div class="review-grid">
                                <div class="review-item">
                                    <span class="review-label">School Year:</span>
                                    <span class="review-value">{{ student.schoolYear || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Grade Level:</span>
                                    <span class="review-value">{{ student.gradeLevel || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">With LRN:</span>
                                    <span class="review-value">{{ student.learnerStatus === 'WITH LRN' ? 'Yes' : 'No' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Returning (Balik-Aral):</span>
                                    <span class="review-value">{{ student.learnerType === 'Balik-Aral' ? 'Yes' : 'No' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Learner Information -->
                        <div class="review-subsection">
                            <h4 class="review-subsection-title">Learner Information</h4>
                            <div class="review-grid">
                                <div class="review-item">
                                    <span class="review-label">Full Name:</span>
                                    <span class="review-value">{{ student.name || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">PSA Birth Certificate No.:</span>
                                    <span class="review-value">{{ student.psaBirthCert || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Birthdate:</span>
                                    <span class="review-value">{{ student.birthdate ? new Date(student.birthdate).toLocaleDateString() : 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Place of Birth:</span>
                                    <span class="review-value">{{ student.placeOfBirth || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Sex:</span>
                                    <span class="review-value">{{ student.gender || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Age:</span>
                                    <span class="review-value">{{ student.age || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Mother Tongue:</span>
                                    <span class="review-value">{{ student.motherTongue || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">House Income (Monthly):</span>
                                    <span class="review-value">{{ student.houseIncome || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Indigenous People:</span>
                                    <span class="review-value">{{ student.isIndigenous ? 'Yes' : 'No' }}</span>
                                </div>
                                <div class="review-item" v-if="student.isIndigenous">
                                    <span class="review-label">Indigenous Community:</span>
                                    <span class="review-value">{{ student.indigenousCommunity || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">4Ps Beneficiary:</span>
                                    <span class="review-value">{{ student.is4PsBeneficiary ? 'Yes' : 'No' }}</span>
                                </div>
                                <div class="review-item" v-if="student.is4PsBeneficiary">
                                    <span class="review-label">4Ps Household ID:</span>
                                    <span class="review-value">{{ student.householdID || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Learner with Disability:</span>
                                    <span class="review-value">{{ student.hasDisability ? 'Yes' : 'No' }}</span>
                                </div>
                                <div class="review-item" v-if="student.hasDisability && student.disabilities.length > 0">
                                    <span class="review-label">Disability Types:</span>
                                    <span class="review-value">{{ student.disabilities.join(', ') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Address Information -->
                        <div class="review-subsection">
                            <h4 class="review-subsection-title">Address Information</h4>
                            <div class="review-grid">
                                <div class="review-item">
                                    <span class="review-label">Current Address:</span>
                                    <span class="review-value">
                                        {{ [student.currentHouseNo, student.currentStreet, student.currentBarangay, student.currentCity, student.currentProvince, student.currentZipCode].filter(Boolean).join(', ') || 'N/A' }}
                                    </span>
                                </div>
                                <div class="review-item" v-if="!student.sameAsCurrentAddress">
                                    <span class="review-label">Permanent Address:</span>
                                    <span class="review-value">
                                        {{ [student.permanentHouseNo, student.permanentStreet, student.permanentBarangay, student.permanentCity, student.permanentProvince, student.permanentZipCode].filter(Boolean).join(', ') || 'N/A' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Parent/Guardian Information -->
                        <div class="review-subsection">
                            <h4 class="review-subsection-title">Parent/Guardian Information</h4>
                            <div class="review-grid">
                                <div class="review-item">
                                    <span class="review-label">Father's Name:</span>
                                    <span class="review-value">
                                        {{ [student.fatherFirstName, student.fatherMiddleName, student.fatherLastName].filter(Boolean).join(' ') || 'N/A' }}
                                    </span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Father's Contact:</span>
                                    <span class="review-value">{{ student.fatherContactNumber || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Father's Occupation:</span>
                                    <span class="review-value">{{ student.fatherOccupation || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Mother's Name:</span>
                                    <span class="review-value">
                                        {{ [student.motherMaidenFirstName, student.motherMaidenMiddleName, student.motherMaidenLastName].filter(Boolean).join(' ') || 'N/A' }}
                                    </span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Mother's Contact:</span>
                                    <span class="review-value">{{ student.motherContactNumber || 'N/A' }}</span>
                                </div>
                                <div class="review-item">
                                    <span class="review-label">Mother's Occupation:</span>
                                    <span class="review-value">{{ student.motherOccupation || 'N/A' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="review-subsection">
                            <div class="terms-checkbox">
                                <Checkbox v-model="termsAccepted" inputId="termsAccepted" :binary="true" />
                                <label for="termsAccepted" class="terms-label">
                                    I hereby certify that the above information given are true and correct to the best of my knowledge and I allow the school to use my child's details to create and/or update his/her learner profile in the Learner
                                    Information System.
                                </label>
                            </div>
                            <div v-if="!termsAccepted && submitted" class="terms-error">You must accept the terms to continue</div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="enrollment-footer">
                    <Button label="Cancel" class="p-button-text" @click="cancelStudentDialog" />
                    <Button v-if="currentStep > 1" label="Previous" icon="pi pi-chevron-left" class="p-button-outlined" @click="previousStep" />
                    <Button v-if="currentStep < totalSteps" label="Next" icon="pi pi-chevron-right" iconPos="right" class="p-button-primary" @click="nextStep" />
                    <Button v-if="currentStep === totalSteps" label="Submit" icon="pi pi-check" class="p-button-success" @click="saveStudent" :loading="loading" :disabled="!termsAccepted" />
                </div>
            </template>
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


        <!-- Status Change Dialog -->
        <Dialog v-model:visible="statusChangeDialog" modal header="Change Student Status" :style="{ width: '500px' }">
            <div class="p-4">
                <div class="mb-4">
                    <h4 class="mb-2">Student: {{ selectedStudentForStatus?.name }}</h4>
                    <p class="text-sm text-gray-600">Current Status: {{ getStudentStatusDisplay(selectedStudentForStatus) }}</p>
                </div>
                
                <div class="field mb-4">
                    <label for="newStatus" class="block text-sm font-medium mb-2">New Status</label>
                    <Dropdown 
                        id="newStatus"
                        v-model="newStudentStatus" 
                        :options="[
                            { label: 'Active', value: 'active' },
                            { label: 'Dropped Out', value: 'dropped_out' },
                            { label: 'Transferred Out', value: 'transferred_out' },
                            { label: 'Transferred In', value: 'transferred_in' },
                            { label: 'Graduated', value: 'graduated' }
                        ]"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select new status"
                        class="w-full"
                    />
                </div>
                
                <div class="field mb-4">
                    <label for="effectiveDate" class="block text-sm font-medium mb-2">Effective Date</label>
                    <Calendar 
                        id="effectiveDate"
                        v-model="statusEffectiveDate" 
                        dateFormat="yy-mm-dd" 
                        :showIcon="true" 
                        class="w-full"
                        placeholder="Select effective date"
                    />
                </div>
                
                <div class="field mb-4">
                    <label for="statusReason" class="block text-sm font-medium mb-2">Notes</label>
                    <Textarea 
                        id="statusReason"
                        v-model="statusChangeReason" 
                        rows="3" 
                        placeholder="Enter reason for status change..."
                        class="w-full"
                    />
                </div>
                
                <div class="flex justify-end space-x-2 mt-4">
                    <Button label="Cancel" class="p-button-text" @click="statusChangeDialog = false" />
                    <Button label="Save Changes" icon="pi pi-check" class="p-button-primary" @click="saveStatusChange" />
                </div>
            </div>
        </Dialog>

        <Dialog v-model:visible="qrCodeDialog" modal header="Student LRN QR Code" :style="{ width: '350px' }">
            <div class="p-4 flex flex-column align-items-center">
                <div v-if="selectedStudent && qrCodes[selectedStudent.lrn]" class="mb-3">
                    <img :src="qrCodes[selectedStudent.lrn]" alt="LRN QR Code" class="w-48 h-48 border border-gray-200 rounded-md" />
                </div>
                <div v-if="selectedStudent" class="text-center">
                    <h3 class="text-xl font-semibold mb-1">{{ selectedStudent.name }}</h3>
                    <p class="mb-1">LRN: {{ selectedStudent.lrn }}</p>
                    <p class="text-sm text-color-secondary">{{ selectedStudent.gradeLevel }} - {{ selectedStudent.section }}</p>
                </div>
                <div class="flex justify-center mt-4">
                    <Button label="Close" icon="pi pi-times" class="p-button-text" @click="qrCodeDialog = false" />
                    <Button label="Print" icon="pi pi-print" class="p-button-text p-button-success ml-2" @click="printQRCode" />
                </div>
            </div>
        </Dialog>

        <!-- Success Dialog with Fireworks -->
        <Dialog v-model:visible="successDialog" modal :closable="false" :style="{ width: '500px' }" class="success-dialog">
            <template #header>
                <div class="success-header">
                    <i class="pi pi-check-circle success-icon"></i>
                </div>
            </template>

            <div class="success-content">
                <!-- Fireworks Animation -->
                <div v-if="showFireworks" class="fireworks-container">
                    <div class="firework firework-1"></div>
                    <div class="firework firework-2"></div>
                    <div class="firework firework-3"></div>
                    <div class="firework firework-4"></div>
                </div>

                <div class="success-message">
                    <h2 class="success-title">ðŸŽ‰ Thank You! ðŸŽ‰</h2>
                    <p class="success-subtitle">Student Enrolled Successfully!</p>
                    <div class="success-details">
                        <p>The new student has been successfully enrolled in the system.</p>
                        <p>Welcome to <strong>Naawan Central School</strong>!</p>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="success-footer">
                    <Button label="Continue" icon="pi pi-arrow-right" iconPos="right" class="p-button-success" @click="successDialog = false" />
                </div>
            </template>
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

/* --- Consistent styling with Teacher Management --- */
.teacher-management-title {
    color: var(--primary-color);
    font-size: 1.75rem;
    font-weight: 600;
}

:deep(.p-button-primary) {
    background-color: #4361ee;
    border: none;
}

:deep(.p-button-primary:hover) {
    background-color: #3b5ce6;
}

/* Enrollment Form Styles */
.enrollment-dialog :deep(.p-dialog-content) {
    padding: 0 !important;
}

.enrollment-dialog :deep(.p-dialog-header) {
    padding: 0 !important;
    border: none !important;
}

.enrollment-header-wrapper {
    position: relative;
    margin: -2rem -2rem 0 -2rem;
    padding: 0;
    width: calc(100% + 4rem);
    left: -2rem;
    border-radius: 12px 12px 0 0;
}

.close-button {
    position: absolute;
    top: 15px;
    right: 15px;
    z-index: 10;
    color: white !important;
    background: rgba(255, 255, 255, 0.2) !important;
    border-radius: 50% !important;
    width: 32px !important;
    height: 32px !important;
    padding: 0 !important;
}

.close-button:hover {
    background: rgba(255, 255, 255, 0.3) !important;
}

.enrollment-header {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
    color: white;
    text-align: center;
    padding: 24px 0;
    width: 100%;
    border-radius: 12px 12px 0 0;
    position: relative;
    overflow: hidden;
}

.enrollment-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    opacity: 0.6;
}

.enrollment-title {
    font-size: 1.3rem;
    font-weight: bold;
    margin: 0 0 3px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: relative;
    z-index: 1;
}

.enrollment-subtitle {
    font-size: 0.9rem;
    margin: 0 0 2px 0;
    opacity: 0.9;
    position: relative;
    z-index: 1;
}

.enrollment-sy {
    font-size: 0.85rem;
    margin: 0;
    font-weight: 600;
    position: relative;
    z-index: 1;
}

.enrollment-form-container {
    max-height: 60vh;
    overflow-y: auto;
    padding: 0 4px;
}

.form-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.section-title {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
    color: white;
    font-size: 1rem;
    font-weight: 600;
    padding: 12px 20px;
    margin: -24px -24px 20px -24px;
    border-radius: 12px 12px 0 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.parent-subsection {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 16px;
}

.subsection-title {
    color: #374151;
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 12px 0;
    padding-bottom: 8px;
    border-bottom: 2px solid #e5e7eb;
}

.review-subsection {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 16px;
    margin-bottom: 16px;
}

.review-subsection-title {
    color: #1f2937;
    font-size: 1.1rem;
    font-weight: 600;
    margin: 0 0 16px 0;
    padding-bottom: 8px;
    border-bottom: 2px solid #e5e7eb;
}

.review-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.review-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 8px 0;
    border-bottom: 1px solid #f3f4f6;
}

.review-item:last-child {
    border-bottom: none;
}

.review-label {
    font-weight: 600;
    color: #374151;
    min-width: 200px;
    flex-shrink: 0;
}

.review-value {
    color: #6b7280;
    text-align: right;
    flex: 1;
    margin-left: 16px;
    word-break: break-word;
}

.terms-checkbox {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 8px;
}

.terms-label {
    font-size: 0.875rem;
    color: #374151;
    line-height: 1.5;
    cursor: pointer;
    margin: 0;
}

.terms-error {
    color: #dc2626;
    font-size: 0.875rem;
    font-weight: 500;
    margin-top: 4px;
}

/* Success Dialog Styling */
.success-dialog :deep(.p-dialog-header) {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    color: white;
    text-align: center;
    border-radius: 12px 12px 12px 0;
    padding: 20px;
}

.success-header {
    width: 100%;
    text-align: center;
}

.success-icon {
    font-size: 3rem;
    color: white;
    animation: bounce 1s ease-in-out infinite alternate;
}

.success-content {
    position: relative;
    text-align: center;
    padding: 30px 20px;
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    overflow: hidden;
}

.success-message {
    position: relative;
    z-index: 10;
}

.success-title {
    font-size: 2rem;
    font-weight: bold;
    color: #16a34a;
    margin: 0 0 10px 0;
    animation: fadeInUp 0.8s ease-out;
}

.success-subtitle {
    font-size: 1.2rem;
    font-weight: 600;
    color: #15803d;
    margin: 0 0 20px 0;
    animation: fadeInUp 0.8s ease-out 0.2s both;
}

.success-details {
    color: #374151;
    line-height: 1.6;
    animation: fadeInUp 0.8s ease-out 0.4s both;
}

.success-details p {
    margin: 8px 0;
}

.success-footer {
    text-align: center;
    padding: 15px;
    background: #f9fafb;
}

/* Fireworks Animation */
.fireworks-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 1;
}

.firework {
    position: absolute;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    animation: firework 2s ease-out infinite;
}

.firework-1 {
    top: 20%;
    left: 20%;
    background: #ff6b6b;
    animation-delay: 0s;
}

.firework-2 {
    top: 30%;
    right: 20%;
    background: #4ecdc4;
    animation-delay: 0.5s;
}

.firework-3 {
    top: 60%;
    left: 30%;
    background: #45b7d1;
    animation-delay: 1s;
}

.firework-4 {
    top: 50%;
    right: 30%;
    background: #f9ca24;
    animation-delay: 1.5s;
}

/* Animations */
@keyframes bounce {
    0% {
        transform: translateY(0);
    }
    100% {
        transform: translateY(-10px);
    }
}

@keyframes fadeInUp {
    0% {
        opacity: 0;
        transform: translateY(30px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes firework {
    0% {
        transform: scale(0);
        opacity: 1;
    }
    50% {
        transform: scale(1);
        opacity: 1;
    }
    100% {
        transform: scale(0);
        opacity: 0;
    }
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
    align-items: start;
}

.form-group {
    display: flex;
    flex-direction: column;
    min-height: 80px;
}

.form-group label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    font-size: 0.875rem;
    line-height: 1.4;
}

.readonly-field {
    background-color: #f3f4f6 !important;
    color: #6b7280 !important;
    cursor: not-allowed;
}

.radio-group {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 4px;
}

.radio-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.radio-item label {
    font-weight: 500;
    font-size: 0.875rem;
    margin: 0;
    cursor: pointer;
}

.disability-checkboxes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 8px;
    margin-top: 8px;
}

.checkbox-item {
    display: flex;
    align-items: center;
    padding: 4px 0;
}

.checkbox-item label {
    font-weight: 500;
    font-size: 0.875rem;
    margin: 0;
    cursor: pointer;
    color: #374151;
}

:deep(.p-checkbox .p-checkbox-box) {
    border: 2px solid #d1d5db;
    width: 18px;
    height: 18px;
    border-radius: 4px;
}

:deep(.p-checkbox .p-checkbox-box.p-highlight) {
    border-color: #3b82f6;
    background: #3b82f6;
}

.enrollment-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 0 0 0;
    border-top: 1px solid #e5e7eb;
    margin-top: 20px;
}

/* Form field styling */
:deep(.p-inputtext),
:deep(.p-dropdown),
:deep(.p-calendar) {
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 10px 14px;
    font-size: 0.875rem;
    transition: all 0.2s ease;
    min-height: 42px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}


/* Improved table layout without horizontal scroll */
.modern-datatable {
    border-radius: 8px;
    overflow: hidden;
    width: 100%;
}

.modern-datatable .p-datatable-table {
    table-layout: fixed;
    width: 100%;
}

.modern-datatable .p-datatable-tbody > tr > td {
    padding: 0.5rem 0.25rem;
    vertical-align: middle;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.modern-datatable .p-datatable-thead > tr > th {
    padding: 0.75rem 0.25rem;
    font-weight: 600;
    font-size: 0.875rem;
}

/* Specific column styling */
.modern-datatable .text-wrap {
    white-space: normal;
    word-break: break-word;
    line-height: 1.2;
}

.modern-datatable .p-button-sm {
    padding: 0.25rem;
    font-size: 0.75rem;
}

.modern-datatable .p-tag {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

:deep(.modern-datatable .p-datatable-header) {
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
    padding: 1rem;
}

:deep(.modern-datatable .p-datatable-thead > tr > th) {
    background: #f1f5f9;
    color: #374151;
    font-weight: 600;
    padding: 1rem;
    border-bottom: 2px solid #e5e7eb;
}

:deep(.modern-datatable .p-datatable-tbody > tr) {
    transition: all 0.2s ease;
}

:deep(.modern-datatable .p-datatable-tbody > tr:hover) {
    background: #f8fafc;
}

:deep(.modern-datatable .p-datatable-tbody > tr > td) {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
}

:deep(.p-inputtext:focus),
:deep(.p-dropdown:focus),
:deep(.p-calendar:focus) {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

:deep(.p-dropdown-panel) {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

:deep(.p-radiobutton .p-radiobutton-box) {
    border: 2px solid #d1d5db;
    width: 18px;
    height: 18px;
}

:deep(.p-radiobutton .p-radiobutton-box.p-highlight) {
    border-color: #3b82f6;
    background: #3b82f6;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }

    .radio-group {
        flex-direction: column;
        gap: 8px;
    }

    .enrollment-form-container {
        max-height: 50vh;
    }

    .header-content {
        flex-direction: column;
        gap: 1rem;
    }

    .header-left {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .header-actions {
        flex-direction: column;
        gap: 0.75rem;
        width: 100%;
    }

    .search-input {
        width: 100% !important;
    }
}

/* Modern Header Styles */
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
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
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

.search-input:focus {
    background: white !important;
    border-color: rgba(255, 255, 255, 0.8) !important;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2) !important;
    outline: none !important;
}

.search-input::placeholder {
    color: #64748b !important;
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
    background: rgba(255, 255, 255, 0.2) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    color: white !important;
    font-weight: 600 !important;
    padding: 0.75rem 1.5rem !important;
    border-radius: 25px !important;
    backdrop-filter: blur(10px) !important;
    transition: all 0.3s ease !important;
}

.add-student-btn:hover {
    background: rgba(255, 255, 255, 0.3) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2) !important;
}
</style>



