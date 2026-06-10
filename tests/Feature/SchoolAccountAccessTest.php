<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchoolAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_account_only_sees_its_assigned_school(): void
    {
        $schoolUser = User::factory()->create([
            'role' => 'school',
            'school_code' => 'BES',
        ]);

        $this->seedSchoolRows();

        $this->actingAs($schoolUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Barangka Elementary School')
            ->assertDontSee('Concepcion Elementary School');

        $this->actingAs($schoolUser)
            ->get(route('schools', ['school' => 'CES']))
            ->assertOk()
            ->assertSee('Barangka Elementary School')
            ->assertDontSee('Concepcion Elementary School');
    }

    public function test_school_account_cannot_open_admin_pages_or_update_another_school(): void
    {
        $schoolUser = User::factory()->create([
            'role' => 'school',
            'school_code' => 'BES',
        ]);

        $this->seedSchoolRows();

        $this->actingAs($schoolUser)->get(route('parameters'))->assertForbidden();
        $this->actingAs($schoolUser)->get(route('accounts.index'))->assertForbidden();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($schoolUser)
            ->put(route('schools.update', 'CES'), ['rows' => []])
            ->assertForbidden();
    }

    public function test_admin_can_open_account_management(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertSee('Account Management')
            ->assertSee('Create Account')
            ->assertSee('School Accounts');
    }

    public function test_admin_can_create_update_and_delete_a_school_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($admin)
            ->post(route('accounts.store'), [
                'name' => 'Barangka School Account',
                'email' => 'barangka@deped.gov.ph',
                'school_code' => 'BES',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('accounts.index'));

        $account = User::query()->where('school_code', 'BES')->firstOrFail();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($admin)
            ->put(route('accounts.update', $account), [
                'name' => 'Updated Barangka Account',
                'email' => 'bes@deped.gov.ph',
                'school_code' => 'BES',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('users', [
            'id' => $account->id,
            'name' => 'Updated Barangka Account',
            'email' => 'bes@deped.gov.ph',
            'role' => 'school',
            'school_code' => 'BES',
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($admin)
            ->delete(route('accounts.destroy', $account))
            ->assertRedirect(route('accounts.index'));

        $this->assertDatabaseMissing('users', ['id' => $account->id]);
    }

    private function seedSchoolRows(): void
    {
        $importId = DB::table('audit_imports')->insertGetId([
            'file_name' => 'test.xlsx',
            'school_year' => '2025-2026',
            'sheet_count' => 1,
            'row_count' => 2,
            'imported_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['BES', 'CES'] as $schoolCode) {
            DB::table('school_grade_audits')->insert([
                'audit_import_id' => $importId,
                'school_code' => $schoolCode,
                'grade_level' => 1,
                'male_learners' => 10,
                'female_learners' => 10,
                'learners' => 20,
                'sections' => 1,
                'class_size' => 20,
                'required_teachers' => 1,
                'available_teachers' => 1,
                'surplus' => 0,
                'shortage' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
