<script setup>
import { useToast } from 'primevue/usetoast';
import QRCode from 'qrcode';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const toast = useToast();
const router = useRouter();
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
    photo: null,
    birthdate: '',
    age: ''
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
const fileInput = ref(null);
const viewStudentDialog = ref(false);
const isEdit = ref(false);

const selectedStudentAge = computed(() => calculateAge(selectedStudent.value?.birthdate));
const originalStudentClone = ref(null);

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

// Navigate to enrollment statistics page
const viewEnrollmentStats = () => {
    if (selectedStudent.value) {
        router.push({
            path: '/admin-student-statistics',
            query: {
                name: selectedStudent.value.name,
                photo: selectedStudent.value.photo || ''
            }
        });
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

// Load all students from database via API
const loadStudents = async () => {
    try {
        loading.value = true;

        // Fetch students from Laravel API
        const response = await fetch('http://localhost:8000/api/students', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const apiStudents = await response.json();
        console.log('Loaded students from API:', apiStudents);

        // Format students for display (mapping from lowercase database fields)
        const formattedStudents = apiStudents.map((student) => {
            return {
                id: student.id,
                studentId: student.studentid || student.student_id,
                name: student.name || `${student.firstname || ''} ${student.lastname || ''}`.trim(),
                firstName: student.firstname,
                lastName: student.lastname,
                email: student.email || 'N/A',
                gender: student.gender || student.sex || 'Male',
                age: calculateAge(student.birthdate),
                birthdate: student.birthdate ? new Date(student.birthdate).toLocaleDateString() : 'N/A',
                address: formatAddress(student),
                contact: student.contactinfo || student.parentcontact || 'N/A',
                photo: student.profilephoto || `https://randomuser.me/api/portraits/${student.gender === 'Female' ? 'women' : 'men'}/${student.id}.jpg`,
                gradeLevel: student.gradelevel,
                section: student.section,
                lrn: student.lrn || `${new Date().getFullYear()}${String(student.id).padStart(8, '0')}`,
                enrollmentDate: student.enrollmentdate ? new Date(student.enrollmentdate).toLocaleDateString() : new Date().toLocaleDateString(),
                // Store original data for reference
                originalData: student
            };
        });

        students.value = formattedStudents;
        totalStudents.value = formattedStudents.length;

        // Update the filter counts
        updateFilterCounts();
    } catch (error) {
        console.error('Error loading student data from API:', error);
        
        // Fallback to localStorage if API fails
        console.log('Falling back to localStorage...');
        try {
            const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
            const formattedStudents = enrolledStudents.map((student, index) => {
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
                    originalData: originalData
                };
            });
            students.value = formattedStudents;
            totalStudents.value = formattedStudents.length;
            updateFilterCounts();
        } catch (localError) {
            console.error('Error loading from localStorage:', localError);
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to load student data from both API and localStorage',
                life: 3000
            });
        }
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

// Save student to database via API
const saveStudent = async () => {
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
        // Prepare student data for API (using lowercase field names to match database)
        const studentId = student.value.studentId || `STU${String(Date.now()).slice(-5)}`;
        const studentData = {
            name: student.value.name,
            firstname: student.value.firstName || student.value.name.split(' ')[0],
            lastname: student.value.lastName || student.value.name.split(' ').slice(1).join(' '),
            email: student.value.email,
            gender: student.value.gender,
            sex: student.value.gender,
            gradelevel: student.value.gradeLevel,
            section: student.value.section,
            lrn: student.value.lrn || `${new Date().getFullYear()}${String(Date.now()).slice(-8)}`,
            studentid: studentId,
            student_id: studentId, // Both fields for compatibility
            birthdate: student.value.birthdate,
            age: student.value.age,
            contactinfo: student.value.phone,
            parentcontact: student.value.phone,
            profilephoto: student.value.photo,
            currentaddress: typeof student.value.address === 'string' ? { full: student.value.address } : student.value.address,
            enrollmentdate: new Date().toISOString(),
            status: 'Enrolled'
        };

        let response;
        if (student.value.id) {
            // Update existing student
            response = await fetch(`http://localhost:8000/api/students/${student.value.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(studentData)
            });
        } else {
            // Create new student
            response = await fetch('http://localhost:8000/api/students', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(studentData)
            });
        }

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
        }

        const savedStudent = await response.json();
        console.log('Student saved to database:', savedStudent);

        // Also save to localStorage as backup
        try {
            const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
            if (student.value.id) {
                const index = enrolledStudents.findIndex((s) => s.id === student.value.id);
                if (index !== -1) {
                    enrolledStudents[index] = { ...enrolledStudents[index], ...student.value };
                }
            } else {
                enrolledStudents.push({ ...student.value, id: savedStudent.id });
            }
            localStorage.setItem('enrolledStudents', JSON.stringify(enrolledStudents));
        } catch (localError) {
            console.warn('Failed to save to localStorage backup:', localError);
        }

        // Reload students from database
        await loadStudents();

        studentDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: `Student ${student.value.id ? 'Updated' : 'Created'} and saved to database!`,
            life: 3000
        });
    } catch (error) {
        console.error('Error saving student to database:', error);
        
        // Fallback to localStorage only
        try {
            const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
            if (student.value.id) {
                const index = enrolledStudents.findIndex((s) => s.id === student.value.id);
                if (index !== -1) {
                    enrolledStudents[index] = { ...enrolledStudents[index], ...student.value };
                }
            } else {
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
            localStorage.setItem('enrolledStudents', JSON.stringify(enrolledStudents));
            await loadStudents();
            studentDialog.value = false;
            toast.add({
                severity: 'warn',
                summary: 'Saved Locally',
                detail: 'Database unavailable. Student saved to local storage only.',
                life: 5000
            });
        } catch (localError) {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to save student to both database and localStorage',
                life: 3000
            });
        }
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

// Delete student from database via API
const deleteStudent = async () => {
    try {
        // Delete from database first
        const response = await fetch(`http://localhost:8000/api/students/${student.value.id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        console.log('Student deleted from database:', student.value.id);

        // Also remove from localStorage backup
        try {
            const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
            const updatedStudents = enrolledStudents.filter((s) => {
                if (s.studentId && student.value.studentId) {
                    return s.studentId !== student.value.studentId;
                }
                return s.id !== student.value.id;
            });
            localStorage.setItem('enrolledStudents', JSON.stringify(updatedStudents));
        } catch (localError) {
            console.warn('Failed to remove from localStorage backup:', localError);
        }

        // Reload students from database
        await loadStudents();

        deleteStudentDialog.value = false;
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: 'Student deleted from database successfully!',
            life: 3000
        });
    } catch (error) {
        console.error('Error deleting student from database:', error);
        
        // Fallback to localStorage only
        try {
            const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
            const updatedStudents = enrolledStudents.filter((s) => {
                if (s.studentId && student.value.studentId) {
                    return s.studentId !== student.value.studentId;
                }
                return s.id !== student.value.id;
            });
            localStorage.setItem('enrolledStudents', JSON.stringify(updatedStudents));
            await loadStudents();
            deleteStudentDialog.value = false;
            toast.add({
                severity: 'warn',
                summary: 'Deleted Locally',
                detail: 'Database unavailable. Student removed from local storage only.',
                life: 5000
            });
        } catch (localError) {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Failed to delete student from both database and localStorage',
                life: 3000
            });
        }
    }
};

