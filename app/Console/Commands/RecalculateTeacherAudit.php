<?php

namespace App\Console\Commands;

use App\Services\TeacherAuditCalculator;
use Illuminate\Console\Command;

class RecalculateTeacherAudit extends Command
{
    protected $signature = 'audit:recalculate {--school= : Recalculate only one school code}';

    protected $description = 'Recalculate class size, required teachers, and excess/shortage fields.';

    public function handle(TeacherAuditCalculator $calculator): int
    {
        $count = $calculator->recalculateStoredRows($this->option('school'));

        $this->info("Recalculated {$count} teacher audit rows.");

        return self::SUCCESS;
    }
}
