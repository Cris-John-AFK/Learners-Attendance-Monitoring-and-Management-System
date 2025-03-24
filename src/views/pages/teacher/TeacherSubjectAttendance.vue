<template>
    <div class="grid">
        <div class="col-12">
            <div class="card">
                <div class="flex justify-between items-center mb-4">
                    <h5 class="text-xl font-semibold">{{ subjectName }} Attendance</h5>
                    <Button label="Take Attendance" icon="pi pi-plus" @click="showAttendanceModal = true" />
                </div>

                <!-- Attendance Table -->
                <DataTable :value="attendanceData" class="p-datatable-sm" :paginator="true" :rows="10" responsiveLayout="scroll">
                    <Column field="date" header="Date">
                        <template #body="slotProps">
                            {{ formatDate(slotProps.data.date) }}
                        </template>
                    </Column>
                    <Column field="studentName" header="Student Name"></Column>
                    <Column field="studentId" header="Student ID"></Column>
                    <Column field="status" header="Status">
                        <template #body="slotProps">
                            <span :class="getStatusClass(slotProps.data.status)">
                                {{ slotProps.data.status }}
                            </span>
                        </template>
                    </Column>
                    <Column field="time" header="Time"></Column>
                    <Column field="remarks" header="Remarks"></Column>
                </DataTable>
            </div>
        </div>
    </div>
    <!-- Attendance Method Selection Modal -->
    <Dialog v-model:visible="showAttendanceModal" modal header="Select Attendance Method" :style="{ width: '40vw' }">
        <div class="grid gap-4">
            <div class="col-12 md:col-6">
                <div class="attendance-option" @click="startQRAttendance">
                    <i class="pi pi-qrcode icon"></i>
                    <h3 class="title">QR Code Attendance</h3>
                    <p class="description">Scan student's QR Code for quick and efficient attendance tracking.</p>
                </div>
            </div>
            <div class="col-12 md:col-6">
                <div class="attendance-option" @click="startRollCall">
                    <i class="pi pi-list icon"></i>
                    <h3 class="title">Roll Call</h3>
                    <p class="description">Manually call out names and mark attendance.</p>
                </div>
            </div>
        </div>
    </Dialog>

    <!-- QR Scanner Modal -->
    <Dialog v-model:visible="showQRScanner" modal header="QR Code Scanner" :style="{ width: '80vw' }">
        <div class="grid">
            <!-- Camera Feed -->
            <div class="col-12 md:col-8">
                <div class="card camera-card">
                    <div v-if="isCameraLoading" class="camera-loading">
                        <i class="pi pi-spin pi-spinner text-4xl"></i>
                        <p class="mt-2">Initializing camera...</p>
                    </div>
                    <video v-show="!isCameraLoading" ref="videoElement" class="camera-feed" autoplay></video>
                    <p class="camera-indicator">🔴 Scanning for QR Codes...</p>
                </div>
            </div>

            <!-- Scanned Students List -->
            <div class="col-12 md:col-4">
                <div class="card">
                    <h3 class="text-lg font-semibold mb-3">📋 Scanned Students</h3>
                    <div class="scanned-list">
                        <div v-for="student in scannedStudents" :key="student.id" class="scanned-student">
                            <i class="pi pi-check-circle text-green-500 text-2xl"></i>
                            <div>
                                <div class="font-medium text-lg">{{ student.name }}</div>
                                <div class="text-sm text-gray-500">ID: {{ student.id }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <template #footer>
            <Button label="Stop Scanning" icon="pi pi-times" class="p-button-danger" @click="closeScanner" />
        </template>
    </Dialog>

    <!-- Enhanced Student Roll Call Modal -->
    <Dialog v-model:visible="showRollCall" modal header="Mark Attendance" :style="{ width: '450px' }" class="attendance-modal">
        <div class="card border-none shadow-none p-0">
            <div class="student-profile text-center mb-4">
                <div class="student-avatar mb-3">
                    <i class="pi pi-user text-4xl"></i>
                </div>
                <h2 class="student-name text-xl font-bold mb-1">{{ currentStudent?.name || 'No Name' }}</h2>
                <div class="student-id text-sm text-gray-500">ID: {{ currentStudent?.id || 'No ID' }}</div>
            </div>

            <div class="attendance-buttons-container">
                <div class="attendance-btn present-btn" @click="markAttendance('Present')">
                    <i class="pi pi-check status-icon"></i>
                    <span>Present</span>
                </div>

                <div class="attendance-btn late-btn" @click="markAttendance('Late')">
                    <i class="pi pi-clock status-icon"></i>
                    <span>Late</span>
                </div>

                <div class="attendance-btn absent-btn" @click="showRemarksModal('Absent')">
                    <i class="pi pi-times status-icon"></i>
                    <span>Absent</span>
                </div>

                <div class="attendance-btn excused-btn" @click="showRemarksModal('Excused')">
                    <i class="pi pi-info-circle status-icon"></i>
                    <span>Excused</span>
                </div>
            </div>

            <div class="skip-button-container">
                <Button label="Skip" icon="pi pi-arrow-right" iconPos="right" class="p-button-outlined p-button-lg w-full" style="font-size: 1.1rem; padding: 0.75rem" @click="moveToNextStudent()" />
            </div>
        </div>
    </Dialog>

    <!-- Remarks Modal -->
    <Dialog v-model:visible="showRemarks" modal :header="'Remarks for ' + currentStudent?.name" :style="{ width: '50vw' }">
        <div class="card">
            <div class="flex flex-column p-4">
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2">Enter Remarks</label>
                    <Textarea v-model="remarks" rows="3" class="w-full" placeholder="Enter remarks for the absence/excuse..."></Textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showRemarks = false" />
                    <Button label="Save" icon="pi pi-check" class="p-button-success" @click="saveWithRemarks" />
                </div>
            </div>
        </div>
    </Dialog>
</template>

<script setup>
import { AttendanceService } from '@/router/service/Students';
import { BrowserMultiFormatReader } from '@zxing/browser';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const subjectName = ref('Subject');

// Modal states
const showAttendanceModal = ref(true); // Show immediately when page loads
const showRollCall = ref(false);
const showRemarks = ref(false);
const isCameraLoading = ref(true);
const showQRScanner = ref(false);
const videoElement = ref(null);

// Student data
const students = ref([]);
const currentStudent = ref(null);
const currentStudentIndex = ref(0);
const remarks = ref('');
const pendingStatus = ref('');

// Attendance records
const attendanceData = ref([]);
const scannedStudents = ref([]);
let codeReader = null;

const startScanning = async () => {
    try {
        const result = await codeReader.decodeOnceFromVideoDevice(undefined, videoElement.value);
        if (result) {
            processScannedData(result.text);
            codeReader.reset(); // Stop scanner after successful scan
        }
    } catch (error) {
        console.warn('No QR code detected.');
    }
};

// Open Scanner
const startQRAttendance = () => {
    showQRScanner.value = true;
    isCameraLoading.value = true;

    // Ensure Vue has updated the DOM before initializing the camera
    nextTick(() => {
        if (videoElement.value) {
            initializeCamera();
        } else {
            console.error('Video element not found in DOM.');
        }
    });
};

const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
};

