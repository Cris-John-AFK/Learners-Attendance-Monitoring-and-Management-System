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
const showTemplateManager = ref(false);
const showTemplateSaveDialog = ref(false);
const isEditMode = ref(false);

// Seating plan configuration
const rows = ref(9);
const columns = ref(9);
const templateName = ref('');
const savedTemplates = ref([]);
const selectedTemplate = ref(null);

// Layout configuration options
const showTeacherDesk = ref(true);
const showStudentIds = ref(true);

// Student and attendance data
const students = ref([]);
const selectedStudent = ref(null);
const pendingStatus = ref('');
const searchQuery = ref('');
const unassignedStudents = ref([]);
const seatPlan = ref([]);
const attendanceRecords = ref({});
const remarksPanel = ref([]);

// Drag and drop state
const draggedStudent = ref(null);
const selectedSeat = ref(null);

// Add these new refs for the remarks functionality
const showRemarksDialog = ref(false);

const attendanceRemarks = ref('');

// Status selection dialog
const showStatusDialog = ref(false);

// Attendance method selection
const showAttendanceMethodModal = ref(false);
const showQRScanner = ref(false);
const showRollCall = ref(false);
const isCameraLoading = ref(false);
const videoElement = ref(null);
const currentStudentIndex = ref(0);
const currentStudent = ref(null);
const scannedStudents = ref([]);
let codeReader = null;

// Rename to avoid conflict
const showAttendanceDialog = ref(false);

// Add these refs if not already present
const showRollCallRemarksDialog = ref(false);
const rollCallRemarks = ref('');
const pendingRollCallStatus = ref('');

// Toggle edit mode
const toggleEditMode = () => {
    isEditMode.value = !isEditMode.value;

    if (isEditMode.value) {
        // Entering edit mode
        calculateUnassignedStudents();
    } else {
        // Exiting edit mode - save the current layout
        saveCurrentLayout(false);
    }
};