// View student details dialog
function viewStudentDetails(studentData) {
    selectedStudent.value = studentData;
    viewStudentDialog.value = true;
}

// Row click handler to open student info dialog
function onRowClick(event) {
    viewStudentDetails(event.data);
}

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

// Generate Temporary ID card
function generateTempId() {
    if (!selectedStudent.value) return;

    const student = selectedStudent.value;
    const qrSrc = qrCodes.value[student.lrn] || '';
    const today = new Date().toISOString().slice(0, 10);

    const win = window.open('', '_blank');
    win.document.write(`
        <html>
        <head>
            <title>Temporary ID - ${student.name}</title>
            <style>
                * { box-sizing: border-box; }
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .card-wrapper { display: flex; gap: 20px; }
                .front, .back {
                    width: 350px;
                    height: 550px;
                    border: 1px solid #000;
                    border-radius: 12px;
                    overflow: hidden;
                    position: relative;
                }
                .front {
                    background: #fff url('https://via.placeholder.com/350x550?text=Background') no-repeat center/cover;
                }
                .vertical-ribbon {
                    position: absolute;
                    left: 0;
                    top: 0;
                    bottom: 0;
                    width: 40px;
                    background:#7a0c0c;
                    color:#fff;
                    writing-mode: vertical-rl;
                    text-orientation: mixed;
                    font-weight: bold;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    letter-spacing:2px;
                }
                .school-header { padding: 15px 20px 10px 70px; text-align: center; background: #fff; border-bottom: 2px solid #7a0c0c; }
                .logos-container { display: flex; justify-content: center; align-items: center; gap: 20px; margin-bottom: 8px; }
                .school-logo { width: 50px; height: 50px; object-fit: contain; }
                .school-name { font-size: 14px; font-weight: bold; color: #7a0c0c; margin: 0; line-height: 1.2; }
                .school-subtitle { font-size: 10px; color: #666; margin: 2px 0 0; }
                .front-content { padding: 20px 20px 20px 70px; text-align:center; }
                .front-content img.photo { width: 120px; height: 120px; object-fit:cover; border:2px solid #000; margin-bottom: 10px; border-radius: 50%; }
                .front-content h3 { margin:5px 0 0; font-size:18px; font-weight: bold; }
                .front-content h2 { margin:2px 0 5px; font-size:16px; color: #7a0c0c; }
                .front-content p { margin:2px 0; font-size:14px; }
                .qr-small { width:180px; height:180px; margin:10px auto 0; }
                /* back */
                .back-content { padding:20px; font-size:14px; }
                .field { margin:4px 0; }
                .label { font-weight:bold; }
                .ribbon-back { position:absolute; right:0; top:0; bottom:0; width:40px; background:#7a0c0c; color:#fff; writing-mode: vertical-rl; text-orientation: mixed; display:flex;align-items:center;justify-content:center; letter-spacing:2px; font-weight:bold; }
                @media print { .no-print { display:none; } }
            </style>
        </head>
        <body>
            <div class="card-wrapper">
                <!-- FRONT SIDE -->
                <div class="front">
                    <div class="vertical-ribbon">TEMPORARY ID</div>
                    <div class="school-header">
                        <div class="logos-container">
                            <img src="/demo/images/logo.png" class="school-logo" />
                            <img src="/demo/images/logo-msunaawan.jpg" class="school-logo" />
                            <img src="/demo/images/logo-cmas.jpg" class="school-logo" />
                        </div>
                        <div class="school-name">NAAWAN CENTRAL SCHOOL</div>
                        <div class="school-subtitle">NAAWAN, MIS OR.</div>
                    </div>
                    <div class="front-content">
                        <img src="${student.photo || 'https://via.placeholder.com/120x150?text=Photo'}" class="photo" />
                        <h3>${student.name.toUpperCase()}</h3>
                        <h2>${student.studentId || student.lrn}</h2>
                        <p>${student.gradeLevel} - ${student.section}</p>
                        <img src="${qrSrc}" class="qr-small" />
                    </div>
                </div>
                <!-- BACK SIDE -->
                <div class="back">
                    <div class="ribbon-back">TEMPORARY ID</div>
                    <div class="back-content">
                        <div class="field"><span class="label">DATE ISSUED:</span> ${today}</div>
                        <div class="field"><span class="label">DATE OF BIRTH:</span> ${student.birthdate || 'N/A'}</div>
                        <div class="field"><span class="label">CONTACT:</span> ${student.contact || 'N/A'}</div>
                        <div class="field"><span class="label">ADDRESS:</span> ${student.address || 'N/A'}</div>
                        <div style="margin:20px 0; text-align:center;">
                            <img src="${student.signature || 'https://via.placeholder.com/120x40?text=Signature'}" style="width:120px; height:40px; object-fit:contain;" />
                            <div style="font-size:12px; font-weight:bold; margin-top:4px;">${student.name.toUpperCase()}</div>
                        </div>
                        <div style="border:2px solid #000; padding:6px; text-align:center; font-weight:bold; margin-top:10px;">VALIDITY PERIOD: AY ${new Date().getFullYear()}-${new Date().getFullYear() + 1}</div>
                        <div style="font-size:11px; text-align:center; margin-top:6px;">THIS ID CARD IS NON-TRANSFERABLE</div>
                    </div>
                </div>
            </div>
            <div class="no-print" style="margin-top:20px; text-align:center;">
                <button onclick="window.print()">Print</button>
                <button onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
    `);
    win.document.close();
}

