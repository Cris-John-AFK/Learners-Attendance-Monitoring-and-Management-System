<template>
    <div class="create-schedule-container">
        <div class="schedule-header">
            <Button 
                icon="pi pi-arrow-left" 
                class="p-button-text p-button-sm"
                @click="goBack"
            />
            <h2 class="schedule-title">Create Schedule</h2>
        </div>

        <div class="schedule-form-card">
            <!-- Assignment Selector (if not pre-selected) -->
            <div v-if="!isAssignmentFromRoute" class="assignment-selector mb-4">
                <h3>Select Subject Assignment:</h3>
                <Dropdown 
                    v-model="selectedAssignmentOption" 
                    :options="teacherAssignments" 
                    optionLabel="label"
                    placeholder="Choose a subject to schedule"
                    class="w-full"
                    @change="onAssignmentChange"
                >
                    <template #value="slotProps">
                        <div v-if="slotProps.value" class="flex items-center gap-2">
                            <i class="pi pi-book text-blue-600"></i>
                            <span>{{ slotProps.value.label }}</span>
                        </div>
                        <span v-else>{{ slotProps.placeholder }}</span>
                    </template>
                    <template #option="slotProps">
                        <div class="flex flex-col">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-book text-blue-600"></i>
                                <strong>{{ slotProps.option.subject_name }}</strong>
                            </div>
                            <small class="text-gray-500">
                                <i class="pi pi-users"></i> {{ slotProps.option.section_name }}
                            </small>
                        </div>
                    </template>
                </Dropdown>
            </div>

            <!-- Assignment Display (when selected) -->
            <div v-if="assignment" class="assignment-info">
                <h3>Creating Schedule For:</h3>
                <div class="assignment-details">
                    <div class="detail-item">
                        <i class="pi pi-book text-blue-600"></i>
                        <span class="label">Subject:</span>
                        <span class="value">{{ assignment?.subject_name || 'Not selected' }}</span>
                    </div>
                    <div class="detail-item">
                        <i class="pi pi-users text-green-600"></i>
                        <span class="label">Section:</span>
                        <span class="value">{{ assignment?.section_name || 'Not selected' }}</span>
                    </div>
                </div>
            </div>

            <form @submit.prevent="saveSchedule" class="schedule-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="days">Days of Week</label>
                        <div class="custom-days-selector" :class="{ 'p-invalid': errors.days }">
                            <!-- Select All -->
                            <div class="day-option select-all-option" v-if="availableDaysCount > 0">
                                <Checkbox 
                                    :modelValue="scheduleForm.days.length === availableDaysCount" 
                                    @update:modelValue="selectAllDays" 
                                    binary 
                                    inputId="select-all"
                                />
                                <label for="select-all" class="ml-2 cursor-pointer font-semibold" @click="selectAllDays">
                                    Select All Available ({{ availableDaysCount }} days)
                                </label>
                            </div>
                            <!-- Individual Days -->
                            <div v-for="day in availableWeekdays" :key="day.value" class="day-option" :class="{ 'day-disabled': day.isDisabled }">
                                <Checkbox 
                                    v-model="scheduleForm.days" 
                                    :value="day.value" 
                                    :inputId="day.value"
                                    :disabled="day.isDisabled"
                                />
                                <label :for="day.value" class="ml-2" :class="{ 'cursor-pointer': !day.isDisabled, 'cursor-not-allowed text-gray-400': day.isDisabled }">
                                    {{ day.label }}
                                    <span v-if="day.isAlreadyScheduled" class="text-xs text-orange-600 ml-1">(Already Scheduled)</span>
                                </label>
                            </div>
                            
                            <!-- No Available Days Message -->
                            <div v-if="availableDaysCount === 0 && assignment" class="day-option">
                                <div class="text-center text-gray-500 py-4">
                                    <i class="pi pi-check-circle text-green-500 text-2xl mb-2"></i>
                                    <p class="font-semibold">All days are already scheduled!</p>
                                    <p class="text-sm">{{ assignment.subject_name }} has schedules for all weekdays.</p>
                                </div>
                            </div>
                        </div>
                        <small v-if="errors.days" class="p-error">{{ errors.days }}</small>
                        <small v-else class="p-help">💡 Select multiple days to create schedules for all at once</small>
                    </div>

                    <div class="form-group">
                        <label for="startTime">Start Time</label>
                        <Calendar 
                            id="startTime"
                            v-model="scheduleForm.start_time" 
                            timeOnly 
                            hourFormat="12"
                            placeholder="Select Start Time"
                            class="w-full"
                            :class="{ 'p-invalid': errors.start_time }"
                        />
                        <small v-if="errors.start_time" class="p-error">{{ errors.start_time }}</small>
                    </div>

                    <div class="form-group">
                        <label for="endTime">End Time</label>
                        <Calendar 
                            id="endTime"
                            v-model="scheduleForm.end_time" 
                            timeOnly 
                            hourFormat="12"
                            placeholder="Select End Time"
                            class="w-full"
                            :class="{ 'p-invalid': errors.end_time }"
                        />
                        <small v-if="errors.end_time" class="p-error">{{ errors.end_time }}</small>
                        <small v-else class="p-help">💡 Auto-calculated as 1 hour after start time</small>
                    </div>
                </div>

                <!-- Time Conflict Warning -->
                <div v-if="conflictWarning" class="conflict-warning">
                    <i class="pi pi-exclamation-triangle"></i>
                    <div>
                        <strong>Time Conflict Warning:</strong>
                        <p>{{ conflictWarning }}</p>
                    </div>
                </div>

                <!-- Available Time Slots Suggestion -->
                <div v-if="suggestedSlots.length > 0" class="suggested-slots">
                    <h4>Suggested Available Time Slots:</h4>
                    <div class="slots-grid">
                        <div 
                            v-for="slot in suggestedSlots" 
                            :key="`${slot.day}-${slot.start_time}`"
                            class="slot-suggestion"
                            @click="useSlot(slot)"
                        >
                            <div class="slot-day">{{ slot.day }}</div>
                            <div class="slot-time">{{ formatTimeRange(slot.start_time, slot.end_time) }}</div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <Button 
                        type="button"
                        label="Cancel" 
                        class="p-button-outlined"
                        @click="goBack"
                        :disabled="saving"
                    />
                    <Button 
                        type="submit"
                        label="Create Schedule" 
                        icon="pi pi-save"
                        :loading="saving"
                        :disabled="!isFormValid"
                    />
                </div>
            </form>
        </div>
    </div>
