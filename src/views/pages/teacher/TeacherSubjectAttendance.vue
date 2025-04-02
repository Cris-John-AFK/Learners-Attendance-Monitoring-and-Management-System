<script setup>
import { AttendanceService } from '@/router/service/Students';
import { SubjectService } from '@/router/service/Subjects';
import { BrowserMultiFormatReader } from '@zxing/browser';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

// Add OverlayPanel and Menu components

// Add Dialog component if not already imported

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
const rows = ref(9);
const columns = ref(9);
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
const attendanceRecords = ref({});
const remarksPanel = ref([]);

// Drag and drop state
const isDragging = ref(false);
const draggedStudent = ref(null);
const draggedPosition = ref(null);

// Status menu and panel refs
const statusMenu = ref(null);
const selectedSeat = ref(null);
const studentsWithRemarks = ref([]);

// Attendance statuses with icons and colors
const attendanceStatuses = [
    { name: 'Present', icon: 'pi pi-check-circle', color: '#4caf50' },
    { name: 'Absent', icon: 'pi pi-times-circle', color: '#f44336', requiresRemarks: true },
    { name: 'Late', icon: 'pi pi-clock', color: '#ff9800' },
    { name: 'Excused', icon: 'pi pi-info-circle', color: '#2196f3', requiresRemarks: true }
];

// Status menu items
const statusMenuItems = computed(() => {
    return attendanceStatuses.map((status) => ({
        label: status.name,
        icon: status.icon,
        command: () => {
            if (selectedSeat.value) {
                const { rowIndex, colIndex } = selectedSeat.value;
                updateStudentStatus(rowIndex, colIndex, status.name);
            }
        },
        style: { color: status.color }
    }));
});

// Add these variables to the script setup
const autoScrollSpeed = ref(0);
const autoScrollInterval = ref(null);
const scrollThreshold = 100; // px from the edge of the viewport to start scrolling

// Add these new refs for the remarks functionality
const showRemarksDialog = ref(false);
const selectedStudentForRemarks = ref(null);
const attendanceRemarks = ref('');

// Add this new ref to track if dialog was canceled
const dialogCanceled = ref(false);

// Status selection dialog
const showStatusDialog = ref(false);

// Attendance method selection
const showAttendanceMethodModal = ref(true); // Start with this visible
const showQRScanner = ref(false);
const showRollCall = ref(false);
const isCameraLoading = ref(true);
const videoElement = ref(null);
const currentStudentIndex = ref(0);
const currentStudent = ref(null);
const scannedStudents = ref([]);
let codeReader = null;

// Rename to avoid conflict
const showAttendanceDialog = ref(false);

// Toggle edit mode
const toggleEditMode = () => {
    isEditMode.value = !isEditMode.value;

    if (isEditMode.value) {
        // Entering edit mode
        calculateUnassignedStudents();
    } else {
        // Exiting edit mode - save the current layout
        saveCurrentLayout();
    }
};

// Save current layout
const saveCurrentLayout = () => {
    // Implementation depends on your storage mechanism
    console.log('Saving current layout');

    // Example: save to local storage
    const layout = {
        rows: rows.value,
        columns: columns.value,
        seatPlan: seatPlan.value,
        showTeacherDesk: showTeacherDesk.value,
        showStudentIds: showStudentIds.value
    };

    localStorage.setItem(`seatPlan_${subjectId.value}`, JSON.stringify(layout));

    toast.add({
        severity: 'success',
        summary: 'Layout Saved',
        detail: 'Seating arrangement has been saved',
        life: 3000
    });
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
    seatPlan.value = [];
    for (let i = 0; i < rows.value; i++) {
        const row = [];
        for (let j = 0; j < columns.value; j++) {
            row.push({
                isOccupied: false,
                studentId: null,
                status: null
            });
        }
        seatPlan.value.push(row);
    }
};

