<script setup>
import { AttendanceService } from '@/router/service/Students';
import { SubjectService } from '@/router/service/Subjects';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();
const toast = useToast();
const subjectName = ref('Subject');
const subjectId = ref('');
const currentDate = ref(new Date().toISOString().split('T')[0]);

// Modals and UI states
const showSeatEditor = ref(false);
const showStudentDetails = ref(false);
const showAttendanceHistory = ref(false);
const showTemplateManager = ref(false);
const showTemplateSaveDialog = ref(false);
const isEditMode = ref(false);
const showRemarks = ref(false);

// Seating plan configuration
const rows = ref(6);
const columns = ref(6);
const templateName = ref('');
const savedTemplates = ref([]);
const selectedTemplate = ref(null);

// Layout configuration options
const showTeacherDesk = ref(true);
const showStudentIds = ref(true);
const currentGrade = ref('3');

// Student and attendance data
const students = ref([]);
const selectedStudent = ref(null);
const remarks = ref('');
const pendingStatus = ref('');
const searchQuery = ref('');
const unassignedStudents = ref([]);
const seatPlan = ref([]);
const attendanceHistory = ref([]);

// Drag and drop state
const isDragging = ref(false);
const draggedStudent = ref(null);
const draggedPosition = ref(null);

// Status options for attendance
const attendanceStatuses = [
    { name: 'Present', color: '#4caf50', icon: 'pi pi-check-circle' },
    { name: 'Absent', color: '#f44336', icon: 'pi pi-times-circle' },
    { name: 'Late', color: '#ff9800', icon: 'pi pi-clock' },
    { name: 'Excused', color: '#2196f3', icon: 'pi pi-info-circle' }
];

// Add these variables to the script setup
const autoScrollSpeed = ref(0);
const autoScrollInterval = ref(null);
const scrollThreshold = 100; // px from the edge of the viewport to start scrolling

// Toggle edit mode
const toggleEditMode = () => {
    isEditMode.value = !isEditMode.value;

    if (!isEditMode.value) {
        // Save the current layout when exiting edit mode
        saveCurrentLayout();

        toast.add({
            severity: 'success',
            summary: 'Layout Saved',
            detail: 'Your seat plan layout has been saved',
            life: 3000
        });
    }
};

// Save current layout without creating a template
const saveCurrentLayout = () => {
    localStorage.setItem(
        'currentLayout',
        JSON.stringify({
            grid: {
                rows: rows.value,
                columns: columns.value,
                seatPlan: seatPlan.value
            },
            showTeacherDesk: showTeacherDesk.value,
            showStudentIds: showStudentIds.value
        })
    );
};

// Format subject name for display
const formatSubjectName = (id) => {
    if (!id) return 'Subject';

    // Replace hyphens and underscores with spaces
    let name = id.replace(/[-_]/g, ' ');

    // Capitalize each word
    name = name.replace(/\w\S*/g, (txt) => {
        return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
    });

    return name;
};

// Create an empty seat plan grid
const initializeSeatPlan = () => {
    const grid = [];
    for (let i = 0; i < rows.value; i++) {
        const row = [];
        for (let j = 0; j < columns.value; j++) {
            row.push({
                row: i,
                col: j,
                studentId: null,
                status: null,
                isOccupied: false
            });
        }
        grid.push(row);
    }
    seatPlan.value = grid;

    // Recalculate unassigned students when grid size changes
    calculateUnassignedStudents();
};

// Update seat plan with changes to row/column count
watch([rows, columns], () => {
    if (isEditMode.value) {
        // Save current student assignments
        const currentAssignments = [];

        // Extract current assignments from the existing seat plan
        for (let i = 0; i < seatPlan.value.length; i++) {
            for (let j = 0; j < seatPlan.value[i].length; j++) {
                const seat = seatPlan.value[i][j];
                if (seat.isOccupied && seat.studentId) {
                    currentAssignments.push({
                        studentId: seat.studentId,
                        status: seat.status,
                        row: i,
                        col: j
                    });
                }
            }
        }

        // Initialize new seat plan with updated dimensions
        initializeSeatPlan();

        // Restore assignments where possible in the new grid
        for (const assignment of currentAssignments) {
            // Only restore if the row and column exist in the new grid
            if (assignment.row < rows.value && assignment.col < columns.value) {
                const seat = seatPlan.value[assignment.row][assignment.col];
                seat.studentId = assignment.studentId;
                seat.status = assignment.status;
                seat.isOccupied = true;
            }
        }

        // Recalculate unassigned students with the updated seat plan
        calculateUnassignedStudents();
    }
});