</template>
<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Dropdown from 'primevue/dropdown';
import Checkbox from 'primevue/checkbox';
import SubjectScheduleService from '@/services/SubjectScheduleService';
import TeacherAuthService from '@/services/TeacherAuthService';

const router = useRouter();
const route = useRoute();
const toast = useToast();

// Data
const assignment = ref(null);
const teacherAssignments = ref([]);
const selectedAssignmentOption = ref(null);
const isAssignmentFromRoute = ref(false);
const saving = ref(false);
const conflictWarning = ref('');
const suggestedSlots = ref([]);
const existingScheduledDays = ref([]);

const scheduleForm = ref({
    days: [], // Changed from 'day' to 'days' for multiple selection
    start_time: null,
    end_time: null
});

const errors = ref({});

const weekdays = ref([
    { label: 'Monday', value: 'Monday' },
    { label: 'Tuesday', value: 'Tuesday' },
    { label: 'Wednesday', value: 'Wednesday' },
    { label: 'Thursday', value: 'Thursday' },
    { label: 'Friday', value: 'Friday' }
]);

// Computed
const isFormValid = computed(() => {
    return scheduleForm.value.days.length > 0 && 
           scheduleForm.value.start_time && 
           scheduleForm.value.end_time &&
           Object.keys(errors.value).length === 0;
});

