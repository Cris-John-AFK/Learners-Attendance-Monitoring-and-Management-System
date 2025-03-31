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

// Custom layout configuration
const layoutType = ref('grid'); // 'grid' or 'custom'
const showTeacherDesk = ref(true);
const showStudentIds = ref(true);
const sectionCount = ref(3);
const sectionConfigs = ref([
    { rows: 5, columns: 2, addExtraSeat: false },
    { rows: 5, columns: 2, addExtraSeat: false },
    { rows: 5, columns: 2, addExtraSeat: true }
]);
const customSections = ref([]);
const extraSeats = ref([]);
const customDragPosition = ref(null);
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
            layoutType: layoutType.value,
            grid: {
                rows: rows.value,
                columns: columns.value,
                seatPlan: seatPlan.value
            },
            custom: {
                sectionCount: sectionCount.value,
                sectionConfigs: sectionConfigs.value,
                customSections: customSections.value,
                extraSeats: extraSeats.value
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
};

// Set the layout type
const setLayoutType = (type) => {
    layoutType.value = type;
    if (type === 'custom') {
        initializeCustomLayout();
    }
};

// Initialize the custom layout structure
const initializeCustomLayout = () => {
    customSections.value = [];
    extraSeats.value = [];

    for (let s = 0; s < sectionCount.value; s++) {
        const config = sectionConfigs.value[s] || { rows: 5, columns: 2, addExtraSeat: false };
        const section = [];

        for (let i = 0; i < config.rows; i++) {
            const row = [];
            for (let j = 0; j < config.columns; j++) {
                row.push({
                    section: s,
                    row: i,
                    col: j,
                    studentId: null,
                    status: null,
                    isOccupied: false
                });
            }
            section.push(row);
        }

        customSections.value.push(section);

        if (config.addExtraSeat) {
            extraSeats.value[s] = {
                section: s,
                studentId: null,
                status: null,
                isOccupied: false
            };
        }
    }

    // Recalculate unassigned students after initializing the layout
    calculateUnassignedStudents();
};

// Update the section count and configurations
const updateSections = () => {
    // Ensure we have the right number of section configs
    while (sectionConfigs.value.length < sectionCount.value) {
        sectionConfigs.value.push({ rows: 5, columns: 2, addExtraSeat: false });
    }

    while (sectionConfigs.value.length > sectionCount.value) {
        sectionConfigs.value.pop();
    }
};

// Apply the custom layout configuration
const applyCustomLayout = () => {
    initializeCustomLayout();
};

// Get student at custom seat
const getCustomStudentAtSeat = (sectionIndex, rowIndex, colIndex) => {
    if (!customSections.value[sectionIndex] || !customSections.value[sectionIndex][rowIndex] || !customSections.value[sectionIndex][rowIndex][colIndex]) return null;

    const studentId = customSections.value[sectionIndex][rowIndex][colIndex].studentId;
    return getStudentById(studentId);
};

// Get student at extra seat
const getExtraSeatStudent = (sectionIndex) => {
    if (!extraSeats.value[sectionIndex]) return null;
    const studentId = extraSeats.value[sectionIndex].studentId;
    return getStudentById(studentId);
};

// Update seat plan with changes to row/column count
watch([rows, columns], () => {
    if (isEditMode.value && layoutType.value === 'grid') {
        initializeSeatPlan();
    }
});

// Watch for changes to sectionConfigs
watch(
    sectionConfigs,
    () => {
        if (isEditMode.value && layoutType.value === 'custom') {
            initializeCustomLayout();
        }
    },
    { deep: true }
);

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

    // Try standard grid layout
    if (layoutType.value === 'grid') {
        // Find the seat with this student
        for (const row of seatPlan.value) {
            for (const seat of row) {
                if (seat.studentId === student.id) {
                    return seat.status;
                }
            }
        }
    }
    // Try custom layout
    else if (layoutType.value === 'custom') {
        // Check all sections
        for (let s = 0; s < customSections.value.length; s++) {
            for (let r = 0; r < customSections.value[s].length; r++) {
                for (let c = 0; c < customSections.value[s][r].length; c++) {
                    if (customSections.value[s][r][c].studentId === student.id) {
                        return customSections.value[s][r][c].status;
                    }
                }
            }

            // Check extra seat
            if (extraSeats.value[s] && extraSeats.value[s].studentId === student.id) {
                return extraSeats.value[s].status;
            }
        }
    }

    return null;
};

