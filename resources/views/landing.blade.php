<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#00a8f0">
  <meta name="description" content="JASPEST — CV Jaya Abadi Solusi. Konsultasikan kebutuhan Pest Control, Rodent Control, Termite Control, Fumigasi, dan Disinfeksi untuk rumah dan bisnis Anda.">
  <meta property="og:title" content="JASPEST — Rumah nyaman. Bisnis tetap jalan.">
  <meta property="og:description" content="Ceritakan kebutuhan pengendalian hama Anda. JASPEST siap membantu kebutuhan rumah dan bisnis.">
  <meta property="og:type" content="website">
  <link rel="canonical" href="{{ url('/') }}">
  <meta property="og:url" content="{{ url('/') }}">
  <meta property="og:image" content="{{ asset('jaspest/assets/hero-pest-control.webp') }}">
  <title>JASPEST — Solusi Pengendalian Hama untuk Rumah & Bisnis</title>
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('jaspest/assets/favicon-32.png') }}">
  <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
  <link rel="apple-touch-icon" href="{{ asset('jaspest/assets/apple-touch-icon.png') }}">
  <link rel="canonical" href="{{ url('/') }}">
  <script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'LocalBusiness','name'=>'JASPEST — CV Jaya Abadi Solusi','url'=>url('/'),'logo'=>asset('jaspest/assets/logo-jas.png'),'image'=>asset('jaspest/assets/hero-pest-control.webp'),'description'=>'Konsultasi pengendalian hama untuk rumah dan bisnis: pest control, rodent control, termite control, fumigasi, dan disinfeksi.','knowsAbout'=>['Pest Control','Rodent Control','Termite Control','Fumigasi','Disinfeksi'],'inLanguage'=>'id-ID'], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG) !!}</script>
  <script>document.documentElement.classList.add('js-anim');</script>
  <link rel="stylesheet" href="{{ asset('jaspest/styles.css') }}?v=15">
  <link rel="stylesheet" href="{{ asset('jaspest/blog.css') }}">
  <link rel="stylesheet" href="{{ asset('jaspest/animations.css') }}?v=23">
  <link rel="stylesheet" href="{{ asset('jaspest/responsive-overrides.css') }}?v=20261104-05">
  <link rel="stylesheet" href="{{ asset('jaspest/certifications.css') }}?v=20261104-01">
  <link rel="stylesheet" href="{{ asset('jaspest/location-map.css') }}?v=20261104-03">
  <script>window.JASPEST_CONFIG = {{ Illuminate\Support\Js::from($jaspestConfig ?? []) }}; window.JASPEST_PUBLISHED_ARTICLES = {{ Illuminate\Support\Js::from($jaspestPublishedArticles) }}; window.JASPEST_DYNAMIC_CONTENT = {{ Illuminate\Support\Js::from($jaspestDynamicContent ?? []) }};</script>
  @include('jaspest.partials.cookie-consent')
  <script src="{{ asset('jaspest/content.js') }}?v=3" defer></script>
  <script src="{{ asset('jaspest/app.js') }}?v=7" defer></script>
  <script src="{{ asset('jaspest/animations.js') }}?v=10" defer></script>