const availableWeekdays = computed(() => {
    return weekdays.value.map(day => ({
        ...day,
        isDisabled: existingScheduledDays.value.includes(day.value),
        isAlreadyScheduled: existingScheduledDays.value.includes(day.value)
    }));
});

const availableDaysCount = computed(() => {
    return availableWeekdays.value.filter(day => !day.isDisabled).length;
});

// Methods
const initializeForm = async () => {
    // Get assignment data from route query or create from params
    if (route.query.subject_name) {
        assignment.value = {
            subject_name: route.query.subject_name,
            section_name: route.query.section_name,
            section_id: parseInt(route.query.section_id),
            subject_id: parseInt(route.query.subject_id)
        };
    } else if (route.query.section_id && route.query.subject_id) {
        // Fallback: get from teacher assignments
        const teacherData = TeacherAuthService.getTeacherData();
        const assignments = teacherData?.assignments || [];
        
        const foundAssignment = assignments.find(a => 
            a.section_id == route.query.section_id && 
            a.subject_id == route.query.subject_id
        );
        
        if (foundAssignment) {
            assignment.value = foundAssignment;
        } else {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Assignment not found',
                life: 3000
            });
            goBack();
        }
    } else {
        // NO ASSIGNMENT - User will select from their assignments
        console.log('📝 No assignment pre-selected. Loading teacher assignments for selection.');
        isAssignmentFromRoute.value = false;
        assignment.value = null;
        
        // Load teacher assignments for selection
        const teacherData = TeacherAuthService.getTeacherData();
        console.log('📋 Raw teacher data:', teacherData);
        
        if (teacherData?.assignments && teacherData.assignments.length > 0) {
            console.log('📋 First assignment structure:', teacherData.assignments[0]);
            
            // Load existing schedules to filter out
            let existingSchedules = [];
            try {
                const teacherId = teacherData.teacher?.id || teacherData.id;
                const schedulesResponse = await SubjectScheduleService.getTeacherSchedules(teacherId);
                existingSchedules = schedulesResponse.data || [];
                console.log('📅 Existing schedules:', existingSchedules);
            } catch (error) {
                console.warn('Could not load existing schedules:', error);
            }
            
            teacherAssignments.value = teacherData.assignments
                .filter(a => {
                    // Filter out homeroom - homeroom is not a schedulable subject
                    const subjectName = a.subject_name || a.subjectName || a.subject?.name || '';
                    return subjectName.toLowerCase() !== 'homeroom';
                })
                .map(a => {
                    // Try different field name variations
                    const subjectName = a.subject_name || a.subjectName || a.subject?.name || 'Unknown Subject';
                    const sectionName = a.section_name || a.sectionName || a.section?.name || 'Unknown Section';
                    const sectionId = a.section_id || a.sectionId || a.section?.id;
                    const subjectId = a.subject_id || a.subjectId || a.subject?.id;
                    
                    return {
                        label: `${subjectName} - ${sectionName}`,
                        subject_name: subjectName,
                        section_name: sectionName,
                        section_id: sectionId,
                        subject_id: subjectId
                    };
                })
                .filter(assignment => {
                    // Only filter out assignments that have schedules for ALL weekdays
                    const assignmentSchedules = existingSchedules.filter(schedule => 
                        schedule.section_id == assignment.section_id && 
                        schedule.subject_id == assignment.subject_id
                    );
                    
                    // Get unique days that are already scheduled
                    const scheduledDays = [...new Set(assignmentSchedules.map(s => s.day))];
                    const allWeekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                    
                    // Only exclude if ALL weekdays are scheduled
                    const hasAllDaysScheduled = allWeekdays.every(day => scheduledDays.includes(day));
                    
                    console.log(`📅 ${assignment.subject_name} - ${assignment.section_name}:`, {
                        scheduledDays,
                        hasAllDaysScheduled,
                        willBeIncluded: !hasAllDaysScheduled
                    });
                    
                    return !hasAllDaysScheduled;
                });
                
            console.log('📋 Available assignments (filtered):', teacherAssignments.value);
            
            if (teacherAssignments.value.length === 0) {
                toast.add({
                    severity: 'info',
                    summary: 'All Subjects Scheduled',
                    detail: 'You have already created schedules for all your assignments!',
                    life: 5000
                });
                setTimeout(() => goBack(), 2000);
                return;
            }
        } else {
            toast.add({
                severity: 'warn',
                summary: 'No Assignments',
                detail: 'You have no subject assignments. Please contact admin.',
                life: 5000
            });
        }
        return; // Don't set assignment yet - wait for user selection
    }
    
    // Mark that assignment came from route
    if (assignment.value) {
        isAssignmentFromRoute.value = true;
        // Load existing schedules for this assignment
        await loadExistingSchedulesForAssignment(assignment.value);
    }
    
    console.log('📝 Creating schedule for assignment:', assignment.value);
};

