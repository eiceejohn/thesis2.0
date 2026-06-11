<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\TeacherAuditImporter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@deped.gov.ph');

        User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'SDO Marikina ICTU',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'role' => 'admin',
                'school_code' => null,
            ],
        );

        $this->seedSchoolAccounts();
        $this->seedAuditData();
    }

    private function seedSchoolAccounts(): void
    {
        $schools = collect(config('audit_schools'))
            ->merge(config('audit_secondary_schools'));

        foreach ($schools as $schoolCode => $schoolName) {
            User::firstOrCreate(
                ['school_code' => $schoolCode],
                [
                    'name' => $schoolName,
                    'email' => strtolower($schoolCode).'@deped.gov.ph',
                    'password' => Hash::make(env('SCHOOL_DEFAULT_PASSWORD', 'password')),
                    'role' => 'school',
                ],
            );
        }
    }

    private function seedAuditData(): void
    {
        if (DB::table('school_grade_audits')->exists()) {
            return;
        }

        $fixturePath = database_path('seeders/data/teacher_audit_seed.json');

        if (is_file($fixturePath)) {
            $data = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);

            DB::transaction(function () use ($data) {
                DB::table('audit_rows')->delete();
                DB::table('audit_sheets')->delete();
                DB::table('school_grade_audits')->delete();
                DB::table('audit_imports')->delete();

                DB::table('audit_imports')->insert($data['audit_imports']);
                DB::table('school_grade_audits')->insert($data['school_grade_audits']);

                if (! DB::table('audit_parameters')->exists()) {
                    DB::table('audit_parameters')->insert($data['audit_parameters']);
                }
            });

            return;
        }

        $path = env('TEACHER_AUDIT_WORKBOOK', 'C:\Users\Lenovo\Downloads\Elementary School Teacher Audit-SY-2025-2026.xlsx');

        if (is_file($path)) {
            app(TeacherAuditImporter::class)->import($path);
        }
    }
}
