<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_the_login_page_returns_a_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_school_audit_updates_manual_fields_and_recalculates_computed_columns(): void
    {
        $user = User::factory()->create();
        $importId = DB::table('audit_imports')->insertGetId([
            'file_name' => 'test.xlsx',
            'school_year' => '2025-2026',
            'sheet_count' => 1,
            'row_count' => 1,
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rowId = DB::table('school_grade_audits')->insertGetId([
            'audit_import_id' => $importId,
            'school_code' => 'BES',
            'grade_level' => 2,
            'male_learners' => 0,
            'female_learners' => 0,
            'learners' => 100,
            'sections' => 4,
            'class_size' => 25,
            'required_teachers' => 4,
            'available_teachers' => 3,
            'surplus' => 0,
            'shortage' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('schools.update', 'BES'), [
                'rows' => [
                    $rowId => [
                        'male_learners' => 80,
                        'female_learners' => 90,
                        'learners' => 1,
                        'sections' => 4,
                        'available_teachers' => 3,
                    ],
                ],
            ])
            ->assertRedirect(route('schools', ['school' => 'BES']));

        $this->assertDatabaseHas('school_grade_audits', [
            'id' => $rowId,
            'male_learners' => 80,
            'female_learners' => 90,
            'learners' => 170,
            'class_size' => 42.5,
            'required_teachers' => 5,
            'available_teachers' => 3,
            'shortage' => 2,
            'surplus' => 0,
        ]);
    }

    public function test_school_audit_displays_signed_excess_shortage_formula(): void
    {
        $user = User::factory()->create();
        $importId = DB::table('audit_imports')->insertGetId([
            'file_name' => 'test.xlsx',
            'school_year' => '2025-2026',
            'sheet_count' => 1,
            'row_count' => 1,
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('school_grade_audits')->insert([
            'audit_import_id' => $importId,
            'school_code' => 'BES',
            'grade_level' => 2,
            'male_learners' => 80,
            'female_learners' => 90,
            'learners' => 170,
            'sections' => 4,
            'class_size' => 42.5,
            'required_teachers' => 5,
            'available_teachers' => 3,
            'surplus' => 0,
            'shortage' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('schools'))
            ->assertOk()
            ->assertSee('Excess/Shortage')
            ->assertSee('data-role="class_size">43</td>', false)
            ->assertSee('-2');
    }

    public function test_school_audit_shows_auto_switching_picker_without_view_school_button(): void
    {
        $user = User::factory()->create();
        $importId = DB::table('audit_imports')->insertGetId([
            'file_name' => 'test.xlsx',
            'school_year' => '2025-2026',
            'sheet_count' => 1,
            'row_count' => 1,
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('school_grade_audits')->insert([
            'audit_import_id' => $importId,
            'school_code' => 'BES',
            'grade_level' => 1,
            'male_learners' => 80,
            'female_learners' => 90,
            'learners' => 170,
            'sections' => 5,
            'class_size' => 34,
            'required_teachers' => 4,
            'available_teachers' => 3,
            'surplus' => 0,
            'shortage' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('schools'))
            ->assertOk()
            ->assertSee('data-school-select', false)
            ->assertSee('data-school-panel="BES"', false)
            ->assertSee('<th class="spacer-cell" rowspan="2" aria-hidden="true"></th>', false)
            ->assertSee('<td class="spacer-cell" aria-hidden="true"></td>', false)
            ->assertDontSee('View School');
    }

    public function test_excess_is_recalculated_from_actual_minus_required_teachers(): void
    {
        $user = User::factory()->create();
        $importId = DB::table('audit_imports')->insertGetId([
            'file_name' => 'test.xlsx',
            'school_year' => '2025-2026',
            'sheet_count' => 1,
            'row_count' => 1,
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rowId = DB::table('school_grade_audits')->insertGetId([
            'audit_import_id' => $importId,
            'school_code' => 'BES',
            'grade_level' => 4,
            'male_learners' => 0,
            'female_learners' => 0,
            'learners' => 207,
            'sections' => 6,
            'class_size' => 34.5,
            'required_teachers' => 8,
            'available_teachers' => 8,
            'surplus' => 0,
            'shortage' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('schools.update', 'BES'), [
                'rows' => [
                    $rowId => [
                        'learners' => 216,
                        'sections' => 6,
                        'available_teachers' => 11,
                    ],
                ],
            ])
            ->assertRedirect(route('schools', ['school' => 'BES']));

        $this->assertDatabaseHas('school_grade_audits', [
            'id' => $rowId,
            'class_size' => 36,
            'required_teachers' => 9,
            'available_teachers' => 11,
            'shortage' => 0,
            'surplus' => 2,
        ]);
    }

    public function test_recalculate_command_repairs_stale_teacher_audit_values(): void
    {
        $importId = DB::table('audit_imports')->insertGetId([
            'file_name' => 'test.xlsx',
            'school_year' => '2025-2026',
            'sheet_count' => 1,
            'row_count' => 1,
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rowId = DB::table('school_grade_audits')->insertGetId([
            'audit_import_id' => $importId,
            'school_code' => 'BES',
            'grade_level' => 2,
            'male_learners' => 80,
            'female_learners' => 90,
            'learners' => 170,
            'sections' => 4,
            'class_size' => 0,
            'required_teachers' => 99,
            'available_teachers' => 3,
            'surplus' => 99,
            'shortage' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('audit:recalculate'));

        $this->assertDatabaseHas('school_grade_audits', [
            'id' => $rowId,
            'class_size' => 42.5,
            'required_teachers' => 5,
            'available_teachers' => 3,
            'surplus' => 0,
            'shortage' => 2,
        ]);
    }
}
