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
                        <p class="debug-info" style="color: white; font-size: 0.9rem; margin-top: 0.5rem">Debug: Notification Count = {{ newSubmissionsCount }}, Reports = {{ submittedReports.length }}</p>
                    </div>
                    <div class="header-actions">
                        <Button label="Generate Report" icon="pi pi-plus" class="p-button-success" @click="showGenerateDialog = true" />
                        <Button label="Export All" icon="pi pi-download" class="p-button-outlined" @click="exportAllReports" />
                        <Button label="Test Notification" icon="pi pi-bell" class="p-button-warning p-button-outlined" @click="receiveNewSubmission" />
                        <Button label="Reload Reports" icon="pi pi-refresh" class="p-button-info p-button-outlined" @click="loadSubmittedReports" />
                    </div>
                </div>
            </div>

            <!-- Grade Level Statistics Section -->
            <div class="grade-statistics-section">
                <div class="statistics-header">
                    <h2 class="statistics-title">
                        <span class="emoji-icon">📊</span>
                        Grade Level Overview
                    </h2>
                    <p class="statistics-subtitle">Section counts and student distribution by grade level</p>
                </div>

                <!-- Month Navigation -->
                <div class="month-navigation-header">
                    <Button icon="pi pi-chevron-left" class="p-button-text p-button-rounded month-nav-btn" @click="previousOverviewMonth()" :disabled="!canGoPreviousOverviewMonth()" v-tooltip.top="'Previous Month'" />
                    <div class="current-month-display">
                        <h3 class="month-title">{{ getCurrentOverviewMonthDisplay() }}</h3>
                        <p class="month-subtitle">{{ getActiveSectionsCount() }} sections active</p>
                    </div>
                    <Button icon="pi pi-chevron-right" class="p-button-text p-button-rounded month-nav-btn" @click="nextOverviewMonth()" :disabled="!canGoNextOverviewMonth()" v-tooltip.top="'Next Month'" />
                </div>

                <div v-if="loadingRealData" class="loading-container">
                    <ProgressSpinner />
                    <p>Loading curriculum data...</p>
                </div>

                <div v-else-if="gradeStatistics.length === 0" class="empty-state">
                    <i class="pi pi-inbox empty-icon"></i>
                    <h3>No Grades Found</h3>
                    <p>No grade levels have been configured in the curriculum yet.</p>
                </div>

                <div v-else class="grade-stats-grid">
                    <div v-for="gradeStats in gradeStatistics" :key="gradeStats.grade" class="grade-stat-card">
                        <div class="grade-header">
                            <div class="grade-info">
                                <h3 class="grade-name">{{ gradeStats.grade }}</h3>
                                <span class="grade-level">{{ gradeStats.level }}</span>
                            </div>
                            <div class="grade-icon">
                                <span class="grade-emoji">{{ gradeStats.emoji }}</span>
                            </div>
                        </div>

                        <div class="grade-metrics">
                            <div class="metric-item sections">
                                <div class="metric-value">{{ gradeStats.sectionCount }}</div>
                                <div class="metric-label">Sections</div>
                            </div>
                            <div class="metric-item students">
                                <div class="metric-value">{{ gradeStats.totalStudents }}</div>
                                <div class="metric-label">Students</div>
                            </div>
                            <div class="metric-item teachers">
                                <div class="metric-value">{{ gradeStats.teacherCount }}</div>
                                <div class="metric-label">Teachers</div>
                            </div>
                        </div>

                        <div class="grade-attendance">
                            <div class="attendance-bar">
                                <div class="attendance-fill" :style="{ width: gradeStats.attendanceRate + '%' }" :class="getAttendanceBarClass(gradeStats.attendanceRate)"></div>
                            </div>
                            <div class="attendance-text">
                                <span class="attendance-rate">{{ gradeStats.attendanceRate }}%</span>
                                <span class="attendance-label">Attendance Rate</span>
                            </div>
                        </div>

                        <div class="section-list">
                            <div class="section-list-header">
                                <span>Sections:</span>
                            </div>
                            <div class="sections-cards-grid">
                                <div
                                    v-for="section in gradeStats.sections"
                                    :key="section.id"
                                    class="section-card"
                                    :class="section.statusClass"
                                    @click="viewSectionDetails(section)"
                                    :title="`${section.name} - ${section.teacher} (${section.attendanceRate}%)`"
                                >
                                    <div class="section-card-header" :class="section.statusClass">
                                        <div class="section-card-title">{{ section.name }}</div>
                                        <div class="section-card-subtitle">{{ section.teacher }}</div>
                                        <div class="section-card-icon">
                                            <span class="attendance-badge">{{ section.attendanceRate }}%</span>
                                        </div>
                                    </div>
                                    <div class="section-card-body">
                                        <div class="section-stats">
                                            <div class="stat-item">
                                                <i class="pi pi-users"></i>
                                                <span>{{ section.studentCount }} Students</span>
                                            </div>
                                            <div class="stat-item">
                                                <i class="pi pi-check-circle"></i>
                                                <span>{{ section.presentCount }} Present</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="section-card-footer">
                                        <Button icon="pi pi-eye" class="p-button-text p-button-sm" @click.stop="viewSectionDetails(section)" v-tooltip.top="'View Details'" />
                                        <Button icon="pi pi-file-excel" class="p-button-text p-button-sm" @click.stop="downloadSectionReport(section)" v-tooltip.top="'Download Report'" />
                                        <Button icon="pi pi-ellipsis-v" class="p-button-text p-button-sm" v-tooltip.top="'More Options'" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grade-actions">
                            <Button label="View All Sections" icon="pi pi-eye" class="p-button-outlined p-button-sm" @click="viewGradeSections(gradeStats.grade)" />
                            <Button label="Generate Report" icon="pi pi-file-excel" class="p-button-success p-button-outlined p-button-sm" @click="generateGradeReport(gradeStats.grade)" />
                        </div>
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
                        <Select v-model="selectedStatus" :options="monthOptions" optionLabel="name" optionValue="value" placeholder="All Months" class="w-full" />
                    </div>
                    <div class="filter-item">
                        <label class="filter-label">Search</label>
                        <InputText v-model="searchQuery" placeholder="Search reports..." class="w-full" />
                    </div>
                </div>
            </div>

            <!-- Reports Table -->
            <div class="reports-table-container">
                <div v-if="loading" class="loading-container">
                    <ProgressSpinner />
                    <p>Loading reports...</p>
                </div>

                <div v-else>
                    <!-- Empty State - Only show when there are no reports at all -->
                    <div v-if="reports.length === 0" class="empty-state">
                        <i class="pi pi-inbox empty-icon"></i>
                        <h3>No Submitted Reports</h3>
                        <p>Submitted reports from teachers will appear here</p>
                    </div>

                    <!-- No Results State - When there are reports but none match filters -->
                    <div v-else-if="filteredReports.length === 0" class="empty-state">
                        <i class="pi pi-file-excel empty-icon"></i>
                        <h3>No Reports Found</h3>
                        <p>No reports match your current filters. Try adjusting your search criteria.</p>
                    </div>

                    <!-- Reports List - Only show when there are reports -->
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
                                    <span v-if="report.submitted" class="new-submission-badge" v-tooltip.top="'New Submission'"></span>
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

            <!-- Submitted Reports Section -->
            <div class="submitted-reports-section" v-if="submittedReports.length > 0">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="emoji-icon">📋</span>
                        Submitted SF2 Reports
                    </h2>
                    <p class="section-subtitle">Reports submitted by teachers awaiting review</p>
                </div>

                <div class="submitted-reports-grid">
                    <div v-for="report in submittedReports" :key="report.id" class="submitted-report-card">
                        <div class="report-card-header">
                            <div class="report-info">
                                <h4 class="report-section-name">{{ report.section_name }}</h4>
                                <p class="report-teacher">{{ report.teacher_name }}</p>
                                <p class="report-month">{{ report.month_name }}</p>
                            </div>
                            <div class="report-status-badge">
                                <span :class="['status-badge', getStatusClass(report.status)]">
                                    {{ report.status.toUpperCase() }}
                                </span>
                            </div>
                        </div>

                        <div class="report-card-details">
                            <div class="detail-item">
                                <i class="pi pi-calendar"></i>
                                <span>Submitted: {{ formatDate(report.submitted_at) }}</span>
                            </div>
                            <div class="detail-item">
                                <i class="pi pi-tag"></i>
                                <span>Grade: {{ report.grade_level }}</span>
                            </div>
                            <div class="detail-item">
                                <i class="pi pi-file"></i>
                                <span>Type: {{ report.report_type }}</span>
                            </div>
                        </div>

                        <div class="report-card-actions">
                            <Button icon="pi pi-eye" class="p-button-rounded p-button-outlined p-button-sm" v-tooltip.top="'View Report'" />
                            <Button icon="pi pi-download" class="p-button-rounded p-button-success p-button-outlined p-button-sm" @click="downloadSubmittedReport(report)" v-tooltip.top="'Download Report'" />
                            <Button icon="pi pi-check" class="p-button-rounded p-button-success p-button-outlined p-button-sm" @click="approveReport(report)" v-tooltip.top="'Approve Report'" v-if="report.status === 'submitted'" />
                            <Button icon="pi pi-times" class="p-button-rounded p-button-danger p-button-outlined p-button-sm" @click="rejectReport(report)" v-tooltip.top="'Reject Report'" v-if="report.status === 'submitted'" />
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

                <!-- Official SF2 Header -->
                <div class="sf2-official-header">
                    <div class="header-top">
                        <div class="deped-logo">
                            <img src="/demo/images/deped-logo.png" alt="DepEd Logo" class="logo-img" />
                        </div>
                        <div class="header-center">
                            <div class="form-title">
                                <span class="form-number">(The replaced Form 1, Form 2 & SF Form 4 - Absenteeism and Dropout Profile)</span>
                                <h2>School Form 2 (SF2) - Daily Attendance Report of Learners</h2>
                            </div>
                        </div>
                        <div class="deped-text">
                            <div class="deped-brand"><span class="dep">Dep</span><span class="ed">ED</span></div>
                            <p>DEPARTMENT OF EDUCATION</p>
                        </div>
                    </div>

                    <div class="school-details-form">
                        <div class="form-row">
                            <div class="form-field">
                                <label>School ID:</label>
                                <input type="text" class="form-input" value="123456" readonly />
                            </div>
                            <div class="form-field">
                                <label>School Year:</label>
                                <input type="text" class="form-input" value="2024-2025" readonly />
                            </div>
                            <div class="form-field">
                                <label>Report for the Month of:</label>
                                <input type="text" class="form-input" value="September 2025" readonly />
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-field wide">
                                <label>Name of School:</label>
                                <input type="text" class="form-input" value="Kagawasan Elementary School" readonly />
                            </div>
                            <div class="form-field">
                                <label>Grade Level:</label>
                                <input type="text" class="form-input" value="Kindergarten" readonly />
                            </div>
                            <div class="form-field">
                                <label>Section:</label>
                                <input type="text" class="form-input" :value="selectedSectionDetails.name" readonly />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Month Navigation -->
                <div class="month-navigation">
                    <Button icon="pi pi-chevron-left" class="p-button-text p-button-sm" @click="previousMonth()" :disabled="!canGoPreviousMonth()" />
                    <div class="month-selector">
                        <h4>{{ getCurrentMonthDisplay() }}</h4>
                        <p class="teacher-info">{{ getCurrentTeacher() }}</p>
                    </div>
                    <Button icon="pi pi-chevron-right" class="p-button-text p-button-sm" @click="nextMonth()" :disabled="!canGoNextMonth()" />
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
                                    <td class="gender-header" colspan="26">
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
                                            <span v-if="student.status && student.status !== 'active'" :class="'status-badge status-' + student.status.replace('_', '-')" :title="student.statusDate ? 'Status changed on: ' + student.statusDate : ''">
                                                {{ student.status.replace('_', ' ') }}
                                                <small v-if="student.statusDate"> ({{ formatStatusDate(student.statusDate) }})</small>
                                            </span>
                                            <button v-if="!student.status || student.status === 'active'" class="status-change-btn" @click="showStatusChangeDialog(student)" title="Change student status">⚙️</button>
                                            <span class="student-lrn">LRN: {{ student.lrn }}</span>
                                        </div>
                                    </td>
                                    <td v-for="day in attendanceDays" :key="day.date" class="attendance-cell">
                                        <span :class="['attendance-mark', getAttendanceClass(student.attendance?.[day.date])]">
                                            {{ getAttendanceMark(student.attendance?.[day.date]) }}
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
                                    <td class="gender-header" colspan="26">
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
                                            <span v-if="student.status && student.status !== 'active'" :class="'status-badge status-' + student.status.replace('_', '-')" :title="student.statusDate ? 'Status changed on: ' + student.statusDate : ''">
                                                {{ student.status.replace('_', ' ') }}
                                                <small v-if="student.statusDate"> ({{ formatStatusDate(student.statusDate) }})</small>
                                            </span>
                                            <button v-if="!student.status || student.status === 'active'" class="status-change-btn" @click="showStatusChangeDialog(student)" title="Change student status">⚙️</button>
                                            <span class="student-lrn">LRN: {{ student.lrn }}</span>
                                        </div>
                                    </td>
                                    <td v-for="day in attendanceDays" :key="day.date" class="attendance-cell">
                                        <span :class="['attendance-mark', getAttendanceClass(student.attendance?.[day.date])]">
                                            {{ getAttendanceMark(student.attendance?.[day.date]) }}
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

                                <!-- Line Numbers Row -->
                                <tr class="line-numbers-row">
                                    <td class="line-number-label">Line No.</td>
                                    <td v-for="(day, index) in attendanceDays" :key="day.date" class="line-number-cell">{{ index + 2 }}</td>
                                    <td class="line-number-cell">{{ attendanceDays.length + 2 }}</td>
                                    <td class="line-number-cell">{{ attendanceDays.length + 3 }}</td>
                                    <td class="line-number-cell">{{ attendanceDays.length + 4 }}</td>
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

            <!-- SF2 Template Preview -->
            <div class="sf2-template-preview">
                <div class="sf2-header">
                    <h3>SCHOOL FORM 2 (SF2) - DAILY ATTENDANCE REPORT OF LEARNERS</h3>
                </div>

                <div class="sf2-info-section">
                    <div class="sf2-school-info">
                        <div class="info-row">
                            <span class="label">School:</span>
                            <span class="value">Kagawasan Elementary School</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Section:</span>
                            <span class="value">{{ selectedSectionDetails.name }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Teacher:</span>
                            <span class="value">{{ getCurrentTeacher() }}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Month/Year:</span>
                            <span class="value">{{ getCurrentMonthDisplay() }}</span>
                        </div>
                    </div>

                    <div class="sf2-guidelines">
                        <h4>GUIDELINES:</h4>
                        <ol>
                            <li>This attendance shall be accomplished daily. Refer to the codes for checking learners' attendance.</li>
                            <li>Data shall be written in the columns after Learner's Name.</li>
                            <li>To compute the following:</li>
                        </ol>
                        <div class="formulas">
                            <div class="formula-item">
                                <span class="formula-label">a. Percentage of Enrolment =</span>
                                <div class="formula-box">
                                    <div>Registered Learners as of end of the month</div>
                                    <div class="divider">Enrolment as of 1st Friday of the School Year</div>
                                    <span class="multiply">x 100</span>
                                </div>
                            </div>
                            <div class="formula-item">
                                <span class="formula-label">b. Average Daily Attendance =</span>
                                <div class="formula-box">
                                    <div>Total Daily Attendance</div>
                                    <div class="divider">Number of School Days in reporting month</div>
                                </div>
                            </div>
                            <div class="formula-item">
                                <span class="formula-label">c. Percentage of Attendance for the month =</span>
                                <div class="formula-box">
                                    <div>Average Daily Attendance</div>
                                    <div class="divider">Registered Learners as of end of the month</div>
                                    <span class="multiply">x 100</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sf2-codes">
                        <h4>CODES FOR CHECKING ATTENDANCE</h4>
                        <div class="codes-grid">
                            <div class="code-section">
                                <h5>A. REASONS/CAUSES (Specify the appropriate code)</h5>
                                <div class="code-list">
                                    <div class="code-item">a. Domestic-Related Factors</div>
                                    <div class="code-item">a1. Had to take care of siblings</div>
                                    <div class="code-item">a2. Early marriage/pregnancy</div>
                                    <div class="code-item">a3. Parents' attitude toward schooling</div>
                                    <div class="code-item">a4. Peer pressure</div>
                                    <div class="code-item">b. Individual-Related Factors</div>
                                    <div class="code-item">b1. Illness</div>
                                    <div class="code-item">b2. Overage</div>
                                    <div class="code-item">b3. Death</div>
                                    <div class="code-item">b4. Drug Abuse</div>
                                    <div class="code-item">b5. Poor academic performance</div>
                                    <div class="code-item">b6. Lack of interest/Disinterest</div>
                                    <div class="code-item">b7. Hunger/Malnutrition</div>
                                </div>
                            </div>
                            <div class="code-section">
                                <h5>c. School-Related Factors</h5>
                                <div class="code-list">
                                    <div class="code-item">c1. Teacher Factor</div>
                                    <div class="code-item">c2. Physical condition of classroom</div>
                                    <div class="code-item">c3. Peer influence</div>
                                    <div class="code-item">c4. School environment</div>
                                    <div class="code-item">d. Geographic/Environmental</div>
                                    <div class="code-item">d1. Distance between home and school</div>
                                    <div class="code-item">d2. Armed conflict area. Tribal wars & clan feuds</div>
                                    <div class="code-item">d3. Calamities/Disasters</div>
                                    <div class="code-item">e. Economic/Financial</div>
                                    <div class="code-item">e1. Child labor - work</div>
                                    <div class="code-item">f. Others (Specify)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sf2-summary-section">
                        <div class="summary-box">
                            <div class="summary-header">
                                <div class="header-box">Month: _____</div>
                                <div class="header-box">No. of Days of Classes: _____</div>
                                <div class="header-box summary-box-header">Summary</div>
                            </div>

                            <table class="sf2-summary-table">
                                <thead>
                                    <tr>
                                        <th class="summary-description"></th>
                                        <th class="summary-m">M</th>
                                        <th class="summary-f">F</th>
                                        <th class="summary-total">TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="summary-label">* Enrolment as of (1st Friday of June)</td>
                                        <td class="summary-value">{{ getMaleStudentsCount(selectedSectionDetails.name) }}</td>
                                        <td class="summary-value">{{ getFemaleStudentsCount(selectedSectionDetails.name) }}</td>
                                        <td class="summary-value">{{ selectedSectionDetails.totalStudents }}</td>
                                    </tr>
                                    <tr>
                                        <td class="summary-label">Late Enrolment during the month<br /><small>(beyond cut-off)</small></td>
                                        <td class="summary-value">0</td>
                                        <td class="summary-value">0</td>
                                        <td class="summary-value">0</td>
                                    </tr>
                                    <tr>
                                        <td class="summary-label">Registered Learners as of <em>end of the month</em></td>
                                        <td class="summary-value">{{ getMaleStudentsCount(selectedSectionDetails.name) }}</td>
                                        <td class="summary-value">{{ getFemaleStudentsCount(selectedSectionDetails.name) }}</td>
                                        <td class="summary-value">{{ selectedSectionDetails.totalStudents }}</td>
                                    </tr>
                                    <tr>
                                        <td class="summary-label">Percentage of Enrolment as of <em>end of the month</em></td>
                                        <td class="summary-value">100%</td>
                                        <td class="summary-value">100%</td>
                                        <td class="summary-value">100%</td>
                                    </tr>
                                    <tr>
                                        <td class="summary-label">Average Daily Attendance</td>
                                        <td class="summary-value">{{ getMaleAttendanceRate(selectedSectionDetails.name) }}%</td>
                                        <td class="summary-value">{{ getFemaleAttendanceRate(selectedSectionDetails.name) }}%</td>
                                        <td class="summary-value">{{ selectedSectionDetails.attendanceRate }}%</td>
                                    </tr>
                                    <tr>
                                        <td class="summary-label">Percentage of Attendance for the month</td>
                                        <td class="summary-value">{{ getMaleAttendanceRate(selectedSectionDetails.name) }}%</td>
                                        <td class="summary-value">{{ getFemaleAttendanceRate(selectedSectionDetails.name) }}%</td>
                                        <td class="summary-value">{{ selectedSectionDetails.attendanceRate }}%</td>
                                    </tr>
                                    <tr>
                                        <td class="summary-label">Number of students absent for 5 consecutive days:</td>
                                        <td class="summary-value">0</td>
                                        <td class="summary-value">0</td>
                                        <td class="summary-value">0</td>
                                    </tr>
                                    <tr>
                                        <td class="summary-label">Drop out</td>
                                        <td class="summary-value">{{ getMaleDroppedOutCount(selectedSectionDetails.name) }}</td>
                                        <td class="summary-value">{{ getFemaleDroppedOutCount(selectedSectionDetails.name) }}</td>
                                        <td class="summary-value">{{ getDroppedOutCount(selectedSectionDetails.name) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="summary-label">Transferred out</td>
                                        <td class="summary-value">{{ getMaleTransferredOutCount(selectedSectionDetails.name) }}</td>
                                        <td class="summary-value">{{ getFemaleTransferredOutCount(selectedSectionDetails.name) }}</td>
                                        <td class="summary-value">{{ getTransferredOutCount(selectedSectionDetails.name) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="summary-label">Transferred in</td>
                                        <td class="summary-value">{{ getMaleTransferredInCount(selectedSectionDetails.name) }}</td>
                                        <td class="summary-value">{{ getFemaleTransferredInCount(selectedSectionDetails.name) }}</td>
                                        <td class="summary-value">{{ getTransferredInCount(selectedSectionDetails.name) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="certification">
                                <p>I certify that this is a true and correct report.</p>
                                <div class="signature-section">
                                    <div class="signature-box">
                                        <div class="signature-line"></div>
                                        <span>(Signature of Teacher over Printed Name)</span>
                                    </div>
                                    <div class="signature-box">
                                        <span>Attested by:</span>
                                        <div class="signature-line"></div>
                                        <span>(Signature of School Head over Printed Name)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sf2-footer">
                    <span>School Form 2 - Page ___ of ___</span>
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

        <!-- Status Change Dialog -->
        <Dialog v-model:visible="statusChangeDialog" modal header="Change Student Status" :style="{ width: '450px' }">
            <div class="status-change-form" v-if="selectedStudent">
                <div class="student-details">
                    <h4>{{ selectedStudent.firstName }} {{ selectedStudent.lastName }}</h4>
                    <p>LRN: {{ selectedStudent.lrn }}</p>
                    <p>Current Status: {{ selectedStudent.status || 'Active' }}</p>
                </div>

                <div class="form-group">
                    <label for="newStatus">New Status:</label>
                    <Dropdown v-model="newStatus" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Select new status" class="w-full" />
                </div>

                <div class="form-group" v-if="newStatus">
                    <label for="statusDate">Effective Date:</label>
                    <Calendar v-model="statusDate" dateFormat="yy-mm-dd" placeholder="Select date" class="w-full" />
                </div>

                <div class="form-group" v-if="newStatus">
                    <label for="statusReason">Reason (Optional):</label>
                    <Textarea v-model="statusReason" rows="3" placeholder="Enter reason for status change..." class="w-full" />
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="closeStatusDialog" class="p-button-text" />
                <Button label="Update Status" icon="pi pi-check" @click="updateStudentStatus" :disabled="!newStatus || !statusDate" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { CurriculumService } from '@/router/service/CurriculumService';
import 'jspdf-autotable';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, onUnmounted, ref } from 'vue';

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

// Real curriculum data
const realGrades = ref([]);
const realSections = ref([]);
const loadingRealData = ref(false);

// Status change dialog variables
const statusChangeDialog = ref(false);
const selectedStudent = ref(null);
const newStatus = ref('');
const statusDate = ref(null);
const statusReason = ref('');

const statusOptions = [
    { label: 'Drop Out', value: 'dropped_out' },
    { label: 'Transfer Out', value: 'transferred_out' },
    { label: 'Transfer In', value: 'transferred_in' }
];
const searchQuery = ref('');
const selectedGradeType = ref(null);
const selectedDateRange = ref(null);
const selectedStatus = ref(null);

// Notification system
const newSubmissionsCount = ref(0);
const submittedReports = ref([]);
const pollingInterval = ref(null);
const lastCheckedTime = ref(new Date());

// Current month and year for navigation
const currentMonth = ref(8); // September (0-indexed)
const currentYear = ref(2025);

// Overview month navigation (separate from section details)
const currentOverviewMonth = ref(8); // September (0-indexed)
const currentOverviewYear = ref(2025);

// Available months for navigation
const availableMonths = ref([
    { month: 7, year: 2025, display: 'August 2025' },
    { month: 8, year: 2025, display: 'September 2025' },
    { month: 9, year: 2025, display: 'October 2025' }
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
                        '2025-08-01': 'present',
                        '2025-08-02': 'present',
                        '2025-08-05': 'present',
                        '2025-08-06': 'present',
                        '2025-08-07': 'present',
                        '2025-08-08': 'present',
                        '2025-08-09': 'present',
                        '2025-08-12': 'present',
                        '2025-08-13': 'absent',
                        '2025-08-14': 'present',
                        '2025-08-15': 'present',
                        '2025-08-16': 'present',
                        '2025-08-19': 'present',
                        '2025-08-20': 'present',
                        '2025-08-21': 'present',
                        '2025-08-22': 'present',
                        '2025-08-23': 'present',
                        '2025-08-26': 'present',
                        '2025-08-27': 'present',
                        '2025-08-28': 'present',
                        '2025-08-29': 'present',
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
                        '2025-08-01': 'present',
                        '2025-08-02': 'present',
                        '2025-08-05': 'present',
                        '2025-08-06': 'present',
                        '2025-08-07': 'present',
                        '2025-08-08': 'present',
                        '2025-08-09': 'present',
                        '2025-08-12': 'present',
                        '2025-08-13': 'present',
                        '2025-08-14': 'present',
                        '2025-08-15': 'present',
                        '2025-08-16': 'present',
                        '2025-08-19': 'present',
                        '2025-08-20': 'present',
                        '2025-08-21': 'present',
                        '2025-08-22': 'present',
                        '2025-08-23': 'present',
                        '2025-08-26': 'present',
                        '2025-08-27': 'present',
                        '2025-08-28': 'present',
                        '2025-08-29': 'present',
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
                        '2025-08-01': 'present',
                        '2025-08-02': 'present',
                        '2025-08-05': 'present',
                        '2025-08-06': 'present',
                        '2025-08-07': 'present',
                        '2025-08-08': 'absent',
                        '2025-08-09': 'present',
                        '2025-08-12': 'present',
                        '2025-08-13': 'present',
                        '2025-08-14': 'present',
                        '2025-08-15': 'present',
                        '2025-08-16': 'present',
                        '2025-08-19': 'present',
                        '2025-08-20': 'present',
                        '2025-08-21': 'present',
                        '2025-08-22': 'present',
                        '2025-08-23': 'present',
                        '2025-08-26': 'present',
                        '2025-08-27': 'present',
                        '2025-08-28': 'present',
                        '2025-08-29': 'present',
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
                        '2025-08-01': 'present',
                        '2025-08-02': 'present',
                        '2025-08-05': 'present',
                        '2025-08-06': 'present',
                        '2025-08-07': 'present',
                        '2025-08-08': 'present',
                        '2025-08-09': 'present',
                        '2025-08-12': 'present',
                        '2025-08-13': 'present',
                        '2025-08-14': 'present',
                        '2025-08-15': 'absent',
                        '2025-08-16': 'present',
                        '2025-08-19': 'present',
                        '2025-08-20': 'present',
                        '2025-08-21': 'present',
                        '2025-08-22': 'present',
                        '2025-08-23': 'present',
                        '2025-08-26': 'present',
                        '2025-08-27': 'present',
                        '2025-08-28': 'present',
                        '2025-08-29': 'present',
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
                        '2025-10-01': 'present',
                        '2025-10-02': 'present',
                        '2025-10-03': 'present',
                        '2025-10-04': 'present',
                        '2025-10-07': 'present',
                        '2025-10-08': 'present',
                        '2025-10-09': 'present',
                        '2025-10-10': 'present',
                        '2025-10-11': 'present',
                        '2025-10-14': 'present',
                        '2025-10-15': 'present',
                        '2025-10-16': 'present',
                        '2025-10-17': 'present',
                        '2025-10-18': 'present',
                        '2025-10-21': 'present',
                        '2025-10-22': 'present',
                        '2025-10-23': 'present',
                        '2025-10-24': 'present',
                        '2025-10-25': 'present',
                        '2025-10-28': 'present',
                        '2025-10-29': 'present',
                        '2025-10-30': 'present',
                        '2025-10-31': 'present'
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

// Grade 5 sections data
const grade5Sections = ref([
    {
        id: 1,
        name: 'Grade 5-A',
        teacher: 'Ms. Patricia Wilson',
        studentCount: 30,
        presentCount: 29,
        absentCount: 1,
        attendanceRate: 97,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 2,
        name: 'Grade 5-B',
        teacher: 'Mr. Robert Chen',
        studentCount: 28,
        presentCount: 26,
        absentCount: 2,
        attendanceRate: 93,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 3,
        name: 'Grade 5-C',
        teacher: 'Ms. Jennifer Lopez',
        studentCount: 31,
        presentCount: 30,
        absentCount: 1,
        attendanceRate: 97,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    }
]);

// Grade 6 sections data
const grade6Sections = ref([
    {
        id: 1,
        name: 'Grade 6-A',
        teacher: 'Mr. Michael Rodriguez',
        studentCount: 29,
        presentCount: 28,
        absentCount: 1,
        attendanceRate: 97,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 2,
        name: 'Grade 6-B',
        teacher: 'Ms. Angela Davis',
        studentCount: 32,
        presentCount: 30,
        absentCount: 2,
        attendanceRate: 94,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 3,
        name: 'Grade 6-C',
        teacher: 'Mr. Thomas Anderson',
        studentCount: 30,
        presentCount: 29,
        absentCount: 1,
        attendanceRate: 97,
        status: 'EXCELLENT',
        statusClass: 'status-excellent'
    },
    {
        id: 4,
        name: 'Grade 6-D',
        teacher: 'Ms. Lisa Martinez',
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
            status: 'active', // active, dropped_out, transferred_out, transferred_in
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
            lrn: '123456789014',
            status: 'dropped_out', // Student dropped out
            statusDate: '2025-09-15', // Date when student dropped out
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
                '2025-09-12': 'present'
                // Student dropped out on Sept 15, so no attendance after this date
            },
            totalPresent: 10,
            totalAbsent: 0,
            attendanceRate: 100
        },
        {
            id: 3,
            firstName: 'Carlos',
            middleName: 'Antonio',
            lastName: 'Santos',
            gender: 'Male',
            lrn: '123456789015',
            status: 'transferred_out', // Student transferred out
            statusDate: '2025-09-20', // Date when student transferred out
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
                '2025-09-19': 'present'
                // Student transferred out on Sept 20, so no attendance after this date
            },
            totalPresent: 13,
            totalAbsent: 2,
            attendanceRate: 87
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
            id: 4,
            firstName: 'Maria',
            middleName: 'Santos',
            lastName: 'Cruz',
            gender: 'Female',
            lrn: '123456789016',
            status: 'transferred_in', // Student transferred in
            statusDate: '2025-09-10', // Date when student transferred in
            attendance: {
                // Student transferred in on Sept 10, so no attendance before this date
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
            id: 5,
            firstName: 'Ana',
            middleName: 'Marie',
            lastName: 'Garcia',
            gender: 'Female',
            lrn: '123456789017',
            status: 'active',
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
                '2025-09-29': 'present',
                '2025-09-30': 'present'
            },
            totalPresent: 20,
            totalAbsent: 2,
            attendanceRate: 91
        },
        {
            id: 6,
            firstName: 'Sofia',
            middleName: 'Isabel',
            lastName: 'Lopez',
            gender: 'Female',
            lrn: '123456789018',
            status: 'active',
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

// Reports data - starts empty until reports are submitted
const reports = ref([]);

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

const monthOptions = ref([
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
        case 'Grade 5':
            return grade5Sections.value;
        case 'Grade 6':
            return grade6Sections.value;
        default:
            return [];
    }
};

// Month-specific section data
const monthlyGradeData = ref({
    'August 2025': {
        Kindergarten: {
            sections: [
                { id: 1, name: 'Kinder A', teacher: 'Ms. Lisa Chen', studentCount: 22, presentCount: 20, absentCount: 2, attendanceRate: 91, status: 'EXCELLENT', statusClass: 'status-excellent' },
                { id: 2, name: 'Kinder B', teacher: 'Ms. Maria Santos', studentCount: 25, presentCount: 23, absentCount: 2, attendanceRate: 92, status: 'EXCELLENT', statusClass: 'status-excellent' },
                { id: 3, name: 'Kinder C', teacher: 'Ms. Lisa Chen', studentCount: 21, presentCount: 18, absentCount: 3, attendanceRate: 86, status: 'GOOD', statusClass: 'status-good' }
            ]
        },
        'Grade 1': {
            sections: [
                { id: 1, name: 'Grade 1-A', teacher: 'Ms. Jennifer Lee', studentCount: 28, presentCount: 26, absentCount: 2, attendanceRate: 93, status: 'EXCELLENT', statusClass: 'status-excellent' },
                { id: 2, name: 'Grade 1-B', teacher: 'Ms. Patricia Wong', studentCount: 27, presentCount: 23, absentCount: 4, attendanceRate: 85, status: 'GOOD', statusClass: 'status-good' }
            ]
        },
        'Grade 2': {
            sections: [
                { id: 1, name: 'Grade 2-A', teacher: 'Ms. Catherine Lopez', studentCount: 25, presentCount: 24, absentCount: 1, attendanceRate: 96, status: 'EXCELLENT', statusClass: 'status-excellent' },
                { id: 2, name: 'Grade 2-B', teacher: 'Ms. Diana Cruz', studentCount: 27, presentCount: 26, absentCount: 1, attendanceRate: 96, status: 'EXCELLENT', statusClass: 'status-excellent' },
                { id: 3, name: 'Grade 2-C', teacher: 'Mr. Robert Kim', studentCount: 26, presentCount: 23, absentCount: 3, attendanceRate: 88, status: 'GOOD', statusClass: 'status-good' }
            ]
        }
    },
    'September 2025': {
        Kindergarten: {
            sections: kinderSections.value
        },
        'Grade 1': {
            sections: grade1Sections.value
        },
        'Grade 2': {
            sections: grade2Sections.value
        },
        'Grade 3': {
            sections: grade3Sections.value
        },
        'Grade 4': {
            sections: grade4Sections.value
        },
        'Grade 5': {
            sections: grade5Sections.value
        },
        'Grade 6': {
            sections: grade6Sections.value
        }
    },
    'October 2025': {
        Kindergarten: {
            sections: [
                { id: 1, name: 'Kinder A', teacher: 'Ms. Lisa Chen', studentCount: 26, presentCount: 25, absentCount: 1, attendanceRate: 96, status: 'EXCELLENT', statusClass: 'status-excellent' },
                { id: 2, name: 'Kinder B', teacher: 'Ms. Maria Santos', studentCount: 29, presentCount: 28, absentCount: 1, attendanceRate: 97, status: 'EXCELLENT', statusClass: 'status-excellent' },
                { id: 3, name: 'Kinder C', teacher: 'Ms. Lisa Chen', studentCount: 25, presentCount: 22, absentCount: 3, attendanceRate: 88, status: 'GOOD', statusClass: 'status-good' },
                { id: 4, name: 'Kinder D', teacher: 'Ms. Anna Rodriguez', studentCount: 27, presentCount: 20, absentCount: 7, attendanceRate: 74, status: 'NEEDS ATTENTION', statusClass: 'status-warning' }
            ]
        },
        'Grade 1': {
            sections: [
                { id: 1, name: 'Grade 1-A', teacher: 'Ms. Jennifer Lee', studentCount: 31, presentCount: 29, absentCount: 2, attendanceRate: 94, status: 'EXCELLENT', statusClass: 'status-excellent' },
                { id: 2, name: 'Grade 1-B', teacher: 'Ms. Patricia Wong', studentCount: 30, presentCount: 26, absentCount: 4, attendanceRate: 87, status: 'GOOD', statusClass: 'status-good' },
                { id: 3, name: 'Grade 1-C', teacher: 'Ms. Rebecca Davis', studentCount: 29, presentCount: 24, absentCount: 5, attendanceRate: 83, status: 'GOOD', statusClass: 'status-good' }
            ]
        },
        'Grade 2': {
            sections: [
                { id: 1, name: 'Grade 2-A', teacher: 'Ms. Catherine Lopez', studentCount: 28, presentCount: 27, absentCount: 1, attendanceRate: 96, status: 'EXCELLENT', statusClass: 'status-excellent' },
                { id: 2, name: 'Grade 2-B', teacher: 'Ms. Diana Cruz', studentCount: 30, presentCount: 29, absentCount: 1, attendanceRate: 97, status: 'EXCELLENT', statusClass: 'status-excellent' },
                { id: 3, name: 'Grade 2-C', teacher: 'Mr. Robert Kim', studentCount: 29, presentCount: 26, absentCount: 3, attendanceRate: 90, status: 'EXCELLENT', statusClass: 'status-excellent' },
                { id: 4, name: 'Grade 2-D', teacher: 'Ms. Elena Reyes', studentCount: 31, presentCount: 30, absentCount: 1, attendanceRate: 97, status: 'EXCELLENT', statusClass: 'status-excellent' }
            ]
        }
    }
});

// Computed property for grade statistics based on real curriculum data
const gradeStatistics = computed(() => {
    if (loadingRealData.value || realGrades.value.length === 0) {
        return [];
    }

    const gradeEmojiMap = {
        Kindergarten: '🎨',
        'Grade 1': '📚',
        'Grade 2': '✏️',
        'Grade 3': '💻',
        'Grade 4': '🔬',
        'Grade 5': '🌟',
        'Grade 6': '🎓'
    };

    return realGrades.value
        .map((grade) => {
            // Get sections for this grade
            const gradeSections = realSections.value.filter((section) => section.gradeId === grade.id);

            // Use real data from loaded sections
            const sectionsWithData = gradeSections.map((section) => {
                const studentCount = section.studentCount || 0;
                // For now, generate mock attendance data until we have real attendance API
                const presentCount = Math.floor(studentCount * (0.85 + Math.random() * 0.15)); // 85-100% attendance
                const absentCount = studentCount - presentCount;
                const attendanceRate = studentCount > 0 ? Math.round((presentCount / studentCount) * 100) : 0;

                return {
                    id: section.id,
                    name: section.name,
                    teacher: section.teacher || 'No Teacher Assigned',
                    studentCount,
                    presentCount,
                    absentCount,
                    attendanceRate,
                    status: attendanceRate >= 95 ? 'EXCELLENT' : attendanceRate >= 85 ? 'GOOD' : 'NEEDS ATTENTION',
                    statusClass: attendanceRate >= 95 ? 'status-excellent' : attendanceRate >= 85 ? 'status-good' : 'status-warning'
                };
            });

            const sectionCount = sectionsWithData.length;
            const totalStudents = sectionsWithData.reduce((sum, section) => sum + section.studentCount, 0);
            const teacherCount = new Set(sectionsWithData.map((section) => section.teacher)).size;
            const totalPresent = sectionsWithData.reduce((sum, section) => sum + section.presentCount, 0);
            const attendanceRate = totalStudents > 0 ? Math.round((totalPresent / totalStudents) * 100) : 0;

            return {
                grade: grade.name,
                level: grade.name.includes('Kindergarten') ? 'Pre-Elementary' : 'Elementary',
                emoji: gradeEmojiMap[grade.name] || '📖',
                sections: sectionsWithData,
                sectionCount,
                totalStudents,
                teacherCount,
                attendanceRate
            };
        })
        .filter((grade) => grade.sectionCount > 0); // Only show grades with sections
});

const getAttendanceBarClass = (rate) => {
    if (rate >= 95) return 'excellent';
    if (rate >= 90) return 'good';
    if (rate >= 80) return 'warning';
    return 'poor';
};

const viewGradeSections = (grade) => {
    toast.add({
        severity: 'info',
        summary: 'Grade Sections',
        detail: `Viewing all sections for ${grade}`,
        life: 3000
    });
};

const generateGradeReport = (grade) => {
    toast.add({
        severity: 'success',
        summary: 'Report Generation',
        detail: `Generating comprehensive report for ${grade}`,
        life: 3000
    });
};

// Load real curriculum data
const loadRealCurriculumData = async () => {
    try {
        loadingRealData.value = true;

        // Get all curriculums (use plural method)
        const curriculumsResponse = await CurriculumService.getCurriculums();
        const curriculums = Array.isArray(curriculumsResponse) ? curriculumsResponse : curriculumsResponse.data || [];

        // Find the active curriculum or use the first one
        const activeCurriculum = curriculums.find((c) => c.is_active) || curriculums[0];

        if (activeCurriculum && activeCurriculum.id) {
            console.log('Using curriculum:', activeCurriculum);

            // Get grades for this curriculum
            const gradesResponse = await CurriculumService.getGradesByCurriculum(activeCurriculum.id);
            realGrades.value = Array.isArray(gradesResponse) ? gradesResponse : gradesResponse.data || [];

            console.log('Loaded grades:', realGrades.value);

            // Get all sections for each grade
            const allSections = [];
            for (const grade of realGrades.value) {
                try {
                    const sectionsResponse = await CurriculumService.getSectionsByGrade(activeCurriculum.id, grade.id);
                    const sections = Array.isArray(sectionsResponse) ? sectionsResponse : sectionsResponse.data || [];

                    console.log(`Sections for grade ${grade.name}:`, sections);

                    // Add grade info to each section and load student counts
                    for (const section of sections) {
                        section.gradeName = grade.name;
                        section.gradeId = grade.id;
                        section.curriculumId = activeCurriculum.id;

                        // Load real student count for this section
                        try {
                            const response = await fetch(`http://127.0.0.1:8000/api/sections/${section.id}/students/count`);
                            if (response.ok) {
                                const data = await response.json();
                                section.studentCount = data.count || 0;
                            } else {
                                section.studentCount = 0;
                            }
                        } catch (error) {
                            console.warn(`Error loading student count for section ${section.name}:`, error);
                            section.studentCount = 0;
                        }

                        // Load teacher information
                        if (section.homeroom_teacher_id) {
                            try {
                                const teacherResponse = await fetch(`http://127.0.0.1:8000/api/teachers/${section.homeroom_teacher_id}`);
                                if (teacherResponse.ok) {
                                    const teacherData = await teacherResponse.json();
                                    section.teacher = `${teacherData.first_name} ${teacherData.last_name}`;
                                } else {
                                    section.teacher = 'No Teacher Assigned';
                                }
                            } catch (error) {
                                console.warn(`Error loading teacher for section ${section.name}:`, error);
                                section.teacher = 'No Teacher Assigned';
                            }
                        } else {
                            section.teacher = 'No Teacher Assigned';
                        }

                        allSections.push(section);
                    }
                } catch (error) {
                    console.warn(`Error loading sections for grade ${grade.name}:`, error);
                }
            }

            realSections.value = allSections;
            console.log('Loaded real curriculum data:', {
                curriculum: activeCurriculum,
                grades: realGrades.value,
                sections: realSections.value
            });

            // Force reactivity update
            loadingRealData.value = false;

            toast.add({
                severity: 'success',
                summary: 'Data Loaded',
                detail: `Loaded ${realGrades.value.length} grades and ${realSections.value.length} sections`,
                life: 3000
            });
        } else {
            console.warn('No active curriculum found');
            toast.add({
                severity: 'warn',
                summary: 'No Curriculum',
                detail: 'No active curriculum found. Please set up curriculum first.',
                life: 5000
            });
        }
    } catch (error) {
        console.error('Error loading curriculum data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load curriculum data: ' + error.message,
            life: 5000
        });
    } finally {
        loadingRealData.value = false;
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
        case 'Grade 5':
            return 'Records: 3 / 3';
        case 'Grade 6':
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
        detail: 'Exporting all reports to Excel...',
        life: 3000
    });
    // Implement export logic here
};

// Notification functions
const scrollToSubmittedReports = () => {
    const submittedSection = document.querySelector('.submitted-reports-section');
    if (submittedSection) {
        submittedSection.scrollIntoView({ behavior: 'smooth' });
        // Clear notification count when user clicks
        newSubmissionsCount.value = 0;
    } else {
        // Fallback to reports section if submitted section doesn't exist
        const reportsSection = document.querySelector('.reports-table-container');
        if (reportsSection) {
            reportsSection.scrollIntoView({ behavior: 'smooth' });
            newSubmissionsCount.value = 0;
        }
    }
};

// Load submitted reports from API
const loadSubmittedReports = async () => {
    try {
        console.log('Loading submitted reports...');
        const response = await fetch('http://127.0.0.1:8000/api/admin/reports/submitted');
        const data = await response.json();

        console.log('API Response:', data);

        if (data.success) {
            const newReports = data.data;
            const previousReports = submittedReports.value;

            // Check for truly new submissions (not in previous list)
            const newSubmissions = newReports.filter((report) => {
                return report.status === 'submitted' && !previousReports.some((prev) => prev.id === report.id);
            });

            // Update submitted reports
            submittedReports.value = newReports;

            // Update notification count (only count 'submitted' status reports)
            const submittedCount = newReports.filter((report) => report.status === 'submitted').length;
            newSubmissionsCount.value = submittedCount;

            console.log('Submitted reports count:', submittedCount);
            console.log('New submissions:', newSubmissions.length);

            // Show toast notification for new submissions (only after initial load)
            if (newSubmissions.length > 0 && previousReports.length > 0) {
                newSubmissions.forEach((report) => {
                    toast.add({
                        severity: 'info',
                        summary: 'New Report Submitted',
                        detail: `${report.teacher_name} submitted SF2 report for ${report.section_name}`,
                        life: 5000
                    });
                });
                
                // Trigger refresh of AdminTopbar notifications
                window.dispatchEvent(new Event('refreshNotifications'));
            }

            // Update last checked time
            lastCheckedTime.value = new Date();
            
            // Also trigger refresh if count changed (for manual reloads)
            window.dispatchEvent(new Event('refreshNotifications'));
        }
    } catch (error) {
        console.error('Error loading submitted reports:', error);
        toast.add({
            severity: 'error',
            summary: 'Connection Error',
            detail: 'Failed to load submitted reports. Please check if the server is running.',
            life: 5000
        });
    }
};

// Start real-time polling
const startPolling = () => {
    // Load immediately
    loadSubmittedReports();

    // Then poll every 30 seconds
    pollingInterval.value = setInterval(() => {
        loadSubmittedReports();
    }, 30000);
};

// Stop polling
const stopPolling = () => {
    if (pollingInterval.value) {
        clearInterval(pollingInterval.value);
        pollingInterval.value = null;
    }
};

// Simulate receiving new submission notification (for testing)
const receiveNewSubmission = () => {
    newSubmissionsCount.value += 1;
    toast.add({
        severity: 'info',
        summary: 'New Report Submitted',
        detail: 'A teacher has submitted a new SF2 report',
        life: 5000
    });
    
    // Trigger refresh of AdminTopbar notifications
    window.dispatchEvent(new Event('refreshNotifications'));
};

// Submitted reports actions
const viewSubmittedReport = (report) => {
    // Open the SF2 report in a new window/tab
    const url = `http://127.0.0.1:8000/api/teacher/reports/sf2/download/${report.section_id}/${report.month}`;
    window.open(url, '_blank');

    toast.add({
        severity: 'info',
        summary: 'Opening Report',
        detail: `Viewing SF2 report for ${report.section_name}`,
        life: 3000
    });
};

const downloadSubmittedReport = async (report) => {
    try {
        const response = await fetch(`http://127.0.0.1:8000/api/teacher/reports/sf2/download/${report.section_id}/${report.month}`);

        if (response.ok) {
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `SF2_${report.section_name}_${report.month_name}.xlsx`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            toast.add({
                severity: 'success',
                summary: 'Download Complete',
                detail: `SF2 report for ${report.section_name} downloaded successfully`,
                life: 3000
            });
        } else {
            throw new Error('Download failed');
        }
    } catch (error) {
        console.error('Error downloading report:', error);
        toast.add({
            severity: 'error',
            summary: 'Download Failed',
            detail: 'Failed to download the SF2 report',
            life: 3000
        });
    }
};

const approveReport = async (report) => {
    try {
        const response = await fetch(`http://127.0.0.1:8000/api/admin/reports/submitted/${report.id}/status`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                status: 'approved',
                admin_notes: 'Report approved by admin'
            })
        });

        const data = await response.json();

        if (data.success) {
            // Update the report status in the local array
            const reportIndex = submittedReports.value.findIndex((r) => r.id === report.id);
            if (reportIndex !== -1) {
                submittedReports.value[reportIndex].status = 'approved';
            }

            // Update notification count
            const submittedCount = submittedReports.value.filter((r) => r.status === 'submitted').length;
            newSubmissionsCount.value = submittedCount;
            
            // Trigger refresh of AdminTopbar notifications
            window.dispatchEvent(new Event('refreshNotifications'));

            toast.add({
                severity: 'success',
                summary: 'Report Approved',
                detail: `SF2 report for ${report.section_name} has been approved`,
                life: 3000
            });
        } else {
            throw new Error(data.message || 'Failed to approve report');
        }
    } catch (error) {
        console.error('Error approving report:', error);
        toast.add({
            severity: 'error',
            summary: 'Approval Failed',
            detail: 'Failed to approve the SF2 report',
            life: 3000
        });
    }
};

const rejectReport = async (report) => {
    try {
        const response = await fetch(`http://127.0.0.1:8000/api/admin/reports/submitted/${report.id}/status`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                status: 'rejected',
                admin_notes: 'Report rejected by admin - please review and resubmit'
            })
        });

        const data = await response.json();

        if (data.success) {
            // Update the report status in the local array
            const reportIndex = submittedReports.value.findIndex((r) => r.id === report.id);
            if (reportIndex !== -1) {
                submittedReports.value[reportIndex].status = 'rejected';
            }

            // Update notification count
            const submittedCount = submittedReports.value.filter((r) => r.status === 'submitted').length;
            newSubmissionsCount.value = submittedCount;
            
            // Trigger refresh of AdminTopbar notifications
            window.dispatchEvent(new Event('refreshNotifications'));

            toast.add({
                severity: 'warn',
                summary: 'Report Rejected',
                detail: `SF2 report for ${report.section_name} has been rejected`,
                life: 3000
            });
        } else {
            throw new Error(data.message || 'Failed to reject report');
        }
    } catch (error) {
        console.error('Error rejecting report:', error);
        toast.add({
            severity: 'error',
            summary: 'Rejection Failed',
            detail: 'Failed to reject the SF2 report',
            life: 3000
        });
    }
};

// Section details methods
const viewSectionDetails = async (section) => {
    // Close the main report dialog first
    showViewDialog.value = false;
    
    // Load real student data for this section
    await loadSectionStudents(section);
    
    // Wait a moment then open the section details dialog
    setTimeout(() => {
        selectedSectionDetails.value = section;
        showSectionDetailsDialog.value = true;
    }, 100);
};

// Load real students from database and integrate with SF2 data
const loadSectionStudents = async (section) => {
    try {
        console.log('Loading students for section:', section.name, 'ID:', section.id);
        
        // Step 1: Get section details to get section name
        const sectionResponse = await fetch(`http://127.0.0.1:8000/api/sections/${section.id}`);
        if (!sectionResponse.ok) {
            throw new Error(`Failed to fetch section details: ${sectionResponse.status}`);
        }
        const sectionData = await sectionResponse.json();
        const sectionNameFromAPI = sectionData.name;
        console.log('Section name from API:', sectionNameFromAPI);
        
        // Step 2: Get all students and filter by section name
        const studentsResponse = await fetch('http://127.0.0.1:8000/api/students');
        if (!studentsResponse.ok) {
            throw new Error(`Failed to fetch students: ${studentsResponse.status}`);
        }
        const studentsData = await studentsResponse.json();
        
        // Filter students by section name
        const filteredStudents = studentsData.filter(student => 
            student.section === sectionNameFromAPI || 
            student.current_section_name === sectionNameFromAPI
        );
        
        console.log('Found students for section:', filteredStudents.length);
        
        // Step 3: Check for submitted SF2 reports for this section and month
        const currentMonth = getCurrentMonthForAPI();
        console.log('Checking for submitted SF2 data for month:', currentMonth);
        
        let sf2Data = null;
        try {
            // First try to get stored submitted SF2 data (admin endpoint)
            const submittedSF2Response = await fetch(`http://127.0.0.1:8000/api/admin/reports/sf2/submitted/${section.id}/${currentMonth}`);
            if (submittedSF2Response.ok) {
                const submittedData = await submittedSF2Response.json();
                if (submittedData.success) {
                    sf2Data = submittedData.data;
                    console.log('Found submitted SF2 data:', sf2Data);
                    
                    toast.add({
                        severity: 'success',
                        summary: 'Real Submitted SF2 Data Loaded',
                        detail: `Showing data submitted by ${sf2Data.section?.teacher?.name || 'teacher'} (Status: ${sf2Data.submission_info?.status || 'submitted'})`,
                        life: 5000
                    });
                }
            } else {
                console.log('No submitted SF2 data found, trying live data...');
                
                // Fallback to live SF2 data (teacher endpoint)
                const liveSF2Response = await fetch(`http://127.0.0.1:8000/api/teacher/reports/sf2/data/${section.id}/${currentMonth}`);
                if (liveSF2Response.ok) {
                    const liveData = await liveSF2Response.json();
                    if (liveData.success) {
                        sf2Data = liveData.data;
                        console.log('Found live SF2 data:', sf2Data);
                        
                        toast.add({
                            severity: 'info',
                            summary: 'Live SF2 Data Loaded',
                            detail: 'Showing current attendance data (not yet submitted)',
                            life: 4000
                        });
                    }
                } else {
                    console.log('No SF2 data found, using sample data');
                    toast.add({
                        severity: 'warn',
                        summary: 'Sample Data Only',
                        detail: 'No SF2 report data found for this section and month',
                        life: 4000
                    });
                }
            }
        } catch (sf2Error) {
            console.log('Error fetching SF2 data:', sf2Error);
            toast.add({
                severity: 'error',
                summary: 'Error Loading SF2 Data',
                detail: 'Failed to fetch SF2 report data from server',
                life: 4000
            });
        }
        
        // Step 4: Process students and integrate with SF2 data
        const processedStudents = filteredStudents.map((student, index) => {
            // Try to find matching student in SF2 data
            let sf2StudentData = null;
            if (sf2Data && sf2Data.students) {
                sf2StudentData = sf2Data.students.find(sf2Student => 
                    sf2Student.id === student.id || 
                    (sf2Student.firstName === student.firstName && sf2Student.lastName === student.lastName)
                );
            }
            
            return {
                id: student.id || index + 1,
                firstName: student.firstName || student.name?.split(' ')[0] || 'Unknown',
                lastName: student.lastName || student.name?.split(' ').slice(1).join(' ') || 'Student',
                middleName: student.middleName || '',
                gender: student.gender || (index % 2 === 0 ? 'Male' : 'Female'),
                gradeLevel: student.gradeLevel || sectionData.grade || 'Unknown',
                section: sectionNameFromAPI,
                
                // Use real SF2 attendance data if available, otherwise generate sample
                attendance: sf2StudentData ? sf2StudentData.attendance : generateSampleAttendance(),
                totalPresent: sf2StudentData ? sf2StudentData.totalPresent : 15,
                totalAbsent: sf2StudentData ? sf2StudentData.totalAbsent : 5,
                attendanceRate: sf2StudentData ? sf2StudentData.attendanceRate : 75,
                status: sf2StudentData ? sf2StudentData.status : 'active'
            };
        });
        
        // Step 5: Update the sectionStudents data
        sectionStudents.value[sectionNameFromAPI] = processedStudents;
        
        console.log('Updated section students:', processedStudents.length, 'students loaded');
        
    } catch (error) {
        console.error('Error loading section students:', error);
        toast.add({
            severity: 'error',
            summary: 'Error Loading Students',
            detail: 'Failed to load student data from database',
            life: 5000
        });
        
        // Fallback to existing sample data
        console.log('Falling back to sample data for section:', section.name);
    }
};

// Helper function to get current month in API format
const getCurrentMonthForAPI = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    return `${year}-${month}`;
};

