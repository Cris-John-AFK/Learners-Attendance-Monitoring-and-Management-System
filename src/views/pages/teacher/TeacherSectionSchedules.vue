<template>
    <div class="teacher-schedules-container">
        <div class="card">
            <div class="card-header blue-gradient-header">
                <div class="header-content">
                    <div class="header-left">
                        <div class="icon-circle">
                            <i class="pi pi-calendar"></i>
                        </div>
                        <div class="header-text">
                            <h2 class="card-title">Teacher Schedule Management</h2>
                            <p class="card-subtitle">Naawan Central School</p>
                            <div class="total-teachers-badge">
                                <i class="pi pi-book"></i>
                                <span class="badge-label">Total Subject Assignments:</span>
                                <span class="badge-count">{{ teacherAssignments.length }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="header-right">
                        <Button label="Create Schedule" icon="pi pi-plus" severity="success" @click="openCreateScheduleModal" class="create-schedule-header-btn" />
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="text-center py-4">
                <ProgressSpinner />
                <p class="mt-2 text-gray-600">Loading your schedules...</p>
            </div>

            <!-- No Schedules State -->
            <div v-else-if="schedules.length === 0 && teacherAssignments.length === 0" class="text-center py-8">
                <i class="pi pi-calendar-times text-6xl text-gray-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">No Assignments Found</h3>
                <p class="text-gray-600 mb-4">You don't have any subject assignments yet.</p>
                <p class="text-sm text-gray-500">Please contact your administrator to assign you to sections and subjects.</p>
            </div>

            <!-- Assignments Without Schedules -->
            <div v-else-if="schedules.length === 0 && teacherAssignments.length > 0" class="assignments-without-schedules">
                <div class="assignments-grid">
                    <h4 class="text-lg font-semibold mb-4">Your Subject Assignments</h4>
                    <div class="grid">
                        <div v-for="assignment in teacherAssignments" :key="assignment.id" class="col-12 md:col-6 lg:col-4">
                            <div class="assignment-card">
                                <div class="assignment-header">
                                    <i class="pi pi-book text-blue-600 mr-2"></i>
                                    <span class="font-semibold">{{ assignment.subject_name }}</span>
                                </div>
                                <div class="assignment-section">
                                    <i class="pi pi-users text-green-600 mr-2"></i>
                                    <span>{{ assignment.section_name }}</span>
                                </div>
                                <div class="assignment-status">
                                    <Tag v-if="assignment.hasSchedule" value="Scheduled" severity="success" />
                                    <Tag v-else value="No Schedule" severity="warning" />
                                </div>
                                <Button
                                    v-if="!assignment.hasSchedule"
                                    :label="creatingScheduleFor === assignment.id ? 'Loading...' : 'Create Schedule'"
                                    :icon="creatingScheduleFor === assignment.id ? 'pi pi-spin pi-spinner' : 'pi pi-calendar-plus'"
                                    size="small"
                                    class="w-full mt-3"
                                    @click="openCreateScheduleDialog(assignment)"
                                    :disabled="creatingScheduleFor === assignment.id"
                                    :loading="creatingScheduleFor === assignment.id"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedules Display -->
            <div v-else class="schedule-content">
                <!-- Weekly Schedule Overview -->
                <div class="schedule-expansion-header">
                    <h3 class="text-lg font-semibold">Weekly Schedule Overview</h3>
                    <div class="expansion-controls">
                        <Button label="Expand All" icon="pi pi-plus" @click="expandAll" text class="p-button-success" size="small" />
                        <Button label="Collapse All" icon="pi pi-minus" @click="collapseAll" text class="p-button-secondary" size="small" />
                    </div>
                </div>

                <div class="schedule-expansion-container">
                    <div v-for="day in weekdays" :key="day.value" class="day-expansion-row">
                        <div class="day-row-header" @click="toggleDay(day.value)" :class="{ expanded: expandedDays.includes(day.value) }">
                            <div class="day-row-left">
                                <i class="pi" :class="expandedDays.includes(day.value) ? 'pi-chevron-down' : 'pi-chevron-right'"></i>
                                <span class="day-name">{{ day.label }}</span>
                                <span class="day-count">{{ getSchedulesForDay(day.value).length }} {{ getSchedulesForDay(day.value).length === 1 ? 'class' : 'classes' }}</span>
                            </div>
                            <div class="day-row-right">
                                <Tag v-if="getSchedulesForDay(day.value).length > 0" :value="getSchedulesForDay(day.value).length + ' scheduled'" severity="info" />
                                <Tag v-else value="No classes" severity="secondary" />
                            </div>
                        </div>

                        <div v-if="expandedDays.includes(day.value)" class="day-expansion-content">
                            <div v-if="getSchedulesForDay(day.value).length > 0" class="schedules-list">
                                <!-- Table Header -->
                                <div class="schedule-table-header">
                                    <div class="header-time">Time</div>
                                    <div class="header-subject">Subject</div>
                                    <div class="header-section">Section</div>
                                    <div class="header-actions">Actions</div>
                                </div>
                                <!-- Schedule Items -->
                                <div v-for="schedule in getSchedulesForDay(day.value)" :key="schedule.id" class="schedule-expansion-item">
                                    <div class="schedule-item-row">
                                        <div class="schedule-time-col">
                                            <i class="pi pi-clock"></i>
                                            {{ SubjectScheduleService.formatTimeRange(schedule.start_time, schedule.end_time) }}
                                        </div>
                                        <div class="schedule-subject-col">
                                            <i class="pi pi-book"></i>
                                            {{ schedule.subject_name }}
                                        </div>
                                        <div class="schedule-section-col">
                                            <i class="pi pi-users"></i>
                                            {{ schedule.section_name }}
                                        </div>
                                        <div class="schedule-actions-col">
                                            <Button icon="pi pi-search" @click="viewScheduleDetails(schedule)" rounded size="small" severity="info" />
                                            <Button icon="pi pi-pencil" @click="editSchedule(schedule)" rounded size="small" severity="warning" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="no-schedule-message">
                                <i class="pi pi-info-circle"></i>
                                <span>No classes scheduled for this day</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current/Next Class Info -->
            <div v-if="currentSchedule || nextSchedule" class="current-schedule-info mt-6">
                <div class="grid">
                    <div v-if="currentSchedule" class="col-12 md:col-6">
                        <div class="current-class-card">
                            <div class="card-header-small">
                                <i class="pi pi-clock text-green-600 mr-2"></i>
                                <span class="font-semibold text-green-600">Current Class</span>
                            </div>
                            <div class="class-info">
                                <h4>{{ currentSchedule.subject_name }}</h4>
                                <p>{{ currentSchedule.section_name }}</p>
                                <p class="time-info">
                                    {{ SubjectScheduleService.formatTimeRange(currentSchedule.start_time, currentSchedule.end_time) }}
                                </p>
                            </div>
                            <Button label="Take Attendance" icon="pi pi-check" class="w-full mt-3" @click="createAttendanceSession(currentSchedule)" />
                        </div>
                    </div>

                    <div v-if="nextSchedule" class="col-12 md:col-6">
                        <div class="next-class-card">
                            <div class="card-header-small">
                                <i class="pi pi-clock text-blue-600 mr-2"></i>
                                <span class="font-semibold text-blue-600">Next Class</span>
                            </div>
                            <div class="class-info">
                                <h4>{{ nextSchedule.subject_name }}</h4>
                                <p>{{ nextSchedule.section_name }}</p>
                                <p class="time-info">
                                    {{ SubjectScheduleService.formatTimeRange(nextSchedule.start_time, nextSchedule.end_time) }}
                                </p>
                            </div>
                            <div class="countdown mt-2">
                                <small class="text-gray-600">Starts in {{ getTimeUntilNext(nextSchedule) }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Details Dialog -->
    <Dialog v-model:visible="showDetailsDialog" :modal="true" :closable="false" :dismissableMask="true" :style="{ width: '500px' }">
        <template #header>
            <div class="dialog-header-clickable" @click="switchToEditFromDetails">
                <span>Schedule Details</span>
                <i class="pi pi-arrow-right ml-2"></i>
            </div>
        </template>
        <div v-if="selectedSchedule" class="schedule-details">
            <div class="detail-item">
                <label>Subject:</label>
                <span class="font-semibold">{{ selectedSchedule.subject_name }}</span>
            </div>
            <div class="detail-item">
                <label>Section:</label>
                <span>{{ selectedSchedule.section_name }}</span>
            </div>
            <div class="detail-item">
                <label>Day:</label>
                <span>{{ SubjectScheduleService.getDayDisplayName(selectedSchedule.day) }}</span>
            </div>
            <div class="detail-item">
                <label>Time:</label>
                <span class="font-mono">{{ SubjectScheduleService.formatTimeRange(selectedSchedule.start_time, selectedSchedule.end_time) }}</span>
            </div>
            <div class="detail-item">
                <label>Duration:</label>
                <span>{{ getScheduleDuration(selectedSchedule) }} minutes</span>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-content-end gap-2">
                <Button label="Close" icon="pi pi-times" severity="secondary" @click="showDetailsDialog = false" />
                <Button label="Take Attendance" icon="pi pi-check" @click="createAttendanceSession(selectedSchedule)" :disabled="!isCurrentTimeSlot(selectedSchedule)" />
            </div>
        </template>
    </Dialog>

    <!-- Edit Schedule Dialog -->
    <Dialog v-model:visible="showEditDialog" :modal="true" :closable="false" :dismissableMask="true" :style="{ width: '600px' }">
        <template #header>
            <div class="dialog-header-clickable" @click="switchToDetailsFromEdit">
                <span>Edit Schedule</span>
                <i class="pi pi-arrow-left ml-2"></i>
            </div>
        </template>
        <div v-if="editingSchedule" class="edit-schedule-form">
            <div class="field">
                <label for="edit-subject" class="block text-900 font-medium mb-2">Subject</label>
                <div class="p-inputgroup">
                    <span class="p-inputgroup-addon">
                        <i class="pi pi-book"></i>
                    </span>
                    <InputText id="edit-subject" v-model="editingSchedule.subject_name" disabled class="w-full" />
                </div>
            </div>

            <div class="field">
                <label for="edit-section" class="block text-900 font-medium mb-2">Section</label>
                <div class="p-inputgroup">
                    <span class="p-inputgroup-addon">
                        <i class="pi pi-users"></i>
                    </span>
                    <InputText id="edit-section" v-model="editingSchedule.section_name" disabled class="w-full" />
                </div>
            </div>

            <div class="field">
                <label for="edit-day" class="block text-900 font-medium mb-2">Day</label>
                <Dropdown id="edit-day" v-model="editingSchedule.day" :options="dayOptions" optionLabel="label" optionValue="value" placeholder="Select a day" class="w-full" />
            </div>

            <div class="grid">
                <div class="col-6">
                    <div class="field">
                        <label for="edit-start-time" class="block text-900 font-medium mb-2">Start Time</label>
                        <Calendar id="edit-start-time" v-model="editingSchedule.start_time" timeOnly hourFormat="12" placeholder="Select time" class="w-full" />
                    </div>
                </div>
                <div class="col-6">
                    <div class="field">
                        <label for="edit-end-time" class="block text-900 font-medium mb-2">End Time</label>
                        <Calendar id="edit-end-time" v-model="editingSchedule.end_time" timeOnly hourFormat="12" placeholder="Select time" class="w-full" />
                    </div>
                </div>
            </div>

            <div v-if="editValidationError" class="p-message p-message-error mt-3">
                <span class="p-message-text">{{ editValidationError }}</span>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-content-end gap-2">
                <Button label="Cancel" icon="pi pi-times" severity="secondary" @click="cancelEdit" />
                <Button label="Save Changes" icon="pi pi-check" @click="saveScheduleChanges" :loading="saving" />
            </div>
        </template>
    </Dialog>

    <!-- Old Create Dialog - REMOVED - Now uses dedicated CreateSchedule.vue page -->
</template>

<script setup>
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';

// PrimeVue Components
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Tag from 'primevue/tag';

// Services
import SubjectScheduleService from '@/services/SubjectScheduleService';
import TeacherAuthService from '@/services/TeacherAuthService';

const router = useRouter();
const toast = useToast();

// Data
const loading = ref(false);
const creatingScheduleFor = ref(null); // Track which assignment is being created
const schedules = ref([]);
const teacherAssignments = ref([]);
const showDetailsDialog = ref(false);
const showCreateScheduleDialog = ref(false);
const selectedSchedule = ref(null);
const selectedAssignment = ref(null);
const currentTime = ref(new Date());
const expandedDays = ref([]); // Track which days are expanded

// Edit Schedule Data
const showEditDialog = ref(false);
const editingSchedule = ref(null);
const saving = ref(false);
const editValidationError = ref('');

// Create Schedule Data
const showCreateDialog = ref(false);
const selectedSubjectForCreate = ref(null);
const selectedDayForCreate = ref(null);
const createStartTime = ref(null);
const createEndTime = ref(null);
const creating = ref(false);
const createValidationError = ref('');

// Timer for updating current time
let timeUpdateInterval = null;

// Computed Properties
const weekdays = computed(() => SubjectScheduleService.getWeekdays());

const currentSchedule = computed(() => {
    const now = currentTime.value;
    const currentDay = now.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
    const currentTimeStr = now.toTimeString().split(' ')[0];

    return schedules.value.find((schedule) => schedule.day === currentDay && schedule.start_time <= currentTimeStr && schedule.end_time > currentTimeStr);
});

const nextSchedule = computed(() => {
    const now = currentTime.value;
    const currentDay = now.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
    const currentTimeStr = now.toTimeString().split(' ')[0];

    // Find next schedule today
    const todaySchedules = schedules.value.filter((schedule) => schedule.day === currentDay && schedule.start_time > currentTimeStr).sort((a, b) => a.start_time.localeCompare(b.start_time));

    if (todaySchedules.length > 0) {
        return todaySchedules[0];
    }

    // If no more classes today, find first class tomorrow or next day
    const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    const currentDayIndex = dayOrder.indexOf(currentDay);

    for (let i = 1; i < dayOrder.length; i++) {
        const nextDayIndex = (currentDayIndex + i) % dayOrder.length;
        const nextDay = dayOrder[nextDayIndex];

        const nextDaySchedules = schedules.value.filter((schedule) => schedule.day === nextDay).sort((a, b) => a.start_time.localeCompare(b.start_time));

        if (nextDaySchedules.length > 0) {
            return nextDaySchedules[0];
        }
    }

    return null;
});

// Day options for dropdown
const dayOptions = computed(() => [
    { label: 'Monday', value: 'Monday' },
    { label: 'Tuesday', value: 'Tuesday' },
    { label: 'Wednesday', value: 'Wednesday' },
    { label: 'Thursday', value: 'Thursday' },
    { label: 'Friday', value: 'Friday' }
]);

// Available subjects for create dialog with smart filtering
const availableSubjectsForCreate = computed(() => {
    if (!teacherAssignments.value.length) return [];

    return teacherAssignments.value.map((assignment) => {
        // Get existing schedules for this subject-section combination
        const existingSchedules = schedules.value.filter((schedule) => schedule.subject_id === assignment.subject_id && schedule.section_id === assignment.section_id);

        // Check if subject has all 5 weekdays scheduled
        const scheduledDays = existingSchedules.map((s) => s.day);
        const allDaysScheduled = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'].every((day) => scheduledDays.includes(day));

        const isDisabled = allDaysScheduled;

        return {
            value: `${assignment.subject_id}_${assignment.section_id}`,
            display_name: assignment.subject_name,
            section_name: assignment.section_name,
            subject_id: assignment.subject_id,
            section_id: assignment.section_id,
            disabled: isDisabled,
            tooltip: isDisabled ? 'This subject already has a complete schedule (Monday-Friday)' : `Create schedule for ${assignment.subject_name} in ${assignment.section_name}`,
            existing_days: scheduledDays
        };
    });
});

// Available days for create dialog based on selected subject
const availableDaysForCreate = computed(() => {
    if (!selectedSubjectForCreate.value) {
        return dayOptions.value.map((day) => ({ ...day, disabled: true, tooltip: 'Please select a subject first' }));
    }

    // Find the selected subject info
    const selectedSubject = availableSubjectsForCreate.value.find((subject) => subject.value === selectedSubjectForCreate.value);

    if (!selectedSubject) {
        return dayOptions.value.map((day) => ({ ...day, disabled: true, tooltip: 'Invalid subject selection' }));
    }

    // Return days with disabled state for already scheduled days
    return dayOptions.value.map((day) => {
        const isAlreadyScheduled = selectedSubject.existing_days.includes(day.value);
        return {
            ...day,
            disabled: isAlreadyScheduled,
            tooltip: isAlreadyScheduled ? `${day.label} is already scheduled for this subject` : `Available for scheduling`
        };
    });
});

// Methods
const loadSchedules = async () => {
    loading.value = true;
    try {
        const teacherData = TeacherAuthService.getTeacherData();
        console.log('Teacher data structure:', teacherData);

        // Extract teacher ID from the nested structure
        let teacherId = null;
        if (teacherData?.teacher?.id) {
            teacherId = teacherData.teacher.id;
        } else if (teacherData?.id) {
            teacherId = teacherData.id;
        } else if (teacherData?.user?.id) {
            teacherId = teacherData.user.id;
        }

        if (!teacherId) {
            throw new Error('Teacher not authenticated - no teacher ID found');
        }

        console.log('Using teacher ID:', teacherId);

        // Load schedules first, then assignments (so we can mark which have schedules)
        await loadTeacherSchedules(teacherId);
        await loadTeacherAssignments(teacherId);
    } catch (error) {
        console.error('Error loading schedules:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load your schedules',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

const loadTeacherSchedules = async (teacherId) => {
    try {
        const response = await SubjectScheduleService.getTeacherSchedules(teacherId);
        schedules.value = response.data || [];
        console.log('Loaded teacher schedules:', schedules.value.length);
    } catch (error) {
        console.error('Error loading teacher schedules:', error);
        schedules.value = [];
    }
};

const loadTeacherAssignments = async (teacherId) => {
    try {
        // Get teacher assignments from TeacherAuthService
        const teacherData = TeacherAuthService.getTeacherData();
        console.log('Teacher data for assignments:', teacherData);

        if (teacherData?.assignments) {
            teacherAssignments.value = teacherData.assignments
                // Filter out homeroom assignments - homeroom is not a subject that needs scheduling
                .filter((assignment) => {
                    const subjectName = assignment.subject_name || assignment.subject?.name || '';
                    return subjectName.toLowerCase() !== 'homeroom';
                })
                .map((assignment) => {
                    // Get subject and section info from assignment or section object
                    const subjectName = assignment.subject_name || assignment.subject?.name || (assignment.subject_id ? `Subject ${assignment.subject_id}` : 'Unknown Subject');

                    const sectionName = assignment.section_name || assignment.section?.name || (assignment.section_id ? `Section ${assignment.section_id}` : 'Unknown Section');

                    return {
                        id: `${assignment.section_id}_${assignment.subject_id}`,
                        section_id: assignment.section_id,
                        subject_id: assignment.subject_id,
                        section_name: sectionName,
                        subject_name: subjectName,
                        hasSchedule: false // Will be updated below
                    };
                });

            // Mark assignments that have schedules
            teacherAssignments.value.forEach((assignment) => {
                assignment.hasSchedule = schedules.value.some((schedule) => schedule.section_id == assignment.section_id && schedule.subject_id == assignment.subject_id);
            });

            console.log('Loaded teacher assignments:', teacherAssignments.value);
        } else {
            teacherAssignments.value = [];
        }
    } catch (error) {
        console.error('Error loading teacher assignments:', error);
        teacherAssignments.value = [];
    }
};

const getSchedulesForDay = (day) => {
    return schedules.value.filter((schedule) => schedule.day === day).sort((a, b) => a.start_time.localeCompare(b.start_time));
};

const getScheduleItemClass = (schedule) => {
    const now = currentTime.value;
    const currentDay = now.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
    const currentTimeStr = now.toTimeString().split(' ')[0];

    if (schedule.day === currentDay) {
        if (schedule.start_time <= currentTimeStr && schedule.end_time > currentTimeStr) {
            return 'current-schedule';
        } else if (schedule.start_time > currentTimeStr) {
            return 'upcoming-schedule';
        } else {
            return 'past-schedule';
        }
    }

    return '';
};

const getDaySeverity = (day) => {
    const severities = {
        monday: 'info',
        tuesday: 'success',
        wednesday: 'warning',
        thursday: 'danger',
        friday: 'secondary'
    };
    return severities[day] || 'info';
};

const isCurrentTimeSlot = (schedule) => {
    const now = currentTime.value;
    const currentDay = now.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
    const currentTimeStr = now.toTimeString().split(' ')[0];

    return schedule.day === currentDay && schedule.start_time <= currentTimeStr && schedule.end_time > currentTimeStr;
};

const getTimeUntilNext = (schedule) => {
    const now = currentTime.value;
    const currentDay = now.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();

    if (schedule.day === currentDay) {
        const [hours, minutes] = schedule.start_time.split(':');
        const scheduleTime = new Date();
        scheduleTime.setHours(parseInt(hours), parseInt(minutes), 0, 0);

        const diff = scheduleTime - now;
        if (diff > 0) {
            const diffMinutes = Math.floor(diff / (1000 * 60));
            const diffHours = Math.floor(diffMinutes / 60);
            const remainingMinutes = diffMinutes % 60;

            if (diffHours > 0) {
                return `${diffHours}h ${remainingMinutes}m`;
            } else {
                return `${remainingMinutes}m`;
            }
        }
    }

    return 'Tomorrow or later';
};

// Expand/Collapse functions
const toggleDay = (dayValue) => {
    const index = expandedDays.value.indexOf(dayValue);
    if (index > -1) {
        expandedDays.value.splice(index, 1);
    } else {
        expandedDays.value.push(dayValue);
    }
};

const expandAll = () => {
    expandedDays.value = weekdays.value.map((day) => day.value);
};

const collapseAll = () => {
    expandedDays.value = [];
};

const getScheduleDuration = (schedule) => {
    const [startHours, startMinutes] = schedule.start_time.split(':').map(Number);
    const [endHours, endMinutes] = schedule.end_time.split(':').map(Number);

    const startTotalMinutes = startHours * 60 + startMinutes;
    const endTotalMinutes = endHours * 60 + endMinutes;

    return endTotalMinutes - startTotalMinutes;
};

const viewScheduleDetails = (schedule) => {
    selectedSchedule.value = schedule;
    showDetailsDialog.value = true;
};

// Switch between dialogs
const switchToEditFromDetails = () => {
    console.log('🔄 Switching from Details to Edit', selectedSchedule.value);
    if (selectedSchedule.value) {
        showDetailsDialog.value = false;
        editingSchedule.value = { ...selectedSchedule.value };
        showEditDialog.value = true;
        console.log('✅ Switched to Edit dialog');
    } else {
        console.log('❌ No selected schedule');
    }
};

const switchToDetailsFromEdit = () => {
    console.log('🔄 Switching from Edit to Details', editingSchedule.value);
    if (editingSchedule.value) {
        showEditDialog.value = false;
        selectedSchedule.value = { ...editingSchedule.value };
        showDetailsDialog.value = true;
        console.log('✅ Switched to Details dialog');
    } else {
        console.log('❌ No editing schedule');
    }
};

const editSchedule = (schedule) => {
    console.log('✏️ Editing schedule:', schedule);

    // Create a copy of the schedule for editing
    editingSchedule.value = {
        id: schedule.id,
        subject_id: schedule.subject_id,
        section_id: schedule.section_id,
        subject_name: schedule.subject_name,
        section_name: schedule.section_name,
        day: schedule.day,
        start_time: schedule.start_time,
        end_time: schedule.end_time
    };

    editValidationError.value = '';
    showEditDialog.value = true;
};

const cancelEdit = () => {
    editingSchedule.value = null;
    editValidationError.value = '';
    showEditDialog.value = false;
};

const validateScheduleEdit = () => {
    if (!editingSchedule.value.day) {
        return 'Please select a day';
    }

    if (!editingSchedule.value.start_time || !editingSchedule.value.end_time) {
        return 'Please enter both start and end times';
    }

    // Validate that end time is after start time
    // Handle both Date objects (from Calendar component) and string values
    let startTime, endTime;

    if (editingSchedule.value.start_time instanceof Date) {
        startTime = editingSchedule.value.start_time;
    } else {
        startTime = new Date(`2000-01-01 ${editingSchedule.value.start_time}:00`);
    }

    if (editingSchedule.value.end_time instanceof Date) {
        endTime = editingSchedule.value.end_time;
    } else {
        endTime = new Date(`2000-01-01 ${editingSchedule.value.end_time}:00`);
    }

    if (endTime <= startTime) {
        return 'End time must be after start time';
    }

    return null;
};

// Helper function to convert Date object to HH:MM format
const formatTimeForAPI = (timeValue) => {
    if (!timeValue) return '';

    // If it's already a string in HH:MM format, return it
    if (typeof timeValue === 'string' && timeValue.match(/^\d{2}:\d{2}$/)) {
        return timeValue + ':00';
    }

    // If it's a Date object, convert it
    if (timeValue instanceof Date) {
        const hours = String(timeValue.getHours()).padStart(2, '0');
        const minutes = String(timeValue.getMinutes()).padStart(2, '0');
        return `${hours}:${minutes}:00`;
    }

    return timeValue;
};

const saveScheduleChanges = async () => {
    const validationError = validateScheduleEdit();
    if (validationError) {
        editValidationError.value = validationError;
        return;
    }

    saving.value = true;
    editValidationError.value = '';

    try {
        const teacherData = TeacherAuthService.getTeacherData();
        let teacherId = teacherData?.id || teacherData?.teacher?.id || teacherData?.user?.id;

        const scheduleData = {
            id: editingSchedule.value.id,
            teacher_id: teacherId,
            section_id: editingSchedule.value.section_id,
            subject_id: editingSchedule.value.subject_id,
            day: editingSchedule.value.day,
            start_time: formatTimeForAPI(editingSchedule.value.start_time),
            end_time: formatTimeForAPI(editingSchedule.value.end_time)
        };

        console.log('💾 Saving schedule changes:', scheduleData);

        const response = await SubjectScheduleService.saveSchedule(scheduleData);

        if (response.success) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Schedule updated successfully',
                life: 3000
            });

            // Reload schedules to show updated data
            await loadSchedules();

            // Close dialog
            cancelEdit();
        } else {
            throw new Error(response.message || 'Failed to update schedule');
        }
    } catch (error) {
        console.error('❌ Error updating schedule:', error);
        editValidationError.value = error.message || 'Failed to update schedule. Please try again.';

        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to update schedule',
            life: 3000
        });
    } finally {
        saving.value = false;
    }
};

