<script setup>
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import TabPanel from 'primevue/tabpanel';
import TabView from 'primevue/tabview';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, ref } from 'vue';

const toast = useToast();
const search = ref('');

const requirements = [
    { key: 'form138', label: 'Form 138' },
    { key: 'psa', label: 'PSA Birth Certificate' },
    { key: 'goodMoral', label: 'Certificate of Good Moral' },
    { key: 'others', label: 'Other Documents' }
];

// Computed property to create a list for DataTable
const requirementsList = computed(() => {
    return requirements.map((req) => ({
        key: req.key,
        label: req.label,
        status: selectedApplicant.value?.requirements[req.key] ? 'Complete' : 'Pending'
    }));
});

const applicants = ref([
    {
        id: 1,
        name: 'Juan Dela Cruz',
        email: 'juan@email.com',
        birthdate: '2007-05-20',
        address: 'Brgy. Example, City',
        contact: '09123456789',
        photo: 'https://randomuser.me/api/portraits/men/1.jpg',
        requirements: { form138: true, psa: true, goodMoral: true, others: false },
        status: 'Pending',
        studentId: ''
    },
    {
        id: 2,
        name: 'Maria Santos',
        email: 'maria@email.com',
        birthdate: '2008-08-15',
        address: 'Purok 2, Another City',
        contact: '09987654321',
        photo: 'https://randomuser.me/api/portraits/women/2.jpg',
        requirements: { form138: false, psa: true, goodMoral: false, others: false },
        status: 'Pending',
        studentId: ''
    },
    {
        id: 3,
        name: 'Pedro Reyes',
        email: 'pedro@email.com',
        birthdate: '2006-11-03',
        address: 'Sitio Mabuhay, Barangay Matahimik',
        contact: '09765432109',
        photo: 'https://randomuser.me/api/portraits/men/3.jpg',
        requirements: { form138: true, psa: true, goodMoral: true, others: true },
        status: 'Pending',
        studentId: ''
    }
]);

const selectedApplicant = ref(null);

const filteredApplicants = computed(() => {
    if (!search.value) return applicants.value;
    return applicants.value.filter((a) => a.name.toLowerCase().includes(search.value.toLowerCase()));
});

function selectApplicant(applicant) {
    selectedApplicant.value = applicant;
}

const allRequirementsComplete = computed(() => {
    if (!selectedApplicant.value) return false;
    return requirements.every((req) => selectedApplicant.value.requirements[req.key]);
});

function admitApplicant() {
    if (!allRequirementsComplete.value) {
        toast.add({ severity: 'warn', summary: 'Incomplete Requirements', detail: 'Please complete all requirements before admitting.', life: 3000 });
        return;
    }
    selectedApplicant.value.status = 'Admitted';
    selectedApplicant.value.studentId = 'STU' + String(selectedApplicant.value.id).padStart(5, '0');
    toast.add({ severity: 'success', summary: 'Admitted', detail: `Student ${selectedApplicant.value.name} has been admitted.`, life: 3000 });
}

function markIncomplete() {
    toast.add({ severity: 'info', summary: 'Marked Incomplete', detail: 'Applicant marked as incomplete requirements.', life: 3000 });
}

function rejectApplicant() {
    selectedApplicant.value.status = 'Rejected';
    toast.add({ severity: 'error', summary: 'Rejected', detail: `Student ${selectedApplicant.value.name}'s application has been rejected.`, life: 3000 });
}
</script>

