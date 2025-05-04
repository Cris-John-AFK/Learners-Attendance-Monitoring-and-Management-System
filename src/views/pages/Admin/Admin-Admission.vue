<script setup>
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import TabPanel from 'primevue/tabpanel';
import TabView from 'primevue/tabview';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const toast = useToast();
const search = ref('');
const loading = ref(true);
const activeTab = ref(0); // 0 = Pending, 1 = Admitted
const showStudentDetails = ref(false);
const activeStudentTab = ref(0);

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

// Initialize with empty array, will be loaded from localStorage
const applicants = ref([]);

const selectedApplicant = ref(null);

// Filter applicants by status based on active tab
const filteredApplicants = computed(() => {
    const statusFilter = activeTab.value === 0 ? 'Pending' : 'Admitted';
    let filtered = applicants.value.filter((a) => a.status === statusFilter);

    if (search.value) {
        const searchTerm = search.value.toLowerCase();
        filtered = filtered.filter((a) => {
            return a.name?.toLowerCase().includes(searchTerm) || a.firstName?.toLowerCase().includes(searchTerm) || a.lastName?.toLowerCase().includes(searchTerm);
        });
    }

    return filtered;
});

// Count of pending and admitted applicants
const pendingCount = computed(() => applicants.value.filter((a) => a.status === 'Pending').length);

const admittedCount = computed(() => applicants.value.filter((a) => a.status === 'Admitted').length);

// Load applicants from localStorage on component mount
onMounted(() => {
    loadApplicants();
});

