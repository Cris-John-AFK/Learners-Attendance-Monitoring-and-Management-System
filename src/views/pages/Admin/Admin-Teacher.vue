<script setup>
import axios from 'axios';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import { default as Dropdown } from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { computed, onBeforeMount, ref } from 'vue';

// API configuration with multiple endpoints to try
const API_ENDPOINTS = [
    'http://localhost:8000/api',  // Keep this first as it's now confirmed working
    'http://127.0.0.1:8000/api',
    'http://localhost/api',
    'http://localhost/lamms-backend/public/api'  // Add XAMPP path
];

// API base URL - will be updated if a working endpoint is found
let API_BASE_URL = API_ENDPOINTS[0];

const toast = useToast();
const teachers = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const expandedRows = ref([]);
const teacherDialog = ref(false);
const assignmentDialogVisible = ref(false);
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
const selectedTeacher = ref(null);
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
            return;
        }

        // If sections and grades aren't loaded yet, load them first
        if (sections.value.length === 0) {
            await loadSections();
        }

        if (subjects.value.length === 0) {
            await loadSubjects();
        }

        if (gradeOptions.value.length === 0) {
            await loadGrades();
        }

        // Process teachers data with proper assignment handling
        teachers.value = data.map(teacher => {
            // Normalize assignments
            let processedAssignments = [];

            if (teacher.assignments && Array.isArray(teacher.assignments)) {
                processedAssignments = teacher.assignments
                    .filter(a => a && a.section_id && a.subject_id) // Filter out invalid assignments
                    .map(assignment => {
                        // Find the full section from our loaded sections data
                        const sectionObj = sections.value.find(s => Number(s.id) === Number(assignment.section_id));

                        // Find the full subject from our loaded subjects data
                        const subjectObj = subjects.value.find(s => Number(s.id) === Number(assignment.subject_id));

                        // Create enhanced assignment with complete objects
                        return {
                            id: assignment.id,
                            section_id: Number(assignment.section_id),
                            subject_id: Number(assignment.subject_id),
                            is_primary: assignment.is_primary || false,
                            is_active: assignment.is_active !== undefined ? assignment.is_active : true,
                            // Include full section object with grade info
                            section: sectionObj || {
                                id: Number(assignment.section_id),
                                name: `Section ${assignment.section_id}`,
                                grade_id: null,
                                grade: null
                            },
                            // Include full subject object
                            subject: subjectObj || {
                                id: Number(assignment.subject_id),
                                name: `Subject ${assignment.subject_id}`
                            },
                            role: assignment.role || 'Teacher'
                        };
                    });
            }

            return {
                ...teacher,
                active_assignments: processedAssignments
            };
        });

        console.log('Successfully processed teachers with full assignment data:', teachers.value);

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

        // Initialize with empty array instead of fallback data
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

        // Use the tryApiEndpoints helper to handle multiple endpoints
        const data = await tryApiEndpoints('/sections');
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

        // Use the tryApiEndpoints helper to handle multiple endpoints
        const data = await tryApiEndpoints('/subjects');
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
            detail: 'Failed to load subjects. Using fallback data.',
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

        // Use the tryApiEndpoints helper to handle multiple endpoints
        const data = await tryApiEndpoints('/grades', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
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

// Validate assignment data
const validateAssignment = () => {
    assignmentErrors.value = [];
    let hasErrors = false;

    // Validate required fields
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

    // Early return if basic validation fails
    if (hasErrors) {
        return false;
    }

    // Convert IDs to strings for consistent comparison
    const sectionIdStr = String(assignment.value.section_id);
    const subjectIdStr = String(assignment.value.subject_id);

    // Validate section exists in available sections
    console.log('Validating section ID:', sectionIdStr);
    console.log('Available sections:', filteredSections.value.map(s => ({ id: String(s.id), name: s.name })));

    const sectionExists = filteredSections.value.some(section => String(section.id) === sectionIdStr);
    if (!sectionExists) {
        console.error('Invalid section ID:', sectionIdStr);
        console.log('Available section IDs:', filteredSections.value.map(s => String(s.id)));
        assignmentErrors.value.push(`Invalid section ID (${sectionIdStr}). Please select a valid section.`);
        hasErrors = true;
    }

    // Validate subject exists in available subjects
    console.log('Validating subject ID:', subjectIdStr);
    console.log('Available subject options:', subjectOptions.value.map(s => ({ value: s.value, label: s.label })));

    const subjectExists = subjectOptions.value.some(subject => subject.value === subjectIdStr);
    if (!subjectExists) {
        console.error('Invalid subject ID:', subjectIdStr);
        console.log('Available subject values:', subjectOptions.value.map(s => s.value));
        assignmentErrors.value.push(`Invalid subject ID (${subjectIdStr}). Please select a valid subject.`);
        hasErrors = true;
    }

    // Check for duplicate assignment
    if (editedTeacher.value && editedTeacher.value.active_assignments) {
        const isDuplicate = editedTeacher.value.active_assignments.some(a =>
            String(a.section_id) === sectionIdStr &&
            String(a.subject_id) === subjectIdStr &&
            (!a.id || a.id !== assignment.value.id) // Exclude the current assignment when editing
        );

        if (isDuplicate) {
            assignmentErrors.value.push('This teacher is already assigned to this section and subject');
            hasErrors = true;
        }
    }

    return !hasErrors;
};

// Save assignment
const saveAssignment = async () => {
    // Validate assignment data first
    if (!validateAssignment()) {
        console.error('Assignment validation failed:', assignmentErrors.value);
        toast.add({
            severity: 'error',
            summary: 'Validation Error',
            detail: assignmentErrors.value.join(', '),
            life: 5000
        });
        return;
    }

    console.log('Assignment data is valid, proceeding to save');

    try {
        // Find section and subject objects for display purposes
        const selectedSection = filteredSections.value.find(
            s => String(s.id) === String(assignment.value.section_id)
        );

        const selectedSubject = subjectOptions.value.find(
            s => s.value === String(assignment.value.subject_id)
        );

        if (!selectedSection || !selectedSubject) {
            console.error('Could not find section or subject:',
                {
                    sectionId: assignment.value.section_id,
                    subjectId: assignment.value.subject_id,
                    foundSection: selectedSection,
                    foundSubject: selectedSubject
                }
            );
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Could not find selected section or subject data',
                life: 5000
            });
            return;
        }

        // Create a new assignment object
        const newAssignment = {
            id: assignment.value.id || Date.now(), // Use existing ID or generate a temporary one
            section_id: assignment.value.section_id,
            subject_id: assignment.value.subject_id,
            is_primary: assignment.value.is_primary,
            role: assignment.value.role,
            // For display purposes
            section: {
                id: selectedSection.id,
                name: selectedSection.name,
                grade_id: selectedSection.grade_id,
                grade: selectedSection.grade
            },
            subject: {
                id: selectedSubject.value,
                name: selectedSubject.label
            }
        };

        console.log('New assignment created:', newAssignment);

        // Add to teacher's active assignments if not already there
        if (!editedTeacher.value.active_assignments) {
            editedTeacher.value.active_assignments = [];
        }

        // Check if we're editing an existing assignment
        const existingIndex = editedTeacher.value.active_assignments.findIndex(
            a => a.id === assignment.value.id
        );

        if (existingIndex >= 0) {
            // Update existing assignment
            editedTeacher.value.active_assignments[existingIndex] = newAssignment;
            console.log('Updated existing assignment at index', existingIndex);
        } else {
            // Add new assignment
            editedTeacher.value.active_assignments.push(newAssignment);
            console.log('Added new assignment to teacher');
        }

        // Send to backend API
        await saveAssignmentToBackend(editedTeacher.value.id, editedTeacher.value.active_assignments);

        // Close dialog and show success message
        hideAssignmentDialog();
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Assignment saved successfully',
            life: 3000
        });
    } catch (error) {
        console.error('Error saving assignment:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save assignment: ' + (error.message || 'Unknown error'),
            life: 5000
        });
    }
};