// Save current layout
const saveCurrentLayout = (showToast = true) => {
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

    if (showToast) {
        toast.add({
            severity: 'success',
            summary: 'Layout Saved',
            detail: 'Seating arrangement has been saved',
            life: 3000
        });
    }
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

// Get student data by ID
const getStudentById = (id) => {
    if (!id) return null;
    return students.value.find((student) => student.id === id) || null;
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

    // Save the current layout to localStorage
    saveCurrentLayout(false); // false means don't show toast notification

    // Show success message
    toast.add({
        severity: 'success',
        summary: 'Student Assigned',
        detail: 'Student has been assigned to seat',
        life: 3000
    });
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

    // Recalculate unassigned students
    calculateUnassignedStudents();

    // Save the current layout to localStorage
    saveCurrentLayout(false);

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
        for (let j = 0; j < row.length; j++) {
            if (row[j].isOccupied && row[j].studentId) {
                assignedIds.add(row[j].studentId);
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

        // Save the layout after changing rows
        saveCurrentLayout(false);
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

        // Save the layout after changing rows
        saveCurrentLayout(false);
    }
};

// Add incrementColumns and decrementColumns functions with similar save logic
const incrementColumns = () => {
    if (columns.value < 12) {
        columns.value++;

        // Add a new column to each row
        for (let i = 0; i < seatPlan.value.length; i++) {
            seatPlan.value[i].push({
                isOccupied: false,
                studentId: null,
                status: null
            });
        }

        // Save the layout after changing columns
        saveCurrentLayout(false);
    }
};

const decrementColumns = () => {
    if (columns.value > 1) {
        columns.value--;

        // Remove last column from each row if empty
        let allEmpty = true;

        // Check if all seats in the last column are empty
        for (let i = 0; i < seatPlan.value.length; i++) {
            const lastColIndex = seatPlan.value[i].length - 1;
            if (lastColIndex >= 0 && seatPlan.value[i][lastColIndex].isOccupied) {
                allEmpty = false;
                break;
            }
        }

        // Only remove if all are empty
        if (allEmpty) {
            for (let i = 0; i < seatPlan.value.length; i++) {
                seatPlan.value[i].pop();
            }
        } else {
            // If not all empty, revert the column change
            columns.value++;
            toast.add({
                severity: 'warn',
                summary: 'Cannot Remove Column',
                detail: 'Cannot remove a column with assigned students',
                life: 3000
            });
            return;
        }

        // Save the layout after changing columns
        saveCurrentLayout(false);
    }
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

        // Load saved attendance records (IMPORTANT: This must be done BEFORE loading the layout)
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

        // First try to load the saved layout
        const layoutLoaded = loadSavedLayout();

        if (!layoutLoaded) {
            // If no saved layout, try to load templates
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

                    // Apply attendance statuses after loading template
                    applyAttendanceStatusesToSeatPlan();
                }
            }
        } else {
            toast.add({
                severity: 'info',
                summary: 'Layout Loaded',
                detail: 'Previous seat plan layout has been restored',
                life: 3000
            });
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

    // Save the current layout to localStorage
    saveCurrentLayout(false); // false means don't show toast notification

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

// Function to mark a student as present
const markStudentPresent = (student) => {
    // Find if student is already assigned to a seat
    let found = false;
    let rowIndex = -1;
    let colIndex = -1;

    // Search in seat plan
    for (let i = 0; i < seatPlan.value.length; i++) {
        for (let j = 0; j < seatPlan.value[i].length; j++) {
            if (seatPlan.value[i][j].studentId === student.id) {
                rowIndex = i;
                colIndex = j;
                found = true;
                break;
            }
        }
        if (found) break;
    }

    if (found) {
        // Update existing seat
        seatPlan.value[rowIndex][colIndex].status = 'Present';

        // Save attendance record
        const recordKey = `${student.id}_${currentDate.value}`;
        attendanceRecords.value[recordKey] = {
            studentId: student.id,
            date: currentDate.value,
            status: 'Present',
            time: new Date().toLocaleTimeString(),
            remarks: ''
        };
    } else {
        // Student isn't assigned to a seat, just record attendance
        const recordKey = `${student.id}_${currentDate.value}`;
        attendanceRecords.value[recordKey] = {
            studentId: student.id,
            date: currentDate.value,
            status: 'Present',
            time: new Date().toLocaleTimeString(),
            remarks: ''
        };
    }

    // Save to localStorage
    localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));
};

