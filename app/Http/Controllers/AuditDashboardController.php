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
        $basicEducation = $this->educationLevel($request);
        $import = DB::table('audit_imports')
            ->where('education_level', $basicEducation)
            ->latest('imported_at')
            ->first();
        $auditRows = $this->auditRows($request->user()->school_code, $basicEducation);
        $gradeColumns = $this->gradeColumns($basicEducation);
        $schools = $auditRows
            ->groupBy('school_code')
            ->map(fn (Collection $rows, string $schoolCode) => $this->schoolAggregate($schoolCode, $rows, $basicEducation))
            ->values();
        $totals = $this->totalsFromSchools($schools);
        $schoolYear = $request->query('school_year', $import->school_year ?? '2025-2026');

        return view('dashboard', compact('import', 'totals', 'schools', 'gradeColumns', 'schoolYear', 'basicEducation'));
    }

    public function schools(Request $request): View
    {
        $assignedSchool = $request->user()->school_code;
        $basicEducation = $this->educationLevel($request);
        $selectedSchool = $assignedSchool ?: $request->query('school');
        $schoolOptions = DB::table('school_grade_audits')
            ->where('education_level', $basicEducation)
            ->when($assignedSchool, fn ($query) => $query->where('school_code', $assignedSchool))
            ->distinct()
            ->orderBy('school_code')
            ->pluck('school_code')
            ->map(fn ($code) => [
                'code' => $code,
                'name' => $this->schoolName($code),
            ]);

        if (! $schoolOptions->contains(fn ($school) => $school['code'] === $selectedSchool)) {
            $selectedSchool = $schoolOptions->first()['code'] ?? null;
        }

        $rowsBySchool = DB::table('school_grade_audits')
            ->where('education_level', $basicEducation)
            ->when($assignedSchool, fn ($query) => $query->where('school_code', $assignedSchool))
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
        $schoolYear = DB::table('audit_imports')
            ->where('education_level', $basicEducation)
            ->latest('imported_at')
            ->value('school_year') ?? '2025-2026';

        return view('schools', compact('schoolOptions', 'schoolAudits', 'selectedSchool', 'selectedSchoolName', 'rows', 'summary', 'schoolYear', 'basicEducation'));
    }

    public function updateSchool(Request $request, string $school): RedirectResponse
    {
        if ($request->user()->isSchool()) {
            abort_unless($request->user()->school_code === $school, 403);
        }

        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.male_learners' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'rows.*.female_learners' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'rows.*.learners' => ['required', 'integer', 'min:0', 'max:99999'],
            'rows.*.actual_classrooms' => ['nullable', 'integer', 'min:0', 'max:999'],
            'rows.*.sections' => ['required', 'integer', 'min:1', 'max:999'],
            'rows.*.available_teachers' => ['required', 'integer', 'min:0', 'max:999'],
            'rows.*.remarks' => ['nullable', 'string', 'max:1000'],
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
                $actualClassrooms = (int) ($data['actual_classrooms'] ?? 0);
                $auditRow = DB::table('school_grade_audits')
                    ->where('id', $id)
                    ->where('school_code', $school)
                    ->first();

                if (! $auditRow) {
                    continue;
                }

                $educationLevel = $auditRow->education_level ?? 'Elementary';
                $classesToOrganize = $this->calculator->classesToOrganize($learners, (int) $auditRow->grade_level, $educationLevel);
                $classSize = $sections > 0 ? round($learners / $sections, 2) : 0;
                $requiredTeachers = $this->calculator->requiredTeachers($classesToOrganize, (int) $auditRow->grade_level, $educationLevel);
                $excessShortage = $availableTeachers - $requiredTeachers;

                DB::table('school_grade_audits')
                    ->where('id', $id)
                    ->where('school_code', $school)
                    ->update([
                        'male_learners' => $maleLearners,
                        'female_learners' => $femaleLearners,
                        'actual_classrooms' => $actualClassrooms,
                        'learners' => $learners,
                        'sections' => $sections,
                        'class_size' => $classSize,
                        'required_teachers' => $requiredTeachers,
                        'available_teachers' => $availableTeachers,
                        'surplus' => max($excessShortage, 0),
                        'shortage' => max(-$excessShortage, 0),
                        'remarks' => blank($data['remarks'] ?? null) ? null : trim($data['remarks']),
                        'updated_at' => now(),
                    ]);
            }
        });

        $educationLevel = $this->educationLevelForSchool($school);
        $routeParameters = ['school' => $school];

        if ($educationLevel === 'High School') {
            $routeParameters['basic_education'] = $educationLevel;
        }

        return redirect()
            ->route('schools', $routeParameters)
            ->with('status', 'School audit updated. Classes, required teachers, and need teachers were recalculated.');
    }

    private function schoolName(?string $code): string
    {
        if (! $code) {
            return '';
        }

        return config('audit_schools.'.$code)
            ?? config('audit_secondary_schools.'.$code)
            ?? $code;
    }

    private function auditRows(?string $schoolCode, string $educationLevel): Collection
    {
        return DB::table('school_grade_audits')
            ->where('education_level', $educationLevel)
            ->when($schoolCode, fn ($query) => $query->where('school_code', $schoolCode))
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => $this->withComputedAuditValues($row));
    }

    private function gradeColumns(string $educationLevel): Collection
    {
        return collect($this->calculator->gradeLabels($educationLevel))
            ->map(fn (string $label, int $level) => [
                'level' => $level,
                'label' => $label,
            ])
            ->values();
    }

    private function schoolAggregate(string $schoolCode, Collection $rows, string $educationLevel): object
    {
        $gradesByLevel = $rows->keyBy('grade_level');
        $grades = [];

        foreach (array_keys($this->calculator->gradeLabels($educationLevel)) as $gradeLevel) {
            $grades[$gradeLevel] = $gradesByLevel->get($gradeLevel) ?? $this->emptyGradeRow($gradeLevel, $educationLevel);
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
            'actual_classrooms' => $schools->sum('actual_classrooms'),
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
            'actual_classrooms' => $rows->sum('actual_classrooms'),
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

    private function emptyGradeRow(int $gradeLevel, string $educationLevel): object
    {
        return (object) [
            'grade_level' => $gradeLevel,
            'education_level' => $educationLevel,
            'grade_label' => $this->calculator->gradeLabels($educationLevel)[$gradeLevel] ?? 'Grade '.$gradeLevel,
            'male_learners' => 0,
            'female_learners' => 0,
            'actual_classrooms' => 0,
            'learners' => 0,
            'sections' => 0,
            'classes_to_organize' => 0,
            'class_size' => 0,
            'required_teachers' => 0,
            'available_teachers' => 0,
            'excess_shortage' => 0,
            'surplus' => 0,
            'shortage' => 0,
            'remarks' => null,
        ];
    }

    private function educationLevel(Request $request): string
    {
        if ($request->user()->school_code) {
            return $this->educationLevelForSchool($request->user()->school_code);
        }

        return $request->query('basic_education') === 'High School'
            ? 'High School'
            : 'Elementary';
    }

    private function educationLevelForSchool(string $schoolCode): string
    {
        return array_key_exists($schoolCode, config('audit_secondary_schools'))
            ? 'High School'
            : 'Elementary';
    }

}
