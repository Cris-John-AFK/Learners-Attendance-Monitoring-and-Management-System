<script setup>
import { useLayout } from '@/layout/composables/layout';
import AuthService from '@/services/AuthService';
import GuardhouseService from '@/services/GuardhouseService';
import axios from 'axios';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { QrcodeStream } from 'vue-qrcode-reader';
import { useRouter } from 'vue-router';

const { layoutState, isSidebarActive } = useLayout();
const router = useRouter();
const outsideClickListener = ref(null);
const scanning = ref(true); // Auto-start scanning
const guardPaused = ref(false); // Track if guard manually paused scanner
const attendanceRecords = ref([]);
const guestAttendanceRecords = ref([]);
const searchQuery = ref('');
const selectedStudent = ref(null);
const selectedGuest = ref(null);
const toast = useToast();

// Only for learners
const visitorType = ref('learners');

// Load students from API
const allStudents = ref([]);
axios
    .get('/api/students')
    .then((response) => {
        allStudents.value = response.data;
    })
    .catch((error) => {
        console.error('Error loading students:', error);
    });

const currentDateTime = ref(new Date());
// Removed hardcoded guard info
const statusFilter = ref('all');
const scanFeedback = ref({ show: false, type: '', message: '' });
const cameraError = ref(null);

// New verification system
const showVerificationModal = ref(false);
const verificationStudent = ref(null);
const verificationRecordType = ref('check-in');
const isLoadingVerification = ref(false);
const verificationCountdown = ref(5); // Reduced from 10 to 5 seconds
let verificationTimer = null;
const scannerEnabled = ref(true); // Track scanner status from admin

// Stats
const totalCheckins = computed(() => attendanceRecords.value.filter((record) => record.recordType === 'check-in').length);
const totalCheckouts = computed(() => attendanceRecords.value.filter((record) => record.recordType === 'check-out').length);
// Removed guest counter

// Timer reference for cleanup
const timeInterval = ref(null);

// Check scanner status from admin
const checkScannerStatus = async () => {
    try {
        const response = await axios.get(`${import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'}/api/guardhouse/scanner-status`);
        if (response.data.success) {
            const adminScannerEnabled = response.data.scanner_enabled;
            
            // If admin disabled scanner, pause scanning
            if (!adminScannerEnabled && scanning.value) {
                scanning.value = false;
                guardPaused.value = false; // Reset guard pause when admin disables
                showScanFeedback('error', 'Scanner disabled by administrator');
                console.log('Scanner disabled by admin');
            }
            // If admin enabled scanner and we're not scanning, resume ONLY if guard hasn't manually paused
            else if (adminScannerEnabled && !scanning.value && !cameraError.value && !showVerificationModal.value && !guardPaused.value) {
                scanning.value = true;
                console.log('Scanner enabled by admin');
            }
            
            scannerEnabled.value = adminScannerEnabled;
        }
    } catch (error) {
        console.error('Error checking scanner status:', error);
        // Default to enabled if we can't check status
        scannerEnabled.value = true;
    }
};

// Update time every second
onMounted(async () => {
    timeInterval.value = setInterval(() => {
        currentDateTime.value = new Date();
    }, 1000);

    console.log('Component mounted, students data:', allStudents.value);
    console.log('Students data type:', typeof allStudents.value);

    // Load today's attendance records
    await loadTodayAttendanceRecords();
    
    // Check initial scanner status
    await checkScannerStatus();
    
    // Check scanner status every 5 seconds
    setInterval(checkScannerStatus, 5000);
});

// Load today's attendance records from the database
const loadTodayAttendanceRecords = async () => {
    try {
        console.log("Loading today's attendance records...");
        const response = await GuardhouseService.getTodayRecords();

        if (response.success) {
            attendanceRecords.value = response.records || [];
            console.log('Loaded attendance records:', attendanceRecords.value.length);
        } else {
            console.error('Failed to load attendance records:', response.message);
            attendanceRecords.value = [];
        }
    } catch (error) {
        console.error('Error loading attendance records:', error);
        attendanceRecords.value = [];

        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: "Failed to load today's attendance records",
            life: 3000
        });
    }
};

// Clean up interval on component unmount
onBeforeUnmount(() => {
    if (timeInterval.value) {
        clearInterval(timeInterval.value);
        timeInterval.value = null;
    }

    // Clean up verification timer
    stopVerificationCountdown();

    // Make sure scanning is stopped to release camera
    scanning.value = false;
    console.log('Component unmounting, camera resources released');
});

watch(isSidebarActive, (newVal) => {
    if (newVal) {
        bindOutsideClickListener();
    } else {
        unbindOutsideClickListener();
    }
});

function bindOutsideClickListener() {
    if (!outsideClickListener.value) {
        outsideClickListener.value = (event) => {
            if (isOutsideClicked(event)) {
                layoutState.overlayMenuActive = false;
                layoutState.staticMenuMobileActive = false;
                layoutState.menuHoverActive = false;
            }
        };
        document.addEventListener('click', outsideClickListener.value);
    }
}

function unbindOutsideClickListener() {
    if (outsideClickListener.value) {
        document.removeEventListener('click', outsideClickListener.value);
        outsideClickListener.value = null;
    }
}

function isOutsideClicked(event) {
    const topbarEl = document.querySelector('.layout-menu-button');
    return !(topbarEl && (topbarEl.isSameNode(event.target) || topbarEl.contains(event.target)));
}

const onDetect = async (detectedCodes) => {
    try {
        console.log('QR Code Detected:', detectedCodes);
        
        // Check if scanner is enabled by admin
        if (!scannerEnabled.value) {
            console.log('Scanner disabled by administrator, ignoring QR code');
            showScanFeedback('error', 'Scanner is disabled by administrator');
            scanning.value = false;
            return;
        }
        
        if (detectedCodes.length > 0 && !isLoadingVerification.value) {
            // Pause scanning while processing to avoid multiple scans of the same code
            scanning.value = false;
            isLoadingVerification.value = true;

            const qrData = detectedCodes[0].rawValue;
            console.log('Detected QR Data:', qrData);

            // Process QR code scan with new verification system
            await processQRCodeVerification(qrData);
        } else {
            console.log('No valid QR code detected or verification in progress');
        }
    } catch (error) {
        console.error('Error in QR code detection:', error);
        showScanFeedback('error', 'Error processing QR code');
        isLoadingVerification.value = false;

        // Restart scanner after error only if enabled
        setTimeout(() => {
            if (scannerEnabled.value) {
                scanning.value = true;
            }
        }, 2000);
    }
};

const onCameraError = (error) => {
    console.error('Camera Error:', error);
    cameraError.value = error.message || 'Failed to access camera';
    scanning.value = false;

    // Automatically attempt to restart camera after a delay
    setTimeout(() => {
        if (!scanning.value && !cameraError.value) {
            restartCamera();
        }
    }, 5000);
};

