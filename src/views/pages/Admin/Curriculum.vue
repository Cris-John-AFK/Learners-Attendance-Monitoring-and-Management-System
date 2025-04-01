<script setup>
import { CurriculumService } from '@/router/service/CurriculumService';
import { GradeService } from '@/router/service/GradesService';
import { SubjectService } from '@/router/service/Subjects';
import { TeacherService } from '@/router/service/TeacherService';
import axios from 'axios';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import ProgressSpinner from 'primevue/progressspinner';
import RadioButton from 'primevue/radiobutton';
import Textarea from 'primevue/textarea';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const toast = useToast();
const confirmDialog = useConfirm();
const API_URL = 'http://localhost:8000/api';
const curriculums = ref([]);
const loading = ref(true);
const curriculumDialog = ref(false);
const deleteCurriculumDialog = ref(false);
const selectedCurriculum = ref(null);
const archiveDialog = ref(false);
const archiveConfirmDialog = ref(false);
const selectedCurriculumToArchive = ref(null);
const searchYear = ref('');

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
const availableEndYears = computed(() => {
    if (!curriculum.value.yearRange.start) return years.value;
    const startIdx = years.value.indexOf(curriculum.value.yearRange.start);
    return years.value.slice(startIdx + 1);
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
    is_active: true
});

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

// Reset search filter
const clearSearch = () => {
    searchYear.value = '';
};

// Handle year range selection in curriculum form
const handleStartYearChange = () => {
    // Reset end year if it's less than or equal to start year
    if (curriculum.value.yearRange.end &&
        parseInt(curriculum.value.yearRange.end) <= parseInt(curriculum.value.yearRange.start)) {
        curriculum.value.yearRange.end = '';
    }
};

