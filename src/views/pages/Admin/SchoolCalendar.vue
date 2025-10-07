<template>
    <div class="school-calendar-manager p-4">
        <div class="card">
            <div class="flex justify-between items-center mb-4 p-4 border-b">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">School Calendar Management</h2>
                    <p class="text-sm text-gray-600 mt-1">Manage holidays, half-days, and special events</p>
                </div>
                <Button icon="pi pi-plus" label="Add Event" @click="openEventDialog()" severity="success" />
            </div>

            <!-- Event Type Filter -->
            <div class="flex gap-3 p-4 bg-gray-50 mb-4">
                <Button 
                    v-for="type in eventTypes" 
                    :key="type.value"
                    :label="type.label"
                    :icon="type.icon"
                    :severity="selectedType === type.value ? 'primary' : 'secondary'"
                    @click="selectedType = type.value"
                    outlined
                    size="small"
                />
                <Button 
                    label="All Events" 
                    icon="pi pi-list"
                    :severity="selectedType === null ? 'primary' : 'secondary'"
                    @click="selectedType = null"
                    outlined
                    size="small"
                />
            </div>

            <!-- Events DataTable -->
            <DataTable 
                :value="filteredEvents" 
                :loading="loading"
                paginator 
                :rows="10"
                dataKey="id"
                :rowHover="true"
                showGridlines
                responsiveLayout="scroll"
            >
                <Column field="title" header="Event Title" sortable>
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <span>{{ getEventIcon(data.event_type) }}</span>
                            <span class="font-semibold">{{ data.title }}</span>
                        </div>
                    </template>
                </Column>

                <Column field="event_type" header="Type" sortable>
                    <template #body="{ data }">
                        <Tag :value="formatEventType(data.event_type)" :severity="getEventSeverity(data.event_type)" />
                    </template>
                </Column>

                <Column field="start_date" header="Start Date" sortable>
                    <template #body="{ data }">
                        {{ formatDate(data.start_date) }}
                    </template>
                </Column>

                <Column field="end_date" header="End Date" sortable>
                    <template #body="{ data }">
                        {{ formatDate(data.end_date) }}
                    </template>
                </Column>


                <Column header="Actions" style="width: 150px">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button icon="pi pi-pencil" severity="info" outlined size="small" @click="editEvent(data)" />
                            <Button icon="pi pi-trash" severity="danger" outlined size="small" @click="confirmDelete(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Add/Edit Event Dialog -->
        <Dialog v-model:visible="eventDialog" :header="editingEvent ? 'Edit Calendar Event' : 'Add Calendar Event'" :modal="true" :style="{ width: '600px' }">
            <div class="flex flex-col gap-4">
                <!-- Title -->
                <div class="field">
                    <label for="title" class="font-semibold">Event Title *</label>
                    <InputText id="title" v-model="eventForm.title" placeholder="e.g., Christmas Holiday" class="w-full" />
                </div>

                <!-- Description -->
                <div class="field">
                    <label for="description" class="font-semibold">Description</label>
                    <Textarea id="description" v-model="eventForm.description" rows="3" class="w-full" />
                </div>

                <!-- Event Type -->
                <div class="field">
                    <label for="event_type" class="font-semibold">Event Type *</label>
                    <Dropdown 
                        id="event_type"
                        v-model="eventForm.event_type" 
                        :options="eventTypes"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select event type"
                        class="w-full"
                    />
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="field">
                        <label for="start_date" class="font-semibold">Start Date *</label>
                        <Calendar id="start_date" v-model="eventForm.start_date" dateFormat="yy-mm-dd" showIcon class="w-full" />
                    </div>
                    <div class="field">
                        <label for="end_date" class="font-semibold">End Date *</label>
                        <Calendar id="end_date" v-model="eventForm.end_date" dateFormat="yy-mm-dd" showIcon class="w-full" />
                    </div>
                </div>

                <!-- Affects Attendance -->

                <!-- Half-Day Times (if applicable) -->
                <div v-if="eventForm.event_type === 'half_day'" class="grid grid-cols-2 gap-4">
                    <div class="field">
                        <label for="modified_start_time" class="font-semibold">Modified Start Time</label>
                        <Calendar id="modified_start_time" v-model="eventForm.modified_start_time" timeOnly showIcon class="w-full" />
                    </div>
                    <div class="field">
                        <label for="modified_end_time" class="font-semibold">Modified End Time</label>
                        <Calendar id="modified_end_time" v-model="eventForm.modified_end_time" timeOnly showIcon class="w-full" />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="eventDialog = false" text />
                <Button label="Save" icon="pi pi-check" @click="saveEvent" :loading="saving" />
            </template>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:visible="deleteDialog" header="Confirm Delete" :modal="true" :style="{ width: '450px' }">
            <div class="flex items-center gap-3">
                <i class="pi pi-exclamation-triangle text-red-500" style="font-size: 2rem"></i>
                <span>Are you sure you want to delete <strong>{{ eventToDelete?.title }}</strong>?</span>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="deleteDialog = false" text />
                <Button label="Delete" icon="pi pi-trash" @click="deleteEvent" severity="danger" :loading="deleting" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { queueApiRequest } from '@/services/ApiRequestManager';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

// State
const events = ref([]);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const eventDialog = ref(false);
const deleteDialog = ref(false);
const editingEvent = ref(null);
const eventToDelete = ref(null);
const selectedType = ref(null);

// Form
const eventForm = ref({
    title: '',
    description: '',
    start_date: null,
    end_date: null,
    event_type: 'holiday',
    modified_start_time: null,
    modified_end_time: null
});

