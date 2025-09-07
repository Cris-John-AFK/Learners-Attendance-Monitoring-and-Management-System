<script setup>
import { QRCodeAPIService } from '@/router/service/QRCodeAPIService';
import { AttendanceService } from '@/router/service/Students';
import { TeacherAttendanceService } from '@/router/service/TeacherAttendanceService';
import { SubjectService } from '@/router/service/Subjects';
import SeatingService from '@/services/SeatingService';
import AttendanceSessionService from '@/services/AttendanceSessionService';
import Calendar from 'primevue/calendar';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { QrcodeStream } from 'vue-qrcode-reader';
import { useRoute, useRouter } from 'vue-router';

// Add OverlayPanel and Menu components

// Add Dialog component if not already imported

const route = useRoute();
const router = useRouter();
const toast = useToast();
const subjectName = ref('Subject');
const subjectId = ref('');
const sectionId = ref('');
const teacherId = ref(1); // Static teacher ID for now (Maria Santos)
const currentDate = ref(new Date().toISOString().split('T')[0]);
const currentDateTime = ref(new Date());

// Attendance Session Management
const currentSession = ref(null);
const attendanceStatuses = ref([]);
const sessionActive = ref(false);
const sessionSummary = ref(null);

// Watch for date changes and update attendance display
watch(currentDate, (newDate) => {
    console.log('Date changed to:', newDate);
    loadCachedAttendanceData();
});

// Timer reference for cleanup
const timeInterval = ref(null);

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
const scanning = ref(false);
const cameraError = ref(null);
const currentStudentIndex = ref(0);
const currentStudent = ref(null);
const scannedStudents = ref([]);

// Rename to avoid conflict
const showAttendanceDialog = ref(false);

// Add these refs if not already present
const showRollCallRemarksDialog = ref(false);
const rollCallRemarks = ref('');
const pendingRollCallStatus = ref('');

// Initialize attendance session system
const initializeAttendanceSession = async () => {
    try {
        // Load attendance statuses
        attendanceStatuses.value = await AttendanceSessionService.getAttendanceStatuses();
        console.log('Loaded attendance statuses:', attendanceStatuses.value);
        
        // Check for active sessions
        const activeSessions = await AttendanceSessionService.getActiveSessionsForTeacher(teacherId.value);
        if (activeSessions && activeSessions.length > 0) {
            // Find session for current subject/section
            const matchingSession = activeSessions.find(session => 
                session.section_id == sectionId.value && 
                session.subject_id == subjectId.value
            );
            
            if (matchingSession) {
                currentSession.value = matchingSession;
                sessionActive.value = true;
                console.log('Found active session:', matchingSession);
            }
        }
    } catch (error) {
        console.error('Error initializing attendance session:', error);
    }
};

// Function to load students data from database
const loadStudentsData = async () => {
    try {
        console.log('Loading students from database...');
        
        // First get teacher assignments to determine section/subject
        const assignments = await TeacherAttendanceService.getTeacherAssignments(teacherId.value);
        console.log('Teacher assignments:', assignments);
        
        if (!assignments || assignments.length === 0 || !assignments.assignments || assignments.assignments.length === 0) {
            console.warn('No assignments found for teacher, falling back to Grade 3 students');
            // Fallback to Grade 3 students if no assignments
            const studentsData = await AttendanceService.getStudentsByGrade(3);
            students.value = studentsData || [];
            
            if (students.value.length > 0) {
                const firstStudent = students.value[0];
                sectionId.value = firstStudent.current_section_id || 13;
                subjectName.value = 'Mathematics (Grade 3)';
                subjectId.value = 1;
            }
        } else {
            // Use the first assignment from the assignments array
            const assignmentData = assignments.assignments[0];
            const firstSubject = assignmentData.subjects[0];
            
            sectionId.value = assignmentData.section_id;
            subjectId.value = firstSubject.subject_id;
            subjectName.value = firstSubject.subject_name || 'Mathematics (Grade 3)';
            
            // Load students for this specific assignment
            const studentsResponse = await TeacherAttendanceService.getStudentsForTeacherSubject(
                teacherId.value,
                sectionId.value,
                subjectId.value
            );
            
            if (studentsResponse && studentsResponse.students) {
                students.value = studentsResponse.students.map(student => ({
                    id: student.id,
                    name: student.name || `${student.firstName} ${student.lastName}`,
                    firstName: student.firstName,
                    lastName: student.lastName,
                    current_section_id: sectionId.value,
                    studentId: student.studentId || student.id,
                    student_id: student.studentId || student.id
                }));
            } else {
                students.value = [];
            }
        }
        
        console.log('Loaded students for attendance:', students.value.length);
        console.log('Student names:', students.value.map(s => `${s.name} (ID: ${s.id})`));
        
        // Initialize attendance session system after we have section/subject info
        await initializeAttendanceSession();
        
        // Update unassigned students list
        calculateUnassignedStudents();
        
        return true;
    } catch (error) {
        console.error('Error loading students:', error);
        students.value = [];
        toast.add({
            severity: 'error',
            summary: 'Error Loading Students',
            detail: 'Could not load students for attendance.',
            life: 5000
        });
        return false;
    }
};

// Toggle edit mode
const toggleEditMode = () => {
    isEditMode.value = !isEditMode.value;

    if (isEditMode.value) {
        // Entering edit mode - refresh students data and calculate unassigned
        loadStudentsData();
    } else {
        // Exiting edit mode - save the current layout
        saveCurrentLayout(false);
    }
};