const validateForm = () => {
    errors.value = {};
    
    if (!scheduleForm.value.days || scheduleForm.value.days.length === 0) {
        errors.value.days = 'At least one day is required';
    }
    
    if (!scheduleForm.value.start_time) {
        errors.value.start_time = 'Start time is required';
    }
    
    if (!scheduleForm.value.end_time) {
        errors.value.end_time = 'End time is required';
    }
    
    if (scheduleForm.value.start_time && scheduleForm.value.end_time) {
        if (scheduleForm.value.start_time >= scheduleForm.value.end_time) {
            errors.value.end_time = 'End time must be after start time';
        }
    }
    
    return Object.keys(errors.value).length === 0;
};

const checkTimeConflicts = async () => {
    if (!scheduleForm.value.day || !scheduleForm.value.start_time || !scheduleForm.value.end_time) {
        return;
    }
    
    try {
        const teacherData = TeacherAuthService.getTeacherData();
        const teacherId = teacherData?.teacher?.id || teacherData?.id;
        
        const response = await SubjectScheduleService.checkTimeConflict({
            teacher_id: teacherId,
            day: scheduleForm.value.day,
            start_time: formatTimeForAPI(scheduleForm.value.start_time),
            end_time: formatTimeForAPI(scheduleForm.value.end_time)
        });
        
        if (response.data.has_conflict) {
            conflictWarning.value = response.data.message;
        } else {
            conflictWarning.value = '';
        }
    } catch (error) {
        console.error('Error checking conflicts:', error);
    }
};

const loadSuggestedSlots = async () => {
    try {
        const teacherData = TeacherAuthService.getTeacherData();
        const teacherId = teacherData?.teacher?.id || teacherData?.id;
        
        const response = await SubjectScheduleService.getAvailableTimeSlots({
            teacher_id: teacherId,
            day: scheduleForm.value.day
        });
        
        suggestedSlots.value = response.data.available_slots || [];
    } catch (error) {
        console.error('Error loading suggested slots:', error);
        suggestedSlots.value = [];
    }
};

const useSlot = (slot) => {
    scheduleForm.value.day = slot.day;
    scheduleForm.value.start_time = parseTimeFromAPI(slot.start_time);
    scheduleForm.value.end_time = parseTimeFromAPI(slot.end_time);
    
    toast.add({
        severity: 'info',
        summary: 'Time Slot Selected',
        detail: `Selected ${slot.day} ${formatTimeRange(slot.start_time, slot.end_time)}`,
        life: 3000
    });
};

const formatTimeForAPI = (timeDate) => {
    if (!timeDate) return '';
    return timeDate.toTimeString().slice(0, 8); // HH:MM:SS format
};

const parseTimeFromAPI = (timeString) => {
    if (!timeString) return null;
    const [hours, minutes] = timeString.split(':');
    const date = new Date();
    date.setHours(parseInt(hours), parseInt(minutes), 0, 0);
    return date;
};

const formatTimeRange = (startTime, endTime) => {
    const formatTime = (time) => {
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        return `${displayHour}:${minutes} ${ampm}`;
    };
    
    return `${formatTime(startTime)} - ${formatTime(endTime)}`;
};