// Function to save assignments to backend
const saveAssignmentToBackend = async (teacherId, assignments) => {
    try {
        console.log('Saving assignments to backend for teacher ID:', teacherId);

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
        throw error;
    }
};

const expandAll = () => {
    expandedRows.value = [...teachers.value];
};

const collapseAll = () => {
    expandedRows.value = [];
};

const getDepartment = (assignments) => {
    return assignments[0]?.subject?.department || 'N/A';
};

const getRoomNumber = (assignments) => {
    return assignments[0]?.section?.room_number || 'N/A';
};

const getGradeName = (assignments) => {
    if (!assignments || assignments.length === 0) return 'N/A';

    const firstAssignment = assignments[0];

    // Try to get grade from the section's grade property
    if (firstAssignment.section && firstAssignment.section.grade) {
        return firstAssignment.section.grade.name || `Grade ${firstAssignment.section.grade.id}`;
    }

    // Try to get grade_id from section
    if (firstAssignment.section && firstAssignment.section.grade_id) {
        const gradeId = firstAssignment.section.grade_id;
        // Ensure we have grade options loaded
        if (gradeOptions.value.length === 0) {
            loadGrades();
            return 'Loading...';
        }
        const grade = gradeOptions.value.find(g => Number(g.id) === Number(gradeId));
        return grade ? grade.name : `Grade ${gradeId}`;
    }

    return 'N/A';
};

