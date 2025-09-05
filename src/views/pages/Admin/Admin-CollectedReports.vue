<template>
    <div class="admin-reports-wrapper">
        <!-- Background container for shapes -->
        <div class="background-container">
            <div class="geometric-shape circle"></div>
            <div class="geometric-shape square"></div>
            <div class="geometric-shape triangle"></div>
            <div class="geometric-shape rectangle"></div>
            <div class="geometric-shape diamond"></div>
        </div>

        <!-- Main Content -->
        <div class="content-container">
            <!-- Header Section -->
            <div class="reports-header">
                <div class="header-content">
                    <div class="title-section">
                        <h1 class="page-title">
                            <span class="emoji-icon">📊</span>
                            <span class="text-gradient">Collected Reports</span>
                        </h1>
                        <p class="page-subtitle">Manage and view all system reports</p>
                    </div>
                    <div class="header-actions">
                        <Button label="Generate Report" icon="pi pi-plus" class="p-button-success" @click="showGenerateDialog = true" />
                        <Button label="Export All" icon="pi pi-download" class="p-button-outlined" @click="exportAllReports" />
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-grid">
                    <div class="filter-item">
                        <label class="filter-label">Grade Type</label>
                        <Select v-model="selectedGradeType" :options="gradeTypes" optionLabel="name" optionValue="value" placeholder="All Grades" class="w-full" />
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">Year</label>
                        <Select v-model="selectedDateRange" :options="dateRanges" optionLabel="name" optionValue="value" placeholder="All Years" class="w-full" />
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">Month</label>
                        <Select v-model="selectedStatus" :options="statusOptions" optionLabel="name" optionValue="value" placeholder="All Months" class="w-full" />
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">Search</label>
                        <InputText v-model="searchQuery" placeholder="Search reports..." class="w-full" />
                    </div>
                </div>
            </div>

            <!-- Reports Grid -->
            <div class="reports-grid">
                <div v-if="loading" class="loading-container">
                    <ProgressSpinner />
                    <p>Loading reports...</p>
                </div>

                <div v-else-if="filteredReports.length === 0" class="empty-state">
                    <i class="pi pi-file-excel empty-icon"></i>
                    <h3>No Reports Found</h3>
                    <p>No reports match your current filters. Try adjusting your search criteria.</p>
                </div>

                <div v-else class="reports-list">
                    <div v-for="report in filteredReports" :key="report.id" class="report-card">
                        <div class="report-header">
                            <div class="report-info">
                                <h4 class="report-title">{{ report.title }}</h4>
                                <p class="report-description">{{ report.description }}</p>
                            </div>
                            <div class="report-status">
                                <span :class="['status-badge', getStatusClass(report.status)]">
                                    {{ report.status }}
                                </span>
                            </div>
                        </div>

                        <div class="report-details">
                            <div class="detail-item">
                                <i class="pi pi-calendar"></i>
                                <span>{{ formatDate(report.created_at) }}</span>
                            </div>
                            <div class="detail-item">
                                <i class="pi pi-user"></i>
                                <span>{{ report.generated_by }}</span>
                            </div>
                            <div class="detail-item">
                                <i class="pi pi-file"></i>
                                <span>{{ report.type }}</span>
                            </div>
                            <div class="detail-item">
                                <i class="pi pi-database"></i>
                                <span>{{ report.records_count }} records</span>
                            </div>
                        </div>

                        <div class="report-actions">
                            <Button icon="pi pi-eye" class="p-button-rounded p-button-outlined p-button-sm" @click="viewReport(report)" v-tooltip.top="'View Report'" />
                            <Button icon="pi pi-download" class="p-button-rounded p-button-success p-button-outlined p-button-sm" @click="downloadReport(report)" v-tooltip.top="'Download Report'" />
                            <Button icon="pi pi-share-alt" class="p-button-rounded p-button-info p-button-outlined p-button-sm" @click="shareReport(report)" v-tooltip.top="'Share Report'" />
                            <Button icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-outlined p-button-sm" @click="confirmDeleteReport(report)" v-tooltip.top="'Delete Report'" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Generate Report Dialog -->
        <Dialog v-model:visible="showGenerateDialog" :header="'Generate New Report'" modal class="p-fluid" :style="{ width: '500px' }">
            <div class="generate-form">
                <div class="field">
                    <label for="reportTitle" class="font-medium mb-2 block">Report Title</label>
                    <InputText id="reportTitle" v-model="newReport.title" placeholder="Enter report title" class="w-full" />
                </div>

                <div class="field">
                    <label for="reportType" class="font-medium mb-2 block">Report Type</label>
                    <Select id="reportType" v-model="newReport.type" :options="reportTypes" optionLabel="name" optionValue="value" placeholder="Select report type" class="w-full" />
                </div>

                <div class="field">
                    <label for="reportDescription" class="font-medium mb-2 block">Description</label>
                    <InputText id="reportDescription" v-model="newReport.description" placeholder="Enter report description" class="w-full" />
                </div>

                <div class="field">
                    <label class="font-medium mb-2 block">Date Range</label>
                    <div class="flex gap-2">
                        <InputText v-model="newReport.startDate" type="date" class="w-full" />
                        <InputText v-model="newReport.endDate" type="date" class="w-full" />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showGenerateDialog = false" />
                <Button label="Generate" icon="pi pi-check" class="p-button-primary" @click="generateReport" :loading="generating" />
            </template>
        </Dialog>

        <!-- Detailed Section Attendance Dialog -->
        <Dialog
            v-model:visible="showSectionDetailsDialog"
            :header="getSectionDialogTitle()"
            modal
            class="p-fluid section-details-overlay"
            :style="{ width: '95vw', maxWidth: '1400px', height: '90vh' }"
            :appendTo="'body'"
            :blockScroll="true"
            :baseZIndex="1000"
            :closable="true"
            :dismissableMask="true"
            @hide="backToMainReport"
        >
            <div v-if="selectedSectionDetails" class="section-attendance-details">
                <!-- Section Header Info -->
                <div class="section-info-header">
                    <div class="section-basic-info">
                        <h3 class="section-title">{{ selectedSectionDetails.name }}</h3>
                        <p class="teacher-name">{{ selectedSectionDetails.teacher }}</p>
                        <div class="attendance-summary">
                            <div class="summary-item">
                                <span class="summary-label">Total Students:</span>
                                <span class="summary-value">{{ selectedSectionDetails.studentCount }}</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Present Today:</span>
                                <span class="summary-value present">{{ selectedSectionDetails.presentCount }}</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Absent Today:</span>
                                <span class="summary-value absent">{{ selectedSectionDetails.absentCount }}</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Attendance Rate:</span>
                                <span class="summary-value" :class="getRateClass(selectedSectionDetails.attendanceRate)">{{ selectedSectionDetails.attendanceRate }}%</span>
                            </div>
                        </div>
                    </div>
                    <div class="report-period-info">
                        <div class="period-badge">
                            <i class="pi pi-calendar"></i>
                            <span>September 2025</span>
                        </div>
                        <div class="school-info">
                            <strong>Kagawasan Elementary School</strong>
                            <p>School Form 2 (SF2) Daily Attendance Report</p>
                        </div>
                    </div>
                </div>

                <!-- Month Navigation -->
                <div class="month-navigation">
                    <Button 
                        icon="pi pi-chevron-left" 
                        class="p-button-text p-button-sm" 
                        @click="previousMonth()"
                        :disabled="!canGoPreviousMonth()"
                    />
                    <div class="month-selector">
                        <h4>{{ getCurrentMonthDisplay() }}</h4>
                        <p class="teacher-info">{{ getCurrentTeacher() }}</p>
                    </div>
                    <Button 
                        icon="pi pi-chevron-right" 
                        class="p-button-text p-button-sm" 
                        @click="nextMonth()"
                        :disabled="!canGoNextMonth()"
                    />
                </div>

                <!-- Attendance Grid -->
                <div class="attendance-grid-container">
                    <div class="grid-header">
                        <h4>Daily Attendance Record - {{ getCurrentMonthDisplay() }}</h4>
                        <div class="legend">
                            <div class="legend-item">
                                <span class="legend-symbol present-symbol">✓</span>
                                <span>Present</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-symbol absent-symbol">✗</span>
                                <span>Absent</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-symbol late-symbol">L</span>
                                <span>Late</span>
                            </div>
                        </div>
                    </div>

                    <div class="attendance-table-wrapper">
                        <table class="attendance-table">
                            <thead>
                                <tr>
                                    <th class="student-name-header">Learner's Name<br /><small>(Last Name, First Name, Middle Name)</small></th>
                                    <th v-for="day in attendanceDays" :key="day.date" class="day-header">
                                        <div class="day-info">
                                            <span class="day-number">{{ day.day }}</span>
                                            <span class="day-name">{{ day.dayName }}</span>
                                        </div>
                                    </th>
                                    <th class="summary-header">Total<br />Present</th>
                                    <th class="summary-header">Total<br />Absent</th>
                                    <th class="summary-header">Attendance<br />Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- MALE Students Section -->
                                <tr class="gender-header-row">
                                    <td class="gender-header" colspan="25">
                                        <div class="gender-section">
                                            <span class="gender-label">👨 MALE</span>
                                            <span class="gender-total">TOTAL Per Day</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="student in getMaleStudents(selectedSectionDetails.name)" :key="student.id" class="student-row">
                                    <td class="student-name-cell">
                                        <div class="student-info">
                                            <span class="student-name">{{ student.lastName }}, {{ student.firstName }} {{ student.middleName }}</span>
                                            <span class="student-lrn">LRN: {{ student.lrn }}</span>
                                        </div>
                                    </td>
                                    <td v-for="day in attendanceDays" :key="day.date" class="attendance-cell">
                                        <span :class="['attendance-mark', getAttendanceClass(student.attendance[day.date])]">
                                            {{ getAttendanceMark(student.attendance[day.date]) }}
                                        </span>
                                    </td>
                                    <td class="summary-cell present-count">{{ student.totalPresent }}</td>
                                    <td class="summary-cell absent-count">{{ student.totalAbsent }}</td>
                                    <td class="summary-cell rate-cell">
                                        <span :class="['rate-badge', getRateClass(student.attendanceRate)]">{{ student.attendanceRate }}%</span>
                                    </td>
                                </tr>

                                <!-- MALE TOTAL Per Day Row -->
                                <tr class="gender-total-row male-total">
                                    <td class="gender-total-label">
                                        <strong>👨 MALE | TOTAL Per Day</strong>
                                    </td>
                                    <td v-for="day in attendanceDays" :key="day.date" class="gender-total-cell">
                                        <strong>{{ getMaleDayTotal(selectedSectionDetails.name, day.date) }}</strong>
                                    </td>
                                    <td class="gender-total-cell">
                                        <strong>{{ getMaleTotalPresent(selectedSectionDetails.name) }}</strong>
                                    </td>
                                    <td class="gender-total-cell">
                                        <strong>{{ getMaleTotalAbsent(selectedSectionDetails.name) }}</strong>
                                    </td>
                                    <td class="gender-total-cell">
                                        <strong>{{ getMaleAttendanceRate(selectedSectionDetails.name) }}%</strong>
                                    </td>
                                </tr>

                                <!-- FEMALE Students Section -->
                                <tr class="gender-header-row">
                                    <td class="gender-header" colspan="25">
                                        <div class="gender-section">
                                            <span class="gender-label">👩 FEMALE</span>
                                            <span class="gender-total">TOTAL Per Day</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="student in getFemaleStudents(selectedSectionDetails.name)" :key="student.id" class="student-row">
                                    <td class="student-name-cell">
                                        <div class="student-info">
                                            <span class="student-name">{{ student.lastName }}, {{ student.firstName }} {{ student.middleName }}</span>
                                            <span class="student-lrn">LRN: {{ student.lrn }}</span>
                                        </div>
                                    </td>
                                    <td v-for="day in attendanceDays" :key="day.date" class="attendance-cell">
                                        <span :class="['attendance-mark', getAttendanceClass(student.attendance[day.date])]">
                                            {{ getAttendanceMark(student.attendance[day.date]) }}
                                        </span>
                                    </td>
                                    <td class="summary-cell present-count">{{ student.totalPresent }}</td>
                                    <td class="summary-cell absent-count">{{ student.totalAbsent }}</td>
                                    <td class="summary-cell rate-cell">
                                        <span :class="['rate-badge', getRateClass(student.attendanceRate)]">{{ student.attendanceRate }}%</span>
                                    </td>
                                </tr>

                                <!-- FEMALE TOTAL Per Day Row -->
                                <tr class="gender-total-row female-total">
                                    <td class="gender-total-label">
                                        <strong>👩 FEMALE | TOTAL Per Day</strong>
                                    </td>
                                    <td v-for="day in attendanceDays" :key="day.date" class="gender-total-cell">
                                        <strong>{{ getFemaleDayTotal(selectedSectionDetails.name, day.date) }}</strong>
                                    </td>
                                    <td class="gender-total-cell">
                                        <strong>{{ getFemaleTotalPresent(selectedSectionDetails.name) }}</strong>
                                    </td>
                                    <td class="gender-total-cell">
                                        <strong>{{ getFemaleTotalAbsent(selectedSectionDetails.name) }}</strong>
                                    </td>
                                    <td class="gender-total-cell">
                                        <strong>{{ getFemaleAttendanceRate(selectedSectionDetails.name) }}%</strong>
                                    </td>
                                </tr>

                                <!-- Combined TOTAL PER DAY Row -->
                                <tr class="combined-total-row">
                                    <td class="total-label-cell">
                                        <strong>Combined TOTAL PER DAY</strong>
                                    </td>
                                    <td v-for="day in attendanceDays" :key="day.date" class="total-cell">
                                        <strong>{{ getDayTotal(selectedSectionDetails.name, day.date) }}</strong>
                                    </td>
                                    <td class="total-cell">
                                        <strong>{{ getTotalPresent(selectedSectionDetails.name) }}</strong>
                                    </td>
                                    <td class="total-cell">
                                        <strong>{{ getTotalAbsent(selectedSectionDetails.name) }}</strong>
                                    </td>
                                    <td class="total-cell">
                                        <strong>{{ getOverallAttendanceRate(selectedSectionDetails.name) }}%</strong>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Monthly Statistics -->
                <div class="monthly-statistics">
                    <h4>Monthly Statistics</h4>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="pi pi-users"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-number">{{ selectedSectionDetails.studentCount }}</span>
                                <span class="stat-label">Total Students</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon present">
                                <i class="pi pi-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-number">{{ calculateTotalPresent() }}</span>
                                <span class="stat-label">Total Present Days</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon absent">
                                <i class="pi pi-times-circle"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-number">{{ calculateTotalAbsent() }}</span>
                                <span class="stat-label">Total Absent Days</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon rate">
                                <i class="pi pi-chart-line"></i>
                            </div>
                            <div class="stat-content">
                                <span class="stat-number">{{ selectedSectionDetails.attendanceRate }}%</span>
                                <span class="stat-label">Average Attendance</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Back to Reports" icon="pi pi-arrow-left" class="p-button-text" @click="backToMainReport()" />
                <Button label="Download SF2 Report" icon="pi pi-download" class="p-button-primary" @click="downloadSF2Report()" />
                <Button label="Print Report" icon="pi pi-print" class="p-button-success" @click="printReport()" />
            </template>
        </Dialog>

        <!-- View Report Dialog -->
        <Dialog v-model:visible="showViewDialog" :header="selectedReport?.title || 'Report Details'" modal class="p-fluid" :style="{ width: '80vw', maxWidth: '1000px' }">
            <div v-if="selectedReport" class="report-preview">
                <div class="preview-header">
                    <div class="header-main">
                        <h3>{{ selectedReport.title }}</h3>
                        <p>{{ selectedReport.description }}</p>
                    </div>
                    <div class="report-period">
                        <span class="period-text">{{ getReportPeriod(selectedReport) }}</span>
                    </div>
                </div>

                <div class="preview-content">
                    <div v-if="hasGradeSections(selectedReport.grade)" class="grade-sections">
                        <div class="sections-header">
                            <h4 class="sections-title mb-4">{{ selectedReport.grade }} Sections</h4>
                            <div class="records-count">
                                <span class="records-text">{{ getRecordsCount(selectedReport.grade) }}</span>
                            </div>
                        </div>
                        <div class="sections-grid">
                            <div v-for="section in getGradeSections(selectedReport.grade)" :key="section.id" class="section-card">
                                <div class="section-header">
                                    <div class="section-status" :class="section.statusClass">{{ section.status }}</div>
                                </div>
                                <div class="section-content">
                                    <h5 class="section-name">{{ section.name }}</h5>
                                    <p class="section-teacher">{{ section.teacher }}</p>
                                    <div class="section-stats">
                                        <div class="stat-item">
                                            <span class="stat-label">Students:</span>
                                            <span class="stat-value">{{ section.studentCount }}</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Present:</span>
                                            <span class="stat-value">{{ section.presentCount }}</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="stat-label">Absent:</span>
                                            <span class="stat-value">{{ section.absentCount }}</span>
                                        </div>
                                    </div>
                                    <div class="attendance-rate">
                                        <span class="rate-label">Attendance Rate:</span>
                                        <span class="rate-value" :class="getRateClass(section.attendanceRate)">{{ section.attendanceRate }}%</span>
                                    </div>
                                </div>
                                <div class="section-actions">
                                    <Button label="View Details" size="small" class="p-button-outlined p-button-sm" @click="viewSectionDetails(section)" />
                                    <Button icon="pi pi-download" size="small" class="p-button-text p-button-sm" @click="downloadSectionReport(section)" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="general-report-content">
                        <p>Report content would be displayed here...</p>
                        <p>Generated on: {{ formatDate(selectedReport.created_at) }}</p>
                        <p>Records: {{ selectedReport.records_count }}</p>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" class="p-button-text" @click="showViewDialog = false" />
                <Button label="Download" icon="pi pi-download" class="p-button-primary" @click="downloadReport(selectedReport)" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Select from 'primevue/select';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const toast = useToast();