const restartCamera = () => {
    cameraError.value = null;
    scanning.value = false;

    // Use a short timeout to ensure component unmounts and remounts properly
    setTimeout(() => {
        scanning.value = true;
        console.log('Camera restarted');
    }, 500);
};

// New QR Code Verification Process
const processQRCodeVerification = async (qrData) => {
    try {
        console.log('Processing QR code verification:', qrData);

        // Call new GuardhouseService to verify QR code
        const response = await GuardhouseService.verifyQRCode(qrData.trim());

        if (!response.success) {
            console.log('Invalid QR code:', qrData);
            showScanFeedback('error', response.message || 'Invalid QR code');
            await playStatusSound('error');
            isLoadingVerification.value = false;

            // Restart scanner after a delay
            setTimeout(() => {
                scanning.value = true;
            }, 2000);
            return false;
        }

        const studentData = response.student;
        const nextRecordType = response.next_record_type;

        console.log('QR code verified for student:', studentData);
        console.log('Next record type:', nextRecordType);

        // Set verification data and show modal
        console.log('Setting verification student data:', studentData);
        verificationStudent.value = studentData;
        verificationRecordType.value = nextRecordType;
        showVerificationModal.value = true;
        isLoadingVerification.value = false;

        // Start countdown timer
        startVerificationCountdown();

        return true;
    } catch (error) {
        console.error('Error verifying QR code:', error);
        showScanFeedback('error', error.message || 'Error validating QR code');
        await playStatusSound('error');
        isLoadingVerification.value = false;

        // Restart scanner after error
        setTimeout(() => {
            scanning.value = true;
        }, 2000);
        return false;
    }
};

// Handle verification modal events
const onVerificationConfirm = async (data) => {
    try {
        console.log('Confirming verification:', data);

        // Record attendance using GuardhouseService
        const response = await GuardhouseService.recordAttendance(
            data.student.id,
            data.student.qr_code,
            data.recordType,
            false, // not manual
            null // no notes
        );

        if (response.success) {
            // Create local record for display
            const record = {
                id: response.record.student_id,
                name: response.record.student_name,
                gradeLevel: response.record.grade_level,
                section: response.record.section,
                photo: response.record.photo,
                timestamp: new Date(response.record.timestamp).toLocaleTimeString(),
                date: new Date(response.record.date).toLocaleDateString(),
                recordType: response.record.record_type,
                recordId: `${response.record.id}-${Date.now()}`
            };

            // Show feedback
            showScanFeedback(data.recordType, response.message);

            // Play sound based on record type
            await playStatusSound(data.recordType === 'check-in' ? 'success' : 'checkout');

            // Add to records and show details
            attendanceRecords.value.unshift(record);
            selectedStudent.value = record;

            console.log('Attendance recorded successfully:', record);

            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: response.message,
                life: 3000
            });
        } else {
            throw new Error(response.message || 'Failed to record attendance');
        }
    } catch (error) {
        console.error('Error recording attendance:', error);
        showScanFeedback('error', error.message || 'Failed to record attendance');
        await playStatusSound('error');

        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to record attendance',
            life: 5000
        });
    } finally {
        // Close modal and restart scanner
        closeVerificationModal();
    }
};

const onVerificationReject = (data) => {
    console.log('Verification rejected:', data);
    showScanFeedback('error', 'Verification rejected by guard');

    toast.add({
        severity: 'warn',
        summary: 'Verification Rejected',
        detail: `${data.student.name} verification was rejected`,
        life: 3000
    });

    // Close modal and restart scanner
    closeVerificationModal();
};

const onVerificationSkip = () => {
    console.log('Verification skipped - proceeding to next student');

    // Close modal and restart scanner immediately
    closeVerificationModal();
};

// Countdown timer functions
const startVerificationCountdown = () => {
    verificationCountdown.value = 5; // Reduced from 10 to 5 seconds
    verificationTimer = setInterval(() => {
        verificationCountdown.value--;
        if (verificationCountdown.value <= 0) {
            // Auto-confirm when countdown reaches 0
            confirmVerification();
        }
    }, 1000);
};

const stopVerificationCountdown = () => {
    if (verificationTimer) {
        clearInterval(verificationTimer);
        verificationTimer = null;
    }
};

const closeVerificationModal = () => {
    stopVerificationCountdown();
    showVerificationModal.value = false;
    verificationStudent.value = null;
    isLoadingVerification.value = false;

    // Restart scanner after a short delay
    setTimeout(() => {
        scanning.value = true;
    }, 500);
};

// New action methods for inline verification
const confirmVerification = async () => {
    if (isLoadingVerification.value || !verificationStudent.value) return;

    isLoadingVerification.value = true;
    stopVerificationCountdown();

    try {
        await onVerificationConfirm({
            student: verificationStudent.value,
            recordType: verificationRecordType.value
        });
    } finally {
        closeVerificationModal();
    }
};

const rejectVerification = () => {
    if (isLoadingVerification.value || !verificationStudent.value) return;

    stopVerificationCountdown();
    onVerificationReject({
        student: verificationStudent.value,
        recordType: verificationRecordType.value
    });
    closeVerificationModal();
};

const handleImageError = (event) => {
    // Set default image based on gender or use generic default
    const defaultImage = verificationStudent.value?.gender === 'male' ? '/demo/images/avatar/default-male-student.png' : '/demo/images/avatar/default-female-student.png';

    event.target.src = defaultImage;
};

const showScanFeedback = (recordType, message = '') => {
    // Default messages based on record type
    let defaultMessage = '';
    let feedbackType = 'success';

    if (recordType === 'check-in') {
        defaultMessage = `Check-in successful`;
    } else if (recordType === 'check-out') {
        defaultMessage = 'Check-out successful';
    } else if (recordType === 'error') {
        defaultMessage = 'Unauthorized scan';
        feedbackType = 'error';
    }

    scanFeedback.value = {
        show: true,
        type: feedbackType,
        message: message || defaultMessage
    };

    // Hide feedback after 3 seconds
    setTimeout(() => {
        scanFeedback.value.show = false;
    }, 3000);
};

const playStatusSound = async (type) => {
    // In a real app, would play different sounds based on record type
    let sound;

    if (type === 'success') {
        sound = new Audio('/demo/sounds/success.wav');
    } else if (type === 'checkout') {
        sound = new Audio('/demo/sounds/error.wav');
    } else {
        sound = new Audio('/demo/sounds/error.wav');
    }

    // Attempt to play the sound (may fail if sounds don't exist)
    try {
        await sound.play().catch((e) => console.log('Sound play failed:', e));
    } catch (e) {
        console.log('Sound play error:', e);
    }

    console.log(`Playing ${type} sound`);
};

