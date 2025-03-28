<script setup>
import CustomDialog from '@/components/CustomDialog.vue';
import { GradeService } from '@/router/service/Grades';
import { SubjectService } from '@/router/service/Subjects';
import Button from 'primevue/button';
import Calendar from 'primevue/calendar';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dropdown from 'primevue/dropdown';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const toast = useToast();
const curriculums = ref([]);
const grades = ref([]);
const loading = ref(true);
const curriculumDialog = ref(false);
const deleteCurriculumDialog = ref(false);
const selectedCurriculum = ref(null);
const archiveDialog = ref(false);
const archiveConfirmDialog = ref(false);
const selectedCurriculumToArchive = ref(null);
const curriculum = ref({
    id: null,
    name: '',
    yearRange: { start: '', end: '' },
    description: '',
    status: 'Active'
});
const submitted = ref(false);
const years = ref(['2023', '2024', '2025', '2026', '2027', '2028']);
const availableEndYears = computed(() => {
    if (!curriculum.value.yearRange.start) return years.value;
    const startIdx = years.value.indexOf(curriculum.value.yearRange.start);
    return years.value.slice(startIdx + 1);
});

const handleStartYearChange = () => {
    // Reset end year if it's less than or equal to start year
    if (curriculum.value.yearRange.end && parseInt(curriculum.value.yearRange.end) <= parseInt(curriculum.value.yearRange.start)) {
        curriculum.value.yearRange.end = '';
    }
};

const searchYear = ref('');
const availableYears = computed(() => {
    const years = new Set();
    curriculums.value.forEach((curr) => {
        years.add(curr.yearRange.start);
        years.add(curr.yearRange.end);
    });
    return Array.from(years).sort();
});

// Grade Level Management variables
const showGradeLevelManagement = ref(false);
const selectedGrade = ref(null);
const newGradeLevel = ref('');
const selectedYearRange = ref('2024-2025');
const sections = ref([]);
const newSection = ref({ name: '', gradeLevel: '', group: '', year: null });
const showSectionDialog = ref(false);
const groups = ref(['Group 1', 'Group 2', 'Group 3', 'Group 4']);
const showSubjectDetailsDialog = ref(false);
const selectedSubjectDetails = ref(null);
const showAddSectionDialog = ref(false);
const newSectionData = ref({
    id: '',
    subjectName: '',
    section: selectedSubjectDetails.value
});
const sectionDetails = ref([]);
const subjects = ref([]);
const gradeSubjects = ref({}); // Store subjects for each grade level
const teachers = ref(['Mr. Smith', 'Mrs. Johnson', 'Dr. Williams', 'Ms. Brown']);
const isEditMode = ref(false);
const editingSectionDetail = ref(null);

const getRandomGradient = () => {
    const gradients = ['linear-gradient(45deg, #FF8008 0%, #FFC837 100%)', 'linear-gradient(45deg, #00C6FF 0%, #0072FF 100%)', 'linear-gradient(45deg, #834d9b 0%, #d04ed6 100%)'];
    return gradients[Math.floor(Math.random() * gradients.length)];
};

// Mock data for curriculums
const mockCurriculums = [
    { id: 1, name: 'Curriculum', yearRange: { start: '2023', end: '2024' }, description: 'Standard curriculum for 2023-2024', status: 'Active' },
    { id: 2, name: 'Curriculum', yearRange: { start: '2024', end: '2025' }, description: 'Standard curriculum for 2024-2025', status: 'Active' },
    { id: 3, name: 'Curriculum', yearRange: { start: '2025', end: '2026' }, description: 'Standard curriculum for 2025-2026', status: 'Active' },
    { id: 4, name: 'Curriculum', yearRange: { start: '2026', end: '2027' }, description: 'Standard curriculum for 2026-2027', status: 'Planned' },
    { id: 5, name: 'Special Curriculum', yearRange: { start: '2023', end: '2024' }, description: 'Special education curriculum', status: 'Active' }
];

const filteredCurriculums = computed(() => {
    let filtered = curriculums.value;

    // Filter by year if searchYear is set
    if (searchYear.value) {
        filtered = filtered.filter((c) => c.yearRange.start === searchYear.value || c.yearRange.end === searchYear.value || `${c.yearRange.start}-${c.yearRange.end}` === searchYear.value);
    }

    // Only show active curriculums in the main view
    filtered = filtered.filter((c) => c.status === 'Active');

    return filtered;
});

