<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TeacherAuditImporter
{
    private const NON_SCHOOL_SHEETS = ['Parameters', 'Summary', '% to SY 2024-2025'];

    public function __construct(private XlsxWorkbookReader $reader)
    {
    }

    public function import(string $path): int
    {
        $sheets = $this->reader->read($path);
        $educationLevel = $this->educationLevel($path, $sheets);

        return DB::transaction(function () use ($path, $sheets, $educationLevel) {
            $importIds = DB::table('audit_imports')
                ->where('education_level', $educationLevel)
                ->pluck('id');
            $sheetIds = DB::table('audit_sheets')
                ->whereIn('audit_import_id', $importIds)
                ->pluck('id');

            DB::table('audit_rows')->whereIn('audit_sheet_id', $sheetIds)->delete();
            DB::table('audit_sheets')->whereIn('audit_import_id', $importIds)->delete();
            DB::table('school_grade_audits')->where('education_level', $educationLevel)->delete();
            DB::table('audit_imports')->whereIn('id', $importIds)->delete();

            $importId = DB::table('audit_imports')->insertGetId([
                'file_name' => basename($path),
                'school_year' => '2025-2026',
                'education_level' => $educationLevel,
                'sheet_count' => count($sheets),
                'row_count' => collect($sheets)->sum(fn ($sheet) => count($sheet['rows'])),
                'imported_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($sheets as $sheet) {
                $sheetId = DB::table('audit_sheets')->insertGetId([
                    'audit_import_id' => $importId,
                    'name' => $sheet['name'],
                    'position' => $sheet['position'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($sheet['rows'] as $row) {
                    DB::table('audit_rows')->insert([
                        'audit_sheet_id' => $sheetId,
                        'row_number' => $row['number'],
                        'cells' => json_encode($row['cells']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if (! in_array($sheet['name'], self::NON_SCHOOL_SHEETS, true)) {
                    if ($educationLevel === 'High School') {
                        $this->importSecondarySchoolRows($importId, $sheet['name'], $sheet['rows']);
                    } else {
                        $this->importElementarySchoolRows($importId, $sheet['name'], $sheet['rows']);
                    }
                }
            }

            return $importId;
        });
    }

    private function importElementarySchoolRows(int $importId, string $schoolCode, array $rows): void
    {
        $grade = 1;

        foreach ($rows as $row) {
            $cells = $row['cells'];
            $maleLearners = $this->number($cells['B'] ?? null);
            $femaleLearners = $this->number($cells['C'] ?? null);
            $splitTotal = $this->number($cells['D'] ?? null);
            $splitSections = $this->number($cells['E'] ?? null);
            $hasSplitEnrollment = ($maleLearners > 0 || $femaleLearners > 0 || $splitTotal > 0) && $splitSections > 0;

            if ($hasSplitEnrollment) {
                $learners = $splitTotal > 0 ? $splitTotal : $maleLearners + $femaleLearners;
                $sections = $splitSections;
                $classSize = $this->number($cells['G'] ?? null);
                $availableTeachers = $this->number($cells['H'] ?? null);
                $requiredTeachers = $this->number($cells['I'] ?? null);
                $surplus = $this->number($cells['J'] ?? null);
                $shortage = $this->number($cells['K'] ?? null);
            } else {
                $maleLearners = 0;
                $femaleLearners = 0;
                $learners = $this->number($cells['C'] ?? null);
                $sections = $this->number($cells['D'] ?? null);
                $classSize = $this->number($cells['E'] ?? null);
                $availableTeachers = $this->number($cells['G'] ?? null);
                $requiredTeachers = $this->number($cells['H'] ?? null);
                $surplus = $this->number($cells['I'] ?? null);
                $shortage = $this->number($cells['J'] ?? null);
            }

            if ($row['number'] < 10 || (! $hasSplitEnrollment && isset($cells['B'])) || $learners <= 0 || $sections <= 0) {
                continue;
            }

            DB::table('school_grade_audits')->insert([
                'audit_import_id' => $importId,
                'school_code' => Str::upper($schoolCode),
                'education_level' => 'Elementary',
                'grade_level' => $grade++,
                'male_learners' => (int) round($maleLearners),
                'female_learners' => (int) round($femaleLearners),
                'actual_classrooms' => 0,
                'learners' => (int) round($learners),
                'sections' => (int) round($sections),
                'class_size' => round($classSize, 2),
                'required_teachers' => (int) round($requiredTeachers),
                'available_teachers' => (int) round($availableTeachers),
                'surplus' => (int) round($surplus),
                'shortage' => (int) round($shortage),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($grade > 8) {
                break;
            }
        }
    }

    private function importSecondarySchoolRows(int $importId, string $schoolCode, array $rows): void
    {
        $gradeLevels = [
            'grade 7' => 7,
            'grade 8' => 8,
            'grade 9' => 9,
            'grade 10' => 10,
            'grade 11' => 11,
            'grade 12' => 12,
            'sned' => 13,
        ];

        foreach ($rows as $row) {
            $cells = $row['cells'];
            $label = Str::lower(trim((string) ($cells['A'] ?? '')));

            if ($label === 'gtotal') {
                break;
            }

            if (! isset($gradeLevels[$label])) {
                continue;
            }

            $learners = $this->number($cells['C'] ?? null);
            $sections = $this->number($cells['D'] ?? null);

            if ($learners <= 0 || $sections <= 0) {
                continue;
            }

            DB::table('school_grade_audits')->insert([
                'audit_import_id' => $importId,
                'school_code' => Str::upper($schoolCode),
                'education_level' => 'High School',
                'grade_level' => $gradeLevels[$label],
                'male_learners' => 0,
                'female_learners' => 0,
                'actual_classrooms' => (int) round($this->number($cells['B'] ?? null)),
                'learners' => (int) round($learners),
                'sections' => (int) round($sections),
                'class_size' => round($this->number($cells['E'] ?? null), 2),
                'required_teachers' => (int) round($this->number($cells['H'] ?? null)),
                'available_teachers' => (int) round($this->number($cells['G'] ?? null)),
                'surplus' => (int) round($this->number($cells['I'] ?? null)),
                'shortage' => (int) round($this->number($cells['J'] ?? null)),
                'remarks' => blank($cells['K'] ?? null) ? null : trim((string) $cells['K']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function educationLevel(string $path, array $sheets): string
    {
        if (Str::contains(Str::lower(basename($path)), ['secondary', 'high school'])) {
            return 'High School';
        }

        foreach ($sheets as $sheet) {
            foreach ($sheet['rows'] as $row) {
                if (Str::lower(trim((string) ($row['cells']['A'] ?? ''))) === 'grade 7') {
                    return 'High School';
                }
            }
        }

        return 'Elementary';
    }

    private function number(mixed $value): float
    {
        if ($value === null || $value === '' || str_starts_with((string) $value, '#')) {
            return 0;
        }

        return (float) str_replace(',', '', (string) $value);
    }
}
