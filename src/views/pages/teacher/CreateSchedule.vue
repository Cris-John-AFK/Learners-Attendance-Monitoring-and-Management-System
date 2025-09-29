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
            <div class="assignment-info">
                <h3>Creating Schedule For:</h3>
                <div class="assignment-details">
                    <div class="detail-item">
                        <i class="pi pi-book text-blue-600"></i>
                        <span class="label">Subject:</span>
                        <span class="value">{{ assignment?.subject_name }}</span>
                    </div>
                    <div class="detail-item">
                        <i class="pi pi-users text-green-600"></i>
                        <span class="label">Section:</span>
                        <span class="value">{{ assignment?.section_name }}</span>
                    </div>
                </div>
            </div>

            <form @submit.prevent="saveSchedule" class="schedule-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="days">Days of Week</label>
                        <MultiSelect 
                            id="days"
                            v-model="scheduleForm.days" 
                            :options="weekdays" 
                            optionLabel="label" 
                            optionValue="value"
                            placeholder="Select Days"
                            class="w-full"
                            :class="{ 'p-invalid': errors.days }"
                            display="chip"
                            :maxSelectedLabels="3"
                        />
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
import Dropdown from 'primevue/dropdown';
import MultiSelect from 'primevue/multiselect';
import Calendar from 'primevue/calendar';
import SubjectScheduleService from '@/services/SubjectScheduleService';
import TeacherAuthService from '@/services/TeacherAuthService';

const router = useRouter();
const route = useRoute();
const toast = useToast();

// Data
const assignment = ref(null);
const saving = ref(false);
const conflictWarning = ref('');
const suggestedSlots = ref([]);

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

// Methods
const initializeForm = () => {
    // Get assignment data from route query or create from params
    if (route.query.subject_name) {
        assignment.value = {
            subject_name: route.query.subject_name,
            section_name: route.query.section_name,
            section_id: parseInt(route.query.section_id),
            subject_id: parseInt(route.query.subject_id)
        };
    } else {
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
    if (newStartTime && !scheduleForm.value.end_time) {
        // Auto-set end time to 1 hour after start time
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