// Event Types
const eventTypes = [
    { label: '🎄 Holiday', value: 'holiday', icon: 'pi pi-star' },
    { label: '⏰ Half Day', value: 'half_day', icon: 'pi pi-clock' },
    { label: '🏠 Early Dismissal', value: 'early_dismissal', icon: 'pi pi-home' },
    { label: '📋 No Classes', value: 'no_classes', icon: 'pi pi-ban' },
    { label: '🎉 School Event', value: 'school_event', icon: 'pi pi-calendar' },
    { label: '👨‍🏫 Teacher Training', value: 'teacher_training', icon: 'pi pi-users' },
    { label: '📝 Exam Day', value: 'exam_day', icon: 'pi pi-file-edit' }
];

// Computed
const filteredEvents = computed(() => {
    if (!selectedType.value) return events.value;
    return events.value.filter(e => e.event_type === selectedType.value);
});

// Methods
async function loadEvents() {
    loading.value = true;
    try {
        const response = await queueApiRequest(
            () => fetch('/api/calendar/events').then(res => res.json()),
            'normal'
        );
        console.log('📥 Load events response:', response);
        console.log('📊 Events array:', response.events);
        events.value = response.events || [];
        console.log('✅ Events loaded, count:', events.value.length);
    } catch (error) {
        console.error('❌ Error loading events:', error);
        if (error.response?.status !== 429) {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load calendar events', life: 3000 });
        }
    } finally {
        loading.value = false;
    }
}

function openEventDialog() {
    editingEvent.value = null;
    eventForm.value = {
        title: '',
        description: '',
        start_date: null,
        end_date: null,
        event_type: 'holiday',
        modified_start_time: null,
        modified_end_time: null
    };
    eventDialog.value = true;
}

function editEvent(event) {
    editingEvent.value = event;
    eventForm.value = {
        title: event.title,
        description: event.description,
        start_date: new Date(event.start_date),
        end_date: new Date(event.end_date),
        event_type: event.event_type,
        modified_start_time: event.modified_start_time ? new Date(`2000-01-01 ${event.modified_start_time}`) : null,
        modified_end_time: event.modified_end_time ? new Date(`2000-01-01 ${event.modified_end_time}`) : null
    };
    eventDialog.value = true;
}

async function saveEvent() {
    saving.value = true;
    try {
        const payload = {
            ...eventForm.value,
            start_date: formatDateForAPI(eventForm.value.start_date),
            end_date: formatDateForAPI(eventForm.value.end_date),
            modified_start_time: eventForm.value.modified_start_time ? formatTimeForAPI(eventForm.value.modified_start_time) : null,
            modified_end_time: eventForm.value.modified_end_time ? formatTimeForAPI(eventForm.value.modified_end_time) : null
        };

        console.log('📤 Sending payload:', payload);

        let response;
        if (editingEvent.value) {
            response = await queueApiRequest(
                () => fetch(`/api/calendar/events/${editingEvent.value.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                }).then(res => res.json()),
                'normal'
            );
            console.log('✅ Update response:', response);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Event updated and teachers notified!', life: 3000 });
        } else {
            response = await queueApiRequest(
                () => fetch('/api/calendar/events', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                }).then(res => res.json()),
                'normal'
            );
            console.log('✅ Create response:', response);
            toast.add({ severity: 'success', summary: 'Success', detail: 'Event created and teachers notified!', life: 3000 });
        }

        eventDialog.value = false;
        
        // Force reload after a short delay
        await new Promise(resolve => setTimeout(resolve, 500));
        await loadEvents();
    } catch (error) {
        console.error('❌ Error saving event:', error);
        console.error('Error response:', error.response?.data);
        toast.add({ severity: 'error', summary: 'Error', detail: error.response?.data?.message || 'Failed to save event', life: 3000 });
    } finally {
        saving.value = false;
    }
}

function confirmDelete(event) {
    eventToDelete.value = event;
    deleteDialog.value = true;
}

async function deleteEvent() {
    deleting.value = true;
    try {
        await api.delete(`/api/calendar/events/${eventToDelete.value.id}`);
        toast.add({ severity: 'success', summary: 'Success', detail: 'Event deleted successfully', life: 3000 });
        deleteDialog.value = false;
        loadEvents();
    } catch (error) {
        console.error('Error deleting event:', error);
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to delete event', life: 3000 });
    } finally {
        deleting.value = false;
    }
}

function getEventIcon(type) {
    const icons = {
        holiday: '🎄',
        half_day: '⏰',
        early_dismissal: '🏠',
        no_classes: '📋',
        school_event: '🎉',
        teacher_training: '👨‍🏫',
        exam_day: '📝'
    };
    return icons[type] || '📅';
}

function formatEventType(type) {
    return type.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

function getEventSeverity(type) {
    const severities = {
        holiday: 'danger',
        half_day: 'warning',
        early_dismissal: 'info',
        no_classes: 'danger',
        school_event: 'success',
        teacher_training: 'info',
        exam_day: 'warning'
    };
    return severities[type] || 'secondary';
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateForAPI(date) {
    if (!date) return null;
    const d = new Date(date);
    return d.toISOString().split('T')[0];
}

function formatTimeForAPI(time) {
    if (!time) return null;
    const t = new Date(time);
    return t.toTimeString().split(' ')[0].substring(0, 5);
}

onMounted(() => {
    loadEvents();
});
</script>

<style scoped>
.school-calendar-manager {
    max-width: 1400px;
    margin: 0 auto;
}

.field {
    margin-bottom: 1rem;
}

.field label {
    display: block;
    margin-bottom: 0.5rem;
    color: #374151;
}
</style>