const manualCheckIn = async () => {
    const studentId = prompt('Enter student ID:');
    if (!studentId) return;

    try {
        // Show loading feedback
        showScanFeedback('info', 'Processing manual entry...');

        // Get student information first
        const students = await GuardhouseService.getAllStudents();
        const student = students.find((s) => s.id.toString() === studentId.toString());

        if (!student) {
            showScanFeedback('error', 'Student not found');
            toast.add({
                severity: 'error',
                summary: 'Student Not Found',
                detail: `No student found with ID: ${studentId}`,
                life: 3000
            });
            return;
        }

        // Determine record type (check-in or check-out)
        const recordType = prompt('Enter record type (check-in or check-out):', 'check-in');
        if (!recordType || !['check-in', 'check-out'].includes(recordType)) {
            showScanFeedback('error', 'Invalid record type');
            return;
        }

        const notes = prompt('Enter notes (optional):', 'Manual entry by guard');

        // Record manual attendance
        const response = await GuardhouseService.manualRecord(studentId, recordType, notes);

        if (response.success) {
            // Create local record for display
            const record = {
                id: response.record.student_id,
                name: response.record.student_name,
                gradeLevel: response.record.grade_level,
                section: response.record.section,
                photo: response.record.photo,
                timestamp: new Date(response.record.timestamp).toLocaleTimeString(),
                date: new Date(response.record.date).toLocaleDateString(),
                recordType: response.record.record_type,
                recordId: `${response.record.id}-${Date.now()}`
            };

            // Show feedback
            showScanFeedback(recordType, response.message);

            // Play sound
            await playStatusSound(recordType === 'check-in' ? 'success' : 'checkout');

            // Add to records and show details
            attendanceRecords.value.unshift(record);
            selectedStudent.value = record;

            toast.add({
                severity: 'success',
                summary: 'Manual Entry Successful',
                detail: response.message,
                life: 3000
            });
        } else {
            throw new Error(response.message || 'Failed to record manual attendance');
        }
    } catch (error) {
        console.error('Error with manual check-in:', error);
        showScanFeedback('error', error.message || 'Manual entry failed');

        toast.add({
            severity: 'error',
            summary: 'Manual Entry Failed',
            detail: error.message || 'Failed to record manual attendance',
            life: 5000
        });
    }
};

const exportReport = async () => {
    try {
        // First, show a loading message
        toast.add({
            severity: 'info',
            summary: 'Generating Report',
            detail: 'Preparing attendance report PDF...',
            life: 3000
        });

        // Filter records for today only
        const today = new Date().toLocaleDateString();
        const todayRecords = attendanceRecords.value.filter((record) => record.date === today);

        if (todayRecords.length === 0) {
            toast.add({
                severity: 'warn',
                summary: 'No Records',
                detail: 'No attendance records found for today',
                life: 3000
            });
            return;
        }

        // Format date for filename (YYYY-MM-DD)
        const dateForFilename = today.split('/').reverse().join('-');
        const filename = `GH_${dateForFilename}.pdf`;

        // Use jsPDF to generate the PDF
        const { jsPDF } = await import('jspdf');
        const { default: autoTable } = await import('jspdf-autotable');

        // Create new PDF document
        const doc = new jsPDF();

        // Add title
        doc.setFontSize(18);
        doc.text('Attendance Report', 14, 22);

        // Add date
        doc.setFontSize(12);
        doc.text(`Date: ${today}`, 14, 30);

        // Add time generated
        const timeGenerated = new Date().toLocaleTimeString();
        doc.text(`Time Generated: ${timeGenerated}`, 14, 36);

        // Add summary counts
        const checkins = todayRecords.filter((r) => r.recordType === 'check-in').length;
        const checkouts = todayRecords.filter((r) => r.recordType === 'check-out').length;

        doc.text(`Total Check-ins: ${checkins}`, 14, 44);
        doc.text(`Total Check-outs: ${checkouts}`, 14, 50);

        // Prepare data for the table
        const tableData = todayRecords.map((record) => [record.id, record.name, record.recordType === 'check-in' ? 'Check In' : 'Check Out', record.gradeLevel, record.section, record.timestamp]);

        // Generate the table
        autoTable(doc, {
            startY: 58,
            head: [['ID', 'Name', 'Type', 'Grade', 'Section', 'Time']],
            body: tableData,
            theme: 'striped',
            headStyles: { fillColor: [59, 130, 246], textColor: 255 },
            alternateRowStyles: { fillColor: [240, 245, 255] }
        });

        // Save the PDF
        doc.save(filename);

        toast.add({
            severity: 'success',
            summary: 'Report Generated',
            detail: `Report saved as ${filename}`,
            life: 3000
        });
    } catch (error) {
        console.error('Error generating PDF:', error);
        toast.add({
            severity: 'error',
            summary: 'Export Failed',
            detail: 'Failed to generate PDF report',
            life: 3000
        });
    }
};

const filteredRecords = computed(() => {
    let records = attendanceRecords.value;

    // Apply record type filter
    if (statusFilter.value !== 'all') {
        records = records.filter((record) => {
            return record.recordType === statusFilter.value; // Filter by check-in or check-out
        });
    }

    // Apply search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        records = records.filter((record) => record.name.toLowerCase().includes(query) || record.id.toString().includes(query) || record.gradeLevel?.toString().toLowerCase().includes(query) || record.section?.toLowerCase().includes(query));
    }

    return records;
});

const filteredGuestRecords = computed(() => {
    let records = guestAttendanceRecords.value;

    // Apply search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        records = records.filter((record) => record.name.toLowerCase().includes(query) || record.purpose.toLowerCase().includes(query) || record.personToVisit.toLowerCase().includes(query) || record.department.name.toLowerCase().includes(query));
    }

    return records;
});

// Open guest form dialog
const openGuestForm = () => {
    resetGuestForm();
    guestDialog.value = true;
};

// Reset guest form
const resetGuestForm = () => {
    guest.value = {
        id: null,
        name: '',
        purpose: '',
        contactNumber: '',
        personToVisit: '',
        department: ''
    };
    guestFormSubmitted.value = false;
};

// Submit guest form
const submitGuestForm = () => {
    guestFormSubmitted.value = true;

    // Validate form
    if (!guest.value.name || !guest.value.purpose || !guest.value.personToVisit || !guest.value.department) {
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'Please fill in all required fields',
            life: 3000
        });
        return;
    }

    // Create guest record
    const guestRecord = {
        ...guest.value,
        id: `G-${Date.now().toString().slice(-6)}`,
        timestamp: new Date().toLocaleTimeString(),
        date: new Date().toLocaleDateString(),
        recordId: `guest-${Date.now()}` // Unique ID for each record
    };

    // Add to records and show details
    guestAttendanceRecords.value.unshift(guestRecord); // Add to beginning
    selectedGuest.value = guestRecord;

    // Show success message
    toast.add({
        severity: 'success',
        summary: 'Guest Registered',
        detail: `${guest.value.name} has been registered successfully`,
        life: 3000
    });

    // Close dialog and switch back to learners mode
    guestDialog.value = false;
    visitorType.value = 'learners';
};