// Get student initials
const getStudentInitials = (student) => {
    if (!student || !student.name) return '?';
    return student.name
        .split(' ')
        .map((word) => word.charAt(0))
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

// Check if a seat is occupied
const isSeatOccupied = (row, col) => {
    if (!seatPlan.value[row] || !seatPlan.value[row][col]) return false;
    return seatPlan.value[row][col].isOccupied;
};

// Get student data by ID
const getStudentById = (id) => {
    if (!id) return null;
    return students.value.find((student) => student.id === id) || null;
};

// Get student assigned to a seat
const getStudentAtSeat = (row, col) => {
    if (!seatPlan.value[row] || !seatPlan.value[row][col]) return null;
    const studentId = seatPlan.value[row][col].studentId;
    return getStudentById(studentId);
};

// Get student's current status
const getStudentStatus = (student) => {
    if (!student) return null;

    // Find the seat with this student
    for (const row of seatPlan.value) {
        for (const seat of row) {
            if (seat.studentId === student.id) {
                return seat.status;
            }
        }
    }

    return null;
};

// Start auto-scroll based on mouse position
const handleDragOver = (event) => {
    if (!isDragging.value) return;

    const { clientY } = event;
    const { innerHeight } = window;

    // Calculate distance from top and bottom of viewport
    const distanceFromTop = clientY;
    const distanceFromBottom = innerHeight - clientY;

    // Clear any existing interval
    clearAutoScroll();

    // Auto-scroll up if near the top
    if (distanceFromTop < scrollThreshold) {
        const scrollSpeed = Math.max(5, Math.floor((scrollThreshold - distanceFromTop) / 5));
        startAutoScroll(-scrollSpeed);
    }
    // Auto-scroll down if near the bottom
    else if (distanceFromBottom < scrollThreshold) {
        const scrollSpeed = Math.max(5, Math.floor((scrollThreshold - distanceFromBottom) / 5));
        startAutoScroll(scrollSpeed);
    }
};

// Start auto-scrolling with the given speed
const startAutoScroll = (speed) => {
    autoScrollSpeed.value = speed;

    if (!autoScrollInterval.value) {
        autoScrollInterval.value = setInterval(() => {
            window.scrollBy(0, autoScrollSpeed.value);
        }, 16); // ~60fps
    }
};

// Clear auto-scroll interval
const clearAutoScroll = () => {
    if (autoScrollInterval.value) {
        clearInterval(autoScrollInterval.value);
        autoScrollInterval.value = null;
    }
};

// Modify the startDrag function to initialize drag state
const startDrag = (student, position) => {
    isDragging.value = true;
    draggedStudent.value = student;
    draggedPosition.value = position;

    // Add global event listeners
    document.addEventListener('dragover', handleDragOver);
    document.addEventListener('dragend', clearAutoScroll);
    document.addEventListener('drop', clearAutoScroll);
};

// Modify the existing cancelDrag and dropOnSeat functions to clean up
const cancelDrag = () => {
    isDragging.value = false;
    draggedStudent.value = null;
    draggedPosition.value = null;
    clearAutoScroll();

    // Remove global event listeners
    document.removeEventListener('dragover', handleDragOver);
    document.removeEventListener('dragend', clearAutoScroll);
    document.removeEventListener('drop', clearAutoScroll);
};

// Update the other drop functions to also clean up
const dropOnSeat = (rowIndex, colIndex) => {
    if (!draggedStudent.value) return;

    // If student was already placed, clear the previous seat
    if (draggedPosition.value) {
        const { row, col } = draggedPosition.value;
        seatPlan.value[row][col].studentId = null;
        seatPlan.value[row][col].isOccupied = false;
    }

    // Assign student to new seat
    seatPlan.value[rowIndex][colIndex].studentId = draggedStudent.value.id;
    seatPlan.value[rowIndex][colIndex].isOccupied = true;

    // Reset drag state
    const studentName = draggedStudent.value.name;
    cancelDrag();

    // Update unassigned students
    calculateUnassignedStudents();

    toast.add({
        severity: 'success',
        summary: 'Student Assigned',
        detail: `${studentName} has been assigned to seat (Row ${rowIndex + 1}, Col ${colIndex + 1})`,
        life: 3000
    });
};

const dropToUnassigned = () => {
    if (!draggedStudent.value) return;

    // Clear the seat the student was in
    if (draggedPosition.value) {
        // Standard grid seat
        const { row, col } = draggedPosition.value;
        seatPlan.value[row][col].studentId = null;
        seatPlan.value[row][col].isOccupied = false;
    }

    // Reset drag state
    cancelDrag();

    // Update unassigned students
    calculateUnassignedStudents();
};

// Toggle attendance status for a seat
const toggleAttendanceStatus = (rowIndex, colIndex) => {
    const seat = seatPlan.value[rowIndex][colIndex];
    if (!seat.isOccupied) return;

    // Find the current status index
    const currentStatusIndex = attendanceStatuses.findIndex((status) => status.name === seat.status);

    // Move to next status, or to Present if no status yet
    const nextIndex = currentStatusIndex === -1 ? 0 : (currentStatusIndex + 1) % attendanceStatuses.length;
    seat.status = attendanceStatuses[nextIndex].name;

    // Save attendance record
    saveAttendanceRecord(seat.studentId, seat.status);
};

// Show remarks dialog for a student
const showRemarksDialog = (row, col, status) => {
    const seat = seatPlan.value[row][col];
    if (!seat.isOccupied) return;

    selectedStudent.value = getStudentById(seat.studentId);
    remarks.value = ''; // Clear previous remarks
    pendingStatus.value = status || seat.status || 'Absent';
    showRemarks.value = true;
};

// Save attendance with remarks
const saveWithRemarks = async () => {
    if (!selectedStudent.value || !pendingStatus.value) return;

    await saveAttendanceRecord(selectedStudent.value.id, pendingStatus.value, remarks.value);

    // Find the student in the grid
    for (const row of seatPlan.value) {
        for (const seat of row) {
            if (seat.studentId === selectedStudent.value.id) {
                seat.status = pendingStatus.value;
            }
        }
    }

    // Clear state
    remarks.value = '';
    showRemarks.value = false;
    pendingStatus.value = '';
};

// Save attendance record to the service
const saveAttendanceRecord = async (studentId, status, remarks = '') => {
    try {
        const student = getStudentById(studentId);
        if (!student) return;

        const attendanceRecord = {
            date: currentDate.value,
            studentName: student.name,
            studentId: student.id,
            status: status,
            time: new Date().toLocaleTimeString(),
            remarks: remarks
        };

        // Record attendance in the service
        await AttendanceService.recordAttendance(student.id, attendanceRecord);

        // Add to local attendance history
        attendanceHistory.value.push(attendanceRecord);

        // Show success message
        toast.add({
            severity: 'success',
            summary: 'Attendance Recorded',
            detail: `${student.name} marked as ${status}`,
            life: 3000
        });
    } catch (error) {
        console.error('Error recording attendance:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to record attendance',
            life: 3000
        });
    }
};