<template>
    <div class="card p-fluid">
        <div class="flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="text-2xl font-bold m-0"><i class="pi pi-user-plus mr-2"></i>Admission Center</h2>
                <p class="text-color-secondary mt-1 mb-0">Review and process student admission applications</p>
            </div>
            <div>
                <span class="p-input-icon-left">
                    <i class="pi pi-search" />
                    <InputText v-model="search" placeholder="Search applicants..." class="p-inputtext-sm" />
                </span>
            </div>
        </div>

        <div class="grid">
            <!-- Applicant List Panel -->
            <div class="col-12 md:col-5 lg:col-4">
                <div class="card mb-0">
                    <h5>Applicants ({{ filteredApplicants.length }})</h5>
                    <div class="applicant-list p-2">
                        <div
                            v-for="applicant in filteredApplicants"
                            :key="applicant.id"
                            @click="selectApplicant(applicant)"
                            class="applicant-item p-3 border-round mb-2 flex align-items-center"
                            :class="{ 'selected-applicant': selectedApplicant && selectedApplicant.id === applicant.id }"
                        >
                            <Avatar :image="applicant.photo" shape="circle" size="large" class="mr-3" />
                            <div class="flex-1">
                                <h6 class="m-0 mb-1">{{ applicant.name }}</h6>
                                <div class="text-sm text-color-secondary flex align-items-center">
                                    <i class="pi pi-tag mr-1"></i>
                                    <Tag :severity="applicant.status === 'Pending' ? 'info' : applicant.status === 'Admitted' ? 'success' : 'danger'" :value="applicant.status" />
                                </div>
                            </div>
                            <i class="pi pi-chevron-right text-color-secondary"></i>
                        </div>
                        <div v-if="filteredApplicants.length === 0" class="p-4 text-center text-color-secondary">No applicants found</div>
                    </div>
                </div>
            </div>

            <!-- Applicant Details Panel -->
            <div class="col-12 md:col-7 lg:col-8">
                <div v-if="selectedApplicant" class="card mb-0">
                    <div class="flex align-items-center mb-4">
                        <Avatar :image="selectedApplicant.photo" shape="circle" size="xlarge" class="mr-3" />
                        <div>
                            <h4 class="m-0">{{ selectedApplicant.name }}</h4>
                            <p class="text-color-secondary m-0"><i class="pi pi-envelope mr-1"></i>{{ selectedApplicant.email }}</p>
                        </div>
                    </div>

                    <TabView>
                        <!-- Personal Information Tab -->
                        <TabPanel header="Personal Information">
                            <div class="grid">
                                <div class="col-12 md:col-6">
                                    <div class="field">
                                        <label class="font-bold">Birthdate</label>
                                        <div>{{ selectedApplicant.birthdate }}</div>
                                    </div>
                                </div>
                                <div class="col-12 md:col-6">
                                    <div class="field">
                                        <label class="font-bold">Contact Number</label>
                                        <div>{{ selectedApplicant.contact }}</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="field">
                                        <label class="font-bold">Address</label>
                                        <div>{{ selectedApplicant.address }}</div>
                                    </div>
                                </div>
                            </div>
                        </TabPanel>

                        <!-- Requirements Tab -->
                        <TabPanel header="Requirements" class="p-0">
                            <DataTable :value="requirementsList" responsiveLayout="scroll" class="p-datatable-sm">
                                <Column field="label" header="Document"></Column>
                                <Column field="status" header="Status">
                                    <template #body="slotProps">
                                        <div class="flex align-items-center">
                                            <Checkbox v-model="selectedApplicant.requirements[slotProps.data.key]" :binary="true" :disabled="selectedApplicant.status !== 'Pending'" class="mr-2" />
                                            <Tag :severity="selectedApplicant.requirements[slotProps.data.key] ? 'success' : 'warning'" :value="selectedApplicant.requirements[slotProps.data.key] ? 'Complete' : 'Pending'" />
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </TabPanel>

                        <!-- Actions Tab -->
                        <TabPanel header="Actions">
                            <div v-if="selectedApplicant.status === 'Pending'" class="card">
                                <h5>Admission Decision</h5>
                                <div class="flex flex-column md:flex-row gap-3">
                                    <Button label="Admit Student" icon="pi pi-check" class="p-button-success" :disabled="!allRequirementsComplete" @click="admitApplicant" />
                                    <Button label="Mark as Incomplete" icon="pi pi-exclamation-triangle" class="p-button-warning" @click="markIncomplete" />
                                    <Button label="Reject Application" icon="pi pi-times" class="p-button-danger" @click="rejectApplicant" />
                                </div>
                                <div v-if="!allRequirementsComplete" class="mt-3 p-message p-message-warning">
                                    <i class="pi pi-exclamation-triangle"></i>
                                    <span class="ml-2">All requirements must be complete before admission</span>
                                </div>
                            </div>

                            <div v-if="selectedApplicant.status === 'Admitted'" class="card bg-green-50">
                                <div class="flex align-items-center">
                                    <i class="pi pi-check-circle text-green-500 text-2xl mr-3"></i>
                                    <div>
                                        <h5 class="m-0 text-green-700">Student Admitted</h5>
                                        <p class="m-0">
                                            Student ID: <strong>{{ selectedApplicant.studentId }}</strong>
                                        </p>
                                        <p class="m-0 mt-2">This student can now proceed to the enrollment process.</p>
                                    </div>
                                </div>
                            </div>

                            <div v-if="selectedApplicant.status === 'Rejected'" class="card bg-red-50">
                                <div class="flex align-items-center">
                                    <i class="pi pi-times-circle text-red-500 text-2xl mr-3"></i>
                                    <div>
                                        <h5 class="m-0 text-red-700">Application Rejected</h5>
                                        <p class="m-0">This application has been rejected and cannot proceed to enrollment.</p>
                                    </div>
                                </div>
                            </div>
                        </TabPanel>
                    </TabView>
                </div>

                <div v-else class="card flex align-items-center justify-content-center" style="min-height: 400px">
                    <div class="text-center">
                        <i class="pi pi-user text-4xl text-color-secondary mb-3"></i>
                        <h5>Select an applicant to view details</h5>
                    </div>
                </div>
            </div>
        </div>
        <Toast />
    </div>
