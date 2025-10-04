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
    month: null,
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

// Grade levels loaded from database
const gradeLevels = ref([]);

// Sections loaded from database
const allSections = ref([]);
const sectionsByGrade = ref({});

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
        sectionsData.forEach((section) => {
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
        gradeLevels.value = gradesData.map((grade) => ({
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

// Load all submitted SF2 reports from database via API
const loadStudents = async () => {
    try {
        loading.value = true;

        // Fetch submitted SF2 reports from Laravel API
        const response = await fetch('http://127.0.0.1:8000/api/sf2/submitted', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const apiResponse = await response.json();
        console.log('Raw SF2 reports API response:', apiResponse);

        if (apiResponse.success && apiResponse.data) {
            // Format SF2 reports data for frontend (using snake_case to match table fields)
            const formattedReports = apiResponse.data.map((report) => {
                return {
                    id: report.id,
                    section_id: report.section_id, // Add this for the API call
                    grade_level: report.grade_level,
                    section: report.section_name,
                    school_year: '2025-2026',
                    month: report.month, // Use the raw month format (2025-01)
                    month_name: report.month_name, // Keep the display name
                    total_students: report.total_students || 0,
                    present_today: report.present_today || 0,
                    absent_today: report.absent_today || 0,
                    attendance_rate: report.attendance_rate || 0,
                    teacher_name: report.teacher_name,
                    status: report.status,
                    submitted_at: report.submitted_at,
                    // Add display fields for compatibility
                    name: `${report.section_name} - ${report.month_name || report.month}`,
                    firstName: report.teacher_name?.split(' ')[0] || 'Unknown',
                    lastName: report.teacher_name?.split(' ').slice(1).join(' ') || 'Teacher',
                    isActive: true
                };
            });

            students.value = formattedReports;
        } else {
            students.value = [];
        }

        totalStudents.value = students.value.length;

        // Update the filter counts
        updateFilterCounts();
    } catch (error) {
        console.error('Error loading SF2 reports from API:', error);
        toast.add({
            severity: 'error',
            summary: 'Connection Error',
            detail: 'Failed to load submitted reports from database. Please check if the server is running.',
            life: 5000
        });
        students.value = [];
        totalStudents.value = 0;
    } finally {
        loading.value = false;
    }
};

// View SF2 Report in modal popup
const showSF2Modal = ref(false);
const selectedSF2Report = ref(null);
const sf2ReportData = ref(null);
const loadingSF2 = ref(false);

const viewSF2Report = async (reportData) => {
    try {
        selectedSF2Report.value = reportData;
        loadingSF2.value = true;
        showSF2Modal.value = true;

        // Show loading toast
        toast.add({
            severity: 'info',
            summary: 'Loading Report',
            detail: `Loading ${reportData.month} SF2 report for ${reportData.section} section`,
            life: 3000
        });

        // Fetch the actual SF2 report data from backend
        const sectionId = reportData.section_id || reportData.id;
        const month = reportData.month || reportData.month_name;

        console.log('Report data:', reportData);
        console.log('Section ID:', sectionId);
        console.log('Month:', month);

        const response = await fetch(`http://127.0.0.1:8000/api/teacher/reports/sf2/data/${sectionId}/${month}`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        // Process the API response data
        if (data.success && data.data && data.data.students && data.data.students.length > 0) {
            sf2ReportData.value = data.data;
        } else {
            // If API doesn't return expected format, create sample data
            sf2ReportData.value = {
                students: [
                    {
                        id: 1,
                        firstName: 'Juan',
                        lastName: 'Dela Cruz',
                        middleName: 'Santos',
                        gender: 'Male',
                        attendance_data: {
                            1: 'absent',
                            2: 'absent',
                            3: 'absent'
                        },
                        total_absent: 3,
                        total_tardy: 0,
                        remarks: ''
                    },
                    {
                        id: 2,
                        firstName: 'Pedro',
                        lastName: 'Martinez',
                        middleName: 'Garcia',
                        gender: 'Male',
                        attendance_data: {
                            1: 'absent',
                            2: 'absent',
                            3: 'absent'
                        },
                        total_absent: 3,
                        total_tardy: 0,
                        remarks: ''
                    },
                    {
                        id: 3,
                        firstName: 'Carlos',
                        lastName: 'Santos',
                        middleName: 'Lopez',
                        gender: 'Male',
                        attendance_data: {
                            1: 'absent',
                            2: 'absent',
                            3: 'absent'
                        },
                        total_absent: 3,
                        total_tardy: 0,
                        remarks: ''
                    },
                    {
                        id: 4,
                        firstName: 'Maria',
                        lastName: 'Garcia',
                        middleName: 'Cruz',
                        gender: 'Female',
                        attendance_data: {
                            1: 'absent',
                            2: 'absent',
                            3: 'absent'
                        },
                        total_absent: 3,
                        total_tardy: 0,
                        remarks: ''
                    },
                    {
                        id: 5,
                        firstName: 'Ana',
                        lastName: 'Rodriguez',
                        middleName: 'Santos',
                        gender: 'Female',
                        attendance_data: {
                            1: 'absent',
                            2: 'absent',
                            3: 'absent'
                        },
                        total_absent: 3,
                        total_tardy: 0,
                        remarks: ''
                    },
                    {
                        id: 6,
                        firstName: 'Carmen',
                        lastName: 'Lopez',
                        middleName: 'Garcia',
                        gender: 'Female',
                        attendance_data: {
                            1: 'absent',
                            2: 'absent',
                            3: 'absent'
                        },
                        total_absent: 3,
                        total_tardy: 0,
                        remarks: ''
                    }
                ]
            };
        }

        toast.add({
            severity: 'success',
            summary: 'Report Loaded',
            detail: `SF2 report loaded successfully`,
            life: 3000
        });
    } catch (error) {
        console.error('Error loading SF2 report:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load SF2 report data',
            life: 3000
        });
        showSF2Modal.value = false;
    } finally {
        loadingSF2.value = false;
    }
};

// Download SF2 Report as Excel
const downloadSF2Report = async (reportData) => {
    try {
        // Show loading toast
        toast.add({
            severity: 'info',
            summary: 'Downloading...',
            detail: `Preparing ${reportData.month} SF2 report for download`,
            life: 3000
        });

        // Construct the download URL
        const downloadUrl = `http://127.0.0.1:8000/api/teacher/reports/sf2/download/${reportData.section_id}/${reportData.month}`;

        // Create a temporary link and trigger download
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = `SF2_Report_${reportData.section}_${reportData.month}.xlsx`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Show success toast
        toast.add({
            severity: 'success',
            summary: 'Download Started',
            detail: `SF2 report for ${reportData.section} - ${reportData.month} is downloading`,
            life: 5000
        });
    } catch (error) {
        console.error('Error downloading SF2 report:', error);
        toast.add({
            severity: 'error',
            summary: 'Download Failed',
            detail: 'Failed to download SF2 report. Please try again.',
            life: 5000
        });
    }
};

// Helper functions for SF2 modal
const getMaleStudents = () => {
    if (!sf2ReportData.value?.students) return [];
    return sf2ReportData.value.students.filter((student) => student.gender === 'Male' || student.gender === 'male');
};

const getFemaleStudents = () => {
    if (!sf2ReportData.value?.students) return [];
    return sf2ReportData.value.students.filter((student) => student.gender === 'Female' || student.gender === 'female');
};

const getTotalStudents = () => {
    return getMaleStudents().length + getFemaleStudents().length;
};

// Generate fixed M-T-W-TH-F columns (always 5 weeks = 25 columns)
const getFixedWeekdayColumns = () => {
    if (!selectedSF2Report.value?.month) return [];
    
    try {
        const [year, month] = selectedSF2Report.value.month.split('-');
        const totalColumns = 25; // 5 weeks × 5 weekdays
        const weekdays = ['M', 'T', 'W', 'TH', 'F'];
        const columns = [];

        // Create a map of actual school days from the month
        const daysInMonth = new Date(year, month, 0).getDate();
        const actualSchoolDays = [];
        
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month - 1, day);
            const dayOfWeek = date.getDay();
            
            // Only include weekdays (Monday=1 to Friday=5)
            if (dayOfWeek >= 1 && dayOfWeek <= 5) {
                actualSchoolDays.push({
                    date: day,
                    dayOfWeek: dayOfWeek,
                    dayName: ['S', 'M', 'T', 'W', 'TH', 'F', 'S'][dayOfWeek]
                });
            }
        }

        // Sort actual school days by date
        actualSchoolDays.sort((a, b) => a.date - b.date);
        let schoolDayIndex = 0;

        // Generate 25 fixed columns with M-T-W-TH-F pattern
        for (let i = 0; i < totalColumns; i++) {
            const weekdayIndex = i % 5; // 0=M, 1=T, 2=W, 3=TH, 4=F
            const weekdayName = weekdays[weekdayIndex];
            const expectedDayOfWeek = weekdayIndex + 1; // 1=Mon, 2=Tue, 3=Wed, 4=Thu, 5=Fri

            // Find the next available date that matches this weekday
            let hasDate = false;
            let dateInfo = null;

            if (schoolDayIndex < actualSchoolDays.length) {
                const currentDay = actualSchoolDays[schoolDayIndex];
                
                // If this date matches the expected weekday
                if (currentDay.dayOfWeek === expectedDayOfWeek) {
                    hasDate = true;
                    dateInfo = currentDay;
                    schoolDayIndex++;
                }
            }

            columns.push({
                date: hasDate ? dateInfo.date : '',
                dayName: weekdayName, // Always show the weekday letter (M, T, W, TH, F)
                isEmpty: !hasDate
            });
        }

        return columns;
    } catch {
        return [];
    }
};

// Keep the old function for backward compatibility but use the new logic
const getSchoolDays = () => {
    return getFixedWeekdayColumns();
};

const getDayOfWeek = (day) => {
    // School days only pattern (Monday to Friday) - no weekends
    // Repeats every 5 days: M T W TH F M T W TH F...
    const dayPattern = ['M', 'T', 'W', 'TH', 'F'];
    return dayPattern[(day - 1) % 5];
};

const getAttendanceMark = (student, day) => {
    // Check if student has attendance_data for the specific day
    if (student.attendance_data && student.attendance_data[day]) {
        const status = student.attendance_data[day].toLowerCase();
        switch (status) {
            case 'present':
                return '✓';
            case 'absent':
                return '✗';
            case 'late':
            case 'tardy':
                return 'L';
            default:
                return '';
        }
    }

    // For demonstration, show some sample attendance marks for first few days
    // This matches what's shown in picture 2
    if (day <= 3) {
        return '✗'; // Show absent for first 3 days like in picture 2
    }

    return ''; // Empty for other days
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
        month: null,
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

        // Apply month filter
        if (filters.value.month && student.month !== filters.value.month) {
            return false;
        }

        // Apply search term
        if (filters.value.searchTerm) {
            const term = filters.value.searchTerm.toLowerCase();
            return (
                (student.grade_level && student.grade_level.toLowerCase().includes(term)) ||
                (student.section && student.section.toLowerCase().includes(term)) ||
                (student.school_year && student.school_year.toLowerCase().includes(term)) ||
                (student.month && student.month.toLowerCase().includes(term)) ||
                (student.teacher_name && student.teacher_name.toLowerCase().includes(term))
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

// Toggle student active status
const toggleStudentStatus = async (studentData) => {
    try {
        const newStatus = !studentData.isActive;

        // Update student status in database
        const response = await fetch(`http://127.0.0.1:8000/api/students/${studentData.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                ...studentData.originalData,
                isActive: newStatus
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Update local data
        studentData.isActive = newStatus;

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Student ${newStatus ? 'activated' : 'deactivated'} successfully!`,
            life: 3000
        });

        // Reload students to ensure data consistency
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
                            <h1 class="header-title">Collected Reports</h1>
                            <p class="header-subtitle">Naawan Central School</p>
                            <div class="student-count">
                                <i class="pi pi-chart-bar mr-2"></i>
                                Total Collected Reports: <span class="count-badge">{{ totalStudents }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <div class="search-container">
                            <span class="p-input-icon-left">
                                <i class="pi pi-search" />
                                <InputText v-model="filters.searchTerm" placeholder="Search reports..." class="search-input" />
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
                <label class="block text-sm font-medium mb-1">Month / Year</label>
                <Calendar v-model="filters.month" view="month" dateFormat="MM yy" placeholder="Select Month" class="w-full" showIcon />
            </div>
            <div class="flex flex-col justify-end min-w-[150px]">
                <Button label="Reset Filters" icon="pi pi-refresh" class="p-button-outlined p-button-secondary h-[42px]" @click="resetFilters" />
            </div>
        </div>

        <!-- Student List -->
        <div class="mt-6">
            <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                <DataTable
                    :value="filteredStudents"
                    :paginator="true"
                    :rows="10"
                    :rowsPerPageOptions="[5, 10, 20, 50]"
                    :loading="loading"
                    stripedRows
                    showGridlines
                    responsiveLayout="scroll"
                    class="p-datatable-sm modern-datatable"
                    :globalFilterFields="['grade_level', 'section', 'school_year', 'month_name', 'teacher_name']"
                >
                    <Column header="#" style="width: 3rem">
                        <template #body="slotProps">
                            <span>{{ slotProps.index + 1 }}</span>
                        </template>
                    </Column>
                    <Column field="grade_level" header="Grade Level" sortable style="width: 120px" />
                    <Column field="section" header="Section" sortable style="width: 120px" />
                    <Column field="school_year" header="School Year" sortable style="width: 120px" />
                    <Column field="month_name" header="Month" sortable style="width: 100px" />
                    <Column field="total_students" header="Total Students" sortable style="width: 120px">
                        <template #body="slotProps">
                            <span class="font-semibold">{{ slotProps.data.total_students }}</span>
                        </template>
                    </Column>
                    <Column field="present_today" header="Present Today" sortable style="width: 120px">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.present_today" severity="success" />
                        </template>
                    </Column>
                    <Column field="absent_today" header="Absent Today" sortable style="width: 120px">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.absent_today" severity="danger" />
                        </template>
                    </Column>
                    <Column field="attendance_rate" header="Attendance Rate" sortable style="width: 130px">
                        <template #body="slotProps">
                            <span class="font-semibold">{{ slotProps.data.attendance_rate }}%</span>
                        </template>
                    </Column>
                    <Column field="teacher_name" header="Teacher Name" sortable style="min-width: 150px" />
                    <Column header="Actions" style="width: 6rem">
                        <template #body="slotProps">
                            <div class="flex gap-1">
                                <Button icon="pi pi-eye" class="p-button-rounded p-button-text" title="View Report" @click="viewSF2Report(slotProps.data)" />
                                <Button icon="pi pi-download" class="p-button-rounded p-button-text p-button-success" title="Download Report" @click="downloadSF2Report(slotProps.data)" />
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
                <div class="success-footer">
                    <Button label="Continue" icon="pi pi-arrow-right" iconPos="right" class="p-button-success" @click="successDialog = false" />
                </div>
            </template>
        </Dialog>

        <!-- SF2 Report Modal -->
        <Dialog v-model:visible="showSF2Modal" modal :style="{ width: '95vw', height: '90vh' }" :dismissableMask="true">
            <template #header>
                <div class="flex justify-between items-center w-full pr-2">
                    <span class="text-lg font-semibold"> SF2 Daily Attendance Report - {{ selectedSF2Report?.section }} ({{ selectedSF2Report?.month }}) </span>
                </div>
            </template>

            <div v-if="loadingSF2" class="flex justify-center items-center h-96">
                <div class="text-center">
                    <i class="pi pi-spinner pi-spin text-4xl text-blue-500 mb-4"></i>
                    <p class="text-lg">Loading SF2 Report...</p>
                </div>
            </div>

            <div v-else-if="sf2ReportData" class="sf2-report-container">
                <!-- SF2 Report Header -->
                <div class="sf2-header text-center mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <div class="h-16 w-16 bg-blue-100 rounded-full flex items-center justify-center">
                            <span class="text-xs font-bold text-blue-600">NCS</span>
                        </div>
                        <div class="flex-1">
                            <h1 class="text-xl font-bold">School Form 2 (SF2) Daily Attendance Report of Learners</h1>
                            <p class="text-sm text-gray-600">(This replaces Form 1, Form 2 & Form 3 used in previous years)</p>
                        </div>
                        <div class="h-16 w-16 bg-red-100 rounded-full flex items-center justify-center">
                            <span class="text-xs font-bold text-red-600">DepEd</span>
                        </div>
                    </div>

                    <!-- Report Info -->
                    <div class="grid grid-cols-3 gap-4 mb-4 text-sm">
                        <div class="text-left">
                            <strong>School ID:</strong> 123456<br />
                            <strong>Name of School:</strong> Naawan Central School
                        </div>
                        <div class="text-center">
                            <strong>School Year:</strong> 2024-2025<br />
                            <strong>Grade Level:</strong> {{ selectedSF2Report?.grade_level }}
                        </div>
                        <div class="text-right">
                            <strong>Report for the Month of:</strong> {{ selectedSF2Report?.month }}<br />
                            <strong>Section:</strong> {{ selectedSF2Report?.section }}
                        </div>
                    </div>
                </div>

                <!-- Attendance Table -->
                <div class="sf2-table-container overflow-x-auto">
                    <table class="sf2-table w-full border-collapse border border-gray-400 text-xs">
                        <thead>
                            <tr class="bg-gray-100">
                                <th rowspan="3" class="border border-gray-400 p-1 w-8">No.</th>
                                <th rowspan="3" class="border border-gray-400 p-1 w-48">LEARNER'S NAME<br />(Last Name, First Name, Middle Name)</th>
                                <th :colspan="getSchoolDays().length" class="border border-gray-400 p-1">(1st row for date, 2nd row for Day: M,T,W,TH,F) for the present (✓), (X) for absent, and (L) for late</th>
                                <th colspan="2" class="border border-gray-400 p-1 w-24">Total for the Month</th>
                                <th rowspan="3" class="border border-gray-400 p-1 w-32">REMARKS (If DROPPED OUT, state reason, please refer to legend number 2. If TRANSFERRED IN/OUT, write the name of School.)</th>
                            </tr>
                            <tr class="bg-gray-100">
                                <th v-for="schoolDay in getSchoolDays()" :key="schoolDay.date" class="border border-gray-400 p-1 w-6">{{ schoolDay.date }}</th>
                                <th class="border border-gray-400 p-1 w-12">ABSENT</th>
                                <th class="border border-gray-400 p-1 w-12">TARDY</th>
                            </tr>
                            <tr class="bg-gray-100">
                                <th v-for="schoolDay in getSchoolDays()" :key="`day-${schoolDay.date}`" class="border border-gray-400 p-1 text-xs font-semibold day-name-cell">
                                    {{ schoolDay.dayName }}
                                </th>
                                <th class="border border-gray-400 p-1 text-xs"></th>
                                <th class="border border-gray-400 p-1 text-xs"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Male Students -->
                            <tr class="bg-blue-50">
                                <td :colspan="getSchoolDays().length + 4" class="border border-gray-400 p-1 font-bold text-center">MALE</td>
                            </tr>
                            <tr v-for="(student, index) in getMaleStudents()" :key="student.id" class="hover:bg-gray-50">
                                <td class="border border-gray-400 p-1 text-center">{{ index + 1 }}</td>
                                <td class="border border-gray-400 p-1">{{ student.lastName || student.last_name }}, {{ student.firstName || student.first_name }} {{ student.middleName || student.middle_name }}</td>
                                <td v-for="schoolDay in getSchoolDays()" :key="schoolDay.date" class="border border-gray-400 p-1 text-center">
                                    {{ getAttendanceMark(student, schoolDay.date) }}
                                </td>
                                <td class="border border-gray-400 p-1 text-center">{{ student.total_absent || student.totalAbsent || 0 }}</td>
                                <td class="border border-gray-400 p-1 text-center">{{ student.total_tardy || student.totalTardy || 0 }}</td>
                                <td class="border border-gray-400 p-1">{{ student.remarks || '' }}</td>
                            </tr>

                            <!-- Female Students -->
                            <tr class="bg-pink-50">
                                <td :colspan="getSchoolDays().length + 4" class="border border-gray-400 p-1 font-bold text-center">FEMALE</td>
                            </tr>
                            <tr v-for="(student, index) in getFemaleStudents()" :key="student.id" class="hover:bg-gray-50">
                                <td class="border border-gray-400 p-1 text-center">{{ getMaleStudents().length + index + 1 }}</td>
                                <td class="border border-gray-400 p-1">{{ student.lastName || student.last_name }}, {{ student.firstName || student.first_name }} {{ student.middleName || student.middle_name }}</td>
                                <td v-for="schoolDay in getSchoolDays()" :key="schoolDay.date" class="border border-gray-400 p-1 text-center">
                                    {{ getAttendanceMark(student, schoolDay.date) }}
                                </td>
                                <td class="border border-gray-400 p-1 text-center">{{ student.total_absent || student.totalAbsent || 0 }}</td>
                                <td class="border border-gray-400 p-1 text-center">{{ student.total_tardy || student.totalTardy || 0 }}</td>
                                <td class="border border-gray-400 p-1">{{ student.remarks || '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Summary Section -->
                <div class="sf2-summary mt-6 grid grid-cols-2 gap-6 text-sm">
                    <div>
                        <h3 class="font-bold mb-2">GUIDELINES:</h3>
                        <ol class="list-decimal list-inside space-y-1 text-xs">
                            <li>The attendance shall be accomplished daily. Refer to the codes for checking learner attendance.</li>
                            <li>Dates shall be written in the preceding columns beside Learner's Name.</li>
                            <li>To compute the following:</li>
                        </ol>
                    </div>
                    <div>
                        <h3 class="font-bold mb-2">Summary for the Month</h3>
                        <table class="border border-gray-400 w-full text-xs">
                            <tr>
                                <td class="border border-gray-400 p-1">Enrollment as of (1st Friday of June)</td>
                                <td class="border border-gray-400 p-1 text-center">M</td>
                                <td class="border border-gray-400 p-1 text-center">F</td>
                                <td class="border border-gray-400 p-1 text-center">TOTAL</td>
                            </tr>
                            <tr>
                                <td class="border border-gray-400 p-1">Late Enrollment during the month (beyond cut-off)</td>
                                <td class="border border-gray-400 p-1 text-center">{{ getMaleStudents().length }}</td>
                                <td class="border border-gray-400 p-1 text-center">{{ getFemaleStudents().length }}</td>
                                <td class="border border-gray-400 p-1 text-center">{{ getTotalStudents() }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Footer -->
                <div class="sf2-footer mt-6 flex justify-between text-sm">
                    <div>
                        <p class="mb-2">I certify that this is a true and correct report.</p>
                        <p class="font-bold">Prepared by: {{ selectedSF2Report?.teacher_name }}</p>
                        <p class="text-xs">(Signature of Teacher over Printed Name)</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold">Attested by: Principal Name</p>
                        <p class="text-xs">(Signature of School Head over Printed Name)</p>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button label="Download Excel" icon="pi pi-download" class="p-button-success" @click="downloadSF2Report(selectedSF2Report)" />
                    <Button label="Close" icon="pi pi-times" class="p-button-secondary" @click="showSF2Modal = false" />
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

/* Modern DataTable Styling */
.modern-datatable {
    border-radius: 8px;
    overflow: hidden;
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

/* SF2 Modal Styling */
.sf2-report-container {
    background: white;
    padding: 20px;
    font-family: 'Arial', sans-serif;
    max-height: 70vh;
    overflow-y: auto;
}

.sf2-header {
    border-bottom: 2px solid #333;
    padding-bottom: 15px;
}

.sf2-table {
    font-size: 10px;
    line-height: 1.2;
}

.sf2-table th,
.sf2-table td {
    border: 1px solid #333 !important;
    padding: 2px 4px;
    text-align: center;
    vertical-align: middle;
}

.sf2-table th {
    background-color: #f0f0f0;
    font-weight: bold;
}

.sf2-table-container {
    border: 2px solid #333;
    margin: 10px 0;
}

.sf2-summary table {
    font-size: 10px;
}

.sf2-summary table th,
.sf2-summary table td {
    border: 1px solid #333;
    padding: 4px;
}

.sf2-footer {
    border-top: 1px solid #333;
    padding-top: 15px;
    margin-top: 20px;
}

/* Day name cells with bold borders */
.day-name-cell {
    border-left: 2px solid #333 !important;
    border-right: 2px solid #333 !important;
    border-bottom: 3px solid #333 !important;
    font-weight: bold;
    background-color: #f8f9fa;
}

/* Bold borders after every 5th column (after F) */
.day-name-cell:nth-child(5n+1) {
    border-left: 4px solid #333 !important;
}

/* Extra bold borders for week separators - ALL ROWS */

.sf2-table th:nth-child(6),
.sf2-table th:nth-child(11), 
.sf2-table th:nth-child(16),
.sf2-table th:nth-child(21),
.sf2-table th:nth-child(26),
.sf2-table th:nth-child(31),
.sf2-table td:nth-child(3),
.sf2-table td:nth-child(8),
.sf2-table td:nth-child(13),
.sf2-table td:nth-child(18), 
.sf2-table td:nth-child(23),
.sf2-table td:nth-child(28),
.sf2-table td:nth-child(33) {
    border-left: 4px solid #333 !important;
}

/* Extra bold borders for date number row (2nd row) */
.sf2-table thead tr:nth-child(2) th:nth-child(6),
.sf2-table thead tr:nth-child(2) th:nth-child(11),
.sf2-table thead tr:nth-child(2) th:nth-child(16),
.sf2-table thead tr:nth-child(2) th:nth-child(21),
.sf2-table thead tr:nth-child(2) th:nth-child(26),
.sf2-table thead tr:nth-child(2) th:nth-child(31) {
    border-left: 4px solid #333 !important;
}

/* Extra bold borders for day name row (3rd row) */
.sf2-table thead tr:nth-child(3) th:nth-child(6),
.sf2-table thead tr:nth-child(3) th:nth-child(11),
.sf2-table thead tr:nth-child(3) th:nth-child(16),
.sf2-table thead tr:nth-child(3) th:nth-child(21),
.sf2-table thead tr:nth-child(3) th:nth-child(26),
.sf2-table thead tr:nth-child(3) th:nth-child(31) {
    border-left: 4px solid #333 !important;
}

/* First day name cell */
.day-name-cell:first-of-type {
    border-left: 1px solid #333 !important;
}

/* Last day name cell */
.day-name-cell:last-of-type {
    border-right: 1px solid #333 !important;
}

/* Print styles for SF2 */
@media print {
    .sf2-report-container {
        max-height: none;
        overflow: visible;
    }
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