// Save the current seat plan as a template
const saveAsTemplate = () => {
    if (!templateName.value) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Please enter a template name',
            life: 3000
        });
        return;
    }

    // Create a template object
    const template = {
        id: Date.now().toString(),
        name: templateName.value,
        showTeacherDesk: showTeacherDesk.value,
        showStudentIds: showStudentIds.value,

        // Grid layout data
        rows: rows.value,
        columns: columns.value,
        seatPlan: JSON.parse(JSON.stringify(seatPlan.value)),

        // Template metadata
        createdAt: new Date().toISOString(),
        subjectId: subjectId.value,
        grade: currentGrade.value
    };

    // Add to saved templates
    savedTemplates.value.push(template);

    // Save to local storage
    const storageKey = `seatPlan_${subjectId.value}`;
    localStorage.setItem(storageKey, JSON.stringify(savedTemplates.value));

    // Close dialog and reset
    templateName.value = '';
    showTemplateSaveDialog.value = false;

    toast.add({
        severity: 'success',
        summary: 'Template Saved',
        detail: `Seat plan template "${template.name}" has been saved`,
        life: 3000
    });
};

// Load a saved template
const loadTemplate = (template) => {
    // Apply layout options
    showTeacherDesk.value = template.showTeacherDesk !== undefined ? template.showTeacherDesk : true;
    showStudentIds.value = template.showStudentIds !== undefined ? template.showStudentIds : true;

    // Apply grid layout
    rows.value = template.rows || 6;
    columns.value = template.columns || 6;

    // Reset and initialize seat plan with template data
    initializeSeatPlan();

    // Apply student assignments if present
    if (template.seatPlan) {
        for (let i = 0; i < Math.min(template.seatPlan.length, seatPlan.value.length); i++) {
            for (let j = 0; j < Math.min(template.seatPlan[i].length, seatPlan.value[i].length); j++) {
                const sourceSeat = template.seatPlan[i][j];
                const targetSeat = seatPlan.value[i][j];

                // Copy seat properties, but ensure student actually exists
                if (sourceSeat.studentId && getStudentById(sourceSeat.studentId)) {
                    targetSeat.studentId = sourceSeat.studentId;
                    targetSeat.isOccupied = true;
                }
            }
        }
    }

    // Update unassigned students
    calculateUnassignedStudents();

    // Close template manager
    showTemplateManager.value = false;

    toast.add({
        severity: 'success',
        summary: 'Template Loaded',
        detail: `Seating layout "${template.name}" has been loaded`,
        life: 3000
    });
};

