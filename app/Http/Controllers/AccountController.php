<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $schools = collect(config('audit_schools'));
        $accounts = User::query()
            ->where('role', 'school')
            ->orderBy('school_code')
            ->get();

        return view('accounts.index', compact('accounts', 'schools'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        User::create([
            ...$validated,
            'role' => 'school',
        ]);

        return redirect()->route('accounts.index')->with('status', 'School account created.');
    }

    public function update(Request $request, User $account): RedirectResponse
    {
        abort_unless($account->isSchool(), 404);

        $validated = $request->validate($this->rules($account));

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $account->update($validated);

        return redirect()->route('accounts.index')->with('status', 'School account updated.');
    }

    public function destroy(User $account): RedirectResponse
    {
        abort_unless($account->isSchool(), 404);

        $account->delete();

        return redirect()->route('accounts.index')->with('status', 'School account deleted.');
    }

    private function rules(?User $account = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($account),
            ],
            'school_code' => [
                'required',
                Rule::in(array_keys(config('audit_schools'))),
                Rule::unique('users', 'school_code')->ignore($account),
            ],
            'password' => [
                $account ? 'nullable' : 'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ];
    }
}
