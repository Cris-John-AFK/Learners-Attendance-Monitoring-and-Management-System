<script setup>
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import TabPanel from 'primevue/tabpanel';
import TabView from 'primevue/tabview';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';

const toast = useToast();
const search = ref('');
const loading = ref(true);
const activeTab = ref(0); // 0 = Pending, 1 = Admitted
const showStudentDetails = ref(false);
const activeStudentTab = ref(0);

// Grade and section selection
const selectedGradeLevel = ref(null);
const selectedSection = ref(null);
const showPendingOnly = ref(false);

// Grade level options
const gradeLevelOptions = [
    { name: 'View All', code: 'all' },
    { name: 'Kindergarten', code: 'K' },
    { name: 'Grade 1', code: '1' },
    { name: 'Grade 2', code: '2' },
    { name: 'Grade 3', code: '3' },
    { name: 'Grade 4', code: '4' },
    { name: 'Grade 5', code: '5' },
    { name: 'Grade 6', code: '6' }
];

// Section options based on selected grade
const sectionOptions = computed(() => {
    if (!selectedGradeLevel.value) return [];

    // Different sections based on grade level
    if (selectedGradeLevel.value.code === 'K') {
        return [
            { name: 'Kinder A', code: 'KA' },
            { name: 'Kinder B', code: 'KB' },
            { name: 'Kinder C', code: 'KC' }
        ];
    } else {
        return [
            { name: `${selectedGradeLevel.value.name} - Section A`, code: `${selectedGradeLevel.value.code}A` },
            { name: `${selectedGradeLevel.value.name} - Section B`, code: `${selectedGradeLevel.value.code}B` },
            { name: `${selectedGradeLevel.value.name} - Section C`, code: `${selectedGradeLevel.value.code}C` }
        ];
    }
});

// Reset section when grade level changes
watch(selectedGradeLevel, () => {
    selectedSection.value = null;
});

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

// Helper to format registration date
const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    try {
        return new Date(dateStr).toLocaleString();
    } catch {
        return dateStr;
    }
};

// Initialize with empty array, will be loaded from localStorage
const applicants = ref([]);

const selectedApplicant = ref(null);