const getSectionName = (assignments) => {
    if (!assignments || assignments.length === 0) return 'N/A';

    const firstAssignment = assignments[0];

    // Get section name if available
    if (firstAssignment.section && firstAssignment.section.name) {
        return firstAssignment.section.name;
    }

    // Try to get section by ID
    if (firstAssignment.section_id) {
        const sectionId = firstAssignment.section_id;
        const section = sections.value.find(s => Number(s.id) === Number(sectionId));
        return section ? section.name : `Section ${sectionId}`;
    }

    return 'N/A';
};

const getSubjects = (assignments) => {
    return assignments.map(a => a.subject.name).join(', ');
};

// Handle grade change in the assignment dialog
const handleGradeChange = () => {
    // Reset selected section and subject when grade changes
    assignment.value.section_id = null;
    assignment.value.subject_id = null;

    if (!assignmentGrade.value) {
        filteredSections.value = [];
        subjectOptions.value = [];
        console.log('No grade selected, sections and subjects cleared');
        return;
    }

    console.log('Grade selected:', assignmentGrade.value);

    // Ensure we're working with properly loaded section data
    if (sections.value.length === 0) {
        loadSections().then(() => {
            filterSectionsForGrade();
        });
    } else {
        filterSectionsForGrade();
    }

    // Also ensure we have subjects loaded
    if (subjects.value.length === 0) {
        loadSubjects().then(() => {
            loadGradeSubjects(assignmentGrade.value);
        });
    } else {
        loadGradeSubjects(assignmentGrade.value);
    }
};

// Extract the section filtering logic to a separate function
const filterSectionsForGrade = () => {
    console.log('Available sections (before filtering):', sections.value.map(s => ({
        id: s.id,
        name: s.name,
        grade_id: s.grade_id,
        grade: s.grade ? { id: s.grade.id, name: s.grade.name } : null
    })));

    // Filter sections by selected grade
    filteredSections.value = sections.value.filter(section => {
        // Get the section's grade ID, handling different data structures
        let sectionGradeId;

        if (typeof section.grade_id === 'number' || typeof section.grade_id === 'string') {
            sectionGradeId = Number(section.grade_id);
        } else if (section.grade && (typeof section.grade.id === 'number' || typeof section.grade.id === 'string')) {
            sectionGradeId = Number(section.grade.id);
        } else {
            console.warn('Section has invalid grade_id structure:', section);
            return false;
        }

        // Compare with the selected grade (ensure we're comparing numbers)
        const selectedGradeId = Number(assignmentGrade.value);
        const result = sectionGradeId === selectedGradeId;

        if (result) {
            console.log(`Found matching section: ${section.name} (grade_id: ${sectionGradeId})`);
        }

        return result;
    });

    console.log('Filtered sections for grade ID', assignmentGrade.value, ':', filteredSections.value.map(s => ({
        id: s.id,
        name: s.name,
        grade_id: s.grade_id
    })));

    // Show notification based on results
    if (filteredSections.value.length === 0) {
        toast.add({
            severity: 'warn',
            summary: 'No Sections',
            detail: `No sections found for the selected grade. You may need to create sections first.`,
            life: 5000
        });
    } else {
        toast.add({
            severity: 'info',
            summary: 'Sections Found',
            detail: `Found ${filteredSections.value.length} sections for the selected grade.`,
            life: 3000
        });
    }
};

