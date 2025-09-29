<template>
    <div class="subject-scheduling-container">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="pi pi-calendar mr-2"></i>
                    Subject Scheduling Management
                </h2>
                <p class="card-subtitle">
                    Manage subject schedules for all sections and teachers. Prevent time conflicts and ensure proper scheduling.
                </p>
            </div>

            <!-- Filters -->
            <div class="filters-section mb-4">
                <div class="grid">
                    <div class="col-12 md:col-4">
                        <label for="sectionFilter" class="block text-sm font-medium mb-2">Filter by Section</label>
                        <Select 
                            v-model="selectedSectionFilter" 
                            :options="sections" 
                            optionLabel="name" 
                            optionValue="id"
                            placeholder="All Sections"
                            class="w-full"
                            @change="loadSchedules"
                        />
                    </div>
                    <div class="col-12 md:col-4">
                        <label for="teacherFilter" class="block text-sm font-medium mb-2">Filter by Teacher</label>
                        <Select 
                            v-model="selectedTeacherFilter" 
                            :options="teachers" 
                            optionLabel="full_name" 
                            optionValue="id"
                            placeholder="All Teachers"
                            class="w-full"
                            @change="loadSchedules"
                        />
                    </div>
                    <div class="col-12 md:col-4">
                        <label class="block text-sm font-medium mb-2">&nbsp;</label>
                        <Button 
                            label="Add New Schedule" 
                            icon="pi pi-plus" 
                            class="w-full"
                            @click="openScheduleDialog()"
                        />
                    </div>
                </div>
            </div>

            <!-- Schedule Table -->
            <div class="schedule-table-container">
                <DataTable 
                    :value="schedules" 
                    :loading="loading"
                    paginator 
                    :rows="10"
                    :rowsPerPageOptions="[10, 25, 50]"
                    responsiveLayout="scroll"
                    class="p-datatable-sm"
                >
                    <Column field="section_name" header="Section" sortable>
                        <template #body="{ data }">
                            <span class="font-semibold">{{ data.section_name }}</span>
                        </template>
                    </Column>
                    
                    <Column field="subject_name" header="Subject" sortable>
                        <template #body="{ data }">
                            <Tag :value="data.subject_name" severity="info" />
                        </template>
                    </Column>
                    
                    <Column field="teacher_name" header="Teacher" sortable>
                        <template #body="{ data }">
                            {{ data.teacher_first_name }} {{ data.teacher_last_name }}
                        </template>
                    </Column>
                    
                    <Column field="day" header="Day" sortable>
                        <template #body="{ data }">
                            <span class="capitalize">{{ SubjectScheduleService.getDayDisplayName(data.day) }}</span>
                        </template>
                    </Column>
                    
                    <Column field="time_range" header="Time" sortable>
                        <template #body="{ data }">
                            <span class="font-mono">
                                {{ SubjectScheduleService.formatTimeRange(data.start_time, data.end_time) }}
                            </span>
                        </template>
                    </Column>
                    
                    <Column header="Actions" :exportable="false">
                        <template #body="{ data }">
                            <div class="flex gap-2">
                                <Button 
                                    icon="pi pi-pencil" 
                                    size="small" 
                                    severity="info"
                                    @click="openScheduleDialog(data)"
                                    v-tooltip.top="'Edit Schedule'"
                                />
                                <Button 
                                    icon="pi pi-trash" 
                                    size="small" 
                                    severity="danger"
                                    @click="confirmDeleteSchedule(data)"
                                    v-tooltip.top="'Delete Schedule'"
                                />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <!-- Schedule Dialog -->
        <Dialog 
            v-model:visible="showScheduleDialog" 
            :header="editingSchedule ? 'Edit Schedule' : 'Add New Schedule'"
            :modal="true" 
            :closable="true"
            :style="{ width: '600px' }"
            @hide="resetScheduleForm"
        >
            <div class="schedule-form">
                <div class="grid">
                    <!-- Teacher Selection -->
                    <div class="col-12 md:col-6">
                        <label for="teacher" class="block text-sm font-medium mb-2">Teacher *</label>
                        <Select 
                            v-model="scheduleForm.teacher_id" 
                            :options="teachers" 
                            optionLabel="full_name" 
                            optionValue="id"
                            placeholder="Select Teacher"
                            class="w-full"
                            :class="{ 'p-invalid': formErrors.teacher_id }"
                            @change="onTeacherChange"
                        />
                        <small v-if="formErrors.teacher_id" class="p-error">{{ formErrors.teacher_id }}</small>
                    </div>

                    <!-- Section Selection -->
                    <div class="col-12 md:col-6">
                        <label for="section" class="block text-sm font-medium mb-2">Section *</label>
                        <Select 
                            v-model="scheduleForm.section_id" 
                            :options="availableSections" 
                            optionLabel="name" 
                            optionValue="id"
                            placeholder="Select Section"
                            class="w-full"
                            :class="{ 'p-invalid': formErrors.section_id }"
                            @change="onSectionChange"
                        />
                        <small v-if="formErrors.section_id" class="p-error">{{ formErrors.section_id }}</small>
                    </div>

                    <!-- Subject Selection -->
                    <div class="col-12">
                        <label for="subject" class="block text-sm font-medium mb-2">Subject *</label>
                        <Select 
                            v-model="scheduleForm.subject_id" 
                            :options="availableSubjects" 
                            optionLabel="name" 
                            optionValue="id"
                            placeholder="Select Subject"
                            class="w-full"
                            :class="{ 'p-invalid': formErrors.subject_id }"
                        />
                        <small v-if="formErrors.subject_id" class="p-error">{{ formErrors.subject_id }}</small>
                    </div>

                    <!-- Day Selection -->
                    <div class="col-12 md:col-4">
                        <label for="day" class="block text-sm font-medium mb-2">Day *</label>
                        <Select 
                            v-model="scheduleForm.day" 
                            :options="weekdays" 
                            optionLabel="label" 
                            optionValue="value"
                            placeholder="Select Day"
                            class="w-full"
                            :class="{ 'p-invalid': formErrors.day }"
                            @change="onDayChange"
                        />
                        <small v-if="formErrors.day" class="p-error">{{ formErrors.day }}</small>
                    </div>

                    <!-- Time Slot Selection -->
                    <div class="col-12 md:col-8">
                        <label for="timeSlot" class="block text-sm font-medium mb-2">Time Slot *</label>
                        <Select 
                            v-model="selectedTimeSlot" 
                            :options="availableTimeSlots" 
                            optionLabel="label" 
                            placeholder="Select Time Slot"
                            class="w-full"
                            :class="{ 'p-invalid': formErrors.time_slot }"
                            :loading="loadingTimeSlots"
                            @change="onTimeSlotChange"
                        />
                        <small v-if="formErrors.time_slot" class="p-error">{{ formErrors.time_slot }}</small>
                        <small v-if="availableTimeSlots.length === 0 && scheduleForm.section_id && scheduleForm.day" class="text-orange-600">
                            No available time slots for this section on {{ SubjectScheduleService.getDayDisplayName(scheduleForm.day) }}
                        </small>
                    </div>

                    <!-- Custom Time (Optional) -->
                    <div class="col-12">
                        <div class="flex align-items-center mb-2">
                            <Checkbox v-model="useCustomTime" inputId="customTime" />
                            <label for="customTime" class="ml-2 text-sm font-medium">Use Custom Time</label>
                        </div>
                        
                        <div v-if="useCustomTime" class="grid">
                            <div class="col-6">
                                <label class="block text-sm font-medium mb-2">Start Time *</label>
                                <Calendar 
                                    v-model="customStartTime" 
                                    timeOnly 
                                    hourFormat="12"
                                    class="w-full"
                                    :class="{ 'p-invalid': formErrors.start_time }"
                                />
                                <small v-if="formErrors.start_time" class="p-error">{{ formErrors.start_time }}</small>
                            </div>
                            <div class="col-6">
                                <label class="block text-sm font-medium mb-2">End Time *</label>
                                <Calendar 
                                    v-model="customEndTime" 
                                    timeOnly 
                                    hourFormat="12"
                                    class="w-full"
                                    :class="{ 'p-invalid': formErrors.end_time }"
                                />
                                <small v-if="formErrors.end_time" class="p-error">{{ formErrors.end_time }}</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Conflict Warning -->
                <div v-if="conflictWarning" class="mt-4">
                    <Message severity="warn" :closable="false">
                        <strong>Time Conflict Detected:</strong>
                        {{ conflictWarning }}
                    </Message>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-content-end gap-2">
                    <Button 
                        label="Cancel" 
                        icon="pi pi-times" 
                        severity="secondary"
                        @click="showScheduleDialog = false"
                    />
                    <Button 
                        :label="editingSchedule ? 'Update' : 'Save'" 
                        icon="pi pi-check" 
                        :loading="saving"
                        @click="saveSchedule"
                    />
                </div>
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';

