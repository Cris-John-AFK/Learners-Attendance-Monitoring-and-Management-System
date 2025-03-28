<script setup>
import CustomDialog from '@/components/CustomDialog.vue';
import SakaiCard from '@/components/SakaiCard.vue';
import { GradeService } from '@/router/service/Grades';
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
const curriculum = ref({
    id: null,
    name: '',
    yearRange: { start: '', end: '' },
    description: '',
    status: 'Active'
});
const submitted = ref(false);
const yearRanges = ref([
    { start: '2023', end: '2024' },
    { start: '2024', end: '2025' },
    { start: '2025', end: '2026' },
    { start: '2026', end: '2027' },
    { start: '2027', end: '2028' }
]);

// Search functionality
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
const teachers = ref(['Mr. Smith', 'Mrs. Johnson', 'Dr. Williams', 'Ms. Brown']);
const isEditMode = ref(false);
const editingSectionDetail = ref(null);

const getRandomGradient = () => {
    const colors = ['#ff9a9e', '#fad0c4', '#fbc2eb', '#a6c1ee', '#ffdde1', '#ee9ca7', '#ff758c', '#ff7eb3', '#c3cfe2', '#d4fc79', '#96e6a1', '#84fab0', '#8fd3f4', '#a18cd1'];

    const color1 = colors[Math.floor(Math.random() * colors.length)];
    const color2 = colors[Math.floor(Math.random() * colors.length)];

    return `linear-gradient(135deg, ${color1}, ${color2})`;
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
        filtered = filtered.filter((c) => 
            c.yearRange.start === searchYear.value || 
            c.yearRange.end === searchYear.value ||
            `${c.yearRange.start}-${c.yearRange.end}` === searchYear.value
        );
    }
    
    // Only show active curriculums in the main view
    filtered = filtered.filter(c => c.status === 'Active');
    
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

const archiveCurriculum = (curr) => {
    const index = curriculums.value.findIndex(c => c.id === curr.id);
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
    const index = curriculums.value.findIndex(c => c.id === curr.id);
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
        const allSubjects = await GradeService.getSubjectsByGrade('all');
        subjects.value = [...new Set(allSubjects.map((subject) => subject.name))];
    } catch (error) {
        console.error('Error fetching subjects:', error);
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
        selectedSubjectDetails.value = section;

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
        if (isEditMode.value) {
            const index = sectionDetails.value.findIndex((d) => d.id === editingSectionDetail.value.id);
            if (index !== -1) {
                sectionDetails.value[index] = {
                    ...newSectionData.value
                };
            }
        } else {
            sectionDetails.value.push({
                ...newSectionData.value
            });
        }
        closeAddSectionDialog();
    }
}

function validateSectionData() {
    return newSectionData.value.subjectName;
}