// Update seat plan with changes to row/column count
watch([rows, columns], ([newRows, newColumns], [oldRows, oldColumns]) => {
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

        // Create a new seat plan with the updated dimensions
        const newSeatPlan = [];

        // If we're adding rows, add them at the top
        // If we're removing rows, remove them from the top
        const rowDifference = newRows - oldRows;

        if (rowDifference > 0) {
            // Add new rows at the top (further from teacher's desk)
            for (let i = 0; i < rowDifference; i++) {
                const newRow = [];
                for (let j = 0; j < newColumns; j++) {
                    newRow.push({
                        row: i,
                        col: j,
                        studentId: null,
                        status: null,
                        isOccupied: false
                    });
                }
                newSeatPlan.push(newRow);
            }
        }

        // Keep existing rows, starting from the bottom ones (closest to teacher)
        // if reducing rows, we'll skip the top ones
        const startIndex = Math.max(0, -rowDifference);
        const endIndex = oldRows;

        for (let i = startIndex; i < endIndex; i++) {
            if (i >= seatPlan.value.length) continue;

            const newRow = [];
            const oldRow = seatPlan.value[i];

            // Handle column changes
            const colDifference = newColumns - oldColumns;

            // Keep existing columns, add new ones if needed
            for (let j = 0; j < Math.min(oldColumns, newColumns); j++) {
                if (j < oldRow.length) {
                    // Copy existing seat
                    newRow.push({ ...oldRow[j] });
                }
            }

            // Add new columns if needed
            if (colDifference > 0) {
                for (let j = oldColumns; j < newColumns; j++) {
                    newRow.push({
                        row: newSeatPlan.length,
                        col: j,
                        studentId: null,
                        status: null,
                        isOccupied: false
                    });
                }
            }

            newSeatPlan.push(newRow);
        }

        // Update the seat plan
        seatPlan.value = newSeatPlan;

        // Recalculate unassigned students
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
    // Only allow drops in edit mode
    if (!isEditMode.value || !draggedStudent.value) return;

    const seat = seatPlan.value[rowIndex][colIndex];

    // If seat is already occupied, don't allow drop
    if (seat.isOccupied) {
        toast.add({
            severity: 'warn',
            summary: 'Seat Occupied',
            detail: 'This seat is already assigned to a student',
            life: 3000
        });
        return;
    }

    // Assign student to seat
    seat.studentId = draggedStudent.value.id;
    seat.isOccupied = true;

    // Remove from unassigned students
    const index = unassignedStudents.value.findIndex((s) => s.id === draggedStudent.value.id);
    if (index !== -1) {
        unassignedStudents.value.splice(index, 1);
    }

    // Reset dragged student
    draggedStudent.value = null;

    // Show success message
    toast.add({
        severity: 'success',
        summary: 'Student Assigned',
        detail: 'Student has been assigned to seat',
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

// Show status menu for a student
const showStatusMenu = (event, rowIndex, colIndex) => {
    const seat = seatPlan.value[rowIndex][colIndex];
    if (!seat.isOccupied || isEditMode.value) return;

    selectedSeat.value = { rowIndex, colIndex };
    statusMenu.value.toggle(event);
};

// Update student status
const updateStudentStatus = (rowIndex, colIndex, status) => {
    const seat = seatPlan.value[rowIndex][colIndex];
    if (!seat.isOccupied) return;

    // If status requires remarks, show dialog
    const statusObj = attendanceStatuses.find((s) => s.name === status);
    if (statusObj && statusObj.requiresRemarks) {
        selectedStudentForRemarks.value = getStudentById(seat.studentId);
        pendingStatus.value = status;
        attendanceRemarks.value = ''; // Clear previous remarks
        showRemarksDialog.value = true;
    } else {
        // Otherwise, update status directly
        seat.status = status;
        saveAttendanceRecord(seat.studentId, status);
    }
};

// Save attendance with remarks
const saveAttendanceWithRemarks = (status, remarks = '') => {
    const { rowIndex, colIndex } = selectedSeat.value;
    const seat = seatPlan.value[rowIndex][colIndex];

    // Update seat status
    seat.status = status;

    // Get the student
    const student = getStudentById(seat.studentId);

    // Save to attendance records
    const recordKey = `${seat.studentId}-${currentDate.value}`;

    if (status === 'Present' || status === 'Late') {
        // Remove from remarks panel if exists
        remarksPanel.value = remarksPanel.value.filter((r) => r.studentId !== seat.studentId);
        // Remove remarks from records
        if (attendanceRecords.value[recordKey]) {
            attendanceRecords.value[recordKey].remarks = '';
        }
    } else if (remarks) {
        // Update or add to remarks panel for Absent/Excused
        const remarkItem = {
            studentId: seat.studentId,
            studentName: student?.name || 'Unknown Student',
            status,
            remarks,
            timestamp: new Date().toISOString()
        };

        const existingIndex = remarksPanel.value.findIndex((r) => r.studentId === seat.studentId);
        if (existingIndex >= 0) {
            remarksPanel.value[existingIndex] = remarkItem;
        } else {
            remarksPanel.value.push(remarkItem);
        }
    }

    // Update attendance records
    attendanceRecords.value[recordKey] = {
        studentId: seat.studentId,
        date: currentDate.value,
        status,
        remarks: remarks || '',
        timestamp: new Date().toISOString()
    };

    // Save to localStorage
    localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));
    localStorage.setItem('remarksPanel', JSON.stringify(remarksPanel.value));

    // Show success message
    toast.add({
        severity: 'success',
        summary: 'Attendance Marked',
        detail: `Student marked as ${status}`,
        life: 3000
    });

    // Close dialogs and reset values
    showAttendanceDialog.value = false;
    showRemarksDialog.value = false;
    selectedSeat.value = null;
    attendanceRemarks.value = '';
    pendingStatus.value = '';
};

// Update the saveAttendanceRecord function to include remarks
const saveAttendanceRecord = async (studentId, status, remarks = '') => {
    try {
        // Implement API call to save attendance record
        console.log('Saving attendance record:', { studentId, status, remarks, date: currentDate.value });

        // Show success message
        toast.add({
            severity: 'success',
            summary: 'Attendance Saved',
            detail: `Marked student ${studentId} as ${status}`,
            life: 3000
        });
    } catch (error) {
        console.error('Error saving attendance record:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save attendance record',
            life: 3000
        });
    }
};

// Save the current seat plan as a template
const saveAsTemplate = () => {
    if (!templateName.value.trim()) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Please enter a template name',
            life: 3000
        });
        return;
    }

    // Check if template name already exists
    const existingIndex = savedTemplates.value.findIndex((t) => t.name === templateName.value);

    const templateData = {
        name: templateName.value,
        rows: rows.value,
        columns: columns.value,
        seatPlan: JSON.parse(JSON.stringify(seatPlan.value)),
        showTeacherDesk: showTeacherDesk.value,
        createdAt: new Date().toISOString()
    };

    if (existingIndex >= 0) {
        // Update existing template
        savedTemplates.value[existingIndex] = templateData;
        toast.add({
            severity: 'success',
            summary: 'Template Updated',
            detail: `Template "${templateName.value}" has been updated`,
            life: 3000
        });
    } else {
        // Add new template
        savedTemplates.value.push(templateData);
        toast.add({
            severity: 'success',
            summary: 'Template Saved',
            detail: `Template "${templateName.value}" has been saved`,
            life: 3000
        });
    }

    // Save to localStorage
    localStorage.setItem('seatPlanTemplates', JSON.stringify(savedTemplates.value));

    // Close dialog and reset form
    showTemplateSaveDialog.value = false;
    templateName.value = '';
};