</template>

<style scoped>
/* Applicant list styling */
.applicant-item {
    cursor: pointer;
    transition: all 0.2s;
    background-color: var(--surface-card);
    border: 1px solid var(--surface-border);
}

.applicant-item:hover {
    background-color: var(--surface-hover);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
}

.selected-applicant {
    border-left: 4px solid var(--primary-color);
    background-color: var(--primary-50);
}

/* Tab panel styling */
:deep(.p-tabview-nav) {
    border-bottom: 2px solid var(--surface-border);
}

:deep(.p-tabview-nav li.p-highlight .p-tabview-nav-link) {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

:deep(.p-tabview-panels) {
    padding: 1.5rem 0;
}

/* DataTable styling */
:deep(.p-datatable-sm .p-datatable-thead > tr > th) {
    padding: 0.75rem 1rem;
    background-color: var(--surface-ground);
    font-weight: 600;
}

:deep(.p-datatable-sm .p-datatable-tbody > tr > td) {
    padding: 0.75rem 1rem;
}

:deep(.p-datatable-sm .p-datatable-tbody > tr:nth-child(even)) {
    background-color: var(--surface-hover);
}

/* Card styling */
:deep(.card) {
    background: var(--surface-card);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow:
        0 2px 1px -1px rgba(0, 0, 0, 0.1),
        0 1px 1px 0 rgba(0, 0, 0, 0.07),
        0 1px 3px 0 rgba(0, 0, 0, 0.06);
}

/* Button styling */
:deep(.p-button) {
    border-radius: 6px;
}

/* Status colors */
.bg-green-50 {
    background-color: #f0fdf4;
}

.bg-red-50 {
    background-color: #fef2f2;
}

.text-green-500 {
    color: #22c55e;
}

.text-red-500 {
    color: #ef4444;
}

.text-green-700 {
    color: #15803d;
}

.text-red-700 {
    color: #b91c1c;
}

/* Responsive adjustments */
@media screen and (max-width: 768px) {
    .grid > .col-12 {
        padding: 0.5rem;
    }

    :deep(.p-tabview-panels) {
        padding: 1rem 0;
    }
}
</style>