// Function to check API connectivity
const checkApiConnectivity = async () => {
    try {
        console.log('Checking API connectivity...');

        // Create a controller with timeout
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 3000);

        // Try a simple fetch to check if the API is available
        const response = await fetch(`${API_BASE_URL}/health-check`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            },
            signal: controller.signal
        }).catch(error => {
            // Handle network errors or timeouts
            console.error('API connectivity check failed:', error);
            return { ok: false, status: 0, statusText: error.message };
        });

        clearTimeout(timeoutId);

        if (response.ok) {
            console.log('API is accessible ✓');
            return true;
        } else {
            console.error(`API check failed: ${response.status} ${response.statusText}`);

            // If it's not a connection refused (which would be status 0), try with a different endpoint
            if (response.status !== 0) {
                // Try another endpoint as fallback
                const fallbackController = new AbortController();
                const fallbackTimeoutId = setTimeout(() => fallbackController.abort(), 3000);

                const fallbackResponse = await fetch(`${API_BASE_URL}/teachers`, {
                    method: 'HEAD',
                    signal: fallbackController.signal
                }).catch(() => ({ ok: false }));

                clearTimeout(fallbackTimeoutId);

                if (fallbackResponse.ok) {
                    console.log('API is accessible through fallback endpoint ✓');
                    return true;
                }
            }

            // API is not accessible
            toast.add({
                severity: 'warn',
                summary: 'Backend Unavailable',
                detail: 'Could not connect to the backend API. Some features may be limited.',
                life: 8000,
                sticky: true
            });
            return false;
        }
    } catch (error) {
        console.error('Error checking API connectivity:', error);

        // API is not accessible
        toast.add({
            severity: 'warn',
            summary: 'Backend Unavailable',
            detail: 'Could not connect to the backend API. Using offline mode with local data.',
            life: 8000,
            sticky: true
        });
        return false;
    }
};

// Function to try multiple API endpoints
const tryApiEndpoints = async (path, options = {}) => {
    let lastError = null;
    let lastResponse = null;

    // Prioritize the localhost:8000 endpoint which seems most reliable
    const prioritizedEndpoints = [
        'http://localhost:8000/api',  // This one works based on the 422 error
        'http://127.0.0.1:8000/api',
        'http://localhost/api',
        'http://localhost/lamms-backend/public/api'
    ];

    // Try each endpoint until one works
    for (const baseUrl of prioritizedEndpoints) {
        try {
            const url = `${baseUrl}${path}`;
            console.log(`Trying API endpoint: ${url}`);

            // Create abort controller for timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 3000); // Shorter timeout

            // Make the request
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(options.headers || {})
                },
                signal: controller.signal
            });

            clearTimeout(timeoutId);
            lastResponse = response;

            // If successful, update the base URL and return the response
            if (response.ok) {
                if (baseUrl !== API_BASE_URL) {
                    console.log(`Found working API endpoint: ${baseUrl}`);
                    API_BASE_URL = baseUrl;
                }

                // Handle empty responses - return empty array for empty responses
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const text = await response.text();
                    if (!text || text.trim() === '') {
                        console.log('Empty response received, returning empty array');
                        return [];
                    }
                    return JSON.parse(text);
                } else {
                    console.log('Non-JSON response received');
                    return [];
                }
            } else if (response.status === 422) {
                // Handle validation errors
                const errorData = await response.json();
                console.error('Validation errors:', errorData);

                // Format validation errors for toast
                let errorMessage = "Validation failed: ";
                if (errorData.errors) {
                    errorMessage += Object.values(errorData.errors)
                        .flat()
                        .join(', ');
                } else if (errorData.message) {
                    errorMessage += errorData.message;
                }

                throw new Error(errorMessage);
            }
        } catch (error) {
            lastError = error;
            if (error.name !== 'AbortError') {
                console.error(`Error with endpoint ${baseUrl}:`, error);
            }
        }
    }

    // If we got a response but it wasn't OK, try to get more error details
    if (lastResponse && !lastResponse.ok) {
        try {
            const errorText = await lastResponse.text();
            console.error('API error response:', errorText);
            throw new Error(`API error: ${lastResponse.status} ${lastResponse.statusText}. ${errorText}`);
        } catch (error) {
            throw new Error(`API error: ${lastResponse.status} ${lastResponse.statusText}`);
        }
    }

    // If all endpoints failed, throw the last error
    if (lastError) {
        throw lastError;
    } else {
        throw new Error('All API endpoints failed');
    }
};