// Roll Call Methods
const startRollCall = () => {
    showRollCall.value = true;
    showQRScanner.value = false;
    showAttendanceMethodModal.value = false;

    // Initialize roll call process
    currentStudentIndex.value = 0;
    if (students.value.length > 0) {
        currentStudent.value = students.value[0];
    }

    toast.add({
        severity: 'info',
        summary: 'Roll Call Mode',
        detail: 'Roll call mode started. Mark each student as they respond.',
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

// Add this function to show the attendance method dialog
const openAttendanceMethodDialog = () => {
    showAttendanceMethodModal.value = true;

    // Reset any previous selection
    showQRScanner.value = false;
    showRollCall.value = false;

    console.log('Opening attendance method dialog');
};

// Add these functions for handling attendance methods
const startQRScanner = async () => {
    showQRScanner.value = true;
    showRollCall.value = false;
    showAttendanceMethodModal.value = false;
    isCameraLoading.value = true;

    try {
        // Initialize QR code scanner
        codeReader = new BrowserMultiFormatReader();

        // Get available video devices
        const videoDevices = await codeReader.listVideoInputDevices();

        if (videoDevices.length === 0) {
            throw new Error('No camera devices found');
        }

        // Use first video device
        const selectedDeviceId = videoDevices[0].deviceId;

        // Wait for video element to be available in the DOM
        await nextTick();

        if (!videoElement.value) {
            throw new Error('Video element not found');
        }

        // Start decoding from video stream
        codeReader.decodeFromVideoDevice(selectedDeviceId, videoElement.value, (result, error) => {
            if (result) {
                handleQRCodeResult(result.getText());
            }
            if (error && error.name !== 'NotFoundException') {
                console.error('QR Scanner error:', error);
            }
        });

        isCameraLoading.value = false;

        toast.add({
            severity: 'info',
            summary: 'QR Scanner',
            detail: 'QR Scanner is active. Scan student ID QR codes.',
            life: 3000
        });
    } catch (error) {
        console.error('Error starting QR scanner:', error);
        isCameraLoading.value = false;
        showQRScanner.value = false;

        toast.add({
            severity: 'error',
            summary: 'Scanner Error',
            detail: 'Could not start QR scanner: ' + error.message,
            life: 5000
        });
    }
};

// Handle QR code result
const handleQRCodeResult = (qrData) => {
    try {
        // Extract student ID from QR code data
        const studentId = parseInt(qrData.trim());

        if (isNaN(studentId)) {
            throw new Error('Invalid QR code format');
        }

        // Find the student
        const student = getStudentById(studentId);

        if (!student) {
            throw new Error('Student not found');
        }

        // Check if already scanned
        if (scannedStudents.value.includes(studentId)) {
            toast.add({
                severity: 'warn',
                summary: 'Already Scanned',
                detail: `${student.name} has already been marked present`,
                life: 3000
            });
            return;
        }

        // Mark student as present
        markStudentPresent(student);

        // Add to scanned list
        scannedStudents.value.push(studentId);

        toast.add({
            severity: 'success',
            summary: 'Student Present',
            detail: `${student.name} marked as present`,
            life: 2000
        });
    } catch (error) {
        console.error('Error processing QR code:', error);
        toast.add({
            severity: 'error',
            summary: 'QR Error',
            detail: error.message,
            life: 3000
        });
    }
};

// Clean up camera resources on unmount
onUnmounted(() => {
    if (codeReader) {
        codeReader.reset();
        codeReader = null;
    }
});

// Add this function for the Roll Call feature
const markRollCallStatus = (status) => {
    if (!currentStudent.value) return;

    // Mark student with the selected status
    const student = currentStudent.value;
    const studentId = student.id;

    // Find if student is in a seat
    let found = false;

    // Search all seats
    for (let i = 0; i < seatPlan.value.length && !found; i++) {
        for (let j = 0; j < seatPlan.value[i].length && !found; j++) {
            if (seatPlan.value[i][j].studentId === studentId) {
                // Update seat status
                seatPlan.value[i][j].status = status;
                found = true;
            }
        }
    }

    // Save attendance record
    const recordKey = `${studentId}_${currentDate.value}`;
    attendanceRecords.value[recordKey] = {
        studentId: studentId,
        date: currentDate.value,
        status: status,
        time: new Date().toLocaleTimeString(),
        remarks: ''
    };

    // Save to localStorage
    localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));

    // Show confirmation
    toast.add({
        severity: status === 'Present' ? 'success' : status === 'Absent' ? 'error' : 'warn',
        summary: `Marked ${status}`,
        detail: `${student.name} has been marked as ${status}`,
        life: 2000
    });

    // Move to next student
    if (currentStudentIndex.value < students.value.length - 1) {
        currentStudentIndex.value++;
        currentStudent.value = students.value[currentStudentIndex.value];
    } else {
        // End of roll call
        toast.add({
            severity: 'info',
            summary: 'Roll Call Complete',
            detail: 'All students have been processed',
            life: 3000
        });
        showRollCall.value = false;
    }
};

// Update the roll call attendance marking function
const markRollCallAttendance = (status) => {
    if (!currentStudent.value) return;

    // For Absent or Excused, show remarks dialog
    if (status === 'Absent' || status === 'Excused') {
        pendingRollCallStatus.value = status;
        showRollCallRemarksDialog.value = true;
        return;
    }

    // For Present or Late, mark directly
    saveRollCallAttendanceWithRemarks(status);
};

// Add function to save roll call attendance with remarks
const saveRollCallAttendanceWithRemarks = (status, remarks = '') => {
    if (!currentStudent.value) return;

    // Find student's seat in the grid
    let foundSeat = null;
    let rowIndex = -1;
    let colIndex = -1;

    seatPlan.value.forEach((row, rIndex) => {
        row.forEach((seat, cIndex) => {
            if (seat.studentId === currentStudent.value.id) {
                foundSeat = seat;
                rowIndex = rIndex;
                colIndex = cIndex;
            }
        });
    });

    if (foundSeat) {
        // Update seat status
        foundSeat.status = status;

        // Save attendance with remarks
        const recordKey = `${currentStudent.value.id}-${currentDate.value}`;
        attendanceRecords.value[recordKey] = {
            studentId: currentStudent.value.id,
            date: currentDate.value,
            status,
            remarks,
            timestamp: new Date().toISOString()
        };

        // Update remarks panel if needed
        if (status === 'Absent' || status === 'Excused') {
            updateRemarksPanel(currentStudent.value.id, status, remarks);
        } else {
            // Remove from remarks panel if exists
            remarksPanel.value = remarksPanel.value.filter((r) => r.studentId !== currentStudent.value.id);
        }
    }

    // Move to next student
    currentStudentIndex.value++;
    if (currentStudentIndex.value < students.value.length) {
        currentStudent.value = students.value[currentStudentIndex.value];
    } else {
        // End roll call
        showRollCall.value = false;
        toast.add({
            severity: 'success',
            summary: 'Roll Call Complete',
            detail: 'Roll call has been completed',
            life: 3000
        });
    }

    // Reset remarks dialog
    showRollCallRemarksDialog.value = false;
    rollCallRemarks.value = '';
    pendingRollCallStatus.value = '';
};

// Add function to save roll call remarks
const saveRollCallRemarks = () => {
    if (!rollCallRemarks.value.trim()) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Please enter remarks',
            life: 3000
        });
        return;
    }

    saveRollCallAttendanceWithRemarks(pendingRollCallStatus.value, rollCallRemarks.value);
};