// Toggle scanning function that respects admin settings
const toggleScanning = () => {
    // Only block if admin has explicitly disabled the scanner
    if (!scannerEnabled.value) {
        showScanFeedback('error', 'Scanner is disabled by administrator');
        return;
    }
    
    scanning.value = !scanning.value;
    
    if (scanning.value) {
        guardPaused.value = false; // Clear guard pause when resuming
        console.log('Scanner resumed by guard');
    } else {
        guardPaused.value = true; // Set guard pause when pausing
        console.log('Scanner paused by guard');
    }
};

const logout = async () => {
    try {
        console.log('🚪 Guardhouse logging out...');

        // Use unified AuthService to properly logout
        await AuthService.logout();

        console.log('✅ Logout successful, clearing session data');

        // Use window.location for a hard redirect to ensure clean state
        console.log('🔄 Redirecting to homepage...');
        window.location.href = '/';
    } catch (error) {
        console.error('❌ Logout error:', error);
        // Even if logout fails, clear local data and redirect
        AuthService.clearAuthData();
        
        // Force redirect even on error
        window.location.href = '/';
    }
};
</script>

<template>
    <div class="layout-wrapper">
        <Toast />
        <!-- Fixed Header -->
        <header class="dashboard-header">
            <div class="header-left">
                <img src="/demo/images/logo.png" alt="School Logo" class="school-logo" />
                <h1>Learner's Attendance Monitoring and Management System (LAMMS)</h1>
            </div>
            <div class="header-center">
                <div class="date-time">
                    <div class="date">{{ currentDateTime.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</div>
                    <div class="time">{{ currentDateTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) }}</div>
                </div>
            </div>
            <div class="header-right">
                <button @click="logout" class="logout-button">
                    <i class="pi pi-sign-out"></i>
                    Logout
                </button>
            </div>
        </header>

        <!-- Main Dashboard -->
        <header class="dashboard-container">
            <div class="dashboard-content">
                <!-- Two-column layout for main content -->
                <div class="main-content-columns">
                    <!-- Left Column: QR Scanner -->
                    <div class="left-column">
                        <!-- QR Scanner Section -->
                        <div class="scanner-section">
                            <div class="section-header">
                                <h2><i class="pi pi-camera"></i> QR Scanner</h2>
                                <div class="scanner-actions">
                                    <button 
                                        @click="toggleScanning" 
                                        class="action-button pause-button" 
                                        :class="{ 'resume-state': !scanning, 'disabled': !scannerEnabled }"
                                        :disabled="!scannerEnabled"
                                        :title="!scannerEnabled ? 'Scanner disabled by administrator' : ''"
                                    >
                                        <i :class="scanning ? 'pi pi-pause' : 'pi pi-play'"></i>
                                        {{ scanning ? 'Pause' : 'Resume' }}
                                        <span v-if="!scannerEnabled" class="admin-disabled"> (Admin Disabled)</span>
                                    </button>
                                    <button @click="manualCheckIn" class="action-button manual-button">
                                        <i class="pi pi-pencil"></i>
                                        Manual
                                    </button>
                                </div>
                            </div>

                            <div class="scanner-container" :class="{ 'scanning-active': scanning, 'disabled': !scannerEnabled }">
                                <!-- Show verification content when verifying student -->
                                <div v-if="showVerificationModal && verificationStudent" class="verification-content">
                                    <div class="verification-header">
                                        <h3>{{ verificationRecordType === 'check-in' ? 'Student Check-In Verification' : 'Student Check-Out Verification' }}</h3>
                                    </div>

                                    <!-- Student Photo and Info -->
                                    <div class="student-display">
                                        <div class="photo-container">
                                            <img :src="verificationStudent.photo || '/demo/images/avatar/default-student.png'" :alt="verificationStudent.name || 'Student'" class="student-photo" @error="handleImageError" />
                                        </div>

                                        <div class="student-info">
                                            <h4 class="student-name">{{ verificationStudent.name || 'Unknown Student' }}</h4>
                                            <div class="info-grid">
                                                <div class="info-item">
                                                    <span class="label">ID:</span>
                                                    <span class="value">{{ verificationStudent.id || 'N/A' }}</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="label">Grade:</span>
                                                    <span class="value">{{ verificationStudent.gradeLevel || 'N/A' }}</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="label">Section:</span>
                                                    <span class="value">{{ verificationStudent.section || 'N/A' }}</span>
                                                </div>
                                                <div class="info-item">
                                                    <span class="label">Action:</span>
                                                    <span class="value record-type" :class="verificationRecordType === 'check-in' ? 'check-in' : 'check-out'">
                                                        <i :class="verificationRecordType === 'check-in' ? 'pi pi-sign-in' : 'pi pi-sign-out'"></i>
                                                        {{ verificationRecordType === 'check-in' ? 'Check In' : 'Check Out' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Countdown Timer -->
                                    <div class="countdown-section">
                                        <div class="countdown-circle" :class="{ urgent: verificationCountdown <= 3 }">
                                            <div class="countdown-number">{{ verificationCountdown }}</div>
                                            <div class="countdown-label">seconds</div>
                                        </div>
                                        <p class="verification-text">Please verify this student's identity</p>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="action-buttons">
                                        <button class="confirm-btn" @click="confirmVerification" :disabled="isLoadingVerification">
                                            <i class="pi pi-check"></i>
                                            Confirm
                                        </button>
                                        <button class="reject-btn" @click="rejectVerification" :disabled="isLoadingVerification">
                                            <i class="pi pi-times"></i>
                                            Reject
                                        </button>
                                    </div>

                                    <!-- Processing Indicator -->
                                    <div v-if="isLoadingVerification" class="processing-overlay">
                                        <div class="spinner"></div>
                                        <p>Recording attendance...</p>
                                    </div>
                                </div>

                                <!-- Show camera feed when scanning -->
                                <qrcode-stream v-else-if="scanning && !cameraError && !showVerificationModal" @detect="onDetect" @error="onCameraError" class="qr-scanner" :torch="false" :camera="'auto'"></qrcode-stream>

                                <!-- Show paused message when not scanning -->
                                <div v-else-if="!scanning && !cameraError && !showVerificationModal" class="scanner-paused">
                                    <i class="pi pi-camera-off"></i>
                                    <p>Scanner paused</p>
                                </div>

                                <!-- Show error message when camera fails -->
                                <div v-else-if="cameraError && !showVerificationModal" class="scanner-error">
                                    <i class="pi pi-exclamation-triangle"></i>
                                    <p>{{ cameraError || 'Camera error occurred' }}</p>
                                    <button @click="restartCamera" class="restart-button">
                                        <i class="pi pi-refresh"></i>
                                        Retry Camera
                                    </button>
                                </div>

                                <!-- Scan feedback notification -->
                                <div v-if="scanFeedback.show" :class="['scan-feedback', 'feedback-' + scanFeedback.type]">
                                    <i :class="scanFeedback.type === 'success' ? 'pi pi-check-circle' : scanFeedback.type === 'checkout' ? 'pi pi-check-circle' : 'pi pi-exclamation-circle'"></i>
                                    {{ scanFeedback.message }}
                                </div>
                            </div>

                            <!-- Student Preview Section -->
                            <div class="student-preview" v-if="selectedStudent">
                                <div class="preview-header" :class="selectedStudent.recordType === 'check-in' ? 'record-checkin' : 'record-checkout'">
                                    <div class="record-badge" :class="selectedStudent.recordType === 'check-in' ? 'record-checkin' : 'record-checkout'">
                                        <i :class="selectedStudent.recordType === 'check-in' ? 'pi pi-sign-in' : 'pi pi-sign-out'"></i>
                                        {{ selectedStudent.recordType === 'check-in' ? 'Check In' : 'Check Out' }}
                                    </div>
                                    <div class="timestamp">{{ selectedStudent.timestamp }}</div>
                                </div>

                                <div class="preview-content">
                                    <div class="student-photo-container">
                                        <img :src="selectedStudent.photo" alt="Student Photo" class="student-photo" />
                                    </div>
                                    <div class="student-info">
                                        <h3>{{ selectedStudent.name }}</h3>
                                        <div class="info-row">
                                            <span class="info-label">ID:</span>
                                            <span class="info-value">{{ selectedStudent.id }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Grade:</span>
                                            <span class="info-value">{{ selectedStudent.gradeLevel }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Section:</span>
                                            <span class="info-value">{{ selectedStudent.section }}</span>
                                        </div>
                                        <div class="info-row" v-if="selectedStudent.contact">
                                            <span class="info-label">Contact:</span>
                                            <span class="info-value">{{ selectedStudent.contact }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty state when no student is selected -->
                            <div class="student-preview empty-state" v-else>
                                <div class="empty-content">
                                    <i class="pi pi-user-plus"></i>
                                    <p>Scan a student ID or enter manually to see details</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Attendance Feed -->
                    <div class="right-column">
                        <!-- Learners Only -->

                        <!-- Attendance Feed -->
                        <div class="attendance-feed">
                            <div class="section-header">
                                <h2><i class="pi pi-list"></i> Attendance Feed</h2>
                                <div class="feed-actions">
                                    <div class="search-container">
                                        <span class="p-input-icon-left">
                                            <i class="pi pi-search"></i>
                                            <input v-model="searchQuery" type="text" placeholder="Search..." class="search-input" />
                                        </span>
                                    </div>

                                    <div class="filter-buttons">
                                        <button @click="statusFilter = 'all'" :class="['filter-button', statusFilter === 'all' ? 'active' : '']">All</button>
                                        <button @click="statusFilter = 'check-in'" :class="['filter-button', statusFilter === 'check-in' ? 'active' : '']"><i class="pi pi-sign-in"></i> Check-ins</button>
                                        <button @click="statusFilter = 'check-out'" :class="['filter-button', statusFilter === 'check-out' ? 'active' : '']"><i class="pi pi-sign-out"></i> Check-outs</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendance feed -->
                            <div class="attendance-columns">
                                <!-- Learners Attendance -->
                                <div class="attendance-column">
                                    <!-- Empty state for learner attendance feed -->
                                    <div v-if="filteredRecords.length === 0" class="empty-feed">
                                        <i class="pi pi-calendar-times"></i>
                                        <p>No learner attendance records found</p>
                                        <span>Records will appear here as learners check in</span>
                                    </div>

                                    <!-- Learner attendance records table -->
                                    <DataTable v-else :value="filteredRecords" paginator :rows="10" class="attendance-table" responsiveLayout="scroll" :rowHover="true" v-model:selection="selectedStudent" selectionMode="single" dataKey="id">
                                        <Column field="id" header="ID" :sortable="true"></Column>
                                        <Column field="name" header="Name" :sortable="true"></Column>
                                        <Column field="recordType" header="Type" :sortable="true">
                                            <template #body="slotProps">
                                                <span :class="['record-type', slotProps.data.recordType === 'check-in' ? 'type-in' : 'type-out']">
                                                    <i :class="slotProps.data.recordType === 'check-in' ? 'pi pi-sign-in' : 'pi pi-sign-out'"></i>
                                                    {{ slotProps.data.recordType === 'check-in' ? 'Check In' : 'Check Out' }}
                                                </span>
                                            </template>
                                        </Column>
                                        <Column field="gradeLevel" header="Grade" :sortable="true"></Column>
                                        <Column field="section" header="Section"></Column>
                                        <Column field="timestamp" header="Time" :sortable="true"></Column>
                                    </DataTable>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fixed Footer -->
                <div class="dashboard-footer">
                    <div class="footer-stats">
                        <div class="stat-item">
                            <div class="stat-label">Check-ins</div>
                            <div class="stat-value">{{ totalCheckins }}</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Check-outs</div>
                            <div class="stat-value">{{ totalCheckouts }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
    </div>
</template>

<style lang="scss" scoped>
:root {
    --color-primary: #3b82f6;
    --color-secondary: #64748b;
    --color-dark: #1e293b;
    --color-light: #f8fafc;
    --color-ontime: #10b981;
    --color-late: #f59e0b;
    --color-danger: #ef4444;

    --radius-sm: 0.25rem;
    --radius-md: 0.5rem;
    --radius-lg: 1rem;

    --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
}

/* Override QR code reader styles to remove blue circle */
:deep(.qrcode-stream) .qrcode-stream__inner-wrapper::after {
    display: none !important;
}

:deep(.qrcode-stream) .qrcode-stream__overlay {
    display: none !important;
}

:deep(.qrcode-stream) .qrcode-stream__camera {
    border-radius: 8px;
    overflow: hidden;
}

/* Global Styles */
.layout-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    background: #f1f5f9;
}

/* Header Styles */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    background: white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    position: sticky;
    top: 0;
    z-index: 10;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.school-logo {
    height: 55px;
    width: auto;
}

.header-left h1 {
    font-size: 1.8rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
}

.header-center {
    text-align: center;
    margin-bottom: 2px;
}

.date-time {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.date {
    font-size: 1.2rem;
    color: #64748b;
}

.time {
    font-size: 1.8rem;
    font-weight: 600;
    color: #1e293b;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-left: 10px;
}

.guard-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;

    i {
        font-size: 2rem;
        color: #64748b;
        background: #f1f5f9;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    div {
        display: flex;
        flex-direction: column;
    }
}

.guard-name {
    font-size: 1.2rem;
    font-weight: 600;
    color: #1e293b;
}

.guard-id {
    font-size: 1.2rem;
    color: #64748b;
}

.logout-button {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 2rem;
    border-radius: 0.75rem;
    border: 3px solid #e2e8f0;
    background: white;
    color: #64748b;
    font-size: 1.2rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 55px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);

    &:hover {
        background: #f8fafc;
        border-color: #3b82f6;
        color: #3b82f6;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    i {
        font-size: 1.3rem;
    }
}

/* Main Container Styles */
.dashboard-container {
    flex: 1;
    padding: 1.5rem 2rem;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.dashboard-content {
    height: 100%;
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.main-content-columns {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 1.5rem;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

.left-column,
.right-column {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    overflow: hidden;
    min-height: 0;
}

/* Visitor Toggle Styles */
.visitor-toggle-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    grid-column: 1 / -1;
}

.visitor-toggle-buttons {
    display: flex;
    gap: 0.5rem;
}

.visitor-toggle-button {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 2rem 3rem;
    border-radius: 1rem;
    border: 3px solid #e2e8f0;
    background: white;
    color: #64748b;
    font-size: 1.6rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 75px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);

    &:hover {
        background: #f8fafc;
        border-color: #3b82f6;
        color: #3b82f6;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    &.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
        box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
    }

    i {
        font-size: 1.8rem;
    }
}

.guest-register-button {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 2rem 3rem;
    background-color: #10b981;
    color: white;
    border: none;
    border-radius: 1rem;
    font-weight: 700;
    font-size: 1.6rem;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 75px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);

    &:hover {
        background-color: #059669;
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
    }

    i {
        font-size: 1.8rem;
    }
}

/* Attendance Columns */
.attendance-columns {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow: auto;
    padding: 0 1.5rem 1.5rem 1.5rem;
}

.attendance-column {
    flex: 1;
    overflow: auto;
    min-height: 0;
}

/* Guest Dialog Styles */
.guest-dialog .guest-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.guest-form .form-field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.guest-form label {
    font-weight: 500;
    color: #374151;
}

.guest-dialog .p-dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

.cancel-button {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem 2rem;
    background-color: #f3f4f6;
    color: #4b5563;
    border: 3px solid #d1d5db;
    border-radius: 0.75rem;
    font-weight: 700;
    font-size: 1.4rem;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 60px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);

    &:hover {
        background-color: #e5e7eb;
        border-color: #9ca3af;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    i {
        font-size: 1.3rem;
    }
}

.submit-button {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1.25rem 2rem;
    background-color: #10b981;
    color: white;
    border: none;
    border-radius: 0.75rem;
    font-weight: 700;
    font-size: 1.4rem;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 60px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);

    &:hover {
        background-color: #059669;
        transform: translateY(-1px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
    }

    i {
        font-size: 1.3rem;
    }
}

/* Section Header Styles */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;

    h2 {
        font-size: 2rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;

        i {
            color: #3b82f6;
            font-size: 2.2rem;
        }
    }
}

/* Scanner Section Styles */
.scanner-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.scanner-actions {
    display: flex;
    gap: 0.5rem;
}

.action-button {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 2rem 3rem;
    border-radius: 1rem;
    border: 3px solid #3b82f6;
    background: white;
    color: #3b82f6;
    font-size: 1.6rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 75px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);

    &:hover {
        background: #3b82f6;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
    }

    i {
        font-size: 1.8rem;
    }
}

.pause-button {
    background-color: #ef4444;
    color: white;
    border-color: #ef4444;
    box-shadow: 0 2px 4px rgba(239, 68, 68, 0.4);

    &:hover {
        background-color: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(239, 68, 68, 0.5);
    }
}

.pause-button.resume-state {
    background-color: #10b981;
    color: white;
    border-color: #10b981;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.4);

    &:hover {
        background-color: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(16, 185, 129, 0.5);
    }
}

.manual-button {
    background-color: #8b5cf6;
    color: white;
    border-color: #8b5cf6;
    box-shadow: 0 2px 4px rgba(139, 92, 246, 0.4);

    &:hover {
        background-color: #7c3aed;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(139, 92, 246, 0.5);
    }
}

.scanner-container {
    position: relative;
    height: 400px;
    width: 400px;
    margin: 0 auto;
    border-radius: 0.5rem;
    overflow: hidden;
    background: #0f172a;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.qr-scanner {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Verification Content Styles */
.verification-content {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    display: flex;
    flex-direction: column;
    padding: 0.8rem;
    overflow: hidden;
    justify-content: space-between;
}

.verification-header {
    text-align: center;
    margin-bottom: 0.5rem;
}

.verification-header h3 {
    color: #2c5aa0;
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
}

.student-display {
    display: flex;
    gap: 0.8rem;
    margin-bottom: 0.8rem;
    padding: 0.8rem;
    background: white;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
    align-items: center;
    flex-shrink: 0;
    min-height: 80px;
}

.photo-container {
    flex-shrink: 0;
    width: 60px;
    height: 60px;
    overflow: hidden;
    border-radius: 50%;
}

.student-photo {
    width: 60px !important;
    height: 60px !important;
    max-width: 60px !important;
    max-height: 60px !important;
    min-width: 60px !important;
    min-height: 60px !important;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid white;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    display: block;
}

.student-info {
    flex: 1;
    min-width: 0;
}

.student-name {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 0.3rem 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.3rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.1rem 0;
}

.info-item .label {
    font-weight: 500;
    color: #64748b;
    font-size: 0.7rem;
}

.info-item .value {
    font-weight: 600;
    color: #1e293b;
    font-size: 0.7rem;
}

.record-type {
    display: flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.1rem 0.4rem;
    border-radius: 8px;
    font-size: 0.65rem;
    font-weight: 600;
}

.record-type.check-in {
    background: #dcfce7;
    color: #166534;
}

.record-type.check-out {
    background: #fef3c7;
    color: #92400e;
}

.countdown-section {
    text-align: center;
    margin-bottom: 0.8rem;
    flex-shrink: 0;
}

.countdown-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    box-shadow: 0 3px 8px rgba(59, 130, 246, 0.3);
    transition: all 0.3s ease;
}

.countdown-circle.urgent {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    animation: pulse 1s infinite;
    box-shadow: 0 3px 8px rgba(239, 68, 68, 0.4);
}

@keyframes pulse {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

.countdown-number {
    font-size: 1.2rem;
    font-weight: 700;
    color: white;
    line-height: 1;
}

.countdown-label {
    font-size: 0.5rem;
    color: rgba(255, 255, 255, 0.9);
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.verification-text {
    color: #64748b;
    font-size: 0.75rem;
    margin: 0;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    flex-shrink: 0;
}

.action-buttons button {
    padding: 0.5rem 0.8rem;
    border: none;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.7rem;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.3rem;
    flex: 1;
    justify-content: center;
}

.confirm-btn {
    background: #10b981;
    color: white;
}

.confirm-btn:hover:not(:disabled) {
    background: #059669;
    transform: translateY(-1px);
}

.reject-btn {
    background: #ef4444;
    color: white;
}

.reject-btn:hover:not(:disabled) {
    background: #dc2626;
    transform: translateY(-1px);
}

.next-btn {
    background: #64748b;
    color: white;
}

.next-btn:hover:not(:disabled) {
    background: #475569;
    transform: translateY(-1px);
}

.action-buttons button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.processing-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.95);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    z-index: 10;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #e2e8f0;
    border-top: 4px solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.processing-overlay p {
    margin-top: 1rem;
    color: #64748b;
    font-weight: 500;
}

.scanner-paused {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: red;
    font-weight: bold;
    background: rgba(104, 105, 105, 0.582);
}

.scanner-paused i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.7;
}

.scanner-paused p {
    font-size: 1.25rem;
    opacity: 0.9;
}

.scanner-error {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    background: rgba(15, 23, 42, 0.9);
}

.scanner-error i {
    font-size: 3rem;
    color: #ef4444;
    margin-bottom: 1rem;
}

.scanner-error p {
    font-size: 1.25rem;
    opacity: 0.9;
    margin-bottom: 1.5rem;
}

.restart-button {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem 3rem;
    border-radius: 0.75rem;
    border: none;
    background: #ef4444;
    color: white;
    font-weight: 700;
    font-size: 1.4rem;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 65px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);

    &:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
    }

    i {
        font-size: 1.5rem;
    }
}

