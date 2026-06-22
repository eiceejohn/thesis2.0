<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Teacher Audit System' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <style>
        :root {
            --blue: #1f5fbd;
            --blue-dark: #16499a;
            --blue-soft: #edf5ff;
            --red: #d8242f;
            --gold: #f4b51b;
            --ink: #27364a;
            --muted: #6d7785;
            --line: #dfe5ec;
            --bg: #f4f7fb;
            --white: #ffffff;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--ink);
            background: var(--bg);
        }

        a { color: inherit; text-decoration: none; }
        button, input, select { font: inherit; }

        .shell { min-height: 100vh; display: grid; grid-template-columns: 260px 1fr; }
        .sidebar { background: var(--blue); color: var(--white); padding: 28px 22px; }
        .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 34px; }
        .seal {
            width: 46px; height: 46px; border-radius: 50%;
            display: grid; place-items: center;
            background: #f7cf39; color: #102d63; font-weight: 800;
            border: 3px solid rgba(255,255,255,.75); font-size: 13px;
        }
        .brand strong { display: block; font-size: 15px; line-height: 1.2; }
        .brand span { display: block; color: rgba(255,255,255,.74); font-size: 12px; margin-top: 3px; }
        .nav { display: grid; gap: 8px; }
        .nav a, .logout {
            width: 100%; display: flex; align-items: center; gap: 10px;
            padding: 11px 12px; border-radius: 8px; color: rgba(255,255,255,.82);
            background: transparent; border: 0; cursor: pointer; text-align: left;
        }
        .nav a.active, .nav a:hover, .logout:hover { background: rgba(255,255,255,.14); color: var(--white); }
        .nav-user {
            margin: 0 4px 14px; padding-bottom: 14px; border-bottom: 1px solid rgba(255,255,255,.18);
            color: rgba(255,255,255,.72); font-size: 12px; line-height: 1.4;
        }
        .nav-user strong { display: block; color: var(--white); font-size: 13px; }
        .main { padding: 28px; overflow: auto; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 18px; margin-bottom: 24px; }
        .topbar h1 { margin: 0; font-size: 28px; letter-spacing: 0; }
        .topbar p { margin: 5px 0 0; color: var(--muted); }
        .pill { background: var(--blue-soft); color: var(--blue-dark); border: 1px solid #cfe1fb; border-radius: 999px; padding: 8px 13px; font-size: 13px; white-space: nowrap; }
        .grid { display: grid; gap: 16px; }
        .stats { grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 20px; }
        .two { grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr); }
        .card {
            background: var(--white); border: 1px solid var(--line); border-radius: 8px;
            box-shadow: 0 8px 24px rgba(39,54,74,.06);
        }
        .card.pad { padding: 18px; }
        .stat .label { color: var(--muted); font-size: 13px; }
        .stat .value { margin-top: 8px; font-size: 30px; font-weight: 800; color: var(--blue-dark); }
        .stat .hint { margin-top: 4px; font-size: 12px; color: var(--muted); }
        .card-title { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 14px; }
        .card-title h2 { margin: 0; font-size: 18px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 720px; }
        th, td { padding: 12px 14px; border-bottom: 1px solid var(--line); text-align: left; font-size: 14px; }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; background: #f8fafc; }
        tr:last-child td { border-bottom: 0; }
        .parameter-table { min-width: 1180px; border: 1px solid #111827; }
        .parameter-table th, .parameter-table td {
            border: 1px solid #111827; color: #111827; padding: 7px 9px; text-align: center; vertical-align: middle;
        }
        .parameter-table th { background: #fff; text-transform: none; font-size: 13px; font-weight: 800; }
        .parameter-table td { font-size: 13px; }
        .parameter-title { margin-bottom: 18px; line-height: 1.45; color: #111827; }
        .parameter-title strong, .parameter-title em { display: block; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .badge { display: inline-flex; align-items: center; border-radius: 999px; padding: 4px 9px; background: #fff7df; color: #946300; font-size: 12px; font-weight: 700; }
        .danger { background: #ffecef; color: #b11725; }
        .ok { background: #eaf8ef; color: #24703a; }
        .bar { display: grid; gap: 10px; }
        .bar-row { display: grid; grid-template-columns: 70px 1fr 58px; gap: 10px; align-items: center; font-size: 13px; }
        .track { height: 9px; background: #e9eef5; border-radius: 999px; overflow: hidden; }
        .fill { height: 100%; background: linear-gradient(90deg, var(--blue), var(--gold)); }
        .filters { display: flex; gap: 14px; align-items: end; flex-wrap: wrap; margin-bottom: 16px; }
        .filter-field { display: grid; gap: 6px; min-width: 220px; }
        .filter-field.wide { min-width: 340px; }
        .filter-field span { color: var(--muted); font-size: 12px; }
        select, input {
            height: 42px; border: 1px solid var(--line); border-radius: 7px; padding: 0 12px;
            background: var(--white); color: var(--ink);
        }
        .button {
            height: 42px; display: inline-flex; align-items: center; justify-content: center;
            border: 0; border-radius: 7px; padding: 0 15px; background: var(--blue); color: var(--white);
            cursor: pointer; font-weight: 700;
        }
        .button.secondary { background: #eef4ff; color: var(--blue-dark); border: 1px solid #cfe1fb; }
        .editable {
            width: 94px; height: 36px; border: 1px solid var(--line); border-radius: 6px;
            padding: 0 9px; text-align: right; background: #fff;
        }
        .editable:focus { outline: 0; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(31,95,189,.12); }
        .enrollment-input { width: 74px; }
        .computed-input { background: #f8fafc; color: var(--ink); font-weight: 700; }
        .computed-value { color: var(--ink); font-weight: 700; }
        .dashboard-summary { min-width: 1680px; table-layout: fixed; }
        .dashboard-summary col.school-column { width: 11%; }
        .dashboard-summary col.enrollment-column { width: 2.3%; }
        .dashboard-summary col.spacer-column { width: .8%; }
        .dashboard-summary col.metric-column { width: 4.35%; }
        .dashboard-summary th, .dashboard-summary td {
            padding: 7px 4px; text-align: center; font-size: 11px; vertical-align: middle;
        }
        .dashboard-summary th {
            background: #fff; border: 1px solid var(--line); color: var(--ink); text-transform: none;
            white-space: normal; overflow-wrap: anywhere; line-height: 1.15;
        }
        .dashboard-summary td { border: 1px solid var(--line); }
        .dashboard-summary .metric-heading { font-size: 10px; }
        .dashboard-summary .total-group { background: #f1f6fd; font-weight: 800; }
        .dashboard-summary .spacer-cell {
            padding: 0; background: var(--bg); border-top: 0; border-bottom: 0;
        }
        .dashboard-summary .school-cell {
            min-width: 0; text-align: left; position: sticky; left: 0; background: #fff; z-index: 1;
        }
        .dashboard-summary thead .school-cell { z-index: 2; }
        .secondary-summary { min-width: 1920px; }
        .school-audit-table { min-width: 1320px; }
        .school-audit-table th { text-transform: none; white-space: normal; vertical-align: bottom; }
        .school-audit-table .spacer-cell {
            width: 12px; min-width: 12px; padding: 0; background: var(--bg); border-top: 0; border-bottom: 0;
        }
        .total-row td {
            background: #eaf1ff; color: var(--ink); font-weight: 800; border-top: 2px solid #b8d0f5;
        }
        .total-row .badge { font-weight: 800; }
        .total-row .total-group { background: #dbeafe; }
        .dashboard-summary .total-row .school-cell { background: #eaf1ff; }
        .dashboard-summary .total-row .spacer-cell,
        .school-audit-table .total-row .spacer-cell {
            background: var(--bg); border-top: 0;
        }
        .notice {
            border-radius: 8px; padding: 12px 14px; margin-bottom: 16px;
            border: 1px solid #cfe1fb; background: var(--blue-soft); color: var(--blue-dark);
        }
        .notice.error { border-color: #ffc5cd; background: #ffecef; color: #b11725; }
        .account-create { margin-bottom: 18px; }
        .account-section-heading {
            display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;
            padding-bottom: 14px; margin-bottom: 14px; border-bottom: 1px solid var(--line);
        }
        .eyebrow { color: var(--blue); font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .account-section-heading h2, .accounts-toolbar h2 { margin: 2px 0 4px; font-size: 18px; }
        .account-section-heading p, .accounts-toolbar p { margin: 0; color: var(--muted); font-size: 13px; }
        .account-create-grid, .account-edit-panel {
            display: grid; grid-template-columns: repeat(3, minmax(160px, 1fr));
            gap: 12px; align-items: end;
        }
        .account-create-grid .school-field { grid-column: span 1; }
        .account-create-actions { display: flex; justify-content: flex-end; align-items: end; }
        .account-create-actions .button { min-width: 150px; white-space: nowrap; }
        .accounts-card { overflow: hidden; }
        .accounts-toolbar {
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            padding: 18px; border-bottom: 1px solid var(--line);
        }
        .account-search { display: grid; gap: 6px; min-width: 280px; }
        .account-search span { color: var(--muted); font-size: 12px; }
        .accounts-table { min-width: 860px; }
        .accounts-table th { text-transform: none; font-size: 12px; }
        .accounts-table td { vertical-align: middle; }
        .account-school { display: grid; gap: 3px; }
        .account-school strong { color: var(--ink); }
        .account-school span { color: var(--muted); font-size: 12px; }
        .email-chip {
            display: inline-flex; align-items: center; min-height: 30px; padding: 0 10px;
            border-radius: 999px; background: #f8fafc; border: 1px solid var(--line); color: var(--ink);
        }
        .access-badge {
            display: inline-flex; align-items: center; min-height: 28px; padding: 0 10px;
            border-radius: 999px; background: #eaf8ef; color: #24703a; font-size: 12px; font-weight: 800;
        }
        .table-action { height: 34px; padding: 0 12px; }
        .account-edit-row td { background: #f8fbff; padding: 16px 18px; }
        .account-edit-panel { margin-bottom: 12px; }
        .account-edit-actions { display: flex; justify-content: flex-end; align-items: end; }
        .account-delete-form { display: flex; justify-content: flex-end; padding-top: 12px; border-top: 1px solid var(--line); }
        .danger-button { background: #fff0f2; color: #b11725; border: 1px solid #ffc5cd; }
        .empty-state { padding: 24px 18px; color: var(--muted); }
        .fixed-filter {
            min-height: 42px; display: flex; align-items: center; padding: 0 12px;
            border: 1px solid var(--line); border-radius: 7px; background: #f8fafc; font-size: 14px;
        }
        .summary-strip { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        .summary-strip.six { grid-template-columns: repeat(6, minmax(0, 1fr)); }
        .mini-stat { background: var(--white); border: 1px solid var(--line); border-radius: 8px; padding: 14px; }
        .mini-stat span { display: block; color: var(--muted); font-size: 12px; margin-bottom: 7px; }
        .mini-stat strong { display: block; color: var(--blue-dark); font-size: 23px; }
        .muted { color: var(--muted); }

        @media (max-width: 920px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar { position: static; }
            .stats, .two, .summary-strip, .summary-strip.six { grid-template-columns: 1fr; }
            .filter-field, .filter-field.wide { min-width: 100%; }
            .account-create-grid, .account-edit-panel { grid-template-columns: 1fr; }
            .accounts-toolbar { align-items: stretch; flex-direction: column; }
            .account-search { min-width: 100%; }
            .account-create-actions, .account-edit-actions { justify-content: stretch; }
            .account-create-actions .button, .account-edit-actions .button { width: 100%; }
            .main { padding: 20px; }
            .topbar { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="seal">SDO</div>
                <div>
                    <strong>Teacher Audit System</strong>
                    <span>Schools Division Office - Marikina City</span>
                </div>
            </div>
            <nav class="nav">
                <div class="nav-user">
                    <strong>{{ auth()->user()->name }}</strong>
                    {{ auth()->user()->isAdmin() ? 'Administrator' : auth()->user()->school_code }}
                </div>
                @if (auth()->user()->isAdmin())
                    <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dashboard</a>
                @endif
                <a class="{{ request()->routeIs('schools') ? 'active' : '' }}" href="{{ auth()->user()->isSchool() ? route('schools', ['school' => auth()->user()->school_code]) : route('schools') }}">School Audit</a>
                @if (auth()->user()->isAdmin())
                    <a class="{{ request()->routeIs('parameters') ? 'active' : '' }}" href="{{ route('parameters') }}">Parameters</a>
                    <a class="{{ request()->routeIs('accounts.*') ? 'active' : '' }}" href="{{ route('accounts.index') }}">Account Management</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="logout" type="submit">Sign Out</button>
                </form>
            </nav>
        </aside>

        <main class="main">
            {{ $slot }}
        </main>
    </div>
</body>
</html>