// Add this function for the updateRemarksPanel
const updateRemarksPanel = (studentId, status, remarks) => {
    // Get the student
    const student = getStudentById(studentId);

    // Create a remark item
    const remarkItem = {
        studentId: studentId,
        studentName: student?.name || 'Unknown Student',
        status,
        remarks,
        timestamp: new Date().toISOString()
    };

    // Update or add to remarks panel
    const existingIndex = remarksPanel.value.findIndex((r) => r.studentId === studentId);
    if (existingIndex >= 0) {
        remarksPanel.value[existingIndex] = remarkItem;
    } else {
        remarksPanel.value.push(remarkItem);
    }

    // Save to localStorage
    localStorage.setItem('remarksPanel', JSON.stringify(remarksPanel.value));
};

// Add a function to apply attendance statuses to the seat plan after loading
const applyAttendanceStatusesToSeatPlan = () => {
    // Skip if no attendance records
    if (!attendanceRecords.value || Object.keys(attendanceRecords.value).length === 0) return;

    // Get today's date
    const today = currentDate.value;

    // Apply attendance statuses to the seat plan
    seatPlan.value.forEach((row) => {
        row.forEach((seat) => {
            if (seat.isOccupied && seat.studentId) {
                // Look for a record matching this student on current date
                // Check both formats: studentId-date and studentId_date
                const recordKey1 = `${seat.studentId}-${today}`;
                const recordKey2 = `${seat.studentId}_${today}`;

                const record = attendanceRecords.value[recordKey1] || attendanceRecords.value[recordKey2];

                if (record) {
                    // Apply the status from the record
                    seat.status = record.status;
                    console.log(`Applied status ${record.status} to student ${seat.studentId}`);
                }
            }
        });
    });
};