// Lifecycle hooks
onBeforeMount(async () => {
    try {
        loading.value = true;

        // Try to find a working API endpoint first
        try {
            await tryApiEndpoints('/health-check').catch(error => {
                console.warn('Health check failed:', error.message);
                // This is just a test, so we can continue even if it fails
            });
        } catch (error) {
            console.warn('API discovery failed:', error.message);
        }

        // Load all required data in parallel
        await Promise.all([
            loadTeachers(),
            loadSections(),
            loadSubjects(),
            loadGrades()
        ]);
    } catch (error) {
        console.error('Error during initialization:', error);
        toast.add({
            severity: 'error',
            summary: 'Initialization Error',
            detail: 'There was a problem loading the application data. Some features may be limited.',
            life: 5000
        });
    } finally {
        loading.value = false;
    }
});

// Add these new refs for the scheduling functionality
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

    // Load existing schedule data for this subject and teacher
    loadSubjectSchedule(subject);

    scheduleDialog.value = true;
};

// Function to load schedule for a subject
const loadSubjectSchedule = async (subject) => {
    try {
        // This would fetch from the API in production
        // For now we'll use sample data
        console.log(`Loading schedule for ${subject.name}`);

        // Simulate loading (would be API call in production)
        scheduleData.value = subject.schedule || [];

        // If no schedule exists yet, initialize with empty array
        if (!scheduleData.value.length) {
            console.log('No existing schedule found, initializing empty schedule');
            scheduleData.value = [];
        } else {
            console.log('Loaded schedule data:', scheduleData.value);
        }
    } catch (error) {
        console.error('Error loading schedule:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load schedule data',
            life: 3000
        });
    }
};

// Function to save a schedule item
const saveScheduleItem = () => {
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

    // Generate unique ID and add to schedule
    const newItem = {
        id: 'schedule-' + Date.now(),
        day: newScheduleItem.value.day,
        timeSlot: newScheduleItem.value.timeSlot,
        room: newScheduleItem.value.room,
        section_id: newScheduleItem.value.section_id,
        // Get section name for display
        section_name: filteredSections.value.find(s => s.id === newScheduleItem.value.section_id)?.name || 'Unknown Section'
    };

    // Add to schedule data
    scheduleData.value.push(newItem);

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
        detail: 'Schedule item added',
        life: 3000
    });

    // In production, would save to backend
    saveScheduleToBackend();
};

// Function to remove a schedule item
const removeScheduleItem = (item) => {
    scheduleData.value = scheduleData.value.filter(i => i.id !== item.id);

    // In production, would sync with backend
    saveScheduleToBackend();

    toast.add({
        severity: 'info',
        summary: 'Removed',
        detail: 'Schedule item removed',
        life: 3000
    });
};