// Save current layout
const saveCurrentLayout = async (showToast = true) => {
    console.log('Saving current layout to database');

    try {
        // Prepare the layout data for the API
        const layout = {
            rows: rows.value,
            columns: columns.value,
            seatPlan: seatPlan.value,
            showTeacherDesk: showTeacherDesk.value,
            showStudentIds: showStudentIds.value
        };

        // Save to database via API
        await SeatingService.saveSeatingArrangement(
            sectionId.value,
            subjectId.value,
            teacherId.value,
            layout
        );

        // Also save to localStorage as backup
        localStorage.setItem(`seatPlan_${subjectId.value}`, JSON.stringify(layout));

        if (showToast) {
            toast.add({
                severity: 'success',
                summary: 'Layout Saved',
                detail: 'Seating arrangement has been saved to database',
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error saving layout to database:', error);
        
        // Fallback to localStorage only
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
                severity: 'warn',
                summary: 'Saved Locally',
                detail: 'Layout saved to local storage only (database error)',
                life: 5000
            });
        }
    }
};

// Load seating arrangement from database
const loadSeatingArrangementFromDatabase = async () => {
    try {
        console.log('Loading seating arrangement from database...');
        
        if (!sectionId.value || !teacherId.value) {
            console.log('Missing sectionId or teacherId, falling back to localStorage');
            return loadSavedLayout();
        }

        const response = await SeatingService.getSeatingArrangement(
            sectionId.value,
            teacherId.value,
            subjectId.value
        );

        if (response && response.seating_layout) {
            const layout = response.seating_layout;
            
            // Apply the loaded layout
            rows.value = layout.rows || rows.value;
            columns.value = layout.columns || columns.value;
            showTeacherDesk.value = layout.showTeacherDesk !== undefined ? layout.showTeacherDesk : showTeacherDesk.value;
            showStudentIds.value = layout.showStudentIds !== undefined ? layout.showStudentIds : showStudentIds.value;

            // Set the seat plan with deep copy to avoid reference issues
            if (layout.seatPlan) {
                seatPlan.value = JSON.parse(JSON.stringify(layout.seatPlan));
                console.log('Loaded seating arrangement from database');
                return true;
            }
        }
        
        // Fallback to localStorage if database doesn't have data
        console.log('No seating arrangement in database, trying localStorage...');
        return loadSavedLayout();
        
    } catch (error) {
        console.error('Error loading seating arrangement from database:', error);
        console.log('Falling back to localStorage...');
        return loadSavedLayout();
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
const getStudentById = (studentId) => {
    if (!studentId) return null;

    // Convert to string for comparison if needed
    const idStr = studentId.toString();

    // Find the student with the matching ID
    const student = students.value.find((s) => s.id.toString() === idStr);

    if (!student) {
        console.warn(`Student with ID ${studentId} not found`);
    }

    return student || null;
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
const saveAttendanceWithRemarks = async (status, remarks = '') => {
    const { rowIndex, colIndex } = selectedSeat.value;
    const seat = seatPlan.value[rowIndex][colIndex];

    // Update seat status
    seat.status = status;

    // Get the student
    const student = getStudentById(seat.studentId);

    // Add a timestamp for this attendance record (crucial for chronological ordering)
    const timestamp = new Date().toISOString();

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
            timestamp
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
        timestamp
    };

    // Save to localStorage
    localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));
    localStorage.setItem('remarksPanel', JSON.stringify(remarksPanel.value));

    // Also update the cache to ensure our most recent changes persist across refreshes
    const today = currentDate.value;
    const cacheKey = `attendanceCache_${subjectId.value}_${today}`;
    const cacheData = {
        timestamp,
        seatPlan: JSON.parse(JSON.stringify(seatPlan.value))
    };
    localStorage.setItem(cacheKey, JSON.stringify(cacheData));
    console.log('Updated attendance cache with latest status changes');

    // Save to database via API
    try {
        await saveAttendanceRecord(seat.studentId, status, remarks);
    } catch (error) {
        console.error('Failed to save to database, but local changes preserved:', error);
    }

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

// Enhanced save attendance using session system
const saveAttendanceRecord = async (studentId, status, remarks = '') => {
    try {
        // If we have an active session, use the session system
        if (sessionActive.value && currentSession.value && attendanceStatuses.value.length > 0) {
            try {
                // Map status to attendance status ID
                let statusId;
                const statusLower = status.toLowerCase();
                
                if (statusLower === 'present' || status === 1) {
                    statusId = attendanceStatuses.value.find(s => s.code === 'P')?.id;
                } else if (statusLower === 'absent' || status === 2) {
                    statusId = attendanceStatuses.value.find(s => s.code === 'A')?.id;
                } else if (statusLower === 'late' || status === 3) {
                    statusId = attendanceStatuses.value.find(s => s.code === 'L')?.id;
                } else if (statusLower === 'excused' || status === 4) {
                    statusId = attendanceStatuses.value.find(s => s.code === 'E')?.id;
                } else {
                    statusId = attendanceStatuses.value.find(s => s.code === 'P')?.id; // Default to Present
                }

                if (statusId) {
                    const attendanceData = AttendanceSessionService.formatAttendanceForAPI(
                        studentId,
                        statusId,
                        remarks
                    );
                    
                    const response = await AttendanceSessionService.markSessionAttendance(
                        currentSession.value.id,
                        [attendanceData]
                    );
                    
                    console.log('Attendance saved via session system:', response);
                    return response;
                }
            } catch (sessionError) {
                console.warn('Session attendance save failed, falling back to legacy:', sessionError);
            }
        }
        
        // Get attendance status ID based on status
        const getAttendanceStatusId = (status) => {
            if (typeof status === 'number') {
                return status; // Already an ID
            }
            // Map status strings to IDs (adjust based on your attendance_statuses table)
            const statusMap = {
                'present': 1,
                'absent': 2, 
                'late': 3,
                'excused': 4
            };
            return statusMap[status.toLowerCase()] || 1;
        };

        // Fallback to legacy system - format for markAttendance API
        const attendanceData = {
            section_id: sectionId.value,
            subject_id: subjectId.value,
            teacher_id: teacherId.value,
            date: currentDate.value,
            attendance: [{
                student_id: studentId,
                attendance_status_id: getAttendanceStatusId(status),
                remarks: remarks || null
            }]
        };

        console.log('Saving attendance record (legacy):', attendanceData);
        const response = await TeacherAttendanceService.markAttendance(attendanceData);
        console.log('Attendance saved successfully (legacy):', response);
        
        return response;
    } catch (error) {
        console.error('Error saving attendance record:', error);
        throw error;
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
        // Use the correct storage key for templates
        const savedData = localStorage.getItem('seatPlanTemplates');

        if (savedData) {
            const parsed = JSON.parse(savedData);
            if (Array.isArray(parsed)) {
                savedTemplates.value = parsed;
                console.log('Loaded saved templates:', savedTemplates.value.length);
            } else {
                console.warn('Saved template data is not an array, initializing empty array');
                savedTemplates.value = [];
            }
        } else {
            console.log('No saved templates found');
            savedTemplates.value = [];
        }
    } catch (error) {
        console.error('Error loading saved templates:', error);
        savedTemplates.value = [];
    }
};

// Calculate which students are not assigned to seats
const calculateUnassignedStudents = () => {
    const assignedStudentIds = new Set();

    // Collect all assigned student IDs from the seat plan
    seatPlan.value.forEach((row) => {
        row.forEach((seat) => {
            if (seat.isOccupied && seat.studentId) {
                assignedStudentIds.add(seat.studentId);
            }
        });
    });

    // Filter out assigned students from the full student list
    unassignedStudents.value = students.value.filter((student) => !assignedStudentIds.has(student.id));

    console.log('Total students:', students.value.length);
    console.log('Assigned students:', assignedStudentIds.size);
    console.log('Unassigned students:', unassignedStudents.value.length);
    console.log('Unassigned student names:', unassignedStudents.value.map(s => s.name));
};

const filteredUnassignedStudents = computed(() => {
    if (!searchQuery.value) return unassignedStudents.value;
    const query = searchQuery.value.toLowerCase();
    return unassignedStudents.value.filter((student) => student.name.toLowerCase().includes(query) || student.id.toString().includes(query));
});

// Fetch attendance history from service
const fetchAttendanceHistory = async () => {
    try {
        if (!subjectId.value) return;

        // Get current day's records from localStorage first, as these are the most recent
        const todayRecords = {};
        const today = currentDate.value;
        Object.keys(attendanceRecords.value).forEach((key) => {
            // Check if the record is for today
            if (key.includes(today)) {
                todayRecords[key] = attendanceRecords.value[key];
            }
        });

        // Now fetch server records - only if we need historical data
        // Use a valid student ID from the students array instead of seatingArrangement
        const firstStudent = students.value && students.value.length > 0 ? students.value[0] : null;
        const records = firstStudent ? await AttendanceService.getAttendanceRecords(firstStudent.id) : [];
        console.log('Fetched attendance records:', records);

        // Merge records, but give priority to localStorage records for today
        const mergedRecords = [...records];

        // Add today's records from localStorage (they take precedence)
        Object.values(todayRecords).forEach((record) => {
            // Check if this record already exists in the server records
            const existingIndex = mergedRecords.findIndex((r) => r.studentId === record.studentId && r.date === record.date);

            if (existingIndex >= 0) {
                // Replace the existing record
                mergedRecords[existingIndex] = record;
            } else {
                // Add the record
                mergedRecords.push(record);
            }
        });

        // Update seat plan with merged records, prioritizing the most recent
        updateSeatPlanStatuses(mergedRecords);
    } catch (error) {
        console.error('Error fetching attendance history:', error);

        // If server fetch fails, still use localStorage records
        const localRecords = Object.values(attendanceRecords.value);
        updateSeatPlanStatuses(localRecords);
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

// Add function to save roll call attendance with remarks
const saveRollCallAttendanceWithRemarks = async (status, remarks = '') => {
    if (!currentStudent.value) return;

    // Add a timestamp for this attendance record
    const timestamp = new Date().toISOString();

    // Save to attendance records
    const recordKey = `${currentStudent.value.id}-${currentDate.value}`;
    attendanceRecords.value[recordKey] = {
        studentId: currentStudent.value.id,
        date: currentDate.value,
        status,
        remarks: remarks || '',
        timestamp
    };

    // Update seat status if student is assigned to a seat
    const foundSeat = findSeatByStudentId(currentStudent.value.id);
    if (foundSeat) {
        // Update seat status
        foundSeat.status = status;

        // Save attendance with remarks
        const recordKey = `${currentStudent.value.id}-${currentDate.value}`;
        attendanceRecords.value[recordKey] = {
            studentId: currentStudent.value.id,
            date: currentDate.value,
            status,
            remarks: remarks || '',
            timestamp
        };
    }

    // Handle remarks panel for Absent/Excused status
    if (status === 'Present' || status === 'Late') {
        // Remove from remarks panel if exists
        remarksPanel.value = remarksPanel.value.filter((r) => r.studentId !== currentStudent.value.id);
    } else if (remarks) {
        // Update or add to remarks panel for Absent/Excused
        const remarkItem = {
            studentId: currentStudent.value.id,
            studentName: currentStudent.value.name,
            status,
            remarks,
            timestamp
        };

        const existingIndex = remarksPanel.value.findIndex((r) => r.studentId === currentStudent.value.id);
        if (existingIndex >= 0) {
            remarksPanel.value[existingIndex] = remarkItem;
        } else {
            remarksPanel.value.push(remarkItem);
        }
    }

    // Save to localStorage
    localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));
    localStorage.setItem('remarksPanel', JSON.stringify(remarksPanel.value));

    // Update cache
    const today = currentDate.value;
    const cacheKey = `attendanceCache_${subjectId.value}_${today}`;
    const cacheData = {
        timestamp,
        seatPlan: JSON.parse(JSON.stringify(seatPlan.value))
    };
    localStorage.setItem(cacheKey, JSON.stringify(cacheData));

    // Save to database via API
    try {
        await saveAttendanceRecord(currentStudent.value.id, status, remarks);
    } catch (error) {
        console.error('Failed to save roll call attendance to database, but local changes preserved:', error);
    }

    // Show success message
    toast.add({
        severity: 'success',
        summary: 'Attendance Marked',
        detail: `${currentStudent.value.name} marked as ${status}`,
        life: 3000
    });

    // Move to next student
    nextStudent();
};

// Start new attendance session
const startAttendanceSession = async () => {
    try {
        if (sessionActive.value) {
            toast.add({
                severity: 'warn',
                summary: 'Session Active',
                detail: 'An attendance session is already active for this subject.',
                life: 3000
            });
            return;
        }
        
        const sessionData = {
            teacherId: teacherId.value,
            sectionId: sectionId.value,
            subjectId: subjectId.value,
            date: currentDate.value,
            startTime: new Date().toTimeString().split(' ')[0],
            type: 'regular',
            metadata: {
                seatingArrangement: true,
                qrCodeEnabled: true
            }
        };
        
        const response = await AttendanceSessionService.createSession(sessionData);
        currentSession.value = response.session;
        sessionActive.value = true;
        
        toast.add({
            severity: 'success',
            summary: 'Session Started',
            detail: 'Attendance session started successfully',
            life: 3000
        });
        
        console.log('Started attendance session:', response.session);
    } catch (error) {
        console.error('Error starting attendance session:', error);
        toast.add({
            severity: 'error',
            summary: 'Session Error',
            detail: 'Failed to start attendance session',
            life: 5000
        });
    }
};

// Complete attendance session
const completeAttendanceSession = async () => {
    try {
        if (!sessionActive.value || !currentSession.value) {
            toast.add({
                severity: 'warn',
                summary: 'No Active Session',
                detail: 'No active attendance session to complete.',
                life: 3000
            });
            return;
        }
        
        const response = await AttendanceSessionService.completeSession(currentSession.value.id);
        sessionSummary.value = response.summary;
        sessionActive.value = false;
        currentSession.value = null;
        
        toast.add({
            severity: 'success',
            summary: 'Session Completed',
            detail: `Attendance session completed. ${response.summary?.total_students || 0} students processed.`,
            life: 5000
        });
        
        console.log('Completed attendance session:', response);
    } catch (error) {
        console.error('Error completing attendance session:', error);
        toast.add({
            severity: 'error',
            summary: 'Session Error',
            detail: 'Failed to complete attendance session',
            life: 5000
        });
    }
};

const markAllPresent = () => {
    if (confirm('Are you sure you want to mark all students as present?')) {
        // Mark all occupied seats as present
        seatPlan.value.forEach((row) => {
            row.forEach((seat) => {
                if (seat.isOccupied && seat.studentId) {
                    seat.status = 1; // Present status
                    
                    // Update attendance records
                    const recordKey = `${seat.studentId}-${currentDate.value}`;
                    attendanceRecords.value[recordKey] = {
                        studentId: seat.studentId,
                        date: currentDate.value,
                        status: 1,
                        timestamp: new Date().toISOString()
                    };
                }
            });
        });

        // Save to localStorage
        localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));

        toast.add({
            severity: 'success',
            summary: 'All Present',
            detail: 'All students have been marked as present',
            life: 3000
        });
    }
};