const confirm = useConfirm();

// Reactive data
const loading = ref(false);
const generating = ref(false);
const showGenerateDialog = ref(false);
const showViewDialog = ref(false);
const showSectionDetailsDialog = ref(false);
const selectedReport = ref(null);
const selectedSectionDetails = ref(null);
const searchQuery = ref('');
const selectedGradeType = ref(null);
const selectedDateRange = ref(null);
const selectedStatus = ref(null);

// Month navigation
const currentMonth = ref(8); // September = 8 (0-based index)
const currentYear = ref(2025);
const availableMonths = ref([
    { month: 7, year: 2025, name: 'August 2025' },
    { month: 8, year: 2025, name: 'September 2025' },
    { month: 9, year: 2025, name: 'October 2025' }
]);

// Historical section data with different teachers per month
const historicalSectionData = ref({
    'Kinder A': {
        'August 2025': {
            teacher: 'Ms. Maria Santos',
            students: [
                {
                    id: 1,
                    firstName: 'Juan',
                    middleName: 'Carlos',
                    lastName: 'Dela Cruz',
                    gender: 'Male',
                    lrn: '123456789010',
                    attendance: {
                        '2025-08-01': 'present', '2025-08-02': 'present', '2025-08-05': 'present',
                        '2025-08-06': 'present', '2025-08-07': 'present', '2025-08-08': 'present',
                        '2025-08-09': 'present', '2025-08-12': 'present', '2025-08-13': 'absent',
                        '2025-08-14': 'present', '2025-08-15': 'present', '2025-08-16': 'present',
                        '2025-08-19': 'present', '2025-08-20': 'present', '2025-08-21': 'present',
                        '2025-08-22': 'present', '2025-08-23': 'present', '2025-08-26': 'present',
                        '2025-08-27': 'present', '2025-08-28': 'present', '2025-08-29': 'present',
                        '2025-08-30': 'present'
                    },
                    totalPresent: 21,
                    totalAbsent: 1,
                    attendanceRate: 95
                },
                {
                    id: 2,
                    firstName: 'Maria',
                    middleName: 'Elena',
                    lastName: 'Rodriguez',
                    gender: 'Female',
                    lrn: '123456789011',
                    attendance: {
                        '2025-08-01': 'present', '2025-08-02': 'present', '2025-08-05': 'present',
                        '2025-08-06': 'present', '2025-08-07': 'present', '2025-08-08': 'present',
                        '2025-08-09': 'present', '2025-08-12': 'present', '2025-08-13': 'present',
                        '2025-08-14': 'present', '2025-08-15': 'present', '2025-08-16': 'present',
                        '2025-08-19': 'present', '2025-08-20': 'present', '2025-08-21': 'present',
                        '2025-08-22': 'present', '2025-08-23': 'present', '2025-08-26': 'present',
                        '2025-08-27': 'present', '2025-08-28': 'present', '2025-08-29': 'present',
                        '2025-08-30': 'present'
                    },
                    totalPresent: 22,
                    totalAbsent: 0,
                    attendanceRate: 100
                },
                {
                    id: 3,
                    firstName: 'Carlos',
                    middleName: 'Antonio',
                    lastName: 'Martinez',
                    gender: 'Male',
                    lrn: '123456789012',
                    attendance: {
                        '2025-08-01': 'present', '2025-08-02': 'present', '2025-08-05': 'present',
                        '2025-08-06': 'present', '2025-08-07': 'present', '2025-08-08': 'absent',
                        '2025-08-09': 'present', '2025-08-12': 'present', '2025-08-13': 'present',
                        '2025-08-14': 'present', '2025-08-15': 'present', '2025-08-16': 'present',
                        '2025-08-19': 'present', '2025-08-20': 'present', '2025-08-21': 'present',
                        '2025-08-22': 'present', '2025-08-23': 'present', '2025-08-26': 'present',
                        '2025-08-27': 'present', '2025-08-28': 'present', '2025-08-29': 'present',
                        '2025-08-30': 'present'
                    },
                    totalPresent: 21,
                    totalAbsent: 1,
                    attendanceRate: 95
                },
                {
                    id: 4,
                    firstName: 'Ana',
                    middleName: 'Isabel',
                    lastName: 'Garcia',
                    gender: 'Female',
                    lrn: '123456789013',
                    attendance: {
                        '2025-08-01': 'present', '2025-08-02': 'present', '2025-08-05': 'present',
                        '2025-08-06': 'present', '2025-08-07': 'present', '2025-08-08': 'present',
                        '2025-08-09': 'present', '2025-08-12': 'present', '2025-08-13': 'present',
                        '2025-08-14': 'present', '2025-08-15': 'absent', '2025-08-16': 'present',
                        '2025-08-19': 'present', '2025-08-20': 'present', '2025-08-21': 'present',
                        '2025-08-22': 'present', '2025-08-23': 'present', '2025-08-26': 'present',
                        '2025-08-27': 'present', '2025-08-28': 'present', '2025-08-29': 'present',
                        '2025-08-30': 'present'
                    },
                    totalPresent: 21,
                    totalAbsent: 1,
                    attendanceRate: 95
                }
            ]
        },
        'September 2025': {
            teacher: 'Ms. Lisa Chen',
            students: [] // Will use existing sectionStudents data
        },
        'October 2025': {
            teacher: 'Ms. Lisa Chen',
            students: [
                {
                    id: 1,
                    firstName: 'Juan',
                    middleName: 'Carlos',
                    lastName: 'Dela Cruz',
                    gender: 'Male',
                    lrn: '123456789010',
                    attendance: {
                        '2025-10-01': 'present', '2025-10-02': 'present', '2025-10-03': 'present',
                        '2025-10-04': 'present', '2025-10-07': 'present', '2025-10-08': 'present',
                        '2025-10-09': 'present', '2025-10-10': 'present', '2025-10-11': 'present',
                        '2025-10-14': 'present', '2025-10-15': 'present', '2025-10-16': 'present',
                        '2025-10-17': 'present', '2025-10-18': 'present', '2025-10-21': 'present',
                        '2025-10-22': 'present', '2025-10-23': 'present', '2025-10-24': 'present',
                        '2025-10-25': 'present', '2025-10-28': 'present', '2025-10-29': 'present',
                        '2025-10-30': 'present', '2025-10-31': 'present'
                    },
                    totalPresent: 23,
                    totalAbsent: 0,
                    attendanceRate: 100
                }
            ]
        }
    }
});