// Load curriculums on component mount
onMounted(async () => {
    try {
        console.log('Component mounted, loading data...');
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
        teachers.value = data;
    } catch (error) {
        console.error('Error loading teachers:', error);
        toast.add({
            severity: 'error',
            summary: 'Database Error',
            detail: 'Failed to load teachers from database',
            life: 5000
        });
    }
}

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
        selectedCurriculum.value = normalizeYearRange(curr);
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

const openAddGradeDialog = async () => {
    selectedGradeToAdd.value = null;
    submitted.value = false;

    // Load available grade levels that aren't already in the curriculum
    try {
        loading.value = true;
        await loadAllGrades();

        // Filter out grade levels that are already in the curriculum
        if (grades.value && grades.value.length > 0) {
            const curriculumGradeIds = grades.value.map(g => g.id);
            availableGrades.value = availableGrades.value.filter(g => !curriculumGradeIds.includes(g.id));
        }

        gradeDialog.value = true;
    } catch (error) {
        console.error('Error preparing grade level selection:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load available grade levels',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

const saveGrade = async () => {
    submitted.value = true;

    if (!grade.value.name || !grade.value.code) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Please fill in all required fields',
            life: 3000
        });
        return;
    }

    try {
        loading.value = true;

        // Prepare data for API
        const gradeData = {
            ...grade.value,
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

const openAddSectionDialog = () => {
    section.value = {
        id: null,
        name: '',
        grade_id: selectedGrade.value.id,
        capacity: 25,
        is_active: true
    };
    sectionDialog.value = true;
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
            description: section.value.description || ''
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
            description: ''
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
    selectedSection.value = section;
            loading.value = true;
    console.log('Opening subject list for section:', section);

    try {
        // Initialize subjects array
        selectedSubjects.value = [];

        // First try to get curriculum grade relationship (but don't fail if it doesn't exist)
        try {
            const curriculumGrade = await CurriculumService.getCurriculumGrade(
                selectedCurriculum.value.id,
                selectedGrade.value.id
            );
            console.log('Curriculum grade relationship:', curriculumGrade);
        } catch (error) {
            console.warn('Could not get curriculum grade relationship, continuing anyway');
        }

        // Try to get subjects for this section
        try {
            const subjects = await CurriculumService.getSubjectsBySection(
                selectedCurriculum.value.id,
                selectedGrade.value.id,
                section.id
            );

            if (Array.isArray(subjects)) {
                selectedSubjects.value = subjects;
                console.log('Retrieved subjects:', subjects.length);
            } else {
                console.warn('Subjects response is not an array:', subjects);
                selectedSubjects.value = [];
            }
        } catch (subjectError) {
            console.warn('Error fetching subjects from API:', subjectError);
            selectedSubjects.value = [];
        }

        // Try to load schedules and teachers for this section's subjects
        if (selectedSubjects.value.length > 0) {
            for (const subject of selectedSubjects.value) {
                // Try to get schedule for this subject
                try {
                    const scheduleResponse = await axios.get(`${API_URL}/sections/${section.id}/subjects/${subject.id}/schedule`);
                    if (scheduleResponse.data) {
                        subject.schedule = scheduleResponse.data;
                    }
                } catch (scheduleError) {
                    console.warn(`Could not get schedule for subject ${subject.id}:`, scheduleError);
                    subject.schedule = [];
                }

                // Try to get assigned teacher for this subject
                try {
                    const teacherResponse = await axios.get(`${API_URL}/sections/${section.id}/subjects/${subject.id}/teacher`);
                    if (teacherResponse.data) {
                        subject.teacher = teacherResponse.data;
                    }
                } catch (teacherError) {
                    console.warn(`Could not get teacher for subject ${subject.id}:`, teacherError);
                    subject.teacher = null;
                }
            }
        }

        // Always show the dialog, even if there are no subjects
        showSubjectListDialog.value = true;
    } catch (error) {
        console.error('Error in openSubjectList:', error);
        toast.add({
            severity: 'info',
            summary: 'Subject List',
            detail: 'No subjects found for this section. You can add subjects below.',
            life: 3000
        });
        selectedSubjects.value = [];
        showSubjectListDialog.value = true; // Still show the dialog even if there's an error
    } finally {
        loading.value = false;
    }
};

const openAddSubjectDialog = () => {
    subjectDialog.value = true;
};

const addSubjectToSection = async (subjectId) => {
    try {
        await CurriculumService.addSubjectToSection(
            selectedCurriculum.value.id,
            selectedGrade.value.id,
            selectedSection.value.id,
            { subject_id: subjectId }
        );

        // Reload subjects
        const subjectsForSection = await CurriculumService.getSubjectsBySection(
            selectedCurriculum.value.id,
            selectedGrade.value.id,
            selectedSection.value.id
        );
        selectedSubjects.value = subjectsForSection;

        subjectDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Subject added successfully',
            life: 3000
        });
    } catch (error) {
        console.error('Error adding subject:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to add subject: ' + (error.message || 'Unknown error'),
            life: 3000
        });
    }
};

// Add the loadAllGrades function
async function loadAllGrades() {
    try {
        loading.value = true;
        console.log('Loading all grade levels from database');
        const data = await GradeService.getGrades();
        availableGrades.value = Array.isArray(data) ? data : [];
        console.log('Available grade levels loaded:', availableGrades.value);
    } catch (error) {
        console.error('Error loading all grade levels:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load grade levels from database',
            life: 3000
        });
        availableGrades.value = [];
    } finally {
        loading.value = false;
    }
}