const resetAllAttendance = () => {
    if (confirm('Are you sure you want to reset all attendance statuses?')) {
        // Reset all seat statuses to null
        seatPlan.value.forEach((row) => {
            row.forEach((seat) => {
                if (seat.isOccupied) {
                    seat.status = null;
                }
            });
        });

        // Get the current date for filtering records
        const today = currentDate.value;

        // Identify keys to remove (all records for today)
        const keysToRemove = [];
        Object.keys(attendanceRecords.value).forEach((key) => {
            if (key.includes(today)) {
                keysToRemove.push(key);
            }
        });

        // Remove all of today's records
        keysToRemove.forEach((key) => {
            delete attendanceRecords.value[key];
        });

        // Clear remarks panel for today's students
        remarksPanel.value = remarksPanel.value.filter((remark) => {
            // Keep remarks from other days
            const recordKey = `${remark.studentId}-${today}`;
            return !keysToRemove.includes(recordKey);
        });

        // Update localStorage - make sure to save the cleaned data
        localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));
        localStorage.setItem('remarksPanel', JSON.stringify(remarksPanel.value));

        // Force clear any cached data that might be used on reload
        localStorage.removeItem(`attendanceCache_${subjectId.value}_${today}`);

        toast.add({
            severity: 'success',
            summary: 'Attendance Reset',
            detail: 'All attendance statuses for today have been cleared',
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

// Store interval reference for cleanup
let refreshInterval = null;

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

        // Load students directly from Grade 3 API to match dashboard
        await loadStudentsData();
        
        // Set up auto-refresh to catch new enrollments (store reference for cleanup)
        refreshInterval = setInterval(async () => {
            console.log('Auto-refreshing student data...');
            await loadStudentsData();
        }, 30000); // Refresh every 30 seconds (reduced frequency)

        // Initialize an empty seat plan for grid layout
        initializeSeatPlan();

        // Try to load seating arrangement from database first
        await loadSeatingArrangementFromDatabase();

        // Update time every second
        timeInterval.value = setInterval(() => {
            currentDateTime.value = new Date();
        }, 1000);

        // Always load saved templates first, so they're available for use
        await loadSavedTemplates();
        console.log('Templates loaded, count:', savedTemplates.value.length);

        // Load attendance records from localStorage if available
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

            // Load cached attendance data after loading layout
            const cachedDataLoaded = loadCachedAttendanceData();
            if (!cachedDataLoaded) {
                // If no cached data, apply attendance statuses from records
                applyAttendanceStatusesToSeatPlan();
            }
        }
    } catch (error) {
        console.error('Error initializing data:', error);
    }
});