function deleteSectionDetail(detail) {
    const index = sectionDetails.value.findIndex((d) => d.id === detail.id);
    if (index !== -1) {
        sectionDetails.value.splice(index, 1);
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
                    <Dropdown v-model="searchYear" :options="availableYears" placeholder="Search by Year" class="w-full" appendTo="body">
                        <template #value="slotProps">
                            <div v-if="slotProps.value" class="flex align-items-center">
                                <span>{{ slotProps.value }}</span>
                            </div>
                            <span v-else>Search by Year</span>
                        </template>
                    </Dropdown>
                    <Button icon="pi pi-times" @click="clearSearch" v-if="searchYear" class="p-button-secondary" />
                </div>
                <Button label="Add New Curriculum" icon="pi pi-plus" class="p-button-success" @click="openNew" />
                <Button label="Archive" icon="pi pi-archive" class="p-button-warning" @click="openArchiveDialog" />
            </div>
        </div>

        <div v-if="loading" class="flex justify-center my-8">
            <i class="pi pi-spin pi-spinner text-4xl text-blue-500"></i>
        </div>

        <div v-else class="card-container">
            <SakaiCard v-for="(curr, index) in filteredCurriculums" :key="curr.id" class="custom-card" :style="cardStyles[index]" @click="openGradeLevelManagement(curr)">
                <div class="card-header">
                    <h1 class="curriculum-name">{{ curr.name }}</h1>
                    <p class="year-info">{{ curr.yearRange.start }}-{{ curr.yearRange.end }}</p>
                </div>
            </SakaiCard>
        </div>
    </div>

    <!-- Add Curriculum Dialog -->
    <CustomDialog v-model:visible="curriculumDialog" :style="{ width: '500px' }" header="Add Curriculum" :modal="true" class="p-fluid curriculum-dialog">
        <div class="field mb-4">
            <label for="name" class="font-medium mb-2 block">Curriculum Name</label>
            <InputText id="name" v-model="curriculum.name" required autofocus :class="{ 'p-invalid': submitted && !curriculum.name }" placeholder="Enter curriculum name" class="w-full p-inputtext-lg" />
            <small class="p-error" v-if="submitted && !curriculum.name">Curriculum name is required.</small>
        </div>

        <div class="field mb-4">
            <label for="yearRange" class="font-medium mb-2 block">Academic Year</label>
            <div class="flex gap-2">
                <Dropdown
                    id="startYear"
                    v-model="curriculum.yearRange.start"
                    :options="['2023', '2024', '2025', '2026', '2027', '2028']"
                    placeholder="Select Start Year"
                    required
                    :class="{ 'p-invalid': submitted && !curriculum.yearRange.start }"
                    class="w-full p-inputtext-lg"
                    appendTo="body"
                />
                <Dropdown
                    id="endYear"
                    v-model="curriculum.yearRange.end"
                    :options="['2024', '2025', '2026', '2027', '2028', '2029']"
                    placeholder="Select End Year"
                    required
                    :class="{ 'p-invalid': submitted && !curriculum.yearRange.end }"
                    class="w-full p-inputtext-lg"
                    appendTo="body"
                />
            </div>
            <small class="p-error" v-if="submitted && (!curriculum.yearRange.start || !curriculum.yearRange.end)">Academic year is required.</small>
        </div>

        <div class="field mb-4">
            <label for="status" class="font-medium mb-2 block">Status</label>
            <Dropdown id="status" v-model="curriculum.status" :options="['Active', 'Planned', 'Archived']" placeholder="Select Status" class="w-full p-inputtext-lg" appendTo="body" />
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
                            <Button icon="pi pi-search" class="p-button-text" @click="openSubjectDetails(slotProps.data)" tooltip="View Subject Details" aria-label="View Subject Details" />
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
    <CustomDialog v-model:visible="showSubjectDetailsDialog" :header="`Subject Details - ${selectedSubjectDetails?.name}`" modal class="w-11/12 max-w-6xl" :maximizable="true">
        <div class="p-4 space-y-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Schedule Details</h3>
                <Button label="Add Schedule" icon="pi pi-plus" class="p-button-success" @click="openAddSectionDialog" />
            </div>

            <DataTable
                :value="sectionDetails.filter((d) => d.section?.name === selectedSubjectDetails?.name)"
                class="p-datatable-striped"
                responsiveLayout="stack"
                :rows="10"
                :rowsPerPageOptions="[5, 10, 20, 50]"
                paginator
                paginatorTemplate="CurrentPageReport FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
                currentPageReportTemplate="Showing {first} to {last} of {totalRecords}"
                :resizableColumns="true"
                columnResizeMode="fit"
                showGridlines
                :scrollable="true"
                scrollHeight="400px"
            >
                <Column field="id" header="ID" sortable style="min-width: 100px" />
                <Column field="subjectName" header="Subject" sortable style="min-width: 150px" />
                <Column header="Actions" style="min-width: 100px">
                    <template #body="slotProps">
                        <div class="flex gap-2">
                            <Button icon="pi pi-pencil" class="p-button-text p-button-warning" @click="editSectionDetail(slotProps.data)" tooltip="Edit Schedule" />
                            <Button icon="pi pi-trash" class="p-button-text p-button-danger" @click="deleteSectionDetail(slotProps.data)" tooltip="Delete Schedule" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </CustomDialog>

    <!-- Add Schedule Dialog -->
    <CustomDialog v-model:visible="showAddSectionDialog" :header="isEditMode ? 'Edit Schedule' : 'Add Schedule'" modal class="max-w-md w-full rounded-lg">
        <div class="p-4 space-y-4">
            <div class="field">
                <label class="block text-gray-700 font-medium">Subject</label>
                <Dropdown v-model="newSectionData.subjectName" :options="subjects" placeholder="Select Subject" class="w-full" appendTo="body" />
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <Button label="Cancel" class="p-button-secondary" @click="closeAddSectionDialog" />
                <Button :label="isEditMode ? 'Update' : 'Save'" class="p-button-success" @click="addNewSection" :disabled="!validateSectionData()" />
            </div>
        </div>
    </CustomDialog>

    <!-- Archive Dialog -->
    <CustomDialog v-model:visible="archiveDialog" header="Archive Curriculum" :modal="true" class="max-w-4xl w-full rounded-lg">
        <div class="p-4">
            <h3 class="text-xl font-semibold mb-4">Archive Curriculum</h3>
            
            <DataTable :value="curriculums" stripedRows class="p-datatable-sm" v-model:selection="selectedCurriculum" 
                selectionMode="single" dataKey="id" :paginator="true" :rows="5" 
                paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink RowsPerPageDropdown"
                :rowsPerPageOptions="[5, 10, 25]">
                <Column selectionMode="single" headerStyle="width: 3rem"></Column>
                <Column field="name" header="Name" sortable></Column>
                <Column header="Year Range" sortable>
                    <template #body="slotProps">
                        {{ slotProps.data.yearRange.start }}-{{ slotProps.data.yearRange.end }}
                    </template>
                </Column>
                <Column field="status" header="Status" sortable>
                    <template #body="slotProps">
                        <Tag :severity="slotProps.data.status === 'Active' ? 'success' : 'warning'" 
                            :value="slotProps.data.status" />
                    </template>
                </Column>
                <Column header="Actions">
                    <template #body="slotProps">
                        <div class="flex gap-2">
                            <Button v-if="slotProps.data.status === 'Active'" 
                                icon="pi pi-archive" 
                                class="p-button-rounded p-button-warning p-button-sm" 
                                @click="archiveCurriculum(slotProps.data)" 
                                tooltip="Archive" />
                            <Button v-else 
                                icon="pi pi-refresh" 
                                class="p-button-rounded p-button-success p-button-sm" 
                                @click="restoreCurriculum(slotProps.data)" 
                                tooltip="Restore" />
                        </div>
                    </template>
                </Column>
            </DataTable>
            
            <div class="flex justify-end gap-2 mt-4">
                <Button label="Close" class="p-button-text" @click="archiveDialog = false" />
            </div>
        </div>
    </CustomDialog>
</template>

<style scoped>
.card-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    transition: all 0.3s ease;
}

.custom-card {
    width: 200px;
    height: 250px;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    cursor: pointer;
    text-align: center;
    background: #f0f0f0;
    transition:
        transform 0.2s,
        box-shadow 0.2s;
    color: white;
    font-weight: bold;
    position: relative;
    animation: card-appear 0.5s ease-out forwards;
    padding: 15px;
    justify-content: center;
}

@keyframes card-appear {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.custom-card:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.curriculum-name {
    margin: 0;
    text-align: center;
    word-break: break-word;
    overflow-wrap: break-word;
    white-space: normal;
    font-size: 24px;
    font-weight: 700;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
    margin-bottom: 10px;
    line-height: 1.2;
}

.year-info {
    margin: 10px 0 0;
    font-size: 18px;
    font-weight: 600;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.card-header {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    height: 100%;
    width: 100%;
}

:deep(.p-datatable-striped .p-datatable-tbody > tr:nth-child(even)) {
    background-color: #f9fafb;
}
:deep(.p-datatable-thead th) {
    background-color: #e5e7eb;
    font-weight: bold;
}
:deep(.p-dialog) {
    border-radius: 12px;
    z-index: 1000;
}
:deep(.p-dialog-mask) {
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(3px);
}
:deep(.p-button-success) {
    background-color: #22c55e;
    border: none;
}
:deep(.p-button-success:hover) {
    background-color: #16a34a;
}

:deep(.p-datatable) {
    font-size: 0.95rem;
}

:deep(.p-datatable .p-datatable-header) {
    background-color: #f8fafc;
    padding: 1rem;
}

:deep(.p-datatable .p-datatable-thead > tr > th) {
    background-color: #f1f5f9;
    color: #334155;
    padding: 0.75rem;
    font-weight: 600;
    text-align: left;
}

:deep(.p-datatable .p-datatable-tbody > tr) {
    background-color: #ffffff;
    transition: background-color 0.2s;
}

:deep(.p-datatable .p-datatable-tbody > tr:hover) {
    background-color: #f1f5f9;
}

:deep(.p-datatable .p-datatable-tbody > tr > td) {
    padding: 0.75rem;
    border: 1px solid #e2e8f0;
}

:deep(.p-paginator) {
    padding: 1rem;
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
}

:deep(.p-dialog.w-11\/12) {
    max-height: 90vh;
    overflow-y: auto;
}

:deep(.p-dialog-content) {
    padding: 0 !important;
}
</style>