// Create Schedule Methods
const openCreateScheduleModal = () => {
    console.log('🆕 Navigating to create schedule page');

    // Navigate to create schedule page without pre-selected assignment
    router
        .push({
            path: '/teacher/create-schedule'
        })
        .catch((error) => {
            console.error('Navigation error:', error);
            toast.add({
                severity: 'error',
                summary: 'Navigation Error',
                detail: 'Failed to navigate to schedule creation page',
                life: 3000
            });
        });
};

const resetCreateForm = () => {
    selectedSubjectForCreate.value = null;
    selectedDayForCreate.value = null;
    createStartTime.value = null;
    createEndTime.value = null;
    createValidationError.value = '';
};

const cancelCreate = () => {
    resetCreateForm();
    showCreateDialog.value = false;
};

const onSubjectChange = () => {
    // Reset day selection when subject changes
    selectedDayForCreate.value = null;
    createValidationError.value = '';
};

const validateCreateSchedule = () => {
    if (!selectedSubjectForCreate.value) {
        return 'Please select a subject';
    }

    if (!selectedDayForCreate.value) {
        return 'Please select a day';
    }

    if (!createStartTime.value || !createEndTime.value) {
        return 'Please enter both start and end times';
    }

    // Validate that end time is after start time
    const startTime = createStartTime.value instanceof Date ? createStartTime.value : new Date(`2000-01-01 ${createStartTime.value}:00`);
    const endTime = createEndTime.value instanceof Date ? createEndTime.value : new Date(`2000-01-01 ${createEndTime.value}:00`);

    if (endTime <= startTime) {
        return 'End time must be after start time';
    }

    return null;
};

