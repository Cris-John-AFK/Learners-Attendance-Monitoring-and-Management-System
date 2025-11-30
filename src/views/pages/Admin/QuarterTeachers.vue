<template>
    <div class="quarter-teachers-manager p-4">
        <div class="card">
            <!-- Header -->
            <div class="flex justify-between items-center mb-4 p-4 border-b quarter-header">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Teachers with Access to {{ quarterInfo }}</h2>
                    <p class="text-sm text-gray-600 mt-1">Manage teacher permissions for this school quarter</p>
                </div>
                <div class="flex gap-3">
                    <Button icon="pi pi-arrow-left" label="Back" @click="$router.back()" severity="info" />
                    <Button icon="pi pi-user-plus" label="Add Teacher" @click="openAddTeacherDialog()" severity="success" />
                </div>
            </div>

            <!-- Teachers DataTable -->
            <DataTable :value="teachers" :loading="loading" paginator :rows="10" dataKey="id" :rowHover="true" showGridlines responsiveLayout="scroll">
                <Column field="name" header="Teacher Name" sortable>
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-user text-blue-600"></i>
                            <span class="font-semibold">{{ data.name }}</span>
                        </div>
                    </template>
                </Column>

                <Column field="email" header="Email" sortable>
                    <template #body="{ data }">
                        <span class="text-gray-600">{{ data.email }}</span>
                    </template>
                </Column>

                <Column field="grade" header="Grade" sortable>
                    <template #body="{ data }">
                        <Tag :value="data.grade || 'N/A'" :severity="getGradeSeverity(data.grade)" />
                    </template>
                </Column>

                <Column field="section" header="Section" sortable>
                    <template #body="{ data }">
                        <Tag :value="data.section" severity="info" />
                    </template>
                </Column>

                <Column field="access_granted" header="Access Granted" sortable>
                    <template #body="{ data }">
                        {{ formatDate(data.access_granted) }}
                    </template>
                </Column>

                <Column header="Actions" style="width: 150px">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button icon="pi pi-trash" severity="danger" outlined size="small" @click="confirmRemove(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Add Teacher Dialog -->
        <Dialog v-model:visible="addTeacherDialog" header="Add Teacher Access" :modal="true" :style="{ width: '600px' }">
            <div class="flex flex-col gap-4">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="pi pi-info-circle text-blue-600"></i>
                        <span class="font-semibold text-blue-800">Grant Quarter Access</span>
                    </div>
                    <p class="text-sm text-blue-700">Select teachers to grant access to this quarter. They will be able to view and manage attendance for this period.</p>
                </div>

                <!-- Select All Button -->
                <div class="flex justify-between items-center">
                    <label class="font-semibold">Select Teachers *</label>
                    <Button :label="isAllSelected ? 'Deselect All' : 'Select All'" :icon="isAllSelected ? 'pi pi-times' : 'pi pi-check-square'" @click="toggleSelectAll" size="small" :severity="isAllSelected ? 'secondary' : 'info'" outlined />
                </div>

                <!-- Teachers Table with Checkboxes -->
                <DataTable v-model:selection="selectedTeachers" :value="availableTeachers" dataKey="id" :rowHover="true" showGridlines scrollable scrollHeight="400px" class="teacher-selection-table">
                    <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
                    <Column field="name" header="Teacher Name" sortable>
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-user text-blue-600"></i>
                                <span class="font-semibold">{{ data.first_name }} {{ data.last_name }}</span>
                            </div>
                        </template>
                    </Column>
                    <Column field="grade" header="Grade" sortable>
                        <template #body="{ data }">
                            <Tag :value="data.grade || 'N/A'" :severity="getGradeSeverity(data.grade)" />
                        </template>
                    </Column>
                    <Column field="section" header="Section" sortable>
                        <template #body="{ data }">
                            <Tag :value="data.section" :severity="data.section === 'No Homeroom' ? 'warning' : 'info'" />
                        </template>
                    </Column>
                </DataTable>

                <!-- Selected Count -->
                <div v-if="selectedTeachers.length > 0" class="bg-green-50 p-3 rounded-lg border border-green-200">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-check-circle text-green-600"></i>
                        <span class="text-green-800 font-semibold">{{ selectedTeachers.length }} teacher(s) selected</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="addTeacherDialog = false" text />
                <Button label="Grant Access" icon="pi pi-check" @click="addTeacher" :loading="adding" severity="success" />
            </template>
        </Dialog>

        <!-- Remove Confirmation Dialog -->
        <Dialog v-model:visible="removeDialog" header="Confirm Remove" :modal="true" :style="{ width: '450px' }">
            <div class="flex items-center gap-3">
                <i class="pi pi-exclamation-triangle text-red-500" style="font-size: 2rem"></i>
                <span
                    >Are you sure you want to remove <strong>{{ teacherToRemove?.name }}</strong
                    >'s access to this quarter?</span
                >
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="removeDialog = false" text />
                <Button label="Remove" icon="pi pi-trash" @click="removeTeacher" severity="danger" :loading="removing" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const toast = useToast();

// Get quarter info from route params
const quarterId = route.params.quarterId;
const schoolYear = route.params.schoolYear;
const quarter = route.params.quarter;

// Computed quarter info display
const quarterInfo = computed(() => {
    return `${quarter} Quarter - ${schoolYear}`;
});

// State
const teachers = ref([]);
const availableTeachers = ref([]);
const loading = ref(false);
const adding = ref(false);
const removing = ref(false);
const addTeacherDialog = ref(false);
const removeDialog = ref(false);
const selectedTeachers = ref([]); // Changed to array for multiple selection
const teacherToRemove = ref(null);

