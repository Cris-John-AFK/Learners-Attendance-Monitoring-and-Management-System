<script setup>
import AttendanceCompletionModal from '@/components/AttendanceCompletionModal.vue';
import AttendanceEditDialog from '@/components/AttendanceEditDialog.vue';
import AttendanceReasonDialog from '@/components/AttendanceReasonDialog.vue';
import { QRCodeAPIService } from '@/router/service/QRCodeAPIService';
import { AttendanceService } from '@/router/service/Students';
import { TeacherAttendanceService } from '@/router/service/TeacherAttendanceService';
import AttendanceSessionService from '@/services/AttendanceSessionService';
import NotificationService from '@/services/NotificationService';
import SeatingService from '@/services/SeatingService';
import TeacherAuthService from '@/services/TeacherAuthService';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import RadioButton from 'primevue/radiobutton';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch, watchEffect } from 'vue';
import { QrcodeStream } from 'vue-qrcode-reader';
import { useRoute, useRouter } from 'vue-router';

// Add Dialog component if not already imported

const route = useRoute();
const router = useRouter();
const toast = useToast();

// Define props for subject routing
const props = defineProps({
    subjectId: {
        type: String,
        default: ''
    },
    subjectName: {
        type: String,
        default: 'Subject'
    }
});

// Initialize subject info immediately from route/props
const getInitialSubjectInfo = () => {
    if (props.subjectId && props.subjectName) {
        return { id: props.subjectId, name: props.subjectName };
    } else if (route.path.includes('/subject/homeroom')) {
        return { id: '2', name: 'Homeroom' };
    } else if (route.path.includes('/subject/english')) {
        return { id: 'english', name: 'English' };
    } else if (route.path.includes('/subject/filipino')) {
        return { id: 'filipino', name: 'Filipino' };
    } else if (route.path.includes('/subject/mathematics')) {
        return { id: '1', name: 'Mathematics' };
    } else if (route.params.subjectId) {
        const id = route.params.subjectId;
        // Map common subject IDs to proper names
        const subjectNames = {
            english: 'English',
            filipino: 'Filipino',
            mathematics: 'Mathematics',
            science: 'Science'
        };
        return { id, name: subjectNames[id] || 'Loading...' };
    }
    return { id: '1', name: 'Mathematics' };
};

const initialSubject = getInitialSubjectInfo();
const subjectName = ref(initialSubject.name);
const subjectId = ref(initialSubject.id);
const sectionId = ref('');
const teacherId = ref(null); // Will be set from authenticated teacher
const currentDate = ref(new Date()); // Use Date object for DatePicker compatibility
const currentDateTime = ref(new Date());

// Computed property to get date string for API calls (using LOCAL timezone, NOT UTC)
const currentDateString = computed(() => {
    if (currentDate.value instanceof Date) {
        // Use local timezone, NOT UTC to avoid date shifting
        const year = currentDate.value.getFullYear();
        const month = String(currentDate.value.getMonth() + 1).padStart(2, '0');
        const day = String(currentDate.value.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    return currentDate.value;
});

// Computed property to get current section name
const currentSectionName = computed(() => {
    return route.query.sectionName || 'Unknown Section';
});

// Function to ensure date is always current
const ensureCurrentDate = () => {
    const today = new Date();
    // Set to today's date, removing time component
    today.setHours(0, 0, 0, 0);
    currentDate.value = today;
    console.log('✅ Date set to (LOCAL TIMEZONE):', currentDateString.value);
    console.log('Current time:', new Date().toLocaleString('en-PH', { timeZone: 'Asia/Manila' }));
};

// Loading states
const isLoadingSeating = ref(false);
const loadingMessage = ref('');
const isLoadingStudents = ref(false);

// Attendance Session Management
const currentSession = ref(null);
const attendanceStatuses = ref([]);
const sessionActive = ref(false);
const sessionSummary = ref(null);

// Watch for date changes and update attendance display (removed duplicate - see line 2957)

// Timer reference for cleanup
const timeInterval = ref(null);

// Modals and UI states
const showTemplateManager = ref(false);
const showTemplateSaveDialog = ref(false);
const isEditMode = ref(false);

// QR Scanner states
const showQRScanner = ref(false);
const isScanning = ref(false);
const scannedStudents = ref([]);
const lastScannedStudent = ref(null);

// Track attendance method for notifications
const attendanceMethod = ref('seat_plan'); // 'seat_plan', 'roll_call', or 'qr'

// Attendance Reason Dialog states
const showReasonDialog = ref(false);
const reasonDialogType = ref('late'); // 'late' or 'excused'
const pendingAttendanceUpdate = ref(null); // Store student info while dialog is open

// Attendance Edit Dialog states
const showEditDialog = ref(false);
const editSessionData = ref(null);

// Hover quick actions states
const hoveredSeat = ref(null); // { row, col }
const quickActionsPosition = ref({ top: 0, left: 0 });

// Loading states
const isCompletingSession = ref(false);
const sessionCompletionProgress = ref(0);

// Floating panel states
const panelPosition = ref({ x: 20, y: 100 }); // Initial position
const isMinimized = ref(false);
const isDraggingPanel = ref(false);
const dragOffset = ref({ x: 0, y: 0 });

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
const scanning = ref(false);
const cameraError = ref(null);
const currentStudentIndex = ref(0);
const currentStudent = ref(null);

// Rename to avoid conflict
const showAttendanceDialog = ref(false);

// Attendance Completion Modal state
const showCompletionModal = ref(false);
const completedSessionData = ref(null);
const modalDismissedToday = ref(false);
const completionModalTimer = ref(null);
const showSessionDetailsDialog = ref(false);

// Function to fetch actual subject details from API
const fetchSubjectDetails = async (subjectIdentifier) => {
    console.log(`📡 Starting API call for subject: ${subjectIdentifier}`);
    try {
        // If it's already a number, fetch by ID
        if (!isNaN(subjectIdentifier)) {
            console.log(`🔢 Fetching by ID: ${subjectIdentifier}`);
            const response = await fetch(`http://localhost:8000/api/subjects/${subjectIdentifier}`);
            console.log(`📊 Response status: ${response.status}`);
            if (response.ok) {
                const subject = await response.json();
                console.log(`📋 Subject data:`, subject);
                return { id: subject.id, name: subject.name || 'Subject' };
            }
        } else {
            // If it's a string (like "hekasi"), find by name/code
            console.log(`🔤 Fetching all subjects to find: ${subjectIdentifier}`);
            const response = await fetch(`http://localhost:8000/api/subjects`);
            console.log(`📊 All subjects response status: ${response.status}`);
            if (response.ok) {
                const subjects = await response.json();
                console.log(`📚 All subjects:`, subjects);

                // First try exact match
                let subject = subjects.find((s) => s.name.toLowerCase() === subjectIdentifier.toLowerCase() || s.code.toLowerCase() === subjectIdentifier.toLowerCase());

                // If no exact match, try special mappings
                if (!subject) {
                    const subjectMappings = {
                        technologyandlivelihoodeducation: 'Technology and Livelihood Education',
                        technology: 'Technology and Livelihood Education',
                        tle: 'Technology and Livelihood Education',
                        arts: 'Arts',
                        english: 'English',
                        filipino: 'Filipino',
                        mathematics: 'Mathematics',
                        science: 'Science'
                    };

                    const mappedName = subjectMappings[subjectIdentifier.toLowerCase()];
                    if (mappedName) {
                        subject = subjects.find((s) => s.name.toLowerCase() === mappedName.toLowerCase());
                        console.log(`🔄 Using mapping: ${subjectIdentifier} → ${mappedName}`);
                    }
                }

                // If still no match, try partial match
                if (!subject) {
                    subject = subjects.find((s) => s.name.toLowerCase().includes(subjectIdentifier.toLowerCase()) || subjectIdentifier.toLowerCase().includes(s.name.toLowerCase().replace(/\s+/g, '')));
                    console.log(`🔍 Trying partial match for: ${subjectIdentifier}`);
                }

                console.log(`🎯 Found matching subject:`, subject);
                if (subject) {
                    return { id: subject.id, name: subject.name };
                }
            }
        }
    } catch (error) {
        console.error('❌ Error fetching subject details:', error);
    }
    console.log(`⚠️ Returning default subject data`);
    return { id: null, name: 'Subject' };
};

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
            const resolvedSubjectId = getResolvedSubjectId();
            const matchingSession = activeSessions.find((session) => session.section_id == sectionId.value && session.subject_id == resolvedSubjectId);

            if (matchingSession) {
                // Check if session is from today - don't auto-resume old sessions
                const sessionDate = new Date(matchingSession.session_date || matchingSession.created_at);
                const today = new Date();

                // Use UTC dates to avoid timezone issues
                const sessionDateUTC = sessionDate.toISOString().split('T')[0]; // YYYY-MM-DD
                const todayUTC = today.toISOString().split('T')[0]; // YYYY-MM-DD
                const isToday = sessionDateUTC === todayUTC;

                console.log('Session date check:');
                console.log('- Session date (original):', matchingSession.session_date);
                console.log('- Session date (parsed):', sessionDate.toISOString());
                console.log('- Session date (UTC):', sessionDateUTC);
                console.log('- Today date (UTC):', todayUTC);
                console.log('- Is today?', isToday);

                if (isToday && !isEditMode.value) {
                    // Only auto-restore if not in edit mode
                    currentSession.value = matchingSession;
                    sessionActive.value = true;
                    console.log('Found active session from today:', matchingSession);
                    console.log('Session details - ID:', matchingSession.id, 'Date:', matchingSession.session_date, 'Status:', matchingSession.status);
                } else if (isToday && isEditMode.value) {
                    console.log('⏭️ Skipping session restoration - user is in edit mode');
                } else {
                    console.log('Found old active session from', sessionDate.toDateString(), '- not resuming');
                    // Optionally auto-complete old sessions
                    try {
                        await AttendanceSessionService.completeSession(matchingSession.id);
                        console.log('Auto-completed old session:', matchingSession.id);
                    } catch (error) {
                        console.error('Error auto-completing old session:', error);
                    }
                }
            }
        }
    } catch (error) {
        console.error('Error initializing attendance session:', error);
    }
};

// Initialize authenticated teacher data
const initializeTeacherData = async () => {
    try {
        // Check if teacher is authenticated first
        if (!TeacherAuthService.isAuthenticated()) {
            console.warn('Teacher not authenticated, redirecting to login');
            router.push('/');
            return;
        }

        // Get teacher data from stored authentication
        const teacherData = TeacherAuthService.getTeacherData();
        if (teacherData && teacherData.teacher) {
            teacherId.value = teacherData.teacher.id;
            console.log('Initialized teacher ID from stored data:', teacherId.value);
        } else {
            console.warn('No stored teacher data found, redirecting to login');
            router.push('/');
        }
    } catch (error) {
        console.error('Error initializing teacher data:', error);
        router.push('/');
    }
};

// AGGRESSIVE CACHING - Load once, use forever
const permanentCache = reactive({
    students: null,
    sections: null,
    seatingArrangement: null,
    teacherData: null,
    timestamp: null
});

// Simple data cache for temporary storage
const dataCache = reactive({
    students: null,
    sections: null,
    timestamp: null
});

// Request deduplication to prevent multiple identical API calls
const pendingRequests = new Map();
const CACHE_FOREVER = true; // Since teacher/section/students don't change during session

// Check if permanent cache exists (load once, use forever)
const isPermanentlyCached = (cacheKey) => {
    return permanentCache[cacheKey] !== null;
};

// Check if cache is valid (for dataCache)
const isCacheValid = (cacheKey) => {
    if (!dataCache[cacheKey] || !dataCache.timestamp) {
        return false;
    }
    // Cache is valid for 5 minutes
    const cacheAge = Date.now() - dataCache.timestamp;
    return cacheAge < 5 * 60 * 1000; // 5 minutes in milliseconds
};

// Only clear cache on explicit user action (not automatic)
const clearCache = () => {
    console.log('🗑️ Clearing permanent cache (user requested)');
    permanentCache.students = null;
    permanentCache.sections = null;
    permanentCache.seatingArrangement = null;
    permanentCache.timestamp = null;
};

// Lightweight preload - only cache essential data
const preloadData = async () => {
    console.log('🚀 Preloading essential data...');

    try {
        // Only preload sections if not cached (students will be loaded per-section)
        if (!isCacheValid('sections')) {
            const sectionsResponse = await fetch('http://127.0.0.1:8000/api/sections');
            const sectionsData = await sectionsResponse.json();
            permanentCache.sections = sectionsData.sections || sectionsData || [];
            permanentCache.timestamp = Date.now();
            console.log('📦 Preloaded sections');
        } else {
            console.log('📦 Sections already cached');
        }
        console.log('✅ Preload completed');
    } catch (error) {
        console.warn('⚠️ Preload failed, will use regular loading:', error);
    }
};