// Save schedule to backend
const saveScheduleToBackend = async () => {
    try {
        console.log('Saving schedule to backend:', scheduleData.value);

        // This would be an API call in production
        // For now, attach schedule to the subject in teacher's data
        if (selectedTeacher.value && selectedTeacher.value.subjects) {
            const subjectIndex = selectedTeacher.value.subjects.findIndex(
                s => s.id === selectedSubjectForSchedule.value.id
            );

            if (subjectIndex !== -1) {
                // Attach the schedule to the subject
                selectedTeacher.value.subjects[subjectIndex].schedule = [...scheduleData.value];
                console.log('Updated subject with schedule:', selectedTeacher.value.subjects[subjectIndex]);
            }
        }

        // For demo, we store in localStorage
        const teacherSchedules = JSON.parse(localStorage.getItem('teacherSchedules') || '{}');
        const key = `teacher_${selectedTeacher.value.id}_subject_${selectedSubjectForSchedule.value.id}`;
        teacherSchedules[key] = scheduleData.value;
        localStorage.setItem('teacherSchedules', JSON.stringify(teacherSchedules));

        console.log('Schedule saved to localStorage');
    } catch (error) {
        console.error('Error saving schedule:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save schedule',
            life: 3000
        });
    }
};

// Load sections for schedule
const loadSectionsForSchedule = () => {
    if (selectedSubjectForSchedule.value && selectedSubjectForSchedule.value.grade_id) {
        const gradeId = selectedSubjectForSchedule.value.grade_id;

        // Filter sections by grade
        filteredSections.value = sections.value.filter(section => {
            let sectionGradeId;
            if (typeof section.grade_id === 'number' || typeof section.grade_id === 'string') {
                sectionGradeId = Number(section.grade_id);
            } else if (section.grade && section.grade.id) {
                sectionGradeId = Number(section.grade.id);
            } else {
                return false;
            }

            return sectionGradeId === Number(gradeId);
        });

        console.log('Filtered sections for scheduling:', filteredSections.value);
    } else {
        // If no grade ID, use all sections
        filteredSections.value = sections.value;
    }
};

const getInitials = (teacher) => {
    return teacher.first_name.charAt(0) + teacher.last_name.charAt(0);
};

// Open assignment dialog
const openAssignmentDialog = (teacherData) => {
    editedTeacher.value = teacherData;
    assignmentGrade.value = null;
    assignmentErrors.value = [];
    filteredSections.value = [];

    // Initialize the assignment form
    assignment.value = {
        id: null,
        section_id: null,
        subject_id: null,
        is_primary: false,
        role: null
    };

    // Load initial data if not already loaded
    if (sections.value.length === 0) {
        loadSections();
    }

    if (subjects.value.length === 0) {
        loadSubjects();
    }

    assignmentDialogVisible.value = true;
    console.log('Assignment dialog initialized and opened for teacher:', teacherData.first_name, teacherData.last_name);
};