// PrimeVue Components
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';

// Services
import SubjectScheduleService from '@/services/SubjectScheduleService';
import { TeacherService } from '@/router/service/TeacherService';
import { SectionService } from '@/router/service/SectionService';
import { SubjectService } from '@/router/service/Subjects';

const toast = useToast();
const confirm = useConfirm();

// Data
const loading = ref(false);
const saving = ref(false);
const loadingTimeSlots = ref(false);

const schedules = ref([]);
const teachers = ref([]);
const sections = ref([]);
const subjects = ref([]);

// Filters
const selectedSectionFilter = ref(null);
const selectedTeacherFilter = ref(null);

// Schedule Dialog
const showScheduleDialog = ref(false);
const editingSchedule = ref(null);
const useCustomTime = ref(false);
const customStartTime = ref(null);
const customEndTime = ref(null);
const selectedTimeSlot = ref(null);
const availableTimeSlots = ref([]);
const conflictWarning = ref('');

// Form Data
const scheduleForm = ref({
    teacher_id: null,
    section_id: null,
    subject_id: null,
    day: null,
    start_time: null,
    end_time: null
});

const formErrors = ref({});

// Computed Properties
const weekdays = computed(() => SubjectScheduleService.getWeekdays());

const availableSections = computed(() => {
    if (!scheduleForm.value.teacher_id) return [];
    
    // Get sections assigned to the selected teacher
    return sections.value.filter(section => {
        // This would need to be filtered based on teacher assignments
        // For now, return all sections
        return true;
    });
});