// Function to load students data with caching
const loadStudentsData = async () => {
    // 🚀 PERFORMANCE: Prevent duplicate calls
    if (isLoadingStudents.value) {
        console.log('⏸️ Students already loading, skipping duplicate call');
        return;
    }

    try {
        // Set loading flag at the start
        isLoadingStudents.value = true;

        // Check cache first
        if (isCacheValid('students') && permanentCache.students) {
            console.log('📦 Using cached student data');
            students.value = permanentCache.students;
            await nextTick();
            calculateUnassignedStudents();

            // Even with cached students, we need to ensure seating arrangement is loaded
            // Check if we already have a seating arrangement loaded
            const hasSeatingArrangement = seatPlan.value.some((row) => row.some((seat) => seat.studentId !== null));

            if (!hasSeatingArrangement) {
                console.log('📦 Cached students loaded but no seating arrangement, loading seating...');
                const restored = restorePreservedAssignments();
                if (!restored) {
                    await loadSeatingArrangementFromDatabase();
                }
            } else {
                console.log('📦 Cached students and seating arrangement both available');
            }

            return true;
        }
        
        console.log('🔄 Loading students from database...');
        
        // Set loading animation flags
        isLoadingSeating.value = true;
        loadingMessage.value = 'Loading students and seating arrangement...';

        // Ensure teacher is initialized first
        if (!teacherId.value) {
            await initializeTeacherData();
        }

        if (!teacherId.value) {
            throw new Error('Teacher not authenticated');
        }

        // Determine which section to load students from
        console.log('🔍 Determining section for teacher ID:', teacherId.value, 'subject ID:', subjectId.value);

        try {
            // First, check if we have route parameters that specify a section
            const routeSectionName = route.query.sectionName;
            let targetSectionId = null;
            let targetSectionName = '';

            if (routeSectionName) {
                console.log('🎯 Route specifies section:', routeSectionName);

                // Check sections cache first
                let allSections;
                if (isCacheValid('sections') && permanentCache.sections) {
                    console.log('📦 Using cached sections data');
                    allSections = permanentCache.sections;
                } else {
                    console.log('🔄 Fetching sections from database...');
                    const sectionsResponse = await fetch('http://127.0.0.1:8000/api/sections');
                    const sectionsData = await sectionsResponse.json();
                    allSections = sectionsData.sections || sectionsData || [];

                    // Cache sections data
                    permanentCache.sections = allSections;
                    permanentCache.timestamp = Date.now();
                }

                // Find the section by name from route
                const targetSection = allSections.find((section) => section.name === routeSectionName);
                if (targetSection) {
                    targetSectionId = targetSection.id;
                    targetSectionName = targetSection.name;
                    console.log('✅ Found target section:', targetSectionName, 'ID:', targetSectionId);
                } else {
                    console.warn('⚠️ Section not found:', routeSectionName, 'falling back to homeroom');
                }
            }

            // If no route section or section not found, fall back to homeroom
            if (!targetSectionId) {
                console.log('🏠 No route section specified, using teacher homeroom section');

                // Check sections cache first (if not already loaded)
                let allSections;
                if (isCacheValid('sections') && permanentCache.sections) {
                    allSections = permanentCache.sections;
                } else {
                    console.log('🔄 Fetching sections from database...');
                    const sectionsResponse = await fetch('http://127.0.0.1:8000/api/sections');
                    const sectionsData = await sectionsResponse.json();
                    allSections = sectionsData.sections || sectionsData || [];

                    // Cache sections data
                    permanentCache.sections = allSections;
                    permanentCache.timestamp = Date.now();
                }

                // Find homeroom section for this teacher
                const homeroomSection = allSections.find((section) => section.homeroom_teacher_id === parseInt(teacherId.value));
                if (homeroomSection) {
                    targetSectionId = homeroomSection.id;
                    targetSectionName = homeroomSection.name;
                    console.log('✅ Found homeroom section:', targetSectionName, 'ID:', targetSectionId);
                }
            }

            if (targetSectionId) {
                sectionId.value = targetSectionId;

                // Use optimized teacher-specific API to avoid loading ALL students
                console.log('🔄 Loading students for section:', targetSectionName, 'ID:', targetSectionId);
                const studentsApiKey = `teacher_${teacherId.value}_section_${targetSectionId}_students`;

                // Check if request is already pending
                if (pendingRequests.has(studentsApiKey)) {
                    console.log('🔄 Students API call already pending for section, waiting...');
                } else {
                    // Use teacher-specific endpoint to get students from the correct section
                    const requestPromise = TeacherAttendanceService.getStudentsForTeacherSubject(teacherId.value, targetSectionId, subjectId.value || null)
                        .then((data) => {
                            const students = data.students || [];
                            pendingRequests.delete(studentsApiKey); // Clean up
                            console.log(`📦 Loaded ${students.length} students via teacher API`);
                            return students;
                        })
                        .catch((error) => {
                            console.warn('Teacher API failed, falling back to sections API:', error);
                            pendingRequests.delete(studentsApiKey);
                            // Fallback to smaller sections-based API
                            return fetch(`http://127.0.0.1:8000/api/sections/${targetSectionId}`)
                                .then((response) => response.json())
                                .then((data) => data.students || []);
                        });

                    pendingRequests.set(studentsApiKey, requestPromise);
                }

                const teacherStudents = (await pendingRequests.get(studentsApiKey)) || [];

                // Backend now handles filtering out dropped out students
                console.log('📦 Received students from API:', teacherStudents.length);
                const filteredStudents = teacherStudents;

                console.log(`✅ Filtered and normalized ${filteredStudents.length} students for section ${targetSectionName}`);

                // Normalize student data to ensure consistent IDs - use actual database IDs
                const normalizedStudents = filteredStudents.map((student, index) => {
                    // Use the actual database ID as primary identifier
                    const dbId = student.id || student.student_id;
                    // Generate NCS format for display/compatibility but keep database ID as primary
                    const ncsId = student.student_id || `NCS-2025-${String(dbId).padStart(5, '0')}`;

                    return {
                        id: dbId,
                        name: student.name || `${student.first_name || ''} ${student.last_name || ''}`.trim(),
                        firstName: student.first_name || student.firstName || '',
                        lastName: student.last_name || student.lastName || '',
                        current_section_id: targetSectionId,
                        studentId: ncsId, // NCS format for display
                        student_id: ncsId, // NCS format for compatibility
                        dbId: dbId // Keep database ID for lookups
                    };
                });

                students.value = normalizedStudents;

                // Cache student data
                permanentCache.students = normalizedStudents;
                permanentCache.timestamp = Date.now();

                console.log('✅ Filtered and normalized', students.value.length, 'students for section', targetSectionName);
                console.log(
                    '📋 Student IDs:',
                    students.value.map((s) => s.studentId)
                );

                // Prevent infinite loop - if we still have 0 students, don't retry
                if (students.value.length === 0) {
                    console.warn('⚠️ No students found for section', targetSectionName, 'ID:', targetSectionId);
                    console.warn('⚠️ This may indicate a data issue or API problem');
                    // Don't return false here to avoid infinite retry loop
                }
            } else {
                console.warn('❌ No target section determined - this should not happen with proper route parameters');
                throw new Error('Unable to determine target section for student loading');
            }
        } catch (error) {
            console.error('Error loading students data:', error);
            throw error; // Re-throw to be handled by outer catch
        }

        // Validate section_id before proceeding
        if (!sectionId.value || sectionId.value === '' || sectionId.value === 'null') {
            console.warn('Cannot load attendance: section_id is missing');
            return false;
        }
        // Ensure students are fully processed before loading seating arrangement
        await nextTick();
        console.log('Students loaded, now loading seating arrangement. Student count:', students.value.length);

        // Try to restore preserved assignments first
        const restored = restorePreservedAssignments();
        if (!restored) {
            // Load seating arrangement after students and sectionId are set
            const layoutLoaded = await loadSeatingArrangementFromDatabase();

            // If no saved layout exists, auto-assign students optimally
            if (!layoutLoaded) {
                console.log('No saved layout found, auto-assigning students optimally');
                autoAssignStudents();
            }
        } else {
            console.log('Used preserved assignments instead of database');
            // Skip any further database loading to prevent overwriting restored assignments
            return;
        }

        console.log('Loaded students for attendance:', students.value.length);
        console.log(
            'Student names:',
            students.value.map((s) => `${s.name} (ID: ${s.id})`)
        );

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
    } finally {
        // Clear loading flags
        isLoadingStudents.value = false;

        // Hide loading animation after a minimum display time
        setTimeout(() => {
            isLoadingSeating.value = false;
            loadingMessage.value = '';
        }, 800);
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

// Debounced save function to prevent excessive API calls
let saveTimeout = null;

// Save current layout with debouncing
const saveCurrentLayout = async (showToast = true) => {
    // Clear existing timeout
    if (saveTimeout) {
        clearTimeout(saveTimeout);
    }

    // Set new timeout to debounce saves
    saveTimeout = setTimeout(async () => {
        await saveCurrentLayoutImmediate(showToast);
    }, 500); // 500ms debounce
};

// Immediate save function (internal)
const saveCurrentLayoutImmediate = async (showToast = true) => {
    console.log('Saving current layout to database');

    try {
        if (!sectionId.value || !teacherId.value) {
            console.log('Missing required IDs for saving layout');
            return;
        }

        const assignedSeats = seatPlan.value.flat().filter((seat) => seat.isOccupied).length;

        // Use the resolved subject ID (numeric) instead of the route parameter
        const resolvedSubjectId = getResolvedSubjectId();

        // Prepare the layout data for the API
        const layout = {
            rows: rows.value,
            columns: columns.value,
            seatPlan: seatPlan.value,
            showTeacherDesk: showTeacherDesk.value,
            showStudentIds: showStudentIds.value
        };

        // Save to database via API
        console.log('Saving layout with data:', {
            sectionId: sectionId.value,
            subjectId: resolvedSubjectId,
            teacherId: teacherId.value,
            seatPlan: layout.seatPlan,
            assignedSeats
        });

        console.log('Calling SeatingService.saveSeatingArrangement with:', {
            sectionId: sectionId.value,
            subjectId: resolvedSubjectId,
            teacherId: teacherId.value,
            layoutKeys: Object.keys(layout)
        });

        const response = await SeatingService.saveSeatingArrangement(sectionId.value, resolvedSubjectId, teacherId.value, layout);

        if (showToast) {
            toast.add({
                severity: 'success',
                summary: 'Layout Saved',
                detail: 'Seating arrangement has been saved successfully',
                life: 3000
            });
        }

        console.log('Layout saved successfully:', response);
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
        // Make localStorage key subject-specific
        const resolvedSubjectId = getResolvedSubjectId();
        localStorage.setItem(`seatPlan_section_${sectionId.value}_subject_${resolvedSubjectId}`, JSON.stringify(layout));

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
        isLoadingSeating.value = true;
        loadingMessage.value = 'Loading seating arrangement...';
        console.log('Loading seating arrangement from database...');

        if (!sectionId.value || !teacherId.value) {
            console.log('Missing sectionId or teacherId, falling back to localStorage');
            console.log('Current values - sectionId:', sectionId.value, 'teacherId:', teacherId.value);
            return loadSavedLayout();
        }
        console.log('Loading with sectionId:', sectionId.value, 'teacherId:', teacherId.value, 'subjectId:', getResolvedSubjectId());

        const response = await SeatingService.getSeatingArrangement(sectionId.value, teacherId.value, getResolvedSubjectId());

        console.log('Loading seating arrangement response:', response);
        console.log('🔍 SUBJECT-SPECIFIC CHECK: Section', sectionId.value, 'Subject', getResolvedSubjectId(), 'Last Updated:', response?.last_updated);

        // Check if this is actually subject-specific data
        if (response?.seating_layout?.seatPlan) {
            const occupiedSeats = response.seating_layout.seatPlan.flat().filter((seat) => seat.isOccupied);
            console.log('🎯 SEATING CHECK: Found', occupiedSeats.length, 'occupied seats for Subject', getResolvedSubjectId());
            if (occupiedSeats.length > 0) {
                console.log(
                    '🎯 FIRST 3 STUDENTS:',
                    occupiedSeats.slice(0, 3).map((seat) => seat.studentId)
                );
            }
        }

        if (response && response.seating_layout) {
            const layout = response.seating_layout;
            console.log('Loaded layout data:', layout);
            console.log('Loaded assigned seats:', layout.seatPlan ? layout.seatPlan.flat().filter((seat) => seat.isOccupied).length : 0);

            // Apply the loaded layout
            rows.value = layout.rows || rows.value;
            columns.value = layout.columns || columns.value;
            showTeacherDesk.value = layout.showTeacherDesk !== undefined ? layout.showTeacherDesk : showTeacherDesk.value;
            showStudentIds.value = layout.showStudentIds !== undefined ? layout.showStudentIds : showStudentIds.value;

            // Set the seat plan with deep copy to avoid reference issues
            if (layout.seatPlan) {
                seatPlan.value = JSON.parse(JSON.stringify(layout.seatPlan));

                // Defer cleanup until after students are fully loaded
                // Use nextTick to ensure students are loaded first
                await nextTick();

                // Double-check students are loaded before cleanup
                if (students.value && students.value.length > 0) {
                    console.log('Running cleanup after loading seating arrangement - students loaded:', students.value.length);
                    cleanupInvalidStudentAssignments();
                } else {
                    console.log('Skipping cleanup - students not loaded yet, will cleanup later');
                    // Schedule cleanup for later when students are loaded
                    setTimeout(() => {
                        if (students.value && students.value.length > 0) {
                            console.log('Delayed cleanup - students now loaded:', students.value.length);
                            cleanupInvalidStudentAssignments();
                        }
                    }, 1000);
                }

                console.log('Loaded seating arrangement from database');
                return true;
            }
        }

        // Fallback to localStorage if database doesn't have data
        console.log('No seating arrangement found in database, trying localStorage');
        const localResult = loadSavedLayout();
        if (localResult) {
            console.log('Loaded seating arrangement from localStorage');
            return true;
        } else {
            console.log('No saved layout found, using default grid');
            initializeSeatPlan();
            return false;
        }
    } catch (error) {
        console.error('Error loading seating arrangement from database:', error);
        console.log('Falling back to localStorage...');
        return loadSavedLayout();
    } finally {
        // Ensure loading state is cleared
        isLoadingSeating.value = false;
        loadingMessage.value = '';
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
    console.log(`Initializing seat plan with ${rows.value} rows and ${columns.value} columns`);

    // AUTO-OPTIMIZATION DISABLED - Let user choose any grid size they want!
    // Teachers should have full control over classroom layout

    seatPlan.value = [];
    for (let i = 0; i < rows.value; i++) {
        const row = [];
        for (let j = 0; j < columns.value; j++) {
            row.push({
                isOccupied: false,
                studentId: null,
                studentName: '',
                status: null
            });
        }
        seatPlan.value.push(row);
    }
    console.log(`Seat plan initialized with ${rows.value} rows and ${columns.value} columns`);
};

// Assignment method options
const assignmentMethods = ref([
    { label: 'Alphabetical (A-Z)', value: 'alphabetical', icon: 'pi pi-sort-alpha-down' },
    { label: 'Reverse Alphabetical (Z-A)', value: 'reverse_alphabetical', icon: 'pi pi-sort-alpha-up' },
    { label: 'Random', value: 'random', icon: 'pi pi-refresh' }
]);

const selectedAssignmentMethod = ref('alphabetical');
const showAssignmentOptions = ref(false);

// Sort students based on selected method
const sortStudents = (students, method) => {
    const sortedStudents = [...students];

    switch (method) {
        case 'alphabetical':
            return sortedStudents.sort((a, b) => a.name.localeCompare(b.name));

        case 'reverse_alphabetical':
            return sortedStudents.sort((a, b) => b.name.localeCompare(a.name));

        case 'random':
            // Fisher-Yates shuffle algorithm
            for (let i = sortedStudents.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [sortedStudents[i], sortedStudents[j]] = [sortedStudents[j], sortedStudents[i]];
            }
            return sortedStudents;

        default:
            return sortedStudents;
    }
};

// Auto-assign students to seats with different methods
const autoAssignStudents = (method = null) => {
    if (!students.value || students.value.length === 0) {
        console.log('No students to assign');
        toast.add({
            severity: 'warn',
            summary: 'No Students',
            detail: 'No students available to assign to seats',
            life: 3000
        });
        return;
    }

    const assignmentMethod = method || selectedAssignmentMethod.value;
    const methodLabel = assignmentMethods.value.find((m) => m.value === assignmentMethod)?.label || 'Default';

    console.log(`Auto-assigning ${students.value.length} students using ${methodLabel} method`);

    // Clear all current assignments
    seatPlan.value.forEach((row) => {
        row.forEach((seat) => {
            seat.isOccupied = false;
            seat.studentId = null;
            seat.studentName = '';
            seat.status = null;
        });
    });

    // Sort students based on selected method
    const sortedStudents = sortStudents(students.value, assignmentMethod);
    let studentIndex = 0;

    // Fill seats row by row, left to right
    for (let row = 0; row < rows.value && studentIndex < sortedStudents.length; row++) {
        for (let col = 0; col < columns.value && studentIndex < sortedStudents.length; col++) {
            const student = sortedStudents[studentIndex];

            // Update the existing seat object - use NCS format for seating arrangement
            seatPlan.value[row][col].isOccupied = true;
            seatPlan.value[row][col].studentId = student.studentId; // Use NCS format ID
            seatPlan.value[row][col].studentName = student.name;
            seatPlan.value[row][col].status = null;

            console.log(`Assigned ${student.name} to seat [${row}][${col}]`);
            studentIndex++;
        }
    }

    // Recalculate unassigned students
    calculateUnassignedStudents();

    console.log(`Assigned ${studentIndex} students to seats using ${methodLabel}`);

    // Save the layout after auto-assignment
    saveCurrentLayout(false);

    // Show success message
    toast.add({
        severity: 'success',
        summary: 'Students Auto-Assigned',
        life: 3000
    });

    // Hide assignment options after successful assignment
    showAssignmentOptions.value = false;
};

// Floating Panel Drag Functions
const startDragPanel = (event) => {
    if (event.target.closest('.panel-btn') || event.target.closest('.floating-panel-content')) {
        return; // Don't drag when clicking buttons or content
    }

    isDraggingPanel.value = true;
    const rect = event.currentTarget.getBoundingClientRect();
    dragOffset.value = {
        x: event.clientX - rect.left,
        y: event.clientY - rect.top
    };

    document.addEventListener('mousemove', dragPanel);
    document.addEventListener('mouseup', stopDragPanel);
    event.preventDefault();
};

const dragPanel = (event) => {
    if (!isDraggingPanel.value) return;

    const newX = event.clientX - dragOffset.value.x;
    const newY = event.clientY - dragOffset.value.y;

    // Keep panel within viewport bounds
    const panelWidth = 320;
    const panelHeight = isMinimized.value ? 50 : 400;
    const maxX = window.innerWidth - panelWidth;
    const maxY = window.innerHeight - panelHeight;

    panelPosition.value = {
        x: Math.max(0, Math.min(newX, maxX)),
        y: Math.max(0, Math.min(newY, maxY))
    };
};

const stopDragPanel = () => {
    isDraggingPanel.value = false;
    document.removeEventListener('mousemove', dragPanel);
    document.removeEventListener('mouseup', stopDragPanel);
};

const toggleMinimize = () => {
    isMinimized.value = !isMinimized.value;
};

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

    // Find the student with the matching ID - check all possible ID formats
    const student = students.value.find((s) => {
        // Check all possible ID fields
        const dbId = s.dbId ? s.dbId.toString() : '';
        const mainId = s.id ? s.id.toString() : '';
        const ncsId = s.studentId ? s.studentId.toString() : '';
        const altId = s.student_id ? s.student_id.toString() : '';

        return dbId === idStr || mainId === idStr || ncsId === idStr || altId === idStr;
    });

    if (!student) {
        // Only warn once per missing student to avoid console spam
        if (!getStudentById._warnedIds) getStudentById._warnedIds = new Set();
        if (!getStudentById._warnedIds.has(idStr)) {
            console.warn(`Student with ID ${studentId} not found in current student list`);
            getStudentById._warnedIds.add(idStr);
        }
    }

    return student || null;
};

// Get student by QR code
const getStudentByQRCode = (qrCode) => {
    if (!qrCode) return null;

    // Find student with matching QR code
    const student = students.value.find((s) => s.qr_code === qrCode || s.studentId === qrCode);

    if (!student) {
        console.warn(`Student with QR code ${qrCode} not found`);
    }

    return student || null;
};

// QR Scanner Functions
const toggleScanning = () => {
    scanning.value = !scanning.value;
    if (scanning.value) {
        initializeCamera();
    } else {
        stopCamera();
    }
};

const initializeCamera = async () => {
    try {
        console.log('Initializing camera for QR scanning...');
        // Camera initialization is handled by vue-qrcode-reader component
        isScanning.value = true;
    } catch (error) {
        console.error('Error initializing camera:', error);
        toast.add({
            severity: 'error',
            summary: 'Camera Error',
            detail: 'Could not access camera for QR scanning',
            life: 5000
        });
    }
};

const stopCamera = () => {
    console.log('Stopping camera...');
    isScanning.value = false;
};

const startQRScanner = async () => {
    console.log('Starting QR scanner...');
    attendanceMethod.value = 'qr'; // Set method to QR when scanner starts
    showQRScanner.value = true;
    scannedStudents.value = [];
    lastScannedStudent.value = null;

    try {
        await initializeCamera();
    } catch (error) {
        console.error('Failed to start QR scanner:', error);
        showQRScanner.value = false;
        attendanceMethod.value = 'seat_plan'; // Reset to seat plan on error
    }
};

const selectQRMethod = async () => {
    try {
        // If no active session, create new one
        if (!sessionActive.value) {
            await createAttendanceSession();
        } else {
            // Just change method for existing session
            attendanceMethod.value = 'qr';
            console.log('🔄 Changed method to QR Scanner for existing session');
        }

        showAttendanceMethodModal.value = false;
        await startQRScanner();

        toast.add({
            severity: 'success',
            summary: sessionActive.value ? 'Method Changed' : 'Session Started',
            detail: 'QR Scanner is now active',
            life: 2000
        });
    } catch (error) {
        console.error('Error in selectQRMethod:', error);
        toast.add({
            severity: 'error',
            summary: 'QR Scanner Error',
            detail: 'Failed to start QR scanner: ' + error.message,
            life: 5000
        });
    }
};

const onQRCodeDetected = async (detectedCodes) => {
    if (!detectedCodes || detectedCodes.length === 0) return;

    const qrCode = detectedCodes[0].rawValue;
    console.log('QR Code detected:', qrCode);

    // Find student by QR code
    const student = getStudentByQRCode(qrCode);

    if (!student) {
        toast.add({
            severity: 'warn',
            summary: 'Student Not Found',
            detail: `No student found with QR code: ${qrCode}`,
            life: 3000
        });
        return;
    }

    // Check if already scanned
    if (scannedStudents.value.includes(student.id)) {
        toast.add({
            severity: 'info',
            summary: 'Already Scanned',
            detail: `${student.name} has already been marked present`,
            life: 3000
        });
        return;
    }

    // Mark student as present
    await markStudentPresent(student);

    // Add to scanned list
    scannedStudents.value.push(student.id);
    lastScannedStudent.value = student;

    // Show confirmation
    toast.add({
        severity: 'success',
        summary: 'Attendance Marked',
        detail: `${student.name} marked as Present`,
        life: 3000
    });

    // Check if all students are scanned
    if (scannedStudents.value.length >= students.value.length) {
        await autoCompleteSession();
    }
};

const markStudentPresent = async (student) => {
    try {
        // Find student's seat if they have one
        let studentSeat = null;
        seatPlan.value.forEach((row, rowIndex) => {
            row.forEach((seat, colIndex) => {
                if (seat.isOccupied && seat.studentId === student.id) {
                    studentSeat = seat;
                }
            });
        });

        // If student has a seat, mark it
        if (studentSeat) {
            studentSeat.status = 'Present';
        }

        // Add to attendance records
        const attendanceRecord = {
            studentId: student.id,
            studentName: student.name,
            status: 'Present',
            timestamp: new Date().toISOString(),
            method: 'QR_SCAN'
        };

        attendanceRecords.value.push(attendanceRecord);

        // Save to localStorage
        localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));

        console.log(`Marked ${student.name} as Present via QR scan`);
    } catch (error) {
        console.error('Error marking student present:', error);
        throw error;
    }
};