// Edit teacher function
const editTeacher = (teacherData) => {
    // Reset the form
    teacher.value = { ...teacherData };
    submitted.value = false;
    teacherDialog.value = true;
    console.log('Edit teacher dialog opened for:', teacherData.first_name, teacherData.last_name);
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
                            <div class="teacher-detail">
                                <span class="detail-label">Grade:</span>
                                <span class="grade-badge">{{ getGradeName(teacher.active_assignments) }}</span>
                </div>

                            <div class="teacher-detail">
                                <span class="detail-label">Section:</span>
                                <span class="section-badge">{{ getSectionName(teacher.active_assignments) }}</span>
                </div>

                            <div class="teacher-detail">
                                <span class="detail-label">Subjects:</span>
                                <span class="subjects-list">{{ getSubjects(teacher.active_assignments) }}</span>
                </div>
            </div>

                        <div class="teacher-card-actions">
                            <Button class="action-btn schedule-btn" @click="viewTeacher(teacher)" v-tooltip.top="'View Details'">
                                <i class="pi pi-user"></i>
                                <span>Details</span>
                            </Button>

                            <Button class="action-btn subject-btn" @click="openAssignmentDialog(teacher)" v-tooltip.top="'Add Subject'">
                                <i class="pi pi-plus"></i>
                                <span>Subject</span>
                            </Button>

                            <Button class="action-btn edit-btn" @click="editTeacher(teacher)" v-tooltip.top="'Edit Teacher'">
                                <i class="pi pi-pencil"></i>
                                <span>Edit</span>
                            </Button>

                            <Button class="action-btn delete-btn" @click="confirmDeleteTeacher(teacher)" v-tooltip.top="'Delete Teacher'">
                                <i class="pi pi-trash"></i>
                                <span>Delete</span>
                            </Button>
                </div>
                </div>
            </div>
                </div>
                </div>

        <!-- All Dialogs go here, unchanged -->

        <!-- Teacher Details Dialog -->
        <Dialog v-model:visible="teacherDetailsDialog" modal header="Teacher Details" :style="{ width: '550px' }" class="teacher-details-dialog">
            <div class="p-fluid" v-if="selectedTeacher">
                <div class="teacher-details-header">
                    <div class="teacher-avatar">
                        <div class="teacher-initials">{{ getInitials(selectedTeacher) }}</div>
                </div>
                    <div class="teacher-details-name">
                        <h2>{{ selectedTeacher.first_name }} {{ selectedTeacher.last_name }}</h2>
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
                        <div class="info-row">
                            <div class="info-label">Grade Level</div>
                            <div class="info-value grade-badge-details">{{ getGradeName(selectedTeacher.active_assignments) }}</div>
                </div>
                        <div class="info-row">
                            <div class="info-label">Section</div>
                            <div class="info-value section-badge-details">{{ getSectionName(selectedTeacher.active_assignments) }}</div>
                    </div>
                </div>
                </div>

                <div class="teacher-details-subjects">
                    <h3>Teaching Subjects</h3>
                    <div v-if="!selectedTeacher.active_assignments || selectedTeacher.active_assignments.length === 0" class="no-subjects">
                        No subjects assigned to this teacher yet.
                    </div>
                    <DataTable v-else :value="selectedTeacher.active_assignments" scrollable scrollHeight="200px" class="subjects-table">
                        <Column header="Subject">
                            <template #body="slotProps">
                                <div class="subject-name">{{ slotProps.data.subject?.name || 'Unknown Subject' }}</div>
                            </template>
                        </Column>
                        <Column header="Section">
                            <template #body="slotProps">
                                <div>{{ slotProps.data.section?.name || 'Unknown Section' }}</div>
                            </template>
                        </Column>
                        <Column header="Status">
                            <template #body>
                                <Tag value="active" severity="success" />
                            </template>
                        </Column>
                        <Column header="Actions">
                            <template #body>
                                <Button icon="pi pi-calendar"
                                    class="p-button-rounded p-button-primary p-button-sm"
                                    v-tooltip.top="'Manage Schedule'" />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <div class="teacher-details-actions">
                    <Button label="Add Subject" icon="pi pi-plus" @click="openAssignmentDialog(selectedTeacher)" class="p-button-outlined p-button-primary" />
                    <Button label="Edit Details" icon="pi pi-pencil" @click="editTeacher(selectedTeacher)" class="p-button-outlined" />
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
                <div v-if="assignmentErrors.length > 0" class="error-container mb-2">
                    <ul class="error-list p-0 m-0">
                        <li v-for="(error, index) in assignmentErrors" :key="index" class="error-item">
                            {{ error }}
                        </li>
                    </ul>
                </div>

                <div class="compact-field">
                    <label for="grade">Grade Level*</label>
                    <Dropdown id="grade" v-model="assignmentGrade"
                             :options="gradeOptions"
                             optionLabel="name"
                             optionValue="id"
                             placeholder="Select Grade"
                             class="w-full"
                             @change="handleGradeChange" />
                </div>

                <div class="compact-field">
                    <label for="section">Section*</label>
                    <Dropdown id="section" v-model="assignment.section_id"
                             :options="filteredSections"
                             optionLabel="name"
                             optionValue="id"
                             placeholder="Select Section"
                             class="w-full"
                             :disabled="!assignmentGrade" />
                </div>

                <div class="compact-field">
                    <label for="subject">Subject*</label>
                    <Dropdown id="subject" v-model="assignment.subject_id"
                             :options="subjectOptions"
                             optionLabel="label"
                             optionValue="value"
                             placeholder="Select Subject"
                             class="w-full"
                             :disabled="!assignmentGrade" />
                </div>

                <div class="compact-field">
                    <label for="teacher_role">Teacher Role*</label>
                    <Dropdown id="teacher_role" v-model="assignment.role"
                             :options="teacherRoleOptions"
                             optionLabel="label"
                             optionValue="value"
                             placeholder="Select Role"
                             class="w-full" />
                </div>

                <div class="compact-field">
                    <div class="p-field-checkbox align-items-center">
                        <input type="checkbox" id="is_primary" v-model="assignment.is_primary" />
                        <label for="is_primary" class="checkbox-label ml-2 mb-0">Primary Teacher</label>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="dialog-footer p-2">
                    <Button label="Cancel" icon="pi pi-times" @click="hideAssignmentDialog" class="p-button-text" />
                    <Button label="Assign" icon="pi pi-check" @click="saveAssignment" class="p-button-primary" />
                </div>
            </template>
        </Dialog>
    </div>
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
    content: "•";
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
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.teacher-detail {
    display: flex;
    align-items: center;
}

.detail-label {
    font-weight: 600;
    color: #64748b;
    width: 80px;
}

.teacher-card-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
    padding: 1rem;
    background-color: #f8fafc;
}