// Add grade to curriculum function
const addExistingGradeLevel = async () => {
    if (!selectedGradeToAdd.value) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Please select a grade level to add',
            life: 3000
        });
        return;
    }

    try {
        loading.value = true;
        console.log('Adding grade to curriculum:', selectedCurriculum.value.id, selectedGradeToAdd.value);

        // Format the grade data properly for the API
        const gradeData = {
            grade_id: selectedGradeToAdd.value.id,
            name: selectedGradeToAdd.value.name,
            code: selectedGradeToAdd.value.code,
            display_order: selectedGradeToAdd.value.display_order || 0
        };

        await CurriculumService.addGradeToCurriculum(
            selectedCurriculum.value.id,
            gradeData
        );

        // Reload grades after successful addition
        await loadGradeLevels();

        gradeDialog.value = false;
        selectedGradeToAdd.value = null;

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Grade level added successfully',
            life: 3000
        });
    } catch (error) {
        console.error('Error adding grade to curriculum:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to add grade level: ' + (error.message || 'Unknown error'),
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Function to confirm deletion of a curriculum
const confirmDeleteCurriculum = (curr) => {
    selectedCurriculum.value = normalizeYearRange({ ...curr });
    deleteCurriculumDialog.value = true;
};

// Function to delete a curriculum
const deleteCurriculum = async () => {
    try {
        loading.value = true;
        await CurriculumService.archiveCurriculum(selectedCurriculum.value.id);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Curriculum deleted successfully',
            life: 3000
        });

        deleteCurriculumDialog.value = false;
        await loadCurriculums();
    } catch (error) {
        console.error('Error deleting curriculum:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete curriculum: ' + (error.message || 'Unknown error'),
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Function for assigning teachers to subjects
const assignTeacherToSubject = async (subject) => {
    selectedSubjectForTeacher.value = subject;
    loading.value = true;

    try {
        // Fetch available teachers
        const response = await axios.get(`${API_URL}/teachers/active`);
        if (response.data && Array.isArray(response.data)) {
            availableTeachers.value = response.data;
        } else {
            availableTeachers.value = [];
        }
    } catch (error) {
        console.error('Error fetching teachers:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load teachers. Please try again.',
            life: 3000
        });
        availableTeachers.value = [];
    } finally {
        loading.value = false;
        showTeacherAssignmentDialog.value = true;
    }
};

const saveTeacherAssignment = async () => {
    if (!selectedTeacher.value) {
        toast.add({
            severity: 'warn',
            summary: 'Required Field',
            detail: 'Please select a teacher to assign',
            life: 3000
        });
        return;
    }

    loading.value = true;

    try {
        // API call to assign teacher
        await CurriculumService.assignTeacherToSubject(
            selectedCurriculum.value.id,
            selectedGrade.value.id,
            selectedSection.value.id,
            selectedSubjectForTeacher.value.id,
            { teacher_id: selectedTeacher.value.id }
        );

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Teacher assigned successfully',
            life: 3000
        });

        // Update local data
        selectedSubjectForTeacher.value.teacher = selectedTeacher.value;

        // Close dialog
        showTeacherAssignmentDialog.value = false;

        // Reload subjects to refresh data
        openSubjectList(selectedSection.value);
    } catch (error) {
        console.error('Error assigning teacher:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to assign teacher. Please try again.',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Function to handle adding a schedule to a subject
const openScheduleDialog = (subject) => {
    selectedSubjectForSchedule.value = subject;

    // Reset schedule form
    schedule.value = {
        day: 'Monday',
        start_time: '08:00',
        end_time: '09:00',
        teacher_id: subject.teacher?.id || null
    };

    showScheduleDialog.value = true;
};

const saveSchedule = async () => {
    // Validate form
    if (!schedule.value.day || !schedule.value.start_time || !schedule.value.end_time) {
        toast.add({
            severity: 'warn',
            summary: 'Required Fields',
            detail: 'Please fill in all schedule details',
            life: 3000
        });
        return;
    }

    loading.value = true;

    try {
        // API call to set schedule
        await CurriculumService.setSubjectSchedule(
            selectedCurriculum.value.id,
            selectedGrade.value.id,
            selectedSection.value.id,
            selectedSubjectForSchedule.value.id,
            schedule.value
        );

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Schedule set successfully',
            life: 3000
        });

        // Close dialog
        showScheduleDialog.value = false;

        // Reload subjects to refresh data
        openSubjectList(selectedSection.value);
    } catch (error) {
        console.error('Error setting schedule:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to set schedule. Please try again.',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Method to remove a subject from a section
const removeSubjectFromSection = async (subjectId) => {
    // Confirm deletion
    confirmDialog({
        message: 'Are you sure you want to remove this subject from the section?',
        header: 'Confirm Removal',
        icon: 'pi pi-exclamation-triangle',
        accept: async () => {
            loading.value = true;

            try {
                // API call to remove subject
                await CurriculumService.removeSubjectFromSection(
                    selectedCurriculum.value.id,
                    selectedGrade.value.id,
                    selectedSection.value.id,
                    subjectId
                );

                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: 'Subject removed successfully',
                    life: 3000
                });

                // Update local data
                selectedSubjects.value = selectedSubjects.value.filter(s => s.id !== subjectId);
            } catch (error) {
                console.error('Error removing subject:', error);
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: 'Failed to remove subject. Please try again.',
                    life: 3000
                });
            } finally {
                loading.value = false;
            }
        }
    });
};
</script>

