<?php

require_once 'lamms-backend/vendor/autoload.php';

// Load Laravel app
$app = require_once 'lamms-backend/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Section;
use App\Models\CurriculumGrade;
use App\Models\Grade;
use App\Models\Teacher;

echo "Testing Section model relationships...\n\n";

try {
    // Test basic section count
    $sectionCount = Section::count();
    echo "Total sections: $sectionCount\n";

    // Test curriculum grade count
    $curriculumGradeCount = CurriculumGrade::count();
    echo "Total curriculum grades: $curriculumGradeCount\n";

    // Test grade count
    $gradeCount = Grade::count();
    echo "Total grades: $gradeCount\n";

    // Test teacher count
    $teacherCount = Teacher::count();
    echo "Total teachers: $teacherCount\n\n";

    // Test sections for grade 1
    echo "Testing sections for grade 1...\n";
    $sections = Section::select(['id', 'name', 'capacity', 'is_active', 'curriculum_grade_id', 'homeroom_teacher_id'])
        ->with([
            'curriculumGrade:id,grade_id,curriculum_id',
            'curriculumGrade.grade:id,code,name',
            'homeroomTeacher:id,first_name,last_name'
        ])
        ->whereHas('curriculumGrade', function($query) {
            $query->where('grade_id', 1);
        })
        ->get();

    echo "Found " . $sections->count() . " sections for grade 1:\n";
    foreach ($sections as $section) {
        echo "- Section {$section->name} (ID: {$section->id})\n";
        if ($section->curriculumGrade && $section->curriculumGrade->grade) {
            echo "  Grade: {$section->curriculumGrade->grade->name}\n";
        }
        if ($section->homeroomTeacher) {
            echo "  Teacher: {$section->homeroomTeacher->first_name} {$section->homeroomTeacher->last_name}\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