const autoCompleteSession = async () => {
    try {
        console.log('Auto-completing session - all students scanned');

        // 🚨 CRITICAL FIX: Update seat plan with QR scan results BEFORE completing session
        if (qrScanResults.value.length > 0) {
            console.log('🎯 Transferring QR scan results to seat plan before session completion');
            qrScanResults.value.forEach((result) => {
                const seat = findSeatByStudentId(result.studentId);
                if (seat) {
                    seat.status = result.status;
                    console.log(`✅ Updated seat for ${result.name}: ${result.status}`);
                } else {
                    console.warn(`⚠️ No seat found for student ${result.name} (ID: ${result.studentId})`);
                }
            });
            // Force reactivity update
            seatPlan.value = [...seatPlan.value];
        }

        // Close QR scanner UI
        showQRScanner.value = false;
        isScanning.value = false;

        // Start loading animation (same as seating plan method)
        isCompletingSession.value = true;
        sessionCompletionProgress.value = 0;

        // Start progressive animation that runs parallel to API calls
        const progressInterval = setInterval(() => {
            if (sessionCompletionProgress.value < 85) {
                sessionCompletionProgress.value += Math.random() * 1.5 + 0.5; // Slower increment 0.5-2%
            }
        }, 150); // Slower interval

        // Step 1: Initial progress
        sessionCompletionProgress.value = 10;
        await new Promise((resolve) => setTimeout(resolve, 800));

        // Step 2: Send seat plan data to backend (skip if already saved)
        sessionCompletionProgress.value = 25;
        console.log('📤 [AUTO-COMPLETE] Sending seat plan attendance to backend...');
        try {
            await AttendanceSessionService.markSeatPlanAttendance(
                currentSession.value.id,
                seatPlan.value,
                attendanceStatuses.value
            );
            console.log('✅ [AUTO-COMPLETE] Attendance data sent successfully');
        } catch (error) {
            console.warn('⚠️ [AUTO-COMPLETE] Attendance already saved, skipping...', error.message);
        }
        await new Promise((resolve) => setTimeout(resolve, 600));

        // Step 3: Complete session
        const response = await AttendanceSessionService.completeSession(currentSession.value.id);

        // Step 3: Process response
        sessionCompletionProgress.value = 60;
        await new Promise((resolve) => setTimeout(resolve, 700));

        sessionSummary.value = response.summary;
        sessionActive.value = false;
        currentSession.value = null;

        // Step 4: Finalize
        sessionCompletionProgress.value = 80;
        await new Promise((resolve) => setTimeout(resolve, 500));

        // Step 5: Prepare modal (keep loading visible)
        sessionCompletionProgress.value = 95;

        // Save session data but don't show modal yet
        saveCompletedSession(response.summary);

        // Final progress update
        sessionCompletionProgress.value = 100;
        await new Promise((resolve) => setTimeout(resolve, 500));

        // Clear interval and hide loading
        clearInterval(progressInterval);
        isCompletingSession.value = false;
        sessionCompletionProgress.value = 0;

        // Show completion modal after loading is done
        showCompletionModal.value = true;

        toast.add({
            severity: 'success',
            summary: 'Session Auto-Completed',
            detail: 'All students scanned. Attendance session completed automatically.',
            life: 5000
        });
    } catch (error) {
        console.error('Error auto-completing session:', error);
        toast.add({
            severity: 'error',
            summary: 'Completion Error',
            detail: 'Could not complete session automatically',
            life: 5000
        });
    }
};

const closeQRScanner = () => {
    showQRScanner.value = false;
    isScanning.value = false;
    stopCamera();
};

const onScannerError = (error) => {
    console.error('QR Scanner error:', error);
    toast.add({
        severity: 'error',
        summary: 'Scanner Error',
        detail: 'Camera access denied or not available',
        life: 5000
    });
    isScanning.value = false;
};

