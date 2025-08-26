<script setup>
import { useLayout } from '@/layout/composables/layout';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputMask from 'primevue/inputmask';
import InputText from 'primevue/inputtext';
import SelectButton from 'primevue/selectbutton';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { QrcodeStream } from 'vue-qrcode-reader';

const { layoutState, isSidebarActive } = useLayout();
const outsideClickListener = ref(null);
const scanning = ref(true); // Auto-start scanning
const attendanceRecords = ref([]);
const guestAttendanceRecords = ref([]);
const searchQuery = ref('');
const selectedStudent = ref(null);
const selectedGuest = ref(null);
const toast = useToast();

// Toggle between learners and guests
const visitorOptions = [
    { label: 'Learners', value: 'learners' },
    { label: 'Guests', value: 'guests' }
];
const visitorType = ref('learners');

// Guest form modal
const guestDialog = ref(false);
const guest = ref({
    id: null,
    name: '',
    purpose: '',
    contactNumber: '',
    personToVisit: '',
    department: ''
});
const guestFormSubmitted = ref(false);

// Department options for dropdown
const departments = [
    { name: 'Administration', code: 'admin' },
    { name: 'Faculty', code: 'faculty' },
    { name: 'Guidance', code: 'guidance' },
    { name: 'Registrar', code: 'registrar' },
    { name: 'Accounting', code: 'accounting' }
];

// Mock student data instead of using AttendanceService.getData()
const allStudents = ref([
    {
        id: '12345',
        name: 'Jess Smith',
        gradeLevel: '10',
        section: 'A',
        contact: '(555) 123-4567',
        emergencyContact: '(555) 987-6543',
        validUntil: 'June 30, 2024',
        photo: '/demo/images/student-photo.jpg'
    },
    {
        id: '23456',
        name: 'John Doe',
        gradeLevel: '11',
        section: 'B',
        contact: '(555) 234-5678',
        emergencyContact: '(555) 876-5432',
        validUntil: 'June 30, 2024',
        photo: '/demo/images/student-photo.jpg'
    },
    {
        id: '34567',
        name: 'Alice Johnson',
        gradeLevel: '9',
        section: 'C',
        contact: '(555) 345-6789',
        emergencyContact: '(555) 765-4321',
        validUntil: 'June 30, 2024',
        photo: '/demo/images/student-photo.jpg'
    }
]);

const currentDateTime = ref(new Date());
const guardName = ref('Bread Doe');
const guardId = ref('G-12345');
const statusFilter = ref('all');
const scanFeedback = ref({ show: false, type: '', message: '' });
const cameraError = ref(null);

// Stats
const totalCheckins = computed(() => attendanceRecords.value.filter((record) => record.recordType === 'check-in').length);
const totalCheckouts = computed(() => attendanceRecords.value.filter((record) => record.recordType === 'check-out').length);
const totalGuests = computed(() => guestAttendanceRecords.value.length);

// Timer reference for cleanup
const timeInterval = ref(null);

// Update time every second
onMounted(() => {
    timeInterval.value = setInterval(() => {
        currentDateTime.value = new Date();
    }, 1000);

    console.log('Component mounted, students data:', allStudents.value);
    console.log('Students data type:', typeof allStudents.value);
});