// -- ACTION BUTTON HANDLERS --
function updatePhoto() {
    if (fileInput.value) {
        fileInput.value.click();
    }
}
function handlePhotoUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
        selectedStudent.value.photo = reader.result;
        // persist immediately
        try {
            const enrolled = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
            const idx = enrolled.findIndex((s) => s.id === selectedStudent.value.id || s.studentId === selectedStudent.value.studentId);
            if (idx > -1) {
                enrolled[idx].photo = reader.result;
                localStorage.setItem('enrolledStudents', JSON.stringify(enrolled));
                loadStudents();
                toast.add({ severity: 'success', summary: 'Photo Updated', detail: 'Student photo updated successfully', life: 3000 });
            }
        } catch (err) {
            console.error('Save photo error', err);
            toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to save photo', life: 3000 });
        }
    };
    reader.readAsDataURL(file);
}
function startEditProfile() {
    originalStudentClone.value = { ...selectedStudent.value };
    isEdit.value = true;
}
function cancelInlineEdit() {
    // revert changes
    selectedStudent.value = { ...originalStudentClone.value };
    isEdit.value = false;
}
function saveInlineProfile() {
    try {
        const enrolledStudents = JSON.parse(localStorage.getItem('enrolledStudents') || '[]');
        const idx = enrolledStudents.findIndex((s) => {
            if (s.studentId && selectedStudent.value.studentId) {
                return s.studentId === selectedStudent.value.studentId;
            }
            return s.id === selectedStudent.value.id;
        });
        if (idx > -1) {
            selectedStudent.value.age = calculateAge(selectedStudent.value.birthdate);
            enrolledStudents[idx] = { ...selectedStudent.value };
            localStorage.setItem('enrolledStudents', JSON.stringify(enrolledStudents));
            loadStudents();
            toast.add({ severity: 'success', summary: 'Success', detail: 'Student Updated', life: 3000 });
        }
        isEdit.value = false;
    } catch (error) {
        console.error('Error updating student:', error);
        toast.add({ severity: 'error', summary: 'Error', detail: 'Failed to update student', life: 3000 });
    }
}