// Generate sample attendance data when real data is not available
const generateSampleAttendance = () => {
    const attendance = {};
    const daysInMonth = attendanceDays.value.length;
    
    attendanceDays.value.forEach(day => {
        // Generate realistic attendance pattern (80% present, 15% absent, 5% late)
        const rand = Math.random();
        if (rand < 0.80) {
            attendance[day.date] = 'present';
        } else if (rand < 0.95) {
            attendance[day.date] = 'absent';
        } else {
            attendance[day.date] = 'late';
        }
    });
    
    return attendance;
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

// Removed duplicate - using the one defined later in gender-based functions section

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

// Removed duplicate - using the one defined later in gender-based functions section

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

const formatStatusDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

// Student status functions
const getDroppedOutCount = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.status === 'dropped_out').length;
};

const getTransferredOutCount = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.status === 'transferred_out').length;
};

const getTransferredInCount = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.status === 'transferred_in').length;
};

// Status change functions
const showStatusChangeDialog = (student) => {
    selectedStudent.value = student;
    newStatus.value = '';
    statusDate.value = null;
    statusReason.value = '';
    statusChangeDialog.value = true;
};

const closeStatusDialog = () => {
    statusChangeDialog.value = false;
    selectedStudent.value = null;
    newStatus.value = '';
    statusDate.value = null;
    statusReason.value = '';
};