// Function to check for and load cached attendance data for a specific date
const loadCachedAttendanceData = () => {
    // Reset all statuses first
    seatPlan.value.forEach((row) => {
        row.forEach((seat) => {
            if (seat.isOccupied) {
                seat.status = null;
            }
        });
    });
    const selectedDate = currentDate.value;
    const cacheKey = `attendanceCache_${subjectId.value}_${selectedDate}`;

    try {
        // First try to load from cache
        const cachedData = localStorage.getItem(cacheKey);
        if (cachedData) {
            const { timestamp, seatPlan: cachedSeatPlan } = JSON.parse(cachedData);
            console.log(`Found cached attendance data from ${new Date(timestamp).toLocaleTimeString()} for ${selectedDate}`);

            // Apply the cached seat plan to the current one
            if (cachedSeatPlan && Array.isArray(cachedSeatPlan)) {
                cachedSeatPlan.forEach((row, rowIndex) => {
                    if (rowIndex < seatPlan.value.length) {
                        row.forEach((cachedSeat, colIndex) => {
                            if (colIndex < seatPlan.value[rowIndex].length) {
                                // Only copy the status - preserve other seat properties
                                if (seatPlan.value[rowIndex][colIndex].studentId === cachedSeat.studentId) {
                                    seatPlan.value[rowIndex][colIndex].status = cachedSeat.status;
                                }
                            }
                        });
                    }
                });
                console.log('Applied cached attendance statuses');
                return true;
            }
        }

        // If no cache found, try to reconstruct from attendance records
        const dateRecords = {};
        Object.keys(attendanceRecords.value).forEach((key) => {
            // Check if the record is for the selected date
            const record = attendanceRecords.value[key];
            if (record.date === selectedDate) {
                dateRecords[key] = record;
            }
        });

        // If we found records for this date, apply them to the seat plan
        if (Object.keys(dateRecords).length > 0) {
            console.log(`Found ${Object.keys(dateRecords).length} attendance records for ${selectedDate}`);

            // Reset all statuses first
            seatPlan.value.forEach((row) => {
                row.forEach((seat) => {
                    if (seat.isOccupied) {
                        seat.status = null;
                    }
                });
            });

            // Apply the statuses from records
            let appliedCount = 0;
            seatPlan.value.forEach((row) => {
                row.forEach((seat) => {
                    if (seat.isOccupied && seat.studentId) {
                        // Check both formats of record keys for compatibility
                        const recordKey1 = `${seat.studentId}-${selectedDate}`;
                        const recordKey2 = `${seat.studentId}_${selectedDate}`;

                        const record = attendanceRecords.value[recordKey1] || attendanceRecords.value[recordKey2];

                        if (record) {
                            seat.status = record.status;
                            appliedCount++;
                        }
                    }
                });
            });

            console.log(`Applied ${appliedCount} attendance statuses from records for ${selectedDate}`);
            return appliedCount > 0;
        }
    } catch (error) {
        console.error('Error loading attendance data for date:', selectedDate, error);
    }
    return false;
};