function updateSignature(studentData) {
    toast.add({ severity: 'info', summary: 'Update E-Signature', detail: 'Feature not implemented yet', life: 3000 });
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
    <input type="file" accept="image/*" ref="fileInput" class="hidden" @change="handlePhotoUpload" />
    <div class="card p-6 shadow-lg rounded-lg bg-white">
        <div class="flex justify-between items-center mb-4 student-management-header">
            <div>
                <h1 class="teacher-management-title"><i class="pi pi-users mr-2"></i>Student Management</h1>
                <p class="text-color-secondary">
                    Total Students: <span class="font-bold">{{ totalStudents }}</span>
                </p>
            </div>
            <div class="flex gap-2">
                <span class="p-input-icon-left w-full md:w-20rem">
                    <i class="pi pi-search" />
                    <InputText v-model="filters.searchTerm" placeholder="Search students..." class="w-full" />
                </span>
                <Button label="Add Student" icon="pi pi-plus" class="p-button-primary" @click="studentDialog = true" />
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
                <DataTable :value="filteredStudents" dataKey="id" class="p-datatable-sm" :loading="loading" stripedRows responsiveLayout="scroll" :paginator="filteredStudents.length > 10" :rows="10" @rowClick="onRowClick">
                    <Column header="#" style="width: 3rem">
                        <template #body="slotProps">
                            <span>{{ slotProps.index + 1 }}</span>
                        </template>
                    </Column>
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
                            <span class="font-semibold">{{ slotProps.data.lrn }}</span>
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
                                <Button icon="pi pi-search" class="p-button-rounded p-button-text" @click="viewStudentDetails(slotProps.data)" />
                                <Button icon="pi pi-pencil" class="p-button-rounded p-button-text" @click="editStudent(slotProps.data)" />
                                <Button icon="pi pi-trash" class="p-button-rounded p-button-text p-button-danger" @click="confirmDeleteStudent(slotProps.data)" />
                            </div>
                        </template>
                    </Column>
                </DataTable>
            </div>
        </div>

        <!-- View Student Details Dialog -->
        <Dialog v-model:visible="viewStudentDialog" modal :style="{ width: '850px' }" :dismissableMask="true">
            <template #header>
                <div class="flex justify-between items-center w-full pr-2">
                    <span class="text-lg font-semibold">Student Information - {{ selectedStudent ? selectedStudent.name : '' }}</span>
                    <div class="flex gap-2">
                        <Button label="Enrollment Statistics" icon="pi pi-chart-bar" class="p-button-primary p-button-sm" @click="viewEnrollmentStats" />
                        <Button label="Generate Temporary ID" icon="pi pi-id-card" class="p-button-danger p-button-sm" @click="generateTempId" />
                    </div>
                </div>
            </template>

            <div v-if="selectedStudent" class="grid md:grid-cols-3 gap-6 p-4">
                <!-- Left column -->
                <div class="flex flex-col items-center space-y-4">
                    <p class="text-gray-600 font-medium">Temporary ID Photo</p>
                    <img v-if="selectedStudent.photo" :src="selectedStudent.photo" alt="Student Photo" class="w-48 h-48 rounded-full object-cover ring-2 ring-gray-300" />
                    <div v-else class="w-48 h-48 rounded-full bg-gray-200 flex items-center justify-center text-gray-500">No Photo</div>
                    <p class="text-gray-600 font-medium">QR Code</p>
                    <img v-if="qrCodes[selectedStudent.lrn]" :src="qrCodes[selectedStudent.lrn]" class="w-48 h-48 border rounded-md object-contain" />
                    <p v-else class="text-xs text-gray-400">No QR</p>
                </div>

                <!-- Right column -->
                <div v-if="!isEdit" class="md:col-span-2 space-y-2">
                    <h2 class="font-bold text-2xl mb-1">{{ selectedStudent.name }}</h2>
                    <p class="text-gray-600 mb-3">{{ selectedStudent.studentId }}</p>
                    <hr />
                    <div class="space-y-2 text-sm mt-2">
                        <p><span class="font-semibold">Grade & Section:</span> {{ selectedStudent.gradeLevel }} - {{ selectedStudent.section }}</p>
                        <p><span class="font-semibold">Sex:</span> {{ selectedStudent.gender }}</p>
                        <p><span class="font-semibold">Date of Birth:</span> {{ selectedStudent.birthdate || 'N/A' }}</p>
                        <p><span class="font-semibold">Age:</span> {{ selectedStudentAge || 'N/A' }}</p>
                        <p><span class="font-semibold">Enrollment Date:</span> {{ selectedStudent.enrollmentDate }}</p>
                        <p><span class="font-semibold">LRN:</span> {{ selectedStudent.lrn }}</p>
                        <p><span class="font-semibold">Address:</span> {{ selectedStudent.address }}</p>
                        <p><span class="font-semibold">Email:</span> {{ selectedStudent.email }}</p>
                        <p><span class="font-semibold">Contact:</span> {{ selectedStudent.contact }}</p>
                    </div>

                    <div class="col-12 sm:col-6">
                        <label class="font-semibold">Email</label>
                        <p>{{ selectedStudent.email }}</p>
                    </div>
                    <div class="col-12 sm:col-6">
                        <label class="font-semibold">Contact</label>
                        <p>{{ selectedStudent.contact }}</p>
                    </div>
                </div>
                <div v-else class="md:col-span-2 space-y-2">
                    <div class="space-y-2 text-sm mt-2">
                        <div>
                            <label class="font-semibold">Student Name</label>
                            <InputText v-model="selectedStudent.name" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Grade Level</label>
                            <Dropdown v-model="selectedStudent.gradeLevel" :options="['Kindergarten', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6']" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Section</label>
                            <Dropdown v-model="selectedStudent.section" :options="sectionsByGrade[selectedStudent.gradeLevel] || []" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Gender</label>
                            <Dropdown v-model="selectedStudent.gender" :options="['Male', 'Female']" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Date of Birth</label>
                            <Calendar v-model="selectedStudent.birthdate" showIcon dateFormat="yy-mm-dd" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Address</label>
                            <InputText v-model="selectedStudent.address" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Email</label>
                            <InputText v-model="selectedStudent.email" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">Contact</label>
                            <InputText v-model="selectedStudent.contact" class="w-full" />
                        </div>
                        <div>
                            <label class="font-semibold">LRN</label>
                            <InputText v-model="selectedStudent.lrn" class="w-full" />
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2 w-full">
                    <Button label="Update Photo" icon="pi pi-camera" class="p-button-warning p-button-sm" @click="updatePhoto(selectedStudent)" />
                    <Button label="Update E-Signature" icon="pi pi-pencil" class="p-button-danger p-button-sm" @click="updateSignature(selectedStudent)" />
                    <Button v-if="!isEdit" label="Update Profile" icon="pi pi-user-edit" class="p-button-info p-button-sm" @click="startEditProfile" />
                    <Button v-else label="Save Changes" icon="pi pi-check" class="p-button-success p-button-sm" @click="saveInlineProfile" />
                    <Button v-if="isEdit" label="Cancel" icon="pi pi-times" class="p-button-text p-button-sm" @click="cancelInlineEdit" />
                </div>
            </template>
        </Dialog>

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

/* --- Consistent styling with Teacher Management --- */
.teacher-management-title {
    color: var(--primary-color);
    font-size: 1.75rem;
    font-weight: 600;
}

:deep(.p-button-primary) {
    background-color: #4361ee;
    border: none;
}

:deep(.p-button-primary:hover) {
    background-color: #3b5ce6;
}
</style>



    

   