// Kinder sections data
const kinderSections = ref([
    {
        id: 1,
        name: 'Kinder A',
        teacher: 'Ms. Lisa Chen',
        studentCount: 25,
        presentCount: 23,
        absentCount: 2,
        attendanceRate: 92,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 2,
        name: 'Kinder B',
        teacher: 'Ms. Maria Santos',
        studentCount: 28,
        presentCount: 26,
        absentCount: 2,
        attendanceRate: 93,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 3,
        name: 'Kinder C',
        teacher: 'Ms. Lisa Chen',
        studentCount: 24,
        presentCount: 20,
        absentCount: 4,
        attendanceRate: 83,
        status: 'GOOD',
        statusClass: 'status-good'
    },
    {
        id: 4,
        name: 'Kinder D',
        teacher: 'Ms. Anna Rodriguez',
        studentCount: 26,
        presentCount: 18,
        absentCount: 8,
        attendanceRate: 69,
        status: 'NEEDS ATTENTION',
        statusClass: 'status-warning'
    }
]);

// Grade 1 sections data
const grade1Sections = ref([
    {
        id: 1,
        name: 'Grade 1-A',
        teacher: 'Ms. Jennifer Lee',
        studentCount: 30,
        presentCount: 28,
        absentCount: 2,
        attendanceRate: 93,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 2,
        name: 'Grade 1-B',
        teacher: 'Ms. Patricia Wong',
        studentCount: 29,
        presentCount: 25,
        absentCount: 4,
        attendanceRate: 86,
        status: 'GOOD',
        statusClass: 'status-good'
    },
    {
        id: 3,
        name: 'Grade 1-C',
        teacher: 'Ms. Rebecca Davis',
        studentCount: 28,
        presentCount: 22,
        absentCount: 6,
        attendanceRate: 79,
        status: 'GOOD',
        statusClass: 'status-good'
    }
]);

