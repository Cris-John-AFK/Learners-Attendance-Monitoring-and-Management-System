<script setup>
import { useToast } from 'primevue/usetoast';
import QRCode from 'qrcode';
import { computed, onMounted, ref } from 'vue';

const toast = useToast();
const students = ref([]);
const grades = ref([]);
const loading = ref(true);
const studentDialog = ref(false);
const deleteStudentDialog = ref(false);
const expandedRows = ref([]);
const qrCodes = ref({});
const student = ref({
    id: null,
    name: '',
    gender: 'Male',
    gradeLevel: '',
    section: '',
    email: '',
    phone: '',
    address: '',
    lrn: '',
    photo: null
});
const submitted = ref(false);
const filters = ref({
    grade: null,
    section: null,
    gender: null,
    searchTerm: ''
});
const sections = ref([]);
const totalStudents = ref(0);
const qrCodeDialog = ref(false);
const selectedStudent = ref(null);

// Grade levels for filtering
const gradeLevels = [
    { name: 'Kindergarten', code: 'Kindergarten' },
    { name: 'Grade 1', code: 'Grade 1' },
    { name: 'Grade 2', code: 'Grade 2' },
    { name: 'Grade 3', code: 'Grade 3' },
    { name: 'Grade 4', code: 'Grade 4' },
    { name: 'Grade 5', code: 'Grade 5' },
    { name: 'Grade 6', code: 'Grade 6' }
];

// Sections for each grade level
const sectionsByGrade = {
    Kindergarten: ['Daisy', 'Rose', 'Sunflower'],
    'Grade 1': ['Faith', 'Hope', 'Love'],
    'Grade 2': ['Honesty', 'Kindness', 'Patience'],
    'Grade 3': ['Wisdom', 'Courage', 'Respect'],
    'Grade 4': ['Integrity', 'Excellence', 'Humility'],
    'Grade 5': ['Diligence', 'Creativity', 'Teamwork'],
    'Grade 6': ['Leadership', 'Perseverance', 'Responsibility']
};

// Load all grade levels and sections
const loadGradesAndSections = () => {
    try {
        grades.value = gradeLevels;

        // Set default sections based on first grade
        if (gradeLevels.length > 0) {
            sections.value = sectionsByGrade[gradeLevels[0].code] || [];
        }
    } catch (error) {
        console.error('Error loading grade data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load grade data',
            life: 3000
        });
    }
};

// Generate QR code from LRN
const generateQRCode = async (lrn) => {
    if (!lrn) return '';

    // Return cached QR code if available
    if (qrCodes.value[lrn]) {
        return qrCodes.value[lrn];
    }

    try {
        const qrDataUrl = await QRCode.toDataURL(lrn, {
            width: 128,
            margin: 1,
            color: {
                dark: '#4a90e2',
                light: '#ffffff'
            }
        });
        // Cache the QR code
        qrCodes.value[lrn] = qrDataUrl;
        return qrDataUrl;
    } catch (error) {
        console.error('Error generating QR code:', error);
        return '';
    }
};

// Generate QR codes for all students
const generateAllQRCodes = async () => {
    for (const student of students.value) {
        if (student.lrn && !qrCodes.value[student.lrn]) {
            await generateQRCode(student.lrn);
        }
    }
};

// Load all students from localStorage
const loadStudents = () => {
    try {
        loading.value = true;

        // Get enrolled students from localStorage
        const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');

        // Format students for display
        const formattedStudents = enrolledStudents.map((student, index) => {
            // Extract data from original data if available
            const originalData = student.originalData || student;

            return {
                id: index + 1,
                studentId: student.studentId || `STU${String(index + 1).padStart(5, '0')}`,
                name: student.name || `${originalData.firstName} ${originalData.lastName}`,
                firstName: originalData.firstName,
                lastName: originalData.lastName,
                email: student.email || originalData.email || 'N/A',
                gender: student.sex || originalData.sex || 'Male',
                age: calculateAge(originalData.birthdate),
                birthdate: originalData.birthdate ? new Date(originalData.birthdate).toLocaleDateString() : 'N/A',
                address: formatAddress(originalData),
                contact: student.contact || originalData.mother?.contactNumber || 'N/A',
                photo: student.photo || `https://randomuser.me/api/portraits/${originalData.sex === 'Female' ? 'women' : 'men'}/${index + 1}.jpg`,
                gradeLevel: student.gradeLevel,
                section: student.section,
                lrn: student.lrn || `${new Date().getFullYear()}${String(index + 1).padStart(8, '0')}`,
                enrollmentDate: student.enrollmentDate || new Date().toLocaleDateString(),
                // Store original data for reference
                originalData: originalData
            };
        });

        students.value = formattedStudents;
        totalStudents.value = formattedStudents.length;

        // Update the filter counts
        updateFilterCounts();
    } catch (error) {
        console.error('Error loading student data:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load student data',
            life: 3000
        });
    } finally {
        loading.value = false;
    }
};