// Load a template
const loadTemplate = (template) => {
    // Apply template settings
    rows.value = template.rows;
    columns.value = template.columns;
    showTeacherDesk.value = template.showTeacherDesk;

    // Deep copy the seat plan to avoid reference issues
    seatPlan.value = JSON.parse(JSON.stringify(template.seatPlan));

    toast.add({
        severity: 'success',
        summary: 'Template Loaded',
        detail: `Template "${template.name}" has been loaded`,
        life: 3000
    });

    // Close dialog
    showTemplateManager.value = false;
    selectedTemplate.value = null;
};

// Delete a template
const deleteTemplate = (template, event) => {
    // Stop event propagation to prevent selecting the template
    if (event) event.stopPropagation();

    // Remove template from array
    savedTemplates.value = savedTemplates.value.filter((t) => t.name !== template.name);

    // Save updated templates to localStorage
    localStorage.setItem('seatPlanTemplates', JSON.stringify(savedTemplates.value));

    toast.add({
        severity: 'success',
        summary: 'Template Deleted',
        detail: `Template "${template.name}" has been deleted`,
        life: 3000
    });

    // If the deleted template was selected, clear selection
    if (selectedTemplate.value && selectedTemplate.value.name === template.name) {
        selectedTemplate.value = null;
    }
};

// Load saved templates from local storage
const loadSavedTemplates = async () => {
    try {
        const storageKey = `seatPlan_${subjectId.value}`;
        const savedData = localStorage.getItem(storageKey);

        if (savedData) {
            const parsed = JSON.parse(savedData);
            if (Array.isArray(parsed)) {
                savedTemplates.value = parsed;
            } else {
                console.warn('Saved data is not an array, initializing empty array');
                savedTemplates.value = [];
            }
        } else {
            savedTemplates.value = [];
        }
    } catch (error) {
        console.error('Error loading saved templates:', error);
        savedTemplates.value = [];
    }
};

// Calculate unassigned students based on seating assignment
const calculateUnassignedStudents = () => {
    // Get all assigned student IDs
    const assignedIds = new Set();

    for (const row of seatPlan.value) {
        for (const seat of row) {
            if (seat.isOccupied && seat.studentId) {
                assignedIds.add(seat.studentId);
            }
        }
    }

    // Filter students not in assigned set
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
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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

// Update resetAllAttendance function
const resetAllAttendance = () => {
    if (confirm('Are you sure you want to reset all attendance statuses?')) {
        // Reset all seat statuses
        seatPlan.value.forEach((row) => {
            row.forEach((seat) => {
                if (seat.isOccupied) {
                    seat.status = null;
                }
            });
        });

        // Clear attendance records for current date
        const today = currentDate.value;
        Object.keys(attendanceRecords.value).forEach((key) => {
            if (key.includes(today)) {
                delete attendanceRecords.value[key];
            }
        });

        // Clear remarks panel
        remarksPanel.value = [];

        // Update localStorage
        localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));
        localStorage.setItem('remarksPanel', JSON.stringify(remarksPanel.value));

        toast.add({
            severity: 'success',
            summary: 'Attendance Reset',
            detail: 'All attendance statuses and remarks have been cleared',
            life: 3000
        });
    }
};