// Grade 3 sections data
const grade3Sections = ref([
    {
        id: 1,
        name: 'Grade 3-A',
        teacher: 'Mr. Michael Brown',
        studentCount: 32,
        presentCount: 30,
        absentCount: 2,
        attendanceRate: 94,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 2,
        name: 'Grade 3-B',
        teacher: 'Ms. Amanda Wilson',
        studentCount: 31,
        presentCount: 27,
        absentCount: 4,
        attendanceRate: 87,
        status: 'GOOD',
        statusClass: 'status-good'
    },
    {
        id: 3,
        name: 'Grade 3-C',
        teacher: 'Ms. Karen Martinez',
        studentCount: 30,
        presentCount: 20,
        absentCount: 10,
        attendanceRate: 67,
        status: 'NEEDS ATTENTION',
        statusClass: 'status-warning'
    }
]);

// Grade 2 sections data
const grade2Sections = ref([
    {
        id: 1,
        name: 'Grade 2-A',
        teacher: 'Ms. Catherine Lopez',
        studentCount: 27,
        presentCount: 26,
        absentCount: 1,
        attendanceRate: 96,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 2,
        name: 'Grade 2-B',
        teacher: 'Ms. Diana Cruz',
        studentCount: 29,
        presentCount: 28,
        absentCount: 1,
        attendanceRate: 97,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 3,
        name: 'Grade 2-C',
        teacher: 'Mr. Robert Kim',
        studentCount: 28,
        presentCount: 25,
        absentCount: 3,
        attendanceRate: 89,
        status: 'GOOD',
        statusClass: 'status-good'
    },
    {
        id: 4,
        name: 'Grade 2-D',
        teacher: 'Ms. Elena Reyes',
        studentCount: 30,
        presentCount: 29,
        absentCount: 1,
        attendanceRate: 97,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    }
]);

// Grade 4 sections data
const grade4Sections = ref([
    {
        id: 1,
        name: 'Grade 4-A',
        teacher: 'Mr. James Thompson',
        studentCount: 32,
        presentCount: 31,
        absentCount: 1,
        attendanceRate: 97,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 2,
        name: 'Grade 4-B',
        teacher: 'Ms. Michelle Garcia',
        studentCount: 31,
        presentCount: 30,
        absentCount: 1,
        attendanceRate: 97,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 3,
        name: 'Grade 4-C',
        teacher: 'Ms. Sandra Flores',
        studentCount: 29,
        presentCount: 27,
        absentCount: 2,
        attendanceRate: 93,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 4,
        name: 'Grade 4-D',
        teacher: 'Mr. David Park',
        studentCount: 33,
        presentCount: 31,
        absentCount: 2,
        attendanceRate: 94,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    }
]);

// Attendance days for September 2025
const attendanceDays = ref([
    { date: '2025-09-01', day: 1, dayName: 'M' },
    { date: '2025-09-02', day: 2, dayName: 'T' },
    { date: '2025-09-03', day: 3, dayName: 'W' },
    { date: '2025-09-04', day: 4, dayName: 'Th' },
    { date: '2025-09-05', day: 5, dayName: 'F' },
    { date: '2025-09-08', day: 8, dayName: 'M' },
    { date: '2025-09-09', day: 9, dayName: 'T' },
    { date: '2025-09-10', day: 10, dayName: 'W' },
    { date: '2025-09-11', day: 11, dayName: 'Th' },
    { date: '2025-09-12', day: 12, dayName: 'F' },
    { date: '2025-09-15', day: 15, dayName: 'M' },
    { date: '2025-09-16', day: 16, dayName: 'T' },
    { date: '2025-09-17', day: 17, dayName: 'W' },
    { date: '2025-09-18', day: 18, dayName: 'Th' },
    { date: '2025-09-19', day: 19, dayName: 'F' },
    { date: '2025-09-22', day: 22, dayName: 'M' },
    { date: '2025-09-23', day: 23, dayName: 'T' },
    { date: '2025-09-24', day: 24, dayName: 'W' },
    { date: '2025-09-25', day: 25, dayName: 'Th' },
    { date: '2025-09-26', day: 26, dayName: 'F' },
    { date: '2025-09-29', day: 29, dayName: 'M' },
    { date: '2025-09-30', day: 30, dayName: 'T' }
]);

