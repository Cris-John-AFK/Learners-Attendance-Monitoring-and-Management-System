<template>
    <div class="student-qrcodes-page">
        <!-- Modern Header with Animation -->
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">Homeroom Student QR Codes</h1>
                <p class="page-subtitle">QR codes for your homeroom students - Generate and manage QR codes for attendance tracking</p>
                <div class="info-badge">
                    <i class="pi pi-info-circle"></i>
                    <span>Only homeroom students are shown here. You can scan QR codes of all students you teach during attendance.</span>
                </div>
            </div>
        </div>

        <!-- Modern Action Bar -->
        <div class="action-bar">
            <div class="action-left">
                <Button label="Print All QR Code ID" icon="pi pi-print" @click="printQRCodes" class="print-button" />

                <div class="sort-wrapper">
                    <label class="sort-label">Sort by:</label>
                    <Dropdown v-model="sortOrder" :options="sortOptions" optionLabel="label" optionValue="value" class="sort-dropdown" placeholder="Sort by..." />
                </div>
            </div>

            <div class="search-wrapper">
                <span class="search-icon">
                    <i class="pi pi-search"></i>
                </span>
                <InputText v-model="searchQuery" placeholder="Search students by name or ID..." class="search-input" />
                <span v-if="searchQuery" class="clear-search" @click="searchQuery = ''">
                    <i class="pi pi-times"></i>
                </span>
            </div>
        </div>

        <!-- Professional Skeleton Loader -->
        <div v-if="loading" class="space-y-4">
            <!-- Header Skeleton -->
            <div class="flex justify-between items-center mb-6">
                <div class="h-6 bg-gray-200 rounded w-48 animate-pulse"></div>
                <div class="h-10 w-40 bg-gray-200 rounded animate-pulse"></div>
            </div>

            <!-- QR Code Grid Skeleton -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div v-for="i in 8" :key="i" class="bg-white rounded-xl shadow-sm p-6">
                    <!-- QR Code Placeholder -->
                    <div class="w-full aspect-square bg-gray-200 rounded-lg mb-4 animate-pulse"></div>

                    <!-- Student Name -->
                    <div class="h-5 bg-gray-200 rounded w-3/4 mx-auto mb-2 animate-pulse"></div>

                    <!-- Student ID -->
                    <div class="h-4 bg-gray-200 rounded w-1/2 mx-auto mb-3 animate-pulse"></div>

                    <!-- Action Buttons -->
                    <div class="flex gap-2 justify-center">
                        <div class="h-9 w-24 bg-gray-200 rounded animate-pulse"></div>
                        <div class="h-9 w-24 bg-gray-200 rounded animate-pulse"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Code Batch Loading Progress -->
        <div v-if="!loading && batchLoadingProgress > 0 && batchLoadingProgress < 100" class="batch-loading-progress">
            <div class="progress-header">
                <i class="pi pi-qrcode"></i>
                <span>Loading QR Codes... {{ batchLoadingProgress }}%</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" :style="{ width: batchLoadingProgress + '%' }"></div>
            </div>
        </div>

        <!-- Modern QR code grid with animations -->
        <transition-group v-if="!loading" name="card-list" tag="div" class="qrcode-grid">
            <div v-for="(student, index) in filteredStudents" :key="`${student.id}-${refreshKey}`" class="qrcode-item print-page" :style="{ animationDelay: `${index * 0.05}s` }">
                <StudentQRCode
                    :ref="
                        (el) => {
                            if (el) qrCardRefs[index] = el;
                        }
                    "
                    :studentId="student.id"
                    :studentName="student.full_name || student.name || `${student.first_name} ${student.last_name}` || `Student ${student.id}`"
                    :section="student.section_name || 'N/A'"
                    :grade="student.grade_name || student.grade_level || 'N/A'"
                    :preloadedQRData="qrCodes[student.id]"
                    :batchLoadingComplete="batchLoadingComplete"
                    :key="`qr-${student.id}-${refreshKey}`"
                />
            </div>
        </transition-group>

        <!-- Modern No results message -->
        <div v-if="!loading && filteredStudents.length === 0" class="no-results">
            <div class="no-results-icon">
                <i class="pi pi-search"></i>
            </div>
            <h3>No homeroom students found</h3>
            <p v-if="searchQuery">No homeroom students match your search "{{ searchQuery }}"</p>
            <p v-else>You don't have any homeroom students assigned. Only homeroom teachers can generate QR codes.</p>
            <Button v-if="searchQuery" label="Clear Search" icon="pi pi-times" @click="searchQuery = ''" class="p-button-text" />
        </div>
    </div>