// Filter applicants by status based on active tab
const filteredApplicants = computed(() => {
    // First filter by status (Pending/Admitted)
    const statusFilter = activeTab.value === 0 ? 'Pending' : 'Admitted';
    let filtered = applicants.value.filter((a) => a.status === statusFilter);

    // Filter by grade level if selected
    if (selectedGradeLevel.value && selectedGradeLevel.value.code !== 'all') {
        filtered = filtered.filter((a) => {
            // Convert both to strings for comparison to avoid type issues
            const studentGrade = String(a.gradeLevel);
            const selectedGrade = String(selectedGradeLevel.value.code);

            // Compare as strings to ensure proper matching
            return studentGrade === selectedGrade;
        });
    }

    // Filter by section if selected
    if (selectedSection.value) {
        filtered = filtered.filter((a) => {
            // Match by section code
            return a.section === selectedSection.value.code;
        });
    }

    // Filter by pending status if requested
    if (showPendingOnly.value) {
        filtered = filtered.filter((a) => a.status === 'Pending');
    }

    // Text search filter
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

// Load applicants from database on component mount
onMounted(() => {
    loadApplicants();
});

// Load pending applications from localStorage
async function loadApplicants() {
    loading.value = true;
    try {
        // Load from localStorage where Registration Form saves data
        const enrollmentRegistrations = JSON.parse(localStorage.getItem('enrollmentRegistrations') || '[]');
        const pendingApplicants = JSON.parse(localStorage.getItem('pendingApplicants') || '[]');

        // Combine both localStorage sources
        const allApplicants = [...enrollmentRegistrations, ...pendingApplicants];

        // Remove duplicates based on email or name
        const uniqueApplicants = allApplicants.filter((applicant, index, arr) => {
            return arr.findIndex((a) => a.email === applicant.email || (a.firstName === applicant.firstName && a.lastName === applicant.lastName)) === index;
        });

        // Format students for display
        const formattedApplicants = uniqueApplicants.map((student, index) => {
            return {
                id: student.id || index + 1,
                name: student.name || `${student.firstName || ''} ${student.lastName || ''}`.trim(),
                firstName: student.firstName || student.firstname || '',
                lastName: student.lastName || student.lastname || '',
                email: student.email || 'N/A',
                birthdate: student.birthdate ? new Date(student.birthdate).toLocaleDateString() : 'N/A',
                address: student.address || 'N/A',
                contact: student.contact || student.phone || 'N/A',
                photo: student.photo || `https://randomuser.me/api/portraits/${student.gender === 'Female' ? 'women' : 'men'}/${(index % 50) + 1}.jpg`,
                requirements: {
                    form138: student.form138 || false,
                    psa: student.psa || false,
                    goodMoral: student.goodmoral || false,
                    others: student.others || false
                },
                status: student.status || 'Pending',
                studentId: student.studentid || '',
                gradeLevel: student.gradelevel || 'K',
                section: student.section || null,
                originalData: student
            };
        });

        applicants.value = formattedApplicants;
        console.log('Loaded applicants from localStorage:', formattedApplicants);
    } catch (error) {
        console.error('Error loading applicants from localStorage:', error);
        toast.add({
            severity: 'error',
            summary: 'Loading Error',
            detail: 'Failed to load registration data.',
            life: 3000
        });
        applicants.value = [];
    } finally {
        loading.value = false;
    }
}

// Format address for display
function formatAddress(input) {
    // Handle address object from enrollment registration
    if (input && typeof input === 'object' && (input.street || input.city || input.barangay)) {
        const parts = [];
        if (input.houseNo) parts.push(input.houseNo);
        if (input.street) parts.push(input.street);
        if (input.barangay) parts.push(input.barangay);
        if (input.city) parts.push(input.city);
        if (input.province) parts.push(input.province);
        if (input.zipCode) parts.push(input.zipCode);

        return parts.join(', ') || 'N/A';
    }

    // Handle applicant object
    if (!input || !input.address) return 'N/A';
    return input.address;
}

// Format grade level for display
function formatGradeLevel(gradeLevel) {
    if (!gradeLevel) return 'N/A';
    if (gradeLevel === 'K') return 'Kindergarten';
    return 'Grade ' + gradeLevel;
}

function selectApplicant(applicant) {
    selectedApplicant.value = applicant;
    showStudentDetails.value = true;
}

const allRequirementsComplete = computed(() => {
    if (!selectedApplicant.value) return false;
    return requirements.every((req) => selectedApplicant.value.requirements[req.key]);
});

async function admitApplicant() {
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

    try {
        // Generate a student ID if not exists
        const studentId = selectedApplicant.value.studentId || 'STU' + String(Date.now()).slice(-8);

        // Prepare data for database update
        const updateData = {
            status: 'Admitted',
            studentid: studentId,
            admissiondate: new Date().toISOString().split('T')[0],
            enrollmentstatus: 'Not Enrolled',
            form138: selectedApplicant.value.requirements.form138,
            psa: selectedApplicant.value.requirements.psa,
            goodmoral: selectedApplicant.value.requirements.goodMoral,
            others: selectedApplicant.value.requirements.others
        };

        // Update in localStorage
        selectedApplicant.value.status = 'Admitted';
        selectedApplicant.value.studentId = studentId;

        // Update in localStorage sources
        const enrollmentRegistrations = JSON.parse(localStorage.getItem('enrollmentRegistrations') || '[]');
        const pendingApplicants = JSON.parse(localStorage.getItem('pendingApplicants') || '[]');

        // Find and update in enrollmentRegistrations
        const regIndex = enrollmentRegistrations.findIndex((app) => app.email === selectedApplicant.value.email || (app.firstName === selectedApplicant.value.firstName && app.lastName === selectedApplicant.value.lastName));

        if (regIndex !== -1) {
            enrollmentRegistrations[regIndex].status = 'Admitted';
            enrollmentRegistrations[regIndex].studentId = studentId;
            enrollmentRegistrations[regIndex] = { ...enrollmentRegistrations[regIndex], ...updateData };
            localStorage.setItem('enrollmentRegistrations', JSON.stringify(enrollmentRegistrations));
        }

        // Find and update in pendingApplicants
        const pendingIndex = pendingApplicants.findIndex((app) => app.email === selectedApplicant.value.email || (app.firstName === selectedApplicant.value.firstName && app.lastName === selectedApplicant.value.lastName));

        if (pendingIndex !== -1) {
            pendingApplicants[pendingIndex].status = 'Admitted';
            pendingApplicants[pendingIndex].studentId = studentId;
            pendingApplicants[pendingIndex] = { ...pendingApplicants[pendingIndex], ...updateData };
            localStorage.setItem('pendingApplicants', JSON.stringify(pendingApplicants));
        }

        // Update the applicants array
        const applicantIndex = applicants.value.findIndex((app) => app.id === selectedApplicant.value.id);
        if (applicantIndex !== -1) {
            applicants.value[applicantIndex].status = 'Admitted';
            applicants.value[applicantIndex].studentId = studentId;
        }

        // Show success message
        toast.add({
            severity: 'success',
            summary: 'Student Admitted',
            detail: `${selectedApplicant.value.name} has been successfully admitted with Student ID: ${studentId}`,
            life: 3000
        });

        // Reload the applicant list
        await loadApplicants();
    } catch (error) {
        console.error('Error admitting student:', error);
        toast.add({
            severity: 'error',
            summary: 'Database Error',
            detail: 'Failed to admit student. Please try again.',
            life: 3000
        });
    }
}

async function toggleRequirement(requirementKey) {
    if (!selectedApplicant.value) return;

    try {
        // Update the requirement status
        selectedApplicant.value.requirements[requirementKey] = !selectedApplicant.value.requirements[requirementKey];

        // Update in localStorage
        const enrollmentRegistrations = JSON.parse(localStorage.getItem('enrollmentRegistrations') || '[]');
        const pendingApplicants = JSON.parse(localStorage.getItem('pendingApplicants') || '[]');

        // Find and update in enrollmentRegistrations
        const regIndex = enrollmentRegistrations.findIndex((app) => app.email === selectedApplicant.value.email || (app.firstName === selectedApplicant.value.firstName && app.lastName === selectedApplicant.value.lastName));

        if (regIndex !== -1) {
            enrollmentRegistrations[regIndex].requirements = selectedApplicant.value.requirements;
            localStorage.setItem('enrollmentRegistrations', JSON.stringify(enrollmentRegistrations));
        }

        // Find and update in pendingApplicants
        const pendingIndex = pendingApplicants.findIndex((app) => app.email === selectedApplicant.value.email || (app.firstName === selectedApplicant.value.firstName && app.lastName === selectedApplicant.value.lastName));

        if (pendingIndex !== -1) {
            pendingApplicants[pendingIndex].requirements = selectedApplicant.value.requirements;
            localStorage.setItem('pendingApplicants', JSON.stringify(pendingApplicants));
        }

        // Update the applicants array
        const applicantIndex = applicants.value.findIndex((app) => app.id === selectedApplicant.value.id);
        if (applicantIndex !== -1) {
            applicants.value[applicantIndex].requirements = selectedApplicant.value.requirements;
        }

        toast.add({
            severity: 'success',
            summary: 'Updated',
            detail: 'Requirement status updated successfully.',
            life: 2000
        });
    } catch (error) {
        console.error('Error updating requirements:', error);
        // Revert the change if update failed
        selectedApplicant.value.requirements[requirementKey] = !selectedApplicant.value.requirements[requirementKey];
        toast.add({
            severity: 'error',
            summary: 'Update Failed',
            detail: 'Failed to update requirements. Please try again.',
            life: 3000
        });
    }
}

// Remove localStorage function - now using direct database operations

async function markIncomplete() {
    if (!selectedApplicant.value) return;

    try {
        // Update status in localStorage
        selectedApplicant.value.status = 'Incomplete';

        // Update in localStorage sources
        const enrollmentRegistrations = JSON.parse(localStorage.getItem('enrollmentRegistrations') || '[]');
        const pendingApplicants = JSON.parse(localStorage.getItem('pendingApplicants') || '[]');

        // Find and update in enrollmentRegistrations
        const regIndex = enrollmentRegistrations.findIndex((app) => app.email === selectedApplicant.value.email || (app.firstName === selectedApplicant.value.firstName && app.lastName === selectedApplicant.value.lastName));

        if (regIndex !== -1) {
            enrollmentRegistrations[regIndex].status = 'Incomplete';
            localStorage.setItem('enrollmentRegistrations', JSON.stringify(enrollmentRegistrations));
        }

        // Find and update in pendingApplicants
        const pendingIndex = pendingApplicants.findIndex((app) => app.email === selectedApplicant.value.email || (app.firstName === selectedApplicant.value.firstName && app.lastName === selectedApplicant.value.lastName));

        if (pendingIndex !== -1) {
            pendingApplicants[pendingIndex].status = 'Incomplete';
            localStorage.setItem('pendingApplicants', JSON.stringify(pendingApplicants));
        }

        // Update the applicants array
        const applicantIndex = applicants.value.findIndex((app) => app.id === selectedApplicant.value.id);
        if (applicantIndex !== -1) {
            applicants.value[applicantIndex].status = 'Incomplete';
        }

        toast.add({
            severity: 'info',
            summary: 'Marked Incomplete',
            detail: 'Applicant marked as incomplete requirements.',
            life: 3000
        });

        await loadApplicants();
    } catch (error) {
        console.error('Error marking incomplete:', error);
        toast.add({
            severity: 'error',
            summary: 'Update Failed',
            detail: 'Failed to update student status.',
            life: 3000
        });
    }
}

async function rejectApplicant() {
    if (!selectedApplicant.value) return;

    try {
        // Update status in localStorage
        selectedApplicant.value.status = 'Rejected';

        // Update in localStorage sources
        const enrollmentRegistrations = JSON.parse(localStorage.getItem('enrollmentRegistrations') || '[]');
        const pendingApplicants = JSON.parse(localStorage.getItem('pendingApplicants') || '[]');

        // Find and update in enrollmentRegistrations
        const regIndex = enrollmentRegistrations.findIndex((app) => app.email === selectedApplicant.value.email || (app.firstName === selectedApplicant.value.firstName && app.lastName === selectedApplicant.value.lastName));

        if (regIndex !== -1) {
            enrollmentRegistrations[regIndex].status = 'Rejected';
            localStorage.setItem('enrollmentRegistrations', JSON.stringify(enrollmentRegistrations));
        }

        // Find and update in pendingApplicants
        const pendingIndex = pendingApplicants.findIndex((app) => app.email === selectedApplicant.value.email || (app.firstName === selectedApplicant.value.firstName && app.lastName === selectedApplicant.value.lastName));

        if (pendingIndex !== -1) {
            pendingApplicants[pendingIndex].status = 'Rejected';
            localStorage.setItem('pendingApplicants', JSON.stringify(pendingApplicants));
        }

        // Update the applicants array
        const applicantIndex = applicants.value.findIndex((app) => app.id === selectedApplicant.value.id);
        if (applicantIndex !== -1) {
            applicants.value[applicantIndex].status = 'Rejected';
        }

        toast.add({
            severity: 'info',
            summary: 'Application Rejected',
            detail: `${selectedApplicant.value.name}'s application has been rejected.`,
            life: 3000
        });

        await loadApplicants();
    } catch (error) {
        console.error('Error rejecting student:', error);
        toast.add({
            severity: 'error',
            summary: 'Rejection Failed',
            detail: 'Failed to reject student application.',
            life: 3000
        });
    }
}

// Navigate to enrollment page with the selected student
function navigateToEnrollment() {
    if (!selectedApplicant.value) return;

    // Close the current dialog first
    showStudentDetails.value = false;

    // Navigate to enrollment page with correct hash routing
    window.location.href = '#/admin/enrollment';

    // Show toast message
    toast.add({
        severity: 'success',
        summary: 'Redirecting to Enrollment',
        detail: 'Taking you to the enrollment page...',
        life: 3000
    });
}
</script>

<template>
    <div class="card p-6 shadow-lg rounded-lg bg-white">
        <!-- Modern Gradient Header -->
        <div class="modern-header-container mb-6">
            <div class="gradient-header">
                <div class="header-content">
                    <div class="header-left">
                        <div class="header-icon">
                            <i class="pi pi-user-plus"></i>
                        </div>
                        <div class="header-text">
                            <h1 class="header-title">Admission Center</h1>
                            <p class="header-subtitle">Naawan Central School - Student Applications</p>
                            <div class="student-count">
                                <i class="pi pi-chart-bar mr-2"></i>
                                Pending Applications: <span class="count-badge">{{ pendingCount }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <div class="search-container">
                            <span class="p-input-icon-left">
                                <i class="pi pi-search" />
                                <InputText v-model="search" placeholder="Search applicants..." class="search-input" />
                            </span>
                        </div>
                        <div class="filter-container">
                            <Dropdown v-model="selectedGradeLevel" :options="gradeLevelOptions" optionLabel="name" placeholder="Select Grade Level" class="grade-filter" />
                        </div>
                    </div>
                </div>
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
        </TabView>

        <div class="grid">
            <!-- Applicant Table Panel -->
            <div class="col-12">
                <div class="card mb-0">
                    <h5>Pending Applicants ({{ filteredApplicants.length }})</h5>
                    <!-- Modern DataTable list -->
                    <div class="applicant-table p-2 mb-3">
                        <DataTable
                            :value="filteredApplicants"
                            dataKey="id"
                            class="p-datatable-sm"
                            :loading="loading"
                            stripedRows
                            responsiveLayout="scroll"
                            selectionMode="single"
                            :selection="selectedApplicant"
                            @rowSelect="selectApplicant($event.data)"
                            @rowClick="selectApplicant($event.data)"
                        >
                            <Column field="studentId" header="LRN" style="min-width: 140px" />

                            <Column header="Name" style="min-width: 200px">
                                <template #body="slotProps">
                                    <div class="flex align-items-center">
                                        <Avatar :image="slotProps.data.photo" shape="circle" size="large" class="mr-2" />
                                        <span>{{ slotProps.data.name }}</span>
                                    </div>
                                </template>
                            </Column>

                            <Column field="gradeLevel" header="Grade" style="width: 100px" />

                            <Column header="Validity" style="min-width: 140px">
                                <template #body="slotProps">
                                    <span>{{ slotProps.data.validity || 'N/A' }}</span>
                                </template>
                            </Column>

                            <Column header="Status" style="width: 100px">
                                <template #body="slotProps">
                                    <Tag :severity="slotProps.data.status === 'Pending' ? 'info' : slotProps.data.status === 'Admitted' ? 'success' : 'danger'" :value="slotProps.data.status" />
                                </template>
                            </Column>

                            <Column header="Registered" style="min-width: 160px">
                                <template #body="slotProps">
                                    {{ formatDate(slotProps.data.createdAt) }}
                                </template>
                            </Column>

                            <Column header="Actions" style="width: 8rem">
                                <template #body="slotProps">
                                    <Button icon="pi pi-search" class="p-button-rounded p-button-text" @click="selectApplicant(slotProps.data)" />
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                    <!-- Legacy list hidden for reference -->
                    <div class="applicant-list p-2" v-if="false">
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
                    <!-- Total count line -->
                    <div class="p-3 border-top-1 border-300 text-center text-color-secondary">
                        <strong>Total: {{ filteredApplicants.length }} {{ activeTab === 0 ? 'pending' : 'admitted' }} {{ filteredApplicants.length === 1 ? 'student' : 'students' }}</strong>
                    </div>
                </div>
            </div>

            <!-- Applicant Details Panel -->
        </div>
        <Toast />

        <!-- Student Details Dialog -->
        <Dialog v-model:visible="showStudentDetails" :modal="true" :dismissableMask="true" :style="{ width: '50vw' }" :breakpoints="{ '960px': '75vw', '641px': '90vw' }" class="student-details-dialog p-0" :showHeader="false">
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
                                <div class="info-label">Grade Level</div>
                                <div class="info-value">
                                    <span class="p-tag p-tag-info">
                                        {{ formatGradeLevel(selectedApplicant?.gradeLevel) }}
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Section</div>
                                <div class="info-value">
                                    <span class="p-tag p-tag-success" v-if="selectedApplicant?.section">
                                        {{ selectedApplicant?.section }}
                                    </span>
                                    <span class="text-color-secondary" v-else>Not Assigned</span>
                                </div>
                            </div>
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
                            <div v-for="req in requirements" :key="req.key" class="requirement-item clickable-requirement" :class="{ disabled: selectedApplicant?.status !== 'Pending' }" @click="toggleRequirement(req.key)">
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

                                    <div class="mt-3">
                                        <Button label="Enroll Now" icon="pi pi-user-plus" class="p-button-success" @click="navigateToEnrollment" />
                                    </div>
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

.clickable-requirement {
    cursor: pointer;
    transition: background-color 0.2s ease;
    border-radius: 6px;
    border: 1px solid transparent;
}

.clickable-requirement:hover:not(.disabled) {
    background-color: var(--surface-100);
    border-color: var(--primary-color);
}

.clickable-requirement.disabled {
    cursor: not-allowed;
    opacity: 0.6;
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

/* Modern Header Styling - Matching Student Management System */
.modern-header-container {
    margin: -1.5rem -1.5rem 0 -1.5rem;
    border-radius: 12px 12px 0 0;
    overflow: hidden;
}

.gradient-header {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 50%, #15803d 100%);
    position: relative;
    overflow: hidden;
}

.gradient-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    opacity: 0.3;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 2rem;
    position: relative;
    z-index: 1;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.header-icon {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.header-icon i {
    font-size: 1.5rem;
    color: white;
}

.header-text {
    color: white;
}

.header-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    color: white;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.header-subtitle {
    font-size: 1rem;
    margin: 0 0 0.75rem 0;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 400;
}

.student-count {
    display: flex;
    align-items: center;
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.95);
    font-weight: 500;
}

.count-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    margin-left: 0.5rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.header-actions {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: flex-end;
}

.search-container {
    position: relative;
}

.search-input {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    border-radius: 25px;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    width: 300px;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.search-input::placeholder {
    color: #000000;
}

.search-input:focus {
    background: rgba(255, 255, 255, 0.25);
    border-color: rgba(255, 255, 255, 0.5);
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
}

.search-container .p-input-icon-left i {
    color: rgba(255, 255, 255, 0.8);
    left: 1rem;
}

.filter-container {
    width: 300px;
}

.grade-filter {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.grade-filter :deep(.p-dropdown-label) {
    color: white;
}

.grade-filter :deep(.p-dropdown-trigger) {
    color: rgba(255, 255, 255, 0.8);
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }

    .header-actions {
        align-items: center;
        width: 100%;
    }

    .search-input,
    .grade-filter {
        width: 100%;
        max-width: 300px;
    }

    .header-title {
        font-size: 1.5rem;
    }
}
</style>