// Sample student data for each section
const sectionStudents = ref({
    'Kinder A': [
        // MALE STUDENTS
        {
            id: 1,
            firstName: 'Juan',
            middleName: 'Dela',
            lastName: 'Reyes',
            gender: 'Male',
            lrn: '123456789013',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'absent',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'absent',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 20,
            totalAbsent: 2,
            attendanceRate: 91
        },
        {
            id: 2,
            firstName: 'Pedro',
            middleName: 'Jose',
            lastName: 'Martinez',
            gender: 'Male',
            lrn: '123456789015',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 22,
            totalAbsent: 0,
            attendanceRate: 100
        },
        {
            id: 3,
            firstName: 'Carlos',
            middleName: 'Antonio',
            lastName: 'Santos',
            gender: 'Male',
            lrn: '123456789020',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'absent',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'absent',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 20,
            totalAbsent: 2,
            attendanceRate: 91
        },
        {
            id: 4,
            firstName: 'Miguel',
            middleName: 'Angel',
            lastName: 'Rodriguez',
            gender: 'Male',
            lrn: '123456789019',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'late',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 22,
            totalAbsent: 0,
            attendanceRate: 100
        },
        // FEMALE STUDENTS
        {
            id: 5,
            firstName: 'Maria',
            middleName: 'Santos',
            lastName: 'Cruz',
            gender: 'Female',
            lrn: '123456789012',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'absent',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'absent',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 20,
            totalAbsent: 2,
            attendanceRate: 91
        },
        {
            id: 6,
            firstName: 'Ana',
            middleName: 'Marie',
            lastName: 'Garcia',
            gender: 'Female',
            lrn: '123456789014',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'absent',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'absent',
                '2025-09-30': 'present'
            },
            totalPresent: 20,
            totalAbsent: 2,
            attendanceRate: 91
        },
        {
            id: 7,
            firstName: 'Sofia',
            middleName: 'Isabel',
            lastName: 'Lopez',
            gender: 'Female',
            lrn: '123456789016',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'absent',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'absent',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 20,
            totalAbsent: 2,
            attendanceRate: 91
        },
        {
            id: 8,
            firstName: 'Isabella',
            middleName: 'Grace',
            lastName: 'Dela Cruz',
            gender: 'Female',
            lrn: '123456789021',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'absent',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 21,
            totalAbsent: 1,
            attendanceRate: 95
        },
        {
            id: 9,
            firstName: 'Gabriel',
            middleName: 'Luis',
            lastName: 'Mendoza',
            gender: 'Male',
            lrn: '123456789022',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'absent',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 21,
            totalAbsent: 1,
            attendanceRate: 95
        },
        {
            id: 10,
            firstName: 'Camila',
            middleName: 'Rose',
            lastName: 'Villanueva',
            gender: 'Female',
            lrn: '123456789023',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'absent',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 21,
            totalAbsent: 1,
            attendanceRate: 95
        },
        {
            id: 11,
            firstName: 'Diego',
            middleName: 'Carlos',
            lastName: 'Ramos',
            gender: 'Male',
            lrn: '123456789024',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 22,
            totalAbsent: 0,
            attendanceRate: 100
        },
        {
            id: 12,
            firstName: 'Valentina',
            middleName: 'Joy',
            lastName: 'Santos',
            gender: 'Female',
            lrn: '123456789025',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'absent',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'absent',
                '2025-09-30': 'present'
            },
            totalPresent: 20,
            totalAbsent: 2,
            attendanceRate: 91
        }
    ],
    'Kinder B': [
        {
            id: 6,
            firstName: 'Carlos',
            middleName: 'Antonio',
            lastName: 'Fernandez',
            gender: 'Male',
            lrn: '123456789017',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 22,
            totalAbsent: 0,
            attendanceRate: 100
        },
        {
            id: 7,
            firstName: 'Isabella',
            middleName: 'Grace',
            lastName: 'Morales',
            gender: 'Female',
            lrn: '123456789018',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'absent',
                '2025-09-30': 'absent'
            },
            totalPresent: 20,
            totalAbsent: 2,
            attendanceRate: 91
        },
        {
            id: 13,
            firstName: 'Sebastian',
            middleName: 'Miguel',
            lastName: 'Torres',
            gender: 'Male',
            lrn: '123456789026',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 22,
            totalAbsent: 0,
            attendanceRate: 100
        },
        {
            id: 14,
            firstName: 'Lucia',
            middleName: 'Carmen',
            lastName: 'Herrera',
            gender: 'Female',
            lrn: '123456789027',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'absent',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 21,
            totalAbsent: 1,
            attendanceRate: 95
        },
        {
            id: 15,
            firstName: 'Mateo',
            middleName: 'Jose',
            lastName: 'Castillo',
            gender: 'Male',
            lrn: '123456789028',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'absent',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 21,
            totalAbsent: 1,
            attendanceRate: 95
        },
        {
            id: 16,
            firstName: 'Emilia',
            middleName: 'Faith',
            lastName: 'Jimenez',
            gender: 'Female',
            lrn: '123456789029',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 22,
            totalAbsent: 0,
            attendanceRate: 100
        }
    ],
    'Grade 1-A': [
        {
            id: 8,
            firstName: 'Miguel',
            middleName: 'Angel',
            lastName: 'Rodriguez',
            gender: 'Male',
            lrn: '123456789019',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 22,
            totalAbsent: 0,
            attendanceRate: 100
        },
        {
            id: 17,
            firstName: 'Adriana',
            middleName: 'Nicole',
            lastName: 'Vargas',
            gender: 'Female',
            lrn: '123456789030',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'absent',
                '2025-09-30': 'present'
            },
            totalPresent: 21,
            totalAbsent: 1,
            attendanceRate: 95
        },
        {
            id: 18,
            firstName: 'Leonardo',
            middleName: 'David',
            lastName: 'Gutierrez',
            gender: 'Male',
            lrn: '123456789031',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'absent',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 21,
            totalAbsent: 1,
            attendanceRate: 95
        },
        {
            id: 19,
            firstName: 'Natalia',
            middleName: 'Esperanza',
            lastName: 'Medina',
            gender: 'Female',
            lrn: '123456789032',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 22,
            totalAbsent: 0,
            attendanceRate: 100
        },
        {
            id: 20,
            firstName: 'Santiago',
            middleName: 'Rafael',
            lastName: 'Aguilar',
            gender: 'Male',
            lrn: '123456789033',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'absent',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 21,
            totalAbsent: 1,
            attendanceRate: 95
        },
        {
            id: 21,
            firstName: 'Valeria',
            middleName: 'Cristina',
            lastName: 'Ortega',
            gender: 'Female',
            lrn: '123456789034',
            attendance: {
                '2025-09-01': 'present',
                '2025-09-02': 'present',
                '2025-09-03': 'present',
                '2025-09-04': 'present',
                '2025-09-05': 'present',
                '2025-09-08': 'present',
                '2025-09-09': 'present',
                '2025-09-10': 'present',
                '2025-09-11': 'present',
                '2025-09-12': 'present',
                '2025-09-15': 'present',
                '2025-09-16': 'present',
                '2025-09-17': 'present',
                '2025-09-18': 'present',
                '2025-09-19': 'present',
                '2025-09-22': 'present',
                '2025-09-23': 'present',
                '2025-09-24': 'present',
                '2025-09-25': 'present',
                '2025-09-26': 'present',
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 22,
            totalAbsent: 0,
            attendanceRate: 100
        }
    ]
});

// Form data for new report
const newReport = ref({
    title: '',
    type: null,
    description: '',
    startDate: '',
    endDate: ''
});

// Sample reports data
const reports = ref([
    {
        id: 1,
        title: 'Kinder Attendance Report',
        description: 'Monthly attendance report for Kindergarten students',
        grade: 'Kinder',
        status: 'COMPLETED',
        created_at: '2025-09-01',
        generated_by: 'Admin User',
        records_count: 150,
        year: '2025',
        month: 'september'
    },
    {
        id: 2,
        title: 'Grade 1 Attendance Report',
        description: 'Weekly attendance summary for Grade 1 students',
        grade: 'Grade 1',
        status: 'INCOMPLETE',
        created_at: '2025-09-03',
        generated_by: 'Admin User',
        records_count: 180,
        year: '2025',
        month: 'september'
    },
    {
        id: 3,
        title: 'Grade 3 Attendance Report',
        description: 'Daily attendance tracking for Grade 3 students',
        grade: 'Grade 3',
        status: 'INCOMPLETE',
        created_at: '2025-08-30',
        generated_by: 'Admin User',
        records_count: 165,
        year: '2025',
        month: 'august'
    },
    {
        id: 4,
        title: 'Grade 6 Attendance Report',
        description: 'Monthly attendance analysis for Grade 6 students',
        grade: 'Grade 6',
        status: 'EMPTY',
        created_at: '2025-09-02',
        generated_by: 'Admin User',
        records_count: 0,
        year: '2025',
        month: 'september'
    },
    {
        id: 5,
        title: 'Grade 2 Attendance Report',
        description: 'Monthly attendance report for Grade 2 students',
        grade: 'Grade 2',
        status: 'COMPLETED',
        created_at: '2024-12-15',
        generated_by: 'Admin User',
        records_count: 145,
        year: '2024',
        month: 'december'
    },
    {
        id: 6,
        title: 'Grade 4 Attendance Report',
        description: 'Quarterly attendance summary for Grade 4 students',
        grade: 'Grade 4',
        status: 'COMPLETED',
        created_at: '2023-06-20',
        generated_by: 'Admin User',
        records_count: 175,
        year: '2023',
        month: 'june'
    }
]);

// Options for dropdowns
const gradeTypes = ref([
    { name: 'Kinder', value: 'Kinder' },
    { name: 'Grade 1', value: 'Grade 1' },
    { name: 'Grade 2', value: 'Grade 2' },
    { name: 'Grade 3', value: 'Grade 3' },
    { name: 'Grade 4', value: 'Grade 4' },
    { name: 'Grade 5', value: 'Grade 5' },
    { name: 'Grade 6', value: 'Grade 6' }
]);

const dateRanges = ref([
    { name: '2025', value: '2025' },
    { name: '2024', value: '2024' },
    { name: '2023', value: '2023' },
    { name: '2022', value: '2022' },
    { name: '2021', value: '2021' },
    { name: '2020', value: '2020' }
]);

const statusOptions = ref([
    { name: 'January', value: 'january' },
    { name: 'February', value: 'february' },
    { name: 'March', value: 'march' },
    { name: 'April', value: 'april' },
    { name: 'May', value: 'may' },
    { name: 'June', value: 'june' },
    { name: 'July', value: 'july' },
    { name: 'August', value: 'august' },
    { name: 'September', value: 'september' },
    { name: 'October', value: 'october' },
    { name: 'November', value: 'november' },
    { name: 'December', value: 'december' }
]);

// Computed properties
const filteredReports = computed(() => {
    let filtered = reports.value;

    if (searchQuery.value) {
        filtered = filtered.filter((report) => report.title.toLowerCase().includes(searchQuery.value.toLowerCase()) || report.description.toLowerCase().includes(searchQuery.value.toLowerCase()));
    }

    if (selectedGradeType.value) {
        filtered = filtered.filter((report) => report.grade === selectedGradeType.value);
    }

    if (selectedDateRange.value) {
        filtered = filtered.filter((report) => report.year === selectedDateRange.value);
    }

    if (selectedStatus.value) {
        filtered = filtered.filter((report) => report.month === selectedStatus.value);
    }

    return filtered;
});

