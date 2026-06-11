<?php

namespace App\Console\Commands;

use App\Services\TeacherAuditImporter;
use Illuminate\Console\Command;

class ImportTeacherAudit extends Command
{
    protected $signature = 'audit:import {path=C:\Users\Lenovo\Downloads\Elementary School Teacher Audit-SY-2025-2026.xlsx}';

    protected $description = 'Import an elementary or secondary school teacher audit workbook.';

    public function handle(TeacherAuditImporter $importer): int
    {
        $path = $this->argument('path');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $importId = $importer->import($path);

        $this->info("Teacher audit imported as batch #{$importId}.");

        return self::SUCCESS;
    }
}