.scan-feedback {
    position: absolute;
    top: 1rem;
    left: 50%;
    transform: translateX(-50%);
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 600;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
    animation: fadeInOut 3s ease-in-out;
}

.feedback-success {
    background: rgba(16, 185, 129, 0.9);
    color: white;
}

.feedback-checkout {
    background: rgba(245, 158, 11, 0.9);
    color: white;
}

.feedback-error {
    background: rgba(239, 68, 68, 0.9);
    color: white;
}

@keyframes fadeInOut {
    0% {
        opacity: 0;
        transform: translate(-50%, -20px);
    }
    10% {
        opacity: 1;
        transform: translate(-50%, 0);
    }
    90% {
        opacity: 1;
        transform: translate(-50%, 0);
    }
    100% {
        opacity: 0;
        transform: translate(-50%, -20px);
    }
}

.student-preview {
    background: white;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
}

.preview-header {
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.preview-header.record-checkin {
    background: rgba(16, 185, 129, 0.1);
}

.preview-header.record-checkout {
    background: rgba(59, 130, 246, 0.1);
}

.preview-header.status-on-time {
    background: rgba(16, 185, 129, 0.1);
}

.preview-header.status-late {
    background: rgba(245, 158, 11, 0.1);
}

.preview-header.status-unauthorized {
    background: rgba(239, 68, 68, 0.1);
}

.status-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
}

