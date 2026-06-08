<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuditDashboardController extends Controller
{
    private const GRADE_LABELS = [
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

    public function index(Request $request): View
    {
        $import = DB::table('audit_imports')->latest('imported_at')->first();
        $auditRows = $this->auditRows();
        $gradeColumns = $this->gradeColumns();
        $schools = $auditRows
            ->groupBy('school_code')
            ->map(fn (Collection $rows, string $schoolCode) => $this->schoolAggregate($schoolCode, $rows))
            ->values();
        $totals = $this->totalsFromSchools($schools);
        $schoolYear = $request->query('school_year', $import->school_year ?? '2025-2026');
        $basicEducation = $request->query('basic_education', 'Elementary');

        return view('dashboard', compact('import', 'totals', 'schools', 'gradeColumns', 'schoolYear', 'basicEducation'));
    }

    public function schools(Request $request): View
    {
        $selectedSchool = $request->query('school');
        $schoolOptions = DB::table('school_grade_audits')
            ->distinct()
            ->orderBy('school_code')
            ->pluck('school_code')
            ->map(fn ($code) => [
                'code' => $code,
                'name' => $this->schoolName($code),
            ]);

        if (! $selectedSchool && $schoolOptions->isNotEmpty()) {
            $selectedSchool = $schoolOptions->first()['code'];
        }

        $rows = DB::table('school_grade_audits')
            ->when($selectedSchool, fn ($query) => $query->where('school_code', $selectedSchool))
            ->orderBy('grade_level')
            ->get()
            ->map(fn ($row) => $this->withComputedAuditValues($row));

        $selectedSchoolName = $this->schoolName($selectedSchool);
        $summary = $this->totalsFromRows($rows);
        $schoolYear = DB::table('audit_imports')->latest('imported_at')->value('school_year') ?? '2025-2026';
        $basicEducation = 'Elementary';

        return view('schools', compact('schoolOptions', 'selectedSchool', 'selectedSchoolName', 'rows', 'summary', 'schoolYear', 'basicEducation'));
    }

    public function updateSchool(Request $request, string $school): RedirectResponse
    {
        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.male_learners' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'rows.*.female_learners' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'rows.*.learners' => ['required', 'integer', 'min:0', 'max:99999'],
            'rows.*.sections' => ['required', 'integer', 'min:1', 'max:999'],
            'rows.*.available_teachers' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        DB::transaction(function () use ($validated, $school) {
            foreach ($validated['rows'] as $id => $data) {
                $maleLearners = (int) ($data['male_learners'] ?? 0);
                $femaleLearners = (int) ($data['female_learners'] ?? 0);
                $learners = (int) $data['learners'];
                $sections = max(1, (int) $data['sections']);
                $availableTeachers = (int) $data['available_teachers'];
                $auditRow = DB::table('school_grade_audits')
                    ->where('id', $id)
                    ->where('school_code', $school)
                    ->first();

                if (! $auditRow) {
                    continue;
                }

                $classesToOrganize = $this->classesToOrganize($learners, (int) $auditRow->grade_level);
                $classSize = $sections > 0 ? round($learners / $sections, 2) : 0;
                $requiredTeachers = $this->requiredTeachers($classesToOrganize, (int) $auditRow->grade_level);
                $difference = $requiredTeachers - $availableTeachers;

                DB::table('school_grade_audits')
                    ->where('id', $id)
                    ->where('school_code', $school)
                    ->update([
                        'male_learners' => $maleLearners,
                        'female_learners' => $femaleLearners,
                        'learners' => $learners,
                        'sections' => $sections,
                        'class_size' => $classSize,
                        'required_teachers' => $requiredTeachers,
                        'available_teachers' => $availableTeachers,
                        'surplus' => max(-$difference, 0),
                        'shortage' => max($difference, 0),
                        'updated_at' => now(),
                    ]);
            }
        });

        return redirect()
            ->route('schools', ['school' => $school])
            ->with('status', 'School audit updated. Classes, required teachers, and need teachers were recalculated.');
    }

    private function schoolName(?string $code): string
    {
        if (! $code) {
            return '';
        }

        return config('audit_schools.'.$code, $code);
    }

    private function auditRows(): Collection
    {
        return DB::table('school_grade_audits')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => $this->withComputedAuditValues($row));
    }

    private function gradeColumns(): Collection
    {
        return collect(self::GRADE_LABELS)
            ->map(fn (string $label, int $level) => [
                'level' => $level,
                'label' => $label,
            ])
            ->values();
    }

    private function schoolAggregate(string $schoolCode, Collection $rows): object
    {
        $gradesByLevel = $rows->keyBy('grade_level');
        $grades = [];

        foreach (array_keys(self::GRADE_LABELS) as $gradeLevel) {
            $grades[$gradeLevel] = $gradesByLevel->get($gradeLevel) ?? $this->emptyGradeRow($gradeLevel);
        }

        $totals = $this->totalsFromRows($rows);
        $totals->school_code = $schoolCode;
        $totals->school_name = $this->schoolName($schoolCode);
        $totals->grades = $grades;

        return $totals;
    }

    private function totalsFromSchools(Collection $schools): object
    {
        return (object) [
            'schools' => $schools->count(),
            'male_learners' => $schools->sum('male_learners'),
            'female_learners' => $schools->sum('female_learners'),
            'learners' => $schools->sum('learners'),
            'sections' => $schools->sum('sections'),
            'classes_to_organize' => $schools->sum('classes_to_organize'),
            'required_teachers' => $schools->sum('required_teachers'),
            'available_teachers' => $schools->sum('available_teachers'),
            'shortage' => $schools->sum('shortage'),
            'surplus' => $schools->sum('surplus'),
            'class_size' => $schools->sum('sections') > 0
                ? round($schools->sum('learners') / $schools->sum('sections'), 2)
                : 0,
        ];
    }

    private function totalsFromRows(Collection $rows): object
    {
        return (object) [
            'male_learners' => $rows->sum('male_learners'),
            'female_learners' => $rows->sum('female_learners'),
            'learners' => $rows->sum('learners'),
            'sections' => $rows->sum('sections'),
            'classes_to_organize' => $rows->sum('classes_to_organize'),
            'required_teachers' => $rows->sum('required_teachers'),
            'available_teachers' => $rows->sum('available_teachers'),
            'shortage' => $rows->sum('shortage'),
            'surplus' => $rows->sum('surplus'),
            'class_size' => $rows->sum('sections') > 0
                ? round($rows->sum('learners') / $rows->sum('sections'), 2)
                : 0,
        ];
    }

    private function withComputedAuditValues(object $row): object
    {
        $row->grade_label = self::GRADE_LABELS[(int) $row->grade_level] ?? 'Grade '.$row->grade_level;
        $row->male_learners = (int) ($row->male_learners ?? 0);
        $row->female_learners = (int) ($row->female_learners ?? 0);
        $row->classes_to_organize = $this->classesToOrganize((int) $row->learners, (int) $row->grade_level);
        $row->class_size = (int) $row->sections > 0 ? round((int) $row->learners / (int) $row->sections, 2) : 0;
        $row->required_teachers = $this->requiredTeachers((int) $row->classes_to_organize, (int) $row->grade_level);
        $difference = (int) $row->required_teachers - (int) $row->available_teachers;
        $row->surplus = max(-$difference, 0);
        $row->shortage = max($difference, 0);

        return $row;
    }

    private function emptyGradeRow(int $gradeLevel): object
    {
        return (object) [
            'grade_level' => $gradeLevel,
            'grade_label' => self::GRADE_LABELS[$gradeLevel] ?? 'Grade '.$gradeLevel,
            'male_learners' => 0,
            'female_learners' => 0,
            'learners' => 0,
            'sections' => 0,
            'classes_to_organize' => 0,
            'class_size' => 0,
            'required_teachers' => 0,
            'available_teachers' => 0,
            'surplus' => 0,
            'shortage' => 0,
        ];
    }

    private function classesToOrganize(int $learners, int $gradeLevel): int
    {
        if ($learners <= 0) {
            return 0;
        }

        $rule = $this->teacherRule($gradeLevel);

        return (int) ceil($learners / max($rule['section_divisor'], 1));
    }

    private function requiredTeachers(int $classesToOrganize, int $gradeLevel): int
    {
        if ($classesToOrganize <= 0) {
            return 0;
        }

        $rule = $this->teacherRule($gradeLevel);

        return (int) ceil($classesToOrganize * $rule['teacher_factor']);
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
