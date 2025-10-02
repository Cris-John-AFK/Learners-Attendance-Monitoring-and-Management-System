$path = "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms\src\components\TeacherAssignmentWizard.vue"

# Read the file content
$content = Get-Content -Raw -LiteralPath $path

# Add teacher type validation function after the loadSections function
$validationFunction = @'

// Validate if teacher can be assigned as homeroom to a specific grade level
const validateTeacherForHomeroom = async (teacherId, gradeLevel) => {
    try {
        // Get teacher's current assignments to determine their type
        const response = await axios.get(`${props.apiBaseUrl}/teachers/${teacherId}/assignments`);
        const assignments = response.data;
        
        if (!assignments || assignments.length === 0) {
            // New teacher with no assignments - allow any grade
            return { valid: true, message: '' };
        }
        
        // Analyze teacher's current grade levels
        const currentGrades = [...new Set(assignments.map(a => a.section?.grade_level).filter(g => g))];
        
        // Define grade categories
        const k3Grades = ['Kinder', 'Grade 1', 'Grade 2', 'Grade 3'];
        const grade46Grades = ['Grade 4', 'Grade 5', 'Grade 6'];
        
        // Check what categories the teacher currently teaches
        const teachesK3 = currentGrades.some(grade => k3Grades.includes(grade));
        const teachesGrade46 = currentGrades.some(grade => grade46Grades.includes(grade));
        
        // Determine if the requested grade is compatible
        const requestedIsK3 = k3Grades.includes(gradeLevel);
        const requestedIsGrade46 = grade46Grades.includes(gradeLevel);
        
        // Validation rules
        if (teachesK3 && !teachesGrade46 && requestedIsGrade46) {
            return {
                valid: false,
                message: `This teacher currently teaches K-3 students and cannot be assigned as homeroom teacher to Grade 4-6. K-3 teachers must teach all subjects to their homeroom students only.`
            };
        }
        
        if (!teachesK3 && teachesGrade46 && requestedIsK3) {
            return {
                valid: false,
                message: `This teacher is a Grade 4-6 departmental teacher and cannot be assigned as homeroom teacher to K-3. Grade 4-6 teachers are subject specialists who teach across multiple sections.`
            };
        }
        
        if (teachesK3 && teachesGrade46) {
            return {
                valid: false,
                message: `This teacher has mixed K-3 and Grade 4-6 assignments, which violates school policy. Please review their current assignments first.`
            };
        }
        
        return { valid: true, message: '' };
        
    } catch (error) {
        console.error('Error validating teacher assignment:', error);
        return {
            valid: false,
            message: 'Unable to validate teacher assignment. Please try again.'
        };
    }
};
'@

# Insert the validation function after loadSections
$content = $content -replace "(const loadSections = async \(gradeId\) => \{[\s\S]*?\};\s*)", "`$1`r`n$validationFunction`r`n"

# Add validation call in selectSection function
$selectSectionValidation = @'
const selectSection = async (section) => {
    // Validate teacher assignment for homeroom role
    if (selectedRole.value === 'primary') {
        const validation = await validateTeacherForHomeroom(props.teacher.id, section.grade_level);
        
        if (!validation.valid) {
            toast.add({
                severity: 'error',
                summary: 'Assignment Not Allowed',
                detail: validation.message,
                life: 8000
            });
            return; // Stop the assignment
        }
    }
    
    selectedSection.value = section;
    loadSubjects();
    goToNextStep();
};
'@

# Replace the existing selectSection function
$content = $content -replace "const selectSection = \(section\) => \{[\s\S]*?\};", $selectSectionValidation

# Write the updated content back to the file
Set-Content -LiteralPath $path -Value $content -Encoding UTF8

Write-Output "Added teacher type validation to prevent inappropriate homeroom assignments"