const initializeCamera = async () => {
    if (!codeReader) {
        codeReader = new BrowserMultiFormatReader();
    }

    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter((device) => device.kind === 'videoinput');

        if (videoDevices.length > 0) {
            const selectedDeviceId = videoDevices[0].deviceId;

            // Ensure videoElement is available before accessing it
            if (!videoElement.value) {
                console.error('Error: videoElement is not available.');
                return;
            }

            await codeReader.decodeFromVideoDevice(selectedDeviceId, videoElement.value, (result, err) => {
                if (result) {
                    processScannedData(result.text);
                } else if (err) {
                    console.warn('QR Code scan error:', err);
                }
            });
        } else {
            console.error('No camera found');
        }
    } catch (error) {
        console.error('Error accessing camera:', error);
    } finally {
        isCameraLoading.value = false;
    }
};

const processScannedData = (scannedText) => {
    if (scannedText === 'DEFAULT-ATTENDANCE-QR') {
        console.log('Attendance marked for Default Student');
        alert('Attendance marked for Default Student');
    } else {
        console.log('Scanned:', scannedText);
        alert(`Scanned: ${scannedText}`);
    }
};

watch(
    () => route.fullPath,
    () => {
        // Extract the subject name from the route
        const matchedSubject = route.params.subject;

        if (matchedSubject) {
            subjectName.value = formatSubjectName(matchedSubject);
        } else {
            subjectName.value = 'Subject'; // Default
        }
    }
);

