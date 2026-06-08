<x-layouts.app title="School Audit">
    <div class="topbar">
        <div>
            <h1>School Audit</h1>
            <p>Review grade-level enrollment, sections, class size, and teacher shortage.</p>
        </div>
    </div>

    <form class="filters" method="GET" action="{{ route('schools') }}">
        <label class="filter-field">
            <span>Basic Education</span>
            <select name="basic_education" aria-label="Basic Education">
                <option value="Elementary" @selected($basicEducation === 'Elementary')>Elementary</option>
            </select>
        </label>
        <label class="filter-field">
            <span>School Year</span>
            <select name="school_year" aria-label="School Year">
                <option value="{{ $schoolYear }}" selected>{{ $schoolYear }}</option>
            </select>
        </label>
        <label class="filter-field wide">
            <span>School</span>
            <select name="school" aria-label="School">
                @foreach ($schoolOptions as $school)
                    <option value="{{ $school['code'] }}" @selected($selectedSchool === $school['code'])>
                        {{ $school['name'] }} ({{ $school['code'] }})
                    </option>
                @endforeach
            </select>
        </label>
        <button class="button" type="submit">View School</button>
    </form>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="notice error">{{ $errors->first() }}</div>
    @endif

    <section class="summary-strip">
        <div class="mini-stat">
            <span>Total Enrolled</span>
            <strong>{{ number_format($summary->learners ?? 0) }}</strong>
        </div>
        <div class="mini-stat">
            <span>Sections</span>
            <strong>{{ number_format($summary->sections ?? 0) }}</strong>
        </div>
        <div class="mini-stat">
            <span>Required Teachers</span>
            <strong>{{ number_format($summary->required_teachers ?? 0) }}</strong>
        </div>
        <div class="mini-stat">
            <span>Available Teachers</span>
            <strong>{{ number_format($summary->available_teachers ?? 0) }}</strong>
        </div>
        <div class="mini-stat">
            <span>Need Teachers</span>
            <strong>{{ number_format($summary->shortage ?? 0) }}</strong>
        </div>
    </section>

    <div class="card">
        <div class="card-title" style="padding:18px 18px 0">
            <h2>{{ $selectedSchoolName }} <span class="muted">({{ $selectedSchool }})</span></h2>
            <span class="muted">SY {{ $schoolYear }} - {{ $rows->count() }} grade levels - computed from Parameters</span>
        </div>
        <form method="POST" action="{{ route('schools.update', $selectedSchool) }}">
            @csrf
            @method('PUT')
            <div class="table-wrap">
                <table class="school-audit-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Grade</th>
                            <th class="num" colspan="3">Enrollment</th>
                            <th class="num" rowspan="2">Actual Classes Organized</th>
                            <th class="num" rowspan="2">Classes to be Organized</th>
                            <th class="num" rowspan="2">Average Class Size</th>
                            <th class="num" rowspan="2">Actual No. of Teachers</th>
                            <th class="num" rowspan="2">Required No. of Teachers</th>
                            <th class="num" rowspan="2">Need Teachers</th>
                        </tr>
                        <tr>
                            <th class="num">Male</th>
                            <th class="num">Female</th>
                            <th class="num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td><strong>{{ $row->grade_label }}</strong></td>
                                <td class="num">
                                    <input class="editable enrollment-input" type="number" min="0" name="rows[{{ $row->id }}][male_learners]" value="{{ old("rows.$row->id.male_learners", $row->male_learners ?: '') }}">
                                </td>
                                <td class="num">
                                    <input class="editable enrollment-input" type="number" min="0" name="rows[{{ $row->id }}][female_learners]" value="{{ old("rows.$row->id.female_learners", $row->female_learners ?: '') }}">
                                </td>
                                <td class="num">
                                    <input class="editable enrollment-input" type="number" min="0" name="rows[{{ $row->id }}][learners]" value="{{ old("rows.$row->id.learners", $row->learners) }}">
                                </td>
                                <td class="num">
                                    <input class="editable" type="number" min="1" name="rows[{{ $row->id }}][sections]" value="{{ old("rows.$row->id.sections", $row->sections) }}">
                                </td>
                                <td class="num computed-value">{{ number_format($row->classes_to_organize) }}</td>
                                <td class="num computed-value">{{ number_format($row->class_size, 2) }}</td>
                                <td class="num">
                                    <input class="editable" type="number" min="0" name="rows[{{ $row->id }}][available_teachers]" value="{{ old("rows.$row->id.available_teachers", $row->available_teachers) }}">
                                </td>
                                <td class="num computed-value">{{ number_format($row->required_teachers) }}</td>
                                <td class="num"><span class="badge {{ $row->shortage > 0 ? 'danger' : '' }}">{{ number_format($row->shortage) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="10">No school audit records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; padding:16px 18px 18px">
                <a class="button secondary" href="{{ route('schools', ['school' => $selectedSchool]) }}">Cancel</a>
                <button class="button" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</x-layouts.app>