<template>
    <div class="admin-subject-wrapper">
        <!-- Light geometric background shapes -->
        <div class="background-container">
            <div class="geometric-shape circle"></div>
            <div class="geometric-shape square"></div>
            <div class="geometric-shape triangle"></div>
            <div class="geometric-shape rectangle"></div>
            <div class="geometric-shape diamond"></div>
        </div>

        <div class="admin-subject-container">
            <!-- Top Section -->
            <div class="top-nav-bar">
                <div class="nav-left">
                    <h2 class="text-2xl font-semibold">Curriculum Management</h2>
                </div>
                <div class="nav-right">
                    <div class="filter-area" v-if="availableYears.length">
                        <Dropdown v-model="searchYear" :options="availableYears" placeholder="Filter by Year" class="year-filter" />
                        <Button v-if="searchYear" icon="pi pi-times" class="clear-filter p-button-rounded" @click="clearSearch" />
                    </div>
                    <div class="nav-right-buttons">
                        <Button label="New Curriculum" icon="pi pi-plus" class="add-button" @click="openNew" />
                        <Button label="Archives" icon="pi pi-box" class="archive-button" @click="openArchiveDialog" />
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="loading-container">
                <ProgressSpinner />
                <p>Loading curriculums...</p>
            </div>

            <!-- Cards Grid -->
            <div v-else-if="filteredCurriculums.length > 0" class="cards-grid">
                <div v-for="curr in filteredCurriculums" :key="curr.id" class="subject-card"
                    :style="cardStyles[curr.id]"
                    @click="openGradeLevelManagement(curr)"
                    style="cursor: pointer;">
                    <!-- Floating symbols -->
                    <span class="symbol">∑</span>
                    <span class="symbol">π</span>
                    <span class="symbol">∞</span>
                    <span class="symbol">Δ</span>
                    <span class="symbol">√</span>

                    <div class="card-content">
                        <div class="card-header">
                            <h1 class="subject-title">{{ curr.name }}</h1>
                            <p class="year-badge">{{ curr.yearRange.start || '' }} - {{ curr.yearRange.end || '' }}</p>
                        </div>

                        <div class="card-body">
                            <p v-if="curr.description">{{ curr.description }}</p>
                            <p v-else class="no-description">No description available</p>
                        </div>

                        <div class="card-footer">
                            <span class="status-badge" :class="{ active: curr.status === 'Active' }">
                                <i :class="curr.status === 'Active' ? 'pi pi-check-circle' : 'pi pi-clock'"></i>
                                <span class="ml-2">{{ curr.status }}</span>
                            </span>

                            <div class="card-actions">
                                <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click.stop="editCurriculum(curr)" />
                                <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click.stop="confirmDeleteCurriculum(curr)" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="empty-state">
                <i class="pi pi-book"></i>
                <h3>No Curriculums Found</h3>
                <p v-if="searchYear">No curriculums match your filter. Try clearing the filter or add a new curriculum.</p>
                <p v-else>You haven't created any curriculums yet. Get started by adding your first curriculum.</p>
                <div class="empty-actions">
                    <Button label="New Curriculum" icon="pi pi-plus" class="p-button-success" @click="openNew" />
                </div>
            </div>
        </div>
    </div>

    <!-- Curriculum Dialog -->
    <Dialog v-model:visible="curriculumDialog" :style="{width: '450px'}" header="Curriculum Details" :modal="true" class="p-fluid">
        <div class="dialog-form-container p-5">
            <!-- Floating particles -->
            <div class="dialog-particle"></div>
            <div class="dialog-particle"></div>
            <div class="dialog-particle"></div>

            <div class="field animated-field">
                <label for="name">
                    <i class="pi pi-book mr-2"></i>Name
                </label>
                <InputText id="name" v-model="curriculum.name" required autofocus :class="{'p-invalid': submitted && !curriculum.name}" />
                <small v-if="submitted && !curriculum.name" class="p-error">Name is required.</small>
            </div>
            <div class="field animated-field">
                <label for="start-year">
                    <i class="pi pi-calendar mr-2"></i>Start Year
                </label>
                <Dropdown id="start-year" v-model="curriculum.yearRange.start" :options="years" placeholder="Select Start Year"
                    :class="{'p-invalid': submitted && !curriculum.yearRange.start}" @change="handleStartYearChange" />
                <small v-if="submitted && !curriculum.yearRange.start" class="p-error">Start year is required.</small>
            </div>
            <div class="field animated-field">
                <label for="end-year">
                    <i class="pi pi-calendar mr-2"></i>End Year
                </label>
                <Dropdown id="end-year" v-model="curriculum.yearRange.end" :options="availableEndYears" placeholder="Select End Year"
                    :class="{'p-invalid': submitted && !curriculum.yearRange.end}" />
                <small v-if="submitted && !curriculum.yearRange.end" class="p-error">End year is required.</small>
            </div>
            <div class="field animated-field">
                <label for="description">
                    <i class="pi pi-info-circle mr-2"></i>Description
                </label>
                <Textarea id="description" v-model="curriculum.description" rows="3" autoResize />
            </div>
            <div class="field animated-field">
                <label class="mb-3">
                    <i class="pi pi-check-square mr-2"></i>Status
                </label>
                <div class="field-radiobutton">
                    <RadioButton id="status1" name="status" value="Active" v-model="curriculum.status" />
                    <label for="status1">Active</label>
                </div>
                <div class="field-radiobutton">
                    <RadioButton id="status2" name="status" value="Draft" v-model="curriculum.status" />
                    <label for="status2">Draft</label>
                </div>
            </div>
        </div>
        <template #footer>
            <div class="dialog-footer-buttons">
                <Button label="Cancel" icon="pi pi-times" class="p-button-text cancel-button" @click="curriculumDialog = false" />
                <Button label="Save" icon="pi pi-check" class="p-button-raised p-button-primary save-button-custom" @click="saveCurriculum" :loading="loading" />
            </div>
        </template>
    </Dialog>

    <!-- Delete Curriculum Dialog -->
    <Dialog v-model:visible="deleteCurriculumDialog" :style="{width: '450px'}" header="Confirm" :modal="true">
        <div class="confirmation-content">
            <i class="pi pi-exclamation-triangle mr-3" style="font-size: 2rem"></i>
            <span>Are you sure you want to delete <b>{{ selectedCurriculum?.name }}</b>?</span>
        </div>
        <template #footer>
            <Button label="No" icon="pi pi-times" class="p-button-text" @click="deleteCurriculumDialog = false" />
            <Button label="Yes" icon="pi pi-check" class="p-button-text p-button-danger" @click="deleteCurriculum" />
        </template>
    </Dialog>

    <!-- Archive Dialog -->
    <Dialog v-model:visible="archiveDialog" :style="{width: '80vw'}" header="Archived Curriculums" :modal="true">
        <div class="archive-list">
            <div v-if="loading" class="loading-container">
                <ProgressSpinner style="width: 50px; height: 50px;" strokeWidth="4" />
                <span class="ml-3">Loading archived curriculums...</span>
            </div>
            <DataTable v-else :value="curriculums.filter(c => c.status === 'Archived')" :paginator="true" :rows="10"
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
                :rowsPerPageOptions="[5,10,25]" currentPageReportTemplate="Showing {first} to {last} of {totalRecords} archived curriculums">
                <Column field="name" header="Name" sortable style="width: 25%"></Column>
                <Column header="Year Range" style="width: 20%">
                    <template #body="slotProps">
                        {{ slotProps.data.yearRange.start }} - {{ slotProps.data.yearRange.end }}
                    </template>
                </Column>
                <Column field="description" header="Description" style="width: 35%"></Column>
                <Column header="Actions" style="width: 20%">
                    <template #body="slotProps">
                        <Button icon="pi pi-refresh" class="p-button-rounded p-button-success mr-2" @click="restoreCurriculum(slotProps.data)" />
                    </template>
                </Column>
            </DataTable>
        </div>
    </Dialog>

    <!-- Grade Level Management Dialog -->
    <Dialog v-model:visible="showGradeLevelManagement" :style="{width: '80vw'}" :header="`${selectedCurriculum?.name} - Grade Levels`" :modal="true">
        <div class="dialog-header">
            <div>
                <h2 class="dialog-subtitle">Manage Grade Levels</h2>
                <p class="dialog-description">Add, remove or manage grade levels for this curriculum</p>
            </div>
            <Button label="Add Grade Level" icon="pi pi-plus" class="p-button-success" @click="openAddGradeDialog" />
        </div>

        <div v-if="loading" class="loading-container">
            <ProgressSpinner style="width: 50px; height: 50px;" strokeWidth="4" />
            <span class="ml-3">Loading grade levels...</span>
        </div>

        <div v-else-if="grades.length === 0" class="empty-state">
            <div class="empty-icon">
                <i class="pi pi-info-circle"></i>
            </div>
            <h3>No Grade Levels</h3>
            <p>This curriculum doesn't have any grade levels yet. Add grade levels to continue.</p>
            <div class="empty-actions">
                <Button label="Add Grade Level" icon="pi pi-plus" class="p-button-success" @click="openAddGradeDialog" />
            </div>
        </div>

        <div v-else class="grade-cards">
            <div v-for="grade in grades" :key="grade.id"
                class="subject-card"
                :style="gradeCardStyles[grade.id]"
                @click="openSectionList(grade)"
                style="cursor: pointer;">
                <!-- Floating symbols -->
                <span class="symbol">∑</span>
                <span class="symbol">π</span>
                <span class="symbol">∞</span>
                <span class="symbol">Δ</span>
                <span class="symbol">√</span>

                <div class="card-content">
                    <div class="card-header">
                        <h1 class="subject-title">{{ grade.name }}</h1>
                        <Tag :value="grade.code" severity="info" class="grade-badge" />
                    </div>

                    <div class="card-body">
                        <p class="order-text">Order: {{ grade.display_order || 'Not set' }}</p>
                    </div>

                    <div class="card-footer">
                        <span class="status-badge">
                            <i class="pi pi-book"></i>
                            <span class="ml-2">Grade Level</span>
                        </span>
                        <div class="card-actions">
                            <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click.stop="removeGrade(grade.id)" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Dialog>

    <!-- Add Grade Dialog -->
    <Dialog v-model:visible="gradeDialog" :style="{width: '450px'}" header="Add Grade Level" :modal="true">
        <div v-if="loading" class="loading-container">
            <ProgressSpinner style="width: 50px; height: 50px;" strokeWidth="4" />
            <span class="ml-3">Loading available grades...</span>
        </div>

        <div v-else-if="availableGrades.length === 0" class="empty-state">
            <div class="empty-icon">
                <i class="pi pi-info-circle"></i>
            </div>
            <h3>No Available Grades</h3>
            <p>All grade levels are already added to this curriculum or no grade levels exist in the system.</p>
        </div>

        <div v-else class="grade-selection">
            <h3>Select a Grade Level to Add</h3>
            <div class="grade-selection-cards">
                <div v-for="availableGrade in availableGrades" :key="availableGrade.id"
                    class="grade-select-card"
                    :class="{'selected': selectedGradeToAdd && selectedGradeToAdd.id === availableGrade.id}"
                    @click="selectedGradeToAdd = availableGrade">
                    <div class="flex justify-content-between align-items-center">
                        <h4 class="m-0">{{ availableGrade.name }}</h4>
                        <Tag :value="availableGrade.code" severity="info" />
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="gradeDialog = false" />
            <Button label="Add" icon="pi pi-check" class="p-button-text p-button-success"
                @click="addExistingGradeLevel" :disabled="!selectedGradeToAdd" />
        </template>
    </Dialog>

    <!-- Continue with remaining dialogs -->
    <!-- Section List Dialog, Subject List Dialog, etc. -->