const createNewSchedule = async () => {
    const validationError = validateCreateSchedule();
    if (validationError) {
        createValidationError.value = validationError;
        return;
    }

    creating.value = true;
    createValidationError.value = '';

    try {
        const teacherData = TeacherAuthService.getTeacherData();
        let teacherId = teacherData?.id || teacherData?.teacher?.id || teacherData?.user?.id;

        // Parse subject and section IDs from selected value
        const [subjectId, sectionId] = selectedSubjectForCreate.value.split('_');

        const scheduleData = {
            teacher_id: teacherId,
            section_id: parseInt(sectionId),
            subject_id: parseInt(subjectId),
            day: selectedDayForCreate.value,
            start_time: formatTimeForAPI(createStartTime.value),
            end_time: formatTimeForAPI(createEndTime.value)
        };

        console.log('🆕 Creating new schedule:', scheduleData);

        const response = await SubjectScheduleService.saveSchedule(scheduleData);

        if (response.success) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Schedule created successfully',
                life: 3000
            });

            // Reload schedules to show new data
            await loadSchedules();

            // Close dialog
            cancelCreate();
        } else {
            throw new Error(response.message || 'Failed to create schedule');
        }
    } catch (error) {
        console.error('❌ Error creating schedule:', error);
        createValidationError.value = error.message || 'Failed to create schedule. Please try again.';

        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to create schedule',
            life: 3000
        });
    } finally {
        creating.value = false;
    }
};