// Delete a saved template
const deleteTemplate = (templateId) => {
    const index = savedTemplates.value.findIndex((t) => t.id === templateId);
    if (index !== -1) {
        const deletedTemplate = savedTemplates.value[index];
        savedTemplates.value.splice(index, 1);

        // Update local storage
        const storageKey = `seatPlan_${subjectId.value}`;
        localStorage.setItem(storageKey, JSON.stringify(savedTemplates.value));

        toast.add({
            severity: 'success',
            summary: 'Template Deleted',
            detail: `Template "${deletedTemplate.name}" has been deleted`,
            life: 3000
        });
    }
};

// Load saved templates from local storage
const loadSavedTemplates = () => {
    try {
        const storageKey = `seatPlan_${subjectId.value}`;
        const savedData = localStorage.getItem(storageKey);
        if (savedData) {
            savedTemplates.value = JSON.parse(savedData);
        }
        return Promise.resolve();
    } catch (error) {
        console.error('Error loading saved templates:', error);
        return Promise.reject(error);
    }
};

// Calculate unassigned students based on seating assignment
const calculateUnassignedStudents = () => {
    // Create a set of all assigned student IDs
    const assignedIds = new Set();

    // Check all seats in the grid layout
    for (const row of seatPlan.value) {
        for (const seat of row) {
            if (seat.studentId) {
                assignedIds.add(seat.studentId);
            }
        }
    }

    // Find students not in the assigned set
    unassignedStudents.value = students.value.filter((student) => !assignedIds.has(student.id));
};

// Filter unassigned students by search query
const filteredUnassignedStudents = computed(() => {
    if (!searchQuery.value) return unassignedStudents.value;
    const query = searchQuery.value.toLowerCase();
    return unassignedStudents.value.filter((student) => student.name.toLowerCase().includes(query) || student.id.toString().includes(query));
});

// Fetch attendance history from service
const fetchAttendanceHistory = async () => {
    try {
        if (!subjectId.value) return;

        const records = await AttendanceService.getAttendanceRecords(subjectId.value);
        console.log('Fetched attendance records:', records);

        // Group records by student ID and find the most recent status for each student
        updateSeatPlanStatuses(records);
    } catch (error) {
        console.error('Error fetching attendance history:', error);
    }
};

// Update seat plan statuses based on attendance records
const updateSeatPlanStatuses = (records) => {
    // Group records by student ID
    const studentRecords = {};

    for (const record of records) {
        if (!studentRecords[record.studentId]) {
            studentRecords[record.studentId] = [];
        }
        studentRecords[record.studentId].push(record);
    }

    // For each student, find the latest record and update their status
    for (const studentId in studentRecords) {
        // Sort by date (newest first)
        const sortedRecords = studentRecords[studentId].sort((a, b) => {
            return new Date(b.date) - new Date(a.date);
        });

        // Get the most recent record
        const latestRecord = sortedRecords[0];

        // Update status in grid layout
        updateStudentStatusInGrid(studentId, latestRecord.status);
    }
};

