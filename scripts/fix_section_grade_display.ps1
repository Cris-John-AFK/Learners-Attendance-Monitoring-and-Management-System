# Fix section grade display in Admin-Teacher.vue
$filePath = "c:\xampp\htdocs\CAPSTONE 2\sakai_lamms\src\views\pages\Admin\Admin-Teacher.vue"

Write-Host "Fixing section grade display..." -ForegroundColor Green

# Read the file content
$content = Get-Content $filePath -Raw

# Fix the section mapping to properly extract grade information
$oldMapping = @"
        sections.value = availableSections.map(section => ({
            id: Number(section.id),
            name: section.name || `Section `${section.id}`,
            grade: section.grade?.name || section.grade_level || section.grade || section.curriculum_grade?.grade?.name || 'Grade not set',
            homeroom_teacher_id: section.homeroom_teacher_id
        }));
"@

$newMapping = @"
        sections.value = availableSections.map(section => {
            // Extract grade information more reliably
            let gradeName = 'Grade not set';
            
            // Try multiple sources for grade information
            if (section.grade?.name) {
                gradeName = section.grade.name;
            } else if (section.curriculum_grade?.grade?.name) {
                gradeName = section.curriculum_grade.grade.name;
            } else if (section.grade_level) {
                gradeName = section.grade_level;
            } else if (section.grade) {
                gradeName = typeof section.grade === 'string' ? section.grade : section.grade.name;
            }
            
            // Normalize grade name
            if (gradeName && gradeName !== 'Grade not set') {
                const normalizeGrade = (grade) => {
                    if (!grade) return 'Grade not set';
                    const gradeStr = grade.toString().toLowerCase();
                    if (gradeStr.includes('kinder') || gradeStr.includes('kindergarten')) return 'Kindergarten';
                    if (gradeStr.includes('1') || gradeStr === 'grade 1') return 'Grade 1';
                    if (gradeStr.includes('2') || gradeStr === 'grade 2') return 'Grade 2';
                    if (gradeStr.includes('3') || gradeStr === 'grade 3') return 'Grade 3';
                    if (gradeStr.includes('4') || gradeStr === 'grade 4') return 'Grade 4';
                    if (gradeStr.includes('5') || gradeStr === 'grade 5') return 'Grade 5';
                    if (gradeStr.includes('6') || gradeStr === 'grade 6') return 'Grade 6';
                    return grade;
                };
                gradeName = normalizeGrade(gradeName);
            }
            
            console.log(`Section ${section.name}: grade=${gradeName}`);
            
            return {
                id: Number(section.id),
                name: section.name || `Section ${section.id}`,
                grade: gradeName,
                homeroom_teacher_id: section.homeroom_teacher_id
            };
        });
"@

if ($content -match [regex]::Escape($oldMapping)) {
    $content = $content -replace [regex]::Escape($oldMapping), $newMapping
    Write-Host "Fixed section grade mapping" -ForegroundColor Green
} else {
    Write-Host "Could not find section mapping pattern" -ForegroundColor Yellow
}

# Write back to file
Set-Content $filePath -Value $content -Encoding UTF8

Write-Host "Section grade display fix completed!" -ForegroundColor Green