</template>

<style scoped>
.admin-subject-wrapper {
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
    width: 200px;
    height: 200px;
    background-color: #6b9de8;
    bottom: 10%;
    left: -80px;
    transform: rotate(30deg);
    animation: rotate 25s linear infinite, float 18s ease-in-out infinite;
}

/* Triangle shape */
.triangle {
    width: 0;
    height: 0;
    border-left: 150px solid transparent;
    border-right: 150px solid transparent;
    border-bottom: 260px solid #5a96e3;
    top: 40%;
    right: -100px;
    opacity: 0.15;
    animation: float 22s ease-in-out infinite, opacity-pulse 15s ease-in-out infinite;
}

/* Rectangle shape */
.rectangle {
    width: 400px;
    height: 120px;
    background-color: #78a6f0;
    bottom: -50px;
    right: 20%;
    transform: rotate(-15deg);
    animation: float 24s ease-in-out infinite;
}

/* Diamond shape */
.diamond {
    width: 200px;
    height: 200px;
    background-color: #3c7dd4;
    transform: rotate(45deg);
    top: 15%;
    left: 10%;
    animation: float 23s ease-in-out infinite reverse, opacity-pulse 18s ease-in-out infinite;
}

/* Simple float animation */
@keyframes float {
    0%, 100% {
        transform: translate(0, 0) rotate(0deg);
    }
    25% {
        transform: translate(15px, 15px) rotate(2deg);
    }
    50% {
        transform: translate(5px, -10px) rotate(-2deg);
    }
    75% {
        transform: translate(-15px, 8px) rotate(1deg);
    }
}