// Show status selection dialog
const showStatusSelection = (rowIndex, colIndex) => {
    // Store the selected seat for reference in the status selection handler
    selectedSeat.value = { rowIndex, colIndex };

    // Open the status selection dialog
    showAttendanceDialog.value = true;

    // Clear any pending status/remarks
    pendingStatus.value = '';
    attendanceRemarks.value = '';

    // Log for debugging
    console.log('Opening status selection dialog for seat:', rowIndex, colIndex, 'Student:', seatPlan.value[rowIndex][colIndex].studentId, 'Current status:', seatPlan.value[rowIndex][colIndex].status);
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
    if (!student) return;

    console.log(`Marking student ${student.name} (ID: ${student.id}) as present`);

    // First check if the student is assigned to a seat
    let seatFound = false;

    // Search all seats
    for (let i = 0; i < seatPlan.value.length && !seatFound; i++) {
        for (let j = 0; j < seatPlan.value[i].length && !seatFound; j++) {
            if (seatPlan.value[i][j].studentId === student.id) {
                // Update seat status
                seatPlan.value[i][j].status = 'Present';
                seatFound = true;

                console.log(`Found student ${student.name} in seat at row ${i}, col ${j}`);
            }
        }
    }

    // If student not found in any seat, log a warning but still mark attendance
    if (!seatFound) {
        console.warn(`Student ${student.name} not found in any seat. Marking attendance in records only.`);
    }

    // Save attendance record with timestamp
    const recordKey = `${student.id}-${currentDate.value}`;
    const timestamp = new Date().toISOString();

    attendanceRecords.value[recordKey] = {
        studentId: student.id,
        date: currentDate.value,
        status: 'Present',
        remarks: '',
        timestamp
    };

    // Save to localStorage
    localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));

    // Also update the cache to ensure our most recent changes persist across refreshes
    const today = currentDate.value;
    const cacheKey = `attendanceCache_${subjectId.value}_${today}`;
    const cacheData = {
        timestamp,
        seatPlan: JSON.parse(JSON.stringify(seatPlan.value))
    };
    localStorage.setItem(cacheKey, JSON.stringify(cacheData));
    console.log('Attendance for student marked as Present and saved to records and cache');
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
    // Stop scanning to release camera resources
    scanning.value = false;
    console.log('Component unmounting, camera resources released');

    // Clear the time interval
    if (timeInterval.value) {
        clearInterval(timeInterval.value);
        timeInterval.value = null;
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
    // Check if this is a status that needs remarks
    if (status === 'Absent' || status === 'Excused') {
        // Store the pending status and open remarks dialog
        pendingStatus.value = status;
        showRemarksDialog.value = true;
        return;
    }

    // For Present/Late statuses, no remarks needed so save immediately
    saveAttendanceWithRemarks(status);

    // Extra step to ensure changes are saved properly
    const today = currentDate.value;
    const cacheKey = `attendanceCache_${subjectId.value}_${today}`;
    const cacheData = {
        timestamp: new Date().toISOString(),
        seatPlan: JSON.parse(JSON.stringify(seatPlan.value))
    };
    localStorage.setItem(cacheKey, JSON.stringify(cacheData));
};