// Clean up invalid student assignments from seating arrangement
const cleanupInvalidStudentAssignments = () => {
    if (!students.value?.length) return;

    console.log('Running cleanup - current students:', students.value);

    const availableStudentIds = students.value.map((student) => student.studentId || student.id);
    console.log('Available student IDs:', availableStudentIds);

    let foundInvalid = false;
    let droppedOutStudents = [];

    for (let row = 0; row < seatPlan.value.length; row++) {
        for (let col = 0; col < seatPlan.value[row].length; col++) {
            const seat = seatPlan.value[row][col];
            if (seat.studentId) {
                console.log(`Checking seat [${row}][${col}] with studentId ${seat.studentId}: ${availableStudentIds.includes(seat.studentId) ? 'FOUND' : 'NOT FOUND'}`);

                if (!availableStudentIds.includes(seat.studentId)) {
                    console.log(`🚫 Removing dropped out student: ${seat.studentId} from seat [${row}][${col}]`);
                    droppedOutStudents.push(seat.studentId);
                    seat.studentId = null;
                    seat.studentName = '';
                    seat.isOccupied = false;
                    foundInvalid = true;
                }
            }
        }
    }

    if (foundInvalid) {
        console.log('Found invalid assignments, updating counts');
        calculateUnassignedStudents();

        // Show notification if dropped out students were removed
        if (droppedOutStudents.length > 0) {
            toast.add({
                severity: 'info',
                summary: 'Seating Updated',
                detail: `Removed ${droppedOutStudents.length} dropped out student(s) from seating arrangement`,
                life: 4000
            });
        }
    } else {
        console.log('No invalid assignments found during cleanup');
    }
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
    const recordKey = `${seat.studentId}-${currentDateString.value}`;

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
        date: currentDateString.value,
        status,
        remarks: remarks || '',
        timestamp
    };

    // Save to localStorage
    localStorage.setItem('attendanceRecords', JSON.stringify(attendanceRecords.value));
    localStorage.setItem('remarksPanel', JSON.stringify(remarksPanel.value));

    // Also update the cache to ensure our most recent changes persist across refreshes
    const today = currentDateString.value;
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
                    statusId = attendanceStatuses.value.find((s) => s.code === 'P')?.id;
                } else if (statusLower === 'absent' || status === 2) {
                    statusId = attendanceStatuses.value.find((s) => s.code === 'A')?.id;
                } else if (statusLower === 'late' || status === 3) {
                    statusId = attendanceStatuses.value.find((s) => s.code === 'L')?.id;
                } else if (statusLower === 'excused' || status === 4) {
                    statusId = attendanceStatuses.value.find((s) => s.code === 'E')?.id;
                } else {
                    statusId = attendanceStatuses.value.find((s) => s.code === 'P')?.id; // Default to Present
                }

                if (statusId) {
                    const attendanceData = AttendanceSessionService.formatAttendanceForAPI(studentId, statusId, remarks);

                    const response = await AttendanceSessionService.markSessionAttendance(currentSession.value.id, [attendanceData]);

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
                present: 1,
                absent: 2,
                late: 3,
                excused: 4
            };
            return statusMap[status.toLowerCase()] || 1;
        };

        // Fallback to legacy system - format for markAttendance API
        const resolvedSubjectId = getResolvedSubjectId();
        const attendanceData = {
            section_id: sectionId.value,
            subject_id: resolvedSubjectId,
            teacher_id: teacherId.value,
            date: currentDateString.value,
            attendance: [
                {
                    student_id: studentId,
                    attendance_status_id: getAttendanceStatusId(status),
                    remarks: remarks || null
                }
            ]
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

// Preserve current assignments before navigation
const preserveCurrentAssignments = () => {
    const assignments = [];
    seatPlan.value.forEach((row, rowIndex) => {
        row.forEach((seat, colIndex) => {
            if (seat.isOccupied && seat.studentId) {
                assignments.push({
                    rowIndex,
                    colIndex,
                    studentId: seat.studentId,
                    status: seat.status
                });
            }
        });
    });

    if (assignments.length > 0) {
        // Use resolved subject ID for consistency
        const resolvedSubjectId = getResolvedSubjectId();
        const preservationKey = `preserved_assignments_${teacherId.value}_${sectionId.value}_${resolvedSubjectId}`;
        localStorage.setItem(
            preservationKey,
            JSON.stringify({
                assignments,
                timestamp: new Date().toISOString(),
                rows: rows.value,
                columns: columns.value,
                teacherId: teacherId.value,
                sectionId: sectionId.value,
                subjectId: resolvedSubjectId
            })
        );
        console.log('Preserved assignments for navigation:', assignments.length);
    }
};

// Restore preserved assignments after navigation
const restorePreservedAssignments = () => {
    // Use resolved subject ID for consistency
    const resolvedSubjectId = getResolvedSubjectId();
    const preservationKey = `preserved_assignments_${teacherId.value}_${sectionId.value}_${resolvedSubjectId}`;
    const preserved = localStorage.getItem(preservationKey);

    if (preserved) {
        try {
            const data = JSON.parse(preserved);
            const timeDiff = new Date() - new Date(data.timestamp);

            // Only restore if preserved within the last 5 minutes
            if (timeDiff < 5 * 60 * 1000) {
                console.log('Restoring preserved assignments:', data.assignments.length);

                // Set flag to prevent grid updates during restoration
                isRestoringAssignments.value = true;

                console.log('Setting restoration flag to prevent grid updates');

                // Ensure grid matches preserved dimensions
                if (data.rows && data.columns) {
                    console.log(`Restoring grid dimensions: ${data.rows}×${data.columns}`);
                    rows.value = data.rows;
                    columns.value = data.columns;
                    initializeSeatPlan();
                }

                // Restore assignments
                console.log('Restoring assignments:', data.assignments);
                data.assignments.forEach((assignment) => {
                    if (assignment.rowIndex < seatPlan.value.length && assignment.colIndex < seatPlan.value[assignment.rowIndex].length) {
                        const seat = seatPlan.value[assignment.rowIndex][assignment.colIndex];
                        seat.isOccupied = true;
                        seat.studentId = assignment.studentId;
                        seat.status = assignment.status;
                        console.log(`Restored: student ${assignment.studentId} to seat [${assignment.rowIndex}][${assignment.colIndex}]`);
                    }
                });

                calculateUnassignedStudents();

                // Clear the restoration flag after a delay to ensure all watchers have processed
                setTimeout(() => {
                    isRestoringAssignments.value = false;
                    console.log('Cleared restoration flag');
                }, 100);

                // Layout is already persisted in database, no need to save again
                console.log('Seating arrangement restored from preserved assignments');

                // Clean up preserved data
                localStorage.removeItem(preservationKey);
                return true;
            } else {
                // Clean up old preserved data
                localStorage.removeItem(preservationKey);
            }
        } catch (error) {
            console.error('Error restoring preserved assignments:', error);
            localStorage.removeItem(preservationKey);
        }
    }

    return false;
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
    // Check both student.id and student.studentId to match how we assign them
    unassignedStudents.value = students.value.filter((student) => {
        const studentId = student.studentId || student.id;
        const studentIdAlt = student.id || student.studentId;
        return !assignedStudentIds.has(studentId) && !assignedStudentIds.has(studentIdAlt);
    });

    console.log('Total students:', students.value.length);
    console.log('Assigned students:', assignedStudentIds.size);
    console.log('Unassigned students:', unassignedStudents.value.length);
    console.log(
        'Unassigned student names:',
        unassignedStudents.value.map((s) => s.name)
    );
};

const filteredUnassignedStudents = computed(() => {
    if (!searchQuery.value) return unassignedStudents.value;
    const query = searchQuery.value.toLowerCase();
    return unassignedStudents.value.filter((student) => student.name.toLowerCase().includes(query) || student.id.toString().includes(query));
});

// Computed property for responsive teacher desk size
const teacherDeskSize = computed(() => {
    // Base size calculation based on grid dimensions
    const baseSize = Math.max(80, Math.min(120, columns.value * 20 + 40));
    return `${baseSize}px`;
});

// Fetch attendance history from service
const fetchAttendanceHistory = async () => {
    try {
        if (!subjectId.value) return;

        // Get current day's records from localStorage first, as these are the most recent
        const todayRecords = {};
        const today = currentDateString.value;
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
    const recordKey = `${currentStudent.value.id}-${currentDateString.value}`;
    attendanceRecords.value[recordKey] = {
        studentId: currentStudent.value.id,
        date: currentDateString.value,
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
        const recordKey = `${currentStudent.value.id}-${currentDateString.value}`;
        attendanceRecords.value[recordKey] = {
            studentId: currentStudent.value.id,
            date: currentDateString.value,
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
    const today = currentDateString.value;
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
    if (sessionActive.value) {
        toast.add({
            severity: 'warn',
            summary: 'Session Already Active',
            detail: 'An attendance session is already in progress',
            life: 3000
        });
        return;
    }

    // Show attendance method selection modal
    showAttendanceMethodModal.value = true;
};

// Clear QR scan data for fresh session
const clearQRScanData = () => {
    console.log('🧹 Clearing QR scan data for fresh session');
    qrScanResults.value = [];
    qrScanLog.value = [];
};

// Confirmation dialog utility
const showConfirmDialog = (title, message, confirmLabel = 'Yes', cancelLabel = 'No') => {
    return new Promise((resolve) => {
        // Using browser's native confirm for now - can be enhanced with PrimeVue dialog later
        const result = confirm(`${title}\n\n${message}`);
        resolve(result);
    });
};

// Create actual session after method selection
const createAttendanceSession = async () => {
    try {
        // Clear QR scan data when starting new session
        clearQRScanData();

        // Use resolved subject ID for session creation
        const resolvedSubjectId = getResolvedSubjectId();

        const sessionData = {
            teacherId: teacherId.value,
            sectionId: sectionId.value,
            subjectId: resolvedSubjectId,
            date: currentDateString.value,
            startTime: new Date().toTimeString().split(' ')[0], // Current time in HH:MM:SS format
            type: 'regular',
            method: attendanceMethod.value === 'qr' ? 'QR Scanner' : 'Manual Entry',
            metadata: {
                attendanceMethod: attendanceMethod.value,
                createdVia: attendanceMethod.value === 'qr' ? 'QR Code Scanner' : 'Manual Entry'
            }
        };

        console.log('Creating session with data:', sessionData);
        console.log('🎯 Session Method:', sessionData.method, '| Attendance Method:', attendanceMethod.value);
        const response = await AttendanceSessionService.createSession(sessionData);
        console.log('Session created:', response);

        currentSession.value = response.session;
        sessionActive.value = true;

        toast.add({
            severity: 'success',
            summary: 'Session Started',
            detail: 'Attendance session has been started successfully',
            life: 3000
        });

        // Clear any cached attendance data for today
        const today = currentDateString.value;
        const cacheKey = `attendanceCache_${subjectId.value}_${today}`;
        localStorage.removeItem(cacheKey);
        scannedStudents.value = [];
    } catch (error) {
        console.error('Error starting session:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to start attendance session',
            life: 5000
        });
    }
};

// Calculate session statistics from current seat plan
const calculateSessionStatistics = () => {
    let totalStudents = 0;
    let presentCount = 0;
    let absentCount = 0;
    let lateCount = 0;
    let excusedCount = 0;
    let markedCount = 0;

    // Count students and their statuses from seat plan
    seatPlan.value.forEach((row) => {
        row.forEach((seat) => {
            if (seat.isOccupied && seat.studentId) {
                totalStudents++;

                if (seat.status) {
                    markedCount++;
                    switch (seat.status) {
                        case 'Present':
                            presentCount++;
                            break;
                        case 'Absent':
                            absentCount++;
                            break;
                        case 'Late':
                            lateCount++;
                            break;
                        case 'Excused':
                            excusedCount++;
                            break;
                    }
                }
            }
        });
    });

    // Calculate attendance rate
    const attendanceRate = totalStudents > 0 ? Math.round((presentCount / totalStudents) * 100) : 0;

    return {
        total_students: totalStudents,
        marked_students: markedCount,
        present_count: presentCount,
        absent_count: absentCount,
        late_count: lateCount,
        excused_count: excusedCount,
        attendance_rate: attendanceRate,
        session_date: currentDateString.value,
        subject_name: subjectName.value || 'Subject',
        session_duration: sessionActive.value ? 'Active' : 'Completed'
    };
};

// Method selection functions
const selectSeatPlanMethod = async () => {
    try {
        // If no active session, create new one
        if (!sessionActive.value) {
            attendanceMethod.value = 'seat_plan'; // Set method to seat plan
            await createAttendanceSession();
        } else {
            // Just change method for existing session
            attendanceMethod.value = 'seat_plan';
            console.log('🔄 Changed method to Seat Plan for existing session');
        }

        showAttendanceMethodModal.value = false;

        toast.add({
            severity: 'success',
            summary: sessionActive.value ? 'Method Changed' : 'Session Started',
            detail: 'Seat Plan method is now active',
            life: 2000
        });
    } catch (error) {
        console.error('Error in selectSeatPlanMethod:', error);
        toast.add({
            severity: 'error',
            summary: 'Method Error',
            detail: 'Failed to set seat plan method: ' + error.message,
            life: 3000
        });
    }
};

const selectRollCallMethod = async () => {
    try {
        // If no active session, create new one
        if (!sessionActive.value) {
            attendanceMethod.value = 'roll_call'; // Set method to roll call
            await createAttendanceSession();
        } else {
            // Just change method for existing session
            attendanceMethod.value = 'roll_call';
            console.log('🔄 Changed method to Roll Call for existing session');
        }

        showAttendanceMethodModal.value = false;
        startRollCall();

        toast.add({
            severity: 'success',
            summary: sessionActive.value ? 'Method Changed' : 'Session Started',
            detail: 'Roll Call method is now active',
            life: 2000
        });
    } catch (error) {
        console.error('Error in selectRollCallMethod:', error);
        toast.add({
            severity: 'error',
            summary: 'Method Error',
            detail: 'Failed to set roll call method: ' + error.message,
            life: 3000
        });
    }
};

// Complete attendance session
const completeAttendanceSession = async () => {
    if (!sessionActive.value || !currentSession.value) {
        toast.add({
            severity: 'warn',
            summary: 'No Active Session',
            detail: 'No active attendance session to complete.',
            life: 3000
        });
        return;
    }

    // 🚨 CRITICAL FIX: Update seat plan with QR scan results BEFORE completing session
    if (qrScanResults.value.length > 0) {
        console.log('🎯 Transferring QR scan results to seat plan before session completion');
        qrScanResults.value.forEach((result) => {
            const seat = findSeatByStudentId(result.studentId);
            if (seat) {
                seat.status = result.status;
                console.log(`✅ Updated seat for ${result.name}: ${result.status}`);
            } else {
                console.warn(`⚠️ No seat found for student ${result.name} (ID: ${result.studentId})`);
            }
        });
        // Force reactivity update
        seatPlan.value = [...seatPlan.value];
    }

    // Start loading animation
    isCompletingSession.value = true;
    sessionCompletionProgress.value = 0;

    // Start progressive animation that runs parallel to API calls
    const progressInterval = setInterval(() => {
        if (sessionCompletionProgress.value < 85) {
            sessionCompletionProgress.value += Math.random() * 1.5 + 0.5; // Slower increment 0.5-2%
        }
    }, 150); // Slower interval

    try {
        // Step 1: Initial progress
        sessionCompletionProgress.value = 10;
        await new Promise((resolve) => setTimeout(resolve, 800));

        // Step 2: Send seat plan data to backend (skip if already saved)
        sessionCompletionProgress.value = 25;
        console.log('📤 [COMPLETE] Sending seat plan attendance to backend...');
        try {
            await AttendanceSessionService.markSeatPlanAttendance(
                currentSession.value.id,
                seatPlan.value,
                attendanceStatuses.value
            );
            console.log('✅ [COMPLETE] Attendance data sent successfully');
        } catch (error) {
            console.warn('⚠️ [COMPLETE] Attendance already saved, skipping...', error.message);
        }
        await new Promise((resolve) => setTimeout(resolve, 600));

        // Step 3: Complete session
        const response = await AttendanceSessionService.completeSession(currentSession.value.id);

        // Step 3: Process response
        sessionCompletionProgress.value = 60;
        await new Promise((resolve) => setTimeout(resolve, 700));

        sessionSummary.value = response.summary;
        sessionActive.value = false;
        currentSession.value = null;

        // Clear QR scan data and reset attendance method
        clearQRScanData();
        attendanceMethod.value = null;

        // Clear all student statuses from seating arrangement
        seatPlan.value.forEach((row) => {
            row.forEach((seat) => {
                if (seat.student) {
                    seat.status = null;
                }
            });
        });

        console.log('🧹 Session completed - cleared all data for fresh start');

        // Step 4: Prepare modal data
        sessionCompletionProgress.value = 80;
        await new Promise((resolve) => setTimeout(resolve, 500));

        // Step 5: Prepare modal (keep loading visible)
        sessionCompletionProgress.value = 95;

        // Save session data but don't show modal yet
        const today = new Date().toISOString().split('T')[0];
        const completionKey = `attendance_completion_${today}`;
        const completionData = {
            timestamp: new Date().toISOString(),
            sessionData: {
                ...response.summary,
                subject_name: response.summary?.subject_name || subjectName.value, // Use API subject name if available
                section_name: currentSectionName.value
            }
        };
        // Get the actual subject name - use resolved subject name if available
        let actualSubjectName = response.summary?.subject_name || subjectName.value;

        // If subject name is still "Loading..." or generic, try to get the real name
        if (actualSubjectName === 'Loading...' || actualSubjectName === 'Subject') {
            if (resolvedSubjectId.value) {
                try {
                    const subjectDetails = await fetchSubjectDetails(resolvedSubjectId.value);
                    actualSubjectName = subjectDetails.name || actualSubjectName;
                    console.log('🔧 Fixed subject name for completion modal:', actualSubjectName);
                } catch (error) {
                    console.warn('Could not fetch subject name for completion modal:', error);
                }
            }
        }

        // Update the completion data with the correct subject name
        completionData.sessionData.subject_name = actualSubjectName;
        localStorage.setItem(completionKey, JSON.stringify(completionData));

        completedSessionData.value = {
            ...response.summary,
            subject_name: actualSubjectName,
            section_name: currentSectionName.value
        };

        // Add notification with correct method
        const methodNames = {
            qr: 'QR Code Scan',
            seat_plan: 'Seat Plan',
            roll_call: 'Roll Call'
        };

        const sessionSummaryWithMethod = {
            ...response.summary,
            method: methodNames[attendanceMethod.value] || 'Manual Entry'
        };
        console.log('Adding session completion notification:', sessionSummaryWithMethod);
        // NotificationService method might not exist, skip for now
        // const notification = NotificationService.addSessionCompletionNotification(sessionSummaryWithMethod);
        // console.log('Notification added:', notification);

        // Step 6: Final completion
        sessionCompletionProgress.value = 100;
        await new Promise((resolve) => setTimeout(resolve, 500));

        // Clear interval and hide loading
        clearInterval(progressInterval);
        isCompletingSession.value = false;
        sessionCompletionProgress.value = 0;

        // Show modal immediately
        showCompletionModal.value = true;
        modalDismissedToday.value = false;
        localStorage.removeItem(`completion_dismissed_${today}`);
        setupMidnightTimer();
        setupAutoHideTimer();

        // Force notification reload after a brief delay to ensure backend has created it
        try {
            // Small delay to ensure notification is created in database first
            await new Promise((resolve) => setTimeout(resolve, 500));
            await NotificationService.loadNotifications();
            console.log('✅ Notifications reloaded after session completion - should show new notification');
        } catch (notifError) {
            console.error('Failed to reload notifications:', notifError);
        }

        toast.add({
            severity: 'success',
            summary: 'Session Completed',
            detail: `Attendance session completed. ${response.summary?.total_students || 0} students processed.`,
            life: 5000
        });

        console.log('Completed attendance session:', response);
    } catch (error) {
        // Clear interval on error
        clearInterval(progressInterval);
        console.error('Error completing attendance session:', error);

        // Hide loading on error
        isCompletingSession.value = false;
        sessionCompletionProgress.value = 0;

        toast.add({
            severity: 'error',
            summary: 'Session Error',
            detail: 'Failed to complete attendance session',
            life: 5000
        });
    }
};

const markAllPresent = async () => {
    console.log('Mark All Present clicked');

    if (!confirm('Are you sure you want to mark all students as present?')) {
        console.log('User cancelled');
        return;
    }

    console.log('Checking session status - Active:', sessionActive.value, 'Session:', currentSession.value);

    if (!sessionActive.value || !currentSession.value) {
        toast.add({
            severity: 'error',
            summary: 'No Active Session',
            detail: 'Please start a session first',
            life: 3000
        });
        return;
    }

    try {
        console.log('Starting mark all present process');
        let markedCount = 0;
        const attendanceData = [];

        // Collect all students to mark as present
        seatPlan.value.forEach((row) => {
            row.forEach((seat) => {
                if (seat.isOccupied && seat.studentId) {
                    // Find the student object to get the numeric ID
                    const student = students.value.find((s) => s.student_id === seat.studentId || s.id === seat.studentId);
                    if (!student) {
                        console.error('Student not found for seat:', seat.studentId);
                        return;
                    }

                    // Update visual status
                    seat.status = 1; // Present status

                    // Find Present status ID from attendanceStatuses
                    const presentStatus = attendanceStatuses.value.find((status) => status.code === 'P' || status.name === 'Present' || status.id === 1);

                    if (!presentStatus) {
                        console.error('Present status not found in attendanceStatuses');
                        return;
                    }

                    // Add to batch with correct IDs
                    attendanceData.push({
                        student_id: student.id, // Use numeric student.id, not student_id string
                        attendance_status_id: presentStatus.id, // Use actual status ID from database
                        arrival_time: new Date().toTimeString().split(' ')[0],
                        remarks: null,
                        reason_id: null,
                        marking_method: 'manual'
                    });
                }
            });
        });

        console.log('Collected attendance data for', attendanceData.length, 'students');
        console.log('Attendance data:', attendanceData);

        // Save all attendance records in one API call
        if (attendanceData.length > 0) {
            console.log('Calling API to mark attendance for session:', currentSession.value.id);
            await AttendanceSessionService.markSessionAttendance(currentSession.value.id, attendanceData);
            markedCount = attendanceData.length;
            console.log('Successfully marked', markedCount, 'students');

            // Force visual update - ensure all seats show green status
            const updatedSeats = [];
            seatPlan.value.forEach((row, rowIndex) => {
                row.forEach((seat, colIndex) => {
                    if (seat.isOccupied && seat.studentId) {
                        seat.status = 1; // Set to green/present
                        console.log(`Setting seat [${rowIndex}][${colIndex}] student ${seat.studentId} to status 1 (green)`);
                        updatedSeats.push({ rowIndex, colIndex, studentId: seat.studentId });
                    }
                });
            });

            console.log('Updated', updatedSeats.length, 'seats with green status');
            console.log('Updated seats:', updatedSeats);

            // Force Vue reactivity with nextTick
            await nextTick();
            console.log('Vue DOM updated via nextTick');
        }

        toast.add({
            severity: 'success',
            summary: 'All Present',
            detail: `${markedCount} students marked as present`,
            life: 3000
        });
    } catch (error) {
        console.error('Error marking all present:', error);
        console.error('Error response:', error.response?.data);
        console.error('Validation errors:', JSON.stringify(error.response?.data?.errors, null, 2));

        const errorMsg = error.response?.data?.message || JSON.stringify(error.response?.data?.errors) || 'Failed to mark all students as present';

        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: errorMsg,
            life: 10000
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
        const today = currentDateString.value;

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
        updateGridSize();
    }
};

// Update decrementRows function
const decrementRows = () => {
    if (rows.value > 1) {
        rows.value--;
        updateGridSize();
    }
};

// Add incrementColumns and decrementColumns functions with similar save logic
const incrementColumns = () => {
    if (columns.value < 12) {
        columns.value++;
        updateGridSize();
    }
};

const decrementColumns = () => {
    if (columns.value > 1) {
        columns.value--;
        updateGridSize();
    }
};

// Function to resolve subject details from identifier
const resolveSubjectDetails = async (subjectIdentifier) => {
    console.log(`🚀 Resolving subject details for identifier: ${subjectIdentifier}`);

    if (subjectIdentifier && (subjectName.value === 'Subject' || subjectName.value === 'Loading...' || subjectName.value === subjectIdentifier || isNaN(subjectIdentifier))) {
        const subjectDetails = await fetchSubjectDetails(subjectIdentifier);
        console.log(`📋 Received subject details:`, subjectDetails);

        if (subjectDetails.id) {
            const newSubjectName = subjectDetails.name;
            const newSubjectId = subjectDetails.id;

            subjectId.value = newSubjectId;
            subjectName.value = newSubjectName;
            resolvedSubjectId.value = newSubjectId; // Store the resolved ID
            console.log(`✅ Updated subject to: ${newSubjectName} (ID: ${newSubjectId})`);

            // Force Vue to update the DOM
            await nextTick();
            console.log(`🔄 DOM updated, current title should show: ${newSubjectName} Attendance`);

            // Use template ref for direct access with the resolved name
            await nextTick();
            if (titleRef.value) {
                titleRef.value.textContent = `${newSubjectName} Attendance`;
                console.log(`🎯 Template ref update applied: ${titleRef.value.textContent}`);
            }
        }
    }
};

// Add missing updateGridSize function
const updateGridSize = () => {
    console.log(`Updating grid size to ${rows.value} rows × ${columns.value} columns`);

    // Preserve current assignments before reinitializing
    const currentAssignments = [];
    if (seatPlan.value && seatPlan.value.length > 0) {
        seatPlan.value.forEach((row, rowIndex) => {
            row.forEach((seat, colIndex) => {
                if (seat.isOccupied && seat.studentId) {
                    currentAssignments.push({
                        rowIndex,
                        colIndex,
                        studentId: seat.studentId,
                        status: seat.status
                    });
                }
            });
        });
    }

    console.log('Preserving assignments during grid update:', currentAssignments.length);

    // Reinitialize the grid
    initializeSeatPlan();

    // Restore assignments to new grid if they fit
    currentAssignments.forEach((assignment) => {
        if (assignment.rowIndex < seatPlan.value.length && assignment.colIndex < seatPlan.value[assignment.rowIndex].length) {
            const seat = seatPlan.value[assignment.rowIndex][assignment.colIndex];
            seat.isOccupied = true;
            seat.studentId = assignment.studentId;
            seat.status = assignment.status;
            console.log(`Restored assignment: student ${assignment.studentId} to [${assignment.rowIndex}][${assignment.colIndex}]`);
        } else {
            console.log(`Could not restore assignment for student ${assignment.studentId} - position out of bounds`);
        }
    });

    calculateUnassignedStudents();

    // Only save if this is a user-initiated grid change, not during initialization
    if (!isInitializing.value && !isRestoringAssignments.value) {
        saveCurrentLayout(false);
    } else {
        console.log('Skipping save during initialization/restoration');
    }
};

// Watch for route changes to update subject
watch(
    () => route.params,
    async (params, oldParams) => {
        const matchedSubject = params.subjectId;
        console.log(`Route changed - Updated to: ${subjectName.value} (ID: ${matchedSubject})`);

        // Only reinitialize if the subject actually changed and we're not already loading
        if (oldParams && oldParams.subjectId !== matchedSubject && !isLoadingStudents.value) {
            console.log(`Subject changed from ${oldParams.subjectId} to ${matchedSubject}, reinitializing...`);

            // Clear any existing intervals to prevent conflicts
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }

            // Clear current data
            students.value = [];
            seatPlan.value = [];
            attendanceRecords.value = [];
            resolvedSubjectId.value = null; // Clear resolved ID for fresh resolution

            // Reset subject info
            if (matchedSubject) {
                subjectId.value = matchedSubject;

                // Resolve subject details if needed
                if (isNaN(matchedSubject)) {
                    await resolveSubjectDetails(matchedSubject);
                } else {
                    // For numeric IDs, set loading placeholder and fetch from API
                    subjectName.value = 'Loading...';
                    await resolveSubjectDetails(matchedSubject);
                }

                // Reinitialize component with new subject
                await initializeComponent();
            } else {
                subjectName.value = 'Subject';
                subjectId.value = '';
            }
        } else if (matchedSubject) {
            // First load or same subject
            subjectId.value = matchedSubject;

            // Resolve subject details if needed for first load
            if (isNaN(matchedSubject)) {
                await resolveSubjectDetails(matchedSubject);
            } else {
                // For numeric IDs, set loading placeholder and fetch from API
                subjectName.value = 'Loading...';
                await resolveSubjectDetails(matchedSubject);
            }

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

// Initialize component data and setup
const initializeComponent = async () => {
    try {
        // Ensure date is set to today
        ensureCurrentDate();

        // Subject info is already set during component creation, just log it
        console.log(`Initializing component for: ${subjectName.value} (ID: ${subjectId.value})`);

        // Skip subject info setting since it's already done

        // 🚀 PERFORMANCE: Students already loaded in onMounted, skip duplicate call
        // Set up auto-refresh interval (students already loaded)
        // Clear any existing interval first
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }

        // Set up auto-refresh after initial load (much less frequent)
        refreshInterval = setInterval(async () => {
            console.log('Auto-refreshing student data...');
            // Only refresh if not currently loading to prevent conflicts
            if (!isLoadingSeating.value && !isLoadingStudents.value) {
                await loadStudentsData();
            }
        }, 300000); // Refresh every 5 minutes instead of 30 seconds

        // Only initialize empty seat plan if no students are loaded yet
        if (students.value.length === 0) {
            console.log('No students loaded yet, initializing empty seat plan');
            initializeSeatPlan();
        } else {
            console.log('Students already loaded, skipping seat plan initialization to preserve seating arrangement');
        }

        // Load seating arrangement after student data is loaded (moved to loadStudentsData)

        // Update time every second
        timeInterval.value = setInterval(() => {
            currentDateTime.value = new Date();
        }, 1000);

        // Load saved templates in background (non-blocking)
        loadSavedTemplates().then(() => {
            console.log('Templates loaded, count:', savedTemplates.value.length);
        });

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

                // Clean up seating assignments after students are fully loaded
                if (seatPlan.value && seatPlan.value.length > 0) {
                    cleanupInvalidStudentAssignments();
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

        // Check for completed sessions on mount
        checkCompletedSessionPersistence();
    } catch (error) {
        console.error('Error initializing component:', error);
        toast.add({
            severity: 'error',
            summary: 'Initialization Error',
            detail: 'Failed to initialize component properly',
            life: 5000
        });
    }
};

// Track if component is currently initializing to prevent duplicate loads
const isInitializing = ref(false);

// Watch for route changes to update subject info immediately
watchEffect(() => {
    const newSubject = getInitialSubjectInfo();
    if (newSubject.id !== subjectId.value) {
        subjectId.value = newSubject.id;

        // Only update subject name if we don't have a properly resolved name yet
        // Don't overwrite resolved subject names (like "English") with generic placeholders
        const hasResolvedName = subjectName.value !== 'Subject' && subjectName.value !== 'Mathematics' && subjectName.value !== 'Loading...' && isNaN(parseInt(subjectName.value)); // Current name is NOT a number

        if (!hasResolvedName) {
            subjectName.value = newSubject.name;
        }

        console.log(`Route changed - Updated to: ${subjectName.value} (ID: ${newSubject.id})`);

        // Only reload students data if not currently initializing and teacher is available
        if (!isInitializing.value && teacherId.value) {
            console.log('Route change detected - reloading students data');
            loadStudentsData().catch(console.warn);
        } else {
            console.log('Skipping route change reload - component is initializing');
        }
    }
});

// Initialize component
onMounted(async () => {
    console.log('🔄 Component mounted with:', `Subject (ID: ${subjectId.value})`);
    console.log(`🔍 Subject ID is NaN: ${isNaN(subjectId.value)}`);

    // Set initialization flag to prevent duplicate loads
    isInitializing.value = true;

    try {
        // Start preloading data in background (non-blocking)
        preloadData().catch(console.warn);

        // Initialize teacher data first
        await initializeTeacherData();

        // Load students and seating data (critical for UI)
        await loadStudentsData();

        // Clear loading state as soon as critical data is loaded
        isLoadingSeating.value = false;
        console.log('🎯 Critical data loaded, UI ready for interaction');

        // Load saved templates in background (non-blocking)
        loadSavedTemplates().catch(console.warn);

        // Fetch actual subject details if needed (handles both numeric IDs and string identifiers)
        if (subjectId.value && (subjectName.value === 'Subject' || subjectName.value === 'Loading...' || subjectName.value === subjectId.value || isNaN(subjectId.value))) {
            console.log(`Fetching subject details for identifier: ${subjectId.value}`);
            const subjectDetails = await fetchSubjectDetails(subjectId.value);
            console.log(`📋 Received subject details:`, subjectDetails);

            if (subjectDetails.id) {
                const newSubjectName = subjectDetails.name;
                const newSubjectId = subjectDetails.id;

                // Update reactive values - Vue will handle DOM updates automatically
                subjectId.value = newSubjectId;
                subjectName.value = newSubjectName;
                resolvedSubjectId.value = newSubjectId; // Store the resolved ID
                console.log(`✅ Updated subject to: ${newSubjectName} (ID: ${newSubjectId})`);

                // Force Vue reactivity update
                await nextTick();
                console.log(`🔄 DOM updated, current title should show: ${newSubjectName} Attendance`);
            } else {
                console.log(`❌ Failed to resolve subject ID for: ${subjectId.value}`);
            }
        } else {
            console.log(`⏭️ No subject ID resolution needed`);
        }

        console.log(`🎯 Final subject values - Name: ${subjectName.value}, ID: ${subjectId.value}`);

        // Initialize component in background without blocking UI
        initializeComponent().catch(console.warn);

        // Load today's attendance in background
        loadTodayAttendanceFromDatabase().catch(console.warn);
    } catch (error) {
        console.error('Error during component initialization:', error);
        toast.add({
            severity: 'error',
            summary: 'Initialization Error',
            detail: 'Failed to initialize component properly',
            life: 5000
        });
    } finally {
        // Clear initialization flag
        isInitializing.value = false;
        console.log('🏁 Component initialization completed');
    }
});

// Watch for session status changes and clear statuses when session becomes inactive
watch(sessionActive, (newValue, oldValue) => {
    console.log('Session active changed:', oldValue, '->', newValue);
    if (!newValue && oldValue) {
        // Session just became inactive - DO NOT clear statuses to keep visual feedback
        // clearStudentStatuses();  // Commented out to preserve green status after completion
        console.log('Session became inactive - keeping visual statuses visible');
    } else if (newValue && !oldValue) {
        // Session just became active, clear any cached statuses to start fresh
        console.log('Session became active - clearing cached statuses for fresh start');
        clearStudentStatuses();
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
    const selectedDate = currentDateString.value;
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
                                    // Only apply cached status if there's an active session
                                    if (sessionActive.value) {
                                        seatPlan.value[rowIndex][colIndex].status = cachedSeat.status;
                                    } else {
                                        seatPlan.value[rowIndex][colIndex].status = null; // Clear status when no active session
                                    }
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
    const seat = seatPlan.value[rowIndex][colIndex];

    // Find the student data
    const student = students.value.find((s) => s.id === seat.studentId);

    if (!student) {
        console.error('Student not found for seat:', rowIndex, colIndex);
        return;
    }

    // Store the selected seat with student data for reference
    selectedSeat.value = {
        rowIndex,
        colIndex,
        student: student,
        status: seat.status
    };

    console.log('Opening status selection dialog for seat:', rowIndex, colIndex, 'Student:', student.name, 'Current status:', seat.status);

    // Show the status selection dialog
    showStatusDialog.value = true;

    // Clear any pending status/remarks
    pendingStatus.value = seat.status || '';
    attendanceRemarks.value = '';
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

// Clean up on component unmount
onUnmounted(() => {
    // Preserve current assignments before unmounting
    preserveCurrentAssignments();

    // Stop scanning to release camera resources
    scanning.value = false;
    console.log('Component unmounting, camera resources released');

    // Clear all intervals
    if (timeInterval.value) {
        clearInterval(timeInterval.value);
        timeInterval.value = null;
    }

    if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }

    // Clear loading states
    isLoadingSeating.value = false;
    isLoadingStudents.value = false;
    loadingMessage.value = '';
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

// Handle seat click - cycle through attendance statuses
// Handle seat hover for quick actions
const handleSeatHover = (rowIndex, colIndex, event) => {
    const seat = seatPlan.value[rowIndex][colIndex];
    if (!seat.isOccupied || !sessionActive.value || isEditMode.value || justClicked.value) return;

    // Get total columns in the grid
    const totalColumns = seatPlan.value[0]?.length || 0;
    const totalRows = seatPlan.value.length || 0;

    // Determine transform origin based on GRID POSITION (not viewport)
    let transformOrigin = '';

    // Horizontal position in grid
    if (colIndex === 0) {
        // First column - expand to the RIGHT
        transformOrigin = 'left';
    } else if (colIndex === totalColumns - 1) {
        // Last column - expand to the LEFT
        transformOrigin = 'right';
    } else {
        // Middle columns - expand from center
        transformOrigin = 'center';
    }

    // Vertical position in grid
    if (rowIndex === 0) {
        // First row - expand DOWNWARD
        transformOrigin += ' top';
    } else if (rowIndex === totalRows - 1) {
        // Last row - expand UPWARD
        transformOrigin += ' bottom';
    } else {
        // Middle rows - expand from center
        transformOrigin += ' center';
    }

    hoveredSeat.value = {
        row: rowIndex,
        col: colIndex,
        transformOrigin
    };
};

const handleSeatLeave = () => {
    // Hide after a delay, but only if not hovering over actions
    setTimeout(() => {
        if (!showReasonDialog.value) {
            hoveredSeat.value = null;
        }
    }, 150);
};

const justClicked = ref(false);

const clearHoveredSeat = () => {
    hoveredSeat.value = null;
    justClicked.value = true;
    setTimeout(() => {
        justClicked.value = false;
    }, 300);
};

const handleSeatClick = async (rowIndex, colIndex) => {
    const seat = seatPlan.value[rowIndex][colIndex];
    if (!seat.isOccupied) return;

    // Check if session is active
    if (!sessionActive.value) {
        showAttendanceMethodModal.value = true;
        return;
    }

    // Just keep hover actions visible on click
};

// Handle quick action click
const handleQuickAction = async (status) => {
    if (!hoveredSeat.value) return;

    const { row, col } = hoveredSeat.value;
    const seat = seatPlan.value[row][col];
    console.log(`🎯 Quick action: ${status} for seat [${row}][${col}] with studentId: ${seat.studentId}`);

    const student = students.value.find((s) => s.student_id === seat.studentId || s.id === seat.studentId);
    console.log(`🔍 Found student:`, student);

    if (!student) {
        console.error(`❌ Student not found for seat studentId: ${seat.studentId}`);
        return;
    }

    // For Late or Excused, show reason dialog
    if (status === 'Late' || status === 'Excused') {
        pendingAttendanceUpdate.value = {
            student,
            status,
            rowIndex: row,
            colIndex: col,
            previousStatus: seat.status
        };
        reasonDialogType.value = status.toLowerCase();
        showReasonDialog.value = true;
        clearHoveredSeat(); // Hide quick actions
        return;
    }

    // For Present/Absent, save immediately
    // Map status names to numeric values for visual display
    const statusMap = {
        Present: 1,
        Absent: 2,
        Late: 3,
        Excused: 4
    };

    seatPlan.value[row][col].status = statusMap[status] || 1;
    console.log(`✅ Set seat [${row}][${col}] status to ${statusMap[status]} (${status})`);
    clearHoveredSeat(); // Hide quick actions

    try {
        await saveAttendanceToDatabase(student.id, status, '', null, null);

        toast.add({
            severity: 'success',
            summary: 'Attendance Updated',
            detail: `${student.name || student.firstName + ' ' + student.lastName} marked as ${status}`,
            life: 2000
        });
    } catch (error) {
        console.error('Error saving attendance:', error);
        // Revert the change if save failed
        seatPlan.value[row][col].status = seat.status;

        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save attendance',
            life: 3000
        });
    }
};

// Handle reason dialog confirmation
const onReasonConfirmed = async (reasonData) => {
    if (!pendingAttendanceUpdate.value) return;

    const { student, status, seat, rowIndex, colIndex, newStatus } = pendingAttendanceUpdate.value;
    const finalStatus = newStatus || status;

    // Check if this is from roll call (has seat object) or seat plan (has rowIndex/colIndex)
    if (seat) {
        // Roll call - update the seat object and continue roll call
        seat.status = finalStatus;
        await processRollCallAttendance(finalStatus, reasonData.reason_notes || '', reasonData.reason_id);
    } else if (rowIndex !== undefined && colIndex !== undefined) {
        // Seat plan - update seat by coordinates
        seatPlan.value[rowIndex][colIndex].status = finalStatus;

        // Save to database with reason
        try {
            await saveAttendanceToDatabase(student.id, finalStatus, '', reasonData.reason_id, reasonData.reason_notes);

            toast.add({
                severity: 'success',
                summary: 'Attendance Updated',
                detail: `${student.name || student.firstName + ' ' + student.lastName} marked as ${finalStatus} - ${reasonData.reason_name}`,
                life: 3000
            });
        } catch (error) {
            console.error('Error saving attendance with reason:', error);
            // Revert the change if save failed
            seatPlan.value[rowIndex][colIndex].status = pendingAttendanceUpdate.value.previousStatus;

            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to save attendance',
                life: 3000
            });
        }
    }

    // Clear pending update
    pendingAttendanceUpdate.value = null;
};

const showStartSessionConfirmation = () => {
    return new Promise((resolve) => {
        const confirmed = confirm('No active session found. Would you like to start a new attendance session?');
        resolve(confirmed);
    });
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
    const today = currentDateString.value;
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
    const today = currentDateString.value;
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
    scannedStudents.value = [];

    // Open the attendance method selection dialog
    showAttendanceMethodModal.value = true;

    console.log('Opening attendance method dialog', showAttendanceMethodModal.value);
};

// Enhanced QR scanner with gate log functionality
const qrScanLog = ref([]);
const qrScanResults = ref([]);
const cameraInitialized = ref(false);

const stopQRScanner = () => {
    console.log('Stopping QR scanner...');
    console.log('🎯 Updating seating arrangement with QR scan results:', qrScanResults.value);

    // Update seating arrangement with QR scan results
    qrScanResults.value.forEach((result) => {
        const seat = findSeatByStudentId(result.studentId);
        if (seat) {
            seat.status = result.status;
            console.log(`✅ Updated seat for ${result.name}: ${result.status}`);
        } else {
            console.warn(`⚠️ No seat found for student ${result.name} (ID: ${result.studentId})`);
        }
    });

    // Force reactivity update
    seatPlan.value = [...seatPlan.value];

    scanning.value = false;
    showQRScanner.value = false;

    toast.add({
        severity: 'success',
        summary: 'Scanner Closed',
        detail: `Updated ${qrScanResults.value.length} students in seating arrangement`,
        life: 3000
    });
};

const reopenQRScanner = () => {
    console.log('🔄 Reopening QR scanner...');
    showQRScanner.value = true;
    scanning.value = true;

    toast.add({
        severity: 'info',
        summary: 'Scanner Reopened',
        detail: 'QR scanner is ready to scan more students',
        life: 2000
    });
};

const changeAttendanceMethod = () => {
    console.log('🔄 Changing attendance method...');

    // Close QR scanner if it's open
    if (showQRScanner.value) {
        stopQRScanner();
    }

    // Show method selection modal
    showAttendanceMethodModal.value = true;

    toast.add({
        severity: 'info',
        summary: 'Change Method',
        detail: 'Select a new attendance method. Current progress will be preserved.',
        life: 3000
    });
};

const cancelAttendanceSession = async () => {
    console.log('❌ Canceling attendance session...');

    // Show confirmation dialog
    const confirmed = await showConfirmDialog('Cancel Session', 'Are you sure you want to cancel this attendance session? All progress will be lost.', 'Yes, Cancel Session', 'Keep Session');

    if (!confirmed) {
        return;
    }

    try {
        // Close QR scanner if open
        if (showQRScanner.value) {
            scanning.value = false;
            showQRScanner.value = false;
        }

        // Mark session as completed on backend (cancel endpoint doesn't exist)
        if (currentSession.value?.id) {
            try {
                // Use complete endpoint to mark session as finished
                await AttendanceSessionService.completeSession(currentSession.value.id);
                console.log('✅ Session marked as completed in database');
            } catch (error) {
                console.warn('Could not update session status in database:', error);
                // Continue with frontend cleanup anyway
            }
        }

        // Reset all session data
        sessionActive.value = false;
        currentSession.value = null;
        attendanceMethod.value = null;

        // Clear QR scan data
        clearQRScanData();

        // Clear all student statuses from seating arrangement
        seatPlan.value.forEach((row) => {
            row.forEach((seat) => {
                if (seat.student) {
                    seat.status = null;
                }
            });
        });

        // Force reactivity update
        seatPlan.value = [...seatPlan.value];

        console.log('🧹 Session canceled - all data cleared');

        toast.add({
            severity: 'warn',
            summary: 'Session Canceled',
            detail: 'Attendance session has been canceled. All progress has been cleared.',
            life: 4000
        });
    } catch (error) {
        console.error('Error canceling session:', error);

        toast.add({
            severity: 'error',
            summary: 'Cancel Error',
            detail: 'Failed to cancel session properly. Please try again.',
            life: 3000
        });
    }
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

// Enhanced QR decode handler with gate log
const onQRDecode = async (decodedText) => {
    console.log('🔍 QR DECODE EVENT FIRED!');
    console.log('Raw decoded text:', decodedText);
    console.log('Type of decoded text:', typeof decodedText);

    const timestamp = new Date().toLocaleTimeString();
    console.log('QR Code decoded:', decodedText);
    console.log(
        'Available students:',
        students.value.map((s) => ({ id: s.id, name: s.name, studentId: s.studentId, student_id: s.student_id }))
    );

    try {
        // Add to scan log
        qrScanLog.value.unshift({
            timestamp,
            message: `Scanned QR Code: ${decodedText}`,
            success: true
        });

        // Parse LAMMS QR code format: LAMMS_STUDENT_[ID]_[timestamp]_[hash]
        let student = null;
        let extractedStudentId = null;

        // Method 1: Parse LAMMS format QR code
        if (decodedText.startsWith('LAMMS_STUDENT_')) {
            const parts = decodedText.split('_');
            if (parts.length >= 3) {
                extractedStudentId = parseInt(parts[2]); // Extract student ID from position 2
                console.log('Extracted student ID from LAMMS format:', extractedStudentId);

                // Try multiple matching strategies for LAMMS format
                student = students.value.find(
                    (s) =>
                        s.id === extractedStudentId ||
                        s.studentId === extractedStudentId ||
                        s.student_id === extractedStudentId ||
                        parseInt(s.id) === extractedStudentId ||
                        parseInt(s.studentId) === extractedStudentId ||
                        parseInt(s.student_id) === extractedStudentId
                );
            }
        }

        // Method 2: Direct numeric ID match
        if (!student && !isNaN(decodedText)) {
            const studentId = parseInt(decodedText);
            student = students.value.find((s) => s.id === studentId || s.studentId === studentId || s.student_id === studentId || parseInt(s.id) === studentId || parseInt(s.studentId) === studentId || parseInt(s.student_id) === studentId);
        }

        // Method 3: String match against student ID fields
        if (!student) {
            student = students.value.find((s) => s.studentId?.toString() === decodedText || s.student_id?.toString() === decodedText || s.id?.toString() === decodedText);
        }

        // Method 4: Partial ID match (for cases where QR contains partial student info)
        if (!student && extractedStudentId) {
            student = students.value.find((s) => {
                const studentIdStr = s.studentId?.toString() || s.student_id?.toString() || s.id?.toString() || '';
                return studentIdStr.includes(extractedStudentId.toString());
            });
        }

        console.log('Found student:', student);
        console.log('Debug - Extracted ID:', extractedStudentId);
        const availableIds = students.value.map((s) => ({ 
            id: s.id, 
            studentId: s.studentId, 
            student_id: s.student_id,
            name: s.name 
        }));
        console.log('Debug - Available student IDs:', availableIds);
        console.log('Debug - Total students loaded:', students.value.length);

        if (student) {
            // Check if already scanned
            const existingIndex = qrScanResults.value.findIndex((s) => s.studentId === student.id);

            if (existingIndex === -1) {
                // New scan - mark as present
                qrScanResults.value.push({
                    id: student.id,
                    studentId: student.id,
                    name: student.name,
                    status: 'Present',
                    scannedAt: new Date().toISOString()
                });

                // Save attendance record
                await saveAttendanceRecord(student.id, 'Present', 'QR Code scan');

                // Update seat plan if student has a seat
                const seat = findSeatByStudentId(student.id);
                if (seat) {
                    seat.status = 'Present';
                }

                // Add success log
                qrScanLog.value.unshift({
                    timestamp,
                    message: `✓ ${student.name} marked as Present`,
                    success: true
                });

                toast.add({
                    severity: 'success',
                    summary: 'Student Scanned',
                    detail: `${student.name} marked as Present`,
                    life: 2000
                });
            } else {
                // Already scanned
                qrScanLog.value.unshift({
                    timestamp,
                    message: `⚠ ${student.name} already scanned`,
                    success: false
                });

                toast.add({
                    severity: 'warn',
                    summary: 'Already Scanned',
                    detail: `${student.name} has already been scanned`,
                    life: 2000
                });
            }
        } else {
            // Student not found - show detailed info for debugging
            console.warn('Student not found for QR code:', decodedText);
            console.log('Tried matching against:', {
                ids: students.value.map((s) => s.id),
                studentIds: students.value.map((s) => s.studentId),
                student_ids: students.value.map((s) => s.student_id),
                names: students.value.map((s) => s.name)
            });

            qrScanLog.value.unshift({
                timestamp,
                message: `❌ Student not found for QR: ${decodedText}`,
                success: false
            });

            toast.add({
                severity: 'error',
                summary: 'Student Not In This Class',
                detail: extractedStudentId ? `Student ID ${extractedStudentId} is not enrolled in this section. Please check if they belong to a different class.` : `QR code "${decodedText}" does not match any student in this class.`,
                life: 5000
            });
        }
    } catch (error) {
        console.error('Error processing QR code:', error);
        qrScanLog.value.unshift({
            timestamp,
            message: `❌ Error processing QR: ${error.message}`,
            success: false
        });

        toast.add({
            severity: 'error',
            summary: 'QR Processing Error',
            detail: error.message,
            life: 3000
        });
    }
};

const onQRDetect = async (detectedCodes) => {
    console.log('🎯 QR DETECT EVENT FIRED!');
    console.log('Detected codes:', detectedCodes);

    if (detectedCodes && detectedCodes.length > 0) {
        const code = detectedCodes[0];
        console.log('First detected code:', code);
        if (code.rawValue) {
            await onQRDecode(code.rawValue);
        }
    }
};

const onCameraInit = async (promise) => {
    console.log('📷 Camera initialization started...');
    try {
        await promise;
        cameraInitialized.value = true;
        cameraError.value = null;
        console.log('✅ Camera initialized successfully');
        console.log('🔍 QR scanner should now be active and detecting codes');
    } catch (error) {
        console.error('❌ Camera initialization failed:', error);
        cameraError.value = error.message || 'Failed to initialize camera';
        cameraInitialized.value = false;
    }
};

const testQRDetection = async () => {
    console.log('🧪 Testing QR Detection manually...');

    // Test with a sample student ID
    const testStudentId = '1';
    console.log('Testing with student ID:', testStudentId);

    // Simulate QR decode
    await onQRDecode(testStudentId);

    toast.add({
        severity: 'info',
        summary: 'QR Test',
        detail: `Tested QR detection with student ID: ${testStudentId}`,
        life: 3000
    });
};

const completeQRSession = async () => {
    try {
        // 🚨 CRITICAL FIX: Update seat plan with QR scan results BEFORE completing session
        if (qrScanResults.value.length > 0) {
            console.log('🎯 Transferring QR scan results to seat plan before session completion');
            qrScanResults.value.forEach((result) => {
                const seat = findSeatByStudentId(result.studentId);
                if (seat) {
                    seat.status = result.status;
                    console.log(`✅ Updated seat for ${result.name}: ${result.status}`);
                } else {
                    console.warn(`⚠️ No seat found for student ${result.name} (ID: ${result.studentId})`);
                }
            });
            // Force reactivity update
            seatPlan.value = [...seatPlan.value];
        }

        // Close QR scanner UI
        showQRScanner.value = false;

        // Start loading animation (same as seating plan method)
        isCompletingSession.value = true;
        sessionCompletionProgress.value = 0;

        // Start progressive animation that runs parallel to API calls
        const progressInterval = setInterval(() => {
            if (sessionCompletionProgress.value < 85) {
                sessionCompletionProgress.value += Math.random() * 1.5 + 0.5; // Slower increment 0.5-2%
            }
        }, 150); // Slower interval

        // Step 1: Initial progress
        sessionCompletionProgress.value = 10;
        await new Promise((resolve) => setTimeout(resolve, 800));

        // Step 2: Send seat plan data to backend (skip if already saved via QR)
        sessionCompletionProgress.value = 25;
        console.log('📤 [QR-COMPLETE] Sending seat plan attendance to backend...');
        try {
            await AttendanceSessionService.markSeatPlanAttendance(
                currentSession.value.id,
                seatPlan.value,
                attendanceStatuses.value
            );
            console.log('✅ [QR-COMPLETE] Attendance data sent successfully');
        } catch (error) {
            console.warn('⚠️ [QR-COMPLETE] Seat plan attendance already saved via QR scans, skipping...', error.message);
            // Continue anyway - data is already in backend from QR scans
        }
        await new Promise((resolve) => setTimeout(resolve, 600));

        // Step 3: Complete session
        const response = await AttendanceSessionService.completeSession(currentSession.value.id);

        // Step 3: Process response
        sessionCompletionProgress.value = 60;
        await new Promise((resolve) => setTimeout(resolve, 700));

        sessionSummary.value = response.summary;
        sessionActive.value = false;
        currentSession.value = null;

        // Step 4: Finalize
        sessionCompletionProgress.value = 80;
        await new Promise((resolve) => setTimeout(resolve, 500));

        // Step 5: Prepare modal (keep loading visible)
        sessionCompletionProgress.value = 95;

        // Save session data but don't show modal yet
        saveCompletedSession(response.summary);

        // Final progress update
        sessionCompletionProgress.value = 100;
        await new Promise((resolve) => setTimeout(resolve, 500));

        // Clear interval and hide loading
        clearInterval(progressInterval);
        isCompletingSession.value = false;
        sessionCompletionProgress.value = 0;

        // Show completion modal after loading is done
        showCompletionModal.value = true;

        toast.add({
            severity: 'success',
            summary: 'QR Session Complete',
            detail: `${qrScanResults.value.length} students scanned and session completed`,
            life: 3000
        });
    } catch (error) {
        console.error('Error completing QR session:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to complete session',
            life: 3000
        });
    }
};

// Save attendance status from dialog with database integration
const saveAttendanceStatus = async () => {
    if (!pendingStatus.value || !selectedSeat.value) return;

    try {
        const student = selectedSeat.value.student;
        const rowIndex = selectedSeat.value.rowIndex;
        const colIndex = selectedSeat.value.colIndex;

        // Save to database via API
        const attendanceData = {
            student_id: student.id,
            status: pendingStatus.value.toLowerCase(), // Convert to lowercase for database
            remarks: attendanceRemarks.value || '',
            date: currentDateString.value
        };

        // Use the teacher attendance service to mark attendance
        await TeacherAttendanceService.markAttendance(attendanceData);

        // Update seat plan
        seatPlan.value[rowIndex][colIndex].status = pendingStatus.value;

        // Refresh attendance data from database to ensure consistency
        await loadTodayAttendanceFromDatabase();

        // Close dialog and reset
        showStatusDialog.value = false;
        pendingStatus.value = '';
        attendanceRemarks.value = '';
        selectedSeat.value = null;

        toast.add({
            severity: 'success',
            summary: 'Status Updated',
            detail: `${student.name} marked as ${pendingStatus.value}`,
            life: 3000
        });
    } catch (error) {
        console.error('Error saving attendance status:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: error.message || 'Failed to save attendance status',
            life: 3000
        });
    }
};

// Save attendance to session-based database
const saveAttendanceToDatabase = async (studentId, status, remarks = '', reasonId = null, reasonNotes = null) => {
    try {
        if (!currentSession.value || !currentSession.value.id) {
            throw new Error('No active session found');
        }

        // Find status ID from loaded attendanceStatuses
        let attendanceStatusId;
        const statusRecord = attendanceStatuses.value.find((s) => {
            // Try multiple matching strategies
            return (
                s.name === status ||
                s.code === status.charAt(0).toUpperCase() ||
                (status === 'Present' && (s.code === 'P' || s.name === 'Present')) ||
                (status === 'Absent' && (s.code === 'A' || s.name === 'Absent')) ||
                (status === 'Late' && (s.code === 'L' || s.name === 'Late')) ||
                (status === 'Excused' && (s.code === 'E' || s.name === 'Excused'))
            );
        });

        if (!statusRecord) {
            console.error('Available attendance statuses:', attendanceStatuses.value);
            throw new Error(`Status not found in database: ${status}`);
        }

        attendanceStatusId = statusRecord.id;

        // Use session-based attendance API
        const attendanceData = {
            student_id: parseInt(studentId),
            attendance_status_id: attendanceStatusId,
            arrival_time: new Date().toTimeString().split(' ')[0],
            remarks: remarks || null,
            reason_id: reasonId || null,
            reason_notes: reasonNotes || null,
            marking_method: 'manual'
        };

        console.log('Saving session attendance with data:', attendanceData);
        const response = await AttendanceSessionService.markSessionAttendance(currentSession.value.id, [attendanceData]);
        console.log('Session attendance saved successfully:', response);

        return response;
    } catch (error) {
        console.error('Error saving session attendance to database:', error);
        throw error;
    }
};

// Clear all student statuses when no session is active
const clearStudentStatuses = () => {
    console.log('Clearing all student statuses - no active session');
    for (let i = 0; i < seatPlan.value.length; i++) {
        for (let j = 0; j < seatPlan.value[i].length; j++) {
            if (seatPlan.value[i][j].studentId) {
                seatPlan.value[i][j].status = null;
            }
        }
    }
};

// Debug function to force complete current session (can be called from console)
const forceCompleteCurrentSession = async () => {
    if (currentSession.value) {
        try {
            console.log('Force completing session:', currentSession.value.id);
            await AttendanceSessionService.completeSession(currentSession.value.id);
            sessionActive.value = false;
            currentSession.value = null;
            clearStudentStatuses();
            console.log('Session force completed successfully');
        } catch (error) {
            console.error('Error force completing session:', error);
        }
    } else {
        console.log('No active session to complete');
    }
};

// Make debug function available globally for console access
if (typeof window !== 'undefined') {
    window.forceCompleteCurrentSession = forceCompleteCurrentSession;
}

// Load attendance data from database for today
const loadTodayAttendanceFromDatabase = async () => {
    try {
        const today = currentDateString.value;
        console.log('Loading attendance from database for date:', today);

        // Validate required parameters before making API call
        if (!sectionId.value || sectionId.value === '' || sectionId.value === 'null') {
            console.warn('Cannot load attendance: section_id is missing');
            return;
        }

        if (!subjectId.value || subjectId.value === '' || subjectId.value === 'null') {
            console.warn('Cannot load attendance: subject_id is missing');
            return;
        }

        // Fetch students with their attendance data from API
        const response = await TeacherAttendanceService.getStudentsForTeacherSubject(teacherId.value, sectionId.value, subjectId.value);

        if (response && response.students) {
            // Clear existing attendance records and rebuild from database
            attendanceRecords.value = {};

            // Apply attendance status to seat plan and rebuild attendance records
            response.students.forEach((student) => {
                // Check if student has attendance for today
                const todayAttendance = student.attendance?.find((a) => a.date === today);

                if (todayAttendance) {
                    // Update attendance records object
                    const recordKey = `${student.id}-${today}`;
                    attendanceRecords.value[recordKey] = {
                        studentId: student.id,
                        date: today,
                        status: todayAttendance.status,
                        remarks: todayAttendance.remarks || '',
                        timestamp: todayAttendance.marked_at || new Date().toISOString()
                    };
                }

                // Find and update the student's seat in the seat plan
                for (let i = 0; i < seatPlan.value.length; i++) {
                    for (let j = 0; j < seatPlan.value[i].length; j++) {
                        if (seatPlan.value[i][j].studentId === student.id) {
                            // Only show attendance status if there's an active session
                            // If no active session, clear any status to show neutral state
                            if (sessionActive.value) {
                                seatPlan.value[i][j].status = todayAttendance?.status || null;
                            } else {
                                seatPlan.value[i][j].status = null; // Clear status when no active session
                            }
                            break;
                        }
                    }
                }
            });

            console.log('Loaded attendance records from database:', attendanceRecords.value);
            console.log('Session active:', sessionActive.value, '- Status display:', sessionActive.value ? 'enabled' : 'disabled');
        }
    } catch (error) {
        console.error('Error loading attendance from database:', error);
        toast.add({
            severity: 'error',
            summary: 'Database Error',
            detail: 'Failed to load attendance data from database',
            life: 3000
        });
    }
};

// Helper function to get status severity for PrimeVue Tag component
const getStatusSeverity = (status) => {
    switch (status?.toLowerCase()) {
        case 'present':
            return 'success';
        case 'absent':
            return 'danger';
        case 'late':
            return 'warning';
        case 'excused':
            return 'info';
        default:
            return 'secondary';
    }
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

// Helper function to find seat by student ID
const findSeatByStudentId = (studentId) => {
    // Normalize the search ID to handle both numeric and string formats
    const searchId = studentId?.toString();
    
    for (let i = 0; i < seatPlan.value.length; i++) {
        for (let j = 0; j < seatPlan.value[i].length; j++) {
            const seat = seatPlan.value[i][j];
            const seatStudentId = seat.studentId?.toString();
            
            // Match if:
            // 1. Exact match (e.g., "3237" === "3237" or "NCS-2025-03237" === "NCS-2025-03237")
            // 2. Numeric ID matches the end of prefixed ID (e.g., "3237" matches "NCS-2025-03237")
            // 3. Prefixed ID ends with the numeric ID (e.g., "NCS-2025-03237" ends with "3237")
            if (seatStudentId === searchId || 
                seatStudentId?.endsWith(searchId) || 
                searchId?.endsWith(seatStudentId)) {
                return seat;
            }
        }
    }
    return null;
};

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

// Attendance Completion Modal Management Functions
const checkCompletedSessionPersistence = () => {
    const today = new Date().toISOString().split('T')[0];
    const completionKey = `attendance_completion_${today}`;
    const dismissKey = `completion_dismissed_${today}`;

    const completionData = localStorage.getItem(completionKey);
    const isDismissed = localStorage.getItem(dismissKey) === 'true';

    if (completionData && !isDismissed) {
        const data = JSON.parse(completionData);
        const completionTime = new Date(data.timestamp);
        const now = new Date();

        // Check if it's still the same day and before 11:59 PM
        if (completionTime.toDateString() === now.toDateString() && now.getHours() < 24) {
            // Check if the subject name is "Loading..." and fix it
            if (data.sessionData.subject_name === 'Loading...' || data.sessionData.subject_name === 'Subject') {
                console.log('🔧 Clearing completion data with invalid subject name:', data.sessionData.subject_name);
                localStorage.removeItem(completionKey);
                localStorage.removeItem(dismissKey);
                return; // Don't show modal with invalid data
            }

            completedSessionData.value = data.sessionData;
            showCompletionModal.value = true;
            setupMidnightTimer();
        }
    }
};

const saveCompletedSession = (sessionData) => {
    const today = new Date().toISOString().split('T')[0];
    const completionKey = `attendance_completion_${today}`;

    const completionData = {
        timestamp: new Date().toISOString(),
        sessionData: {
            ...sessionData,
            subject_name: subjectName.value, // Store the subject name when session was completed
            section_name: currentSectionName.value
        }
    };

    localStorage.setItem(completionKey, JSON.stringify(completionData));
    completedSessionData.value = {
        ...sessionData,
        subject_name: subjectName.value,
        section_name: currentSectionName.value
    };

    // Add notification to the system with correct method
    const methodNames = {
        qr: 'QR Code Scan',
        seat_plan: 'Seat Plan',
        roll_call: 'Roll Call'
    };

    const sessionDataWithMethod = {
        ...sessionData,
        method: methodNames[attendanceMethod.value] || 'Manual Entry'
    };
    // NotificationService.addSessionCompletionNotification(sessionDataWithMethod);

    // Show modal after 5-10 seconds delay as requested
    setTimeout(() => {
        showCompletionModal.value = true;
        modalDismissedToday.value = false;

        // Clear any previous dismissal
        localStorage.removeItem(`completion_dismissed_${today}`);

        setupMidnightTimer();
        setupAutoHideTimer();
    }, 7000); // 7 second delay
};

const setupAutoHideTimer = () => {
    // Auto-hide modal after 15 seconds
    setTimeout(() => {
        if (showCompletionModal.value && !modalDismissedToday.value) {
            showCompletionModal.value = false;
        }
    }, 15000);
};

const setupMidnightTimer = () => {
    if (completionModalTimer.value) {
        clearTimeout(completionModalTimer.value);
    }

    const now = new Date();
    const midnight = new Date();
    midnight.setHours(23, 59, 59, 999);

    const timeUntilMidnight = midnight.getTime() - now.getTime();

    if (timeUntilMidnight > 0) {
        completionModalTimer.value = setTimeout(() => {
            hideCompletionModal();
            clearCompletedSessionData();
        }, timeUntilMidnight);
    }
};

const clearCompletedSessionData = () => {
    const today = new Date().toISOString().split('T')[0];
    localStorage.removeItem(`attendance_completion_${today}`);
    localStorage.removeItem(`completion_dismissed_${today}`);
    completedSessionData.value = null;
    showCompletionModal.value = false;
    modalDismissedToday.value = false;
};

// Modal Event Handlers
const handleModalClose = () => {
    showCompletionModal.value = false;
};

const handleDontShowAgain = () => {
    const today = new Date().toISOString().split('T')[0];
    localStorage.setItem(`completion_dismissed_${today}`, 'true');
    modalDismissedToday.value = true;
    showCompletionModal.value = false;
};

const handleViewDetails = () => {
    console.log('View details clicked');
    // Navigate to attendance records page with current session data
    router.push({
        name: 'teacher-attendance-records',
        query: {
            sessionId: completedSessionData.value?.id,
            subjectId: subjectId.value,
            subjectName: subjectName.value,
            sessionDate: new Date().toISOString().split('T')[0]
        }
    });
};

const handleEditAttendance = () => {
    console.log('Edit attendance clicked');
    editSessionData.value = completedSessionData.value;
    showEditDialog.value = true;
    showCompletionModal.value = false; // Close completion modal
};

const handleEditSave = (changes) => {
    console.log('Saving attendance changes:', changes);
    // Implement save logic here
    showEditDialog.value = false;

    // Show success message
    toast.add({
        severity: 'success',
        summary: 'Attendance Updated',
        detail: `Updated ${changes.length} student records`,
        life: 3000
    });
};

const handleEditClose = () => {
    showEditDialog.value = false;
    editSessionData.value = null;
};

const handleStartNewSession = async () => {
    if (sessionActive.value) {
        // If session is active, stop it and show modal
        try {
            const response = await AttendanceSessionService.completeSession(currentSession.value.id);
            saveCompletedSession(response.summary);
            sessionActive.value = false;
            currentSession.value = null;
        } catch (error) {
            console.error('Error completing session:', error);
        }
    } else {
        // If no active session, hide modal and start new session
        showCompletionModal.value = false;
        modalDismissedToday.value = true;

        // Start new session logic here
        toast.add({
            severity: 'success',
            summary: 'New Session',
            detail: 'Ready to start a new attendance session',
            life: 3000
        });
    }
};

const hideCompletionModal = () => {
    showCompletionModal.value = false;
};

// Helper functions for session details
const getStatusLabel = (statusId) => {
    const statusMap = {
        1: 'Present',
        2: 'Absent',
        3: 'Late',
        4: 'Excused'
    };
    return statusMap[statusId] || 'Unknown';
};

const exportSessionReport = () => {
    // Export functionality - can be implemented later
    toast.add({
        severity: 'info',
        summary: 'Export Report',
        detail: 'Report export functionality will be implemented soon',
        life: 3000
    });
};

// Add a function to apply attendance statuses to the seat plan after loading
const applyAttendanceStatusesToSeatPlan = () => {
    // Skip if no attendance records
    if (!attendanceRecords.value || Object.keys(attendanceRecords.value).length === 0) return;

    // Get today's date
    const today = currentDateString.value;

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
        // Make localStorage key subject-specific
        const resolvedSubjectId = getResolvedSubjectId();
        const storageKey = `seatPlan_section_${sectionId.value}_subject_${resolvedSubjectId}`;
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

// Cleanup function for intervals and timers
onUnmounted(() => {
    // Clear all intervals
    if (timeInterval.value) {
        clearInterval(timeInterval.value);
    }
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }

    // Clear all timers
    if (completionModalTimer.value) {
        clearTimeout(completionModalTimer.value);
    }

    // Clear any active loading states
    if (isCompletingSession.value) {
        isCompletingSession.value = false;
        sessionCompletionProgress.value = 0;
    }

    // Reset session states
    sessionActive.value = false;
    currentSession.value = null;

    // Close any open modals
    showCompletionModal.value = false;
    showQRScanner.value = false;
    showAttendanceMethodModal.value = false;
    showStatusDialog.value = false;
    showRemarksDialog.value = false;
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

// Track if we're currently restoring assignments to prevent grid reset
const isRestoringAssignments = ref(false);

// Store the resolved subject ID from API
const resolvedSubjectId = ref(null);

// Helper function to get resolved subject ID
const getResolvedSubjectId = () => {
    // First priority: Use the resolved ID from API if available
    if (resolvedSubjectId.value) {
        console.log(`Using resolved subject ID from API: ${resolvedSubjectId.value}`);
        return resolvedSubjectId.value;
    }

    // Second priority: If subjectId is already numeric, use it
    if (typeof subjectId.value === 'number' || !isNaN(Number(subjectId.value))) {
        const numericId = Number(subjectId.value);
        console.log(`Using numeric subject ID: ${numericId}`);
        return numericId;
    }

    // Third priority: Try to map by subject name
    const subjectMapping = {
        Arts: 5,
        English: 1,
        Filipino: 2,
        Mathematics: 3,
        Science: 4,
        'Technology and Livelihood Education': 23, // Updated to correct ID
        TLE: 23
    };

    if (subjectName.value && subjectMapping[subjectName.value]) {
        console.log(`Resolved subject ID by name: ${subjectName.value} → ${subjectMapping[subjectName.value]}`);
        return subjectMapping[subjectName.value];
    }

    // Last resort: Map route parameter to numeric ID (fallback only)
    const routeMapping = {
        arts: 5,
        english: 1,
        filipino: 2,
        mathematics: 3,
        science: 4,
        technologyandlivelihoodeducation: 23, // Updated to correct ID
        technology: 23,
        tle: 23
    };

    if (routeMapping[subjectId.value]) {
        console.log(`Resolved subject ID by route (fallback): ${subjectId.value} → ${routeMapping[subjectId.value]}`);
        return routeMapping[subjectId.value];
    }

    console.warn(`Could not resolve subject ID for: name="${subjectName.value}", id="${subjectId.value}"`);
    return subjectId.value;
};
// Calculate optimal grid size for students (accommodates up to 50+ students)
const calculateOptimalGridSize = (studentCount) => {
    if (studentCount <= 0) return { rows: 4, columns: 5 };

    // For 20 students, use exact fit arrangements to avoid empty seats
    if (studentCount <= 20) {
        return { rows: 4, columns: 5 }; // Exactly 20 seats
    }

    // Optimal arrangements for different student counts (up to 50+ students):
    const arrangements = [
        { rows: 5, columns: 5 }, // 25 seats
        { rows: 5, columns: 6 }, // 30 seats
        { rows: 6, columns: 6 }, // 36 seats
        { rows: 6, columns: 7 }, // 42 seats
        { rows: 7, columns: 7 }, // 49 seats
        { rows: 7, columns: 8 }, // 56 seats (accommodates 50+ students)
        { rows: 8, columns: 7 }, // 56 seats (alternative layout)
        { rows: 8, columns: 8 } // 64 seats (for very large classes)
    ];

    // Find the arrangement that fits the students with minimal empty seats (max 6 empty)
    for (const arrangement of arrangements) {
        const totalSeats = arrangement.rows * arrangement.columns;
        const emptySeats = totalSeats - studentCount;
        if (totalSeats >= studentCount && emptySeats <= 6) {
            return arrangement;
        }
    }

    // Fallback: calculate square-ish grid
    const sqrt = Math.ceil(Math.sqrt(studentCount));
    return { rows: sqrt, columns: sqrt };
};

// Watch for rows and columns changes to ensure grid updates (with debouncing)
let gridUpdateTimeout = null;
watch([rows, columns], ([newRows, newColumns], [oldRows, oldColumns]) => {
    console.log(`Grid dimensions changed: ${oldRows}×${oldColumns} → ${newRows}×${newColumns}`);

    // Don't update grid if we're currently restoring assignments
    if (isRestoringAssignments.value) {
        console.log('Skipping grid update - currently restoring assignments');
        return;
    }

    // Debounce grid updates to prevent rapid changes
    if (gridUpdateTimeout) {
        clearTimeout(gridUpdateTimeout);
    }

    gridUpdateTimeout = setTimeout(() => {
        if (newRows !== oldRows || newColumns !== oldColumns) {
            updateGridSize();
        }
    }, 300);
});

// Watch for students loading to trigger cleanup if needed
watch(
    students,
    (newStudents, oldStudents) => {
        if (newStudents && newStudents.length > 0 && (!oldStudents || oldStudents.length === 0)) {
            console.log('Students loaded, checking if cleanup is needed');
            // Small delay to ensure seating arrangement is also loaded
            setTimeout(() => {
                const hasAssignedSeats = seatPlan.value.some((row) => row.some((seat) => seat.isOccupied && seat.studentId));
                if (hasAssignedSeats) {
                    console.log('Found assigned seats, running cleanup to validate assignments');
                    cleanupInvalidStudentAssignments();
                }
            }, 500);
        }
    },
    { deep: true }
);

// Fix reference to isDropTarget
const isDropTarget = ref(false);

// Template ref for title element
const titleRef = ref(null);
</script>

<template>
    <div class="attendance-container p-4">
        <!-- Header with title and date/time -->
        <div class="header-section mb-4">
            <div class="flex justify-content-between align-items-start">
                <div class="header-left">
                    <h2 class="text-2xl font-bold text-gray-800 mb-3" ref="titleRef">{{ subjectName }} Attendance</h2>
                    <div class="header-info flex align-items-center gap-4">
                        <div class="calendar-section flex align-items-center gap-2 text-sm text-gray-600">
                            <i class="pi pi-calendar text-gray-500"></i>
                            <DatePicker v-model="currentDate" dateFormat="yy-mm-dd" :showIcon="false" />
                        </div>
                        <!-- Session Status Indicator -->
                        <div v-if="sessionActive" class="session-status active">
                            <i class="pi pi-circle-fill"></i>
                            <span>ACTIVE SESSION</span>
                        </div>
                        <div v-else class="session-status inactive">
                            <i class="pi pi-circle"></i>
                            <span>NO ACTIVE SESSION</span>
                        </div>
                    </div>
                </div>
                <div class="date-time-display">
                    <div class="date">{{ new Date().toLocaleDateString() }}</div>
                    <div class="time">{{ currentDateTime.toLocaleTimeString() }}</div>
                </div>
            </div>
        </div>

        <!-- Action Buttons - Smart Contextual Display -->
        <div class="action-buttons flex flex-wrap gap-2 mb-4">
            <!-- EDIT MODE BUTTONS - Only show when NOT in session -->
            <template v-if="!sessionActive">
                <Button icon="pi pi-pencil" label="Edit Seats" class="p-button-success" :class="{ 'p-button-outlined': !isEditMode }" @click="toggleEditMode" />

                <!-- Auto Assignment - Only in Edit Mode -->
                <div v-if="isEditMode" class="auto-assign-container">
                    <Button icon="pi pi-users" label="Auto Assign" class="p-button-info" @click="showAssignmentOptions = !showAssignmentOptions" />
                    <div v-if="showAssignmentOptions" class="assignment-options-overlay">
                        <div class="assignment-options-panel">
                            <h4 class="mb-3">Choose Assignment Method</h4>
                            <div class="assignment-methods">
                                <div v-for="method in assignmentMethods" :key="method.value" class="assignment-method-item" @click="autoAssignStudents(method.value)">
                                    <i :class="method.icon" class="method-icon"></i>
                                    <span class="method-label">{{ method.label }}</span>
                                </div>
                            </div>
                            <div class="assignment-options-footer">
                                <Button label="Cancel" class="p-button-text p-button-sm" @click="showAssignmentOptions = false" />
                            </div>
                        </div>
                    </div>
                </div>

                <Button v-if="isEditMode" icon="pi pi-save" label="Save Template" class="p-button-outlined" @click="showTemplateSaveDialog = true" />
                <Button v-if="isEditMode" icon="pi pi-list" label="Load Template" class="p-button-outlined" @click="showTemplateManager = true" />

                <!-- Start Session - Only when NOT editing -->
                <Button v-if="!isEditMode" icon="pi pi-play" label="Start Session" class="p-button-success" @click="startAttendanceSession" />
                <Button v-if="!isEditMode" icon="pi pi-table" label="View Records" class="p-button-info" @click="viewAttendanceRecords" />
            </template>

            <!-- ACTIVE SESSION BUTTONS - Only show during session -->
            <template v-if="sessionActive">
                <Button icon="pi pi-check-circle" label="Mark All Present" class="p-button-success" @click="markAllPresent" :disabled="isCompletingSession" />
                <Button icon="pi pi-sync" label="Change Method" class="p-button-help" @click="changeAttendanceMethod" :disabled="isCompletingSession" />
                
                <!-- QR Scanner specific -->
                <Button v-if="attendanceMethod === 'qr' && !showQRScanner" icon="pi pi-qrcode" label="Reopen Scanner" class="p-button-info" @click="reopenQRScanner" :disabled="isCompletingSession" />
                
                <Button icon="pi pi-refresh" label="Reset" class="p-button-outlined" @click="resetAllAttendance" :disabled="isCompletingSession" />
                <Button
                    :icon="isCompletingSession ? 'pi pi-spin pi-spinner' : 'pi pi-stop'"
                    :label="isCompletingSession ? 'Completing...' : 'Complete Session'"
                    class="p-button-warning"
                    @click="completeAttendanceSession"
                    :disabled="isCompletingSession"
                />
                <Button icon="pi pi-times" label="Cancel Session" class="p-button-danger p-button-outlined" @click="cancelAttendanceSession" :disabled="isCompletingSession" />
            </template>
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
                                    <InputNumber id="rows" v-model="rows" :min="1" :max="9" @change="updateGridSize" class="w-20" :showButtons="false" />
                                    <Button icon="pi pi-plus" @click="incrementRows" :disabled="rows >= 9" class="p-button-secondary p-button-sm" />
                                </div>
                            </div>

                            <div class="flex items-center">
                                <label for="columns" class="mr-1 whitespace-nowrap">Columns:</label>
                                <div class="p-inputgroup">
                                    <Button icon="pi pi-minus" @click="decrementColumns" :disabled="columns <= 1" class="p-button-secondary p-button-sm" />
                                    <InputNumber id="columns" v-model="columns" :min="1" :max="9" @change="updateGridSize" class="w-20" :showButtons="false" />
                                    <Button icon="pi pi-plus" @click="incrementColumns" :disabled="columns >= 9" class="p-button-secondary p-button-sm" />
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

                <!-- Teacher's desk at the front of classroom -->
                <div v-if="showTeacherDesk" class="teacher-desk-front">
                    <div class="teacher-desk-label" :style="{ width: teacherDeskSize, height: teacherDeskSize }">
                        <div>
                            <i class="pi pi-user block mb-1"></i>
                            <span class="text-xs">Teacher's Desk</span>
                        </div>
                    </div>
                </div>

                <!-- Student seating grid -->
                <div class="seating-grid-container">
                    <div class="seating-grid">
                        <div v-for="(row, rowIndex) in seatPlan" :key="`row-${rowIndex}`" class="seat-row flex">
                            <div v-for="(seat, colIndex) in row" :key="`seat-${rowIndex}-${colIndex}`" class="seat-container p-1">
                                <div
                                    :class="[
                                        'seat p-3 border rounded-lg transition-all duration-200',
                                        { 'cursor-pointer': !isEditMode || seat.isOccupied },
                                        { 'drop-target': isDropTarget && isDropTarget(rowIndex, colIndex) },
                                        { 'student-present': seat.isOccupied && seat.status === 'Present' },
                                        { 'student-absent': seat.isOccupied && seat.status === 'Absent' },
                                        { 'student-late': seat.isOccupied && seat.status === 'Late' },
                                        { 'student-excused': seat.isOccupied && seat.status === 'Excused' },
                                        { 'student-occupied': seat.isOccupied },
                                        { removable: isEditMode && seat.isOccupied },
                                        { 'seat-hovered': hoveredSeat && hoveredSeat.row === rowIndex && hoveredSeat.col === colIndex }
                                    ]"
                                    :style="hoveredSeat && hoveredSeat.row === rowIndex && hoveredSeat.col === colIndex ? { transformOrigin: hoveredSeat.transformOrigin } : {}"
                                    @click="isEditMode ? (seat.isOccupied ? removeStudentFromSeat(rowIndex, colIndex) : null) : handleSeatClick(rowIndex, colIndex)"
                                    @mouseenter="handleSeatHover(rowIndex, colIndex, $event)"
                                    @mouseleave="handleSeatLeave"
                                    @dragover="allowDrop($event)"
                                    @drop="dropOnSeat(rowIndex, colIndex)"
                                >
                                    <!-- Show 2x2 quadrant grid when hovered -->
                                    <div v-if="hoveredSeat && hoveredSeat.row === rowIndex && hoveredSeat.col === colIndex && !isEditMode" class="quadrant-grid-container">
                                        <!-- Top-Left: Absent (Red) -->
                                        <div class="quadrant quadrant-top-left quadrant-absent" @click.stop="handleQuickAction('Absent')">
                                            <i class="pi pi-times"></i>
                                            <span>Absent</span>
                                        </div>

                                        <!-- Top-Right: Present (Green) -->
                                        <div class="quadrant quadrant-top-right quadrant-present" @click.stop="handleQuickAction('Present')">
                                            <i class="pi pi-check"></i>
                                            <span>Present</span>
                                        </div>

                                        <!-- Bottom-Left: Excused (Blue) -->
                                        <div class="quadrant quadrant-bottom-left quadrant-excused" @click.stop="handleQuickAction('Excused')">
                                            <i class="pi pi-info-circle"></i>
                                            <span>Excused</span>
                                        </div>

                                        <!-- Bottom-Right: Late (Orange/Yellow) -->
                                        <div class="quadrant quadrant-bottom-right quadrant-late" @click.stop="handleQuickAction('Late')">
                                            <i class="pi pi-clock"></i>
                                            <span>Late</span>
                                        </div>

                                        <!-- Student name overlay in center -->
                                        <div class="student-name-overlay">
                                            {{ getStudentById(seat.studentId)?.name }}
                                        </div>
                                    </div>

                                    <!-- Normal student info when not hovered -->
                                    <div v-else-if="seat.isOccupied && getStudentById(seat.studentId)" class="student-info relative">
                                        <div class="student-initials bg-blue-500 text-white">
                                            {{ getStudentInitials(getStudentById(seat.studentId)) }}
                                        </div>
                                        <!-- Status indicator badge -->
                                        <div
                                            v-if="seat.status"
                                            class="absolute -top-1 -right-1 w-5 h-5 rounded-full flex items-center justify-center"
                                            :class="{
                                                'bg-green-500': seat.status === 1 || seat.status === 'Present',
                                                'bg-red-500': seat.status === 2 || seat.status === 'Absent',
                                                'bg-yellow-500': seat.status === 3 || seat.status === 'Late',
                                                'bg-blue-400': seat.status === 4 || seat.status === 'Excused'
                                            }"
                                        >
                                            <i
                                                class="pi text-white text-xs"
                                                :class="{
                                                    'pi-check': seat.status === 1 || seat.status === 'Present',
                                                    'pi-times': seat.status === 2 || seat.status === 'Absent',
                                                    'pi-clock': seat.status === 3 || seat.status === 'Late',
                                                    'pi-info-circle': seat.status === 4 || seat.status === 'Excused'
                                                }"
                                            ></i>
                                        </div>
                                        <div class="student-name">{{ getStudentById(seat.studentId)?.name }}</div>
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
                </div>
            </div>
        </div>

        <!-- Floating Game-Style Unassigned Students Panel -->
        <div v-if="isEditMode" class="floating-students-panel" :style="{ left: panelPosition.x + 'px', top: panelPosition.y + 'px' }" @mousedown="startDragPanel">
            <div class="floating-panel-header" @mousedown="startDragPanel">
                <div class="panel-title">
                    <i class="pi pi-users"></i>
                    <span>Unassigned Students</span>
                </div>
                <div class="panel-controls">
                    <button class="panel-btn minimize-btn" @click="toggleMinimize">
                        <i :class="isMinimized ? 'pi pi-window-maximize' : 'pi pi-window-minimize'"></i>
                    </button>
                </div>
            </div>

            <div v-if="!isMinimized" class="floating-panel-content">
                <div class="search-container">
                    <span class="p-input-icon-left w-full">
                        <i class="pi pi-search" />
                        <InputText v-model="searchQuery" placeholder="Search students..." class="w-full" />
                    </span>
                </div>

                <div v-if="filteredUnassignedStudents.length === 0" class="empty-state">
                    <i class="pi pi-check-circle"></i>
                    <p v-if="unassignedStudents.length === 0">All students assigned!</p>
                    <p v-else>No matches found</p>
                </div>

                <div v-else class="floating-students-list">
                    <div v-for="student in sortedUnassignedStudents" :key="student.id" class="floating-student-card" draggable="true" @dragstart="dragStudent(student)">
                        <div class="student-avatar">
                            <div class="student-initials">
                                {{ getStudentInitials(student) }}
                            </div>
                        </div>
                        <div class="student-details">
                            <div class="student-name">{{ student.name }}</div>
                            <div v-if="showStudentIds" class="student-id">ID: {{ student.id }}</div>
                        </div>
                        <div class="drag-handle">
                            <i class="pi pi-arrows-alt"></i>
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
                    <Button icon="pi pi-users" label="Seat Plan" class="p-button-outlined" @click="selectSeatPlanMethod" />

                    <Button icon="pi pi-qrcode" label="QR Code Scanner" class="p-button-outlined" @click="selectQRMethod" />
                </div>
            </div>
        </Dialog>

        <!-- Enhanced QR Scanner Dialog with Gate Log -->
        <Dialog v-model:visible="showQRScanner" modal header="QR Code Attendance Scanner" :style="{ width: '95vw', height: '85vh' }" :closable="false">
            <div class="qr-scanner-layout" style="display: flex; gap: 1.5rem; height: calc(85vh - 120px); min-height: 500px">
                <!-- Camera Section (Left Side) -->
                <div style="flex: 1; min-width: 0; display: flex; flex-direction: column">
                    <div class="qr-camera-section" style="flex: 1; display: flex; flex-direction: column">
                        <h4 class="text-lg font-semibold mb-3">Camera Feed</h4>

                        <div v-if="cameraError" class="text-center p-4 border-2 border-dashed border-red-300 rounded-lg">
                            <i class="pi pi-exclamation-triangle text-4xl text-red-500 mb-3"></i>
                            <p class="text-red-600 mb-3">{{ cameraError }}</p>
                            <Button label="Try Again" icon="pi pi-refresh" @click="initializeCamera" />
                        </div>

                        <div v-else class="scanner-container border-2 border-blue-300 rounded-lg overflow-hidden" style="flex: 1; min-height: 300px">
                            <QrcodeStream v-if="scanning" @decode="onQRDecode" @detect="onQRDetect" @init="onCameraInit" @error="onCameraError" class="qr-scanner w-full" style="height: 100%" />
                            <div v-if="!scanning" class="scanner-paused bg-gray-100 flex flex-column align-items-center justify-content-center" style="height: 100%">
                                <i class="pi pi-pause-circle text-4xl mb-2"></i>
                                <p>Scanner Paused - Click Resume to start scanning</p>
                            </div>
                        </div>

                        <!-- Debug Info -->
                        <div class="mt-2 text-xs text-gray-500">
                            Scanner Status: {{ scanning ? 'ACTIVE' : 'PAUSED' }} | Camera: {{ cameraInitialized ? 'READY' : 'INITIALIZING' }}
                            <span v-if="cameraError" class="text-red-500"> | Error: {{ cameraError }}</span>
                        </div>

                        <div class="mt-3 flex gap-2">
                            <Button :label="!scanning ? 'Resume' : 'Pause'" :icon="!scanning ? 'pi pi-play' : 'pi pi-pause'" @click="toggleScanning" />
                            <Button label="Test QR" icon="pi pi-search" class="p-button-warning" @click="testQRDetection" />
                            <Button label="Complete Session" icon="pi pi-check" class="p-button-success" @click="completeQRSession" />
                            <Button label="Close Scanner" icon="pi pi-times" class="p-button-secondary" @click="stopQRScanner" />
                        </div>
                    </div>
                </div>

                <!-- Results & Log Section (Right Side) -->
                <div style="flex: 1; min-width: 0; border-left: 2px solid #e5e7eb; padding-left: 1.5rem; display: flex; flex-direction: column">
                    <div class="qr-results-section" style="flex: 1; display: flex; flex-direction: column">
                        <!-- Enhanced Scanned Students Panel -->
                        <div class="mb-4">
                            <div class="flex justify-content-between align-items-center mb-3">
                                <h4 class="text-lg font-semibold">📱 Scanned Students ({{ qrScanResults.length }})</h4>
                                <Badge v-if="qrScanResults.length > 0" :value="qrScanResults.length" severity="success" />
                            </div>

                            <div class="max-h-40 overflow-y-auto border rounded-lg bg-white">
                                <div v-if="qrScanResults.length === 0" class="p-4 text-center text-gray-500">
                                    <i class="pi pi-qrcode text-3xl mb-2 block"></i>
                                    <p>No students scanned yet</p>
                                    <small>Students will appear here when they scan their QR codes</small>
                                </div>

                                <div v-for="result in qrScanResults" :key="result.id" class="p-3 border-bottom-1 surface-border hover:bg-gray-50 transition-colors">
                                    <div class="flex justify-content-between align-items-start">
                                        <div class="flex-1">
                                            <div class="font-semibold text-primary">{{ result.name }}</div>
                                            <div class="text-sm text-gray-600 mt-1"><i class="pi pi-id-card mr-1"></i>ID: {{ result.studentId }}</div>
                                            <div class="text-xs text-gray-500 mt-1"><i class="pi pi-clock mr-1"></i>Scanned: {{ new Date(result.scannedAt).toLocaleTimeString() }}</div>
                                        </div>
                                        <div class="flex flex-column align-items-end">
                                            <Tag :value="result.status" :severity="getStatusSeverity(result.status)" class="mb-1" />
                                            <i class="pi pi-check-circle text-green-500 text-sm"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Stats -->
                            <div v-if="qrScanResults.length > 0" class="mt-2 p-2 bg-green-50 border border-green-200 rounded text-center">
                                <small class="text-green-700">
                                    <i class="pi pi-check mr-1"></i>
                                    {{ qrScanResults.length }} student{{ qrScanResults.length !== 1 ? 's' : '' }} marked present via QR scan
                                </small>
                            </div>
                        </div>

                        <!-- Gate Log -->
                        <div>
                            <h4 class="text-lg font-semibold mb-3">Scan Log ({{ qrScanLog.length }})</h4>
                            <div class="max-h-40 overflow-y-auto border rounded-lg bg-gray-50">
                                <div v-if="qrScanLog.length === 0" class="p-3 text-center text-gray-500">Scan log will appear here</div>
                                <div v-for="(log, index) in qrScanLog" :key="index" class="p-2 border-bottom-1 surface-border text-sm">
                                    <div class="flex justify-content-between">
                                        <span class="font-mono">{{ log.timestamp }}</span>
                                        <span :class="log.success ? 'text-green-600' : 'text-red-600'">
                                            {{ log.success ? 'SUCCESS' : 'FAILED' }}
                                        </span>
                                    </div>
                                    <div class="mt-1">{{ log.message }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>

        <!-- Status Selection Dialog -->
        <Dialog v-model:visible="showStatusDialog" modal header="Update Attendance Status" :style="{ width: '400px' }" :closable="true">
            <div v-if="selectedSeat && selectedSeat.student" class="p-4">
                <div class="mb-4">
                    <h4 class="text-lg font-semibold mb-2">{{ selectedSeat.student.name }}</h4>
                    <p class="text-gray-600">
                        Current Status:
                        <Tag v-if="selectedSeat.status" :value="selectedSeat.status" :severity="getStatusSeverity(selectedSeat.status)" />
                        <span v-else class="text-gray-400">Not marked</span>
                    </p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Select Status:</label>
                    <div class="flex flex-col gap-2">
                        <div class="flex align-items-center">
                            <RadioButton v-model="pendingStatus" inputId="present" value="Present" />
                            <label for="present" class="ml-2">Present</label>
                        </div>
                        <div class="flex align-items-center">
                            <RadioButton v-model="pendingStatus" inputId="absent" value="Absent" />
                            <label for="absent" class="ml-2">Absent</label>
                        </div>
                        <div class="flex align-items-center">
                            <RadioButton v-model="pendingStatus" inputId="late" value="Late" />
                            <label for="late" class="ml-2">Late</label>
                        </div>
                        <div class="flex align-items-center">
                            <RadioButton v-model="pendingStatus" inputId="excused" value="Excused" />
                            <label for="excused" class="ml-2">Excused</label>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="statusRemarks" class="block text-sm font-medium mb-2">Remarks (Optional):</label>
                    <Textarea id="statusRemarks" v-model="attendanceRemarks" rows="3" placeholder="Enter any additional notes..." class="w-full" />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-content-end gap-2">
                    <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showStatusDialog = false" />
                    <Button label="Save" icon="pi pi-check" class="p-button-success" @click="saveAttendanceStatus" :disabled="!pendingStatus" />
                </div>
            </template>
        </Dialog>

        <!-- Attendance Completion Modal -->
        <AttendanceCompletionModal
            :visible="showCompletionModal"
            :subject-name="completedSessionData?.subject_name || subjectName"
            :section-name="completedSessionData?.section_name || currentSectionName"
            :session-date="new Date().toLocaleDateString()"
            :session-data="completedSessionData"
            @close="handleModalClose"
            @view-details="handleViewDetails"
            @edit-attendance="handleEditAttendance"
            @start-new-session="handleStartNewSession"
            @dont-show-again="handleDontShowAgain"
        />

        <!-- Attendance Reason Dialog -->
        <AttendanceReasonDialog
            v-model="showReasonDialog"
            :status-type="reasonDialogType"
            :student-name="pendingAttendanceUpdate?.student?.name || pendingAttendanceUpdate?.student?.firstName + ' ' + pendingAttendanceUpdate?.student?.lastName"
            @confirm="onReasonConfirmed"
        />

        <!-- Attendance Edit Dialog -->
        <AttendanceEditDialog v-model="showEditDialog" :session-data="editSessionData" :subject-name="subjectName" :section-name="'Sampaguita'" @save="handleEditSave" @close="handleEditClose" />

        <!-- Seating Loading Overlay -->
        <div v-if="isLoadingSeating" class="seating-loading-overlay">
            <div class="loading-content">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                </div>
                <div class="loading-text">
                    <h3>{{ loadingMessage }}</h3>
                    <p>Please wait while we prepare your classroom...</p>
                </div>
            </div>
        </div>

        <!-- Session Completion Loading Overlay -->
        <div v-if="isCompletingSession" class="session-loading-overlay">
            <div class="loading-content">
                <div class="loading-header">
                    <h3>Generating Session Summary</h3>
                    <p>Please wait while we process your attendance data...</p>
                </div>

                <!-- Animated Character -->
                <div class="character-container">
                    <div class="character">🏃‍♂️</div>
                    <div class="obstacles">
                        <div class="obstacle obstacle-1">📚</div>
                        <div class="obstacle obstacle-2">✏️</div>
                        <div class="obstacle obstacle-3">📊</div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="progress-container">
                    <div class="progress-bar">
                        <div class="progress-fill" :style="{ width: sessionCompletionProgress + '%' }"></div>
                    </div>
                    <div class="progress-text">{{ sessionCompletionProgress }}%</div>
                </div>

                <div class="loading-message">
                    <span v-if="sessionCompletionProgress <= 10">Initializing session completion...</span>
                    <span v-else-if="sessionCompletionProgress <= 25">Connecting to server...</span>
                    <span v-else-if="sessionCompletionProgress <= 60">Saving attendance data...</span>
                    <span v-else-if="sessionCompletionProgress <= 80">Calculating statistics...</span>
                    <span v-else-if="sessionCompletionProgress <= 95">Generating session summary...</span>
                    <span v-else>Session completed! Opening summary...</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Disabled button cursor styling */
.p-button:disabled {
    cursor: not-allowed !important;
}

.p-button:disabled:hover {
    cursor: not-allowed !important;
}

/* Add these styles for the side-by-side layout */
.edit-layout {
    display: flex;
    gap: 1rem;
    width: 100%;
    min-width: fit-content;
    overflow-x: visible;
}

.main-content {
    flex: 3;
    min-width: fit-content; /* Allow content to determine minimum width */
    overflow-x: visible;
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

/* Auto Assignment Options */
.auto-assign-container {
    position: relative;
}

.assignment-options-overlay {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1000;
    margin-top: 0.5rem;
}

.assignment-options-panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    padding: 1rem;
    min-width: 280px;
}

.assignment-options-panel h4 {
    margin: 0 0 1rem 0;
    color: #374151;
    font-size: 0.9rem;
    font-weight: 600;
}

.assignment-methods {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.assignment-method-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.assignment-method-item:hover {
    background: #f8fafc;
    border-color: #3b82f6;
}

.method-icon {
    margin-right: 0.75rem;
    color: #6b7280;
    font-size: 1rem;
}

.method-label {
    color: #374151;
    font-size: 0.875rem;
    font-weight: 500;
}

.assignment-options-footer {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
}

/* Teacher's desk at front of classroom */
.teacher-desk-front {
    display: flex;
    justify-content: center;
    margin: 2rem 0;
    padding: 1rem;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 12px;
    border: 2px dashed #cbd5e1;
}

.teacher-desk {
    display: flex;
    justify-content: center;
    margin-bottom: 1.5rem;
}

.teacher-desk-label {
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    border: 2px solid #3b82f6;
    border-radius: 0.75rem;
    font-weight: 600;
    color: #1e40af;
    box-shadow: 0 2px 4px rgba(59, 130, 246, 0.1);
    transition: all 0.2s ease;
    padding: 0.75rem;
}

.teacher-desk-label:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(59, 130, 246, 0.15);
}

.teacher-desk-label i {
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
    padding-left: 25px;
}

.teacher-desk-label span {
    font-size: 0.75rem;
    line-height: 1;
}

/* Seat grid styles */
.seating-grid-container {
    width: 100%;
    overflow-x: visible;
    display: flex;
    justify-content: center;
    padding: 0.5rem;
    min-width: fit-content;
}

.seating-grid {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: center;
    max-width: fit-content;
}

.seat-row {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
}

.seat-container {
    flex: 0 0 auto; /* Don't grow or shrink, maintain fixed size */
    width: 90px; /* Fixed width for consistent sizing */
    height: 90px; /* Fixed height for consistent sizing */
    min-width: 90px;
    max-width: 90px;
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
    position: relative;
    overflow: visible;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    background-color: #f9f9f9;
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
    font-size: 14px;
}

.student-name {
    font-size: 10px;
    font-weight: 600;
    text-align: center;
    line-height: 1.2;
    color: #374151;
    max-width: 80px;
    word-wrap: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
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
    text-align: right;
    font-size: 0.875rem;
    color: #6b7280;
}

.date-time-display .date {
    font-weight: 600;
    color: #374151;
}

.date-time-display .time {
    color: #9ca3af;
}

.header-left {
    min-width: 0;
    flex: 1;
}

.header-info {
    margin-top: 0.5rem;
}

.calendar-section {
    background: #f8f9fa;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.session-status {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    border: 2px solid;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.session-status.active {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border-color: #10b981;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    animation: pulse-active 2s infinite;
}

.session-status.inactive {
    background: #f3f4f6;
    color: #6b7280;
    border-color: #d1d5db;
}

@keyframes pulse-active {
    0%,
    100% {
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
    }
    50% {
        box-shadow: 0 4px 20px rgba(16, 185, 129, 0.5);
    }
}

/* Seating Loading Overlay Styles */
.seating-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    backdrop-filter: blur(5px);
}

.seating-loading-overlay .loading-content {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    max-width: 400px;
    width: 90%;
}

.seating-loading-overlay .loading-spinner {
    margin-bottom: 1.5rem;
}

.seating-loading-overlay .spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.seating-loading-overlay .loading-text h3 {
    margin: 0 0 0.5rem 0;
    color: #2c3e50;
    font-size: 1.2rem;
}

.seating-loading-overlay .loading-text p {
    margin: 0;
    color: #7f8c8d;
    font-size: 0.9rem;
}

/* Session Completion Loading Overlay Styles */
.session-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(5px);
}

.loading-content {
    background: white;
    padding: 3rem 2rem;
    border-radius: 20px;
    text-align: center;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideInUp 0.5s ease-out;
}

.loading-header h3 {
    color: #1f2937;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
}

.loading-header p {
    color: #6b7280;
    margin: 0 0 2rem 0;
    font-size: 0.95rem;
}

.character-container {
    position: relative;
    height: 80px;
    margin: 2rem 0;
    overflow: hidden;
}

.character {
    font-size: 2.5rem;
    position: absolute;
    left: 0;
    bottom: 0;
    animation: runAndJump 3s infinite linear;
}

.obstacles {
    position: absolute;
    width: 100%;
    height: 100%;
}

.obstacle {
    position: absolute;
    font-size: 1.5rem;
    bottom: 0;
    animation: moveObstacles 3s infinite linear;
}

.obstacle-1 {
    right: 80%;
    animation-delay: 0s;
}

.obstacle-2 {
    right: 50%;
    animation-delay: -1s;
}

.obstacle-3 {
    right: 20%;
    animation-delay: -2s;
}

.progress-container {
    margin: 2rem 0 1rem 0;
}

.progress-bar {
    width: 100%;
    height: 12px;
    background: #e5e7eb;
    border-radius: 6px;
    overflow: hidden;
    position: relative;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669, #047857);
    border-radius: 6px;
    transition: width 0.3s ease;
    position: relative;
    overflow: hidden;
}

.progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    animation: shimmer 1.5s infinite;
}

.progress-text {
    margin-top: 0.5rem;
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
}

.loading-message {
    color: #6b7280;
    font-size: 0.9rem;
    font-weight: 500;
    margin-top: 1rem;
}

@keyframes slideInUp {
    from {
        transform: translateY(50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes runAndJump {
    0% {
        left: -10%;
        transform: translateY(0) rotate(0deg);
    }
    15% {
        transform: translateY(-20px) rotate(5deg);
    }
    25% {
        transform: translateY(0) rotate(0deg);
    }
    40% {
        transform: translateY(-25px) rotate(-5deg);
    }
    50% {
        transform: translateY(0) rotate(0deg);
    }
    65% {
        transform: translateY(-30px) rotate(10deg);
    }
    75% {
        transform: translateY(0) rotate(0deg);
    }
    100% {
        left: 110%;
        transform: translateY(0) rotate(0deg);
    }
}

@keyframes moveObstacles {
    0% {
        right: -10%;
    }
    100% {
        right: 110%;
    }
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

/* Hovered Seat - Enlarge slightly */
.seat-hovered {
    transform: scale(1.3) !important;
    z-index: 9999 !important;
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.35) !important;
    border-radius: 12px !important;
    position: relative !important;
    transition: transform 0.2s ease-out !important;
}

/* Quadrant Grid Container - 2x2 grid */
.quadrant-grid-container {
    position: absolute;
    inset: 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: 1fr 1fr;
    gap: 2px;
    border-radius: 12px;
    overflow: hidden;
    animation: fadeInScale 0.2s ease-out;
}

/* Individual Quadrant */
.quadrant {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
    font-size: 14px;
    font-weight: 700;
    color: white;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
    position: relative;
    padding: 6px 4px;
    overflow: hidden;
    min-height: 40px;
    gap: 2px;
}

.quadrant i {
    font-size: 16px;
    display: block;
    line-height: 1;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.3));
}

.quadrant span {
    font-size: 7px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 800;
    line-height: 1;
    display: block;
    word-break: keep-all;
    white-space: nowrap;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

.quadrant:hover {
    filter: brightness(1.15);
    transform: scale(1.05);
}

/* Top-Left: Absent (Red) */
.quadrant-absent {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    border-top-left-radius: 12px;
}

/* Top-Right: Present (Green) */
.quadrant-present {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    border-top-right-radius: 12px;
}

/* Bottom-Left: Excused (Blue) */
.quadrant-excused {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    border-bottom-left-radius: 12px;
}

/* Bottom-Right: Late (Orange/Yellow) */
.quadrant-late {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border-bottom-right-radius: 12px;
}

/* Student Name Overlay - Centered over quadrants */
.student-name-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
    z-index: 10;
    pointer-events: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    max-width: 80%;
    overflow: hidden;
    text-overflow: ellipsis;
}

.floating-panel-content::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 3px;
}

.floating-panel-content::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.3);
}

