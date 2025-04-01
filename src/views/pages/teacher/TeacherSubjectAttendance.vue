<script setup>
import { AttendanceService } from '@/router/service/Students';
import { SubjectService } from '@/router/service/Subjects';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

// Add OverlayPanel and Menu components

// Add Dialog component if not already imported
import Dialog from 'primevue/dialog';

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

// Status menu and panel refs
const statusMenu = ref(null);
const remarksPanel = ref(null);
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
const saveAttendanceWithRemarks = () => {
    if (!selectedStudentForRemarks.value || !selectedSeat.value) return;

    const { rowIndex, colIndex } = selectedSeat.value;
    const seat = seatPlan.value[rowIndex][colIndex];

    // Update the status
    seat.status = pendingStatus.value;

    // Save the attendance record with remarks
    saveAttendanceRecord(selectedStudentForRemarks.value.id, pendingStatus.value, attendanceRemarks.value);

    // Add to students with remarks
    const existingIndex = studentsWithRemarks.value.findIndex((s) => s.id === selectedStudentForRemarks.value.id);

    const remarkData = {
        id: selectedStudentForRemarks.value.id,
        name: selectedStudentForRemarks.value.name,
        status: pendingStatus.value,
        remarks: attendanceRemarks.value,
        timestamp: new Date().toISOString()
    };

    if (existingIndex >= 0) {
        studentsWithRemarks.value[existingIndex] = remarkData;
    } else {
        studentsWithRemarks.value.push(remarkData);
    }

    // Close the dialog
    showRemarksDialog.value = false;
    selectedStudentForRemarks.value = null;
    pendingStatus.value = null;
};