// Drag and drop functionality for grid layout
const startDrag = (student, position) => {
    isDragging.value = true;
    draggedStudent.value = student;
    draggedPosition.value = position;
};

const dropOnSeat = (rowIndex, colIndex) => {
    if (!draggedStudent.value) return;

    // If student was already placed, clear the previous seat
    if (draggedPosition.value) {
        const { row, col } = draggedPosition.value;
        seatPlan.value[row][col].studentId = null;
        seatPlan.value[row][col].isOccupied = false;
    } else if (customDragPosition.value) {
        if (customDragPosition.value.isExtra) {
            // Was in an extra seat
            extraSeats.value[customDragPosition.value.section].studentId = null;
            extraSeats.value[customDragPosition.value.section].isOccupied = false;
        } else {
            // Was in a custom seat
            const { section, row, col } = customDragPosition.value;
            customSections.value[section][row][col].studentId = null;
            customSections.value[section][row][col].isOccupied = false;
        }
    }

    // Assign student to new seat
    seatPlan.value[rowIndex][colIndex].studentId = draggedStudent.value.id;
    seatPlan.value[rowIndex][colIndex].isOccupied = true;

    // Reset drag state
    isDragging.value = false;
    draggedStudent.value = null;
    draggedPosition.value = null;
    customDragPosition.value = null;

    // Update unassigned students
    calculateUnassignedStudents();

    toast.add({
        severity: 'success',
        summary: 'Student Assigned',
        detail: `${draggedStudent.value.name} has been assigned to seat (Row ${rowIndex + 1}, Col ${colIndex + 1})`,
        life: 3000
    });
};

// Custom layout drag and drop functions
const startDragCustom = (student, position) => {
    isDragging.value = true;
    draggedStudent.value = student;
    customDragPosition.value = position;
};

const startDragExtra = (student, position) => {
    isDragging.value = true;
    draggedStudent.value = student;
    customDragPosition.value = position;
};

const dropOnCustomSeat = (sectionIndex, rowIndex, colIndex) => {
    if (!draggedStudent.value) return;

    // Clear the previous seat if student was already placed
    if (customDragPosition.value) {
        if (customDragPosition.value.isExtra) {
            // Was in an extra seat
            extraSeats.value[customDragPosition.value.section].studentId = null;
            extraSeats.value[customDragPosition.value.section].isOccupied = false;
        } else if (draggedPosition.value) {
            // Was in a standard grid seat
            const { row, col } = draggedPosition.value;
            seatPlan.value[row][col].studentId = null;
            seatPlan.value[row][col].isOccupied = false;
        } else {
            // Was in another custom seat
            const { section, row, col } = customDragPosition.value;
            customSections.value[section][row][col].studentId = null;
            customSections.value[section][row][col].isOccupied = false;
        }
    } else if (draggedPosition.value) {
        // Coming from standard grid seat
        const { row, col } = draggedPosition.value;
        seatPlan.value[row][col].studentId = null;
        seatPlan.value[row][col].isOccupied = false;
    }

    // Assign to new seat
    customSections.value[sectionIndex][rowIndex][colIndex].studentId = draggedStudent.value.id;
    customSections.value[sectionIndex][rowIndex][colIndex].isOccupied = true;

    // Reset drag state
    isDragging.value = false;
    const studentName = draggedStudent.value.name;
    draggedStudent.value = null;
    draggedPosition.value = null;
    customDragPosition.value = null;

    // Update unassigned students
    calculateUnassignedStudents();

    toast.add({
        severity: 'success',
        summary: 'Student Assigned',
        detail: `${studentName} has been assigned to section ${sectionIndex + 1}, seat (${rowIndex + 1}, ${colIndex + 1})`,
        life: 3000
    });
};

