<x-layouts.app title="Account Management">
    <div class="topbar">
        <div>
            <h1>Account Management</h1>
            <p>Create and manage school-level access. School accounts can only view their assigned school.</p>
        </div>
        <div class="pill">{{ $accounts->count() }} school accounts</div>
    </div>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="notice error">{{ $errors->first() }}</div>
    @endif

    <section class="card pad account-create">
        <div class="account-section-heading">
            <div>
                <span class="eyebrow">Access Control</span>
                <h2>Create School Account</h2>
                <p>Assign one school to each account. Passwords must contain at least 8 characters.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('accounts.store') }}">
            @csrf
            <div class="account-create-grid">
                <label class="filter-field">
                    <span>Account Name</span>
                    <input name="name" value="{{ old('name') }}" placeholder="School account name" required>
                </label>
                <label class="filter-field">
                    <span>DepEd Email</span>
                    <input name="email" type="email" value="{{ old('email') }}" placeholder="school@deped.gov.ph" required>
                </label>
                <label class="filter-field school-field">
                    <span>Assigned School</span>
                    <select name="school_code" required>
                        <option value="">Select school</option>
                        @foreach ($schools as $code => $name)
                            <option value="{{ $code }}" @selected(old('school_code') === $code)>
                                {{ $name }} ({{ $code }})
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="filter-field">
                    <span>Password</span>
                    <input name="password" type="password" required>
                </label>
                <label class="filter-field">
                    <span>Confirm Password</span>
                    <input name="password_confirmation" type="password" required>
                </label>
                <div class="account-create-actions">
                    <button class="button" type="submit">Create Account</button>
                </div>
            </div>
        </form>
    </section>

    <section class="card accounts-card">
        <div class="accounts-toolbar">
            <div>
                <h2>School Accounts</h2>
                <p>Use Edit to update details or reset a password.</p>
            </div>
            <label class="account-search">
                <span>Search accounts</span>
                <input type="search" placeholder="Search school or email" data-account-search>
            </label>
        </div>

        <div class="table-wrap">
            <table class="accounts-table">
                <thead>
                    <tr>
                        <th>School</th>
                        <th>Login Email</th>
                        <th>Access</th>
                        <th class="num">Actions</th>
                    </tr>
                </thead>
                <tbody data-account-list>
                    @forelse ($accounts as $account)
                        <tr data-account-row data-search="{{ strtolower($account->name.' '.$account->email.' '.$account->school_code.' '.($schools[$account->school_code] ?? '')) }}">
                            <td>
                                <div class="account-school">
                                    <strong>{{ $schools[$account->school_code] ?? $account->name }}</strong>
                                    <span>{{ $account->school_code }} - {{ $account->name }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="email-chip">{{ $account->email }}</span>
                            </td>
                            <td>
                                <span class="access-badge">School data only</span>
                            </td>
                            <td class="num">
                                <button class="button secondary table-action" type="button" data-account-edit>Edit</button>
                            </td>
                        </tr>
                        <tr class="account-edit-row" data-account-editor hidden>
                            <td colspan="4">
                                <form class="account-edit-panel" method="POST" action="{{ route('accounts.update', $account) }}">
                                    @csrf
                                    @method('PUT')
                                    <label class="filter-field">
                                        <span>Account Name</span>
                                        <input name="name" value="{{ $account->name }}" required>
                                    </label>
                                    <label class="filter-field">
                                        <span>DepEd Email</span>
                                        <input name="email" type="email" value="{{ $account->email }}" required>
                                    </label>
                                    <label class="filter-field">
                                        <span>Assigned School</span>
                                        <select name="school_code" required>
                                            @foreach ($schools as $code => $name)
                                                <option value="{{ $code }}" @selected($account->school_code === $code)>
                                                    {{ $name }} ({{ $code }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="filter-field">
                                        <span>New Password</span>
                                        <input name="password" type="password" placeholder="Leave blank to keep current">
                                    </label>
                                    <label class="filter-field">
                                        <span>Confirm Password</span>
                                        <input name="password_confirmation" type="password" placeholder="Leave blank to keep current">
                                    </label>
                                    <div class="account-edit-actions">
                                        <button class="button" type="submit">Save Changes</button>
                                    </div>
                                </form>
                                <form class="account-delete-form" method="POST" action="{{ route('accounts.destroy', $account) }}" onsubmit="return confirm('Delete this school account?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button danger-button" type="submit">Delete Account</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty-state">No school accounts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <script>
        (() => {
            const searchInput = document.querySelector('[data-account-search]');
            const accountRows = [...document.querySelectorAll('[data-account-row]')];

            document.querySelectorAll('[data-account-edit]').forEach((button) => {
                button.addEventListener('click', () => {
                    const row = button.closest('[data-account-row]');
                    const editor = row?.nextElementSibling;
                    const isOpen = editor && !editor.hidden;

                    document.querySelectorAll('[data-account-editor]').forEach((panel) => {
                        panel.hidden = true;
                    });
                    document.querySelectorAll('[data-account-edit]').forEach((editButton) => {
                        editButton.textContent = 'Edit';
                    });

                    if (editor) {
                        editor.hidden = isOpen;
                        button.textContent = isOpen ? 'Edit' : 'Close';
                    }
                });
            });

            searchInput?.addEventListener('input', () => {
                const query = searchInput.value.trim().toLowerCase();

                accountRows.forEach((row) => {
                    const editor = row.nextElementSibling;
                    const isMatch = row.dataset.search.includes(query);

                    row.hidden = !isMatch;
                    if (editor) {
                        editor.hidden = true;
                    }
                });

                document.querySelectorAll('[data-account-edit]').forEach((button) => {
                    button.textContent = 'Edit';
                });
            });
        })();
    </script>
</x-layouts.app>
