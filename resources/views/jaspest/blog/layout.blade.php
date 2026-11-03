<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#104dc1">
  @php
    $metaTitle = $pageTitle ?? 'Blog JASPEST — Informasi pengendalian hama untuk rumah dan bisnis';
    $metaDescription = $pageDescription ?? 'Panduan sederhana untuk membantu Anda menyiapkan konsultasi pengendalian hama bersama JASPEST.';
    $metaCanonical = $canonical ?? url()->current();
    $metaOgType = $ogType ?? 'website';
    $metaOgImage = $ogImage ?? asset('jaspest/assets/hero-pest-control.webp');
  @endphp
  <title>{{ $metaTitle }}</title>
  <meta name="description" content="{{ $metaDescription }}">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="{{ $metaCanonical }}">
  <meta property="og:site_name" content="JASPEST — Jaya Abadi Solusi">
  <meta property="og:locale" content="id_ID">
  <meta property="og:type" content="{{ $metaOgType }}">
  <meta property="og:title" content="{{ $metaTitle }}">
  <meta property="og:description" content="{{ $metaDescription }}">
  <meta property="og:url" content="{{ $metaCanonical }}">
  <meta property="og:image" content="{{ $metaOgImage }}">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('jaspest/assets/favicon-32.png') }}">
  <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
  <link rel="apple-touch-icon" href="{{ asset('jaspest/assets/apple-touch-icon.png') }}">
  <link rel="stylesheet" href="{{ asset('jaspest/styles.css') }}">
  <link rel="stylesheet" href="{{ asset('jaspest/blog.css') }}">
  <link rel="stylesheet" href="{{ asset('jaspest/animations.css') }}?v=9">
  @if(! empty($schema))
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
  @endif
  <script>window.JASPEST_CONFIG = { previewMode: false };</script>
  @include('jaspest.partials.cookie-consent')
  <script src="{{ asset('jaspest/blog.js') }}" defer></script>
</head>
<body>
  <a class="skip-link" href="#main">Langsung ke konten</a>
  @include('jaspest.partials.icons')
  @include('jaspest.partials.header')
  <main id="main" class="blog-page container">
    @yield('content')
  </main>
  @include('jaspest.partials.footer')
  @include('jaspest.partials.dialog')
</body>
</html>