.status-badge.status-on-time {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.status-badge.status-late {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.status-badge.status-unauthorized {
    background: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.timestamp {
    font-size: 0.875rem;
    color: #64748b;
}

.preview-content {
    padding: 1.5rem;
    display: flex;
    gap: 1.5rem;
}

.student-photo-container {
    width: 100px;
    height: 120px;
    border-radius: 0.5rem;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.student-photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.student-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.student-info h3 {
    margin: 0 0 0.5rem 0;
    font-size: 1.25rem;
    color: #1e293b;
}

.info-row {
    display: flex;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.info-label {
    font-weight: 600;
    color: #64748b;
    width: 70px;
}

.info-value {
    color: #334155;
}

.empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem;
}

.empty-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.empty-content i {
    font-size: 3rem;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.empty-content p {
    color: #64748b;
    font-size: 1rem;
}

/* Attendance Feed Styles */
.attendance-feed {
    display: flex;
    flex-direction: column;
    background: white;
    border-radius: 0.5rem;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    flex: 1;
    min-height: 0;

    .section-header {
        padding: 1.5rem 1.5rem 1rem 1.5rem;
        margin-bottom: 0;
    }
}

.feed-actions {
    display: flex;
    flex-direction: column;
    align-items: stretch;
}

.search-container {
    position: relative;
    width: 100%;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
    border: 1px solid #e2e8f0;
    font-size: 1rem;
    transition: all 0.2s;

    &:focus {
        outline: none;
        border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
}

.p-input-icon-left {
    position: relative;
    width: 100%;

    i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1rem;
    }
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-top: 1.5rem;
}

.filter-button {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    border: 2px solid #e2e8f0;
    background: white;
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 40px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);

    &:hover {
        background: #f8fafc;
        border-color: #3b82f6;
        color: #3b82f6;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }

    &.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.4);
    }

    i {
        font-size: 1rem;
    }
}

.empty-feed {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;

    i {
        font-size: 3rem;
        color: #cbd5e1;
        margin-bottom: 1rem;
    }

    p {
        color: #1e293b;
        font-size: 1.25rem;
        font-weight: 600;
        margin: 0 0 0.5rem 0;
    }

    span {
        color: #64748b;
        font-size: 0.875rem;
    }
}

/* DataTable Customizations */
:deep(.p-datatable) {
    .p-datatable-thead > tr > th {
        background: #f8fafc;
        color: #64748b;
        font-weight: 600;
        padding: 0.75rem 1rem;
        border-bottom: 2px solid #e2e8f0;
    }

    .p-datatable-tbody > tr {
        transition: background-color 0.2s;

        &:hover {
            background-color: #f1f5f9;
        }

        > td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        &:nth-child(even) {
            background-color: #f8fafc;
        }
    }
}

/* Footer Styles */
.dashboard-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 2rem;
    background: white;
    box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.05);
}

.footer-stats {
    display: flex;
    gap: 2rem;
}

.stat-item {
    text-align: center;

    .stat-label {
        font-size: 0.8rem;
        color: #64748b;
        margin-bottom: 0.25rem;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
}

.footer-actions {
    .export-button {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        padding: 2rem 3.5rem;
        background: #8b5cf6;
        color: white;
        border: none;
        border-radius: 1rem;
        font-weight: 700;
        font-size: 1.6rem;
        cursor: pointer;
        transition: all 0.2s;
        min-height: 75px;
        box-shadow: 0 4px 8px rgba(139, 92, 246, 0.4);

        &:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(139, 92, 246, 0.5);
        }

        i {
            font-size: 1.8rem;
        }
    }
}

/* Main Content Layout */
.main-content-columns {
    display: grid;
    grid-template-columns: 1fr 1fr; /* Two equal columns */
    gap: 1.5rem;
}

/* Responsive Adjustments */
@media (max-width: 1024px) {
    .dashboard-header {
        padding: 1rem;
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .dashboard-header h1 {
        font-size: 1.25rem;
        line-height: 1.2;
    }

    .date-time .date {
        font-size: 0.875rem;
    }

    .date-time .time {
        font-size: 1.25rem;
    }

    .guard-info {
        font-size: 0.75rem;
    }

    .logout-button {
        padding: 0.5rem 1rem;
        font-size: 0.75rem;
    }

    .main-content-columns {
        flex-direction: column;
        gap: 1.5rem;
    }

    .left-column,
    .right-column {
        width: 100%;
    }

    .scanner-actions {
        flex-direction: column;
        gap: 0.75rem;
    }

    .action-button {
        padding: 0.75rem 1rem;
        font-size: 0.75rem;
        width: 100%;
        min-height: 40px;
        border-radius: 0.5rem;

        i {
            font-size: 1rem;
        }
    }

    .visitor-toggle-buttons {
        flex-direction: column;
        gap: 0.5rem;
    }

    .visitor-toggle-button {
        padding: 0.75rem 1rem;
        font-size: 0.75rem;
        width: 100%;
        min-height: 40px;
        border-radius: 0.5rem;

        i {
            font-size: 1rem;
        }
    }

    .guest-register-button {
        padding: 0.75rem 1rem;
        font-size: 0.75rem;
        width: 100%;
        margin-top: 0.5rem;
        min-height: 40px;
        border-radius: 0.5rem;

        i {
            font-size: 1rem;
        }
    }

    .filter-buttons {
        flex-direction: column;
        gap: 0.5rem;
        width: 100%;
    }

    .filter-button {
        padding: 0.5rem 0.75rem;
        font-size: 0.75rem;
        width: 100%;
        min-height: 40px;
        border-radius: 0.5rem;

        i {
            font-size: 1rem;
        }
    }

    .section-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }

    .section-header h2 {
        font-size: 1rem;
        text-align: center;
    }

    .feed-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }

    .search-input {
        width: 100%;
        padding: 0.75rem;
        font-size: 0.875rem;
    }

    .scanner-container {
        height: 200px;
    }

    .student-preview {
        padding: 0.75rem;
    }

    .student-info h3 {
        font-size: 0.875rem;
    }

    .info-row {
        font-size: 0.75rem;
    }

    .stat-item {
        min-width: 60px;
    }

    .stat-label {
        font-size: 0.75rem;
    }

    .stat-value {
        font-size: 1.25rem;
    }

    .export-button {
        padding: 0.75rem 1rem !important;
        font-size: 0.75rem !important;
        width: 100% !important;
        min-height: 40px !important;
        border-radius: 0.5rem !important;
        gap: 0.75rem !important;

        i {
            font-size: 1rem !important;
        }
    }

    .dashboard-footer {
        padding: 1rem 0.75rem;
    }

    .footer-stats {
        gap: 0.5rem;
    }
}

