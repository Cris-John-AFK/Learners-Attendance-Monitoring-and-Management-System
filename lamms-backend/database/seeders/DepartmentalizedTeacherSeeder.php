<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepartmentalizedTeacherSeeder extends Seeder
{
    /**
     * Seed departmentalized teacher assignments for Grade 4-6
     * 
     * Grade 4-6 use subject specialists:
     * - Each section has a homeroom teacher (is_primary=true, subject_id=NULL)
     * - Subject specialist teachers teach their subject across multiple sections (is_primary=false)
     */
    public function run(): void
    {
        Log::info('🎓 Starting Departmentalized Teacher Assignment Seeder for Grade 4-6');
        
        // Grade 4-6 Subject IDs (from G4-6 prefix)
        $grade4to6Subjects = [
            'Filipino' => 16,
            'English' => 17,
            'Mathematics' => 18,
            'Science' => 19,
            'Araling Panlipunan' => 20,
            'MAPEH' => 21,
            'ESP' => 22, // Edukasyon sa Pagpapakatao
            'TLE' => 23  // Technology and Livelihood Education
        ];
        
        // Get Grade 4-6 sections (use DISTINCT to avoid duplicates)
        $grade4Sections = DB::table('sections')
            ->join('curriculum_grade', 'sections.curriculum_grade_id', '=', 'curriculum_grade.id')
            ->join('grades', 'curriculum_grade.grade_id', '=', 'grades.id')
            ->where('grades.level', 'Grade 4')
            ->select('sections.id', 'sections.name', 'grades.name as grade')
            ->groupBy('sections.id', 'sections.name', 'grades.name')
            ->get();
            
        $grade5Sections = DB::table('sections')
            ->join('curriculum_grade', 'sections.curriculum_grade_id', '=', 'curriculum_grade.id')
            ->join('grades', 'curriculum_grade.grade_id', '=', 'grades.id')
            ->where('grades.level', 'Grade 5')
            ->select('sections.id', 'sections.name', 'grades.name as grade')
            ->groupBy('sections.id', 'sections.name', 'grades.name')
            ->get();
            
        $grade6Sections = DB::table('sections')
            ->join('curriculum_grade', 'sections.curriculum_grade_id', '=', 'curriculum_grade.id')
            ->join('grades', 'curriculum_grade.grade_id', '=', 'grades.id')
            ->where('grades.level', 'Grade 6')
            ->select('sections.id', 'sections.name', 'grades.name as grade')
            ->groupBy('sections.id', 'sections.name', 'grades.name')
            ->get();
        
        Log::info("Found sections", [
            'grade4' => $grade4Sections->count(),
            'grade5' => $grade5Sections->count(),
            'grade6' => $grade6Sections->count()
        ]);
        
        // Assign subject specialist teachers
        // We'll use teachers 3-20 as specialists (teacher 1 and 2 already have assignments)
        
        $subjectTeachers = [
            'Filipino' => 3,      // Rosa Garcia
            'English' => 4,       // Carmen Reyes  
            'Mathematics' => 5,   // Elena Morales
            'Science' => 6,       // Roberto Dela Cruz
            'Araling Panlipunan' => 7, // Gloria Villanueva
            'MAPEH' => 8,         // Jose Ramos
            'ESP' => 9,           // Luz Fernandez
            'TLE' => 10           // Pedro Gonzales
        ];
        
        // Homeroom teachers for Grade 4-6 sections
        $homeroomTeachers = [
            'Grade 4' => [
                'Silang' => 11,      // Esperanza Torres
                'Dagohoy' => 12,     // Antonio Mendoza
            ],
            'Grade 5' => [
                'Tandang Sora' => 13, // Cristina Aquino
                'Gabriela' => 14,     // Miguel Rivera
            ],
            'Grade 6' => [
                'Lapu-Lapu' => 15,    // Teresita Bautista
                'Magat Salamat' => 16, // Ricardo Pascual
            ]
        ];
        
        DB::beginTransaction();
        
        try {
            // Clear existing Grade 4-6 assignments
            $grade4to6SectionIds = $grade4Sections->pluck('id')
                ->merge($grade5Sections->pluck('id'))
                ->merge($grade6Sections->pluck('id'))
                ->unique();
                
            DB::table('teacher_section_subject')
                ->whereIn('section_id', $grade4to6SectionIds)
                ->delete();
            
            Log::info("Cleared existing Grade 4-6 assignments");
            
            $assignmentsCreated = 0;
            
            // Process each grade level
            foreach (['Grade 4' => $grade4Sections, 'Grade 5' => $grade5Sections, 'Grade 6' => $grade6Sections] as $gradeLevel => $sections) {
                foreach ($sections as $section) {
                    $sectionName = $section->name;
                    
                    // 1. Assign homeroom teacher (is_primary=true, subject_id=NULL)
                    if (isset($homeroomTeachers[$gradeLevel][$sectionName])) {
                        $homeroomTeacherId = $homeroomTeachers[$gradeLevel][$sectionName];
                        
                        // Update section's homeroom_teacher_id
                        DB::table('sections')
                            ->where('id', $section->id)
                            ->update(['homeroom_teacher_id' => $homeroomTeacherId]);
                        
                        // Create homeroom assignment
                        DB::table('teacher_section_subject')->insert([
                            'teacher_id' => $homeroomTeacherId,
                            'section_id' => $section->id,
                            'subject_id' => null,
                            'is_primary' => true,
                            'is_active' => true,
                            'role' => 'homeroom'
                        ]);
                        
                        $assignmentsCreated++;
                        
                        Log::info("Assigned homeroom teacher", [
                            'grade' => $gradeLevel,
                            'section' => $sectionName,
                            'teacher_id' => $homeroomTeacherId
                        ]);
                    }
                    
                    // 2. Assign subject specialist teachers (is_primary=false)
                    foreach ($subjectTeachers as $subjectName => $teacherId) {
                        $subjectId = $grade4to6Subjects[$subjectName] ?? null;
                        
                        if ($subjectId) {
                            DB::table('teacher_section_subject')->insert([
                                'teacher_id' => $teacherId,
                                'section_id' => $section->id,
                                'subject_id' => $subjectId,
                                'is_primary' => false,
                                'is_active' => true,
                                'role' => 'subject_teacher'
                            ]);
                            
                            $assignmentsCreated++;
                        }
                    }
                    
                    Log::info("Assigned subject teachers for section", [
                        'grade' => $gradeLevel,
                        'section' => $sectionName,
                        'subjects_assigned' => count($subjectTeachers)
                    ]);
                }
            }
            
            DB::commit();
            
            Log::info("✅ Departmentalized teacher assignments completed", [
                'total_assignments' => $assignmentsCreated,
                'sections_processed' => $grade4to6SectionIds->count()
            ]);
            
            $this->command->info("✅ Created {$assignmentsCreated} departmentalized teacher assignments for Grade 4-6");
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Error creating departmentalized assignments: " . $e->getMessage());
            $this->command->error("Error: " . $e->getMessage());
            throw $e;
        }
    }
}