const createAttendanceSession = (schedule) => {
    console.log('🎯 Starting attendance session for schedule:', schedule);

    // Navigate to attendance taking page with schedule context
    router.push({
        name: 'subject-attendance',
        params: {
            subjectId: schedule.subject_id || schedule.subject?.id
        },
        query: {
            sectionId: schedule.section_id,
            scheduleId: schedule.id,
            autoStart: 'true'
        }
    });
};

const openCreateScheduleDialog = (assignment) => {
    console.log('🔧 Create Schedule button clicked!', assignment);

    // Set loading state for this specific button
    creatingScheduleFor.value = assignment.id;

    selectedAssignment.value = assignment;
    // DON'T show the dialog - navigate directly
    // showCreateScheduleDialog.value = true;

    console.log('📝 Assignment details:', {
        subject: assignment.subject_name,
        section: assignment.section_name,
        section_id: assignment.section_id,
        subject_id: assignment.subject_id
    });

    toast.add({
        severity: 'info',
        summary: 'Schedule Creation',
        detail: `Creating schedule for ${assignment.subject_name} in ${assignment.section_name}`,
        life: 3000
    });

    // Navigate immediately (reduced timeout for better UX)
    setTimeout(() => {
        console.log('🚀 Redirecting to teacher schedule creation interface...');

        // Navigate to teacher schedule creation with query parameters
        router
            .push({
                path: '/teacher/create-schedule',
                query: {
                    section_id: assignment.section_id,
                    subject_id: assignment.subject_id,
                    section_name: assignment.section_name,
                    subject_name: assignment.subject_name
                }
            })
            .catch((error) => {
                // Clear loading state if navigation fails
                console.error('Navigation error:', error);
                creatingScheduleFor.value = null;
                toast.add({
                    severity: 'error',
                    summary: 'Navigation Error',
                    detail: 'Failed to navigate to schedule creation page',
                    life: 3000
                });
            });
    }, 500); // Reduced from 1500ms to 500ms for better UX
};