// Update decrementRows function
const decrementRows = () => {
    if (rows.value > 1) {
        rows.value--;

        // Find the first empty row from the top
        let foundEmptyRow = false;
        for (let i = 0; i < seatPlan.value.length; i++) {
            const rowIsEmpty = seatPlan.value[i].every((seat) => !seat.isOccupied);
            if (rowIsEmpty) {
                // Remove this empty row
                seatPlan.value.splice(i, 1);
                foundEmptyRow = true;
                break;
            }
        }

        // If no empty row was found, remove from bottom
        if (!foundEmptyRow) {
            seatPlan.value.pop();
        }
    }
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

        // Load attendance records from localStorage
        try {
            const savedAttendanceRecords = localStorage.getItem('attendanceRecords');
            if (savedAttendanceRecords) {
                attendanceRecords.value = JSON.parse(savedAttendanceRecords);
            }

            const savedRemarksPanel = localStorage.getItem('remarksPanel');
            if (savedRemarksPanel) {
                remarksPanel.value = JSON.parse(savedRemarksPanel);
            }
        } catch (err) {
            console.warn('Error loading attendance records from localStorage:', err);
        }

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

// Show status selection dialog
const showStatusSelection = (rowIndex, colIndex) => {
    const seat = seatPlan.value[rowIndex][colIndex];
    if (!seat.isOccupied || isEditMode.value) return;

    selectedSeat.value = { rowIndex, colIndex };
    selectedStudent.value = getStudentById(seat.studentId);
    showStatusDialog.value = true;
};

// Update student status from dialog
const selectStatus = (status) => {
    if (!selectedSeat.value) return;

    const { rowIndex, colIndex } = selectedSeat.value;
    const seat = seatPlan.value[rowIndex][colIndex];

    // If status requires remarks, show remarks dialog
    const statusObj = attendanceStatuses.find((s) => s.name === status);
    if (statusObj && statusObj.requiresRemarks) {
        selectedStudentForRemarks.value = selectedStudent.value;
        pendingStatus.value = status;
        attendanceRemarks.value = ''; // Clear previous remarks
        showStatusDialog.value = false;
        showRemarksDialog.value = true;
    } else {
        // Otherwise, update status directly
        seat.status = status;
        saveAttendanceRecord(seat.studentId, status);
        showStatusDialog.value = false;
    }
};

// Function to handle drag start
const dragStudent = (student) => {
    draggedStudent.value = student;
};

// Add function to handle removing a student from a seat
const removeStudentFromSeat = (rowIndex, colIndex) => {
    if (!isEditMode.value) return;

    const seat = seatPlan.value[rowIndex][colIndex];
    if (!seat.isOccupied) return;

    // Get the student before clearing the seat
    const studentId = seat.studentId;
    const student = getStudentById(studentId);

    // Clear the seat
    seat.studentId = null;
    seat.isOccupied = false;
    seat.status = null;

    // Add student back to unassigned list if found
    if (student) {
        // Check if student is already in unassigned list
        const exists = unassignedStudents.value.some((s) => s.id === student.id);
        if (!exists) {
            unassignedStudents.value.push(student);
        }
    }

    toast.add({
        severity: 'info',
        summary: 'Student Unassigned',
        detail: 'Student has been removed from seat',
        life: 3000
    });
};

// Add function to handle drag over unassigned section
const allowDrop = (event) => {
    if (isEditMode.value) {
        event.preventDefault();
    }
};

// Add function to handle drop on unassigned section
const dropOnUnassigned = (event) => {
    if (!isEditMode.value || !draggedStudent.value) return;

    // Find the student's current seat
    let foundSeat = false;

    for (let i = 0; i < seatPlan.value.length; i++) {
        for (let j = 0; j < seatPlan.value[i].length; j++) {
            const seat = seatPlan.value[i][j];
            if (seat.isOccupied && seat.studentId === draggedStudent.value.id) {
                // Clear the seat
                seat.studentId = null;
                seat.isOccupied = false;
                seat.status = null;
                foundSeat = true;
                break;
            }
        }
        if (foundSeat) break;
    }

    // Make sure student is in unassigned list
    const exists = unassignedStudents.value.some((s) => s.id === draggedStudent.value.id);
    if (!exists) {
        unassignedStudents.value.push(draggedStudent.value);
    }

    // Reset dragged student
    draggedStudent.value = null;

    toast.add({
        severity: 'info',
        summary: 'Student Unassigned',
        detail: 'Student has been moved to unassigned list',
        life: 3000
    });
};

// Compute whether to show remarks on side or bottom
const showRemarksOnSide = computed(() => {
    return columns.value <= 11;
});

// QR Code Attendance Methods
const startQRAttendance = () => {
    showAttendanceMethodModal.value = false;
    showQRScanner.value = true;
    isCameraLoading.value = true;

    // Ensure Vue has updated the DOM before initializing the camera
    nextTick(() => {
        if (videoElement.value) {
            initializeCamera();
        } else {
            console.error('Video element not found in DOM.');
        }
    });
};

const initializeCamera = async () => {
    if (!codeReader) {
        codeReader = new BrowserMultiFormatReader();
    }

    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter((device) => device.kind === 'videoinput');

        if (videoDevices.length > 0) {
            const selectedDeviceId = videoDevices[0].deviceId;

            // Ensure videoElement is available before accessing it
            if (!videoElement.value) {
                console.error('Error: videoElement is not available.');
                return;
            }

            await codeReader.decodeFromVideoDevice(selectedDeviceId, videoElement.value, (result, err) => {
                if (result) {
                    processScannedData(result.text);
                } else if (err) {
                    console.warn('QR Code scan error:', err);
                }
            });
        } else {
            console.error('No camera found');
        }
    } catch (error) {
        console.error('Error accessing camera:', error);
    } finally {
        isCameraLoading.value = false;
    }
};

const processScannedData = (scannedText) => {
    try {
        // Try to parse the scanned data as JSON
        const studentData = JSON.parse(scannedText);

        // Check if it has the expected properties
        if (studentData.id && studentData.name) {
            // Find the student in our data
            const student = students.value.find((s) => s.id === studentData.id);

            if (student) {
                // Check if already scanned
                if (!scannedStudents.value.some((s) => s.id === student.id)) {
                    // Add to scanned students
                    scannedStudents.value.push(student);

                    // Mark as present in the seat plan
                    markStudentPresent(student.id);

                    toast.add({
                        severity: 'success',
                        summary: 'Student Scanned',
                        detail: `${student.name} marked as present`,
                        life: 3000
                    });
                } else {
                    toast.add({
                        severity: 'info',
                        summary: 'Already Scanned',
                        detail: `${student.name} was already scanned`,
                        life: 3000
                    });
                }
            } else {
                toast.add({
                    severity: 'warn',
                    summary: 'Unknown Student',
                    detail: 'Student not found in class roster',
                    life: 3000
                });
            }
        } else {
            throw new Error('Invalid student data format');
        }
    } catch (error) {
        console.error('Error processing QR data:', error);
        toast.add({
            severity: 'error',
            summary: 'Invalid QR Code',
            detail: 'The scanned QR code is not valid for attendance',
            life: 3000
        });
    }
};