</head>
<body>
  <a class="skip-link" href="#main">Langsung ke konten</a>
  <svg class="icon-defs" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></symbol>
    <symbol id="i-house" viewBox="0 0 24 24"><path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-7h6v7"/></symbol>
    <symbol id="i-building" viewBox="0 0 24 24"><path d="M5 21V3h14v18M3 21h18M9 7h1m4 0h1M9 11h1m4 0h1m-6 4h1m4 0h1m-4 6v-3h2v3"/></symbol>
    <symbol id="i-bug" viewBox="0 0 24 24"><path d="m9 3 1 3m5-3-1 3M7 11H3m18 0h-4M7 15H3m18 0h-4M7 8 4 6m13 2 3-2M7 18l-3 3m13-3 3 3M12 7v13"/><rect x="7" y="6" width="10" height="15" rx="5"/></symbol>
    <symbol id="i-mouse" viewBox="0 0 24 24"><path d="M8 9a3 3 0 1 1 4-4m1 3a3 3 0 1 1 5 4m-9-1c-4 0-6 3-6 6s3 4 6 4h6c3 0 5-2 5-5s-3-6-7-6M20 17c4 0 3-5 0-5"/><path d="M7 15h.01"/></symbol>
    <symbol id="i-warehouse" viewBox="0 0 24 24"><path d="m3 8 9-5 9 5v13H3V8Zm4 13V11h10v10M7 15h10M7 18h10"/></symbol>
    <symbol id="i-sparkles" viewBox="0 0 24 24"><path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Zm7 0v4m-2-2h4M4 17v4m-2-2h4"/></symbol>
    <symbol id="i-chat" viewBox="0 0 24 24"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2V11.5A8.5 8.5 0 0 1 10.5 3h2A8.5 8.5 0 0 1 21 11.5Z"/><path d="M7 11h.01M12 11h.01M17 11h.01"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
    <symbol id="i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M6 18 18 6"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
    <symbol id="i-camera" viewBox="0 0 24 24"><path d="M14 4H9L7 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-3-3Z"/><circle cx="12" cy="13" r="4"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6M8 13h8m-8 4h6"/></symbol>
    <symbol id="i-send" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4L22 2ZM11 13 22 2"/></symbol>
    <symbol id="i-play" viewBox="0 0 24 24"><path d="m9 5 12 7-12 7V5Z"/></symbol>
    <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
    <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.01"/></symbol>
  </defs></svg>

  <header class="site-header" id="site-header">
    <div class="header-inner container">
      <a class="brand" href="#" aria-label="CV Jaya Abadi Solusi — beranda"><img class="brand-logo" src="{{ asset('jaspest/assets/logo-jas.png') }}" alt="Logo CV Jaya Abadi Solusi" width="458" height="368" decoding="async"></a>
      <nav class="desktop-nav" aria-label="Navigasi utama"><a href="#layanan">Layanan</a><a href="#jenis-hama">Jenis hama</a><a href="#tentang">Tentang kami</a><a href="#galeri">Galeri</a><a href="#blog">Blog</a><a href="#lokasi">Kontak</a></nav>
      <div class="header-actions"><button class="portal-link" data-portal><svg><use href="#i-lock"/></svg>Portal klien</button><a class="button button-small button-blue" href="#konsultasi">Konsultasi</a><button class="menu-toggle icon-button" id="menu-toggle" type="button" aria-expanded="false" aria-controls="mobile-nav" aria-label="Buka menu"><span class="menu-icon" aria-hidden="true"><span></span><span></span><span></span></span></button></div>
    </div>
  </header>

  {{-- Elemen drawer diletakkan di luar header sticky agar position: fixed selalu mengacu ke viewport. --}}
  <button class="mobile-nav-backdrop" id="mobile-nav-backdrop" type="button" aria-label="Tutup menu navigasi" tabindex="-1" hidden></button>
  <nav class="mobile-nav" id="mobile-nav" aria-label="Navigasi ponsel" aria-hidden="true" inert hidden><div class="mobile-nav-heading"><span>NAVIGASI</span><strong>Jelajahi JASPEST</strong><small>Pilih halaman yang ingin Anda kunjungi.</small></div><a href="#layanan">Layanan</a><a href="#jenis-hama">Jenis hama</a><a href="#tentang">Tentang kami</a><a href="#galeri">Galeri</a><a href="#blog">Blog</a><a href="#lokasi">Lokasi kantor</a><button type="button" data-portal>Portal klien</button><a href="#konsultasi">Ajukan konsultasi</a></nav>

  <main id="main">
    @php
      $pageContent = $jaspestPageContent ?? collect();
      $heroPage = $pageContent->get('beranda-hero');
      $aboutPage = $pageContent->get('tentang-jaspest');
      $contactPage = $pageContent->get('kontak-konsultasi');

      $aboutDefaults = [
        'JASPEST adalah perusahaan yang sudah berpengalaman lebih dari 15 tahun di bidang Jasa Pest Control (pengendalian hama).',
        'JASPEST berdiri di Sidoarjo sejak tahun 2008. Kami hadir sebagai mitra terpercaya bagi sektor residensial (rumah tinggal) maupun komersial (bisnis/industri).',
        'Kami menerapkan Sistem IPM (Integrated Pest Management) dalam pengendalian hama yang ramah lingkungan, aman, dan efektif. Bagi kami, mewujudkan kepuasan pelanggan adalah prioritas utama dalam setiap layanan yang kami berikan.',
      ];
      $aboutText = $aboutPage ? trim($aboutPage->body ?? '') : '';
      $aboutParagraphs = $aboutText !== '' ? array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $aboutText)))) : $aboutDefaults;
      $officeInfo = (array) (($jaspestDynamicContent ?? [])['office'] ?? []);
      $officeAddress = $officeInfo['address'] ?? 'Jl. Taruna Kav.IX No.126 Blk N - O, Sritanjung, Wage, Kec. Taman, Kabupaten Sidoarjo, Jawa Timur 61257';
      $officePhone = $officeInfo['phone'] ?? '(031) 85582382';
      $officeEmail = $officeInfo['email'] ?? 'cv.jayaabadisolusi@yahoo.com';
      $officeEmailHref = $officeInfo['emailHref'] ?? ($officeEmail !== '' ? 'mailto:'.$officeEmail : null);
    @endphp
    <section class="hero container" aria-labelledby="hero-title">
      <div class="hero-copy"><p class="hero-kicker"><span class="hero-kicker-mark" aria-hidden="true"><svg><use href="#i-bug"/></svg></span><span class="hero-kicker-text">Pengendalian hama oleh <strong>CV Jaya Abadi Solusi</strong></span></p><h1 id="hero-title">{{ $heroPage?->title ?: 'Rumah nyaman. Bisnis tetap jalan.' }}</h1><p class="hero-description">{{ $heroPage?->description ?: 'Jaga ruang yang berarti bagi Anda. Konsultasikan kebutuhan pengendalian hama rumah dan bisnis bersama JASPEST.' }}</p><div class="hero-cta"><a class="button button-red" href="#konsultasi">Ajukan konsultasi</a><a class="text-link" href="#layanan">Kenali layanan kami</a></div><div class="hero-audience"><span><svg><use href="#i-house"/></svg>Rumah & hunian</span><span class="audience-divider"></span><span><svg><use href="#i-building"/></svg>Bisnis & industri</span></div></div>
      <div class="hero-visual"><div class="hero-image-frame"><img src="{{ \App\Support\JaspestMedia::url($heroPage?->image_path) ?? asset('jaspest/assets/hero-pest-control.webp') }}" width="1536" height="1024" alt="{{ $heroPage?->image_alt ?: 'Ilustrasi teknisi melakukan pemeriksaan di area komersial yang bersih' }}" fetchpriority="high"></div><div class="hero-note"><span class="note-icon"><svg><use href="#i-shield"/></svg></span><div><strong>Ruang Anda, perhatian kami.</strong><span>Penanganan berawal dari kebutuhan Anda.</span></div></div><span class="hero-red-accent" aria-hidden="true"></span></div>
    </section>

    <section class="assurance" aria-label="Pendekatan layanan"><div class="container assurance-inner"><div><svg><use href="#i-house"/></svg><span>Untuk rumah <strong>dan bisnis</strong></span></div><span class="assurance-line"></span><div><svg><use href="#i-chat"/></svg><span>Komunikasi langsung <strong>dengan tim</strong></span></div><span class="assurance-line"></span><div><svg><use href="#i-shield"/></svg><span>Solusi sesuai <strong>kebutuhan Anda</strong></span></div></div></section>

    <section class="section services-section" id="layanan"><div class="container"><div class="section-heading"><div><h2>Kebutuhan berbeda.<br>Perhatian yang sama.</h2></div><p>Kenali layanan kami dan ceritakan kebutuhan lokasi Anda. Tim JASPEST akan membantu membahas penanganannya.</p></div><div class="services-grid" id="services-grid"></div></div></section>

    <section class="section directory-section" id="jenis-hama"><div class="container"><div class="section-heading"><div><h2>Informasi jenis hama.</h2></div><p>Kenali tanda dan karakteristik hama yang sering ditemukan di rumah maupun lingkungan usaha.</p></div><div class="directory-grid" id="pests-grid"></div></div></section>
    <section class="section about-section" id="tentang"><div class="container"><div class="about-grid"><div class="about-visual"><img src="{{ \App\Support\JaspestMedia::url($aboutPage?->image_path) ?? asset('jaspest/assets/residential-inspection.webp') }}" width="1536" height="1024" loading="lazy" alt="{{ $aboutPage?->image_alt ?: 'Ilustrasi pemeriksaan pada sudut kabinet rumah' }}"><div class="about-stamp"><svg><use href="#i-shield"/></svg><span>{{ $aboutPage?->category ?: 'JASPEST' }}<strong>{{ $aboutPage?->description ?: 'Sejak 2008 di Sidoarjo' }}</strong></span></div></div><div class="about-copy"><h2>{{ $aboutPage?->title ?: 'CV. JAYA ABADI SOLUSI' }}</h2>@foreach($aboutParagraphs as $aboutParagraph)<p>{{ $aboutParagraph }}</p>@endforeach<a class="button button-blue about-cta" href="#konsultasi"><svg aria-hidden="true"><use href="#i-chat"/></svg><span>{{ data_get($aboutPage, 'data.cta') ?: 'Mari bicarakan kebutuhan Anda' }}</span></a></div></div></div></section>

    <section class="section gallery-section" id="galeri"><div class="container"><div class="section-heading"><div><h2>Perhatian yang terlihat<br>di setiap sudut.</h2></div><div class="gallery-intro"><p>Dokumentasi layanan untuk kebutuhan rumah dan lingkungan usaha.</p></div></div><div class="gallery-toolbar" role="group" aria-label="Jenis galeri"><button class="filter-button active" data-gallery-filter="Semua" aria-pressed="true">Semua</button><button class="filter-button" data-gallery-filter="Rumah" aria-pressed="false">Rumah</button><button class="filter-button" data-gallery-filter="Bisnis" aria-pressed="false">Bisnis</button></div><div class="gallery-grid" id="gallery-grid"></div></div></section>

    <section class="video-section section"><div class="container video-layout"><div class="video-copy"><h2>Lebih dekat dengan<br>kegiatan kami.</h2><p>Temukan cuplikan kegiatan dan informasi JASPEST melalui video TikTok.</p><div class="video-mini-note"><span class="tiktok-letter" aria-hidden="true">♪</span><div><strong>Kegiatan & informasi JASPEST</strong><span>Video akan hadir di halaman ini.</span></div></div><div class="video-cta"><a class="button button-red" href="#konsultasi">Ajukan konsultasi</a><span>Lihat langsung cara tim bekerja, lalu ceritakan kebutuhan Anda.</span></div></div><div class="video-host" id="tiktok-host"><div class="video-placeholder"><img src="{{ asset('jaspest/assets/commercial-inspection.webp') }}" alt="Ilustrasi kegiatan pemeriksaan fasilitas" width="1536" height="1024" loading="lazy"><div class="video-shade"></div><span class="video-brand">JAS<span>PEST</span>.</span><div class="video-placeholder-copy"><span class="video-coming">SEGERA HADIR</span><strong>Cerita dari<br>lapangan.</strong><span>Video kegiatan JASPEST</span></div><span class="video-caption">Belum ada video yang dipublikasikan.</span></div><span class="video-outline" aria-hidden="true"></span></div></div></section>

    <section class="section articles-section" id="blog"><span id="artikel" class="anchor-alias" aria-hidden="true"></span><div class="container"><div class="section-heading"><div><h2>Informasi untuk<br>rumah dan bisnis Anda.</h2></div><p>Beberapa panduan sederhana untuk membantu Anda menyiapkan konsultasi bersama tim kami.</p></div>@include('jaspest.blog.latest', ['posts' => $blogPosts])</div></section>

    <section class="section faq-section" id="faq"><div class="container faq-layout"><div><h2>Masih ada<br>yang ingin ditanyakan?</h2><p>Temukan jawaban singkat di sini, atau gunakan asisten FAQ JASPEST di sudut halaman.</p><button class="text-link blue-text" data-open-chat>Tanya asisten FAQ <svg><use href="#i-chat"/></svg></button></div><div class="faq-list" id="faq-list"></div></div></section>

    <section class="contact-section section" id="konsultasi"><div class="container contact-layout"><div class="contact-copy"><h2>{{ $contactPage?->title ?: 'Mulai dari kebutuhan Anda' }}</h2><p>{{ $contactPage?->description ?: 'Isi pengajuan agar tim JASPEST dapat memahami kebutuhan dan menghubungi Anda.' }}</p><div class="contact-note"><svg><use href="#i-chat"/></svg><div><strong>Diskusikan langsung dengan tim.</strong><p>Rincian penanganan akan dijelaskan melalui WhatsApp atau kontak langsung.</p></div></div><a class="text-link company-whatsapp-link" data-company-whatsapp href="#konsultasi" target="_blank" rel="noopener" hidden>Chat JASPEST melalui WhatsApp <span aria-hidden="true">↗</span></a><div class="contact-privacy"><svg><use href="#i-lock"/></svg><span>Foto dan informasi pengajuan Anda digunakan untuk membantu konsultasi, bukan untuk galeri publik.</span></div><div class="contact-map" id="lokasi">
          <div class="contact-map-frame"><iframe src="{{ $officeInfo['mapEmbedUrl'] ?? ('https://maps.google.com/maps?q='.rawurlencode($officeAddress).'&z=16&output=embed') }}" title="Peta lokasi kantor CV Jaya Abadi Solusi — JASPEST di Sidoarjo" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>
          <div class="contact-map-info">
            <span class="form-kicker">LOKASI KANTOR</span>
            <div class="contact-map-row"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg><address>{{ $officeAddress }}</address></div>
            <div class="contact-map-row"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3H4a1 1 0 0 0-1 1c0 9.4 7.6 17 17 17a1 1 0 0 0 1-1v-3l-5-2-2 3c-3.2-1.3-5.7-3.8-7-7l3-2-2-5Z"/></svg><a href="{{ $officeInfo['phoneHref'] ?? 'tel:+623185582382' }}">{{ $officePhone }}</a></div>
            @if(filled($officeEmail))
            <div class="contact-map-row"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg><a href="{{ $officeEmailHref }}">{{ $officeEmail }}</a></div>
            @endif
            <a class="button button-red" href="{{ $officeInfo['mapLink'] ?? ('https://www.google.com/maps/search/?api=1&query='.rawurlencode($officeAddress)) }}" target="_blank" rel="noopener noreferrer"><span>Petunjuk arah</span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 3 14 21l-4-8-8-4 19-6Z"/><path d="m10 13 11-10"/></svg></a>
          </div>
        </div></div><div class="form-card"><div class="form-card-heading"><span class="form-kicker">FORMULIR PENGAJUAN</span><h3>Ceritakan kebutuhan Anda.</h3><p>Kolom bertanda <span class="required-mark">*</span> wajib diisi.</p></div><form id="enquiry-form" novalidate>
      <div class="form-error-summary" id="form-error-summary" role="alert" tabindex="-1" hidden></div>
      <div class="form-row"><div class="field"><label for="full-name">Nama lengkap <span>*</span></label><input id="full-name" name="full_name" autocomplete="name" maxlength="150" placeholder="Nama lengkap Anda" required><small class="field-error" id="error-full_name"></small></div><div class="field"><label for="whatsapp">Nomor WhatsApp aktif <span>*</span></label><input id="whatsapp" name="whatsapp" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" placeholder="08xx atau +62xx" required><small class="field-error" id="error-whatsapp"></small></div></div>
      <div class="field"><label for="address">Alamat lokasi <span>*</span></label><textarea id="address" name="address" autocomplete="street-address" rows="2" maxlength="1000" placeholder="Jalan, nomor, kelurahan, kecamatan, dan kota" required></textarea><small class="field-error" id="error-address"></small></div>
      <div class="form-row"><div class="field"><label for="area">Perkiraan luasan <span>*</span></label><div class="input-unit"><input id="area" name="estimated_area" type="number" min="0.1" step="any" inputmode="decimal" placeholder="Contoh: 100" required><span>m²</span></div><small class="field-hint">Cukup perkiraan luas lokasi.</small><small class="field-error" id="error-estimated_area"></small></div><div class="field"><label for="need">Kebutuhan layanan <span>*</span></label><select id="need" name="need" required><option value="">Pilih kebutuhan</option></select><small class="field-error" id="error-need"></small></div></div>
      <fieldset class="property-field"><legend>Jenis lokasi <span>*</span></legend><div class="property-options"><label><input type="radio" name="property_type" value="rumah" required><span><svg><use href="#i-house"/></svg>Rumah<svg class="radio-tick"><use href="#i-check"/></svg></span></label><label><input type="radio" name="property_type" value="bisnis" required><span><svg><use href="#i-building"/></svg>Bisnis<svg class="radio-tick"><use href="#i-check"/></svg></span></label></div><small class="field-error" id="error-property_type"></small></fieldset>
      <div class="field" id="company-field" hidden><label for="company">Nama perusahaan <span>*</span></label><input id="company" name="company_name" autocomplete="organization" maxlength="200" placeholder="Nama perusahaan Anda" disabled><small class="field-error" id="error-company_name"></small></div>
      <div class="field"><label for="description">Keterangan <span>*</span></label><textarea id="description" name="description" rows="3" maxlength="3000" placeholder="Ceritakan temuan, kondisi lokasi, atau kebutuhan Anda" required></textarea><small class="field-error" id="error-description"></small></div>
      <div class="field"><label for="photos">Foto temuan <span class="optional-label">Opsional</span></label><label class="upload-zone" id="upload-zone" for="photos"><svg><use href="#i-camera"/></svg><strong>Pilih atau letakkan foto di sini</strong><span>JPG, PNG, atau WebP · Maks. 5 foto, 5 MB per foto</span><input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple></label><div class="photo-list" id="photo-list"></div><small class="field-error" id="error-photos" aria-live="polite"></small></div>
      <div class="honeypot" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
      <label class="consent"><input type="checkbox" name="consent" id="consent" required><span>Saya bersedia untuk dihubungi oleh team JASPEST. <span class="required-mark">*</span></span></label><small class="field-error" id="error-consent"></small><p class="consent-note">Kontak mencakup WhatsApp atau telepon dan tindak lanjut terkait pengajuan. Baca <button type="button" class="inline-link" data-privacy>kebijakan privasi</button>.</p>
      <button class="button button-red form-submit" id="submit-enquiry" type="submit"><span>Tinjau pengajuan</span></button><p class="preview-form-note" id="preview-form-note"><svg><use href="#i-info"/></svg>Mode pratinjau: pengajuan belum diteruskan ke tim JASPEST.</p>
    </form></div></div></section>
  </main>

  <footer class="site-footer"><div class="container footer-main"><a class="brand brand-footer" href="#" aria-label="CV Jaya Abadi Solusi — beranda"><img class="brand-logo" src="{{ asset('jaspest/assets/logo-jas.png') }}" alt="Logo CV Jaya Abadi Solusi" width="458" height="368" decoding="async"></a><p>Perhatian untuk ruang Anda.<br>Solusi untuk kebutuhan pengendalian hama.</p><div class="footer-links"><a href="#layanan">Layanan</a><a href="#galeri">Galeri</a><a href="#blog">Blog</a><a href="#lokasi">Lokasi kantor</a><a href="#konsultasi">Hubungi kami</a></div></div>
    @if(($jaspestCertifications ?? collect())->isNotEmpty())
      <section class="container footer-certifications" aria-labelledby="footer-certifications-title">
        <div class="footer-certifications-heading">
          <span>PENGAKUAN &amp; KEANGGOTAAN</span>
          <h2 id="footer-certifications-title">Sertifikasi &amp; Keanggotaan</h2>
          <p>Komitmen terhadap standar dan profesionalisme di bidang pengendalian hama.</p>
        </div>
        <div class="footer-certifications-list">
          @foreach($jaspestCertifications as $certification)
            <article class="footer-certification-card">
              <div class="footer-certification-logo"><img src="{{ $certification['logo'] }}" alt="{{ $certification['image_alt'] }}" loading="lazy" decoding="async"></div>
              <div class="footer-certification-copy">
                <strong>{{ $certification['title'] }}</strong>
                @if(filled($certification['category']))<span>{{ $certification['category'] }}</span>@endif
                @if(filled($certification['description']))<p>{{ $certification['description'] }}</p>@endif
              </div>
            </article>
          @endforeach
        </div>
      </section>
    @endif
    <div class="container footer-bottom"><span>{{ ($jaspestDynamicContent['brand']['copyright'] ?? null) ?: '© '.now('Asia/Jakarta')->year.' JASPEST · CV Jaya Abadi Solusi' }}</span><button type="button" data-privacy>Kebijakan privasi</button><button type="button" data-cookie-settings>Pengaturan cookie</button><span class="asa-credit">Build Better. Automate Faster. Grow Smarter. with <a href="https://www.asaproduction.tech/" target="_blank" rel="noopener noreferrer">Asa Production</a></span></div></footer>

  <button class="chat-launcher" id="chat-launcher" aria-expanded="false" aria-controls="chat-panel"><span class="chat-launcher-icon"><svg><use href="#i-chat"/></svg></span><span>Tanya JASPEST</span><span class="chat-online-dot"></span></button>
  <section class="chat-panel" id="chat-panel" aria-label="Asisten FAQ JASPEST" hidden><div class="chat-header"><span class="chat-bot-icon"><svg><use href="#i-chat"/></svg></span><div><strong>Asisten FAQ JASPEST</strong><span>Informasi dari website JASPEST</span></div><button class="chat-close icon-button" id="chat-close" aria-label="Tutup asisten FAQ"><svg><use href="#i-close"/></svg></button></div><div class="chat-messages" id="chat-messages" role="log" aria-live="polite" aria-relevant="additions"></div><form class="chat-form" id="chat-form"><label class="sr-only" for="chat-input">Pertanyaan Anda</label><input id="chat-input" autocomplete="off" maxlength="500" placeholder="Tulis pertanyaan Anda…"><button type="submit" class="chat-send icon-button" aria-label="Kirim pertanyaan"><svg><use href="#i-send"/></svg></button></form><div class="chat-bottom-note">Jawaban berbasis FAQ, tanpa AI.</div></section>

  <dialog class="content-dialog" id="content-dialog" aria-labelledby="dialog-title"><button class="dialog-close icon-button" id="dialog-close" aria-label="Tutup jendela"><svg><use href="#i-close"/></svg></button><div id="dialog-content"></div></dialog>
  <div class="toast" id="toast" role="status" hidden></div>
</body>
</html>
