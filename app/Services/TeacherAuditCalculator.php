<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TeacherAuditCalculator
{
    public const GRADE_LABELS = [
        1 => 'Kinder',
        2 => 'Grade 1',
        3 => 'Grade 2',
        4 => 'Grade 3',
        5 => 'Grade 4',
        6 => 'Grade 5',
        7 => 'Grade 6',
        8 => 'SNED',
    ];

    private const PARAMETER_LEVELS = [
        1 => 'Kindergarten',
        2 => 'Grade 1',
        3 => 'Grade 2',
        4 => 'Grade 3',
        5 => 'Grade 4',
        6 => 'Grade 5',
        7 => 'Grade 6',
        8 => 'Multigrade',
    ];

    private array $teacherRules = [];

    public function gradeLabels(): array
    {
        return self::GRADE_LABELS;
    }

    public function classesToOrganize(int $learners, int $gradeLevel): int
    {
        if ($learners <= 0) {
            return 0;
        }

        $rule = $this->teacherRule($gradeLevel);

        return (int) ceil($learners / max($rule['section_divisor'], 1));
    }

    public function requiredTeachers(int $classesToOrganize, int $gradeLevel): int
    {
        if ($classesToOrganize <= 0) {
            return 0;
        }

        $rule = $this->teacherRule($gradeLevel);

        return (int) ceil($classesToOrganize * $rule['teacher_factor']);
    }

    public function withComputedValues(object $row): object
    {
        $gradeLevel = (int) $row->grade_level;
        $learners = (int) $row->learners;
        $sections = max(1, (int) $row->sections);
        $availableTeachers = (int) $row->available_teachers;
        $rule = $this->teacherRule($gradeLevel);
        $classesToOrganize = $this->classesToOrganize($learners, $gradeLevel);
        $requiredTeachers = $this->requiredTeachers($classesToOrganize, $gradeLevel);
        $excessShortage = $availableTeachers - $requiredTeachers;

        $row->grade_label = self::GRADE_LABELS[$gradeLevel] ?? 'Grade '.$gradeLevel;
        $row->male_learners = (int) ($row->male_learners ?? 0);
        $row->female_learners = (int) ($row->female_learners ?? 0);
        $row->section_divisor = $rule['section_divisor'];
        $row->teacher_factor = $rule['teacher_factor'];
        $row->classes_to_organize = $classesToOrganize;
        $row->class_size = round($learners / $sections, 2);
        $row->required_teachers = $requiredTeachers;
        $row->excess_shortage = $excessShortage;
        $row->surplus = max($excessShortage, 0);
        $row->shortage = max(-$excessShortage, 0);

        return $row;
    }

    public function recalculateStoredRows(?string $school = null): int
    {
        $rows = DB::table('school_grade_audits')
            ->when($school, fn ($query) => $query->where('school_code', $school))
            ->get();

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $computed = $this->withComputedValues($row);

                DB::table('school_grade_audits')
                    ->where('id', $row->id)
                    ->update([
                        'class_size' => $computed->class_size,
                        'required_teachers' => $computed->required_teachers,
                        'surplus' => $computed->surplus,
                        'shortage' => $computed->shortage,
                        'updated_at' => now(),
                    ]);
            }
        });

        return $rows->count();
    }

    private function teacherRule(int $gradeLevel): array
    {
        if (isset($this->teacherRules[$gradeLevel])) {
            return $this->teacherRules[$gradeLevel];
        }

        $fallback = config('audit_parameters.teacher_rules.'.$gradeLevel, [
            'section_divisor' => 1,
            'teacher_factor' => 1,
        ]);
        $level = self::PARAMETER_LEVELS[$gradeLevel] ?? null;
        $parameter = $level
            ? DB::table('audit_parameters')->where('level', $level)->first()
            : null;

        return $this->teacherRules[$gradeLevel] = [
            'section_divisor' => $this->positiveNumber($parameter->maximum ?? $fallback['section_divisor']),
            'teacher_factor' => $this->positiveNumber($parameter->teacher_factor ?? $fallback['teacher_factor']),
        ];
    }

    private function positiveNumber(mixed $value): float
    {
        $number = (float) str_replace(',', '', (string) $value);

        return $number > 0 ? $number : 1;
    }
}
