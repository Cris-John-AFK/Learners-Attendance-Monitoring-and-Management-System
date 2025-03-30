<script setup>
import axios from 'axios';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
// TODO: Dropdown is deprecated since PrimeVue v4. Consider migrating to Select component
import TeacherAssignmentWizard from '@/components/TeacherAssignmentWizard.vue';
import TeacherSubjectAdder from '@/components/TeacherSubjectAdder.vue';
import Badge from 'primevue/badge';
import { default as Dropdown } from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';
// Add import for the new TeacherSectionAssigner component
import TeacherSectionAssigner from '@/components/TeacherSectionAssigner.vue';

// API configuration with multiple endpoints to try
const API_ENDPOINTS = [
    'http://localhost:8000/api',
    'http://127.0.0.1:8000/api'
];

let API_BASE_URL = API_ENDPOINTS[0];  // Default API endpoint

// Helper function to try multiple API endpoints until one works
const tryApiEndpoints = async (path, options = {}) => {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
    };

    const requestOptions = { ...defaultOptions, ...options };
    let lastError = null;

    // Log attempt to console
    console.log(`Attempting to fetch data from ${path} using multiple endpoints...`);

    // Try each endpoint in sequence
    for (let baseUrl of API_ENDPOINTS) {
        try {
            const url = `${baseUrl}${path}`;
            console.log(`Trying endpoint: ${url}`);

            // Use axios for the request
            const response = await axios(url, requestOptions);

            // Update the working base URL for future requests
            API_BASE_URL = baseUrl;
            console.log(`Endpoint ${baseUrl} is working! Data retrieved successfully.`);

            // Return the data (either response.data or response.data.data depending on API structure)
            return response.data?.data || response.data;
        } catch (error) {
            console.warn(`Endpoint ${baseUrl} failed:`, error.message);
            lastError = error;
            // Continue to the next endpoint
        }
    }

    // If we get here, all endpoints failed
    console.error('All API endpoints failed:', lastError);
    // Return empty array instead of throwing error, so the component won't crash
    return [];
};

const toast = useToast();
const confirm = useConfirm();
const teachers = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const expandedRows = ref([]);
const teacherDialog = ref(false);
const assignmentDialogVisible = ref(false);
const assignmentWizardDialog = ref(false);
const assignmentWizardMode = ref('new'); // 'new' or 'add-subjects'
const deleteTeacherDialog = ref(false);
const teacherDetailsDialog = ref(false);
const sectionsDialog = ref(false);
const createSectionDialog = ref(false);
const assignSectionDialog = ref(false);
const assignmentsDialog = ref(false);
const subjectSelectionDialog = ref(false);
const submitted = ref(false);
const sections = ref([]);
const subjects = ref([]);
const selectedTeacher = ref({}); // Initialize with empty object instead of null
const editedTeacher = ref(null);
const selectedSubject = ref(null);
const selectedGrade = ref(null);
const selectedSection = ref(null);
const subjectSearchQuery = ref('');
const selectedSubjects = ref([]);
const availableSubjects = ref([]);
const newSection = ref({ name: '', studentsCount: 0 });

// Form models
const teacher = ref({
    first_name: '',
    last_name: '',
    email: '',
    username: '',
    password: '',
    phone_number: '',
    address: '',
    date_of_birth: null,
    gender: null,
    is_head_teacher: false,
    is_active: true
});

const assignment = ref({
    section_id: null,
    subject_id: null,
    is_primary: false,
    role: null
});

const assignmentGrade = ref(null);
const assignmentErrors = ref([]);
const filteredSections = ref([]);
const gradeOptions = ref([]);
const subjectOptions = ref([]);
const teacherRoleOptions = [
    { label: 'Primary Teacher', value: 'primary' },
    { label: 'Subject Teacher', value: 'subject' },
    { label: 'Special Education Teacher', value: 'special_education' },
    { label: 'Teaching Assistant', value: 'assistant' }
];

// Options
const genderOptions = [
    { label: 'Male', value: 'male' },
    { label: 'Female', value: 'female' },
    { label: 'Other', value: 'other' }
];

// Computed properties
const dialogTitle = computed(() => {
    return teacher.value.id ? 'Edit Teacher' : 'Register Teacher';
});

// Add computed property for the API base URL
const apiBaseUrl = computed(() => {
    return API_BASE_URL;
});

const filteredTeachers = computed(() => {
    if (!searchQuery.value) return teachers.value;

    return teachers.value.filter(t => {
        const fullName = `${t.first_name} ${t.last_name}`.toLowerCase();
        return fullName.includes(searchQuery.value.toLowerCase());
    });
});

const filteredSubjects = computed(() => {
    if (!subjectSearchQuery.value) return availableSubjects.value;
    return availableSubjects.value.filter(s =>
        s.name.toLowerCase().includes(subjectSearchQuery.value.toLowerCase()) ||
        s.grade.toLowerCase().includes(subjectSearchQuery.value.toLowerCase())
    );
});

const getSubjectStatusSeverity = (subject) => {
    if (subject.status === 'active') return 'success';
    if (subject.status === 'pending') return 'warning';
            return 'danger';
};

const viewTeacher = (teacherData) => {
    selectedTeacher.value = teacherData;
    teacherDetailsDialog.value = true;
};

const onGradeChange = () => {
    selectedSection.value = null;
    // Load sections for selected grade
    // This would be implemented based on your backend API
};

const assignSection = async () => {
    try {
        const response = await fetch(`http://localhost:8000/api/teachers/${selectedTeacher.value.id}/sections`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                section_id: selectedSection.value
            })
        });

        if (!response.ok) {
            throw new Error('Failed to assign section');
        }

        await loadTeachers();
        assignSectionDialog.value = false;
        toast.add({ severity: 'success', summary: 'Success', detail: 'Section assigned successfully', life: 3000 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: error.message, life: 3000 });
    }
};

const addSubjectToSelection = (subject) => {
    if (!selectedSubjects.value.some(s => s.id === subject.id)) {
        selectedSubjects.value.push(subject);
    }
};

const removeSubjectFromSelection = (subject) => {
    selectedSubjects.value = selectedSubjects.value.filter(s => s.id !== subject.id);
};

const saveSelectedSubjects = async () => {
    try {
        const response = await fetch(`http://localhost:8000/api/teachers/${selectedTeacher.value.id}/subjects`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                subject_ids: selectedSubjects.value.map(s => s.id)
            })
        });

        if (!response.ok) {
            throw new Error('Failed to assign subjects');
        }

        await loadTeachers();
        subjectSelectionDialog.value = false;
    selectedSubjects.value = [];
        toast.add({ severity: 'success', summary: 'Success', detail: 'Subjects assigned successfully', life: 3000 });
    } catch (error) {
        toast.add({ severity: 'error', summary: 'Error', detail: error.message, life: 3000 });
    }
};

// Methods
const loadTeachers = async () => {
    try {
        loading.value = true;
        toast.add({
            severity: 'info',
            summary: 'Loading',
            detail: 'Fetching teachers from server...',
            life: 2000
        });

        // Use the tryApiEndpoints helper to handle multiple endpoints
        const data = await tryApiEndpoints('/teachers');

        if (!data || data.length === 0) {
            console.warn('No teachers returned from API');
            toast.add({
                severity: 'warn',
                summary: 'No Teachers',
                detail: 'No teachers found in the database. Please add teachers using the Register button.',
                life: 5000
            });
            teachers.value = [];
            loading.value = false;
            return;
        }

        // Load supporting data first
        await Promise.all([
            sections.value.length === 0 ? loadSections() : Promise.resolve(),
            subjects.value.length === 0 ? loadSubjects() : Promise.resolve(),
            gradeOptions.value.length === 0 ? loadGrades() : Promise.resolve()
        ]);

        // Process teachers data with proper assignment handling
        teachers.value = data.map(teacher => {
            console.log('Processing teacher data:', teacher);
            console.log('Assignments from API:', teacher.assignments);

            // Initialize arrays for different types of assignments
            let primaryAssignment = null;
            let subjectAssignments = [];

            // Process assignments if present
            if (teacher.assignments && Array.isArray(teacher.assignments)) {
                // Filter valid assignments
                const validAssignments = teacher.assignments.filter(a =>
                    a && a.section_id && a.subject_id);

                console.log('Valid assignments:', validAssignments);

                // Process each assignment
                validAssignments.forEach(assignment => {
                    // Find section and subject objects
                    const sectionObj = sections.value.find(s =>
                        Number(s.id) === Number(assignment.section_id)) || {
                        id: Number(assignment.section_id),
                        name: `Section ${assignment.section_id}`,
                        grade_id: null,
                        grade: null
                    };

                    const subjectObj = subjects.value.find(s =>
                        Number(s.id) === Number(assignment.subject_id)) || {
                        id: Number(assignment.subject_id),
                        name: `Subject ${assignment.subject_id}`
                    };

                    console.log(`Assignment ${assignment.id} - Subject:`, subjectObj);
                    console.log(`Assignment ${assignment.id} - is_primary:`, assignment.is_primary);
                    console.log(`Assignment ${assignment.id} - role:`, assignment.role);

                    const processedAssignment = {
                        id: assignment.id,
                        section_id: Number(assignment.section_id),
                        subject_id: Number(assignment.subject_id),
                        is_primary: assignment.is_primary === true,
                        role: assignment.role || 'subject',
                        is_active: assignment.is_active !== undefined ? assignment.is_active : true,
                        section: sectionObj,
                        subject: subjectObj
                    };

                    // Sort assignments into primary vs subject
                    if (processedAssignment.is_primary || processedAssignment.role === 'primary') {
                        // Update to ensure consistency
                        processedAssignment.is_primary = true;
                        processedAssignment.role = 'primary';
                        primaryAssignment = processedAssignment;
                        console.log('Found primary assignment:', primaryAssignment);
                    } else {
                        subjectAssignments.push(processedAssignment);
                    }
                });
            }

            console.log('Primary assignment for teacher:', primaryAssignment);
            console.log('Subject assignments for teacher:', subjectAssignments);

            // Return teacher with organized assignments
            return {
                ...teacher,
                primary_assignment: primaryAssignment,
                subject_assignments: subjectAssignments,
                active_assignments: [...(primaryAssignment ? [primaryAssignment] : []), ...subjectAssignments]
            };
        });

        console.log('Successfully processed teachers with organized assignment data:', teachers.value);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Loaded ${teachers.value.length} teachers successfully`,
            life: 3000
        });
    } catch (error) {
        console.error('Error loading teachers:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: `Failed to load teachers: ${error.message}`,
            life: 5000
        });

        teachers.value = [];
    } finally {
        loading.value = false;
    }
};

const loadSections = async () => {
    try {
        toast.add({
            severity: 'info',
            summary: 'Loading',
            detail: 'Fetching sections from server...',
            life: 2000
        });

        // Make a direct API call instead of using tryApiEndpoints to ensure we're using the latest API_BASE_URL
        const response = await axios.get(`${API_BASE_URL}/sections`);
        const data = response.data || [];
        console.log('Raw section data from API:', data);

        if (!data || data.length === 0) {
            console.warn('No sections returned from API');
            toast.add({
                severity: 'warn',
                summary: 'No Sections',
                detail: 'No sections found in the database.',
                life: 5000
            });
            sections.value = [];
            return;
        }

        // Map the data to ensure consistent structure AND ensure IDs are numbers
        sections.value = data.map(section => ({
            id: Number(section.id), // Ensure ID is a number
            name: section.name || `Section ${section.id}`,
            grade_id: Number(section.grade_id), // Ensure grade_id is a number
            grade: section.grade ? {
                id: Number(section.grade.id),  // Ensure grade.id is a number
                name: section.grade.name || `Grade ${section.grade.id}`
            } : {
                id: Number(section.grade_id),
                name: `Grade ${section.grade_id}`
            },
            room_number: section.room_number || 'N/A'
        }));

        console.log('Successfully loaded sections with normalized IDs:', sections.value);

        // Show success notification
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Loaded ${sections.value.length} sections successfully`,
            life: 3000
        });
    } catch (error) {
        console.error('Error loading sections:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: `Failed to load sections: ${error.message}`,
            life: 5000
        });

        // Initialize with empty array instead of fallback data
        sections.value = [];
    }
};

const loadSubjects = async () => {
    try {
        toast.add({
            severity: 'info',
            summary: 'Loading',
            detail: 'Fetching subjects from server...',
            life: 2000
        });

        // Make a direct API call instead of using tryApiEndpoints
        const response = await axios.get(`${API_BASE_URL}/subjects`);
        const data = response.data || [];
        console.log('Raw subject data from API:', data);

        if (!data || data.length === 0) {
            console.warn('No subjects returned from API');
            toast.add({
                severity: 'warn',
                summary: 'No Subjects',
                detail: 'No subjects found in the database.',
                life: 5000
            });
            subjects.value = [];
            subjectOptions.value = [];
            return;
        }

        // Ensure subject IDs are numbers
        subjects.value = data.map(subject => ({
            id: Number(subject.id),
            name: subject.name || `Subject ${subject.id}`,
            department: subject.department || 'General',
            grade_id: subject.grade_id ? Number(subject.grade_id) : null,
            grade: subject.grade ? {
                id: Number(subject.grade.id),
                name: subject.grade.name
            } : null
        }));

        console.log('Successfully loaded subjects with normalized IDs:', subjects.value);

        // Initialize subjectOptions with the loaded subjects for dropdowns
        subjectOptions.value = subjects.value.map(subject => ({
            id: Number(subject.id),
            name: subject.name,
            department: subject.department || 'General'
        }));

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Loaded ${subjects.value.length} subjects successfully`,
            life: 3000
        });
    } catch (error) {
        console.error('Error loading subjects:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load subjects.',
            life: 3000
        });

        // Initialize with empty arrays instead of fallback data
        subjects.value = [];
        subjectOptions.value = [];
    }
};

const loadGrades = async () => {
    try {
        toast.add({
            severity: 'info',
            summary: 'Loading',
            detail: 'Fetching grades from server...',
            life: 2000
        });

        // Make a direct API call instead of using tryApiEndpoints
        const response = await axios.get(`${API_BASE_URL}/grades`);
        const data = response.data || [];
        console.log('Raw grade data from API:', data);

        if (!data || data.length === 0) {
            console.warn('No grades returned from API');
            toast.add({
                severity: 'warn',
                summary: 'No Grades',
                detail: 'No grades found in the database.',
                life: 5000
            });
            gradeOptions.value = [];
            return;
        }

        // Ensure proper mapping with correct grade ID values
        gradeOptions.value = data.map(grade => ({
            id: Number(grade.id),
            name: grade.name || `Grade ${grade.id}`,
            value: Number(grade.id) // Add value property for dropdown compatibility
        }));

        console.log('Successfully loaded grades from API:', gradeOptions.value);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Loaded ${gradeOptions.value.length} grades successfully`,
            life: 3000
        });
    } catch (error) {
        console.warn('Error loading grades from API:', error);

        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Could not load grades from server.',
            life: 4000
        });

        // Initialize with empty array instead of fallback data
        gradeOptions.value = [];
    }
};

