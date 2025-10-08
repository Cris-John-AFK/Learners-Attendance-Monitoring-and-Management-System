<script setup>
import LearnerStatusService from '@/services/LearnerStatusService';
import TeacherAuthService from '@/services/TeacherAuthService';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const toast = useToast();
const loading = ref(false);
const students = ref([]);
const currentView = ref('section');
const searchQuery = ref('');
const isSectionAdviser = ref(false);

// Filter states
const selectedStatus = ref(null);
const selectedSection = ref(null);
const selectedGrade = ref(null);
const availableSections = ref([]);
const availableGrades = ref([]);
const teacherId = ref(null);
const showEditDialog = ref(false);
const showHistoryDialog = ref(false);
const selectedStudent = ref(null);
const statusHistory = ref([]);

const editForm = ref({
    new_status: '',
    reason: '',
    reason_category: '',
    effective_date: new Date(),
    notes: ''
});

const viewOptions = [
    { value: 'section', label: 'My Section', icon: 'pi pi-users' },
    { value: 'subject', label: 'My Subject Students', icon: 'pi pi-book' },
    { value: 'all', label: 'All Students', icon: 'pi pi-globe' }
];

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Dropped Out', value: 'dropped_out' },
    { label: 'Transferred Out', value: 'transferred_out' },
    { label: 'Transferred In', value: 'transferred_in' }
];

const reasonOptions = computed(() => {
    const options = LearnerStatusService.getReasonOptions();
    return options[editForm.value.new_status] || [];
});

const filteredStudents = computed(() => {
    let filtered = students.value;

    // Apply search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter((s) => 
            s.name.toLowerCase().includes(query) || 
            s.student_id.toLowerCase().includes(query)
        );
    }

    // Apply status filter
    if (selectedStatus.value) {
        filtered = filtered.filter((s) => s.enrollment_status === selectedStatus.value);
    }

    // Apply section filter
    if (selectedSection.value) {
        filtered = filtered.filter((s) => s.section === selectedSection.value);
    }

    // Apply grade filter
    if (selectedGrade.value) {
        filtered = filtered.filter((s) => s.grade_level === selectedGrade.value);
    }

    return filtered;
});

