<script setup>
// Define API_URL directly instead of importing
// import { API_URL } from '@/config';
import { CurriculumService } from '@/router/service/CurriculumService';
import { GradeService } from '@/router/service/GradesService';
import { SubjectService } from '@/router/service/Subjects';
import { TeacherService } from '@/router/service/TeacherService';
import axios from 'axios';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

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

// Define API_URL directly in the component
const API_URL = 'http://localhost:8000/api';

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
const submitted = ref(false);
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
const timeSlots = ref([
    '7:00 AM', '7:30 AM', '8:00 AM', '8:30 AM', '9:00 AM', '9:30 AM',
    '10:00 AM', '10:30 AM', '11:00 AM', '11:30 AM', '12:00 PM', '12:30 PM',
    '1:00 PM', '1:30 PM', '2:00 PM', '2:30 PM', '3:00 PM', '3:30 PM',
    '4:00 PM', '4:30 PM'
]);
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

// Calculate if a grade requires subject teachers (Grade 4-6)
const requiresSubjectTeachers = (gradeCode) => {
    return ['G4', 'G5', 'G6'].includes(gradeCode);
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
    let filtered = curriculums.value.filter(c => c); // Ensure curriculum exists

    // Filter by year if searchYear is set
    if (searchYear.value) {
        filtered = filtered.filter((c) =>
            c.yearRange && (
                c.yearRange.start === searchYear.value ||
                c.yearRange.end === searchYear.value ||
                `${c.yearRange.start}-${c.yearRange.end}` === searchYear.value
            )
        );
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
    if (curriculum.value.yearRange.end &&
        parseInt(curriculum.value.yearRange.end) <= parseInt(curriculum.value.yearRange.start)) {
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
            keys.forEach(key => localStorage.removeItem(key));
            console.log(`Cleared all local section data (${keys.length} items)`);
        }
    } catch (error) {
        console.warn('Error clearing local data:', error);
    }
};