/* Slow rotation animation */
@keyframes rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Subtle opacity animation */
@keyframes opacity-pulse {
    0%, 100% {
        opacity: 0.05;
    }
    50% {
        opacity: 0.1;
    }
}

.admin-subject-container {
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

/* Top navigation buttons alignment */
.top-nav-bar {
    border-bottom: 1px solid rgba(74, 135, 213, 0.2);
    margin-bottom: 2.5rem;
    padding-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 1rem;
    justify-content: flex-end;
}

.nav-right .p-button {
    height: 2.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 1rem;
}

.nav-right-buttons {
    display: flex;
    align-items: right;
    gap: 1rem;
}

.filter-area {
    display: flex;
    align-items: center;
    height: 2.5rem;
    margin-right: 0.5rem;
}

/* Style for the year filter */
:deep(.year-filter) {
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(74, 135, 213, 0.3);
    border-radius: 8px;
    min-width: 150px;
    height: 2.5rem;
}

/* Cards grid */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    padding: 1rem;
}

/* Subject card styling */
.subject-card {
    height: 200px;
    width: 100%;
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    transition: all 0.4s ease;
    position: relative;
}

.subject-card:hover {
    transform: translateY(-8px);
    box-shadow:
        0 15px 30px rgba(0, 0, 0, 0.15),
        0 0 25px rgba(74, 135, 213, 0.4);
    border: 1px solid rgba(74, 135, 213, 0.5);
}

