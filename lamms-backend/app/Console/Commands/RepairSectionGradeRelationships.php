<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Grade;
use App\Models\Section;
use App\Models\CurriculumGrade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RepairSectionGradeRelationships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:repair-section-grade-relationships';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Repair relationships between sections and grades';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to repair section-grade relationships...');

        // Check if grade_id column exists in sections table
        if (!Schema::hasColumn('sections', 'grade_id')) {
            $this->warn('The grade_id column does not exist in the sections table.');
            $this->info('Using curriculum_grade_id instead and creating indirect relationships.');
            $this->repairUsingCurriculumGrade();
            return Command::SUCCESS;
        }

        // Step 1: Get all sections
        $sections = Section::all();
        $this->info("Found {$sections->count()} sections in total");

        $fixedCount = 0;

        foreach ($sections as $section) {
            $this->info("Examining section ID: {$section->id} Name: {$section->name}");

            // Check if it already has a valid grade_id
            if ($section->grade_id) {
                $grade = Grade::find($section->grade_id);
                if ($grade) {
                    $this->info("  - Section already has valid grade_id: {$section->grade_id}");
                    continue;
                } else {
                    $this->warn("  - Section has invalid grade_id: {$section->grade_id}");
                }
            }

            // Check if it has a curriculum_grade_id and try to use that
            if ($section->curriculum_grade_id) {
                $curriculumGrade = CurriculumGrade::find($section->curriculum_grade_id);
                if ($curriculumGrade && $curriculumGrade->grade_id) {
                    $section->grade_id = $curriculumGrade->grade_id;
                    $section->save();
                    $this->info("  - Fixed: Set grade_id to {$curriculumGrade->grade_id} from curriculum_grade");
                    $fixedCount++;
                    continue;
                }
            }

            // If we still don't have a grade_id, try to determine from the section name
            // Common pattern: "Kinder 1 - Section A" or "Grade 2 - Section B"
            if (preg_match('/Kinder\s*(\d+)|Grade\s*(\d+)/i', $section->name, $matches)) {
                $gradeLevel = !empty($matches[1]) ? 'K'.$matches[1] : $matches[2];

                // Find a matching grade
                $grade = Grade::where('name', 'like', "%{$gradeLevel}%")
                            ->orWhere('id', $gradeLevel)
                            ->orWhere('code', $gradeLevel)
                            ->first();

                if ($grade) {
                    $section->grade_id = $grade->id;
                    $section->save();
                    $this->info("  - Fixed: Set grade_id to {$grade->id} based on name pattern");
                    $fixedCount++;
                    continue;
                }
            }

            // Final attempt: check if this section is linked to any subjects shared with other sections
            $linkedSubjectIds = DB::table('section_subject')
                ->where('section_id', $section->id)
                ->pluck('subject_id');

            if ($linkedSubjectIds->count() > 0) {
                $otherSectionsWithSameSubjects = DB::table('section_subject')
                    ->whereIn('subject_id', $linkedSubjectIds)
                    ->where('section_id', '!=', $section->id)
                    ->pluck('section_id');

                if ($otherSectionsWithSameSubjects->count() > 0) {
                    $otherSection = Section::whereIn('id', $otherSectionsWithSameSubjects)
                        ->whereNotNull('grade_id')
                        ->first();

                    if ($otherSection) {
                        $section->grade_id = $otherSection->grade_id;
                        $section->save();
                        $this->info("  - Fixed: Set grade_id to {$otherSection->grade_id} based on shared subjects");
                        $fixedCount++;
                        continue;
                    }
                }
            }

            $this->warn("  - Could not determine grade for section {$section->id}");
        }

        // Create default grade-curriculum relationships if needed
        $this->info("Creating default curriculum-grade relationships if needed...");
        $curriculums = DB::table('curriculums')->get();
        $grades = Grade::all();

        $relationshipsCreated = 0;

        foreach ($curriculums as $curriculum) {
            foreach ($grades as $grade) {
                $exists = DB::table('curriculum_grade')
                    ->where('curriculum_id', $curriculum->id)
                    ->where('grade_id', $grade->id)
                    ->exists();

                if (!$exists) {
                    DB::table('curriculum_grade')->insert([
                        'curriculum_id' => $curriculum->id,
                        'grade_id' => $grade->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $relationshipsCreated++;
                }
            }
        }

        $this->info("Created {$relationshipsCreated} new curriculum-grade relationships");
        $this->info("Fixed {$fixedCount} sections out of {$sections->count()} total sections");

        return Command::SUCCESS;
    }

    /**
     * Alternative repair method using curriculum_grade relationships when grade_id doesn't exist
     */
    protected function repairUsingCurriculumGrade()
    {
        try {
            // Step 1: Get all sections
            $sections = Section::all();
            $this->info("Found {$sections->count()} sections in total");

            $fixedCount = 0;

            foreach ($sections as $section) {
                $this->info("Examining section ID: {$section->id} Name: {$section->name}");

                // The table doesn't have grade_id, so we focus on curriculum_grade_id
                if (!$section->curriculum_grade_id) {
                    $this->warn("  - Section has no curriculum_grade_id, attempting to find a matching grade");

                    // Try to determine from the section name
                    if (preg_match('/Kinder\s*(\d+)|Grade\s*(\d+)/i', $section->name, $matches)) {
                        $gradeLevel = !empty($matches[1]) ? 'K'.$matches[1] : $matches[2];

                        // Find a matching grade
                        $grade = Grade::where('name', 'like', "%{$gradeLevel}%")
                                    ->orWhere('id', $gradeLevel)
                                    ->orWhere('code', $gradeLevel)
                                    ->first();

                        if ($grade) {
                            // Find or create curriculum_grade relationship
                            $curriculumGrade = DB::table('curriculum_grade')
                                ->where('grade_id', $grade->id)
                                ->first();

                            if ($curriculumGrade) {
                                $section->curriculum_grade_id = $curriculumGrade->id;
                                $section->save();
                                $this->info("  - Fixed: Set curriculum_grade_id to {$curriculumGrade->id} based on name pattern");
                                $fixedCount++;
                                continue;
                            }
                        }
                    }

                    // If we still don't have a match, try to find any curriculum_grade to use
                    if (DB::table('curriculum_grade')->exists()) {
                        $curriculumGrade = DB::table('curriculum_grade')->first();
                        $section->curriculum_grade_id = $curriculumGrade->id;
                        $section->save();
                        $this->info("  - Assigned default curriculum_grade_id: {$curriculumGrade->id}");
                        $fixedCount++;
                    } else {
                        $this->warn("  - No curriculum_grade records found to assign to section");
                    }
                } else {
                    $this->info("  - Section already has curriculum_grade_id: {$section->curriculum_grade_id}");
                }
            }

            // Create default grade-curriculum relationships if needed
            $this->info("Creating curriculum-grade relationships if needed...");
            $curriculums = DB::table('curriculums')->get();
            $grades = Grade::all();

            $relationshipsCreated = 0;

            foreach ($curriculums as $curriculum) {
                foreach ($grades as $grade) {
                    $exists = DB::table('curriculum_grade')
                        ->where('curriculum_id', $curriculum->id)
                        ->where('grade_id', $grade->id)
                        ->exists();

                    if (!$exists) {
                        $newRelationId = DB::table('curriculum_grade')->insertGetId([
                            'curriculum_id' => $curriculum->id,
                            'grade_id' => $grade->id,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $relationshipsCreated++;

                        $this->info("  - Created curriculum-grade relationship: curriculum {$curriculum->id} - grade {$grade->id} - ID: {$newRelationId}");
                    }
                }
            }

            $this->info("Created {$relationshipsCreated} new curriculum-grade relationships");
            $this->info("Fixed {$fixedCount} sections out of {$sections->count()} total sections");

        } catch (\Exception $e) {
            $this->error("Error in repairUsingCurriculumGrade: " . $e->getMessage());
            Log::error("Error in repairUsingCurriculumGrade: " . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }
}