// Helper to update status in grid
const updateStudentStatusInGrid = (studentId, status) => {
    for (let i = 0; i < seatPlan.value.length; i++) {
        for (let j = 0; j < seatPlan.value[i].length; j++) {
            if (seatPlan.value[i][j].studentId === studentId) {
                seatPlan.value[i][j].status = status;
                return;
            }
        }
    }
};

// Format date for display
const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
};

// Mark all students as present
const markAllPresent = () => {
    // Mark all seats in the grid as Present
    for (let i = 0; i < seatPlan.value.length; i++) {
        for (let j = 0; j < seatPlan.value[i].length; j++) {
            if (seatPlan.value[i][j].isOccupied) {
                seatPlan.value[i][j].status = 'Present';
                saveAttendanceRecord(seatPlan.value[i][j].studentId, 'Present');
            }
        }
    }

    toast.add({
        severity: 'success',
        summary: 'Attendance Updated',
        detail: 'All students marked as present',
        life: 3000
    });
};

// Mark all students as absent
const markAllAbsent = () => {
    // Mark all seats in the grid as Absent
    for (let i = 0; i < seatPlan.value.length; i++) {
        for (let j = 0; j < seatPlan.value[i].length; j++) {
            if (seatPlan.value[i][j].isOccupied) {
                seatPlan.value[i][j].status = 'Absent';
                saveAttendanceRecord(seatPlan.value[i][j].studentId, 'Absent');
            }
        }
    }

    toast.add({
        severity: 'success',
        summary: 'Attendance Updated',
        detail: 'All students marked as absent',
        life: 3000
    });
};

// Reset all attendance statuses
const resetAttendance = () => {
    // Reset all statuses in the grid
    for (let i = 0; i < seatPlan.value.length; i++) {
        for (let j = 0; j < seatPlan.value[i].length; j++) {
            if (seatPlan.value[i][j].isOccupied) {
                seatPlan.value[i][j].status = null;
            }
        }
    }

    toast.add({
        severity: 'success',
        summary: 'Attendance Reset',
        detail: 'All attendance statuses have been cleared',
        life: 3000
    });
};

// Get color for status
const getStatusColor = (status) => {
    const statusObj = attendanceStatuses.find((s) => s.name === status);
    return statusObj ? statusObj.color : 'transparent';
};

// Get icon for status
const getStatusIcon = (status) => {
    const statusObj = attendanceStatuses.find((s) => s.name === status);
    return statusObj ? statusObj.icon : '';
};

// Watch for route changes to update subject
watch(
    () => route.params,
    (params) => {
        const matchedSubject = params.subjectId;

        if (matchedSubject) {
            subjectName.value = formatSubjectName(matchedSubject);
            subjectId.value = matchedSubject;
            loadSavedTemplates();
        } else {
            subjectName.value = 'Subject';
            subjectId.value = '';
        }
    },
    { immediate: true }
);

// Initialize data on component mount
onMounted(async () => {
    try {
        // Get subject ID from route params
        if (route.params.subjectId) {
            subjectId.value = route.params.subjectId;

            // Format subject name from ID
            subjectName.value = formatSubjectName(subjectId.value);

            // Try to fetch actual subject data
            try {
                const subject = await SubjectService.getSubjectById(subjectId.value);
                if (subject && subject.name) {
                    subjectName.value = subject.name;
                }
            } catch (err) {
                console.warn('Could not fetch subject details', err);
            }
        }

        // Fetch students
        const studentsData = await AttendanceService.getData();
        if (studentsData && studentsData.length > 0) {
            students.value = studentsData;
        } else {
            console.error('No students found in the database!');

            // Create sample students for testing
            students.value = [
                { id: 1, name: 'Juan Dela Cruz', gradeLevel: 3, section: 'Magalang' },
                { id: 2, name: 'Maria Santos', gradeLevel: 3, section: 'Magalang' },
                { id: 3, name: 'Pedro Penduko', gradeLevel: 3, section: 'Magalang' },
                { id: 4, name: 'Ana Reyes', gradeLevel: 3, section: 'Mahinahon' },
                { id: 5, name: 'Jose Rizal', gradeLevel: 3, section: 'Mahinahon' },
                { id: 6, name: 'Gabriela Silang', gradeLevel: 3, section: 'Mahinahon' }
            ];
        }

        // Initialize an empty seat plan for grid layout
        initializeSeatPlan();

        // Set all students as unassigned initially
        unassignedStudents.value = [...students.value];

        // Fetch attendance history
        await fetchAttendanceHistory();

        // Load saved templates
        await loadSavedTemplates();

        // Use default layout if no templates exist
        if (savedTemplates.value.length === 0) {
            toast.add({
                severity: 'info',
                summary: 'Welcome to Seat Plan Attendance',
                detail: 'Create your own classroom layout using the Edit Seats button',
                life: 5000
            });
        } else {
            // Load the most recently created template
            const defaultTemplate = savedTemplates.value.sort((a, b) => {
                return new Date(b.createdAt) - new Date(a.createdAt);
            })[0];

            if (defaultTemplate) {
                loadTemplate(defaultTemplate);
            }
        }
    } catch (error) {
        console.error('Error initializing data:', error);
    }
});
</script>

