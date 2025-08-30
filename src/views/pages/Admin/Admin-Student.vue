<script setup>
import { useToast } from 'primevue/usetoast';
import QRCode from 'qrcode';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

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

    // Additional Information
    photo: null,
    profilephoto: null,
    enrollmentDate: new Date().toISOString().split('T')[0],
    status: 'Enrolled'
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
const qrCodeDialog = ref(false);
const selectedStudent = ref(null);
const fileInput = ref(null);
const viewStudentDialog = ref(false);
const isEdit = ref(false);

const selectedStudentAge = computed(() => calculateAge(selectedStudent.value?.birthdate));
const originalStudentClone = ref(null);

// Grade levels for filtering
const gradeLevels = [
    { name: 'Kindergarten', code: 'Kindergarten' },
    { name: 'Grade 1', code: 'Grade 1' },
    { name: 'Grade 2', code: 'Grade 2' },
    { name: 'Grade 3', code: 'Grade 3' },
    { name: 'Grade 4', code: 'Grade 4' },
    { name: 'Grade 5', code: 'Grade 5' },
    { name: 'Grade 6', code: 'Grade 6' }
];

// Sections for each grade level
const sectionsByGrade = {
    Kindergarten: ['Daisy', 'Rose', 'Sunflower'],
    'Grade 1': ['Faith', 'Hope', 'Love'],
    'Grade 2': ['Honesty', 'Kindness', 'Patience'],
    'Grade 3': ['Wisdom', 'Courage', 'Respect'],
    'Grade 4': ['Integrity', 'Excellence', 'Humility'],
    'Grade 5': ['Diligence', 'Creativity', 'Teamwork'],
    'Grade 6': ['Leadership', 'Perseverance', 'Responsibility']
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
        console.log('Loaded students from API:', apiStudents);

        // Format students for display (mapping from camelCase database fields)
        const formattedStudents = apiStudents.map((student) => {
            return {
                id: student.id,
                studentId: student.studentId,
                name: student.name || `${student.firstName || ''} ${student.lastName || ''}`.trim(),
                firstName: student.firstName,
                lastName: student.lastName,
                email: student.email || 'N/A',
                gender: student.gender || student.sex || 'Male',
                age: student.age || calculateAge(student.birthdate),
                birthdate: student.birthdate ? new Date(student.birthdate).toLocaleDateString() : 'N/A',
                address: student.address || formatAddress(student),
                contact: student.contactInfo || student.parentContact || 'N/A',
                photo: student.profilePhoto
                    ? student.profilePhoto.startsWith('data:')
                        ? student.profilePhoto
                        : `http://localhost:8000/${student.profilePhoto}`
                    : `https://randomuser.me/api/portraits/${student.gender === 'Female' ? 'women' : 'men'}/${student.id}.jpg`,
                qrCodePath: student.qr_code_path ? `http://localhost:8000/${student.qr_code_path}` : null,
                gradeLevel: student.gradeLevel,
                section: student.section,
                lrn: student.lrn || `${new Date().getFullYear()}${String(student.id).padStart(8, '0')}`,
                enrollmentDate: student.enrollmentDate ? new Date(student.enrollmentDate).toLocaleDateString() : new Date().toLocaleDateString(),
                status: student.status || 'Enrolled',
                // Store original data for reference
                originalData: student
            };
        });

        students.value = formattedStudents;

        // Set QR codes from backend data
        formattedStudents.forEach((student) => {
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
    gradeLevels.forEach((grade) => {
        gradeCounts[grade.code] = students.value.filter((s) => s.gradeLevel === grade.code).length;
    });

    // Count students by gender
    const maleCounts = students.value.filter((s) => s.gender.toLowerCase() === 'male').length;
    const femaleCounts = students.value.filter((s) => s.gender.toLowerCase() === 'female').length;

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
            enrollmentDate: (() => {
                if (!student.value.enrollmentDate || student.value.enrollmentDate === 'N/A') {
                    return new Date().toISOString().split('T')[0];
                }
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
            })()
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

        // Close dialog and reset form
        studentDialog.value = false;
        student.value = {};
        submitted.value = false;

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: result.id ? 'Student updated successfully!' : 'Student added successfully!',
            life: 3000
        });
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
        status: studentData.status || 'Enrolled'
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

    const win = window.open('', '_blank');
    win.document.write(`
        <html>
        <head>
            <title>Temporary ID - ${student.name}</title>
            <style>
                * { box-sizing: border-box; }
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .card-wrapper { display: flex; gap: 20px; }
                .front, .back {
                    width: 350px;
                    height: 550px;
                    border: 1px solid #000;
                    border-radius: 12px;
                    overflow: hidden;
                    position: relative;
                }
                .front {
                    background: #fff url('https://via.placeholder.com/350x550?text=Background') no-repeat center/cover;
                }
                .vertical-ribbon {
                    position: absolute;
                    left: 0;
                    top: 0;
                    bottom: 0;
                    width: 40px;
                    background:#7a0c0c;
                    color:#fff;
                    writing-mode: vertical-rl;
                    text-orientation: mixed;
                    font-weight: bold;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    letter-spacing:2px;
                }
                .school-header { padding: 15px 20px 10px 70px; text-align: center; background: #fff; border-bottom: 2px solid #7a0c0c; }
                .logos-container { display: flex; justify-content: center; align-items: center; gap: 20px; margin-bottom: 8px; }
                .school-logo { width: 50px; height: 50px; object-fit: contain; }
                .school-name { font-size: 14px; font-weight: bold; color: #7a0c0c; margin: 0; line-height: 1.2; }
                .school-subtitle { font-size: 10px; color: #666; margin: 2px 0 0; }
                .front-content { padding: 20px 20px 20px 70px; text-align:center; }
                .front-content img.photo { width: 120px; height: 120px; object-fit:cover; border:2px solid #000; margin-bottom: 10px; border-radius: 50%; }
                .front-content h3 { margin:5px 0 0; font-size:18px; font-weight: bold; }
                .front-content h2 { margin:2px 0 5px; font-size:16px; color: #7a0c0c; }
                .front-content p { margin:2px 0; font-size:14px; }
                .qr-small { width:180px; height:180px; margin:10px auto 0; }
                /* back */
                .back-content { padding:20px; font-size:14px; }
                .field { margin:4px 0; }
                .label { font-weight:bold; }
                .ribbon-back { position:absolute; right:0; top:0; bottom:0; width:40px; background:#7a0c0c; color:#fff; writing-mode: vertical-rl; text-orientation: mixed; display:flex;align-items:center;justify-content:center; letter-spacing:2px; font-weight:bold; }
                @media print { .no-print { display:none; } }
            </style>
        </head>
        <body>
            <div class="card-wrapper">
                <!-- FRONT SIDE -->
                <div class="front">
                    <div class="vertical-ribbon">TEMPORARY ID</div>
                    <div class="school-header">
                        <div class="logos-container">
                            <img src="/demo/images/logo.png" class="school-logo" />
                            <img src="/demo/images/logo-msunaawan.jpg" class="school-logo" />
                            <img src="/demo/images/logo-cmas.jpg" class="school-logo" />
                        </div>
                        <div class="school-name">NAAWAN CENTRAL SCHOOL</div>
                        <div class="school-subtitle">NAAWAN, MIS OR.</div>
                    </div>
                    <div class="front-content">
                        <img src="${student.photo ? (student.photo.startsWith('data:') ? student.photo : `http://localhost:8000/${student.photo}`) : 'https://via.placeholder.com/120x150?text=Photo'}" class="photo" />
                        <h3>${student.name.toUpperCase()}</h3>
                        <h2>${student.studentId || student.lrn}</h2>
                        <p>${student.gradeLevel} - ${student.section}</p>
                        <img src="${qrSrc}" class="qr-small" />
                    </div>
                </div>
                <!-- BACK SIDE -->
                <div class="back">
                    <div class="ribbon-back">TEMPORARY ID</div>
                    <div class="back-content">
                        <div class="field"><span class="label">DATE ISSUED:</span> ${today}</div>
                        <div class="field"><span class="label">DATE OF BIRTH:</span> ${student.birthdate || 'N/A'}</div>
                        <div class="field"><span class="label">CONTACT:</span> ${student.contact || 'N/A'}</div>
                        <div class="field"><span class="label">ADDRESS:</span> ${student.address || 'N/A'}</div>
                        <div style="margin:20px 0; text-align:center;">
                            <img src="${student.signature || 'https://via.placeholder.com/120x40?text=Signature'}" style="width:120px; height:40px; object-fit:contain;" />
                            <div style="font-size:12px; font-weight:bold; margin-top:4px;">${student.name.toUpperCase()}</div>
                        </div>
                        <div style="border:2px solid #000; padding:6px; text-align:center; font-weight:bold; margin-top:10px;">VALIDITY PERIOD: AY ${new Date().getFullYear()}-${new Date().getFullYear() + 1}</div>
                        <div style="font-size:11px; text-align:center; margin-top:6px;">THIS ID CARD IS NON-TRANSFERABLE</div>
                    </div>
                </div>
            </div>
            <div class="no-print" style="margin-top:20px; text-align:center;">
                <button onclick="window.print()">Print</button>
                <button onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
    `);
    win.document.close();
}

// -- ACTION BUTTON HANDLERS --
function updatePhoto() {
    if (fileInput.value) {
        fileInput.value.click();
    }
}
function handlePhotoUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
        selectedStudent.value.photo = reader.result;
        // persist immediately
        try {
            const enrolled = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
            const idx = enrolled.findIndex((s) => s.id === selectedStudent.value.id || s.studentId === selectedStudent.value.studentId);
            if (idx > -1) {
                enrolled[idx].photo = reader.result;
                localStorage.setItem('enrolledStudents', JSON.stringify(enrolled));
                loadStudents();
                toast.add({ severity: 'success', summary: 'Photo Updated', detail: 'Student photo updated successfully', life: 3000 });
            }
        } catch (err) {
            console.error('Save photo error', err);
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save photo', life: 3000 });
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
            gradelevel: selectedStudent.value.gradeLevel || 'Grade 1',
            section: selectedStudent.value.section || 'Default',

            // Optional fields
            firstname: selectedStudent.value.firstName || selectedStudent.value.name?.split(' ')[0] || '',
            middlename: selectedStudent.value.middleName || '',
            lastname: selectedStudent.value.lastName || selectedStudent.value.name?.split(' ').slice(1).join(' ') || '',
            extensionname: selectedStudent.value.extensionName || '',
            birthdate: selectedStudent.value.birthdate ? new Date(selectedStudent.value.birthdate).toISOString().split('T')[0] : null,
            age: selectedStudent.value.age || calculateAge(selectedStudent.value.birthdate) || 0,
            gender: selectedStudent.value.gender || 'Male',
            sex: selectedStudent.value.gender || 'Male',
            email: selectedStudent.value.email || null,
            contactinfo: selectedStudent.value.contact || selectedStudent.value.phone || '',
            parentcontact: selectedStudent.value.parentContact || selectedStudent.value.contact || '',
            address: selectedStudent.value.address || '',
            lrn: selectedStudent.value.lrn || '',
            profilephoto: selectedStudent.value.photo || selectedStudent.value.profilephoto || null,
            status: selectedStudent.value.status || 'Enrolled'
        };

        console.log('Sending student data to API:', studentData);

        // Update student in backend database
        console.log('Updating student with ID:', selectedStudent.value.id);
        const response = await fetch(`http://localhost:8000/api/students/${selectedStudent.value.id}`, {
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
        status: 'Enrolled'
    };
    studentDialog.value = true;
}