</template>

<script setup>
import StudentQRCode from '@/components/StudentQRCode.vue';
import { TeacherAttendanceService } from '@/router/service/TeacherAttendanceService';
// Import teacher authentication service (alternative approach)
import TeacherAuthServiceDefault from '@/services/TeacherAuthService.js';
import Dropdown from 'primevue/dropdown';
import { computed, onMounted, ref } from 'vue';

// State variables
const students = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const downloadingAll = ref(false);
const qrCardRefs = ref([]);
const sortOrder = ref('name_asc'); // Default sort by name ascending
const refreshKey = ref(Date.now()); // Force refresh key
const qrCodes = ref({}); // Cache for QR codes - using object for better reactivity
const qrLoading = ref(new Set()); // Track which QR codes are loading
const batchLoadingProgress = ref(0); // Progress of batch loading (0-100)
const batchLoadingComplete = ref(false); // Flag to indicate batch loading is done

// Sort options
const sortOptions = [
    { label: 'Name (A-Z)', value: 'name_asc' },
    { label: 'Name (Z-A)', value: 'name_desc' },
    { label: 'Student ID (Low-High)', value: 'id_asc' },
    { label: 'Student ID (High-Low)', value: 'id_desc' }
];

// Load students on component mount
onMounted(async () => {
    try {
        loading.value = true;

        // Get the logged-in teacher's ID from authentication
        let teacherId;

        try {
            const teacherData = TeacherAuthServiceDefault.getTeacherData();
            console.log('Raw teacher data from auth service:', teacherData);

            // Check different possible data structures
            let actualTeacherId = null;
            if (teacherData) {
                if (teacherData.id) {
                    actualTeacherId = teacherData.id;
                } else if (teacherData.teacher && teacherData.teacher.id) {
                    actualTeacherId = teacherData.teacher.id;
                } else if (teacherData.user && teacherData.user.id) {
                    actualTeacherId = teacherData.user.id;
                }
            }

            if (!actualTeacherId) {
                console.error('No authenticated teacher ID found in data structure');
                // Fallback to Teacher ID 1 for testing (Maria Santos)
                teacherId = 1;
                console.log('Using fallback teacher ID:', teacherId);
            } else {
                teacherId = actualTeacherId;
                console.log('Using authenticated teacher ID:', teacherId);
            }
        } catch (authError) {
            console.error('TeacherAuthService error:', authError);
            // Fallback to Teacher ID 1 for testing (Maria Santos)
            teacherId = 1;
            console.log('Using fallback teacher ID due to auth error:', teacherId);
        }

        // Load ONLY homeroom students for QR code generation/management
        console.log(`Loading homeroom students for teacher ID: ${teacherId}`);
        const assignmentsResponse = await TeacherAttendanceService.getTeacherAssignments(teacherId);
        console.log('Assignments API response:', assignmentsResponse);

        // Extract assignments from the response object
        const assignments = assignmentsResponse?.assignments || assignmentsResponse || [];
        console.log('Extracted assignments array:', assignments);

        if (assignments && assignments.length > 0) {
            // ONLY get students from HOMEROOM sections (is_primary = true)
            const homeroomStudents = [];

            for (const assignment of assignments) {
                console.log('Processing assignment:', assignment);

                // CRITICAL: Only process homeroom assignments (is_primary = true)
                if (assignment.is_primary === true || assignment.subject_name === 'Homeroom') {
                    console.log('✅ Processing HOMEROOM assignment:', assignment);
                    const studentsResponse = await TeacherAttendanceService.getStudentsForTeacherSubject(teacherId, assignment.section_id, assignment.subject_id);
                    console.log(`Students for HOMEROOM section ${assignment.section_id}:`, studentsResponse);

                    // Extract students from the response object
                    const studentsData = studentsResponse?.students || studentsResponse || [];
                    console.log('Extracted homeroom students array:', studentsData);

                    if (studentsData && studentsData.length > 0) {
                        // Add students with section info (preserve backend data if available)
                        studentsData.forEach((student) => {
                            // Only override if backend didn't provide section info
                            if (!student.section_name && assignment.section?.name) {
                                student.section_name = assignment.section.name;
                            }
                            if (!student.grade_name && assignment.section?.grade_level) {
                                student.grade_name = assignment.section.grade_level;
                            }
                            student.subject_name = assignment.subject_name;
                            student.is_homeroom_student = true; // Mark as homeroom student
                        });
                        homeroomStudents.push(...studentsData);
                    }
                } else {
                    console.log('❌ Skipping NON-HOMEROOM assignment:', assignment);
                }
            }

            // Remove duplicates (though there shouldn't be any for homeroom)
            const uniqueStudents = homeroomStudents.filter((student, index, self) => index === self.findIndex((s) => s.id === student.id));

            students.value = uniqueStudents;
            refreshKey.value = Date.now(); // Force refresh QR components
            console.log(`✅ Loaded ${uniqueStudents.length} ACTIVE homeroom students for teacher ${teacherId}`);

            // DEBUG: Check section and grade data for QR codes
            if (uniqueStudents.length > 0) {
                const firstStudent = uniqueStudents[0];
                console.log('🔍 QR Code Data Check:', {
                    name: `${firstStudent.first_name} ${firstStudent.last_name}`,
                    section_name: firstStudent.section_name,
                    grade_name: firstStudent.grade_name,
                    grade_level: firstStudent.grade_level
                });
            }

            // 🚀 PERFORMANCE: Batch load QR codes for all students
            try {
                await batchLoadQRCodes(uniqueStudents);
            } catch (error) {
                console.error('❌ Batch loading failed:', error);
                // Mark as complete even if failed so components can try individual loading
                batchLoadingComplete.value = true;
            }
        } else {
            console.warn(`No assignments found for teacher ${teacherId}`);
            students.value = [];
        }
    } catch (error) {
        console.error('Error loading teacher students:', error);
        students.value = [];
    } finally {
        loading.value = false;
    }
});