// Load subjects based on grade ID
const loadGradeSubjects = (gradeId) => {
    console.log('Loading subjects for grade ID:', gradeId);

    // Format already loaded subjects for dropdown
    if (subjects.value.length > 0) {
        console.log('Using already loaded subjects:', subjects.value);

        // Format subjects for dropdown
        subjectOptions.value = subjects.value.map(subject => ({
            value: String(subject.id), // Convert to string to ensure consistent comparison
            label: subject.name
        }));

        console.log('Subject options set:', subjectOptions.value);

        if (subjectOptions.value.length === 0) {
            toast.add({
                severity: 'warn',
                summary: 'No Subjects',
                detail: `No subjects available. You may need to create subjects first.`,
                life: 5000
            });
        } else {
            toast.add({
                severity: 'info',
                summary: 'Subjects Loaded',
                detail: `Loaded ${subjectOptions.value.length} subjects successfully.`,
                life: 3000
            });
        }
        return;
    }

    // If no subjects are loaded yet, load them from the API
    axios.get(`${API_BASE_URL}/subjects`)
        .then(response => {
            // Ensure we have a proper response with data
            if (response.data) {
                const subjectsData = response.data.data || response.data;
                console.log('Subjects loaded from API:', subjectsData);

                // Update the subjects ref
                subjects.value = subjectsData;

                // Format subjects for dropdown
                if (subjects.value.length === 0) {
                    toast.add({
                        severity: 'warn',
                        summary: 'No Subjects',
                        detail: 'No subjects available. You may need to create subjects first.',
                        life: 5000
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error loading subjects:', error);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load subjects',
                life: 5000
            });
        });
};

const hideAssignmentDialog = () => {
    assignmentDialogVisible.value = false;
    assignmentErrors.value = [];
    assignmentGrade.value = null;
    assignment.value = {
        section_id: null,
        subject_id: null,
        is_primary: false,
        role: null
    };
};

// Improved assignment validation
const validateAssignment = () => {
    assignmentErrors.value = [];
    let hasErrors = false;

    // Basic required fields validation
    if (!assignmentGrade.value) {
        assignmentErrors.value.push('Please select a grade level');
        hasErrors = true;
    }

    if (!assignment.value.section_id) {
        assignmentErrors.value.push('Please select a section');
        hasErrors = true;
    }

    if (!assignment.value.subject_id) {
        assignmentErrors.value.push('Please select a subject');
        hasErrors = true;
    }

    if (!assignment.value.role) {
        assignmentErrors.value.push('Please select a teaching role');
        hasErrors = true;
    }

    // Early return for basic validation
    if (hasErrors) {
        return false;
    }

    // Format IDs consistently for comparison
    const sectionId = Number(assignment.value.section_id);
    const subjectId = Number(assignment.value.subject_id);
    const isPrimary = assignment.value.role === 'primary' || assignment.value.is_primary === true;

    // Current teacher's existing assignments
    if (editedTeacher.value && editedTeacher.value.active_assignments) {
        // Check for existing identical assignment (excluding currently editing assignment)
        const existingAssignment = editedTeacher.value.active_assignments.find(a =>
            Number(a.section_id) === sectionId &&
            Number(a.subject_id) === subjectId &&
            (!assignment.value.id || a.id !== assignment.value.id)
        );

        if (existingAssignment) {
            assignmentErrors.value.push(`This teacher is already assigned to this section and subject (${existingAssignment.role || 'subject'} role)`);
            hasErrors = true;
        }

        // Check primary teacher conflict if this is a primary assignment
        if (isPrimary) {
            // Check if teacher already has a different primary assignment
            const existingPrimary = editedTeacher.value.active_assignments.find(a =>
                (a.is_primary || a.role === 'primary') &&
                (Number(a.section_id) !== sectionId || Number(a.subject_id) !== subjectId) &&
                (!assignment.value.id || a.id !== assignment.value.id)
            );

            if (existingPrimary) {
                assignmentErrors.value.push(`This teacher is already a primary teacher for ${existingPrimary.section?.name || 'another section'}. A teacher can only be primary for one section.`);
                hasErrors = true;
            }
        }
    }

    // Check if another teacher is already the primary for this section
    if (isPrimary && teachers.value && teachers.value.length > 0) {
        for (const teacher of teachers.value) {
            // Skip checking the current teacher
            if (editedTeacher.value && teacher.id === editedTeacher.value.id) {
                continue;
            }

            // Check if any other teacher is primary for this section
            const conflictingPrimary = teacher.active_assignments?.find(a =>
                Number(a.section_id) === sectionId &&
                (a.is_primary || a.role === 'primary')
            );

            if (conflictingPrimary) {
                assignmentErrors.value.push(`${teacher.first_name} ${teacher.last_name} is already the primary teacher for this section. Each section can only have one primary teacher.`);
                hasErrors = true;
                break;
            }
        }
    }

    // Check for duplicate subject assignments across teachers (non-primary roles)
    if (!isPrimary && teachers.value && teachers.value.length > 0) {
        for (const teacher of teachers.value) {
            // Skip checking the current teacher
            if (editedTeacher.value && teacher.id === editedTeacher.value.id) {
                continue;
            }

            // For subject teachers, check if this specific subject is already assigned
            const conflictingAssignment = teacher.active_assignments?.find(a =>
                Number(a.section_id) === sectionId &&
                Number(a.subject_id) === subjectId
            );

            if (conflictingAssignment) {
                assignmentErrors.value.push(`This section and subject is already assigned to teacher ${teacher.first_name} ${teacher.last_name}`);
                hasErrors = true;
                break;
            }
        }
    }

    return !hasErrors;
};

// Improved payload preparation for API
const prepareAssignmentPayload = () => {
    // Get IDs as numbers for consistency
    const sectionId = Number(assignment.value.section_id);
    const subjectId = Number(assignment.value.subject_id);

    // Determine if this is a primary role
    const isPrimary = assignment.value.role === 'primary' || assignment.value.is_primary === true;

    // Normalize role and is_primary to be consistent
    let role = assignment.value.role;
    let is_primary = isPrimary;

    // Ensure consistency between role and is_primary
    if (role === 'primary') {
        is_primary = true;
    }
    if (is_primary && role !== 'primary') {
        role = 'primary';
    }

    // Prepare the assignment data
    const assignmentData = {
        section_id: sectionId,
        subject_id: subjectId,
        is_primary: is_primary,
        role: role
    };

    // If editing an existing assignment, include its ID
    if (assignment.value.id) {
        assignmentData.id = assignment.value.id;
    }

    return {
        assignments: [assignmentData]
    };
};

// Improved assignment saving with better error handling
const saveAssignment = async () => {
    try {
        // Basic validation
        if (!assignment.value.section_id || !assignment.value.subject_id || !assignment.value.role) {
            toast.add({
                severity: 'error',
                summary: 'Missing Required Fields',
                detail: 'Please select grade, section, subject, and role',
                life: 3000
            });
            return;
        }

        // Full validation
        if (!validateAssignment()) {
            return; // Validation errors are already in assignmentErrors.value
        }

        loading.value = true;
        const teacherId = editedTeacher.value.id;

        // Prepare the payload
        const payload = prepareAssignmentPayload();
        console.log(`Saving assignment with payload:`, JSON.stringify(payload, null, 2));

        // Send request to API
        const response = await axios.put(
            `${API_BASE_URL}/teachers/${teacherId}/assignments`,
            payload
        ).catch(error => {
            // Detailed error handling
            if (error.response) {
                // Server responded with an error status
                const statusCode = error.response.status;
                const errorData = error.response.data;

                if (statusCode === 422) {
                    // Validation error
                    let message = 'Validation error';
                    if (errorData.errors) {
                        message = Object.values(errorData.errors).flat().join(', ');
                    } else if (errorData.message) {
                        message = errorData.message;
                    }
                    throw new Error(message);
                }
                else if (statusCode === 409 ||
                    (statusCode === 500 && errorData.message &&
                     (errorData.message.includes('already assigned') ||
                      errorData.message.includes('duplicate') ||
                      errorData.message.includes('unique constraint')))) {
                    // Conflict error or constraint violation
                    throw new Error('This assignment already exists or conflicts with existing assignments. Please check the current assignments and try again.');
                }
                else if (errorData.message) {
                    throw new Error(errorData.message);
                }
            }

            // Default error
            throw error;
        });

        console.log('Assignment saved successfully:', response.data);

        // Close dialog
        hideAssignmentDialog();

        // Force refresh to get the latest data
        await forceRefreshTeacher(teacherId);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Subject assigned successfully',
            life: 3000
        });
    } catch (error) {
        console.error('Error saving assignment:', error);

        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: `Failed to save assignment: ${error.message}`,
            life: 5000
        });

        assignmentErrors.value = [error.message];
    } finally {
        loading.value = false;
    }
};

// Function to save assignments to backend
const saveAssignmentToBackend = async (teacherId, assignments) => {
    try {
        console.log('Saving assignments to backend for teacher ID:', teacherId);

        // Check for potential conflicts with server data before saving
        const conflicts = await checkServerAssignmentConflicts(assignments);
        if (conflicts.length > 0) {
            // Return a standardized error object for a better UX
            throw {
                isValidationError: true,
                message: conflicts[0],
                conflicts
            };
        }

        const response = await axios.put(
            `${API_BASE_URL}/teachers/${teacherId}/assignments`,
            { assignments: assignments.map(a => ({
                section_id: a.section_id,
                subject_id: a.subject_id,
                is_primary: a.is_primary || false,
                role: a.role || 'Teacher'
            })) }
        );

        console.log('Backend save response:', response.data);

        // Refresh teacher data to ensure we have the latest from the server
        await loadTeachers();

        return response.data;
    } catch (error) {
        console.error('Failed to save assignments to backend:', error);

        // If it's already our custom error, just rethrow it
        if (error.isValidationError) {
            throw error;
        }

        // Check if this is a validation error response from server
        if (error.response) {
            // Handle 500 error that's actually a validation error (not ideal API design)
            if (error.response.status === 500) {
                // Check if error message contains information about duplicate assignment
                const errorMessage = error.response.data?.message || error.message;
                if (errorMessage.includes('duplicate') ||
                    errorMessage.includes('already assigned') ||
                    errorMessage.includes('SQLSTATE[23000]') || // SQL integrity constraint error
                    errorMessage.includes('constraint') ||
                    errorMessage.toLowerCase().includes('unique')) {

                    // Show a user-friendly validation error
                    toast.add({
                        severity: 'warn',
                        summary: 'Validation Error',
                        detail: 'This teacher is already assigned to this section and subject',
                        life: 5000
                    });

                    // Refresh data to get current state from server
                    await loadTeachers();

                    // Return a standardized error object
                    throw {
                        isValidationError: true,
                        message: 'This teacher is already assigned to this section and subject'
                    };
                }
            }

            // Handle proper validation responses (422 status)
            if (error.response.status === 422) {
                const validationErrors = error.response.data.errors || {};
                const errorMessages = Object.values(validationErrors).flat();

                toast.add({
                    severity: 'warn',
                    summary: 'Validation Error',
                    detail: errorMessages.join(', ') || 'Invalid assignment data',
                    life: 5000
                });

                // Return a standardized error object
                throw {
                    isValidationError: true,
                    message: errorMessages.join(', ') || 'Invalid assignment data'
                };
            }
        }

        // For other types of errors, rethrow with a better message
        throw {
            isValidationError: false,
            original: error,
            message: error.message || 'Unknown server error'
        };
    }
};

// New function to check for existing conflicts on the server before saving
const checkServerAssignmentConflicts = async (assignments) => {
    if (!assignments || assignments.length === 0) return [];

    try {
        // Preemptively check for assignment conflicts on the server
        const conflicts = [];

        // Check each assignment
        for (const assignment of assignments) {
            if (!assignment.section_id || !assignment.subject_id) continue;

            // Check if any teacher is already assigned to this section-subject combination
            const checkResponse = await axios.get(
                `${API_BASE_URL}/check-assignment?section_id=${assignment.section_id}&subject_id=${assignment.subject_id}`
            ).catch(error => {
                // If endpoint not available, don't block the operation
                console.warn('Assignment conflict check endpoint not available:', error);
                return { data: { exists: false } };
            });

            if (checkResponse?.data?.exists &&
                checkResponse?.data?.teacher_id &&
                checkResponse.data.teacher_id !== editedTeacher.value.id) {

                conflicts.push(`The section and subject is already assigned to another teacher (${checkResponse.data.teacher_name || 'Unknown Teacher'})`);
            }
        }

        // If endpoint wasn't available, try to check against local data
        if (conflicts.length === 0) {
            // Fallback to client-side check of other teachers
            for (const teacher of teachers.value) {
                // Skip the current teacher we're editing
                if (teacher.id === editedTeacher.value.id) continue;

                // Check each assignment
                for (const newAssignment of assignments) {
                    if (!newAssignment.section_id || !newAssignment.subject_id) continue;

                    // Look for conflicts
                    if (teacher.active_assignments && Array.isArray(teacher.active_assignments)) {
                        const hasConflict = teacher.active_assignments.some(a =>
                            String(a.section_id) === String(newAssignment.section_id) &&
                            String(a.subject_id) === String(newAssignment.subject_id)
                        );

                        if (hasConflict) {
                            conflicts.push(`This section and subject is already assigned to teacher ${teacher.first_name} ${teacher.last_name}`);
                        }
                    }
                }
            }
        }

        return conflicts;
    } catch (error) {
        console.warn('Error checking for assignment conflicts:', error);
        return []; // Don't block the operation if conflict check fails
    }
};

// Load sections for schedule
const loadSectionsForSchedule = async () => {
    try {
        // If the current subject has a grade ID, filter sections by that grade
        let gradeId = null;

        if (selectedSubjectForSchedule.value && selectedSubjectForSchedule.value.grade_id) {
            gradeId = selectedSubjectForSchedule.value.grade_id;
        }

        // If we have a grade ID, fetch sections for this grade from API
        if (gradeId) {
            try {
                const response = await axios.get(`${API_BASE_URL}/grades/${gradeId}/sections`);
                filteredSections.value = response.data.data || response.data;
            } catch (error) {
                console.warn('Error fetching sections by grade API, falling back to local filtering:', error);
                // Fallback to local filtering if API endpoint is not available
                filteredSections.value = sections.value.filter(section =>
                    Number(section.grade_id) === Number(gradeId)
                );
            }
        } else {
            // Otherwise, use all available sections
            if (sections.value.length === 0) {
                // Load sections if not already loaded
                await loadSections();
            }
            filteredSections.value = sections.value;
        }

        console.log('Loaded sections for scheduling:', filteredSections.value);
    } catch (error) {
        console.error('Error loading sections for schedule:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load sections for scheduling',
            life: 3000
        });
    }
};

const getInitials = (teacher) => {
    return teacher.first_name.charAt(0) + teacher.last_name.charAt(0);
};