const markStudentPresent = (studentId) => {
    // Find the student in the seat plan
    for (let i = 0; i < seatPlan.value.length; i++) {
        for (let j = 0; j < seatPlan.value[i].length; j++) {
            const seat = seatPlan.value[i][j];
            if (seat.isOccupied && seat.studentId === studentId) {
                seat.status = 'Present';
                saveAttendanceRecord(studentId, 'Present');
                return;
            }
        }
    }
};

const closeScanner = () => {
    if (codeReader) {
        try {
            codeReader.reset();
        } catch (error) {
            console.error('Error stopping scanner:', error);
        }
    }

    // Stop the camera stream safely
    if (videoElement.value && videoElement.value.srcObject) {
        const stream = videoElement.value.srcObject;
        const tracks = stream.getTracks();
        tracks.forEach((track) => track.stop());
        videoElement.value.srcObject = null;
    }

    showQRScanner.value = false;
};

// Roll Call Methods
const startRollCall = () => {
    showAttendanceMethodModal.value = false;

    // Initialize roll call with the first student
    if (students.value.length > 0) {
        currentStudentIndex.value = 0;
        currentStudent.value = students.value[0];
        showRollCall.value = true;
    } else {
        toast.add({
            severity: 'error',
            summary: 'No Students',
            detail: 'There are no students to call',
            life: 3000
        });
    }
};

const markAttendance = (status) => {
    if (!currentStudent.value) return;

    // Mark the student in the seat plan
    for (let i = 0; i < seatPlan.value.length; i++) {
        for (let j = 0; j < seatPlan.value[i].length; j++) {
            const seat = seatPlan.value[i][j];
            if (seat.isOccupied && seat.studentId === currentStudent.value.id) {
                seat.status = status;

                // If status requires remarks, show dialog
                if (status === 'Absent' || status === 'Excused') {
                    selectedStudentForRemarks.value = currentStudent.value;
                    pendingStatus.value = status;
                    attendanceRemarks.value = '';
                    showRemarksDialog.value = true;
                    return;
                } else {
                    // Otherwise save directly
                    saveAttendanceRecord(currentStudent.value.id, status);
                }
                break;
            }
        }
    }

    // Move to next student
    moveToNextStudent();
};

const moveToNextStudent = () => {
    currentStudentIndex.value++;
    if (currentStudentIndex.value < students.value.length) {
        currentStudent.value = students.value[currentStudentIndex.value];
    } else {
        // End of roll call
        showRollCall.value = false;
        toast.add({
            severity: 'success',
            summary: 'Roll Call Complete',
            detail: 'All students have been called',
            life: 3000
        });
    }
};

// Start seat plan attendance directly
const startSeatPlanAttendance = () => {
    showAttendanceMethodModal.value = false;
    // No additional setup needed as the seat plan is already visible
    toast.add({
        severity: 'info',
        summary: 'Seat Plan Attendance',
        detail: 'Click on students to mark attendance',
        life: 3000
    });
};

// Clean up on component unmount
onUnmounted(() => {
    if (codeReader) {
        codeReader.reset();
        codeReader = null;
    }
});

// Function to choose seat plan directly
const chooseSeatPlan = () => {
    showAttendanceMethodModal.value = false;
};

// Add a function to show the attendance method modal
const showAttendanceMethodSelector = () => {
    // Force the dialog to show
    showAttendanceMethodModal.value = true;

    // Log to console for debugging
    console.log('Showing attendance method modal:', showAttendanceMethodModal.value);
};

// Update incrementRows function
const incrementRows = () => {
    if (rows.value < 12) {
        rows.value++;

        // Add new row at the top
        const newRow = Array(columns.value)
            .fill()
            .map(() => ({
                isOccupied: false,
                studentId: null,
                status: null
            }));

        seatPlan.value.unshift(newRow);
    }
};

// Remove or comment out updateGridSize calls in both functions as we're handling the grid directly

// Add computed property for sorted unassigned students
const sortedUnassignedStudents = computed(() => {
    // First filter by search query
    const filtered = filteredUnassignedStudents.value;

    // Then sort alphabetically by name
    return [...filtered].sort((a, b) => {
        return a.name.localeCompare(b.name);
    });
});

// Function to show attendance dialog when clicking a seat
const handleSeatClick = (rowIndex, colIndex) => {
    if (!isEditMode.value && seatPlan.value[rowIndex][colIndex].isOccupied) {
        selectedSeat.value = { rowIndex, colIndex };
        showAttendanceDialog.value = true;
    }
};

// Function to set attendance status
const setAttendanceStatus = (status) => {
    if (!selectedSeat.value) return;

    // If status is Absent or Excused, show remarks dialog
    if (status === 'Absent' || status === 'Excused') {
        pendingStatus.value = status;
        showAttendanceDialog.value = false;
        showRemarksDialog.value = true;
        return;
    }

    saveAttendanceWithRemarks(status);
};

// Add new function to save attendance with remarks
const saveRemarks = () => {
    if (!attendanceRemarks.value.trim()) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Please enter remarks',
            life: 3000
        });
        return;
    }

    saveAttendanceWithRemarks(pendingStatus.value, attendanceRemarks.value);
};

// Add function to update remarks panel
const updateRemarksPanel = (studentId, status, remarks) => {
    const student = getStudentById(studentId);
    if (!student) return;

    // Add or update remarks
    const existingIndex = remarksPanel.value.findIndex((r) => r.studentId === studentId);
    const remarkItem = {
        studentId,
        studentName: student.name,
        status,
        remarks,
        timestamp: new Date().toISOString()
    };

    if (existingIndex >= 0) {
        remarksPanel.value[existingIndex] = remarkItem;
    } else {
        remarksPanel.value.push(remarkItem);
    }
};
</script>