const saveSchedule = async () => {
    if (!validateForm()) {
        return;
    }
    
    saving.value = true;
    
    try {
        const teacherData = TeacherAuthService.getTeacherData();
        const teacherId = teacherData?.teacher?.id || teacherData?.id;
        
        const successfulDays = [];
        const failedDays = [];
        
        // Create schedules for each selected day
        for (const day of scheduleForm.value.days) {
            try {
                const scheduleData = {
                    teacher_id: teacherId,
                    section_id: assignment.value.section_id,
                    subject_id: assignment.value.subject_id,
                    day: day,
                    start_time: formatTimeForAPI(scheduleForm.value.start_time),
                    end_time: formatTimeForAPI(scheduleForm.value.end_time)
                };
                
                console.log(`💾 Saving schedule for ${day}:`, scheduleData);
                
                const response = await SubjectScheduleService.saveSchedule(scheduleData);
                
                if (response.success) {
                    successfulDays.push(day);
                } else {
                    failedDays.push(day);
                }
            } catch (dayError) {
                console.error(`Error saving schedule for ${day}:`, dayError);
                failedDays.push(day);
                
                // Check if it's a conflict error (409)
                if (dayError.response?.status === 409) {
                    toast.add({
                        severity: 'error',
                        summary: 'Schedule Conflict!',
                        detail: `You already have a schedule on ${day} at this time. Please choose a different time or day.`,
                        life: 6000
                    });
                }
            }
        }
        
        // Show results
        if (successfulDays.length > 0) {
            toast.add({
                severity: 'success',
                summary: 'Schedules Created',
                detail: `Schedules created for ${assignment.value.subject_name} on: ${successfulDays.join(', ')}`,
                life: 5000
            });
        }
        
        if (failedDays.length > 0) {
            toast.add({
                severity: 'warn',
                summary: 'Some Schedules Failed',
                detail: `Failed to create schedules for: ${failedDays.join(', ')}`,
                life: 5000
            });
        }
        
        // Go back to schedules page if at least one was successful
        if (successfulDays.length > 0) {
            router.push('/teacher/schedules');
        }
        
    } catch (error) {
        console.error('Error saving schedules:', error);
        
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || error.message || 'Failed to create schedules',
            life: 5000
        });
    } finally {
        saving.value = false;
    }
};

const selectAllDays = () => {
    const availableDays = availableWeekdays.value.filter(day => !day.isDisabled);
    const availableDayValues = availableDays.map(day => day.value);
    
    if (scheduleForm.value.days.length === availableDayValues.length) {
        // Deselect all available days
        scheduleForm.value.days = [];
    } else {
        // Select all available days (excluding already scheduled ones)
        scheduleForm.value.days = availableDayValues;
    }
};

const loadExistingSchedulesForAssignment = async (assignmentData) => {
    if (!assignmentData) {
        existingScheduledDays.value = [];
        return;
    }
    
    try {
        const teacherData = TeacherAuthService.getTeacherData();
        const teacherId = teacherData?.teacher?.id || teacherData?.id;
        const schedulesResponse = await SubjectScheduleService.getTeacherSchedules(teacherId);
        const existingSchedules = schedulesResponse.data || [];
        
        // Find schedules for this specific assignment
        const assignmentSchedules = existingSchedules.filter(schedule => 
            schedule.section_id == assignmentData.section_id && 
            schedule.subject_id == assignmentData.subject_id
        );
        
        // Get the days that are already scheduled
        existingScheduledDays.value = [...new Set(assignmentSchedules.map(s => s.day))];
        
        console.log(`📅 ${assignmentData.subject_name} existing scheduled days:`, existingScheduledDays.value);
        
        // Clear any selected days that are already scheduled
        scheduleForm.value.days = scheduleForm.value.days.filter(day => 
            !existingScheduledDays.value.includes(day)
        );
        
    } catch (error) {
        console.error('Error loading existing schedules:', error);
        existingScheduledDays.value = [];
    }
};