const loadStudents = async () => {
    loading.value = true;
    try {
        console.log('Loading students for teacher:', teacherId.value, 'view:', currentView.value);
        const response = await LearnerStatusService.getStudentsForTeacher(teacherId.value, currentView.value);
        console.log('API Response:', response);

        if (response.success) {
            students.value = response.students;
            isSectionAdviser.value = response.is_section_adviser;
            console.log('Students loaded:', students.value.length, 'Section Adviser:', isSectionAdviser.value);
            
            // Extract unique sections and grades for filters
            availableSections.value = [...new Set(students.value.map(s => s.section))].filter(Boolean).sort();
            availableGrades.value = [...new Set(students.value.map(s => s.grade_level))].filter(Boolean).sort();
        } else {
            console.error('API returned success=false:', response);
            toast.add({ severity: 'error', summary: 'Error', detail: response.message || 'Failed to load students', life: 3000 });
        }
    } catch (error) {
        console.error('Error loading students:', error);
        console.error('Error response:', error.response?.data);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.response?.data?.message || 'Failed to load students',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

const editStudentStatus = (student) => {
    selectedStudent.value = student;
    editForm.value = {
        new_status: student.enrollment_status || 'active',
        reason: student.dropout_reason || '',
        reason_category: student.dropout_reason_category || '',
        effective_date: student.status_effective_date ? new Date(student.status_effective_date) : new Date(),
        notes: ''
    };
    showEditDialog.value = true;
};

const saveStatusChange = async () => {
    try {
        const response = await LearnerStatusService.updateStudentStatus(teacherId.value, selectedStudent.value.id, {
            ...editForm.value,
            effective_date: editForm.value.effective_date.toISOString().split('T')[0]
        });
        if (response.success) {
            toast.add({ severity: 'success', summary: 'Success', detail: 'Status updated', life: 3000 });
            showEditDialog.value = false;
            loadStudents();
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update', life: 3000 });
    }
};

const viewStudentHistory = async (student) => {
    selectedStudent.value = student;
    try {
        const response = await LearnerStatusService.getStudentStatusHistory(teacherId.value, student.id);
        if (response.success) {
            statusHistory.value = response.history;
            showHistoryDialog.value = true;
        }
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load history', life: 3000 });
    }
};

const getStatusLabel = (status) => {
    return { active: 'Active', dropped_out: 'Dropped Out', transferred_out: 'Transferred Out', transferred_in: 'Transferred In' }[status] || status;
};

const getStatusSeverity = (status) => {
    return { active: 'success', dropped_out: 'danger', transferred_out: 'warning', transferred_in: 'info' }[status] || 'secondary';
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const getFullReasonDescription = (reasonCode, reasonCategory, status) => {
    if (!reasonCode || !reasonCategory || !status) return reasonCode;
    
    const options = LearnerStatusService.getReasonOptions();
    const statusReasons = options[status] || [];
    
    // Find the category
    const category = statusReasons.find(cat => cat.category === reasonCategory);
    if (!category) return reasonCode;
    
    // Find the specific reason
    const reason = category.reasons.find(r => r.value === reasonCode);
    return reason ? reason.label : reasonCode;
};

onMounted(() => {
    console.log('LearnerStatus component mounted');
    const teacherData = TeacherAuthService.getTeacherData();
    console.log('Teacher data from auth:', teacherData);

    if (teacherData?.teacher) {
        teacherId.value = teacherData.teacher.id;
        console.log('Teacher ID set to:', teacherId.value);
        loadStudents();
    } else {
        console.error('No teacher data found!');
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Could not load teacher information',
            life: 3000
        });
    }
});
</script>

<template>
    <div class="p-4">
        <Toast />

        <div class="bg-white p-4 rounded-lg shadow mb-4">
            <h1 class="text-2xl font-bold mb-2">Learner Status Management</h1>
            <p class="text-gray-600">Manage student enrollment status</p>
        </div>

        <div class="bg-white p-4 rounded-lg shadow mb-4">
            <div class="flex justify-between items-center gap-4 flex-wrap mb-4">
                <div class="flex gap-2">
                    <Button
                        v-for="view in viewOptions"
                        :key="view.value"
                        :label="view.label"
                        :icon="view.icon"
                        :class="currentView === view.value ? 'p-button' : 'p-button-outlined'"
                        @click="
                            currentView = view.value;
                            loadStudents();
                        "
                    />
                </div>
                <InputText v-model="searchQuery" placeholder="Search by name or ID..." class="w-64" />
            </div>
            
            <!-- Filter Row -->
            <div class="flex gap-3 items-center flex-wrap">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-semibold mb-2">Status</label>
                    <Dropdown 
                        v-model="selectedStatus" 
                        :options="statusOptions" 
                        optionLabel="label" 
                        optionValue="value" 
                        placeholder="All Statuses" 
                        class="w-full" 
                        :showClear="true"
                    />
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-semibold mb-2">Grade</label>
                    <Dropdown 
                        v-model="selectedGrade" 
                        :options="availableGrades" 
                        placeholder="All Grades" 
                        class="w-full" 
                        :showClear="true"
                    />
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-semibold mb-2">Section</label>
                    <Dropdown 
                        v-model="selectedSection" 
                        :options="availableSections" 
                        placeholder="All Sections" 
                        class="w-full" 
                        :showClear="true"
                    />
                </div>
                <div class="flex items-end pb-1">
                    <Button 
                        label="Clear Filters" 
                        icon="pi pi-filter-slash" 
                        class="p-button-outlined p-button-secondary" 
                        @click="selectedStatus = null; selectedGrade = null; selectedSection = null; searchQuery = ''"
                    />
                </div>
            </div>
        </div>

        <div v-if="!isSectionAdviser" class="bg-yellow-100 border-l-4 border-yellow-500 p-4 mb-4 rounded">
            <i class="pi pi-exclamation-triangle mr-2"></i>
            You can view but not edit (section adviser only).
        </div>

        <div v-if="loading" class="bg-white p-8 rounded-lg shadow text-center">
            <i class="pi pi-spin pi-spinner text-4xl text-blue-500"></i>
            <p class="mt-4 text-gray-600">Loading...</p>
        </div>

        <div v-else class="bg-white p-4 rounded-lg shadow">
            <DataTable :value="filteredStudents" :paginator="true" :rows="10" stripedRows>
                <Column field="student_id" header="ID" :sortable="true"></Column>
                <Column field="name" header="Name" :sortable="true"></Column>
                <Column field="grade_level" header="Grade" :sortable="true"></Column>
                <Column field="section" header="Section" :sortable="true"></Column>
                <Column field="enrollment_status" header="Status" :sortable="true">
                    <template #body="{ data }">
                        <Tag :value="getStatusLabel(data.enrollment_status)" :severity="getStatusSeverity(data.enrollment_status)" />
                    </template>
                </Column>
                <Column header="Actions">
                    <template #body="{ data }">
                        <Button icon="pi pi-eye" class="p-button-rounded p-button-text" @click="viewStudentHistory(data)" />
                        <Button v-if="isSectionAdviser" icon="pi pi-pencil" class="p-button-rounded p-button-text p-button-success" @click="editStudentStatus(data)" />
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="showEditDialog" :header="`Update - ${selectedStudent?.name}`" :modal="true" style="width: 600px">
            <div class="flex flex-col gap-4">
                <div>
                    <label class="font-semibold">New Status</label>
                    <Dropdown v-model="editForm.new_status" :options="statusOptions" optionLabel="label" optionValue="value" class="w-full mt-2" />
                </div>
                <div v-if="editForm.new_status === 'dropped_out' || editForm.new_status === 'transferred_out'">
                    <label class="font-semibold">Category</label>
                    <Dropdown v-model="editForm.reason_category" :options="reasonOptions" optionLabel="label" optionValue="category" class="w-full mt-2" />
                </div>
                <div v-if="editForm.reason_category">
                    <label class="font-semibold">Reason</label>
                    <Dropdown v-model="editForm.reason" :options="reasonOptions.find((r) => r.category === editForm.reason_category)?.reasons || []" optionLabel="label" optionValue="value" class="w-full mt-2" />
                </div>
                <div>
                    <label class="font-semibold">Effective Date</label>
                    <Calendar v-model="editForm.effective_date" dateFormat="yy-mm-dd" :showIcon="true" class="w-full mt-2" />
                </div>
                <div>
                    <label class="font-semibold">Notes</label>
                    <Textarea v-model="editForm.notes" rows="3" class="w-full mt-2" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" class="p-button-text" @click="showEditDialog = false" />
                <Button label="Save" @click="saveStatusChange" />
            </template>
        </Dialog>

        <Dialog v-model:visible="showHistoryDialog" :header="`History - ${selectedStudent?.name}`" :modal="true" style="width: 700px">
            <div v-if="statusHistory.length === 0" class="text-center p-8 text-gray-500">No history</div>
            <div v-else class="space-y-4">
                <div v-for="record in statusHistory" :key="record.id" class="border-l-4 border-blue-500 pl-4 py-2">
                    <div class="flex justify-between items-center mb-2">
                        <Tag :value="getStatusLabel(record.new_status)" :severity="getStatusSeverity(record.new_status)" />
                        <span class="text-sm text-gray-600">{{ formatDate(record.effective_date) }}</span>
                    </div>
                    <p class="text-sm"><strong>By:</strong> {{ record.changed_by }}</p>
                    <p v-if="record.reason" class="text-sm"><strong>Reason:</strong> {{ getFullReasonDescription(record.reason, record.reason_category, record.previous_status === 'active' ? record.new_status : record.previous_status) }}</p>
                    <small class="text-gray-500">{{ record.changed_at }}</small>
                </div>
            </div>
        </Dialog>
    </div>
</template>