// Update the loadSavedLayout function to apply attendance statuses after loading the layout
const loadSavedLayout = () => {
    try {
        const storageKey = `seatPlan_${subjectId.value}`;
        const savedData = localStorage.getItem(storageKey);

        if (savedData) {
            const parsedData = JSON.parse(savedData);

            // Check if it's a template array or a layout object
            if (Array.isArray(parsedData)) {
                console.log('Found array of templates instead of layout object');
                return false;
            }

            // Set layout configuration
            rows.value = parsedData.rows || rows.value;
            columns.value = parsedData.columns || columns.value;
            showTeacherDesk.value = parsedData.showTeacherDesk !== undefined ? parsedData.showTeacherDesk : showTeacherDesk.value;
            showStudentIds.value = parsedData.showStudentIds !== undefined ? parsedData.showStudentIds : showStudentIds.value;

            // Set the seat plan with deep copy to avoid reference issues
            if (parsedData.seatPlan) {
                seatPlan.value = JSON.parse(JSON.stringify(parsedData.seatPlan));

                // Recalculate unassigned students
                calculateUnassignedStudents();

                // Apply attendance statuses to the seat plan
                applyAttendanceStatusesToSeatPlan();

                console.log('Loaded saved layout with student assignments');
                return true;
            }
        }
        return false;
    } catch (error) {
        console.error('Error loading saved layout:', error);
        return false;
    }
};

// Update onMounted to load attendance records first, before loading the layout
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

        // Load saved attendance records (IMPORTANT: This must be done BEFORE loading the layout)
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

        // First try to load the saved layout
        const layoutLoaded = loadSavedLayout();

        if (!layoutLoaded) {
            // If no saved layout, try to load templates
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

                    // Apply attendance statuses after loading template
                    applyAttendanceStatusesToSeatPlan();
                }
            }
        } else {
            toast.add({
                severity: 'info',
                summary: 'Layout Loaded',
                detail: 'Previous seat plan layout has been restored',
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error initializing data:', error);
    }
});

// Update watch for currentDate to reapply statuses when date changes
watch(currentDate, (newDate) => {
    // Reapply attendance statuses when the date changes
    applyAttendanceStatusesToSeatPlan();
});

// Add watchers for the checkbox options
watch(showTeacherDesk, (newValue) => {
    // Save layout when teacher desk visibility is changed
    saveCurrentLayout(false);
});

watch(showStudentIds, (newValue) => {
    // Save layout when student IDs visibility is changed
    saveCurrentLayout(false);
});
</script>

