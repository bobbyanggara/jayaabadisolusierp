<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Kelola Blog | JASPEST</title><link rel="stylesheet" href="{{ asset('jaspest/styles.css') }}"><link rel="stylesheet" href="{{ asset('jaspest/blog-admin.css') }}"><link rel="stylesheet" href="{{ asset('jaspest/admin/admin-system.css') }}?v=20261031-1"></head>
<body class="blog-admin">
    <header class="admin-header"><a href="{{ route('jaspest.blog.admin.index') }}"><strong>JASPEST.</strong> <span>Kelola blog</span></a><nav><a href="{{ url('/admin') }}">Dashboard internal</a><a href="{{ route('jaspest.blog.index') }}" target="_blank" rel="noopener"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6"/></svg><span>Lihat blog</span></a></nav></header>
    <main class="admin-main">
        @if(session('status'))<p class="admin-status" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<div class="admin-error" role="alert"><strong>Periksa kembali isian berikut.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</body></html>
