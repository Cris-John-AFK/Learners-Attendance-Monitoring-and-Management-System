<template>
    <div class="student-qrcodes-page">
        <!-- Modern Header with Animation -->
        <div class="page-header">
            <div class="header-content">
                <h1 class="page-title">Student QR Codes</h1>
                <p class="page-subtitle">QR codes for your assigned students - Use these for attendance tracking</p>
            </div>
        </div>

        <!-- Modern Action Bar -->
        <div class="action-bar">
            <Button 
                label="Print All QR Codes" 
                icon="pi pi-print" 
                @click="printQRCodes"
                class="print-button"
            />
            
            <div class="search-wrapper">
                <span class="search-icon">
                    <i class="pi pi-search"></i>
                </span>
                <InputText 
                    v-model="searchQuery" 
                    placeholder="Search students by name or ID..." 
                    class="search-input"
                />
                <span v-if="searchQuery" class="clear-search" @click="searchQuery = ''">
                    <i class="pi pi-times"></i>
                </span>
            </div>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="flex justify-center items-center py-8">
            <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" />
            <span class="ml-3">Loading student data...</span>
        </div>

        <!-- Modern QR code grid with animations -->
        <transition-group v-else name="card-list" tag="div" class="qrcode-grid">
            <div 
                v-for="(student, index) in filteredStudents" 
                :key="student.id" 
                class="qrcode-item print-page"
                :style="{ animationDelay: `${index * 0.05}s` }"
            >
                <StudentQRCode 
                    :studentId="student.id" 
                    :studentName="student.full_name || student.name || `${student.first_name} ${student.last_name}` || `Student ${student.id}`"
                    :section="student.section_name || 'N/A'"
                    :grade="student.grade_level || 'N/A'"
                />
            </div>
        </transition-group>

        <!-- Modern No results message -->
        <div v-if="!loading && filteredStudents.length === 0" class="no-results">
            <div class="no-results-icon">
                <i class="pi pi-search"></i>
            </div>
            <h3>No students found</h3>
            <p>No students match your search "{{ searchQuery }}"</p>
            <Button label="Clear Search" icon="pi pi-times" @click="searchQuery = ''" class="p-button-text" />
        </div>
    </div>
</template>

<script setup>
import StudentQRCode from '@/components/StudentQRCode.vue';
import { AttendanceService } from '@/router/service/Attendance';
import { QRCodeAPIService } from '@/router/service/QRCodeAPIService';
import { TeacherAttendanceService } from '@/router/service/TeacherAttendanceService';
// Import teacher authentication service (alternative approach)
import TeacherAuthServiceDefault from '@/services/TeacherAuthService.js';
import { computed, onMounted, ref } from 'vue';

// State variables
const students = ref([]);
const loading = ref(true);
const searchQuery = ref('');

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
        
        // Load teacher's assigned students instead of hardcoded Grade 3
        console.log(`Calling getTeacherAssignments for teacher ID: ${teacherId}`);
        const assignmentsResponse = await TeacherAttendanceService.getTeacherAssignments(teacherId);
        console.log('Assignments API response:', assignmentsResponse);
        
        // Extract assignments from the response object
        const assignments = assignmentsResponse?.assignments || assignmentsResponse || [];
        console.log('Extracted assignments array:', assignments);
        
        if (assignments && assignments.length > 0) {
            // Get all students from all teacher's assignments
            const allStudents = [];
            
            for (const assignment of assignments) {
                console.log('Processing assignment:', assignment);
                const studentsResponse = await TeacherAttendanceService.getStudentsForTeacherSubject(
                    teacherId, 
                    assignment.section_id, 
                    assignment.subject_id
                );
                console.log(`Students for section ${assignment.section_id}, subject ${assignment.subject_id}:`, studentsResponse);
                
                // Extract students from the response object
                const studentsData = studentsResponse?.students || studentsResponse || [];
                console.log('Extracted students array:', studentsData);
                
                if (studentsData && studentsData.length > 0) {
                    // Add students with section info
                    studentsData.forEach(student => {
                        student.section_name = assignment.section_name;
                        student.subject_name = assignment.subject_name;
                    });
                    allStudents.push(...studentsData);
                }
            }
            
            // Remove duplicates (same student in multiple subjects)
            const uniqueStudents = allStudents.filter((student, index, self) => 
                index === self.findIndex(s => s.id === student.id)
            );
            
            students.value = uniqueStudents;
            console.log(`Loaded ${uniqueStudents.length} students for teacher ${teacherId} QR codes`);
            console.log('Student data structure:', uniqueStudents);
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

// Computed property for filtered students
const filteredStudents = computed(() => {
    if (!searchQuery.value.trim()) return students.value;

    const query = searchQuery.value.toLowerCase();
    return students.value.filter((student) => {
        const studentName = student.full_name || student.name || `${student.first_name} ${student.last_name}` || '';
        return studentName.toLowerCase().includes(query) || 
               student.id.toString().includes(query) ||
               (student.first_name && student.first_name.toLowerCase().includes(query)) ||
               (student.last_name && student.last_name.toLowerCase().includes(query));
    });
});

// Print all QR codes
const printQRCodes = () => {
    window.print();
};

// Regenerate all QR codes
const regenerateQRCodes = async () => {
    try {
        loading.value = true;
        for (const student of students.value) {
            await QRCodeAPIService.generateQRCode(student.id);
        }
        // Force refresh of the component
        students.value = [...students.value];
    } catch (error) {
        console.error('Error regenerating QR codes:', error);
    } finally {
        loading.value = false;
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
    margin: 0;
    font-size: 1rem;
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
    0%, 100% {
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
    [class*="topbar"],
    [class*="sidebar"] {
        display: none !important;
    }

    /* Reset page to white */
    html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
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
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-height: 100vh !important;
        width: 100% !important;
        background: white !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        animation: none !important;
    }

    /* Last item doesn't need break after */
    .qrcode-item:last-child {
        page-break-after: avoid !important;
    }
}
</style>
