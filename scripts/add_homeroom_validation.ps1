$path = "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms\src\views\pages\Admin\Admin-Teacher.vue"

# Read the file content
$content = Get-Content -Raw -LiteralPath $path

# Add validation function for homeroom assignment
$validationFunction = @'

// Validate teacher homeroom assignment
const validateHomeroomAssignment = async (teacherId, sectionId) => {
    try {
        // Get section details to determine grade level
        const sectionResponse = await fetch(`http://127.0.0.1:8000/api/sections/${sectionId}`);
        if (!sectionResponse.ok) {
            throw new Error('Failed to fetch section details');
        }
        const section = await sectionResponse.json();
        
        // Validate the assignment
        const validationResponse = await fetch('http://127.0.0.1:8000/api/teachers/validate-homeroom-assignment', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                teacher_id: teacherId,
                grade_level: section.grade_level,
                role: 'primary'
            })
        });
        
        if (!validationResponse.ok) {
            throw new Error('Validation request failed');
        }
        
        const validation = await validationResponse.json();
        return validation;
        
    } catch (error) {
        console.error('Error validating homeroom assignment:', error);
        return {
            valid: false,
            message: 'Unable to validate assignment. Please try again.'
        };
    }
};
'@

# Find the assignSection function and add validation
$assignSectionPattern = "(const assignSection = async \(\) => \{)"
$assignSectionReplacement = @'
const assignSection = async () => {
    try {
        // Validate the homeroom assignment first
        const validation = await validateHomeroomAssignment(selectedTeacher.value.id, selectedSection.value);
        
        if (!validation.valid) {
            toast.add({
                severity: 'error',
                summary: 'Assignment Not Allowed',
                detail: validation.message,
                life: 8000
            });
            return; // Stop the assignment
        }
'@

# Insert validation function before assignSection
$content = $content -replace $assignSectionPattern, "$validationFunction`r`n`r`n`$1"

# Update the assignSection function to include validation
$content = $content -replace "const assignSection = async \(\) => \{", $assignSectionReplacement

# Write the updated content back to the file
Set-Content -LiteralPath $path -Value $content -Encoding UTF8

Write-Output "Added homeroom assignment validation to Admin-Teacher.vue"