function cancelStudentDialog() {
    openStudentDialog();
    studentDialog.value = false;
}

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
    <div class="card p-6 shadow-lg rounded-lg bg-white">
        <!-- Modern Gradient Header -->
        <div class="modern-header-container mb-6">
            <div class="gradient-header">
                <div class="header-content">
                    <div class="header-left">
                        <div class="header-icon">
                            <i class="pi pi-users"></i>
                        </div>
                        <div class="header-text">
                            <h1 class="header-title">Student Management System</h1>
                            <p class="header-subtitle">Naawan Central School - Learners Database</p>
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
                        <Button label="Add New Student" icon="pi pi-plus" class="add-student-btn" @click="openStudentDialog" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-4">
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
        </div>

        <!-- Student List -->
        <div class="grid">
            <div class="col-12">
                <DataTable :value="filteredStudents" dataKey="id" class="p-datatable-sm" :loading="loading" stripedRows responsiveLayout="scroll" :paginator="filteredStudents.length > 10" :rows="10" @rowClick="onRowClick">
                    <Column header="#" style="width: 3rem">
                        <template #body="slotProps">
                            <span>{{ slotProps.index + 1 }}</span>
                        </template>
                    </Column>
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
                    <Column field="lrn" header="LRN" sortable style="min-width: 150px">
                        <template #body="slotProps">
                            <span class="font-semibold">{{ slotProps.data.lrn }}</span>
                        </template>
                    </Column>
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
                                <Button icon="pi pi-search" class="p-button-rounded p-button-text" @click="viewStudentDetails(slotProps.data)" />
                                <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click="editStudent(slotProps.data)" />
                                <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click="confirmDeleteStudent(slotProps.data)" />
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
                    <img v-if="selectedStudent.photo" :src="selectedStudent.photo" alt="Student Photo" class="w-48 h-48 rounded-full object-cover ring-2 ring-gray-300" />
                    <div v-else class="w-48 h-48 rounded-full bg-gray-200 flex items-center justify-center text-gray-500">No Photo</div>
                    <p class="text-gray-600 font-medium">QR Code</p>
                    <img v-if="qrCodes[selectedStudent.lrn]" :src="qrCodes[selectedStudent.lrn]" class="w-48 h-48 border rounded-md object-contain" />
                    <p v-else class="text-xs text-gray-400">No QR</p>
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
                <div v-else class="md:col-span-2 space-y-2">
                    <div class="space-y-2 text-sm mt-2">
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
                            <label class="font-semibold">Address</label>
                            <InputText v-model="selectedStudent.address" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Email</label>
                            <InputText v-model="selectedStudent.email" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Contact</label>
                            <InputText v-model="selectedStudent.contact" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">LRN</label>
                            <InputText v-model="selectedStudent.lrn" class="w-full" />
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2 w-full">
                    <Button label="Update Photo" icon="pi pi-camera" class="p-button-warning p-button-sm" @click="updatePhoto(selectedStudent)" />
                    <Button label="Update E-Signature" icon="pi pi-pencil" class="p-button-danger p-button-sm" @click="updateSignature(selectedStudent)" />
                    <Button v-if="!isEdit" label="Update Profile" icon="pi pi-user-edit" class="p-button-info p-button-sm" @click="startEditProfile" />
                    <Button v-else label="Save Changes" icon="pi pi-check" class="p-button-success p-button-sm" @click="saveInlineProfile" />
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

            <div class="enrollment-form-container" style="padding: 20px">
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
                        <div class="form-group">
                            <label>LRN</label>
                            <InputText v-model="student.lrn" placeholder="Enter Learner Reference Number" class="w-full" />
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
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <InputText v-model="student.middleName" placeholder="Enter middle name" class="w-full" @input="updateFullName" />
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <InputText v-model="student.lastName" placeholder="Enter last name" class="w-full" @input="updateFullName" />
                        </div>
                        <div class="form-group">
                            <label>Extension Name</label>
                            <InputText v-model="student.extensionName" placeholder="Jr., Sr., III, etc." class="w-full" @input="updateFullName" />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Date of Birth</label>
                            <Calendar v-model="student.birthdate" showIcon dateFormat="mm/dd/yy" placeholder="Select date" class="w-full" @date-select="calculateStudentAge" />
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

                <!-- CONTACT INFORMATION SECTION -->
                <div class="form-section">
                    <h3 class="section-title">CONTACT INFORMATION</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email Address</label>
                            <InputText v-model="student.email" placeholder="Enter email address" class="w-full" type="email" />
                        </div>
                        <div class="form-group">
                            <label>Contact Number</label>
                            <InputText v-model="student.phone" placeholder="Enter contact number" class="w-full" />
                        </div>
                        <div class="form-group">
                            <label>Parent/Guardian Contact</label>
                            <InputText v-model="student.parentContact" placeholder="Enter parent/guardian contact" class="w-full" />
                        </div>
                    </div>
                </div>

                <!-- ADDRESS INFORMATION SECTION -->
                <div class="form-section">
                    <h3 class="section-title">ADDRESS INFORMATION</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>House No./Street</label>
                            <InputText v-model="student.houseNo" placeholder="Enter house number and street" class="w-full" @input="updateFullAddress" />
                        </div>
                        <div class="form-group">
                            <label>Barangay</label>
                            <InputText v-model="student.barangay" placeholder="Enter barangay" class="w-full" @input="updateFullAddress" />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>City/Municipality</label>
                            <InputText v-model="student.city" placeholder="Enter city/municipality" class="w-full" @input="updateFullAddress" />
                        </div>
                        <div class="form-group">
                            <label>Province</label>
                            <InputText v-model="student.province" placeholder="Enter province" class="w-full" @input="updateFullAddress" />
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

            <template #footer>
                <div class="enrollment-footer">
                    <Button label="Cancel" class="p-button-text" @click="cancelStudentDialog" />
                    <Button label="Save Student" icon="pi pi-check" class="p-button-success" @click="saveStudent" :loading="loading" />
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

        <!-- QR Code Dialog -->
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
    background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%);
    color: white;
    text-align: center;
    padding: 17px 0;
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
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.section-title {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
    color: white;
    font-size: 1rem;
    font-weight: 600;
    margin: -20px -20px 20px -20px;
    padding: 12px 20px;
    border-radius: 8px 8px 0 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 16px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
    font-size: 0.875rem;
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
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 0.875rem;
    transition: all 0.2s ease;
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
    margin: -1.5rem -1.5rem 1.5rem -1.5rem;
}

.gradient-header {
    background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%);
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
}

.header-subtitle {
    font-size: 1.1rem;
    margin: 0 0 0.75rem 0;
    opacity: 0.9;
    font-weight: 400;
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
    padding: 0.75rem 1rem 0.75rem 2.5rem !important;
    color: #1e40af !important;
    font-weight: 500 !important;
    width: 300px !important;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease !important;
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
