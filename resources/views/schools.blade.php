<x-layouts.app title="School Audit">
    <div class="topbar">
        <div>
            <h1>School Audit</h1>
            <p>Review grade-level enrollment, sections, class size, and teacher excess/shortage.</p>
        </div>
    </div>

    <form class="filters" data-school-filter-form>
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
            <select name="school" aria-label="School" data-school-select>
                @foreach ($schoolOptions as $school)
                    <option value="{{ $school['code'] }}" @selected($selectedSchool === $school['code'])>
                        {{ $school['name'] }} ({{ $school['code'] }})
                    </option>
                @endforeach
            </select>
        </label>
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
            <strong data-summary-value="learners">{{ number_format($summary->learners ?? 0) }}</strong>
        </div>
        <div class="mini-stat">
            <span>Sections</span>
            <strong data-summary-value="sections">{{ number_format($summary->sections ?? 0) }}</strong>
        </div>
        <div class="mini-stat">
            <span>Required Teachers</span>
            <strong data-summary-value="required_teachers">{{ number_format($summary->required_teachers ?? 0) }}</strong>
        </div>
        <div class="mini-stat">
            <span>Available Teachers</span>
            <strong data-summary-value="available_teachers">{{ number_format($summary->available_teachers ?? 0) }}</strong>
        </div>
        <div class="mini-stat">
            <span>Excess/Shortage</span>
            <strong data-summary-value="excess_shortage">{{ number_format($summary->excess_shortage ?? 0) }}</strong>
        </div>
    </section>

    <div data-school-panels>
        @foreach ($schoolAudits as $audit)
            <div class="card school-panel" data-school-panel="{{ $audit->code }}" @if ($selectedSchool !== $audit->code) hidden @endif>
                <div class="card-title" style="padding:18px 18px 0">
                    <h2>{{ $audit->name }} <span class="muted">({{ $audit->code }})</span></h2>
                    <span class="muted">SY {{ $schoolYear }} - {{ $audit->rows->count() }} grade levels - computed from Parameters</span>
                </div>
                <form method="POST" action="{{ route('schools.update', $audit->code) }}" data-school-form>
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
                                    <th class="num" rowspan="2">Excess/Shortage</th>
                                </tr>
                                <tr>
                                    <th class="num">Male</th>
                                    <th class="num">Female</th>
                                    <th class="num">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($audit->rows as $row)
                                    <tr
                                        data-audit-row
                                        data-section-divisor="{{ $row->section_divisor }}"
                                        data-teacher-factor="{{ $row->teacher_factor }}"
                                    >
                                        <td><strong>{{ $row->grade_label }}</strong></td>
                                        <td class="num">
                                            <input class="editable enrollment-input" data-role="male" type="number" min="0" name="rows[{{ $row->id }}][male_learners]" value="{{ old("rows.$row->id.male_learners", $row->male_learners ?: '') }}">
                                        </td>
                                        <td class="num">
                                            <input class="editable enrollment-input" data-role="female" type="number" min="0" name="rows[{{ $row->id }}][female_learners]" value="{{ old("rows.$row->id.female_learners", $row->female_learners ?: '') }}">
                                        </td>
                                        <td class="num">
                                            <input class="editable enrollment-input computed-input" data-role="total" type="number" min="0" name="rows[{{ $row->id }}][learners]" value="{{ old("rows.$row->id.learners", $row->learners) }}" readonly>
                                        </td>
                                        <td class="num">
                                            <input class="editable" data-role="sections" type="number" min="1" name="rows[{{ $row->id }}][sections]" value="{{ old("rows.$row->id.sections", $row->sections) }}">
                                        </td>
                                        <td class="num computed-value" data-role="classes_to_organize">{{ number_format($row->classes_to_organize) }}</td>
                                        <td class="num computed-value" data-role="class_size">{{ number_format($row->class_size, 2) }}</td>
                                        <td class="num">
                                            <input class="editable" data-role="available_teachers" type="number" min="0" name="rows[{{ $row->id }}][available_teachers]" value="{{ old("rows.$row->id.available_teachers", $row->available_teachers) }}">
                                        </td>
                                        <td class="num computed-value" data-role="required_teachers">{{ number_format($row->required_teachers) }}</td>
                                        <td class="num"><span class="badge {{ $row->excess_shortage < 0 ? 'danger' : 'ok' }}" data-role="excess_shortage">{{ number_format($row->excess_shortage) }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10">No school audit records found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div style="display:flex; justify-content:flex-end; gap:10px; padding:16px 18px 18px">
                        <button class="button secondary" type="reset">Cancel</button>
                        <button class="button" type="submit">Save Changes</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>

    <script>
        (() => {
            const formatter = new Intl.NumberFormat('en-US');
            const decimalFormatter = new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
            const schoolSelect = document.querySelector('[data-school-select]');
            const panels = [...document.querySelectorAll('[data-school-panel]')];
            const summaryValues = {
                learners: document.querySelector('[data-summary-value="learners"]'),
                sections: document.querySelector('[data-summary-value="sections"]'),
                required_teachers: document.querySelector('[data-summary-value="required_teachers"]'),
                available_teachers: document.querySelector('[data-summary-value="available_teachers"]'),
                excess_shortage: document.querySelector('[data-summary-value="excess_shortage"]'),
            };

            const numberValue = (input) => Number.parseInt(input?.value || '0', 10) || 0;
            const showNumber = (value) => formatter.format(value);
            const showDecimal = (value) => decimalFormatter.format(value);

            const updateBadge = (badge, excessShortage) => {
                if (!badge) {
                    return;
                }

                badge.textContent = showNumber(excessShortage);
                badge.classList.toggle('danger', excessShortage < 0);
                badge.classList.toggle('ok', excessShortage >= 0);
            };

            const recalculateRow = (row) => {
                const maleInput = row.querySelector('[data-role="male"]');
                const femaleInput = row.querySelector('[data-role="female"]');
                const totalInput = row.querySelector('[data-role="total"]');
                const sectionsInput = row.querySelector('[data-role="sections"]');
                const availableInput = row.querySelector('[data-role="available_teachers"]');
                const male = numberValue(maleInput);
                const female = numberValue(femaleInput);
                const splitTotal = male + female;
                const total = splitTotal > 0 || document.activeElement === maleInput || document.activeElement === femaleInput
                    ? splitTotal
                    : numberValue(totalInput);
                const sections = Math.max(1, numberValue(sectionsInput));
                const divisor = Math.max(1, Number.parseFloat(row.dataset.sectionDivisor || '1') || 1);
                const factor = Math.max(0, Number.parseFloat(row.dataset.teacherFactor || '1') || 0);
                const classesToOrganize = total > 0 ? Math.ceil(total / divisor) : 0;
                const classSize = sections > 0 ? total / sections : 0;
                const requiredTeachers = classesToOrganize > 0 ? Math.ceil(classesToOrganize * factor) : 0;
                const availableTeachers = numberValue(availableInput);
                const excessShortage = availableTeachers - requiredTeachers;

                totalInput.value = total;
                row.querySelector('[data-role="classes_to_organize"]').textContent = showNumber(classesToOrganize);
                row.querySelector('[data-role="class_size"]').textContent = showDecimal(classSize);
                row.querySelector('[data-role="required_teachers"]').textContent = showNumber(requiredTeachers);
                updateBadge(row.querySelector('[data-role="excess_shortage"]'), excessShortage);

                return {
                    learners: total,
                    sections,
                    required_teachers: requiredTeachers,
                    available_teachers: availableTeachers,
                    excess_shortage: excessShortage,
                };
            };

            const recalculatePanel = (panel) => {
                const totals = {
                    learners: 0,
                    sections: 0,
                    required_teachers: 0,
                    available_teachers: 0,
                    excess_shortage: 0,
                };

                panel.querySelectorAll('[data-audit-row]').forEach((row) => {
                    const rowTotals = recalculateRow(row);
                    Object.keys(totals).forEach((key) => {
                        totals[key] += rowTotals[key];
                    });
                });

                Object.entries(summaryValues).forEach(([key, element]) => {
                    if (element) {
                        element.textContent = showNumber(totals[key]);
                    }
                });
            };

            const currentPanel = () => panels.find((panel) => !panel.hidden);

            const showSchool = (schoolCode) => {
                panels.forEach((panel) => {
                    panel.hidden = panel.dataset.schoolPanel !== schoolCode;
                });

                const panel = currentPanel();
                if (panel) {
                    recalculatePanel(panel);
                    window.history.replaceState({}, '', `${window.location.pathname}?school=${encodeURIComponent(schoolCode)}`);
                }
            };

            document.querySelector('[data-school-filter-form]')?.addEventListener('submit', (event) => {
                event.preventDefault();
            });

            schoolSelect?.addEventListener('change', () => {
                showSchool(schoolSelect.value);
            });

            panels.forEach((panel) => {
                panel.addEventListener('input', (event) => {
                    if (event.target.matches('input')) {
                        recalculatePanel(panel);
                    }
                });

                panel.addEventListener('reset', () => {
                    window.setTimeout(() => recalculatePanel(panel));
                });
            });

            if (schoolSelect) {
                showSchool(schoolSelect.value);
            }
        })();
    </script>
</x-layouts.app>
