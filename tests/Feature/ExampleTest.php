<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                        'learners' => 170,
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

    public function test_surplus_is_recalculated_from_teacher_requirement_and_actual_teachers(): void
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
}
