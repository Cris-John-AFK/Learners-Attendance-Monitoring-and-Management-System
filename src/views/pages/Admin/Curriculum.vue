<script setup>
// Define API_URL directly instead of importing
// import { API_URL } from '@/config';
import api from '@/config/axios';
import { CurriculumService } from '@/router/service/CurriculumService';
import { GradesService } from '@/router/service/GradesService';
import { SubjectService } from '@/router/service/Subjects';
import { TeacherService } from '@/router/service/TeacherService';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
// import Dropdown from 'primevue/dropdown'; // Deprecated - using Select instead
import InputNumber from 'primevue/inputnumber';
import InputSwitch from 'primevue/inputswitch';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Select from 'primevue/select';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

const toast = useToast();
const confirmDialog = useConfirm();
const route = useRoute();

// Only one curriculum is supported
const curriculum = ref({
    id: null,
    name: 'Curriculum',
    yearRange: { start: null, end: null },
    description: '',
    status: 'Active',
    is_active: true,
    grade_levels: []
});
const loading = ref(true);
const curriculumDialog = ref(false);
const submitted = ref(false); // Form validation flag

// Define API_URL directly in the component
// const API_URL = 'http://localhost:8000/api';

// New curriculum form data removed; only one curriculum ref is used above
const years = ref(['2023', '2024', '2025', '2026', '2027', '2028', '2029', '2030']);
const availableStartYears = computed(() => {
    // Generate year options from current year - 5 to current year + 5
    const currentYear = new Date().getFullYear();
    const years = [];
    for (let i = currentYear - 5; i <= currentYear + 5; i++) {
        years.push(i.toString());
    }
    return years;
});

const availableEndYears = computed(() => {
    // Generate end years based on selected start year
    if (!curriculum.value.yearRange?.start) return [];

    const startYear = parseInt(curriculum.value.yearRange.start);
    const years = [];
    // Allow end year to be only start year + 1 (typical school year format)
    years.push((startYear + 1).toString());
    return years;
});

// Grade level management
const showGradeLevelManagement = ref(false);
const selectedCurriculumToArchive = ref(null);
const archiveDialog = ref(false);
const archiveConfirmDialog = ref(false);
const wasSubjectListOpen = ref(false);

const grades = ref([]);
const selectedGrade = ref(null);
const gradeDialog = ref(false);
const availableGrades = ref([]);
const selectedGradeToAdd = ref(null);
const newGradeDialog = ref(false);
const newGrade = ref({
    code: '',
    name: '',
    is_active: true,
    level: '0',
    display_order: 0,
    description: ''
});
const selectedGradeType = ref(null);
const gradeValue = ref(null);
const gradeTypes = [
    { label: 'Kinder', value: 'KINDER' },
    { label: 'Grade', value: 'GRADE' },
    { label: 'ALS', value: 'ALS' }
];
const gradeSubmitted = ref(false);
const grade = ref({
    id: '',
    name: '',
    code: '',
    display_order: 0
});

// Add function to open grade dialog
const openAddGradeDialog = () => {
    // Reset form
    newGrade.value = {
        code: '',
        name: '',
        level: '0',
        display_order: 0,
        description: ''
    };
    selectedGradeType.value = null;
    gradeValue.value = 1; // Set default value to 1
    gradeSubmitted.value = false;
    newGradeDialog.value = true;
};

// Function to open grade dialog (alias for template usage)
const openGradeDialog = () => {
    openAddGradeDialog();
};

// Section management
const sectionDialog = ref(false);
const showSectionListDialog = ref(false);
const sections = ref([]);
const selectedSection = ref(null);
const section = ref({
    id: null,
    name: '',
    grade_id: '',
    capacity: 25,
    is_active: true,
    teacher_id: null
});

// Add this new variable for section teachers
const sectionTeacher = ref(null);

// Subject management
const subjects = ref([]);
const subjectDialog = ref(false);
const subjectAssignmentDialog = ref(false);
const showSubjectListDialog = ref(false);
const selectedSubject = ref(null);
const selectedSubjects = ref([]);
const subjectsForSection = ref([]);
const subject = ref({
    id: null,
    name: '',
    description: '',
    grade_id: ''
});

// Teacher management
const teachers = ref([]);
const teacherDialog = ref(false);
const selectedTeacher = ref(null);
const homeRoomTeacherAssignmentDialog = ref(false);
const subjectTeacherAssignmentDialog = ref(false);
const teacherSubmitted = ref(false); // Flag for teacher selection validation

// Search variables
const searchYear = ref('');
const searchQuery = ref('');

// Selected curriculum variable needed for various operations
const selectedCurriculum = ref(null);

// Schedule management
const scheduleDialog = ref(false);
const days = ref(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']);
const timeSlots = ref(['7:00 AM', '7:30 AM', '8:00 AM', '8:30 AM', '9:00 AM', '9:30 AM', '10:00 AM', '10:30 AM', '11:00 AM', '11:30 AM', '12:00 PM', '12:30 PM', '1:00 PM', '1:30 PM', '2:00 PM', '2:30 PM', '3:00 PM', '3:30 PM', '4:00 PM', '4:30 PM']);
const schedules = ref([]);

// Add at the top of the script where other refs are defined
const showTeacherAssignmentDialog = ref(false);
const selectedSubjectForTeacher = ref(null);
const availableTeachers = ref([]);

// Only keep this single instance of schedule definition
const showScheduleDialog = ref(false);
const selectedSubjectForSchedule = ref(null);
const schedule = ref({
    day: 'Monday',
    start_time: '08:00',
    end_time: '09:00',
    teacher_id: null
});

// Days of the week options
const dayOptions = [
    { label: 'Monday', value: 'Monday' },
    { label: 'Tuesday', value: 'Tuesday' },
    { label: 'Wednesday', value: 'Wednesday' },
    { label: 'Thursday', value: 'Thursday' },
    { label: 'Friday', value: 'Friday' },
    { label: 'Saturday', value: 'Saturday' }
];

// Calculate if a grade requires subject teachers (Grade 3-6)
const requiresSubjectTeachers = (gradeCode) => {
    return ['G3', 'G4', 'G5', 'G6'].includes(gradeCode);
};

// Ensure curriculum has a valid yearRange property and status
const normalizeYearRange = (curriculum) => {
    if (!curriculum) return curriculum;

    // Create a copy to avoid mutating the original
    const curriculumCopy = { ...curriculum };

    // Initialize yearRange if not present
    if (!curriculumCopy.yearRange) {
        curriculumCopy.yearRange = {
            start: curriculumCopy.start_year || null,
            end: curriculumCopy.end_year || null
        };
    }

    // The actual valid status values for our application
    const validStatusValues = ['Active', 'Draft', 'Archived'];

    // Make sure status is properly set
    if (!curriculumCopy.status && (curriculumCopy.is_active === true || curriculumCopy.is_active === 1)) {
        // If no status but is active, set to Active
        // Setting status to Active based on is_active flag
        curriculumCopy.status = 'Active';
    } else if (!curriculumCopy.status) {
        // Default to Draft if no status is provided and not active
        curriculumCopy.status = 'Draft';
    } else if (!validStatusValues.includes(curriculumCopy.status)) {
        // If status is invalid, set to a valid default based on is_active
        console.warn(`Invalid status value "${curriculumCopy.status}" detected. Valid values are: ${validStatusValues.join(', ')}. Defaulting based on is_active.`);
        curriculumCopy.status = curriculumCopy.is_active ? 'Active' : 'Draft';
    }

    return curriculumCopy;
};

// Filter available years for search dropdown
const availableYears = computed(() => {
    const years = new Set();
    // Only one curriculum is supported
    const curr = curriculum.value;
    if (curr && curr.yearRange) {
        if (curr.yearRange.start) years.add(curr.yearRange.start);
        if (curr.yearRange.end) years.add(curr.yearRange.end);
    }
    return Array.from(years).sort();
});

// Add right after the availableYears computed property

// Remove computed properties for curriculum filtering, searching, activating, archiving, etc.

// Single curriculum implementation - curriculums array only has the one curriculum
const curriculums = ref([curriculum.value]);

// Load the curriculum function that gets the single curriculum
const loadCurriculums = async () => {
    loading.value = true;
    try {
        // Loading the single curriculum
        const response = await api.get('/api/curriculums');
        if (response.data) {
            // Update our single curriculum with data from backend
            curriculum.value = normalizeYearRange(response.data);
            // Also update the curriculums array to maintain compatibility
            curriculums.value = [curriculum.value];
            // Curriculum loaded successfully
        }
    } catch (error) {
        // Error loading curriculum
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load curriculum data: ' + (error.message || 'Unknown error'),
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Simple pass-through for filtered curriculum - always returns the single curriculum
const filteredCurriculums = computed(() => {
    return curriculums.value;
});

// Additional computed property for active curriculums only (if needed later)
// Remove activeCurriculums computed property; only one curriculum is always active.

// Add the getRandomGradient function from Admin-Subject.vue to generate gradients
const getRandomGradient = () => {
    const colors = ['#ff9a9e', '#fad0c4', '#fbc2eb', '#a6c1ee', '#ffdde1', '#ee9ca7', '#ff758c', '#ff7eb3', '#c3cfe2', '#d4fc79', '#96e6a1', '#84fab0', '#8fd3f4', '#a18cd1'];
    const color1 = colors[Math.floor(Math.random() * colors.length)];
    const color2 = colors[Math.floor(Math.random() * colors.length)];
    return `linear-gradient(135deg, ${color1}, ${color2})`;
};

// Modify the cardStyles computed property to match Admin-Subject.vue
const cardStyles = computed(() => {
    return Object.fromEntries(filteredCurriculums.value.map((curr) => [curr.id, { background: getRandomGradient() }]));
});

// Add this computed property after the cardStyles computed property
const gradeCardStyles = computed(() => {
    return Object.fromEntries(grades.value.map((grade) => [grade.id, { background: getRandomGradient() }]));
});

const sectionCardStyles = computed(() => {
    return Object.fromEntries(sections.value.map((section) => [section.id, { background: getRandomGradient() }]));
});

const search = ref('');

// Track if we're already handling a schedule dialog close
const isScheduleClosing = ref(false);

// Function to handle schedule dialog closing
const handleScheduleDialogClose = () => {
    if (isScheduleClosing.value) return;
    isScheduleClosing.value = true;
    scheduleDialog.value = false;
    // Reset any schedule-related state here
    setTimeout(() => {
        isScheduleClosing.value = false;
    }, 300);
};

// Function to handle subject dialog closing
const handleCloseSubjectDialog = () => {
    subjectDialog.value = false;
    wasSubjectListOpen.value = false;
    selectedSubject.value = null;
};

// Function to open grade level management view
const openGradeLevelManagement = (curr) => {
    selectedCurriculum.value = curr;
    showGradeLevelManagement.value = true;
    // Load grades for this curriculum if they aren't already loaded
    if (curr && curr.id) {
        loadGradesForCurriculum(curr.id);
    }
};

// Close subject list dialog
const closeSubjectListDialog = () => {
    showSubjectListDialog.value = false;
    wasSubjectListOpen.value = false;
};

// Reset search filter
const clearSearch = () => {
    searchYear.value = '';
    searchQuery.value = '';
};

const handleStartYearChange = () => {
    // Reset end year if it's less than or equal to start year
    if (curriculum.value.yearRange.end && parseInt(curriculum.value.yearRange.end) <= parseInt(curriculum.value.yearRange.start)) {
        curriculum.value.yearRange.end = '';
    }
};

// Add this function to check if direct endpoints are available
const checkDirectEndpoints = async () => {
    // Since the direct endpoints don't exist on the server (404 error),
    // we'll skip the API call and just return false
    // Direct database endpoints are not available on this server
    return false;
};

// Function to load grades for a specific curriculum
const loadGradesForCurriculum = async (curriculumId) => {
    if (!curriculumId) {
        console.error('No curriculum ID provided to load grades');
        return;
    }

    try {
        // Loading grades for curriculum
        loading.value = true;

        // Get the curriculum data first
        const currResponse = await api.get(`/api/curriculums/${curriculumId}`);

        if (currResponse?.data?.grade_levels && Array.isArray(currResponse.data.grade_levels)) {
            // Use the grades from the curriculum data
            grades.value = currResponse.data.grade_levels;
            // Grades loaded successfully
        } else {
            // Fallback to general grades
            const gradeResponse = await GradesService.getGrades();
            if (gradeResponse && Array.isArray(gradeResponse)) {
                grades.value = gradeResponse;
                // General grades loaded as fallback
            } else {
                // No grades found or invalid response format
                grades.value = [];
            }
        }
    } catch (error) {
        console.error('Error loading grades for curriculum:', error);
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to load grades', life: 3000 });
        grades.value = [];
    } finally {
        loading.value = false;
    }
};

// Add a cleanup function for the local storage when we know the backend is working again
const clearLocalData = (sectionId = null) => {
    try {
        if (sectionId) {
            // Clear specific section data
            const key = `section_subjects_${sectionId}`;
            localStorage.removeItem(key);
            // Cleared local data for section
        } else {
            // Clear all section data by finding all keys with the pattern
            const keys = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.startsWith('section_subjects_')) {
                    keys.push(key);
                }
            }

            // Remove all found keys
            keys.forEach((key) => localStorage.removeItem(key));
            // Cleared all local section data
        }
    } catch (error) {
        console.warn('Error clearing local data:', error);
    }
};

// Grade operations - moved outside onMounted to be accessible to template
const saveGrade = async () => {
    submitted.value = true;

    // Check if a grade is selected - this is the only required field for this form
    if (!selectedGradeToAdd.value) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Please select a grade level',
            life: 3000
        });
        return;
    }

    try {
        loading.value = true;

        // Find the selected grade object
        const selectedGrade = availableGrades.value.find((g) => g.id === selectedGradeToAdd.value);
        if (!selectedGrade) {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Selected grade not found',
                life: 3000
            });
            return;
        }

        // Prepare data for API
        const gradeData = {
            grade_id: selectedGrade.id,
            curriculum_id: curriculum.value.id
        };

        // Adding grade to curriculum
        await CurriculumService.addGradeToCurriculum(curriculum.value.id, gradeData);

        // Reload curriculum data after successful addition
        await loadCurriculums();

        // Clear the selected grade
        selectedGradeToAdd.value = null;

        gradeDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Grade level added successfully',
            life: 3000
        });
    } catch (error) {
        console.error('Error adding grade:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to add grade: ' + (error.message || 'Unknown error'),
            life: 3000
        });
    } finally {
        loading.value = false;
        submitted.value = false;
    }
};