const availableSubjects = computed(() => {
    if (!scheduleForm.value.teacher_id || !scheduleForm.value.section_id) return [];
    
    // Get subjects assigned to the selected teacher for the selected section
    return subjects.value.filter(subject => {
        // This would need to be filtered based on teacher-section-subject assignments
        // For now, return all subjects
        return true;
    });
});

// Methods
const loadInitialData = async () => {
    loading.value = true;
    try {
        await Promise.all([
            loadTeachers(),
            loadSections(),
            loadSubjects(),
            loadSchedules()
        ]);
    } catch (error) {
        console.error('Error loading initial data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load initial data',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

const loadTeachers = async () => {
    try {
        const response = await TeacherService.getTeachers();
        teachers.value = response.map(teacher => ({
            ...teacher,
            full_name: `${teacher.firstName} ${teacher.lastName}`
        }));
    } catch (error) {
        console.error('Error loading teachers:', error);
    }
};

const loadSections = async () => {
    try {
        const response = await SectionService.getSections();
        sections.value = response;
    } catch (error) {
        console.error('Error loading sections:', error);
    }
};

const loadSubjects = async () => {
    try {
        const response = await SubjectService.getSubjects();
        subjects.value = response;
    } catch (error) {
        console.error('Error loading subjects:', error);
    }
};

const loadSchedules = async () => {
    try {
        const filters = {};
        if (selectedSectionFilter.value) filters.section_id = selectedSectionFilter.value;
        if (selectedTeacherFilter.value) filters.teacher_id = selectedTeacherFilter.value;
        
        const response = await SubjectScheduleService.getAllSchedules(filters);
        schedules.value = response.data || [];
    } catch (error) {
        console.error('Error loading schedules:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load schedules',
            life: 3000
        });
    }
};

const loadAvailableTimeSlots = async () => {
    if (!scheduleForm.value.section_id || !scheduleForm.value.day) {
        availableTimeSlots.value = [];
        return;
    }

    loadingTimeSlots.value = true;
    try {
        const response = await SubjectScheduleService.getAvailableTimeSlots(
            scheduleForm.value.section_id,
            scheduleForm.value.day,
            editingSchedule.value?.id
        );
        availableTimeSlots.value = response.data || [];
    } catch (error) {
        console.error('Error loading available time slots:', error);
        availableTimeSlots.value = [];
    } finally {
        loadingTimeSlots.value = false;
    }
};

const openScheduleDialog = (schedule = null) => {
    editingSchedule.value = schedule;
    
    if (schedule) {
        scheduleForm.value = {
            teacher_id: schedule.teacher_id,
            section_id: schedule.section_id,
            subject_id: schedule.subject_id,
            day: schedule.day,
            start_time: schedule.start_time,
            end_time: schedule.end_time
        };
        
        // Set the selected time slot if it matches a predefined slot
        const timeSlot = availableTimeSlots.value.find(slot => 
            slot.start_time === schedule.start_time && slot.end_time === schedule.end_time
        );
        selectedTimeSlot.value = timeSlot || null;
        
        if (!timeSlot) {
            useCustomTime.value = true;
            customStartTime.value = new Date(`2000-01-01T${schedule.start_time}`);
            customEndTime.value = new Date(`2000-01-01T${schedule.end_time}`);
        }
    } else {
        resetScheduleForm();
    }
    
    showScheduleDialog.value = true;
};

const resetScheduleForm = () => {
    scheduleForm.value = {
        teacher_id: null,
        section_id: null,
        subject_id: null,
        day: null,
        start_time: null,
        end_time: null
    };
    formErrors.value = {};
    selectedTimeSlot.value = null;
    useCustomTime.value = false;
    customStartTime.value = null;
    customEndTime.value = null;
    availableTimeSlots.value = [];
    conflictWarning.value = '';
};

const onTeacherChange = () => {
    scheduleForm.value.section_id = null;
    scheduleForm.value.subject_id = null;
    availableTimeSlots.value = [];
    selectedTimeSlot.value = null;
};

const onSectionChange = () => {
    scheduleForm.value.subject_id = null;
    if (scheduleForm.value.day) {
        loadAvailableTimeSlots();
    }
};

const onDayChange = () => {
    selectedTimeSlot.value = null;
    scheduleForm.value.start_time = null;
    scheduleForm.value.end_time = null;
    loadAvailableTimeSlots();
};

const onTimeSlotChange = () => {
    if (selectedTimeSlot.value) {
        scheduleForm.value.start_time = selectedTimeSlot.value.start_time;
        scheduleForm.value.end_time = selectedTimeSlot.value.end_time;
        useCustomTime.value = false;
    }
};

const validateForm = () => {
    formErrors.value = {};
    
    if (!scheduleForm.value.teacher_id) {
        formErrors.value.teacher_id = 'Teacher is required';
    }
    
    if (!scheduleForm.value.section_id) {
        formErrors.value.section_id = 'Section is required';
    }
    
    if (!scheduleForm.value.subject_id) {
        formErrors.value.subject_id = 'Subject is required';
    }
    
    if (!scheduleForm.value.day) {
        formErrors.value.day = 'Day is required';
    }
    
    if (useCustomTime.value) {
        if (!customStartTime.value) {
            formErrors.value.start_time = 'Start time is required';
        }
        if (!customEndTime.value) {
            formErrors.value.end_time = 'End time is required';
        }
        if (customStartTime.value && customEndTime.value && customStartTime.value >= customEndTime.value) {
            formErrors.value.end_time = 'End time must be after start time';
        }
    } else {
        if (!selectedTimeSlot.value) {
            formErrors.value.time_slot = 'Time slot is required';
        }
    }
    
    return Object.keys(formErrors.value).length === 0;
};

const saveSchedule = async () => {
    if (!validateForm()) return;
    
    saving.value = true;
    conflictWarning.value = '';
    
    try {
        // Prepare schedule data
        const scheduleData = { ...scheduleForm.value };
        
        if (useCustomTime.value) {
            scheduleData.start_time = customStartTime.value.toTimeString().split(' ')[0];
            scheduleData.end_time = customEndTime.value.toTimeString().split(' ')[0];
        }
        
        if (editingSchedule.value) {
            scheduleData.id = editingSchedule.value.id;
        }
        
        const response = await SubjectScheduleService.saveSchedule(scheduleData);
        
        if (response.success) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: response.message,
                life: 3000
            });
            
            showScheduleDialog.value = false;
            await loadSchedules();
        } else {
            throw new Error(response.message);
        }
    } catch (error) {
        console.error('Error saving schedule:', error);
        
        if (error.response?.status === 409) {
            // Time conflict
            conflictWarning.value = error.response.data.message;
        } else {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: error.response?.data?.message || 'Failed to save schedule',
                life: 3000
            });
        }
    } finally {
        saving.value = false;
    }
};