// Function to open the teacher assignment wizard
const openAssignmentWizard = (teacherData) => {
    try {
        // Set the selected teacher and explicitly set new mode
        selectedTeacher.value = teacherData;
        assignmentWizardMode.value = 'new';

        console.log(`Opening assignment wizard for teacher: ${teacherData.first_name} ${teacherData.last_name}`);

        // Open the dialog
        assignmentWizardDialog.value = true;
    } catch (error) {
        console.error('Error opening assignment wizard:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to open assignment wizard',
            life: 3000
        });
    }
};

// Add a new method specifically for adding subjects
const openAddSubjectsDialog = (teacherData) => {
    if (!teacherData || !teacherData.id) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'No valid teacher selected',
            life: 3000
        });
        return;
    }

    try {
        // First, check if the teacher has any primary assignment
        const primaryAssignment = teacherData.primary_assignment ||
            (teacherData.active_assignments &&
            teacherData.active_assignments.find(a => a.is_primary || a.role === 'primary'));

        if (!primaryAssignment) {
            toast.add({
                severity: 'warn',
                summary: 'No Primary Assignment',
                detail: 'Teacher must be assigned as a primary teacher to a section first',
                life: 5000
            });
            return;
        }

        // Set the selected teacher and explicitly set add-subjects mode
        selectedTeacher.value = teacherData;
        assignmentWizardMode.value = 'add-subjects';

        console.log(`Opening add-subjects dialog for teacher: ${teacherData.first_name} ${teacherData.last_name}`);
        console.log(`Primary assignment:`, primaryAssignment);

        // Open the dialog
    assignmentWizardDialog.value = true;
    } catch (error) {
        console.error('Error opening add subjects dialog:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to open add subjects dialog',
            life: 3000
        });
    }
};

// Handle assignment completion
const handleAssignmentComplete = async (teacherId) => {
    console.log(`Assignment completed for teacher ${teacherId}`);

    try {
        // Show loading indicator
        loading.value = true;

        // Use the enhanced forceRefreshTeacher function that will refresh everything properly
        await forceRefreshTeacher(teacherId);

    toast.add({
        severity: 'success',
            summary: 'Success',
            detail: 'Teacher assignment updated successfully',
        life: 3000
    });
    } catch (error) {
        console.error('Error updating teacher assignments:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: `Failed to update assignments: ${error.message}`,
            life: 5000
        });
    } finally {
        loading.value = false;
    }
};

// Original function kept for backward compatibility
const openAssignmentDialog = (teacherData) => {
    // Reset the form and errors
    assignmentErrors.value = [];
    assignmentGrade.value = null;
    filteredSections.value = [];

    // Reset assignment data with default values
    assignment.value = {
        id: null,
        section_id: null,
        subject_id: null,
        is_primary: false,
        role: null
    };

    // Set the edited teacher
    editedTeacher.value = teacherData;
    console.log('Opening assignment dialog for teacher:', teacherData.first_name, teacherData.last_name);

    // Load initial data if not already loaded
    Promise.all([
        sections.value.length === 0 ? loadSections() : Promise.resolve(),
        subjects.value.length === 0 ? loadSubjects() : Promise.resolve(),
        gradeOptions.value.length === 0 ? loadGrades() : Promise.resolve()
    ]).then(() => {
        // Pre-populate with primary assignment if it exists
        if (teacherData.primary_assignment) {
            const primary = teacherData.primary_assignment;
            const gradeId = primary.section?.grade?.id || null;

            // Set values
            assignmentGrade.value = gradeId;
            assignment.value = {
                id: primary.id,
                section_id: primary.section_id,
                subject_id: primary.subject_id,
                is_primary: true,
                role: 'primary'
            };

            // Load sections for this grade
            if (gradeId) {
                handleGradeChange({ value: gradeId });
            }
        }

        // Show the dialog
        assignmentDialogVisible.value = true;
    });
};

// Handle role change
const handleRoleChange = (event) => {
    // If primary role is selected, automatically check the primary teacher checkbox
    if (event.value === 'primary') {
        assignment.value.is_primary = true;
    } else {
        // For other roles, leave is_primary as it is, allowing manual selection
        // This ensures the checkbox state matches the role selection
        console.log(`Role changed to ${event.value}, is_primary can be toggled manually`);
    }
};

// Edit teacher function
const editTeacher = (teacherData) => {
    // Reset the form
    teacher.value = { ...teacherData };
    submitted.value = false;
    teacherDialog.value = true;
    console.log('Edit teacher dialog opened for:', teacherData.first_name, teacherData.last_name);
};

// Missing function to open the new teacher dialog
const openNewTeacherDialog = () => {
    teacher.value = {
        first_name: '',
        last_name: '',
        email: '',
        username: '',
        password: '',
        phone_number: '',
        address: '',
        date_of_birth: null,
        gender: null,
        is_head_teacher: false,
        is_active: true
    };
    submitted.value = false;
    teacherDialog.value = true;
};

// Add missing schedule-related refs
const scheduleDialog = ref(false);
const selectedSubjectForSchedule = ref(null);
const scheduleData = ref([]);
const daysOfWeek = ref(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);
const timeSlots = ref([
    '7:00 AM - 8:00 AM',
    '8:00 AM - 9:00 AM',
    '9:00 AM - 10:00 AM',
    '10:00 AM - 11:00 AM',
    '11:00 AM - 12:00 PM',
    '1:00 PM - 2:00 PM',
    '2:00 PM - 3:00 PM',
    '3:00 PM - 4:00 PM',
    '4:00 PM - 5:00 PM'
]);
const newScheduleItem = ref({
    day: null,
    timeSlot: null,
    room: '',
    section_id: null
});

// Open schedule dialog
const openScheduleDialog = (subject) => {
    selectedSubjectForSchedule.value = subject;
    loadSubjectSchedule(subject);
    scheduleDialog.value = true;
};

// Function to load schedule for a subject
const loadSubjectSchedule = async (subject) => {
    try {
        console.log(`Loading schedule for ${subject.name}`);
        loading.value = true;

        // Fetch schedule data from API
        const response = await axios.get(
            `${API_BASE_URL}/teachers/${selectedTeacher.value.id}/subjects/${subject.id}/schedule`
        ).catch(error => {
            console.warn('Schedule API error:', error);
            // If API endpoint not found, handle gracefully
            if (error.response && error.response.status === 404) {
                return { data: [] };
            }
            throw error;
        });

        // Process and set schedule data
        scheduleData.value = Array.isArray(response.data) ? response.data :
                             (response.data.data || []);

        console.log('Loaded schedule data:', scheduleData.value);
    } catch (error) {
        console.error('Error loading schedule:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load schedule data from database',
            life: 3000
        });
        scheduleData.value = [];
    } finally {
        loading.value = false;
    }
};

// Function to save a schedule item
const saveScheduleItem = async () => {
    // Validate required fields
    if (!newScheduleItem.value.day || !newScheduleItem.value.timeSlot || !newScheduleItem.value.section_id) {
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: 'Please fill all required fields',
            life: 3000
        });
        return;
    }

    // Check for conflicts (same day and time)
    const hasConflict = scheduleData.value.some(item =>
        item.day === newScheduleItem.value.day &&
        item.timeSlot === newScheduleItem.value.timeSlot
    );

    if (hasConflict) {
        toast.add({
            severity: 'warn',
            summary: 'Schedule Conflict',
            detail: 'This time slot is already scheduled',
            life: 3000
        });
        return;
    }

    try {
        // Find section name for display
        const selectedSection = filteredSections.value.find(
            s => String(s.id) === String(newScheduleItem.value.section_id)
        );

        // Create schedule item object
        const scheduleItem = {
            day: newScheduleItem.value.day,
            time_slot: newScheduleItem.value.timeSlot,
            room: newScheduleItem.value.room,
            section_id: newScheduleItem.value.section_id,
            subject_id: selectedSubjectForSchedule.value.id,
            teacher_id: selectedTeacher.value.id
        };

        // Save the schedule item via API
        const response = await axios.post(
            `${API_BASE_URL}/schedules`,
            scheduleItem
        );

        // Get the saved item with its ID
        const savedItem = response.data;

        // Add the new item to the local state with section name for display
        scheduleData.value.push({
            ...savedItem,
            section_name: selectedSection?.name || 'Unknown Section'
        });

        // Reset form
        newScheduleItem.value = {
            day: null,
            timeSlot: null,
            room: '',
            section_id: null
        };

        // Show success message
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Schedule item added to database',
            life: 3000
        });
    } catch (error) {
        console.error('Error saving schedule item:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save schedule to database',
            life: 3000
        });
    }
};

// Function to remove a schedule item
const removeScheduleItem = async (item) => {
    try {
        // Delete the schedule item via API
        await axios.delete(`${API_BASE_URL}/schedules/${item.id}`);

        // Update local state after successful delete
        scheduleData.value = scheduleData.value.filter(i => i.id !== item.id);

        toast.add({
            severity: 'info',
            summary: 'Removed',
            detail: 'Schedule item removed from database',
            life: 3000
        });
    } catch (error) {
        console.error('Error removing schedule item:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to remove schedule from database',
            life: 3000
        });
    }
};

// Save schedule to backend
const saveScheduleToBackend = async () => {
    try {
        // For bulk saving multiple schedule changes at once
        const response = await axios.put(
            `${API_BASE_URL}/teachers/${selectedTeacher.value.id}/subjects/${selectedSubjectForSchedule.value.id}/schedule`,
            { schedule: scheduleData.value }
        );

        console.log('Schedule saved to database:', response.data);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Schedule saved to database successfully',
            life: 3000
        });

        scheduleDialog.value = false;
    } catch (error) {
        console.error('Error saving schedule to database:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save schedule to database',
            life: 3000
        });
    }
};

// Teacher form handling
const hideDialog = () => {
    teacherDialog.value = false;
    submitted.value = false;
};

// Helper functions for displaying assignment data
const getGradeName = (assignments) => {
    // First check if there's a primary assignment
    if (assignments?.primary_assignment?.section?.grade?.name) {
        return assignments.primary_assignment.section.grade.name;
    }

    // Then check active assignments
    if (assignments?.active_assignments?.length > 0) {
        // Try to find primary assignments first
        const primaryAssignment = assignments.active_assignments.find(a =>
            (a.is_primary === true || a.role === 'primary') && a.section && a.section.grade);

        if (primaryAssignment && primaryAssignment.section.grade) {
            return primaryAssignment.section.grade.name;
        }

        // If no primary, try any assignment with grade info
        const sectionWithGrade = assignments.active_assignments.find(a => a.section && a.section.grade);
        if (sectionWithGrade && sectionWithGrade.section.grade) {
            return sectionWithGrade.section.grade.name;
        }

        // If no grade found but there are assignments, return at least one grade
        const firstAssignment = assignments.active_assignments[0];
        if (firstAssignment && firstAssignment.section) {
            return firstAssignment.section.grade?.name || 'Unknown Grade';
        }
    }

    return 'Not assigned';
};

const getSectionName = (assignments) => {
    // First check if there's a primary assignment
    if (assignments?.primary_assignment?.section?.name) {
        return assignments.primary_assignment.section.name;
    }

    // Then check active assignments
    if (assignments?.active_assignments?.length > 0) {
        // First try to find primary assignment
        const primaryAssignment = assignments.active_assignments.find(a =>
            (a.is_primary === true || a.role === 'primary') && a.section);

        if (primaryAssignment && primaryAssignment.section) {
            return primaryAssignment.section.name;
        }

        // If not found, get unique section names
        const sectionNames = [...new Set(
            assignments.active_assignments
                .filter(a => a.section && a.section.name)
                .map(a => a.section.name)
        )];

        return sectionNames.join(', ') || 'Unknown Section';
    }

    return 'Not assigned';
};

const getSubjects = (teacher) => {
    // Create a combined array of all assignments
    const allAssignments = [];

    // Add the primary assignment if it exists
    if (teacher.primary_assignment) {
        allAssignments.push(teacher.primary_assignment);
    } else if (findPrimaryAssignments(teacher).length > 0) {
        allAssignments.push(...findPrimaryAssignments(teacher));
    }

    // Add the subject assignments if they exist
    if (teacher.subject_assignments && teacher.subject_assignments.length > 0) {
        allAssignments.push(...teacher.subject_assignments);
    } else if (findSubjectAssignments(teacher).length > 0) {
        allAssignments.push(...findSubjectAssignments(teacher));
    }

    // If we still don't have any assignments but have active_assignments, use those
    if (allAssignments.length === 0 && teacher.active_assignments && teacher.active_assignments.length > 0) {
        allAssignments.push(...teacher.active_assignments);
    }

    if (allAssignments.length === 0) {
        return 'No subjects assigned';
    }

    // Get unique subject names from all assignments
    const subjectNames = [...new Set(
        allAssignments
            .filter(a => a.subject && a.subject.name)
            .map(a => a.subject.name)
    )];

    return subjectNames.join(', ') || 'Unknown Subject';
};

// Find any primary assignments for a teacher
const findPrimaryAssignments = (teacher) => {
    if (!teacher?.active_assignments || !Array.isArray(teacher.active_assignments)) {
        return [];
    }

    return teacher.active_assignments.filter(a =>
        a.is_primary === true || a.role === 'primary'
    );
};

// Find ordinary subject assignments for a teacher
const findSubjectAssignments = (teacher) => {
    if (!teacher?.active_assignments || !Array.isArray(teacher.active_assignments)) {
        return [];
    }

    return teacher.active_assignments.filter(a =>
        a.is_primary !== true && a.role !== 'primary'
    );
};

// Add the missing handleGradeChange function
const handleGradeChange = async (event) => {
    try {
        console.log('Grade changed:', event.value);
        assignmentGrade.value = event.value;
        selectedSection.value = null;
        assignment.value.section_id = null;
        assignment.value.subject_id = null;

        // Clear errors when user changes selections
        assignmentErrors.value = [];

        if (!event.value) {
            filteredSections.value = [];
            return;
        }

        // Show loading toast
        toast.add({
            severity: 'info',
            summary: 'Loading',
            detail: 'Fetching sections for selected grade...',
            life: 2000
        });

        // Get sections for the selected grade
        try {
            const response = await axios.get(`${API_BASE_URL}/sections/grade/${event.value}`);
            filteredSections.value = response.data || [];

            if (filteredSections.value.length === 0) {
                toast.add({
                    severity: 'warn',
                    summary: 'No Sections',
                    detail: 'No sections found for this grade. Please create sections first.',
                    life: 5000
                });
            } else {
                console.log('Sections loaded successfully:', filteredSections.value);
            }
        } catch (error) {
            console.error('Error loading sections for grade:', error);
            filteredSections.value = [];
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load sections for this grade',
                life: 5000
            });
        }
    } catch (error) {
        console.error('Error in handleGradeChange:', error);
    }
};

