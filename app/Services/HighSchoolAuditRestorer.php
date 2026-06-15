<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class HighSchoolAuditRestorer
{
    public function restore(bool $force = false): int
    {
        $existingRows = DB::table('school_grade_audits')
            ->where('education_level', 'High School')
            ->count();

        if ($existingRows > 0 && ! $force) {
            return 0;
        }

        $data = $this->fixtureData();
        $rows = $data['school_grade_audits'] ?? [];

        DB::transaction(function () use ($data, $rows): void {
            DB::table('school_grade_audits')
                ->where('education_level', 'High School')
                ->delete();

            DB::table('audit_imports')
                ->where('education_level', 'High School')
                ->delete();

            $importId = DB::table('audit_imports')->insertGetId($data['audit_import']);

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('school_grade_audits')->insert(array_map(
                    fn (array $row) => $row + ['audit_import_id' => $importId],
                    $chunk,
                ));
            }
        });

        return count($rows);
    }

    public function existingRows(): int
    {
        return DB::table('school_grade_audits')
            ->where('education_level', 'High School')
            ->count();
    }

    private function fixtureData(): array
    {
        $fixturePath = database_path('seeders/data/high_school_audit_seed.json');

        if (! is_file($fixturePath)) {
            throw new RuntimeException("High School audit fixture not found: {$fixturePath}");
        }

        return json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
    }
}