const cardStyles = computed(() =>
    filteredCurriculums.value.map(() => ({
        background: getRandomGradient()
    }))
);

const clearSearch = () => {
    searchYear.value = '';
};

const openArchiveDialog = () => {
    archiveDialog.value = true;
};

const openArchiveConfirmation = (curr) => {
    selectedCurriculumToArchive.value = curr;
    archiveConfirmDialog.value = true;
};

const handleArchiveConfirm = () => {
    if (selectedCurriculumToArchive.value) {
        const curr = selectedCurriculumToArchive.value;
        const index = curriculums.value.findIndex((c) => c.id === curr.id);
        if (index !== -1) {
            curriculums.value[index].status = 'Archived';
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Curriculum archived successfully',
                life: 3000
            });
        }
    }
    archiveConfirmDialog.value = false;
    selectedCurriculumToArchive.value = null;
};

const archiveCurriculum = (curr) => {
    const index = curriculums.value.findIndex((c) => c.id === curr.id);
    if (index !== -1) {
        curriculums.value[index].status = 'Archived';

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Curriculum archived successfully',
            life: 3000
        });
    }
    archiveDialog.value = false;
};

const restoreCurriculum = (curr) => {
    const index = curriculums.value.findIndex((c) => c.id === curr.id);
    if (index !== -1) {
        curriculums.value[index].status = 'Active';

        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Curriculum restored successfully',
            life: 3000
        });
    }
};

onMounted(async () => {
    await loadCurriculums();
    await fetchGrades();
    await fetchSubjects();
});

const loadCurriculums = async () => {
    try {
        loading.value = true;
        // In a real app, you would fetch from an API
        // For now, we'll use mock data
        curriculums.value = mockCurriculums;
        loading.value = false;
    } catch (error) {
        console.error('Error loading curriculum data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load curriculum data',
            life: 3000
        });
        loading.value = false;
    }
};

const openNew = () => {
    curriculum.value = {
        id: null,
        name: 'Curriculum',
        yearRange: { start: '', end: '' },
        description: '',
        status: 'Active'
    };
    curriculumDialog.value = true;
};

const saveCurriculum = async () => {
    submitted.value = true;

    if (!curriculum.value.name.trim() || !curriculum.value.yearRange.start || !curriculum.value.yearRange.end) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Please enter required fields',
            life: 3000
        });
        return;
    }

    try {
        if (curriculum.value.id) {
            // Update existing curriculum
            const index = curriculums.value.findIndex((c) => c.id === curriculum.value.id);
            if (index !== -1) {
                curriculums.value[index] = { ...curriculum.value };
            }
        } else {
            // Create new curriculum
            const newId = Math.max(0, ...curriculums.value.map((c) => c.id)) + 1;
            curriculums.value.push({
                ...curriculum.value,
                id: newId
            });
        }

        curriculumDialog.value = false;
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
            detail: 'Failed to save curriculum',
            life: 3000
        });
    }
};

// Grade Level Management Functions
async function fetchGrades() {
    try {
        loading.value = true;
        const data = await GradeService.getGrades();
        grades.value = data.map((grade) => ({
            id: grade.id,
            name: grade.name,
            category: `Academic Year ${selectedYearRange.value}`,
            sections: grade.sections
        }));
    } catch (error) {
        console.error('Error fetching grades:', error);
    } finally {
        loading.value = false;
    }
}

async function fetchSubjects() {
    try {
        const data = await SubjectService.getSubjects();
        subjects.value = data;
    } catch (error) {
        console.error('Error loading subjects:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load subjects',
            life: 3000
        });
    }
}

function openCreateForm() {
    showCreateDialog.value = true;
}

async function saveGradeLevel() {
    if (newGradeLevel.value.trim() !== '' && selectedYearRange.value) {
        try {
            loading.value = true;
            const newGrade = {
                id: newGradeLevel.value,
                name: `Grade ${newGradeLevel.value}`,
                sections: []
            };

            grades.value.push({
                id: newGrade.id,
                name: newGrade.name,
                category: `Academic Year ${selectedYearRange.value}`,
                sections: []
            });

            newGradeLevel.value = '';
            selectedYearRange.value = '2024-2025';
            showCreateDialog.value = false;
        } catch (error) {
            console.error('Error creating grade:', error);
        } finally {
            loading.value = false;
        }
    }
}