<template>
    <div class="attendance-container p-4">
        <!-- Header with subject name and date -->
        <div class="flex justify-between items-center mb-4">
            <h5 class="text-xl font-semibold">{{ subjectName }} Attendance</h5>
            <div class="flex gap-2 align-items-center">
                <Calendar v-model="currentDate" dateFormat="yy-mm-dd" class="mr-2" />
                <Button icon="pi pi-list-check" label="Take Attendance" class="p-button-primary" @click="openAttendanceMethodDialog" />
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
        <Dialog v-model:visible="showRemarksDialog" header="Enter Remarks" :modal="true" :style="{ width: '30vw', minWidth: '400px' }" :closeOnEscape="true" :dismissableMask="true">
            <div class="p-fluid">
                <div class="field">
                    <label for="attendanceRemarks">Remarks</label>
                    <Textarea id="attendanceRemarks" v-model="attendanceRemarks" rows="3" placeholder="Enter reason for absence/excuse" class="w-full" />
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
                <h3 class="text-lg font-medium mb-3">Attendance Remarks</h3>
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

        <!-- Attendance Method Dialog -->
        <Dialog v-model:visible="showAttendanceMethodModal" header="Select Attendance Method" :modal="true" :closable="true" style="width: 400px">
            <div class="p-4">
                <h3 class="text-lg font-medium mb-4">How would you like to take attendance?</h3>

                <div class="flex flex-col gap-4">
                    <Button icon="pi pi-users" label="Seat Plan" class="p-button-outlined" @click="showAttendanceMethodModal = false" />

                    <Button icon="pi pi-list" label="Roll Call" class="p-button-outlined" @click="startRollCall" />

                    <Button icon="pi pi-camera" label="QR Scanner" class="p-button-outlined" @click="startQRScanner" />
                </div>
            </div>
        </Dialog>

        <!-- Roll Call Dialog -->
        <Dialog v-model:visible="showRollCall" header="Roll Call" :modal="true" :closable="true" style="width: 500px">
            <div v-if="currentStudent" class="p-4">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-800 font-bold mr-3">
                        {{ getStudentInitials(currentStudent) }}
                    </div>
                    <div>
                        <h3 class="text-lg font-medium">{{ currentStudent.name }}</h3>
                        <p class="text-gray-500 text-sm">ID: {{ currentStudent.id }}</p>
                    </div>
                </div>

                <div class="flex gap-2 justify-center mt-4">
                    <Button icon="pi pi-check-circle" label="Present" class="p-button-success" @click="markRollCallAttendance('Present')" />
                    <Button icon="pi pi-times-circle" label="Absent" class="p-button-danger" @click="markRollCallAttendance('Absent')" />
                    <Button icon="pi pi-clock" label="Late" style="background: #eab308; border-color: #eab308" @click="markRollCallAttendance('Late')" />
                    <Button icon="pi pi-info-circle" label="Excused" style="background: #9333ea; border-color: #9333ea" @click="markRollCallAttendance('Excused')" />
                </div>

                <div class="flex justify-between mt-6">
                    <p class="text-gray-500">{{ currentStudentIndex + 1 }} of {{ students.length }}</p>
                    <div>
                        <Button icon="pi pi-times" class="p-button-text" @click="showRollCall = false" label="Finish" />
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Roll Call Remarks Dialog -->
        <Dialog v-model:visible="showRollCallRemarksDialog" header="Enter Remarks" :modal="true" :style="{ width: '30vw', minWidth: '400px' }" :closeOnEscape="true" :dismissableMask="true">
            <div class="p-fluid">
                <div class="field">
                    <label for="rollCallRemarks">Remarks</label>
                    <Textarea id="rollCallRemarks" v-model="rollCallRemarks" rows="3" placeholder="Enter reason for absence/excuse" class="w-full" />
                </div>
            </div>
            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showRollCallRemarksDialog = false" />
                <Button label="Save" icon="pi pi-check" class="p-button-success" @click="saveRollCallRemarks" />
            </template>
        </Dialog>

        <!-- QR Scanner Dialog -->
        <Dialog v-model:visible="showQRScanner" header="QR Scanner" :modal="true" :closable="true" style="width: 500px">
            <div class="p-4">
                <div v-if="isCameraLoading" class="flex items-center justify-center p-6">
                    <i class="pi pi-spin pi-spinner text-3xl text-blue-500 mr-3"></i>
                    <span>Loading camera...</span>
                </div>

                <div v-else class="qr-scanner-container">
                    <video ref="videoElement" class="w-full h-64 bg-black rounded"></video>

                    <div class="mt-4">
                        <p class="text-sm text-gray-600 mb-2">Point the camera at a student ID QR code</p>

                        <div v-if="scannedStudents.length > 0" class="mt-4">
                            <h4 class="text-sm font-medium mb-2">Scanned Students: {{ scannedStudents.length }}</h4>
                            <ul class="text-sm max-h-32 overflow-y-auto">
                                <li v-for="id in scannedStudents" :key="id" class="py-1 px-2 bg-green-50 rounded mb-1 flex items-center">
                                    <i class="pi pi-check-circle text-green-500 mr-2"></i>
                                    {{ getStudentById(id)?.name || `Student ID: ${id}` }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="flex justify-end mt-4">
                        <Button icon="pi pi-times" label="Close" @click="showQRScanner = false" />
                    </div>
                </div>
            </div>
        </Dialog>
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
    background-color: #e3ccf8;
    border-color: #c5a9fa;
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
