$path = "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms\src\views\pages\Admin\Admin-Teacher.vue"

# Read the file content
$content = Get-Content -Raw -LiteralPath $path

# Define the new assignSection function with teacher validation
$newAssignSectionFunction = @'
const assignSection = async (teacher) => {
    console.log('Opening assign section dialog for teacher:', teacher);
    selectedTeacher.value = teacher;
    
    try {
        // Get teacher's current assignments to determine their type
        const assignmentsResponse = await fetch(`http://127.0.0.1:8000/api/teachers/${teacher.id}/assignments`);
        let teacherAssignments = [];
        
        if (assignmentsResponse.ok) {
            teacherAssignments = await assignmentsResponse.json();
        }
        
        console.log('Teacher assignments:', teacherAssignments);
        
        // Determine teacher type based on current assignments
        const currentGrades = [...new Set(teacherAssignments.map(a => a.section?.grade_level).filter(g => g))];
        const k3Grades = ['Kinder', 'Grade 1', 'Grade 2', 'Grade 3'];
        const grade46Grades = ['Grade 4', 'Grade 5', 'Grade 6'];
        
        const teachesK3 = currentGrades.some(grade => k3Grades.includes(grade));
        const teachesGrade46 = currentGrades.some(grade => grade46Grades.includes(grade));
        
        console.log('Teacher grade analysis:', {
            currentGrades,
            teachesK3,
            teachesGrade46
        });
        
        // Load all sections
        const response = await api.get(`${API_BASE_URL}/sections`);
        const allSections = response.data || [];
        
        console.log('All sections loaded:', allSections.length);
        
        // Filter sections based on availability AND teacher compatibility
        const availableSections = allSections.filter(section => {
            const hasHomeroomTeacher = section.homeroom_teacher_id || section.homeroomTeacher;
            const isCurrentTeacher = section.homeroom_teacher_id === teacher.id;
            
            // Must be available (no homeroom teacher or current teacher)
            const isAvailable = !hasHomeroomTeacher || isCurrentTeacher;
            
            if (!isAvailable) return false;
            
            // If teacher has no assignments, allow any grade
            if (currentGrades.length === 0) {
                console.log(`Section ${section.name}: New teacher - allowing all grades`);
                return true;
            }
            
            // Check grade compatibility
            const sectionGrade = section.grade_level || section.grade || section.curriculum_grade?.grade?.name;
            const sectionIsK3 = k3Grades.includes(sectionGrade);
            const sectionIsGrade46 = grade46Grades.includes(sectionGrade);
            
            let isCompatible = false;
            
            if (teachesK3 && !teachesGrade46 && sectionIsK3) {
                isCompatible = true; // K-3 teacher can teach K-3 sections
            } else if (!teachesK3 && teachesGrade46 && sectionIsGrade46) {
                isCompatible = true; // Grade 4-6 teacher can teach Grade 4-6 sections
            } else if (teachesK3 && teachesGrade46) {
                isCompatible = false; // Mixed assignments - no new homeroom allowed
            }
            
            console.log(`Section ${section.name} (${sectionGrade}): compatible=${isCompatible}, available=${isAvailable}`);
            
            return isCompatible;
        });
        
        sections.value = availableSections.map(section => ({
            id: Number(section.id),
            name: section.name || `Section ${section.id}`,
            grade: section.grade_level || section.grade || section.curriculum_grade?.grade?.name,
            homeroom_teacher_id: section.homeroom_teacher_id
        }));
        
        console.log(`Filtered sections: ${sections.value.length} compatible sections for teacher type`);
        console.log('Available sections:', sections.value.map(s => `${s.name} (${s.grade})`));
        
        // Show warning if no compatible sections
        if (sections.value.length === 0) {
            let warningMessage = 'No compatible sections available for this teacher.';
            
            if (teachesK3 && !teachesGrade46) {
                warningMessage = 'This teacher currently teaches K-3 students and can only be assigned to K-3 sections (Kinder, Grade 1, Grade 2, Grade 3).';
            } else if (!teachesK3 && teachesGrade46) {
                warningMessage = 'This teacher is a Grade 4-6 departmental teacher and can only be assigned to Grade 4-6 sections.';
            } else if (teachesK3 && teachesGrade46) {
                warningMessage = 'This teacher has mixed K-3 and Grade 4-6 assignments, which violates school policy. Please review their current assignments first.';
            }
            
            toast.add({
                severity: 'warn',
                summary: 'No Compatible Sections',
                detail: warningMessage,
                life: 8000
            });
        }
        
    } catch (error) {
        console.error('Error loading available sections:', error);
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: 'Failed to load sections. Please try again.',
            life: 5000
        });
    }
    
    assignSectionDialog.value = true;
};
'@

# Find and replace the assignSection function
$pattern = "const assignSection = async \(teacher\) => \{[\s\S]*?\};"
$content = [regex]::Replace($content, $pattern, $newAssignSectionFunction)

# Write the updated content back to the file
Set-Content -LiteralPath $path -Value $content -Encoding UTF8

Write-Output "Successfully replaced assignSection function with teacher validation logic"
