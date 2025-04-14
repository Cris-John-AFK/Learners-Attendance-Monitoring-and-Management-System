<?php

namespace App\Services;

use App\Models\Section;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Curriculum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Exception;

class SectionService
{
    /**
     * Get sections for a specific grade and curriculum
     */
    public function getSections(int $gradeId, int $curriculumId): Collection
    {
        return Section::with(['teacher', 'subjects.teacher'])
            ->where('grade_id', $gradeId)
            ->where('curriculum_id', $curriculumId)
            ->get();
    }

    /**
     * Create a new section with subjects
     */
    public function createSection(array $data): Section
    {
        // Validate curriculum is active
        $curriculum = Curriculum::findOrFail($data['curriculum_id']);
        if (!$curriculum->is_active) {
            throw new Exception('Cannot create sections in an inactive curriculum');
        }

        DB::beginTransaction();
        try {
            // Create section
            $section = Section::create([
                'name' => $data['name'],
                'curriculum_id' => $data['curriculum_id'],
                'grade_id' => $data['grade_id'],
                'teacher_id' => $data['teacher_id'] ?? null,
                'room_number' => $data['room_number'],
                'max_students' => $data['max_students'],
                'is_active' => true
            ]);

            // Check if primary teacher is required
            $grade = Grade::findOrFail($data['grade_id']);
            $gradeCode = strtoupper($grade->code);
            $needsPrimaryTeacher = in_array($gradeCode, ['K1', 'K2', 'G1', 'G2', 'G3']);

            if ($needsPrimaryTeacher && !$data['teacher_id']) {
                throw new Exception('Primary teacher is required for this grade level');
            }

            // Attach subjects with default schedules
            if (!empty($data['subject_ids'])) {
                $defaultTimes = Section::getDefaultScheduleTimes();
                $timeIndex = 0;

                foreach ($data['subject_ids'] as $subjectId) {
                    if ($timeIndex >= count($defaultTimes)) {
                        $timeIndex = 0;
                    }

                    $time = $defaultTimes[$timeIndex];
                    $teacherId = $needsPrimaryTeacher ? $data['teacher_id'] : null;

                    $section->subjects()->attach($subjectId, [
                        'teacher_id' => $teacherId,
                        'schedule_start' => $time['start'],
                        'schedule_end' => $time['end'],
                        'day_of_week' => 'Monday',
                        'room_number' => $data['room_number']
                    ]);

                    $timeIndex++;
                }
            }

            DB::commit();
            return $section->load(['teacher', 'subjects.teacher']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update subject schedule in a section
     */
    public function updateSchedule(Section $section, Subject $subject, array $data): void
    {
        // Check for section schedule conflicts
        if ($section->hasScheduleConflict(
            $data['day_of_week'],
            $data['schedule_start'],
            $data['schedule_end'],
            $subject->id
        )) {
            throw new Exception('Schedule conflict detected for this section');
        }

        // Check for teacher schedule conflicts if teacher is assigned
        if (!empty($data['teacher_id'])) {
            $teacher = Teacher::findOrFail($data['teacher_id']);
            if ($teacher->hasScheduleConflict(
                $data['day_of_week'],
                $data['schedule_start'],
                $data['schedule_end'],
                $section->id,
                $subject->id
            )) {
                throw new Exception('Schedule conflict detected for the teacher');
            }
        }

        // Update the schedule
        $section->subjects()->updateExistingPivot($subject->id, [
            'schedule_start' => $data['schedule_start'],
            'schedule_end' => $data['schedule_end'],
            'day_of_week' => $data['day_of_week'],
            'room_number' => $data['room_number'],
            'teacher_id' => $data['teacher_id'] ?? null
        ]);
    }

    /**
     * Add a subject to a section
     */
    public function addSubject(Section $section, array $data): void
    {
        // Check if subject is already assigned
        if ($section->subjects()->where('subjects.id', $data['subject_id'])->exists()) {
            throw new Exception('Subject is already assigned to this section');
        }

        // Check for section schedule conflicts
        if ($section->hasScheduleConflict(
            $data['day_of_week'],
            $data['schedule_start'],
            $data['schedule_end']
        )) {
            throw new Exception('Schedule conflict detected for this section');
        }

        // Check for teacher schedule conflicts if teacher is assigned
        if (!empty($data['teacher_id'])) {
            $teacher = Teacher::findOrFail($data['teacher_id']);
            if ($teacher->hasScheduleConflict(
                $data['day_of_week'],
                $data['schedule_start'],
                $data['schedule_end'],
                $section->id
            )) {
                throw new Exception('Schedule conflict detected for the teacher');
            }
        }

        // Attach the subject
        $section->subjects()->attach($data['subject_id'], [
            'schedule_start' => $data['schedule_start'],
            'schedule_end' => $data['schedule_end'],
            'day_of_week' => $data['day_of_week'],
            'room_number' => $data['room_number'],
            'teacher_id' => $data['teacher_id'] ?? null
        ]);
    }

    /**
     * Remove a subject from a section
     */
    public function removeSubject(Section $section, Subject $subject): void
    {
        $section->subjects()->detach($subject->id);
    }

    /**
     * Get sections with schedule conflicts
     */
    public function getSectionsWithConflicts(): Collection
    {
        return Section::with(['subjects.teacher'])
            ->whereHas('subjects', function (Builder $query) {
                $query->whereRaw('EXISTS (
                    SELECT 1
                    FROM curriculum_subject_section css2
                    WHERE css2.section_id = sections.id
                    AND css2.id != curriculum_subject_section.id
                    AND css2.day_of_week = curriculum_subject_section.day_of_week
                    AND (
                        (css2.schedule_start BETWEEN curriculum_subject_section.schedule_start AND curriculum_subject_section.schedule_end)
                        OR (css2.schedule_end BETWEEN curriculum_subject_section.schedule_start AND curriculum_subject_section.schedule_end)
                        OR (curriculum_subject_section.schedule_start BETWEEN css2.schedule_start AND css2.schedule_end)
                    )
                )');
            })
            ->get();
    }

    /**
     * Get teachers with schedule conflicts
     */
    public function getTeachersWithConflicts(): Collection
    {
        return Teacher::with(['sections.subjects'])
            ->whereHas('sections', function (Builder $query) {
                $query->whereHas('subjects', function (Builder $subQuery) {
                    $subQuery->whereRaw('EXISTS (
                        SELECT 1
                        FROM curriculum_subject_section css2
                        WHERE css2.teacher_id = curriculum_subject_section.teacher_id
                        AND css2.id != curriculum_subject_section.id
                        AND css2.day_of_week = curriculum_subject_section.day_of_week
                        AND (
                            (css2.schedule_start BETWEEN curriculum_subject_section.schedule_start AND curriculum_subject_section.schedule_end)
                            OR (css2.schedule_end BETWEEN curriculum_subject_section.schedule_start AND curriculum_subject_section.schedule_end)
                            OR (curriculum_subject_section.schedule_start BETWEEN css2.schedule_start AND css2.schedule_end)
                        )
                    )');
                });
            })
            ->get();
    }
}
