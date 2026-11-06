<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#0879b8">
  <title>Masuk Administrator | JASPEST</title>
  <link rel="icon" type="image/png" href="{{ asset('jaspest/assets/logo-jas.png') }}">
  <style>
    @font-face {
      font-family: JasSans;
      src: url('{{ asset('jaspest/assets/jas-sans-regular.woff') }}') format('woff');
      font-style: normal;
      font-weight: 400;
      font-display: swap;
    }
    @font-face {
      font-family: JasSans;
      src: url('{{ asset('jaspest/assets/jas-sans-bold.woff') }}') format('woff');
      font-style: normal;
      font-weight: 700;
      font-display: swap;
    }

    :root {
      color-scheme: light;
      --brand: #0879b8;
      --brand-hover: #066aa3;
      --brand-deep: #053a5f;
      --ink: #0f2a3d;
      --muted: #5d7080;
      --subtle: #8a9ba8;
      --page: #f8fafb;
      --field: #edf3f7;
      --danger: #c0283a;
      --danger-soft: #fff1f2;
      --ok: #137a55;
      --ok-soft: #e8f7f0;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html { -webkit-text-size-adjust: 100%; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: JasSans, Arial, Helvetica, sans-serif;
      font-size: 16px;
      line-height: 1.5;
      color: var(--ink);
      background: var(--page);
      -webkit-font-smoothing: antialiased;
    }
    a { color: inherit; text-decoration: none; }
    svg { display: block; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

    .login-shell {
      min-height: 100vh;
      display: grid;
      grid-template-columns: minmax(0, 44fr) minmax(0, 56fr);
    }

    /* ——— Panel kiri: foto + penjelasan portal ——— */
    .login-hero {
      position: relative;
      isolation: isolate;
      display: flex;
      flex-direction: column;
      padding: clamp(24px, 4.6vh, 56px) clamp(28px, 4.6vw, 68px);
      color: #fff;
      overflow: hidden;
      background: var(--brand-deep) url('{{ asset('jaspest/assets/hero-pest-control.webp') }}') 60% center / cover no-repeat;
    }
    .login-hero::before {
      content: "";
      position: absolute;
      inset: 0;
      z-index: -1;
      background: linear-gradient(180deg, rgba(4, 44, 74, .84) 0%, rgba(6, 88, 138, .74) 42%, rgba(3, 36, 62, .94) 100%);
    }
    .hero-brand { display: inline-flex; align-items: center; gap: 16px; align-self: flex-start; }
    .hero-brand-tile {
      width: 56px; height: 56px; flex-shrink: 0;
      display: grid; place-items: center;
      border-radius: 14px; background: #fff;
      box-shadow: 0 6px 18px rgba(0, 0, 0, .18);
    }
    .hero-brand-tile img { width: 44px; height: auto; display: block; }
    .hero-brand-name { display: block; font-size: 20px; font-weight: 700; line-height: 1.2; letter-spacing: -.01em; }
    .hero-brand-sub { display: block; font-size: 14px; line-height: 1.3; color: rgba(255, 255, 255, .85); }

    .hero-body { margin-top: auto; padding-top: clamp(20px, 4vh, 44px); }
    .hero-body h2 {
      margin: 0;
      font-size: clamp(30px, min(5.2vh, 3vw), 50px);
      line-height: 1.1;
      letter-spacing: -.035em;
      font-weight: 700;
      text-shadow: 0 2px 14px rgba(0, 0, 0, .25);
    }
    .hero-lead { max-width: 520px; margin: clamp(10px, 1.8vh, 16px) 0 0; font-size: clamp(14.5px, 2vh, 16.5px); line-height: 1.6; color: rgba(255, 255, 255, .92); }

    .hero-features { list-style: none; margin: clamp(20px, 4vh, 36px) 0 0; padding: 0; display: grid; gap: clamp(12px, 2.4vh, 20px); }
    .hero-feature { display: flex; align-items: flex-start; gap: 16px; }
    .hero-feature-icon {
      width: 46px; height: 46px; flex-shrink: 0;
      display: grid; place-items: center;
      border-radius: 13px;
      background: rgba(255, 255, 255, .14);
      border: 1px solid rgba(255, 255, 255, .2);
      -webkit-backdrop-filter: blur(6px); backdrop-filter: blur(6px);
    }
    .hero-feature-icon svg { width: 22px; height: 22px; }
    .hero-feature-text strong { display: block; font-size: 17px; line-height: 1.3; }
    .hero-feature-text span { display: block; margin-top: 2px; font-size: 14px; line-height: 1.5; color: rgba(255, 255, 255, .86); }

    /* ——— Panel kanan: formulir ——— */
    .login-main {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: clamp(20px, 4vh, 44px) clamp(24px, 4vw, 64px) 18px;
      background: var(--page);
    }
    .login-card { width: 100%; max-width: 380px; margin-block: auto; }
    .login-eyebrow {
      display: flex; align-items: center; gap: 10px;
      margin: 0 0 12px;
      color: var(--brand);
      font-size: 12px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase;
    }
    .login-eyebrow::before { content: ""; width: 18px; height: 2px; background: currentColor; }
    .login-card h1 {
      margin: 0 0 26px;
      font-size: 26px;
      line-height: 1.15;
      letter-spacing: -.03em;
      font-weight: 700;
    }

    .alert {
      margin: -8px 0 18px; padding: 11px 14px;
      border: 1px solid #f3c4ca; border-radius: 10px;
      background: var(--danger-soft); color: var(--danger);
      font-size: 13px; font-weight: 700; line-height: 1.5;
    }
    .alert.success { border-color: #bfe5d3; background: var(--ok-soft); color: var(--ok); }

    .field-group + .field-group { margin-top: 20px; }
    .field-label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 700; }
    .field-wrap { position: relative; }
    .field-icon {
      position: absolute; left: 15px; top: 50%;
      width: 16px; height: 16px; margin-top: -8px;
      color: var(--subtle); pointer-events: none;
    }
    .field {
      display: block; width: 100%; height: 50px;
      padding: 0 14px 0 42px;
      border: 1.5px solid transparent; border-radius: 10px;
      background: var(--field); color: var(--ink);
      font: inherit; font-size: 14px; outline: 0;
      transition: background-color .15s, border-color .15s, box-shadow .15s;
    }
    .field.has-toggle { padding-right: 50px; }
    .field::placeholder { color: var(--subtle); }
    .field:hover { border-color: #d3e0e9; }
    .field:focus { background: #fff; border-color: var(--brand); box-shadow: 0 0 0 3px rgba(8, 121, 184, .14); }
    .field.is-invalid { background: var(--danger-soft); border-color: var(--danger); }
    .field.is-invalid:focus { box-shadow: 0 0 0 3px rgba(192, 40, 58, .14); }
    .field-error { margin-top: 6px; color: var(--danger); font-size: 12.5px; font-weight: 700; line-height: 1.45; }

    .password-toggle {
      position: absolute; right: 8px; top: 50%;
      width: 34px; height: 34px; margin-top: -17px;
      display: grid; place-items: center;
      border: 0; border-radius: 8px; background: transparent;
      color: var(--brand); cursor: pointer;
    }
    .password-toggle:hover { background: rgba(8, 121, 184, .09); }
    .password-toggle:focus-visible { outline: 2px solid var(--brand); outline-offset: 1px; }
    .password-toggle svg { width: 17px; height: 17px; }
    .password-toggle .icon-eye-off { display: none; }
    .password-toggle[aria-pressed="true"] .icon-eye { display: none; }
    .password-toggle[aria-pressed="true"] .icon-eye-off { display: block; }

    .remember { display: flex; align-items: center; gap: 9px; margin-top: 18px; color: var(--muted); font-size: 13.5px; cursor: pointer; width: fit-content; }
    .remember input { width: 16px; height: 16px; margin: 0; accent-color: var(--brand); cursor: pointer; }

    .submit {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; height: 48px; margin-top: 24px;
      border: 0; border-radius: 10px;
      background: var(--brand); color: #fff;
      font: inherit; font-size: 15px; font-weight: 700;
      cursor: pointer;
      box-shadow: 0 6px 16px rgba(8, 121, 184, .2);
      transition: background-color .15s, transform .1s;
    }
    .submit:hover { background: var(--brand-hover); }
    .submit:active { transform: translateY(1px); }
    .submit:focus-visible { outline: 3px solid rgba(8, 121, 184, .4); outline-offset: 2px; }
    .submit svg { width: 18px; height: 18px; }

    .login-copy {
      display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 4px 10px;
      margin: 24px 0 0; color: var(--subtle); font-size: 12.5px; text-align: center;
    }
    .login-copy-sep { width: 4px; height: 4px; border-radius: 50%; background: currentColor; opacity: .6; }
    .login-copy a { color: var(--brand); font-weight: 700; }
    .login-copy a:hover { text-decoration: underline; }
    .login-copy a:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; border-radius: 3px; }

    /* ——— Responsif ——— */
    @media (max-width: 1100px) {
      .login-shell { grid-template-columns: minmax(0, 40fr) minmax(0, 60fr); }
      .hero-lead { font-size: 15px; }
    }
    @media (max-width: 900px) {
      .login-shell { display: block; min-height: 0; }
      .login-hero { padding: 28px 24px 30px; }
      .hero-body { margin-top: 0; padding-top: 28px; }
      .hero-body h2 { font-size: 30px; }
      .hero-lead { margin-top: 12px; font-size: 15px; }
      .hero-features { display: none; }
      .login-main { padding: 36px 22px 24px; }
      .login-card { margin-block: 0; }
      .login-card h1 { margin-bottom: 26px; }
    }
    @media (max-width: 420px) {
      .hero-brand-tile { width: 54px; height: 54px; border-radius: 14px; }
      .hero-brand-tile img { width: 42px; }
      .hero-brand-name { font-size: 20px; }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { transition-duration: .01ms !important; animation-duration: .01ms !important; }
    }
  </style>
  <link rel="stylesheet" href="{{ asset('jaspest/admin/admin-login-responsive.css') }}?v=20261104-01">
</head>
<body>
  <div class="login-shell">
    <aside class="login-hero" aria-label="Informasi portal JASPEST">
      <a class="hero-brand" href="{{ route('jaspest.login') }}" aria-label="JASPEST - halaman masuk administrator">
        <span class="hero-brand-tile"><img src="{{ asset('jaspest/assets/logo-jas.png') }}" alt="" width="458" height="368"></span>
        <span>
          <span class="hero-brand-name">JASPEST</span>
          <span class="hero-brand-sub">CV Jaya Abadi Solusi</span>
        </span>
      </a>

      <div class="hero-body">
        <h2>Halo,<br>selamat datang.</h2>
        <p class="hero-lead">Kelola permintaan layanan, order, pelanggan, dan operasional JASPEST dari satu dashboard.</p>

        <ul class="hero-features">
          <li class="hero-feature">
            <span class="hero-feature-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM4 14h5l1 2h4l1-2h5"/></svg></span>
            <div class="hero-feature-text">
              <strong>Order Masuk Real-Time</strong>
              <span>Setiap permintaan layanan tercatat di dashboard beserta informasi dan dokumentasi pelanggan.</span>
            </div>
          </li>
          <li class="hero-feature">
            <span class="hero-feature-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l4 4v14H7zM14 3v4h4"/></svg></span>
            <div class="hero-feature-text">
              <strong>Kelola Konten Website</strong>
              <span>Atur layanan, jenis hama, galeri, dan artikel yang tampil di website publik.</span>
            </div>
          </li>
          <li class="hero-feature">
            <span class="hero-feature-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg></span>
            <div class="hero-feature-text">
              <strong>Rekap Order Lengkap</strong>
              <span>Pantau tindak lanjut pelanggan dan ekspor laporan order ke Excel.</span>
            </div>
          </li>
        </ul>
      </div>
    </aside>

    <main class="login-main">
      <section class="login-card" aria-labelledby="login-title">
        <p class="login-eyebrow">CV Jaya Abadi Solusi</p>
        <h1 id="login-title">Masuk ke Dashboard</h1>

        @if (session('status'))
          <div class="alert success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any() && !$errors->has('username') && !$errors->has('password'))
          <div class="alert" role="alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('jaspest.login.submit') }}">
          @csrf
          <div class="field-group">
            <label class="field-label" for="username">Username</label>
            <div class="field-wrap">
              <svg class="field-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8"/></svg>
              <input
                class="field @error('username') is-invalid @enderror"
                id="username"
                name="username"
                type="text"
                value="{{ old('username') }}"
                autocomplete="username"
                autocapitalize="none"
                autocorrect="off"
                spellcheck="false"
                maxlength="50"
                placeholder="username"
                aria-invalid="@error('username') true @else false @enderror"
                @error('username') aria-describedby="username-error" @enderror
                required
                autofocus
              >
            </div>
            @error('username')<div class="field-error" id="username-error">{{ $message }}</div>@enderror
          </div>

          <div class="field-group">
            <label class="field-label" for="password">Kata Sandi</label>
            <div class="field-wrap">
              <svg class="field-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
              <input
                class="field has-toggle @error('password') is-invalid @enderror"
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                placeholder="Masukkan kata sandi"
                aria-invalid="@error('password') true @else false @enderror"
                @error('password') aria-describedby="password-error" @enderror
                required
              >
              <button class="password-toggle" type="button" id="togglePassword" aria-controls="password" aria-pressed="false" aria-label="Tampilkan kata sandi">
                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 6.2A9.6 9.6 0 0 1 12 6c6.1 0 9.5 6 9.5 6a16 16 0 0 1-3.1 3.9M6.6 7.6A15.6 15.6 0 0 0 2.5 12s3.4 6 9.5 6c1.5 0 2.8-.4 4-.9M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
              </button>
            </div>
            @error('password')<div class="field-error" id="password-error">{{ $message }}</div>@enderror
          </div>

          <label class="remember">
            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
            <span>Ingat saya di perangkat ini</span>
          </label>

          <button class="submit" type="submit">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
            <span>Masuk</span>
          </button>
        </form>

      </section>

      <p class="login-copy">
        <span>© {{ now('Asia/Jakarta')->year }} CV Jaya Abadi Solusi</span>
        <span class="login-copy-sep" aria-hidden="true"></span>
        <a href="https://www.asaproduction.tech" target="_blank" rel="noopener noreferrer">Created By Asa Production</a>
      </p>
    </main>
  </div>

  <script>
    (function () {
      var toggle = document.getElementById('togglePassword');
      var field = document.getElementById('password');
      if (!toggle || !field) return;

      toggle.addEventListener('click', function () {
        var show = field.type === 'password';
        field.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
        toggle.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        field.focus();
      });
    })();
  </script>
</body>
</html>