// This is the function that will handle curriculum card click
async function openGradeLevelManagement(curr) {
    try {
        loading.value = true;
        selectedCurriculum.value = curr;

        // Directly show grade level management
        showGradeLevelManagement.value = true;

        // Simulate fetching section data for this curriculum
        // In a real app, you would get this from your service
        const mockSections = [
            { name: 'Kinder', gradeLevel: 'Kinder', group: '', year: new Date(), id: 's1' },
            { name: 'Grade 1', gradeLevel: 'Grade 1', group: '', year: new Date(), id: 's2' },
            { name: 'Grade 2', gradeLevel: 'Grade 2', group: '', year: new Date(), id: 's3' },
            { name: 'Grade 3', gradeLevel: 'Grade 3', group: '', year: new Date(), id: 's4' },
            { name: 'Grade 4', gradeLevel: 'Grade 4', group: '', year: new Date(), id: 's5' },
            { name: 'Grade 5', gradeLevel: 'Grade 5', group: '', year: new Date(), id: 's6' },
            { name: 'Grade 6', gradeLevel: 'Grade 6', group: '', year: new Date(), id: 's7' }
        ];

        sections.value = mockSections;
        selectedGrade.value = {
            name: `Curriculum - ${curr.yearRange.start}-${curr.yearRange.end}`,
            id: curr.id
        };
    } catch (error) {
        console.error('Error fetching sections:', error);
    } finally {
        loading.value = false;
    }
}

async function addSection() {
    if (newSection.value.name.trim() !== '') {
        try {
            loading.value = true;

            // Create new section with form data
            sections.value.push({
                ...newSection.value,
                gradeLevel: selectedGrade.value.name,
                id: 'SEC-' + Math.random().toString(36).substr(2, 9).toUpperCase()
            });

            resetSectionForm();
        } catch (error) {
            console.error('Error creating section:', error);
        } finally {
            loading.value = false;
        }
    }
}

async function deleteSection(section) {
    try {
        loading.value = true;

        const index = sections.value.findIndex((s) => s.name === section.name && s.gradeLevel === section.gradeLevel);
        if (index !== -1) {
            sections.value.splice(index, 1);
        }
    } catch (error) {
        console.error('Error deleting section:', error);
    } finally {
        loading.value = false;
    }
}

function resetSectionForm() {
    newSection.value = { name: '', gradeLevel: '', group: '', year: null };
    showSectionDialog.value = false;
}

async function openSubjectDetails(section) {
    try {
        loading.value = true;
        selectedGrade.value = section;
        selectedSubjectDetails.value = section;
        
        // Initialize subjects array for this grade if not exists
        if (!gradeSubjects.value[section.name]) {
            gradeSubjects.value[section.name] = [];
        }
        
        // Load subjects for this grade
        sectionDetails.value = gradeSubjects.value[section.name];
        showSubjectDetailsDialog.value = true;
    } catch (error) {
        console.error('Error fetching subject details:', error);
    } finally {
        loading.value = false;
    }
}

function openAddSectionDialog() {
    isEditMode.value = false;
    editingSectionDetail.value = null;
    newSectionData.value = {
        id: generateId(),
        subjectName: '',
        section: selectedSubjectDetails.value
    };
    showAddSectionDialog.value = true;
}

function generateId() {
    return 'SEC-' + Math.random().toString(36).substr(2, 9).toUpperCase();
}