const getCurrentTeacherId = () => {
    const teacherData = TeacherAuthService.getTeacherData();
    return teacherData?.teacher?.id || teacherData?.id || teacherData?.user?.id;
};

const updateCurrentTime = () => {
    currentTime.value = new Date();
};

// Lifecycle
onMounted(() => {
    loadSchedules();

    // Update current time every minute
    timeUpdateInterval = setInterval(updateCurrentTime, 60000);
});

onUnmounted(() => {
    if (timeUpdateInterval) {
        clearInterval(timeUpdateInterval);
    }
});
</script>

<style scoped>
.teacher-schedules-container {
    padding: 1rem;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
}

.card-header {
    margin-bottom: 2rem;
}

.card-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
}

.card-subtitle {
    color: #6b7280;
    font-size: 0.875rem;
    margin: 0;
}

.schedule-grid {
    background: #f9fafb;
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid #e5e7eb;
}

.day-column {
    background: white;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    margin-bottom: 1rem;
    overflow: hidden;
}

.day-header {
    background: #3b82f6;
    color: white;
    padding: 0.75rem;
    font-weight: 600;
    text-align: center;
    font-size: 0.875rem;
}

.day-content {
    padding: 0.5rem;
    min-height: 200px;
}

.schedule-item {
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    padding: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.75rem;
    transition: all 0.2s;
}

