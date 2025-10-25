<template>
    <div class="summary-report-container">
        <!-- Header Banner (No Print) -->
        <div class="no-print mb-6 bg-gradient-to-r from-blue-600 to-blue-400 rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="bg-white bg-opacity-20 rounded-full p-3">
                        <i class="pi pi-chart-bar text-white text-3xl"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold text-white mb-1">Summary Attendance Management</h1>
                        <p class="text-blue-100 text-sm">{{ sectionName }} - {{ gradeLevel }}</p>
                    </div>
                </div>
                <div class="bg-white bg-opacity-20 rounded-lg px-4 py-2">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-users text-white text-lg"></i>
                        <span class="text-white font-semibold">Total Students: {{ students.length }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header Controls (No Print) -->
        <div class="no-print mb-4 bg-white p-4 rounded-lg shadow">
            <div class="flex flex-wrap items-center gap-3">
                <!-- Section Selector (for teachers with multiple sections) -->
                <div v-if="teacherSections.length > 1" class="flex items-center gap-2 border-2 border-purple-400 rounded-lg px-4 py-3 bg-purple-50 shadow-sm">
                    <i class="pi pi-users text-purple-600 text-lg"></i>
                    <label class="text-base font-bold text-purple-900">Section:</label>
                    <Dropdown v-model="sectionId" :options="teacherSections" optionLabel="name" optionValue="id" placeholder="Select Section" @change="onSectionChange" class="w-48">
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="flex items-center gap-2">
                                <i class="pi pi-users text-purple-600"></i>
                                <span class="font-semibold text-sm">{{ getSectionName(slotProps.value) }}</span>
                            </div>
                            <span v-else class="text-gray-500">{{ slotProps.placeholder }}</span>
                        </template>
                        <template #option="slotProps">
                            <div class="flex items-center gap-2 p-2 hover:bg-purple-50">
                                <i class="pi pi-users text-purple-600"></i>
                                <div>
                                    <div class="font-semibold text-sm">{{ slotProps.option.name }}</div>
                                    <div class="text-xs text-gray-600">{{ slotProps.option.grade_level }}</div>
                                </div>
                            </div>
                        </template>
                    </Dropdown>
                </div>

                <!-- School Year Selector -->
                <div class="flex items-center gap-2 border-2 rounded-lg px-4 py-3 shadow-sm transition-all" :class="selectedSchoolYear ? 'border-purple-400 bg-purple-50' : 'border-gray-300 bg-gray-50'">
                    <i class="pi pi-calendar-plus text-lg" :class="selectedSchoolYear ? 'text-purple-600' : 'text-gray-400'"></i>
                    <label class="text-base font-bold" :class="selectedSchoolYear ? 'text-purple-900' : 'text-gray-700'">School Year:</label>
                    <Dropdown v-model="selectedSchoolYear" :options="schoolYears" placeholder="Select School Year" @change="onSchoolYearChange" class="w-48" showClear>
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="flex items-center gap-2">
                                <i class="pi pi-calendar text-purple-600"></i>
                                <span class="font-semibold text-sm">{{ slotProps.value }}</span>
                            </div>
                            <span v-else class="text-gray-500">{{ slotProps.placeholder }}</span>
                        </template>
                    </Dropdown>
                </div>

                <!-- Quarter Selector (User-Friendly for Elderly Teachers) -->
                <div class="flex items-center gap-2 border-2 rounded-lg px-4 py-3 shadow-sm transition-all" :class="isQuarterMode ? 'border-blue-400 bg-blue-50' : 'border-gray-300 bg-gray-50'">
                    <i class="pi pi-calendar text-lg" :class="isQuarterMode ? 'text-blue-600' : 'text-gray-400'"></i>
                    <label class="text-base font-bold" :class="isQuarterMode ? 'text-blue-900' : 'text-gray-700'">School Quarter:</label>
                    <Dropdown v-model="selectedQuarter" :options="quarters" optionLabel="label" placeholder="Choose Quarter" @change="onQuarterChange" class="w-64" showClear>
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="flex items-center gap-2">
                                <i class="pi pi-book text-blue-600"></i>
                                <span class="font-semibold text-sm">{{ slotProps.value.displayLabel || slotProps.value.label }}</span>
                            </div>
                            <span v-else class="text-gray-500">{{ slotProps.placeholder }}</span>
                        </template>
                        <template #option="slotProps">
                            <div class="flex items-center gap-2 p-2 hover:bg-blue-50">
                                <i class="pi pi-calendar text-blue-600"></i>
                                <div>
                                    <div class="font-semibold text-sm">{{ slotProps.option.label }}</div>
                                    <div class="text-xs text-gray-600">{{ slotProps.option.dateRange }}</div>
                                </div>
                            </div>
                        </template>
                    </Dropdown>
                </div>

                <!-- OR Custom Date Range -->
                <div class="flex items-center gap-2 border-2 rounded-lg px-3 py-2 shadow-sm transition-all" :class="isCustomDateMode ? 'border-green-400 bg-green-50' : 'border-gray-300 bg-gray-50'">
                    <label class="text-sm font-bold" :class="isCustomDateMode ? 'text-green-700' : 'text-gray-700'">📅 From:</label>
                    <Calendar v-model="startDate" dateFormat="mm/dd/yy" placeholder="Start Date" @date-select="onDateRangeChange" class="w-40" showIcon />
                    <span class="font-bold" :class="isCustomDateMode ? 'text-green-600' : 'text-gray-500'">to</span>
                    <label class="text-sm font-bold" :class="isCustomDateMode ? 'text-green-700' : 'text-gray-700'">📅 To:</label>
                    <Calendar v-model="endDate" dateFormat="mm/dd/yy" placeholder="End Date" @date-select="onDateRangeChange" class="w-40" showIcon />
                </div>
            </div>
        </div>

        <!-- Report Card -->
        <div class="report-card bg-white rounded-lg shadow-lg p-8">
            <!-- Header Section with Logos -->
            <div class="report-header">
                <div class="header-logo-left">
                    <img src="/demo/images/logo.png" alt="School Logo" class="logo-large" />
                </div>
                <div class="header-center">
                    <h2 class="report-title">Summary Attendance Report of Learners</h2>
                    <p class="report-subtitle">{{ reportSubtitle }}</p>
                </div>
                <div class="header-logo-right">
                    <img src="/demo/images/deped-logo.png" alt="DepEd Logo" class="logo-large" />
                </div>
            </div>

            <!-- School Information -->
            <div class="school-info-section">
                <div class="info-row">
                    <div class="info-field">
                        <label>Name of School:</label>
                        <input type="text" value="Naawan Central School" class="input-compact" readonly />
                    </div>
                    <div class="info-field">
                        <label>Grade Level:</label>
                        <input type="text" :value="gradeLevel" class="input-compact" readonly />
                    </div>
                    <div class="info-field">
                        <label>Section:</label>
                        <input type="text" :value="sectionName" class="input-compact" readonly />
                    </div>
                </div>
            </div>

            <!-- Summary Attendance Table -->
            <div class="summary-table-section mb-6">
                <table class="summary-table">
                    <thead>
                        <tr>
                            <th rowspan="2" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold">No.</th>
                            <th rowspan="2" class="border-2 border-gray-900 bg-gray-100 p-2 text-left font-bold">Learner's Name<br />(Last Name, First Name, Middle Name)</th>
                            <th colspan="4" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold">Attendance Summary</th>
                            <th rowspan="2" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold">
                                Attendance Rate<br />
                                <span class="text-xs font-normal text-gray-600">(Out of {{ schoolDays }} sessions)</span>
                            </th>
                            <th rowspan="2" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold">Remarks</th>
                            <th rowspan="2" class="border-2 border-gray-900 bg-gray-100 p-2 text-center font-bold no-print">Actions</th>
                        </tr>
                        <tr>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">Present</th>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">Late</th>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">Absent</th>
                            <th class="border-2 border-gray-900 bg-gray-100 p-1 text-center text-xs font-bold">Excused</th>
                        </tr>
                    </thead>
                    <tbody v-if="!loading && students.length > 0">
                        <!-- Male Students -->
                        <tr>
                            <td colspan="9" class="border-2 border-gray-900 bg-blue-100 p-2 font-bold text-sm">MALE STUDENTS</td>
                        </tr>
                        <tr v-for="(student, index) in maleStudents" :key="student.id" class="hover:bg-blue-50 transition-colors">
                            <td class="border border-gray-900 p-2 text-center text-sm">{{ index + 1 }}</td>
                            <td class="border border-gray-900 p-2 text-sm">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-user text-blue-600"></i>
                                    <span>{{ student.name }}</span>
                                </div>
                            </td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'present') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'late') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'absent') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'excused') }}</td>
                            <td
                                class="border border-gray-900 p-1 text-center text-sm font-bold"
                                :class="getAttendanceRateClass(schoolDays > 0 ? Math.round(((calculateStudentTotal(student, 'present') + calculateStudentTotal(student, 'late')) / schoolDays) * 100) : 0)"
                            >
                                {{ schoolDays > 0 ? Math.round(((calculateStudentTotal(student, 'present') + calculateStudentTotal(student, 'late')) / schoolDays) * 100) : 0 }}%
                            </td>
                            <td class="border border-gray-900 p-1 text-sm">{{ student.remarks || '-' }}</td>
                            <td class="border border-gray-900 p-2 text-center no-print">
                                <Button icon="pi pi-eye" label="View Details" @click="showStudentDetails(student)" class="p-button-sm p-button-info" v-tooltip.top="'View detailed attendance report'" />
                            </td>
                        </tr>
                        <!-- Female Students -->
                        <tr>
                            <td colspan="9" class="border-2 border-gray-900 bg-pink-100 p-2 font-bold text-sm">FEMALE STUDENTS</td>
                        </tr>
                        <tr v-for="(student, index) in femaleStudents" :key="student.id" class="hover:bg-pink-50 transition-colors">
                            <td class="border border-gray-900 p-2 text-center text-sm">{{ index + 1 }}</td>
                            <td class="border border-gray-900 p-2 text-sm">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-user text-pink-600"></i>
                                    <span>{{ student.name }}</span>
                                </div>
                            </td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'present') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'late') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'absent') }}</td>
                            <td class="border border-gray-900 p-1 text-center text-sm">{{ calculateStudentTotal(student, 'excused') }}</td>
                            <td
                                class="border border-gray-900 p-1 text-center text-sm font-bold"
                                :class="getAttendanceRateClass(schoolDays > 0 ? Math.round(((calculateStudentTotal(student, 'present') + calculateStudentTotal(student, 'late')) / schoolDays) * 100) : 0)"
                            >
                                {{ schoolDays > 0 ? Math.round(((calculateStudentTotal(student, 'present') + calculateStudentTotal(student, 'late')) / schoolDays) * 100) : 0 }}%
                            </td>
                            <td class="border border-gray-900 p-1 text-sm">{{ student.remarks || '-' }}</td>
                            <td class="border border-gray-900 p-2 text-center no-print">
                                <Button icon="pi pi-eye" label="View Details" @click="showStudentDetails(student)" class="p-button-sm p-button-info" v-tooltip.top="'View detailed attendance report'" />
                            </td>
                        </tr>
                        <!-- Total Row -->
                        <tr class="bg-gray-100">
                            <td colspan="2" class="border-2 border-gray-900 p-2 text-center font-bold">TOTAL</td>
                            <td class="border-2 border-gray-900 p-1 text-center font-bold">{{ totalPresent }}</td>
                            <td class="border-2 border-gray-900 p-1 text-center font-bold">{{ totalAbsent }}</td>
                            <td class="border-2 border-gray-900 p-1 text-center font-bold">{{ totalLate }}</td>
                            <td class="border-2 border-gray-900 p-1 text-center font-bold">{{ totalExcused }}</td>
                            <td class="border-2 border-gray-900 p-1 text-center font-bold">{{ averageAttendanceRate }}%</td>
                            <td class="border-2 border-gray-900 p-1"></td>
                            <td class="border-2 border-gray-900 p-1 no-print"></td>
                        </tr>
                    </tbody>
                    <tbody v-else-if="!loading && students.length === 0">
                        <tr>
                            <td colspan="9" class="border border-gray-900 p-8 text-center text-gray-500">
                                <i class="pi pi-info-circle text-4xl mb-2"></i>
                                <p>No student data available</p>
                            </td>
                        </tr>
                    </tbody>
                    <tbody v-else>
                        <tr>
                            <td colspan="9" class="border border-gray-900 p-8 text-center">
                                <ProgressSpinner style="width: 40px; height: 40px" strokeWidth="6" />
                                <p class="mt-2 text-gray-600">Loading data...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Close report-card -->

        <!-- Student Details Dialog - SF2 Style with Floating Navigation -->
        <div v-if="showDetailsDialog" class="dialog-wrapper">
            <!-- Floating Previous Button with Label -->
            <div v-if="hasPreviousStudent" class="floating-nav-container floating-nav-left">
                <Button icon="pi pi-chevron-left" @click="navigateToPreviousStudent" class="floating-nav-btn p-button-rounded p-button-primary" aria-label="Previous Student" />
                <span class="floating-nav-label">Previous<br />Student</span>
            </div>

            <!-- Floating Next Button with Label -->
            <div v-if="hasNextStudent" class="floating-nav-container floating-nav-right">
                <Button icon="pi pi-chevron-right" @click="navigateToNextStudent" class="floating-nav-btn p-button-rounded p-button-primary" aria-label="Next Student" />
                <span class="floating-nav-label">Next<br />Student</span>
            </div>
        </div>

        <Dialog v-model:visible="showDetailsDialog" :modal="true" :closable="false" :style="{ width: '90vw', maxWidth: '1200px' }" class="no-print sf2-dialog" @show="attachNavigationListeners" @hide="detachNavigationListeners">
            <template #header>
                <div class="w-full">
                    <!-- SF2 Header with Logos -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex-shrink-0">
                            <img src="/demo/images/logo.png" alt="School Logo" style="width: 60px; height: 60px" />
                        </div>
                        <div class="flex-grow text-center">
                            <h3 class="text-lg font-bold m-0 text-gray-800">Student Attendance Report of Learners</h3>
                        </div>
                        <div class="flex-shrink-0">
                            <img src="/demo/images/deped-logo.png" alt="DepEd Logo" style="width: 60px; height: 60px" />
                        </div>
                    </div>

                    <!-- School Information Grid -->
                    <div class="grid grid-cols-3 gap-3 mb-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-semibold text-gray-700 whitespace-nowrap">Student Name:</label>
                            <input type="text" :value="selectedStudent?.firstName + ' ' + selectedStudent?.lastName" class="flex-1 border border-gray-400 px-2 py-1 text-xs font-semibold" readonly />
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-semibold text-gray-700 whitespace-nowrap">School Year:</label>
                            <input type="text" value="2024-2025" class="flex-1 border border-gray-400 px-2 py-1 text-xs" readonly />
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-semibold text-gray-700 whitespace-nowrap">Report Period:</label>
                            <input type="text" :value="getMonthName()" class="flex-1 border border-gray-400 px-2 py-1 text-xs" readonly />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-semibold text-gray-700 whitespace-nowrap">LRN:</label>
                            <input type="text" :value="selectedStudent?.lrn || 'N/A'" class="flex-1 border border-gray-400 px-2 py-1 text-xs font-semibold" readonly />
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-semibold text-gray-700 whitespace-nowrap">Grade Level:</label>
                            <input type="text" :value="gradeLevel" class="flex-1 border border-gray-400 px-2 py-1 text-xs" readonly />
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-semibold text-gray-700 whitespace-nowrap">Section:</label>
                            <input type="text" :value="sectionName" class="flex-1 border border-gray-400 px-2 py-1 text-xs" readonly />
                        </div>
                    </div>
                </div>
            </template>

            <div v-if="selectedStudent" class="sf2-student-view">
                <!-- Student Info Row (like the red box in image 1) -->
                <div class="bg-gray-50 border-2 border-gray-400 mb-3">
                    <div class="grid grid-cols-4 gap-0">
                        <div class="border-r border-gray-400 p-2">
                            <label class="text-xs font-semibold text-gray-600 block">Student Name</label>
                            <p class="text-sm font-bold text-gray-900 m-0">{{ selectedStudent.lastName }}, {{ selectedStudent.firstName }}</p>
                        </div>
                        <div class="border-r border-gray-400 p-2">
                            <label class="text-xs font-semibold text-gray-600 block">Gender</label>
                            <p class="text-sm font-bold m-0" :class="selectedStudent.gender === 'Male' ? 'text-blue-700' : 'text-pink-700'">
                                <i class="pi" :class="selectedStudent.gender === 'Male' ? 'pi-mars' : 'pi-venus'"></i>
                                {{ selectedStudent.gender }}
                            </p>
                        </div>
                        <div class="border-r border-gray-400 p-2">
                            <label class="text-xs font-semibold text-gray-600 block">Attendance Rate</label>
                            <p class="text-sm font-bold text-green-700 m-0">{{ schoolDays > 0 ? Math.round(((calculateStudentTotal(selectedStudent, 'present') + calculateStudentTotal(selectedStudent, 'late')) / schoolDays) * 100) : 0 }}%</p>
                        </div>
                        <div class="p-2">
                            <label class="text-xs font-semibold text-gray-600 block">Status</label>
                            <p class="text-sm font-bold text-green-700 m-0">{{ selectedStudent.enrollment_status || 'active' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Daily Attendance Calendar -->
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border-2 border-gray-900">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="border-2 border-gray-900 p-2 text-xs font-bold" style="min-width: 80px">Date</th>
                                <th class="border-2 border-gray-900 p-2 text-xs font-bold" style="min-width: 80px">Day</th>
                                <th class="border-2 border-gray-900 p-2 text-xs font-bold" style="min-width: 100px">Status</th>
                                <th class="border-2 border-gray-900 p-2 text-xs font-bold">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="day in getStudentDailyAttendance(selectedStudent)"
                                :key="day.date"
                                :class="{
                                    'bg-green-50': day.status === 'present',
                                    'bg-red-50': day.status === 'absent',
                                    'bg-yellow-50': day.status === 'late',
                                    'bg-blue-50': day.status === 'excused',
                                    'bg-gray-50': !day.status
                                }"
                            >
                                <td class="border border-gray-900 p-2 text-sm text-center">{{ formatDate(day.date) }}</td>
                                <td class="border border-gray-900 p-2 text-sm text-center">{{ day.dayName }}</td>
                                <td class="border border-gray-900 p-2 text-center">
                                    <span v-if="day.status === 'present'" class="inline-flex items-center gap-1 px-3 py-1 bg-green-500 text-white rounded-full text-xs font-bold"> <i class="pi pi-check"></i> PRESENT </span>
                                    <span v-else-if="day.status === 'absent'" class="inline-flex items-center gap-1 px-3 py-1 bg-red-500 text-white rounded-full text-xs font-bold"> <i class="pi pi-times"></i> ABSENT </span>
                                    <span v-else-if="day.status === 'late'" class="inline-flex items-center gap-1 px-3 py-1 bg-yellow-500 text-white rounded-full text-xs font-bold"> <i class="pi pi-clock"></i> LATE </span>
                                    <span v-else-if="day.status === 'excused'" class="inline-flex items-center gap-1 px-3 py-1 bg-blue-500 text-white rounded-full text-xs font-bold"> <i class="pi pi-info-circle"></i> EXCUSED </span>
                                    <span v-else class="text-gray-400 text-xs">-</span>
                                </td>
                                <td class="border border-gray-900 p-2 text-sm">{{ day.remarks || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Summary Statistics -->
                <div class="grid grid-cols-4 gap-3 mt-4">
                    <div class="bg-green-50 p-3 rounded-lg border-2 border-green-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-green-700">Present</p>
                                <p class="text-2xl font-bold text-green-800">{{ calculateStudentTotal(selectedStudent, 'present') }}</p>
                            </div>
                            <i class="pi pi-check-circle text-3xl text-green-500"></i>
                        </div>
                    </div>

                    <div class="bg-red-50 p-3 rounded-lg border-2 border-red-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-red-700">Absent</p>
                                <p class="text-2xl font-bold text-red-800">{{ calculateStudentTotal(selectedStudent, 'absent') }}</p>
                            </div>
                            <i class="pi pi-times-circle text-3xl text-red-500"></i>
                        </div>
                    </div>

                    <div class="bg-yellow-50 p-3 rounded-lg border-2 border-yellow-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-yellow-700">Late</p>
                                <p class="text-2xl font-bold text-yellow-800">{{ calculateStudentTotal(selectedStudent, 'late') }}</p>
                            </div>
                            <i class="pi pi-clock text-3xl text-yellow-500"></i>
                        </div>
                    </div>

                    <div class="bg-blue-50 p-3 rounded-lg border-2 border-blue-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-blue-700">Excused</p>
                                <p class="text-2xl font-bold text-blue-800">{{ calculateStudentTotal(selectedStudent, 'excused') }}</p>
                            </div>
                            <i class="pi pi-info-circle text-3xl text-blue-500"></i>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <!-- Clean Footer with Centered Actions -->
                <div class="flex flex-col items-center gap-3 w-full bg-white pt-4 pb-2" style="position: relative; z-index: 10">
                    <!-- Student Counter -->
                    <div class="text-center mb-2">
                        <span class="text-sm font-semibold text-gray-700 bg-white px-3 py-1 rounded-full border border-gray-300"> Student {{ currentStudentIndex + 1 }} of {{ students.length }} </span>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-center items-center gap-3 flex-wrap">
                        <Button label="Print This Student" icon="pi pi-print" @click="printCurrentStudent" class="p-button-success" />
                        <Button label="Print All Students" icon="pi pi-file" @click="printAllStudents" class="p-button-info" />
                        <Button label="Close" icon="pi pi-times" @click="showDetailsDialog = false" class="p-button-secondary" />
                    </div>
                </div>
            </template>
        </Dialog>
    </div>
    <!-- Close summary-report-container -->
</template>

<script setup>
import TeacherAuthService from '@/services/TeacherAuthService';
import axios from 'axios';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import ProgressSpinner from 'primevue/progressspinner';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const toast = useToast();

// Get section ID from teacher's data
const sectionId = ref(null); // Will be loaded from teacher authentication
const teacherSections = ref([]);

const reportData = ref(null);
const loading = ref(false);
const selectedMonth = ref(null); // Will be set when user selects dates
const selectedQuarter = ref(null);
const sectionName = ref('Loading...');
const gradeLevel = ref('Loading...');

// Student details dialog
const showDetailsDialog = ref(false);
const selectedStudent = ref(null);

// School quarters - loaded from backend API
const allQuarters = ref([]); // All quarters from backend
const schoolYears = ref([]); // Unique school years
const selectedSchoolYear = ref(null); // Selected school year

// Filtered quarters based on selected school year
const quarters = computed(() => {
    if (!selectedSchoolYear.value) {
        return allQuarters.value;
    }
    return allQuarters.value.filter((q) => q.school_year === selectedSchoolYear.value);
});

// Date range for filtering - will be set from selected quarter
const startDate = ref(null);
const endDate = ref(null);

// Computed property to detect which mode is active
const isQuarterMode = computed(() => {
    return selectedQuarter.value !== null;
});

const isCustomDateMode = computed(() => {
    return !!startDate.value && !!endDate.value;
});

// Dynamic subtitle based on selected mode
const reportSubtitle = computed(() => {
    if (selectedQuarter.value) {
        return `(${selectedQuarter.value.label} - ${selectedQuarter.value.dateRange})`;
    } else if (startDate.value && endDate.value) {
        const formatDate = (date) => {
            return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        };
        return `(Custom Date Range: ${formatDate(startDate.value)} to ${formatDate(endDate.value)})`;
    }
    return '(Quarterly attendance summary for all students)';
});

// Computed properties using SF2 Report data structure
const students = computed(() => {
    if (!reportData.value?.students) return [];
    return reportData.value.students;
});

const maleStudents = computed(() => {
    if (!reportData.value?.students) return [];
    return reportData.value.students.filter((student) => student.gender === 'Male');
});

const femaleStudents = computed(() => {
    if (!reportData.value?.students) return [];
    return reportData.value.students.filter((student) => student.gender === 'Female');
});

const schoolDays = computed(() => {
    if (!reportData.value?.students || reportData.value.students.length === 0) return 0;
    if (!startDate.value || !endDate.value) return 0;

    const start = new Date(startDate.value);
    start.setHours(0, 0, 0, 0);
    const end = new Date(endDate.value);
    end.setHours(23, 59, 59, 999);

    // Count unique dates where ANY student has attendance data (sessions were recorded)
    const sessionDates = new Set();

    reportData.value.students.forEach((student) => {
        if (student.attendance_data) {
            Object.keys(student.attendance_data).forEach((dateStr) => {
                const dayDate = new Date(dateStr);
                dayDate.setHours(0, 0, 0, 0);

                // Only count if within date range and has a status
                if (dayDate >= start && dayDate <= end && student.attendance_data[dateStr]) {
                    sessionDates.add(dateStr);
                }
            });
        }
    });

    const count = sessionDates.size;
    console.log(`📅 Attendance sessions recorded: ${count}`);
    return count;
});

// Calculate totals from attendance_data within selected date range
const calculateStudentTotal = (student, status) => {
    // Debug logging for first student only to avoid console spam
    const isFirstStudent = students.value[0]?.id === student.id;

    if (isFirstStudent) {
        console.log(`🔍 [DEBUG] Calculating ${status} for ${student.firstName} ${student.lastName}`);
        console.log('  - Has attendance_data:', !!student.attendance_data);
        console.log('  - Attendance data keys:', student.attendance_data ? Object.keys(student.attendance_data).length : 0);
        console.log('  - Has days_in_month:', !!reportData.value?.days_in_month);
        console.log('  - Days count:', reportData.value?.days_in_month?.length || 0);
        console.log('  - Start date:', startDate.value);
        console.log('  - End date:', endDate.value);
    }

    if (!student.attendance_data || !reportData.value?.days_in_month) {
        if (isFirstStudent) console.log('  ❌ Missing attendance_data or days_in_month');
        return 0;
    }
    if (!startDate.value || !endDate.value) {
        if (isFirstStudent) console.log('  ❌ Missing start or end date');
        return 0;
    }

    let count = 0;
    const start = new Date(startDate.value);
    start.setHours(0, 0, 0, 0); // Reset time to start of day
    const end = new Date(endDate.value);
    end.setHours(23, 59, 59, 999); // Set time to end of day

    if (isFirstStudent) {
        console.log('  - Date range:', start.toISOString().split('T')[0], 'to', end.toISOString().split('T')[0]);
        console.log('  - Sample attendance data:', Object.entries(student.attendance_data).slice(0, 5));
    }

    reportData.value.days_in_month.forEach((day) => {
        const dayDate = new Date(day.date);
        dayDate.setHours(0, 0, 0, 0); // Reset time for comparison

        // Only count if the day is within the selected date range
        if (dayDate >= start && dayDate <= end) {
            const dayStatus = student.attendance_data[day.date];
            if (isFirstStudent && dayStatus) {
                console.log(`  - Day ${day.date}: status='${dayStatus}', looking for='${status}', match=${dayStatus === status}`);
            }
            if (dayStatus === status) {
                count++;
            }
        }
    });

    if (isFirstStudent) {
        console.log(`  ✅ Total ${status} count: ${count}`);
    }

    return count;
};

// Calculate total present
const totalPresent = computed(() => {
    return students.value.reduce((sum, student) => sum + calculateStudentTotal(student, 'present'), 0);
});

// Calculate total absent
const totalAbsent = computed(() => {
    return students.value.reduce((sum, student) => sum + calculateStudentTotal(student, 'absent'), 0);
});

// Calculate total late
const totalLate = computed(() => {
    return students.value.reduce((sum, student) => sum + calculateStudentTotal(student, 'late'), 0);
});

// Calculate total excused
const totalExcused = computed(() => {
    return students.value.reduce((sum, student) => sum + calculateStudentTotal(student, 'excused'), 0);
});

// Calculate average attendance rate
const averageAttendanceRate = computed(() => {
    if (students.value.length === 0 || !reportData.value?.days_in_month) return 0;

    const totalDays = reportData.value.days_in_month.length;
    if (totalDays === 0) return 0;

    const totalAttendanceRate = students.value.reduce((sum, student) => {
        const presentDays = calculateStudentTotal(student, 'present');
        const rate = (presentDays / totalDays) * 100;
        return sum + rate;
    }, 0);

    return Math.round(totalAttendanceRate / students.value.length);
});

// Fetch teacher's sections
const fetchTeacherSections = async () => {
    try {
        console.log('🔍 Fetching teacher sections from API...');
        const response = await axios.get('http://127.0.0.1:8000/api/teacher/sections');
        console.log('📥 Sections API response:', response.data);

        if (response.data.success && response.data.data.length > 0) {
            teacherSections.value = response.data.data;
            console.log('✅ Found sections:', teacherSections.value);

            // Use first section as default if no sectionId is set
            if (!sectionId.value) {
                sectionId.value = response.data.data[0].id;
                console.log('✅ Using first section ID:', sectionId.value);
                console.log('📋 Section details:', response.data.data[0]);
            }
            return true;
        }
        console.warn('⚠️ No sections found in response');
        return false;
    } catch (error) {
        console.error('❌ Error fetching teacher sections:', error);
        console.error('Error details:', error.response?.data || error.message);
        return false;
    }
};

// Load SF2 report data for ALL months in the selected date range
const loadAttendanceData = async () => {
    loading.value = true;
    console.log('🔄 Loading attendance data...');
    console.log('Current sectionId:', sectionId.value);
    console.log('Date range:', startDate.value, 'to', endDate.value);

    try {
        console.log('🎯 Using section ID:', sectionId.value);

        // Get all months in the date range
        const months = [];
        const start = new Date(startDate.value);
        const end = new Date(endDate.value);

        let current = new Date(start.getFullYear(), start.getMonth(), 1);
        const endMonth = new Date(end.getFullYear(), end.getMonth(), 1);

        while (current <= endMonth) {
            months.push({
                year: current.getFullYear(),
                month: current.getMonth() + 1,
                monthStr: `${current.getFullYear()}-${String(current.getMonth() + 1).padStart(2, '0')}`
            });
            current.setMonth(current.getMonth() + 1);
        }

        console.log('📅 Loading data for months:', months.map((m) => m.monthStr).join(', '));

        // Load data for all months and merge
        const allStudentsMap = new Map();
        const allDays = [];
        let sectionData = null;

        for (const monthInfo of months) {
            try {
                const apiUrl = `http://127.0.0.1:8000/api/teacher/reports/sf2/data/${sectionId.value}/${monthInfo.monthStr}`;
                console.log('🌐 Loading:', apiUrl);

                const response = await axios.get(apiUrl);

                if (response.data.success) {
                    const data = response.data.data;

                    // Store section data (same for all months)
                    if (!sectionData) {
                        sectionData = data.section;
                    }

                    // Merge days
                    allDays.push(...data.days_in_month);

                    // Merge student data
                    data.students.forEach((student) => {
                        const studentId = student.id; // API returns 'id', not 'student_id'

                        if (!allStudentsMap.has(studentId)) {
                            // Create new student entry with their attendance data
                            allStudentsMap.set(studentId, {
                                ...student,
                                attendance_data: { ...student.attendance_data } // Copy attendance data
                            });
                        } else {
                            // Merge attendance data for existing student
                            const existingStudent = allStudentsMap.get(studentId);
                            Object.assign(existingStudent.attendance_data, student.attendance_data);
                        }
                    });
                }
            } catch (monthError) {
                console.warn(`⚠️ Could not load data for ${monthInfo.monthStr}:`, monthError.message);
            }
        }

        // Build final report data with remarks for dropped out/transferred students
        const studentsWithRemarks = Array.from(allStudentsMap.values()).map((student) => {
            let remarks = '';

            // Reason code mapping (DepEd guidelines)
            const reasonMap = {
                a1: 'a.1 Had to take care of siblings',
                a2: 'a.2 Early marriage/pregnancy',
                a3: "a.3 Parents' attitude toward schooling",
                a4: 'a.4 Family problems',
                b1: 'b.1 Illness',
                b2: 'b.2 Disease',
                b3: 'b.3 Disability',
                b4: 'b.4 Death',
                c1: 'c.1 Lack of personal interest',
                c2: 'c.2 Employment/Job',
                c3: 'c.3 Got married',
                d1: 'd.1 Distance of school from home',
                d2: 'd.2 High cost of education',
                d3: 'd.3 Family income is insufficient',
                e1: 'e.1 Transferred to another school',
                e2: 'e.2 Moved to another place',
                f1: 'f.1 Others (Specify)'
            };

            // Generate remarks based on enrollment status
            if (student.enrollment_status === 'dropped_out') {
                const reasonText = student.dropout_reason ? reasonMap[student.dropout_reason] || student.dropout_reason : '';
                remarks = reasonText ? `Dropped Out: ${reasonText}` : 'Dropped Out';
            } else if (student.enrollment_status === 'transferred_out') {
                const reasonText = student.dropout_reason ? reasonMap[student.dropout_reason] || student.dropout_reason : '';
                remarks = reasonText ? `Transferred Out: ${reasonText}` : 'Transferred Out';
            } else if (student.enrollment_status === 'withdrawn') {
                const reasonText = student.dropout_reason ? reasonMap[student.dropout_reason] || student.dropout_reason : '';
                remarks = reasonText ? `Withdrawn: ${reasonText}` : 'Withdrawn';
            }

            return {
                ...student,
                remarks: remarks || student.remarks || '-'
            };
        });

        reportData.value = {
            section: sectionData,
            month: `${start.getFullYear()}-${String(start.getMonth() + 1).padStart(2, '0')}`,
            month_name: `${start.toLocaleString('default', { month: 'long' })} ${start.getFullYear()} - ${end.toLocaleString('default', { month: 'long' })} ${end.getFullYear()}`,
            students: studentsWithRemarks,
            days_in_month: allDays,
            summary: {
                total_students: allStudentsMap.size,
                total_days: allDays.length
            }
        };

        console.log('📊 Loaded attendance data for', months.length, 'months');
        console.log('👥 Total students:', allStudentsMap.size);
        console.log('📅 Total days:', allDays.length);
    } catch (error) {
        console.error('❌ Error loading attendance data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to load attendance data',
            life: 3000
        });
    } finally {
        loading.value = false;
        console.log('✅ Loading complete. Students:', students.value.length);
    }
};

const printReport = () => {
    window.print();
};

const exportExcel = () => {
    toast.add({
        severity: 'info',
        summary: 'Export',
        detail: 'Excel export functionality coming soon',
        life: 3000
    });
};

// Show student details dialog
const showStudentDetails = (student) => {
    selectedStudent.value = student;
    showDetailsDialog.value = true;
    console.log('📋 Showing details for:', student.name);
};

// Current student index
const currentStudentIndex = computed(() => {
    if (!selectedStudent.value || !students.value.length) return -1;
    return students.value.findIndex((s) => s.id === selectedStudent.value.id);
});

// Check if there's a previous student
const hasPreviousStudent = computed(() => {
    return currentStudentIndex.value > 0;
});

// Check if there's a next student
const hasNextStudent = computed(() => {
    return currentStudentIndex.value >= 0 && currentStudentIndex.value < students.value.length - 1;
});

// Navigate to previous student
const navigateToPreviousStudent = () => {
    if (hasPreviousStudent.value) {
        selectedStudent.value = students.value[currentStudentIndex.value - 1];
        console.log('⬅️ Previous student:', selectedStudent.value.name);
    }
};

// Navigate to next student
const navigateToNextStudent = () => {
    if (hasNextStudent.value) {
        selectedStudent.value = students.value[currentStudentIndex.value + 1];
        console.log('➡️ Next student:', selectedStudent.value.name);
    }
};

// Handle keyboard navigation
const handleKeyDown = (event) => {
    if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
        event.preventDefault();
        navigateToPreviousStudent();
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
        event.preventDefault();
        navigateToNextStudent();
    } else if (event.key === 'Escape') {
        showDetailsDialog.value = false;
    }
};

// Mouse wheel navigation REMOVED - users found it confusing
// Now only keyboard arrows and floating buttons work

// Attach event listeners when dialog opens
const attachNavigationListeners = () => {
    document.addEventListener('keydown', handleKeyDown);
    // Wheel navigation removed - only keyboard arrows work now
};

// Detach event listeners when dialog closes
const detachNavigationListeners = () => {
    document.removeEventListener('keydown', handleKeyDown);
    // Wheel listener removed
};

// Get daily attendance for a specific student
const getStudentDailyAttendance = (student) => {
    if (!student || !reportData.value?.days_in_month) return [];

    const dailyAttendance = [];
    const start = new Date(startDate.value);
    start.setHours(0, 0, 0, 0);
    const end = new Date(endDate.value);
    end.setHours(23, 59, 59, 999);

    reportData.value.days_in_month.forEach((day) => {
        const dayDate = new Date(day.date);
        dayDate.setHours(0, 0, 0, 0);

        // Only include days within the selected date range
        if (dayDate >= start && dayDate <= end) {
            const status = student.attendance_data ? student.attendance_data[day.date] : null;
            dailyAttendance.push({
                date: day.date,
                dayName: day.dayName || new Date(day.date).toLocaleDateString('en-US', { weekday: 'short' }),
                status: status,
                remarks: status ? getStatusRemarks(status) : ''
            });
        }
    });

    return dailyAttendance;
};

// Get remarks based on status
const getStatusRemarks = (status) => {
    const remarks = {
        present: 'Attended class',
        absent: 'Did not attend',
        late: 'Arrived late',
        excused: 'Excused absence'
    };
    return remarks[status] || '';
};

// Format date for display
const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

// Print current student's attendance
const printCurrentStudent = () => {
    console.log('🖨️ Printing current student:', selectedStudent.value?.name);

    // Hide dialog temporarily
    const wasVisible = showDetailsDialog.value;
    showDetailsDialog.value = false;

    // Create a new window for printing
    const printWindow = window.open('', '_blank');

    // Generate HTML content for the current student
    const studentData = selectedStudent.value;
    const dailyAttendance = getStudentDailyAttendance(studentData);

    const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Student Attendance - ${studentData.firstName} ${studentData.lastName}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .header img { width: 60px; height: 60px; }
                .header h2 { text-align: center; flex-grow: 1; margin: 0; }
                .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
                .info-field { border: 1px solid #666; padding: 5px; }
                .info-field label { font-weight: bold; font-size: 10px; display: block; }
                .info-field input { border: none; width: 100%; font-size: 12px; }
                .student-info { border: 2px solid #666; padding: 10px; margin-bottom: 20px; display: grid; grid-template-columns: repeat(4, 1fr); }
                .student-info div { border-right: 1px solid #666; padding: 5px; }
                .student-info div:last-child { border-right: none; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #333; padding: 8px; text-align: center; font-size: 11px; }
                th { background-color: #f0f0f0; font-weight: bold; }
                .present { background-color: #d4edda; }
                .absent { background-color: #f8d7da; }
                .late { background-color: #fff3cd; }
                .excused { background-color: #d1ecf1; }
                .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
                .summary-card { border: 2px solid #666; padding: 10px; text-align: center; }
                .summary-card h4 { margin: 0 0 5px 0; font-size: 12px; }
                .summary-card p { margin: 0; font-size: 24px; font-weight: bold; }
                @media print { body { padding: 10px; } }
            </style>
        </head>
        <body>
            <div class="header">
                <img src="/demo/images/logo.png" alt="Logo" />
                <h2>Student Attendance Report of Learners</h2>
                <img src="/demo/images/deped-logo.png" alt="DepEd" />
            </div>

            <div class="info-grid">
                <div class="info-field">
                    <label>Student Name:</label>
                    <input type="text" value="${studentData.firstName} ${studentData.lastName}" readonly />
                </div>
                <div class="info-field">
                    <label>School Year:</label>
                    <input type="text" value="2024-2025" readonly />
                </div>
                <div class="info-field">
                    <label>Report for the Month of:</label>
                    <input type="text" value="${getMonthName()}" readonly />
                </div>
                <div class="info-field">
                    <label>LRN:</label>
                    <input type="text" value="${studentData.lrn || 'N/A'}" readonly />
                </div>
                <div class="info-field">
                    <label>Grade Level:</label>
                    <input type="text" value="${gradeLevel.value}" readonly />
                </div>
                <div class="info-field">
                    <label>Section:</label>
                    <input type="text" value="${sectionName.value}" readonly />
                </div>
            </div>

            <div class="student-info">
                <div>
                    <label style="font-size: 10px; font-weight: bold;">Student Name</label>
                    <p style="margin: 0; font-size: 12px;">${studentData.lastName}, ${studentData.firstName}</p>
                </div>
                <div>
                    <label style="font-size: 10px; font-weight: bold;">Gender</label>
                    <p style="margin: 0; font-size: 12px;">${studentData.gender}</p>
                </div>
                <div>
                    <label style="font-size: 10px; font-weight: bold;">Attendance Rate</label>
                    <p style="margin: 0; font-size: 12px;">${Math.round((calculateStudentTotal(studentData, 'present') / schoolDays.value) * 100)}%</p>
                </div>
                <div>
                    <label style="font-size: 10px; font-weight: bold;">Status</label>
                    <p style="margin: 0; font-size: 12px;">${studentData.enrollment_status || 'active'}</p>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    ${dailyAttendance
                        .map(
                            (day) => `
                        <tr class="${day.status || ''}">
                            <td>${formatDate(day.date)}</td>
                            <td>${day.dayName}</td>
                            <td>${day.status ? day.status.toUpperCase() : '-'}</td>
                            <td>${day.remarks || '-'}</td>
                        </tr>
                    `
                        )
                        .join('')}
                </tbody>
            </table>

            <div class="summary">
                <div class="summary-card" style="border-color: #28a745;">
                    <h4>Present</h4>
                    <p style="color: #28a745;">${calculateStudentTotal(studentData, 'present')}</p>
                </div>
                <div class="summary-card" style="border-color: #dc3545;">
                    <h4>Absent</h4>
                    <p style="color: #dc3545;">${calculateStudentTotal(studentData, 'absent')}</p>
                </div>
                <div class="summary-card" style="border-color: #ffc107;">
                    <h4>Late</h4>
                    <p style="color: #ffc107;">${calculateStudentTotal(studentData, 'late')}</p>
                </div>
                <div class="summary-card" style="border-color: #17a2b8;">
                    <h4>Excused</h4>
                    <p style="color: #17a2b8;">${calculateStudentTotal(studentData, 'excused')}</p>
                </div>
            </div>
        </body>
        </html>
    `;

    printWindow.document.write(htmlContent);
    printWindow.document.close();

    // Wait for content to load then print
    printWindow.onload = () => {
        printWindow.print();
        printWindow.onafterprint = () => {
            printWindow.close();
            showDetailsDialog.value = wasVisible;
        };
    };
};

// Print all students
const printAllStudents = () => {
    console.log('🖨️ Printing all students...');

    toast.add({
        severity: 'info',
        summary: 'Preparing Print',
        detail: `Generating report for ${students.value.length} students...`,
        life: 3000
    });

    // Hide dialog temporarily
    const wasVisible = showDetailsDialog.value;
    showDetailsDialog.value = false;

    // Create a new window for printing
    const printWindow = window.open('', '_blank');

    // Generate HTML content for all students
    let allStudentsHtml = '';

    students.value.forEach((student, index) => {
        const dailyAttendance = getStudentDailyAttendance(student);
        const pageBreak = index < students.value.length - 1 ? 'page-break-after: always;' : '';

        allStudentsHtml += `
            <div style="${pageBreak}">
                <div class="header">
                    <img src="/demo/images/logo.png" alt="Logo" />
                    <h2>Student Attendance Report of Learners</h2>
                    <img src="/demo/images/deped-logo.png" alt="DepEd" />
                </div>

                <div class="info-grid">
                    <div class="info-field">
                        <label>Student Name:</label>
                        <input type="text" value="${student.firstName} ${student.lastName}" readonly />
                    </div>
                    <div class="info-field">
                        <label>School Year:</label>
                        <input type="text" value="2024-2025" readonly />
                    </div>
                    <div class="info-field">
                        <label>Report for the Month of:</label>
                        <input type="text" value="${getMonthName()}" readonly />
                    </div>
                    <div class="info-field">
                        <label>LRN:</label>
                        <input type="text" value="${student.lrn || 'N/A'}" readonly />
                    </div>
                    <div class="info-field">
                        <label>Grade Level:</label>
                        <input type="text" value="${gradeLevel.value}" readonly />
                    </div>
                    <div class="info-field">
                        <label>Section:</label>
                        <input type="text" value="${sectionName.value}" readonly />
                    </div>
                </div>

                <div class="student-info">
                    <div>
                        <label style="font-size: 10px; font-weight: bold;">Student Name</label>
                        <p style="margin: 0; font-size: 12px;">${student.lastName}, ${student.firstName}</p>
                    </div>
                    <div>
                        <label style="font-size: 10px; font-weight: bold;">Gender</label>
                        <p style="margin: 0; font-size: 12px;">${student.gender}</p>
                    </div>
                    <div>
                        <label style="font-size: 10px; font-weight: bold;">Attendance Rate</label>
                        <p style="margin: 0; font-size: 12px;">${Math.round((calculateStudentTotal(student, 'present') / schoolDays.value) * 100)}%</p>
                    </div>
                    <div>
                        <label style="font-size: 10px; font-weight: bold;">Status</label>
                        <p style="margin: 0; font-size: 12px;">${student.enrollment_status || 'active'}</p>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${dailyAttendance
                            .map(
                                (day) => `
                            <tr class="${day.status || ''}">
                                <td>${formatDate(day.date)}</td>
                                <td>${day.dayName}</td>
                                <td>${day.status ? day.status.toUpperCase() : '-'}</td>
                                <td>${day.remarks || '-'}</td>
                            </tr>
                        `
                            )
                            .join('')}
                    </tbody>
                </table>

                <div class="summary">
                    <div class="summary-card" style="border-color: #28a745;">
                        <h4>Present</h4>
                        <p style="color: #28a745;">${calculateStudentTotal(student, 'present')}</p>
                    </div>
                    <div class="summary-card" style="border-color: #dc3545;">
                        <h4>Absent</h4>
                        <p style="color: #dc3545;">${calculateStudentTotal(student, 'absent')}</p>
                    </div>
                    <div class="summary-card" style="border-color: #ffc107;">
                        <h4>Late</h4>
                        <p style="color: #ffc107;">${calculateStudentTotal(student, 'late')}</p>
                    </div>
                    <div class="summary-card" style="border-color: #17a2b8;">
                        <h4>Excused</h4>
                        <p style="color: #17a2b8;">${calculateStudentTotal(student, 'excused')}</p>
                    </div>
                </div>
            </div>
        `;
    });

    const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>All Students Attendance Report</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .header img { width: 60px; height: 60px; }
                .header h2 { text-align: center; flex-grow: 1; margin: 0; }
                .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
                .info-field { border: 1px solid #666; padding: 5px; }
                .info-field label { font-weight: bold; font-size: 10px; display: block; }
                .info-field input { border: none; width: 100%; font-size: 12px; }
                .student-info { border: 2px solid #666; padding: 10px; margin-bottom: 20px; display: grid; grid-template-columns: repeat(4, 1fr); }
                .student-info div { border-right: 1px solid #666; padding: 5px; }
                .student-info div:last-child { border-right: none; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #333; padding: 8px; text-align: center; font-size: 11px; }
                th { background-color: #f0f0f0; font-weight: bold; }
                .present { background-color: #d4edda; }
                .absent { background-color: #f8d7da; }
                .late { background-color: #fff3cd; }
                .excused { background-color: #d1ecf1; }
                .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 30px; }
                .summary-card { border: 2px solid #666; padding: 10px; text-align: center; }
                .summary-card h4 { margin: 0 0 5px 0; font-size: 12px; }
                .summary-card p { margin: 0; font-size: 24px; font-weight: bold; }
                @media print { body { padding: 10px; } }
            </style>
        </head>
        <body>
            ${allStudentsHtml}
        </body>
        </html>
    `;

    printWindow.document.write(htmlContent);
    printWindow.document.close();

    // Wait for content to load then print
    printWindow.onload = () => {
        printWindow.print();
        printWindow.onafterprint = () => {
            printWindow.close();
            showDetailsDialog.value = wasVisible;
        };
    };
};

const getMonthName = () => {
    // Show date range instead of just one month
    if (!startDate.value || !endDate.value) return '';

    const months = ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'];

    const startMonth = months[startDate.value.getMonth()];
    const startDay = startDate.value.getDate();
    const startYear = startDate.value.getFullYear();

    const endMonth = months[endDate.value.getMonth()];
    const endDay = endDate.value.getDate();
    const endYear = endDate.value.getFullYear();

    // If same month and year, show: "JUNE 24-29, 2025"
    if (startMonth === endMonth && startYear === endYear) {
        return `${startMonth} ${startDay}-${endDay}, ${startYear}`;
    }

    // If different months, show: "JUNE 24 - AUGUST 29, 2025"
    if (startYear === endYear) {
        return `${startMonth} ${startDay} - ${endMonth} ${endDay}, ${startYear}`;
    }

    // If different years, show full dates: "JUNE 24, 2025 - AUGUST 29, 2026"
    return `${startMonth} ${startDay}, ${startYear} - ${endMonth} ${endDay}, ${endYear}`;
};

const getAttendanceRateClass = (rate) => {
    if (rate >= 95) return 'text-green-700';
    if (rate >= 85) return 'text-blue-700';
    if (rate >= 75) return 'text-yellow-700';
    return 'text-red-700';
};

const onDateRangeChange = () => {
    // Don't clear quarter - let both work independently

    // Only load if both dates are selected
    if (startDate.value && endDate.value) {
        // Validate that end date is after start date
        if (endDate.value < startDate.value) {
            toast.add({
                severity: 'warn',
                summary: 'Invalid Date Range',
                detail: 'End date must be after start date',
                life: 3000
            });
            return;
        }
        // Update selectedMonth based on date range
        selectedMonth.value = new Date(startDate.value);
        console.log('📅 Date range changed, loading data...');
        loadAttendanceData();
    } else {
        console.log('⏳ Waiting for both dates to be selected...');
    }
};

// Clear custom dates and go back to quarterly mode
const clearCustomDates = () => {
    // Reset to default first quarter dates
    startDate.value = new Date(2025, 5, 24); // June 24, 2025
    endDate.value = new Date(2025, 7, 29); // August 29, 2025

    // This will re-enable the quarter dropdown since selectedQuarter is null
    // User can now select a quarter again

    toast.add({
        severity: 'info',
        summary: 'Custom Dates Cleared',
        detail: 'You can now select a school quarter',
        life: 3000
    });

    // Don't reload data automatically - let user select a quarter
};

// Section change handler
const onSectionChange = () => {
    if (sectionId.value) {
        // Update section name and grade level
        const selectedSection = teacherSections.value.find((s) => s.id === sectionId.value);
        if (selectedSection) {
            sectionName.value = selectedSection.name;
            gradeLevel.value = selectedSection.grade_level || 'Unknown Grade';

            toast.add({
                severity: 'info',
                summary: '📚 Section Changed',
                detail: `Loading data for ${selectedSection.name}`,
                life: 3000
            });

            loadAttendanceData();
        }
    }
};

// Helper function to get section name by ID
const getSectionName = (id) => {
    const section = teacherSections.value.find((s) => s.id === id);
    return section ? section.name : 'Unknown';
};

// Quarter change handler
const onQuarterChange = () => {
    if (selectedQuarter.value) {
        startDate.value = selectedQuarter.value.startDate;
        endDate.value = selectedQuarter.value.endDate;
        selectedMonth.value = new Date(selectedQuarter.value.startDate);

        toast.add({
            severity: 'info',
            summary: '📚 Quarter Selected',
            detail: `Loading ${selectedQuarter.value.label} data (${selectedQuarter.value.dateRange})`,
            life: 3000
        });

        loadAttendanceData();
    }
};

// Load school quarters from backend API
async function loadQuarters() {
    try {
        // Get teacher ID from authentication
        const teacherData = TeacherAuthService.getTeacherData();
        const teacherId = teacherData?.teacher?.id;
        
        if (!teacherId) {
            console.error('❌ No teacher ID found');
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Unable to identify teacher. Please log in again.',
                life: 5000
            });
            return;
        }
        
        // Fetch only quarters this teacher has access to
        const response = await fetch(`http://127.0.0.1:8000/api/teachers/${teacherId}/quarters`);
        if (!response.ok) {
            throw new Error('Failed to load quarters');
        }

        const data = await response.json();
        
        // If no quarters, show message
        if (data.length === 0) {
            console.log('ℹ️ No quarters available for this teacher');
            toast.add({
                severity: 'info',
                summary: 'No Quarters Available',
                detail: 'You have not been granted access to any school quarters yet. Please contact the admin.',
                life: 5000
            });
            return;
        }

        // Extract unique school years
        const uniqueYears = [...new Set(data.map((q) => q.school_year))];
        schoolYears.value = uniqueYears.sort().reverse(); // Sort descending (newest first)

        // Transform backend data to match the format expected by the dropdown
        allQuarters.value = data.map((quarter) => {
            const startDate = new Date(quarter.start_date);
            const endDate = new Date(quarter.end_date);

            // Format date range for display - compact format like Picture 1
            const formatShortDate = (date) => {
                const month = date.toLocaleDateString('en-US', { month: 'short' });
                const day = date.getDate();
                const year = date.getFullYear();
                return `${month} ${day}, ${year}`;
            };

            const dateRange = `${formatShortDate(startDate)} - ${formatShortDate(endDate)}`;

            // Ensure quarter name includes "Quarter" if not already present
            const quarterName = quarter.quarter.includes('Quarter') ? quarter.quarter : `${quarter.quarter} Quarter`;

            return {
                label: quarterName, // Full label for dropdown options (e.g., "1st Quarter")
                displayLabel: quarterName, // Short label for selected value (e.g., "1st Quarter")
                value: quarter.id,
                dateRange: dateRange,
                startDate: startDate,
                endDate: endDate,
                quarter: quarter.quarter,
                school_year: quarter.school_year
            };
        });

        console.log('✅ Loaded', allQuarters.value.length, 'quarters from backend');
        console.log('📅 School years available:', schoolYears.value);

        // Auto-select the first (newest) school year
        if (schoolYears.value.length > 0) {
            selectedSchoolYear.value = schoolYears.value[0];
        }
    } catch (error) {
        console.error('❌ Error loading quarters:', error);
        quarters.value = [];
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'No school quarters available. Please contact admin to set up quarters.',
            life: 5000
        });
    }
}

// School Year change handler
function onSchoolYearChange() {
    // Clear selected quarter when school year changes
    selectedQuarter.value = null;
    startDate.value = null;
    endDate.value = null;

    console.log('📅 School year changed to:', selectedSchoolYear.value);
    console.log('📊 Quarters available:', quarters.value.length);
}

onMounted(async () => {
    console.log('🚀 Component mounted. Loading teacher data...');

    // Load school quarters first
    await loadQuarters();

    // Get teacher's section ID from authentication
    const teacherData = TeacherAuthService.getTeacherData();
    console.log('👨‍🏫 Teacher data:', teacherData);

    // Collect all sections this teacher handles
    const allSections = [];
    let homeroomSection = null;

    // Get homeroom section
    if (teacherData?.teacher?.homeroom_section) {
        homeroomSection = teacherData.teacher.homeroom_section;
        allSections.push(homeroomSection);
        console.log('✅ Found homeroom section in teacher object:', homeroomSection);
    } else if (teacherData?.assignments && teacherData.assignments.length > 0) {
        // Find homeroom assignment (subject_id is null)
        const homeroomAssignment = teacherData.assignments.find((a) => a.subject_id === null || a.subject_name === 'Homeroom');
        if (homeroomAssignment && homeroomAssignment.section) {
            homeroomSection = homeroomAssignment.section;
            allSections.push(homeroomSection);
            console.log('✅ Found homeroom section in assignments:', homeroomSection);
        }
    }

    // Get all other sections from assignments
    if (teacherData?.assignments && teacherData.assignments.length > 0) {
        teacherData.assignments.forEach((assignment) => {
            if (assignment.section && assignment.section.id !== homeroomSection?.id) {
                // Check if not already added
                if (!allSections.find((s) => s.id === assignment.section.id)) {
                    allSections.push(assignment.section);
                }
            }
        });
    }

    // Store all sections
    teacherSections.value = allSections;
    console.log(
        '📚 Teacher handles',
        allSections.length,
        'section(s):',
        allSections.map((s) => s.name)
    );

    if (homeroomSection) {
        sectionId.value = homeroomSection.id;
        sectionName.value = homeroomSection.name || 'Unknown Section';

        // Extract grade_level - it might be nested in a grade object or directly available
        if (homeroomSection.grade_level) {
            gradeLevel.value = homeroomSection.grade_level;
        } else if (homeroomSection.grade && homeroomSection.grade.name) {
            gradeLevel.value = homeroomSection.grade.name;
        } else if (homeroomSection.grade && homeroomSection.grade.grade_name) {
            gradeLevel.value = homeroomSection.grade.grade_name;
        } else {
            gradeLevel.value = 'Unknown Grade';
        }

        console.log('✅ Teacher section loaded:', {
            id: sectionId.value,
            name: sectionName.value,
            grade: gradeLevel.value,
            rawSection: homeroomSection
        });

        // Set default to First Quarter if available
        if (quarters.value.length > 0) {
            selectedQuarter.value = quarters.value[0];
            startDate.value = quarters.value[0].startDate;
            endDate.value = quarters.value[0].endDate;
            selectedMonth.value = quarters.value[0].startDate;

            // Auto-load First Quarter data
            toast.add({
                severity: 'success',
                summary: '✅ Ready',
                detail: `Loading ${quarters.value[0].label} attendance for ${sectionName.value}...`,
                life: 3000
            });

            loadAttendanceData();
        } else {
            // No quarters available
            toast.add({
                severity: 'info',
                summary: 'No Quarters Available',
                detail: 'Please contact admin to set up school quarters.',
                life: 5000
            });
        }
    } else {
        console.error('❌ No homeroom section found in teacher data');
        console.log('Available data:', {
            teacher: teacherData?.teacher,
            assignments: teacherData?.assignments
        });

        sectionName.value = 'No Section';
        gradeLevel.value = 'N/A';

        toast.add({
            severity: 'error',
            summary: '❌ Error',
            detail: 'Could not load your homeroom section. Please contact administrator.',
            life: 5000
        });
    }
});
</script>

<style scoped>
.summary-report-container {
    min-height: 100vh;
    background-color: #f8fafc;
    padding: 1rem;
}

/* Floating Navigation Buttons Wrapper */
.dialog-wrapper {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    pointer-events: none;
    z-index: 9999 !important; /* Higher than dialog (1100) */
    display: flex;
    align-items: center;
    justify-content: center;
}

.dialog-wrapper .floating-nav-container {
    pointer-events: auto !important;
    position: absolute !important;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    top: 50% !important;
    transform: translateY(-50%) !important;
    z-index: 1200 !important;
}

.dialog-wrapper .floating-nav-btn {
    width: 56px !important;
    height: 56px !important;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2) !important;
    transition: all 0.3s ease !important;
}

.dialog-wrapper .floating-nav-btn:hover {
    transform: scale(1.1) !important;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3) !important;
}

.dialog-wrapper .floating-nav-label {
    font-size: 11px;
    font-weight: 600;
    color: #495057;
    text-align: center;
    line-height: 1.2;
    background: white;
    padding: 4px 8px;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    white-space: nowrap;
}

.dialog-wrapper .floating-nav-left {
    left: calc(50% - 670px) !important;
}

.dialog-wrapper .floating-nav-right {
    right: calc(50% - 670px) !important;
}

/* Ensure buttons are visible on smaller screens */
@media (max-width: 1400px) {
    .dialog-wrapper .floating-nav-left {
        left: 10px !important;
    }

    .dialog-wrapper .floating-nav-right {
        right: 10px !important;
    }
}

/* Make sure dialog doesn't cover buttons */
:deep(.sf2-dialog) {
    z-index: 1100 !important;
}

/* Hide any scroll indicators in the dialog footer area */
:deep(.sf2-dialog .p-dialog-footer) {
    position: relative;
    z-index: 1300 !important;
    background: white;
    border-top: 1px solid #e5e7eb;
}

/* Hide scroll indicators that might overlap */
:deep(.sf2-dialog .p-dialog-content) {
    position: relative;
    z-index: 1;
}

/* Ensure footer is always on top */
:deep(.sf2-dialog .p-dialog-footer::before) {
    content: '';
    position: absolute;
    top: -20px;
    left: 0;
    right: 0;
    height: 20px;
    background: linear-gradient(to bottom, transparent, white);
    pointer-events: none;
    z-index: 1299;
}

.report-card {
    font-family: 'Arial', 'Calibri', sans-serif;
    line-height: 1.2;
}

/* Header Section */
.report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1rem 0;
}

.header-logo-left,
.header-logo-right {
    flex: 0 0 100px;
}

.header-center {
    flex: 1;
    text-align: center;
    padding: 0 2rem;
}

.logo-large {
    width: 90px;
    height: 90px;
    object-fit: contain;
}

.report-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    line-height: 1.4;
    color: #2c3e50;
}

.report-subtitle {
    margin: 0.25rem 0 0 0;
    font-size: 0.75rem;
    color: #666;
    font-style: italic;
}

/* School Info */
.school-info-section {
    margin-bottom: 1.5rem;
    background: white;
}

.info-row {
    display: flex;
    gap: 1rem;
    margin-bottom: 0.75rem;
    align-items: center;
}

.info-field {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}

.info-field label {
    font-weight: 600;
    white-space: nowrap;
    font-size: 0.85rem;
    color: #333;
}

.info-field input.input-compact {
    flex: 1;
    padding: 0.4rem 0.6rem;
    border: 1px solid #333;
    background: white;
    font-size: 0.85rem;
    border-radius: 0;
}

.info-field input.input-compact:focus {
    outline: none;
    border: 2px solid #4a90e2;
}

.border-circle {
    border-radius: 50%;
}

/* Summary Table */
.summary-table-section {
    overflow-x: auto;
}

.summary-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.summary-table th,
.summary-table td {
    border: 1px solid #1f2937;
    padding: 0.5rem;
}

.summary-table thead th {
    background-color: #f3f4f6;
    font-weight: 600;
    text-align: center;
}

.summary-table tbody tr:hover {
    background-color: #f9fafb;
}

/* Print Styles */
@media print {
    .no-print {
        display: none !important;
    }

    .summary-report-container {
        padding: 0;
        background: white;
    }

    .report-card {
        box-shadow: none;
    }

    .p-button,
    .p-dropdown {
        display: none !important;
    }
}
</style>