const updateStudentStatus = () => {
    if (!selectedStudent.value || !newStatus.value || !statusDate.value) {
        toast.add({
            severity: 'warn',
            summary: 'Missing Information',
            detail: 'Please fill in all required fields.',
            life: 3000
        });
        return;
    }

    // Find the student in the data and update their status
    const sectionName = selectedSectionDetails.value.name;
    const students = sectionStudents.value[sectionName];
    const studentIndex = students.findIndex((s) => s.id === selectedStudent.value.id);

    if (studentIndex !== -1) {
        // Update student status
        students[studentIndex].status = newStatus.value;
        students[studentIndex].statusDate = statusDate.value.toISOString().split('T')[0];
        students[studentIndex].statusReason = statusReason.value;

        // If dropping out or transferring out, remove future attendance
        if (newStatus.value === 'dropped_out' || newStatus.value === 'transferred_out') {
            const cutoffDate = new Date(statusDate.value);
            const attendance = students[studentIndex].attendance;

            // Remove attendance records after the status change date
            Object.keys(attendance).forEach((dateKey) => {
                const attendanceDate = new Date(dateKey);
                if (attendanceDate > cutoffDate) {
                    delete attendance[dateKey];
                }
            });

            // Recalculate totals
            const presentDays = Object.values(attendance).filter((status) => status === 'present').length;
            const absentDays = Object.values(attendance).filter((status) => status === 'absent').length;
            const totalDays = presentDays + absentDays;

            students[studentIndex].totalPresent = presentDays;
            students[studentIndex].totalAbsent = absentDays;
            students[studentIndex].attendanceRate = totalDays > 0 ? Math.round((presentDays / totalDays) * 100) : 0;
        }

        toast.add({
            severity: 'success',
            summary: 'Status Updated',
            detail: `${selectedStudent.value.firstName} ${selectedStudent.value.lastName} status changed to ${newStatus.value.replace('_', ' ')}.`,
            life: 3000
        });

        closeStatusDialog();
    }
};