.schedule-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.schedule-item.current-schedule {
    background: #dcfce7;
    border-color: #16a34a;
    color: #15803d;
}

.schedule-item.upcoming-schedule {
    background: #dbeafe;
    border-color: #3b82f6;
    color: #1d4ed8;
}

.schedule-item.past-schedule {
    background: #f3f4f6;
    border-color: #d1d5db;
    color: #6b7280;
}

.schedule-time {
    font-weight: 600;
    font-family: 'Courier New', monospace;
    margin-bottom: 0.25rem;
}

.schedule-subject {
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.schedule-section {
    color: #6b7280;
    font-size: 0.6875rem;
}

.no-schedule {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100px;
    color: #9ca3af;
}

.current-schedule-info {
    background: #f9fafb;
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid #e5e7eb;
}

.current-class-card,
.next-class-card {
    background: white;
    border-radius: 6px;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    height: 100%;
}

.card-header-small {
    display: flex;
    align-items: center;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.class-info h4 {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0 0 0.25rem 0;
    color: #1f2937;
}

.class-info p {
    margin: 0 0 0.25rem 0;
    color: #6b7280;
}

.time-info {
    font-family: 'Courier New', monospace;
    font-weight: 500;
    color: #374151 !important;
}

.countdown {
    background: #f3f4f6;
    padding: 0.5rem;
    border-radius: 4px;
    text-align: center;
}

.schedule-details {
    padding: 1rem 0;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f3f4f6;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-item label {
    font-weight: 500;
    color: #6b7280;
}

.font-mono {
    font-family: 'Courier New', monospace;
}

:deep(.p-datatable-sm .p-datatable-tbody > tr > td) {
    padding: 0.5rem;
}

:deep(.p-tag) {
    font-size: 0.75rem;
}

.assignments-without-schedules {
    padding: 1rem 0;
}

.assignments-grid {
    background: #f9fafb;
    border-radius: 8px;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
}

.assignment-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.2s;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.assignment-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.assignment-header {
    display: flex;
    align-items: center;
    margin-bottom: 0.75rem;
    font-size: 1.125rem;
}

.assignment-section {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    color: #6b7280;
}

.assignment-status {
    margin-bottom: 1rem;
    flex-grow: 1;
}

@media (max-width: 768px) {
    .day-column {
        margin-bottom: 0.5rem;
    }

    .day-content {
        min-height: 150px;
    }

    .schedule-item {
        font-size: 0.6875rem;
    }
}

/* Blue Gradient Header - Picture 2 Style */
.blue-gradient-header {
    background: linear-gradient(135deg, #4169e1 0%, #1e3a8a 100%) !important;
    padding: 2rem 2.5rem !important;
    border-radius: 12px !important;
    margin-bottom: 2rem !important;
    box-shadow: 0 4px 12px rgba(65, 105, 225, 0.3) !important;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.header-right {
    display: flex;
    align-items: center;
}

.create-schedule-header-btn {
    background-color: #10b981 !important;
    border-color: #10b981 !important;
    color: #ffffff !important;
    padding: 0.75rem 1.5rem !important;
    font-weight: 600 !important;
    border-radius: 8px !important;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3) !important;
    transition: all 0.2s ease !important;
}

.create-schedule-header-btn:hover {
    background-color: #059669 !important;
    border-color: #059669 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4) !important;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.icon-circle {
    width: 64px;
    height: 64px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
}

.icon-circle i {
    font-size: 32px;
    color: #ffffff;
}

.header-text h2 {
    color: #ffffff !important;
    font-size: 1.75rem !important;
    font-weight: 700 !important;
    margin: 0 0 0.25rem 0 !important;
}

.header-text p {
    color: rgba(255, 255, 255, 0.9) !important;
    font-size: 1rem !important;
    margin: 0 !important;
}

.blue-gradient-header .card-title {
    color: #ffffff !important;
}

.blue-gradient-header .card-subtitle {
    color: rgba(255, 255, 255, 0.9) !important;
}

/* Total Teachers Badge */
.total-teachers-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.25);
    padding: 0.5rem 1.25rem;
    border-radius: 24px;
    margin-top: 0.75rem;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.total-teachers-badge i {
    color: #ffffff;
    font-size: 1rem;
}

.total-teachers-badge .badge-label {
    color: rgba(255, 255, 255, 0.95);
    font-size: 0.875rem;
    font-weight: 500;
}

.total-teachers-badge .badge-count {
    background: #ffffff;
    color: #4169e1;
    padding: 0.25rem 0.75rem;
    border-radius: 16px;
    font-weight: 700;
    font-size: 0.875rem;
    min-width: 40px;
    text-align: center;
}

/* Expandable Row Design - Picture 2 Style */
.schedule-content {
    background-color: #e5c95f !important;
    padding: 2rem;
    border-radius: 8px;
}

.schedule-expansion-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.expansion-controls {
    display: flex;
    gap: 0.5rem;
}

.expansion-controls :deep(.p-button) {
    background-color: #ffffff !important;
    border: 1px solid #d1d5db !important;
    border-radius: 6px !important;
    padding: 0.5rem 1rem !important;
}

.expansion-controls :deep(.p-button:hover) {
    background-color: #f3f4f6 !important;
}

.expansion-controls :deep(.p-button:active),
.expansion-controls :deep(.p-button:focus) {
    background-color: #3b82f6 !important;
    border-color: #3b82f6 !important;
    color: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3) !important;
}

.expansion-controls :deep(.p-button:active .p-button-label),
.expansion-controls :deep(.p-button:focus .p-button-label),
.expansion-controls :deep(.p-button:active .p-button-icon),
.expansion-controls :deep(.p-button:focus .p-button-icon) {
    color: #ffffff !important;
}

.schedule-expansion-container {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.day-expansion-row {
    border-bottom: 1px solid #e5e7eb;
}

.day-expansion-row:last-child {
    border-bottom: none;
}

.day-row-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    cursor: pointer;
    transition: background-color 0.2s ease;
    background: white;
}

.day-row-header:hover {
    background: #f9fafb;
}

.day-row-header.expanded {
    background: #f3f4f6;
}

.day-row-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.day-row-left i {
    color: #6b7280;
    font-size: 0.875rem;
    transition: transform 0.2s ease;
}

.day-row-header.expanded .day-row-left i {
    transform: rotate(0deg);
}

.day-name {
    font-weight: 600;
    font-size: 1rem;
    color: #1f2937;
}

.day-count {
    font-size: 0.875rem;
    color: #6b7280;
    margin-left: 0.5rem;
}

.day-row-right {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.day-expansion-content {
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        max-height: 0;
    }
    to {
        opacity: 1;
        max-height: 1000px;
    }
}

.schedules-list {
    padding: 0.5rem;
}

/* Table Header */
.schedule-table-header {
    display: grid;
    grid-template-columns: 2fr 2fr 2fr 1fr;
    gap: 1rem;
    padding: 1rem 1.5rem;
    background: #3b82f6 !important;
    border-bottom: 2px solid #2563eb;
    font-weight: 600;
    color: #ffffff !important;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.header-time,
.header-subject,
.header-section,
.header-actions {
    display: flex;
    align-items: center;
    color: #ffffff !important;
}

.header-actions {
    justify-content: flex-end;
}

.schedule-expansion-item {
    background: white;
    border-radius: 6px;
    margin-bottom: 0.5rem;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.schedule-expansion-item:last-child {
    margin-bottom: 0;
}

.schedule-item-row {
    display: grid;
    grid-template-columns: 2fr 2fr 2fr 1fr;
    gap: 1rem;
    padding: 1rem;
    align-items: center;
}

.schedule-time-col,
.schedule-subject-col,
.schedule-section-col {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.schedule-time-col i {
    color: #3b82f6;
}

.schedule-subject-col i {
    color: #10b981;
}

.schedule-section-col i {
    color: #f59e0b;
}

/* Ensure all icons are always visible and don't blink */
.schedule-item-row .pi {
    font-family: 'primeicons' !important;
    font-style: normal !important;
    font-weight: normal !important;
    font-variant: normal !important;
    text-transform: none !important;
    line-height: 1 !important;
    display: inline-block !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.schedule-item-row .pi::before {
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.schedule-time-col {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: #1f2937;
}

.schedule-subject-col {
    font-weight: 500;
    color: #374151;
}

.schedule-section-col {
    color: #6b7280;
}

.schedule-actions-col {
    display: flex;
    justify-content: flex-end;
    gap: 0.25rem;
}

.schedule-actions-col :deep(.p-button) {
    border: 1px solid #e5e7eb !important;
    pointer-events: auto !important;
    cursor: pointer !important;
    position: relative !important;
    z-index: 10 !important;
}

.schedule-actions-col :deep(.p-button.p-button-info) {
    background-color: #3b82f6 !important;
    color: #ffffff !important;
    border-color: #3b82f6 !important;
}

.schedule-actions-col :deep(.p-button.p-button-info:hover) {
    background-color: #2563eb !important;
    border-color: #2563eb !important;
}

.schedule-actions-col :deep(.p-button.p-button-warning) {
    background-color: #f59e0b !important;
    color: #ffffff !important;
    border-color: #f59e0b !important;
}

.schedule-actions-col :deep(.p-button.p-button-warning:hover) {
    background-color: #d97706 !important;
    border-color: #d97706 !important;
}

.schedule-actions-col :deep(.p-button .p-button-icon) {
    font-size: 1.1rem !important;
}

.schedule-actions-col :deep(.p-button-icon) {
    color: inherit !important;
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.schedule-actions-col :deep(.p-button.p-button-rounded) {
    width: 2.5rem !important;
    height: 2.5rem !important;
}

.schedule-actions-col :deep(.p-button .pi) {
    font-size: 1.1rem !important;
    line-height: 1 !important;
}

.schedule-actions-col :deep(.p-button.p-button-info .pi) {
    color: #ffffff !important;
}

.schedule-actions-col :deep(.p-button.p-button-warning .pi) {
    color: #ffffff !important;
}

.schedule-actions-col :deep(.p-button .pi) {
    font-family: 'primeicons' !important;
    speak: none !important;
    font-style: normal !important;
    font-weight: normal !important;
    font-variant: normal !important;
    text-transform: none !important;
    line-height: 1 !important;
    display: inline-block !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.schedule-actions-col :deep(.p-button .pi::before) {
    color: inherit !important;
    display: inline-block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.no-schedule-message {
    padding: 2rem;
    text-align: center;
    color: #9ca3af;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.no-schedule-message i {
    font-size: 1.25rem;
}

@media (max-width: 768px) {
    .schedule-item-row {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .schedule-actions-col {
        justify-content: flex-start;
    }
}

/* Hide Dialog Close Button (X) */
:deep(.p-dialog .p-dialog-header-close) {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
}

:deep(.p-dialog-header-icon) {
    display: none !important;
}

:deep(.p-dialog-header-icons) {
    display: none !important;
}

:deep(.p-dialog .p-dialog-header .p-dialog-header-icon) {
    display: none !important;
}

/* Clickable Dialog Headers */
.dialog-header-clickable {
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: all 0.2s ease;
    display: inline-block;
}

.dialog-header-clickable:hover {
    background-color: rgba(59, 130, 246, 0.1);
    transform: scale(1.02);
}

.dialog-header-clickable span {
    font-weight: 600;
    font-size: 1.25rem;
    color: #1f2937;
}

.dialog-header-clickable:hover span {
    color: #3b82f6;
}
</style>