// Computed property for filtered and sorted students
const filteredStudents = computed(() => {
    let filtered = students.value;

    // CRITICAL: Filter out non-active students (only show active students)
    filtered = filtered.filter((student) => {
        const status = student.enrollment_status || 'active';
        return ['active', 'enrolled', 'transferred_in'].includes(status);
    });

    // Apply search filter
    if (searchQuery.value.trim()) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter((student) => {
            const name = (student.full_name || student.name || `${student.first_name} ${student.last_name}` || '').toLowerCase();
            const studentId = (student.student_id || student.id || '').toString().toLowerCase();
            return name.includes(query) || studentId.includes(query);
        });
    }

    // Apply sorting
    filtered.sort((a, b) => {
        const nameA = (a.full_name || a.name || `${a.first_name} ${a.last_name}` || '').toLowerCase();
        const nameB = (b.full_name || b.name || `${b.first_name} ${b.last_name}` || '').toLowerCase();
        const idA = (a.student_id || a.id || '').toString();
        const idB = (b.student_id || b.id || '').toString();
        const sectionA = (a.section_name || '').toLowerCase();
        const sectionB = (b.section_name || '').toLowerCase();

        switch (sortOrder.value) {
            case 'name_asc':
                return nameA.localeCompare(nameB);
            case 'name_desc':
                return nameB.localeCompare(nameA);
            case 'id_asc':
                return idA.localeCompare(idB);
            case 'id_desc':
                return idB.localeCompare(idA);
            case 'section':
                return sectionA.localeCompare(sectionB) || nameA.localeCompare(nameB);
            default:
                return nameA.localeCompare(nameB);
        }
    });

    return filtered;
});

