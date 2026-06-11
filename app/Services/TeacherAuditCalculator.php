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

    public const SECONDARY_GRADE_LABELS = [
        7 => 'Grade 7',
        8 => 'Grade 8',
        9 => 'Grade 9',
        10 => 'Grade 10',
        11 => 'Grade 11',
        12 => 'Grade 12',
        13 => 'SNED',
    ];

    private const PARAMETER_LEVELS = [
        'Elementary' => [
            1 => 'Kindergarten',
            2 => 'Grade 1',
            3 => 'Grade 2',
            4 => 'Grade 3',
            5 => 'Grade 4',
            6 => 'Grade 5',
            7 => 'Grade 6',
            8 => 'Multigrade',
        ],
        'High School' => [
            7 => 'Grade 7',
            8 => 'Grade 8',
            9 => 'Grade 9',
            10 => 'Grade 10',
            11 => 'Grade 11',
            12 => 'Grade 12',
            13 => 'Elem/JHS',
        ],
    ];

    private array $teacherRules = [];

    public function gradeLabels(string $educationLevel = 'Elementary'): array
    {
        return $educationLevel === 'High School'
            ? self::SECONDARY_GRADE_LABELS
            : self::GRADE_LABELS;
    }

    public function classesToOrganize(int $learners, int $gradeLevel, string $educationLevel = 'Elementary'): int
    {
        if ($learners <= 0) {
            return 0;
        }

        $rule = $this->teacherRule($gradeLevel, $educationLevel);

        return (int) ceil($learners / max($rule['section_divisor'], 1));
    }

    public function requiredTeachers(int $classesToOrganize, int $gradeLevel, string $educationLevel = 'Elementary'): int
    {
        if ($classesToOrganize <= 0) {
            return 0;
        }

        $rule = $this->teacherRule($gradeLevel, $educationLevel);

        return (int) ceil($classesToOrganize * $rule['teacher_factor']);
    }

    public function withComputedValues(object $row): object
    {
        $gradeLevel = (int) $row->grade_level;
        $educationLevel = $row->education_level ?? 'Elementary';
        $learners = (int) $row->learners;
        $sections = max(1, (int) $row->sections);
        $availableTeachers = (int) $row->available_teachers;
        $rule = $this->teacherRule($gradeLevel, $educationLevel);
        $classesToOrganize = $this->classesToOrganize($learners, $gradeLevel, $educationLevel);
        $requiredTeachers = $this->requiredTeachers($classesToOrganize, $gradeLevel, $educationLevel);
        $excessShortage = $availableTeachers - $requiredTeachers;

        $row->grade_label = $this->gradeLabels($educationLevel)[$gradeLevel] ?? 'Grade '.$gradeLevel;
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

    private function teacherRule(int $gradeLevel, string $educationLevel): array
    {
        $cacheKey = $educationLevel.':'.$gradeLevel;

        if (isset($this->teacherRules[$cacheKey])) {
            return $this->teacherRules[$cacheKey];
        }

        $fallback = $educationLevel === 'High School'
            ? [
                'section_divisor' => $gradeLevel >= 11 && $gradeLevel <= 12 ? 40 : ($gradeLevel === 13 ? 15 : 45),
                'teacher_factor' => $gradeLevel >= 11 && $gradeLevel <= 12 ? 1.5 : ($gradeLevel === 13 ? 1.0 : 1.25),
            ]
            : config('audit_parameters.teacher_rules.'.$gradeLevel, [
                'section_divisor' => 1,
                'teacher_factor' => 1,
            ]);
        $level = self::PARAMETER_LEVELS[$educationLevel][$gradeLevel] ?? null;
        $parameter = $level
            ? DB::table('audit_parameters')->where('level', $level)->first()
            : null;

        return $this->teacherRules[$cacheKey] = [
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
