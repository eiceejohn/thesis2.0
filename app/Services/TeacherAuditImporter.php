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

        return DB::transaction(function () use ($path, $sheets) {
            DB::table('school_grade_audits')->delete();
            DB::table('audit_rows')->delete();
            DB::table('audit_sheets')->delete();
            DB::table('audit_imports')->delete();

            $importId = DB::table('audit_imports')->insertGetId([
                'file_name' => basename($path),
                'school_year' => '2025-2026',
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
                    $this->importSchoolRows($importId, $sheet['name'], $sheet['rows']);
                }
            }

            return $importId;
        });
    }

    private function importSchoolRows(int $importId, string $schoolCode, array $rows): void
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
                'grade_level' => $grade++,
                'male_learners' => (int) round($maleLearners),
                'female_learners' => (int) round($femaleLearners),
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

    private function number(mixed $value): float
    {
        if ($value === null || $value === '' || str_starts_with((string) $value, '#')) {
            return 0;
        }

        return (float) str_replace(',', '', (string) $value);
    }
}