const getActiveStudentsCount = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.status === 'active').length;
};

// Gender-based count functions
const getMaleStudentsCount = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.gender === 'Male').length;
};

const getFemaleStudentsCount = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.gender === 'Female').length;
};

const getMaleAttendanceRate = (sectionName) => {
    const students = getSectionStudents(sectionName);
    const maleStudents = students.filter((student) => student.gender === 'Male');
    if (maleStudents.length === 0) return 0;

    const totalRate = maleStudents.reduce((sum, student) => sum + (student.attendanceRate || 0), 0);
    return Math.round(totalRate / maleStudents.length);
};

const getFemaleAttendanceRate = (sectionName) => {
    const students = getSectionStudents(sectionName);
    const femaleStudents = students.filter((student) => student.gender === 'Female');
    if (femaleStudents.length === 0) return 0;

    const totalRate = femaleStudents.reduce((sum, student) => sum + (student.attendanceRate || 0), 0);
    return Math.round(totalRate / femaleStudents.length);
};

const getMaleDroppedOutCount = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.gender === 'Male' && student.status === 'dropped_out').length;
};

const getFemaleDroppedOutCount = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.gender === 'Female' && student.status === 'dropped_out').length;
};

const getMaleTransferredOutCount = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.gender === 'Male' && student.status === 'transferred_out').length;
};