// 🚀 ULTRA-FAST: Single bulk API call for all QR codes
const batchLoadQRCodes = async (studentList) => {
    console.log('🚀 Starting BULK QR code loading for', studentList.length, 'students');

    try {
        batchLoadingProgress.value = 10; // Show initial progress

        // Extract student IDs
        const studentIds = studentList.map((student) => student.id);

        // Single API call for ALL QR codes
        const authToken = TeacherAuthServiceDefault.getToken();
        const apiUrl = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';
        const response = await fetch(`${apiUrl}/api/qr-codes/bulk`, {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${authToken}`,
                'Content-Type': 'application/json',
                Accept: 'application/json'
            },
            body: JSON.stringify({
                student_ids: studentIds
            })
        });

        batchLoadingProgress.value = 50; // Halfway progress

        if (response.ok) {
            const result = await response.json();

            if (result.success) {
                // Store all QR codes at once
                qrCodes.value = { ...result.qr_codes };

                batchLoadingProgress.value = 100;
                console.log(`🎉 BULK loading complete! Loaded ${result.found_count}/${result.requested_count} QR codes in ONE request`);
            } else {
                throw new Error('Bulk API returned error');
            }
        } else {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
    } catch (error) {
        console.error('❌ Bulk QR loading failed:', error);
        batchLoadingProgress.value = 0;
        throw error;
    } finally {
        // Mark batch loading as complete
        batchLoadingComplete.value = true;

        // Reset progress after showing completion
        setTimeout(() => {
            batchLoadingProgress.value = 0;
        }, 1000);
    }
};

// Print all QR codes
const printQRCodes = () => {
    window.print();
};

// Download all QR codes as PNG files
const downloadAllQRCodes = async () => {
    try {
        downloadingAll.value = true;

        for (let i = 0; i < filteredStudents.value.length; i++) {
            const qrCard = qrCardRefs.value[i];
            if (qrCard && qrCard.downloadAsPNG) {
                await qrCard.downloadAsPNG();
                // Wait a bit between downloads to avoid overwhelming the browser
                await new Promise((resolve) => setTimeout(resolve, 500));
            }
        }
    } catch (error) {
        console.error('Error downloading all QR codes:', error);
    } finally {
        downloadingAll.value = false;
    }
};
</script>

<style scoped>
/* Modern Page Layout */
.student-qrcodes-page {
    padding: 2rem;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
}

/* Animated Header */
.page-header {
    margin-bottom: 2rem;
    animation: slideDown 0.6s ease-out;
}

.header-content {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.page-title {
    font-size: 2rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0 0 0.5rem 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-subtitle {
    color: #64748b;
    margin: 0 0 1rem 0;
    font-size: 1rem;
}

.info-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: #e0f2fe;
    border: 1px solid #b3e5fc;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    color: #0277bd;
    font-size: 0.875rem;
}

.info-badge i {
    color: #0288d1;
}

/* Modern Action Bar */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    animation: slideDown 0.6s ease-out 0.1s backwards;
}

.action-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.sort-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sort-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
}

.sort-dropdown {
    min-width: 180px;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.download-all-button {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    border: none !important;
    padding: 0.75rem 1.5rem !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
}

.download-all-button:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4) !important;
}

.print-button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border: none !important;
    padding: 0.75rem 1.5rem !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
}

.print-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4) !important;
}

/* Modern Search */
.search-wrapper {
    position: relative;
    flex: 1;
    max-width: 400px;
}

.search-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
    transition: color 0.3s ease;
}

.search-input {
    width: 100%;
    padding: 0.75rem 3rem 0.75rem 3rem !important;
    border: 2px solid #e2e8f0 !important;
    border-radius: 50px !important;
    font-size: 0.95rem !important;
    transition: all 0.3s ease !important;
    background: #f8fafc !important;
}

.search-input:focus {
    border-color: #667eea !important;
    background: white !important;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.15) !important;
}

.search-input:focus ~ .search-icon {
    color: #667eea;
}

.clear-search {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    cursor: pointer;
    padding: 0.25rem;
    transition: all 0.3s ease;
}

.clear-search:hover {
    color: #ef4444;
    transform: translateY(-50%) scale(1.2);
}

/* Modern Grid with Stagger Animation */
.qrcode-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2rem;
    padding: 1rem 0;
}

.qrcode-item {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    animation: fadeInUp 0.6s ease-out backwards;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}

.qrcode-item:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 12px 35px rgba(102, 126, 234, 0.2);
}

/* No Results */
.no-results {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    animation: fadeIn 0.6s ease-out;
}

.no-results-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    animation: pulse 2s ease-in-out infinite;
}

.no-results-icon i {
    font-size: 2.5rem;
    color: white;
}

.no-results h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2c3e50;
    margin: 0 0 0.5rem 0;
}

.no-results p {
    color: #64748b;
    margin: 0 0 1.5rem 0;
}

/* Batch Loading Progress */
.batch-loading-progress {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
    animation: slideDown 0.3s ease-out;
}

.progress-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    font-weight: 600;
    color: #4f46e5;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #4f46e5 0%, #7c3aed 100%);
    border-radius: 4px;
    transition: width 0.3s ease;
}

/* Animations */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes pulse {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

/* Card List Transitions */
.card-list-enter-active {
    transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.card-list-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.card-list-enter-from {
    opacity: 0;
    transform: translateY(30px) scale(0.9);
}

.card-list-leave-to {
    opacity: 0;
    transform: scale(0.9);
}

.card-list-move {
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Print Styles - One QR code per page */
@media print {
    /* Remove browser default headers and footers */
    @page {
        margin: 0;
        size: auto;
    }

    /* Hide all UI elements */
    .page-header,
    .action-bar,
    .search-wrapper,
    .print-button,
    button,
    nav,
    .layout-topbar,
    .layout-sidebar,
    .layout-menu,
    header,
    [class*='topbar'],
    [class*='sidebar'] {
        display: none !important;
    }

    /* Reset page to white and hide scrollbars */
    html,
    body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        overflow: hidden !important;
    }

    /* Hide all scrollbars */
    *,
    *::before,
    *::after {
        overflow: visible !important;
        scrollbar-width: none !important; /* Firefox */
        -ms-overflow-style: none !important; /* IE and Edge */
    }

    /* Hide scrollbar for Chrome, Safari and Opera */
    *::-webkit-scrollbar {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
    }

    .student-qrcodes-page {
        padding: 0 !important;
        margin: 0 !important;
        background: white !important;
        min-height: auto !important;
    }

    /* One card per page layout */
    .qrcode-grid {
        display: block !important;
        padding: 0 !important;
        gap: 0 !important;
    }

    /* Each QR code takes full page */
    .qrcode-item {
        page-break-after: always !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        display: block !important;
        width: 100% !important;
        height: 100vh !important;
        background: white !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        animation: none !important;
        position: relative !important;
        overflow: hidden !important;
    }

    /* Last item doesn't need break after */
    .qrcode-item:last-child {
        page-break-after: avoid !important;
    }
}
</style>