@media (max-width: 768px) {
    .dashboard-header h1 {
        font-size: 1rem;
        line-height: 1.2;
    }

    .date-time .date {
        font-size: 0.75rem;
    }

    .date-time .time {
        font-size: 1rem;
    }

    .scanner-container {
        height: 250px;
    }

    .student-preview {
        padding: 1rem;
    }

    .student-info h3 {
        font-size: 0.875rem;
    }

    .info-row {
        font-size: 0.75rem;
    }

    .attendance-table {
        font-size: 0.75rem;
    }

    .visitor-toggle-container {
        padding: 1rem;
    }

    .guest-dialog {
        width: 95% !important;
        margin: 1rem;
    }
}

@media (max-width: 480px) {
    .dashboard-header {
        padding: 0.75rem;
    }

    .dashboard-header h1 {
        font-size: 0.875rem;
        line-height: 1.2;
    }

    .school-logo {
        width: 30px;
        height: 30px;
    }

    .date-time .date {
        font-size: 0.625rem;
    }

    .date-time .time {
        font-size: 0.875rem;
    }

    .guard-info {
        font-size: 0.625rem;
    }

    .guard-name {
        font-size: 0.75rem;
    }

    .guard-id {
        font-size: 0.625rem;
    }

    .logout-button {
        padding: 0.5rem 0.75rem;
        font-size: 0.625rem;
    }

    .section-header h2 {
        font-size: 0.875rem;
    }

    .action-button,
    .visitor-toggle-button,
    .guest-register-button {
        padding: 0.625rem 0.875rem;
        font-size: 0.625rem;
        min-height: 35px;
        border-radius: 0.375rem;

        i {
            font-size: 0.875rem;
        }
    }

    .filter-button {
        padding: 0.5rem;
        font-size: 0.625rem;
        min-height: 35px;
        border-radius: 0.375rem;

        i {
            font-size: 0.875rem;
        }
    }

    .search-input {
        padding: 0.625rem;
        font-size: 0.75rem;
    }

    .scanner-container {
        height: 200px;
    }

    .student-preview {
        padding: 0.75rem;
    }

    .student-info h3 {
        font-size: 0.75rem;
    }

    .info-row {
        font-size: 0.625rem;
    }

    .stat-item {
        min-width: 60px;
    }

    .stat-label {
        font-size: 0.625rem;
    }

    .stat-value {
        font-size: 1rem;
    }

    .export-button {
        padding: 0.625rem 1rem !important;
        font-size: 0.625rem !important;
        width: 100%;
        min-height: 35px !important;
        border-radius: 0.375rem !important;
        gap: 0.5rem !important;

        i {
            font-size: 0.875rem !important;
        }
    }

    .dashboard-footer {
        padding: 1rem 0.75rem;
    }

    .footer-stats {
        gap: 0.5rem;
    }
}

/* Scanner disabled state styling */
.action-button.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background-color: #6c757d !important;
    border-color: #6c757d !important;
}

.action-button.disabled:hover {
    background-color: #6c757d !important;
    border-color: #6c757d !important;
    transform: none !important;
}

.admin-disabled {
    font-size: 0.7rem;
    color: #dc3545;
    font-weight: 600;
}

/* Scanner container when disabled */
.scanner-container.disabled {
    opacity: 0.6;
    pointer-events: none;
}

.scanner-container.disabled::after {
    content: "Scanner Disabled by Administrator";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(220, 53, 69, 0.9);
    color: white;
    padding: 1rem 2rem;
    border-radius: 8px;
    font-weight: 600;
    z-index: 10;
}
</style>