<template>
    <div class="attendance-container p-4">
        <!-- Header section with subject info and date selector -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
            <div>
                <h1 class="text-2xl font-bold">{{ subjectName }} Attendance</h1>
                <p class="text-gray-600">{{ formatDate(currentDate) }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Calendar v-model="currentDate" dateFormat="yy-mm-dd" showIcon class="calendar-input" />
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons flex flex-wrap gap-2 mb-4">
            <Button icon="pi pi-pencil" class="p-button-success" @click="toggleEditMode" :class="{ 'p-button-outlined': !isEditMode }"> Edit Seats </Button>

            <Button icon="pi pi-save" label="Save as Template" class="p-button-outlined" @click="showTemplateSaveDialog = true" />

            <Button icon="pi pi-list" label="Load Template" class="p-button-outlined" @click="showTemplateManager = true" />

            <!-- Attendance Actions -->
            <Button icon="pi pi-check-circle" label="Mark All Present" class="p-button-success" @click="markAllPresent" />

            <Button icon="pi pi-times-circle" label="Mark All Absent" class="p-button-danger" @click="markAllAbsent" />

            <Button icon="pi pi-refresh" label="Reset Attendance" class="p-button-outlined" @click="resetAttendance" />
        </div>

        <!-- Edit Mode Notice -->
        <div v-if="isEditMode" class="bg-blue-100 text-blue-800 p-3 mb-4 rounded-lg">
            <i class="pi pi-info-circle mr-2"></i>
            Edit mode active. Drag students from the unassigned list to assign them to seats. Click "Edit Seats" again to save.
        </div>

        <!-- Layout Configuration -->
        <div class="layout-config bg-white rounded-lg shadow-sm border mb-4">
            <h3 class="text-lg font-medium px-4 pt-4 pb-2">Layout Configuration</h3>
            <div class="grid grid-cols-2 gap-4 px-4 pb-4">
                <!-- Row and Column configuration -->
                <div class="config-group">
                    <div class="flex flex-col">
                        <label for="rowsInput" class="mb-2 font-medium text-sm">Rows:</label>
                        <InputNumber id="rowsInput" v-model="rows" :min="1" :max="10" class="w-full" :disabled="!isEditMode" showButtons inputClass="w-full" />
                    </div>
                </div>

                <div class="config-group">
                    <div class="flex flex-col">
                        <label for="columnsInput" class="mb-2 font-medium text-sm">Columns:</label>
                        <InputNumber id="columnsInput" v-model="columns" :min="1" :max="10" class="w-full" :disabled="!isEditMode" showButtons inputClass="w-full" />
                    </div>
                </div>
            </div>

            <!-- Additional options -->
            <div class="flex items-center px-4 py-3 border-t border-gray-100">
                <div class="flex items-center mr-6">
                    <Checkbox id="teacherDesk" v-model="showTeacherDesk" :binary="true" :disabled="!isEditMode" />
                    <label for="teacherDesk" class="ml-2 text-sm">Show Teacher's Desk</label>
                </div>

                <div class="flex items-center">
                    <Checkbox id="studentIds" v-model="showStudentIds" :binary="true" :disabled="!isEditMode" />
                    <label for="studentIds" class="ml-2 text-sm">Show Student IDs</label>
                </div>
            </div>
        </div>

        <!-- Seating grid layout -->
        <div class="seating-grid-container mt-4">
            <!-- Teacher's desk at the top (if enabled) -->
            <div v-if="showTeacherDesk" class="teacher-desk mb-6">
                <div class="teacher-desk-label bg-blue-50 p-3 text-center rounded-lg border border-blue-200 shadow-sm font-medium">
                    <i class="pi pi-user mr-2"></i>
                    Teacher's Desk
                </div>
            </div>

            <!-- Seating grid -->
            <div class="seating-grid">
                <div v-for="(row, rowIndex) in seatPlan" :key="rowIndex" class="seat-row flex justify-center mb-3">
                    <div
                        v-for="(seat, colIndex) in row"
                        :key="`${rowIndex}-${colIndex}`"
                        class="seat-container mx-2"
                        @click="!isEditMode && seat.isOccupied ? toggleAttendanceStatus(rowIndex, colIndex) : null"
                        @dragover.prevent="isEditMode"
                        @drop="isEditMode ? dropOnSeat(rowIndex, colIndex) : null"
                    >
                        <div
                            class="seat p-3 rounded-lg border select-none"
                            :class="{
                                'bg-white border-gray-300 shadow-sm': !seat.isOccupied,
                                'bg-blue-50 border-blue-200 shadow': seat.isOccupied && !seat.status,
                                'drop-target': isEditMode && !seat.isOccupied,
                                'student-present': seat.status === 'Present',
                                'student-absent': seat.status === 'Absent',
                                'student-late': seat.status === 'Late',
                                'student-excused': seat.status === 'Excused'
                            }"
                        >
                            <div v-if="seat.isOccupied" class="student-info">
                                <div
                                    class="student-initials"
                                    :class="{
                                        'initials-present': seat.status === 'Present',
                                        'initials-absent': seat.status === 'Absent',
                                        'initials-late': seat.status === 'Late',
                                        'initials-excused': seat.status === 'Excused'
                                    }"
                                >
                                    {{ getStudentInitials(getStudentById(seat.studentId)) }}
                                </div>
                                <div class="student-name">{{ getStudentById(seat.studentId)?.name }}</div>
                                <div v-if="showStudentIds" class="student-id">ID: {{ seat.studentId }}</div>
                            </div>
                            <div v-else-if="isEditMode" class="empty-seat">
                                <span>Empty</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unassigned students section -->
        <div class="unassigned-section mb-4">
            <h3 class="text-lg font-semibold mb-2">Unassigned Students</h3>

            <div class="search-container mb-3">
                <span class="p-input-icon-left w-full">
                    <i class="pi pi-search" />
                    <InputText v-model="searchQuery" placeholder="Search students..." class="w-full" />
                </span>
            </div>

            <div class="unassigned-list p-3 bg-gray-50 rounded-lg">
                <div v-if="filteredUnassignedStudents.length === 0" class="text-center p-4">
                    <p v-if="unassignedStudents.length === 0" class="text-gray-500">All students have been assigned to seats.</p>
                    <p v-else class="text-gray-500">No students match your search.</p>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="student in filteredUnassignedStudents" :key="student.id" class="student-card p-3 bg-white rounded-lg border cursor-move" draggable="true" @dragstart="startDrag(student, null)">
                        <div class="flex items-center">
                            <div class="student-avatar w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                {{ getStudentInitials(student) }}
                            </div>
                            <div>
                                <div class="student-name font-medium">{{ student.name }}</div>
                                <div class="student-details text-xs text-gray-500">ID: {{ student.id }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="isEditMode && isDragging" class="unassign-dropzone mt-3 p-3 border-2 border-dashed border-gray-300 rounded-lg text-center" @dragover.prevent @drop="dropToUnassigned">Drop here to unassign a student</div>
        </div>

        <!-- Dialogs -->

        <!-- Save Template Dialog -->
        <Dialog v-model:visible="showTemplateSaveDialog" header="Save as Template" :modal="true" style="width: 30vw; min-width: 400px">
            <div class="p-fluid">
                <div class="field">
                    <label for="templateName">Template Name</label>
                    <InputText id="templateName" v-model="templateName" autofocus />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showTemplateSaveDialog = false" class="p-button-text" />
                <Button label="Save" icon="pi pi-save" @click="saveAsTemplate" autofocus />
            </template>
        </Dialog>

        <!-- Template Manager Dialog -->
        <Dialog v-model:visible="showTemplateManager" header="Manage Templates" :modal="true" style="width: 60vw; min-width: 500px">
            <div v-if="savedTemplates.length === 0" class="text-center p-4">
                <p class="text-gray-500">No saved templates found.</p>
            </div>

            <DataTable v-else :value="savedTemplates">
                <Column field="name" header="Template Name"></Column>
                <Column field="createdAt" header="Created On">
                    <template #body="slotProps">
                        {{ formatDate(slotProps.data.createdAt) }}
                    </template>
                </Column>
                <Column header="Actions">
                    <template #body="slotProps">
                        <Button icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-outlined mr-2" @click="deleteTemplate(slotProps.data.id)" />
                        <Button icon="pi pi-check" class="p-button-rounded p-button-success p-button-outlined" @click="loadTemplate(slotProps.data)" />
                    </template>
                </Column>
            </DataTable>

            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="showTemplateManager = false" class="p-button-text" />
            </template>
        </Dialog>

        <!-- Remarks Dialog -->
        <Dialog v-model:visible="showRemarks" header="Add Remarks" :modal="true" style="width: 30vw; min-width: 400px">
            <div v-if="selectedStudent" class="p-fluid">
                <div class="field mb-4">
                    <h4 class="font-medium mb-2">{{ selectedStudent.name }}</h4>
                    <div class="flex gap-3 mb-4">
                        <div
                            v-for="status in attendanceStatuses"
                            :key="status.name"
                            class="status-option p-2 rounded-lg cursor-pointer"
                            :class="{ selected: pendingStatus === status.name }"
                            :style="{ borderColor: status.color, backgroundColor: pendingStatus === status.name ? status.color + '20' : 'transparent' }"
                            @click="pendingStatus = status.name"
                        >
                            <i :class="status.icon" :style="{ color: status.color }"></i>
                            <span class="ml-1">{{ status.name }}</span>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label for="remarks">Remarks (optional)</label>
                    <Textarea id="remarks" v-model="remarks" rows="3" autoResize />
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showRemarks = false" class="p-button-text" />
                <Button label="Save" icon="pi pi-save" @click="saveWithRemarks" autofocus />
            </template>
        </Dialog>
    </div>
</template>

<style>
.teacher-route {
    --seat-width: 130px;
    --seat-height: 110px;
}

.seating-grid-container {
    width: 100%;
    overflow-x: auto;
}

.teacher-desk {
    max-width: 300px;
    margin: 0 auto 2rem;
}

.teacher-desk-label {
    width: 100%;
}

.seating-grid {
    width: max-content;
    margin: 0 auto;
}

.seat-row {
    display: flex;
    justify-content: center;
    margin-bottom: 1rem;
}

.seat-container {
    width: var(--seat-width);
    min-width: var(--seat-width);
    margin: 0 0.5rem;
}

.seat {
    height: var(--seat-height);
    width: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    transition: all 0.2s;
}

.drop-target {
    border: 2px dashed #4caf50 !important;
    background-color: rgba(76, 175, 80, 0.1) !important;
}

.student-info {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.student-initials {
    width: 40px;
    height: 40px;
    background-color: #3f51b5;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.initials-present {
    background-color: #4caf50;
}

.initials-absent {
    background-color: #f44336;
}

.initials-late {
    background-color: #ff9800;
}

.initials-excused {
    background-color: #2196f3;
}

.student-present {
    background-color: rgba(76, 175, 80, 0.1);
    border-color: #4caf50;
}

.student-absent {
    background-color: rgba(244, 67, 54, 0.1);
    border-color: #f44336;
}

.student-late {
    background-color: rgba(255, 152, 0, 0.1);
    border-color: #ff9800;
}

.student-excused {
    background-color: rgba(33, 150, 243, 0.1);
    border-color: #2196f3;
}

.student-name {
    font-weight: bold;
    margin-bottom: 0.25rem;
    font-size: 0.9rem;
    max-width: 120px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.student-id {
    font-size: 0.75rem;
    color: #666;
    margin-bottom: 0.25rem;
}

.empty-seat {
    color: #999;
    font-style: italic;
}

.unassigned-section {
    margin-top: 2rem;
}

.student-card {
    transition: all 0.2s;
}

.student-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Dialog styling */
:deep(.p-dialog-content) {
    padding: 1.5rem;
}
</style>
