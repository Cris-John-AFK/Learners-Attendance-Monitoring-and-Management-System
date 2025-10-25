<template>
    <div class="school-quarter-manager p-4">
        <div class="card">
            <!-- Header -->
            <div class="flex justify-between items-center mb-4 p-4 border-b quarter-header">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">School Quarter Management</h2>
                    <p class="text-sm text-gray-600 mt-1">Define and manage school quarters for the academic year</p>
                </div>
                <Button icon="pi pi-plus" label="Add Quarter" @click="openQuarterDialog()" severity="success" />
            </div>

            <!-- Quarters DataTable -->
            <DataTable 
                :value="quarters" 
                :loading="loading"
                paginator 
                :rows="10"
                dataKey="id"
                :rowHover="true"
                showGridlines
                responsiveLayout="scroll"
            >
                <Column field="school_year" header="School Year" sortable>
                    <template #body="{ data }">
                        <span class="font-semibold">{{ data.school_year }}</span>
                    </template>
                </Column>

                <Column field="quarter" header="Quarter" sortable>
                    <template #body="{ data }">
                        <Tag :value="data.quarter + ' Quarter'" severity="info" />
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

                <Column field="description" header="Description">
                    <template #body="{ data }">
                        <span class="text-gray-600">{{ data.description || '-' }}</span>
                    </template>
                </Column>

                <Column header="Actions" style="width: 200px">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button icon="pi pi-pencil" severity="info" outlined size="small" @click="editQuarter(data)" />
                            <Button icon="pi pi-eye" severity="success" outlined size="small" @click="viewQuarterTeachers(data)" />
                            <Button icon="pi pi-trash" severity="danger" outlined size="small" @click="confirmDeleteQuarter(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Add/Edit Quarter Dialog -->
        <Dialog v-model:visible="quarterDialog" :header="editingQuarter ? 'Edit School Quarter' : 'Add School Quarter'" :modal="true" :style="{ width: '700px' }">
            <div class="flex flex-col gap-4">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="pi pi-info-circle text-blue-600"></i>
                        <span class="font-semibold text-blue-800">School Quarter Information</span>
                    </div>
                    <p class="text-sm text-blue-700">Define the start and end dates for each quarter of the school year. This will help organize attendance records and reports by quarter.</p>
                </div>

                <!-- School Year -->
                <div class="field">
                    <label for="school_year" class="font-semibold">School Year *</label>
                    <InputText id="school_year" v-model="quarterForm.school_year" placeholder="e.g., 2024-2025" class="w-full" />
                </div>

                <!-- Quarter Selection -->
                <div class="field">
                    <label for="quarter" class="font-semibold">Quarter *</label>
                    <Dropdown 
                        id="quarter"
                        v-model="quarterForm.quarter"
                        :options="quarterOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select quarter"
                        class="w-full"
                    />
                </div>

                <!-- Date Range -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="field">
                        <label for="quarter_start_date" class="font-semibold">Start Date *</label>
                        <Calendar id="quarter_start_date" v-model="quarterForm.start_date" dateFormat="yy-mm-dd" showIcon class="w-full" />
                    </div>
                    <div class="field">
                        <label for="quarter_end_date" class="font-semibold">End Date *</label>
                        <Calendar id="quarter_end_date" v-model="quarterForm.end_date" dateFormat="yy-mm-dd" showIcon class="w-full" />
                    </div>
                </div>

                <!-- Description -->
                <div class="field">
                    <label for="quarter_description" class="font-semibold">Description (Optional)</label>
                    <Textarea id="quarter_description" v-model="quarterForm.description" rows="3" placeholder="Add any notes or description for this quarter" class="w-full" />
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="quarterDialog = false" text />
                <Button label="Save Quarter" icon="pi pi-check" @click="saveQuarter" :loading="saving" severity="info" />
            </template>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:visible="deleteDialog" header="Confirm Delete" :modal="true" :style="{ width: '450px' }">
            <div class="flex items-center gap-3">
                <i class="pi pi-exclamation-triangle text-red-500" style="font-size: 2rem"></i>
                <span>
                    Are you sure you want to delete <strong>{{ quarterToDelete?.quarter }} - {{ quarterToDelete?.school_year }}</strong>?
                    <br><br>
                    This will also remove all teacher access permissions for this quarter.
                </span>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="deleteDialog = false" text />
                <Button label="Delete" icon="pi pi-trash" @click="deleteQuarter" :loading="deleting" severity="danger" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useRouter } from 'vue-router';

const toast = useToast();
const router = useRouter();

// State
const quarters = ref([]);
const loading = ref(false);
const saving = ref(false);
const quarterDialog = ref(false);
const editingQuarter = ref(null);
const deleteDialog = ref(false);
const quarterToDelete = ref(null);
const deleting = ref(false);

// Form
const quarterForm = ref({
    school_year: '',
    quarter: '1st',
    start_date: null,
    end_date: null,
    description: ''
});

// Quarter Options
const quarterOptions = [
    { label: '1st Quarter', value: '1st' },
    { label: '2nd Quarter', value: '2nd' },
    { label: '3rd Quarter', value: '3rd' },
    { label: '4th Quarter', value: '4th' }
];