// Methods
const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const getStatusClass = (status) => {
    switch (status) {
        case 'COMPLETED':
            return 'status-completed';
        case 'INCOMPLETE':
            return 'status-incomplete';
        case 'EMPTY':
            return 'status-empty';
        case 'Pending':
            return 'status-pending';
        default:
            return '';
    }
};

const getRateClass = (rate) => {
    if (rate >= 90) return 'rate-excellent';
    if (rate >= 80) return 'rate-good';
    if (rate >= 70) return 'rate-warning';
    return 'rate-poor';
};

const getReportPeriod = (report) => {
    if (!report) return '';
    const monthNames = {
        september: 'September',
        august: 'August',
        december: 'December',
        june: 'June'
    };
    const month = monthNames[report.month] || 'September';
    return `${month}, ${report.year}`;
};

const hasGradeSections = (grade) => {
    return ['Kinder', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4'].includes(grade);
};

const getGradeSections = (grade) => {
    switch (grade) {
        case 'Kinder':
            return kinderSections.value;
        case 'Grade 1':
            return grade1Sections.value;
        case 'Grade 2':
            return grade2Sections.value;
        case 'Grade 3':
            return grade3Sections.value;
        case 'Grade 4':
            return grade4Sections.value;
        default:
            return [];
    }
};

const getRecordsCount = (grade) => {
    switch (grade) {
        case 'Kinder':
            return 'Records: 4 / 4';
        case 'Grade 1':
            return 'Records: 3 / 4';
        case 'Grade 2':
            return 'Records: 4 / 4';
        case 'Grade 3':
            return 'Records: 3 / 4';
        case 'Grade 4':
            return 'Records: 4 / 4';
        default:
            return 'Records: 0 / 0';
    }
};

const viewReport = (report) => {
    selectedReport.value = report;
    showViewDialog.value = true;
};

const downloadReport = (report) => {
    toast.add({
        severity: 'info',
        summary: 'Download Started',
        detail: `Downloading ${report.title}...`,
        life: 3000
    });
    // Implement actual download logic here
};

const shareReport = (report) => {
    toast.add({
        severity: 'info',
        summary: 'Share Report',
        detail: `Sharing ${report.title}...`,
        life: 3000
    });
    // Implement sharing logic here
};

const confirmDeleteReport = (report) => {
    confirm.require({
        message: `Are you sure you want to delete "${report.title}"?`,
        header: 'Confirm Deletion',
        icon: 'pi pi-exclamation-triangle',
        accept: () => {
            deleteReport(report);
        }
    });
};

const deleteReport = (report) => {
    const index = reports.value.findIndex((r) => r.id === report.id);
    if (index > -1) {
        reports.value.splice(index, 1);
        toast.add({
            severity: 'success',
            summary: 'Report Deleted',
            detail: `${report.title} has been deleted`,
            life: 3000
        });
    }
};

const generateReport = async () => {
    if (!newReport.value.title || !newReport.value.type) {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: 'Please fill in all required fields',
            life: 3000
        });
        return;
    }

    generating.value = true;

    // Simulate report generation
    setTimeout(() => {
        const newId = Math.max(...reports.value.map((r) => r.id)) + 1;
        reports.value.unshift({
            id: newId,
            title: newReport.value.title,
            description: newReport.value.description,
            type: newReport.value.type,
            status: 'Processing',
            created_at: new Date().toISOString().split('T')[0],
            generated_by: 'Admin User',
            records_count: Math.floor(Math.random() * 1000) + 100
        });

        toast.add({
            severity: 'success',
            summary: 'Report Generated',
            detail: `${newReport.value.title} has been generated successfully`,
            life: 3000
        });

        // Reset form
        newReport.value = {
            title: '',
            type: null,
            description: '',
            startDate: '',
            endDate: ''
        };

        generating.value = false;
        showGenerateDialog.value = false;
    }, 2000);
};

const exportAllReports = () => {
    toast.add({
        severity: 'info',
        summary: 'Export Started',
        detail: 'Exporting all reports...',
        life: 3000
    });
    // Implement export logic here
};

// Section details methods
const viewSectionDetails = (section) => {
    // Close the main report dialog first
    showViewDialog.value = false;
    // Wait a moment then open the section details dialog
    setTimeout(() => {
        selectedSectionDetails.value = section;
        showSectionDetailsDialog.value = true;
    }, 100);
};

const downloadSectionReport = (section) => {
    toast.add({
        severity: 'info',
        summary: 'Download Started',
        detail: `Downloading ${section.name} attendance report...`,
        life: 3000
    });
};

const getSectionDialogTitle = () => {
    if (!selectedSectionDetails.value) return 'Section Details';
    return `${selectedSectionDetails.value.name} - Daily Attendance Report`;
};

const getSectionStudents = (sectionName) => {
    const monthDisplay = getCurrentMonthDisplay();
    const historicalData = historicalSectionData.value[sectionName];
    
    // Check if we have historical data for this month
    if (historicalData && historicalData[monthDisplay] && historicalData[monthDisplay].students.length > 0) {
        return historicalData[monthDisplay].students;
    }
    
    // Fall back to current section students data
    return sectionStudents.value[sectionName] || [];
};

const getMaleStudents = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.gender === 'Male');
};

const getFemaleStudents = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.gender === 'Female');
};

const getDayTotal = (sectionName, date) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.attendance[date] === 'present').length;
};

const getTotalPresent = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.reduce((total, student) => total + student.totalPresent, 0);
};

const getTotalAbsent = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.reduce((total, student) => total + student.totalAbsent, 0);
};

const getOverallAttendanceRate = (sectionName) => {
    const students = getSectionStudents(sectionName);
    if (students.length === 0) return 0;
    const totalRate = students.reduce((sum, student) => sum + student.attendanceRate, 0);
    return Math.round(totalRate / students.length);
};

// Male-specific calculations
const getMaleDayTotal = (sectionName, date) => {
    const maleStudents = getMaleStudents(sectionName);
    return maleStudents.filter((student) => student.attendance[date] === 'present').length;
};

const getMaleTotalPresent = (sectionName) => {
    const maleStudents = getMaleStudents(sectionName);
    return maleStudents.reduce((total, student) => total + student.totalPresent, 0);
};

const getMaleTotalAbsent = (sectionName) => {
    const maleStudents = getMaleStudents(sectionName);
    return maleStudents.reduce((total, student) => total + student.totalAbsent, 0);
};

const getMaleAttendanceRate = (sectionName) => {
    const maleStudents = getMaleStudents(sectionName);
    if (maleStudents.length === 0) return 0;
    const totalRate = maleStudents.reduce((sum, student) => sum + student.attendanceRate, 0);
    return Math.round(totalRate / maleStudents.length);
};

// Female-specific calculations
const getFemaleDayTotal = (sectionName, date) => {
    const femaleStudents = getFemaleStudents(sectionName);
    return femaleStudents.filter((student) => student.attendance[date] === 'present').length;
};

const getFemaleTotalPresent = (sectionName) => {
    const femaleStudents = getFemaleStudents(sectionName);
    return femaleStudents.reduce((total, student) => total + student.totalPresent, 0);
};

const getFemaleTotalAbsent = (sectionName) => {
    const femaleStudents = getFemaleStudents(sectionName);
    return femaleStudents.reduce((total, student) => total + student.totalAbsent, 0);
};

const getFemaleAttendanceRate = (sectionName) => {
    const femaleStudents = getFemaleStudents(sectionName);
    if (femaleStudents.length === 0) return 0;
    const totalRate = femaleStudents.reduce((sum, student) => sum + student.attendanceRate, 0);
    return Math.round(totalRate / femaleStudents.length);
};

const getAttendanceMark = (status) => {
    switch (status) {
        case 'present':
            return '✓';
        case 'absent':
            return '✗';
        case 'late':
            return 'L';
        default:
            return '-';
    }
};

const getAttendanceClass = (status) => {
    switch (status) {
        case 'present':
            return 'mark-present';
        case 'absent':
            return 'mark-absent';
        case 'late':
            return 'mark-late';
        default:
            return 'mark-none';
    }
};

