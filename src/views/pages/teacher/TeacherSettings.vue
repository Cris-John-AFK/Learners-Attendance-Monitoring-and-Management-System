<script setup>
import { GradeService } from '@/router/service/Grades';
import { useToast } from 'primevue/usetoast';
import { onMounted, ref } from 'vue';

const sections = ref([]);
const scheduleDialog = ref(false);
const selectedSchedule = ref(null);
const toast = useToast();

// Load sections with schedules
const loadSections = async () => {
    try {
        // Get all grades
        const grades = await GradeService.getGrades();

        // Create a flattened array of all sections with grade info
        const allSections = [];

        for (const grade of grades) {
            for (const section of grade.sections) {
                allSections.push({
                    id: `${grade.id}-${section}`,
                    section: `${grade.name} - Section ${section}`,
                    grade: grade.name,
                    startTime: '08:00 AM', // Default values
                    endTime: '09:00 AM' // Default values
                });
            }
        }

        sections.value = allSections;
    } catch (error) {
        console.error('Error loading sections:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load sections. Please try again.',
            life: 3000
        });
    }
};

function editSchedule(section) {
    selectedSchedule.value = { ...section }; // Clone the section object
    scheduleDialog.value = true;
}

async function saveSchedule() {
    if (selectedSchedule.value) {
        try {
            // Parse the section ID to get grade and section info
            const [gradeId, sectionName] = selectedSchedule.value.id.split('-');

            // Prepare schedule data
            const schedule = {
                startTime: selectedSchedule.value.startTime,
                endTime: selectedSchedule.value.endTime
            };

            // Update the schedule using the GradeService
            await GradeService.updateSectionSchedule(gradeId, sectionName, schedule);

            // Update local state
            const index = sections.value.findIndex((sec) => sec.id === selectedSchedule.value.id);
            if (index !== -1) {
                sections.value[index] = { ...selectedSchedule.value };
            }

            toast.add({
                severity: 'success',
                summary: 'Schedule Updated',
                detail: `Updated schedule for ${selectedSchedule.value.section}`,
                life: 3000
            });
        } catch (error) {
            console.error('Error saving schedule:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to save schedule. Please try again.',
                life: 3000
            });
        }
    }
    scheduleDialog.value = false;
}

onMounted(() => {
    loadSections();
});
</script>

<template>
    <div>
        <div class="card">
            <Toolbar class="mb-6">
                <template #start>
                    <h4 class="m-0 text-lg font-semibold">Section Schedules</h4>
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
                    <label class="block font-semibold mb-3 text-sm">Start Time</label>
                    <Calendar v-model="selectedSchedule.startTime" showIcon iconDisplay="input" timeOnly />
                </div>
                <div>
                    <label class="block font-semibold mb-3 text-sm">End Time</label>
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
