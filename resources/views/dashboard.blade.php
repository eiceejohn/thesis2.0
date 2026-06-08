<x-layouts.app title="Dashboard">
    <div class="topbar">
        <div>
            <h1>Elementary Teacher Audit</h1>
            <p>SY {{ $import->school_year ?? '2025-2026' }} staffing overview from the uploaded workbook.</p>
        </div>
        <div class="pill">
            {{ $import ? 'Imported '.$import->imported_at : 'No workbook imported yet' }}
        </div>
    </div>

    <form class="filters" method="GET" action="{{ route('dashboard') }}">
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
    </form>

    <section class="grid stats">
        <div class="card pad stat">
            <div class="label">Schools</div>
            <div class="value">{{ number_format($totals->schools ?? 0) }}</div>
            <div class="hint">Elementary campuses</div>
        </div>
        <div class="card pad stat">
            <div class="label">Learners</div>
            <div class="value">{{ number_format($totals->learners ?? 0) }}</div>
            <div class="hint">Total enrollment</div>
        </div>
        <div class="card pad stat">
            <div class="label">Sections</div>
            <div class="value">{{ number_format($totals->sections ?? 0) }}</div>
            <div class="hint">Across grade levels</div>
        </div>
        <div class="card pad stat">
            <div class="label">Teacher Gap</div>
            <div class="value">{{ number_format(($totals->shortage ?? 0) - ($totals->surplus ?? 0)) }}</div>
            <div class="hint">Shortage minus surplus</div>
        </div>
    </section>

    <section class="card">
        <div class="card-title" style="padding:18px 18px 0">
            <h2>School Summary</h2>
            <span class="muted">{{ $schools->count() }} records</span>
        </div>
        <div class="table-wrap">
            <table class="dashboard-summary">
                <thead>
                    <tr>
                        <th class="school-cell" rowspan="2">SCHOOL</th>
                        @foreach ($gradeColumns as $grade)
                            <th colspan="3">{{ str_replace('Grade ', 'Grade', $grade['label']) }}</th>
                        @endforeach
                        <th rowspan="2">Actual Classes Organized</th>
                        <th rowspan="2">Classes to be Organized</th>
                        <th rowspan="2">Average Class Size</th>
                        <th rowspan="2">Actual No. Of Teachers</th>
                        <th rowspan="2">Required No. Of Teachers</th>
                        <th rowspan="2">Need Teachers</th>
                    </tr>
                    <tr>
                        @foreach ($gradeColumns as $grade)
                            <th>Male</th>
                            <th>Female</th>
                            <th>Total</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schools as $school)
                        <tr>
                            <td class="school-cell">
                                <strong>{{ $school->school_name }}</strong>
                                <div class="muted">{{ $school->school_code }}</div>
                            </td>
                            @foreach ($gradeColumns as $grade)
                                @php($gradeRow = $school->grades[$grade['level']])
                                <td class="num">{{ $gradeRow->male_learners ? number_format($gradeRow->male_learners) : '' }}</td>
                                <td class="num">{{ $gradeRow->female_learners ? number_format($gradeRow->female_learners) : '' }}</td>
                                <td class="num">{{ $gradeRow->learners ? number_format($gradeRow->learners) : '-' }}</td>
                            @endforeach
                            <td class="num">{{ number_format($school->sections) }}</td>
                            <td class="num">{{ number_format($school->classes_to_organize) }}</td>
                            <td class="num">{{ number_format($school->class_size, 2) }}</td>
                            <td class="num">{{ number_format($school->available_teachers) }}</td>
                            <td class="num">{{ number_format($school->required_teachers) }}</td>
                            <td class="num">
                                <span class="badge {{ $school->shortage > 0 ? 'danger' : 'ok' }}">
                                    {{ number_format($school->shortage) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 7 + ($gradeColumns->count() * 3) }}">
                                No audit data yet. Run <strong>php artisan audit:import</strong>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