// Extend onMounted to check for direct endpoints
onMounted(async () => {
    try {
        console.log('Component mounted, loading data...');

        // Clear all subject data from localStorage to reset state
        clearLocalData();

        await loadCurriculums();
        await loadGrades();
        await loadSubjects();
        await loadTeachers();

        console.log('Initial data loading complete');
    } catch (error) {
        console.error('Error during initial data loading:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load initial data. Please refresh the page.',
            life: 5000
        });
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
            curriculums.value = response.map(curriculum => normalizeYearRange(curriculum));
            console.log('Normalized curriculums:', curriculums.value);

            // Log filtered curriculums
            console.log('Filtered curriculums:', filteredCurriculums.value);

            // If none are displayed but we have data, check why they're filtered out
            if (curriculums.value.length > 0 && filteredCurriculums.value.length === 0) {
                console.warn('Curriculums are filtered out. Check status values:');
                curriculums.value.forEach(curr => {
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
    try {
        const data = await GradeService.getGrades();
        grades.value = data;
    } catch (error) {
        console.error('Error loading grades:', error);
        toast.add({
            severity: 'error',
            summary: 'Database Error',
            detail: 'Failed to load grades from database',
            life: 5000
        });
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
        const data = await CurriculumService.getGradesByCurriculum(selectedCurriculum.value.id);
        console.log('Grade levels loaded from API:', data);
        grades.value = Array.isArray(data) ? data : [];

        console.log('Final grade levels:', grades.value);
    } catch (error) {
        console.error('Error loading grade levels:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load grade levels for this curriculum',
            life: 3000
        });
        grades.value = [];
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
            const existingGradeIds = grades.value.map(g => g.id);
            availableGrades.value = availableGrades.value.filter(g => !existingGradeIds.includes(g.id));

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
    selectedGrade.value = grade;
    loading.value = true;
    try {
        console.log('Opening section list for grade:', grade.id, 'in curriculum:', selectedCurriculum.value.id);

        // Initialize sections as empty array
        sections.value = [];

        try {
            // Attempt to get sections with improved error handling
            const sectionsForGrade = await CurriculumService.getSectionsByGrade(
                selectedCurriculum.value.id,
                grade.id
            );

            if (Array.isArray(sectionsForGrade)) {
                sections.value = sectionsForGrade;
                console.log('Retrieved sections:', sections.value.length);

                // Show appropriate message based on sections found
                if (sections.value.length === 0) {
                    toast.add({
                        severity: 'info',
                        summary: 'No Sections',
                        detail: 'No sections found for this grade. You can add sections below.',
                        life: 3000
                    });
                }
            } else {
                console.warn('Sections response is not an array:', sectionsForGrade);
                sections.value = [];
                toast.add({
                    severity: 'warn',
                    summary: 'Warning',
                    detail: 'Received invalid data format for sections. Please try again.',
                    life: 3000
                });
            }
    } catch (error) {
            console.error('Error fetching sections:', error);
            sections.value = [];
        toast.add({
            severity: 'error',
            summary: 'Error',
                detail: error.message || 'Failed to load sections. Please try again.',
            life: 3000
        });
        }

        // Always show the dialog
        showSectionListDialog.value = true;
    } catch (error) {
        console.error('Error in openSectionList:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load sections for this grade level. Please try again later.',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

const openAddSectionDialog = async () => {
    try {
        // Set default section values
        section.value = {
            id: null,
            name: '',
            grade_id: selectedGrade.value.id,
            capacity: 25,
            is_active: true,
            teacher_id: null
        };

        // Load teachers if they haven't been loaded yet
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
        const response = await CurriculumService.getSectionsByCurriculumGrade(
            selectedCurriculum.value.id,
            selectedGrade.value.id
        );
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

        // Try to get curriculum_grade_id if possible
        try {
            const curriculumGrade = await CurriculumService.getCurriculumGrade(
                selectedCurriculum.value.id,
                selectedGrade.value.id
            );
            if (curriculumGrade && curriculumGrade.id) {
                sectionData.curriculum_grade_id = curriculumGrade.id;
            }
        } catch (error) {
            console.warn('Could not get curriculum_grade_id, continuing without it:', error);
        }

        // Add the section
        await CurriculumService.addSection(sectionData);

        // Reload sections if possible
        try {
            const sectionsForGrade = await CurriculumService.getSectionsByGrade(
                selectedCurriculum.value.id,
                selectedGrade.value.id
            );
            sections.value = Array.isArray(sectionsForGrade) ? sectionsForGrade : [];
        } catch (error) {
            console.warn('Could not reload sections, will continue with success message:', error);
        }

        // Close dialog and show success message
        sectionDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Section added successfully',
            life: 3000
        });

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
            await CurriculumService.removeSection(
                selectedCurriculum.value.id,
                selectedGrade.value.id,
                sectionId
            );
        } catch (error) {
            console.error('Error with removeSection API:', error);
            // Try direct API call as fallback
            await axios.delete(`${API_URL}/sections/${sectionId}`);
        }

        // Try to reload sections
        try {
            const gradeSections = await CurriculumService.getSectionsByGrade(
                selectedCurriculum.value.id,
                selectedGrade.value.id
            );
            sections.value = Array.isArray(gradeSections) ? gradeSections : [];
        } catch (error) {
            console.warn('Could not reload sections after removal:', error);
            // Remove the section locally from the array
            sections.value = sections.value.filter(s => s.id !== sectionId);
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
        selectedSection.value = section;
        loading.value = true;
        console.log('Opening subject list for section:', section);

        // Clear all subjects first
        selectedSubjects.value = [];
        showSubjectListDialog.value = true;

        // Load subjects with their schedules
        const response = await CurriculumService.getSubjectsBySection(
            selectedCurriculum.value.id,
            selectedGrade.value.id,
            section.id
        );

        if (Array.isArray(response)) {
            // Load schedules for each subject
            const subjectsWithSchedules = await Promise.all(
                response.map(async (subject) => {
                    const schedules = await loadSubjectSchedules(section.id, subject.id);
                    return {
                        ...subject,
                        schedules: schedules
                    };
                })
            );
            selectedSubjects.value = subjectsWithSchedules;
            console.log('Loaded subjects with schedules:', selectedSubjects.value);
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

// Add Subject Dialog handler
const openAddSubjectDialog = () => {
    selectedSubject.value = null;
    submitted.value = false;
    subjectDialog.value = true;
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
  // Hide the dialog
  showSubjectListDialog.value = false;

  // Reset all related variables
  selectedSection.value = null;
  selectedSubjects.value = [];
  selectedSubject.value = null;
  selectedSubjectForTeacher.value = null;
  selectedSubjectForSchedule.value = null;
  selectedTeacher.value = null;
};

// Refresh section subjects from API
const refreshSectionSubjects = async () => {
    if (!selectedSection.value || !selectedSection.value.id) {
        console.warn('Cannot refresh subjects: No section selected');
        return;
    }

    try {
        loading.value = true;
        console.log('Refreshing subjects and schedules...');

        const response = await CurriculumService.getSubjectsBySection(
            selectedCurriculum.value.id,
            selectedGrade.value.id,
            selectedSection.value.id
        );

        if (Array.isArray(response)) {
            // Load schedules for each subject
            const subjectsWithSchedules = await Promise.all(
                response.map(async (subject) => {
                    const schedules = await loadSubjectSchedules(selectedSection.value.id, subject.id);
                    return {
                        ...subject,
                        schedules: schedules
                    };
                })
            );
            selectedSubjects.value = subjectsWithSchedules;
            console.log('Successfully refreshed subjects with schedules:', selectedSubjects.value);
        }
    } catch (error) {
        console.error('Error refreshing section subjects:', error);
        toast.add({
            severity: 'error',
            summary: 'Refresh Failed',
            detail: 'Could not refresh subject data from server',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Confirm remove subject with a dialog
const confirmRemoveSubject = (subject) => {
  confirmDialog.require({
    message: `Are you sure you want to remove ${subject.name} from this section?`,
    header: 'Confirm Removal',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: () => removeSubject(subject.id),
    reject: () => {}
  });
};

// Store subjects locally to persist between page navigations
const storeLocalSubjects = () => {
    try {
        if (selectedSection.value && selectedSection.value.id && selectedSubjects.value) {
            const key = `section_subjects_${selectedSection.value.id}`;
            localStorage.setItem(key, JSON.stringify(selectedSubjects.value));
        }
    } catch (e) {
        console.warn('Error storing local subjects:', e);
    }
};

// Add this function before storeLocalSubjects
const addSubjectToSection = async (subjectId) => {
    try {
        if (!subjectId) {
            toast.add({
                severity: 'warn',
                summary: 'Warning',
                detail: 'Please select a subject',
                life: 3000
            });
            return;
        }

        loading.value = true;
        console.log(`Adding subject ${subjectId} to section ${selectedSection.value.id}`);

        // Find the subject details
        const subjectToAdd = subjects.value.find(s => s.id === subjectId);
        if (!subjectToAdd) {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Subject not found',
                life: 3000
            });
            return;
        }

        try {
            // Try to call the API
            await CurriculumService.addSubjectToSection(
                selectedCurriculum.value.id,
                selectedGrade.value.id,
                selectedSection.value.id,
                { subject_id: subjectId }
            );

            // Format and add the subject to the local array
            const formattedSubject = {
                ...subjectToAdd,
                pivot: {
                    section_id: selectedSection.value.id,
                    subject_id: subjectId,
                    created_at: new Date().toISOString()
                }
            };

            // Add to the displayed subjects
            selectedSubjects.value.push(formattedSubject);

            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Subject added successfully',
                life: 3000
            });

            // Close the dialog
            subjectDialog.value = false;
        } catch (error) {
            console.error('Error adding subject:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to add subject: ' + (error.message || 'Unknown error'),
                life: 3000
            });
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

// Add the removeSubject function before addSubjectToSection
const removeSubject = async (subjectId) => {
    try {
        loading.value = true;
        console.log(`Removing subject ${subjectId} from section ${selectedSection.value.id}`);

        try {
            // Try to call the API
            await CurriculumService.removeSubjectFromSection(
                selectedCurriculum.value.id,
                selectedGrade.value.id,
                selectedSection.value.id,
                subjectId
            );

            // Remove from the displayed subjects
            selectedSubjects.value = selectedSubjects.value.filter(s => s.id !== subjectId);

            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Subject removed successfully',
                life: 3000
            });
        } catch (error) {
            console.error('Error removing subject:', error);

            // Still remove from UI even if API call fails
            selectedSubjects.value = selectedSubjects.value.filter(s => s.id !== subjectId);

            toast.add({
                severity: 'warn',
                summary: 'Warning',
                detail: 'Subject removed from display, but may still exist on the server.',
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error in removeSubject:', error);
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

// Add the saveSchedule function after removeSubject
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

        loading.value = true;

        // Validate time format
        if (schedule.value.start_time >= schedule.value.end_time) {
            toast.add({
                severity: 'error',
                summary: 'Invalid Time Range',
                detail: 'Start time must be before end time',
                life: 3000
            });
            return;
        }

        try {
            // Format the schedule data
            const scheduleData = {
                day: schedule.value.day,
                start_time: schedule.value.start_time,
                end_time: schedule.value.end_time,
                teacher_id: schedule.value.teacher_id,
                subject_id: selectedSubjectForSchedule.value.id,
                section_id: selectedSection.value.id
            };

            // Call the API using the correct method name
            await CurriculumService.setSubjectSchedule(
                selectedCurriculum.value.id,
                selectedGrade.value.id,
                selectedSection.value.id,
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

            // Close the dialog
            showScheduleDialog.value = false;
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
            // Call the API
            await CurriculumService.assignTeacherToSubject(
                selectedCurriculum.value.id,
                selectedGrade.value.id,
                selectedSection.value.id,
                selectedSubjectForTeacher.value.id,
                { teacher_id: selectedTeacher.value.id }
            );

            // Update the subject with the teacher
            selectedSubjectForTeacher.value.teacher = selectedTeacher.value;
            selectedSubjectForTeacher.value.teacher_id = selectedTeacher.value.id;

            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Teacher assigned successfully',
                life: 3000
            });

            // Close the dialog
            showTeacherAssignmentDialog.value = false;
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
    const foundTeacher = teachers.value.find(t => t.id === teacherId);

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
        teacherSubmitted.value = true;
        console.log('Current section.teacher_id:', section.value.teacher_id);
        console.log('Available teachers:', teachers.value);

        if (!selectedSection.value) {
            console.error('No section selected');
            if (toast) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'No section selected',
                    life: 3000
                });
            }
            return;
        }

        if (!section.value.teacher_id) {
            console.warn('No teacher selected');
            if (toast) {
                toast.add({
                    severity: 'warn',
                    summary: 'Warning',
                    detail: 'Please select a teacher',
                    life: 3000
                });
            }
            return;
        }

        loading.value = true;

        try {
            console.log('Assigning teacher ID:', section.value.teacher_id, 'to section:', selectedSection.value.id);

            // Call the API
            await CurriculumService.assignTeacherToSection(
                selectedCurriculum.value.id,
                selectedGrade.value.id,
                selectedSection.value.id,
                { teacher_id: section.value.teacher_id }
            );

            // Update the section with the teacher ID
            selectedSection.value.teacher_id = section.value.teacher_id;

            // Find the teacher details and update local data
            const teacherDetails = teachers.value.find(t => t.id === section.value.teacher_id);
            console.log('Found teacher details:', teacherDetails);

            if (teacherDetails) {
                selectedSection.value.teacher = teacherDetails;
            }

            // Update sections array
            const sectionIndex = sections.value.findIndex(s => s.id === selectedSection.value.id);
            if (sectionIndex !== -1) {
                sections.value[sectionIndex] = {...selectedSection.value};
                console.log('Updated section in sections array:', sections.value[sectionIndex]);
            }

            if (toast) {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Homeroom teacher assigned successfully',
                    life: 3000
                });
            } else {
                console.log('Homeroom teacher assigned successfully');
            }

            // Clear form validation state
            teacherSubmitted.value = false;

            // Close the dialog
            homeRoomTeacherAssignmentDialog.value = false;
        } catch (error) {
            console.error('Error assigning homeroom teacher:', error);
            if (toast) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to assign homeroom teacher: ' + (error.message || 'Unknown error'),
                    life: 3000
                });
            } else {
                console.error('Failed to assign homeroom teacher:', error.message || 'Unknown error');
            }
        }
    } catch (error) {
        console.error('Error in assignHomeRoomTeacher:', error);
        if (toast) {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'An unexpected error occurred',
                life: 3000
            });
        } else {
            console.error('An unexpected error occurred:', error);
        }
    } finally {
        loading.value = false;
    }
};

// Update the openAssignHomeRoomTeacherDialog function
const openAssignHomeRoomTeacherDialog = async (sectionData) => {
    try {
        loading.value = true;

        // Store the section data
        selectedSection.value = sectionData;
        section.value = { ...sectionData }; // Copy the section data to the form

        // Reset validation state
        teacherSubmitted.value = false;

        console.log('Opening teacher assignment dialog for section:', sectionData);

        // Load teachers
        try {
            console.log('Fetching teachers data...');
            const teachersData = await TeacherService.getTeachers();

            if (Array.isArray(teachersData) && teachersData.length > 0) {
                teachers.value = teachersData;
                console.log(`Successfully loaded ${teachers.value.length} teachers:`, teachers.value);
            } else {
                console.warn('No teachers returned from API or empty array returned');
                // Use the mock data directly from the service
                teachers.value = [
                    { id: 1, name: 'Maria Santos Reyes', department: 'Mathematics' },
                    { id: 2, name: 'Jose Cruz Mendoza', department: 'Science' },
                    { id: 3, name: 'Carmela Bautista Lim', department: 'Filipino' },
                    { id: 4, name: 'Antonio dela Cruz', department: 'Social Studies' },
                    { id: 5, name: 'Rosario Fernandez', department: 'English' }
                ];
                console.log('Using mock teacher data:', teachers.value);
            }
        } catch (error) {
            console.error('Error loading teachers:', error);
            toast.add({
                severity: 'warn',
                summary: 'Warning',
                detail: 'Could not load teachers list',
                life: 3000
            });
            // Fallback to mock data
            teachers.value = [
                { id: 1, name: 'Maria Santos Reyes', department: 'Mathematics' },
                { id: 2, name: 'Jose Cruz Mendoza', department: 'Science' },
                { id: 3, name: 'Carmela Bautista Lim', department: 'Filipino' },
                { id: 4, name: 'Antonio dela Cruz', department: 'Social Studies' },
                { id: 5, name: 'Rosario Fernandez', department: 'English' }
            ];
            console.log('Fallback to mock teacher data:', teachers.value);
        }

        // Load subjects for this section for display purposes
        try {
            const subjectsResponse = await CurriculumService.getSubjectsBySection(
                selectedCurriculum.value.id,
                selectedGrade.value.id,
                sectionData.id
            );

            if (Array.isArray(subjectsResponse)) {
                selectedSubjects.value = subjectsResponse;
                console.log(`Loaded ${selectedSubjects.value.length} subjects for section ${sectionData.id}`);
            } else {
                selectedSubjects.value = [];
            }
        } catch (error) {
            console.warn('Could not load subjects for section:', error);
            selectedSubjects.value = [];
        }

        // Show the dialog
        homeRoomTeacherAssignmentDialog.value = true;
    } catch (error) {
        console.error('Error in openAssignHomeRoomTeacherDialog:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to open teacher assignment dialog',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Add the openScheduleDialog function after closeSubjectListDialog
const openScheduleDialog = async (subject) => {
    try {
        selectedSubjectForSchedule.value = subject;

        // Initialize schedule with default values
        schedule.value = {
            day: 'Monday',
            start_time: '08:00',
            end_time: '09:00',
            subject_id: subject.id,
            teacher_id: subject.teacher?.id || null
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

    showTeacherAssignmentDialog.value = true;
};

// Add a helper function to get teacher name from ID
const getTeacherName = (teacherId) => {
    if (!teacherId) return 'No teacher selected';

    if (typeof teacherId === 'object' && teacherId.name) {
        return teacherId.name;
    }

    const teacher = teachers.value.find(t => t.id === teacherId);
    return teacher ? teacher.name : `Teacher ${teacherId}`;
};

// Add this function after loadSubjects
const loadSubjectSchedules = async (sectionId, subjectId) => {
    try {
        const response = await axios.get(`${API_URL}/sections/${sectionId}/subjects/${subjectId}/schedules`);
        return Array.isArray(response.data) ? response.data : [];
    } catch (error) {
        console.error('Error loading subject schedules:', error);
        return [];
    }
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
                <div v-for="curr in filteredCurriculums" :key="curr.id"
                     class="curriculum-card"
                     :class="{ 'is-active': curr.is_active }">
                    <div class="card-header">
                        <h3 class="curriculum-name">{{ curr.name }}</h3>
                        <div class="status-badge" :class="{ 'active': curr.status === 'Active', 'archived': curr.status === 'Archived' }">
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
        <Dialog
            v-model:visible="curriculumDialog"
            :header="curriculum.id ? 'Edit Curriculum' : 'New Curriculum'"
            modal
            class="p-fluid curriculum-dialog"
            :style="{ width: '500px' }"
        >
            <div class="curriculum-form p-4">
                <!-- Name field -->
                <div class="field mb-4">
                    <label for="name" class="font-medium mb-2 block">Curriculum Name</label>
                    <div class="p-inputgroup">
                        <span class="p-inputgroup-addon">
                            <i class="pi pi-book"></i>
                        </span>
                        <InputText
                            id="name"
                            v-model="curriculum.name"
                            disabled
                            class="p-inputtext-lg"
                        />
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
                    <small class="p-error block mt-1" v-if="submitted && (!curriculum.yearRange?.start || !curriculum.yearRange?.end)">
                        Please select both start and end years
                    </small>
                </div>

                <!-- Description field -->
                <div class="field mb-4">
                    <label for="description" class="font-medium mb-2 block">Description</label>
                    <div class="p-inputgroup">
                        <span class="p-inputgroup-addon">
                            <i class="pi pi-info-circle"></i>
                        </span>
                        <Textarea
                            id="description"
                            v-model="curriculum.description"
                            rows="3"
                            placeholder="Enter curriculum description (optional)"
                            autoResize
                            class="w-full"
                        />
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
                    <Button
                        label="Cancel"
                        icon="pi pi-times"
                        class="p-button-text"
                        @click="curriculumDialog = false"
                    />
                    <Button
                        label="Save"
                        icon="pi pi-check"
                        class="p-button-primary"
                        :loading="loading"
                        @click="saveCurriculum"
                    />
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
                    <ProgressSpinner style="width: 50px; height: 50px;" />
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
                        placeholder="Select a grade level"
                        :class="{ 'p-invalid': submitted && !selectedGradeToAdd }"
                    />
                    <small class="p-error" v-if="submitted && !selectedGradeToAdd">Please select a grade level.</small>
                </div>
            </template>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="gradeDialog = false" />
                <Button label="Add" icon="pi pi-check" class="p-button-primary" @click="saveGrade" />
            </template>
        </Dialog>

        <!-- Section List Dialog -->
        <Dialog v-model:visible="showSectionListDialog" header="Sections" modal class="p-fluid section-list-dialog" :style="{ width: '80vw', maxWidth: '1000px' }">
            <div v-if="selectedGrade" class="section-management-container">
                <div class="grade-info">
                    <h3>{{ selectedGrade.name }}</h3>
                </div>

                <div class="section-list-section">
                    <div class="section-header">
                        <h4>Sections</h4>
                        <Button label="Add Section" icon="pi pi-plus" class="p-button-sm" @click="openAddSectionDialog" />
                    </div>

                    <div v-if="loading" class="loading-container">
                        <ProgressSpinner />
                    </div>
                    <div v-else-if="sections.length === 0" class="empty-sections">
                        <p>No sections assigned to this grade level.</p>
                    </div>
                    <div v-else class="section-cards">
                        <div v-for="section in sections" :key="section.id" class="section-card" @click.stop.prevent="openSubjectList(section)">
                            <div class="card-content">
                                <h3>{{ section.name }}</h3>
                                <p>Capacity: {{ section.capacity || 'Not set' }}</p>
                                <p v-if="section.teacher">Teacher: {{ section.teacher.name }}</p>
                                <p v-else-if="section.teacher_id">Teacher ID: {{ section.teacher_id }}</p>
                                <p v-else class="no-teacher">No homeroom teacher assigned</p>
                                <div class="card-actions">
                                    <Button icon="pi pi-user" class="p-button-rounded p-button-text" tooltip="Assign Homeroom Teacher" @click.stop="openAssignHomeRoomTeacherDialog(section)" />
                                    <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click.stop="removeSection(section.id)" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Close" icon="pi pi-times" class="p-button-text" @click="showSectionListDialog = false" />
            </template>
        </Dialog>

        <!-- Add Section Dialog -->
        <Dialog v-model:visible="sectionDialog" header="Add Section" modal class="p-fluid" :style="{ width: '450px' }">
            <div class="field">
                <label for="sectionName">Section Name</label>
                <InputText id="sectionName" v-model="section.name" required :class="{ 'p-invalid': submitted && !section.name }" />
                <small class="p-error" v-if="submitted && !section.name">Section name is required.</small>
            </div>

            <div class="field">
                <label for="capacity">Capacity</label>
                <InputNumber id="capacity" v-model="section.capacity" :min="1" />
            </div>

            <div class="field">
                <label for="teacher">Homeroom Teacher (Optional)</label>
                <Select id="teacher" v-model="section.teacher_id" :options="teachers || []" optionLabel="name" optionValue="id" placeholder="Select Homeroom Teacher" />
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="sectionDialog = false" />
                <Button label="Save" icon="pi pi-check" class="p-button-primary" @click="saveSection" />
            </template>
        </Dialog>

        <!-- Add this after the Add Section Dialog -->
        <!-- Subject List Dialog -->
        <Dialog
            v-model:visible="showSubjectListDialog"
            header="Subjects"
            :style="{ width: '80vw' }"
            class="p-fluid"
            :modal="true"
            :closable="true"
            :closeOnEscape="true"
        >
            <div v-if="loading" class="flex justify-content-center align-items-center" style="height: 200px;">
                <ProgressSpinner />
            </div>
            <div v-else>
                <div class="flex align-items-center justify-content-between mb-3">
                    <h3 class="m-0">Section: {{ selectedSection?.name }}</h3>
                    <div class="flex gap-2">
                        <Button
                            icon="pi pi-refresh"
                            @click="refreshSectionSubjects"
                            class="p-button-outlined p-button-secondary"
                            v-tooltip.top="'Refresh Subjects'"
                        />
                        <Button
                            label="Add Subject"
                            icon="pi pi-plus"
                            @click="openAddSubjectDialog"
                            class="p-button-success"
                        />
                    </div>
                </div>

                <div v-if="selectedSubjects.length === 0" class="text-center p-4">
                    <i class="pi pi-book text-5xl text-primary mb-3"></i>
                    <p>No subjects have been added to this section.</p>
                    <p>Click "Add Subject" to assign subjects.</p>
                </div>

                <div v-else class="subject-grid">
                    <div
                        v-for="subject in selectedSubjects"
                        :key="subject.id"
                        class="subject-card p-3 border-round shadow-2"
                    >
                        <div class="flex flex-column h-full">
                            <!-- Subject Header -->
                            <div class="flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h4 class="m-0 mb-1 text-xl">{{ subject.name }}</h4>
                                    <p class="mt-0 mb-2 text-600">Code: {{ subject.code }}</p>
                                    <p v-if="subject.description" class="m-0 text-sm text-500">{{ subject.description }}</p>
                                </div>
                                <div class="flex flex-column gap-2">
                                    <Button
                                        icon="pi pi-calendar"
                                        class="p-button-rounded p-button-primary p-button-outlined"
                                        @click="openScheduleDialog(subject)"
                                        v-tooltip.top="'Set Schedule'"
                                    />
                                    <Button
                                        icon="pi pi-user"
                                        class="p-button-rounded p-button-success p-button-outlined"
                                        @click="openTeacherDialog(subject)"
                                        v-tooltip.top="'Assign Teacher'"
                                    />
                                    <Button
                                        icon="pi pi-trash"
                                        class="p-button-rounded p-button-danger p-button-outlined"
                                        @click="confirmRemoveSubject(subject)"
                                        v-tooltip.top="'Remove Subject'"
                                    />
                                </div>
                            </div>

                            <!-- Schedule Section -->
                            <div class="schedule-section mt-2 flex-grow-1">
                                <div class="schedule-header flex align-items-center gap-2 mb-2">
                                    <i class="pi pi-calendar text-primary"></i>
                                    <span class="font-semibold">Class Schedule</span>
                                </div>

                                <div v-if="subject.schedules && subject.schedules.length > 0" class="schedule-list">
                                    <div
                                        v-for="schedule in subject.schedules"
                                        :key="schedule.id"
                                        class="schedule-item p-2 mb-2 border-round surface-ground"
                                    >
                                        <div class="flex align-items-center justify-content-between">
                                            <div class="flex align-items-center gap-2">
                                                <span class="schedule-day font-semibold text-primary">{{ schedule.day }}</span>
                                                <span class="schedule-time text-700">
                                                    {{ schedule.start_time }} - {{ schedule.end_time }}
                                                </span>
                                            </div>
                                            <div v-if="schedule.teacher" class="teacher-info flex align-items-center gap-2">
                                                <i class="pi pi-user text-primary"></i>
                                                <span class="text-600">{{ schedule.teacher.name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="no-schedules p-3 text-center surface-ground border-round">
                                    <i class="pi pi-calendar-times text-500 text-xl mb-2"></i>
                                    <p class="m-0 text-600">No schedules set for this subject</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Close" @click="closeSubjectListDialog" class="p-button-text" />
            </template>
        </Dialog>
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

.symbol:nth-child(1) { top: 10%; left: 10%; animation: float 6s ease-in-out infinite; }
.symbol:nth-child(2) { top: 40%; right: 15%; animation: float 7s ease-in-out infinite 1s; }
.symbol:nth-child(3) { bottom: 20%; left: 20%; animation: float 5s ease-in-out infinite 0.5s; }
.symbol:nth-child(4) { bottom: 40%; right: 10%; animation: float 8s ease-in-out infinite 1.5s; }
.symbol:nth-child(5) { top: 30%; left: 50%; animation: float 4s ease-in-out infinite 2s; }

@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
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

.grade-cards, .section-cards, .subject-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}

.grade-card, .section-card, .subject-card {
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

.grade-card:hover, .section-card:hover, .subject-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
}

.empty-grades, .empty-sections, .empty-subjects {
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
.schedule-info, .teacher-info {
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
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1rem;
  margin-top: 1rem;
}

.subject-card {
  border: 1px solid #e9ecef;
  transition: transform 0.2s, box-shadow 0.2s;
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
    transition: transform 0.2s, box-shadow 0.2s;
    height: 100%;
}

.subject-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.schedule-section {
    border-top: 1px solid var(--surface-200);
    padding-top: 1rem;
}

.schedule-item {
    background: var(--surface-50);
    border: 1px solid var(--surface-200);
    transition: background-color 0.2s;
}

.schedule-item:hover {
    background: var(--surface-100);
}

.schedule-day {
    min-width: 80px;
    display: inline-block;
}

.schedule-time {
    font-family: var(--font-family);
    font-size: 0.9rem;
}

.no-schedules {
    background: var(--surface-50);
    border: 1px dashed var(--surface-300);
}

.no-schedules i {
    display: block;
    margin-bottom: 0.5rem;
}

.subject-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
    padding: 0.5rem;
}
</style>

