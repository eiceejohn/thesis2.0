<?php

namespace App\Http\Controllers;

use App\Services\TeacherAuditCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuditDashboardController extends Controller
{
    public function __construct(private TeacherAuditCalculator $calculator)
    {
    }

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

        $rowsBySchool = DB::table('school_grade_audits')
            ->orderBy('school_code')
            ->orderBy('grade_level')
            ->get()
            ->map(fn ($row) => $this->withComputedAuditValues($row))
            ->groupBy('school_code');

        $schoolAudits = $schoolOptions->map(function (array $school) use ($rowsBySchool) {
            $rows = $rowsBySchool->get($school['code'], collect());

            return (object) [
                'code' => $school['code'],
                'name' => $school['name'],
                'rows' => $rows,
                'summary' => $this->totalsFromRows($rows),
            ];
        });

        $selectedAudit = $schoolAudits->first(fn ($audit) => $audit->code === $selectedSchool);
        $rows = $selectedAudit?->rows ?? collect();

        $selectedSchoolName = $this->schoolName($selectedSchool);
        $summary = $this->totalsFromRows($rows);
        $schoolYear = DB::table('audit_imports')->latest('imported_at')->value('school_year') ?? '2025-2026';
        $basicEducation = 'Elementary';

        return view('schools', compact('schoolOptions', 'schoolAudits', 'selectedSchool', 'selectedSchoolName', 'rows', 'summary', 'schoolYear', 'basicEducation'));
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
                $submittedLearners = (int) $data['learners'];
                $learners = $maleLearners + $femaleLearners > 0
                    ? $maleLearners + $femaleLearners
                    : $submittedLearners;
                $sections = max(1, (int) $data['sections']);
                $availableTeachers = (int) $data['available_teachers'];
                $auditRow = DB::table('school_grade_audits')
                    ->where('id', $id)
                    ->where('school_code', $school)
                    ->first();

                if (! $auditRow) {
                    continue;
                }

                $classesToOrganize = $this->calculator->classesToOrganize($learners, (int) $auditRow->grade_level);
                $classSize = $sections > 0 ? round($learners / $sections, 2) : 0;
                $requiredTeachers = $this->calculator->requiredTeachers($classesToOrganize, (int) $auditRow->grade_level);
                $excessShortage = $availableTeachers - $requiredTeachers;

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
                        'surplus' => max($excessShortage, 0),
                        'shortage' => max(-$excessShortage, 0),
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
        return collect($this->calculator->gradeLabels())
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

        foreach (array_keys($this->calculator->gradeLabels()) as $gradeLevel) {
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
            'excess_shortage' => $schools->sum('excess_shortage'),
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
            'excess_shortage' => $rows->sum('excess_shortage'),
            'shortage' => $rows->sum('shortage'),
            'surplus' => $rows->sum('surplus'),
            'class_size' => $rows->sum('sections') > 0
                ? round($rows->sum('learners') / $rows->sum('sections'), 2)
                : 0,
        ];
    }

    private function withComputedAuditValues(object $row): object
    {
        return $this->calculator->withComputedValues($row);
    }

    private function emptyGradeRow(int $gradeLevel): object
    {
        return (object) [
            'grade_level' => $gradeLevel,
            'grade_label' => $this->calculator->gradeLabels()[$gradeLevel] ?? 'Grade '.$gradeLevel,
            'male_learners' => 0,
            'female_learners' => 0,
            'learners' => 0,
            'sections' => 0,
            'classes_to_organize' => 0,
            'class_size' => 0,
            'required_teachers' => 0,
            'available_teachers' => 0,
            'excess_shortage' => 0,
            'surplus' => 0,
            'shortage' => 0,
        ];
    }

}