// Update filter counts for UI display
const updateFilterCounts = () => {
    // Count students by grade level
    const gradeCounts = {};
    gradeLevels.forEach((grade) => {
        gradeCounts[grade.code] = students.value.filter((s) => s.gradeLevel === grade.code).length;
    });

    // Count students by gender
    const maleCounts = students.value.filter((s) => s.gender.toLowerCase() === 'male').length;
    const femaleCounts = students.value.filter((s) => s.gender.toLowerCase() === 'female').length;

    console.log('Grade counts:', gradeCounts);
    console.log('Gender counts - Male:', maleCounts, 'Female:', femaleCounts);
};

// Calculate age from birthdate
function calculateAge(birthdate) {
    if (!birthdate) return 'N/A';

    const birthDate = new Date(birthdate);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }

    return age;
}

// Format address for display
function formatAddress(student) {
    if (student.currentAddress) {
        const addr = student.currentAddress;
        const parts = [addr.houseNo, addr.street, addr.barangay, addr.city, addr.province].filter((part) => part);
        return parts.join(', ') || 'N/A';
    }
    return student.address || 'N/A';
}

// Update sections when grade changes
function updateSections() {
    if (filters.value.grade) {
        sections.value = sectionsByGrade[filters.value.grade] || [];
        filters.value.section = null; // Reset section when grade changes
    } else {
        sections.value = [];
    }
}

// Now the computed property will work properly with the import
const filteredStudents = computed(() => {
    return students.value.filter((student) => {
        // Apply grade filter
        if (filters.value.grade && student.gradeLevel !== filters.value.grade) {
            return false;
        }

        // Apply section filter
        if (filters.value.section && student.section !== filters.value.section) {
            return false;
        }

        // Apply gender filter
        if (filters.value.gender && student.gender !== filters.value.gender) {
            return false;
        }

        // Apply search term
        if (filters.value.searchTerm) {
            const term = filters.value.searchTerm.toLowerCase();
            return (
                student.name.toLowerCase().includes(term) || student.studentId.toString().includes(term) || (student.firstName && student.firstName.toLowerCase().includes(term)) || (student.lastName && student.lastName.toLowerCase().includes(term))
            );
        }

        return true;
    });
});

// Save student
const saveStudent = () => {
    submitted.value = true;

    if (!student.value.name.trim()) {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: 'Please enter a name',
            life: 3000
        });
        return;
    }

    try {
        // Get current students from localStorage
        const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');

        if (student.value.id) {
            // Update existing student
            const index = enrolledStudents.findIndex((s) => s.id === student.value.id);
            if (index !== -1) {
                enrolledStudents[index] = {
                    ...enrolledStudents[index],
                    ...student.value
                };
            }
        } else {
            // Create new student
            const newStudent = {
                ...student.value,
                id: enrolledStudents.length + 1,
                studentId: `STU${String(enrolledStudents.length + 1).padStart(5, '0')}`,
                enrollmentDate: new Date().toISOString().split('T')[0],
                status: 'Enrolled',
                lrn: student.value.lrn || `${new Date().getFullYear()}${String(enrolledStudents.length + 1).padStart(8, '0')}`
            };
            enrolledStudents.push(newStudent);
        }

        // Save back to localStorage
        localStorage.setItem('enrolledStudents', JSON.stringify(enrolledStudents));

        // Reload students
        loadStudents();

        studentDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: student.value.id ? 'Student Updated' : 'Student Created',
            life: 3000
        });
    } catch (error) {
        console.error('Error saving student:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to save student',
            life: 3000
        });
    }
};

