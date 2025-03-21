<script setup>
import AppFooter from '@/layout/AppFooter.vue';
import { useLayout } from '@/layout/composables/layout';
import GuardHouseTopbar from '@/layout/guardhouselayout/GuardHouseTopbar.vue';
import { AttendanceService } from '@/router/service/Students';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import { computed, ref, watch } from 'vue';
import { QrcodeStream } from 'vue-qrcode-reader';

const { layoutState, isSidebarActive } = useLayout();
const outsideClickListener = ref(null);
const scanning = ref(false);
const attendanceRecords = ref([]);
const searchQuery = ref('');
const selectedStudent = ref(null);
const allStudents = AttendanceService.getData();

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
        fetchStudentDetails(studentId);
    }
};

const startScan = () => {
    scanning.value = true;
    addStudentRecord();
};

const addStudentRecord = () => {
    if (attendanceRecords.value.length < allStudents.length) {
        const newRecord = allStudents[attendanceRecords.value.length];
        attendanceRecords.value.push(newRecord);
        selectedStudent.value = newRecord; // Show details of last added student
    }
};

const fetchStudentDetails = (studentId) => {
    const student = allStudents.find((s) => s.id.toString() === studentId);
    if (student) {
        selectedStudent.value = student;
    }
};

const filteredRecords = computed(() => {
    return attendanceRecords.value.filter((student) => student.name.toLowerCase().includes(searchQuery.value.toLowerCase()) || student.id.toString().includes(searchQuery.value));
});
</script>

<template>
    <div class="layout-wrapper">
        <guard-house-topbar></guard-house-topbar>
        <div class="layout-main-container">
            <div class="content-wrapper">
                <div class="scanner-container">
                    <qrcode-stream @detect="onDetect" class="qr-scanner" :class="{ scanning: scanning }"></qrcode-stream>
                    <button @click="startScan" class="scan-button">Start Scan</button>
                </div>
                <div class="table-container">
                    <input v-model="searchQuery" type="text" placeholder="Search records..." class="search-input" />
                    <DataTable :value="filteredRecords" paginator :rows="5" class="p-datatable-striped">
                        <Column field="id" header="ID Number"></Column>
                        <Column field="name" header="Name"></Column>
                        <Column field="date" header="Date"></Column>
                        <Column field="timeIn" header="Time In"></Column>
                        <Column field="timeOut" header="Time Out"></Column>
                    </DataTable>
                </div>
                <div class="student-details" v-if="selectedStudent">
                    <h2>Student Attendance Details</h2>
                    <img :src="selectedStudent.photo" alt="Student Photo" class="student-photo" />
                    <p><strong>ID:</strong> {{ selectedStudent.id }}</p>
                    <p><strong>Name:</strong> {{ selectedStudent.name }}</p>
                    <p><strong>Gender:</strong> {{ selectedStudent.gender }}</p>
                    <p><strong>Grade Level:</strong> {{ selectedStudent.gradeLevel }}</p>
                    <p><strong>Section:</strong> {{ selectedStudent.section }}</p>
                </div>
            </div>
        </div>
        <app-footer></app-footer>
        <div class="layout-mask animate-fadein"></div>
    </div>
</template>

<style lang="scss" scoped>
.content-wrapper {
    display: flex;
    justify-content: center;
    padding: 20px;
    gap: 20px;
}

.layout-grid {
    display: flex;
    width: 100%;
    gap: 20px;
}

.scanner-container {
    width: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 10px;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
}
.qr-scanner {
    width: 500px;
    height: 500px;
    border: 3px solid gray;
    border-radius: 12px;
    transition:
        border-color 0.3s ease,
        box-shadow 0.3s ease;
    background: #fff;
}

.scanning {
    border-color: #00ff00 !important;
    box-shadow: 0px 0px 15px 4px #00ff00;
}

.scan-button {
    margin-top: 12px;
    padding: 14px 28px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition:
        background 0.3s ease,
        transform 0.2s ease;
    font-size: 18px;
    font-weight: 600;
}

.scan-button:hover {
    background-color: #0056b3;
    transform: scale(1.05);
}

.right-container {
    width: 50%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 20px;
}

.table-container {
    width: 100%;
    margin-bottom: 20px;
}

.student-details {
    padding: 20px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.student-photo {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    margin-bottom: 10px;
}

.search-input {
    width: 100%;
    padding: 10px;
    margin-bottom: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 16px;
}
</style>