// Load quarters from backend
async function loadQuarters() {
    loading.value = true;
    try {
        const response = await fetch('http://127.0.0.1:8000/api/school-quarters');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        quarters.value = data;
        
        console.log('✅ Loaded school quarters:', quarters.value.length);
    } catch (error) {
        console.error('❌ Error loading quarters:', error);
        // Initialize with empty array so page still works
        quarters.value = [];
        toast.add({ 
            severity: 'warn', 
            summary: 'Warning', 
            detail: 'Could not load quarters. Backend API may not be available.', 
            life: 5000 
        });
    } finally {
        loading.value = false;
    }
}

function openQuarterDialog() {
    editingQuarter.value = null;
    
    // Get current school year (e.g., 2024-2025)
    const currentDate = new Date();
    const currentYear = currentDate.getFullYear();
    const currentMonth = currentDate.getMonth();
    
    // If we're in August or later, school year is current-next year
    // Otherwise, it's previous-current year
    const schoolYearStart = currentMonth >= 7 ? currentYear : currentYear - 1;
    const schoolYearEnd = schoolYearStart + 1;
    
    quarterForm.value = {
        school_year: `${schoolYearStart}-${schoolYearEnd}`,
        quarter: '1st',
        start_date: null,
        end_date: null,
        description: ''
    };
    quarterDialog.value = true;
}

function editQuarter(quarter) {
    editingQuarter.value = quarter;
    quarterForm.value = {
        school_year: quarter.school_year,
        quarter: quarter.quarter,
        start_date: new Date(quarter.start_date),
        end_date: new Date(quarter.end_date),
        description: quarter.description || ''
    };
    quarterDialog.value = true;
}

async function saveQuarter() {
    // Validate required fields
    if (!quarterForm.value.school_year || !quarterForm.value.quarter || 
        !quarterForm.value.start_date || !quarterForm.value.end_date) {
        toast.add({ 
            severity: 'warn', 
            summary: 'Validation Error', 
            detail: 'Please fill in all required fields', 
            life: 3000 
        });
        return;
    }

    saving.value = true;
    try {
        const quarterData = {
            school_year: quarterForm.value.school_year,
            quarter: quarterForm.value.quarter,
            start_date: formatDateForAPI(quarterForm.value.start_date),
            end_date: formatDateForAPI(quarterForm.value.end_date),
            description: quarterForm.value.description
        };

        console.log('💾 Saving quarter:', quarterData);

        let response;
        if (editingQuarter.value) {
            // Update existing quarter
            response = await fetch(`http://127.0.0.1:8000/api/school-quarters/${editingQuarter.value.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(quarterData)
            });
            
            if (response.ok) {
                toast.add({ 
                    severity: 'success', 
                    summary: 'Success', 
                    detail: 'Quarter updated successfully!', 
                    life: 3000 
                });
            }
        } else {
            // Create new quarter
            response = await fetch('http://127.0.0.1:8000/api/school-quarters', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(quarterData)
            });
            
            if (response.ok) {
                toast.add({ 
                    severity: 'success', 
                    summary: 'Success', 
                    detail: `${quarterForm.value.quarter} Quarter for ${quarterForm.value.school_year} has been created!`, 
                    life: 3000 
                });
            }
        }

        if (!response.ok) {
            throw new Error('Failed to save quarter');
        }

        quarterDialog.value = false;
        loadQuarters();
    } catch (error) {
        console.error('❌ Error saving quarter:', error);
        toast.add({ 
            severity: 'error', 
            summary: 'Error', 
            detail: 'Failed to save school quarter', 
            life: 3000 
        });
    } finally {
        saving.value = false;
    }
}

function viewQuarterTeachers(quarter) {
    // Navigate to a page showing teachers who have access to this quarter
    router.push({
        name: 'quarter-teachers',
        params: {
            quarterId: quarter.id,
            schoolYear: quarter.school_year,
            quarter: quarter.quarter
        }
    });
}

function confirmDeleteQuarter(quarter) {
    quarterToDelete.value = quarter;
    deleteDialog.value = true;
}

async function deleteQuarter() {
    deleting.value = true;
    try {
        const response = await fetch(`http://127.0.0.1:8000/api/school-quarters/${quarterToDelete.value.id}`, {
            method: 'DELETE'
        });

        if (response.ok) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: `${quarterToDelete.value.quarter} Quarter for ${quarterToDelete.value.school_year} has been deleted successfully!`,
                life: 3000
            });
            deleteDialog.value = false;
            loadQuarters();
        } else {
            throw new Error('Failed to delete quarter');
        }
    } catch (error) {
        console.error('❌ Error deleting quarter:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete school quarter',
            life: 3000
        });
    } finally {
        deleting.value = false;
    }
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateForAPI(date) {
    if (!date) return null;
    const d = new Date(date);
    return d.toISOString().split('T')[0];
}

onMounted(() => {
    loadQuarters();
});
</script>

<style scoped>
.school-quarter-manager {
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