// Computed property to check if all teachers are selected
const isAllSelected = computed(() => {
    return selectedTeachers.value.length === availableTeachers.value.length && availableTeachers.value.length > 0;
});

// Load teachers with access to this quarter
async function loadTeachers() {
    loading.value = true;
    try {
        const apiUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
        const response = await fetch(`${apiUrl}/api/school-quarters/${quarterId}/teachers`);
        const data = await response.json();

        console.log('🔍 API Response:', data);
        console.log('🔍 Response type:', typeof data);
        console.log('🔍 Is array:', Array.isArray(data));

        teachers.value = data;

        console.log('✅ Loaded teachers with quarter access:', teachers.value.length);
        console.log('👥 Teachers:', teachers.value);
    } catch (error) {
        console.error('Error loading teachers:', error);
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load teachers', life: 3000 });
    } finally {
        loading.value = false;
    }
}

// Load available teachers (not yet granted access)
async function loadAvailableTeachers() {
    try {
        // Fetch all teachers from the system
        const apiUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
        const response = await fetch(`${apiUrl}/api/teachers`);
        const data = await response.json();

        // Format teachers for table display
        // Extract: Name, Grade, Section
        availableTeachers.value = data.map((teacher) => {
            const fullName = `${teacher.first_name} ${teacher.last_name}`;
            const sectionInfo = teacher.primary_assignment?.section?.name || 'No Homeroom';
            const gradeInfo = teacher.primary_assignment?.section?.grade?.name || null;

            return {
                id: teacher.id,
                name: `${fullName} - ${sectionInfo}`,
                first_name: teacher.first_name,
                last_name: teacher.last_name,
                section: sectionInfo,
                grade: gradeInfo
            };
        });

        console.log('✅ Loaded teachers from system:', availableTeachers.value.length);
    } catch (error) {
        console.error('❌ Error loading available teachers:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load teachers from system',
            life: 3000
        });
    }
}

// Toggle select all teachers
function toggleSelectAll() {
    if (isAllSelected.value) {
        // Deselect all
        selectedTeachers.value = [];
    } else {
        // Select all
        selectedTeachers.value = [...availableTeachers.value];
    }
}

function openAddTeacherDialog() {
    selectedTeachers.value = []; // Clear previous selections
    loadAvailableTeachers();
    addTeacherDialog.value = true;
}

async function addTeacher() {
    if (selectedTeachers.value.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'Validation Error',
            detail: 'Please select at least one teacher',
            life: 3000
        });
        return;
    }

    adding.value = true;
    try {
        // Loop through selected teachers and grant access to each
        let successCount = 0;
        let errorCount = 0;

        for (const teacher of selectedTeachers.value) {
            try {
                const apiUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
                const response = await fetch(`${apiUrl}/api/school-quarters/${quarterId}/teachers`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        teacher_id: teacher.id
                    })
                });

                if (response.ok) {
                    successCount++;
                } else {
                    errorCount++;
                    console.error(`Failed to grant access to teacher ${teacher.id}`);
                }
            } catch (err) {
                errorCount++;
                console.error(`Error granting access to teacher ${teacher.id}:`, err);
            }
        }

        if (successCount > 0) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: `Access granted to ${successCount} teacher(s) successfully!`,
                life: 3000
            });
        }

        if (errorCount > 0) {
            toast.add({
                severity: 'warn',
                summary: 'Warning',
                detail: `Failed to grant access to ${errorCount} teacher(s)`,
                life: 3000
            });
        }

        addTeacherDialog.value = false;
        selectedTeachers.value = []; // Reset selection
        await loadTeachers(); // Reload teachers with access
        await loadAvailableTeachers(); // Reload available teachers list
    } catch (error) {
        console.error('Error adding teacher:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to grant teacher access',
            life: 3000
        });
    } finally {
        adding.value = false;
    }
}

function confirmRemove(teacher) {
    teacherToRemove.value = teacher;
    removeDialog.value = true;
}

async function removeTeacher() {
    removing.value = true;
    try {
        const apiUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
        const response = await fetch(`${apiUrl}/api/school-quarters/${quarterId}/teachers/${teacherToRemove.value.id}`, {
            method: 'DELETE'
        });

        if (response.ok) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Teacher access removed successfully',
                life: 3000
            });
        } else {
            throw new Error('Failed to remove teacher access');
        }

        removeDialog.value = false;
        loadTeachers();
    } catch (error) {
        console.error('Error removing teacher:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to remove teacher access',
            life: 3000
        });
    } finally {
        removing.value = false;
    }
}

// Get tag color based on grade level
function getGradeSeverity(grade) {
    if (!grade) return 'secondary';

    const gradeLower = grade.toLowerCase();

    // Kindergarten - purple
    if (gradeLower.includes('kinder')) {
        return 'help';
    }
    // Grade 1-2 - success (green)
    if (gradeLower.includes('grade 1') || gradeLower.includes('grade 2')) {
        return 'success';
    }
    // Grade 3-4 - info (blue)
    if (gradeLower.includes('grade 3') || gradeLower.includes('grade 4')) {
        return 'info';
    }
    // Grade 5-6 - warning (orange)
    if (gradeLower.includes('grade 5') || gradeLower.includes('grade 6')) {
        return 'warning';
    }
    // Default
    return 'secondary';
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

onMounted(() => {
    loadTeachers();
});
</script>

<style scoped>
.quarter-teachers-manager {
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

/* Quarter Header - Blue Background */
.quarter-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border: none !important;
    border-radius: 8px 8px 0 0 !important;
}

.quarter-header h2 {
    color: #ffffff !important;
}

.quarter-header p {
    color: rgba(255, 255, 255, 0.9) !important;
}
</style>
