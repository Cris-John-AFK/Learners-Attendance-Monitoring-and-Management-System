<script setup>
import { useLayout } from '@/layout/composables/layout';
import { AttendanceService } from '@/router/service/Students';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { computed, onMounted, ref, watch } from 'vue';
import { QrcodeStream } from 'vue-qrcode-reader';

const { layoutState, isSidebarActive } = useLayout();
const outsideClickListener = ref(null);
const scanning = ref(true); // Auto-start scanning
const attendanceRecords = ref([]);
const searchQuery = ref('');
const selectedStudent = ref(null);
const allStudents = AttendanceService.getData();
const currentDateTime = ref(new Date());
const guardName = ref('John Doe');
const guardId = ref('G-12345');
const statusFilter = ref('all');

// Stats
const totalCheckins = computed(() => attendanceRecords.value.length);
const lateArrivals = computed(() => attendanceRecords.value.filter((record) => record.status === 'late').length);
const unauthorizedAttempts = computed(() => attendanceRecords.value.filter((record) => record.status === 'unauthorized').length);

// Update time every second
onMounted(() => {
    setInterval(() => {
        currentDateTime.value = new Date();
    }, 1000);

    // Add some sample data
    addSampleData();
});

const addSampleData = () => {
    // Add some sample records with different statuses
    const statuses = ['on-time', 'late', 'unauthorized'];
    for (let i = 0; i < 10; i++) {
        const student = allStudents[i % allStudents.length];
        const record = {
            ...student,
            timestamp: new Date().toLocaleTimeString(),
            date: new Date().toLocaleDateString(),
            status: statuses[i % 3]
        };
        attendanceRecords.value.push(record);
    }
};

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

const onDetect = (detectedCodes) => {
    console.log('QR Code Detected:', detectedCodes);
    if (detectedCodes.length > 0) {
        const studentId = detectedCodes[0].rawValue;
        processStudentScan(studentId);
    }
};

const processStudentScan = (studentId) => {
    const student = allStudents.find((s) => s.id.toString() === studentId);
    if (student) {
        // Check if already scanned today
        const alreadyScanned = attendanceRecords.value.some((record) => record.id === student.id && record.date === new Date().toLocaleDateString());

        // Determine status (simplified logic - in real app would check against schedule)
        const currentHour = new Date().getHours();
        let status = 'on-time';
        if (currentHour >= 8) status = 'late';
        if (alreadyScanned) status = 'unauthorized';

        // Create record
        const record = {
            ...student,
            timestamp: new Date().toLocaleTimeString(),
            date: new Date().toLocaleDateString(),
            status: status
        };

        // Play sound based on status
        playStatusSound(status);

        // Add to records and show details
        attendanceRecords.value.unshift(record); // Add to beginning
        selectedStudent.value = record;
    } else {
        // Invalid QR code
        playStatusSound('unauthorized');
    }
};

const playStatusSound = (status) => {
    // In a real app, would play different sounds based on status
    console.log(`Playing ${status} sound`);
};

const manualCheckIn = () => {
    const studentId = prompt('Enter student ID:');
    if (studentId) {
        processStudentScan(studentId);
    }
};

const exportReport = () => {
    alert('Exporting attendance report...');
    // In a real app, would generate CSV/PDF
};

const filteredRecords = computed(() => {
    let records = attendanceRecords.value;

    // Apply status filter
    if (statusFilter.value !== 'all') {
        records = records.filter((record) => record.status === statusFilter.value);
    }

    // Apply search filter
    if (searchQuery.value) {
        records = records.filter((student) => student.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || student.id.toString().includes(searchQuery.value));
    }

    return records;
});

const getStatusClass = (status) => {
    switch (status) {
        case 'on-time':
            return 'status-ontime';
        case 'late':
            return 'status-late';
        case 'unauthorized':
            return 'status-unauthorized';
        default:
            return '';
    }
};

const getStatusIcon = (status) => {
    switch (status) {
        case 'on-time':
            return 'pi pi-check-circle';
        case 'late':
            return 'pi pi-clock';
        case 'unauthorized':
            return 'pi pi-times-circle';
        default:
            return 'pi pi-question-circle';
    }
};
</script>

