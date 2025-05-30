<template>
    <div class="card">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-xl">Students with High Absences</h3>
            <div class="flex gap-2">
                <Button label="Schedule Interventions" icon="pi pi-calendar-plus" size="small" class="p-button-primary" />
            </div>
        </div>

        <DataTable :value="students" :paginator="true" :rows="5"
                  responsiveLayout="scroll" stripedRows class="p-datatable-sm">
            <Column field="name" header="Student Name"></Column>
            <Column field="section" header="Section"></Column>
            <Column field="absences" header="Absences" :sortable="true">
                <template #body="slotProps">
                    <span :class="getAbsenceClass(slotProps.data.absences)">
                        {{ slotProps.data.absences }}
                    </span>
                </template>
            </Column>
            <Column field="lastAbsent" header="Last Absent"></Column>
            <Column field="contactStatus" header="Contact Status">
                <template #body="slotProps">
                    <Tag :severity="getTagSeverity(slotProps.data.contactStatus)"
                         :value="slotProps.data.contactStatus" />
                </template>
            </Column>
            <Column header="Actions">
                <template #body="slotProps">
                    <div>
                        <Button icon="pi pi-user" class="p-button-text p-button-rounded" />
                        <Button icon="pi pi-pencil" class="p-button-text p-button-rounded"
                                @click="openEditDialog(slotProps.data)" />
                    </div>
                </template>
            </Column>
        </DataTable>

        <!-- Edit Contact Status Dialog -->
        <Dialog v-model:visible="editDialogVisible" header="Edit Contact Status" :modal="true" :closable="false">
            <div class="p-fluid">
                <label for="contactStatus" class="font-semibold">Contact Status</label>
                <Dropdown v-model="selectedStudent.contactStatus" :options="statusOptions"
                          placeholder="Select Status" class="w-full" />
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="editDialogVisible = false" />
                <Button label="Save" icon="pi pi-check" class="p-button-primary" @click="saveChanges" />
            </template>
        </Dialog>
    </div>
</template>

<script>
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import Tag from 'primevue/tag';

export default {
    components: {
        Button,
        DataTable,
        Column,
        Tag,
        Dialog,
        Dropdown
    },
    data() {
        return {
            students: [
                { name: "Cris John Canales", section: "Section A", absences: 20, lastAbsent: "Today", contactStatus: "Not Contacted" },
                { name: "Jane Doe", section: "Section B", absences: 15, lastAbsent: "Yesterday", contactStatus: "Contacted" },
                { name: "John Smith", section: "Section A", absences: 10, lastAbsent: "Mar 19, 2025", contactStatus: "Resolved" },
                { name: "Emily Johnson", section: "Section C", absences: 8, lastAbsent: "Mar 18, 2025", contactStatus: "Pending" },
                { name: "Michael Brown", section: "Section B", absences: 7, lastAbsent: "Mar 17, 2025", contactStatus: "Contacted" },
                { name: "Sarah Davis", section: "Section A", absences: 6, lastAbsent: "Mar 15, 2025", contactStatus: "Resolved" },
                { name: "David Wilson", section: "Section C", absences: 5, lastAbsent: "Mar 14, 2025", contactStatus: "Not Contacted" }
            ],
            editDialogVisible: false,
            selectedStudent: null,
            statusOptions: ["Not Contacted", "Contacted", "Pending", "Resolved"]
        };
    },
    methods: {
        getTagSeverity(status) {
            switch (status) {
                case 'Not Contacted': return 'danger';
                case 'Contacted': return 'warning';
                case 'Pending': return 'info';
                case 'Resolved': return 'success';
                default: return 'info';
            }
        },
        getAbsenceClass(absences) {
            if (absences >= 15) return 'text-red-600 font-bold';
            if (absences >= 10) return 'text-orange-500 font-bold';
            if (absences >= 5) return 'text-yellow-600';
            return '';
        },
        openEditDialog(student) {
            this.selectedStudent = { ...student };  // Clone the object
            this.editDialogVisible = true;
        },
        saveChanges() {
            // Find the student and update the contact status
            const studentIndex = this.students.findIndex(s => s.name === this.selectedStudent.name);
            if (studentIndex !== -1) {
                this.students[studentIndex].contactStatus = this.selectedStudent.contactStatus;
            }
            this.editDialogVisible = false;
        }
    }
};
</script>