@keyframes floatIn {
    from {
        opacity: 0;
        transform: translateY(-20px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Floating Game-Style Panel */
.floating-students-panel {
    position: fixed;
    width: 320px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    box-shadow:
        0 20px 40px rgba(0, 0, 0, 0.3),
        0 0 0 1px rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    z-index: 1000;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    overflow: hidden;
    animation: floatIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.floating-students-panel:hover {
    box-shadow:
        0 25px 50px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.floating-panel-header {
    background: rgba(255, 255, 255, 0.1);
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: move;
    user-select: none;
}

.panel-title {
    display: flex;
    align-items: center;
    gap: 8px;
    color: white;
    font-weight: 600;
    font-size: 14px;
}

.panel-title i {
    font-size: 16px;
    opacity: 0.9;
}

.panel-controls {
    display: flex;
    gap: 4px;
}

.panel-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    border-radius: 6px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    color: white;
}

.panel-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
}

.floating-panel-content {
    padding: 16px;
    max-height: 400px;
    overflow-y: auto;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
}

.search-container {
    margin-bottom: 12px;
}

.search-container input {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    font-size: 13px;
}

.empty-state {
    text-align: center;
    padding: 24px 16px;
    color: #6b7280;
}

.empty-state i {
    font-size: 32px;
    margin-bottom: 8px;
    color: #10b981;
}

.empty-state p {
    margin: 0;
    font-size: 14px;
    font-weight: 500;
}

.floating-students-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.floating-student-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 12px;
    border: 1px solid rgba(0, 0, 0, 0.05);
    cursor: grab;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.floating-student-card:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
}

.floating-student-card:active {
    cursor: grabbing;
    transform: scale(0.98);
}

.student-avatar {
    flex-shrink: 0;
}

.student-avatar .student-initials {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 12px;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.student-details {
    flex: 1;
    min-width: 0;
}

.student-details .student-name {
    font-weight: 600;
    font-size: 13px;
    color: #1f2937;
    margin: 0;
    line-height: 1.3;
    word-wrap: break-word;
}

.student-details .student-id {
    font-size: 11px;
    color: #6b7280;
    margin-top: 2px;
}

.drag-handle {
    flex-shrink: 0;
    color: #9ca3af;
    font-size: 14px;
    opacity: 0.6;
    transition: opacity 0.2s ease;
}

.floating-student-card:hover .drag-handle {
    opacity: 1;
    color: #6b7280;
}
</style>
