<?php
require_once 'lamms-backend/vendor/autoload.php';

try {
    $app = require_once 'lamms-backend/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    
    echo "=== NAAWAN CENTRAL SCHOOL COMPLETE SEEDER ===\n\n";
    echo "Building upon existing data: 839 students, 16 teacher assignments\n\n";
    
    // Define grade level mapping for sections (based on DepEd structure)
    $sectionGradeMapping = [
        // Kindergarten sections
        'Sampaguita' => 'Kindergarten',
        'Gumamela' => 'Kindergarten', 
        'Mabini' => 'Kindergarten',
        
        // Grade 1 sections
        'Rizal' => 'Grade 1',
        'Bonifacio' => 'Grade 1',
        
        // Grade 2 sections  
        'Luna' => 'Grade 2',
        'Jacinto' => 'Grade 2',
        
        // Grade 3 sections
        'Aguinaldo' => 'Grade 3',
        'Silang' => 'Grade 3',
        
        // Grade 4 sections
        'Dagohoy' => 'Grade 4',
        'Tandang Sora' => 'Grade 4',
        
        // Grade 5 sections
        'Gabriela' => 'Grade 5',
        'Lapu-Lapu' => 'Grade 5',
        
        // Grade 6 sections
        'Magat Salamat' => 'Grade 6'
    ];
    
    // Get current data
    $sections = DB::table('sections')->get()->keyBy('name');
    $teachers = DB::table('teachers')->get()->keyBy('id');
    $subjects = DB::table('subjects')->get();
    
    // Get unassigned teachers
    $assignedTeacherIds = DB::table('teacher_section_subject')->pluck('teacher_id')->unique();
    $unassignedTeachers = $teachers->reject(function($teacher) use ($assignedTeacherIds) {
        return $assignedTeacherIds->contains($teacher->id);
    });
    
    echo "1. FILLING HOMEROOM GAPS...\n";
    
    // Sections without homeroom teachers
    $sectionsNeedingHomeroom = ['Rizal', 'Luna', 'Aguinaldo', 'Jacinto'];
    $availableTeachers = $unassignedTeachers->values();
    
    foreach ($sectionsNeedingHomeroom as $index => $sectionName) {
        if ($index < count($availableTeachers)) {
            $teacher = $availableTeachers[$index];
            $section = $sections[$sectionName];
            $gradeLevel = $sectionGradeMapping[$sectionName];
            
            // Assign as homeroom teacher
            DB::table('teacher_section_subject')->insert([
                'teacher_id' => $teacher->id,
                'section_id' => $section->id,
                'subject_id' => null,
                'is_primary' => true,
                'is_active' => true,
                'role' => 'homeroom'
            ]);
            
            echo "   ✅ {$teacher->first_name} {$teacher->last_name} -> {$sectionName} ({$gradeLevel}) [HOMEROOM]\n";
        }
    }
    
    echo "\n2. ADDING SUBJECT ASSIGNMENTS FOR KINDER-GRADE 3 HOMEROOM TEACHERS...\n";
    
    // For Kinder-Grade 3: Homeroom teachers teach ALL subjects
    $kinderGrade3Subjects = [
        'Kindergarten' => [
            'Mother Tongue-Based Multilingual Education' => 'KINDER-MTB',
            'English' => 'KINDER-ENG', 
            'Filipino' => 'KINDER-FIL',
            'Mathematics' => 'KINDER-MATH',
            'Arts' => 'KINDER-ARTS',
            'Music' => 'KINDER-MUSIC',
            'Physical Education' => 'KINDER-PE'
        ],
        'Grade 1' => [
            'Mother Tongue' => 'G1-3-MTB',
            'Filipino' => 'G1-3-FIL',
            'English' => 'G1-3-ENG',
            'Mathematics' => 'G1-3-MATH',
            'Araling Panlipunan' => 'G1-3-AP',
            'Science' => 'G1-3-SCI',
            'MAPEH' => 'G1-3-MAPEH',
            'Edukasyon sa Pagpapakatao' => 'G1-3-ESP'
        ],
        'Grade 2' => [
            'Mother Tongue' => 'G1-3-MTB',
            'Filipino' => 'G1-3-FIL',
            'English' => 'G1-3-ENG',
            'Mathematics' => 'G1-3-MATH',
            'Araling Panlipunan' => 'G1-3-AP',
            'Science' => 'G1-3-SCI',
            'MAPEH' => 'G1-3-MAPEH',
            'Edukasyon sa Pagpapakatao' => 'G1-3-ESP'
        ],
        'Grade 3' => [
            'Mother Tongue' => 'G1-3-MTB',
            'Filipino' => 'G1-3-FIL',
            'English' => 'G1-3-ENG',
            'Mathematics' => 'G1-3-MATH',
            'Araling Panlipunan' => 'G1-3-AP',
            'Science' => 'G1-3-SCI',
            'MAPEH' => 'G1-3-MAPEH',
            'Edukasyon sa Pagpapakatao' => 'G1-3-ESP'
        ]
    ];
    
    // Get homeroom teachers for Kinder-Grade 3
    $kinderGrade3Sections = ['Sampaguita', 'Gumamela', 'Mabini', 'Rizal', 'Bonifacio', 'Luna', 'Jacinto', 'Aguinaldo', 'Silang'];
    
    foreach ($kinderGrade3Sections as $sectionName) {
        $section = $sections[$sectionName];
        $gradeLevel = $sectionGradeMapping[$sectionName];
        
        // Get homeroom teacher for this section
        $homeroomAssignment = DB::table('teacher_section_subject')
            ->where('section_id', $section->id)
            ->where('role', 'homeroom')
            ->first();
        
        if ($homeroomAssignment) {
            $teacher = $teachers[$homeroomAssignment->teacher_id];
            $subjectsForGrade = $kinderGrade3Subjects[$gradeLevel] ?? [];
            
            echo "   👨‍🏫 {$teacher->first_name} {$teacher->last_name} -> {$sectionName} ({$gradeLevel}):\n";
            
            foreach ($subjectsForGrade as $subjectName => $subjectCode) {
                $subject = $subjects->first(function($s) use ($subjectCode) {
                    return $s->code === $subjectCode;
                });
                
                if ($subject) {
                    // Check if assignment already exists
                    $existingAssignment = DB::table('teacher_section_subject')
                        ->where('teacher_id', $teacher->id)
                        ->where('section_id', $section->id)
                        ->where('subject_id', $subject->id)
                        ->first();
                    
                    if (!$existingAssignment) {
                        DB::table('teacher_section_subject')->insert([
                            'teacher_id' => $teacher->id,
                            'section_id' => $section->id,
                            'subject_id' => $subject->id,
                            'is_primary' => true,
                            'is_active' => true,
                            'role' => 'homeroom'
                        ]);
                        echo "     📚 {$subjectName}\n";
                    }
                }
            }
        }
    }
    
    echo "\n3. CREATING SPECIALIST TEACHERS FOR GRADE 4-6...\n";
    
    // Grade 4-6 subjects
    $grade46Subjects = [
        'Filipino' => 'G4-6-FIL',
        'English' => 'G4-6-ENG',
        'Mathematics' => 'G4-6-MATH',
        'Araling Panlipunan' => 'G4-6-AP',
        'Science' => 'G4-6-SCI',
        'MAPEH' => 'G4-6-MAPEH',
        'Edukasyon sa Pagpapakatao' => 'G4-6-ESP',
        'Technology and Livelihood Education' => 'G4-6-TLE'
    ];
    
    $grade46Sections = ['Dagohoy', 'Tandang Sora', 'Gabriela', 'Lapu-Lapu', 'Magat Salamat'];
    
    // Get remaining unassigned teachers for specialists
    $currentlyAssignedIds = DB::table('teacher_section_subject')->pluck('teacher_id')->unique();
    $remainingTeachers = $teachers->reject(function($teacher) use ($currentlyAssignedIds) {
        return $currentlyAssignedIds->contains($teacher->id);
    })->values();
    
    // Assign specialist teachers
    $specialistSubjects = ['English', 'Mathematics', 'Science', 'MAPEH', 'Filipino', 'Araling Panlipunan'];
    
    foreach ($specialistSubjects as $index => $specialistSubject) {
        if ($index < count($remainingTeachers)) {
            $teacher = $remainingTeachers[$index];
            
            echo "   🎯 {$teacher->first_name} {$teacher->last_name} -> {$specialistSubject} Specialist (Grade 4-6):\n";
            
            // Find the subject
            $subjectCode = $grade46Subjects[$specialistSubject] ?? '';
            $subject = $subjects->first(function($s) use ($subjectCode) {
                return $s->code === $subjectCode;
            });
            
            if ($subject) {
                foreach ($grade46Sections as $sectionName) {
                    $section = $sections[$sectionName];
                    $gradeLevel = $sectionGradeMapping[$sectionName];
                    
                    DB::table('teacher_section_subject')->insert([
                        'teacher_id' => $teacher->id,
                        'section_id' => $section->id,
                        'subject_id' => $subject->id,
                        'is_primary' => false,
                        'is_active' => true,
                        'role' => 'subject_teacher'
                    ]);
                    
                    echo "     📚 {$sectionName} ({$gradeLevel})\n";
                }
            }
        }
    }
    
    echo "\n4. COMPLETING GRADE 4-6 HOMEROOM SUBJECT ASSIGNMENTS...\n";
    
    // For Grade 4-6 homeroom teachers, assign remaining subjects they don't have specialists for
    foreach ($grade46Sections as $sectionName) {
        $section = $sections[$sectionName];
        $gradeLevel = $sectionGradeMapping[$sectionName];
        
        // Get homeroom teacher
        $homeroomAssignment = DB::table('teacher_section_subject')
            ->where('section_id', $section->id)
            ->where('role', 'homeroom')
            ->first();
        
        if ($homeroomAssignment) {
            $teacher = $teachers[$homeroomAssignment->teacher_id];
            
            echo "   👨‍🏫 {$teacher->first_name} {$teacher->last_name} -> {$sectionName} ({$gradeLevel}) additional subjects:\n";
            
            // Get subjects not assigned to specialists for this section
            $assignedSubjectIds = DB::table('teacher_section_subject')
                ->where('section_id', $section->id)
                ->where('role', 'subject_teacher')
                ->pluck('subject_id');
            
            foreach ($grade46Subjects as $subjectName => $subjectCode) {
                $subject = $subjects->first(function($s) use ($subjectCode) {
                    return $s->code === $subjectCode;
                });
                
                if ($subject && !$assignedSubjectIds->contains($subject->id)) {
                    // Check if homeroom teacher already has this subject
                    $existingAssignment = DB::table('teacher_section_subject')
                        ->where('teacher_id', $teacher->id)
                        ->where('section_id', $section->id)
                        ->where('subject_id', $subject->id)
                        ->first();
                    
                    if (!$existingAssignment) {
                        DB::table('teacher_section_subject')->insert([
                            'teacher_id' => $teacher->id,
                            'section_id' => $section->id,
                            'subject_id' => $subject->id,
                            'is_primary' => true,
                            'is_active' => true,
                            'role' => 'homeroom'
                        ]);
                        echo "     📚 {$subjectName}\n";
                    }
                }
            }
        }
    }
    
    echo "\n5. FINAL SUMMARY...\n";
    
    // Count final assignments
    $totalAssignments = DB::table('teacher_section_subject')->count();
    $homeroomCount = DB::table('teacher_section_subject')->where('role', 'homeroom')->count();
    $specialistCount = DB::table('teacher_section_subject')->where('role', 'subject_teacher')->count();
    
    echo "   📊 Total teacher assignments: {$totalAssignments}\n";
    echo "   🏠 Homeroom assignments: {$homeroomCount}\n";
    echo "   🎯 Specialist assignments: {$specialistCount}\n";
    
    // Check coverage
    $sectionsWithHomeroom = DB::table('teacher_section_subject')
        ->where('role', 'homeroom')
        ->distinct('section_id')
        ->count();
    
    echo "   ✅ Sections with homeroom teachers: {$sectionsWithHomeroom}/14\n";
    
    $teachersWithAssignments = DB::table('teacher_section_subject')
        ->distinct('teacher_id')
        ->count();
    
    echo "   ✅ Teachers with assignments: {$teachersWithAssignments}/21\n";
    
    echo "\n=== SEEDING COMPLETE ===\n";
    echo "🎉 Naawan Central School is now fully staffed with realistic DepEd-compliant assignments!\n\n";
    
    echo "STRUCTURE:\n";
    echo "- Kindergarten (3 sections): Homeroom teachers handle all subjects\n";
    echo "- Grade 1-3 (6 sections): Homeroom teachers handle all subjects\n";
    echo "- Grade 4-6 (5 sections): Homeroom + Subject specialists\n";
    echo "- Total: 839 students across 14 sections\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