// Clean up interval on component unmount
onBeforeUnmount(() => {
    if (timeInterval.value) {
        clearInterval(timeInterval.value);
        timeInterval.value = null;
    }

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
        if (detectedCodes.length > 0) {
            // Pause scanning while processing to avoid multiple scans of the same code
            scanning.value = false;

            const studentId = detectedCodes[0].rawValue;
            console.log('Detected Student ID:', studentId);

            // Process student scan in a microtask to avoid blocking the message channel
            await new Promise((resolve) => setTimeout(resolve, 10));
            await processStudentScan(studentId);

            // Wait a moment before restarting the scanner to avoid rapid scanning
            setTimeout(() => {
                scanning.value = true;
            }, 1000);
        } else {
            console.log('No valid QR code detected');
        }
    } catch (error) {
        console.error('Error in QR code detection:', error);
        showScanFeedback('error', null, 'Error processing QR code');

        // Restart scanner after error
        setTimeout(() => {
            scanning.value = true;
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

const processStudentScan = async (scannedId) => {
    try {
        console.log('Processing student scan for ID:', scannedId);
        console.log('All students:', allStudents.value);

        // Find the student in our allStudents array
        // Handle both string and number IDs
        const student = allStudents.value.find((s) => s.id.toString() === scannedId.toString());

        console.log('Found student:', student);

        if (student) {
            // Get today's records for this student
            const todaysRecords = attendanceRecords.value.filter((record) => record.id.toString() === student.id.toString() && record.date === new Date().toLocaleDateString());

            console.log("Today's records for this student:", todaysRecords);

            // Determine record type based on the pattern of previous records
            let recordType;

            if (todaysRecords.length === 0) {
                // First scan of the day - always a check-in
                recordType = 'check-in';
            } else {
                // Get the most recent record for this student
                const latestRecord = [...todaysRecords].sort((a, b) => {
                    // Convert timestamps to Date objects for proper comparison
                    const timeA = new Date(`1/1/2023 ${a.timestamp}`);
                    const timeB = new Date(`1/1/2023 ${b.timestamp}`);
                    return timeB - timeA; // Sort in descending order (newest first)
                })[0];

                console.log('Latest record:', latestRecord);

                // If the latest record is a check-in, this should be a check-out
                // If the latest record is a check-out, this should be a check-in
                recordType = latestRecord.recordType === 'check-in' ? 'check-out' : 'check-in';
            }

            console.log('Determined record type:', recordType);

            // Create record with unique ID
            const record = {
                ...student,
                timestamp: new Date().toLocaleTimeString(),
                date: new Date().toLocaleDateString(),
                recordType: recordType,
                recordId: `${student.id}-${Date.now()}` // Unique ID for each record
            };

            // Show feedback
            showScanFeedback(recordType);

            // Play sound based on record type
            await playStatusSound(recordType === 'check-in' ? 'success' : 'checkout');

            // Add to records and show details
            attendanceRecords.value.unshift(record); // Add to beginning
            selectedStudent.value = record;

            console.log('Student record created:', record);
            return true;
        } else {
            // Invalid QR code
            console.log('Invalid student ID:', scannedId);
            showScanFeedback('error', null, 'Invalid student ID');
            await playStatusSound('error');
            return false;
        }
    } catch (error) {
        console.error('Error processing student scan:', error);
        showScanFeedback('error', null, 'An error occurred while processing the scan');
        return false;
    }
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
        sound = new Audio('/demo/sounds/beep.mp3');
    } else {
        sound = new Audio('/demo/sounds/error.mp3');
    }

    // Attempt to play the sound (may fail if sounds don't exist)
    try {
        await sound.play().catch((e) => console.log('Sound play failed:', e));
    } catch (e) {
        console.log('Sound play error:', e);
    }

    console.log(`Playing ${type} sound`);
};

const manualCheckIn = () => {
    const studentId = prompt('Enter student ID:');
    if (studentId) {
        processStudentScan(studentId);
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
                <div class="guard-info">
                    <i class="pi pi-user"></i>
                    <div>
                        <div class="guard-name">{{ guardName }}</div>
                        <div class="guard-id">ID: {{ guardId }}</div>
                    </div>
                </div>
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
                                    <button @click="scanning = !scanning" class="action-button">
                                        <i :class="scanning ? 'pi pi-pause' : 'pi pi-play'"></i>
                                        {{ scanning ? 'Pause' : 'Resume' }}
                                    </button>
                                    <button @click="manualCheckIn" class="action-button">
                                        <i class="pi pi-pencil"></i>
                                        Manual
                                    </button>
                                </div>
                            </div>

                            <div class="scanner-container" :class="{ 'scanning-active': scanning }">
                                <!-- Show camera feed when scanning -->
                                <qrcode-stream v-if="scanning && !cameraError" @detect="onDetect" @error="onCameraError" class="qr-scanner" :torch="false" :camera="'auto'"></qrcode-stream>

                                <!-- Show paused message when not scanning -->
                                <div v-else-if="!scanning && !cameraError" class="scanner-paused">
                                    <i class="pi pi-camera-off"></i>
                                    <p>Scanner paused</p>
                                </div>

                                <!-- Show error message when camera fails -->
                                <div v-else class="scanner-error">
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
                        <!-- Toggle Button for Learners/Guests -->
                        <div class="visitor-toggle-container">
                            <SelectButton v-model="visitorType" :options="visitorOptions" optionLabel="label" optionValue="value" class="visitor-toggle" />
                            <button v-if="visitorType === 'guests'" @click="openGuestForm" class="guest-register-button">
                                <i class="pi pi-user-plus"></i>
                                Register Guest
                            </button>
                        </div>

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

                                    <div v-if="visitorType === 'learners'" class="filter-buttons">
                                        <button @click="statusFilter = 'all'" :class="['filter-button', statusFilter === 'all' ? 'active' : '']">All</button>
                                        <button @click="statusFilter = 'check-in'" :class="['filter-button', statusFilter === 'check-in' ? 'active' : '']"><i class="pi pi-sign-in"></i> Check-ins</button>
                                        <button @click="statusFilter = 'check-out'" :class="['filter-button', statusFilter === 'check-out' ? 'active' : '']"><i class="pi pi-sign-out"></i> Check-outs</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Two-column layout for attendance feeds -->
                            <div class="attendance-columns">
                                <!-- Learners Attendance -->
                                <div v-if="visitorType === 'learners'" class="attendance-column">
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

                                <!-- Guest Attendance -->
                                <div v-if="visitorType === 'guests'" class="attendance-column">
                                    <!-- Empty state for guest attendance feed -->
                                    <div v-if="filteredGuestRecords.length === 0" class="empty-feed">
                                        <i class="pi pi-calendar-times"></i>
                                        <p>No guest attendance records found</p>
                                        <span>Register a guest to see records here</span>
                                    </div>

                                    <!-- Guest attendance records table -->
                                    <DataTable v-else :value="filteredGuestRecords" paginator :rows="10" class="attendance-table" responsiveLayout="scroll" :rowHover="true" v-model:selection="selectedGuest" selectionMode="single" dataKey="id">
                                        <Column field="id" header="ID" :sortable="true"></Column>
                                        <Column field="name" header="Name" :sortable="true"></Column>
                                        <Column field="purpose" header="Purpose" :sortable="true"></Column>
                                        <Column field="personToVisit" header="Person to Visit" :sortable="true"></Column>
                                        <Column field="department.name" header="Department" :sortable="true"></Column>
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
                        <div class="stat-item">
                            <div class="stat-label">Guests</div>
                            <div class="stat-value">{{ totalGuests }}</div>
                        </div>
                    </div>

                    <div class="footer-actions">
                        <button @click="exportReport" class="export-button">
                            <i class="pi pi-download"></i>
                            Export Report
                        </button>
                    </div>
                </div>

                <!-- Guest Registration Dialog -->
                <Dialog v-model:visible="guestDialog" header="Guest Registration" :modal="true" class="guest-dialog" :style="{ width: '450px' }">
                    <div class="guest-form">
                        <div class="form-field">
                            <label for="name">Name*</label>
                            <InputText id="name" v-model="guest.name" :class="{ 'p-invalid': guestFormSubmitted && !guest.name }" />
                            <small v-if="guestFormSubmitted && !guest.name" class="p-error">Name is required.</small>
                        </div>

                        <div class="form-field">
                            <label for="purpose">Purpose of Visit*</label>
                            <InputText id="purpose" v-model="guest.purpose" :class="{ 'p-invalid': guestFormSubmitted && !guest.purpose }" />
                            <small v-if="guestFormSubmitted && !guest.purpose" class="p-error">Purpose is required.</small>
                        </div>

                        <div class="form-field">
                            <label for="contactNumber">Contact Number</label>
                            <InputMask id="contactNumber" v-model="guest.contactNumber" mask="(999) 999-9999" />
                        </div>

                        <div class="form-field">
                            <label for="personToVisit">Person to Visit*</label>
                            <InputText id="personToVisit" v-model="guest.personToVisit" :class="{ 'p-invalid': guestFormSubmitted && !guest.personToVisit }" />
                            <small v-if="guestFormSubmitted && !guest.personToVisit" class="p-error">Person to visit is required.</small>
                        </div>

                        <div class="form-field">
                            <label for="department">Department*</label>
                            <Dropdown id="department" v-model="guest.department" :options="departments" optionLabel="name" placeholder="Select Department" :class="{ 'p-invalid': guestFormSubmitted && !guest.department }" />
                            <small v-if="guestFormSubmitted && !guest.department" class="p-error">Department is required.</small>
                        </div>
                    </div>

                    <template #footer>
                        <button @click="guestDialog = false" class="cancel-button">
                            <i class="pi pi-times"></i>
                            Cancel
                        </button>
                        <button @click="submitGuestForm" class="submit-button">
                            <i class="pi pi-check"></i>
                            Register
                        </button>
                    </template>
                </Dialog>
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

.visitor-toggle {
    font-weight: 600;
}

.guest-register-button {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    background-color: #10b981;
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 50px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);

    &:hover {
        background-color: #059669;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    i {
        font-size: 1.2rem;
    }
}

/* Attendance Columns */
.attendance-columns {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow: auto;
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
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    background-color: #f3f4f6;
    color: #4b5563;
    border: 2px solid #d1d5db;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 48px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);

    &:hover {
        background-color: #e5e7eb;
        border-color: #9ca3af;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    i {
        font-size: 1.1rem;
    }
}

.submit-button {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    background-color: #10b981;
    color: white;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 48px;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);

    &:hover {
        background-color: #059669;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(16, 185, 129, 0.4);
    }

    i {
        font-size: 1.1rem;
    }
}

/* Section Header Styles */
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;

    h2 {
        font-size: 1.25rem;
        font-weight: 600;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;

        i {
            color: #3b82f6;
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
    gap: 0.5rem;
    padding: 1rem 1.5rem;
    border-radius: 0.5rem;
    border: 2px solid #3b82f6;
    background: white;
    color: #3b82f6;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 50px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);

    &:hover {
        background: #3b82f6;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    i {
        font-size: 1.2rem;
    }
}

.scanner-container {
    position: relative;
    height: 300px;
    border-radius: 0.75rem;
    overflow: hidden;
    background: #0f172a;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.qr-scanner {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.scanner-paused {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: white;
    background: rgba(15, 23, 42, 0.9);
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
    gap: 0.75rem;
    padding: 1rem 2rem;
    border-radius: 0.5rem;
    border: none;
    background: #ef4444;
    color: white;
    font-weight: 600;
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 50px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);

    &:hover {
        background: #dc2626;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    i {
        font-size: 1.2rem;
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
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
    border-radius: 0.75rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    flex: 1;
    min-height: 0;
}

.feed-actions {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.search-container {
    position: relative;
    width: 100%;
}

.search-input {
    width: 100%;
    padding: 0.625rem 0.75rem 0.625rem 2.5rem;
    border-radius: 0.375rem;
    border: 1px solid #e2e8f0;
    font-size: 0.875rem;
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
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.filter-button {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 0.5rem;
    border: 2px solid #e2e8f0;
    background: white;
    color: #64748b;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 45px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);

    &:hover {
        background: #f8fafc;
        border-color: #3b82f6;
        color: #3b82f6;
        transform: translateY(-1px);
    }

    &.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
    }

    i {
        font-size: 1.1rem;
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
        gap: 0.75rem;
        padding: 1rem 2rem;
        background: #8b5cf6;
        color: white;
        border: none;
        border-radius: 0.5rem;
        font-weight: 600;
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.2s;
        min-height: 50px;
        box-shadow: 0 2px 4px rgba(139, 92, 246, 0.3);

        &:hover {
            background: #7c3aed;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(139, 92, 246, 0.4);
        }

        i {
            font-size: 1.2rem;
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
    .main-content-columns {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .scanner-container {
        height: 350px;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;
    }

    .header-left,
    .header-center,
    .header-right {
        width: 100%;
        justify-content: center;
    }

    .dashboard-container {
        padding: 1rem;
    }

    .feed-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .search-input {
        width: 100%;
    }

    .dashboard-footer {
        flex-direction: column;
        gap: 1.5rem;
        padding: 1.5rem 1rem;
    }

    .footer-stats {
        width: 100%;
        justify-content: space-between;
    }
}

/* Record Type Styling */
.preview-header.record-checkin {
    background: rgba(16, 185, 129, 0.1);
}

.preview-header.record-checkout {
    background: rgba(59, 130, 246, 0.1);
}

.record-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    padding: 0.375rem 0.75rem;
    border-radius: 0.375rem;
}

.record-badge.record-checkin {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.record-badge.record-checkout {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}
</style>