const getFemaleTransferredOutCount = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.gender === 'Female' && student.status === 'transferred_out').length;
};

const getMaleTransferredInCount = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.gender === 'Male' && student.status === 'transferred_in').length;
};

const getFemaleTransferredInCount = (sectionName) => {
    const students = getSectionStudents(sectionName);
    return students.filter((student) => student.gender === 'Female' && student.status === 'transferred_in').length;
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
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
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
    const currentIndex = availableMonths.value.findIndex((m) => m.month === currentMonth.value && m.year === currentYear.value);
    return currentIndex > 0;
};

const canGoNextMonth = () => {
    const currentIndex = availableMonths.value.findIndex((m) => m.month === currentMonth.value && m.year === currentYear.value);
    return currentIndex < availableMonths.value.length - 1;
};

const previousMonth = () => {
    const currentIndex = availableMonths.value.findIndex((m) => m.month === currentMonth.value && m.year === currentYear.value);
    if (currentIndex > 0) {
        const prevMonth = availableMonths.value[currentIndex - 1];
        currentMonth.value = prevMonth.month;
        currentYear.value = prevMonth.year;
    }
};

const nextMonth = () => {
    const currentIndex = availableMonths.value.findIndex((m) => m.month === currentMonth.value && m.year === currentYear.value);
    if (currentIndex < availableMonths.value.length - 1) {
        const nextMonth = availableMonths.value[currentIndex + 1];
        currentMonth.value = nextMonth.month;
        currentYear.value = nextMonth.year;
    }
};