.action-btn {
    border: none !important;
    border-radius: 0 !important;
    background: transparent !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 1.25rem 0.5rem !important;
    transition: all 0.3s ease;
    color: #4A5568 !important;
    box-shadow: none !important;
    height: auto !important;
    width: 100% !important;
}

.action-btn i {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
}

.action-btn span {
    font-size: 0.9rem;
    font-weight: 500;
}

.schedule-btn {
    background: linear-gradient(135deg, #60a5fa, #3b82f6);
}

.schedule-btn:hover {
    background: linear-gradient(to bottom, rgba(67, 24, 255, 0.1), rgba(151, 71, 255, 0.1)) !important;
    color: #4318FF !important;
}

.subject-btn {
    background: linear-gradient(135deg, #4ade80, #22c55e);
}

.subject-btn:hover {
    background: linear-gradient(to bottom, rgba(45, 156, 219, 0.1), rgba(86, 204, 242, 0.1)) !important;
    color: #2D9CDB !important;
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
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
}

.subjects-table {
    margin-top: 1rem;
}

.subject-name {
    font-weight: 500;
    color: #1e293b;
}

.teacher-details-subjects {
    margin-top: 1.5rem;
}

.teacher-details-subjects h3 {
    font-size: 1.1rem;
    font-weight: 600;
    color: #334155;
    margin-top: 0;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.no-subjects {
    text-align: center;
    padding: 1.5rem;
    background-color: #f8fafc;
    border-radius: 8px;
    color: #64748b;
    font-style: italic;
}

.teacher-details-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
    justify-content: flex-end;
}

.empty-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 3rem;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 10px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
    margin: 2rem auto;
    max-width: 500px;
    height: 300px;
}

.empty-message h3 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #495057;
}

.empty-message p {
    color: #6c757d;
    margin-bottom: 1rem;
}

.dialog-footer-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1rem;
}

.cancel-button, .save-button-custom {
    transition: all 0.3s ease;
}

.save-button-custom {
    background: linear-gradient(135deg, #4361ee, #3a0ca3) !important;
    border: none !important;
    box-shadow: 0 4px 8px rgba(67, 97, 238, 0.2);
}

.save-button-custom:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(67, 97, 238, 0.3);
}

/* Compact assignment dialog styles */
.compact-form {
    padding: 0.5rem;
}

.compact-field {
    margin-bottom: 0.75rem;
}

.compact-field label {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
    color: #334155;
}

.error-container {
    background-color: #fef2f2;
    border-left: 3px solid #ef4444;
    padding: 0.5rem;
    border-radius: 0.25rem;
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
}

.error-item {
    color: #b91c1c;
    list-style-type: none;
    padding: 0.25rem 0;
}

.assignment-dialog :deep(.p-dialog-content) {
    padding: 1rem !important;
}

.assignment-dialog :deep(.p-dropdown) {
    width: 100%;
}

.assignment-dialog :deep(.p-dialog-footer) {
    padding: 0.5rem 1rem !important;
    border-top: 1px solid #e2e8f0;
}
</style>