// Edit student
function editStudent(studentData) {
    student.value = { ...studentData };
    studentDialog.value = true;
}

// Confirm delete student
function confirmDeleteStudent(studentData) {
    student.value = { ...studentData };
    deleteStudentDialog.value = true;
}

// Delete student
const deleteStudent = () => {
    try {
        // Get current students from localStorage
        const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');

        // Filter out the student to delete
        const updatedStudents = enrolledStudents.filter((s) => {
            // Check if studentId exists and matches
            if (s.studentId && student.value.studentId) {
                return s.studentId !== student.value.studentId;
            }
            // Fallback to index-based comparison
            return s.id !== student.value.id;
        });

        // Save back to localStorage
        localStorage.setItem('enrolledStudents', JSON.stringify(updatedStudents));

        // Reload students
        loadStudents();

        deleteStudentDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Student Deleted Successfully',
            life: 3000
        });
    } catch (error) {
        console.error('Error deleting student:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to delete student',
            life: 3000
        });
    }
};

// Show QR code in a dialog
function showQRCode(studentData) {
    selectedStudent.value = studentData;
    qrCodeDialog.value = true;
}

// Print QR code
function printQRCode() {
    if (!selectedStudent.value || !qrCodes.value[selectedStudent.value.lrn]) return;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Student LRN QR Code</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    text-align: center;
                    padding: 20px;
                }
                .container {
                    max-width: 400px;
                    margin: 0 auto;
                    border: 1px solid #ddd;
                    padding: 20px;
                    border-radius: 8px;
                }
                .qr-code {
                    width: 200px;
                    height: 200px;
                    margin: 0 auto 20px;
                }
                h2 {
                    margin-bottom: 5px;
                }
                p {
                    margin: 5px 0;
                    color: #666;
                }
                .school-name {
                    font-size: 18px;
                    font-weight: bold;
                    margin-bottom: 20px;
                }
                @media print {
                    .no-print {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="school-name">Learners Attendance Monitoring System</div>
                <img src="${qrCodes.value[selectedStudent.value.lrn]}" class="qr-code" />
                <h2>${selectedStudent.value.name}</h2>
                <p>LRN: ${selectedStudent.value.lrn}</p>
                <p>${selectedStudent.value.gradeLevel} - ${selectedStudent.value.section}</p>
            </div>
            <div class="no-print" style="margin-top: 20px;">
                <button onclick="window.print()">Print</button>
                <button onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Initialize component
onMounted(() => {
    loadGradesAndSections();
    loadStudents();

    // Generate QR codes after students are loaded
    setTimeout(() => {
        generateAllQRCodes();
    }, 500);
});
</script>

<template>
    <div class="card p-6 shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4">
            <div>
                <h2 class="text-2xl font-semibold mb-1"><i class="pi pi-users mr-2"></i>Student Management</h2>
                <p class="text-color-secondary">
                    Total Students: <span class="font-bold">{{ totalStudents }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <span class="p-input-icon-left w-full md:w-20rem">
                    <i class="pi pi-search" />
                    <InputText v-model="filters.searchTerm" placeholder="Search students..." class="w-full" />
                </span>
                <Button label="Add Student" icon="pi pi-plus" class="p-button-success" @click="studentDialog = true" />
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3 mb-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1">Grade Level</label>
                <Dropdown v-model="filters.grade" :options="grades" optionLabel="name" optionValue="code" placeholder="Select Grade" class="w-full" @change="updateSections" />
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1">Section</label>
                <Dropdown v-model="filters.section" :options="sections" placeholder="Select Section" class="w-full" :disabled="!filters.grade" />
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1">Gender</label>
                <Dropdown
                    v-model="filters.gender"
                    :options="[
                        { name: 'Male', value: 'Male' },
                        { name: 'Female', value: 'Female' }
                    ]"
                    optionLabel="name"
                    optionValue="value"
                    placeholder="Select Gender"
                    class="w-full"
                />
            </div>
        </div>

        <!-- Student List -->
        <div class="grid">
            <div class="col-12">
                <DataTable v-model:expandedRows="expandedRows" :value="filteredStudents" dataKey="id" class="p-datatable-sm" :loading="loading" stripedRows responsiveLayout="scroll" :paginator="filteredStudents.length > 10" :rows="10">
                    <Column expander style="width: 3rem" />
                    <Column header="Student" style="min-width: 200px">
                        <template #body="slotProps">
                            <div class="flex align-items-center">
                                <Avatar :image="slotProps.data.photo" shape="circle" size="large" class="mr-2" />
                                <div>
                                    <div class="font-bold">{{ slotProps.data.name }}</div>
                                    <div class="text-sm text-color-secondary">{{ slotProps.data.studentId }}</div>
                                </div>
                            </div>
                        </template>
                    </Column>
                    <Column field="gradeLevel" header="Grade" sortable style="width: 120px" />
                    <Column field="section" header="Section" sortable style="width: 120px" />
                    <Column field="lrn" header="LRN" sortable style="min-width: 150px">
                        <template #body="slotProps">
                            <div class="flex flex-column">
                                <span class="font-semibold">{{ slotProps.data.lrn }}</span>
                                <Button v-if="qrCodes[slotProps.data.lrn]" icon="pi pi-qrcode" class="p-button-rounded p-button-text p-button-sm mt-1" @click="showQRCode(slotProps.data)" />
                            </div>
                        </template>
                    </Column>
                    <Column field="age" header="Age" sortable style="width: 80px">
                        <template #body="slotProps">
                            <span>{{ slotProps.data.age }}</span>
                        </template>
                    </Column>
                    <Column header="Gender" sortable style="width: 100px">
                        <template #body="slotProps">
                            <Tag :value="slotProps.data.gender" :severity="slotProps.data.gender === 'Male' ? 'info' : 'success'" />
                        </template>
                    </Column>
                    <Column field="email" header="Email" sortable style="min-width: 200px" />
                    <Column field="contact" header="Contact" style="width: 130px" />
                    <Column header="Actions" style="width: 8rem">
                        <template #body="slotProps">
                            <div class="flex gap-1">
                                <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click="editStudent(slotProps.data)" />
                                <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click="confirmDeleteStudent(slotProps.data)" />
                            </div>
                        </template>
                    </Column>
                    <template #expansion="slotProps">
                        <div class="p-4 surface-hover border-round-bottom">
                            <h5 class="mb-3">Student Details</h5>
                            <div class="grid">
                                <div class="col-12 md:col-6 lg:col-4">
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Full Name</div>
                                        <div>{{ slotProps.data.name }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Student ID</div>
                                        <div>{{ slotProps.data.studentId }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Birthdate</div>
                                        <div>{{ slotProps.data.birthdate }}</div>
                                    </div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4">
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Email</div>
                                        <div>{{ slotProps.data.email }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Contact</div>
                                        <div>{{ slotProps.data.contact }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Address</div>
                                        <div>{{ slotProps.data.address }}</div>
                                    </div>
                                </div>
                                <div class="col-12 md:col-6 lg:col-4">
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Grade & Section</div>
                                        <div>{{ slotProps.data.gradeLevel }} - {{ slotProps.data.section }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">Enrollment Date</div>
                                        <div>{{ slotProps.data.enrollmentDate }}</div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="text-sm text-color-secondary mb-1">LRN</div>
                                        <div>{{ slotProps.data.lrn }}</div>
                                        <div v-if="qrCodes[slotProps.data.lrn]" class="mt-2">
                                            <img :src="qrCodes[slotProps.data.lrn]" alt="LRN QR Code" class="w-24 h-24 border border-gray-200 rounded-md" />
                                        </div>
                                        <div v-else class="mt-2 flex items-center justify-center w-24 h-24 border border-gray-200 rounded-md">
                                            <i class="pi pi-spin pi-spinner text-xl text-color-secondary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty state -->
                    <template #empty>
                        <div class="p-4 text-center">
                            <i class="pi pi-search text-4xl text-color-secondary mb-3"></i>
                            <p>No students found. Try adjusting your filters or add a new student.</p>
                        </div>
                    </template>

                    <!-- Loading state -->
                    <template #loading>
                        <div class="p-4 text-center">
                            <i class="pi pi-spin pi-spinner text-4xl text-color-secondary mb-3"></i>
                            <p>Loading student data...</p>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Student Dialog -->
        <Dialog v-model:visible="studentDialog" modal header="Student Details" :style="{ width: '500px' }">
            <div class="p-4 space-y-4 left-2">
                <div>
                    <label for="name" class="block text-gray-700 font-medium">Student Name</label>
                    <InputText id="name" v-model="student.name" placeholder="Enter Student Name" class="w-full" />
                </div>
                <div>
                    <label for="gradeLevel" class="block font-medium">Grade Level</label>
                    <Dropdown id="gradeLevel" v-model="student.gradeLevel" :options="['Kindergarten', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6']" placeholder="Select Grade Level" class="w-full" />
                </div>
                <div>
                    <label for="section" class="block font-medium">Section</label>
                    <Dropdown id="section" v-model="student.section" :options="sectionsByGrade[student.gradeLevel] || []" placeholder="Select Section" class="w-full" />
                </div>
                <div>
                    <label for="gender" class="block font-medium">Gender</label>
                    <Dropdown id="gender" v-model="student.gender" :options="['Male', 'Female']" placeholder="Select Gender" class="w-full" />
                </div>
                <div>
                    <label for="lrn" class="block font-medium">LRN</label>
                    <InputText id="lrn" v-model="student.lrn" placeholder="Enter LRN" class="w-full" />
                </div>
                <div class="flex justify-end space-x-2 mt-4">
                    <Button label="Cancel" class="p-button-text" @click="studentDialog = false" />
                    <Button label="Save" icon="pi pi-check" class="p-button-success" @click="saveStudent" />
                </div>
            </div>
        </Dialog>

        <!-- Delete Confirmation -->
        <Dialog v-model:visible="deleteStudentDialog" modal header="Confirm Deletion" :style="{ width: '400px' }">
            <div class="p-4">
                <p>Are you sure you want to delete this student?</p>
                <div class="flex justify-end space-x-2 mt-4">
                    <Button label="No" class="p-button-text" @click="deleteStudentDialog = false" />
                    <Button label="Yes" icon="pi pi-trash" class="p-button-danger" @click="deleteStudent" />
                </div>
            </div>
        </Dialog>

        <!-- QR Code Dialog -->
        <Dialog v-model:visible="qrCodeDialog" modal header="Student LRN QR Code" :style="{ width: '350px' }">
            <div class="p-4 flex flex-column align-items-center">
                <div v-if="selectedStudent && qrCodes[selectedStudent.lrn]" class="mb-3">
                    <img :src="qrCodes[selectedStudent.lrn]" alt="LRN QR Code" class="w-48 h-48 border border-gray-200 rounded-md" />
                </div>
                <div v-if="selectedStudent" class="text-center">
                    <h3 class="text-xl font-semibold mb-1">{{ selectedStudent.name }}</h3>
                    <p class="mb-1">LRN: {{ selectedStudent.lrn }}</p>
                    <p class="text-sm text-color-secondary">{{ selectedStudent.gradeLevel }} - {{ selectedStudent.section }}</p>
                </div>
                <div class="flex justify-center mt-4">
                    <Button label="Close" icon="pi pi-times" class="p-button-text" @click="qrCodeDialog = false" />
                    <Button label="Print" icon="pi pi-print" class="p-button-text p-button-success ml-2" @click="printQRCode" />
                </div>
            </div>
        </Dialog>
    </div>
</template>

<style scoped>
:deep(.p-datatable-striped .p-datatable-tbody > tr:nth-child(even)) {
    background-color: #f9fafb;
}

:deep(.p-datatable-thead th) {
    background-color: #e5e7eb;
    font-weight: bold;
}

:deep(.p-dialog) {
    border-radius: 12px;
}

:deep(.p-button-success) {
    background-color: #22c55e;
    border: none;
}

:deep(.p-button-success:hover) {
    background-color: #16a34a;
}

:deep(.p-dialog) {
    border-radius: 12px;
}

:deep(.p-inputtext-lg) {
    padding: 0.75rem;
    border-radius: 8px;
}

:deep(.p-button-success) {
    background-color: #22c55e;
    border: none;
}

:deep(.p-button-success:hover) {
    background-color: #16a34a;
}
</style>