// Add subject loading function when section is selected
const handleSectionChange = async (event) => {
    try {
        console.log('Section changed:', event.value);
        assignment.value.section_id = event.value;
        assignment.value.subject_id = null;

        // Clear errors when user changes selections
        assignmentErrors.value = [];

        if (!event.value) {
            return;
        }

        // Get subjects for the selected grade
        try {
            const response = await axios.get(`${API_BASE_URL}/subjects`);
            subjectOptions.value = response.data || [];

            if (subjectOptions.value.length === 0) {
                toast.add({
                    severity: 'warn',
                    summary: 'No Subjects',
                    detail: 'No subjects found in the database. Please create subjects first.',
                    life: 5000
                });
            } else {
                console.log('Subjects loaded successfully:', subjectOptions.value);
            }
        } catch (error) {
            console.error('Error loading subjects:', error);
            subjectOptions.value = [];
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load subjects',
                life: 5000
            });
        }
    } catch (error) {
        console.error('Error in handleSectionChange:', error);
    }
};

const saveTeacher = async () => {
    try {
        submitted.value = true;

        // Basic validation
        if (!teacher.value.first_name || !teacher.value.last_name || !teacher.value.email || !teacher.value.username) {
            toast.add({
                severity: 'error',
                summary: 'Validation Error',
                detail: 'Please fill in all required fields',
                life: 5000
            });
            return;
        }

        loading.value = true;
        console.log('Saving teacher:', teacher.value);

        // Determine if this is a create or update operation
        const isUpdate = !!teacher.value.id;
        const endpoint = isUpdate ? `/teachers/${teacher.value.id}` : '/teachers';
        const method = isUpdate ? 'PUT' : 'POST';

        // Use axios directly to better handle errors
        const response = await axios({
            method: method,
            url: `${API_BASE_URL}${endpoint}`,
            data: teacher.value
        });

        console.log('Save teacher response:', response);

        // Always hide the dialog first to prevent UI freezing
        hideDialog();

        // Show success message
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Teacher ${isUpdate ? 'updated' : 'created'} successfully`,
            life: 3000
        });

        // Refresh the teachers list
        await loadTeachers();
    } catch (error) {
        console.error('Error saving teacher:', error);

        // Handle validation errors
        if (error.response && error.response.status === 422) {
            const validationErrors = error.response.data.errors;
            let errorMessage = 'Validation Error: ';

            if (validationErrors) {
                errorMessage += Object.values(validationErrors).flat().join(', ');
            } else {
                errorMessage += 'Please check your input and try again';
            }

            toast.add({
                severity: 'error',
                summary: 'Validation Error',
                detail: errorMessage,
                life: 5000
            });
        } else {
            // Handle other errors
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: `Failed to save teacher: ${error.message || 'Unknown error'}`,
                life: 5000
            });
        }
    } finally {
        loading.value = false;
    }
};

// Use onMounted to initialize the component
onMounted(async () => {
    try {
        // Load everything needed for teacher management
        await Promise.all([
            loadTeachers(),
            loadSubjects() // Explicitly load subjects on mount
        ]);
    } catch (error) {
        console.error('Error initializing teacher management:', error);
        toast.add({
            severity: 'error',
            summary: 'Init Error',
            detail: `Failed to initialize teacher management: ${error.message}`,
            life: 5000
        });
    }
});

// Improved refresh function to ensure data consistency
const forceRefreshTeacher = async (teacherId) => {
    try {
        loading.value = true;
        console.log(`Force refreshing data for teacher ID: ${teacherId}`);

        toast.add({
            severity: 'info',
            summary: 'Refreshing',
            detail: 'Fetching latest teacher data from server...',
            life: 2000
        });

        // Load or refresh sections and subjects first to ensure we have up-to-date reference data
        await Promise.all([
            loadSections(),
            loadSubjects(),
            loadGrades()
        ]);

        console.log('Reference data refreshed. Now fetching teacher data.');

        // Add cache-busting parameter to avoid browser caching
        const response = await axios.get(`${API_BASE_URL}/teachers/${teacherId}?_=${new Date().getTime()}`);

        if (!response.data) {
            console.error('No teacher data returned from force refresh');
            toast.add({
                severity: 'error',
                summary: 'Refresh Failed',
                detail: 'Could not retrieve teacher data from server',
                life: 3000
            });
            return;
        }

        // Process this teacher's data
        const teacherData = response.data;
        console.log('Fresh teacher data received:', teacherData);

        // Initialize arrays for different types of assignments
        let primaryAssignment = null;
        let subjectAssignments = [];

        // Process assignments if present
        if (teacherData.assignments && Array.isArray(teacherData.assignments)) {
            // Filter valid assignments
            const validAssignments = teacherData.assignments.filter(a =>
                a && a.section_id && a.subject_id);

            console.log('Valid assignments received:', validAssignments);

            // Process each assignment
            validAssignments.forEach(assignment => {
                // Find section and subject objects
                const sectionObj = sections.value.find(s =>
                    Number(s.id) === Number(assignment.section_id)) || {
                    id: Number(assignment.section_id),
                    name: `Section ${assignment.section_id}`,
                    grade_id: null,
                    grade: null
                };

                const subjectObj = subjects.value.find(s =>
                    Number(s.id) === Number(assignment.subject_id)) || {
                    id: Number(assignment.subject_id),
                    name: `Subject ${assignment.subject_id}`
                };

                console.log(`Processing assignment - Subject: ${subjectObj.name}, Section: ${sectionObj.name}, Is Primary: ${assignment.is_primary}, Role: ${assignment.role}`);

                const processedAssignment = {
                    id: assignment.id,
                    section_id: Number(assignment.section_id),
                    subject_id: Number(assignment.subject_id),
                    is_primary: assignment.is_primary === true,
                    role: assignment.role || 'subject',
                    is_active: assignment.is_active !== undefined ? assignment.is_active : true,
                    section: sectionObj,
                    subject: subjectObj
                };

                // Sort assignments into primary vs subject
                if (processedAssignment.is_primary || processedAssignment.role === 'primary') {
                    // Update to ensure consistency
                    processedAssignment.is_primary = true;
                    processedAssignment.role = 'primary';
                    primaryAssignment = processedAssignment;
                    console.log('Found primary assignment:', primaryAssignment);
                } else {
                    subjectAssignments.push(processedAssignment);
                    console.log('Added subject assignment:', processedAssignment);
                }
            });
        }

        // Create processed teacher data with all assignments properly categorized
        const active_assignments = [
            ...(primaryAssignment ? [primaryAssignment] : []),
            ...subjectAssignments
        ];

        const processedTeacher = {
            ...teacherData,
            primary_assignment: primaryAssignment,
            subject_assignments: subjectAssignments,
            active_assignments: active_assignments
        };

        console.log('Processed teacher with all assignments:', processedTeacher);
        console.log('Teacher has primary assignment:', !!primaryAssignment);
        console.log('Teacher has subject assignments:', subjectAssignments.length);
        console.log('Total active assignments:', active_assignments.length);

        // Update the specific teacher in the local array
        const index = teachers.value.findIndex(t => t.id === teacherId);
        if (index !== -1) {
            teachers.value[index] = processedTeacher;
            console.log('Teacher data updated locally:', teachers.value[index]);

            // If this is the currently selected teacher, update selectedTeacher
            if (selectedTeacher.value && selectedTeacher.value.id === teacherId) {
                selectedTeacher.value = processedTeacher;
                console.log('Updated selected teacher with fresh data');
            }

            // If this is the edited teacher, update editedTeacher
            if (editedTeacher.value && editedTeacher.value.id === teacherId) {
                editedTeacher.value = processedTeacher;
                console.log('Updated edited teacher with fresh data');
            }
        } else {
            // If teacher not found in array, add it
            teachers.value.push(processedTeacher);
            console.log('Added new teacher to array');
        }

        toast.add({
            severity: 'success',
            summary: 'Refreshed',
            detail: 'Teacher data refreshed successfully',
            life: 3000
        });

    } catch (error) {
        console.error('Error during force refresh:', error);
        toast.add({
            severity: 'error',
            summary: 'Refresh Failed',
            detail: `Could not refresh: ${error.message}`,
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Update teacher status display component - add this helper function
const getTeacherStatus = (teacher) => {
    return {
        value: teacher.is_active ? 'ACTIVE' : 'INACTIVE',
        severity: teacher.is_active ? 'success' : 'danger'
    };
};

// Add function to confirm deleting an assignment
const confirmDeleteAssignment = (assignment) => {
    confirm.require({
        message: `Are you sure you want to remove the ${assignment.subject?.name} assignment for this teacher?`,
        header: 'Confirm Removal',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => deleteAssignment(assignment),
        reject: () => {
            // Do nothing if rejected
        }
    });
};

// Add function to delete an assignment
const deleteAssignment = async (assignment) => {
    try {
        loading.value = true;

        // We'll create a new array of assignments excluding the one to delete
        const updatedAssignments = selectedTeacher.value.active_assignments
            .filter(a => a.id !== assignment.id)
            .map(a => ({
                id: a.id,
                section_id: a.section_id,
                subject_id: a.subject_id,
                is_primary: a.is_primary,
                role: a.role
            }));

        console.log('Updating assignments after deletion:', updatedAssignments);

        // Use API to update assignments
        const response = await axios.put(
            `${API_BASE_URL}/teachers/${selectedTeacher.value.id}/assignments`,
            { assignments: updatedAssignments }
        );

        // Refresh data
        await forceRefreshTeacher(selectedTeacher.value.id);

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Assignment removed successfully',
            life: 3000
        });
    } catch (error) {
        console.error('Error deleting assignment:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: `Failed to remove assignment: ${error.message}`,
            life: 5000
        });
    } finally {
        loading.value = false;
    }
};

// Helper function to get all assignments for a teacher (without duplicates)
const getAllTeacherAssignments = (teacher) => {
    // Create a map to track unique assignments by subject_id+section_id combination
    const assignmentMap = new Map();

    // Function to add assignments to the map
    const addToMap = (assignments) => {
        if (!assignments || !Array.isArray(assignments)) return;

        assignments.forEach(a => {
            if (!a || !a.subject_id || !a.section_id) return;
            // Create a unique key
            const key = `${a.section_id}-${a.subject_id}`;
            // Only add if not already in the map or if this is a primary assignment
            if (!assignmentMap.has(key) || a.is_primary === true || a.role === 'primary') {
                assignmentMap.set(key, a);
            }
        });
    };

    // Add assignments in priority order
    // 1. Primary assignment
    if (teacher.primary_assignment) {
        addToMap([teacher.primary_assignment]);
    }

    // 2. Found primary assignments
    const primaryAssignments = findPrimaryAssignments(teacher);
    if (primaryAssignments && primaryAssignments.length > 0) {
        addToMap(primaryAssignments);
    }

    // 3. Subject assignments
    if (teacher.subject_assignments && teacher.subject_assignments.length > 0) {
        addToMap(teacher.subject_assignments);
    }

    // 4. Found subject assignments
    const subjectAssignments = findSubjectAssignments(teacher);
    if (subjectAssignments && subjectAssignments.length > 0) {
        addToMap(subjectAssignments);
    }

    // 5. Active assignments as fallback
    if (teacher.active_assignments && teacher.active_assignments.length > 0) {
        addToMap(teacher.active_assignments);
    }

    // Convert the map values to an array
    return Array.from(assignmentMap.values());
};

// Load sections for the selected grade
const loadSectionsForAssignment = async (gradeId) => {
    try {
        loading.value = true;
        const response = await axios.get(`${API_BASE_URL}/sections/grade/${gradeId}`);
        availableSections.value = response.data;

        if (availableSections.value.length === 0) {
            toast.add({
                severity: 'warn',
                summary: 'No Sections',
                detail: 'No sections found for this grade. Please add sections first.',
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error loading sections:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load sections. Please try again.',
            life: 3000
        });
        availableSections.value = [];
    } finally {
        loading.value = false;
    }
};

// Load subjects for assignment
const loadSubjectsForAssignment = async () => {
    try {
        loading.value = true;
        const response = await axios.get(`${API_BASE_URL}/subjects`);
        availableSubjectsForAssignment.value = response.data;

        // If primary teacher is selected, automatically add a homeroom subject
        if (selectedRole.value === 'primary') {
            // Check if there's already a Homeroom subject in the database
            const homeroomSubject = availableSubjectsForAssignment.value.find(
                s => s.name.toLowerCase() === 'homeroom'
            );

            if (homeroomSubject) {
                selectedSubjectsForAssignment.value = [homeroomSubject];
            } else {
                // Add a temporary homeroom subject (will need to be created in backend)
                selectedSubjectsForAssignment.value = [{
                    id: 'homeroom',
                    name: 'Homeroom',
                    description: 'Main class for primary teacher',
                    is_required: true
                }];

                toast.add({
                    severity: 'info',
                    summary: 'Homeroom Subject',
                    detail: 'Homeroom subject will be automatically assigned for primary teachers.',
                    life: 3000
                });
            }
        }

        if (availableSubjectsForAssignment.value.length === 0) {
            toast.add({
                severity: 'warn',
                summary: 'No Subjects',
                detail: 'No subjects found in the system. Please add subjects first.',
                life: 3000
            });
        }
    } catch (error) {
        console.error('Error loading subjects:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load subjects. Please try again.',
            life: 3000
        });
        availableSubjectsForAssignment.value = [];
    } finally {
        loading.value = false;
    }
};

// Handle role selection in the wizard
const selectRole = (role) => {
    selectedRole.value = role.value;
    goToNextStep();
};

// Handle grade selection in the wizard
const selectGrade = (grade) => {
    selectedGradeForAssignment.value = grade;
    loadSectionsForAssignment(grade.id);
    goToNextStep();
};

// Handle section selection in the wizard
const selectSection = (section) => {
    selectedSectionForAssignment.value = section;
    loadSubjectsForAssignment();
    goToNextStep();
};

// Handle going to next step in the wizard
const goToNextStep = () => {
    if (currentAssignmentStep.value < totalAssignmentSteps.value) {
        currentAssignmentStep.value++;
    }
};

// Handle going back to previous step in the wizard
const goToPreviousStep = () => {
    if (currentAssignmentStep.value > 1) {
        currentAssignmentStep.value--;
    }
};

// Handle completing the wizard
const completeAssignmentWizard = async () => {
    try {
        loading.value = true;

        // Prepare teacher assignment data
        const assignments = selectedSubjectsForAssignment.value.map(subject => ({
            section_id: selectedSectionForAssignment.value.id,
            subject_id: subject.id,
            is_primary: selectedRole.value === 'primary',
            role: selectedRole.value
        }));

        // Send assignment data to backend
        const response = await axios.put(
            `${API_BASE_URL}/teachers/${assignmentWizardTeacher.value.id}/assignments`,
            { assignments }
        );

        // Show success message
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Teacher assigned successfully',
            life: 3000
        });

        // Close dialog and refresh data
        assignmentWizardDialog.value = false;
        await forceRefreshTeacher(assignmentWizardTeacher.value.id);

    } catch (error) {
        console.error('Error assigning teacher:', error);

        // Handle validation errors
        if (error.response && error.response.status === 422) {
            const errorMessage = error.response.data.message || 'Validation error';
            toast.add({
                severity: 'error',
                summary: 'Validation Error',
                detail: errorMessage,
                life: 5000
            });
        } else {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: `Failed to assign teacher: ${error.message}`,
                life: 5000
            });
        }
    } finally {
        loading.value = false;
    }
};

// Add refs for the assignment wizard
const currentAssignmentStep = ref(1);
const totalAssignmentSteps = ref(4);

// Assignment wizard refs
const selectedRole = ref(null);
const selectedGradeForAssignment = ref(null);
const selectedSectionForAssignment = ref(null);
const selectedSubjectsForAssignment = ref([]);
const assignmentWizardTeacher = ref(null);
const availableSubjectsForAssignment = ref([]);
const availableSections = ref([]);

// In the script setup, add a ref for the subject adder dialog
const subjectAdderDialog = ref(false);

// Add a function to open the subject adder dialog
const openSubjectAdderDialog = (teacherData) => {
    if (!teacherData || !teacherData.id) {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'No valid teacher selected',
            life: 3000
        });
        return;
    }

    try {
        // First, check if the teacher has any primary assignment
        const primaryAssignment = teacherData.primary_assignment ||
            (teacherData.active_assignments &&
            teacherData.active_assignments.find(a => a.is_primary || a.role === 'primary'));

        if (!primaryAssignment) {
            toast.add({
                severity: 'warn',
                summary: 'No Primary Assignment',
                detail: 'Teacher must be assigned as a primary teacher to a section first',
                life: 5000
            });
            return;
        }

        // Set the selected teacher and open the dialog
        selectedTeacher.value = teacherData;
        subjectAdderDialog.value = true;

        console.log(`Opening subject adder dialog for teacher: ${teacherData.first_name} ${teacherData.last_name}`);
    } catch (error) {
        console.error('Error opening subject adder dialog:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to open subject adder dialog',
            life: 3000
        });
    }
};

// Add variables for controlling dialog visibility
const sectionAssignerVisible = ref(false);
const subjectAdderVisible = ref(false);

// Method to show section assigner dialog
const showSectionAssigner = (teacher) => {
    selectedTeacher.value = teacher;
    sectionAssignerVisible.value = true;
};

// Method to show subject adder dialog
const showSubjectAdder = (teacher) => {
    selectedTeacher.value = teacher;
    subjectAdderVisible.value = true;
};

// Handle section assigned event
const handleSectionAssigned = async (teacherId) => {
    await loadTeachers();
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Teacher assigned to section successfully',
        life: 3000
    });
};

// Handle subject added event
const handleSubjectAdded = async (teacherId) => {
    await loadTeachers();
    toast.add({
        severity: 'success',
        summary: 'Success',
        detail: 'Subjects added successfully',
        life: 3000
    });
};
</script>

<template>
    <div class="admin-teacher-wrapper">
        <!-- Enhanced Background Animation -->
        <div class="background-shapes">
            <div class="shape circle"></div>
            <div class="shape square"></div>
            <div class="shape triangle"></div>
            <div class="shape rectangle"></div>
            <div class="shape diamond"></div>
            <div class="shape circle-2"></div>
            <div class="shape square-2"></div>
            <div class="shape triangle-2"></div>
                </div>

        <div class="content-wrapper">
            <!-- Header Section -->
            <div class="header-section">
                <div class="title-section">
                    <h1>Teacher Management</h1>
                    <p class="subtitle">Manage and organize your teaching staff</p>
                </div>
                <Button label="Register Teacher" icon="pi pi-plus" class="p-button-primary" @click="openNewTeacherDialog" />
                </div>

            <!-- Search and Filter Section -->
            <div class="search-section">
                <span class="p-input-icon-left search-box">
                    <InputText v-model="searchQuery" placeholder="Search teachers..." class="modern-search" />
                </span>
                <!-- Removed filter actions -->
            </div>

            <!-- Teachers Cards -->
            <div class="teacher-cards-container">
                <div v-if="filteredTeachers.length === 0 && !loading" class="empty-message">
                    <i class="pi pi-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <h3>No Teachers Found</h3>
                    <p v-if="searchQuery">No teachers match your search criteria.</p>
                    <p v-else>No teachers exist in the database yet.</p>
                    <Button label="Register New Teacher" icon="pi pi-plus" class="p-button-primary mt-3" @click="openNewTeacherDialog" />
                </div>

                <div v-if="loading" class="loading-message">
                    <i class="pi pi-spin pi-spinner" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                    <p>Loading teachers...</p>
                </div>

                <div v-if="!loading && filteredTeachers.length > 0" class="teacher-cards">
                    <div v-for="teacher in filteredTeachers" :key="teacher.id" class="teacher-card">
                        <div class="teacher-card-header">
                            <div class="teacher-info">
                                <div class="teacher-name">{{ teacher.first_name }} {{ teacher.last_name }}</div>
                                <div v-if="teacher.is_head_teacher" class="teacher-role">Head Teacher</div>
                            </div>
                            <Tag :value="teacher.is_active ? 'ACTIVE' : 'INACTIVE'"
                                :severity="teacher.is_active ? 'success' : 'danger'" />
                </div>

                        <div class="teacher-card-body">
                            <!-- Primary Assignment -->
                            <div class="primary-assignment">
                                <h4>Primary Assignment</h4>
                                <div v-if="!teacher.primary_assignment && findPrimaryAssignments(teacher).length === 0" class="not-assigned">
                                    <span class="badge-muted">Not assigned</span>
                                </div>
                                <div v-else class="assignment-details">
                                    <div class="teacher-detail">
                                        <span class="detail-label">Grade:</span>
                                        <span class="grade-badge">
                                            {{ teacher.primary_assignment?.section?.grade?.name ||
                                              (findPrimaryAssignments(teacher)[0]?.section?.grade?.name || 'Not assigned') }}
                                        </span>
                                    </div>
                                    <div class="teacher-detail">
                                        <span class="detail-label">Section:</span>
                                        <span class="section-badge">
                                            {{ teacher.primary_assignment?.section?.name ||
                                               (findPrimaryAssignments(teacher)[0]?.section?.name || 'Not assigned') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Subject Assignments -->
                            <div class="subject-assignments">
                                <h4>Teaching Subjects</h4>
                                <div v-if="!teacher.subject_assignments && findSubjectAssignments(teacher).length === 0" class="not-assigned">
                                    <span class="badge-muted">No subjects assigned</span>
                                </div>
                                <div v-else class="assignment-details">
                                    <div class="teacher-detail">
                                        <span class="detail-label">Subjects:</span>
                                        <div class="subjects-list">
                                            <span v-if="getAllTeacherAssignments(teacher).length === 0" class="badge-muted">
                                                No subjects assigned
                                            </span>
                                            <div v-else class="subject-tags">
                                                <Tag v-for="(assignment, index) in getAllTeacherAssignments(teacher)"
                                                    :key="assignment.subject_id + '-' + index"
                                                    :value="assignment.subject?.name || 'Unknown Subject'"
                                                    :severity="assignment.is_primary || assignment.role === 'primary' ? 'success' : 'info'"
                                                    class="subject-tag" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="teacher-card-actions">
                            <!-- First row of buttons -->
                            <div class="action-buttons-row">
                                <Button class="action-btn details-btn"
                                    @click="viewTeacher(teacher)"
                                    v-tooltip.top="'View Details'">
                                    <i class="pi pi-eye"></i>
                                <span>Details</span>
                            </Button>

                                <Button class="action-btn edit-btn"
                                    @click="editTeacher(teacher)"
                                    v-tooltip.top="'Edit Teacher'">
                                <i class="pi pi-pencil"></i>
                                <span>Edit</span>
                            </Button>

                                <Button class="action-btn delete-btn"
                                    @click="confirmDeleteTeacher(teacher)"
                                    v-tooltip.top="'Delete Teacher'">
                                <i class="pi pi-trash"></i>
                                <span>Delete</span>
                            </Button>
                            </div>

                            <!-- Second row of buttons -->
                            <div class="action-buttons-row">
                                <Button class="action-btn assign-section-btn"
                                    @click="showSectionAssigner(teacher)"
                                    v-tooltip.top="'Assign to Section'">
                                    <i class="pi pi-users"></i>
                                    <span>Assign</span>
                                </Button>

                                <Button class="action-btn add-subject-btn"
                                    @click="showSubjectAdder(teacher)"
                                    v-tooltip.top="'Add Teaching Subjects'">
                                    <i class="pi pi-book"></i>
                                    <span>Add Subjects</span>
                                </Button>
                            </div>
                        </div>
                </div>
                </div>
            </div>
                </div>
                </div>

        <!-- Teacher Assignment Wizard -->
<TeacherAssignmentWizard
    v-model:visible="assignmentWizardDialog"
    :teacher="selectedTeacher"
    :apiBaseUrl="apiBaseUrl"
    :mode="assignmentWizardMode"
    :existingAssignments="selectedTeacher.active_assignments || []"
    @assignment-complete="handleAssignmentComplete" />

<TeacherSubjectAdder
    v-model:visible="subjectAdderDialog"
    :teacher="selectedTeacher"
    :apiBaseUrl="apiBaseUrl"
    @subject-added="handleAssignmentComplete" />

<!-- All Dialogs go here, unchanged -->

        <!-- Teacher Details Dialog -->
        <Dialog v-model:visible="teacherDetailsDialog" modal header="Teacher Details" :style="{ width: '550px' }" class="teacher-details-dialog">
            <div class="p-fluid" v-if="selectedTeacher">
                <div class="teacher-details-header">
                    <div class="teacher-avatar">
                        <div class="teacher-initials">{{ getInitials(selectedTeacher) }}</div>
                </div>
                    <div class="teacher-details-name">
                        <!-- Teacher info with refresh button -->
                        <div class="teacher-info-header">
                            <div class="name-status">
                                <h1>{{ selectedTeacher.first_name }} {{ selectedTeacher.last_name }}</h1>
                                <Tag :value="selectedTeacher.is_active ? 'ACTIVE' : 'INACTIVE'"
                                     :severity="selectedTeacher.is_active ? 'success' : 'danger'" />
                            </div>
                            <button @click="forceRefreshTeacher(selectedTeacher.id)" class="refresh-button">
                                <i class="pi pi-refresh"></i>
                                Refresh Data
                            </button>
                        </div>
                        <div class="teacher-status">
                            <Tag :value="selectedTeacher.is_active ? 'ACTIVE' : 'INACTIVE'"
                                :severity="selectedTeacher.is_active ? 'success' : 'danger'" />
                            <span v-if="selectedTeacher.is_head_teacher" class="head-teacher-badge">Head Teacher</span>
                </div>
            </div>
                </div>

                <div class="teacher-details-info">
                    <div class="info-section">
                        <div class="info-row">
                            <div class="info-label">Email</div>
                            <div class="info-value">{{ selectedTeacher.email || 'Not provided' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Phone</div>
                            <div class="info-value">{{ selectedTeacher.phone_number || 'Not provided' }}</div>
                        </div>

                        <!-- Primary Assignment Section -->
                        <div class="primary-assignment-section">
                            <h3>Primary Assignment</h3>
                            <div v-if="!selectedTeacher.primary_assignment && findPrimaryAssignments(selectedTeacher).length === 0" class="no-primary">
                                <span class="no-assignment-text">No primary assignment yet</span>
                            </div>
                            <div v-else class="primary-assignment-details">
                                <div class="info-row">
                                    <div class="info-label">Grade Level</div>
                                    <div class="info-value grade-badge-details">
                                        {{ selectedTeacher.primary_assignment?.section?.grade?.name ||
                                            (findPrimaryAssignments(selectedTeacher)[0]?.section?.grade?.name || 'Not assigned') }}
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Section</div>
                                    <div class="info-value section-badge-details">
                                        {{ selectedTeacher.primary_assignment?.section?.name ||
                                            (findPrimaryAssignments(selectedTeacher)[0]?.section?.name || 'Not assigned') }}
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Subject</div>
                                    <div class="info-value subject-badge-details">
                                        {{ selectedTeacher.primary_assignment?.subject?.name ||
                                            (findPrimaryAssignments(selectedTeacher)[0]?.subject?.name || 'Not assigned') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="teacher-details-subjects">
                    <h3>Subject Assignments</h3>
                    <div v-if="(!selectedTeacher.subject_assignments || selectedTeacher.subject_assignments.length === 0) &&
                        !selectedTeacher.primary_assignment && findPrimaryAssignments(selectedTeacher).length === 0 &&
                        findSubjectAssignments(selectedTeacher).length === 0" class="no-subjects">
                        No subject assignments for this teacher yet.
                    </div>
                    <DataTable v-else :value="getAllTeacherAssignments(selectedTeacher)"
                        :rows="10" scrollable scrollHeight="200px" class="subjects-table">
                        <Column header="Subject">
                            <template #body="slotProps">
                                <div class="subject-name" :class="{'primary-subject': slotProps.data.is_primary || slotProps.data.role === 'primary'}">
                                    {{ slotProps.data.subject?.name || 'Unknown Subject' }}
                                    <Badge v-if="slotProps.data.is_primary || slotProps.data.role === 'primary'" value="Primary" severity="success" />
                                </div>
                            </template>
                        </Column>
                        <Column header="Section">
                            <template #body="slotProps">
                                <div>{{ slotProps.data.section?.name || 'Unknown Section' }}</div>
                            </template>
                        </Column>
                        <Column header="Grade">
                            <template #body="slotProps">
                                <div>{{ slotProps.data.section?.grade?.name || 'Unknown Grade' }}</div>
                            </template>
                        </Column>
                        <Column header="Role">
                            <template #body="slotProps">
                                <Tag
                                    :value="slotProps.data.role || 'subject'"
                                    :severity="slotProps.data.is_primary || slotProps.data.role === 'primary' ? 'success' : 'info'" />
                            </template>
                        </Column>
                        <Column header="Actions">
                            <template #body="slotProps">
                                <Button icon="pi pi-trash"
                                    class="p-button-rounded p-button-danger p-button-sm mr-2"
                                    @click="confirmDeleteAssignment(slotProps.data)"
                                    v-tooltip.top="'Remove Assignment'" />
                            </template>
                        </Column>
                    </DataTable>
                </div>
            </div>
            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="teacherDetailsDialog = false" text />
            </template>
        </Dialog>

        <!-- Teacher Registration Dialog -->
        <Dialog v-model:visible="teacherDialog" :header="dialogTitle" :style="{ width: '700px' }" :modal="true" class="registration-dialog">
            <div class="p-fluid">
                <!-- Form Grid Layout -->
                <div class="form-grid p-5">
                    <div class="field">
                        <label for="first_name">First Name*</label>
                        <InputText id="first_name" v-model="teacher.first_name" required :class="{ 'p-invalid': submitted && !teacher.first_name }" />
                        <small class="p-error" v-if="submitted && !teacher.first_name">First name is required.</small>
                    </div>

                    <div class="field">
                        <label for="last_name">Last Name*</label>
                        <InputText id="last_name" v-model="teacher.last_name" required :class="{ 'p-invalid': submitted && !teacher.last_name }" />
                        <small class="p-error" v-if="submitted && !teacher.last_name">Last name is required.</small>
                    </div>

                    <div class="field">
                        <label for="email">Email*</label>
                        <InputText id="email" v-model="teacher.email" required :class="{ 'p-invalid': submitted && !teacher.email }" />
                        <small class="p-error" v-if="submitted && !teacher.email">Email is required.</small>
                    </div>

                    <div class="field">
                        <label for="phone_number">Phone Number</label>
                        <InputText id="phone_number" v-model="teacher.phone_number" />
                    </div>

                    <div class="field">
                        <label for="username">Username*</label>
                        <InputText id="username" v-model="teacher.username" required :class="{ 'p-invalid': submitted && !teacher.username }" />
                        <small class="p-error" v-if="submitted && !teacher.username">Username is required.</small>
                    </div>

                    <div class="field">
                        <label for="password">Password*</label>
                        <InputText id="password" v-model="teacher.password" type="password" required :class="{ 'p-invalid': submitted && !teacher.password }" />
                        <small class="p-error" v-if="submitted && !teacher.password">Password is required.</small>
                    </div>

                    <div class="field">
                        <label for="address">Address</label>
                        <InputText id="address" v-model="teacher.address" />
                    </div>

                    <div class="field">
                        <label for="gender">Gender</label>
                        <Dropdown id="gender" v-model="teacher.gender" :options="genderOptions" optionLabel="label" optionValue="value" placeholder="Select Gender" />
                    </div>

                    <div class="p-field-checkbox">
                        <label for="is_head_teacher" class="checkbox-label">Head Teacher</label>
                        <input type="checkbox" id="is_head_teacher" v-model="teacher.is_head_teacher" />
                    </div>

                    <div class="p-field-checkbox">
                        <label for="is_active" class="checkbox-label">Active Status</label>
                        <input type="checkbox" id="is_active" v-model="teacher.is_active" />
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="dialog-footer-buttons p-5">
                    <Button label="Cancel" icon="pi pi-times" @click="hideDialog" class="p-button-text cancel-button" />
                    <Button label="Save" icon="pi pi-check" @click="saveTeacher" class="p-button-raised p-button-primary save-button-custom" />
                </div>
            </template>
        </Dialog>

        <!-- Keep all other existing dialogs unchanged -->

        <!-- Subject Schedule Dialog -->
        <Dialog v-model:visible="scheduleDialog"
            :header="`Schedule for ${selectedSubjectForSchedule?.name || 'Subject'}`"
            modal
            :style="{ width: '80vw', maxWidth: '1000px' }"
            class="schedule-dialog">
            <div v-if="selectedSubjectForSchedule" class="p-fluid">
                <div class="schedule-header mb-4">
                    <div class="flex justify-content-between align-items-center">
                        <h4 class="m-0">
                            Manage class schedule for {{ selectedSubjectForSchedule.name }}
                        </h4>
                        <Tag :value="scheduleData.length ? `${scheduleData.length} Classes Scheduled` : 'No Classes'"
                            :severity="scheduleData.length ? 'success' : 'warning'" />
            </div>
                    <div class="text-500 mt-2">
                        Add, edit, and remove class schedules for this subject. These schedules will be used for notifications and attendance taking.
                </div>
                </div>

                <!-- Schedule Form -->
                <div class="schedule-form p-3 mb-4 border-1 surface-border border-round">
                    <h5 class="mb-3">Add New Schedule</h5>
                    <div class="formgrid grid">
                        <div class="field col-12 md:col-3">
                            <label for="day">Day of Week*</label>
                            <Dropdown id="day" v-model="newScheduleItem.day"
                                :options="daysOfWeek"
                                placeholder="Select Day"
                                class="w-full" />
                </div>
                        <div class="field col-12 md:col-3">
                            <label for="time">Time Slot*</label>
                            <Dropdown id="time" v-model="newScheduleItem.timeSlot"
                                :options="timeSlots"
                                placeholder="Select Time"
                                class="w-full" />
            </div>
                        <div class="field col-12 md:col-3">
                            <label for="section">Section*</label>
                            <Dropdown id="section" v-model="newScheduleItem.section_id"
                                :options="filteredSections"
                                optionLabel="name"
                                optionValue="id"
                                placeholder="Select Section"
                                class="w-full"
                                @click="loadSectionsForSchedule" />
                        </div>
                        <div class="field col-12 md:col-2">
                            <label for="room">Room</label>
                            <InputText id="room" v-model="newScheduleItem.room" placeholder="Room number" />
                    </div>
                        <div class="field col-12 md:col-1 flex align-items-end">
                            <Button label="Add"
                                icon="pi pi-plus"
                                class="p-button-success w-full"
                                @click="saveScheduleItem" />
                </div>
                </div>
            </div>

                <!-- Schedule Table -->
                <div class="schedule-table">
                    <h5 class="mb-3">Current Schedule</h5>
                    <DataTable :value="scheduleData"
                        responsiveLayout="scroll"
                        class="p-datatable-sm"
                        :paginator="scheduleData.length > 10"
                        :rows="10"
                        emptyMessage="No schedules found. Add one using the form above.">

                        <Column field="day" header="Day" sortable></Column>
                        <Column field="timeSlot" header="Time" sortable></Column>
                        <Column field="section_name" header="Section"></Column>
                        <Column field="room" header="Room"></Column>

                        <Column header="Actions" style="width: 8rem">
                            <template #body="slotProps">
                                <div class="flex gap-2">
                                    <Button icon="pi pi-trash"
                                        class="p-button-rounded p-button-danger p-button-sm"
                                        @click="removeScheduleItem(slotProps.data)"
                                        v-tooltip.top="'Remove'" />
                        </div>
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <!-- Weekly View -->
                <div v-if="scheduleData.length > 0" class="weekly-schedule mt-4">
                    <h5 class="mb-3">Weekly View</h5>
                    <div class="schedule-grid border-1 surface-border">
                        <div class="schedule-header grid">
                            <div class="col-2 font-bold p-2 border-right-1 surface-border">Time</div>
                            <div v-for="day in daysOfWeek.slice(0, 6)" :key="day" class="col-2 font-bold p-2 border-right-1 surface-border">{{ day }}</div>
                </div>

                        <div v-for="time in timeSlots" :key="time" class="schedule-row grid">
                            <div class="col-2 p-2 border-top-1 border-right-1 surface-border time-label">{{ time }}</div>
                            <div v-for="day in daysOfWeek.slice(0, 6)" :key="`${time}-${day}`"
                                class="col-2 p-2 border-top-1 border-right-1 surface-border schedule-cell">
                                <div v-for="item in scheduleData.filter(i => i.day === day && i.timeSlot === time)"
                                    :key="item.id"
                                    class="schedule-item p-2 border-round mb-1"
                                    style="background-color: #e0f2fe; color: #0369a1;">
                                    {{ item.section_name }}
                                    <div class="text-xs">{{ item.room }}</div>
                    </div>
                    </div>
                            </div>
                        </div>
                    </div>
                </div>
            <template #footer>
                <Button label="Close" icon="pi pi-times" @click="scheduleDialog = false" class="p-button-text" />
                <Button label="Save Schedule" icon="pi pi-save" @click="saveScheduleToBackend" class="p-button-primary" />
            </template>
        </Dialog>

        <!-- Assignment Dialog -->
        <Dialog v-model:visible="assignmentDialogVisible" :header="`Assign Subject to Teacher`" modal :style="{ width: '450px' }" class="assignment-dialog">
            <div class="p-fluid compact-form">
                <!-- Display validation errors if any -->
                <div v-if="assignmentErrors.length > 0" class="assignment-error-container mb-3">
                    <div class="error-header">
                        <i class="pi pi-exclamation-triangle"></i>
                        <span>Error</span>
                    </div>
                    <ul class="error-list">
                        <li v-for="(error, index) in assignmentErrors" :key="index" class="error-item">
                            {{ error }}
                        </li>
                    </ul>
                    <div class="error-actions" v-if="editedTeacher">
                        <button @click="forceRefreshTeacher(editedTeacher.id)" class="refresh-error-btn">
                            <i class="pi pi-refresh"></i> Refresh Teacher Data
                        </button>
                    </div>
                </div>

                <div class="compact-field">
                    <label for="grade">Grade Level*</label>
                    <Dropdown id="grade" v-model="assignmentGrade"
                        :options="gradeOptions"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Select Grade"
                        @change="handleGradeChange"
                        :class="{ 'p-invalid': assignmentErrors.length > 0 }" />
                </div>

                <div class="compact-field">
                    <label for="section">Section*</label>
                    <Dropdown id="section" v-model="assignment.section_id"
                        :options="filteredSections"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Select Section"
                        @change="handleSectionChange"
                        :class="{ 'p-invalid': assignmentErrors.length > 0 }"
                        :disabled="!assignmentGrade" />
                </div>

                <div class="compact-field">
                    <label for="subject">Subject*</label>
                    <Dropdown id="subject" v-model="assignment.subject_id"
                        :options="subjectOptions"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Select Subject"
                        :class="{ 'p-invalid': assignmentErrors.length > 0 }"
                        :disabled="!assignment.section_id" />
                </div>

                <div class="compact-field">
                    <label for="role">Teacher Role*</label>
                    <Dropdown id="role" v-model="assignment.role"
                        :options="teacherRoleOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Select Role"
                        @change="handleRoleChange"
                        :class="{ 'p-invalid': assignmentErrors.length > 0 }" />
                </div>

                <div class="compact-field field-checkbox">
                    <div class="p-field-checkbox">
                        <Checkbox id="isPrimary" v-model="assignment.is_primary"
                            :binary="true"
                            :disabled="assignment.role === 'primary'" />
                        <label for="isPrimary" class="ml-2">Primary Teacher</label>
                        <small class="help-text">This will be automatically checked when 'Primary' role is selected.</small>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="dialog-footer">
                    <button type="button" class="custom-cancel-btn" @click="hideAssignmentDialog">
                        Cancel
                    </button>
                    <button type="button" class="custom-save-btn" @click="saveAssignment">
                        Assign
                    </button>
                </div>
            </template>
        </Dialog>

    <!-- Add the new components at the bottom of the template -->
    <!-- TeacherSectionAssigner Dialog -->
    <TeacherSectionAssigner
        v-model:visible="sectionAssignerVisible"
        :teacher="selectedTeacher"
        :apiBaseUrl="apiBaseUrl"
        @section-assigned="handleSectionAssigned"
    />

    <!-- TeacherSubjectAdder Dialog -->
    <TeacherSubjectAdder
        v-model:visible="subjectAdderVisible"
        :teacher="selectedTeacher"
        :apiBaseUrl="apiBaseUrl"
        @subject-added="handleSubjectAdded"
    />
</template>

<style scoped>
.admin-teacher-wrapper {
    min-height: 100vh;
    background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
    padding: 2rem;
    position: relative;
    overflow: hidden;
    animation: gradientShift 15s ease infinite;
}

/* Help text styling for form elements */
.help-text {
    display: block;
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 0.25rem;
    font-style: italic;
}



/* Help text styling for form elements */
.help-text {
    display: block;
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 0.25rem;
    font-style: italic;
}

@keyframes gradientShift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

/* Professional background elements */
.background-shapes {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: 0;
}

.background-shapes .shape {
    position: absolute;
    opacity: 0.05;
    animation: float 20s infinite ease-in-out;
}

.shape.circle {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: #4361ee;
    top: 10%;
    left: 10%;
    animation-delay: 0s;
}

.shape.square {
    width: 120px;
    height: 120px;
    background: #3a0ca3;
    top: 40%;
    right: 15%;
    transform: rotate(45deg);
    animation-delay: 2s;
}

.shape.triangle {
    width: 0;
    height: 0;
    border-left: 80px solid transparent;
    border-right: 80px solid transparent;
    border-bottom: 140px solid #4895ef;
    top: 60%;
    left: 20%;
    animation-delay: 4s;
}

.shape.rectangle {
    width: 180px;
    height: 90px;
    background: #560bad;
    bottom: 20%;
    right: 10%;
    animation-delay: 1s;
}

.shape.diamond {
    width: 100px;
    height: 100px;
    background: #f72585;
    top: 25%;
    left: 45%;
    transform: rotate(45deg);
    animation-delay: 3s;
}

.shape.circle-2 {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: #4cc9f0;
    top: 70%;
    right: 30%;
    animation-delay: 5s;
}

.shape.square-2 {
    width: 100px;
    height: 100px;
    background: #4361ee;
    top: 15%;
    right: 35%;
    transform: rotate(25deg);
    animation-delay: 6s;
}

.shape.triangle-2 {
    width: 0;
    height: 0;
    border-left: 60px solid transparent;
    border-right: 60px solid transparent;
    border-bottom: 120px solid #7209b7;
    bottom: 15%;
    left: 30%;
    animation-delay: 7s;
}

/* Enhanced floating animation */
@keyframes float {
    0% {
        transform: translateY(0) rotate(0deg);
    }
    25% {
        transform: translateY(-15px) rotate(3deg);
    }
    50% {
        transform: translateY(0) rotate(0deg);
    }
    75% {
        transform: translateY(15px) rotate(-3deg);
    }
    100% {
        transform: translateY(0) rotate(0deg);
    }
}

.content-wrapper {
    position: relative;
    z-index: 1;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 1rem;
    padding: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08), 0 6px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    animation: fadeIn 0.8s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.header-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    padding-bottom: 1.5rem;
}

.title-section h1 {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    background: linear-gradient(to right, #3a0ca3, #4361ee);
    background-clip: text;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: gradientText 8s ease infinite;
}

@keyframes gradientText {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

.subtitle {
    color: #64748b;
    margin-top: 0.5rem;
    font-size: 1rem;
}

/* Enhanced DataTable styling */
.teacher-table {
    background: white;
    border-radius: 0.75rem;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.03);
    margin-top: 1rem;
    animation: slideUp 0.5s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.teacher-name {
    font-weight: 600;
    color: #334155;
}

.teacher-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
}

.teacher-avatar:hover {
    transform: scale(1.15);
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.action-buttons .p-button {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.action-buttons .p-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Enhanced dialog styling */
.registration-dialog {
    max-width: 800px;
}

:deep(.p-dialog) {
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.1), 0 10px 20px rgba(0, 0, 0, 0.05) !important;
    border-radius: 16px !important;
    overflow: hidden;
}

:deep(.p-dialog-header) {
    background: linear-gradient(135deg, #4361ee, #3a0ca3) !important;
    color: white !important;
    padding: 1.5rem 2rem !important;
}

:deep(.p-dialog-title) {
    font-weight: 700 !important;
    font-size: 1.25rem !important;
    letter-spacing: 0.5px !important;
    color: white !important;
}

:deep(.p-dialog-content) {
    padding: 2rem !important;
    background: white !important;
}

/* Style specifically for the Assignment Dialog */
:deep(.assignmentDialog .p-dialog-content) {
    background: linear-gradient(to bottom, #ffffff, #f8fafc) !important;
    position: relative;
    overflow: hidden !important;
}

.dialog-content {
    position: relative;
    z-index: 10;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    animation: fadeIn 0.5s ease-out;
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    margin-bottom: 1.5rem;
    animation: slideUp 0.5s ease-out;
}

.form-row:nth-child(1) { animation-delay: 0.1s; }
.form-row:nth-child(2) { animation-delay: 0.2s; }
.form-row:nth-child(3) { animation-delay: 0.3s; }

.p-field-half {
    flex: 1 1 calc(50% - 0.625rem);
    min-width: 200px;
}

.field {
    margin-bottom: 1.5rem;
    animation: fadeIn 0.5s forwards;
}

.field:nth-child(1) { animation-delay: 0.1s; }
.field:nth-child(2) { animation-delay: 0.15s; }
.field:nth-child(3) { animation-delay: 0.2s; }
.field:nth-child(4) { animation-delay: 0.25s; }
.field:nth-child(5) { animation-delay: 0.3s; }
.field:nth-child(6) { animation-delay: 0.35s; }

.field label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #334155;
    position: relative;
    padding-left: 0.75rem;
    transition: color 0.3s ease;
}

.field label::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0.25rem;
    height: 1rem;
    width: 3px;
    background: linear-gradient(to bottom, #4361ee, #3a0ca3);
    border-radius: 1px;
}

.field:hover label {
    color: #4361ee;
}

:deep(.p-inputtext:focus) {
    border-color: #4361ee !important;
    box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.1) !important;
}

.p-field-checkbox {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1rem;
}

.checkbox-label {
    font-weight: 500;
    color: #475569;
}
.assignment-error-container {
    background-color: #fef2f2;
    border-left: 3px solid #ef4444;
    border-radius: 0.25rem;
    padding: 0.75rem;
    margin-bottom: 1rem;
}

.error-header {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #dc2626;
}

.error-header i {
    margin-right: 0.5rem;
    font-size: 1rem;
}

.error-list {
    margin: 0;
    padding-left: 1.5rem;
    list-style-type: none;
}

.error-item {
    color: #dc2626;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    position: relative;
}

.error-item:before {
    content: "â€¢";
    color: #ef4444;
    position: absolute;
    left: -1rem;
}

.error-actions {
    margin-top: 0.75rem;
    display: flex;
    justify-content: flex-end;
}

.refresh-error-btn {
    display: flex;
    align-items: center;
    background: rgba(220, 38, 38, 0.1);
    border: 1px solid rgba(220, 38, 38, 0.3);
    border-radius: 4px;
    padding: 0.375rem 0.75rem;
    color: #b91c1c;
    font-size: 0.8125rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.refresh-error-btn i {
    margin-right: 0.375rem;
    font-size: 0.875rem;
}

.refresh-error-btn:hover {
    background: rgba(220, 38, 38, 0.15);
    border-color: rgba(220, 38, 38, 0.4);
}

.teacher-info-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    margin-bottom: 1rem;
}

.name-status {
    display: flex;
    flex-direction: column;
}

.name-status h1 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.refresh-button {
    display: flex;
    align-items: center;
    background: #f1f5f9;
    border: none;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    color: #4361ee;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.refresh-button i {
    margin-right: 0.5rem;
}

.refresh-button:hover {
    background: #e0f2fe;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
/* Animated error container */
.error-container {
    background-color: #fee2e2;
    border-left: 4px solid #dc2626;
    padding: 1rem;
    border-radius: 0.25rem;
    margin-top: 1rem;
    animation: fadeIn 0.5s ease-out;
}

.error-list {
    margin: 0;
    padding-left: 1.5rem;
}

.error-item {
    color: #dc2626;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
}

.error-item:last-child {
    margin-bottom: 0;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.error-item::before {
    content: "â€¢";
    margin-right: 0.5rem;
    color: #ef4444;
    font-size: 1.25rem;
}

/* Footer buttons */
:deep(.p-dialog-footer) {
    padding: 1.5rem 2rem !important;
    background: #f8fafc !important;
    border-top: 1px solid #e2e8f0 !important;
}

:deep(.p-button) {
    font-weight: 600 !important;
    padding: 0.75rem 1.5rem !important;
    transition: all 0.3s ease !important;
}

:deep(.p-button-primary) {
    background: linear-gradient(135deg, #4361ee, #3a0ca3) !important;
    border: none !important;
    position: relative;
    overflow: hidden;
}

:deep(.p-button-primary::before) {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(45deg);
    opacity: 0;
    transition: opacity 0.3s ease;
}

:deep(.p-button-primary:hover::before) {
    animation: shine 1.5s ease;
}

@keyframes shine {
    0% {
        opacity: 0;
        left: -50%;
        top: -50%;
    }
    20% {
        opacity: 0.5;
    }
    100% {
        opacity: 0;
        left: 100%;
        top: 100%;
    }
}

:deep(.p-button-primary:hover) {
    background: linear-gradient(135deg, #3a0ca3, #4361ee) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 15px rgba(67, 97, 238, 0.3) !important;
}

:deep(.p-button-text) {
    color: #64748b !important;
}

:deep(.p-button-text:hover) {
    background: #f1f5f9 !important;
    color: #334155 !important;
}

/* Responsive design */
@media (max-width: 768px) {
    .admin-teacher-wrapper {
        padding: 1rem;
    }

    .header-section {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .search-section {
        flex-direction: column;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .form-row {
        flex-direction: column;
        gap: 0.75rem;
    }

    .p-field-half {
        width: 100%;
    }
}

/* Assignment Dialog Specific Styles */
.assignment-dialog {
    overflow: visible !important;
}

.assignment-header {
    text-align: center;
    margin-bottom: 2rem;
    position: relative;
    z-index: 5;
}

.edu-symbol-container {
    width: 100px;
    height: 100px;
    margin: 0 auto 1.5rem;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.math-symbol {
    font-size: 4rem;
    color: #4361ee;
}

.education-animation-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: 1;
    opacity: 0.7;
    pointer-events: none; /* Ensure it doesn't block clicks */
}

.edu-symbol {
    position: absolute;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    z-index: 1;
    animation: float 15s infinite ease-in-out;
}

.edu-symbol i {
    font-size: 18px;
    color: #4361ee;
}

.edu-symbol.pi {
    top: 15%;
    left: 8%;
    animation-delay: 0s;
}

.edu-symbol.sigma {
    top: 25%;
    right: 8%;
    animation-delay: 3s;
}

.edu-symbol.delta {
    bottom: 20%;
    left: 12%;
    animation-delay: 1.5s;
}

.edu-symbol.omega {
    top: 60%;
    right: 10%;
    animation-delay: 4s;
}

.edu-symbol.sqrt {
    bottom: 30%;
    right: 20%;
    animation-delay: 2s;
}

.edu-symbol.infinity {
    bottom: 15%;
    left: 30%;
    animation-delay: 7s;
}

.edu-symbol.function {
    top: 50%;
    left: 45%;
    transform: translate(-50%, -50%);
    animation: pulse 15s ease-in-out infinite;
}

.edu-symbol.equation {
    bottom: 20%;
    right: 20%;
    animation-delay: 2s;
}

.animated-circle {
    position: absolute;
    border-radius: 50%;
    opacity: 0.1;
    z-index: 0;
}

.animated-circle.circle1 {
    width: 200px;
    height: 200px;
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
    top: -50px;
    right: -50px;
    animation: rotate 25s linear infinite;
}

.animated-circle.circle2 {
    width: 150px;
    height: 150px;
    background: linear-gradient(135deg, #4cc9f0, #4361ee);
    bottom: -30px;
    left: -30px;
    animation: rotate 20s linear infinite reverse;
}

.animated-circle.circle3 {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, #f72585, #7209b7);
    top: 50%;
    left: 45%;
    transform: translate(-50%, -50%);
    animation: pulse 15s ease-in-out infinite;
}

@keyframes rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.animated-field {
    opacity: 0;
    transform: translateY(10px);
    animation: fadeInUp 0.6s forwards;
}

.animated-field:nth-child(1) { animation-delay: 0.1s; }
.animated-field:nth-child(2) { animation-delay: 0.2s; }
.animated-field:nth-child(3) { animation-delay: 0.3s; }
.animated-field:nth-child(4) { animation-delay: 0.4s; }

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.custom-cancel-btn, .custom-save-btn {
    transition: all 0.3s ease;
}

.custom-save-btn {
    position: relative;
    overflow: hidden;
}

.custom-save-btn::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(transparent, rgba(255, 255, 255, 0.2), transparent);
    transform: rotate(30deg);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% {
        transform: translateX(-150%) rotate(30deg);
    }
    100% {
        transform: translateX(150%) rotate(30deg);
    }
}

.custom-save-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
}

.custom-cancel-btn:hover {
    background-color: #d1d5db;
    transform: translateY(-2px);
}

.refresh-link {
    color: #4361ee;
    cursor: pointer;
    text-decoration: underline;
    font-weight: 500;
    transition: color 0.2s ease;
}

.refresh-link:hover {
    color: #3a0ca3;
}

.p-inputgroup {
    display: flex;
}

.p-inputgroup .p-button {
    margin-left: 1px;
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.p-inputgroup .p-dropdown,
.p-inputgroup .p-inputtext {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    flex: 1;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 1rem;
}

.table-header h3 {
    margin: 0;
    font-size: 1.25rem;
    color: #334155;
}

.header-buttons {
    display: flex;
    gap: 0.5rem;
}

.empty-message, .loading-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 1rem;
    color: #64748b;
    text-align: center;
}

.teacher-name-cell {
    display: flex;
    align-items: center;
    padding: 0.5rem 0;
}

.teacher-name {
    font-weight: 600;
    color: #1e293b;
    font-size: 1rem;
}

.teacher-role {
    font-size: 0.75rem;
    color: #4361ee;
    font-weight: 600;
    margin-top: 2px;
}

.department-badge, .room-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.875rem;
    font-weight: 500;
}

.subjects-list {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.no-data {
    color: #94a3b8;
    font-style: italic;
    font-size: 0.875rem;
}

/* Dialog header close button fix */
:deep(.p-dialog-header-icon) {
    width: 2rem !important;
    height: 2rem !important;
    color: white !important;
    margin-right: 0.5rem !important;
    border-radius: 50% !important;
    transition: background-color 0.2s !important;
}

:deep(.p-dialog-header-icon:hover) {
    background-color: rgba(255, 255, 255, 0.2) !important;
    color: white !important;
}

:deep(.p-dialog-header-close-icon) {
    font-size: 1rem !important;
}

/* Custom buttons for the assignment dialog */
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    width: 100%;
}

.custom-cancel-btn, .custom-save-btn {
    border: none;
    border-radius: 6px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.custom-cancel-btn {
    background-color: #e5e7eb;
    color: #4b5563;
}

.custom-save-btn {
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
    color: white;
    box-shadow: 0 4px 6px rgba(67, 97, 238, 0.2);
}

.custom-cancel-btn:hover {
    background-color: #d1d5db;
    transform: translateY(-2px);
}

.custom-save-btn:hover {
    background: linear-gradient(135deg, #3a0ca3, #4361ee);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(67, 97, 238, 0.3);
}

.custom-save-btn:active, .custom-cancel-btn:active {
    transform: translateY(0);
}

.custom-save-btn::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: -100%;
    background: linear-gradient(90deg,
        rgba(255,255,255,0) 0%,
        rgba(255,255,255,0.2) 50%,
        rgba(255,255,255,0) 100%);
    animation: shine 3s infinite;
}

@keyframes shine {
    to {
        left: 100%;
    }
}

/* Math symbols and educational styling */
.edu-symbol-container {
    width: 100px;
    height: 100px;
    margin: 0 auto 1.5rem;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}

.math-symbol {
    font-size: 3.5rem;
    font-weight: bold;
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: pulse 2s infinite ease-in-out;
}

.edu-symbol {
    position: absolute;
    font-family: 'Times New Roman', serif;
    font-size: 1.5rem;
    font-weight: bold;
    color: #3a0ca3;
    opacity: 0.6;
    background: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    z-index: 1;
    animation: float 15s infinite ease-in-out;
    user-select: none;
}

.edu-symbol.pi {
    top: 15%;
    left: 8%;
    font-size: 2rem;
    animation-delay: 0s;
}

.edu-symbol.sigma {
    top: 25%;
    right: 8%;
    font-size: 1.8rem;
    animation-delay: 3s;
}

.edu-symbol.delta {
    bottom: 20%;
    left: 12%;
    font-size: 2rem;
    animation-delay: 1.5s;
}

.edu-symbol.omega {
    top: 60%;
    right: 10%;
    font-size: 1.8rem;
    animation-delay: 4s;
}

.edu-symbol.sqrt {
    bottom: 30%;
    right: 20%;
    font-size: 2rem;
    animation-delay: 2s;
}

.edu-symbol.infinity {
    bottom: 15%;
    left: 30%;
    width: 45px;
    height: 45px;
    font-size: 2.2rem;
    animation-delay: 7s;
}

.edu-symbol.function {
    top: 40%;
    left: 25%;
    width: 55px;
    height: 30px;
    border-radius: 15px;
    font-size: 1.2rem;
    animation-delay: 5s;
}

.edu-symbol.equation {
    bottom: 35%;
    right: 25%;
    width: 65px;
    height: 30px;
    border-radius: 15px;
    font-size: 1.1rem;
    animation-delay: 6s;
}

/* Enhanced dialog styling for Assign Teacher */
.assignment-dialog :deep(.p-dialog-header) {
    background: linear-gradient(135deg, #3a0ca3, #4361ee) !important;
}

.assignment-dialog :deep(.p-dialog-content) {
    background: #fff !important;
    background-image:
        radial-gradient(circle at 10% 20%, rgba(67, 97, 238, 0.03) 0%, transparent 20%),
        radial-gradient(circle at 90% 80%, rgba(58, 12, 163, 0.03) 0%, transparent 20%),
        linear-gradient(to bottom, #ffffff, #f8fafc) !important;
}

.assignment-header h4 {
    color: #1e293b;
    font-weight: 700;
    margin-top: 0.5rem;
    text-align: center;
    font-size: 1.5rem;
    background: linear-gradient(to right, #3a0ca3, #4361ee);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.field label {
    color: #334155;
    font-weight: 600;
}

/* Add a subtle graph paper background to the dialog content */
.dialog-content {
    position: relative;
    background-color: #fff;
    background-image:
        linear-gradient(rgba(67, 97, 238, 0.05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(67, 97, 238, 0.05) 1px, transparent 1px);
    background-size: 20px 20px;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-top: 1rem;
    z-index: 10;
}

/* Fix the animation container positioning */
.education-animation-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
    z-index: 1;
    opacity: 0.7;
    pointer-events: none; /* Ensure it doesn't block clicks */
}

/* Teacher symbol styling in the data table */
.teacher-symbol-container {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.16);
}

.teacher-symbol {
    color: white;
    font-size: 1.5rem;
    font-weight: bold;
    font-family: 'Times New Roman', serif;
    user-select: none;
}

.teacher-name-cell {
    display: flex;
    align-items: center;
    padding: 0.5rem 0;
}

.teacher-name {
    font-weight: 600;
    color: #1e293b;
    font-size: 1rem;
}

.teacher-role {
    font-size: 0.75rem;
    color: #4361ee;
    font-weight: 600;
    margin-top: 2px;
}

.grade-badge, .section-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    background: #f1f5f9;
    color: #475569;
    font-size: 0.875rem;
    font-weight: 500;
}

.add-subject-btn, .view-btn, .edit-btn, .delete-btn {
    transition: all 0.3s ease;
    border: none;
    border-radius: 6px;
    padding: 0.75rem 1rem;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.add-subject-btn {
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
    color: white;
    box-shadow: 0 4px 6px rgba(67, 97, 238, 0.2);
}

.add-subject-btn:hover {
    background: linear-gradient(135deg, #3a0ca3, #4361ee);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(67, 97, 238, 0.3);
}

.view-btn, .edit-btn, .delete-btn {
    margin-left: 1rem;
    background: #f1f5f9;
    color: #475569;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.view-btn:hover, .edit-btn:hover, .delete-btn:hover {
    background: #e2e8f0;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.floating-animation {
    animation: float 1.5s ease infinite;
}

.pulse-animation {
    animation: pulse 1.5s ease infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

/* Modern search box styling */
.modern-search {
    border-radius: 30px;
    padding: 0.75rem 1.25rem;
    border: 2px solid #e2e8f0;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    width: 100%;
    font-size: 1rem;
}

.modern-search:focus {
    border-color: #4361ee;
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.25);
    outline: none;
}

.search-box {
    position: relative;
    width: 100%;
    max-width: 500px;
}

/* Enhanced button animations */
.add-subject-btn, .view-btn, .edit-btn, .delete-btn {
    transition: all 0.3s ease;
    border: none;
    border-radius: 12px;
    padding: 0.75rem 0.75rem;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    margin: 0 0.25rem;
    width: 40px;
    height: 40px;
}

.add-subject-btn {
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
    color: white;
    box-shadow: 0 4px 6px rgba(67, 97, 238, 0.2);
    width: auto;
    padding: 0.75rem 1.25rem;
}

.btn-hover-text {
    margin-left: 0.5rem;
    opacity: 0;
    transform: translateX(-10px);
    transition: all 0.3s ease;
}

.add-subject-btn:hover .btn-hover-text {
    opacity: 1;
    transform: translateX(0);
}

.add-subject-btn:hover {
    background: linear-gradient(135deg, #3a0ca3, #4361ee);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(67, 97, 238, 0.3);
    width: auto;
    padding-right: 1.5rem;
}

.add-subject-btn::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(90deg,
        rgba(255,255,255,0) 0%,
        rgba(255,255,255,0.3) 50%,
        rgba(255,255,255,0) 100%);
    transform: rotate(30deg);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%) rotate(30deg);
    }
    100% {
        transform: translateX(100%) rotate(30deg);
    }
}

.view-btn {
    background: #e0f2fe;
    color: #0284c7;
}

.edit-btn {
    background: #fef3c7;
    color: #d97706;
}

.delete-btn {
    background: #fee2e2;
    color: #dc2626;
}

.view-btn:hover, .edit-btn:hover, .delete-btn:hover {
    transform: translateY(-3px) scale(1.05);
    filter: brightness(1.05);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
}

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
}

.floating-animation {
    animation: float 2s ease-in-out infinite;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
}

.pulse-animation {
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 4px 6px rgba(67, 97, 238, 0.2);
    }
    50% {
        transform: scale(1.05);
        box-shadow: 0 8px 15px rgba(67, 97, 238, 0.3);
    }
}

.grade-badge, .section-badge {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    font-weight: 600;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    min-width: 100px;
}

.grade-badge {
    background: linear-gradient(135deg, #e0f2fe, #bae6fd);
    color: #0369a1;
}

.section-badge {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1e40af;
}

.grade-badge:hover, .section-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.subjects-list {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #4b5563;
    font-weight: 500;
    transition: all 0.3s ease;
}

.subjects-list:hover {
    overflow: visible;
    white-space: normal;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    background-color: #f9fafb;
    border-radius: 8px;
    padding: 0.5rem;
    max-width: none;
    position: relative;
    z-index: 10;
}

.table-header {
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e5e7eb;
}

.table-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    background: linear-gradient(to right, #3a0ca3, #4361ee);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

/* Schedule Dialog Styles */
.schedule-dialog {
    overflow: hidden;
}

.schedule-dialog .schedule-header {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.schedule-form {
    background-color: #f8fafc;
    transition: all 0.3s ease;
}

.schedule-form:hover {
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}

.schedule-item {
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.schedule-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.schedule-grid {
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.time-label {
    font-weight: 600;
    color: #475569;
    background-color: #f8fafc;
}

.schedule-cell {
    min-height: 80px;
    background-color: #ffffff;
    transition: background-color 0.2s;
}

.schedule-cell:hover {
    background-color: #f8fafc;
}

.weekly-schedule {
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Teacher Card Styles */
.teacher-cards-container {
    margin-top: 1.5rem;
}

.teacher-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
}

.teacher-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.teacher-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.teacher-card-header {
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid #f0f0f0;
    background: linear-gradient(135deg, #f8f9fa, #e2e8f0);
}

.teacher-info {
    display: flex;
    flex-direction: column;
}

.teacher-card .teacher-name {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.teacher-card .teacher-role {
    font-size: 0.875rem;
    color: #4361ee;
    font-weight: 600;
}

.teacher-card-body {
    padding: 1rem;
    flex-grow: 1;
}

.primary-assignment, .subject-assignments {
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
}

.primary-assignment h4, .subject-assignments h4 {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--primary-700);
    margin-bottom: 0.5rem;
    padding-bottom: 0.25rem;
    border-bottom: 1px dashed var(--surface-200);
}

.not-assigned {
    padding: 0.2rem 0;
    font-style: italic;
}

.badge-muted {
    background-color: var(--surface-200);
    color: var(--text-color-secondary);
    font-size: 0.8rem;
    padding: 0.2rem 0.5rem;
    border-radius: 3px;
}

.assignment-details {
    padding: 0.2rem 0;
}

.teacher-detail {
    margin-bottom: 0.5rem;
    display: flex;
    align-items: flex-start;
}

.teacher-card-actions {
    display: flex;
    flex-direction: column;
    width: 100%;
}

.action-buttons-row {
    display: flex;
    width: 100%;
    margin-bottom: 4px;
}

.action-btn {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0.75rem 0.25rem !important;
    transition: all 0.2s ease;
    border: none !important;
    border-radius: 0 !important;
    color: white !important;
    box-shadow: none !important;
    height: auto !important;
    width: 78px !important;
    margin: 0 1px !important;
    flex: 1;
}

.action-btn i {
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
}

.action-btn span {
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
    text-align: center;
    width: 100%;
}

.action-btn.details-btn {
    background-color: #4096ff !important;
}

.action-btn.edit-btn {
    background-color: #ffa940 !important;
}

.action-btn.delete-btn {
    background-color: #ff4d4f !important;
}

.action-btn.assign-section-btn {
    background-color: #4096ff !important;
    flex: 1;
}

.action-btn.add-subject-btn {
    background-color: #6366f1 !important;
    flex: 1;
}

/* Button styles for different actions */
.details-btn {
    background: linear-gradient(135deg, #60a5fa, #3b82f6) !important;
}

.details-btn:hover {
    background: linear-gradient(to bottom, rgba(96, 165, 250, 0.1), rgba(59, 130, 246, 0.1)) !important;
    color: #3b82f6 !important;
}

.primary-btn {
    background: linear-gradient(135deg, #8b5cf6, #6d28d9) !important;
}

.primary-btn:hover {
    background: linear-gradient(to bottom, rgba(139, 92, 246, 0.1), rgba(109, 40, 217, 0.1)) !important;
    color: #6d28d9 !important;
}

.subject-btn {
    background: linear-gradient(135deg, #4ade80, #22c55e) !important;
}

.subject-btn:hover {
    background: linear-gradient(to bottom, rgba(74, 222, 128, 0.1), rgba(34, 197, 94, 0.1)) !important;
    color: #22c55e !important;
}

.edit-btn {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
}

.edit-btn:hover {
    background: linear-gradient(to bottom, rgba(242, 153, 74, 0.1), rgba(242, 201, 76, 0.1)) !important;
    color: #F2994A !important;
}

.delete-btn {
    background: linear-gradient(135deg, #f87171, #ef4444);
}

.delete-btn:hover {
    background: linear-gradient(to bottom, rgba(235, 87, 87, 0.1), rgba(242, 153, 74, 0.1)) !important;
    color: #EB5757 !important;
}

/* Update badge styles for cards */
.teacher-card .grade-badge,
.teacher-card .section-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-block;
}

.teacher-card .grade-badge {
    background-color: #dbeafe;
    color: #1e40af;
}

.teacher-card .section-badge {
    background-color: #e0f2fe;
    color: #0369a1;
}

.teacher-card .subjects-list {
    font-size: 0.875rem;
    color: #475569;
    font-weight: 500;
}

/* Empty and loading states */
.empty-message,
.loading-message {
    text-align: center;
    padding: 3rem 1rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.empty-message h3,
.loading-message h3 {
    margin-bottom: 0.5rem;
    color: #1e293b;
}

.empty-message p,
.loading-message p {
    color: #64748b;
}

.teacher-details-dialog {
    overflow: hidden;
}

.teacher-details-header {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.teacher-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    overflow: hidden;
    margin-right: 1rem;
}

.teacher-initials {
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 2rem;
    font-weight: bold;
    color: white;
    background: linear-gradient(135deg, #4361ee, #3a0ca3);
}

.teacher-details-name {
    flex-grow: 1;
}

.teacher-status {
    display: flex;
    align-items: center;
    margin-top: 0.5rem;
}

.head-teacher-badge {
    background: linear-gradient(135deg, #4ade80, #22c55e);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    margin-left: 0.5rem;
}

.teacher-details-info {
    margin-top: 1rem;
}

.info-section {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
}

.info-row {
    display: flex;
    flex-basis: 48%;
    margin-bottom: 1rem;
}

.info-label {
    font-weight: 600;
    color: #4b5563;
    width: 100px;
}

.info-value {
    flex-grow: 1;
}

.grade-badge-details, .section-badge-details {
    background: linear-gradient(135deg, #e0f2fe, #bae6fd);
    color: #0369a1;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
}

.section-badge-details {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #1e40af;
    color: #b91c1c;
    list-style-type: none;
    padding: 0.25rem 0;
}

.teacher-info-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    margin-bottom: 1rem;
}

.name-status {
    display: flex;
    flex-direction: column;
}

.name-status h1 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.refresh-button {
    display: flex;
    align-items: center;
    background: #f1f5f9;
    border: none;
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    color: #4361ee;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.refresh-button i {
    margin-right: 0.5rem;
}

.refresh-button:hover {
    background: #e0f2fe;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.primary-assignment-section {
    margin-top: 1.5rem;
    border-top: 1px solid var(--surface-200);
    padding-top: 1rem;
}

.primary-assignment-section h3 {
    font-size: 1.1rem;
    margin-bottom: 1rem;
    color: var(--primary-color);
}

.no-primary {
    font-style: italic;
    color: var(--text-color-secondary);
    padding: 0.5rem 0;
}

.no-assignment-text {
    display: inline-block;
    background-color: var(--surface-100);
    padding: 0.3rem 0.6rem;
    border-radius: 4px;
}

.subject-badge-details {
    display: inline-block;
    background-color: var(--primary-100);
    color: var(--primary-900);
    padding: 0.3rem 0.6rem;
    border-radius: 4px;
    font-weight: 500;
}

.teacher-details-subjects {
    margin-top: 1.5rem;
}

.teacher-details-subjects h3 {
    font-size: 1.1rem;
    margin-bottom: 1rem;
    color: var(--primary-color);
    border-top: 1px solid var(--surface-200);
    padding-top: 1rem;
}

.no-subjects {
    font-style: italic;
    color: var(--text-color-secondary);
    padding: 1rem 0;
}

.subject-name {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.primary-subject {
    font-weight: 600;
    color: var(--primary-700);
}

.subjects-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.subject-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.subject-tag {
    margin-right: 0.25rem;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0.25rem;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 1px 2px rgba(0, 0, 0, 0.24);
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.12);
}

.action-btn i {
    margin-right: 0.5rem;
    font-size: 1rem;
}

.action-btn.details-btn {
    background-color: #3182ce;
    border-color: #3182ce;
    color: #fff;
}

.action-btn.details-btn:hover {
    background-color: #2c5282;
    border-color: #2c5282;
}

.action-btn.edit-btn {
    background-color: #f59e0b;
    border-color: #f59e0b;
    color: #fff;
}

.action-btn.edit-btn:hover {
    background-color: #d97706;
    border-color: #d97706;
}

.action-btn.delete-btn {
    background-color: #ef4444;
    border-color: #ef4444;
    color: #fff;
}

.action-btn.delete-btn:hover {
    background-color: #dc2626;
    border-color: #dc2626;
}

.action-btn.assign-section-btn {
    background-color: #3182ce;
    border-color: #3182ce;
    color: #fff;
}

.action-btn.assign-section-btn:hover {
    background-color: #2c5282;
    border-color: #2c5282;
}

.action-btn.add-subject-btn {
    background-color: #38a169;
    border-color: #38a169;
    color: #fff;
}

.action-btn.add-subject-btn:hover {
    background-color: #276749;
    border-color: #276749;
}

.action-btn {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 0.75rem 0.5rem !important;
    transition: all 0.2s ease;
    border: none !important;
    border-radius: 0 !important;
    color: white !important;
    box-shadow: none !important;
    height: auto !important;
    width: 75px !important;
    margin: 0 !important;
}

.action-btn i {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
}

.action-btn span {
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
    text-align: center;
    width: 100%;
}

/* First row buttons */
.action-btn.details-btn {
    background-color: #4096ff !important;
    border-color: #4096ff !important;
}

.action-btn.edit-btn {
    background-color: #ffb340 !important;
    border-color: #ffb340 !important;
}

.action-btn.delete-btn {
    background-color: #ff4d4f !important;
    border-color: #ff4d4f !important;
}

/* Second row buttons */
.action-btn.assign-section-btn {
    background-color: #4096ff !important;
    border-color: #4096ff !important;
    width: 100px !important;
}

.action-btn.add-subject-btn {
    background-color: #5e35b1 !important;
    border-color: #5e35b1 !important;
    width: 100px !important;
}
</style>
