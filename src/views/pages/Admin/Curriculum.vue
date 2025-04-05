<script setup>
// Define API_URL directly instead of importing
// import { API_URL } from '@/config';
import api from '@/config/axios';
import { CurriculumService } from '@/router/service/CurriculumService';
import { GradeService } from '@/router/service/GradesService';
import { SubjectService } from '@/router/service/Subjects';
import { TeacherService } from '@/router/service/TeacherService';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputSwitch from 'primevue/inputswitch';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Select from 'primevue/select';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';

const toast = useToast();
const confirmDialog = useConfirm();
const curriculums = ref([]);
const loading = ref(true);
const curriculumDialog = ref(false);
const deleteCurriculumDialog = ref(false);
const selectedCurriculum = ref(null);
const archiveDialog = ref(false);
const archiveConfirmDialog = ref(false);
const selectedCurriculumToArchive = ref(null);
const searchYear = ref('');
const submitted = ref(false); // Form validation flag

// Define API_URL directly in the component
// const API_URL = 'http://localhost:8000/api';

// New curriculum form data
const curriculum = ref({
    id: null,
    name: 'Curriculum',
    yearRange: { start: null, end: null },
    description: '',
    status: 'Active',
    is_active: true,
    grade_levels: []
});
const years = ref(['2023', '2024', '2025', '2026', '2027', '2028', '2029', '2030']);
const availableStartYears = computed(() => {
    const currentYear = new Date().getFullYear();
    const years = [];
    for (let year = currentYear - 1; year <= currentYear + 5; year++) {
        years.push(year.toString());
    }
    return years;
});

const availableEndYears = computed(() => {
    if (!curriculum.value.yearRange.start) return [];
    const startYear = parseInt(curriculum.value.yearRange.start);
    const years = [];
    for (let year = startYear + 1; year <= startYear + 4; year++) {
        years.push(year.toString());
    }
    return years;
});

// Grade level management
const showGradeLevelManagement = ref(false);
const grades = ref([]);
const selectedGrade = ref(null);
const gradeDialog = ref(false);
const availableGrades = ref([]);
const selectedGradeToAdd = ref(null);
const grade = ref({
    id: '',
    name: '',
    code: '',
    display_order: 0
});

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
const teachers = ref([
    { id: 1, name: 'Maria Santos Reyes', department: 'Mathematics' },
    { id: 2, name: 'Jose Cruz Mendoza', department: 'Science' },
    { id: 3, name: 'Carmela Bautista Lim', department: 'Filipino' },
    { id: 4, name: 'Antonio dela Cruz', department: 'Social Studies' },
    { id: 5, name: 'Rosario Fernandez', department: 'English' }
]);
const teacherDialog = ref(false);
const selectedTeacher = ref(null);
const homeRoomTeacherAssignmentDialog = ref(false);
const subjectTeacherAssignmentDialog = ref(false);

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

    // Make sure status is properly set
    if (!curriculumCopy.status && (curriculumCopy.is_active === true || curriculumCopy.is_active === 1)) {
        console.log(`Setting status to Active for curriculum ${curriculumCopy.id} based on is_active=${curriculumCopy.is_active}`);
        curriculumCopy.status = 'Active';
    } else if (!curriculumCopy.status) {
        // Default to Draft if no status is provided and not active
        curriculumCopy.status = 'Draft';
    }

    return curriculumCopy;
};

// Filter available years for search dropdown
const availableYears = computed(() => {
    const years = new Set();
    curriculums.value.forEach((curr) => {
        if (curr && curr.yearRange) {
            if (curr.yearRange.start) years.add(curr.yearRange.start);
            if (curr.yearRange.end) years.add(curr.yearRange.end);
        }
    });
    return Array.from(years).sort();
});

// Add right after the availableYears computed property

// Computed property to determine if the warning message should be hidden
const hideWarning = computed(() => {
    return !!selectedGradeToAdd.value;
});

// Filter active curriculums
const filteredCurriculums = computed(() => {
    let filtered = curriculums.value.filter((c) => c); // Ensure curriculum exists

    // Filter by year if searchYear is set
    if (searchYear.value) {
        filtered = filtered.filter((c) => c.yearRange && (c.yearRange.start === searchYear.value || c.yearRange.end === searchYear.value || `${c.yearRange.start}-${c.yearRange.end}` === searchYear.value));
    }

    // Check for active status using either the status field or is_active field
    filtered = filtered.filter((c) => {
        // Check if the status field is explicitly set to 'Active'
        const activeByStatus = c.status === 'Active';

        // Check if the is_active field is true (as a fallback)
        const activeByFlag = c.is_active === true || c.is_active === 1;

        // Debug status check
        console.log(`Curriculum ${c.id} (${c.name}): status=${c.status}, is_active=${c.is_active}, active=${activeByStatus || activeByFlag}`);

        // Show curriculum if either condition is met
        return activeByStatus || activeByFlag;
    });

    return filtered;
});

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