const calculateTotalPresent = () => {
    if (!selectedSectionDetails.value) return 0;
    const students = getSectionStudents(selectedSectionDetails.value.name);
    return students.reduce((total, student) => total + student.totalPresent, 0);
};

const calculateTotalAbsent = () => {
    if (!selectedSectionDetails.value) return 0;
    const students = getSectionStudents(selectedSectionDetails.value.name);
    return students.reduce((total, student) => total + student.totalAbsent, 0);
};

// Month navigation functions
const getCurrentMonthDisplay = () => {
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                       'July', 'August', 'September', 'October', 'November', 'December'];
    return `${monthNames[currentMonth.value]} ${currentYear.value}`;
};

const getCurrentTeacher = () => {
    if (!selectedSectionDetails.value) return '';
    const sectionName = selectedSectionDetails.value.name;
    const monthDisplay = getCurrentMonthDisplay();
    const historicalData = historicalSectionData.value[sectionName];
    
    if (historicalData && historicalData[monthDisplay]) {
        return historicalData[monthDisplay].teacher;
    }
    return selectedSectionDetails.value.teacher;
};

const canGoPreviousMonth = () => {
    const currentIndex = availableMonths.value.findIndex(m => 
        m.month === currentMonth.value && m.year === currentYear.value
    );
    return currentIndex > 0;
};

const canGoNextMonth = () => {
    const currentIndex = availableMonths.value.findIndex(m => 
        m.month === currentMonth.value && m.year === currentYear.value
    );
    return currentIndex < availableMonths.value.length - 1;
};

const previousMonth = () => {
    const currentIndex = availableMonths.value.findIndex(m => 
        m.month === currentMonth.value && m.year === currentYear.value
    );
    if (currentIndex > 0) {
        const prevMonth = availableMonths.value[currentIndex - 1];
        currentMonth.value = prevMonth.month;
        currentYear.value = prevMonth.year;
    }
};

const nextMonth = () => {
    const currentIndex = availableMonths.value.findIndex(m => 
        m.month === currentMonth.value && m.year === currentYear.value
    );
    if (currentIndex < availableMonths.value.length - 1) {
        const nextMonth = availableMonths.value[currentIndex + 1];
        currentMonth.value = nextMonth.month;
        currentYear.value = nextMonth.year;
    }
};

const downloadSF2Report = () => {
    toast.add({
        severity: 'success',
        summary: 'Download Started',
        detail: 'Downloading SF2 report in PDF format...',
        life: 3000
    });
};

const printReport = () => {
    window.print();
};

const backToMainReport = () => {
    showSectionDetailsDialog.value = false;
    // Wait a moment then reopen the main report dialog
    setTimeout(() => {
        showViewDialog.value = true;
    }, 100);
};

// Lifecycle
onMounted(() => {
    // Load reports data
    loading.value = true;
    setTimeout(() => {
        loading.value = false;
    }, 1000);
});

// Report types for dropdown
const reportTypes = ref([
    { name: 'Daily Attendance', value: 'daily' },
    { name: 'Weekly Summary', value: 'weekly' },
    { name: 'Monthly Report', value: 'monthly' },
    { name: 'Quarterly Analysis', value: 'quarterly' }
]);
</script>

<style scoped>
.admin-reports-wrapper {
    position: relative;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    overflow: hidden;
}

/* Background shapes */
.background-container {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
    opacity: 0.1;
    z-index: 0;
}

.geometric-shape {
    position: absolute;
    opacity: 0.3;
    filter: blur(1px);
}

.circle {
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background: #ffffff;
    top: -80px;
    right: -80px;
    animation: float 20s ease-in-out infinite;
}

.square {
    width: 250px;
    height: 250px;
    background: #ffffff;
    bottom: -100px;
    left: -100px;
    transform: rotate(45deg);
    animation: float 25s ease-in-out infinite reverse;
}

.triangle {
    width: 0;
    height: 0;
    border-left: 150px solid transparent;
    border-right: 150px solid transparent;
    border-bottom: 260px solid #ffffff;
    top: 200px;
    right: 50px;
    animation: float 30s ease-in-out infinite 5s;
}

.rectangle {
    width: 350px;
    height: 180px;
    background: #ffffff;
    top: 300px;
    left: -120px;
    transform: rotate(-20deg);
    animation: float 22s ease-in-out infinite 2s;
}

.diamond {
    width: 200px;
    height: 200px;
    background: #ffffff;
    transform: rotate(45deg);
    bottom: 200px;
    right: 200px;
    animation: float 18s ease-in-out infinite 3s;
}

@keyframes float {
    0%,
    100% {
        transform: translateY(0px) rotate(0deg);
    }
    50% {
        transform: translateY(-20px) rotate(5deg);
    }
}

