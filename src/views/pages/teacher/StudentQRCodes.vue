<template>
    <div class="student-qrcodes-page p-4">
        <div class="header mb-4">
            <h1 class="text-2xl font-bold">Student QR Codes</h1>
            <p class="text-gray-600">QR codes for your assigned students - Use these for attendance tracking</p>
        </div>

        <div class="flex justify-between items-center mb-4">
            <div class="flex gap-2">
                <Button label="Print All QR Codes" icon="pi pi-print" @click="printQRCodes" />
                <Button label="Generate New QR Codes" icon="pi pi-refresh" @click="regenerateQRCodes" class="p-button-outlined" />
            </div>
            <div class="search-container">
                <span class="p-input-icon-left w-full">
                    <i class="pi pi-search" />
                    <InputText v-model="searchQuery" placeholder="Search students..." class="w-full" />
                </span>
            </div>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="flex justify-center items-center py-8">
            <ProgressSpinner style="width: 50px; height: 50px" strokeWidth="4" />
            <span class="ml-3">Loading student data...</span>
        </div>

        <!-- QR code grid -->
        <div v-else class="qrcode-grid">
            <div v-for="student in filteredStudents" :key="student.id" class="qrcode-item p-3 print-page">
                <StudentQRCode 
                    :studentId="student.id" 
                    :studentName="student.full_name || student.name || `${student.first_name} ${student.last_name}` || `Student ${student.id}`"
                    :section="student.section_name || 'N/A'"
                    :grade="student.grade_level || 'N/A'"
                />
            </div>
        </div>

        <!-- No results message -->
        <div v-if="!loading && filteredStudents.length === 0" class="text-center py-8 text-gray-500">
            <i class="pi pi-search text-4xl mb-3"></i>
            <p>No students match your search criteria</p>
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
.qrcode-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1.5rem;
}

.qrcode-item {
    background-color: #f9f9f9;
    border-radius: 8px;
    transition: transform 0.2s;
}

.qrcode-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

/* Print Styles - One QR code per page */
@media print {
    /* Remove browser default headers and footers */
    @page {
        margin: 0;
        size: auto;
    }

    /* Hide EVERYTHING except our content */
    body > *:not(.student-qrcodes-page) {
        display: none !important;
    }

    /* Hide all UI elements and navigation */
    .header,
    .flex,
    .search-container,
    .p-button,
    button,
    nav,
    .layout-topbar,
    .layout-sidebar,
    .layout-menu,
    .layout-main-container,
    header,
    [class*="topbar"],
    [class*="sidebar"],
    [class*="menu"],
    [class*="navigation"],
    [class*="nav"] {
        display: none !important;
        visibility: hidden !important;
        height: 0 !important;
        overflow: hidden !important;
    }
    
    /* Hide parent layout elements */
    .layout-wrapper > *:not(.layout-main),
    .layout-main > *:not(.student-qrcodes-page),
    #app > *:not(.student-qrcodes-page) {
        display: none !important;
    }

    /* Reset page layout */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html, body {
        margin: 0 !important;
        padding: 0 !important;
        height: 100%;
        overflow: visible !important;
    }

    /* Force remove all wrappers and containers */
    body * {
        visibility: hidden !important;
    }

    .student-qrcodes-page,
    .student-qrcodes-page *,
    .print-page,
    .print-page * {
        visibility: visible !important;
    }

    .student-qrcodes-page {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 0 !important;
        margin: 0 !important;
        background: white;
    }

    /* One card per page layout */
    .qrcode-grid {
        display: block !important;
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
    }

    /* Last item doesn't need break after */
    .qrcode-item:last-child {
        page-break-after: avoid !important;
    }
}
</style>