// Function to format subject names
const formatSubjectName = (subject) => {
    // Convert kebab-case or lowercase to title case
    return subject
        .replace(/-/g, ' ') // Replace dashes with spaces
        .replace(/\b\w/g, (char) => char.toUpperCase()); // Capitalize words
};

const closeScanner = () => {
    if (codeReader) {
        try {
            codeReader.stopContinuousDecode(); // Correct function to stop scanning
        } catch (error) {
            console.error('Error stopping scanner:', error);
        }
    }

    // Stop the camera stream safely
    if (videoElement.value && videoElement.value.srcObject) {
        const stream = videoElement.value.srcObject;
        const tracks = stream.getTracks();
        tracks.forEach((track) => track.stop()); // Stop each track
        videoElement.value.srcObject = null; // Clear video feed
    }

    showQRScanner.value = false;
};

onUnmounted(() => {
    if (codeReader) {
        codeReader.reset(); // Properly release camera
        codeReader = null;
    }
});

const startRollCall = () => {
    showAttendanceModal.value = false;
    showRollCall.value = true;

    // Check if students exist before assigning
    if (students.value.length > 0) {
        currentStudentIndex.value = 0;
        currentStudent.value = students.value[0];
    } else {
        console.error('No students found!');
    }
};

const markAttendance = async (status) => {
    if (!currentStudent.value) return;

    const attendanceRecord = {
        date: new Date().toISOString().split('T')[0],
        studentName: currentStudent.value.name,
        studentId: currentStudent.value.id,
        status: status,
        time: new Date().toLocaleTimeString(),
        remarks: ''
    };

    try {
        // Record attendance in the service
        await AttendanceService.recordAttendance(currentStudent.value.id, attendanceRecord);

        // Update local state
        attendanceData.value.push(attendanceRecord);

        // Move to next student
        moveToNextStudent();
    } catch (error) {
        console.error('Error recording attendance:', error);
    }
};

const showRemarksModal = (status) => {
    pendingStatus.value = status;
    showRemarks.value = true;
};

const saveWithRemarks = async () => {
    if (!currentStudent.value || !pendingStatus.value) return;

    const attendanceRecord = {
        date: new Date().toISOString().split('T')[0],
        studentName: currentStudent.value.name,
        studentId: currentStudent.value.id,
        status: pendingStatus.value,
        time: new Date().toLocaleTimeString(),
        remarks: remarks.value
    };

    try {
        // Record attendance with remarks in the service
        await AttendanceService.recordAttendance(currentStudent.value.id, attendanceRecord);

        // Update local state
        attendanceData.value.push(attendanceRecord);

        // Reset and move to next student
        remarks.value = '';
        showRemarks.value = false;
        moveToNextStudent();
    } catch (error) {
        console.error('Error recording attendance with remarks:', error);
    }
};

const moveToNextStudent = () => {
    currentStudentIndex.value++;
    if (currentStudentIndex.value < students.value.length) {
        currentStudent.value = students.value[currentStudentIndex.value];
    } else {
        showRollCall.value = false;
        // Show completion message or redirect
    }
};

const moveToPreviousStudent = () => {
    if (currentStudentIndex.value > 0) {
        currentStudentIndex.value--;
        currentStudent.value = students.value[currentStudentIndex.value];
    }
};

const getStatusClass = (status) => {
    return {
        'text-green-500': status === 'Present',
        'text-red-500': status === 'Absent',
        'text-orange-500': status === 'Late',
        'text-blue-500': status === 'Excused'
    };
};

// Use the service to fetch attendance data for the subject
const fetchAttendanceData = async () => {
    try {
        // If you have real subject data, use subjectName.value
        const data = await AttendanceService.getAttendanceForSubject(subjectName.value);
        attendanceData.value = data;
    } catch (error) {
        console.error('Error fetching attendance data:', error);
    }
};