/* Main content */
.content-container {
    position: relative;
    z-index: 1;
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.reports-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.title-section h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.title-section h1 .emoji-icon {
    background: none;
    -webkit-text-fill-color: initial;
    background-clip: initial;
    font-size: 2.5rem;
}

.title-section h1 .text-gradient {
    background: linear-gradient(135deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-subtitle {
    color: #666;
    margin: 0.5rem 0 0 0;
    font-size: 1.1rem;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

/* Filter section */
.filter-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.filter-item {
    display: flex;
    flex-direction: column;
}

.filter-label {
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333;
}

/* Reports grid */
.reports-grid {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
}

.loading-container {
    text-align: center;
    padding: 3rem;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    color: #666;
}

.empty-icon {
    font-size: 4rem;
    color: #ddd;
    margin-bottom: 1rem;
}

.reports-list {
    display: grid;
    gap: 1.5rem;
}

.report-card {
    background: #ffffff;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid #e0e0e0;
    transition: all 0.3s ease;
}

.report-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.report-title {
    font-size: 1.2rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
    color: #333;
}

.report-description {
    color: #666;
    margin: 0;
    font-size: 0.9rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-incomplete {
    background: #fff3cd;
    color: #856404;
}

.status-empty {
    background: #f8d7da;
    color: #721c24;
}

.status-pending {
    background: #d1ecf1;
    color: #0c5460;
}

.report-details {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #666;
    font-size: 0.9rem;
}

.detail-item i {
    color: #667eea;
}

.report-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

/* Kinder Sections Styles */
.kinder-sections {
    padding: 1rem 0;
}

.sections-title {
    color: #333;
    font-weight: 600;
    margin-bottom: 1.5rem;
    border-bottom: 2px solid #667eea;
    padding-bottom: 0.5rem;
}

.sections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.section-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition:
        transform 0.3s ease,
        box-shadow 0.3s ease;
    border: 1px solid #e9ecef;
}

.section-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
}

.section-header {
    position: relative;
    padding: 1rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.section-status {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-excellent {
    background: #28a745;
    color: white;
}

.status-good {
    background: #17a2b8;
    color: white;
}

.status-warning {
    background: #ffc107;
    color: #212529;
}

.section-content {
    padding: 1.5rem;
}

.section-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: #333;
    margin: 0 0 0.5rem 0;
}

.section-teacher {
    color: #666;
    font-size: 0.9rem;
    margin: 0 0 1rem 0;
    font-style: italic;
}

.section-stats {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.stat-item {
    text-align: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.stat-label {
    display: block;
    font-size: 0.75rem;
    color: #666;
    text-transform: uppercase;
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.stat-value {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    color: #333;
}

.attendance-rate {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.rate-label {
    font-weight: 500;
    color: #333;
}

.rate-value {
    font-size: 1.1rem;
    font-weight: 700;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
}

.rate-excellent {
    background: #d4edda;
    color: #155724;
}

.rate-good {
    background: #d1ecf1;
    color: #0c5460;
}

.rate-warning {
    background: #fff3cd;
    color: #856404;
}

.rate-poor {
    background: #f8d7da;
    color: #721c24;
}

.section-actions {
    display: flex;
    gap: 0.5rem;
    padding: 0 1.5rem 1.5rem;
}

.general-report-content {
    padding: 2rem;
    text-align: center;
    color: #666;
}

/* Dialog styles */
.generate-form .field {
    margin-bottom: 1rem;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e0e0e0;
}

.header-main h3 {
    margin: 0 0 0.5rem 0;
    color: #333;
}

.header-main p {
    margin: 0;
    color: #666;
}

.report-period {
    background: #f8f9fa;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.period-text {
    font-weight: 600;
    color: #495057;
    font-size: 0.9rem;
}

.sections-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.records-count {
    background: #e3f2fd;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    border: 1px solid #bbdefb;
}

.records-text {
    font-weight: 600;
    color: #1976d2;
    font-size: 0.9rem;
}

.preview-content {
    color: #666;
    line-height: 1.6;
}

/* Section Details Dialog Styles */
.section-details-overlay {
    z-index: 10001 !important;
}

.section-details-overlay .p-dialog {
    z-index: 10001 !important;
}

.section-details-overlay .p-dialog-mask {
    z-index: 10000 !important;
    background-color: rgba(0, 0, 0, 0.8) !important;
}

.section-details-overlay .p-dialog-content {
    z-index: 10002 !important;
}

.section-details-overlay .p-dialog-header {
    z-index: 10002 !important;
}

.section-attendance-details {
    max-height: 75vh;
    overflow-y: auto;
}

.section-info-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    color: white;
}

.section-basic-info {
    flex: 1;
}

.section-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    color: white;
}

.teacher-name {
    font-size: 1.1rem;
    margin: 0 0 1.5rem 0;
    opacity: 0.9;
    font-style: italic;
}

.attendance-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.summary-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.summary-label {
    font-size: 0.8rem;
    opacity: 0.8;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    font-weight: 500;
}

.summary-value {
    font-size: 1.5rem;
    font-weight: 700;
}

.summary-value.present {
    color: #4caf50;
}

.summary-value.absent {
    color: #f44336;
}

.report-period-info {
    text-align: right;
}

.period-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    padding: 0.5rem 1rem;
    border-radius: 20px;
    margin-bottom: 1rem;
    backdrop-filter: blur(10px);
}

.school-info {
    opacity: 0.9;
}

.school-info strong {
    display: block;
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
}

.school-info p {
    margin: 0;
    font-size: 0.9rem;
    opacity: 0.8;
}

/* Attendance Grid Styles */
.attendance-grid-container {
    margin: 2rem 0;
}

.grid-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.grid-header h4 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.legend {
    display: flex;
    gap: 1rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.85rem;
}

.legend-symbol {
    font-weight: bold;
    font-size: 1rem;
}

.present-symbol {
    color: #4caf50;
}

.absent-symbol {
    color: #f44336;
}

.late-symbol {
    color: #ff9800;
}

.attendance-table-wrapper {
    overflow-x: auto;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    background: white;
}

.attendance-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.attendance-table th,
.attendance-table td {
    border: 1px solid #e9ecef;
    text-align: center;
    vertical-align: middle;
}

.student-name-header {
    background: #667eea;
    color: white;
    padding: 0.75rem;
    font-weight: 600;
    text-align: left;
    min-width: 200px;
    position: sticky;
    left: 0;
    z-index: 10;
}

.day-header {
    background: #f8f9fa;
    padding: 0.5rem 0.25rem;
    min-width: 35px;
    font-weight: 600;
}

.day-info {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.day-number {
    font-size: 0.9rem;
    font-weight: 700;
}

.day-name {
    font-size: 0.7rem;
    opacity: 0.7;
}

.summary-header {
    background: #e3f2fd;
    color: #1976d2;
    padding: 0.75rem 0.5rem;
    font-weight: 600;
    min-width: 80px;
}

.student-name-cell {
    background: white;
    padding: 0.75rem;
    text-align: left;
    position: sticky;
    left: 0;
    z-index: 5;
    border-right: 2px solid #667eea;
}

.student-info {
    display: flex;
    flex-direction: column;
}

.student-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 0.25rem;
}

.student-lrn {
    font-size: 0.75rem;
    color: #666;
    opacity: 0.8;
}

.attendance-cell {
    padding: 0.5rem 0.25rem;
    background: white;
}

.attendance-mark {
    font-weight: bold;
    font-size: 1rem;
    padding: 0.25rem;
    border-radius: 4px;
    display: inline-block;
    min-width: 20px;
}

.mark-present {
    color: #4caf50;
    background: #e8f5e8;
}

.mark-absent {
    color: #f44336;
    background: #ffeaea;
}

.mark-late {
    color: #ff9800;
    background: #fff3e0;
}

.mark-none {
    color: #999;
}

.summary-cell {
    padding: 0.75rem 0.5rem;
    font-weight: 600;
}

.present-count {
    background: #e8f5e8;
    color: #2e7d32;
}

.absent-count {
    background: #ffeaea;
    color: #c62828;
}

.rate-cell {
    background: #f8f9fa;
}

.rate-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Monthly Statistics */
.monthly-statistics {
    margin-top: 2rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 12px;
    border: 1px solid #e9ecef;
}

.monthly-statistics h4 {
    margin: 0 0 1.5rem 0;
    color: #333;
    font-weight: 600;
    text-align: center;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid #e9ecef;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: #667eea;
    color: white;
}

.stat-icon.present {
    background: #4caf50;
}

.stat-icon.absent {
    background: #f44336;
}

.stat-icon.rate {
    background: #2196f3;
}

.stat-content {
    display: flex;
    flex-direction: column;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.9rem;
    color: #666;
    text-transform: uppercase;
    font-weight: 500;
}

/* Month Navigation Styles */
.month-navigation {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    padding: 1rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 8px;
    margin-bottom: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.month-selector {
    text-align: center;
    color: white;
    min-width: 200px;
}

.month-selector h4 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
}

.teacher-info {
    margin: 0.25rem 0 0 0;
    font-size: 0.9rem;
    opacity: 0.9;
    font-style: italic;
}

.month-navigation .p-button {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    transition: all 0.3s ease;
}

.month-navigation .p-button:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-1px);
}

.month-navigation .p-button:disabled {
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.5);
    cursor: not-allowed;
}

/* Gender Section Styles */
.gender-header-row {
    background: #f8f9fa;
    border-top: 2px solid #667eea;
}

.gender-header {
    background: #667eea;
    color: white;
    padding: 0.75rem;
    font-weight: 700;
    text-align: left;
}

.gender-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.gender-label {
    font-size: 1rem;
    font-weight: 700;
}

.gender-total {
    font-size: 0.9rem;
    opacity: 0.9;
    font-style: italic;
}

/* Gender-specific total rows */
.gender-total-row {
    background: #f8f9fa;
    border-top: 2px solid #6c757d;
    font-weight: 700;
}

.male-total {
    background: #e3f2fd;
    border-top: 2px solid #2196f3;
}

.female-total {
    background: #fce4ec;
    border-top: 2px solid #e91e63;
}

.gender-total-label {
    background: #6c757d;
    color: white;
    padding: 0.75rem;
    font-weight: 700;
    text-align: left;
    position: sticky;
    left: 0;
    z-index: 5;
}

.male-total .gender-total-label {
    background: #2196f3;
}

.female-total .gender-total-label {
    background: #e91e63;
}

.gender-total-cell {
    padding: 0.75rem 0.5rem;
    text-align: center;
    font-weight: 700;
    border: 1px solid #dee2e6;
}

.male-total .gender-total-cell {
    background: #e3f2fd;
    color: #1976d2;
    border-color: #2196f3;
}

.female-total .gender-total-cell {
    background: #fce4ec;
    color: #c2185b;
    border-color: #e91e63;
}

.combined-total-row {
    background: #e3f2fd;
    border-top: 3px solid #2196f3;
    font-weight: 700;
}

.total-label-cell {
    background: #2196f3;
    color: white;
    padding: 0.75rem;
    font-weight: 700;
    text-align: left;
    position: sticky;
    left: 0;
    z-index: 5;
}

.total-cell {
    background: #e3f2fd;
    color: #1976d2;
    padding: 0.75rem 0.5rem;
    text-align: center;
    font-weight: 700;
    border: 1px solid #2196f3;
}

/* Responsive design */
@media (max-width: 768px) {
    .content-container {
        padding: 1rem;
    }

    .header-content {
        flex-direction: column;
        text-align: center;
    }

    .filter-grid {
        grid-template-columns: 1fr;
    }

    .report-header {
        flex-direction: column;
        gap: 1rem;
    }

    .report-details {
        flex-direction: column;
        gap: 0.5rem;
    }

    .section-info-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .attendance-summary {
        grid-template-columns: repeat(2, 1fr);
    }

    .grid-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .legend {
        justify-content: center;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .attendance-table {
        font-size: 0.75rem;
    }

    .student-name-header,
    .student-name-cell {
        min-width: 150px;
    }
}
</style>
