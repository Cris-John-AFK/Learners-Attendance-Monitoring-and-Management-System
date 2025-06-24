<template>
    <div class="card p-fluid">
        <div class="flex justify-content-center align-items-center mb-2" v-if="studentName">
            <Avatar :image="studentPhoto" shape="circle" class="mr-3" style="width: 90px; height: 90px" />
            <span class="font-bold text-xl">{{ studentName }}</span>
        </div>
        <div class="text-center mb-4">
            <h2 class="m-0 font-bold">Enrollment Statistics</h2>
            <p class="text-color-secondary mt-1">Overview of attendance by level & section</p>
        </div>

        <DataTable :value="stats" dataKey="id" class="p-datatable-sm" stripedRows responsiveLayout="scroll">
            <Column field="level" header="LEVEL" sortable style="min-width: 120px" />
            <Column field="section" header="Section" sortable style="min-width: 140px" />
            <Column field="advisor" header="Advisor" sortable style="min-width: 160px" />
            <Column field="type" header="Type" sortable style="min-width: 120px" />
            <Column header="Status" style="min-width: 120px">
                <template #body="slotProps">
                    <Tag :value="slotProps.data.status" :severity="slotProps.data.status === 'Open' ? 'success' : 'danger'" />
                </template>
            </Column>
            <Column field="date" header="Date" sortable style="min-width: 140px" />
            <Column field="totalAttendance" header="Total Attendance" sortable style="min-width: 140px" />
            <Column header="Actions" style="width: 7rem">
                <template #body="slotProps">
                    <Button icon="pi pi-eye" class="p-button-rounded p-button-text" @click="openDialog(slotProps.data)" />
                </template>
            </Column>
        </DataTable>

        <Dialog v-model:visible="showDialog" :style="{ width: '600px' }" header="Student Attendance Profile" modal>
            <div class="flex align-items-center mb-3">
                <Avatar :image="studentPhoto" shape="circle" class="mr-3" style="width:60px;height:60px" />
                <div>
                    <h3 class="m-0">{{ studentName }}</h3>
                    <small class="text-color-secondary">{{ selectedStat?.section }} • {{ selectedStat?.level }}</small>
                </div>
            </div>

            <div v-if="selectedStat">
                <!-- Summary tiles -->
                <div class="flex gap-4 mb-4">
                    <!-- Total Absences -->
                    <div class="surface-card p-3 border-round flex-1 text-center">
                        <h6 class="m-0 text-color-secondary">Total Absences</h6>
                        <div class="text-4xl font-bold text-green-500">{{ selectedStat.absences }}</div>
                        <small class="text-color-secondary">Absence level</small>
                    </div>
                    <!-- Attendance Rate -->
                    <div class="surface-card p-3 border-round flex-1">
                        <h6 class="m-0 text-color-secondary">Attendance Rate</h6>
                        <div class="mt-2 mb-2 font-bold">{{ selectedStat.rate }}%</div>
                        <ProgressBar :value="selectedStat.rate" style="height:12px" />
                    </div>
                </div>

                <!-- Calendar -->
                <h5 class="mt-0 mb-2">Attendance Calendar: {{ selectedStat.subject }} ({{ selectedStat.level }})</h5>
                <Calendar :inline="true" :disabled="true" class="w-full mb-4" />

                <!-- Action -->
                <Button label="Contact Parent" icon="pi pi-envelope" class="p-button-success mt-3" />
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" class="p-button-text" @click="showDialog = false" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import ProgressBar from 'primevue/progressbar';
import Calendar from 'primevue/calendar';
import Tag from 'primevue/tag';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Avatar from 'primevue/avatar';
import { useRouter, useRoute } from 'vue-router';

const router = useRouter();
const route = useRoute();

const studentName = computed(() => route.query.name || '');
const studentPhoto = computed(() => route.query.photo || '');

// Placeholder statistics data
const stats = ref([
    {
        id: 1,
        level: 'Kinder',
        section: 'Joy',
        advisor: 'Ms. Cruz',
        type: 'Face-to-Face',
        status: 'Open',
        date: '2025-06-01',
        totalAttendance: 114
    },
    {
        id: 2,
        level: 'Grade 1',
        section: 'Faith',
        advisor: 'Mr. Santos',
        type: 'Blended',
        status: 'Closed',
        date: '2025-06-01',
        totalAttendance: 91
    }
]);

const selectedStat = ref(null);
const showDialog = ref(false);

const openDialog = (row) => {
    selectedStat.value = {
        ...row,
        absences: row.absences ?? 1,
        rate: row.rate ?? 95,
        subject: row.subject ?? 'Mathematics'
    };
    showDialog.value = true;
};
</script>

<style scoped>
.card {
    box-shadow: var(--card-shadow);
}
.dialog-header {
    border-bottom: 1px solid var(--surface-border);
    width: 100%;
    padding-bottom: 0.75rem;
}
</style>