.subject-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        120deg,
        transparent,
        rgba(255, 255, 255, 0.3),
        transparent
    );
    transition: all 0.6s ease;
}

.subject-card:hover::before {
    transform: translateX(100%);
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

.symbol {
    position: absolute;
    color: rgba(255, 255, 255, 0.3);
    font-family: 'Courier New', monospace;
    pointer-events: none;
    z-index: 1;
    animation: float-symbol 8s linear infinite;
    font-weight: bold;
    font-size: 20px;
}

.subject-card:nth-child(3n) .symbol {
    animation-duration: 10s;
}

.subject-card:nth-child(3n+1) .symbol {
    animation-duration: 7s;
}

.subject-card .symbol:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
.subject-card .symbol:nth-child(2) { top: 30%; left: 80%; animation-delay: 1s; }
.subject-card .symbol:nth-child(3) { top: 70%; left: 30%; animation-delay: 2s; }
.subject-card .symbol:nth-child(4) { top: 60%; left: 70%; animation-delay: 3s; }
.subject-card .symbol:nth-child(5) { top: 20%; left: 50%; animation-delay: 4s; }

@keyframes float-symbol {
    0% {
        transform: translateY(0) translateX(0) rotate(0deg);
        opacity: 0.1;
    }
    20% {
        opacity: 0.3;
    }
    80% {
        opacity: 0.3;
    }
    100% {
        transform: translateY(-20px) translateX(10px) rotate(360deg);
        opacity: 0.1;
    }
}

.card-content {
    height: 100%;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    z-index: 2;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.subject-title {
    color: white;
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    text-align: left;
    text-shadow: none;
}

.year-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: 600;
    margin: 0;
    backdrop-filter: blur(5px);
}

.card-body {
    color: rgba(255, 255, 255, 0.9);
    margin: 1rem 0;
}

.card-body p {
    margin: 0;
    line-height: 1.5;
}

.card-body .no-description {
    color: rgba(255, 255, 255, 0.7);
    font-style: italic;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
}

.status-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-size: 0.9rem;
    background: rgba(255, 255, 255, 0.2);
    color: white;
    display: flex;
    align-items: center;
    height: 2rem;
}

.status-badge.active {
    background: rgba(46, 213, 115, 0.3);
}

.card-actions {
    display: flex;
    gap: 0.5rem;
}

/* Empty state styling */
.empty-state {
    text-align: center;
    padding: 3rem 2rem;
    background: rgba(211, 233, 255, 0.7);
    border-radius: 16px;
    color: #1a365d;
    margin: 2rem 0;
}

.empty-icon {
    font-size: 3rem;
    color: rgba(74, 135, 213, 0.5);
    margin-bottom: 1rem;
}

.empty-actions {
    margin-top: 1.5rem;
}

/* Global button styles with consistent height */
:deep(.p-button) {
    border-radius: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    height: 2.5rem;
}

:deep(.p-button.p-button-rounded) {
    border-radius: 50%;
    width: 2.5rem;
    height: 2.5rem;
    padding: 0;
}

:deep(.p-button:hover) {
    transform: translateY(-2px);
    box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
}

/* Dialog form styling */
.dialog-form-container {
    position: relative;
    overflow: hidden;
    padding: 2rem 1.5rem;
}