function addNewSection() {
    if (validateSectionData()) {
        const gradeName = selectedGrade.value.name;
        
        // Initialize grade subjects if not exists
        if (!gradeSubjects.value[gradeName]) {
            gradeSubjects.value[gradeName] = [];
        }
        
        const newSection = {
            id: generateId(),
            subjectName: newSectionData.value.subjectName,
            gradeName: gradeName
        };
        
        if (isEditMode.value) {
            // Update existing subject
            const index = sectionDetails.value.findIndex(d => d.id === editingSectionDetail.value.id);
            if (index !== -1) {
                sectionDetails.value[index] = newSection;
                gradeSubjects.value[gradeName][index] = newSection;
            }
        } else {
            // Add new subject
            sectionDetails.value.push(newSection);
            gradeSubjects.value[gradeName].push(newSection);
        }
        
        closeAddSectionDialog();
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Subject ${isEditMode.value ? 'updated' : 'added'} successfully`,
            life: 3000
        });
    }
}

function validateSectionData() {
    return newSectionData.value.subjectName;
}

function deleteSectionDetail(detail) {
    const gradeName = selectedGrade.value.name;
    const index = sectionDetails.value.findIndex(d => d.id === detail.id);
    if (index !== -1) {
        // Remove from both arrays
        sectionDetails.value.splice(index, 1);
        gradeSubjects.value[gradeName].splice(index, 1);
        
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Subject removed successfully',
            life: 3000
        });
    }
}

function editSectionDetail(detail) {
    isEditMode.value = true;
    editingSectionDetail.value = { ...detail };
    newSectionData.value = {
        ...detail,
        section: selectedSubjectDetails.value
    };
    showAddSectionDialog.value = true;
}

function closeAddSectionDialog() {
    showAddSectionDialog.value = false;
    isEditMode.value = false;
    editingSectionDetail.value = null;
    newSectionData.value = {
        id: generateId(),
        subjectName: '',
        section: selectedSubjectDetails.value
    };
}
</script>

<template>
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold">Curriculum Management</h2>
            <div class="flex gap-3">
                <div class="p-inputgroup">
                    <Dropdown v-model="searchYear" :options="availableYears" placeholder="Filter by Year" class="w-48" />
                    <Button icon="pi pi-times" @click="clearSearch" v-if="searchYear" class="p-button-secondary" />
                </div>
                <Button label="New Curriculum" icon="pi pi-plus" @click="openNew" />
                <Button label="Archive" icon="pi pi-archive" @click="openArchiveDialog" />
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <div v-for="(curr, index) in filteredCurriculums" :key="curr.id" class="curriculum-card group cursor-pointer" @click="openGradeLevelManagement(curr)">
                <div class="bg-white rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow duration-300">
                    <!-- Gradient Header with Icon -->
                    <div class="h-32 relative" :style="{ background: cardStyles[index].background }">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="icon-burst relative">
                                <i class="pi pi-book text-white text-4xl z-10 relative"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Card Content -->
                    <div class="p-6 text-center">
                        <h3 class="text-xl font-bold mb-2 text-gray-800">{{ curr.name }}</h3>
                        <p class="text-gray-600 mb-4">{{ curr.yearRange.start }} - {{ curr.yearRange.end }}</p>
                        <p class="text-gray-500 text-sm mb-4">{{ curr.description }}</p>

                        <!-- Action Buttons -->
                        <div class="flex justify-center gap-2">
                            <Button
                                :label="curr.status === 'Active' ? 'Archive' : 'Restore'"
                                :class="curr.status === 'Active' ? 'p-button-warning' : 'p-button-success'"
                                @click.stop="curr.status === 'Active' ? openArchiveConfirmation(curr) : restoreCurriculum(curr)"
                                size="small"
                            />
                            <Button label="Skip" class="p-button-secondary" size="small" @click.stop />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Curriculum Dialog -->
    <CustomDialog v-model:visible="curriculumDialog" :header="curriculum.id ? 'Edit Curriculum' : 'New Curriculum'" :modal="true" class="p-fluid">
        <div class="p-4">
            <div class="formgrid grid">
                <div class="field col-12">
                    <label for="name">Name</label>
                    <InputText id="name" v-model="curriculum.name" required autofocus :class="{ 'p-invalid': submitted && !curriculum.name }" />
                    <small class="p-error" v-if="submitted && !curriculum.name">Name is required.</small>
                </div>

                <div class="field col-6">
                    <label for="startYear">Start Year</label>
                    <Dropdown id="startYear" v-model="curriculum.yearRange.start" :options="years" placeholder="Select Start Year" @change="handleStartYearChange" :class="{ 'p-invalid': submitted && !curriculum.yearRange.start }" />
                    <small class="p-error" v-if="submitted && !curriculum.yearRange.start">Start Year is required.</small>
                </div>

                <div class="field col-6">
                    <label for="endYear">End Year</label>
                    <Dropdown id="endYear" v-model="curriculum.yearRange.end" :options="availableEndYears" placeholder="Select End Year" :disabled="!curriculum.yearRange.start" :class="{ 'p-invalid': submitted && !curriculum.yearRange.end }" />
                    <small class="p-error" v-if="submitted && !curriculum.yearRange.end">End Year is required.</small>
                </div>

                <div class="field col-12">
                    <label for="description">Description</label>
                    <InputText id="description" v-model="curriculum.description" required :class="{ 'p-invalid': submitted && !curriculum.description }" />
                    <small class="p-error" v-if="submitted && !curriculum.description">Description is required.</small>
                </div>
            </div>
        </div>
        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="curriculumDialog = false" />
                <Button label="Save" icon="pi pi-check" class="p-button-primary" @click="saveCurriculum" />
            </div>
        </template>
    </CustomDialog>

    <!-- Grade Level Management Section -->
    <CustomDialog v-model:visible="showGradeLevelManagement" :header="`Grade Level Management - ${selectedGrade?.name}`" modal class="w-11/12 max-w-6xl" :maximizable="true" :baseZIndex="1000">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center mb-4">
                <div class="flex gap-2">
                    <Button icon="pi pi-arrow-left" class="p-button-secondary" @click="showGradeLevelManagement = false" tooltip="Back to Curriculum" />
                    <Button label="Add Grade Level" icon="pi pi-user-plus" class="p-button-success" @click="showSectionDialog = true" />
                </div>
            </div>
            <DataTable :value="sections" class="p-datatable-striped">
                <Column field="name" header="Grade Level" sortable />
                <Column header="Action">
                    <template #body="slotProps">
                        <div class="flex space-x-2">
                            <Button icon="pi pi-folder-plus" class="p-button-text" @click="openSubjectDetails(slotProps.data)" tooltip="View Subject Details" aria-label="View Subject Details" />
                            <Button icon="pi pi-eye" class="p-button-text" />
                            <Button icon="pi pi-trash" class="p-button-text" @click="deleteSection(slotProps.data)" tooltip="Delete Grade Level" aria-label="Delete Grade Level" />
                        </div>
                    </template>
                </Column>
            </DataTable>
            <div class="flex justify-end mt-4">
                <Button label="Close" icon="pi pi-times" class="p-button-secondary" @click="showGradeLevelManagement = false" />
            </div>
        </div>
    </CustomDialog>

    <!-- Add Section Dialog -->
    <CustomDialog v-model:visible="showSectionDialog" header="Add Grade Level" modal class="max-w-md w-full rounded-lg">
        <div class="p-4 space-y-4">
            <label class="block text-gray-700 font-medium">Grade Level</label>
            <InputText v-model="newSection.name" placeholder="Enter Grade Level" class="w-full" />
            <label class="block text-gray-700 font-medium">Year</label>
            <Calendar v-model="newSection.year" :showIcon="true" placeholder="Select Year" />
            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" class="p-button-secondary" @click="resetSectionForm" />
                <Button label="Save" class="p-button-success" @click="addSection()" />
            </div>
        </div>
    </CustomDialog>

    <!-- Subject Details Dialog -->
    <CustomDialog v-model:visible="showSubjectDetailsDialog" :header="`Subject Details - ${selectedGrade?.name || ''}`" modal class="max-w-4xl w-full">
        <div class="p-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Schedule Details</h3>
                <Button label="Add Schedule" icon="pi pi-plus" class="p-button-success" @click="openAddSectionDialog" />
            </div>

            <DataTable :value="sectionDetails" stripedRows>
                <Column field="id" header="ID">
                    <template #body="{ data }">
                        <span class="font-medium">{{ data.id }}</span>
                    </template>
                </Column>
                <Column header="Subject">
                    <template #body="{ data }">
                        <div v-if="typeof data.subjectName === 'object'">
                            <div class="font-medium">{{ data.subjectName.name }}</div>
                            <div class="text-sm text-gray-500">{{ data.subjectName.description }}</div>
                        </div>
                        <div v-else>{{ data.subjectName }}</div>
                    </template>
                </Column>
                <Column header="Actions" :exportable="false" style="min-width: 8rem">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button icon="pi pi-pencil" class="p-button-rounded p-button-success mr-2" @click="editSectionDetail(data)" />
                            <Button icon="pi pi-trash" class="p-button-rounded p-button-danger" @click="deleteSectionDetail(data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </CustomDialog>

    <!-- Add/Edit Section Dialog -->
    <CustomDialog v-model:visible="showAddSectionDialog" :header="isEditMode ? 'Edit Schedule' : 'Add Schedule'" modal class="p-fluid">
        <div class="p-4">
            <div class="field">
                <label for="subject">Subject</label>
                <Dropdown id="subject" v-model="newSectionData.subjectName" :options="subjects" optionLabel="name" placeholder="Select a Subject" class="w-full" :class="{ 'p-invalid': submitted && !newSectionData.subjectName }">
                    <template #option="slotProps">
                        <div>
                            <div>{{ slotProps.option.name }}</div>
                            <div class="text-sm text-gray-500">{{ slotProps.option.description }}</div>
                        </div>
                    </template>
                </Dropdown>
                <small class="p-error" v-if="submitted && !newSectionData.subjectName">Subject is required.</small>
            </div>
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="closeAddSectionDialog" />
            <Button :label="isEditMode ? 'Update' : 'Add'" icon="pi pi-check" @click="addNewSection" />
        </template>
    </CustomDialog>

    <!-- Archive List Dialog -->
    <CustomDialog v-model:visible="archiveDialog" header="Archive Curriculum" :modal="true" class="max-w-4xl w-full rounded-lg">
        <div class="p-4">
            <h3 class="text-xl font-semibold mb-4">Archive Curriculum</h3>

            <DataTable
                :value="curriculums"
                stripedRows
                class="p-datatable-sm"
                v-model:selection="selectedCurriculum"
                selectionMode="single"
                dataKey="id"
                :paginator="true"
                :rows="5"
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
                :rowsPerPageOptions="[5, 10, 25]"
            >
                <Column selectionMode="single" headerStyle="width: 3rem"></Column>
                <Column field="name" header="Name" sortable></Column>
                <Column header="Year Range" sortable>
                    <template #body="slotProps"> {{ slotProps.data.yearRange.start }}-{{ slotProps.data.yearRange.end }} </template>
                </Column>
                <Column field="status" header="Status" sortable>
                    <template #body="slotProps">
                        <Tag :severity="slotProps.data.status === 'Active' ? 'success' : 'warning'" :value="slotProps.data.status" />
                    </template>
                </Column>
                <Column header="Actions">
                    <template #body="slotProps">
                        <div class="flex gap-2">
                            <Button v-if="slotProps.data.status === 'Active'" icon="pi pi-archive" class="p-button-rounded p-button-warning p-button-sm" @click="openArchiveConfirmation(slotProps.data)" tooltip="Archive" />
                            <Button v-else icon="pi pi-refresh" class="p-button-rounded p-button-success p-button-sm" @click="restoreCurriculum(slotProps.data)" tooltip="Restore" />
                        </div>
                    </template>
                </Column>
            </DataTable>

            <div class="flex justify-end gap-2 mt-4">
                <Button label="Close" class="p-button-text" @click="archiveDialog = false" />
            </div>
        </div>
    </CustomDialog>

    <!-- Archive Confirmation Dialog -->
    <CustomDialog v-model:visible="archiveConfirmDialog" modal header="Archive Confirmation" :style="{ width: '450px' }" class="p-4">
        <div class="text-center mb-6">
            <i class="pi pi-exclamation-triangle text-5xl text-yellow-500 mb-4"></i>
            <p class="text-lg">Are you sure you want to archive this curriculum?</p>
            <p class="text-sm text-gray-600 mt-2">This action can be reversed later.</p>
        </div>
        <div class="flex justify-center gap-3">
            <Button label="Yes, Archive" icon="pi pi-check" class="p-button-warning" @click="handleArchiveConfirm" />
            <Button label="No, Cancel" icon="pi pi-times" class="p-button-secondary" @click="archiveConfirmDialog = false" />
        </div>
    </CustomDialog>
</template>

<style scoped>
.curriculum-card {
    transition: transform 0.3s ease;
}

.curriculum-card:hover {
    transform: translateY(-5px);
}

.icon-burst {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.icon-burst::before {
    content: '';
    position: absolute;
    inset: -10px;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath fill='rgba(255,255,255,0.5)' d='M50 0 L52 48 L98 50 L52 52 L50 100 L48 52 L2 50 L48 48z'/%3E%3C/svg%3E") no-repeat center/contain;
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* PrimeVue Button Customization */
:deep(.p-button) {
    border-radius: 20px;
    padding: 0.5rem 1.5rem;
}

:deep(.p-button.p-button-sm) {
    padding: 0.3rem 1rem;
    font-size: 0.875rem;
}

/* Card Shadow */
.shadow-lg {
    box-shadow:
        0 10px 15px -3px rgba(0, 0, 0, 0.1),
        0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.shadow-xl {
    box-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
</style>