// Reset search filter
const clearSearch = () => {
    searchYear.value = '';
};

// Function to filter curriculums when year filter changes
const filterCurriculums = () => {
    console.log('Filtering by year:', searchYear.value);
    // The actual filtering is done by the filteredCurriculums computed property
};

// Handle year range selection in curriculum form
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
    console.log('Direct database endpoints are not available on this server');
    return false;
};

// Add a cleanup function for the local storage when we know the backend is working again
const clearLocalData = (sectionId = null) => {
    try {
        if (sectionId) {
            // Clear specific section data
            const key = `section_subjects_${sectionId}`;
            localStorage.removeItem(key);
            console.log(`Cleared local data for section ${sectionId}`);
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
            console.log(`Cleared all local section data (${keys.length} items)`);
        }
    } catch (error) {
        console.warn('Error clearing local data:', error);
    }
};

// Extend onMounted to load all necessary data
onMounted(async () => {
    try {
        console.log('Component mounted, loading data...');
        loading.value = true;

        // First load curriculums
        await loadCurriculums();
        console.log('Curriculums loaded:', curriculums.value);

        // Then load grades
        await loadGrades();
        console.log('Grades loaded:', grades.value);

        // Load subjects
        await loadSubjects();
        console.log('Subjects loaded:', subjects.value);

        // Load teachers
        await loadTeachers();
        console.log('Teachers loaded:', teachers.value);

        // If we have a selected curriculum and grade, load their sections
        if (selectedCurriculum.value?.id && selectedGrade.value?.id) {
            console.log('Loading sections for curriculum:', selectedCurriculum.value.id, 'grade:', selectedGrade.value.id);

            try {
                const loadedSections = await CurriculumService.getSectionsByGrade(selectedCurriculum.value.id, selectedGrade.value.id);

                if (Array.isArray(loadedSections)) {
                    sections.value = loadedSections;
                    console.log('Sections loaded:', sections.value);

                    // For each section, load its subjects and homeroom teacher
                    await Promise.all(
                        sections.value.map(async (section) => {
                            try {
                                // Load subjects for this section
                                const sectionSubjects = await CurriculumService.getSubjectsBySection(selectedCurriculum.value.id, selectedGrade.value.id, section.id);

                                if (Array.isArray(sectionSubjects)) {
                                    section.subjects = sectionSubjects;
                                }

                                // If there's a homeroom teacher, ensure it's loaded
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

        console.log('Initial data loading complete');
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

// Add watch for curriculum and grade changes to reload sections
watch([() => selectedCurriculum.value, () => selectedGrade.value], async ([newCurriculum, newGrade], [oldCurriculum, oldGrade]) => {
    if (!newCurriculum?.id || !newGrade?.id) {
        sections.value = [];
        return;
    }

    if (newCurriculum?.id === oldCurriculum?.id && newGrade?.id === oldGrade?.id) {
        return; // No change in selection
    }

    try {
        loading.value = true;
        console.log('Selection changed, reloading sections...');

        const loadedSections = await CurriculumService.getSectionsByGrade(newCurriculum.id, newGrade.id);

        if (Array.isArray(loadedSections)) {
            sections.value = loadedSections;
            console.log('Sections reloaded:', sections.value);

            // Load subjects and homeroom teachers for each section
            await Promise.all(
                sections.value.map(async (section) => {
                    try {
                        // Load subjects
                        const sectionSubjects = await CurriculumService.getSubjectsBySection(newCurriculum.id, newGrade.id, section.id);

                        if (Array.isArray(sectionSubjects)) {
                            section.subjects = sectionSubjects;
                        }

                        // Load homeroom teacher if assigned
                        if (section.homeroom_teacher_id) {
                            const teacher = teachers.value.find((t) => t.id === section.homeroom_teacher_id);
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
});

// Main data loading functions
const loadCurriculums = async () => {
    try {
        loading.value = true;
        console.log('Calling CurriculumService.getCurriculums()...');
        const response = await CurriculumService.getCurriculums();
        console.log('Raw response from API:', response);

        // Make sure we have an array and normalize each curriculum
        if (Array.isArray(response)) {
            curriculums.value = response.map((curriculum) => normalizeYearRange(curriculum));
            console.log('Normalized curriculums:', curriculums.value);

            // Log filtered curriculums
            console.log('Filtered curriculums:', filteredCurriculums.value);

            // If none are displayed but we have data, check why they're filtered out
            if (curriculums.value.length > 0 && filteredCurriculums.value.length === 0) {
                console.warn('Curriculums are filtered out. Check status values:');
                curriculums.value.forEach((curr) => {
                    console.log(`Curriculum ID ${curr.id}, Name: ${curr.name}, Status: ${curr.status}, Active: ${curr.is_active}`);

                    // Auto-fix status if it's not set properly but is_active is true
                    if (!curr.status && curr.is_active) {
                        console.log(`Auto-fixing status for curriculum ID ${curr.id}`);
                        curr.status = 'Active';
                    }
                });

                // Check filtered curriculums again after fixes
                console.log('Filtered curriculums after fixes:', filteredCurriculums.value);
            }
        } else {
            console.error('API did not return an array:', response);
            curriculums.value = [];
        }
    } catch (error) {
        console.error('Error loading curriculums:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load curriculums: ' + (error.message || 'Unknown error'),
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

async function loadGrades() {
    let retryCount = 0;
    const maxRetries = 3;

    while (retryCount < maxRetries) {
        try {
            const data = await GradeService.getGrades();
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
                const defaultGrades = await GradeService.getDefaultGrades();
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
    if (!selectedCurriculum.value?.id) {
        console.error('Cannot load grades: no curriculum selected');
        return;
    }

    console.log('Loading grade levels for curriculum ID:', selectedCurriculum.value.id);
    loading.value = true;

    try {
        // Only get curriculum-specific grades - no fallbacks
        const data = await CurriculumService.getGradesByCurriculum(selectedCurriculum.value.id);
            console.log('Grade levels loaded from API:', data);

        // Ensure we have a valid array
        grades.value = Array.isArray(data) ? data : [];

        // If no grades are linked to this curriculum, show empty list (no fallbacks)
        if (grades.value.length === 0) {
            console.log('No grades are linked to this curriculum');
        }

        console.log('Final grade levels:', grades.value);
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
        const data = await TeacherService.getTeachers();
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
            console.log(`Successfully loaded ${teachers.value.length} teachers`);
        }
    } catch (error) {
        console.error('Error loading teachers:', error);
        toast.add({
            severity: 'error',
            summary: 'Database Error',
            detail: 'Failed to load teachers from database',
            life: 5000
        });
        teachers.value = [];
    }
}

// Add this function after loadGrades
const loadAllGrades = async () => {
    try {
        loading.value = true;
        const response = await GradeService.getGrades();
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
    } finally {
        loading.value = false;
    }
};

// Curriculum CRUD operations
const openNew = () => {
    curriculum.value = {
        id: null,
        name: 'Curriculum',
        yearRange: { start: null, end: null },
        description: '',
        status: 'Active',
        is_active: true,
        grade_levels: []
    };
    submitted.value = false;
    curriculumDialog.value = true;
};

const editCurriculum = (curr) => {
    curriculum.value = normalizeYearRange({ ...curr });
    curriculumDialog.value = true;
};

const saveCurriculum = async () => {
    try {
        loading.value = true;
        submitted.value = true;

        // Validation
        if (!curriculum.value.name || !curriculum.value.yearRange?.start || !curriculum.value.yearRange?.end) {
            toast.add({ severity: 'error', summary: 'Error', detail: 'Please fill in all required fields', life: 3000 });
            loading.value = false;
            return;
        }

        // Prepare data for saving
        const saveData = {
            ...curriculum.value,
            start_year: curriculum.value.yearRange?.start,
            end_year: curriculum.value.yearRange?.end
        };

        let result;
        if (curriculum.value.id) {
            // Update existing curriculum
            result = await CurriculumService.updateCurriculum(curriculum.value.id, saveData);
        } else {
            // Create new curriculum
            result = await CurriculumService.createCurriculum(saveData);
        }

        curriculumDialog.value = false;
        await loadCurriculums();

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: curriculum.value.id ? 'Curriculum Updated' : 'Curriculum Created',
            life: 3000
        });
    } catch (error) {
        console.error('Error saving curriculum:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save curriculum: ' + (error.message || 'Unknown error'),
            life: 3000
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

const activateCurriculum = async (curr) => {
    try {
        await CurriculumService.activateCurriculum(curr.id);
        await loadCurriculums();

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Curriculum activated successfully',
            life: 3000
        });
    } catch (error) {
        console.error('Error activating curriculum:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to activate curriculum: ' + (error.message || 'Unknown error'),
            life: 3000
        });
    }
};

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

        // Load all available grades
        await loadAllGrades();

        // If we have grades in the curriculum, filter out those that are already added
        if (grades.value && grades.value.length > 0) {
            const existingGradeIds = grades.value.map((g) => g.id);
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

        // Prepare data for API
        const gradeData = {
            grade_id: selectedGradeToAdd.value.id,
            curriculum_id: selectedCurriculum.value.id
        };

        console.log('Adding grade to curriculum:', selectedCurriculum.value.id, gradeData);
        await CurriculumService.addGradeToCurriculum(selectedCurriculum.value.id, gradeData);

        // Reload grades after successful addition
        await loadGradeLevels();

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

const removeGrade = async (gradeId) => {
    try {
        loading.value = true;
        console.log('Removing grade from curriculum:', selectedCurriculum.value.id, gradeId);

        await CurriculumService.removeGradeFromCurriculum(selectedCurriculum.value.id, gradeId);

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
            validSections = sectionsForGrade.filter(section => {
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
                            const sectionSubjects = await CurriculumService.getSubjectsBySection(
                                selectedCurriculum.value.id,
                                selectedGrade.value.id,
                                section.id
                            );

                            if (Array.isArray(sectionSubjects) && sectionSubjects.length > 0) {
                                section.subjects = sectionSubjects;
                                console.log(`Loaded ${sectionSubjects.length} subjects for section ${section.id}`);
                            } else {
                                console.log(`No subjects found for section ${section.id}`);
                                section.subjects = [];
                            }
                        }

                        // Load teacher if needed
                        if (section.homeroom_teacher_id && !section.teacher) {
                            const teacher = teachers.value.find((t) => t.id === section.homeroom_teacher_id);
                            if (teacher) {
                                section.teacher = teacher;
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
        const response = await CurriculumService.getSubjectsBySection(
            selectedCurriculum.value.id,
            selectedGrade.value.id,
            selectedSection.value.id
        );

        if (Array.isArray(response)) {
            console.log('Successfully refreshed subjects:', response.length);
            console.log('Subject names:', response.map(s => s.name).join(', '));

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
            is_active: true,
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
            is_active: section.value.is_active !== undefined ? section.value.is_active : true,
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
            await CurriculumService.addSectionToGrade(
                selectedCurriculum.value.id,
                selectedGrade.value.id,
                sectionData
            );
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
            const sectionsForGrade = await CurriculumService.getSectionsByGrade(
                selectedCurriculum.value.id,
                selectedGrade.value.id
            );

            if (Array.isArray(sectionsForGrade)) {
                sections.value = sectionsForGrade;
                console.log('Reloaded sections, count:', sections.value.length);

                // If we got a 500 error but find our section in the list, then it was created
                if (!sectionCreated) {
                    const sectionExists = sectionsForGrade.some(s => s.name === sectionData.name);
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
            console.log('Cleared cache for section:', section.id);
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
                console.log('Subject names:', response.data.map(s => s.name).join(', '));

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

    // Only hide the dialog initially
    showSubjectListDialog.value = false;

    // Check if schedule dialog is visible
    if (!showScheduleDialog.value) {
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
        console.log('Schedule dialog is open, preserving section/subject context');
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
                CurriculumService.getSubjectsBySection(
                    selectedCurriculum.value.id,
                    selectedGrade.value.id,
                    storedSection.id
                ).then(subjects => {
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
                }).catch(error => {
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

// Update the saveSchedule function to not reopen the subject list dialog
const saveSchedule = async () => {
    try {
        if (!selectedSubjectForSchedule.value) {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'No subject selected',
                life: 3000
            });
            return;
        }

        if (!schedule.value.day || !schedule.value.start_time || !schedule.value.end_time) {
            toast.add({
                severity: 'warn',
                summary: 'Warning',
                detail: 'Please fill in all schedule fields',
                life: 3000
            });
            return;
        }

        // Check if we have a valid section_id directly in the schedule object
        if (!schedule.value.section_id) {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'No section selected. Please try again.',
                life: 3000
            });
            loading.value = false;
            return;
        }

        loading.value = true;

        // Validate time format
        if (schedule.value.start_time >= schedule.value.end_time) {
            toast.add({
                severity: 'error',
                summary: 'Invalid Time Range',
                detail: 'Start time must be before end time',
                life: 3000
            });
            loading.value = false;
            return;
        }

        try {
            // Format the schedule data using the stored section_id
            const scheduleData = {
                day: schedule.value.day,
                start_time: schedule.value.start_time,
                end_time: schedule.value.end_time,
                teacher_id: currentGradeHasSubjectTeachers ? schedule.value.teacher_id : null,
                subject_id: selectedSubjectForSchedule.value.id,
                section_id: schedule.value.section_id
            };

            console.log('Saving schedule with data:', scheduleData);

            // Get curriculum and grade IDs from selected values
            const curriculumId = selectedCurriculum.value.id;
            const gradeId = selectedGrade.value.id;
            const sectionId = schedule.value.section_id;

            // Call the API using the correct method name
            await CurriculumService.setSubjectSchedule(
                curriculumId,
                gradeId,
                sectionId,
                selectedSubjectForSchedule.value.id,
                scheduleData
            );

            // Initialize the schedules array if it doesn't exist
            if (!selectedSubjectForSchedule.value.schedules) {
                selectedSubjectForSchedule.value.schedules = [];
            }

            // Add the schedule to the subject
            selectedSubjectForSchedule.value.schedules.push(scheduleData);

            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Schedule added successfully',
                life: 3000
            });

            // Close the schedule dialog
            showScheduleDialog.value = false;

            // Refresh the subjects list to show the updated schedules
            refreshSectionSubjects();
        } catch (error) {
            console.error('Error adding schedule:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to add schedule: ' + (error.message || 'Unknown error'),
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error in saveSchedule:', error);
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
            await CurriculumService.assignTeacherToSubject(
                selectedCurriculum.value.id,
                selectedGrade.value.id,
                selectedSection.value.id,
                selectedSubjectForTeacher.value.id,
                { teacher_id: selectedTeacher.value.id }
            );

            // Update the subject with the teacher in the local data
            selectedSubjectForTeacher.value.teacher = selectedTeacher.value;
            selectedSubjectForTeacher.value.teacher_id = selectedTeacher.value.id;

            // Update the subject in the selectedSubjects array
            const subjectIndex = selectedSubjects.value.findIndex(s => s.id === selectedSubjectForTeacher.value.id);
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

// Add this ref for teacher form validation
const teacherSubmitted = ref(false);

// Update the assignHomeRoomTeacher function
const assignHomeRoomTeacher = async () => {
    try {
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

        // Make API call to assign teacher
        await CurriculumService.assignTeacherToSection(selectedCurriculum.value.id, selectedGrade.value.id, selectedSection.value.id, { teacher_id: selectedTeacher.value.id });

        // Update local state
        const updatedSection = {
            ...selectedSection.value,
            teacher_id: selectedTeacher.value.id,
            teacher: selectedTeacher.value
        };

        // Update sections array
        const sectionIndex = sections.value.findIndex((s) => s.id === selectedSection.value.id);
        if (sectionIndex !== -1) {
            sections.value[sectionIndex] = updatedSection;
        }

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Homeroom teacher assigned successfully',
            life: 3000
        });

        // Reset state and close dialog
        selectedTeacher.value = null;
        selectedSection.value = null;
        homeRoomTeacherAssignmentDialog.value = false;
    } catch (error) {
        console.error('Error assigning homeroom teacher:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to assign homeroom teacher',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Update the openAssignHomeRoomTeacherDialog function
const openAssignHomeRoomTeacherDialog = async (sectionData) => {
    try {
        loading.value = true;
        selectedSection.value = { ...sectionData }; // Create a copy to avoid reference issues
        section.value = { ...sectionData };
        selectedTeacher.value = null;

        // Load teachers if not already loaded
        if (!teachers.value || teachers.value.length === 0) {
            const response = await TeacherService.getTeachers();
            if (Array.isArray(response)) {
                teachers.value = response;
            } else {
                console.warn('Invalid teacher data format:', response);
                teachers.value = [];
            }
        }

        homeRoomTeacherAssignmentDialog.value = true;
    } catch (error) {
        console.error('Error in openAssignHomeRoomTeacherDialog:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load teachers',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Add the openScheduleDialog function after closeSubjectListDialog
const openScheduleDialog = async (subject) => {
    try {
        // Store the subject reference (without hiding the subject dialog)
        selectedSubjectForSchedule.value = subject;

        console.log('Opening schedule dialog for subject:', subject.name);

        // Initialize schedule with default values
        schedule.value = {
            day: 'Monday',
            start_time: '08:00',
            end_time: '09:00',
            subject_id: subject.id,
            teacher_id: currentGradeHasSubjectTeachers ? (subject.teacher?.id || null) : null,
            section_id: selectedSection.value.id
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

        // Show the custom schedule dialog
        showScheduleDialog.value = true;
    } catch (error) {
        console.error('Error in openScheduleDialog:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to open schedule dialog',
            life: 3000
        });
    }
};

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
            loading.value = false;
        }
    }

    // If the subject already has a teacher assigned, pre-select it
    if (subject.teacher_id) {
        const existingTeacher = teachers.value.find(t => t.id === subject.teacher_id);
        if (existingTeacher) {
            selectedTeacher.value = existingTeacher;
            console.log('Pre-selected existing teacher:', existingTeacher);
        }
    }
};

// Add a helper function to get teacher name from ID
const getTeacherName = (teacherId) => {
    if (!teacherId) return 'No teacher selected';

    if (typeof teacherId === 'object' && teacherId.name) {
        return teacherId.name;
    }

    const teacher = teachers.value.find((t) => t.id === teacherId);
    return teacher ? teacher.name : `Teacher ${teacherId}`;
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
            await CurriculumService.removeSubjectFromSection(
                selectedCurriculum.value.id,
                selectedGrade.value.id,
                selectedSection.value.id,
                subjectId
            );

            console.log('Successfully removed subject from section');

            // Update the UI by removing the subject from the list
            selectedSubjects.value = selectedSubjects.value.filter(s => s.id !== subjectId);

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
                selectedSubjects.value = selectedSubjects.value.filter(s => s.id !== subjectId);

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
            await CurriculumService.addSubjectToSection(
                selectedCurriculum.value.id,
                selectedGrade.value.id,
                selectedSection.value.id,
                subjectData
            );

            console.log('Successfully added subject to section');

            // Get the subject details
            const subject = subjects.value.find(s => s.id === actualSubjectId);

            // Add the subject to the local list if it's not already there
            if (subject && !selectedSubjects.value.some(s => s.id === actualSubjectId)) {
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
                const subject = subjects.value.find(s => s.id === actualSubjectId);

                // Add the subject to the local list if it's not already there
                if (subject && !selectedSubjects.value.some(s => s.id === actualSubjectId)) {
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
</script>
<template>
    <div class="curriculum-wrapper">
        <!-- Light geometric background shapes -->
        <div class="background-container">
            <div class="geometric-shape circle"></div>
            <div class="geometric-shape square"></div>
            <div class="geometric-shape triangle"></div>
            <div class="geometric-shape rectangle"></div>
            <div class="geometric-shape diamond"></div>
        </div>

        <div class="curriculum-container">
            <!-- Top Navigation Section -->
            <div class="top-nav-bar">
                <div class="nav-left">
                    <h2 class="text-2xl font-semibold">Curriculum Management</h2>
                </div>
                <div class="search-container">
                    <Select v-model="searchYear" :options="availableYears || []" placeholder="Filter by Year" class="year-filter" @change="filterCurriculums">
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="year-badge">
                                <span>{{ slotProps.value }}</span>
                                <i class="pi pi-times clear-year" @click.stop="clearSearch"></i>
                            </div>
                            <span v-else>Filter by Year</span>
                        </template>
                    </Select>
                </div>
                <div class="nav-right">
                    <Button label="Add Curriculum" icon="pi pi-plus" class="add-button p-button-success" @click="openNew" />
                    <Button label="Archive" icon="pi pi-archive" class="p-button-secondary" @click="openArchiveDialog" />
                </div>
            </div>

            <!-- Content Grid -->
            <div v-if="!loading" class="cards-grid">
                <!-- Active Curriculums -->
                <div v-for="curr in filteredCurriculums" :key="curr.id" class="curriculum-card" :class="{ 'is-active': curr.is_active }">
                    <div class="card-header">
                        <h3 class="curriculum-name">{{ curr.name }}</h3>
                        <div class="status-badge" :class="{ active: curr.status === 'Active', archived: curr.status === 'Archived' }">
                            {{ curr.status }}
                        </div>
                    </div>
                    <div class="year-range">
                        <i class="pi pi-calendar"></i>
                        <span>{{ curr.yearRange?.start }} - {{ curr.yearRange?.end }}</span>
                    </div>
                    <p v-if="curr.description" class="description">{{ curr.description }}</p>
                    <div class="card-actions">
                        <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click="editCurriculum(curr)" />
                        <Button v-if="curr.status === 'Active'" icon="pi pi-list" class="p-button-rounded p-button-text" @click="openGradeLevelManagement(curr)" tooltip="Manage Grade Levels" />
                        <Button v-if="curr.status === 'Archived'" icon="pi pi-refresh" class="p-button-rounded p-button-text" @click="restoreCurriculum(curr)" tooltip="Restore" />
                        <Button v-if="curr.status === 'Active' && !curr.is_active" icon="pi pi-check" class="p-button-rounded p-button-text p-button-success" @click="activateCurriculum(curr)" tooltip="Activate" />
                        <Button v-if="curr.status === 'Active'" icon="pi pi-inbox" class="p-button-rounded p-button-text p-button-secondary" @click="openArchiveConfirmation(curr)" tooltip="Archive" />
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="filteredCurriculums.length === 0" class="empty-state">
                    <div class="empty-icon">
                        <i class="pi pi-book"></i>
                    </div>
                    <h3>No Curriculums Found</h3>
                    <p>Add a new curriculum to get started</p>
                    <Button label="Add Curriculum" icon="pi pi-plus" @click="openNew" />
                </div>
            </div>

            <!-- Loading Spinner -->
            <div v-else class="loading-container">
                <ProgressSpinner />
            </div>
        </div>

        <!-- Toast for notifications -->
        <Toast position="top-right" />
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
                        <InputText id="name" v-model="curriculum.name" disabled class="p-inputtext-lg" />
                    </div>
                    <small class="text-gray-500 mt-1">Curriculum name is automatically set</small>
                </div>

                <!-- Year Range field -->
                <div class="field mb-4">
                    <label class="font-medium mb-2 block">Academic Year Range</label>
                    <div class="grid">
                        <div class="col-6">
                            <label for="startYear" class="block text-sm mb-1">Start Year</label>
                            <Select
                                id="startYear"
                                v-model="curriculum.yearRange.start"
                                :options="availableStartYears"
                                placeholder="Select Start Year"
                                class="w-full"
                                :class="{ 'p-invalid': submitted && !curriculum.yearRange?.start }"
                                @change="handleStartYearChange"
                            />
                        </div>
                        <div class="col-6">
                            <label for="endYear" class="block text-sm mb-1">End Year</label>
                            <Select
                                id="endYear"
                                v-model="curriculum.yearRange.end"
                                :options="availableEndYears"
                                placeholder="Select End Year"
                                class="w-full"
                                :class="{ 'p-invalid': submitted && !curriculum.yearRange?.end }"
                                :disabled="!curriculum.yearRange.start"
                            />
                        </div>
                    </div>
                    <small class="p-error block mt-1" v-if="submitted && (!curriculum.yearRange?.start || !curriculum.yearRange?.end)"> Please select both start and end years </small>
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

                <!-- Active Status -->
                <div class="field-checkbox">
                    <div class="flex align-items-center gap-2 mb-1">
                        <ToggleSwitch v-model="curriculum.is_active" />
                        <label for="is_active" class="font-medium">Active Status</label>
                    </div>
                    <small class="text-gray-500">Toggle to set curriculum status as active or inactive</small>
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
                    <Select id="grade" v-model="selectedGradeToAdd" :options="availableGrades || []" optionLabel="name" placeholder="Select a grade level" :class="{ 'p-invalid': submitted && !selectedGradeToAdd }" />
                    <small class="p-error" v-if="submitted && !selectedGradeToAdd">Please select a grade level.</small>
                </div>
            </template>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="gradeDialog = false" />
                <Button label="Add" icon="pi pi-check" class="p-button-primary" @click="saveGrade" />
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

        <!-- Schedule Dialog - completely custom implementation -->
        <div v-if="showScheduleDialog" class="custom-schedule-overlay">
            <div class="custom-schedule-dialog">
                <div class="custom-dialog-header">
                    <span>Set Schedule for {{ selectedSubjectForSchedule?.name || 'Subject' }}</span>
                    <button class="custom-close-button" @click="showScheduleDialog = false">&times;</button>
                </div>
                <div class="custom-dialog-content">
            <div class="field">
                        <label for="day">Day</label>
                        <Select id="day" v-model="schedule.day" :options="dayOptions" optionLabel="label" optionValue="value" placeholder="Select Day" />
            </div>

            <div class="field">
                        <label for="startTime">Start Time</label>
                        <input type="time" id="startTime" v-model="schedule.start_time" class="p-inputtext w-full" />
            </div>

            <div class="field">
                        <label for="endTime">End Time</label>
                        <input type="time" id="endTime" v-model="schedule.end_time" class="p-inputtext w-full" />
            </div>

                    <div v-if="currentGradeHasSubjectTeachers" class="field">
                        <label for="teacher">Teacher</label>
                        <Select id="teacher" v-model="schedule.teacher_id" :options="teachers" optionLabel="name" optionValue="id" placeholder="Select Teacher" />
                    </div>
                </div>
                <div class="custom-dialog-footer">
                    <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showScheduleDialog = false" />
                    <Button label="Save" icon="pi pi-check" class="p-button-primary" @click="saveSchedule" />
                </div>
            </div>
        </div>

        <!-- Subject List Dialog -->
        <Dialog v-model:visible="showSubjectListDialog"
            :header="'Subjects for Section ' + (selectedSection?.name || '')"
            modal
            class="p-fluid"
            :style="{ width: '800px' }"
            :closable="true"
            @hide="closeSubjectListDialog"
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
                            <Button v-if="currentGradeHasSubjectTeachers"
                                   icon="pi pi-user"
                                   class="p-button-rounded p-button-primary p-button-outlined"
                                   @click="openTeacherDialog(subject)"
                                   v-tooltip.top="'Assign Teacher'" />
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

        <!-- Add Subject Dialog (positioned last in the DOM to ensure it's on top) -->
        <Teleport to="body">
            <Dialog v-model:visible="subjectDialog" header="Add Subject" modal class="p-fluid"
                :style="{ width: '450px', zIndex: 9999 }"
                :closable="true"
                appendTo="body"
            >
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
            <div class="field">
                <label for="teacher">Select Homeroom Teacher</label>
                <select v-model="selectedTeacher" class="p-inputtext w-full" :class="{ 'p-invalid': teacherSubmitted && !selectedTeacher }">
                    <option value="">Select a teacher</option>
                    <option v-for="teacher in teachers" :key="teacher.id" :value="teacher">{{ teacher.first_name }} {{ teacher.last_name }}</option>
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

                <div class="field-checkbox">
                    <InputSwitch v-model="section.is_active" />
                    <label for="is_active" class="ml-2">Active</label>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="sectionDialog = false" />
                <Button label="Save" icon="pi pi-check" class="p-button-primary" @click="saveSection" />
            </template>
        </Dialog>

        <!-- Teacher Assignment Dialog -->
        <Teleport to="body">
            <Dialog
                v-model:visible="showTeacherAssignmentDialog"
                header="Assign Subject Teacher"
                modal
                class="p-fluid"
                :style="{ width: '450px', zIndex: 9999 }"
                :closable="true"
                appendTo="body"
                @hide="selectedTeacher = null"
            >
                <div class="field">
                    <label for="subject-name">Subject</label>
                    <div class="p-field-value">{{ selectedSubjectForTeacher?.name }}</div>
                </div>

                <div class="field">
                    <label for="teacher">Select Teacher</label>
                    <select v-model="selectedTeacher" class="p-inputtext w-full" :class="{ 'p-invalid': teacherSubmitted && !selectedTeacher }">
                        <option value="">Select a teacher</option>
                        <option v-for="teacher in teachers" :key="teacher.id" :value="teacher">
                            {{ teacher.first_name }} {{ teacher.last_name }}
                        </option>
                    </select>
                    <small class="p-error" v-if="teacherSubmitted && !selectedTeacher">Please select a teacher.</small>
                </div>

                <template #footer>
                    <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showTeacherAssignmentDialog = false" :disabled="loading" />
                    <Button label="Assign" icon="pi pi-check" class="p-button-primary" @click="assignTeacher" :loading="loading" />
                </template>
            </Dialog>
        </Teleport>
    </div>
</template>
<style scoped>
.curriculum-wrapper {
    position: relative;
    overflow: hidden;
    min-height: 100vh;
    background-color: #e0f2ff;
    border-radius: 0 0 24px 0;
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.1);
}

.curriculum-container {
    padding: 2rem;
    position: relative;
    z-index: 1;
}

.top-nav-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.7);
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
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
    display: inline-flex;
    align-items: center;
    background: #e1f5fe;
    border-radius: 20px;
    padding: 0.25rem 0.75rem;
    gap: 0.5rem;
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
    from { opacity: 0; }
    to { opacity: 1; }
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
    background-color: rgba(0,0,0,0.4) !important;
}

/* Make sure the schedule dialog mask is on top */
body > .p-dialog-mask {
    z-index: 99998 !important;
}

/* Special styling for the schedule dialog */
.schedule-dialog {
    box-shadow: 0 10px 30px rgba(0,0,0,0.3) !important;
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
</style>