// Overview month navigation functions
const getCurrentOverviewMonthDisplay = () => {
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    return `${monthNames[currentOverviewMonth.value]} ${currentOverviewYear.value}`;
};

const canGoPreviousOverviewMonth = () => {
    const currentIndex = availableMonths.value.findIndex((m) => m.month === currentOverviewMonth.value && m.year === currentOverviewYear.value);
    return currentIndex > 0;
};

const canGoNextOverviewMonth = () => {
    const currentIndex = availableMonths.value.findIndex((m) => m.month === currentOverviewMonth.value && m.year === currentOverviewYear.value);
    return currentIndex < availableMonths.value.length - 1;
};

const previousOverviewMonth = () => {
    const currentIndex = availableMonths.value.findIndex((m) => m.month === currentOverviewMonth.value && m.year === currentOverviewYear.value);
    if (currentIndex > 0) {
        const prevMonth = availableMonths.value[currentIndex - 1];
        currentOverviewMonth.value = prevMonth.month;
        currentOverviewYear.value = prevMonth.year;
    }
};

const nextOverviewMonth = () => {
    const currentIndex = availableMonths.value.findIndex((m) => m.month === currentOverviewMonth.value && m.year === currentOverviewYear.value);
    if (currentIndex < availableMonths.value.length - 1) {
        const nextMonth = availableMonths.value[currentIndex + 1];
        currentOverviewMonth.value = nextMonth.month;
        currentOverviewYear.value = nextMonth.year;
    }
};

