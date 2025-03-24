<script setup>
import { ref } from 'vue';

const sections = ref([
    { id: '1', section: 'Section A', startTime: '08:00 AM', endTime: '09:00 AM' },
    { id: '2', section: 'Section B', startTime: '09:15 AM', endTime: '10:15 AM' },
    { id: '3', section: 'Section C', startTime: '10:30 AM', endTime: '11:30 AM' }
]);

const scheduleDialog = ref(false);
const selectedSchedule = ref(null);

function editSchedule(section) {
    selectedSchedule.value = { ...section }; // Clone the section object
    scheduleDialog.value = true;
}

function saveSchedule() {
    if (selectedSchedule.value) {
        const index = sections.value.findIndex(sec => sec.id === selectedSchedule.value.id);
        if (index !== -1) {
            sections.value[index] = { ...selectedSchedule.value }; // Update the section
        }
    }
    scheduleDialog.value = false;
}
</script>

<template>
    <div>
        <div class="card">
            <Toolbar class="mb-6">
                <template #start>
                    <h4 class="m-0">Section Schedules</h4>
                </template>
            </Toolbar>

            <DataTable :value="sections" dataKey="id" :paginator="true" :rows="10">
                <Column field="section" header="Section" sortable></Column>
                <Column field="startTime" header="Start Time" sortable></Column>
                <Column field="endTime" header="End Time" sortable></Column>
                <Column header="Actions">
                    <template #body="slotProps">
                        <Button icon="pi pi-pencil" outlined rounded class="mr-2" @click="editSchedule(slotProps.data)" />
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Edit Schedule Modal -->
        <Dialog v-model:visible="scheduleDialog" :style="{ width: '450px' }" header="Edit Schedule" :modal="true">
            <div class="flex flex-col gap-6">
                <div>
                    <label class="block font-bold mb-3">Start Time</label>
                    <Calendar v-model="selectedSchedule.startTime" showIcon iconDisplay="input" timeOnly />
                </div>
                <div>
                    <label class="block font-bold mb-3">End Time</label>
                    <Calendar v-model="selectedSchedule.endTime" showIcon iconDisplay="input" timeOnly />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" text @click="scheduleDialog = false" />
                <Button label="Save" icon="pi pi-check" @click="saveSchedule" />
            </template>
        </Dialog>
    </div>
</template>
