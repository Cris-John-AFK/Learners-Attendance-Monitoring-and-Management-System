<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header with Notifications -->
        <div class="bg-white shadow-sm border-b">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Smart Teacher Dashboard</h1>
                        <p class="text-sm text-gray-600">Welcome back, {{ teacherName }}</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <!-- Notification Bell -->
                        <button @click="showNotifications = !showNotifications" 
                                class="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-full">
                            <i class="pi pi-bell text-lg"></i>
                            <span v-if="unreadNotifications > 0" 
                                  class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                {{ unreadNotifications }}
                            </span>
                        </button>
                        
                        <!-- Notes Toggle -->
                        <button @click="showNotesPanel = !showNotesPanel" 
                                class="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-full">
                            <i class="pi pi-bookmark text-lg"></i>
                            <span v-if="totalNotes > 0" 
                                  class="absolute -top-1 -right-1 bg-blue-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                                {{ totalNotes }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Quick Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="pi pi-users text-blue-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Students</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ studentSummary.total_students || 0 }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="pi pi-exclamation-triangle text-red-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Critical Cases</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ studentSummary.critical_risk || 0 }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                <i class="pi pi-clock text-yellow-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">18+ Absences</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ studentSummary.exceeding_18_limit || 0 }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="pi pi-bookmark text-green-600"></i>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Active Notes</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ totalNotes }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Toggle -->
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-medium text-gray-900">Student Management</h2>
                        <div class="flex space-x-1 bg-gray-100 rounded-lg p-1">
                            <button v-for="view in viewOptions" :key="view.value"
                                    @click="currentView = view.value"
                                    :class="[
                                        'px-3 py-1 text-sm font-medium rounded-md transition-colors',
                                        currentView === view.value 
                                            ? 'bg-white text-gray-900 shadow-sm' 
                                            : 'text-gray-600 hover:text-gray-900'
                                    ]">
                                {{ view.label }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <div class="flex flex-wrap gap-4">
                        <div class="flex-1 min-w-64">
                            <input v-model="searchQuery" 
                                   type="text" 
                                   placeholder="Search students..." 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <select v-model="filterRiskLevel" 
                                class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Risk Levels</option>
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                        <button @click="refreshData" 
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            <i class="pi pi-refresh mr-2"></i>Refresh
                        </button>
                    </div>
                </div>

                <!-- Student List -->
                <div class="p-6">
                    <div v-if="loading" class="text-center py-8">
                        <i class="pi pi-spinner pi-spin text-2xl text-gray-400"></i>
                        <p class="text-gray-500 mt-2">Loading students...</p>
                    </div>

                    <div v-else-if="filteredStudents.length === 0" class="text-center py-8">
                        <i class="pi pi-users text-4xl text-gray-300"></i>
                        <p class="text-gray-500 mt-2">No students found</p>
                    </div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div v-for="student in filteredStudents" :key="student.id" 
                             class="bg-white border rounded-lg p-4 hover:shadow-md transition-shadow">
                            <!-- Student Header -->
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900">
                                        {{ formatStudentName(student) }}
                                    </h3>
                                    <p class="text-sm text-gray-500">{{ student.gradeLevel }} - {{ student.section }}</p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <!-- Risk Level Badge -->
                                    <span :class="[
                                        'px-2 py-1 text-xs font-medium rounded-full',
                                        getRiskColorClass(student.risk_level)
                                    ]">
                                        {{ getRiskIcon(student.risk_level) }} {{ student.risk_level?.toUpperCase() || 'UNKNOWN' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Analytics Summary -->
                            <div class="space-y-2 mb-4">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Attendance:</span>
                                    <span :class="[
                                        'font-medium',
                                        student.attendance_percentage >= 90 ? 'text-green-600' :
                                        student.attendance_percentage >= 80 ? 'text-yellow-600' : 'text-red-600'
                                    ]">
                                        {{ formatAttendancePercentage(student.attendance_percentage) }}
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Total Absences:</span>
                                    <span :class="[
                                        'font-medium',
                                        student.analytics?.total_absences_this_year >= 18 ? 'text-red-600' :
                                        student.analytics?.total_absences_this_year >= 12 ? 'text-yellow-600' : 'text-green-600'
                                    ]">
                                        {{ student.analytics?.total_absences_this_year || 0 }}
                                        <span v-if="student.exceeds_18_limit" class="text-red-500 ml-1">⚠️</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex space-x-2">
                                <button @click="viewStudentDetails(student)" 
                                        class="flex-1 px-3 py-2 text-sm bg-blue-50 text-blue-700 rounded-md hover:bg-blue-100 transition-colors">
                                    <i class="pi pi-eye mr-1"></i>View Details
                                </button>
                                <button @click="addNoteForStudent(student)" 
                                        class="px-3 py-2 text-sm bg-yellow-50 text-yellow-700 rounded-md hover:bg-yellow-100 transition-colors">
                                    <i class="pi pi-bookmark"></i>
                                </button>
                                <button @click="changeStudentStatus(student)" 
                                        class="px-3 py-2 text-sm bg-gray-50 text-gray-700 rounded-md hover:bg-gray-100 transition-colors">
                                    <i class="pi pi-cog"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Notes Panel -->
        <div v-if="showNotesPanel" 
             class="fixed inset-y-0 right-0 w-96 bg-white shadow-xl z-50 transform transition-transform duration-300">
            <StickyNotesPanel @close="showNotesPanel = false" @noteUpdated="loadNotes" />
        </div>

        <!-- Student Details Modal -->
        <Dialog v-model:visible="showStudentModal" 
                :header="`${selectedStudent?.firstName} ${selectedStudent?.lastName} - Analytics`"
                :style="{ width: '80vw', maxWidth: '1000px' }"
                :modal="true">
            <StudentAnalyticsDetail v-if="selectedStudent" 
                                   :student="selectedStudent" 
                                   @close="showStudentModal = false" />
        </Dialog>

        <!-- Status Change Modal -->
        <Dialog v-model:visible="showStatusModal" 
                header="Change Student Status"
                :style="{ width: '500px' }"
                :modal="true">
            <StudentStatusChange v-if="selectedStudent" 
                               :student="selectedStudent" 
                               @statusChanged="handleStatusChange"
                               @close="showStatusModal = false" />
        </Dialog>

        <!-- Add Note Modal -->
        <Dialog v-model:visible="showAddNoteModal" 
                header="Add Note"
                :style="{ width: '600px' }"
                :modal="true">
            <AddNoteForm v-if="selectedStudent" 
                        :student="selectedStudent" 
                        @noteAdded="handleNoteAdded"
                        @close="showAddNoteModal = false" />
        </Dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import Dialog from 'primevue/dialog';