// Save remarks for the selected status
const saveRemarks = () => {
    if (!pendingStatus.value) return;

    // Don't allow empty remarks for Absent/Excused status
    if ((!attendanceRemarks.value || attendanceRemarks.value.trim() === '') && (pendingStatus.value === 'Absent' || pendingStatus.value === 'Excused')) {
        toast.add({
            severity: 'warn',
            summary: 'Remarks Required',
            detail: 'Please enter remarks for this status',
            life: 3000
        });
        return;
    }

    // Save attendance with the entered remarks
    saveAttendanceWithRemarks(pendingStatus.value, attendanceRemarks.value);

    // Extra step to ensure changes are saved properly
    const today = currentDate.value;
    const cacheKey = `attendanceCache_${subjectId.value}_${today}`;
    const cacheData = {
        timestamp: new Date().toISOString(),
        seatPlan: JSON.parse(JSON.stringify(seatPlan.value))
    };
    localStorage.setItem(cacheKey, JSON.stringify(cacheData));
};

// Fix openAttendanceMethodDialog function
const openAttendanceMethodDialog = () => {
    // Reset any previous selections
    showQRScanner.value = false;
    showRollCall.value = false;
    scannedStudents.value = [];

    // Open the attendance method selection dialog
    showAttendanceMethodModal.value = true;

    console.log('Opening attendance method dialog', showAttendanceMethodModal.value);
};

// Simplified QR scanner functions using vue-qrcode-reader
const startQRScanner = () => {
    // Close the method selection dialog and show QR scanner
    showAttendanceMethodModal.value = false;
    showQRScanner.value = true;
    showRollCall.value = false;
    scanning.value = true;

    console.log('Starting QR scanner...');

    toast.add({
        severity: 'info',
        summary: 'QR Scanner',
        detail: 'QR Scanner is active. Scan student ID QR codes.',
        life: 3000
    });
};

const stopQRScanner = () => {
    console.log('Stopping QR scanner...');
    scanning.value = false;
    showQRScanner.value = false;
};

const onDetect = async (detectedCodes) => {
    try {
        console.log('QR Code Detected:', detectedCodes);
        if (detectedCodes.length > 0) {
            // Pause scanning while processing to avoid multiple scans of the same code
            scanning.value = false;

            const qrData = detectedCodes[0].rawValue;
            console.log('Detected QR code:', qrData);

            // Process the QR code data
            await processQRCode(qrData);

            // Wait a moment before restarting the scanner to avoid rapid scanning
            setTimeout(() => {
                scanning.value = true;
            }, 1000);
        }
    } catch (error) {
        console.error('Error in QR code detection:', error);
        toast.add({
            severity: 'error',
            summary: 'QR Error',
            detail: 'Error processing QR code: ' + error.message,
            life: 3000
        });

        // Restart scanner after error
        setTimeout(() => {
            scanning.value = true;
        }, 2000);
    }
};

const onCameraError = (error) => {
    console.error('Camera Error:', error);
    cameraError.value = error.message || 'Failed to access camera';
    scanning.value = false;

    toast.add({
        severity: 'error',
        summary: 'Camera Error',
        detail: error.message || 'Failed to access camera',
        life: 5000
    });
};

const restartCamera = () => {
    cameraError.value = null;
    scanning.value = false;

    // Use a short timeout to ensure component unmounts and remounts properly
    setTimeout(() => {
        scanning.value = true;
        console.log('Camera restarted');
    }, 500);
};

const processQRCode = async (qrData) => {
    try {
        console.log('Processing QR code:', qrData);

        // Validate QR code using the backend API
        const validationResponse = await QRCodeAPIService.validateQRCode(qrData.trim());
        
        if (!validationResponse.valid) {
            throw new Error('Invalid QR code or QR code not registered');
        }

        const studentData = validationResponse.student;
        const studentId = studentData.id;

        // Find the student in our current student list
        const student = getStudentById(studentId);

        if (!student) {
            throw new Error(`Student ${studentData.firstName} ${studentData.lastName} is not in this class`);
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
            detail: `${student.name} marked as present via QR code`,
            life: 2000
        });

        return true;
    } catch (error) {
        console.error('Error processing QR code:', error);
        toast.add({
            severity: 'error',
            summary: 'QR Error',
            detail: error.message,
            life: 3000
        });
        return false;
    }
};

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
    // Call the existing function from earlier in the file
    const foundSeat = findSeatByStudentId(currentStudent.value.id);
    if (foundSeat) {
        foundSeat.status = status;
    }
    
    // Save attendance record
    const recordKey = `${currentStudent.value.id}-${currentDate.value}`;
    attendanceRecords.value[recordKey] = {
        studentId: currentStudent.value.id,
        date: currentDate.value,
        status,
        remarks: '',
        timestamp: new Date().toISOString()
    };
    
    // Save to localStorage and database
    localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));
    saveAttendanceRecord(currentStudent.value.id, status, '');
    
    nextStudent();
};