const dropOnExtraSeat = (sectionIndex) => {
    if (!draggedStudent.value || !extraSeats.value[sectionIndex]) return;

    // Clear the previous seat if student was already placed
    if (customDragPosition.value) {
        if (customDragPosition.value.isExtra) {
            // Was in an extra seat
            extraSeats.value[customDragPosition.value.section].studentId = null;
            extraSeats.value[customDragPosition.value.section].isOccupied = false;
        } else if (draggedPosition.value) {
            // Was in a standard grid seat
            const { row, col } = draggedPosition.value;
            seatPlan.value[row][col].studentId = null;
            seatPlan.value[row][col].isOccupied = false;
        } else {
            // Was in another custom seat
            const { section, row, col } = customDragPosition.value;
            customSections.value[section][row][col].studentId = null;
            customSections.value[section][row][col].isOccupied = false;
        }
    } else if (draggedPosition.value) {
        // Coming from standard grid seat
        const { row, col } = draggedPosition.value;
        seatPlan.value[row][col].studentId = null;
        seatPlan.value[row][col].isOccupied = false;
    }

    // Assign to extra seat
    extraSeats.value[sectionIndex].studentId = draggedStudent.value.id;
    extraSeats.value[sectionIndex].isOccupied = true;

    // Reset drag state
    isDragging.value = false;
    const studentName = draggedStudent.value.name;
    draggedStudent.value = null;
    draggedPosition.value = null;
    customDragPosition.value = null;

    // Update unassigned students
    calculateUnassignedStudents();

    toast.add({
        severity: 'success',
        summary: 'Student Assigned',
        detail: `${studentName} has been assigned to extra seat in section ${sectionIndex + 1}`,
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
    } else if (customDragPosition.value) {
        if (customDragPosition.value.isExtra) {
            // Extra seat
            extraSeats.value[customDragPosition.value.section].studentId = null;
            extraSeats.value[customDragPosition.value.section].isOccupied = false;
        } else {
            // Custom seat
            const { section, row, col } = customDragPosition.value;
            customSections.value[section][row][col].studentId = null;
            customSections.value[section][row][col].isOccupied = false;
        }
    }

    // Reset drag state
    isDragging.value = false;
    draggedStudent.value = null;
    draggedPosition.value = null;
    customDragPosition.value = null;

    // Update unassigned students
    calculateUnassignedStudents();
};

const cancelDrag = () => {
    isDragging.value = false;
    draggedStudent.value = null;
    draggedPosition.value = null;
    customDragPosition.value = null;
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

// Toggle attendance status for custom seat
const toggleCustomAttendanceStatus = (sectionIndex, rowIndex, colIndex) => {
    const seat = customSections.value[sectionIndex][rowIndex][colIndex];
    if (!seat.isOccupied) return;

    // Find current status index
    const currentStatusIndex = attendanceStatuses.findIndex((status) => status.name === seat.status);

    // Move to next status, or to Present if no status yet
    const nextIndex = currentStatusIndex === -1 ? 0 : (currentStatusIndex + 1) % attendanceStatuses.length;
    seat.status = attendanceStatuses[nextIndex].name;

    // Save attendance record
    saveAttendanceRecord(seat.studentId, seat.status);
};

// Toggle attendance status for extra seat
const toggleExtraSeatStatus = (sectionIndex) => {
    const seat = extraSeats.value[sectionIndex];
    if (!seat || !seat.isOccupied) return;

    // Find current status index
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

// Show remarks dialog for custom seat
const showRemarksCustomDialog = (sectionIndex, rowIndex, colIndex) => {
    const seat = customSections.value[sectionIndex][rowIndex][colIndex];
    if (!seat.isOccupied) return;

    selectedStudent.value = getStudentById(seat.studentId);
    remarks.value = ''; // Clear previous remarks
    pendingStatus.value = seat.status || 'Absent';
    showRemarks.value = true;
};

// Show remarks dialog for extra seat
const showRemarksExtraSeat = (sectionIndex) => {
    const seat = extraSeats.value[sectionIndex];
    if (!seat || !seat.isOccupied) return;

    selectedStudent.value = getStudentById(seat.studentId);
    remarks.value = ''; // Clear previous remarks
    pendingStatus.value = seat.status || 'Absent';
    showRemarks.value = true;
};

// Save attendance with remarks
const saveWithRemarks = async () => {
    if (!selectedStudent.value || !pendingStatus.value) return;

    await saveAttendanceRecord(selectedStudent.value.id, pendingStatus.value, remarks.value);

    // Update UI based on layout type
    if (layoutType.value === 'grid') {
        // Find the student in the grid
        for (const row of seatPlan.value) {
            for (const seat of row) {
                if (seat.studentId === selectedStudent.value.id) {
                    seat.status = pendingStatus.value;
                }
            }
        }
    } else {
        // Find in custom sections
        for (let s = 0; s < customSections.value.length; s++) {
            for (let r = 0; r < customSections.value[s].length; r++) {
                for (let c = 0; c < customSections.value[s][r].length; c++) {
                    if (customSections.value[s][r][c].studentId === selectedStudent.value.id) {
                        customSections.value[s][r][c].status = pendingStatus.value;
                    }
                }
            }

            // Check extra seats
            if (extraSeats.value[s] && extraSeats.value[s].studentId === selectedStudent.value.id) {
                extraSeats.value[s].status = pendingStatus.value;
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
        layoutType: layoutType.value,
        showTeacherDesk: showTeacherDesk.value,
        showStudentIds: showStudentIds.value,

        // Grid layout data
        rows: rows.value,
        columns: columns.value,
        seatPlan: JSON.parse(JSON.stringify(seatPlan.value)),

        // Custom layout data
        sectionCount: sectionCount.value,
        sectionConfigs: JSON.parse(JSON.stringify(sectionConfigs.value)),
        customSections: JSON.parse(JSON.stringify(customSections.value)),
        extraSeats: JSON.parse(JSON.stringify(extraSeats.value)),

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
    // Apply layout type first
    layoutType.value = template.layoutType || 'grid';
    showTeacherDesk.value = template.showTeacherDesk !== undefined ? template.showTeacherDesk : true;
    showStudentIds.value = template.showStudentIds !== undefined ? template.showStudentIds : true;

    if (layoutType.value === 'grid') {
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
    } else {
        // Apply custom layout
        sectionCount.value = template.sectionCount || 3;

        // Apply section configurations
        if (template.sectionConfigs) {
            sectionConfigs.value = JSON.parse(JSON.stringify(template.sectionConfigs));
        }

        // Initialize the custom layout
        initializeCustomLayout();

        // Apply student assignments if present
        if (template.customSections) {
            for (let s = 0; s < Math.min(template.customSections.length, customSections.value.length); s++) {
                for (let i = 0; i < Math.min(template.customSections[s].length, customSections.value[s].length); i++) {
                    for (let j = 0; j < Math.min(template.customSections[s][i].length, customSections.value[s][i].length); j++) {
                        const sourceSeat = template.customSections[s][i][j];
                        const targetSeat = customSections.value[s][i][j];

                        // Copy seat properties, but ensure student actually exists
                        if (sourceSeat.studentId && getStudentById(sourceSeat.studentId)) {
                            targetSeat.studentId = sourceSeat.studentId;
                            targetSeat.isOccupied = true;
                        }
                    }
                }
            }
        }

        // Apply extra seat assignments
        if (template.extraSeats) {
            // Loop through each extra seat in the template
            Object.keys(template.extraSeats).forEach((seatIndex) => {
                const s = parseInt(seatIndex);
                if (extraSeats.value[s]) {
                    const sourceExtraSeat = template.extraSeats[s];

                    // Copy seat properties, but ensure student actually exists
                    if (sourceExtraSeat.studentId && getStudentById(sourceExtraSeat.studentId)) {
                        extraSeats.value[s].studentId = sourceExtraSeat.studentId;
                        extraSeats.value[s].isOccupied = true;
                    }
                }
            });
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

    // Check standard grid layout
    if (layoutType.value === 'grid') {
        for (const row of seatPlan.value) {
            for (const seat of row) {
                if (seat.studentId) {
                    assignedIds.add(seat.studentId);
                }
            }
        }
    }
    // Check custom layout
    else if (layoutType.value === 'custom') {
        // Check regular seats
        for (const section of customSections.value) {
            for (const row of section) {
                for (const seat of row) {
                    if (seat.studentId) {
                        assignedIds.add(seat.studentId);
                    }
                }
            }
        }

        // Check extra seats
        for (const extraSeat of extraSeats.value) {
            if (extraSeat && extraSeat.studentId) {
                assignedIds.add(extraSeat.studentId);
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

        // Update seat status based on layout type
        if (layoutType.value === 'grid') {
            // Update status in grid layout
            updateStudentStatusInGrid(studentId, latestRecord.status);
        } else {
            // Update status in custom layout
            updateStudentStatusInCustomLayout(studentId, latestRecord.status);
        }
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

// Helper to update status in custom layout
const updateStudentStatusInCustomLayout = (studentId, status) => {
    // Check regular seats
    for (let s = 0; s < customSections.value.length; s++) {
        for (let i = 0; i < customSections.value[s].length; i++) {
            for (let j = 0; j < customSections.value[s][i].length; j++) {
                if (customSections.value[s][i][j].studentId === studentId) {
                    customSections.value[s][i][j].status = status;
                    return;
                }
            }
        }

        // Check extra seats
        if (extraSeats.value[s] && extraSeats.value[s].studentId === studentId) {
            extraSeats.value[s].status = status;
            return;
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
    if (layoutType.value === 'grid') {
        // Mark all seats in the grid as Present
        for (let i = 0; i < seatPlan.value.length; i++) {
            for (let j = 0; j < seatPlan.value[i].length; j++) {
                if (seatPlan.value[i][j].isOccupied) {
                    seatPlan.value[i][j].status = 'Present';
                    saveAttendanceRecord(seatPlan.value[i][j].studentId, 'Present');
                }
            }
        }
    } else {
        // Mark all seats in custom sections as Present
        for (let s = 0; s < customSections.value.length; s++) {
            for (let i = 0; i < customSections.value[s].length; i++) {
                for (let j = 0; j < customSections.value[s][i].length; j++) {
                    if (customSections.value[s][i][j].isOccupied) {
                        customSections.value[s][i][j].status = 'Present';
                        saveAttendanceRecord(customSections.value[s][i][j].studentId, 'Present');
                    }
                }
            }

            // Mark extra seats if occupied
            if (extraSeats.value[s] && extraSeats.value[s].isOccupied) {
                extraSeats.value[s].status = 'Present';
                saveAttendanceRecord(extraSeats.value[s].studentId, 'Present');
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
    if (layoutType.value === 'grid') {
        // Mark all seats in the grid as Absent
        for (let i = 0; i < seatPlan.value.length; i++) {
            for (let j = 0; j < seatPlan.value[i].length; j++) {
                if (seatPlan.value[i][j].isOccupied) {
                    seatPlan.value[i][j].status = 'Absent';
                    saveAttendanceRecord(seatPlan.value[i][j].studentId, 'Absent');
                }
            }
        }
    } else {
        // Mark all seats in custom sections as Absent
        for (let s = 0; s < customSections.value.length; s++) {
            for (let i = 0; i < customSections.value[s].length; i++) {
                for (let j = 0; j < customSections.value[s][i].length; j++) {
                    if (customSections.value[s][i][j].isOccupied) {
                        customSections.value[s][i][j].status = 'Absent';
                        saveAttendanceRecord(customSections.value[s][i][j].studentId, 'Absent');
                    }
                }
            }

            // Mark extra seats if occupied
            if (extraSeats.value[s] && extraSeats.value[s].isOccupied) {
                extraSeats.value[s].status = 'Absent';
                saveAttendanceRecord(extraSeats.value[s].studentId, 'Absent');
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
    if (layoutType.value === 'grid') {
        // Reset all statuses in the grid
        for (let i = 0; i < seatPlan.value.length; i++) {
            for (let j = 0; j < seatPlan.value[i].length; j++) {
                if (seatPlan.value[i][j].isOccupied) {
                    seatPlan.value[i][j].status = null;
                }
            }
        }
    } else {
        // Reset all statuses in custom sections
        for (let s = 0; s < customSections.value.length; s++) {
            for (let i = 0; i < customSections.value[s].length; i++) {
                for (let j = 0; j < customSections.value[s][i].length; j++) {
                    if (customSections.value[s][i][j].isOccupied) {
                        customSections.value[s][i][j].status = null;
                    }
                }
            }

            // Reset extra seats if occupied
            if (extraSeats.value[s] && extraSeats.value[s].isOccupied) {
                extraSeats.value[s].status = null;
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

        // Initialize custom layout
        initializeCustomLayout();

        // Set all students as unassigned initially
        unassignedStudents.value = [...students.value];

        // Fetch attendance history
        await fetchAttendanceHistory();

        // Load saved templates
        await loadSavedTemplates();

        // Use default (grid) layout if no templates exist
        if (savedTemplates.value.length === 0) {
            layoutType.value = 'grid';
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
                <Button icon="pi pi-cog" outlined class="p-button-rounded" @click="showSeatEditor = true" />
            </div>
        </div>

        <!-- Layout selection tabs -->
        <div class="mb-4 layout-tabs">
            <div class="flex border-b border-gray-200">
                <button class="px-4 py-2 text-md font-medium" :class="{ 'border-b-2 border-blue-500 text-blue-600': layoutType === 'grid' }" @click="setLayoutType('grid')">Standard Grid</button>
                <button class="px-4 py-2 text-md font-medium" :class="{ 'border-b-2 border-blue-500 text-blue-600': layoutType === 'custom' }" @click="setLayoutType('custom')">Custom Layout</button>
            </div>
        </div>

        <!-- Action buttons -->
        <div class="flex flex-wrap gap-2 mb-4">
            <Button label="Edit Seats" icon="pi pi-pencil" @click="toggleEditMode" :class="{ 'p-button-success': isEditMode }" />
            <Button label="Save as Template" icon="pi pi-save" @click="showTemplateSaveDialog = true" />
            <Button label="Load Template" icon="pi pi-folder-open" @click="showTemplateManager = true" />
            <Button label="Mark All Present" icon="pi pi-check" class="p-button-success" @click="markAllPresent" />
            <Button label="Mark All Absent" icon="pi pi-times" class="p-button-danger" @click="markAllAbsent" />
            <Button label="Reset" icon="pi pi-refresh" class="p-button-secondary" @click="resetAttendance" />
        </div>

        <!-- Edit Mode Notice -->
        <div v-if="isEditMode" class="bg-blue-100 text-blue-800 p-3 mb-4 rounded-lg">
            <i class="pi pi-info-circle mr-2"></i>
            Edit mode active. Drag students from the unassigned list to assign them to seats. Click "Edit Seats" again to save.
        </div>

        <!-- Standard Grid Layout -->
        <div v-if="layoutType === 'grid'" class="grid-layout mb-6">
            <!-- Teacher's desk -->
            <div v-if="showTeacherDesk" class="teachers-desk mb-6 bg-gray-100 p-3 text-center rounded-lg">
                <span class="font-semibold">Teacher's Desk</span>
            </div>

            <!-- Seating grid -->
            <div class="seat-grid" :style="{ gridTemplateColumns: `repeat(${columns}, 1fr)` }">
                <div v-for="(row, rowIndex) in seatPlan" :key="rowIndex" class="seat-row">
                    <div
                        v-for="(seat, colIndex) in row"
                        :key="colIndex"
                        class="seat-cell"
                        :class="{
                            occupied: seat.isOccupied,
                            'edit-mode': isEditMode,
                            'dragging-over': isDragging && !seat.isOccupied,
                            [seat.status?.toLowerCase()]: seat.status
                        }"
                        @click="isEditMode ? null : toggleAttendanceStatus(rowIndex, colIndex)"
                        @dragover.prevent="isEditMode && !seat.isOccupied ? $event.preventDefault() : null"
                        @drop="isEditMode ? dropOnSeat(rowIndex, colIndex) : null"
                    >
                        <div v-if="seat.isOccupied" class="student-info">
                            <div class="student-initials">
                                {{ getStudentInitials(getStudentById(seat.studentId)) }}
                            </div>
                            <div class="student-name">{{ getStudentById(seat.studentId)?.name }}</div>
                            <div v-if="showStudentIds" class="student-id">ID: {{ seat.studentId }}</div>
                            <div v-if="seat.status" class="status-indicator" :style="{ backgroundColor: getStatusColor(seat.status) }">
                                <i :class="getStatusIcon(seat.status)"></i>
                                {{ seat.status }}
                            </div>
                        </div>
                        <div v-else-if="isEditMode" class="empty-seat">
                            <span>Empty</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Layout -->
        <div v-else class="custom-layout mb-6">
            <!-- Teacher's desk -->
            <div v-if="showTeacherDesk" class="teachers-desk mb-6 bg-gray-100 p-3 text-center rounded-lg">
                <span class="font-semibold">Teacher's Desk</span>
            </div>

            <!-- Section configuration in edit mode -->
            <div v-if="isEditMode" class="section-config p-3 bg-gray-50 rounded-lg mb-4">
                <h3 class="text-lg font-semibold mb-2">Layout Configuration</h3>

                <div class="flex flex-wrap gap-4 mb-3">
                    <div class="field">
                        <label class="block mb-1">Sections</label>
                        <InputNumber v-model="sectionCount" :min="1" :max="5" @update:modelValue="updateSections" />
                    </div>

                    <div class="flex gap-3">
                        <Checkbox v-model="showTeacherDesk" binary id="teacherDesk" />
                        <label for="teacherDesk">Show Teacher's Desk</label>
                    </div>

                    <div class="flex gap-3">
                        <Checkbox v-model="showStudentIds" binary id="studentIds" />
                        <label for="studentIds">Show Student IDs</label>
                    </div>
                </div>

                <h4 class="font-medium mb-2">Section Configurations</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="(config, index) in sectionConfigs.slice(0, sectionCount)" :key="index" class="section-config-item p-3 border rounded-lg">
                        <h5 class="font-medium mb-2">Section {{ index + 1 }}</h5>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="field">
                                <label class="block mb-1 text-sm">Rows</label>
                                <InputNumber v-model="config.rows" :min="1" :max="8" class="w-full" />
                            </div>

                            <div class="field">
                                <label class="block mb-1 text-sm">Columns</label>
                                <InputNumber v-model="config.columns" :min="1" :max="6" class="w-full" />
                            </div>
                        </div>

                        <div class="flex gap-2 mt-2">
                            <Checkbox v-model="config.addExtraSeat" binary :id="'extraSeat' + index" />
                            <label :for="'extraSeat' + index" class="text-sm">Add Extra Seat</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <Button label="Apply Layout" icon="pi pi-check" @click="applyCustomLayout" />
                </div>
            </div>

            <!-- Custom sections display -->
            <div class="custom-sections">
                <div v-for="(section, sectionIndex) in customSections" :key="sectionIndex" class="custom-section mb-6">
                    <h3 class="font-semibold mb-2">Section {{ sectionIndex + 1 }}</h3>

                    <div class="section-grid" :style="{ gridTemplateColumns: `repeat(${sectionConfigs[sectionIndex].columns}, 1fr)` }">
                        <div v-for="(row, rowIndex) in section" :key="rowIndex" class="seat-row">
                            <div
                                v-for="(seat, colIndex) in row"
                                :key="colIndex"
                                class="seat-cell"
                                :class="{
                                    occupied: seat.isOccupied,
                                    'edit-mode': isEditMode,
                                    'dragging-over': isDragging && !seat.isOccupied,
                                    [seat.status?.toLowerCase()]: seat.status
                                }"
                                @click="isEditMode ? null : toggleCustomAttendanceStatus(sectionIndex, rowIndex, colIndex)"
                                @dragover.prevent="isEditMode && !seat.isOccupied ? $event.preventDefault() : null"
                                @drop="isEditMode ? dropOnCustomSeat(sectionIndex, rowIndex, colIndex) : null"
                            >
                                <div v-if="seat.isOccupied" class="student-info">
                                    <div class="student-initials">
                                        {{ getStudentInitials(getStudentById(seat.studentId)) }}
                                    </div>
                                    <div class="student-name">{{ getStudentById(seat.studentId)?.name }}</div>
                                    <div v-if="showStudentIds" class="student-id">ID: {{ seat.studentId }}</div>
                                    <div v-if="seat.status" class="status-indicator" :style="{ backgroundColor: getStatusColor(seat.status) }">
                                        <i :class="getStatusIcon(seat.status)"></i>
                                        {{ seat.status }}
                                    </div>
                                </div>
                                <div v-else-if="isEditMode" class="empty-seat">
                                    <span>Empty</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Extra seat for this section if enabled -->
                    <div
                        v-if="extraSeats[sectionIndex]"
                        class="extra-seat mt-2"
                        :class="{
                            occupied: extraSeats[sectionIndex].isOccupied,
                            'edit-mode': isEditMode,
                            'dragging-over': isDragging && !extraSeats[sectionIndex].isOccupied,
                            [extraSeats[sectionIndex].status?.toLowerCase()]: extraSeats[sectionIndex].status
                        }"
                        @click="isEditMode ? null : toggleExtraSeatStatus(sectionIndex)"
                        @dragover.prevent="isEditMode && !extraSeats[sectionIndex].isOccupied ? $event.preventDefault() : null"
                        @drop="isEditMode ? dropOnExtraSeat(sectionIndex) : null"
                    >
                        <div v-if="extraSeats[sectionIndex].isOccupied" class="student-info">
                            <div class="student-initials">
                                {{ getStudentInitials(getStudentById(extraSeats[sectionIndex].studentId)) }}
                            </div>
                            <div class="student-name">{{ getStudentById(extraSeats[sectionIndex].studentId)?.name }}</div>
                            <div v-if="showStudentIds" class="student-id">ID: {{ extraSeats[sectionIndex].studentId }}</div>
                            <div v-if="extraSeats[sectionIndex].status" class="status-indicator" :style="{ backgroundColor: getStatusColor(extraSeats[sectionIndex].status) }">
                                <i :class="getStatusIcon(extraSeats[sectionIndex].status)"></i>
                                {{ extraSeats[sectionIndex].status }}
                            </div>
                        </div>
                        <div v-else class="extra-label">
                            <span>Extra Seat</span>
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
                <Column field="layoutType" header="Layout Type"></Column>
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

<style scoped>
.attendance-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Seat grid styling */
.seat-grid {
    display: grid;
    gap: 10px;
}

.section-grid {
    display: grid;
    gap: 10px;
    margin-bottom: 20px;
}

.seat-cell {
    border: 1px solid #ccc;
    border-radius: 6px;
    padding: 10px;
    height: 100px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    position: relative;
    transition: all 0.2s;
}

.seat-cell.edit-mode {
    cursor: pointer;
    background-color: #f8f9fa;
}

.seat-cell.occupied {
    background-color: #e8f4fc;
    border-color: #90caf9;
}

.seat-cell.dragging-over {
    background-color: #e3f2fd;
    box-shadow: 0 0 0 2px #2196f3;
}

.student-info {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.student-initials {
    width: 36px;
    height: 36px;
    background-color: #2196f3;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 5px;
}

.student-name {
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
}

.student-id {
    font-size: 0.75rem;
    color: #666;
    margin-top: 2px;
}

.status-indicator {
    position: absolute;
    top: 5px;
    right: 5px;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    color: white;
}

.seat-cell.present {
    background-color: rgba(76, 175, 80, 0.1);
    border-color: #4caf50;
}

.seat-cell.absent {
    background-color: rgba(244, 67, 54, 0.1);
    border-color: #f44336;
}

.seat-cell.late {
    background-color: rgba(255, 152, 0, 0.1);
    border-color: #ff9800;
}

.seat-cell.excused {
    background-color: rgba(33, 150, 243, 0.1);
    border-color: #2196f3;
}

.empty-seat {
    color: #aaa;
    font-size: 0.8rem;
}

.extra-seat {
    border: 1px dashed #ccc;
    border-radius: 6px;
    padding: 10px;
    height: 80px;
    width: 120px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background-color: #f5f5f5;
    margin-left: 20px;
    position: relative;
}

.extra-seat.occupied {
    background-color: #fff8e1;
    border-color: #ffc107;
    border-style: solid;
}

.extra-label {
    color: #777;
    font-size: 0.8rem;
}

.student-card {
    transition: all 0.2s;
}

.student-card:hover {
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.student-avatar {
    font-size: 0.8rem;
}

.status-option {
    display: flex;
    align-items: center;
    border: 1px solid;
    transition: all 0.2s;
}

.status-option:hover {
    opacity: 0.9;
}

.unassign-dropzone {
    background-color: #f5f5f5;
    transition: all 0.2s;
}

.unassign-dropzone:hover {
    background-color: #e0e0e0;
}

/* Responsive adjustments */
@media screen and (max-width: 768px) {
    .seat-cell {
        height: 80px;
        padding: 5px;
    }

    .student-initials {
        width: 28px;
        height: 28px;
        font-size: 0.7rem;
    }

    .student-name {
        font-size: 0.8rem;
    }

    .layout-tabs {
        overflow-x: auto;
    }
}
</style>