// Update the saveAttendanceRecord function to include remarks
const saveAttendanceRecord = async (studentId, status, remarks = '') => {
    try {
        const student = getStudentById(studentId);
        if (!student) {
            console.error('Student not found:', studentId);
            return;
        }

        // Create attendance record object
        const attendanceRecord = {
            id: Date.now().toString(),
            studentId: studentId,
            subjectId: subjectId.value,
            date: currentDate.value,
            status: status,
            remarks: remarks, // Add remarks to the record
            createdAt: new Date().toISOString()
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

// Reset all attendance statuses
const resetAttendance = () => {
    // Confirm before resetting
    if (confirm('Are you sure you want to reset all attendance statuses?')) {
        // Reset all seat statuses
        for (let i = 0; i < seatPlan.value.length; i++) {
            for (let j = 0; j < seatPlan.value[i].length; j++) {
                if (seatPlan.value[i][j].isOccupied) {
                    seatPlan.value[i][j].status = null;
                }
            }
        }

        // Clear all remarks
        studentsWithRemarks.value = [];

        toast.add({
            severity: 'success',
            summary: 'Attendance Reset',
            detail: 'All attendance statuses and remarks have been cleared',
            life: 3000
        });
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
            <Button icon="pi pi-pencil" label="Edit Seats" class="p-button-success" :class="{ 'p-button-outlined': !isEditMode }" @click="toggleEditMode" />

            <Button icon="pi pi-save" label="Save as Template" class="p-button-outlined" @click="showTemplateSaveDialog = true" />

            <Button icon="pi pi-list" label="Load Template" class="p-button-outlined" @click="showTemplateManager = true" />

            <!-- Attendance Actions -->
            <Button icon="pi pi-check-circle" label="Mark All Present" class="p-button-success" @click="markAllPresent" />

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
                        <InputNumber id="rowsInput" v-model="rows" :min="1" :max="15" class="w-full" :disabled="!isEditMode" showButtons inputClass="w-full" />
                    </div>
                </div>

                <div class="config-group">
                    <div class="flex flex-col">
                        <label for="columnsInput" class="mb-2 font-medium text-sm">Columns:</label>
                        <InputNumber id="columnsInput" v-model="columns" :min="1" :max="15" class="w-full" :disabled="!isEditMode" showButtons inputClass="w-full" />
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

        <!-- Main content with grid and remarks panel -->
        <div class="flex flex-col">
            <div class="flex flex-col md:flex-row">
                <!-- Left side: Seating grid -->
                <div class="flex-grow">
                    <!-- Seating grid layout -->
                    <div class="seating-grid-container mt-4">
                        <!-- Seating grid -->
                        <div class="seating-grid">
                            <div v-for="(row, rowIndex) in seatPlan" :key="rowIndex" class="seat-row flex justify-center mb-3">
                                <div
                                    v-for="(seat, colIndex) in row"
                                    :key="`${rowIndex}-${colIndex}`"
                                    class="seat-container mx-2"
                                    @click="!isEditMode && seat.isOccupied ? showStatusSelection(rowIndex, colIndex) : isEditMode && seat.isOccupied ? removeStudentFromSeat(rowIndex, colIndex) : null"
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
                                            'student-excused': seat.status === 'Excused',
                                            removable: isEditMode && seat.isOccupied
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
                                            <div v-if="isEditMode" class="remove-hint text-xs text-gray-500 mt-1"><i class="pi pi-times-circle"></i> Click to remove</div>
                                        </div>
                                        <div v-else-if="isEditMode" class="empty-seat">
                                            <span>Empty</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Teacher's desk at the bottom -->
                        <div v-if="showTeacherDesk" class="teacher-desk mt-6">
                            <div class="teacher-desk-label bg-blue-50 p-3 text-center rounded-lg border border-blue-200 shadow-sm font-medium">
                                <i class="pi pi-user mr-2"></i>
                                Teacher's Desk
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right side: Remarks panel (only shown if columns <= 11) -->
                <div v-if="!isEditMode && studentsWithRemarks.length > 0 && showRemarksOnSide" class="remarks-sidebar w-80 ml-4 bg-gray-50 p-4 rounded-lg">
                    <h3 class="text-lg font-semibold mb-3">Student Remarks</h3>

                    <div v-for="student in studentsWithRemarks" :key="student.id" class="student-remark mb-3 bg-white p-3 rounded-lg shadow-sm">
                        <div class="flex items-center mb-2">
                            <div class="status-indicator w-3 h-3 rounded-full mr-2" :style="{ backgroundColor: getStatusColor(student.status) }"></div>
                            <span class="font-medium">{{ student.name }}</span>
                        </div>
                        <div class="status-badge text-xs mb-1" :class="student.status.toLowerCase()">
                            {{ student.status }}
                        </div>
                        <p class="text-sm text-gray-600">{{ student.remarks }}</p>
                    </div>
                </div>
            </div>

            <!-- Bottom remarks panel (only shown if columns > 11) -->
            <div v-if="!isEditMode && studentsWithRemarks.length > 0 && !showRemarksOnSide" class="remarks-bottom mt-6 bg-gray-50 p-4 rounded-lg">
                <h3 class="text-lg font-semibold mb-3">Student Remarks</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <div v-for="student in studentsWithRemarks" :key="student.id" class="student-remark bg-white p-3 rounded-lg shadow-sm">
                        <div class="flex items-center mb-2">
                            <div class="status-indicator w-3 h-3 rounded-full mr-2" :style="{ backgroundColor: getStatusColor(student.status) }"></div>
                            <span class="font-medium">{{ student.name }}</span>
                        </div>
                        <div class="status-badge text-xs mb-1" :class="student.status.toLowerCase()">
                            {{ student.status }}
                        </div>
                        <p class="text-sm text-gray-600">{{ student.remarks }}</p>
                    </div>
                </div>
            </div>

            <!-- Unassigned students section (only visible in edit mode) -->
            <div v-if="isEditMode" class="unassigned-section mt-8 bg-white p-4 rounded-lg shadow-sm" @dragover="allowDrop" @drop="dropOnUnassigned">
                <h3 class="text-lg font-medium mb-3">Unassigned Students</h3>

                <div class="mb-4">
                    <span class="p-input-icon-left w-full">
                        <i class="pi pi-search" />
                        <InputText v-model="searchQuery" placeholder="Search students..." class="w-full" />
                    </span>
                </div>

                <div v-if="filteredUnassignedStudents.length === 0" class="text-center py-4 text-gray-500">
                    <p v-if="unassignedStudents.length === 0">All students have been assigned to seats.</p>
                    <p v-else>No students match your search.</p>
                </div>

                <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <div v-for="student in filteredUnassignedStudents" :key="student.id" class="student-card p-3 bg-blue-50 rounded-lg border border-blue-200 shadow-sm" draggable="true" @dragstart="dragStudent(student)">
                        <div class="student-info">
                            <div class="student-initials bg-blue-500 text-white">
                                {{ getStudentInitials(student) }}
                            </div>
                            <div class="student-name">{{ student.name }}</div>
                            <div v-if="showStudentIds" class="student-id">ID: {{ student.id }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Selection Dialog -->
        <Dialog v-model:visible="showStatusDialog" header="Select Attendance Status" :modal="true" :closable="true" :dismissableMask="true" class="status-dialog" style="width: 90vw; max-width: 500px">
            <div v-if="selectedStudent" class="p-fluid">
                <div class="text-center mb-4">
                    <h3 class="text-xl font-bold mb-1">{{ selectedStudent.name }}</h3>
                    <p class="text-gray-600">Select attendance status</p>
                </div>

                <div class="status-buttons grid grid-cols-2 gap-4">
                    <button class="status-button present-button p-4 rounded-lg flex flex-col items-center justify-center" @click="selectStatus('Present')">
                        <i class="pi pi-check-circle text-3xl mb-2"></i>
                        <span class="text-lg font-medium">Present</span>
                    </button>

                    <button class="status-button absent-button p-4 rounded-lg flex flex-col items-center justify-center" @click="selectStatus('Absent')">
                        <i class="pi pi-times-circle text-3xl mb-2"></i>
                        <span class="text-lg font-medium">Absent</span>
                    </button>

                    <button class="status-button late-button p-4 rounded-lg flex flex-col items-center justify-center" @click="selectStatus('Late')">
                        <i class="pi pi-clock text-3xl mb-2"></i>
                        <span class="text-lg font-medium">Late</span>
                    </button>

                    <button class="status-button excused-button p-4 rounded-lg flex flex-col items-center justify-center" @click="selectStatus('Excused')">
                        <i class="pi pi-info-circle text-3xl mb-2"></i>
                        <span class="text-lg font-medium">Excused</span>
                    </button>
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showStatusDialog = false" class="p-button-text" />
            </template>
        </Dialog>

        <!-- Remarks dialog -->
        <Dialog v-model:visible="showRemarksDialog" :header="`Add Remarks - ${pendingStatus}`" :modal="true" class="remarks-dialog" style="width: 90vw; max-width: 500px">
            <div v-if="selectedStudentForRemarks" class="p-fluid">
                <div class="field mb-4">
                    <h4 class="font-medium mb-2">{{ selectedStudentForRemarks.name }}</h4>
                    <div class="status-badge mb-3" :class="pendingStatus?.toLowerCase()">
                        <i :class="getStatusIcon(pendingStatus)"></i>
                        <span>{{ pendingStatus }}</span>
                    </div>
                </div>

                <div class="field">
                    <label for="remarks">Remarks</label>
                    <Textarea id="remarks" v-model="attendanceRemarks" rows="3" placeholder="Enter reason or additional information..." autoResize />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" @click="showRemarksDialog = false" class="p-button-text" />
                <Button label="Save" icon="pi pi-save" @click="saveAttendanceWithRemarks" class="p-button-success" autofocus />
            </template>
        </Dialog>

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
    </div>
</template>

<style>
.teacher-route {
    --seat-width: 90px;
    --seat-height: 80px;
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
    width: 30px;
    height: 30px;
    background-color: #3f51b5;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 0.4rem;
    font-size: 0.8rem;
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
    font-size: 0.8rem;
    max-width: 85px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.student-id {
    font-size: 0.7rem;
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

/* Dialog styling */
:deep(.p-dialog-content) {
    padding: 1.5rem;
}

/* Styles for the remarks dialog */
.remarks-dialog :deep(.p-dialog-content) {
    padding: 1.5rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 2rem;
    font-weight: 500;
    font-size: 0.875rem;
}

.status-badge.late {
    background-color: rgba(255, 152, 0, 0.1);
    color: #ff9800;
}

.status-badge.excused {
    background-color: rgba(33, 150, 243, 0.1);
    color: #2196f3;
}

.status-badge i {
    margin-right: 0.5rem;
}

/* Status menu styling */
:deep(.p-menu) {
    min-width: 12rem;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

:deep(.p-menu .p-menuitem-link) {
    padding: 0.75rem 1rem;
}

:deep(.p-menu .p-menuitem-icon) {
    margin-right: 0.75rem;
}

/* Remarks sidebar styling */
.remarks-sidebar {
    border-left: 1px solid #e5e7eb;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
}

.student-remark {
    border-left: 3px solid #e0e0e0;
}

.student-remark .status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 1rem;
    font-weight: 500;
}

.student-remark .status-badge.absent {
    background-color: rgba(244, 67, 54, 0.1);
    color: #f44336;
}

.student-remark .status-badge.excused {
    background-color: rgba(33, 150, 243, 0.1);
    color: #2196f3;
}

/* Status dialog styling */
.status-dialog :deep(.p-dialog-content) {
    padding: 1.5rem;
}

.status-button {
    border: 2px solid;
    transition: all 0.2s;
    min-height: 120px;
}

.status-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.present-button {
    background-color: rgba(76, 175, 80, 0.1);
    border-color: #4caf50;
    color: #4caf50;
}

.absent-button {
    background-color: rgba(244, 67, 54, 0.1);
    border-color: #f44336;
    color: #f44336;
}

.late-button {
    background-color: rgba(255, 152, 0, 0.1);
    border-color: #ff9800;
    color: #ff9800;
}

.excused-button {
    background-color: rgba(33, 150, 243, 0.1);
    border-color: #2196f3;
    color: #2196f3;
}

/* Unassigned students section styling */
.unassigned-section {
    border-top: 1px solid #e5e7eb;
    padding-top: 1.5rem;
}

.student-card {
    cursor: grab;
    transition: all 0.2s;
}

.student-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Styles for removable seats */
.removable {
    cursor: pointer;
    position: relative;
}

.removable:hover {
    border-color: #f44336 !important;
    background-color: rgba(244, 67, 54, 0.05) !important;
}

.remove-hint {
    opacity: 0;
    transition: opacity 0.2s;
}

.removable:hover .remove-hint {
    opacity: 1;
}

/* Unassigned dropzone styling */
.unassigned-dropzone {
    transition: all 0.2s;
}

.unassigned-dropzone:hover {
    background-color: rgba(59, 130, 246, 0.15);
    border-color: #3b82f6;
}

/* Remarks bottom panel styling */
.remarks-bottom {
    border-top: 1px solid #e5e7eb;
    margin-top: 2rem;
}
</style>