const confirmDeleteSchedule = (schedule) => {
    confirm.require({
        message: `Are you sure you want to delete the schedule for ${schedule.subject_name} in ${schedule.section_name}?`,
        header: 'Confirm Delete',
        icon: 'pi pi-exclamation-triangle',
        rejectClass: 'p-button-secondary p-button-outlined',
        rejectLabel: 'Cancel',
        acceptLabel: 'Delete',
        accept: () => deleteSchedule(schedule.id)
    });
};

const deleteSchedule = async (scheduleId) => {
    try {
        const response = await SubjectScheduleService.deleteSchedule(scheduleId);
        
        if (response.success) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Schedule deleted successfully',
                life: 3000
            });
            
            await loadSchedules();
        } else {
            throw new Error(response.message);
        }
    } catch (error) {
        console.error('Error deleting schedule:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete schedule',
            life: 3000
        });
    }
};

// Watchers
watch([() => scheduleForm.value.section_id, () => scheduleForm.value.day], () => {
    if (scheduleForm.value.section_id && scheduleForm.value.day) {
        loadAvailableTimeSlots();
    }
});

watch([customStartTime, customEndTime], () => {
    if (useCustomTime.value && customStartTime.value && customEndTime.value) {
        scheduleForm.value.start_time = customStartTime.value.toTimeString().split(' ')[0];
        scheduleForm.value.end_time = customEndTime.value.toTimeString().split(' ')[0];
    }
});

// Lifecycle
onMounted(() => {
    loadInitialData();
});
</script>

<style scoped>
.subject-scheduling-container {
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

.filters-section {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
}

.schedule-table-container {
    margin-top: 1rem;
}

.schedule-form {
    padding: 1rem 0;
}

.capitalize {
    text-transform: capitalize;
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
</style>