const onAssignmentChange = async () => {
    if (selectedAssignmentOption.value) {
        assignment.value = {
            subject_name: selectedAssignmentOption.value.subject_name,
            section_name: selectedAssignmentOption.value.section_name,
            section_id: selectedAssignmentOption.value.section_id,
            subject_id: selectedAssignmentOption.value.subject_id
        };
        console.log('✅ Assignment selected:', assignment.value);
        
        // Load existing schedules for this assignment
        await loadExistingSchedulesForAssignment(assignment.value);
    }
};

const goBack = () => {
    router.push('/teacher/schedules');
};

// Watchers
watch([() => scheduleForm.value.days, () => scheduleForm.value.start_time, () => scheduleForm.value.end_time], 
    () => {
        checkTimeConflicts();
    }, 
    { deep: true }
);

watch(() => scheduleForm.value.days, () => {
    if (scheduleForm.value.days && scheduleForm.value.days.length > 0) {
        loadSuggestedSlots();
    }
});

// Auto-calculate end time when start time changes (Quality of Life improvement)
watch(() => scheduleForm.value.start_time, (newStartTime, oldStartTime) => {
    if (newStartTime) {
        // Always auto-set end time to 1 hour after start time when start time changes
        const endTime = new Date(newStartTime);
        endTime.setHours(endTime.getHours() + 1);
        scheduleForm.value.end_time = endTime;
        
        console.log('🕒 Auto-calculated end time:', formatTimeForAPI(endTime));
    }
});

// Lifecycle
onMounted(() => {
    initializeForm();
});
</script>

<style scoped>
/* Custom Days Selector Styling */
.custom-days-selector {
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 0.75rem;
    background: white;
}

.custom-days-selector.p-invalid {
    border-color: #ef4444;
}

.day-option {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.day-option:hover {
    background-color: #f3f4f6;
}

.day-option.select-all-option {
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 0.5rem;
    padding-bottom: 0.75rem;
}

.day-option label {
    margin-bottom: 0;
}

.day-option.day-disabled {
    opacity: 0.6;
    background-color: #f9fafb;
}

.day-option.day-disabled:hover {
    background-color: #f9fafb;
}

.create-schedule-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 2rem;
}

.schedule-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
}

.schedule-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0;
}

.schedule-form-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
}

.assignment-info {
    background: #f8fafc;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-left: 4px solid #3b82f6;
}

.assignment-info h3 {
    margin: 0 0 1rem 0;
    color: #1f2937;
    font-size: 1.125rem;
}

.assignment-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.detail-item .label {
    font-weight: 500;
    color: #6b7280;
    min-width: 60px;
}

.detail-item .value {
    font-weight: 600;
    color: #1f2937;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form-group label {
    font-weight: 500;
    color: #374151;
    font-size: 0.875rem;
}

.conflict-warning {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    background: #fef3cd;
    border: 1px solid #f59e0b;
    border-radius: 6px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.conflict-warning i {
    color: #f59e0b;
    margin-top: 0.125rem;
}

.conflict-warning strong {
    color: #92400e;
}

.conflict-warning p {
    margin: 0.25rem 0 0 0;
    color: #92400e;
    font-size: 0.875rem;
}

.suggested-slots {
    background: #f0f9ff;
    border: 1px solid #0ea5e9;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.suggested-slots h4 {
    margin: 0 0 1rem 0;
    color: #0c4a6e;
    font-size: 1rem;
}

.slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.75rem;
}

.slot-suggestion {
    background: white;
    border: 1px solid #0ea5e9;
    border-radius: 6px;
    padding: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}

.slot-suggestion:hover {
    background: #0ea5e9;
    color: white;
    transform: translateY(-1px);
}

.slot-day {
    font-weight: 600;
    font-size: 0.875rem;
}

.slot-time {
    font-size: 0.75rem;
    opacity: 0.8;
    margin-top: 0.25rem;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

@media (max-width: 768px) {
    .create-schedule-container {
        padding: 1rem;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .assignment-details {
        gap: 0.5rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>
