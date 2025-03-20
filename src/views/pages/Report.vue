<template>
    <div class="card">
        <div class="font-semibold text-xl mb-4">Attendance Report</div>
        <DataTable v-model:expandedRows="expandedRows" :value="sessions" dataKey="id" tableStyle="min-width: 60rem">
            <template #header>
                <div class="flex flex-wrap justify-end gap-2">
                    <Button text icon="pi pi-plus" label="Expand All" @click="expandAll" />
                    <Button text icon="pi pi-minus" label="Collapse All" @click="collapseAll" />
                    <Button  @click="showSuccess()" label="Submit" severity="success"></Button>
                </div>
            </template>

            <Column expander style="width: 5rem" />
            <Column field="title" header="Session Title"></Column>
            <Column field="date" header="Date"></Column>

            <template #expansion="slotProps">
                <div class="p-4">
                    <h5>Attendance for {{ slotProps.data.title }}</h5>
                    <DataTable :value="slotProps.data.students">
                        <Column field="id" header="ID" sortable></Column>
                        <Column field="name" header="Name" sortable></Column>
                        <Column field="status" header="Status" sortable>
                            <template #body="student">
                                <Tag :value="student.data.status" :severity="getStatusSeverity(student.data.status)" />
                            </template>
                        </Column>
                        <Column field="remarks" header="Remarks" sortable></Column>
                    </DataTable>
                </div>
            </template>
        </DataTable>
    </div>
</template>

<script setup>
import { useToast } from 'primevue/usetoast';
import { ref } from 'vue';


const toast = useToast();
function showSuccess() {
    toast.add({ severity: 'success', summary: 'Success Message', detail: 'Message Detail', life: 3000 });
}
const expandedRows = ref([]);

const sessions = ref([
    {
        id: 'SES001', title: 'Math Class - Section A', date: '2025-03-10',
        students: [
            { id: 'S001', name: 'John Doe', status: 'Present', remarks: '' },
            { id: 'S002', name: 'Jane Smith', status: 'Absent', remarks: '' }
        ]
    },
    {
        id: 'SES002', title: 'Science Class - Section B', date: '2025-03-11',
        students: [
            { id: 'S003', name: 'Mike Johnson', status: 'Late', remarks: '' },
            { id: 'S004', name: 'Emily Davis', status: 'Present', remarks: '' }
        ]
    },
    {
        id: 'SES003', title: 'History Class - Section C', date: '2025-03-12',
        students: [
            { id: 'S003', name: 'Mike Johnson', status: 'Late', remarks: '' },
            { id: 'S004', name: 'Emily Davis', status: 'Present', remarks: '' }
        ]
    }
]);

function expandAll() {
    expandedRows.value = sessions.value.reduce((acc, s) => (acc[s.id] = true) && acc, {});
}

function collapseAll() {
    expandedRows.value = null;
}

function getStatusSeverity(status) {
    switch (status) {
        case 'Present': return 'success';
        case 'Absent': return 'danger';
        case 'Late': return 'warn';
        default: return null;
    }
}
</script>

<style scoped>
:deep(.p-datatable-frozen-tbody) {
    font-weight: bold;
}

:deep(.p-datatable-scrollable .p-frozen-column) {
    font-weight: bold;
}
</style>