<template>
    <div class="layout-wrapper">
        <!-- Fixed Header -->
        <header class="dashboard-header">
            <div class="header-left">
                <img src="/demo/images/logo.png" alt="School Logo" class="school-logo" />
                <h1>Attendance Monitoring System</h1>
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
        <div class="dashboard-container">
            <!-- Left Panel: QR Scanner -->
            <div class="scanner-panel">
                <div class="panel-header">
                    <h2><i class="pi pi-camera"></i> QR Scanner</h2>
                </div>

                <div class="scanner-container">
                    <qrcode-stream @detect="onDetect" class="qr-scanner" :class="{ scanning: scanning }"></qrcode-stream>
                    <div class="scanner-overlay">
                        <div class="scanner-corners">
                            <span></span>
                        </div>
                    </div>
                </div>

                <div class="scanner-controls">
                    <button @click="scanning = !scanning" class="control-button">
                        <i :class="scanning ? 'pi pi-pause' : 'pi pi-play'"></i>
                        {{ scanning ? 'Pause Scanner' : 'Resume Scanner' }}
                    </button>
                    <button @click="manualCheckIn" class="control-button">
                        <i class="pi pi-pencil"></i>
                        Manual Entry
                    </button>
                </div>

                <!-- Student Details Card -->
                <div class="student-details" v-if="selectedStudent">
                    <div class="details-header" :class="getStatusClass(selectedStudent.status)">
                        <i :class="getStatusIcon(selectedStudent.status)"></i>
                        <h3>Last Scanned Student</h3>
                    </div>
                    <div class="student-card">
                        <div class="student-photo-container">
                            <img :src="selectedStudent.photo" alt="Student Photo" class="student-photo" />
                        </div>
                        <div class="student-info">
                            <div class="info-row">
                                <span class="info-label">ID:</span>
                                <span class="info-value">{{ selectedStudent.id }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Name:</span>
                                <span class="info-value">{{ selectedStudent.name }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Grade:</span>
                                <span class="info-value">{{ selectedStudent.gradeLevel }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Section:</span>
                                <span class="info-value">{{ selectedStudent.section }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Status:</span>
                                <span class="info-value status-badge" :class="getStatusClass(selectedStudent.status)">
                                    {{ selectedStudent.status.toUpperCase() }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Real-Time Attendance Feed -->
            <div class="feed-panel">
                <div class="panel-header">
                    <h2><i class="pi pi-list"></i> Real-Time Attendance Feed</h2>
                </div>

                <div class="feed-controls">
                    <div class="search-container">
                        <div class="p-input-icon-left w-full">
                            <i class="pi pi-search"></i>
                            <input v-model="searchQuery" type="text" placeholder="Search by name or ID..." class="search-input" />
                        </div>
                    </div>

                    <div class="filter-buttons">
                        <button @click="statusFilter = 'all'" :class="{ active: statusFilter === 'all' }">All</button>
                        <button @click="statusFilter = 'on-time'" :class="{ active: statusFilter === 'on-time' }">On-Time</button>
                        <button @click="statusFilter = 'late'" :class="{ active: statusFilter === 'late' }">Late</button>
                        <button @click="statusFilter = 'unauthorized'" :class="{ active: statusFilter === 'unauthorized' }">Unauthorized</button>
                    </div>
                </div>

                <div class="attendance-feed">
                    <DataTable :value="filteredRecords" class="p-datatable-sm" responsiveLayout="scroll" :rowHover="true" stripedRows scrollable scrollHeight="calc(100vh - 400px)">
                        <Column field="timestamp" header="Time" style="width: 100px"></Column>
                        <Column field="id" header="ID" style="width: 80px"></Column>
                        <Column field="name" header="Name"></Column>
                        <Column field="gradeLevel" header="Grade" style="width: 80px"></Column>
                        <Column field="section" header="Section" style="width: 100px"></Column>
                        <Column field="status" header="Status" style="width: 120px">
                            <template #body="{ data }">
                                <span class="status-badge" :class="getStatusClass(data.status)">
                                    <i :class="getStatusIcon(data.status)"></i>
                                    {{ data.status.toUpperCase() }}
                                </span>
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Fixed Footer -->
        <footer class="dashboard-footer">
            <div class="footer-stats">
                <div class="stat-item">
                    <div class="stat-label">Total Check-ins</div>
                    <div class="stat-value">{{ totalCheckins }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Late Arrivals</div>
                    <div class="stat-value status-late">{{ lateArrivals }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Unauthorized</div>
                    <div class="stat-value status-unauthorized">{{ unauthorizedAttempts }}</div>
                </div>
            </div>

            <div class="footer-actions">
                <button @click="exportReport" class="export-button">
                    <i class="pi pi-download"></i>
                    Export Report
                </button>
            </div>
        </footer>
    </div>
</template>

<style lang="scss" scoped>
/* Base Styles */
:root {
    --color-primary: #2563eb;
    --color-secondary: #4b5563;
    --color-success: #10b981;
    --color-warning: #f59e0b;
    --color-danger: #ef4444;
    --color-light: #f3f4f6;
    --color-dark: #1f2937;
    --color-white: #ffffff;
    --color-ontime: #10b981;
    --color-late: #f59e0b;
    --color-unauthorized: #ef4444;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    --radius-sm: 0.25rem;
    --radius-md: 0.5rem;
    --radius-lg: 1rem;
}

.layout-wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    background-color: #f1f5f9;
}

/* Header Styles */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
    color: white;
    padding: 1rem 2rem;
    box-shadow: var(--shadow-md);
    position: sticky;
    top: 0;
    z-index: 100;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1rem;

    h1 {
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
        color: white;
    }
}

.school-logo {
    height: 60px;
    width: auto;
}

.header-center {
    .date-time {
        text-align: center;

        .date {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .time {
            font-size: 1.8rem;
            font-weight: 700;
        }
    }
}

.header-right {
    .guard-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: rgba(255, 255, 255, 0.1);
        padding: 0.5rem 1rem;
        border-radius: var(--radius-md);

        i {
            font-size: 2.5rem;
        }

        .guard-name {
            font-weight: 700;
            font-size: 1.5rem;
        }

        .guard-id {
            font-size: 1.2rem;
            opacity: 0.8;
        }
    }
}

/* Dashboard Container */
.dashboard-container {
    display: flex;
    gap: 1.5rem;
    padding: 1.5rem;
    flex: 1;

    @media (max-width: 1024px) {
        flex-direction: column;
    }
}

/* Panel Styles */
.scanner-panel,
.feed-panel {
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    overflow: hidden;
}

.scanner-panel {
    flex: 1;
    display: flex;
    flex-direction: column;
    max-width: 500px;

    @media (max-width: 1024px) {
        max-width: none;
    }
}

.feed-panel {
    flex: 1.5;
    display: flex;
    flex-direction: column;
}

.panel-header {
    background: #f8fafc;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;

    h2 {
        margin: 0;
        font-size: 1.25rem;
        color: var(--color-dark);
        display: flex;
        align-items: center;
        gap: 0.5rem;

        i {
            color: var(--color-primary);
        }
    }
}

/* Scanner Styles */
.scanner-container {
    position: relative;
    width: 100%;
    height: 350px;
    background: #000;
    overflow: hidden;
}

.qr-scanner {
    width: 100%;
    height: 100%;
}

.scanner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.scanner-corners {
    width: 250px;
    height: 250px;
    position: relative;

    &::before,
    &::after,
    span::before,
    span::after {
        content: '';
        position: absolute;
        width: 30px;
        height: 30px;
        border-color: #3b82f6;
        border-style: solid;
        border-width: 0;
    }

    &::before {
        top: 0;
        left: 0;
        border-top-width: 4px;
        border-left-width: 4px;
    }

    &::after {
        top: 0;
        right: 0;
        border-top-width: 4px;
        border-right-width: 4px;
    }

    span::before {
        bottom: 0;
        left: 0;
        border-bottom-width: 4px;
        border-left-width: 4px;
    }

    span::after {
        bottom: 0;
        right: 0;
        border-bottom-width: 4px;
        border-right-width: 4px;
    }

    &::before,
    &::after,
    span::before,
    span::after {
        box-shadow: 0 0 10px rgba(59, 130, 246, 0.8);
    }
}

.scanning {
    &::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, #3b82f6, transparent);
        animation: scan 2s linear infinite;
    }
}

@keyframes scan {
    0% {
        top: 0;
    }
    50% {
        top: 100%;
    }
    100% {
        top: 0;
    }
}

.scanner-controls {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;

    .control-button {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem;
        border: none;
        border-radius: var(--radius-md);
        background: #f1f5f9;
        color: var(--color-dark);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;

        &:hover {
            background: #e2e8f0;
        }

        i {
            font-size: 1rem;
        }
    }
}

/* Student Details */
.student-details {
    margin: 1rem;
    border-radius: var(--radius-md);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.details-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;

    h3 {
        margin: 0;
        font-size: 1rem;
        color: var(--color-dark);
    }

    i {
        font-size: 1.25rem;
    }

    &.status-ontime {
        background: rgba(16, 185, 129, 0.1);
        border-bottom: 1px solid rgba(16, 185, 129, 0.2);

        i,
        h3 {
            color: var(--color-ontime);
        }
    }

    &.status-late {
        background: rgba(245, 158, 11, 0.1);
        border-bottom: 1px solid rgba(245, 158, 11, 0.2);

        i,
        h3 {
            color: var(--color-late);
        }
    }

    &.status-unauthorized {
        background: rgba(239, 68, 68, 0.1);
        border-bottom: 1px solid rgba(239, 68, 68, 0.2);

        i,
        h3 {
            color: var(--color-danger);
        }
    }
}

.student-card {
    display: flex;
    padding: 1rem;
    gap: 1rem;
}

.student-photo-container {
    flex-shrink: 0;
}

.student-photo {
    width: 80px;
    height: 80px;
    border-radius: var(--radius-md);
    object-fit: cover;
}

.student-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.info-row {
    display: flex;
    font-size: 0.9rem;
}

.info-label {
    flex: 0 0 70px;
    font-weight: 600;
    color: var(--color-secondary);
}

.info-value {
    flex: 1;
    color: var(--color-dark);
}

/* Feed Controls */
.feed-controls {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.search-container {
    position: relative;

    .p-input-icon-left {
        width: 100%;

        i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
    }
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-md);
    font-size: 1rem;

    &:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }
}

.filter-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;

    button {
        padding: 0.5rem 1rem;
        border: 1px solid #e2e8f0;
        border-radius: var(--radius-md);
        background: white;
        color: var(--color-secondary);
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;

        &:hover {
            background: #f8fafc;
        }

        &.active {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }
    }
}

/* Attendance Feed */
.attendance-feed {
    flex: 1;
    padding: 0 1rem 1rem;
    overflow: hidden;
}

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    font-weight: 600;

    &.status-ontime {
        background: rgba(16, 185, 129, 0.1);
        color: var(--color-ontime);
    }

    &.status-late {
        background: rgba(245, 158, 11, 0.1);
        color: var(--color-late);
    }

    &.status-unauthorized {
        background: rgba(239, 68, 68, 0.1);
        color: var(--color-danger);
    }
}

/* Footer Styles */
.dashboard-footer {
    background: white;
    border-top: 1px solid #e2e8f0;
    padding: 1rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--shadow-sm);

    @media (max-width: 768px) {
        flex-direction: column;
        gap: 1rem;
    }
}

.footer-stats {
    display: flex;
    gap: 2rem;

    @media (max-width: 768px) {
        width: 100%;
        justify-content: space-between;
    }
}

.stat-item {
    text-align: center;

    .stat-label {
        font-size: 0.8rem;
        color: var(--color-secondary);
        margin-bottom: 0.25rem;
    }

    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-dark);

        &.status-late {
            color: var(--color-late);
        }

        &.status-unauthorized {
            color: var(--color-danger);
        }
    }
}

.footer-actions {
    .export-button {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: var(--color-primary);
        color: white;
        border: none;
        border-radius: var(--radius-md);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;

        &:hover {
            background: #1d4ed8;
        }
    }
}

/* DataTable Customizations */
:deep(.p-datatable) {
    .p-datatable-thead > tr > th {
        background: #f8fafc;
        color: var(--color-secondary);
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

/* Responsive Adjustments */
@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        gap: 1rem;
        padding: 1rem;

        .header-left,
        .header-center,
        .header-right {
            width: 100%;
        }
    }

    .dashboard-container {
        padding: 1rem;
    }

    .scanner-container {
        height: 300px;
    }
}
</style>