// Move initialization to nextTick to prevent async setup issues
onMounted(() => {
    // Use nextTick to defer async work until after component setup
    nextTick(async () => {
        try {
            // Component mounted, loading data
            loading.value = true;

            // Clear any existing toasts that might be showing the wrong message
            if (toast && toast.removeAllGroups) {
                toast.removeAllGroups();
            }

            // Load initial data - single curriculum
            await loadCurriculums();
            // Curriculum loaded
            await loadGrades();
            // Grades loaded
            await loadAllGrades(); // Load all available grades for dropdown
            // Available grades loaded
            await loadSubjects();
            // Subjects loaded
            await loadTeachers();
            console.log('Teachers loaded:', teachers.value);

            // If we have curriculum data and a selected grade, load their sections
            if (curriculum.value?.id && selectedGrade.value?.id) {
                // Loading sections for curriculum and grade
                try {
                    const loadedSections = await CurriculumService.getSectionsByGrade(curriculum.value.id, selectedGrade.value.id);
                    if (Array.isArray(loadedSections)) {
                        sections.value = loadedSections;
                        // Sections loaded
                        // For each section, load its subjects and homeroom teacher
                        await Promise.all(
                            sections.value.map(async (section) => {
                                try {
                                    const sectionSubjects = await CurriculumService.getSubjectsBySection(curriculum.value.id, selectedGrade.value.id, section.id);
                                    if (Array.isArray(sectionSubjects)) {
                                        section.subjects = sectionSubjects;
                                    }
                                    if (section.homeroom_teacher_id) {
                                        const teacher = teachers.value.find((t) => t.id === section.homeroom_teacher_id);
                                        if (teacher) {
                                            section.teacher = teacher;
                                        }
                                    }
                                } catch (sectionError) {
                                    console.error('Error loading data for section:', section.id, sectionError);
                                }
                            })
                        );
                    }
                } catch (sectionsError) {
                    console.error('Error loading sections:', sectionsError);
                    sections.value = [];
                }
            }

            // Initial data loading complete
        } catch (error) {
            console.error('Error during initial data loading:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load initial data. Please refresh the page.',
                life: 5000
            });
        } finally {
            loading.value = false;
        }
    });

    // Create safe computed properties for the watched values to prevent unhandled errors
    // Use the single curriculum and selected grade for watching changes
    const watchedValues = computed(() => {
        return {
            curriculum: curriculum.value || {},
            grade: selectedGrade.value || {}
        };
    });

    // Add watch for curriculum and grade changes to reload sections - use the safe computed
    watch(
        watchedValues,
        async (newVal, oldVal) => {
            // Destructure with defaults to avoid null/undefined errors
            const { curriculum: newCurriculum = {}, grade: newGrade = {} } = newVal || {};
            const { curriculum: oldCurriculum = {}, grade: oldGrade = {} } = oldVal || {};

            // Safely check IDs using optional chaining
            if (!newCurriculum?.id || !newGrade?.id) {
                sections.value = [];
                return;
            }

            // Check if selection hasn't changed
            if (newCurriculum?.id === oldCurriculum?.id && newGrade?.id === oldGrade?.id) {
                return; // No change in selection
            }

            try {
                loading.value = true;
                // Selection changed, reloading sections

                const loadedSections = await CurriculumService.getSectionsByGrade(newCurriculum.id, newGrade.id);

                if (Array.isArray(loadedSections)) {
                    sections.value = loadedSections;
                    // Sections reloaded

                    if (sections.value.length > 0) {
                        // Load subjects and homeroom teachers for each section
                        await Promise.all(
                            sections.value.map(async (section) => {
                                if (!section || !section.id) return; // Skip invalid sections

                                try {
                                    // Load subjects
                                    const sectionSubjects = await CurriculumService.getSubjectsBySection(newCurriculum.id, newGrade.id, section.id);

                                    if (Array.isArray(sectionSubjects)) {
                                        section.subjects = sectionSubjects;
                                    }

                                    // Load homeroom teacher if assigned
                                    if (section.homeroom_teacher_id && teachers.value) {
                                        const teacher = teachers.value.find((t) => t && t.id === section.homeroom_teacher_id);
                                        if (teacher) {
                                            section.teacher = teacher;
                                        }
                                    }
                                } catch (error) {
                                    console.error('Error loading section data:', error);
                                }
                            })
                        );
                    }
                }
            } catch (error) {
                console.error('Error reloading sections:', error);
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to load sections',
                    life: 3000
                });
                sections.value = [];
            } finally {
                loading.value = false;
            }
        },
        { deep: true } // Add deep watching to detect nested property changes
    );

    // Main data loading function for the single curriculum
    const loadCurriculum = async () => {
        loading.value = true;
        try {
            // Loading curriculum
            const response = await CurriculumService.getCurriculum();
            if (response && typeof response === 'object') {
                curriculum.value = normalizeYearRange(response);
                // Curriculum loaded
            } else {
                console.warn('Invalid curriculum data format:', response);
                curriculum.value = {
                    id: null,
                    name: 'Curriculum',
                    yearRange: { start: null, end: null },
                    description: '',
                    status: 'Active',
                    is_active: true,
                    grade_levels: []
                };
            }
        } catch (error) {
            // Error loading curriculum
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load curriculum from database',
                life: 3000
            });
            curriculum.value = {
                id: null,
                name: 'Curriculum',
                yearRange: { start: null, end: null },
                description: '',
                status: 'Active',
                is_active: true,
                grade_levels: []
            };
        } finally {
            loading.value = false;
        }
    };

    async function loadGrades() {
        let retryCount = 0;
        const maxRetries = 3;

        while (retryCount < maxRetries) {
            try {
                const data = await GradesService.getGrades();
                if (Array.isArray(data) && data.length > 0) {
                    grades.value = data;
                    return;
                }

                // If empty response, try again
                retryCount++;
                if (retryCount < maxRetries) {
                    await new Promise((resolve) => setTimeout(resolve, 1000 * retryCount));
                }
            } catch (error) {
                retryCount++;
                console.error(`Error loading grades (attempt ${retryCount}):`, error);

                if (retryCount >= maxRetries) {
                    toast.add({
                        severity: 'error',
                        summary: 'Database Error',
                        detail: 'Failed to load grades after multiple attempts',
                        life: 5000
                    });
                    // Only fall back to default grades if absolutely necessary
                    const defaultGrades = await GradesService.getDefaultGrades();
                    if (defaultGrades && defaultGrades.length > 0) {
                        grades.value = defaultGrades;
                    }
                } else {
                    await new Promise((resolve) => setTimeout(resolve, 1000 * retryCount));
                }
            }
        }
    }

    // Function to load grade levels for the selected curriculum
    const loadGradeLevels = async () => {
        if (!curriculum.value?.id) {
            console.error('Cannot load grades: no curriculum available');
            return;
        }

        // Loading grade levels for curriculum
        loading.value = true;

        try {
            // Only get curriculum-specific grades - no fallbacks
            const data = await CurriculumService.getGradesByCurriculum(curriculum.value.id);
            // Grade levels loaded from API

            // Ensure we have a valid array
            grades.value = Array.isArray(data) ? data : [];

            // If no grades are linked to this curriculum, show empty list (no fallbacks)
            if (grades.value.length === 0) {
                // No grades are linked to this curriculum
            }

            // Final grade levels loaded
            showGradeLevelManagement.value = true;
        } catch (error) {
            console.error('Error loading grade levels:', error);
            grades.value = []; // Empty grades if error
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load grade levels for this curriculum.',
                life: 5000
            });
            showGradeLevelManagement.value = true;
        } finally {
            loading.value = false;
        }
    };

    async function loadSubjects() {
        try {
            const data = await SubjectService.getSubjects();
            subjects.value = data;
        } catch (error) {
            console.error('Error loading subjects:', error);
            toast.add({
                severity: 'error',
                summary: 'Database Error',
                detail: 'Failed to load subjects from database',
                life: 5000
            });
        }
    }

    async function loadTeachers() {
        try {
            // Loading teachers from API
            const data = await TeacherService.getTeachers();

            if (!data) {
                throw new Error('No data returned from teachers API');
            }

            teachers.value = Array.isArray(data) ? data : [];

            if (teachers.value.length === 0) {
                console.warn('No teachers returned from API');
                toast.add({
                    severity: 'warn',
                    summary: 'Warning',
                    detail: 'No teachers found in the database. You may need to add teachers first.',
                    life: 5000
                });
            } else {
                console.log(`Successfully loaded ${teachers.value.length} teachers:`, teachers.value);
            }
        } catch (error) {
            console.error('Error loading teachers:', error);
            toast.add({
                severity: 'error',
                summary: 'Database Error',
                detail: 'Failed to load teachers from database: ' + (error.message || 'Unknown error'),
                life: 5000
            });
            teachers.value = [];
        }
    }

    // Add this function after loadGrades
    const loadAllGrades = async () => {
        try {
            console.log('Loading all available grades...');
            const response = await GradesService.getGrades();
            if (Array.isArray(response)) {
                availableGrades.value = response;
                console.log('Loaded available grades:', availableGrades.value);
            } else {
                console.warn('Invalid response format for grades:', response);
                availableGrades.value = [];
            }
        } catch (error) {
            console.error('Error loading all grades:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load available grades',
                life: 3000
            });
            availableGrades.value = [];
        }
    };

    // Curriculum CRUD operations
    const openNew = () => {
        // Get current year
        const currentYear = new Date().getFullYear();
        const nextYear = currentYear + 1;

        curriculum.value = {
            id: null,
            name: `Curriculum ${currentYear}-${nextYear}`, // Default name with valid years
            yearRange: {
                start: currentYear.toString(),
                end: nextYear.toString()
            },
            description: '',
            status: 'Draft',
            is_active: false,
            grade_levels: []
        };
        submitted.value = false;
        curriculumDialog.value = true;

        // Show info message about naming convention
        toast.add({
            severity: 'info',
            summary: 'Info',
            detail: 'Curriculum name will be automatically generated based on the selected years',
            life: 5000
        });
    };

    const editCurriculum = (curr) => {
        curriculum.value = normalizeYearRange({ ...curr });
        curriculumDialog.value = true;
    };

    const saveCurriculum = async () => {
        try {
            loading.value = true;
            submitted.value = true; // Set to true to trigger validation

            // Basic validation
            if (!curriculum.value.yearRange?.start || !curriculum.value.yearRange?.end) {
                toast.add({ severity: 'error', summary: 'Error', detail: 'Please select both start and end years', life: 3000 });
                loading.value = false;
                return;
            }

            // Ensure year range values are strings
            curriculum.value.yearRange.start = String(curriculum.value.yearRange.start);
            curriculum.value.yearRange.end = String(curriculum.value.yearRange.end);

            // Automatically generate the name based on year range
            curriculum.value.name = `Curriculum ${curriculum.value.yearRange.start}-${curriculum.value.yearRange.end}`;

            console.log(`Saving curriculum ${curriculum.value.id ? 'update' : 'new'}: ${curriculum.value.name}`);
            console.log('Year range:', curriculum.value.yearRange);

            // Save to backend
            let result;
            if (curriculum.value.id) {
                // Update existing
                const curriculumUpdate = { ...curriculum.value };
                delete curriculumUpdate.status; // Don't send status on update
                result = await CurriculumService.updateCurriculum(curriculumUpdate);
            } else {
                // Create new
                result = await CurriculumService.createCurriculum(curriculum.value);
            }

            // On success
            curriculumDialog.value = false;
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: curriculum.value.id ? 'Curriculum Updated' : 'Curriculum Created',
                life: 3000
            });

            // Refresh data
            await loadCurriculums();
        } catch (error) {
            console.error('Error saving curriculum:', error);

            // User-friendly error message
            let message = 'An error occurred while saving the curriculum.';

            if (error.response) {
                // Check for duplicate year range error specifically
                if (error.response.status === 422) {
                    if (error.response.data?.message?.includes('year range already exists')) {
                        const yearRange = curriculum.value.yearRange;
                        message = `A curriculum for years ${yearRange.start}-${yearRange.end} already exists. Year ranges must be unique.`;
                    } else if (error.response.data?.errors) {
                        // Format validation errors
                        const errorDetails = Object.entries(error.response.data.errors)
                            .map(([field, msgs]) => `${field}: ${msgs.join(', ')}`)
                            .join('; ');
                        message = `Validation error: ${errorDetails}`;
                    } else {
                        message = error.response.data?.message || 'Validation failed. Please check your input.';
                    }
                } else if (error.response.status === 500) {
                    message = 'Database error occurred. Please try again later.';
                } else {
                    message = error.response.data?.message || error.message || 'Unknown error';
                }
            } else if (error.message) {
                // Handle direct error messages (like those thrown from the service)
                message = error.message;
            }

            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: message,
                life: 5000
            });
        } finally {
            loading.value = false;
        }
    };

    // Archive/Restore curriculum
    const openArchiveDialog = () => {
        archiveDialog.value = true;
    };

    const openArchiveConfirmation = (curr) => {
        selectedCurriculumToArchive.value = normalizeYearRange(curr);
        archiveConfirmDialog.value = true;
    };

    const handleArchiveConfirm = async () => {
        if (selectedCurriculumToArchive.value) {
            try {
                await CurriculumService.archiveCurriculum(selectedCurriculumToArchive.value.id);
                await loadCurriculums();

                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Curriculum archived successfully',
                    life: 3000
                });
            } catch (error) {
                console.error('Error archiving curriculum:', error);
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to archive curriculum: ' + (error.message || 'Unknown error'),
                    life: 3000
                });
            }
        }
        archiveConfirmDialog.value = false;
        selectedCurriculumToArchive.value = null;
    };

    // Function to restore curriculum if needed in the future
    const restoreCurriculum = async (curr) => {
        try {
            await CurriculumService.restoreCurriculum(curr.id);
            await loadCurriculums();

            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Curriculum restored successfully',
                life: 3000
            });
        } catch (error) {
            console.error('Error restoring curriculum:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to restore curriculum: ' + (error.message || 'Unknown error'),
                life: 3000
            });
        }
    };

    // Remove toggleActiveCurriculum; not needed for single curriculum.
    const toggleCurriculumStatus = async (curr) => {
        try {
            // Store the previous state
            const wasActive = curr.is_active;

            // If we're trying to activate the curriculum
            if (!wasActive) {
                // Immediately update UI for better responsiveness
                loading.value = true;

                // Call the API to activate
                const updatedCurriculum = await CurriculumService.activateCurriculum(curr.id);
                Object.assign(curr, updatedCurriculum);

                // Update local state to show active status without full reload
                curriculums.value = curriculums.value.map((c) => ({
                    ...c,
                    is_active: c.id === curr.id
                }));
                loading.value = false;
                curriculums.value.forEach((c) => {
                    // Deactivate all other curriculums
                    if (c.id !== curr.id) {
                        c.is_active = false;
                        c.status = 'Draft';
                    }
                });

                // Set this curriculum as active
                curr.is_active = true;
                curr.status = 'Active';

                // Only show success message after API succeeds
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Curriculum activated successfully',
                    life: 3000
                });

                // Delay the reload to allow animation to complete
                await loadCurriculums();
                loading.value = false;

                return;
            }
            // Else if we're deactivating
            else {
                // Check if this is the only active curriculum
                const isOnlyActiveCurriculum = !curriculums.value.some((c) => c.id !== curr.id && (c.is_active === true || c.status === 'Active'));

                if (!isOnlyActiveCurriculum) {
                    // If there's another active curriculum, allow deactivation
                    await CurriculumService.updateCurriculum({
                        ...curr,
                        is_active: false,
                        status: 'Draft'
                    });

                    // Update local state without full reload
                    curr.is_active = false;
                    curr.status = 'Draft';

                    toast.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: 'Curriculum deactivated successfully',
                        life: 3000
                    });

                    // Delay the reload for consistency
                    await loadCurriculums();
                    loading.value = false;

                    return; // Exit early
                } else {
                    // Cannot have no active curriculum
                    toast.add({
                        severity: 'warn',
                        summary: 'Warning',
                        detail: 'There must be an active curriculum. Please activate another curriculum first.',
                        life: 5000
                    });
                    loading.value = false;
                }
            }
        } catch (error) {
            console.error('Error toggling curriculum status:', error);

            // Show error message
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to update curriculum status: ' + (error.message || 'Unknown error'),
                life: 3000
            });

            // Reload to reset UI state
            await loadCurriculums();
            loading.value = false;
        }
    }; // <-- End of toggleCurriculumStatus function

    // Grade level operations
    const openGradeLevelManagement = async (curr) => {
        try {
            console.log('Opening grade level management for curriculum:', curr);
            // Create a clean copy of the curriculum to avoid reactivity issues
            selectedCurriculum.value = JSON.parse(JSON.stringify(normalizeYearRange(curr)));

            // Set the dialog to visible
            showGradeLevelManagement.value = true;

            // Make sure grade levels are loaded
            await loadGradeLevels();
        } catch (error) {
            console.error('Error in openGradeLevelManagement:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load grade level management',
                life: 3000
            });
        }
    };

    // Update the openAddGradeDialog function
    const openAddGradeDialog = async () => {
        try {
            selectedGradeToAdd.value = null;
            submitted.value = false;
            loading.value = true;

            // Load all available grades first
            await loadAllGrades();

            // Get current curriculum grades directly from API
            const curriculumGrades = await CurriculumService.getGradesByCurriculum(curriculum.value.id);

            // Filter out already-added grades
            if (curriculumGrades && curriculumGrades.length > 0) {
                const existingGradeIds = curriculumGrades.map((g) => g.id);
                availableGrades.value = availableGrades.value.filter((g) => !existingGradeIds.includes(g.id));

                if (availableGrades.value.length === 0) {
                    toast.add({
                        severity: 'info',
                        summary: 'Information',
                        detail: 'All available grades have already been added to this curriculum.',
                        life: 3000
                    });
                    return;
                }
            }

            // Show the dialog
            gradeDialog.value = true;
        } catch (error) {
            console.error('Error preparing grade level selection:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load available grades',
                life: 3000
            });
        } finally {
            loading.value = false;
        }
    };

    const removeGrade = async (gradeId) => {
        try {
            loading.value = true;
            console.log('Removing grade from curriculum:', curriculum.value.id, gradeId);

            await CurriculumService.removeGradeFromCurriculum(curriculum.value.id, gradeId);

            // Reload grades after successful removal
            await loadGradeLevels();

            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Grade level removed successfully',
                life: 3000
            });
        } catch (error) {
            console.error('Error removing grade:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to remove grade: ' + (error.message || 'Unknown error'),
                life: 3000
            });
        } finally {
            loading.value = false;
        }
    };

    // Section operations
    const openSectionList = async (grade) => {
        try {
            selectedGrade.value = grade;
            console.log('Opening section list for grade:', grade.id, 'in curriculum:', selectedCurriculum.value.id);

            // Store IDs in component state for nested dialogs
            // This will ensure we can access these IDs even when route params aren't available
            if (selectedCurriculum.value && selectedCurriculum.value.id) {
                localStorage.setItem('currentCurriculumId', selectedCurriculum.value.id);
            }
            if (grade && grade.id) {
                localStorage.setItem('currentGradeId', grade.id);
            }

            console.log('Stored curriculum and grade IDs in localStorage for nested dialogs');

            // Show dialog immediately
            showSectionListDialog.value = true;

            // Set loading state
            loading.value = true;

            // Clear existing sections
            sections.value = [];

            // Get sections with improved error handling
            const sectionsForGrade = await CurriculumService.getSectionsByGrade(selectedCurriculum.value.id, grade.id);

            // Filter out invalid sections (such as fallback sections with invalid IDs)
            let validSections = [];
            if (Array.isArray(sectionsForGrade)) {
                validSections = sectionsForGrade.filter((section) => {
                    // Ensure the section has a valid ID that's not a fallback format
                    return section && section.id && !String(section.id).includes('grade_');
                });

                if (validSections.length !== sectionsForGrade.length) {
                    console.warn(`Filtered out ${sectionsForGrade.length - validSections.length} invalid sections`);
                }

                sections.value = validSections;
                console.log('Retrieved valid sections:', sections.value.length);

                // Load additional data in parallel only for valid sections
                if (sections.value.length > 0) {
                    const loadPromises = sections.value.map(async (section) => {
                        try {
                            if (!section.id) {
                                console.warn('Section missing ID, skipping:', section);
                                return;
                            }

                            // Skip sections with invalid IDs
                            if (String(section.id).includes('grade_')) {
                                console.warn('Invalid section ID format detected:', section.id);
                                return;
                            }

                            // Load subjects if needed
                            if (!section.subjects) {
                                console.log(`Loading subjects for section with ID: ${section.id}`);
                                const sectionSubjects = await CurriculumService.getSubjectsBySection(selectedCurriculum.value.id, selectedGrade.value.id, section.id);

                                if (Array.isArray(sectionSubjects)) {
                                    section.subjects = sectionSubjects;
                                }

                                // Load homeroom teacher if assigned
                                if (section.homeroom_teacher_id && teachers.value) {
                                    const teacher = teachers.value.find((t) => t && t.id === section.homeroom_teacher_id);
                                    if (teacher) {
                                        section.teacher = teacher;
                                    }
                                }
                            }
                        } catch (error) {
                            console.warn('Error loading additional data for section:', section.id, error);
                        }
                    });

                    // Wait for all additional data to load but don't block UI
                    Promise.all(loadPromises).catch((error) => {
                        console.warn('Some additional data failed to load:', error);
                    });
                }

                // Show appropriate message
                if (sections.value.length === 0) {
                    toast.add({
                        severity: 'info',
                        summary: 'No Sections',
                        detail: 'No sections found for this grade. You can add sections below.',
                        life: 3000
                    });
                }
            } else {
                console.warn('Invalid sections data format:', sectionsForGrade);
                toast.add({
                    severity: 'warn',
                    summary: 'Warning',
                    detail: 'Could not load sections properly.',
                    life: 3000
                });
            }
        } catch (error) {
            console.error('Error in openSectionList:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load sections.',
                life: 3000
            });
        } finally {
            loading.value = false;
        }
    };

    // Add a new method to manually refresh the subject list
    const refreshSectionSubjects = async () => {
        if (!selectedSection.value || !selectedSection.value.id) {
            console.warn('Cannot refresh subjects: No section selected');
            return;
        }

        try {
            loading.value = true;
            console.log('Refreshing subjects for section:', selectedSection.value.id);

            // Clear any cached data for this section
            try {
                const cacheKey = `section_subjects_${selectedSection.value.id}`;
                localStorage.removeItem(cacheKey);
                localStorage.removeItem(`${cacheKey}_timestamp`);
            } catch (e) {
                console.warn('Could not clear cache:', e);
            }

            // Fetch fresh data - specifically only user-added subjects
            const response = await CurriculumService.getSubjectsBySection(selectedCurriculum.value.id, selectedGrade.value.id, selectedSection.value.id);

            if (Array.isArray(response)) {
                console.log('Successfully refreshed subjects:', response.length);
                console.log('Subject names:', response.map((s) => s.name).join(', '));

                // Load schedules for each subject
                const subjectsWithSchedules = await Promise.all(
                    response.map(async (subject) => {
                        try {
                            const schedules = await loadSubjectSchedules(selectedSection.value.id, subject.id);
                            return {
                                ...subject,
                                schedules: schedules
                            };
                        } catch (scheduleErr) {
                            console.warn(`Could not load schedules for subject ${subject.id}:`, scheduleErr);
                            return {
                                ...subject,
                                schedules: []
                            };
                        }
                    })
                );

                selectedSubjects.value = subjectsWithSchedules;

                if (subjectsWithSchedules.length === 0) {
                    toast.add({
                        severity: 'info',
                        summary: 'No Subjects',
                        detail: 'No subjects found for this section.',
                        life: 3000
                    });
                } else {
                    toast.add({
                        severity: 'success',
                        summary: 'Subjects Refreshed',
                        detail: `Loaded ${subjectsWithSchedules.length} subjects.`,
                        life: 3000
                    });
                }
            } else {
                console.warn('Invalid response format:', response);
                selectedSubjects.value = [];
            }
        } catch (error) {
            console.error('Error refreshing section subjects:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to refresh subjects: ' + (error.message || 'Unknown error'),
                life: 3000
            });
            selectedSubjects.value = [];
        } finally {
            loading.value = false;
        }
    };

    const openAddSectionDialog = async () => {
        try {
            console.log('Opening Add Section dialog', {
                selectedGrade: selectedGrade.value,
                selectedCurriculum: selectedCurriculum.value
            });

            if (!selectedGrade.value || !selectedGrade.value.id) {
                console.error('Cannot open section dialog: No grade selected');
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'No grade selected. Please select a grade first.',
                    life: 3000
                });
                return;
            }

            // Set default section values
            section.value = {
                id: null,
                name: '',
                grade_id: selectedGrade.value.id,
                curriculum_id: selectedCurriculum.value.id,
                capacity: 25,
                is_active: true, // Always set to true by default without showing toggle
                teacher_id: null
            };

            // Load teachers if needed
            if (!teachers.value || teachers.value.length === 0) {
                loading.value = true;
                try {
                    const teachersData = await TeacherService.getTeachers();
                    teachers.value = Array.isArray(teachersData) ? teachersData : [];
                    console.log(`Loaded ${teachers.value.length} teachers`);
                } catch (error) {
                    console.error('Error loading teachers:', error);
                    toast.add({
                        severity: 'warn',
                        summary: 'Warning',
                        detail: 'Could not load teachers list',
                        life: 3000
                    });
                    teachers.value = [];
                } finally {
                    loading.value = false;
                }
            }

            // Show dialog
            sectionDialog.value = true;
        } catch (error) {
            console.error('Error in openAddSectionDialog:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to open section dialog',
                life: 3000
            });
        }
    };

    const loadSections = async () => {
        try {
            loading.value = true;
            const response = await CurriculumService.getSectionsByCurriculumGrade(selectedCurriculum.value.id, selectedGrade.value.id);
            sections.value = response;
        } catch (error) {
            console.error('Error loading sections:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load sections',
                life: 3000
            });
        } finally {
            loading.value = false;
        }
    };

    const saveSection = async () => {
        if (!section.value.name) {
            toast.add({
                severity: 'warn',
                summary: 'Warning',
                detail: 'Please enter a section name',
                life: 3000
            });
            return;
        }

        try {
            loading.value = true;
            console.log('Adding section for curriculum:', selectedCurriculum.value.id, 'grade:', selectedGrade.value.id);

            // Prepare section data with all necessary properties
            const sectionData = {
                name: section.value.name,
                curriculum_id: selectedCurriculum.value.id,
                grade_id: selectedGrade.value.id,
                capacity: section.value.capacity || 25,
                is_active: true, // Always set to true
                description: section.value.description || '',
                teacher_id: section.value.teacher_id || null
            };

            console.log('Sending section data:', sectionData);

            // First try to get curriculum_grade_id
            try {
                const curriculumGrade = await CurriculumService.getCurriculumGrade(selectedCurriculum.value.id, selectedGrade.value.id);
                if (curriculumGrade && curriculumGrade.id) {
                    sectionData.curriculum_grade_id = curriculumGrade.id;
                    console.log('Got curriculum_grade_id:', curriculumGrade.id);
                }
            } catch (error) {
                console.warn('Could not get curriculum_grade_id, continuing without it:', error);
            }

            let sectionCreated = false;

            // First try using nested endpoint for better association
            try {
                await CurriculumService.addSectionToGrade(selectedCurriculum.value.id, selectedGrade.value.id, sectionData);
                console.log('Successfully added section using nested endpoint');
                sectionCreated = true;
            } catch (nestedError) {
                console.warn('Nested endpoint failed, trying direct endpoint:', nestedError);

                // Only try direct endpoint if the nested endpoint didn't work
                try {
                    // Fall back to direct endpoint if nested fails
                    await CurriculumService.addSection(sectionData);
                    console.log('Successfully added section using direct endpoint');
                    sectionCreated = true;
                } catch (directError) {
                    console.error('Both nested and direct endpoints failed:', directError);

                    // Check if this is a 500 error but the section might have been created anyway
                    if (directError.response && directError.response.status === 500) {
                        console.warn('Got 500 error, but section might have been created. Will check by refreshing sections.');
                        // We'll still try to refresh the sections list to see if the section was created
                    } else {
                        // If it's not a 500 error or we can't determine, rethrow to be handled in the catch block
                        throw directError;
                    }
                }
            }

            // Reload sections
            try {
                const sectionsForGrade = await CurriculumService.getSectionsByGrade(selectedCurriculum.value.id, selectedGrade.value.id);

                if (Array.isArray(sectionsForGrade)) {
                    sections.value = sectionsForGrade;
                    console.log('Reloaded sections, count:', sections.value.length);

                    // If we got a 500 error but find our section in the list, then it was created
                    if (!sectionCreated) {
                        const sectionExists = sectionsForGrade.some((s) => s.name === sectionData.name);
                        if (sectionExists) {
                            sectionCreated = true;
                            console.log('Section was created despite API errors');
                        }
                    }
                } else {
                    console.warn('Invalid response when reloading sections');
                }
            } catch (error) {
                console.warn('Could not reload sections, will try to add locally:', error);

                // Add section locally if reload fails
                const newSection = {
                    ...sectionData,
                    id: Date.now(), // Temporary ID until page refresh
                    subjects: []
                };
                sections.value.push(newSection);
            }

            // Close dialog and show success message
            sectionDialog.value = false;

            if (sectionCreated) {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Section added successfully',
                    life: 3000
                });
            } else {
                toast.add({
                    severity: 'info',
                    summary: 'Info',
                    detail: 'Section may have been added. Please check the sections list or refresh the page.',
                    life: 5000
                });
            }

            // Reset the section form
            section.value = {
                name: '',
                capacity: 25,
                is_active: true,
                description: '',
                teacher_id: null
            };
        } catch (error) {
            console.error('Error adding section:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to add section: ' + (error.message || 'Unknown error'),
                life: 3000
            });
        } finally {
            loading.value = false;
        }
    };

    const removeSection = async (sectionId) => {
        try {
            loading.value = true;
            console.log('Removing section with ID:', sectionId);

            try {
                await CurriculumService.removeSection(selectedCurriculum.value.id, selectedGrade.value.id, sectionId);
            } catch (error) {
                console.error('Error with removeSection API:', error);
                // Try direct API call as fallback
                await api.delete(`/api/sections/${sectionId}`);
            }

            // Try to reload sections
            try {
                const gradeSections = await CurriculumService.getSectionsByGrade(selectedCurriculum.value.id, selectedGrade.value.id);
                sections.value = Array.isArray(gradeSections) ? gradeSections : [];
            } catch (error) {
                console.warn('Could not reload sections after removal:', error);
                // Remove the section locally from the array
                sections.value = sections.value.filter((s) => s.id !== sectionId);
            }

            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Section removed successfully',
                life: 3000
            });
        } catch (error) {
            console.error('Error removing section:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to remove section: ' + (error.message || 'Unknown error'),
                life: 3000
            });
        } finally {
            loading.value = false;
        }
    };

    // Subject management
    const openSubjectList = async (section) => {
        try {
            console.log('Opening subject list for section:', section);
            selectedSection.value = section;
            loading.value = true;

            // Clear all subjects first
            selectedSubjects.value = [];

            // First make sure the dialog is shown immediately
            showSubjectListDialog.value = true;

            // Always clear cache to ensure we get fresh data
            try {
                const cacheKey = `section_subjects_${section.id}`;
                localStorage.removeItem(cacheKey);
                localStorage.removeItem(`${cacheKey}_timestamp`);
            } catch (e) {
                console.warn('Could not clear cache:', e);
            }

            // Load subjects with their schedules
            try {
                console.log('Fetching ONLY user-added subjects for section ID:', section.id);

                // Force using only user-added subjects with the direct-subjects endpoint
                const response = await api.get(`/api/sections/${section.id}/direct-subjects`, {
                    params: {
                        curriculum_id: selectedCurriculum.value.id,
                        grade_id: selectedGrade.value.id,
                        user_added_only: true,
                        force: true,
                        no_fallback: true,
                        timestamp: Date.now() // Cache busting
                    },
                    headers: {
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        Pragma: 'no-cache',
                        Expires: '0'
                    }
                });

                console.log('API response received:', response.status, 'Data length:', Array.isArray(response.data) ? response.data.length : 'not an array');

                if (response.data && Array.isArray(response.data)) {
                    console.log('Successfully retrieved user-added subjects:', response.data.length);
                    console.log('Subject names:', response.data.map((s) => s.name).join(', '));

                    // Load schedules for each subject
                    const subjectsWithSchedules = await Promise.all(
                        response.data.map(async (subject) => {
                            try {
                                const schedules = await loadSubjectSchedules(section.id, subject.id);
                                return {
                                    ...subject,
                                    schedules: schedules
                                };
                            } catch (scheduleErr) {
                                console.warn(`Could not load schedules for subject ${subject.id}:`, scheduleErr);
                                return {
                                    ...subject,
                                    schedules: []
                                };
                            }
                        })
                    );

                    selectedSubjects.value = subjectsWithSchedules;
                    console.log('Loaded subjects with schedules:', selectedSubjects.value.length);
                } else {
                    console.log('No subjects found or invalid response format');
                    selectedSubjects.value = [];
                }
            } catch (error) {
                console.error('Error fetching subjects:', error);
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to load subjects: ' + (error.message || 'Unknown error'),
                    life: 5000
                });
                selectedSubjects.value = [];
            }
        } catch (error) {
            console.error('Error in openSubjectList:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load subjects and schedules',
                life: 3000
            });
        } finally {
            loading.value = false;
        }
    };

    // Add Subject Dialog handler - Modified to keep the subjects dialog open
    const openAddSubjectDialog = () => {
        console.log('Opening Add Subject dialog');

        // First, make sure we have subjects loaded
        if (!subjects.value || subjects.value.length === 0) {
            loadSubjects();
        }

        // Reset form state
        selectedSubject.value = null;
        submitted.value = false;

        // Show the add subject dialog without closing the subject list dialog
        subjectDialog.value = true;
    };

    // Close dialog when a subject is added successfully
    const closeAddSubjectDialog = () => {
        subjectDialog.value = false;
    };

    // Get locally stored subjects
    const getLocalSubjects = (sectionId) => {
        try {
            const key = `section_subjects_${sectionId}`;
            const stored = localStorage.getItem(key);
            if (stored) {
                return JSON.parse(stored);
            }
        } catch (e) {
            console.warn('Error retrieving local subjects:', e);
        }
        return null;
    };

    // Close subject list dialog
    const closeSubjectListDialog = () => {
        console.log('Closing subject list dialog');

        // Check if we're in the process of opening a schedule dialog
        const isOpeningSchedule = wasSubjectListOpen.value;

        // Only hide the dialog initially
        showSubjectListDialog.value = false;

        // If we're NOT transitioning to schedule dialog
        if (!isOpeningSchedule && !showScheduleDialog.value) {
            console.log('No other dialogs open, cleaning up resources');

            // If schedule dialog is not open, then also reset variables
            // Wait briefly before resetting variables to ensure dialog closes smoothly
            setTimeout(() => {
                // Reset all related variables
                selectedSection.value = null;
                selectedSubjects.value = [];
                selectedSubjectForTeacher.value = null;
                selectedSubjectForSchedule.value = null;
                selectedTeacher.value = null;

                // Also ensure schedule dialog is closed
                showScheduleDialog.value = false;
            }, 100);
        } else {
            console.log('Preserving section/subject context for another dialog');
        }
    };
    // Track if we're already handling a schedule dialog close
    const isScheduleClosing = ref(false);

    // Add this function to handle schedule dialog closing
    const handleScheduleDialogClose = () => {
        // Prevent handling this event multiple times
        if (isScheduleClosing.value) {
            console.log('Already handling schedule dialog close, ignoring duplicate event');
            return;
        }

        isScheduleClosing.value = true;
        console.log('Schedule dialog closed');

        // Check if a schedule was saved
        const scheduleWasSaved = localStorage.getItem('schedule_was_saved') === 'true';

        // Check if we should restore the subject dialog
        const subjectDialogWasOpen = localStorage.getItem('temp_subject_dialog_was_open') === 'true';

        if (subjectDialogWasOpen) {
            try {
                // Get the stored section data
                const storedSectionJson = localStorage.getItem('temp_selected_section');
                if (storedSectionJson) {
                    const storedSection = JSON.parse(storedSectionJson);

                    // Restore the section selection
                    selectedSection.value = storedSection;

                    console.log('Restoring subject dialog with section:', storedSection.name);

                    // Load subjects for this section
                    CurriculumService.getSubjectsBySection(selectedCurriculum.value.id, selectedGrade.value.id, storedSection.id)
                        .then((subjects) => {
                            if (Array.isArray(subjects)) {
                                selectedSubjects.value = subjects;
                            }
                            // Then show the dialog after data is loaded
                            setTimeout(() => {
                                showSubjectListDialog.value = true;

                                // If a schedule was saved, refresh the subjects list
                                if (scheduleWasSaved) {
                                    setTimeout(() => {
                                        refreshSectionSubjects();
                                    }, 500);
                                }

                                // Reset the flag after restoring dialogs
                                setTimeout(() => {
                                    isScheduleClosing.value = false;
                                }, 300);
                            }, 200);
                        })
                        .catch((error) => {
                            console.error('Error reloading subjects:', error);
                            // Still show the dialog even if reload fails
                            setTimeout(() => {
                                showSubjectListDialog.value = true;
                                // Reset the flag after restoring dialogs
                                setTimeout(() => {
                                    isScheduleClosing.value = false;
                                }, 300);
                            }, 200);
                        });
                } else {
                    console.warn('No stored section data found');
                    isScheduleClosing.value = false;
                }
            } catch (error) {
                console.error('Error restoring subject dialog:', error);
                isScheduleClosing.value = false;
            } finally {
                // Clean up temp storage
                localStorage.removeItem('temp_subject_dialog_was_open');
                localStorage.removeItem('temp_selected_section');
            }
        } else {
            // Reset the flag if we're not restoring anything
            isScheduleClosing.value = false;
        }
    };

    // Update the saveSchedule function to use the correct API endpoint
    const saveSchedule = async () => {
        try {
            console.log('Schedule data:', schedule.value);
            console.log('Selected subject:', selectedSubjectForSchedule.value);
            console.log('Selected section:', selectedSection.value);

            // Basic validation
            if (!schedule.value.day || !schedule.value.start_time || !schedule.value.end_time) {
                toast.add({
                    severity: 'warn',
                    summary: 'Warning',
                    detail: 'Please fill in all required fields',
                    life: 3000
                });
                return;
            }

            // Ensure the subject_id and section_id are in the schedule
            if (!schedule.value.subject_id || !schedule.value.section_id) {
                // Try to get them from the context if they exist
                if (selectedSubjectForSchedule.value && selectedSection.value) {
                    schedule.value.subject_id = selectedSubjectForSchedule.value.id;
                    schedule.value.section_id = selectedSection.value.id;
                } else {
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: 'Missing subject or section information',
                        life: 3000
                    });
                    return;
                }
            }

            // Get necessary IDs for the API call
            const curriculumId = selectedCurriculum.value?.id;
            const gradeId = selectedGrade.value?.id;
            const sectionId = selectedSection.value.id;
            const subjectId = selectedSubjectForSchedule.value.id;

            // Validate that we have all required IDs
            if (!curriculumId || !gradeId || !sectionId || !subjectId) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Missing curriculum, grade, section, or subject ID',
                    life: 3000
                });
                return;
            }

            // Show loading state
            loading.value = true;

            // Create schedule data
            const scheduleData = {
                day: schedule.value.day,
                start_time: schedule.value.start_time,
                end_time: schedule.value.end_time,
                teacher_id: schedule.value.teacher_id || null
            };

            console.log('Saving schedule with data:', scheduleData);
            console.log('Using CurriculumService.setSubjectSchedule with:', curriculumId, gradeId, sectionId, subjectId);

            // Save using the correct CurriculumService method
            await CurriculumService.setSubjectSchedule(curriculumId, gradeId, sectionId, subjectId, scheduleData);

            // Show success message
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Schedule saved successfully',
                life: 3000
            });

            // Close the schedule dialog - the onDialogHide will handle reopening the subject list dialog
            showScheduleDialog.value = false;

            // Refresh subject list to show updated schedules
            refreshSectionSubjects();
        } catch (error) {
            console.error('Error saving schedule:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to save schedule: ' + (error.message || 'Unknown error'),
                life: 3000
            });
        } finally {
            // Hide loading state
            loading.value = false;
        }
    };

    // Add the assignTeacher function
    const assignTeacher = async () => {
        try {
            teacherSubmitted.value = true;

            if (!selectedSubjectForTeacher.value) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'No subject selected',
                    life: 3000
                });
                return;
            }

            if (!selectedTeacher.value) {
                toast.add({
                    severity: 'warn',
                    summary: 'Warning',
                    detail: 'Please select a teacher',
                    life: 3000
                });
                return;
            }

            loading.value = true;

            try {
                console.log('Assigning teacher:', selectedTeacher.value);
                console.log('To subject:', selectedSubjectForTeacher.value);

                // Call the API
                await CurriculumService.assignTeacherToSubject(selectedCurriculum.value.id, selectedGrade.value.id, selectedSection.value.id, selectedSubjectForTeacher.value.id, { teacher_id: selectedTeacher.value.id });

                // Update the subject with the teacher in the local data
                selectedSubjectForTeacher.value.teacher = selectedTeacher.value;
                selectedSubjectForTeacher.value.teacher_id = selectedTeacher.value.id;

                // Update the subject in the selectedSubjects array
                const subjectIndex = selectedSubjects.value.findIndex((s) => s.id === selectedSubjectForTeacher.value.id);
                if (subjectIndex !== -1) {
                    selectedSubjects.value[subjectIndex].teacher = selectedTeacher.value;
                    selectedSubjects.value[subjectIndex].teacher_id = selectedTeacher.value.id;
                }

                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Teacher assigned successfully',
                    life: 3000
                });

                // Close the dialog
                showTeacherAssignmentDialog.value = false;
                teacherSubmitted.value = false;
            } catch (error) {
                console.error('Error assigning teacher:', error);
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to assign teacher: ' + (error.message || 'Unknown error'),
                    life: 3000
                });
            }
        } catch (error) {
            console.error('Error in assignTeacher:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'An unexpected error occurred',
                life: 3000
            });
        } finally {
            loading.value = false;
        }
    };

    // Add this computed property to get selected teacher details
    const selectedTeacherDetails = computed(() => {
        if (!section.value || !section.value.teacher_id || !teachers.value) {
            return null;
        }

        const teacherId = section.value.teacher_id;
        const foundTeacher = teachers.value.find((t) => t.id === teacherId);

        if (foundTeacher) {
            console.log('Found teacher details:', foundTeacher);
            return foundTeacher;
        } else {
            console.warn(`Teacher with ID ${teacherId} not found in teachers list`);
            return null;
        }
    });

    // Update the assignHomeRoomTeacher function
    const assignHomeRoomTeacher = async () => {
        try {
            // Debug which version is running
            console.log('Running assignHomeRoomTeacher from src/views/pages/Admin/Curriculum.vue');

            // Set submitted flag for validation
            teacherSubmitted.value = true;

            // Validate teacher selection
            if (!selectedTeacher.value) {
                toast.add({
                    severity: 'warn',
                    summary: 'Warning',
                    detail: 'Please select a teacher',
                    life: 3000
                });
                return;
            }

            // Get IDs from multiple sources
            let curriculumId = selectedCurriculum.value?.id;
            let gradeId = selectedGrade.value?.id;
            let sectionId = selectedSection.value?.id;

            // If component state doesn't have IDs, try route params
            if (!curriculumId) {
                curriculumId = route.params.id || route.params.curriculumId;
            }
            if (!gradeId) {
                gradeId = route.params.gradeId;
            }

            // If route params don't have IDs, try localStorage (set by openSectionList)
            if (!curriculumId) {
                curriculumId = localStorage.getItem('currentCurriculumId');
            }
            if (!gradeId) {
                gradeId = localStorage.getItem('currentGradeId');
            }

            // Parse numeric IDs
            if (curriculumId) curriculumId = parseInt(curriculumId);
            if (gradeId) gradeId = parseInt(gradeId);

            console.log('IDs from assignHomeRoomTeacher:');
            console.log('- Curriculum ID:', curriculumId);
            console.log('- Grade ID:', gradeId);
            console.log('- Section ID:', sectionId);

            // Validate that we have all the required IDs
            if (!curriculumId) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'No curriculum selected',
                    life: 3000
                });
                return;
            }

            if (!gradeId) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'No grade selected',
                    life: 3000
                });
                return;
            }

            if (!sectionId) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'No section selected',
                    life: 3000
                });
                return;
            }

            loading.value = true;

            // Extract teacher ID - handle both object and direct ID cases
            const teacherId = typeof selectedTeacher.value === 'object' ? selectedTeacher.value.id || null : parseInt(selectedTeacher.value, 10);

            if (!teacherId || isNaN(teacherId)) {
                throw new Error('Invalid teacher ID');
            }

            console.log('Assigning homeroom teacher with ID:', teacherId);
            console.log('selectedSection:', selectedSection.value);
            console.log('selectedGrade:', selectedGrade.value);
            console.log('selectedCurriculum:', selectedCurriculum.value);

            // Use dedicated endpoint for homeroom teacher assignment
            const url = `/api/curriculums/${curriculumId}/grades/${gradeId}/sections/${sectionId}/teacher`;

            console.log('Using dedicated homeroom teacher endpoint:', url);

            // Send only teacher_id in the payload
            const payload = {
                teacher_id: teacherId
            };

            console.log('Sending payload:', payload);
            const response = await api.post(url, payload);

            console.log('Assignment successful:', response.data);

            // Update local state
            if (selectedSection.value) {
                selectedSection.value.homeroom_teacher_id = teacherId;
            }

            // Update sections array
            if (sections.value && sections.value.length > 0) {
                const sectionIndex = sections.value.findIndex((s) => s.id === sectionId);
                if (sectionIndex !== -1) {
                    sections.value[sectionIndex].homeroom_teacher_id = teacherId;
                }
            }

            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Homeroom teacher assigned successfully',
                life: 3000
            });

            // Reset state and close dialog
            selectedTeacher.value = null;
            homeRoomTeacherAssignmentDialog.value = false;
        } catch (error) {
            console.error('Error assigning homeroom teacher:', error);
            if (error.response) {
                console.error('Error details:', error.response.data);

                // Show the specific SQL error to help debugging
                if (error.response.data && error.response.data.error) {
                    console.error('SQL Error:', error.response.data.error);
                    toast.add({
                        severity: 'error',
                        summary: 'Database Error',
                        detail: 'SQL Error: ' + error.response.data.error.substring(0, 100) + '...',
                        life: 5000
                    });
                }
            }
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: error.message || 'Failed to assign homeroom teacher',
                life: 3000
            });
        } finally {
            loading.value = false;
            teacherSubmitted.value = false;
        }
    };
    // Add the openScheduleDialog function after closeSubjectListDialog
    const openScheduleDialog = async (subject) => {
        try {
            console.log('Opening schedule dialog for subject:', subject);

            // Store whether the subject list dialog was open
            wasSubjectListOpen.value = showSubjectListDialog.value;

            // Store the subject and section information BEFORE closing any dialogs
            selectedSubjectForSchedule.value = subject;

            // Capture section information before closing dialog
            const currentSection = { ...selectedSection.value };

            // Initialize the schedule data with a valid day option
            schedule.value = {
                day: dayOptions[0].value, // Use the first day option (Monday)
                start_time: '08:00',
                end_time: '09:00',
                subject_id: subject.id,
                section_id: currentSection.id,
                teacher_id: subject.teacher?.id || null
            };

            console.log('Captured section info:', currentSection);
            console.log('Prepared schedule data:', schedule.value);

            // Load teachers if we haven't already
            if (!teachers.value || teachers.value.length === 0) {
                try {
                    console.log('Loading teachers...');
                    loadingTeachers.value = true;
                    const response = await api.get('/api/teachers');
                    teachers.value = response.data;
                    console.log('Teachers loaded:', teachers.value);
                } catch (error) {
                    console.error('Failed to load teachers:', error);
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: 'Failed to load teachers: ' + (error.message || 'Unknown error'),
                        life: 3000
                    });
                } finally {
                    loadingTeachers.value = false;
                }
            }

            // Close the subject list dialog if it's open
            if (showSubjectListDialog.value) {
                showSubjectListDialog.value = false;

                // Wait for the subject list dialog to close before opening the schedule dialog
                setTimeout(() => {
                    // Make sure the section is still available
                    if (!selectedSection.value) {
                        selectedSection.value = currentSection;
                    }

                    showScheduleDialog.value = true;
                    console.log('Schedule dialog opened after closing subject list dialog');
                }, 300);
            } else {
                // Just open the schedule dialog if subject list dialog wasn't open
                showScheduleDialog.value = true;
                console.log('Schedule dialog opened directly');
            }
        } catch (error) {
            console.error('Error opening schedule dialog:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to open schedule dialog: ' + (error.message || 'Unknown error'),
                life: 3000
            });
        }
    };

    // Add a watch for the schedule dialog to restore subject list dialog when closed
    // Not needed anymore - replaced by onDialogHide function
    // watch(showScheduleDialog, (newValue) => {
    //     // When schedule dialog is closed and subject list was previously open
    //     if (!newValue && wasSubjectListOpen.value) {
    //         // Re-open the subject list dialog
    //         setTimeout(() => {
    //             showSubjectListDialog.value = true;
    //             wasSubjectListOpen.value = false;
    //         }, 300); // Small delay to ensure proper closing of schedule dialog first
    //     }
    // });

    // Add the openTeacherDialog function before openScheduleDialog
    const openTeacherDialog = async (subject) => {
        selectedSubjectForTeacher.value = subject;
        selectedTeacher.value = null;
        showTeacherAssignmentDialog.value = true; // Show dialog immediately

        // Load teachers if needed
        if (!teachers.value || teachers.value.length === 0) {
            loading.value = true;
            try {
                const teachersData = await TeacherService.getTeachers();
                teachers.value = Array.isArray(teachersData) ? teachersData : [];
                console.log(`Loaded ${teachers.value.length} teachers`);

                if (teachers.value.length === 0) {
                    toast.add({
                        severity: 'warn',
                        summary: 'Notice',
                        detail: 'No teachers found in the system.',
                        life: 3000
                    });
                }
            } catch (error) {
                console.error('Error loading teachers:', error);
                teachers.value = []; // Reset to empty array on error
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to load teachers: ' + (error.message || 'Unknown error'),
                    life: 3000
                });
            } finally {
                // Intentional empty finally block to prevent automatic loading reset
            }
        }

        // If the subject already has a teacher assigned, pre-select it
        if (subject.teacher_id) {
            const existingTeacher = teachers.value.find((t) => t.id === subject.teacher_id);
            if (existingTeacher) {
                selectedTeacher.value = existingTeacher;
                console.log('Pre-selected existing teacher:', existingTeacher);
            }
        }
    };

    // Add a helper function to get teacher name from ID
    const getTeacherName = (teacherId) => {
        if (!teacherId) return 'No teacher selected';

        // If the full teacher object is passed
        if (typeof teacherId === 'object') {
            if (teacherId.first_name && teacherId.last_name) {
                return `${teacherId.first_name} ${teacherId.last_name}`;
            } else if (teacherId.name) {
                return teacherId.name;
            }
        }

        // If just the ID is passed, look up the teacher in the teachers array
        const teacher = teachers.value.find((t) => t.id === teacherId);
        if (teacher) {
            if (teacher.first_name && teacher.last_name) {
                return `${teacher.first_name} ${teacher.last_name}`;
            } else if (teacher.name) {
                return teacher.name;
            }
        }

        return `Teacher ${teacherId}`;
    };

    // Add this function after loadSubjects
    const loadSubjectSchedules = async (sectionId, subjectId) => {
        try {
            // First try the direct endpoint with singular 'schedule'
            try {
                const response = await api.get(`/api/sections/${sectionId}/subjects/${subjectId}/schedule`);
                return Array.isArray(response.data) ? response.data : response.data ? [response.data] : [];
            } catch (directError) {
                console.error('Error fetching schedule from direct endpoint:', directError);
                // Try nested endpoint as fallback
                try {
                    const response = await api.get(`/api/curriculums/${selectedCurriculum.value.id}/grades/${selectedGrade.value.id}/sections/${sectionId}/subjects/${subjectId}/schedule`);
                    return Array.isArray(response.data) ? response.data : response.data ? [response.data] : [];
                } catch (nestedError) {
                    console.error('Error fetching schedule from nested endpoint:', nestedError);
                    return [];
                }
            }
        } catch (error) {
            console.error('Error loading subject schedules:', error);
            return [];
        }
    };

    // Add computed property for available subjects (those not already added to this section)
    const availableSubjects = computed(() => {
        if (!subjects.value || !selectedSubjects.value) return [];

        // Get IDs of subjects already in the section
        const existingSubjectIds = selectedSubjects.value.map((s) => s.id);

        // Filter out subjects that are already added
        return subjects.value.filter((subject) => !existingSubjectIds.includes(subject.id));
    });

    // Add this new method to the component
    const repairSectionGradeRelationships = async () => {
        try {
            loading.value = true;
            console.log('Repairing section-grade relationships');

            toast.add({
                severity: 'info',
                summary: 'Repair Started',
                detail: 'Repairing section-grade relationships. This may take a moment...',
                life: 3000
            });

            const result = await CurriculumService.repairSectionGradeRelationships();

            console.log('Repair completed:', result);

            toast.add({
                severity: 'success',
                summary: 'Repair Completed',
                detail: 'Section-grade relationships have been repaired.',
                life: 5000
            });

            // Reload the grade levels and sections
            await loadGradeLevels();

            // Reload sections if a grade is selected
            if (selectedGrade.value) {
                await openSectionList(selectedGrade.value);
            }
        } catch (error) {
            console.error('Error repairing relationships:', error);
            toast.add({
                severity: 'error',
                summary: 'Repair Failed',
                detail: 'Failed to repair section-grade relationships.',
                life: 5000
            });
        } finally {
            loading.value = false;
        }
    };

    // Remove subject from section
    const removeSubjectFromSection = async (subjectId) => {
        try {
            if (!selectedSection.value || !subjectId) {
                console.error('Missing section or subject ID');
                return;
            }

            console.log('Removing subject', subjectId, 'from section', selectedSection.value.id);
            loading.value = true;

            // First try using the nested API endpoint
            try {
                await CurriculumService.removeSubjectFromSection(selectedCurriculum.value.id, selectedGrade.value.id, selectedSection.value.id, subjectId);

                console.log('Successfully removed subject from section');

                // Update the UI by removing the subject from the list
                selectedSubjects.value = selectedSubjects.value.filter((s) => s.id !== subjectId);

                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Subject removed successfully',
                    life: 3000
                });
            } catch (error) {
                console.error('Error removing subject from section:', error);

                // Try direct endpoint as fallback
                try {
                    console.log('Trying direct endpoint to remove subject');
                    await api.delete(`/api/sections/${selectedSection.value.id}/subjects/${subjectId}`);

                    // Update the UI by removing the subject from the list
                    selectedSubjects.value = selectedSubjects.value.filter((s) => s.id !== subjectId);

                    toast.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: 'Subject removed successfully',
                        life: 3000
                    });
                } catch (directError) {
                    console.error('Direct endpoint also failed:', directError);
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: 'Failed to remove subject. Please try again.',
                        life: 3000
                    });
                }
            }
        } catch (error) {
            console.error('Error in removeSubjectFromSection:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'An unexpected error occurred',
                life: 3000
            });
        } finally {
            loading.value = false;
        }
    };

    // Add subject to section
    const addSubjectToSection = async (subjectId) => {
        if (!subjectId && (!selectedSubject.value || !selectedSubject.value.id)) {
            toast.add({
                severity: 'warn',
                summary: 'Warning',
                detail: 'Please select a subject',
                life: 3000
            });
            return;
        }

        // If subjectId is not provided directly, use the selected subject's ID
        const actualSubjectId = subjectId || selectedSubject.value.id;

        try {
            loading.value = true;
            console.log('Adding subject', actualSubjectId, 'to section', selectedSection.value.id);

            // Prepare the data object
            const subjectData = {
                subject_id: actualSubjectId,
                section_id: selectedSection.value.id,
                curriculum_id: selectedCurriculum.value.id,
                grade_id: selectedGrade.value.id
            };

            // First try using the nested API endpoint
            try {
                await CurriculumService.addSubjectToSection(selectedCurriculum.value.id, selectedGrade.value.id, selectedSection.value.id, subjectData);

                console.log('Successfully added subject to section');

                // Get the subject details
                const subject = subjects.value.find((s) => s.id === actualSubjectId);

                // Add the subject to the local list if it's not already there
                if (subject && !selectedSubjects.value.some((s) => s.id === actualSubjectId)) {
                    selectedSubjects.value.push({
                        ...subject,
                        schedules: []
                    });
                }

                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Subject added successfully',
                    life: 3000
                });

                // Reset selected subject
                selectedSubject.value = null;

                // Close the add subject dialog
                closeAddSubjectDialog();
            } catch (error) {
                console.error('Error adding subject with nested endpoint:', error);

                // Try direct endpoint as fallback
                try {
                    console.log('Trying direct endpoint to add subject');
                    await api.post(`/api/sections/${selectedSection.value.id}/subjects`, { subject_id: actualSubjectId });

                    // Get the subject details
                    const subject = subjects.value.find((s) => s.id === actualSubjectId);

                    // Add the subject to the local list if it's not already there
                    if (subject && !selectedSubjects.value.some((s) => s.id === actualSubjectId)) {
                        selectedSubjects.value.push({
                            ...subject,
                            schedules: []
                        });
                    }

                    toast.add({
                        severity: 'success',
                        summary: 'Success',
                        detail: 'Subject added successfully',
                        life: 3000
                    });

                    // Reset selected subject
                    selectedSubject.value = null;

                    // Close the add subject dialog
                    closeAddSubjectDialog();
                } catch (directError) {
                    console.error('Direct endpoint also failed:', directError);
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: 'Failed to add subject. Please try again.',
                        life: 3000
                    });
                }
            }
        } catch (error) {
            console.error('Error in addSubjectToSection:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'An unexpected error occurred',
                life: 3000
            });
        } finally {
            loading.value = false;
        }
    };

    // Computed property to determine if the current grade should have subject teachers
    const currentGradeHasSubjectTeachers = computed(() => {
        if (!selectedGrade.value || !selectedGrade.value.code) {
            return false;
        }
        return requiresSubjectTeachers(selectedGrade.value.code);
    });

    // Teacher Assignment Dialog
    const openTeacherAssignmentDialog = () => {
        showTeacherAssignmentDialog.value = true;
    };

    const closeTeacherAssignmentDialog = () => {
        showTeacherAssignmentDialog.value = false;
    };

    const assignSubjectTeacher = (subject) => {
        selectedSubjectForTeacher.value = subject;
        openTeacherAssignmentDialog();
    };

    // Add this function to check for schedule conflicts
    const checkForScheduleConflicts = (newSchedule) => {
        if (!selectedSubjects.value || !selectedSection.value) return false;

        // Convert the new schedule's times to minutes for easier comparison
        const newStartMinutes = convertTimeToMinutes(newSchedule.start_time);
        const newEndMinutes = convertTimeToMinutes(newSchedule.end_time);
        const newDay = newSchedule.day;

        // Check each subject's schedules for conflicts
        for (const subject of selectedSubjects.value) {
            // Skip the current subject being edited
            if (subject.id === selectedSubjectForSchedule.value?.id) continue;

            if (subject.schedules && subject.schedules.length > 0) {
                for (const existingSchedule of subject.schedules) {
                    // Only check schedules for the same day
                    if (existingSchedule.day !== newDay) continue;

                    const existingStartMinutes = convertTimeToMinutes(existingSchedule.start_time);
                    const existingEndMinutes = convertTimeToMinutes(existingSchedule.end_time);

                    // Check for overlap
                    if (
                        // New schedule starts during an existing schedule
                        (newStartMinutes >= existingStartMinutes && newStartMinutes < existingEndMinutes) ||
                        // New schedule ends during an existing schedule
                        (newEndMinutes > existingStartMinutes && newEndMinutes <= existingEndMinutes) ||
                        // New schedule completely contains an existing schedule
                        (newStartMinutes <= existingStartMinutes && newEndMinutes >= existingEndMinutes)
                    ) {
                        return {
                            hasConflict: true,
                            conflictWith: subject.name,
                            existingTime: `${existingSchedule.start_time} - ${existingSchedule.end_time}`
                        };
                    }
                }
            }
        }

        return { hasConflict: false };
    };

    // Helper function to convert time string (HH:MM) to minutes for comparison
    const convertTimeToMinutes = (timeString) => {
        const [hours, minutes] = timeString.split(':').map(Number);
        return hours * 60 + minutes;
    };

    // Function to suggest next available time slot for a day
    const getNextAvailableTimeSlot = (day) => {
        if (!selectedSubjects.value || selectedSubjects.value.length === 0) {
            return { start_time: '08:00', end_time: '09:00' }; // Default if no schedules exist
        }

        // Collect all schedules for the given day
        const daySchedules = [];
        for (const subject of selectedSubjects.value) {
            if (subject.schedules && subject.schedules.length > 0) {
                for (const schedule of subject.schedules) {
                    if (schedule.day === day) {
                        daySchedules.push({
                            start: convertTimeToMinutes(schedule.start_time),
                            end: convertTimeToMinutes(schedule.end_time)
                        });
                    }
                }
            }
        }

        if (daySchedules.length === 0) {
            return { start_time: '08:00', end_time: '09:00' }; // Default if no schedules for this day
        }

        // Sort schedules by start time
        daySchedules.sort((a, b) => a.start - b.start);

        // Find latest end time
        const latestEnd = Math.max(...daySchedules.map((s) => s.end));

        // Convert minutes back to HH:MM format
        const startHour = Math.floor(latestEnd / 60);
        const startMinute = latestEnd % 60;
        const endHour = Math.floor((latestEnd + 60) / 60); // Default to 1 hour later
        const endMinute = (latestEnd + 60) % 60;

        // Format times with leading zeros
        const formatTime = (h, m) => `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;

        return {
            start_time: formatTime(startHour, startMinute),
            end_time: formatTime(endHour, endMinute)
        };
    };

    // Function to open homeroom teacher assignment dialog
    const openHomeRoomTeacherDialog = async (section) => {
        try {
            console.log('Opening homeroom teacher dialog for section:', section);

            // Get IDs from multiple sources - try route params first, then localStorage
            let curriculumId = route.params.id || route.params.curriculumId;
            let gradeId = route.params.gradeId;

            // If route params are missing, try localStorage (set by openSectionList)
            if (!curriculumId) {
                curriculumId = localStorage.getItem('currentCurriculumId');
                console.log('Retrieved curriculum ID from localStorage:', curriculumId);
            }

            if (!gradeId) {
                gradeId = localStorage.getItem('currentGradeId');
                console.log('Retrieved grade ID from localStorage:', gradeId);
            }

            console.log('IDs for homeroom teacher assignment:');
            console.log('- Route params:', route.params);
            console.log('- Curriculum ID:', curriculumId);
            console.log('- Grade ID:', gradeId);

            if (!curriculumId || !gradeId) {
                console.error('Missing route parameters:', { curriculumId, gradeId });
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Missing curriculum or grade information. Please try again from the main curriculum view.',
                    life: 5000
                });
                return;
            }

            // Parse IDs to integers
            curriculumId = parseInt(curriculumId);
            gradeId = parseInt(gradeId);

            // Make sure curriculum and grade data is loaded
            if (!curriculums.value.length) {
                await loadCurriculums();
            }

            // Set the selected section first
            selectedSection.value = section;

            // Find and set selected curriculum and grade from their IDs
            selectedCurriculum.value = curriculums.value.find((c) => c.id === curriculumId);

            if (!selectedCurriculum.value) {
                console.error('Selected curriculum not found for ID:', curriculumId);
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Curriculum not found',
                    life: 3000
                });
                return;
            }

            // Make sure grades are loaded for this curriculum
            if (!grades.value.length) {
                await loadGrades(selectedCurriculum.value.id);
            }

            selectedGrade.value = grades.value.find((g) => g.id === gradeId);

            if (!selectedGrade.value) {
                console.error('Selected grade not found for ID:', gradeId);
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Grade not found',
                    life: 3000
                });
                return;
            }

            console.log('Selected data before opening dialog:');
            console.log('- Curriculum:', selectedCurriculum.value);
            console.log('- Grade:', selectedGrade.value);
            console.log('- Section:', selectedSection.value);

            // Pre-select the current teacher if one is assigned
            if (section.homeroom_teacher_id) {
                console.log('Pre-selecting teacher with ID:', section.homeroom_teacher_id);
                selectedTeacher.value = section.homeroom_teacher_id;
            } else {
                selectedTeacher.value = null;
            }

            // Now use our function to handle teacher loading and dialog display
            if (!teachers.value || teachers.value.length === 0) {
                console.log('No teachers found, loading teachers...');
                try {
                    await loadTeachers();
                    if (teachers.value.length === 0) {
                        toast.add({
                            severity: 'warn',
                            summary: 'No Teachers',
                            detail: 'No teachers found in the database. Please add teachers first.',
                            life: 5000
                        });
                        return;
                    }
                    // Open dialog after teachers are loaded
                    homeRoomTeacherAssignmentDialog.value = true;
                } catch (error) {
                    console.error('Failed to load teachers:', error);
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: 'Failed to load teachers: ' + error.message,
                        life: 5000
                    });
                }
            } else {
                // Teachers already loaded, open dialog
                homeRoomTeacherAssignmentDialog.value = true;
            }
        } catch (error) {
            console.error('Error opening homeroom teacher dialog:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to open homeroom teacher assignment dialog: ' + error.message,
                life: 5000
            });
        }
    };

    // Add a watch to update time slots when day changes
    watch(
        () => schedule.value.day,
        (newDay) => {
            if (newDay && showScheduleDialog.value) {
                // Get suggested time slot for the new day
                const suggestedTimes = getNextAvailableTimeSlot(newDay);

                // Update the schedule times
                schedule.value.start_time = suggestedTimes.start_time;
                schedule.value.end_time = suggestedTimes.end_time;

                console.log(`Updated schedule times for ${newDay}: ${suggestedTimes.start_time} - ${suggestedTimes.end_time}`);
            }
        }
    );

    const openAssignTeacherDialog = () => {
        if (!teachers.value || teachers.value.length === 0) {
            console.log('No teachers found, loading teachers...');
            loadTeachers()
                .then(() => {
                    if (teachers.value.length === 0) {
                        toast.add({
                            severity: 'warn',
                            summary: 'No Teachers',
                            detail: 'No teachers found in the database. Please add teachers first.',
                            life: 5000
                        });
                    } else {
                        // Continue opening the dialog if teachers were found
                        selectedTeacher.value = null;
                        homeRoomTeacherAssignmentDialog.value = true;
                    }
                })
                .catch((error) => {
                    console.error('Failed to load teachers:', error);
                    toast.add({
                        severity: 'error',
                        summary: 'Error',
                        detail: 'Failed to load teachers: ' + error.message,
                        life: 5000
                    });
                });
        } else {
            // Teachers already loaded, open dialog
            selectedTeacher.value = null;
            homeRoomTeacherAssignmentDialog.value = true;
        }
    };

    // Add a watch to update the curriculum name when year range changes
    watch(
        () => [curriculum.value.yearRange.start, curriculum.value.yearRange.end],
        ([newStart, newEnd]) => {
            if (newStart && newEnd) {
                curriculum.value.name = `Curriculum ${newStart}-${newEnd}`;
            }
        }
    );

    // Add a watch for yearRange to update the name automatically
    watch(
        () => curriculum.value.yearRange,
        (newYearRange) => {
            if (newYearRange && newYearRange.start && newYearRange.end) {
                curriculum.value.name = `Curriculum ${newYearRange.start}-${newYearRange.end}`;
            }
        },
        { deep: true }
    );

    // At the top of the script with other refs, add:
    const wasSubjectListOpen = ref(false);

    // Add a debug watcher for wasSubjectListOpen
    watch(wasSubjectListOpen, (newValue) => {
        console.log('wasSubjectListOpen changed to:', newValue);
    });

    // Add a watch to handle dialog hide event
    const onDialogHide = () => {
        console.log('Schedule dialog hidden, wasSubjectListOpen:', wasSubjectListOpen.value);

        // If we previously had the subject list dialog open
        if (wasSubjectListOpen.value) {
            // Give a small delay to ensure clean transition
            setTimeout(() => {
                console.log('Reopening subject list dialog');
                showSubjectListDialog.value = true;
                // Reset the flag after reopening
                wasSubjectListOpen.value = false;
            }, 300);
        }
    };


}); // Close onMounted function

// Watch for changes in grade type to reset value if needed
watch(
    selectedGradeType,
    (newType) => {
        console.log('Grade type changed to:', newType);
        // Reset grade value when type changes to enforce limits
        if (newType && gradeValue.value) {
            if (newType === 'KINDER' && gradeValue.value > 2) {
                gradeValue.value = 1; // Reset to valid value for Kinder
            } else if (newType === 'GRADE' && gradeValue.value > 6) {
                gradeValue.value = 1; // Reset to valid value for Grade
            }
        }
    }
);

// Watch for changes in grade type and value to update code, name and level
watch(
    [selectedGradeType, gradeValue],
    ([newType, newValue]) => {
        console.log('Watch triggered - newType:', newType, 'newValue:', newValue);
        
        if (newType && newValue !== null && newValue !== undefined) {
            if (newType === 'KINDER') {
                newGrade.value.code = `K${newValue}`;
                newGrade.value.name = `Kinder ${newValue}`;
                newGrade.value.level = '0';
                newGrade.value.display_order = Number(newValue);
            } else if (newType === 'GRADE') {
                newGrade.value.code = `G${newValue}`;
                newGrade.value.name = `Grade ${newValue}`;
                newGrade.value.level = newValue.toString();
                newGrade.value.display_order = 2 + Number(newValue);
            } else if (newType === 'ALS') {
                newGrade.value.code = `ALS${newValue}`;
                newGrade.value.name = `ALS ${newValue}`;
                newGrade.value.level = (100 + Number(newValue)).toString();
                newGrade.value.display_order = 100 + Number(newValue);
            }
            console.log('Updated newGrade:', newGrade.value);
        } else {
            // Clear the generated fields if no valid selection
            newGrade.value.code = '';
            newGrade.value.name = '';
            newGrade.value.level = '0';
            newGrade.value.display_order = 0;
        }
    },
    { immediate: true, deep: true }
);

// Save new grade function - moved outside onMounted to be accessible to template
const saveNewGrade = async () => {
    console.log('saveNewGrade called');
    console.log('selectedGradeType:', selectedGradeType.value);
    console.log('gradeValue:', gradeValue.value);
    console.log('newGrade:', newGrade.value);
    
    gradeSubmitted.value = true;

    if (!newGrade.value.code?.trim() || !newGrade.value.name?.trim()) {
        console.log('Validation failed - missing code or name');
        console.log('Code:', newGrade.value.code);
        console.log('Name:', newGrade.value.name);
        
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'Grade code and name are required. Please select a grade type and enter a value.',
            life: 5000
        });
        return;
    }

    // Check if grade code already exists
    const existingGrade = grades.value.find(grade => grade.code === newGrade.value.code);
    if (existingGrade) {
        toast.add({ 
            severity: 'warn', 
            summary: 'Duplicate Grade', 
            detail: `Grade ${newGrade.value.code} already exists. Please select a different grade type or number.`, 
            life: 5000 
        });
        return;
    }

    try {
        loading.value = true;
        
        // Set grade as active by default
        const gradeData = {
            ...newGrade.value,
            is_active: true
        };
        
        console.log('Sending grade data:', gradeData);
        await GradesService.createGrade(gradeData);
        
        toast.add({ 
            severity: 'success', 
            summary: 'Success', 
            detail: 'Grade created successfully', 
            life: 3000 
        });

        // Refresh grades data
        await loadGrades();
        await loadAllGrades();
        
        // Reset form
        selectedGradeType.value = null;
        gradeValue.value = 1;
        newGrade.value = {
            code: '',
            name: '',
            is_active: true,
            level: '0',
            display_order: 0,
            description: ''
        };
        
        // Close dialog
        newGradeDialog.value = false;
        
    } catch (error) {
        console.error('Error creating grade:', error);
        let errorMessage = 'Failed to create grade';
        
        if (error.response) {
            if (error.response.status === 422) {
                if (error.response.data.errors) {
                    const validationErrors = Object.values(error.response.data.errors).flat().join(', ');
                    errorMessage = `Validation error: ${validationErrors}`;
                } else if (error.response.data.message) {
                    errorMessage = error.response.data.message;
                }
            } else if (error.response.data && error.response.data.message) {
                errorMessage = error.response.data.message;
            }
        }

        toast.add({ 
            severity: 'error', 
            summary: 'Error', 
            detail: errorMessage, 
            life: 5000 
        });
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.curriculum-wrapper {
    position: relative;
    overflow: hidden;
    min-height: 100vh;
    background-color: #e0f2ff;
    border-radius: 0 0 24px 0;
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.1);
}

.background-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    overflow: hidden;
}

.geometric-shape {
    position: absolute;
    opacity: 0.1;
    pointer-events: none;
}

.geometric-shape.circle {
    width: 400px;
    height: 400px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4a87d5, #6b9de8);
    top: -100px;
    right: -100px;
    animation: float 15s infinite ease-in-out;
}

.geometric-shape.square {
    width: 300px;
    height: 300px;
    background: linear-gradient(135deg, #ff7eb3, #ff758c);
    bottom: -150px;
    left: -150px;
    transform: rotate(45deg);
    animation: float 20s infinite ease-in-out reverse;
}

.geometric-shape.triangle {
    width: 0;
    height: 0;
    border-left: 200px solid transparent;
    border-right: 200px solid transparent;
    border-bottom: 346px solid rgba(150, 230, 161, 0.15);
    top: 400px;
    right: 100px;
    animation: float 18s infinite ease-in-out 2s;
}

.geometric-shape.diamond {
    width: 150px;
    height: 150px;
    background: linear-gradient(135deg, #a18cd1, #fbc2eb);
    top: 300px;
    left: 80px;
    transform: rotate(45deg);
    animation: float 25s infinite ease-in-out 3s;
}

@keyframes float {
    0% {
        transform: translate(0, 0) rotate(0deg);
    }
    50% {
        transform: translate(20px, 20px) rotate(5deg);
    }
    100% {
        transform: translate(0, 0) rotate(0deg);
    }
}

.admin-grade-container {
    position: relative;
    z-index: 2;
    padding: 1.5rem 2.5rem;
    background: rgba(220, 236, 255, 0.85);
    backdrop-filter: blur(10px);
    min-height: 100vh;
    color: #1a365d;
    border-radius: 0 0 24px 0;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05) inset;
    animation: subtle-glow 10s ease-in-out infinite alternate;
}

@keyframes subtle-glow {
    0% {
        box-shadow: 0 0 30px 10px rgba(74, 135, 213, 0.05) inset;
    }
    50% {
        box-shadow: 0 0 40px 15px rgba(107, 157, 232, 0.08) inset;
    }
    100% {
        box-shadow: 0 0 30px 10px rgba(74, 135, 213, 0.05) inset;
    }
}

.top-nav-bar {
    border-bottom: 1px solid rgba(74, 135, 213, 0.2);
    margin-bottom: 2.5rem;
    padding-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.top-nav-bar .nav-left h2 {
    color: #1a365d;
    font-size: 2rem;
    font-weight: 600;
    margin: 0;
    text-shadow: 0 2px 6px rgba(74, 135, 213, 0.3);
    letter-spacing: 0.5px;
}

.nav-right {
    display: flex;
    align-items: center;
}

.search-container {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 0 1.5rem;
}

.search-input-wrapper {
    position: relative;
    width: 100%;
    max-width: 500px;
    background: rgba(211, 233, 255, 0.8);
    border-radius: 10px;
    border: 1px solid rgba(74, 135, 213, 0.3);
    overflow: hidden;
    display: flex;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.search-input-wrapper:focus-within {
    border-color: rgba(74, 135, 213, 0.6);
    box-shadow: 0 6px 16px rgba(74, 135, 213, 0.2);
}

.search-input {
    flex: 1;
    background: transparent;
    border: none;
    height: 42px;
    padding: 0 1rem;
    color: #1a365d;
    font-size: 0.95rem;
    width: 100%;
}

.search-input::placeholder {
    color: rgba(26, 54, 93, 0.6);
}

.search-input:focus {
    outline: none;
}

.search-icon {
    color: rgba(26, 54, 93, 0.6);
    margin-left: 1rem;
}

.search-icon {
    color: rgba(26, 54, 93, 0.6);
    margin-left: 0.5rem;
}

.clear-search-btn {
    background: transparent;
    border: none;
    color: rgba(26, 54, 93, 0.6);
    cursor: pointer;
    margin-right: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.clear-search-btn:hover {
    color: #1a365d;
    background: rgba(74, 135, 213, 0.1);
}

.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: #1a365d;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2.5rem;
    padding: 0.5rem;
}

.subject-card {
    height: 220px;
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    transition: all 0.4s ease;
    position: relative;
    border: 1px solid rgba(74, 135, 213, 0.3);
}

.subject-card:hover {
    transform: translateY(-8px);
    box-shadow:
        0 15px 30px rgba(0, 0, 0, 0.15),
        0 0 25px rgba(74, 135, 213, 0.4);
    border: 1px solid rgba(74, 135, 213, 0.5);
}

.subject-card .symbol {
    position: absolute;
    color: rgba(26, 54, 93, 0.5);
    font-family: 'Courier New', monospace;
    pointer-events: none;
    z-index: 1;
    animation: float-symbol 8s linear infinite;
    font-weight: bold;
}

.subject-card:nth-child(3n) .symbol {
    animation-duration: 10s;
}

.subject-card:nth-child(3n + 1) .symbol {
    animation-duration: 7s;
}

.subject-card .symbol:nth-child(1) {
    top: 10%;
    left: 10%;
    animation-delay: 0s;
}
.subject-card .symbol:nth-child(2) {
    top: 30%;
    left: 80%;
    animation-delay: 1s;
}
.subject-card .symbol:nth-child(3) {
    top: 70%;
    left: 30%;
    animation-delay: 2s;
}
.subject-card .symbol:nth-child(4) {
    top: 60%;
    left: 70%;
    animation-delay: 3s;
}
.subject-card .symbol:nth-child(5) {
    top: 20%;
    left: 50%;
    animation-delay: 4s;
}

/* Math symbol content variations */
.subject-card:nth-child(7n) .symbol:nth-child(1)::after {
    content: 'K';
    font-size: 18px;
}
.subject-card:nth-child(7n) .symbol:nth-child(2)::after {
    content: '1';
    font-size: 20px;
}
.subject-card:nth-child(7n) .symbol:nth-child(3)::after {
    content: '2';
    font-size: 24px;
}
.subject-card:nth-child(7n) .symbol:nth-child(4)::after {
    content: '3';
    font-size: 20px;
}
.subject-card:nth-child(7n) .symbol:nth-child(5)::after {
    content: '4';
    font-size: 18px;
}

.subject-card:nth-child(7n + 1) .symbol:nth-child(1)::after {
    content: '5';
    font-size: 16px;
}
.subject-card:nth-child(7n + 1) .symbol:nth-child(2)::after {
    content: '6';
    font-size: 16px;
}
.subject-card:nth-child(7n + 1) .symbol:nth-child(3)::after {
    content: 'K1';
    font-size: 14px;
}
.subject-card:nth-child(7n + 1) .symbol:nth-child(4)::after {
    content: 'K2';
    font-size: 16px;
}
.subject-card:nth-child(7n + 1) .symbol:nth-child(5)::after {
    content: 'G1';
    font-size: 16px;
}

@keyframes float-symbol {
    0% {
        transform: translateY(0) translateX(0) rotate(0deg);
        opacity: 0;
    }
    20% {
        opacity: 1;
    }
    80% {
        opacity: 1;
    }
    100% {
        transform: translateY(-100px) translateX(20px) rotate(360deg);
        opacity: 0;
    }
}

.card-content {
    position: relative;
    z-index: 2;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 1.5rem;
}

.subject-title {
    color: #1a365d;
    text-shadow: 0 2px 6px rgba(74, 135, 213, 0.4);
    font-size: 1.75rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 0.5rem;
}

.card-actions {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: flex;
    gap: 0.25rem;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.subject-card:hover .card-actions {
    opacity: 1;
}

/* Empty state */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: #1a365d;
}

:deep(.add-button) {
    border-radius: 8px !important;
    background: linear-gradient(135deg, #4a87d5, #6b9de8) !important;
    border: none !important;
    box-shadow: 0 4px 12px rgba(74, 135, 213, 0.3) !important;
    transition: all 0.3s ease !important;
}

:deep(.add-button:hover) {
    box-shadow: 0 6px 16px rgba(74, 135, 213, 0.5) !important;
    transform: translateY(-2px) !important;
}
</style>

<template>
    <div class="admin-grade-wrapper">
        <!-- Light geometric background shapes -->
        <div class="background-container">
            <div class="geometric-shape circle"></div>
            <div class="geometric-shape square"></div>
            <div class="geometric-shape triangle"></div>
            <div class="geometric-shape rectangle"></div>
            <div class="geometric-shape diamond"></div>
        </div>

        <div class="admin-grade-container">
            <!-- Top Navigation Section -->
            <div class="top-nav-bar">
                <div class="nav-left">
                    <h2 class="text-2xl font-semibold">Curriculum Management</h2>
                </div>
                <div class="search-container">
                    <div class="search-input-wrapper">
                        <i class="pi pi-search search-icon"></i>
                        <input v-model="searchQuery" type="text" class="search-input" placeholder="Search grades..." />
                        <button v-if="searchQuery" class="clear-search-btn" @click="searchQuery = ''">
                            <i class="pi pi-times"></i>
                        </button>
                    </div>
                </div>
                <div class="nav-right">
                    <Button label="Add Grade Level" icon="pi pi-plus" class="add-button p-button-success" @click="openAddGradeDialog" />
                </div>
            </div>

            <!-- Content section -->
            <!-- Loading State -->
            <div v-if="loading" class="loading-container">
                <ProgressSpinner />
                <p>Loading grades...</p>
            </div>

            <!-- Cards Grid -->
            <div v-else class="cards-grid">
                <div v-for="grade in grades" :key="grade.id" class="subject-card" :style="{ background: 'linear-gradient(135deg, rgba(211, 233, 255, 0.9), rgba(233, 244, 255, 0.9))' }">
                    <!-- Floating symbols -->
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>
                    <span class="symbol"></span>

                    <div class="card-content">
                        <h1 class="subject-title">{{ grade.name }}</h1>
                        <div class="card-actions">
                            <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click.stop="editGrade(grade)" />
                            <Button
                                icon="pi pi-users"
                                class="p-button-rounded p-button-text"
                                @click.stop="
                                    openSectionListDialog = true;
                                    selectedGrade = grade;
                                "
                            />
                            <Button
                                icon="pi pi-list"
                                class="p-button-rounded p-button-text"
                                @click.stop="
                                    openSubjectListDialog = true;
                                    selectedGrade = grade;
                                "
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="grades?.length === 0 && !loading" class="empty-state">
                <p>No grades found. Click "Add Grade Level" to create one.</p>
            </div>
        </div>

        <!-- Toast for notifications -->
        <Toast position="top-right" />
        <!-- ... -->
        <ConfirmDialog />

        <!-- Add/Edit Curriculum Dialog -->
        <Dialog v-model:visible="curriculumDialog" :header="curriculum.id ? 'Edit Curriculum' : 'New Curriculum'" modal class="p-fluid curriculum-dialog" :style="{ width: '500px' }">
            <div class="curriculum-form p-4">
                <!-- Name field -->
                <div class="field mb-4">
                    <label for="name" class="font-medium mb-2 block">Curriculum Name</label>
                    <div class="p-inputgroup">
                        <span class="p-inputgroup-addon">
                            <i class="pi pi-book"></i>
                        </span>
                        <InputText id="name" v-model="curriculum.name" readonly class="p-inputtext-lg" />
                    </div>
                    <small class="text-gray-500 mt-1">Curriculum name is automatically generated from the selected years</small>
                </div>

                <!-- Year Range fields -->
                <div class="field mb-4">
                    <label for="yearRange" class="font-medium mb-2 block">School Year</label>
                    <div class="p-inputgroup">
                        <span class="p-inputgroup-addon">
                            <i class="pi pi-calendar"></i>
                        </span>
                        <div class="flex align-items-center gap-2 w-full">
                            <Select v-model="curriculum.yearRange.start" :options="availableStartYears" placeholder="Start Year" :class="{ 'p-invalid': submitted && !curriculum.yearRange.start }" class="flex-1" />
                            <span>-</span>
                            <Select v-model="curriculum.yearRange.end" :options="availableEndYears" placeholder="End Year" :class="{ 'p-invalid': submitted && !curriculum.yearRange.end }" class="flex-1" :disabled="!curriculum.yearRange.start" />
                        </div>
                    </div>
                    <small class="p-error" v-if="submitted && (!curriculum.yearRange.start || !curriculum.yearRange.end)"> Please select both start and end years. </small>
                    <small class="helper-text mt-2">
                        <i class="pi pi-info-circle mr-1"></i>
                        Year ranges must be unique. No two curricula can have the same start and end years.
                    </small>
                </div>

                <!-- Description field -->
                <div class="field mb-4">
                    <label for="description" class="font-medium mb-2 block">Description</label>
                    <div class="p-inputgroup">
                        <span class="p-inputgroup-addon">
                            <i class="pi pi-info-circle"></i>
                        </span>
                        <Textarea id="description" v-model="curriculum.description" rows="3" placeholder="Enter curriculum description (optional)" autoResize class="w-full" />
                    </div>
                </div>

                <!-- Note about active status -->
                <div class="info-box p-3 bg-blue-50 border-left-3 border-blue-500 border-round mb-3">
                    <div class="flex align-items-center gap-2">
                        <i class="pi pi-info-circle text-blue-500"></i>
                        <span class="text-sm">You can toggle the active status directly from the curriculum card on the main page.</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-content-end gap-2">
                    <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="curriculumDialog = false" />
                    <Button label="Save" icon="pi pi-check" class="p-button-primary" :loading="loading" @click="saveCurriculum" />
                </div>
            </template>
        </Dialog>

        <!-- Archive Dialog -->
        <Dialog v-model:visible="archiveDialog" header="Archived Curriculums" modal class="p-fluid" :style="{ width: '650px' }">
            <div v-if="archivedCurriculums.length === 0" class="empty-archive">
                <i class="pi pi-inbox text-4xl text-gray-400"></i>
                <p>No archived curriculums found</p>
            </div>
            <div v-else class="archive-list">
                <div v-for="curr in archivedCurriculums" :key="curr.id" class="archive-item">
                    <div class="archive-item-details">
                        <h4>{{ curr.name }}</h4>
                        <p>{{ curr.yearRange?.start }} - {{ curr.yearRange?.end }}</p>
                    </div>
                    <Button icon="pi pi-refresh" class="p-button-rounded p-button-outlined" @click="restoreCurriculum(curr)" tooltip="Restore" />
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" class="p-button-text" @click="archiveDialog = false" />
            </template>
        </Dialog>

        <!-- Archive Confirmation Dialog -->
        <Dialog v-model:visible="archiveConfirmDialog" header="Confirm Archive" modal class="p-fluid" :style="{ width: '450px' }">
            <div class="confirm-content">
                <i class="pi pi-exclamation-triangle" style="font-size: 2rem"></i>
                <p>Are you sure you want to archive the curriculum "{{ selectedCurriculumToArchive?.name }}"?</p>
            </div>

            <template #footer>
                <Button label="No" icon="pi pi-times" class="p-button-text" @click="archiveConfirmDialog = false" />
                <Button label="Yes" icon="pi pi-check" class="p-button-text" @click="handleArchiveConfirm" />
            </template>
        </Dialog>

        <!-- Grade Level Management Dialog -->
        <Dialog v-model:visible="showGradeLevelManagement" header="Grade Level Management" modal class="p-fluid" :style="{ width: '80vw' }">
            <div v-if="selectedCurriculum" class="grade-management-container">
                <div class="curriculum-info">
                    <h3>{{ selectedCurriculum.name }} ({{ selectedCurriculum.yearRange?.start }} - {{ selectedCurriculum.yearRange?.end }})</h3>
                </div>

                <div class="grade-list-section">
                    <div class="grade-header">
                        <h4>Grade Levels</h4>
                        <Button label="Add Grade" icon="pi pi-plus" class="p-button-sm" @click="openAddGradeDialog" />
                    </div>

                    <div v-if="loading" class="loading-container">
                        <ProgressSpinner />
                    </div>
                    <div v-else-if="grades.length === 0" class="empty-grades">
                        <p>No grade levels assigned to this curriculum.</p>
                    </div>
                    <div v-else class="grade-cards">
                        <div v-for="grade in grades" :key="grade.id" class="grade-card" @click="openSectionList(grade)">
                            <div class="card-content">
                                <h3>{{ grade.name }}</h3>
                                <p v-if="grade.description">{{ grade.description }}</p>
                                <div class="card-actions">
                                    <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click.stop="removeGrade(grade.id)" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" class="p-button-text" @click="showGradeLevelManagement = false" />
            </template>
        </Dialog>

        <!-- Add Grade Dialog -->
        <Dialog v-model:visible="gradeDialog" header="Add Grade Level" modal class="p-fluid" :style="{ width: '450px' }">
            <template v-if="loading">
                <div class="flex justify-content-center">
                    <ProgressSpinner style="width: 50px; height: 50px" />
                </div>
            </template>
            <template v-else>
                <div class="field">
                    <label for="grade">Select Grade</label>
                    <Select
                        id="grade"
                        v-model="selectedGradeToAdd"
                        :options="availableGrades || []"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Select a grade level"
                        :class="{ 'p-invalid': submitted && !selectedGradeToAdd }"
                        :loading="loading"
                        showClear
                    />
                    <small class="p-error" v-if="submitted && !selectedGradeToAdd">Please select a grade level.</small>
                    <small class="p-info" v-if="availableGrades && availableGrades.length === 0">No grades available. Create grades first by clicking "Create New Grade" below.</small>
                </div>

                <div class="flex justify-content-center mt-3">
                    <Button label="Create New Grade" icon="pi pi-plus" class="p-button-outlined" @click="openAddGradeDialog" />
                </div>
            </template>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="gradeDialog = false" />
                <Button label="Add" icon="pi pi-check" class="p-button-primary" @click="saveGrade" />
            </template>
        </Dialog>

        <!-- New Grade Creation Dialog -->
        <Dialog v-model:visible="newGradeDialog" :style="{ width: '450px' }" header="Create New Grade" modal class="p-fluid">
            <div class="field">
                <label for="gradeType" class="font-medium mb-2 block">Grade Type</label>
                <Select 
                    id="gradeType" 
                    v-model="selectedGradeType" 
                    :options="gradeTypes" 
                    optionLabel="label" 
                    optionValue="value"
                    placeholder="Select Grade Type" 
                    class="w-full" 
                    :class="{ 'p-invalid': gradeSubmitted && !selectedGradeType }" 
                />
                <small class="p-error" v-if="gradeSubmitted && !selectedGradeType">Grade type is required.</small>
            </div>

            <div class="field" v-if="selectedGradeType">
                <label :for="selectedGradeType === 'ALS' ? 'alsValue' : 'gradeValue'" class="font-medium mb-2 block">
                    {{ selectedGradeType === 'KINDER' ? 'Kinder' : selectedGradeType === 'GRADE' ? 'Grade' : 'ALS' }} {{ selectedGradeType === 'ALS' ? 'Level' : 'Number' }}
                </label>
                <div v-if="selectedGradeType === 'ALS'">
                    <InputText 
                        id="alsValue" 
                        v-model="gradeValue" 
                        placeholder="Enter ALS level" 
                        :class="{ 'p-invalid': gradeSubmitted && !gradeValue }" 
                        class="w-full"
                    />
                </div>
                <div v-else>
                    <InputNumber 
                        id="gradeValue" 
                        v-model="gradeValue" 
                        :min="1" 
                        :max="selectedGradeType === 'KINDER' ? 2 : 6" 
                        placeholder="Enter number" 
                        :class="{ 'p-invalid': gradeSubmitted && !gradeValue }" 
                        class="w-full"
                    />
                </div>
                <small class="p-error" v-if="gradeSubmitted && !gradeValue">Value is required.</small>
                <small class="text-xs text-gray-500 mt-1" v-if="selectedGradeType === 'KINDER'">Enter 1 or 2 for Kinder level</small>
                <small class="text-xs text-gray-500 mt-1" v-if="selectedGradeType === 'GRADE'">Enter 1-6 for Grade level</small>
            </div>

            <div class="field">
                <label class="font-medium mb-2 block">Generated Code</label>
                <InputText v-model="newGrade.code" disabled class="w-full" />
            </div>

            <div class="field">
                <label class="font-medium mb-2 block">Generated Name</label>
                <InputText v-model="newGrade.name" disabled class="w-full" />
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" outlined @click="newGradeDialog = false" />
                <Button label="Create" icon="pi pi-check" @click="saveNewGrade" :loading="loading" />
            </template>
        </Dialog>

        <!-- Section List Dialog -->
        <Dialog v-model:visible="showSectionListDialog" :header="selectedGrade?.name + ' Sections'" modal class="p-fluid" :style="{ width: '800px' }">
            <div class="flex justify-content-between align-items-center mb-3">
                <h3 class="m-0">Sections</h3>
                <div class="flex gap-2">
                    <Button v-tooltip.top="'Repair Section-Grade Relationships'" icon="pi pi-wrench" class="p-button-outlined p-button-secondary" @click="repairSectionGradeRelationships" :loading="loading" />
                    <Button label="Add Section" icon="pi pi-plus" class="p-button-success" @click="openAddSectionDialog" />
                </div>
            </div>

            <div v-if="loading" class="flex justify-content-center">
                <ProgressSpinner />
            </div>

            <div v-else-if="sections.length === 0" class="text-center p-4">
                <i class="pi pi-exclamation-circle text-5xl text-primary mb-3"></i>
                <p>No sections assigned to this grade level.</p>
                <p>Click "Add Section" to create a section.</p>
            </div>

            <div v-else class="section-grid">
                <div v-for="section in sections" :key="section.id" class="section-card p-3 border-round shadow-2">
                    <div class="flex justify-content-between align-items-start mb-3">
                        <div>
                            <h4 class="m-0 mb-1 text-xl">Section {{ section.name }}</h4>
                            <p v-if="section.capacity" class="mt-0 mb-1">Capacity: {{ section.capacity }} students</p>
                            <p v-if="section.description" class="m-0 text-sm text-500">{{ section.description }}</p>
                        </div>
                        <div class="flex gap-2">
                            <Button icon="pi pi-book" class="p-button-rounded p-button-primary p-button-outlined" @click="openSubjectList(section)" v-tooltip.top="'Manage Subjects'" />
                            <Button v-if="!section.homeroom_teacher_id" icon="pi pi-user" class="p-button-rounded p-button-success p-button-outlined" @click="openHomeRoomTeacherDialog(section)" v-tooltip.top="'Assign Teacher'" />
                            <Button icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-outlined" @click="confirmRemoveSection(section)" v-tooltip.top="'Remove Section'" />
                        </div>
                    </div>
                    <div v-if="section.homeroom_teacher_id" class="teacher-info flex align-items-center gap-2 mt-2">
                        <i class="pi pi-user text-primary"></i>
                        <span>Teacher: {{ getTeacherName(section.homeroom_teacher_id) }}</span>
                    </div>
                    <div v-else class="teacher-info flex align-items-center gap-2 mt-2 text-500">
                        <i class="pi pi-user text-gray-400"></i>
                        <span>No homeroom teacher assigned</span>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="showSectionListDialog = false" class="p-button-text" />
            </template>
        </Dialog>

        <!-- Schedule Dialog -->
        <Teleport to="body">
            <Dialog
                v-model:visible="showScheduleDialog"
                :header="`Set Schedule for ${selectedSubjectForSchedule?.name || 'Subject'}`"
                modal
                class="p-fluid schedule-dialog"
                :style="{ width: '450px' }"
                :closable="true"
                appendTo="body"
                @hide="handleScheduleDialogClose"
            >
                <div class="p-field mb-3">
                    <label for="day" class="font-medium mb-2 block">Day</label>
                    <Select id="day" v-model="schedule.day" :options="dayOptions" optionLabel="label" optionValue="value" placeholder="Select Day" class="w-full" />
                </div>

                <div class="p-field mb-3">
                    <label for="startTime" class="font-medium mb-2 block">Start Time</label>
                    <div class="p-inputgroup">
                        <span class="p-inputgroup-addon">
                            <i class="pi pi-clock"></i>
                        </span>
                        <input type="time" id="startTime" v-model="schedule.start_time" class="p-inputtext w-full" />
                    </div>
                </div>

                <div class="p-field mb-3">
                    <label for="endTime" class="font-medium mb-2 block">End Time</label>
                    <div class="p-inputgroup">
                        <span class="p-inputgroup-addon">
                            <i class="pi pi-clock"></i>
                        </span>
                        <input type="time" id="endTime" v-model="schedule.end_time" class="p-inputtext w-full" />
                    </div>
                </div>

                <div v-if="currentGradeHasSubjectTeachers" class="p-field mb-3">
                    <label for="teacher" class="font-medium mb-2 block">Teacher</label>
                    <Select id="teacher" v-model="schedule.teacher_id" :options="teachers" optionLabel="name" optionValue="id" placeholder="Select Teacher" class="w-full" />
                </div>

                <div class="flex align-items-center p-3 border-round bg-blue-50 mb-3">
                    <i class="pi pi-info-circle text-blue-500 mr-2"></i>
                    <span class="text-sm">Time slots are automatically suggested to avoid conflicts. The system prevents scheduling two subjects at the same time.</span>
                </div>

                <template #footer>
                    <div class="flex justify-content-end gap-2">
                        <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showScheduleDialog = false" />
                        <Button label="Save" icon="pi pi-check" class="p-button-primary" @click="saveSchedule" />
                    </div>
                </template>
            </Dialog>
        </Teleport>

        <!-- Subject List Dialog -->
        <Teleport to="body">
            <Dialog
                v-model:visible="showSubjectListDialog"
                :header="'Subjects for Section ' + (selectedSection?.name || '')"
                modal
                class="p-fluid subject-list-dialog"
                :style="{ width: '800px' }"
                :closable="true"
                @hide="handleCloseSubjectDialog"
                appendTo="body"
            >
                <div class="flex justify-content-between align-items-center mb-3">
                    <h3 class="m-0">Subjects</h3>
                    <div class="flex gap-2">
                        <Button label="Add Subject" icon="pi pi-plus" class="p-button-success" @click="openAddSubjectDialog" />
                        <Button icon="pi pi-refresh" class="p-button-outlined" @click="refreshSectionSubjects" v-tooltip.top="'Refresh Subjects'" />
                    </div>
                </div>

                <div v-if="loading" class="flex justify-content-center">
                    <ProgressSpinner />
                </div>

                <div v-else-if="selectedSubjects.length === 0" class="text-center p-4">
                    <i class="pi pi-exclamation-circle text-5xl text-primary mb-3"></i>
                    <p>No subjects assigned to this section.</p>
                    <p>Click "Add Subject" to add subjects to this section.</p>
                </div>

                <div v-else class="subject-grid">
                    <div v-for="subject in selectedSubjects" :key="subject.id" class="subject-card p-3 border-round shadow-2">
                        <div class="flex justify-content-between align-items-start mb-3">
                            <div>
                                <h4 class="m-0 mb-1 text-xl">{{ subject.name }}</h4>
                                <p v-if="subject.description" class="mt-0 mb-1">{{ subject.description }}</p>
                                <div v-if="currentGradeHasSubjectTeachers">
                                    <div v-if="subject.teacher" class="teacher-display mt-2">
                                        <i class="pi pi-user mr-2"></i>
                                        <span class="teacher-name">{{ subject.teacher.name }}</span>
                                    </div>
                                    <div v-else class="teacher-display mt-2">
                                        <i class="pi pi-user mr-2"></i>
                                        <span class="no-teacher-text">No teacher assigned</span>
                                    </div>
                                </div>
                                <div v-else class="teacher-display mt-2 text-muted">
                                    <i class="pi pi-info-circle mr-2"></i>
                                    <span class="text-sm text-gray-500">No teacher required for this grade level</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button v-if="currentGradeHasSubjectTeachers" icon="pi pi-user" class="p-button-rounded p-button-primary p-button-outlined" @click="openTeacherDialog(subject)" v-tooltip.top="'Assign Teacher'" />
                                <Button icon="pi pi-calendar" class="p-button-rounded p-button-success p-button-outlined" @click="openScheduleDialog(subject)" v-tooltip.top="'Set Schedule'" />
                                <Button icon="pi pi-trash" class="p-button-rounded p-button-danger p-button-outlined" @click="removeSubjectFromSection(subject.id)" v-tooltip.top="'Remove Subject'" />
                            </div>
                        </div>

                        <div v-if="subject.schedules && subject.schedules.length > 0" class="schedule-section">
                            <h5 class="mt-0 mb-2">Schedule</h5>
                            <ul class="schedule-list">
                                <li v-for="(schedule, index) in subject.schedules" :key="index" class="mb-2 p-2 schedule-item flex align-items-center justify-content-between">
                                    <div class="flex align-items-center gap-2">
                                        <span class="schedule-day-badge">{{ schedule.day }}</span>
                                        <span class="schedule-time-badge">{{ schedule.start_time }} - {{ schedule.end_time }}</span>
                                    </div>
                                    <div v-if="currentGradeHasSubjectTeachers && schedule.teacher_id" class="teacher-info">
                                        <i class="pi pi-user mr-1"></i>
                                        <span>{{ getTeacherName(schedule.teacher_id) }}</span>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div v-else class="no-schedules text-center mt-3">
                            <i class="pi pi-calendar-times text-3xl"></i>
                            <p class="m-0">No schedules set</p>
                        </div>
                    </div>
                </div>

                <template #footer>
                    <Button label="Close" icon="pi pi-times" class="p-button-text" @click="closeSubjectListDialog" />
                </template>
            </Dialog>
        </Teleport>

        <!-- Add Subject Dialog (positioned last in the DOM to ensure it's on top) -->
        <Teleport to="body">
            <Dialog v-model:visible="subjectDialog" header="Add Subject" modal class="p-fluid" :style="{ width: '450px', zIndex: 9999 }" :closable="true" appendTo="body">
                <div class="field">
                    <label for="subject">Select Subject</label>
                    <Select id="subject" v-model="selectedSubject" :options="availableSubjects" optionLabel="name" placeholder="Choose a subject" :class="{ 'p-invalid': submitted && !selectedSubject }" />
                    <small class="p-error" v-if="submitted && !selectedSubject">Please select a subject.</small>
                </div>

                <template #footer>
                    <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="closeAddSubjectDialog" />
                    <Button label="Add" icon="pi pi-check" class="p-button-primary" @click="addSubjectToSection(selectedSubject?.id)" />
                </template>
            </Dialog>
        </Teleport>

        <!-- Homeroom Teacher Dialog -->
        <Dialog v-model:visible="homeRoomTeacherAssignmentDialog" :style="{ width: '450px' }" header="Assign Homeroom Teacher" :modal="true" class="p-fluid" :closable="true" @hide="selectedTeacher = null">
            <div v-if="loading" class="flex justify-content-center">
                <ProgressSpinner />
            </div>
            <div v-else class="field">
                <label for="teacher">Select Homeroom Teacher</label>
                <select v-model="selectedTeacher" class="p-inputtext w-full" :class="{ 'p-invalid': teacherSubmitted && !selectedTeacher }">
                    <option value="">Select a teacher</option>
                    <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.first_name }} {{ teacher.last_name }}</option>
                </select>
                <small class="p-error" v-if="teacherSubmitted && !selectedTeacher">Please select a teacher.</small>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="homeRoomTeacherAssignmentDialog = false" :disabled="loading" />
                <Button label="Assign" icon="pi pi-check" class="p-button-text" @click="assignHomeRoomTeacher" :loading="loading" />
            </template>
        </Dialog>

        <!-- Add Section Dialog -->
        <Dialog v-model:visible="sectionDialog" header="Add Section" modal class="p-fluid" :style="{ width: '450px' }">
            <div v-if="loading" class="flex justify-content-center">
                <ProgressSpinner />
            </div>
            <div v-else class="section-form">
                <div class="field">
                    <label for="sectionName">Section Name</label>
                    <InputText id="sectionName" v-model="section.name" required :class="{ 'p-invalid': submitted && !section.name }" />
                    <small class="p-error" v-if="submitted && !section.name">Section name is required.</small>
                </div>

                <div class="field">
                    <label for="capacity">Capacity</label>
                    <InputNumber id="capacity" v-model="section.capacity" :min="1" :max="100" />
                </div>

                <div class="field">
                    <label for="description">Description (Optional)</label>
                    <InputText id="description" v-model="section.description" />
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="sectionDialog = false" />
                <Button label="Save" icon="pi pi-check" class="p-button-primary" @click="saveSection" />
            </template>
        </Dialog>

        <!-- Teacher Assignment Dialog -->
        <Teleport to="body">
            <Dialog v-model:visible="showTeacherAssignmentDialog" header="Assign Subject Teacher" modal class="p-fluid" :style="{ width: '450px', zIndex: 9999 }" :closable="true" appendTo="body" @hide="selectedTeacher = null">
                <div class="field">
                    <label for="subject-name">Subject</label>
                    <div class="p-field-value">{{ selectedSubjectForTeacher?.name }}</div>
                </div>

                <div class="field">
                    <label for="teacher">Select Teacher</label>
                    <select v-model="selectedTeacher" class="p-inputtext w-full" :class="{ 'p-invalid': teacherSubmitted && !selectedTeacher }">
                        <option value="">Select a teacher</option>
                        <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.first_name }} {{ teacher.last_name }}</option>
                    </select>
                    <small class="p-error" v-if="teacherSubmitted && !selectedTeacher">Please select a teacher.</small>
                </div>

                <template #footer>
                    <Button label="Assign" icon="pi pi-check" class="p-button-primary" @click="assignTeacher" :loading="loading" />
                </template>
            </Dialog>
        </Teleport>
    </div>
</template>
<style scoped>
.admin-grade-wrapper {
    position: relative;
    overflow: hidden;
    min-height: 100vh;
    background-color: #e0f2ff;
    border-radius: 0 0 24px 0;
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.1);
}

/* Background container for shapes */
.background-container {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
    opacity: 0.4;
    z-index: 0;
    border-radius: 0 0 24px 0;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

/* Base styles for all geometric shapes */
.geometric-shape {
    position: absolute;
    opacity: 0.2;
    filter: blur(1px);
    z-index: 0;
}

/* Circle shape */
.circle {
    width: 300px;
    height: 300px;
    border-radius: 50%;
    background-color: #4a87d5;
    top: -80px;
    right: -80px;
    animation: float 20s ease-in-out infinite;
}

/* Square shape */
.square {
    width: 250px;
    height: 250px;
    background-color: #ff7eb3;
    bottom: -100px;
    left: -100px;
    transform: rotate(45deg);
    animation: float 25s ease-in-out infinite reverse;
}

/* Triangle shape */
.triangle {
    width: 0;
    height: 0;
    border-left: 150px solid transparent;
    border-right: 150px solid transparent;
    border-bottom: 260px solid #96e6a1;
    top: 200px;
    right: 50px;
    animation: float 30s ease-in-out infinite 5s;
}

/* Rectangle shape */
.rectangle {
    width: 350px;
    height: 180px;
    background-color: #a18cd1;
    top: 300px;
    left: -120px;
    transform: rotate(-20deg);
    animation: float 22s ease-in-out infinite 2s;
}

/* Diamond shape */
.diamond {
    width: 200px;
    height: 200px;
    background-color: #fbc2eb;
    bottom: 150px;
    right: 200px;
    transform: rotate(45deg);
    animation: float 18s ease-in-out infinite 8s;
}

@keyframes float {
    0%,
    100% {
        transform: translateY(0px) rotate(0deg);
    }
    50% {
        transform: translateY(-20px) rotate(5deg);
    }
}

.admin-grade-container {
    position: relative;
    z-index: 2;
    padding: 1.5rem 2.5rem;
    background: rgba(220, 236, 255, 0.85);
    backdrop-filter: blur(10px);
    min-height: 100vh;
    color: #1a365d;
    border-radius: 0 0 24px 0;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05) inset;
    animation: subtle-glow 10s ease-in-out infinite alternate;
}

@keyframes subtle-glow {
    0% {
        box-shadow: 0 0 30px 10px rgba(74, 135, 213, 0.05) inset;
    }
    50% {
        box-shadow: 0 0 40px 15px rgba(107, 157, 232, 0.08) inset;
    }
    100% {
        box-shadow: 0 0 30px 10px rgba(74, 135, 213, 0.05) inset;
    }
}

.top-nav-bar {
    border-bottom: 1px solid rgba(74, 135, 213, 0.2);
    margin-bottom: 2.5rem;
    padding-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.top-nav-bar .nav-left h2 {
    color: #1a365d;
    font-size: 2rem;
    font-weight: 600;
    margin: 0;
    text-shadow: 0 2px 6px rgba(74, 135, 213, 0.3);
    letter-spacing: 0.5px;
}

.nav-right {
    display: flex;
    align-items: center;
}

.search-container {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 0 1.5rem;
}

.search-input-wrapper {
    position: relative;
    width: 100%;
    max-width: 500px;
    background: rgba(211, 233, 255, 0.8);
    border-radius: 10px;
    border: 1px solid rgba(74, 135, 213, 0.3);
    overflow: hidden;
    display: flex;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.search-input-wrapper:focus-within {
    border-color: rgba(74, 135, 213, 0.6);
    box-shadow: 0 6px 16px rgba(74, 135, 213, 0.2);
}

.search-input {
    flex: 1;
    background: transparent;
    border: none;
    height: 42px;
    padding: 0 1rem;
    color: #1a365d;
    font-size: 0.95rem;
    width: 100%;
}

.search-input::placeholder {
    color: rgba(26, 54, 93, 0.6);
}

.search-input:focus {
    outline: none;
}

.search-icon {
    color: rgba(26, 54, 93, 0.6);
    margin-left: 0.5rem;
}

.clear-search-btn {
    background: transparent;
    border: none;
    color: rgba(26, 54, 93, 0.6);
    cursor: pointer;
    margin-right: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.clear-search-btn:hover {
    color: #1a365d;
    background: rgba(74, 135, 213, 0.1);
}

.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: #1a365d;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 2.5rem;
    padding: 0.5rem;
}

.subject-card {
    height: 220px;
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    transition: all 0.4s ease;
    position: relative;
    border: 1px solid rgba(74, 135, 213, 0.3);
}

.subject-card:hover {
    transform: translateY(-8px);
    box-shadow:
        0 15px 30px rgba(0, 0, 0, 0.15),
        0 0 25px rgba(74, 135, 213, 0.4);
    border: 1px solid rgba(74, 135, 213, 0.5);
}

.subject-card .symbol {
    position: absolute;
    color: rgba(26, 54, 93, 0.5);
    font-family: 'Courier New', monospace;
    pointer-events: none;
    z-index: 1;
    animation: float-symbol 8s linear infinite;
    font-weight: bold;
}

.subject-card:nth-child(3n) .symbol {
    animation-duration: 10s;
}

.subject-card:nth-child(3n + 1) .symbol {
    animation-duration: 7s;
}

.subject-card .symbol:nth-child(1) {
    top: 10%;
    left: 10%;
    animation-delay: 0s;
}
.subject-card .symbol:nth-child(2) {
    top: 30%;
    left: 80%;
    animation-delay: 1s;
}
.subject-card .symbol:nth-child(3) {
    top: 70%;
    left: 30%;
    animation-delay: 2s;
}
.subject-card .symbol:nth-child(4) {
    top: 60%;
    left: 70%;
    animation-delay: 3s;
}
.subject-card .symbol:nth-child(5) {
    top: 20%;
    left: 50%;
    animation-delay: 4s;
}

/* Math symbol content variations */
.subject-card:nth-child(7n) .symbol:nth-child(1)::after {
    content: 'K';
    font-size: 18px;
}
.subject-card:nth-child(7n) .symbol:nth-child(2)::after {
    content: '1';
    font-size: 20px;
}
.subject-card:nth-child(7n) .symbol:nth-child(3)::after {
    content: '2';
    font-size: 24px;
}
.subject-card:nth-child(7n) .symbol:nth-child(4)::after {
    content: '3';
    font-size: 20px;
}
.subject-card:nth-child(7n) .symbol:nth-child(5)::after {
    content: '4';
    font-size: 18px;
}

.subject-card:nth-child(7n + 1) .symbol:nth-child(1)::after {
    content: '5';
    font-size: 16px;
}
.subject-card:nth-child(7n + 1) .symbol:nth-child(2)::after {
    content: '6';
    font-size: 16px;
}
.subject-card:nth-child(7n + 1) .symbol:nth-child(3)::after {
    content: 'K1';
    font-size: 14px;
}
.subject-card:nth-child(7n + 1) .symbol:nth-child(4)::after {
    content: 'K2';
    font-size: 16px;
}
.subject-card:nth-child(7n + 1) .symbol:nth-child(5)::after {
    content: 'G1';
    font-size: 16px;
}

@keyframes float-symbol {
    0% {
        transform: translateY(0) translateX(0) rotate(0deg);
        opacity: 0;
    }
    20% {
        opacity: 1;
    }
    80% {
        opacity: 1;
    }
    100% {
        transform: translateY(-100px) translateX(20px) rotate(360deg);
        opacity: 0;
    }
}

.card-content {
    position: relative;
    z-index: 2;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    padding: 1.5rem;
}

.subject-title {
    color: #1a365d;
    text-shadow: 0 2px 6px rgba(74, 135, 213, 0.4);
    font-size: 1.75rem;
    font-weight: 700;
    text-align: center;
    margin-bottom: 0.5rem;
}

.card-actions {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: flex;
    gap: 0.25rem;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.subject-card:hover .card-actions {
    opacity: 1;
}

/* Empty state */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: #1a365d;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.curriculum-card {
    position: relative;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    overflow: hidden;
    cursor: pointer;
    color: white;
    min-height: 180px;
    display: flex;
    flex-direction: column;
}

.curriculum-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
}

.curriculum-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.year-range {
    margin-bottom: 0.75rem;
}

.year {
    background: rgba(255, 255, 255, 0.2);
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
}

.card-content {
    position: relative;
    z-index: 2;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.card-actions {
    margin-top: auto;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}

.empty-state {
    text-align: center;
    padding: 3rem;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    margin-top: 2rem;
}

.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
}

.background-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.geometric-shape {
    position: absolute;
    opacity: 0.1;
}

.circle {
    width: 400px;
    height: 400px;
    border-radius: 50%;
    background: linear-gradient(120deg, #84fab0, #8fd3f4);
    top: -100px;
    left: -100px;
}

.square {
    width: 250px;
    height: 250px;
    background: linear-gradient(120deg, #ff758c, #ff7eb3);
    bottom: 50px;
    right: -50px;
    transform: rotate(20deg);
}

.triangle {
    width: 0;
    height: 0;
    border-left: 200px solid transparent;
    border-right: 200px solid transparent;
    border-bottom: 346px solid #a18cd1;
    top: 30%;
    right: 20%;
    opacity: 0.07;
}

.rectangle {
    width: 400px;
    height: 200px;
    background: linear-gradient(120deg, #fbc2eb, #a6c1ee);
    top: 40%;
    left: -100px;
    transform: rotate(-15deg);
}

.diamond {
    width: 200px;
    height: 200px;
    background: linear-gradient(120deg, #ffd1ff, #fad0c4);
    bottom: 10%;
    left: 35%;
    transform: rotate(45deg);
}

.symbol {
    position: absolute;
    font-size: 1.2rem;
    color: rgba(255, 255, 255, 0.3);
    z-index: 1;
}

.symbol:nth-child(1) {
    top: 10%;
    left: 10%;
    animation: float 6s ease-in-out infinite;
}
.symbol:nth-child(2) {
    top: 40%;
    right: 15%;
    animation: float 7s ease-in-out infinite 1s;
}
.symbol:nth-child(3) {
    bottom: 20%;
    left: 20%;
    animation: float 5s ease-in-out infinite 0.5s;
}
.symbol:nth-child(4) {
    bottom: 40%;
    right: 10%;
    animation: float 8s ease-in-out infinite 1.5s;
}
.symbol:nth-child(5) {
    top: 30%;
    left: 50%;
    animation: float 4s ease-in-out infinite 2s;
}

@keyframes float {
    0% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
    100% {
        transform: translateY(0px);
    }
}

.search-container {
    min-width: 240px;
}

/* Grade Management Dialog Styles */
.grade-management-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.curriculum-info {
    background: rgba(240, 245, 255, 0.7);
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.grade-cards,
.section-cards,
.subject-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}

.grade-card,
.section-card,
.subject-card {
    position: relative;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    overflow: hidden;
    cursor: pointer;
    color: white;
    min-height: 140px;
}

.grade-card:hover,
.section-card:hover,
.subject-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.empty-grades,
.empty-sections,
.empty-subjects {
    text-align: center;
    padding: 2rem;
    background: rgba(240, 245, 255, 0.7);
    border-radius: 8px;
}

/* Year filter badges */
.year-badge {
    background-color: rgba(var(--primary-color-rgb), 0.15);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: 600;
    margin-left: 0.5rem;
}

.year-range {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
    color: var(--text-color-secondary);
}

.clear-year {
    cursor: pointer;
    transition: color 0.2s;
}

.clear-year:hover {
    color: #f44336;
}

/* Schedule and teacher styling */
.schedule-info,
.teacher-info {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

.schedule-list {
    list-style-type: none;
    padding-left: 0.5rem;
    margin-top: 0.25rem;
}

.schedule-list li {
    margin-bottom: 0.25rem;
    padding: 0.25rem 0.5rem;
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
}

.schedule-dialog-content h3,
.teacher-dialog-content h3,
.homeroom-teacher-dialog-content h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #eee;
}

.section-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(240, 245, 255, 0.7);
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.section-info h3 {
    margin: 0;
}

.no-teacher {
    font-style: italic;
    color: rgba(255, 255, 255, 0.7);
}

.subject-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
    padding: 0.5rem;
}

.subject-card {
    border: 1px solid #e9ecef;
    transition:
        transform 0.2s,
        box-shadow 0.2s;
}

.subject-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
}

/* Add these styles at the end of the style section */
.section-info-card {
    background-color: #f7fafc;
    border-left: 4px solid #4caf50;
}

.detail-item {
    margin-bottom: 0.5rem;
    display: flex;
    gap: 0.5rem;
}

.selected-teacher-card {
    background-color: #f0f9ff;
    border-left: 4px solid #2196f3;
}

.teacher-avatar {
    background-color: #e3f2fd;
    width: 50px;
    height: 50px;
    color: #2196f3;
}

.homeroom-teacher-dialog-content {
    max-height: 60vh;
    overflow-y: auto;
    padding-right: 0.5rem;
}

.p-badge {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 4px;
}

.p-badge-info {
    background-color: #e3f2fd;
    color: #0d47a1;
}

/* Add these styles at the end of the style section */
:deep(.curriculum-dialog .p-dialog-content) {
    padding: 0;
}

.curriculum-form {
    background-color: #f8fafc;
    border-radius: 8px;
}

:deep(.curriculum-dialog .p-inputgroup-addon) {
    background-color: #f1f5f9;
    border-color: #e2e8f0;
}

:deep(.curriculum-dialog .p-inputtext:disabled) {
    background-color: #f8fafc;
    color: #475569;
    border-color: #e2e8f0;
    opacity: 0.8;
}

.helper-text {
    color: #64748b;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

/* Add the subject card styles that were previously in the template */
.subject-card {
    background: white;
    transition:
        transform 0.2s,
        box-shadow 0.2s;
    height: 100%;
}

.subject-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.schedule-section {
    border-top: 1px solid var(--surface-200);
    padding-top: 1rem;
    margin-top: 1rem;
}

.schedule-item {
    background: var(--surface-50);
    border: 1px solid var(--surface-200);
    transition: all 0.2s;
    border-radius: 8px;
}

.schedule-item:hover {
    background: var(--surface-100);
    transform: translateX(2px);
}

.schedule-day-badge {
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.875rem;
    font-weight: 600;
    min-width: 90px;
    text-align: center;
}

.schedule-time-badge {
    background: var(--surface-200);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.875rem;
    color: var(--text-color-secondary);
}

.teacher-info {
    background: var(--surface-100);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.875rem;
}

.no-schedules {
    background: var(--surface-50);
    border: 1px dashed var(--surface-300);
    border-radius: 8px;
    padding: 1.5rem;
}

.no-schedules i {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--text-color-secondary);
}

.teacher-display {
    margin: 10px 0;
    padding: 8px;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 6px;
    color: #000;
    font-weight: 500;
    display: flex;
    align-items: center;
}

.teacher-name {
    color: #000;
    font-size: 0.95rem;
}

.no-teacher-text {
    color: #666;
    font-style: italic;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

:deep(.p-dialog.schedule-dialog) {
    z-index: 99999 !important;
    animation: fadeIn 0.2s ease-in-out;
}

:deep(.p-dialog-mask) {
    z-index: auto !important;
}

/* Ensure the teleported dialog is always on top */
body > .p-dialog-mask {
    z-index: 9999 !important;
}

/* Fix dialog stacking */
:deep(.p-dialog.schedule-dialog) {
    z-index: 99999 !important;
}

:deep(.p-dialog-mask.p-component-overlay) {
    background-color: rgba(0, 0, 0, 0.4) !important;
}

/* Make sure the schedule dialog mask is on top */
body > .p-dialog-mask {
    z-index: 99998 !important;
}

/* Special styling for the schedule dialog */
.schedule-dialog {
    max-width: 450px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
}

.schedule-dialog .p-dialog-header {
    background-color: var(--primary-color);
    color: white;
    padding: 1.25rem 1.5rem;
}

.schedule-dialog .p-dialog-title {
    font-weight: 600;
    font-size: 1.2rem;
}

.schedule-dialog .p-dialog-content {
    padding: 1.5rem 1.5rem 1rem 1.5rem;
}

.schedule-dialog .p-dialog-footer {
    padding: 1rem 1.5rem 1.5rem 1.5rem;
    border-top: 1px solid #f0f0f0;
}

.schedule-dialog .p-inputtext:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 1px rgba(var(--primary-color-rgb), 0.2);
}

/* Special styling for dropdown in schedule dialog */
.schedule-dialog .p-dropdown {
    border-radius: 6px;
}

.schedule-dialog .p-dropdown:hover {
    border-color: var(--primary-color);
}

.schedule-dialog .p-dropdown-panel .p-dropdown-items .p-dropdown-item.p-highlight {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--primary-color);
}

/* Custom Schedule Dialog styles */
.custom-schedule-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999999; /* Extremely high z-index */
}

.custom-schedule-dialog {
    background-color: white;
    border-radius: 8px;
    width: 450px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    display: flex;
    flex-direction: column;
    animation: dialogFadeIn 0.2s ease-out;
}

.custom-dialog-header {
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e9ecef;
}

.custom-dialog-header span {
    font-size: 1.25rem;
    font-weight: 600;
    color: #333;
}

.custom-close-button {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6c757d;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.custom-close-button:hover {
    background-color: #f0f0f0;
    color: #333;
}

.custom-dialog-content {
    padding: 1.5rem;
    overflow-y: auto;
    max-height: 60vh;
}

.custom-dialog-footer {
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    border-top: 1px solid #e9ecef;
}

@keyframes dialogFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.helper-message {
    display: flex;
    align-items: center;
    color: #2c3e50;
    background-color: #e3f2fd;
    border: 1px solid #bbdefb;
    padding: 0.75rem;
    border-radius: 5px;
    margin-top: 1rem;
}

.helper-message i {
    font-size: 1.2rem;
    margin-right: 0.5rem;
    color: #1976d2;
}

.curriculum-card {
    background-color: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    cursor: pointer; /* Make it clear the card is clickable */
}

.curriculum-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
}

.curriculum-card.is-active {
    border-left: 4px solid var(--primary-color);
}

.curriculum-card:before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 0 50px 50px 0;
    border-color: transparent #f0f0f0 transparent transparent;
    transition: border-color 0.3s ease;
}

.curriculum-card.is-active:before {
    border-color: transparent var(--primary-color) transparent transparent;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.curriculum-name {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    color: var(--text-color);
}

.year-range {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--text-color-secondary);
    margin-bottom: 1rem;
}

.description {
    color: var(--text-color-secondary);
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

.card-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    margin-top: 1rem;
}

/* Status badge styles */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    background-color: #eee;
}

.status-badge.active {
    background-color: var(--green-100);
    color: var(--green-700);
}

.status-badge.archived {
    background-color: var(--gray-100);
    color: var(--gray-700);
}

/* Active status toggle */
.active-status-toggle {
    margin-top: 1rem;
    padding: 1rem;
    background-color: #f8fafc;
    border-radius: 8px;
    border-left: 3px solid var(--primary-color);
    transition: all 0.3s ease;
}

.active-status-toggle:hover {
    background-color: #f1f5f9;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
}

.toggle-label {
    display: flex;
    flex-direction: column;
}

.active-hint {
    color: var(--green-600);
    margin-top: 0.25rem;
}

.inactive-hint {
    color: var(--text-color-secondary);
    margin-top: 0.25rem;
}

/* Animation for active curriculum */
.curriculum-card.is-active {
    border-left: 4px solid var(--primary-color);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

@keyframes activatePulse {
    0% {
        box-shadow: 0 0 0 0 rgba(var(--primary-color-rgb), 0.7);
    }
    70% {
        box-shadow: 0 0 0 15px rgba(var(--primary-color-rgb), 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(var(--primary-color-rgb), 0);
    }
}

.active-animation {
    animation: activatePulse 2s ease-out 1;
}

/* Empty state styles */
.empty-state {
    text-align: center;
    padding: 3rem;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

.empty-icon {
    font-size: 3rem;
    color: var(--text-color-secondary);
    margin-bottom: 1rem;
}

.empty-state h3 {
    margin: 0 0 0.5rem;
    color: var(--text-color);
}

.empty-state p {
    margin: 0 0 1.5rem;
    color: var(--text-color-secondary);
}

/* Loading styles */
.loading-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;
}

/* Archive styles */
.archive-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.archive-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    background-color: #f9f9f9;
    border-radius: 6px;
}

.archive-item-details h4 {
    margin: 0 0 0.25rem;
}

.archive-item-details p {
    margin: 0;
    color: var(--text-color-secondary);
}

.empty-archive {
    text-align: center;
    padding: 2rem;
    color: var(--text-color-secondary);
}

/* Grade management styles */
.grade-management-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.curriculum-info {
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--surface-border);
}

.curriculum-info h3 {
    margin: 0;
    color: var(--text-color);
}

.grade-list-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.grade-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.grade-header h4 {
    margin: 0;
}

.grade-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.grade-card {
    background-color: white;
    border-radius: 6px;
    padding: 1rem;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease;
    cursor: pointer;
}

.grade-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.empty-grades {
    text-align: center;
    padding: 2rem;
    color: var(--text-color-secondary);
}

/* Customizations for dialog contents */
.p-dialog .p-dialog-header {
    border-bottom: 1px solid var(--surface-border);
}

.p-dialog .p-dialog-footer {
    border-top: 1px solid var(--surface-border);
    padding: 1rem 1.5rem;
}

.curriculum-form .field:last-child {
    margin-bottom: 0;
}

/* Grid layout for cards */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

/* Filter / actions bar */
.filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.filter-section,
.action-section {
    display: flex;
    gap: 0.5rem;
}

.confirm-content {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 0;
}

.confirm-content i {
    color: var(--orange-500);
}

.confirm-content p {
    margin: 0;
}

@media (max-width: 768px) {
    .cards-grid {
        grid-template-columns: 1fr;
    }

    .filter-bar {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }

    .action-section {
        width: 100%;
    }
}

/* Special styling for the schedule dialog */
.schedule-dialog {
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important;
}

/* Time slot and schedule display styles */
.schedule-section {
    margin-top: 1rem;
    border-top: 1px solid #f0f0f0;
    padding-top: 1rem;
}

.schedule-section h5 {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 0.5rem;
}

.schedule-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.schedule-item {
    background-color: #f9f9f9;
    border-radius: 4px;
    padding: 0.5rem;
}

.schedule-day-badge {
    background-color: var(--primary-color);
    color: white;
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 3px;
}

.schedule-time-badge {
    font-size: 0.85rem;
}

.teacher-info {
    font-size: 0.8rem;
    color: #666;
}

.no-schedules {
    color: #999;
    font-size: 0.9rem;
}

.no-schedules i {
    margin-bottom: 0.5rem;
    color: #ccc;
}

.schedule-dialog {
    z-index: 99999 !important;
}

.subject-list-dialog {
    z-index: 9000 !important;
}

/* Add these high-specificity rules to ensure the schedule dialog appears on top */
:deep(.schedule-dialog) {
    z-index: 9999999 !important;
    position: relative !important;
}

:deep(.p-dialog-mask[data-pc-section='mask']) {
    z-index: auto !important;
}

:deep(.schedule-dialog .p-dialog-mask) {
    z-index: 9999998 !important;
}

:deep(.subject-list-dialog) {
    z-index: 9000 !important;
}

/* Force the highest stacking context */
:deep(.schedule-dialog) {
    transform: translateZ(0);
}
</style>