// Load pending applications from localStorage
function loadApplicants() {
    loading.value = true;
    try {
        // Get pending applicants from localStorage
        const pendingApplicants = JSON.parse(localStorage.getItem('pendingApplicants') || '[]');

        // Get admitted applicants from localStorage
        const admittedApplicants = JSON.parse(localStorage.getItem('admittedApplicants') || '[]');

        // Create a map to track unique applicants by email or studentId
        const uniqueApplicantsMap = new Map();

        // Process all applicants and keep only unique ones
        [...pendingApplicants, ...admittedApplicants].forEach((applicant) => {
            const uniqueKey = applicant.email || applicant.studentId || `${applicant.firstName}-${applicant.lastName}`;
            // Only add if not already in the map
            if (!uniqueApplicantsMap.has(uniqueKey)) {
                uniqueApplicantsMap.set(uniqueKey, applicant);
            }
        });

        // Convert map values to array and format for display
        const formattedApplicants = Array.from(uniqueApplicantsMap.values()).map((applicant, index) => {
            return {
                id: index + 1,
                // Use name if available, otherwise construct from first and last name
                name: applicant.name || `${applicant.firstName} ${applicant.lastName}`,
                firstName: applicant.firstName,
                lastName: applicant.lastName,
                email: applicant.email || 'N/A',
                birthdate: applicant.birthdate ? new Date(applicant.birthdate).toLocaleDateString() : 'N/A',
                address: formatAddress(applicant),
                contact: applicant.contact || 'N/A',
                photo: applicant.photo || `https://randomuser.me/api/portraits/${applicant.sex === 'Female' ? 'women' : 'men'}/${index + 1}.jpg`,
                requirements: applicant.requirements || { form138: false, psa: false, goodMoral: false, others: false },
                status: applicant.status || 'Pending',
                studentId: applicant.studentId || '',
                // Store the original data for reference
                originalData: applicant
            };
        });

        applicants.value = formattedApplicants;
    } catch (error) {
        console.error('Error loading applicants:', error);
        toast.add({
            severity: 'error',
            summary: 'Error Loading Data',
            detail: 'Failed to load applicant data.',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
}

// Format address for display
function formatAddress(applicant) {
    if (applicant.currentAddress) {
        const addr = applicant.currentAddress;
        const parts = [addr.houseNo, addr.street, addr.barangay, addr.city, addr.province].filter((part) => part);
        return parts.join(', ') || 'N/A';
    }
    return applicant.address || 'N/A';
}

function selectApplicant(applicant) {
    selectedApplicant.value = applicant;
    showStudentDetails.value = true;
}

const allRequirementsComplete = computed(() => {
    if (!selectedApplicant.value) return false;
    return requirements.every((req) => selectedApplicant.value.requirements[req.key]);
});

function admitApplicant() {
    if (!selectedApplicant.value) return;

    // Check if all requirements are complete
    if (!allRequirementsComplete.value) {
        toast.add({
            severity: 'warn',
            summary: 'Requirements Incomplete',
            detail: 'Please ensure all requirements are complete before admitting the student.',
            life: 3000
        });
        return;
    }

    // Update student status to admitted
    selectedApplicant.value.status = 'Admitted';

    // Generate a student ID
    selectedApplicant.value.studentId = 'STU' + String(Date.now()).slice(-8);

    // Update the data in localStorage
    updateApplicantInStorage(selectedApplicant.value);

    // Get the original data to create an admitted student record
    const originalData = selectedApplicant.value.originalData || selectedApplicant.value;

    // Create a student record for enrollment
    const admittedStudent = {
        ...originalData,
        studentId: selectedApplicant.value.studentId,
        name: selectedApplicant.value.name || `${originalData.firstName} ${originalData.lastName}`,
        status: 'Admitted',
        enrollmentStatus: 'Not Enrolled', // Initial status for enrollment
        admissionDate: new Date().toISOString()
    };

    // Get existing admitted students from localStorage
    const admittedStudents = JSON.parse(localStorage.getItem('admittedApplicants') || '[]');

    // Add the new admitted student
    admittedStudents.push(admittedStudent);

    // Save back to localStorage
    localStorage.setItem('admittedApplicants', JSON.stringify(admittedStudents));

    // Show success message
    toast.add({
        severity: 'success',
        summary: 'Student Admitted',
        detail: `${selectedApplicant.value.name} has been successfully admitted with Student ID: ${selectedApplicant.value.studentId}`,
        life: 3000
    });

    // Reload the applicant list
    loadApplicants();

    // Switch to Admitted tab
    activeTab.value = 1;
}

function updateApplicantInStorage(applicant) {
    // Get current data from localStorage
    const pendingApplicants = JSON.parse(localStorage.getItem('pendingApplicants') || '[]');
    const admittedApplicants = JSON.parse(localStorage.getItem('admittedApplicants') || '[]');

    if (applicant.status === 'Admitted') {
        // Remove from pending if present
        const updatedPending = pendingApplicants.filter((app) => app.firstName !== applicant.firstName || app.lastName !== applicant.lastName);

        // Add to admitted with updated status
        const updatedApplicant = {
            ...applicant.originalData,
            status: 'Admitted',
            studentId: applicant.studentId,
            requirements: applicant.requirements
        };
        admittedApplicants.push(updatedApplicant);

        // Save back to localStorage
        localStorage.setItem('pendingApplicants', JSON.stringify(updatedPending));
        localStorage.setItem('admittedApplicants', JSON.stringify(admittedApplicants));
    } else if (applicant.status === 'Rejected') {
        // Remove from pending
        const updatedPending = pendingApplicants.filter((app) => app.firstName !== applicant.firstName || app.lastName !== applicant.lastName);

        // Save back to localStorage
        localStorage.setItem('pendingApplicants', JSON.stringify(updatedPending));
    } else {
        // Just update requirements
        const updatedPending = pendingApplicants.map((app) => {
            if (app.firstName === applicant.firstName && app.lastName === applicant.lastName) {
                return {
                    ...app,
                    requirements: applicant.requirements
                };
            }
            return app;
        });

        localStorage.setItem('pendingApplicants', JSON.stringify(updatedPending));
    }
}

function markIncomplete() {
    if (!selectedApplicant.value) return;

    toast.add({
        severity: 'info',
        summary: 'Marked Incomplete',
        detail: 'Applicant marked as incomplete requirements.',
        life: 3000
    });

    // Update in local storage
    updateApplicantInStorage(selectedApplicant.value);
}

function rejectApplicant() {
    if (!selectedApplicant.value) return;

    selectedApplicant.value.status = 'Rejected';

    // Update in local storage
    updateApplicantInStorage(selectedApplicant.value);

    toast.add({
        severity: 'error',
        summary: 'Rejected',
        detail: `Student ${selectedApplicant.value.name}'s application has been rejected.`,
        life: 3000
    });
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

        <!-- Main Tab View -->
        <TabView v-model:activeIndex="activeTab" class="mb-4">
            <TabPanel header="Pending Applications">
                <div class="status-indicator">
                    <i class="pi pi-clock text-blue-500 mr-2"></i>
                    <span class="font-semibold">Pending Applications ({{ pendingCount }})</span>
                </div>
            </TabPanel>
            <TabPanel header="Admitted Students">
                <div class="status-indicator">
                    <i class="pi pi-check-circle text-green-500 mr-2"></i>
                    <span class="font-semibold">Admitted Students ({{ admittedCount }})</span>
                </div>
            </TabPanel>
        </TabView>

        <div class="grid">
            <!-- Applicant List Panel -->
            <div class="col-12 md:col-5 lg:col-4">
                <div class="card mb-0">
                    <h5>
                        {{ activeTab === 0 ? 'Pending Applicants' : 'Admitted Students' }}
                        ({{ filteredApplicants.length }})
                    </h5>
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
                                    <span v-if="applicant.studentId" class="ml-2"> <i class="pi pi-id-card mr-1"></i>{{ applicant.studentId }} </span>
                                </div>
                            </div>
                            <i class="pi pi-chevron-right text-color-secondary"></i>
                        </div>
                        <div v-if="filteredApplicants.length === 0" class="p-4 text-center text-color-secondary">
                            {{ activeTab === 0 ? 'No pending applications found' : 'No admitted students found' }}
                        </div>
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
                        <h5 class="mt-0">No Applicant Selected</h5>
                        <p class="text-color-secondary">Select an applicant from the list to view details</p>
                    </div>
                </div>
            </div>
        </div>
        <Toast />

        <!-- Student Details Dialog -->
        <Dialog v-model:visible="showStudentDetails" :modal="true" :style="{ width: '50vw' }" :breakpoints="{ '960px': '75vw', '641px': '90vw' }" class="student-details-dialog p-0" :showHeader="false">
            <div class="card-container p-0">
                <!-- Header with gradient background -->
                <div class="student-header">
                    <div class="flex align-items-center">
                        <Avatar :image="selectedApplicant?.photo" shape="circle" size="xlarge" class="mr-3" />
                        <div>
                            <h3 class="m-0 text-white">{{ selectedApplicant?.name }}</h3>
                            <p class="m-0 text-white-alpha-70"><i class="pi pi-envelope mr-1"></i>{{ selectedApplicant?.email || 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Tab navigation -->
                <div class="tab-navigation">
                    <div class="tab-item" :class="{ 'active-tab': activeStudentTab === 0 }" @click="activeStudentTab = 0">
                        <i class="pi pi-user"></i>
                        <span>Personal Information</span>
                    </div>
                    <div class="tab-item" :class="{ 'active-tab': activeStudentTab === 1 }" @click="activeStudentTab = 1">
                        <i class="pi pi-list"></i>
                        <span>Requirements</span>
                    </div>
                    <div class="tab-item" :class="{ 'active-tab': activeStudentTab === 2 }" @click="activeStudentTab = 2">
                        <i class="pi pi-cog"></i>
                        <span>Actions</span>
                    </div>
                </div>

                <!-- Tab content -->
                <div class="tab-content p-3">
                    <!-- Personal Information Tab -->
                    <div v-if="activeStudentTab === 0">
                        <div class="info-list">
                            <div class="info-item">
                                <div class="info-label">Birthdate</div>
                                <div class="info-value">{{ selectedApplicant?.birthdate }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact Number</div>
                                <div class="info-value">{{ selectedApplicant?.contact }}</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Address</div>
                                <div class="info-value">{{ selectedApplicant?.address }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Requirements Tab -->
                    <div v-if="activeStudentTab === 1">
                        <div class="requirements-list">
                            <div v-for="req in requirements" :key="req.key" class="requirement-item">
                                <div class="requirement-name">{{ req.label }}</div>
                                <div class="requirement-status">
                                    <Checkbox v-model="selectedApplicant.requirements[req.key]" :binary="true" :disabled="selectedApplicant?.status !== 'Pending'" class="mr-2" @change="updateApplicantInStorage(selectedApplicant)" />
                                    <Tag :severity="selectedApplicant?.requirements[req.key] ? 'success' : 'warning'">
                                        {{ selectedApplicant?.requirements[req.key] ? 'Complete' : 'Pending' }}
                                    </Tag>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Tab -->
                    <div v-if="activeStudentTab === 2">
                        <div v-if="selectedApplicant?.status === 'Pending'" class="action-buttons">
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

                        <div v-if="selectedApplicant?.status === 'Admitted'" class="status-card bg-green-50">
                            <div class="flex align-items-center">
                                <i class="pi pi-check-circle text-green-500 text-2xl mr-3"></i>
                                <div>
                                    <h5 class="m-0 text-green-700">Student Admitted</h5>
                                    <p class="m-0">
                                        Student ID: <strong>{{ selectedApplicant?.studentId }}</strong>
                                    </p>
                                    <p class="m-0 mt-2">This student can now proceed to the enrollment process.</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedApplicant?.status === 'Rejected'" class="status-card bg-red-50">
                            <div class="flex align-items-center">
                                <i class="pi pi-times-circle text-red-500 text-2xl mr-3"></i>
                                <div>
                                    <h5 class="m-0 text-red-700">Application Rejected</h5>
                                    <p class="m-0">This application has been rejected and cannot proceed to enrollment.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Close button at the bottom -->
                <div class="p-3 flex justify-content-end">
                    <Button icon="pi pi-times" label="Close" class="p-button-rounded p-button-secondary" @click="showStudentDetails = false" />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
/* Applicant list styling */
.applicant-item {
    cursor: pointer;
    transition: all 0.2s;
    background-color: var(--surface-card);
    box-shadow: var(--card-shadow);
}

.applicant-item:hover {
    background-color: var(--surface-hover);
    transform: translateY(-2px);
}

.selected-applicant {
    background-color: var(--primary-50) !important;
    border-left: 4px solid var(--primary-color);
}

.applicant-list {
    max-height: 500px;
    overflow-y: auto;
}

/* Status indicator styling */
.status-indicator {
    display: flex;
    align-items: center;
    padding: 0.5rem;
    border-radius: 0.5rem;
    background-color: var(--surface-50);
}

/* Smooth tab transitions */
:deep(.p-tabview-panels) {
    padding: 0;
    transition: all 0.3s;
}

:deep(.p-tabview-nav) {
    border-radius: 0.5rem;
    background-color: var(--surface-50);
}

:deep(.p-tabview-nav li.p-highlight .p-tabview-nav-link) {
    background-color: var(--surface-0);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

/* Student Details Dialog Styling */
.student-details-dialog :deep(.p-dialog-content) {
    padding: 0 !important;
    border-radius: 8px;
    overflow: hidden;
}

.card-container {
    background: var(--surface-card);
    border-radius: 8px;
    overflow: hidden;
    position: relative;
}

.close-button {
    position: absolute;
    top: 10px;
    right: 5px;
    z-index: 10;
    color: white;
    margin-right: 0;
    padding: 0.5rem;
}

.close-button-left {
    position: absolute;
    top: 10px;
    left: 5px;
    z-index: 10;
    color: white;
    margin-left: 0;
    padding: 0.5rem;
}

.close-button-right {
    position: absolute;
    top: 15px;
    right: 15px;
    z-index: 10;
    color: black;
    margin-right: 0;
    padding: 0.25rem;
    background: transparent;
}

.student-header {
    padding: 1.5rem;
    background: linear-gradient(135deg, #4a90e2 0%, #5e35b1 100%);
    color: white;
}

.tab-navigation {
    display: flex;
    background-color: var(--surface-50);
    border-bottom: 1px solid var(--surface-200);
}

.tab-item {
    padding: 1rem;
    flex: 1;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.tab-item:hover {
    background-color: var(--surface-100);
}

.active-tab {
    background-color: white;
    border-bottom: 3px solid var(--primary-color);
    font-weight: 600;
    color: var(--primary-color);
}

.tab-content {
    padding: 1.5rem;
    min-height: 300px;
}

.info-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem;
    border-bottom: 1px solid var(--surface-200);
}

.info-label {
    font-weight: 600;
    color: var(--text-color-secondary);
}

.info-value {
    color: var(--text-color);
}

.requirements-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.requirement-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    border-bottom: 1px solid var(--surface-200);
}

.requirement-name {
    font-weight: 600;
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.status-card {
    padding: 1.5rem;
    border-radius: 8px;
    margin-top: 1rem;
}
</style>