const getActiveSectionsCount = () => {
    const currentMonthDisplay = getCurrentOverviewMonthDisplay();
    const monthData = monthlyGradeData.value[currentMonthDisplay] || {};

    let totalSections = 0;
    Object.values(monthData).forEach((gradeData) => {
        if (gradeData.sections) {
            totalSections += gradeData.sections.length;
        }
    });

    return totalSections;
};

const downloadSF2Report = async () => {
    if (!selectedSectionDetails.value) {
        toast.add({
            severity: 'warn',
            summary: 'No Section Selected',
            detail: 'Please select a section to download SF2 report.',
            life: 3000
        });
        return;
    }

    try {
        // Get current section data
        const section = selectedSectionDetails.value;
        const sectionId = section.id;
        const monthDisplay = getCurrentMonthDisplay();

        // Convert month display to YYYY-MM format for API
        const monthParts = monthDisplay.split(' ');
        const monthName = monthParts[0];
        const year = monthParts[1];
        const monthNumber = new Date(`${monthName} 1, ${year}`).getMonth() + 1;
        const monthParam = `${year}-${monthNumber.toString().padStart(2, '0')}`;

        toast.add({
            severity: 'info',
            summary: 'Generating Report',
            detail: 'Please wait while we generate your SF2 report...',
            life: 3000
        });

        // Call Laravel backend API to generate and download SF2 report
        const response = await fetch(`http://127.0.0.1:8000/api/admin/reports/sf2/download/${sectionId}/${monthParam}`, {
            method: 'GET',
            headers: {
                Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Get the filename from response headers or create default
        const contentDisposition = response.headers.get('Content-Disposition');
        let filename = `SF2_Daily_Attendance_${section.name}_${monthDisplay.replace(/\s+/g, '_')}.xlsx`;

        if (contentDisposition) {
            const filenameMatch = contentDisposition.match(/filename="(.+)"/);
            if (filenameMatch) {
                filename = filenameMatch[1];
            }
        }

        // Convert response to blob and trigger download
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        toast.add({
            severity: 'success',
            summary: 'SF2 Report Downloaded',
            detail: `SF2 Excel report for ${section.name} (${monthDisplay}) has been downloaded successfully.`,
            life: 5000
        });
    } catch (error) {
        console.error('Error downloading SF2 report:', error);
        toast.add({
            severity: 'error',
            summary: 'Download Failed',
            detail: 'Failed to download SF2 report from server. Please try again.',
            life: 5000
        });
    }
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

// Lifecycle hooks
onMounted(() => {
    // Load real curriculum data
    loadRealCurriculumData();
    loading.value = true;
    setTimeout(() => {
        loading.value = false;
    }, 1000);

    // Start polling for submitted reports
    startPolling();
});

// Cleanup on unmount
onUnmounted(() => {
    stopPolling();
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

/* SF2 Template Styles */
.sf2-template-preview {
    background: white;
    border: 2px solid #000;
    padding: 20px;
    margin: 20px 0;
    font-family: 'Times New Roman', serif;
    font-size: 12px;
    line-height: 1.4;
}

.sf2-header {
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
}

.sf2-header h3 {
    font-size: 14px;
    font-weight: bold;
    margin: 0;
    text-transform: uppercase;
}

.sf2-info-section {
    display: grid;
    grid-template-columns: 1fr 2fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.sf2-school-info {
    border: 1px solid #000;
    padding: 10px;
}

.info-row {
    display: flex;
    margin-bottom: 5px;
}

.info-row .label {
    font-weight: bold;
    min-width: 80px;
}

.info-row .value {
    border-bottom: 1px solid #000;
    flex: 1;
    padding-left: 5px;
}

.sf2-guidelines {
    border: 1px solid #000;
    padding: 10px;
}

.sf2-guidelines h4 {
    font-size: 12px;
    font-weight: bold;
    margin: 0 0 10px 0;
    text-decoration: underline;
}

.sf2-guidelines ol {
    margin: 0;
    padding-left: 20px;
}

.sf2-guidelines li {
    margin-bottom: 5px;
    font-size: 10px;
}

.formulas {
    margin-top: 10px;
}

.formula-item {
    margin-bottom: 10px;
    font-size: 10px;
}

.formula-label {
    font-weight: bold;
    display: block;
    margin-bottom: 3px;
}

.formula-box {
    border: 1px solid #000;
    padding: 5px;
    text-align: center;
    position: relative;
}

.formula-box .divider {
    border-top: 1px solid #000;
    margin: 3px 0;
    padding-top: 3px;
}

.formula-box .multiply {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
}

.sf2-codes {
    border: 1px solid #000;
    padding: 10px;
}

.sf2-codes h4 {
    font-size: 12px;
    font-weight: bold;
    margin: 0 0 10px 0;
    text-align: center;
    text-decoration: underline;
}

.codes-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.code-section h5 {
    font-size: 11px;
    font-weight: bold;
    margin: 0 0 5px 0;
    text-decoration: underline;
}

.code-list {
    font-size: 9px;
}

.code-item {
    margin-bottom: 2px;
    padding-left: 10px;
}

.sf2-summary-section {
    grid-column: 1 / -1;
    margin-top: 20px;
}

.summary-box {
    border: 2px solid #000;
    padding: 15px;
}

.summary-box h4 {
    font-size: 12px;
    font-weight: bold;
    margin: 0 0 15px 0;
    text-align: center;
}

.summary-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 20px;
}

.summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px;
    border-bottom: 1px solid #ccc;
}

.summary-label {
    font-size: 10px;
    flex: 1;
}

.summary-value {
    font-weight: bold;
    min-width: 50px;
    text-align: right;
    border-bottom: 1px solid #000;
    padding: 2px 5px;
}

.certification {
    margin-top: 20px;
    text-align: center;
}

.certification p {
    font-size: 11px;
    font-style: italic;
    margin-bottom: 20px;
}

.signature-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
}

.signature-box {
    text-align: center;
}

.signature-line {
    border-bottom: 1px solid #000;
    height: 40px;
    margin-bottom: 5px;
}

.signature-box span {
    font-size: 10px;
}

.sf2-footer {
    text-align: center;
    margin-top: 20px;
    padding-top: 10px;
    border-top: 1px solid #000;
    font-size: 10px;
}

/* Student Status Badges */
.status-badge {
    font-size: 8px;
    padding: 2px 6px;
    border-radius: 3px;
    margin-left: 8px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-dropped {
    background-color: #ff4444;
    color: white;
}

.status-transferred-out {
    background-color: #ff9800;
    color: white;
}

.status-transferred-in {
    background-color: #4caf50;
    color: white;
}

/* Grade Level Statistics */
.grade-statistics-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

/* Month Navigation Header */
.month-navigation-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2rem;
    margin: 2rem 0;
    padding: 1.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
}

.month-nav-btn {
    width: 50px !important;
    height: 50px !important;
    border-radius: 50% !important;
    background: rgba(255, 255, 255, 0.2) !important;
    border: 2px solid rgba(255, 255, 255, 0.3) !important;
    color: white !important;
    transition: all 0.3s ease !important;
}

.month-nav-btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.3) !important;
    border-color: rgba(255, 255, 255, 0.5) !important;
    transform: scale(1.1);
}

.month-nav-btn:disabled {
    opacity: 0.4 !important;
    cursor: not-allowed !important;
}

.current-month-display {
    text-align: center;
    color: white;
    min-width: 250px;
}

.month-title {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

.month-subtitle {
    font-size: 1rem;
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-weight: 500;
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

/* Official SF2 Header Styles */
.sf2-official-header {
    background: white;
    border: 2px solid #000;
    margin-bottom: 1rem;
    padding: 0;
}

.header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border-bottom: 1px solid #000;
}

.deped-logo {
    flex: 0 0 80px;
}

.logo-img {
    width: 60px;
    height: 60px;
    object-fit: contain;
}

.header-center {
    flex: 1;
    text-align: center;
    padding: 0 1rem;
}

.form-number {
    font-size: 0.8rem;
    color: #666;
    display: block;
    margin-bottom: 0.5rem;
}

.form-title h2 {
    font-size: 1.2rem;
    font-weight: bold;
    margin: 0;
    color: #000;
    text-transform: uppercase;
}

.deped-text {
    flex: 0 0 120px;
    text-align: center;
}

.deped-brand {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.dep {
    color: #1e40af;
}

.ed {
    color: #dc2626;
}

.deped-text p {
    font-size: 0.7rem;
    margin: 0;
    color: #000;
    font-weight: 500;
}

.school-details-form {
    padding: 1rem;
}

.form-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.75rem;
    align-items: end;
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.form-field.wide {
    flex: 2;
}

.form-field label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #000;
}

.form-input {
    border: 1px solid #000;
    border-bottom: 2px solid #000;
    padding: 0.25rem 0.5rem;
    font-size: 0.9rem;
    background: white;
    min-height: 24px;
}

.form-input:focus {
    outline: none;
    border-color: #1e40af;
}

/* Improved Table Styling to Match Official SF2 */
.attendance-table {
    width: 100%;
    border-collapse: collapse;
    border: 2px solid #000;
    background: white;
    font-size: 0.8rem;
}

.attendance-table th,
.attendance-table td {
    border: 1px solid #000;
    padding: 0.25rem;
    text-align: center;
    vertical-align: middle;
}

.student-name-header {
    background: #f0f0f0;
    font-weight: bold;
    text-align: center;
    width: 200px;
    border: 2px solid #000;
}

.day-header {
    background: #f0f0f0;
    font-weight: bold;
    width: 30px;
    border: 1px solid #000;
}

.day-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.1rem;
}

.day-number {
    font-weight: bold;
    font-size: 0.9rem;
}

.day-name {
    font-size: 0.6rem;
    text-transform: uppercase;
}

.summary-header {
    background: #f0f0f0;
    font-weight: bold;
    width: 60px;
    border: 2px solid #000;
    display: table-cell;
    vertical-align: middle;
}

/* Student Row Styling */
.student-name-cell {
    text-align: left;
    padding: 0.5rem;
    border-right: 2px solid #000;
}

.student-name {
    font-weight: 600;
    display: block;
    margin-bottom: 0.25rem;
}

.student-lrn {
    font-size: 0.7rem;
    color: #666;
    display: block;
}

.attendance-cell {
    width: 30px;
    height: 30px;
    position: relative;
    background: white;
}

.attendance-mark {
    font-weight: bold;
    font-size: 0.9rem;
}

.attendance-mark.present {
    color: #059669;
}

.attendance-mark.absent {
    color: #dc2626;
}

.attendance-mark.late {
    color: #d97706;
}

/* Gender Section Styling */
.gender-header {
    background: #e5e7eb;
    color: #000;
    padding: 0.5rem;
    font-weight: bold;
    text-align: center;
    border: 2px solid #000;
}

.gender-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Total Rows */
.gender-total-row,
.combined-total-row {
    background: #f9fafb;
    font-weight: bold;
}

.gender-total-row td,
.combined-total-row td {
    border: 1px solid #000;
    padding: 0.5rem;
}

/* Line Numbers Row */
.line-numbers-row {
    background: #f0f0f0;
    border-top: 2px solid #000;
}

.line-number-label {
    font-weight: bold;
    background: #e5e7eb;
    border: 1px solid #000;
    text-align: center;
}

.line-number-cell {
    font-weight: bold;
    font-size: 0.8rem;
    background: #f9fafb;
    border: 1px solid #000;
    height: 25px;
    vertical-align: middle;
    padding: 0.2rem;
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

/* SF2 Summary Table Styles */
.sf2-summary-section {
    margin: 2rem 0;
    border: 2px solid #333;
    background: white;
}

.summary-header {
    display: flex;
    border-bottom: 2px solid #333;
    background: #f8f9fa;
    font-weight: 600;
    font-size: 0.9rem;
}

.header-box {
    flex: 1;
    padding: 0.5rem 1rem;
    border-right: 2px solid #333;
    text-align: center;
}

.header-box:last-child {
    border-right: none;
}

.summary-box-header {
    font-weight: 700;
    text-transform: uppercase;
}

.month-label,
.days-label {
    flex: 1;
}

.summary-title {
    text-align: center;
    font-weight: 700;
    text-transform: uppercase;
}

.sf2-summary-table {
    width: 100%;
    border-collapse: collapse;
    font-family: Arial, sans-serif;
    font-size: 0.85rem;
}

.sf2-summary-table th {
    background: #f8f9fa;
    border: 1px solid #333;
    padding: 0.5rem;
    text-align: center;
    font-weight: 700;
    font-size: 0.8rem;
}

.sf2-summary-table th.summary-description {
    width: 60%;
    text-align: left;
}

.sf2-summary-table th.summary-m,
.sf2-summary-table th.summary-f,
.sf2-summary-table th.summary-total {
    width: 13.33%;
    text-align: center;
}

.sf2-summary-table td {
    border: 1px solid #333;
    padding: 0.4rem 0.6rem;
    vertical-align: middle;
}

.sf2-summary-table td.summary-label {
    text-align: left;
    font-size: 0.8rem;
    line-height: 1.3;
}

.sf2-summary-table td.summary-label em {
    font-style: italic;
    text-decoration: underline;
}

.sf2-summary-table td.summary-label small {
    font-size: 0.7rem;
    color: #666;
    display: block;
    margin-top: 0.2rem;
}

.sf2-summary-table td.summary-value {
    text-align: center;
    font-weight: 600;
    background: #f8f9fa;
}

.sf2-summary-table tr:nth-child(even) {
    background: #fafafa;
}

.sf2-summary-table tr:hover {
    background: #f0f8ff;
}

.attendance-table .summary-header {
    background: #e3f2fd;
    color: #1976d2;
    padding: 0.75rem 0.5rem;
    text-align: center;
    font-weight: 700;
    border: 1px solid #2196f3;
    font-size: 0.85rem;
    white-space: nowrap;
    min-width: 80px;
    display: table-cell;
    vertical-align: middle;
}

.line-number-label {
    background: #6c757d;
    color: white;
    padding: 0.4rem;
    text-align: center;
    font-size: 0.8rem;
    font-weight: 600;
}

.line-number-cell {
    background: #f8f9fa;
    color: #495057;
    padding: 0.4rem;
    text-align: center;
    font-size: 0.8rem;
    font-weight: 600;
    border: 1px solid #dee2e6;
}

/* Grade Statistics Section Styles */
.grade-statistics-section {
    margin: 2rem 0;
    padding: 2rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.statistics-header {
    text-align: center;
    margin-bottom: 2rem;
}

.statistics-title {
    color: white;
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.statistics-subtitle {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    margin: 0;
}

.grade-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.grade-stat-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.grade-stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.grade-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f1f3f4;
}

.grade-info h3.grade-name {
    font-size: 1.4rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0;
}

.grade-level {
    font-size: 0.9rem;
    color: #7f8c8d;
    font-weight: 500;
}

.grade-icon .grade-emoji {
    font-size: 2.5rem;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
}

.grade-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.metric-item {
    text-align: center;
    padding: 1rem;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.metric-item.sections {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.metric-item.students {
    background: linear-gradient(135deg, #f093fb, #f5576c);
    color: white;
}

.metric-item.teachers {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: white;
}

.metric-value {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.metric-label {
    font-size: 0.85rem;
    opacity: 0.9;
    font-weight: 500;
}

.grade-attendance {
    margin-bottom: 1.5rem;
}

.attendance-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.attendance-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.attendance-fill.excellent {
    background: linear-gradient(90deg, #28a745, #20c997);
}

.attendance-fill.good {
    background: linear-gradient(90deg, #17a2b8, #6f42c1);
}

.attendance-fill.warning {
    background: linear-gradient(90deg, #ffc107, #fd7e14);
}

.attendance-fill.poor {
    background: linear-gradient(90deg, #dc3545, #e83e8c);
}

.attendance-text {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.attendance-rate {
    font-size: 1.1rem;
    font-weight: 700;
    color: #2c3e50;
}

.attendance-label {
    font-size: 0.9rem;
    color: #6c757d;
}

.section-list {
    margin-bottom: 1.5rem;
}

.section-list-header {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
}

.sections-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.section-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    cursor: pointer;
    overflow: hidden;
    border: 1px solid #e9ecef;
}

.section-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

.section-card-header {
    padding: 1rem;
    position: relative;
    color: white;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.section-card-header.status-excellent {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.section-card-header.status-good {
    background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
}

.section-card-header.status-warning {
    background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    color: #212529;
}

.section-card-header.status-poor {
    background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
}

.section-card-title {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.section-card-subtitle {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-bottom: 0.5rem;
}

.section-card-icon {
    position: absolute;
    top: 1rem;
    right: 1rem;
}

.attendance-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 0.25rem 0.5rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
}

.section-card-body {
    padding: 1rem;
    background: #f8f9fa;
}

.section-stats {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #495057;
}

.stat-item i {
    color: #6c757d;
    font-size: 0.9rem;
}

.section-card-footer {
    padding: 0.75rem 1rem;
    background: white;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-card-footer .p-button {
    color: #6c757d;
    transition: color 0.2s ease;
}

.section-card-footer .p-button:hover {
    color: #495057;
}

.section-chip.status-excellent {
    background: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}

.section-chip.status-good {
    background: #d1ecf1;
    color: #0c5460;
    border-color: #bee5eb;
}

.section-chip.status-warning {
    background: #fff3cd;
    color: #856404;
    border-color: #ffeaa7;
}

.section-chip:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.grade-actions {
    display: flex;
    gap: 0.75rem;
}

.grade-actions .p-button {
    flex: 1;
    font-size: 0.85rem;
    padding: 0.6rem 1rem;
    border-radius: 8px;
    font-weight: 600;
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

    .grade-stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .grade-metrics {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .grade-actions {
        flex-direction: column;
    }

    .statistics-title {
        font-size: 2rem;
    }

    .sections-cards-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .section-stats {
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

/* Status Change Button */
.status-change-btn {
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 2px 6px;
    margin-left: 8px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
}

.status-change-btn:hover {
    background: #e0e0e0;
    border-color: #ccc;
}

.status-change-form {
    padding: 1rem 0;
}

.student-details {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.student-details h4 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
}

.student-details p {
    margin: 0.25rem 0;
    color: #666;
    font-size: 0.9rem;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #2c3e50;
}

/* No Data State */
.no-data-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin: 1rem 0;
    padding: 2rem;
    text-align: center;
}

.no-data-content {
    max-width: 400px;
    margin: 0 auto;
}

.no-data-icon {
    font-size: 4rem;
    color: #e0e0e0;
    margin-bottom: 1rem;
}

.no-data-content h3 {
    color: #495057;
    margin-bottom: 0.5rem;
}

.no-data-content p {
    color: #6c757d;
    margin-bottom: 1.5rem;
}

/* Empty State */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    text-align: center;
    background: #f8f9fa;
    border-radius: 8px;
    margin: 1rem 0;
}

.empty-icon {
    font-size: 3.5rem;
    color: #adb5bd;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #495057;
    margin-bottom: 0.5rem;
    font-size: 1.25rem;
}

.empty-state p {
    color: #6c757d;
    margin: 0;
    max-width: 400px;
}

/* New Submission Badge */
.new-submission-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 12px;
    height: 12px;
    background-color: #f44336;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.1);
}

.report-status {
    position: relative;
    display: inline-block;
}

/* Notification Icon Styles */
.notification-icon-container {
    position: relative;
    display: inline-block;
    margin-right: 1rem;
}

.notification-btn {
    color: #6c757d !important;
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.notification-btn:hover {
    color: #495057 !important;
    transform: scale(1.1);
}

.notification-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

/* Submitted Reports Section Styles */
.submitted-reports-section {
    margin: 2rem 0;
    padding: 2rem;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.section-header {
    text-align: center;
    margin-bottom: 2rem;
}

.section-title {
    color: white;
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.section-subtitle {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    margin: 0;
}

.submitted-reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.submitted-report-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.submitted-report-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.report-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f1f3f4;
}

.report-section-name {
    font-size: 1.3rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0 0 0.5rem 0;
}

.report-teacher {
    font-size: 1rem;
    color: #7f8c8d;
    margin: 0 0 0.25rem 0;
    font-weight: 500;
}

.report-month {
    font-size: 0.9rem;
    color: #95a5a6;
    margin: 0;
}

.report-status-badge {
    flex-shrink: 0;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.submitted {
    background: linear-gradient(135deg, #ffc107, #fd7e14);
    color: #212529;
}

.status-badge.approved {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
}

.status-badge.rejected {
    background: linear-gradient(135deg, #dc3545, #e83e8c);
    color: white;
}

.status-badge.reviewed {
    background: linear-gradient(135deg, #17a2b8, #6f42c1);
    color: white;
}

.report-card-details {
    margin-bottom: 1.5rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    font-size: 0.9rem;
    color: #495057;
}

.detail-item:last-child {
    margin-bottom: 0;
}

.detail-item i {
    color: #6c757d;
    font-size: 1rem;
    width: 16px;
    text-align: center;
}

.report-card-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}

.report-card-actions .p-button {
    font-size: 0.85rem;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.report-card-actions .p-button:hover {
    transform: translateY(-1px);
}

/* Responsive design for submitted reports */
@media (max-width: 768px) {
    .submitted-reports-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .report-card-header {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .report-card-actions {
        justify-content: center;
    }

    .section-title {
        font-size: 1.8rem;
    }
}
</style>
