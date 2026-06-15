<?php

namespace App\Console\Commands;

use App\Services\HighSchoolAuditRestorer;
use Illuminate\Console\Command;

class RestoreHighSchoolAudit extends Command
{
    protected $signature = 'audit:restore-high-school {--force : Replace existing High School audit rows}';

    protected $description = 'Restore High School audit rows from the bundled fixture.';

    public function handle(HighSchoolAuditRestorer $restorer): int
    {
        $existingRows = $restorer->existingRows();

        if ($existingRows > 0 && ! $this->option('force')) {
            $this->warn("High School audit rows already exist ({$existingRows}). Use --force to replace them.");

            return self::SUCCESS;
        }

        $restoredRows = $restorer->restore((bool) $this->option('force'));

        $this->info("Restored {$restoredRows} High School audit rows.");

        return self::SUCCESS;
    }
}