.dialog-particle {
    position: absolute;
    width: 10px;
    height: 10px;
    background: rgba(74, 135, 213, 0.15);
    border-radius: 50%;
    opacity: 0.5;
}

.dialog-particle:nth-child(1) {
    top: 20%;
    left: 10%;
    width: 15px;
    height: 15px;
    animation: float 25s infinite;
}

.dialog-particle:nth-child(2) {
    top: 60%;
    right: 10%;
    width: 12px;
    height: 12px;
    animation: float 20s infinite reverse;
}

.dialog-particle:nth-child(3) {
    bottom: 20%;
    left: 30%;
    width: 8px;
    height: 8px;
    animation: float 15s infinite;
}

.animated-field {
    transition: transform 0.3s ease, opacity 0.3s ease;
    transform: translateY(0);
    opacity: 1;
}

.animated-field:hover {
    transform: translateY(-3px);
}

.dialog-footer-buttons {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
}

:deep(.save-button-custom) {
    background: linear-gradient(135deg, #4a87d5, #6b9de8);
    border: none;
    font-weight: 600;
    letter-spacing: 0.5px;
}

:deep(.add-button) {
    background: linear-gradient(135deg, #4a87d5, #6b9de8);
    border: none;
    font-weight: 600;
    letter-spacing: 0.5px;
}

:deep(.archive-button) {
    background: linear-gradient(135deg, #64748b, #94a3b8);
    border: none;
    font-weight: 600;
    letter-spacing: 0.5px;
}

:deep(.p-dialog) {
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
}

:deep(.p-dialog .p-dialog-header) {
    background: linear-gradient(135deg, #4a87d5, #6b9de8);
    color: white;
    padding: 1.25rem 1.5rem;
}

:deep(.p-dialog .p-dialog-title) {
    color: white;
    font-weight: 600;
}

:deep(.p-dialog .p-dialog-header-icon) {
    color: white;
}

:deep(.p-dialog .p-dialog-content) {
    background: rgba(245, 250, 255, 0.95);
    padding: 1.5rem;
    backdrop-filter: blur(10px);
}

:deep(.p-dialog .p-dialog-footer) {
    background: rgba(245, 250, 255, 0.95);
    border-top: 1px solid rgba(74, 135, 213, 0.1);
    padding: 1rem 1.5rem;
}

/* Grade cards styling - update to match curriculum cards */
.grade-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
    padding: 1rem;
    margin-top: 2rem;
}

.grade-badge {
    background: rgba(255, 255, 255, 0.2) !important;
    color: white !important;
    padding: 0.5rem 1rem !important;
    border-radius: 20px !important;
    font-weight: 600 !important;
    margin: 0 !important;
    backdrop-filter: blur(5px) !important;
}

.order-text {
    color: rgba(255, 255, 255, 0.9);
    font-size: 1.1rem;
    margin: 0.5rem 0;
}

/* Override any specific grade card styles to use subject card styles */
.grade-card {
    height: 200px;
    width: 100%;
    border-radius: 16px;
    overflow: hidden;
    cursor: pointer;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    transition: all 0.4s ease;
    position: relative;
}

.grade-card:hover {
    transform: translateY(-8px);
    box-shadow:
        0 15px 30px rgba(0, 0, 0, 0.15),
        0 0 25px rgba(74, 135, 213, 0.4);
    border: 1px solid rgba(74, 135, 213, 0.5);
}

.grade-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        120deg,
        transparent,
        rgba(255, 255, 255, 0.3),
        transparent
    );
    transition: all 0.6s ease;
}

.grade-card:hover::before {
    transform: translateX(100%);
    animation: shimmer 1.5s infinite;
}

/* Dialog header styling */
.dialog-header {
    border-bottom: 1px solid rgba(74, 135, 213, 0.2);
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dialog-subtitle {
    color: #1a365d;
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 0.5rem 0;
}

.dialog-description {
    color: #64748b;
    margin: 0;
}

/* Loading container styling */
.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    text-align: center;
    color: #1a365d;
}

.loading-container p {
    margin-top: 1.5rem;
    font-size: 1.1rem;
}

:deep(.clear-filter) {
    width: 2.5rem;
    height: 2.5rem;
    background: rgba(74, 135, 213, 0.1);
    border: none;
    color: #4a87d5;
    box-shadow: none;
}

:deep(.clear-filter:hover) {
    background: rgba(74, 135, 213, 0.2);
}
</style>
