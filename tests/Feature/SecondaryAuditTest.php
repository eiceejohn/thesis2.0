<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeacherAuditCalculator;
use App\Services\TeacherAuditImporter;
use App\Services\XlsxWorkbookReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SecondaryAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_secondary_import_preserves_elementary_data(): void
    {
        $elementaryImport = DB::table('audit_imports')->insertGetId([
            'file_name' => 'elementary.xlsx',
            'school_year' => '2025-2026',
            'education_level' => 'Elementary',
            'sheet_count' => 1,
            'row_count' => 1,
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('school_grade_audits')->insert([
            'audit_import_id' => $elementaryImport,
            'school_code' => 'BES',
            'education_level' => 'Elementary',
            'grade_level' => 1,
            'learners' => 20,
            'sections' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reader = Mockery::mock(XlsxWorkbookReader::class);
        $reader->shouldReceive('read')->once()->andReturn([
            [
                'name' => 'Parameters',
                'position' => 1,
                'rows' => [],
            ],
            [
                'name' => 'BNHS',
                'position' => 2,
                'rows' => [
                    ['number' => 9, 'cells' => [
                        'A' => 'Grade 7',
                        'B' => '4',
                        'C' => '195',
                        'D' => '5',
                        'E' => '39',
                        'G' => '10',
                        'H' => '7',
                        'I' => '3',
                        'J' => '0',
                    ]],
                    ['number' => 18, 'cells' => ['A' => 'Gtotal']],
                ],
            ],
        ]);

        $importer = new TeacherAuditImporter($reader);
        $importer->import('Secondary School Teacher Audit-SY-2025-2026.xlsx');

        $this->assertDatabaseHas('school_grade_audits', [
            'school_code' => 'BES',
            'education_level' => 'Elementary',
            'learners' => 20,
        ]);
        $this->assertDatabaseHas('school_grade_audits', [
            'school_code' => 'BNHS',
            'education_level' => 'High School',
            'grade_level' => 7,
            'actual_classrooms' => 4,
            'learners' => 195,
            'sections' => 5,
            'available_teachers' => 10,
            'required_teachers' => 7,
        ]);
    }

    public function test_high_school_dashboard_only_shows_secondary_schools(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $importId = DB::table('audit_imports')->insertGetId([
            'file_name' => 'secondary.xlsx',
            'school_year' => '2025-2026',
            'education_level' => 'High School',
            'sheet_count' => 1,
            'row_count' => 1,
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('school_grade_audits')->insert([
            'audit_import_id' => $importId,
            'school_code' => 'BNHS',
            'education_level' => 'High School',
            'grade_level' => 7,
            'actual_classrooms' => 4,
            'learners' => 195,
            'sections' => 5,
            'available_teachers' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard', ['basic_education' => 'High School']))
            ->assertOk()
            ->assertSee('High School Teacher Audit')
            ->assertSee('Barangka National High School')
            ->assertDontSee('Barangka Elementary School');
    }

    public function test_secondary_teacher_rules_use_jhs_and_shs_parameters(): void
    {
        DB::table('audit_parameters')->insert([
            [
                'group_name' => 'Junior High School',
                'level' => 'Grade 7',
                'maximum' => '45',
                'teacher_factor' => 1.25,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group_name' => 'Senior High School',
                'level' => 'Grade 11',
                'maximum' => '40',
                'teacher_factor' => 1.5,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $calculator = app(TeacherAuditCalculator::class);

        $this->assertSame(5, $calculator->classesToOrganize(195, 7, 'High School'));
        $this->assertSame(7, $calculator->requiredTeachers(5, 7, 'High School'));
        $this->assertSame(2, $calculator->classesToOrganize(80, 11, 'High School'));
        $this->assertSame(3, $calculator->requiredTeachers(2, 11, 'High School'));
    }

    public function test_high_school_school_audit_uses_enrollment_split_layout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $importId = DB::table('audit_imports')->insertGetId([
            'file_name' => 'secondary.xlsx',
            'school_year' => '2025-2026',
            'education_level' => 'High School',
            'sheet_count' => 1,
            'row_count' => 1,
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('school_grade_audits')->insert([
            'audit_import_id' => $importId,
            'school_code' => 'BNHS',
            'education_level' => 'High School',
            'grade_level' => 7,
            'actual_classrooms' => 4,
            'learners' => 195,
            'sections' => 5,
            'available_teachers' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('schools', ['basic_education' => 'High School']))
            ->assertOk()
            ->assertSee('Enrollment')
            ->assertSee('Male')
            ->assertSee('Female')
            ->assertSee('Total')
            ->assertSee('class="spacer-cell"', false)
            ->assertSee('Actual Classes Organized')
            ->assertDontSee('Remarks');
    }
}