// Helper function to find seat by student ID
const findSeatByStudentId = (studentId) => {
    for (let i = 0; i < seatPlan.value.length; i++) {
        for (let j = 0; j < seatPlan.value[i].length; j++) {
            if (seatPlan.value[i][j].studentId === studentId) {
                return seatPlan.value[i][j];
            }
        }
    }
    return null;
};

// Helper function to move to next student in roll call
const nextStudent = () => {
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

    // Save roll call attendance with remarks
    const foundSeat = findSeatByStudentId(currentStudent.value.id);
    if (foundSeat) {
        foundSeat.status = pendingRollCallStatus.value;
    }
    
    // Save attendance record
    const recordKey = `${currentStudent.value.id}-${currentDate.value}`;
    attendanceRecords.value[recordKey] = {
        studentId: currentStudent.value.id,
        date: currentDate.value,
        status: pendingRollCallStatus.value,
        remarks: rollCallRemarks.value,
        timestamp: new Date().toISOString()
    };
    
    // Update remarks panel if needed
    if (pendingRollCallStatus.value === 'Absent' || pendingRollCallStatus.value === 'Excused') {
        updateRemarksPanel(currentStudent.value.id, pendingRollCallStatus.value, rollCallRemarks.value);
    }
    
    // Save to localStorage and database
    localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));
    saveAttendanceRecord(currentStudent.value.id, pendingRollCallStatus.value, rollCallRemarks.value);
    
    // Reset dialog values
    showRollCallRemarksDialog.value = false;
    rollCallRemarks.value = '';
    pendingRollCallStatus.value = '';
    
    nextStudent();
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

    // Group records by student ID to ensure we're using the most recent record for each student
    const studentLatestRecords = {};

    // Process all records to find the most recent one for each student
    Object.entries(attendanceRecords.value).forEach(([key, record]) => {
        // Only process records for the current day
        if (!key.includes(today)) return;

        const studentId = record.studentId;

        // If we don't have a record for this student yet, or this record is newer
        if (!studentLatestRecords[studentId] || (record.timestamp && studentLatestRecords[studentId].timestamp && new Date(record.timestamp) > new Date(studentLatestRecords[studentId].timestamp))) {
            studentLatestRecords[studentId] = record;
        }
    });

    // Apply attendance statuses to the seat plan using the most recent records
    seatPlan.value.forEach((row) => {
        row.forEach((seat) => {
            if (seat.isOccupied && seat.studentId) {
                // Get the latest record for this student
                const latestRecord = studentLatestRecords[seat.studentId];

                if (latestRecord) {
                    // Apply the status from the record
                    seat.status = latestRecord.status;
                    console.log(`Applied status ${latestRecord.status} to student ${seat.studentId} (timestamp: ${latestRecord.timestamp || 'none'})`);
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

// Function to navigate to attendance records page
const viewAttendanceRecords = () => {
    router.push('/teacher/attendance-records');
};

// Cleanup function for intervals
onUnmounted(() => {
    if (timeInterval.value) {
        clearInterval(timeInterval.value);
    }
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});

// Update watch for currentDate to reapply statuses when date changes
watch(currentDate, (newDate) => {
    // Check if there's cached data for the selected date
    const cachedDataLoaded = loadCachedAttendanceData();
    if (!cachedDataLoaded) {
        // If no cached data, apply attendance statuses from records
        applyAttendanceStatusesToSeatPlan();
    }

    // Show a notification that we're viewing a past date's attendance
    if (newDate !== new Date().toISOString().split('T')[0]) {
        toast.add({
            severity: 'info',
            summary: 'Historical View',
            detail: `Viewing attendance records for ${new Date(newDate).toLocaleDateString()}`,
            life: 3000
        });
    }
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

// Fix reference to isDropTarget
const isDropTarget = ref(false);
</script>

<template>
    <div class="attendance-container p-4">
        <!-- Header with title and date/time -->
        <div class="header-section flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">{{ subjectName }} Attendance</h2>
                <div class="text-sm text-gray-600 flex align-items-center gap-3">
                    <div class="flex align-items-center">
                        <i class="pi pi-calendar mr-2"></i>
                        <Calendar v-model="currentDate" dateFormat="yy-mm-dd" :showIcon="true" />
                    </div>
                    <!-- Session Status Indicator -->
                    <div v-if="sessionActive" class="flex align-items-center text-green-600">
                        <i class="pi pi-circle-fill mr-1 text-xs"></i>
                        <span class="text-sm font-semibold">Session Active</span>
                    </div>
                    <div v-else class="flex align-items-center text-gray-500">
                        <i class="pi pi-circle mr-1 text-xs"></i>
                        <span class="text-sm">No Active Session</span>
                    </div>
                </div>
            </div>
            <div class="date-time-display">
                <div class="date">{{ new Date().toLocaleDateString() }}</div>
                <div class="time">{{ currentDateTime.toLocaleTimeString() }}</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons flex flex-wrap gap-2 mb-4">
            <Button icon="pi pi-pencil" label="Edit Seats" class="p-button-success" :class="{ 'p-button-outlined': !isEditMode }" @click="toggleEditMode" />

            <Button icon="pi pi-save" label="Save as Template" class="p-button-outlined" @click="showTemplateSaveDialog = true" />

            <Button icon="pi pi-list" label="Load Template" class="p-button-outlined" @click="showTemplateManager = true" />

            <Button icon="pi pi-play" label="Start Session" class="p-button-success" @click="startAttendanceSession" v-if="!sessionActive" />
            
            <Button icon="pi pi-check-circle" label="Mark All Present" class="p-button-success" @click="markAllPresent" />
            
            <Button icon="pi pi-stop" label="Complete Session" class="p-button-warning" @click="completeAttendanceSession" v-if="sessionActive" />

            <Button icon="pi pi-refresh" label="Reset Attendance" class="p-button-outlined" @click="resetAllAttendance" />

            <Button icon="pi pi-table" label="View Records" class="p-button-info" @click="viewAttendanceRecords" />
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
        <Dialog v-model:visible="showQRScanner" header="QR Scanner" :modal="true" :closable="true" style="width: 500px" @hide="stopQRScanner">
            <div class="p-4">
                <div class="scanner-container" :class="{ 'scanning-active': scanning }">
                    <!-- Show camera feed when scanning -->
                    <QrcodeStream v-if="scanning && !cameraError" @detect="onDetect" @error="onCameraError" class="qr-scanner" :torch="false" :camera="'auto'" :track="true"></QrcodeStream>

                    <!-- Show paused message when not scanning -->
                    <div v-else-if="!scanning && !cameraError" class="scanner-paused">
                        <i class="pi pi-camera-off"></i>
                        <p>Scanner paused</p>
                    </div>

                    <!-- Show error message when camera fails -->
                    <div v-else class="scanner-error">
                        <i class="pi pi-exclamation-triangle"></i>
                        <p>{{ cameraError || 'Camera error occurred' }}</p>
                        <button @click="restartCamera" class="restart-button">
                            <i class="pi pi-refresh"></i>
                            Retry Camera
                        </button>
                    </div>

                    <!-- Scanner overlay with corners -->
                    <div class="scanner-overlay">
                        <div class="scanner-corners">
                            <span></span>
                        </div>
                    </div>
                </div>

                <div v-if="scannedStudents.length > 0" class="mt-4">
                    <h4 class="text-sm font-semibold mb-2">Students Marked Present: {{ scannedStudents.length }}</h4>
                    <ul class="text-sm max-h-60 overflow-y-auto border border-gray-200 rounded p-2 bg-gray-50">
                        <li v-for="id in scannedStudents" :key="id" class="py-2 px-3 bg-green-50 rounded mb-1 flex items-center">
                            <i class="pi pi-check-circle text-green-500 mr-2"></i>
                            {{ getStudentById(id)?.name || `Student ID: ${id}` }}
                        </li>
                    </ul>
                </div>

                <div class="flex justify-between mt-4">
                    <div>
                        <Button icon="pi pi-replay" label="Reset" class="p-button-outlined p-button-danger" @click="scannedStudents = []" :disabled="scannedStudents.length === 0" />
                        <Button icon="pi pi-pause" v-if="scanning" label="Pause" class="p-button-outlined ml-2" @click="scanning = false" />
                        <Button icon="pi pi-play" v-else label="Resume" class="p-button-outlined ml-2" @click="scanning = true" />
                    </div>
                    <Button icon="pi pi-times" label="Close" @click="stopQRScanner" />
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
    aspect-ratio: 1/1;
}

.seat {
    height: 100%;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    transition: all 0.2s ease;
    aspect-ratio: 1/1;
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

.qr-scanner-container {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.qr-scanner-container video {
    border: 2px solid #2563eb;
    min-height: 300px;
    background-color: #000;
    border-radius: 8px;
    position: relative;
}

.qr-scanner-container video::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border: 2px solid rgba(59, 130, 246, 0.5);
    border-radius: 8px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        opacity: 0.6;
        transform: scale(1);
    }
    50% {
        opacity: 0.3;
        transform: scale(0.98);
    }
    100% {
        opacity: 0.6;
        transform: scale(1);
    }
}

.scanner-container {
    position: relative;
    width: 100%;
    height: 300px;
    border: 2px dashed #2563eb;
    border-radius: 8px;
    overflow: hidden;
}

.scanner-paused {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 255, 255, 0.8);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #2563eb;
}

.scanner-error {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(255, 0, 0, 0.8);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: white;
}

.scanner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border: 2px dashed #2563eb;
    border-radius: 8px;
    pointer-events: none;
}

.scanner-corners {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 8px;
    pointer-events: none;
}

.scanner-corners span {
    position: absolute;
    width: 20px;
    height: 20px;
    background-color: #2563eb;
    border-radius: 50%;
}

.scanner-corners span:nth-child(1) {
    top: 0;
    left: 0;
}
.scanner-corners span:nth-child(2) {
    top: 0;
    right: 0;
}
.scanner-corners span:nth-child(3) {
    bottom: 0;
    left: 0;
}
.scanner-corners span:nth-child(4) {
    bottom: 0;
    right: 0;
}

.qr-scanner {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #000;
    border-radius: 8px;
    overflow: hidden;
}

.restart-button {
    background-color: #2563eb;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 1rem;
}

.restart-button:hover {
    background-color: #1d4fb8;
}

/* Date and time display styles */
.date-time-display {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    font-size: 0.9rem;
}

.date-time-display .date {
    font-weight: 500;
    color: #4b5563;
}

.date-time-display .time {
    font-weight: 600;
    color: #2563eb;
}
</style>
