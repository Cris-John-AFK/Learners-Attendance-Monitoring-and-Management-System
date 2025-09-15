<template>
    <div class="card p-8 shadow-xl rounded-xl bg-white border border-gray-100">
        <!-- Modern Gradient Header -->
        <div class="modern-header-container mb-8">
            <div class="gradient-header">
                <div class="header-content">
                    <div class="header-left">
                        <div class="header-icon">
                            <i class="pi pi-users"></i>
                        </div>
                        <div class="header-text">
                            <h1 class="header-title">Teacher Management System</h1>
                            <p class="header-subtitle">Naawan Central School</p>
                            <div class="teacher-count">
                                <i class="pi pi-chart-bar mr-2"></i>
                                Total Teachers: <span class="count-badge">{{ filteredTeachers.length }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="header-actions">
                        <div class="search-container">
                            <span class="p-input-icon-left">
                                <i class="pi pi-search" />
                                <InputText v-model="searchQuery" placeholder="Search teachers..." class="search-input" />
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Teachers Cards -->
        <div class="teachers-container">
            <div v-if="loading" class="loading-container">
                <ProgressSpinner />
                <p>Loading teachers...</p>
            </div>
            <div v-else-if="!loading && filteredTeachers.length > 0" class="teacher-cards">
                <div v-for="teacher in filteredTeachers" :key="teacher.id" class="teacher-card">
                    <div class="teacher-card-header">
                        <div class="teacher-info">
                            <div class="teacher-name">{{ teacher.first_name }} {{ teacher.last_name }}</div>
                        </div>
                    </div>

                    <div class="teacher-card-body">
                        <!-- Homeroom Section -->
                        <div class="homeroom-section">
                            <h4>Homeroom</h4>
                            <div v-if="teacher.primary_assignment" class="assignment-info">
                                <div class="section-info">
                                    <div class="section-name">{{ teacher.primary_assignment.section?.name || 'N/A' }}</div>
                                    <div class="grade-level">{{ teacher.primary_assignment.section?.grade?.name || 'N/A' }}</div>
                                </div>
                            </div>
                            <div v-else class="not-assigned">
                                <i class="pi pi-exclamation-triangle"></i>
                                No homeroom assigned
                            </div>
                        </div>

                        <!-- Teaching Subjects Section -->
                        <div class="teaching-subjects-section">
                            <h4>Teaching Subjects</h4>
                            <div v-if="teacher.subject_assignments && teacher.subject_assignments.length > 0" class="subjects-list">
                                <div v-for="(assignment, index) in teacher.subject_assignments" :key="assignment.id || index" class="subject-item">
                                    <div class="subject-name">
                                        <i class="pi pi-book subject-icon"></i>
                                        {{ assignment.subject?.name || 'Unknown Subject' }}
                                    </div>
                                    <div class="section-name">{{ assignment.section?.name || 'N/A' }}</div>
                                </div>
                            </div>
                            <div v-else class="not-assigned">
                                <i class="pi pi-exclamation-triangle"></i>
                                No subjects assigned
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="teacher-card-actions">
                        <div class="action-buttons-row">
                            <button class="action-btn details-btn" @click="viewTeacher(teacher)" title="View Details">
                                <i class="pi pi-eye"></i>
                                <span>Details</span>
                            </button>
                            <button class="action-btn edit-btn" @click="editTeacher(teacher)" title="Edit Teacher">
                                <i class="pi pi-pencil"></i>
                                <span>Edit</span>
                            </button>
                            <button class="action-btn archive-btn" @click="archiveTeacher(teacher)" title="Archive Teacher">
                                <i class="pi pi-archive"></i>
                                <span>Archive</span>
                            </button>
                        </div>
                        <div class="action-buttons-row">
                            <button class="action-btn assign-section-btn" @click="assignSection(teacher)" title="Assign Section">
                                <i class="pi pi-home"></i>
                                <span>Assign Section</span>
                            </button>
                            <button class="action-btn add-subject-btn" @click="addSubject(teacher)" title="Add Subject">
                                <i class="pi pi-plus"></i>
                                <span>Add Subject</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="no-data-message">
                <i class="pi pi-users" style="font-size: 3rem; opacity: 0.3; margin-bottom: 1rem"></i>
                <p>No teachers found</p>
                <p class="no-data-subtitle">Try adjusting your search criteria or add new teachers to get started.</p>
            </div>
        </div>

        <!-- Teacher Registration/Edit Dialog -->
        <Dialog v-model:visible="teacherDialog" :header="dialogTitle" :style="{ width: '700px' }" :modal="true" class="registration-dialog">
            <div class="p-fluid">
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
                        <label for="address">Address</label>
                        <InputText id="address" v-model="teacher.address" />
                    </div>

                    <div class="field">
                        <label for="username">Username*</label>
                        <InputText id="username" v-model="teacher.username" required :class="{ 'p-invalid': submitted && !teacher.username }" />
                        <small class="p-error" v-if="submitted && !teacher.username">Username is required.</small>
                    </div>

                    <div class="field">
                        <label for="password">Password*</label>
                        <Password id="password" v-model="teacher.password" required :class="{ 'p-invalid': submitted && !teacher.password }" :feedback="false" />
                        <small class="p-error" v-if="submitted && !teacher.password">Password is required.</small>
                    </div>

                    <div class="field">
                        <label for="gender">Gender*</label>
                        <Dropdown id="gender" v-model="teacher.gender" :options="genderOptions" optionLabel="label" optionValue="value" placeholder="Select Gender" :class="{ 'p-invalid': submitted && !teacher.gender }" />
                        <small class="p-error" v-if="submitted && !teacher.gender">Gender is required.</small>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="hideDialog" />
                <Button label="Save" icon="pi pi-check" class="p-button-text" @click="saveTeacher" />
            </template>
        </Dialog>

        <!-- Teacher Details Dialog -->
        <Dialog v-model:visible="teacherDetailsDialog" modal :style="{ width: '600px', maxHeight: '80vh', padding: '0' }" class="teacher-details-dialog" :showHeader="false" :contentStyle="{ padding: '0', margin: '0' }">
            <div class="teacher-details-content" v-if="selectedTeacher">
                <!-- Custom Header -->
                <div class="dialog-header">
                    <div class="header-content">
                        <div class="teacher-avatar">
                            <span class="avatar-text">{{ getInitials(selectedTeacher) }}</span>
                        </div>
                        <div class="teacher-basic-info">
                            <h2 class="teacher-name">{{ selectedTeacher?.first_name }} {{ selectedTeacher?.last_name }}</h2>
                            <p class="teacher-email">{{ selectedTeacher?.email || 'No email provided' }}</p>
                        </div>
                    </div>
                    <div class="header-actions">
                        <button @click="forceRefreshTeacher(selectedTeacher?.id)" class="refresh-btn" title="Refresh Data">
                            <i class="pi pi-refresh"></i>
                        </button>
                        <button @click="teacherDetailsDialog = false" class="close-btn" title="Close">
                            <i class="pi pi-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="info-card">
                    <h3 class="section-title">
                        <i class="pi pi-user section-icon"></i>
                        Personal Information
                    </h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Phone</span>
                            <span class="info-value">{{ selectedTeacher.phone_number || 'Not provided' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Gender</span>
                            <span class="info-value">{{ selectedTeacher.gender || 'Not provided' }}</span>
                        </div>
                        <div class="info-item full-width">
                            <span class="info-label">Address</span>
                            <span class="info-value">{{ selectedTeacher.address || 'Not provided' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Homeroom Assignment -->
                <div class="info-card">
                    <h3 class="section-title">
                        <i class="pi pi-home section-icon"></i>
                        Homeroom Assignment
                    </h3>
                    <div v-if="selectedTeacher.primary_assignment" class="homeroom-card">
                        <div class="homeroom-info">
                            <div class="section-badge">
                                <span class="section-name">{{ selectedTeacher.primary_assignment.section?.name || 'N/A' }}</span>
                                <span class="grade-badge">{{ selectedTeacher.primary_assignment.section?.grade?.name || 'Grade 3' }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="empty-state">
                        <i class="pi pi-exclamation-triangle empty-icon"></i>
                        <span class="empty-text">No homeroom assigned</span>
                    </div>
                </div>

                <!-- Teaching Subjects -->
                <div class="info-card">
                    <h3 class="section-title">
                        <i class="pi pi-book section-icon"></i>
                        Teaching Subjects
                    </h3>
                    <div v-if="selectedTeacher.subject_assignments && selectedTeacher.subject_assignments.length > 0" class="subjects-grid">
                        <div v-for="assignment in selectedTeacher.subject_assignments" :key="assignment.id" class="subject-card">
                            <div class="subject-header">
                                <i class="pi pi-book subject-icon"></i>
                                <span class="subject-name">{{ assignment.subject?.name || 'Unknown Subject' }}</span>
                            </div>
                            <div class="subject-section">
                                <span class="section-label">Section:</span>
                                <span class="section-value">{{ assignment.section?.name || 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="empty-state">
                        <i class="pi pi-exclamation-triangle empty-icon"></i>
                        <span class="empty-text">No subjects assigned</span>
                    </div>
                </div>
            </div>
        </Dialog>
    </div>
</template>

<script setup>
import api, { API_BASE_URL } from '@/config/axios';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import { default as Dropdown } from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

// Helper function to try multiple API endpoints until one works
const tryApiEndpoints = async (path, options = {}) => {
    try {
        console.log(`Making API request to: ${path}`);

        // First try the requested path directly
        try {
            const response = await api(path, options);
            console.log('API response successful:', response);
            return response.data;
        } catch (error) {
            console.warn(`Failed to access ${path}, trying alternative endpoints...`, error);

            // If this is a teacher request without API prefix, try with API prefix
            if (path.includes('/teachers') && !path.includes('/api/')) {
                console.log('Trying teacher endpoint with API prefix');
                const apiResponse = await api(`/api${path}`, options);
                if (apiResponse.data) {
                    console.log('API teacher endpoint successful');
                    return apiResponse.data;
                }
            }

            // If this is a subject request without API prefix, try with API prefix
            if (path.includes('/subjects') && !path.includes('/api/')) {
                console.log('Trying subject endpoint with API prefix');
                const apiResponse = await api(`/api${path}`, options);
                if (apiResponse.data) {
                    console.log('API subject endpoint successful');
                    return apiResponse.data;
                }
            }

            // If no alternative worked, throw the original error
            throw error;
        }
    } catch (error) {
        console.error('All API request attempts failed:', error);
        throw error;
    }
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
    gender: null
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

    return teachers.value.filter((t) => {
        const fullName = `${t.first_name} ${t.last_name}`.toLowerCase();
        return fullName.includes(searchQuery.value.toLowerCase());
    });
});

const filteredSubjects = computed(() => {
    if (!subjectSearchQuery.value) return availableSubjects.value;
    return availableSubjects.value.filter((s) => s.name.toLowerCase().includes(subjectSearchQuery.value.toLowerCase()) || s.grade.toLowerCase().includes(subjectSearchQuery.value.toLowerCase()));
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
                'Content-Type': 'application/json'
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
    if (!selectedSubjects.value.some((s) => s.id === subject.id)) {
        selectedSubjects.value.push(subject);
    }
};

const removeSubjectFromSelection = (subject) => {
    selectedSubjects.value = selectedSubjects.value.filter((s) => s.id !== subject.id);
};

const saveSelectedSubjects = async () => {
    try {
        const response = await fetch(`http://localhost:8000/api/teachers/${selectedTeacher.value.id}/subjects`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                subject_ids: selectedSubjects.value.map((s) => s.id)
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

        // Use the API_BASE_URL constant instead of direct URL
        const teachersData = await tryApiEndpoints(`${API_BASE_URL}/teachers`);

        if (!teachersData || teachersData.length === 0) {
            console.warn('No teachers returned from API');
            toast.add({
                severity: 'warn',
                summary: 'No Teachers',
                detail: 'No teachers found in the database.',
                life: 5000
            });
            teachers.value = [];
            return;
        }

        teachers.value = teachersData;
        console.log(`Loaded ${teachers.value.length} teachers successfully`);

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
            detail: 'Failed to load teachers.',
            life: 3000
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
        const response = await api.get(`${API_BASE_URL}/sections`);
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
        sections.value = data.map((section) => ({
            id: Number(section.id), // Ensure ID is a number
            name: section.name || `Section ${section.id}`,
            grade_id: Number(section.grade_id), // Ensure grade_id is a number
            grade: section.grade
                ? {
                      id: Number(section.grade.id), // Ensure grade.id is a number
                      name: section.grade.name || `Grade ${section.grade.id}`
                  }
                : {
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
        const response = await api.get(`${API_BASE_URL}/subjects`);
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
        subjects.value = data.map((subject) => ({
            id: Number(subject.id),
            name: subject.name || `Subject ${subject.id}`,
            department: subject.department || 'General',
            grade_id: subject.grade_id ? Number(subject.grade_id) : null,
            grade: subject.grade
                ? {
                      id: Number(subject.grade.id),
                      name: subject.grade.name
                  }
                : null
        }));

        console.log('Successfully loaded subjects with normalized IDs:', subjects.value);

        // Initialize subjectOptions with the loaded subjects for dropdowns
        subjectOptions.value = subjects.value.map((subject) => ({
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
        const response = await api.get(`${API_BASE_URL}/grades`);
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
        gradeOptions.value = data.map((grade) => ({
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
        subjectOptions.value = subjects.value.map((subject) => ({
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
    api.get(`${API_BASE_URL}/subjects`)
        .then((response) => {
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
        .catch((error) => {
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
        const existingAssignment = editedTeacher.value.active_assignments.find((a) => Number(a.section_id) === sectionId && Number(a.subject_id) === subjectId && (!assignment.value.id || a.id !== assignment.value.id));

        if (existingAssignment) {
            assignmentErrors.value.push(`This teacher is already assigned to this section and subject (${existingAssignment.role || 'subject'} role)`);
            hasErrors = true;
        }

        // Check primary teacher conflict if this is a primary assignment
        if (isPrimary) {
            // Check if teacher already has a different primary assignment
            const existingPrimary = editedTeacher.value.active_assignments.find(
                (a) => (a.is_primary || a.role === 'primary') && (Number(a.section_id) !== sectionId || Number(a.subject_id) !== subjectId) && (!assignment.value.id || a.id !== assignment.value.id)
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
            // Skip checking the current teacher we're editing
            if (editedTeacher.value && teacher.id === editedTeacher.value.id) {
                continue;
            }

            // Check if any other teacher is primary for this section
            const conflictingPrimary = teacher.active_assignments?.find((a) => Number(a.section_id) === sectionId && (a.is_primary || a.role === 'primary'));

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
            const conflictingAssignment = teacher.active_assignments?.find((a) => Number(a.section_id) === sectionId && Number(a.subject_id) === subjectId);

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
            toast.add({ severity: 'error', summary: 'Missing Required Fields', detail: 'Please select grade, section, subject, and role', life: 3000 });
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
        const response = await api({
            method: 'PUT',
            url: `${API_BASE_URL}/teachers/${teacherId}/assignments`,
            data: payload
        }).catch((error) => {
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
                } else if (
                    statusCode === 409 ||
                    (statusCode === 500 &&
                        errorData.message &&
                        (errorData.message.includes('already assigned') ||
                            errorData.message.includes('duplicate') ||
                            errorData.message.includes('SQLSTATE[23000]') || // SQL integrity constraint error
                            errorData.message.includes('constraint') ||
                            errorData.message.toLowerCase().includes('unique')))
                ) {
                    // Conflict error or constraint violation
                    throw new Error('This assignment already exists or conflicts with existing assignments. Please check the current assignments and try again.');
                } else if (errorData.message) {
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
            const checkResponse = await api.get(`${API_BASE_URL}/check-assignment?section_id=${assignment.section_id}&subject_id=${assignment.subject_id}`);

            if (checkResponse?.data?.exists && checkResponse?.data?.teacher_id && checkResponse.data.teacher_id !== editedTeacher.value.id) {
                conflicts.push(`The section and subject is already assigned to another teacher (${checkResponse.data.teacher_name || 'Unknown Teacher'})`);
            }
        }

        // If endpoint wasn't available, try to check against local data
        if (conflicts.length === 0) {
            // Fallback to client-side check of other teachers
            for (const teacher of teachers.value) {
                // Skip checking the current teacher we're editing
                if (editedTeacher.value && teacher.id === editedTeacher.value.id) {
                    continue;
                }

                // Check each assignment
                for (const newAssignment of assignments) {
                    if (!newAssignment.section_id || !newAssignment.subject_id) continue;

                    // Look for conflicts
                    if (teacher.active_assignments && Array.isArray(teacher.active_assignments)) {
                        const hasConflict = teacher.active_assignments.some((a) => String(a.section_id) === String(newAssignment.section_id) && String(a.subject_id) === String(newAssignment.subject_id));

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
                filteredSections.value = sections.value.filter((section) => Number(section.grade_id) === Number(gradeId));
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
        const primaryAssignment = teacherData.primary_assignment || (teacherData.active_assignments && teacherData.active_assignments.find((a) => a.is_primary || a.role === 'primary'));

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
    Promise.all([sections.value.length === 0 ? loadSections() : Promise.resolve(), subjects.value.length === 0 ? loadSubjects() : Promise.resolve(), gradeOptions.value.length === 0 ? loadGrades() : Promise.resolve()]).then(() => {
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
        gender: null
    };
    submitted.value = false;
    teacherDialog.value = true;
};

// Add missing schedule-related refs
const scheduleDialog = ref(false);
const selectedSubjectForSchedule = ref(null);
const scheduleData = ref([]);
const daysOfWeek = ref(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);
const timeSlots = ref(['7:00 AM - 8:00 AM', '8:00 AM - 9:00 AM', '9:00 AM - 10:00 AM', '10:00 AM - 11:00 AM', '11:00 AM - 12:00 PM', '1:00 PM - 2:00 PM', '2:00 PM - 3:00 PM', '3:00 PM - 4:00 PM', '4:00 PM - 5:00 PM']);
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
        const response = await axios.get(`${API_BASE_URL}/teachers/${selectedTeacher.value.id}/subjects/${subject.id}/schedule`).catch((error) => {
            console.warn('Schedule API error:', error);
            // If API endpoint not found, handle gracefully
            if (error.response && error.response.status === 404) {
                return { data: [] };
            }
            throw error;
        });

        // Process and set schedule data
        scheduleData.value = Array.isArray(response.data) ? response.data : response.data.data || [];

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
    const hasConflict = scheduleData.value.some((item) => item.day === newScheduleItem.value.day && item.timeSlot === newScheduleItem.value.timeSlot);

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
        const selectedSection = filteredSections.value.find((s) => String(s.id) === String(newScheduleItem.value.section_id));

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
        const response = await axios.post(`${API_BASE_URL}/schedules`, scheduleItem);

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
        scheduleData.value = scheduleData.value.filter((i) => i.id !== item.id);

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
        const response = await axios.put(`${API_BASE_URL}/teachers/${selectedTeacher.value.id}/subjects/${selectedSubjectForSchedule.value.id}/schedule`, { schedule: scheduleData.value });

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
        const primaryAssignment = assignments.active_assignments.find((a) => a.is_primary === true || a.role === 'primary');

        if (primaryAssignment && primaryAssignment.section.grade) {
            return primaryAssignment.section.grade.name;
        }

        // If no primary, try any assignment with grade info
        const sectionWithGrade = assignments.active_assignments.find((a) => a.section && a.section.grade);
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
        const primaryAssignment = assignments.active_assignments.find((a) => a.is_primary === true || a.role === 'primary');

        if (primaryAssignment && primaryAssignment.section) {
            return primaryAssignment.section.name;
        }

        // If not found, get unique section names
        const sectionNames = [...new Set(assignments.active_assignments.filter((a) => a.section && a.section.name).map((a) => a.section.name))];

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
    const subjectNames = [...new Set(allAssignments.filter((a) => a.subject && a.subject.name).map((a) => a.subject.name))];

    return subjectNames.join(', ') || 'Unknown Subject';
};

// Find any primary assignments for a teacher
const findPrimaryAssignments = (teacher) => {
    if (!teacher?.active_assignments || !Array.isArray(teacher.active_assignments)) {
        return [];
    }

    return teacher.active_assignments.filter((a) => a.is_primary === true || a.role === 'primary');
};

// Find ordinary subject assignments for a teacher
const findSubjectAssignments = (teacher) => {
    if (!teacher?.active_assignments || !Array.isArray(teacher.active_assignments)) {
        return [];
    }

    return teacher.active_assignments.filter((a) => a.is_primary !== true && a.role !== 'primary');
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
        const response = await api({
            method: method,
            url: endpoint,
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
        await Promise.all([loadSections(), loadSubjects(), loadGrades()]);

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
            const validAssignments = teacherData.assignments.filter((a) => a && a.section_id && a.subject_id);

            console.log('Valid assignments received:', validAssignments);

            // Process each assignment
            validAssignments.forEach((assignment) => {
                // Find section and subject objects
                const sectionObj = sections.value.find((s) => Number(s.id) === Number(assignment.section_id)) || {
                    id: Number(assignment.section_id),
                    name: `Section ${assignment.section_id}`,
                    grade_id: null,
                    grade: null
                };

                const subjectObj = subjects.value.find((s) => Number(s.id) === Number(assignment.subject_id)) || {
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
        const active_assignments = [...(primaryAssignment ? [primaryAssignment] : []), ...subjectAssignments];

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
        const index = teachers.value.findIndex((t) => t.id === teacherId);
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
            .filter((a) => a.id !== assignment.id)
            .map((a) => ({
                id: a.id,
                section_id: a.section_id,
                subject_id: a.subject_id,
                is_primary: a.is_primary,
                role: a.role
            }));

        console.log('Updating assignments after deletion:', updatedAssignments);

        // Use API to update assignments
        const response = await axios.put(`${API_BASE_URL}/teachers/${selectedTeacher.value.id}/assignments`, { assignments: updatedAssignments });

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

        assignments.forEach((a) => {
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
            life: 5000
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
            const homeroomSubject = availableSubjectsForAssignment.value.find((s) => s.name.toLowerCase() === 'homeroom');

            if (homeroomSubject) {
                selectedSubjectsForAssignment.value = [homeroomSubject];
            } else {
                // Add a temporary homeroom subject (will need to be created in backend)
                selectedSubjectsForAssignment.value = [
                    {
                        id: 'homeroom',
                        name: 'Homeroom',
                        description: 'Main class for primary teacher',
                        is_required: true
                    }
                ];

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
            life: 5000
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
        const assignments = selectedSubjectsForAssignment.value.map((subject) => ({
            section_id: selectedSectionForAssignment.value.id,
            subject_id: subject.id,
            is_primary: selectedRole.value === 'primary',
            role: selectedRole.value
        }));

        // Send assignment data to backend
        const response = await axios.put(`${API_BASE_URL}/teachers/${assignmentWizardTeacher.value.id}/assignments`, { assignments });

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
        const primaryAssignment = teacherData.primary_assignment || (teacherData.active_assignments && teacherData.active_assignments.find((a) => a.is_primary || a.role === 'primary'));

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

const confirmArchiveTeacher = (teacher) => {
    confirm.require({
        message: `Are you sure you want to archive ${teacher.first_name} ${teacher.last_name}?`,
        header: 'Confirm Archive',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: () => archiveTeacher(teacher),
        reject: () => {
            // Do nothing if rejected
        }
    });
};

const archiveTeacher = async (teacher) => {
    try {
        loading.value = true;
        const response = await axios.put(`${API_BASE_URL}/teachers/${teacher.id}/archive`);
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Teacher archived successfully',
            life: 3000
        });
        await loadTeachers();
    } catch (error) {
        console.error('Error archiving teacher:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: `Failed to archive teacher: ${error.message}`,
            life: 5000
        });
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
/* Main Card Container - matching Admin-Student exactly */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    animation: fadeIn 0.6s ease-out;
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

/* Modern Gradient Header - exact match with Admin-Student */
.modern-header-container {
    margin: -2rem -2rem 2rem -2rem;
}

.gradient-header {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
    border-radius: 12px 12px 0 0;
    padding: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
}

.gradient-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    opacity: 0.3;
}

.header-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.header-icon {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    width: 4rem;
    height: 4rem;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.header-icon i {
    font-size: 1.75rem;
    color: white;
}

.header-text {
    flex: 1;
}

.header-title {
    font-size: 2rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    letter-spacing: -0.025em;
    color: white !important;
}

.header-subtitle {
    font-size: 1.1rem;
    margin: 0 0 0.75rem 0;
    opacity: 0.9;
    font-weight: 400;
    color: white !important;
}

.teacher-count {
    display: flex;
    align-items: center;
    font-size: 1rem;
    font-weight: 500;
    background: rgba(255, 255, 255, 0.15);
    padding: 0.5rem 1rem;
    border-radius: 25px;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    width: fit-content;
}

.teacher-count i {
    margin-right: 0.5rem;
    font-size: 1rem;
}

.count-badge {
    background: rgba(255, 255, 255, 0.9);
    color: #1e40af;
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-weight: 700;
    margin-left: 0.5rem;
    font-size: 0.9rem;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.search-container {
    position: relative;
}

.search-input {
    background: rgba(255, 255, 255, 0.95) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    border-radius: 25px !important;
    padding: 0.75rem 1rem 0.75rem 2.75rem !important;
    color: #1e40af !important;
    font-weight: 500 !important;
    width: 300px !important;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease !important;
    height: 44px !important;
}

.search-input:focus {
    background: white !important;
    border-color: rgba(255, 255, 255, 0.8) !important;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2) !important;
    outline: none !important;
}

.search-input::placeholder {
    color: #64748b !important;
}

.search-container .pi-search {
    color: #64748b !important;
    left: 1rem !important;
    z-index: 2;
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
}

.p-input-icon-left i {
    color: #64748b !important;
    left: 1rem !important;
    z-index: 2;
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
}

/* Teachers Container */
.teachers-container {
    padding: 0;
}

.loading-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
}

.loading-container p {
    margin-top: 1rem;
    color: #6b7280;
    font-size: 1.1rem;
}

.no-data-message {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4rem 2rem;
    text-align: center;
    color: #6b7280;
}

.no-data-message p {
    margin: 0.5rem 0;
    font-size: 1.1rem;
}

.no-data-subtitle {
    font-size: 0.9rem;
    opacity: 0.7;
}

/* Teacher Cards */
.teacher-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
    padding: 0;
}

.teacher-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
    overflow: hidden;
}

.teacher-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: #d1d5db;
}

.teacher-card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.teacher-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.teacher-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: white;
    margin: 0;
}

.teacher-card-body {
    padding: 1.5rem;
}

.homeroom-section,
.teaching-subjects-section {
    margin-bottom: 1.5rem;
}

.homeroom-section:last-child,
.teaching-subjects-section:last-child {
    margin-bottom: 0;
}

.homeroom-section h4,
.teaching-subjects-section h4 {
    font-size: 1rem;
    font-weight: 600;
    color: #374151;
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.assignment-info {
    background: #f8fafc;
    border-radius: 8px;
    padding: 1rem;
    border-left: 4px solid #3b82f6;
}

.section-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.section-name {
    font-weight: 600;
    color: #1f2937;
    font-size: 1rem;
}

.grade-level {
    color: #6b7280;
    font-size: 0.875rem;
}

.not-assigned {
    color: #9ca3af;
    font-style: italic;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem;
    background: #f9fafb;
    border-radius: 6px;
    border: 1px dashed #d1d5db;
}

.subjects-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.subject-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
}

.subject-name {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    color: #374151;
}

.subject-icon {
    color: #3b82f6;
    font-size: 0.875rem;
}

.teacher-card-actions {
    padding: 1rem 1.5rem;
    background: #f9fafb;
    border-top: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.action-buttons-row {
    display: flex;
    gap: 0.5rem;
    justify-content: space-between;
}

.action-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.details-btn {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
}

.details-btn:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    transform: translateY(-1px);
}

.edit-btn {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.edit-btn:hover {
    background: linear-gradient(135deg, #e5a50a 0%, #c2710c 100%);
    transform: translateY(-1px);
}

.archive-btn {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.archive-btn:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-1px);
}

.assign-section-btn {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.assign-section-btn:hover {
    background: linear-gradient(135deg, #0d9488 0%, #047857 100%);
    transform: translateY(-1px);
}

.add-subject-btn {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    color: white;
}

.add-subject-btn:hover {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    transform: translateY(-1px);
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }

    .header-left {
        flex-direction: column;
        gap: 1rem;
    }

    .search-input {
        width: 250px !important;
    }

    .teacher-cards {
        grid-template-columns: 1fr;
        gap: 1rem;
    }

    .action-buttons-row {
        flex-direction: column;
        gap: 0.5rem;
    }
}

/* Teacher Details Dialog Styles */
.teacher-details-dialog .p-dialog-header {
    padding: 0 !important;
    border-bottom: none !important;
    background: transparent !important;
}

.teacher-details-dialog .p-dialog-content {
    padding: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
}

.teacher-details-dialog .p-dialog-mask .p-dialog {
    padding: 0 !important;
}

.teacher-details-dialog .p-dialog-content > * {
    padding: 0 !important;
    margin: 0 !important;
}

.teacher-details-dialog .p-dialog-content::-webkit-scrollbar {
    width: 6px;
}

.teacher-details-dialog .p-dialog-content::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.teacher-details-dialog .p-dialog-content::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.teacher-details-dialog .p-dialog-content::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

.teacher-details-dialog .p-dialog-content {
    scrollbar-width: thin;
    scrollbar-color: #c1c1c1 #f1f1f1;
}

.teacher-details-dialog .p-dialog {
    padding: 0 !important;
    margin: 0 !important;
}

.teacher-details-dialog .p-dialog .p-dialog-content {
    border-radius: 6px !important;
    padding: 0 !important;
    margin: 0 !important;
}

.teacher-details-dialog {
    padding: 1px !important;
}

.teacher-details-dialog .p-component {
    padding: 0 !important;
    margin: 0 !important;
}

.dialog-header {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 35px !important;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    border-radius: 6px 6px 0 0 !important;
    position: relative !important;
    margin: -1.5rem -1.5rem 1.5rem -1.5rem !important;
    width: calc(100% + 3rem) !important;
    box-sizing: border-box !important;
}

.header-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.teacher-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid rgba(255, 255, 255, 0.3);
}

.avatar-text {
    font-size: 1.5rem;
    font-weight: 700;
    color: white;
}

.teacher-basic-info {
    flex: 1;
}

.teacher-name {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: white;
}

.teacher-email {
    margin: 0.25rem 0 0 0;
    opacity: 0.9;
    font-size: 0.875rem;
}

.header-actions {
    display: flex;
    gap: 0.5rem;
}

.refresh-btn,
.close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 0.5rem;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.refresh-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: rotate(180deg);
}

.close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

.teacher-details-content {
    padding: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 0 !important;
    max-height: calc(80vh - 60px) !important;
    overflow-y: auto !important;
    position: relative !important;
}

.teacher-details-content::-webkit-scrollbar {
    width: 6px !important;
}

.teacher-details-content::-webkit-scrollbar-track {
    background: #f8f9fa !important;
    border-radius: 3px !important;
}

.teacher-details-content::-webkit-scrollbar-thumb {
    background: #dee2e6 !important;
    border-radius: 3px !important;
}

.teacher-details-content::-webkit-scrollbar-thumb:hover {
    background: #adb5bd !important;
}

.teacher-details-content {
    scrollbar-width: thin !important;
    scrollbar-color: #dee2e6 #f8f9fa !important;
}

.teacher-details-content .info-card:first-of-type {
    margin-top: 1.5rem !important;
}

.teacher-details-content .info-card {
    margin: 0 1.5rem 1.5rem 1.5rem !important;
}

.info-card {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 1.5rem;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0 0 1rem 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #374151;
}

.section-icon {
    color: #667eea;
    font-size: 1.25rem;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-item.full-width {
    grid-column: 1 / -1;
}

.info-label {
    font-size: 0.875rem;
    font-weight: 500;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.info-value {
    font-size: 1rem;
    font-weight: 500;
    color: #374151;
}

.homeroom-card {
    background: white;
    border: 2px solid #10b981;
    border-radius: 8px;
    padding: 1rem;
}

.section-badge {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.section-name {
    font-size: 1.25rem;
    font-weight: 600;
    color: #065f46;
}

.grade-badge {
    background: #10b981;
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
}

.subjects-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
}

.subject-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    transition: all 0.2s ease;
}

.subject-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

.subject-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.subject-icon {
    color: #667eea;
    font-size: 1rem;
}

.subject-name {
    font-weight: 600;
    color: #374151;
    font-size: 1rem;
}

.subject-section {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section-label {
    font-size: 0.875rem;
    color: #6b7280;
    font-weight: 500;
}

.section-value {
    font-size: 0.875rem;
    color: #374151;
    font-weight: 600;
    background: #f3f4f6;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
}

.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 2rem;
    text-align: center;
}

.empty-icon {
    font-size: 2rem;
    color: #d1d5db;
}

.empty-text {
    color: #6b7280;
    font-size: 0.875rem;
    font-weight: 500;
}

@media (max-width: 480px) {
    .card {
        padding: 1rem;
    }

    .gradient-header {
        padding: 1.5rem;
    }

    .modern-header-container {
        margin: -1rem -1rem 1.5rem -1rem;
    }

    .header-title {
        font-size: 1.5rem;
    }

    .search-input {
        width: 200px !important;
    }
}
</style>
