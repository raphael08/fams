<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Archive') · Document Archive</title>
        <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('theme'); } catch (e) {}
            if (t !== 'dark' && t !== 'light') { t = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
            document.documentElement.setAttribute('data-bs-theme', t);
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('vendor/datatables/vendor.css') }}" rel="stylesheet">
    <style>
        [data-bs-theme=light] body { background: #f4f6f9; }
        [data-bs-theme=dark] body { background: #0f141a; }
        [data-bs-theme=dark] .sidebar { background: #111827; }
        [data-bs-theme=dark] .sidebar a, [data-bs-theme=dark] .sidebar a i { color: #e5e7eb !important; }
        [data-bs-theme=dark] .sidebar .grp { color: #9ca3af; }
        [data-bs-theme=dark] .sidebar a:hover, [data-bs-theme=dark] .sidebar a.active { background: #374151; color: #fff !important; }
        [data-bs-theme=dark] .card, [data-bs-theme=dark] .navbar { border-color: var(--bs-border-color); }
        .sidebar { width: 240px; background: #1f2a37; min-height: 100vh; }
        .sidebar a { color: #cbd5e1; text-decoration: none; display: block; padding: .45rem 1.25rem; font-size: .95rem; }
        .sidebar a:hover, .sidebar a.active { background: #5f6975; color: #fff; }
        .sidebar .grp { color: #64748b; font-size: .7rem; letter-spacing: .08em; padding: 1rem 1.25rem .25rem; text-transform: uppercase; }
        .stat { font-size: 1.9rem; font-weight: 600; }
        .table td, .table th { vertical-align: middle; }
        .dt-container .dt-buttons { display: flex; flex-wrap: wrap; gap: .3rem; }
        .dt-container .dt-buttons .dt-button, .dt-container .dt-buttons .btn {
            background: var(--bs-body-bg) !important; color: var(--bs-body-color) !important; border: 1px solid var(--bs-border-color) !important;
            border-radius: .3rem !important; padding: .25rem .65rem !important; font-size: .8rem !important;
            line-height: 1.4 !important; box-shadow: none !important; margin: 0 !important; }
        .dt-container .dt-buttons .dt-button:hover, .dt-container .dt-buttons .btn:hover {
            background: var(--bs-tertiary-bg) !important; color: var(--bs-emphasis-color) !important; }
        .dt-container .dt-buttons .dt-button i { margin-right: .25rem; }
        .dt-container { padding: .6rem .75rem; }
        .dt-container .dt-layout-row { margin: 0; }
        .dt-container .dt-search input { min-width: 200px; }
        table.dataTable > tbody > tr.child ul.dtr-details { width: 100%; }
    </style>
    @stack('head')
</head>
<body>
@php $nav = fn ($route, $label, $icon) => '<a style="color:black" href="' . route($route) . '" class="' . (request()->routeIs($route . '*') ? 'active' : '') . '"><i class="bi bi-' . $icon . ' me-2"></i>' . $label . '</a>'; @endphp
<div class="d-flex">
    <div class="offcanvas-lg offcanvas-start sidebar text-white flex-shrink-0" tabindex="-1" id="sidebar">
        <div class="w-100" style="color:black">
            <div class="px-3 py-3 fw-semibold fs-5"><i class="bi bi-archive me-2"></i>Document Archive</div>
            {!! $nav('dashboard', 'Dashboard', 'speedometer2') !!}
            <div class="grp">Documents</div>
            {!! $nav('documents.index', 'Documents', 'files') !!}
            @can('manage')
                {!! $nav('upload', 'Upload Document', 'upload') !!}
                {!! $nav('zip', 'ZIP Import', 'file-earmark-zip') !!}
            @endcan
            @can('admin') {!! $nav('documents.trash', 'Deleted Documents', 'trash') !!} @endcan
            {!! $nav('search', 'Search', 'search') !!}
            @can('admin')
                <div class="grp">Setup</div>
                {!! $nav('categories', 'Categories', 'tags') !!}
                {!! $nav('types', 'Document Types', 'bookmarks') !!}
            @endcan
            <div class="grp">Operations</div>
            @can('manage') {!! $nav('ocr', 'OCR', 'cpu') !!} @endcan
            {!! $nav('reports', 'Reports', 'bar-chart') !!}
            @can('admin')
                {!! $nav('audit', 'Audit Logs', 'journal-text') !!}
                <div class="grp">Administration</div>
                {!! $nav('users', 'Users', 'people') !!}
                {!! $nav('settings', 'Settings', 'gear') !!}
            @endcan
        </div>
    </div>

    <div class="flex-grow-1" style="min-width:0">
        <nav class="navbar bg-body border-bottom px-3">
            <button class="btn btn-outline-secondary d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar"><i class="bi bi-list"></i></button>
            <form action="{{ route('search') }}" class="d-none d-md-flex flex-grow-1 mx-3" style="max-width:420px">
                <input name="q" class="form-control form-control-sm" placeholder="Search documents…" value="{{ request('q') }}">
            </form>
            <div class="ms-auto d-flex align-items-center gap-2">
                <button type="button" id="themeToggle" class="btn btn-sm btn-outline-secondary" title="Switch light / dark theme" aria-label="Toggle theme"><i class="bi bi-moon-stars"></i></button>
                <span class="small text-muted">{{ auth()->user()->name }} <span class="badge text-bg-light border">{{ ucfirst(auth()->user()->role) }}</span></span>
                <form method="post" action="{{ route('logout') }}">@csrf <button class="btn btn-sm btn-outline-secondary">Logout</button></form>
            </div>
        </nav>

        <main class="p-3 p-lg-4">
            @foreach (['success', 'error' => 'danger', 'warning'] as $k => $c)
                @php $key = is_int($k) ? $c : $k; $cls = is_int($k) ? $c : $c; @endphp
                @if (session($key))
                    <div class="alert alert-{{ $key === 'error' ? 'danger' : $key }} alert-dismissible fade show">
                        {{ session($key) }}
                        @if ($key === 'warning' && session('duplicate_url'))
                            <a href="{{ session('duplicate_url') }}" class="alert-link">{{ session('duplicate_name') }}</a>
                        @endif
                        <button class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
            @endforeach
            @if ($errors->any())
                <div class="alert alert-danger">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('vendor/datatables/vendor.js') }}"></script>
<script src="{{ asset('vendor/datatables/init.js') }}"></script>
<script>
    (function () {
        var btn = document.getElementById('themeToggle'), root = document.documentElement;
        function paint() {
            var dark = root.getAttribute('data-bs-theme') === 'dark';
            btn.innerHTML = dark ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
            btn.title = dark ? 'Switch to light theme' : 'Switch to dark theme';
        }
        btn.addEventListener('click', function () {
            var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-bs-theme', next);
            try { localStorage.setItem('theme', next); } catch (e) {}
            paint();
        });
        paint();
    })();
</script>
@stack('scripts')
</body>
</html>