<template>
    <div class="attendance-container p-4">
        <!-- Header with subject name and date -->
        <div class="flex justify-between items-center mb-4">
            <h5 class="text-xl font-semibold">{{ subjectName }} Attendance</h5>
            <div class="flex gap-2 align-items-center">
                <Calendar v-model="currentDate" dateFormat="yy-mm-dd" class="mr-2" />
                <Button label="Take Attendance" icon="pi pi-plus" class="p-button-success" @click="showAttendanceMethodSelector" />
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons flex flex-wrap gap-2 mb-4">
            <Button icon="pi pi-pencil" label="Edit Seats" class="p-button-success" :class="{ 'p-button-outlined': !isEditMode }" @click="toggleEditMode" />

            <Button icon="pi pi-save" label="Save as Template" class="p-button-outlined" @click="showTemplateSaveDialog = true" />

            <Button icon="pi pi-list" label="Load Template" class="p-button-outlined" @click="showTemplateManager = true" />

            <Button icon="pi pi-check-circle" label="Mark All Present" class="p-button-success" @click="markAllPresent" />

            <Button icon="pi pi-refresh" label="Reset Attendance" class="p-button-outlined" @click="resetAllAttendance" />
        </div>

        <!-- Main content with seat plan - always visible -->
        <div :class="{ 'edit-layout': isEditMode }">
            <!-- Left side: Layout config and seat plan -->
            <div class="main-content">
                <!-- Layout Configuration - only visible in edit mode -->
                <div v-if="isEditMode" class="layout-config mb-4 p-3 border rounded-lg bg-gray-50">
                    <h3 class="text-lg font-medium mb-2">Layout Configuration</h3>

                    <div class="flex flex-wrap gap-3 items-center">
                        <!-- Rows and Columns in one line -->
                        <div class="flex items-center gap-3">
                            <div class="flex items-center">
                                <label for="rows" class="mr-1 whitespace-nowrap">Rows:</label>
                                <div class="p-inputgroup">
                                    <Button icon="pi pi-minus" @click="decrementRows" :disabled="rows <= 1" class="p-button-secondary p-button-sm" />
                                    <InputNumber id="rows" v-model="rows" :min="1" :max="10" @change="updateGridSize" class="w-20" showButtons="false" />
                                    <Button icon="pi pi-plus" @click="incrementRows" :disabled="rows >= 10" class="p-button-secondary p-button-sm" />
                                </div>
                            </div>

                            <div class="flex items-center">
                                <label for="columns" class="mr-1 whitespace-nowrap">Columns:</label>
                                <div class="p-inputgroup">
                                    <Button icon="pi pi-minus" @click="decrementColumns" :disabled="columns <= 1" class="p-button-secondary p-button-sm" />
                                    <InputNumber id="columns" v-model="columns" :min="1" :max="10" @change="updateGridSize" class="w-20" showButtons="false" />
                                    <Button icon="pi pi-plus" @click="incrementColumns" :disabled="columns >= 10" class="p-button-secondary p-button-sm" />
                                </div>
                            </div>
                        </div>

                        <!-- Checkboxes in one line -->
                        <div class="flex items-center gap-3">
                            <div class="flex align-items-center">
                                <Checkbox v-model="showTeacherDesk" :binary="true" inputId="teacherDesk" />
                                <label for="teacherDesk" class="ml-1">Teacher's Desk</label>
                            </div>

                            <div class="flex align-items-center">
                                <Checkbox v-model="showStudentIds" :binary="true" inputId="studentIds" />
                                <label for="studentIds" class="ml-1">Student IDs</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seating grid -->
                <div class="seating-grid-container">
                    <!-- Teacher's desk at top (removed) -->

                    <div class="seating-grid">
                        <div v-for="(row, rowIndex) in seatPlan" :key="`row-${rowIndex}`" class="seat-row flex">
                            <div v-for="(seat, colIndex) in row" :key="`seat-${rowIndex}-${colIndex}`" class="seat-container p-1" @click="!isEditMode ? handleSeatClick(rowIndex, colIndex) : null">
                                <div
                                    :class="[
                                        'seat p-3 border rounded-lg',
                                        { 'cursor-pointer': !isEditMode || seat.isOccupied },
                                        { 'drop-target': isDropTarget && isDropTarget(rowIndex, colIndex) },
                                        { 'student-present': seat.isOccupied && seat.status === 'Present' },
                                        { 'student-absent': seat.isOccupied && seat.status === 'Absent' },
                                        { 'student-late': seat.isOccupied && seat.status === 'Late' },
                                        { 'student-excused': seat.isOccupied && seat.status === 'Excused' },
                                        { 'student-occupied': seat.isOccupied },
                                        { removable: isEditMode && seat.isOccupied }
                                    ]"
                                    @click="isEditMode ? (seat.isOccupied ? removeStudentFromSeat(rowIndex, colIndex) : null) : showStatusSelection(rowIndex, colIndex)"
                                    @dragover="allowDrop($event)"
                                    @drop="dropOnSeat(rowIndex, colIndex)"
                                >
                                    <div v-if="seat.isOccupied" class="student-info">
                                        <div class="student-initials bg-blue-500 text-white">
                                            {{ getStudentInitials(getStudentById(seat.studentId)) }}
                                        </div>
                                        <div class="student-name">{{ getStudentById(seat.studentId)?.name }}</div>
                                        <div v-if="showStudentIds" class="student-id text-xs text-gray-600">ID: {{ seat.studentId }}</div>
                                    </div>
                                    <div v-else-if="isEditMode" class="empty-seat">
                                        <i class="pi pi-plus text-gray-400"></i>
                                        <div class="text-gray-400 text-xs mt-1">Empty</div>
                                    </div>
                                    <div v-else class="empty-seat">
                                        <div class="text-gray-400">Empty</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Teacher's desk at bottom -->
                    <div v-if="showTeacherDesk" class="teacher-desk mt-8">
                        <div class="teacher-desk-label p-3 bg-blue-50 border border-blue-200 rounded-lg text-center"><i class="pi pi-user mr-2"></i> Teacher's Desk</div>
                    </div>
                </div>
            </div>

            <!-- Right side: Unassigned students panel - only visible in edit mode -->
            <div v-if="isEditMode" class="side-panel">
                <div class="unassigned-panel p-3 border rounded-lg bg-white h-full">
                    <h3 class="text-lg font-medium mb-3">Unassigned Students</h3>

                    <div class="mb-3">
                        <span class="p-input-icon-left w-full">
                            <i class="pi pi-search" />
                            <InputText v-model="searchQuery" placeholder="Search students..." class="w-full" />
                        </span>
                    </div>

                    <div v-if="filteredUnassignedStudents.length === 0" class="text-center py-4 text-gray-500">
                        <p v-if="unassignedStudents.length === 0">All students have been assigned to seats.</p>
                        <p v-else>No students match your search.</p>
                    </div>

                    <div v-else class="unassigned-students-list">
                        <div v-for="student in sortedUnassignedStudents" :key="student.id" class="student-card p-3 mb-2 bg-blue-50 rounded-lg border border-blue-200 shadow-sm" draggable="true" @dragstart="dragStudent(student)">
                            <div class="student-info">
                                <div class="student-initials bg-blue-500 text-white">
                                    {{ getStudentInitials(student) }}
                                </div>
                                <div class="student-name">{{ student.name }}</div>
                                <div v-if="showStudentIds" class="student-id text-xs text-gray-600">ID: {{ student.id }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Template Save Dialog -->
        <Dialog v-model:visible="showTemplateSaveDialog" header="Save as Template" :modal="true" :style="{ width: '450px' }" :closeOnEscape="true" :dismissableMask="true">
            <div class="p-fluid">
                <div class="field">
                    <label for="templateName">Template Name</label>
                    <InputText id="templateName" v-model="templateName" placeholder="Enter a name for this template" autofocus />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showTemplateSaveDialog = false" />
                <Button label="Save" icon="pi pi-save" class="p-button-success" @click="saveAsTemplate" />
            </template>
        </Dialog>

        <!-- Template Manager Dialog -->
        <Dialog v-model:visible="showTemplateManager" header="Load Template" :modal="true" :style="{ width: '600px' }" :closeOnEscape="true" :dismissableMask="true">
            <div v-if="savedTemplates.length === 0" class="text-center p-4 text-gray-500">
                <i class="pi pi-folder-open text-4xl mb-3"></i>
                <p>No templates saved yet. Create a seat plan and save it as a template.</p>
            </div>

            <div v-else class="template-list">
                <div
                    v-for="template in savedTemplates"
                    :key="template.name"
                    :class="['template-item p-3 mb-2 border rounded-lg cursor-pointer', { 'bg-blue-50 border-blue-300': selectedTemplate && selectedTemplate.name === template.name }]"
                    @click="selectedTemplate = template"
                >
                    <div class="flex justify-between items-center">
                        <div>
                            <h4 class="m-0 font-medium">{{ template.name }}</h4>
                            <div class="text-sm text-gray-600 mt-1">
                                <span>{{ template.rows }}×{{ template.columns }} grid</span>
                                <span class="mx-2">•</span>
                                <span>{{ formatDate(template.createdAt) }}</span>
                            </div>
                        </div>
                        <Button icon="pi pi-trash" class="p-button-text p-button-danger p-button-sm" @click="deleteTemplate(template, $event)" />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showTemplateManager = false" />
                <Button label="Load" icon="pi pi-check" class="p-button-success" :disabled="!selectedTemplate" @click="loadTemplate(selectedTemplate)" />
            </template>
        </Dialog>

        <!-- Add the Attendance Dialog -->
        <Dialog v-model:visible="showAttendanceDialog" header="Mark Attendance" :modal="true" :style="{ width: '400px' }" :closeOnEscape="true" :dismissableMask="true">
            <div class="grid grid-cols-2 gap-4 p-4">
                <Button class="attendance-btn present-btn p-button-outlined" @click="setAttendanceStatus('Present')">
                    <i class="pi pi-check-circle text-3xl mb-2"></i>
                    <span class="font-semibold">Present</span>
                </Button>

                <Button class="attendance-btn late-btn p-button-outlined" @click="setAttendanceStatus('Late')">
                    <i class="pi pi-clock text-3xl mb-2"></i>
                    <span class="font-semibold">Late</span>
                </Button>

                <Button class="attendance-btn absent-btn p-button-outlined" @click="setAttendanceStatus('Absent')">
                    <i class="pi pi-times-circle text-3xl mb-2"></i>
                    <span class="font-semibold">Absent</span>
                </Button>

                <Button class="attendance-btn excused-btn p-button-outlined" @click="setAttendanceStatus('Excused')">
                    <i class="pi pi-info-circle text-3xl mb-2"></i>
                    <span class="font-semibold">Excused</span>
                </Button>
            </div>
        </Dialog>

        <!-- Add Remarks Dialog -->
        <Dialog v-model:visible="showRemarksDialog" header="Enter Remarks" :modal="true" :style="{ width: '400px' }" :closeOnEscape="true" :dismissableMask="true">
            <div class="p-fluid">
                <div class="field">
                    <label for="remarks">Remarks</label>
                    <Textarea id="remarks" v-model="attendanceRemarks" rows="3" placeholder="Enter reason for absence/excuse" class="w-full" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showRemarksDialog = false" />
                <Button label="Save" icon="pi pi-check" class="p-button-success" @click="saveRemarks" />
            </template>
        </Dialog>

        <!-- Add Remarks Panel -->
        <div v-if="remarksPanel.length > 0" class="remarks-section">
            <div class="remarks-container">
                <h3 class="text-lg font-semibold mb-3">Attendance Remarks</h3>
                <div class="remarks-list">
                    <div
                        v-for="remark in remarksPanel"
                        :key="remark.studentId"
                        class="remark-card p-3 rounded-lg border mb-2"
                        :class="{
                            'border-red-500 bg-red-50': remark.status === 'Absent',
                            'border-purple-500 bg-purple-50': remark.status === 'Excused'
                        }"
                    >
                        <div class="font-medium">{{ remark.studentName }}</div>
                        <div
                            class="text-sm"
                            :class="{
                                'text-red-600': remark.status === 'Absent',
                                'text-purple-600': remark.status === 'Excused'
                            }"
                        >
                            Status: {{ remark.status }}
                        </div>
                        <div class="text-sm text-gray-600 mt-1">{{ remark.remarks }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Add these styles for the side-by-side layout */
.edit-layout {
    display: flex;
    gap: 1rem;
    width: 100%;
}

.main-content {
    flex: 3;
    min-width: 0; /* Prevent flex item from overflowing */
}

.side-panel {
    flex: 1;
    min-width: 250px;
    max-width: 350px;
}

.unassigned-panel {
    position: sticky;
    top: 1rem;
    height: calc(100vh - 2rem);
    display: flex;
    flex-direction: column;
}

.unassigned-students-list {
    overflow-y: auto;
    flex: 1;
}

/* Seat grid styles */
.seating-grid-container {
    width: 100%;
    overflow-x: auto;
}

.seating-grid {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.seat-row {
    display: flex;
    gap: 0.5rem;
}

.seat-container {
    flex: 1;
    min-width: 100px;
}

.seat {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    transition: all 0.2s ease;
}

.student-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
}

.student-initials {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.5rem;
    font-weight: bold;
}

.empty-seat {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    width: 100%;
}

.student-card {
    cursor: grab;
    transition: all 0.2s;
}

.student-card:active {
    cursor: grabbing;
}

.student-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Status colors - only affect the background, not the initials */
.student-present {
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.student-absent {
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.student-late {
    background-color: #fff3cd;
    border-color: #ffeeba;
}

.student-excused {
    background-color: #d1ecf1;
    border-color: #bee5eb;
}

.student-occupied {
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

/* Hover effects for removable seats */
.removable:hover {
    background-color: #ffebee;
    border-color: #f44336;
}

.removable:hover::after {
    content: 'Click to remove';
    position: absolute;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
    background-color: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 10;
}

/* Responsive adjustments */
@media (max-width: 991px) {
    .edit-layout {
        flex-direction: column;
    }

    .side-panel {
        max-width: none;
    }

    .unassigned-panel {
        position: static;
        height: auto;
        max-height: 400px;
    }
}

/* Add these styles for the template manager */
.template-list {
    max-height: 400px;
    overflow-y: auto;
}

.template-item {
    transition: all 0.2s;
}

.template-item:hover {
    background-color: #f8f9fa;
    border-color: #dee2e6;
}

.attendance-btn {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 1.5rem !important;
    border-radius: 8px !important;
    transition: all 0.2s !important;
    height: 120px !important;
    border: 2px solid !important;
    background-color: white !important;
}

.present-btn {
    color: #22c55e !important;
    border-color: #22c55e !important;
}
.present-btn:hover {
    background-color: #22c55e !important;
    color: white !important;
}

.late-btn {
    color: #eab308 !important;
    border-color: #eab308 !important;
}
.late-btn:hover {
    background-color: #eab308 !important;
    color: white !important;
}

.absent-btn {
    color: #ef4444 !important;
    border-color: #ef4444 !important;
}
.absent-btn:hover {
    background-color: #ef4444 !important;
    color: white !important;
}

.excused-btn {
    color: #9333ea !important;
    border-color: #9333ea !important;
}
.excused-btn:hover {
    background-color: #9333ea !important;
    color: white !important;
}

/* Force icon colors */
.present-btn i {
    color: #22c55e !important;
}
.late-btn i {
    color: #eab308 !important;
}
.absent-btn i {
    color: #ef4444 !important;
}
.excused-btn i {
    color: #9333ea !important;
}

/* Change icon colors on hover */
.attendance-btn:hover i {
    color: white !important;
}

/* Add to your existing styles */
.remarks-section {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
}

.remarks-container {
    width: 100%;
    max-width: 600px;
    background-color: white;
    border-radius: 0.5rem;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
}

.remarks-list {
    max-height: 300px;
    overflow-y: auto;
}

.remark-card {
    transition: all 0.2s ease;
}

.remark-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}
</style>