import SmartAnalyticsService from '@/services/SmartAnalyticsService';
import TeacherNotesService from '@/services/TeacherNotesService';
import StickyNotesPanel from '@/components/teacher/StickyNotesPanel.vue';
import StudentAnalyticsDetail from '@/components/teacher/StudentAnalyticsDetail.vue';
import StudentStatusChange from '@/components/teacher/StudentStatusChange.vue';
import AddNoteForm from '@/components/teacher/AddNoteForm.vue';

// Reactive data
const loading = ref(false);
const teacherName = ref('Teacher');
const currentView = ref('all');
const searchQuery = ref('');
const filterRiskLevel = ref('');
const showNotesPanel = ref(false);
const showNotifications = ref(false);
const showStudentModal = ref(false);
const showStatusModal = ref(false);
const showAddNoteModal = ref(false);

// Data
const students = ref([]);
const studentSummary = ref({});
const notes = ref([]);
const selectedStudent = ref(null);
const unreadNotifications = ref(0);

const toast = useToast();

// View options
const viewOptions = [
    { label: 'All Students', value: 'all' },
    { label: 'By Subject', value: 'by_subject' },
    { label: 'By Section', value: 'by_section' }
];

// Computed properties
const totalNotes = computed(() => {
    return notes.value.filter(note => !note.is_archived).length;
});

const filteredStudents = computed(() => {
    let filtered = students.value;

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(student => 
            `${student.firstName} ${student.lastName}`.toLowerCase().includes(query) ||
            student.studentId?.toLowerCase().includes(query)
        );
    }

    // Risk level filter
    if (filterRiskLevel.value) {
        filtered = filtered.filter(student => student.risk_level === filterRiskLevel.value);
    }

    return filtered;
});

// Methods
const loadStudents = async () => {
    try {
        loading.value = true;
        const response = await SmartAnalyticsService.getTeacherStudentAnalytics();
        
        if (response.success) {
            students.value = response.data.students || [];
            studentSummary.value = response.data.summary || {};
        } else {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: response.message || 'Failed to load students',
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error loading students:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load students',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

const loadNotes = async () => {
    try {
        const response = await TeacherNotesService.getNotes();
        if (response.success) {
            notes.value = response.data.all_notes || [];
        }
    } catch (error) {
        console.error('Error loading notes:', error);
    }
};

const refreshData = async () => {
    await Promise.all([loadStudents(), loadNotes()]);
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Data refreshed successfully',
        life: 2000
    });
};

const viewStudentDetails = (student) => {
    selectedStudent.value = student;
    showStudentModal.value = true;
};

const changeStudentStatus = (student) => {
    selectedStudent.value = student;
    showStatusModal.value = true;
};

const addNoteForStudent = (student) => {
    selectedStudent.value = student;
    showAddNoteModal.value = true;
};

const handleStatusChange = async (statusData) => {
    showStatusModal.value = false;
    await refreshData();
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Student status updated successfully',
        life: 3000
    });
};

const handleNoteAdded = async () => {
    showAddNoteModal.value = false;
    await loadNotes();
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Note added successfully',
        life: 3000
    });
};

// Utility methods
const formatStudentName = (student) => {
    if (!student) return 'Unknown Student';
    return `${student.firstName || ''} ${student.lastName || ''}`.trim() || student.name || 'Unknown Student';
};

const formatAttendancePercentage = (percentage) => {
    if (percentage === null || percentage === undefined) return 'N/A';
    return `${parseFloat(percentage).toFixed(1)}%`;
};

const getRiskColorClass = (riskLevel) => {
    const colors = {
        'low': 'text-green-700 bg-green-100',
        'medium': 'text-yellow-700 bg-yellow-100',
        'high': 'text-orange-700 bg-orange-100',
        'critical': 'text-red-700 bg-red-100'
    };
    return colors[riskLevel] || 'text-gray-700 bg-gray-100';
};

const getRiskIcon = (riskLevel) => {
    const icons = {
        'low': '✅',
        'medium': '⚠️',
        'high': '🔶',
        'critical': '🚨'
    };
    return icons[riskLevel] || '❓';
};

// Lifecycle
onMounted(async () => {
    await Promise.all([loadStudents(), loadNotes()]);
});

// Watch for view changes
watch(currentView, async (newView) => {
    // Reload data when view changes
    await loadStudents();
});
</script>

<style scoped>
.transition-transform {
    transition: transform 0.3s ease-in-out;
}
</style>