// Initialize data
onMounted(async () => {
    try {
        // Fetch students
        const studentsData = await AttendanceService.getData();
        if (studentsData && studentsData.length > 0) {
            students.value = studentsData;
        } else {
            console.error('No students found in the database!');
        }

        // Also fetch existing attendance data
        await fetchAttendanceData();
    } catch (error) {
        console.error('Error initializing data:', error);
    }
});
</script>

<style scoped>
/* Camera Card */
.camera-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
}

/* Video Feed */
.camera-feed {
    width: 100%;
    height: 400px;
    border-radius: 8px;
    border: 3px solid #007bff;
}

/* Scanning Indicator */
.camera-indicator {
    margin-top: 8px;
    font-size: 1rem;
    font-weight: bold;
    color: #007bff;
}

/* Loading Animation */
.camera-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 400px;
    color: #555;
}

/* Scanned Students List */
.scanned-list {
    max-height: 400px;
    overflow-y: auto;
    padding: 5px;
}

/* Individual Scanned Student */
.scanned-student {
    display: flex;
    align-items: center;
    background: #e3f2fd;
    padding: 10px;
    margin-bottom: 5px;
    border-radius: 6px;
}

.scanned-student i {
    margin-right: 10px;
}
.attendance-option {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease-in-out;
    cursor: pointer;
}

.attendance-option:hover {
    background: #f0f8ff;
    border-color: #007bff;
    box-shadow: 0 4px 10px rgba(0, 123, 255, 0.2);
}
.icon {
    font-size: 3rem;
    color: #007bff;
    margin-bottom: 10px;
}

.title {
    font-size: 1.2rem;
    font-weight: bold;
    color: #333;
}

.description {
    font-size: 0.9rem;
    color: #666;
}
.camera-container {
    width: 100%;
    height: 400px;
    background-color: #f8f9fa;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.camera-placeholder {
    text-align: center;
    color: #6c757d;
}

.scanned-list {
    max-height: 400px;
    overflow-y: auto;
}

:deep(.p-datatable) {
    font-size: 0.875rem;
}

:deep(.p-datatable .p-datatable-header) {
    padding: 0.5rem;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    padding: 0.5rem;
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
    padding: 0.5rem;
}

.student-info {
    text-align: center;
}

/* Updated Attendance Modal Styling */
.attendance-modal :deep(.p-dialog-header) {
    border-bottom: 1px solid #f0f0f0;
    padding: 1rem;
}

.attendance-modal :deep(.p-dialog-content) {
    padding: 1.5rem;
}

.student-avatar {
    width: 70px;
    height: 70px;
    background: #f5f7f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    color: #758ca3;
}

/* New button container */
.attendance-buttons-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Updated button styling for larger, more clickable buttons */
.attendance-btn {
    display: flex;
    align-items: center;
    padding: 20px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 1.1rem;
    font-weight: 500;
}

.attendance-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
}

.attendance-btn:active {
    transform: translateY(0);
}

.status-icon {
    margin-right: 10px;
    font-size: 1.3rem;
}

/* Button colors - darker versions for better visibility */
.present-btn {
    background-color: #c8e6c9;
    color: #2e7d32;
    border: 1px solid #a5d6a7;
}

.late-btn {
    background-color: #ffe0b2;
    color: #e65100;
    border: 1px solid #ffcc80;
}

.absent-btn {
    background-color: #ffcdd2;
    color: #c62828;
    border: 1px solid #ef9a9a;
}

.excused-btn {
    background-color: #bbdefb;
    color: #0d47a1;
    border: 1px solid #90caf9;
}

/* Enhanced Skip button */
.skip-button-container {
    margin-top: 20px;
    text-align: center;
}

/* Remove the old grid-based layout styles */
.attendance-options,
.attendance-button {
    display: none;
}

/* Responsive adjustments */
@media (max-width: 480px) {
    .attendance-modal :deep(.p-dialog-content) {
        padding: 1rem;
    }

    .attendance-btn {
        padding: 14px;
    }
}
</style>
